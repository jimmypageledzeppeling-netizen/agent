<?php
/**
 * Rate limiter shared by plugin endpoints
 *
 * @package Agent007
 */

namespace Agent007;

/**
 * Rate limiter class
 *
 * @since 1.1.0
 */
class RateLimiter {

	/**
	 * Check and count a request from an IP within a bucket
	 *
	 * @param string $bucket Endpoint name, each endpoint has its own counter.
	 * @param string $ip     Client IP address.
	 * @param int    $limit  Max requests per minute.
	 *
	 * @return bool True if the request is allowed.
	 *
	 * @since 1.1.0
	 */
	public function check( $bucket, $ip, $limit ) {
		$transient_key = 'agent007_rl_' . md5( $bucket . '|' . $ip );
		$request_count = get_transient( $transient_key );

		if ( false === $request_count ) {
			$request_count = 0;
		}

		if ( $request_count >= $limit ) {
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
	 * @since 1.1.0
	 */
	public function get_client_ip() {
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
