<?php
/**
 * Logger class for logging plugin operations
 *
 * @package Agent007
 */

namespace Agent007;

/**
 * Logger class
 *
 * @since 1.0.0
 */
class Logger {

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * Whether logging is enabled
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->log_file = WP_CONTENT_DIR . '/../PROCESS.LOG';
		$this->enabled  = get_option( 'agent007_logging_enabled', true );
	}

	/**
	 * Log a message
	 *
	 * @param string $message The message to log.
	 * @param string $level   The log level (info, warning, error).
	 *
	 * @since 1.0.0
	 */
	public function log( $message, $level = 'info' ) {
		if ( ! $this->enabled ) {
			return;
		}

		$timestamp = wp_date( 'Y-m-d H:i:s' );
		$log_entry = sprintf( '[%s] [%s] %s', $timestamp, strtoupper( $level ), $message );

		// Get current IP address.
		$ip = $this->get_client_ip();
		if ( $ip ) {
			$log_entry .= ' [IP: ' . $ip . ']';
		}

		$log_entry .= "\n";

		// Write to log file.
		file_put_contents( $this->log_file, $log_entry, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Get client IP address
	 *
	 * @return string|null
	 */
	private function get_client_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Handle multiple IPs, take the first one.
			$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip  = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		} else {
			$ip = null;
		}

		// Validate IP.
		if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}

		return null;
	}

	/**
	 * Get log file contents
	 *
	 * @param int $lines Number of lines to retrieve.
	 *
	 * @return string Log file contents
	 *
	 * @since 1.0.0
	 */
	public function get_log_contents( $lines = 100 ) {
		if ( ! file_exists( $this->log_file ) ) {
			return 'Log file does not exist yet.';
		}

		$file_contents = file_get_contents( $this->log_file );
		if ( ! $file_contents ) {
			return 'Log file is empty.';
		}

		$file_lines = explode( "\n", trim( $file_contents ) );
		$last_lines = array_slice( $file_lines, -$lines );

		return implode( "\n", $last_lines );
	}

	/**
	 * Clear log file
	 *
	 * @since 1.0.0
	 */
	public function clear_log() {
		if ( file_exists( $this->log_file ) ) {
			file_put_contents( $this->log_file, '' );
		}
		$this->log( 'Log file cleared by user' );
	}

	/**
	 * Is logging enabled
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Set logging enabled status
	 *
	 * @param bool $enabled Whether logging should be enabled.
	 *
	 * @since 1.0.0
	 */
	public function set_enabled( $enabled ) {
		$this->enabled = (bool) $enabled;
		update_option( 'agent007_logging_enabled', $this->enabled );
		$status = $this->enabled ? 'enabled' : 'disabled';
		$this->log( sprintf( 'Logging %s', $status ) );
	}
}
