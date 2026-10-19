<?php
/**
 * Plugin Name: Agent007
 * Plugin URI: https://github.com/jimmypageledzeppeling-netizen/agent
 * Description: Управление аккаунтами ТГ через уникальные коды
 * Version: 1.1.0
 * Author: Development Team
 * Author URI: https://github.com/jimmypageledzeppeling-netizen
 * License: GPL v2 or later
 * Text Domain: agent007
 * Domain Path: /languages
 *
 * @package Agent007
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants.
define( 'AGENT007_VERSION', '1.1.0' );
define( 'AGENT007_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGENT007_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AGENT007_PLUGIN_FILE', __FILE__ );

// Require the main plugin class.
require_once AGENT007_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function agent007_run() {
	$plugin = new \Agent007\Plugin();
	$plugin->run();
}

// Start the plugin.
agent007_run();
