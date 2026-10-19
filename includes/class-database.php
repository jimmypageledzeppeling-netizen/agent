<?php
/**
 * Database class for managing plugin tables
 *
 * @package Agent007
 */

namespace Agent007;

/**
 * Database class
 *
 * @since 1.0.0
 */
class Database {

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Create plugin tables
	 *
	 * @since 1.0.0
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Create targets table.
		$targets_table = $wpdb->prefix . 'agent007_targets';
		$targets_sql   = "CREATE TABLE IF NOT EXISTS $targets_table (
			ID BIGINT(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			phone VARCHAR(20) NOT NULL UNIQUE,
			key VARCHAR(10) NOT NULL,
			status TINYINT(1) DEFAULT 0,
			access_path VARCHAR(255),
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_phone (phone),
			INDEX idx_status (status)
		) $charset_collate;";

		// Create accounts table.
		$accounts_table = $wpdb->prefix . 'agent007_accounts';
		$accounts_sql   = "CREATE TABLE IF NOT EXISTS $accounts_table (
			ID BIGINT(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			phone VARCHAR(20) NOT NULL,
			status TINYINT(1) DEFAULT 0,
			target_id BIGINT(20),
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_phone (phone),
			INDEX idx_status (status),
			INDEX idx_target_id (target_id),
			FOREIGN KEY (target_id) REFERENCES $targets_table (ID) ON DELETE CASCADE
		) $charset_collate;";

		// Execute table creation.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $targets_sql );
		dbDelta( $accounts_sql );

		$this->logger->log( 'Database tables created successfully' );
	}

	/**
	 * Drop plugin tables
	 *
	 * @since 1.0.0
	 */
	public function drop_tables() {
		global $wpdb;

		$targets_table  = $wpdb->prefix . 'agent007_targets';
		$accounts_table = $wpdb->prefix . 'agent007_accounts';

		$wpdb->query( "DROP TABLE IF EXISTS $accounts_table" ); // Drop dependent table first.
		$wpdb->query( "DROP TABLE IF EXISTS $targets_table" );

		$this->logger->log( 'Database tables dropped' );
	}

	/**
	 * Insert target record
	 *
	 * @param string $phone Phone number.
	 * @param string $key   Generated key.
	 *
	 * @return int|false Target ID or false on failure
	 *
	 * @since 1.0.0
	 */
	public function insert_target( $phone, $key ) {
		global $wpdb;

		$targets_table = $wpdb->prefix . 'agent007_targets';
		$result        = $wpdb->insert(
			$targets_table,
			array(
				'phone'      => $phone,
				'key'        => $key,
				'status'     => 0,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);

		if ( $result ) {
			$this->logger->log( sprintf( 'Target created - Phone: %s, Key: %s', $phone, $key ) );
			return $wpdb->insert_id;
		} else {
			$this->logger->log( sprintf( 'Failed to create target - Phone: %s, Error: %s', $phone, $wpdb->last_error ), 'error' );
			return false;
		}
	}

	/**
	 * Get target by phone
	 *
	 * @param string $phone Phone number.
	 *
	 * @return object|null Target object or null if not found
	 *
	 * @since 1.0.0
	 */
	public function get_target_by_phone( $phone ) {
		global $wpdb;

		$targets_table = $wpdb->prefix . 'agent007_targets';
		$query         = $wpdb->prepare(
			"SELECT * FROM $targets_table WHERE phone = %s",
			$phone
		);

		return $wpdb->get_row( $query );
	}

	/**
	 * Update target status
	 *
	 * @param int $target_id Target ID.
	 * @param int $status    New status.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function update_target_status( $target_id, $status ) {
		global $wpdb;

		$targets_table = $wpdb->prefix . 'agent007_targets';
		$result        = $wpdb->update(
			$targets_table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'ID' => $target_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			$this->logger->log( sprintf( 'Target status updated - ID: %d, Status: %d', $target_id, $status ) );
			return true;
		} else {
			$this->logger->log( sprintf( 'Failed to update target status - ID: %d, Error: %s', $target_id, $wpdb->last_error ), 'error' );
			return false;
		}
	}

	/**
	 * Insert account record
	 *
	 * @param string $phone    Phone number.
	 * @param int    $target_id Target ID.
	 *
	 * @return int|false Account ID or false on failure
	 *
	 * @since 1.0.0
	 */
	public function insert_account( $phone, $target_id ) {
		global $wpdb;

		$accounts_table = $wpdb->prefix . 'agent007_accounts';
		$result         = $wpdb->insert(
			$accounts_table,
			array(
				'phone'      => $phone,
				'status'     => 0,
				'target_id'  => $target_id,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%s', '%s' )
		);

		if ( $result ) {
			$this->logger->log( sprintf( 'Account created - Phone: %s, Target ID: %d', $phone, $target_id ) );
			return $wpdb->insert_id;
		} else {
			$this->logger->log( sprintf( 'Failed to create account - Phone: %s, Error: %s', $phone, $wpdb->last_error ), 'error' );
			return false;
		}
	}
}
