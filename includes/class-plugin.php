<?php
/**
 * The main plugin class
 *
 * @package Agent007
 */

namespace Agent007;

/**
 * Main plugin class
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Instance of Database class
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * Instance of Logger class
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Instance of RestAPI class
	 *
	 * @var RestAPI
	 */
	private $rest_api;

	/**
	 * Instance of Admin class
	 *
	 * @var Admin
	 */
	private $admin;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Require necessary classes.
		require_once AGENT007_PLUGIN_DIR . 'includes/class-logger.php';
		require_once AGENT007_PLUGIN_DIR . 'includes/class-database.php';
		require_once AGENT007_PLUGIN_DIR . 'includes/class-rest-api.php';
		require_once AGENT007_PLUGIN_DIR . 'admin/class-admin.php';

		// Initialize classes.
		$this->logger   = new Logger();
		$this->database = new Database( $this->logger );
		$this->rest_api = new RestAPI( $this->database, $this->logger );
		$this->admin    = new Admin( $this->logger );
	}

	/**
	 * Run the plugin
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// Register activation hook.
		register_activation_hook( AGENT007_PLUGIN_FILE, array( $this, 'activate' ) );

		// Register deactivation hook.
		register_deactivation_hook( AGENT007_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// Initialize admin.
		add_action( 'admin_menu', array( $this->admin, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_scripts' ) );

		// Initialize REST API.
		add_action( 'rest_api_init', array( $this->rest_api, 'register_routes' ) );
	}

	/**
	 * Plugin activation hook
	 *
	 * @since 1.0.0
	 */
	public function activate() {
		$this->database->create_tables();
		$this->logger->log( 'Plugin activated successfully' );
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
		$this->logger->log( 'Plugin deactivated' );
		flush_rewrite_rules();
	}
}
