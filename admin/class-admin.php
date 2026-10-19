<?php
/**
 * Admin class for plugin administration
 *
 * @package Agent007
 */

namespace Agent007;

/**
 * Admin class
 *
 * @since 1.0.0
 */
class Admin {

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
	 * Add admin menu
	 *
	 * @since 1.0.0
	 */
	public function add_menu() {
		// Main menu item.
		add_menu_page(
			'Agent007',
			'Agent007',
			'manage_options',
			'agent007',
			array( $this, 'render_dashboard' ),
			'dashicons-admin-generic',
			70
		);

		// Logging submenu.
		add_submenu_page(
			'agent007',
			'Логирование',
			'Логирование',
			'manage_options',
			'agent007-logging',
			array( $this, 'render_logging_page' )
		);

		// Test submenu.
		add_submenu_page(
			'agent007',
			'Тест',
			'Тест',
			'manage_options',
			'agent007-test',
			array( $this, 'render_test_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		if ( isset( $_GET['page'] ) && ( 'agent007' === $_GET['page'] || 'agent007-logging' === $_GET['page'] ) ) {
			wp_enqueue_style(
				'agent007-admin-css',
				AGENT007_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				AGENT007_VERSION
			);

			wp_enqueue_script(
				'agent007-admin-js',
				AGENT007_PLUGIN_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				AGENT007_VERSION,
				true
			);

			wp_localize_script(
				'agent007-admin-js',
				'agent007_data',
				array(
					'nonce' => wp_create_nonce( 'agent007_nonce' ),
					'ajax_url' => admin_url( 'admin-ajax.php' ),
				)
			);
		}
	}

	/**
	 * Render admin dashboard page
	 *
	 * @since 1.0.0
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to access this page.' );
		}

		include AGENT007_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render logging page
	 *
	 * @since 1.0.0
	 */
	public function render_logging_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to access this page.' );
		}

		// Handle form submissions.
		if ( isset( $_POST['action'] ) ) {
			check_admin_referer( 'agent007_logging_nonce', 'agent007_nonce' );

			if ( 'toggle_logging' === $_POST['action'] ) {
				$enabled = isset( $_POST['logging_enabled'] ) ? (bool) $_POST['logging_enabled'] : false;
				$this->logger->set_enabled( $enabled );
				$_SESSION['message'] = 'Logging status updated.';
			} elseif ( 'clear_log' === $_POST['action'] ) {
				$this->logger->clear_log();
				$_SESSION['message'] = 'Log file cleared.';
			} elseif ( 'download_log' === $_POST['action'] ) {
				$this->download_log();
			}
		}

		// Handle AJAX requests.
		if ( isset( $_GET['action'] ) && 'agent007_toggle_logging' === $_GET['action'] ) {
			check_admin_referer( 'agent007_nonce' );
			$enabled = isset( $_GET['enabled'] ) ? (bool) $_GET['enabled'] : false;
			$this->logger->set_enabled( $enabled );
			wp_safe_remote_post( admin_url( 'admin-ajax.php' ) );
		}

		include AGENT007_PLUGIN_DIR . 'admin/views/logging.php';
	}

	/**
	 * Render test page: sends a phone through the REST endpoint and shows the response
	 *
	 * @since 1.0.1
	 */
	public function render_test_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to access this page.' );
		}

		$phone  = '';
		$result = null;

		if ( isset( $_POST['phone'] ) ) {
			check_admin_referer( 'agent007_test_nonce', 'agent007_nonce' );

			$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ) );

			// Dispatch the same REST route internally, so validation, rate limit and logging apply.
			$request = new \WP_REST_Request( 'POST', '/agent007/v1/reg' );
			$request->set_body_params( array( 'phone' => $phone ) );
			$response = rest_do_request( $request );

			$result = array(
				'status' => $response->get_status(),
				'body'   => $response->get_data(),
			);
		}

		include AGENT007_PLUGIN_DIR . 'admin/views/test.php';
	}

	/**
	 * Download log file
	 *
	 * @since 1.0.0
	 */
	private function download_log() {
		$log_file = WP_CONTENT_DIR . '/../PROCESS.LOG';

		if ( ! file_exists( $log_file ) ) {
			wp_die( 'Log file not found.' );
		}

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="PROCESS.LOG"' );
		header( 'Content-Length: ' . filesize( $log_file ) );
		readfile( $log_file );
		exit;
	}
}
