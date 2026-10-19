<?php
/**
 * Admin page: list, edit and delete targets
 *
 * @package Agent007
 */

namespace Agent007;

/**
 * Targets admin page
 *
 * @since 1.2.0
 */
class TargetsPage {

	const SLUG       = 'agent007-targets';
	const CAPABILITY = 'manage_options';

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
	 * Register hooks
	 *
	 * @since 1.2.0
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_agent007_update_target', array( $this, 'handle_update' ) );
	}

	/**
	 * Add submenu item
	 *
	 * @since 1.2.0
	 */
	public function add_menu() {
		add_submenu_page(
			'agent007',
			'Цели',
			'Цели',
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render list, edit form, and process list actions
	 *
	 * @since 1.2.0
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'You do not have permission to access this page.' );
		}

		$action = isset( $_GET['agent007_action'] ) ? sanitize_key( wp_unslash( $_GET['agent007_action'] ) ) : '';

		if ( 'edit' === $action ) {
			$this->render_edit();
			return;
		}

		if ( 'delete' === $action ) {
			$this->handle_single_delete();
		}

		if ( 'delete' === $this->current_bulk_action() ) {
			$this->handle_bulk_delete();
		}

		$this->render_list();
	}

	/**
	 * Render the list page
	 *
	 * @since 1.2.0
	 */
	private function render_list() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once AGENT007_PLUGIN_DIR . 'admin/class-targets-table.php';

		$table = new Targets_List_Table( $this->database );
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		$this->render_notice();
		$table->views();

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		$table->search_box( 'Поиск по телефону или ключу', 'target' );
		echo '</form>';

		echo '<form method="post">';
		wp_nonce_field( 'agent007_bulk_targets' );
		$table->display();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render the edit form for one target
	 *
	 * @since 1.2.0
	 */
	private function render_edit() {
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$target = $id ? $this->database->get_target( $id ) : null;

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		$this->render_notice();

		if ( ! $target ) {
			echo '<p>Цель не найдена.</p>';
			echo '<p><a href="' . esc_url( $this->list_url() ) . '">Назад к списку</a></p></div>';
			return;
		}

		include AGENT007_PLUGIN_DIR . 'admin/views/target-edit.php';
		echo '</div>';
	}

	/**
	 * Handle the update form submitted from the edit page
	 *
	 * @since 1.2.0
	 */
	public function handle_update() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'You do not have permission to perform this action.' );
		}

		check_admin_referer( 'agent007_update_target' );

		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$target = $id ? $this->database->get_target( $id ) : null;

		if ( ! $target ) {
			$this->redirect( array( 'page' => self::SLUG, 'agent007_notice' => 'error_not_found' ) );
		}

		$edit_url = array( 'page' => self::SLUG, 'agent007_action' => 'edit', 'id' => $id );

		$phone       = $this->post_text( 'phone' );
		$key         = $this->post_text( 'key' );
		$access_path = substr( $this->post_text( 'access_path' ), 0, 255 );
		$status      = isset( $_POST['status'] ) ? (int) $_POST['status'] : -1;

		if ( ! Validator::is_phone( $phone ) ) {
			$this->redirect( $edit_url + array( 'agent007_notice' => 'error_phone' ) );
		}

		if ( ! Validator::is_key( $key ) ) {
			$this->redirect( $edit_url + array( 'agent007_notice' => 'error_key' ) );
		}

		if ( ! array_key_exists( $status, Database::STATUSES ) ) {
			$this->redirect( $edit_url + array( 'agent007_notice' => 'error_status' ) );
		}

		if ( $this->database->key_exists_elsewhere( $key, $id ) ) {
			$this->redirect( $edit_url + array( 'agent007_notice' => 'error_key_exists' ) );
		}

		$updated = $this->database->update_target(
			$id,
			array(
				'phone'       => $phone,
				'key'         => $key,
				'status'      => $status,
				'access_path' => $access_path,
			)
		);

		if ( ! $updated ) {
			$this->redirect( $edit_url + array( 'agent007_notice' => 'error_db' ) );
		}

		$this->logger->log(
			sprintf( 'Target updated by user %d - ID: %d, Phone: %s, Key: %s, Status: %d', get_current_user_id(), $id, $phone, $key, $status )
		);

		$this->redirect( array( 'page' => self::SLUG, 'agent007_notice' => 'updated' ) );
	}

	/**
	 * Delete one target from a row action link
	 *
	 * @since 1.2.0
	 */
	private function handle_single_delete() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		check_admin_referer( 'agent007_delete_' . $id );

		$count = $this->database->delete_targets( array( $id ) );
		$this->logger->log( sprintf( 'Target deleted by user %d - ID: %d', get_current_user_id(), $id ) );

		$this->redirect( array( 'page' => self::SLUG, 'agent007_notice' => 'deleted', 'count' => $count ) );
	}

	/**
	 * Delete selected targets from the bulk action
	 *
	 * @since 1.2.0
	 */
	private function handle_bulk_delete() {
		check_admin_referer( 'agent007_bulk_targets' );

		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();

		$count = $this->database->delete_targets( $ids );
		$this->logger->log( sprintf( 'Targets deleted by user %d - Count: %d, IDs: %s', get_current_user_id(), $count, implode( ',', $ids ) ) );

		$this->redirect( array( 'page' => self::SLUG, 'agent007_notice' => 'deleted', 'count' => $count ) );
	}

	/**
	 * Bulk action selected in the list (WP_List_Table uses action or action2)
	 *
	 * @return string
	 */
	private function current_bulk_action() {
		$action = isset( $_REQUEST['action'] ) ? wp_unslash( $_REQUEST['action'] ) : '';

		if ( '-1' === $action ) {
			$action = isset( $_REQUEST['action2'] ) ? wp_unslash( $_REQUEST['action2'] ) : '';
		}

		return sanitize_key( $action );
	}

	/**
	 * Show a notice from the agent007_notice query arg
	 *
	 * @since 1.2.0
	 */
	private function render_notice() {
		$notice = isset( $_GET['agent007_notice'] ) ? sanitize_key( wp_unslash( $_GET['agent007_notice'] ) ) : '';
		$texts  = $this->notice_texts();

		if ( ! isset( $texts[ $notice ] ) ) {
			return;
		}

		$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
		$text  = 'deleted' === $notice ? sprintf( 'Удалено целей: %d', $count ) : $texts[ $notice ];
		$type  = 0 === strpos( $notice, 'error' ) ? 'error' : 'success';

		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $text ) );
	}

	/**
	 * Notice texts by code
	 *
	 * @return array
	 */
	private function notice_texts() {
		return array(
			'updated'          => 'Цель обновлена.',
			'deleted'          => 'deleted',
			'error_not_found'  => 'Цель не найдена.',
			'error_phone'      => 'Некорректный номер телефона: только цифры, минимум 10.',
			'error_key'        => 'Ключ должен состоять из 10 латинских букв.',
			'error_status'     => 'Некорректный статус.',
			'error_key_exists' => 'Такой ключ уже есть у другой цели.',
			'error_db'         => 'Ошибка базы данных.',
		);
	}

	/**
	 * Read a text field from POST
	 *
	 * @param string $name Field name.
	 *
	 * @return string
	 */
	private function post_text( $name ) {
		return isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
	}

	/**
	 * URL of the list page
	 *
	 * @return string
	 */
	private function list_url() {
		return add_query_arg( array( 'page' => self::SLUG ), admin_url( 'admin.php' ) );
	}

	/**
	 * Redirect to the admin page with query args and stop
	 *
	 * @param array $args Query args.
	 */
	private function redirect( array $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
