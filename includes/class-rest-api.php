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
	 * Constructor
	 *
	 * @param Database $database Database instance.
	 * @param Logger   $logger   Logger instance.
	 */
	public function __construct( Database $database, Logger $logger ) {
		$this->database = $database;
		$this->logger   = $logger;
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
		if ( ! preg_match( '/^\d{10,}$/', $value ) ) {
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
		$ip = $this->get_client_ip();
		if ( ! $this->check_rate_limit( $ip ) ) {
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

	/**
	 * Check rate limiting for IP address
	 *
	 * @param string $ip Client IP address.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	private function check_rate_limit( $ip ) {
		$transient_key = 'agent007_rate_limit_' . md5( $ip );
		$request_count = get_transient( $transient_key );

		if ( false === $request_count ) {
			$request_count = 0;
		}

		if ( $request_count >= $this->rate_limit ) {
			return false;
		}

		set_transient( $transient_key, $request_count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Get client IP address
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	private function get_client_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip  = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		} else {
			$ip = '0.0.0.0';
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}

		return '0.0.0.0';
	}
}
