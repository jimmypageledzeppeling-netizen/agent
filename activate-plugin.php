<?php
/**
 * Manual plugin activation script
 * Place in plugin root and run via browser: http://localhost:8080/wp-content/plugins/agent007/activate-plugin.php
 */

// Load WordPress
require_once dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Access denied. Admin access required.' );
}

// Deactivate the plugin
deactivate_plugins( 'agent007/agent007.php' );

// Clear any transients
delete_transient( 'plugin_activation_errors' );

// Activate the plugin
$result = activate_plugin( 'agent007/agent007.php' );

if ( is_wp_error( $result ) ) {
	echo '<pre>';
	echo 'Plugin activation failed: ' . esc_html( $result->get_error_message() ) . "\n";
	echo '</pre>';
} else {
	echo '<pre>';
	echo 'Plugin activated successfully!' . "\n";
	echo 'Check PROCESS.LOG for table creation confirmation' . "\n";
	echo '</pre>';
}

// Display table check
global $wpdb;
echo '<hr><h3>Database Check:</h3><pre>';

$targets_table  = $wpdb->prefix . 'agent007_targets';
$accounts_table = $wpdb->prefix . 'agent007_accounts';

// Check targets table
$targets_check = $wpdb->get_results( "SHOW TABLES LIKE '$targets_table'" );
if ( $targets_check ) {
	echo "✓ Table $targets_table EXISTS\n";
} else {
	echo "✗ Table $targets_table NOT FOUND\n";
}

// Check accounts table
$accounts_check = $wpdb->get_results( "SHOW TABLES LIKE '$accounts_table'" );
if ( $accounts_check ) {
	echo "✓ Table $accounts_table EXISTS\n";
} else {
	echo "✗ Table $accounts_table NOT FOUND\n";
}

echo '</pre>';
?>
