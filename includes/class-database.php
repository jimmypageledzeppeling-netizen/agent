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
		$targets_sql   = "CREATE TABLE IF NOT EXISTS `$targets_table` (
			`ID` BIGINT(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`phone` VARCHAR(20) NOT NULL,
			`key` VARCHAR(10) NOT NULL COLLATE utf8mb4_bin,
			`status` TINYINT(1) DEFAULT 0,
			`access_path` VARCHAR(255),
			`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX `idx_phone` (`phone`),
			INDEX `idx_key` (`key`),
			INDEX `idx_status` (`status`)
		) $charset_collate;";

		// Create accounts table.
		$accounts_table = $wpdb->prefix . 'agent007_accounts';
		$accounts_sql   = "CREATE TABLE IF NOT EXISTS `$accounts_table` (
			`ID` BIGINT(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`phone` VARCHAR(20) NOT NULL,
			`status` TINYINT(1) DEFAULT 0,
			`target_id` BIGINT(20),
			`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX `idx_phone` (`phone`),
			INDEX `idx_status` (`status`),
			INDEX `idx_target_id` (`target_id`),
			CONSTRAINT `fk_agent007_account_target` FOREIGN KEY (`target_id`) REFERENCES `$targets_table` (`ID`) ON DELETE CASCADE
		) $charset_collate;";

		// Execute table creation using direct queries for better reliability.
		$wpdb->query( $targets_sql );
		$wpdb->query( $accounts_sql );

		if ( ! empty( $wpdb->last_error ) ) {
			$this->logger->log( 'Database table creation error: ' . $wpdb->last_error, 'error' );
		} else {
			$this->logger->log( 'Database tables created successfully' );
		}
	}

	/**
	 * Upgrade schema of existing installations
	 *
	 * Removes the UNIQUE constraint on targets.phone, so the same number
	 * can be registered again and receives a new key.
	 *
	 * @since 1.0.1
	 */
	public function maybe_upgrade() {
		global $wpdb;

		if ( AGENT007_VERSION === get_option( 'agent007_db_version' ) ) {
			return;
		}

		$targets_table = $wpdb->prefix . 'agent007_targets';
		$unique_keys   = $wpdb->get_results( "SHOW INDEX FROM `$targets_table` WHERE Column_name = 'phone' AND Non_unique = 0" );

		foreach ( (array) $unique_keys as $index ) {
			$wpdb->query( "ALTER TABLE `$targets_table` DROP INDEX `{$index->Key_name}`" );
		}

		// Keys are case-sensitive, so the lookup must not use a case-insensitive collation.
		$wpdb->query( "ALTER TABLE `$targets_table` MODIFY `key` VARCHAR(10) NOT NULL COLLATE utf8mb4_bin" );

		$key_index = $wpdb->get_results( "SHOW INDEX FROM `$targets_table` WHERE Key_name = 'idx_key'" );
		if ( empty( $key_index ) ) {
			$wpdb->query( "ALTER TABLE `$targets_table` ADD INDEX `idx_key` (`key`)" );
		}

		if ( ! empty( $wpdb->last_error ) ) {
			$this->logger->log( 'Database upgrade error: ' . $wpdb->last_error, 'error' );
			return;
		}

		update_option( 'agent007_db_version', AGENT007_VERSION );
		$this->logger->log( 'Database schema upgraded to ' . AGENT007_VERSION );
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
	 * Get target by key (case-sensitive)
	 *
	 * @param string $key Key value.
	 *
	 * @return object|null|false Target row, null if not found, false on database error.
	 *
	 * @since 1.1.0
	 */
	public function get_target_by_key( $key ) {
		global $wpdb;

		$targets_table = $wpdb->prefix . 'agent007_targets';
		$row           = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, phone FROM `$targets_table` WHERE `key` = %s ORDER BY ID DESC LIMIT 1",
				$key
			)
		);

		if ( ! empty( $wpdb->last_error ) ) {
			$this->logger->log( 'Failed to look up key: ' . $wpdb->last_error, 'error' );
			return false;
		}

		return $row;
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
