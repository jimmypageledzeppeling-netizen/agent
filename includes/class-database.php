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
	 * Target statuses: 0 = waiting, 1 = open, 2 = done
	 */
	const STATUSES = array(
		0 => 'Ожидание',
		1 => 'Открыто',
		2 => 'Завершено',
	);

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

		// Create access log table (one row per successful key lookup).
		$access_table = $wpdb->prefix . 'agent007_access_log';
		$access_sql   = "CREATE TABLE IF NOT EXISTS `$access_table` (
			`ID` BIGINT(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`target_id` BIGINT(20) NOT NULL,
			`action` VARCHAR(20) NOT NULL DEFAULT 'view',
			`device` VARCHAR(255) DEFAULT NULL,
			`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
			INDEX `idx_target_id` (`target_id`),
			CONSTRAINT `fk_agent007_access_target` FOREIGN KEY (`target_id`) REFERENCES `$targets_table` (`ID`) ON DELETE CASCADE
		) $charset_collate;";

		// Execute table creation using direct queries for better reliability.
		$wpdb->query( $targets_sql );
		$wpdb->query( $accounts_sql );
		$wpdb->query( $access_sql );

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

		// Creates tables that are missing (e.g. access_log on 1.1.0 installs); existing ones are untouched.
		$this->create_tables();

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

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}agent007_access_log" ); // Drop dependent tables first.
		$wpdb->query( "DROP TABLE IF EXISTS $accounts_table" );
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
	 * Insert access log record
	 *
	 * @param int    $target_id Target ID.
	 * @param string $device    Device label.
	 * @param string $action    Action name.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	public function insert_access_log( $target_id, $device, $action = 'view' ) {
		global $wpdb;

		$access_table = $wpdb->prefix . 'agent007_access_log';
		$result       = $wpdb->insert(
			$access_table,
			array(
				'target_id'  => (int) $target_id,
				'action'     => $action,
				'device'     => $device,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Get targets for the admin list
	 *
	 * @param array $args Keys: status (int|null), search, orderby, order, per_page, page.
	 *
	 * @return array Keys: items (rows with views count), total (int).
	 *
	 * @since 1.2.0
	 */
	public function get_targets( array $args ) {
		global $wpdb;

		$targets_table = $wpdb->prefix . 'agent007_targets';
		$access_table  = $wpdb->prefix . 'agent007_access_log';

		// Whitelist: ORDER BY cannot be parameterized, so only known expressions reach the query.
		$allowed_orderby = array(
			'ID'         => 't.ID',
			'phone'      => 't.phone',
			'status'     => 't.status',
			'created_at' => 't.created_at',
			'views'      => 'views',
		);
		$orderby         = isset( $args['orderby'], $allowed_orderby[ $args['orderby'] ] ) ? $allowed_orderby[ $args['orderby'] ] : 't.ID';
		$order           = isset( $args['order'] ) && 'asc' === strtolower( $args['order'] ) ? 'ASC' : 'DESC';

		$where  = array( '1=1' );
		$params = array();

		if ( isset( $args['status'] ) && is_numeric( $args['status'] ) ) {
			$where[]  = 't.status = %d';
			$params[] = (int) $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(t.phone LIKE %s OR t.`key` LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page      = max( 1, (int) ( $args['page'] ?? 1 ) );

		$sql   = "SELECT t.*, (SELECT COUNT(*) FROM `$access_table` al WHERE al.target_id = t.ID) AS views
			FROM `$targets_table` t
			WHERE $where_sql
			ORDER BY $orderby $order, t.ID $order
			LIMIT %d OFFSET %d";
		$items = $wpdb->get_results( $wpdb->prepare( $sql, ...array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ) );

		$count_sql = "SELECT COUNT(*) FROM `$targets_table` t WHERE $where_sql";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) : $wpdb->get_var( $count_sql ) );

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Get target by ID
	 *
	 * @param int $id Target ID.
	 *
	 * @return object|null
	 *
	 * @since 1.2.0
	 */
	public function get_target( $id ) {
		global $wpdb;

		$targets_table = $wpdb->prefix . 'agent007_targets';

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$targets_table` WHERE ID = %d", (int) $id ) );
	}

	/**
	 * Update target fields
	 *
	 * @param int   $id   Target ID.
	 * @param array $data Keys: phone, key, status, access_path.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	public function update_target( $id, array $data ) {
		global $wpdb;

		$targets_table = $wpdb->prefix . 'agent007_targets';
		$allowed       = array(
			'phone'       => '%s',
			'key'         => '%s',
			'status'      => '%d',
			'access_path' => '%s',
		);

		// Formats must follow the same order as the fields.
		$fields  = array_intersect_key( $data, $allowed );
		$formats = array();
		foreach ( array_keys( $fields ) as $field ) {
			$formats[] = $allowed[ $field ];
		}

		$fields['updated_at'] = current_time( 'mysql' );
		$formats[]            = '%s';

		$result = $wpdb->update( $targets_table, $fields, array( 'ID' => (int) $id ), $formats, array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Check whether a key is used by another target
	 *
	 * @param string $key Key value.
	 * @param int    $id  Target ID to exclude.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	public function key_exists_elsewhere( $key, $id ) {
		global $wpdb;

		$targets_table = $wpdb->prefix . 'agent007_targets';
		$count         = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `$targets_table` WHERE `key` = %s AND ID <> %d", $key, (int) $id )
		);

		return (int) $count > 0;
	}

	/**
	 * Delete targets; accounts and access log rows are removed by ON DELETE CASCADE
	 *
	 * @param array $ids Target IDs.
	 *
	 * @return int Number of deleted targets.
	 *
	 * @since 1.2.0
	 */
	public function delete_targets( array $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$targets_table = $wpdb->prefix . 'agent007_targets';
		$placeholders  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$wpdb->query( $wpdb->prepare( "DELETE FROM `$targets_table` WHERE ID IN ($placeholders)", ...array_values( $ids ) ) );

		return (int) $wpdb->rows_affected;
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
