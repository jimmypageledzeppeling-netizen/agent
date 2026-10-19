<?php
/**
 * List table for targets (admin)
 *
 * @package Agent007
 */

namespace Agent007;

/**
 * Targets list table
 *
 * Requires wp-admin/includes/class-wp-list-table.php to be loaded first.
 *
 * @since 1.2.0
 */
class Targets_List_Table extends \WP_List_Table {

	const PER_PAGE = 20;

	/**
	 * Database instance
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * Constructor
	 *
	 * @param Database $database Database instance.
	 */
	public function __construct( Database $database ) {
		parent::__construct(
			array(
				'singular' => 'target',
				'plural'   => 'targets',
				'ajax'     => false,
			)
		);

		$this->database = $database;
	}

	/**
	 * Current status filter from the URL, null means all
	 *
	 * @return int|null
	 *
	 * @since 1.2.0
	 */
	public static function current_status() {
		if ( ! isset( $_GET['agent007_status'] ) ) {
			return null;
		}

		$status = sanitize_text_field( wp_unslash( $_GET['agent007_status'] ) );

		return is_numeric( $status ) && array_key_exists( (int) $status, Database::STATUSES ) ? (int) $status : null;
	}

	/**
	 * Columns
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'ID'          => 'ID',
			'phone'       => 'Телефон',
			'key'         => 'Ключ',
			'status'      => 'Статус',
			'access_path' => 'Путь доступа',
			'created_at'  => 'Создана',
			'views'       => 'Просмотров',
		);
	}

	/**
	 * Sortable columns
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'ID'         => array( 'ID', false ),
			'phone'      => array( 'phone', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
			'views'      => array( 'views', false ),
		);
	}

	/**
	 * Bulk actions
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => 'Удалить',
		);
	}

	/**
	 * Status filter links above the table
	 *
	 * @return array
	 */
	protected function get_views() {
		$base    = remove_query_arg( array( 'agent007_status', 'paged' ) );
		$current = self::current_status();
		$views   = array(
			'all' => sprintf(
				'<a href="%s" class="%s">Все</a>',
				esc_url( $base ),
				null === $current ? 'current' : ''
			),
		);

		foreach ( Database::STATUSES as $code => $label ) {
			$views[ $code ] = sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( add_query_arg( 'agent007_status', $code, $base ) ),
				$current === $code ? 'current' : '',
				esc_html( $label )
			);
		}

		return $views;
	}

	/**
	 * Load items for the current page
	 *
	 * @since 1.2.0
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'ID';
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';

		$result = $this->database->get_targets(
			array(
				'status'   => self::current_status(),
				'search'   => $search,
				'orderby'  => $orderby,
				'order'    => $order,
				'per_page' => self::PER_PAGE,
				'page'     => $this->get_pagenum(),
			)
		);

		$this->items = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => self::PER_PAGE,
				'total_pages' => (int) ceil( $result['total'] / self::PER_PAGE ),
			)
		);
	}

	/**
	 * Empty state text
	 *
	 * @since 1.2.0
	 */
	public function no_items() {
		echo esc_html( 'Цели не найдены.' );
	}

	/**
	 * Checkbox column
	 *
	 * @param object $item Row.
	 *
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item->ID );
	}

	/**
	 * Phone column (primary, with row actions)
	 *
	 * @param object $item Row.
	 *
	 * @return string
	 */
	protected function column_phone( $item ) {
		$edit_url   = add_query_arg(
			array(
				'page'               => TargetsPage::SLUG,
				'agent007_action'    => 'edit',
				'id'                 => (int) $item->ID,
			),
			admin_url( 'admin.php' )
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'            => TargetsPage::SLUG,
					'agent007_action' => 'delete',
					'id'              => (int) $item->ID,
				),
				admin_url( 'admin.php' )
			),
			'agent007_delete_' . (int) $item->ID
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">Изменить</a>', esc_url( $edit_url ) ),
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'Удалить цель вместе с историей просмотров?\');">Удалить</a>',
				esc_url( $delete_url )
			),
		);

		return esc_html( $item->phone ) . $this->row_actions( $actions );
	}

	/**
	 * ID column
	 *
	 * @param object $item Row.
	 *
	 * @return string
	 */
	protected function column_ID( $item ) {
		return (int) $item->ID;
	}

	/**
	 * Key column
	 *
	 * @param object $item Row.
	 *
	 * @return string
	 */
	protected function column_key( $item ) {
		return '<code>' . esc_html( $item->key ) . '</code>';
	}

	/**
	 * Status column
	 *
	 * @param object $item Row.
	 *
	 * @return string
	 */
	protected function column_status( $item ) {
		$status = (int) $item->status;

		return esc_html( Database::STATUSES[ $status ] ?? 'Неизвестно' );
	}

	/**
	 * Access path column
	 *
	 * @param object $item Row.
	 *
	 * @return string
	 */
	protected function column_access_path( $item ) {
		return esc_html( (string) $item->access_path );
	}

	/**
	 * Created date column
	 *
	 * @param object $item Row.
	 *
	 * @return string
	 */
	protected function column_created_at( $item ) {
		return esc_html( $item->created_at );
	}

	/**
	 * Views count column
	 *
	 * @param object $item Row.
	 *
	 * @return string
	 */
	protected function column_views( $item ) {
		return (int) $item->views;
	}
}
