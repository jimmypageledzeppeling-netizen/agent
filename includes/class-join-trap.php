<?php
/**
 * Join trap: resolves a key from /join/{key} to a phone number
 *
 * @package Agent007
 */

namespace Agent007;

/**
 * Join trap class
 *
 * @since 1.1.0
 */
class JoinTrap {

	const QUERY_VAR   = 'agent007_join';
	const RATE_LIMIT  = 30;
	const REWRITE_OPT = 'agent007_rewrite_version';

	/**
	 * Database instance
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Rate limiter instance
	 *
	 * @var RateLimiter
	 */
	private $rate_limiter;

	/**
	 * Device detector instance
	 *
	 * @var DeviceDetector
	 */
	private $device_detector;

	/**
	 * Constructor
	 *
	 * @param Database            $database        Database instance.
	 * @param Logger              $logger          Logger instance.
	 * @param RateLimiter         $rate_limiter    Rate limiter instance.
	 * @param DeviceDetector|null $device_detector Device detector instance.
	 */
	public function __construct( Database $database, Logger $logger, RateLimiter $rate_limiter, $device_detector = null ) {
		$this->database        = $database;
		$this->logger          = $logger;
		$this->rate_limiter    = $rate_limiter;
		$this->device_detector = $device_detector ? $device_detector : new DeviceDetector();
	}

	/**
	 * Register hooks
	 *
	 * @since 1.1.0
	 */
	public function register() {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'handle_request' ) );
	}

	/**
	 * Add rewrite rule for /join/{key}
	 *
	 * @since 1.1.0
	 */
	public function add_rewrite_rule() {
		add_rewrite_rule( '^join/([A-Za-z]{10})/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Flush rewrite rules once per plugin version
	 *
	 * @since 1.1.0
	 */
	public function maybe_flush_rewrite_rules() {
		if ( AGENT007_VERSION === get_option( self::REWRITE_OPT ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPT, AGENT007_VERSION );
	}

	/**
	 * Register query var
	 *
	 * @param array $vars Public query vars.
	 *
	 * @return array
	 *
	 * @since 1.1.0
	 */
	public function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Handle a join request
	 *
	 * @since 1.1.0
	 */
	public function handle_request() {
		$key = get_query_var( self::QUERY_VAR );

		if ( '' === $key ) {
			return;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );

		$ip = $this->rate_limiter->get_client_ip();

		if ( ! Validator::is_key( $key ) ) {
			$result = $this->build_result( 400, 'Некорректный ключ' );
		} elseif ( ! $this->rate_limiter->check( 'join', $ip, self::RATE_LIMIT ) ) {
			$result = $this->build_result( 429, 'Слишком много запросов' );
		} else {
			$result = $this->resolve( $key );
		}

		$this->logger->log(
			sprintf( 'Join request - Key: %s, IP: %s, Status: %d', substr( $key, 0, 64 ), $ip, $result['status'] ),
			$result['status'] >= 500 ? 'error' : 'info'
		);

		if ( 200 === $result['status'] ) {
			$this->record_access( $result['id'] );
		}

		if ( $this->wants_json() ) {
			$this->send_json( $result );
		}

		$this->send_html( $result );
	}

	/**
	 * Find phone by key
	 *
	 * @param string $key Key from the URL.
	 *
	 * @return array Keys: status, response, id.
	 *
	 * @since 1.1.0
	 */
	public function resolve( $key ) {
		$target = $this->database->get_target_by_key( $key );

		if ( false === $target ) {
			return $this->build_result( 503, 'Ошибка базы данных' );
		}

		if ( null === $target ) {
			return $this->build_result( 404, 'Не найден' );
		}

		return array(
			'status'   => 200,
			'response' => $target->phone,
			'id'       => (int) $target->ID,
		);
	}

	/**
	 * Record that a target's phone was viewed by key
	 *
	 * A failed write is logged but never breaks the response to the client.
	 *
	 * @param int $target_id Target ID.
	 *
	 * @since 1.2.0
	 */
	private function record_access( $target_id ) {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		$device     = $this->device_detector->detect( $user_agent );

		if ( ! $this->database->insert_access_log( $target_id, $device ) ) {
			$this->logger->log( sprintf( 'Failed to write access log - Target ID: %d', $target_id ), 'error' );
		}
	}

	/**
	 * Build result array
	 *
	 * @param int    $status   HTTP status.
	 * @param string $response Response text.
	 *
	 * @return array
	 *
	 * @since 1.1.0
	 */
	private function build_result( $status, $response ) {
		return array(
			'status'   => $status,
			'response' => $response,
			'id'       => null,
		);
	}

	/**
	 * Decide whether the client wants JSON
	 *
	 * @return bool
	 *
	 * @since 1.1.0
	 */
	private function wants_json() {
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return true;
		}

		if ( false !== strpos( $_SERVER['HTTP_ACCEPT'] ?? '', 'application/json' ) ) {
			return true;
		}

		return isset( $_GET['format'] ) && 'json' === sanitize_text_field( wp_unslash( $_GET['format'] ) );
	}

	/**
	 * Send JSON response and stop
	 *
	 * @param array $result Result array.
	 *
	 * @since 1.1.0
	 */
	private function send_json( array $result ) {
		status_header( $result['status'] );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode(
			array(
				'status'   => $result['status'],
				'response' => $result['response'],
				'id'       => $result['id'],
			)
		);
		exit;
	}

	/**
	 * Send minimal HTML page and stop
	 *
	 * @param array $result Result array.
	 *
	 * @since 1.1.0
	 */
	private function send_html( array $result ) {
		status_header( $result['status'] );
		header( 'Content-Type: text/html; charset=utf-8' );

		if ( '0' === get_option( 'agent007_join_visible', '1' ) ) {
			exit;
		}

		$text = 200 === $result['status'] ? 'Номер: ' . $result['response'] : $result['response'];
		?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="utf-8">
	<meta name="robots" content="noindex, nofollow">
	<title>Agent007</title>
</head>
<body>
	<p id="agent007-join-result"><?php echo esc_html( $text ); ?></p>
</body>
</html>
		<?php
		exit;
	}
}
