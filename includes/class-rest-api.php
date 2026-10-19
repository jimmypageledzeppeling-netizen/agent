<?php
/**
 * REST API class for handling plugin endpoints
 *
 * @package Agent007
 */

namespace Agent007;

/**
 * REST API class
 *
 * @since 1.0.0
 */
class RestAPI {

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
	 * Rate limit requests per minute
	 *
	 * @var int
	 */
	private $rate_limit = 10;

	/**
	 * Rate limiter instance
	 *
	 * @var RateLimiter
	 */
	private $rate_limiter;

	/**
	 * Constructor
	 *
	 * @param Database         $database     Database instance.
	 * @param Logger           $logger       Logger instance.
	 * @param RateLimiter|null $rate_limiter Rate limiter instance.
	 */
	public function __construct( Database $database, Logger $logger, $rate_limiter = null ) {
		$this->database     = $database;
		$this->logger       = $logger;
		$this->rate_limiter = $rate_limiter ? $rate_limiter : new RateLimiter();
	}

	/**
	 * Register REST API routes
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			'agent007/v1',
			'/reg',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'register_phone' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'phone' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_phone' ),
					),
				),
			)
		);
	}

	/**
	 * Validate phone number
	 *
	 * @param mixed  $value Phone value to validate.
	 * @param object $request Request object.
	 * @param string $param Parameter name.
	 *
	 * @return bool|WP_Error
	 *
	 * @since 1.0.0
	 */
	public function validate_phone( $value, $request, $param ) {
		// Phone should contain only digits and be at least 10 characters.
		if ( ! Validator::is_phone( $value ) ) {
			return new \WP_Error(
				'invalid_phone',
				'Phone number must contain only digits and be at least 10 characters long.',
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Register phone endpoint callback
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 *
	 * @since 1.0.0
	 */
	public function register_phone( $request ) {
		// Check rate limiting.
		$ip = $this->rate_limiter->get_client_ip();
		if ( ! $this->rate_limiter->check( 'reg', $ip, $this->rate_limit ) ) {
			$this->logger->log( sprintf( 'Rate limit exceeded for IP: %s', $ip ), 'warning' );
			return new \WP_Error(
				'rate_limit_exceeded',
				'Too many requests. Please try again later.',
				array( 'status' => 429 )
			);
		}

		$phone = $request->get_param( 'phone' );

		// Generate unique 10-character key.
		$key = $this->generate_key();

		// Insert into targets table.
		$target_id = $this->database->insert_target( $phone, $key );

		if ( $target_id ) {
			$this->logger->log( sprintf( 'Phone registration successful - Phone: %s, Key: %s, IP: %s', $phone, $key, $ip ) );

			return rest_ensure_response(
				array(
					'success' => true,
					'key'     => $key,
					'message' => 'Phone registered successfully',
				)
			);
		} else {
			$this->logger->log( sprintf( 'Phone registration failed - Phone: %s, IP: %s', $phone, $ip ), 'error' );

			return new \WP_Error(
				'registration_failed',
				'Failed to register phone number. Please try again.',
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Generate random 10-character key (mixed case letters only)
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	private function generate_key() {
		$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$key        = '';

		for ( $i = 0; $i < 10; $i++ ) {
			$key .= $characters[ random_int( 0, strlen( $characters ) - 1 ) ];
		}

		return $key;
	}

}
