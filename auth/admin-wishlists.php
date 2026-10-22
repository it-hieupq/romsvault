<?php
/**
 * Wishlists admin page. Read-only insight into the `member_wishlist` table,
 * grouped by post: how many members wishlisted each post, when it was last
 * added, and the current status of that post (Published / an unpublished state
 * like Draft/Pending / or Missing when the post no longer exists). No row
 * actions — wishlist rows are maintained by the post-lifecycle hooks
 * (auth/actions/wishlist.php), not from here.
 *
 * Admin pages are never cached, so WP nonces are used normally where needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Whitelisted status filter: '' (all) | 'published' | 'unpublished' | 'missing'.
 * Shared by the list query and the status views.
 */
function rf_wishlists_status_filter(): string {
	$s = (string) ( $_REQUEST['status'] ?? '' );
	return in_array( $s, array( 'published', 'unpublished', 'missing' ), true ) ? $s : '';
}

/**
 * List table over `member_wishlist` grouped by post_id, LEFT JOINed to wp_posts
 * so a wishlisted-but-deleted post still shows (as Missing).
 */
class RF_Wishlists_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'wishlist',
			'plural'   => 'wishlists',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'post'       => __( 'Post', 'rom' ),
			'wishers'    => __( 'Wishlisted by', 'rom' ),
			'status'     => __( 'Post status', 'rom' ),
			'last_added' => __( 'Last added', 'rom' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'wishers'    => array( 'wishers', true ),
			'last_added' => array( 'last_added', false ),
		);
	}

	/**
	 * Status views (All / Published / Unpublished / Missing) with distinct-post
	 * counts, preserving the current search term.
	 */
	protected function get_views() {
		global $wpdb;
		$wl    = rf_member_wishlist_table();
		$posts = $wpdb->prefix . 'posts';
		$c     = $wpdb->get_row(
			"SELECT
				COUNT( DISTINCT w.post_id ) AS total,
				COUNT( DISTINCT CASE WHEN p.post_status = 'publish' THEN w.post_id END ) AS published,
				COUNT( DISTINCT CASE WHEN p.ID IS NOT NULL AND p.post_status <> 'publish' THEN w.post_id END ) AS unpublished,
				COUNT( DISTINCT CASE WHEN p.ID IS NULL THEN w.post_id END ) AS missing
			 FROM `{$wl}` w LEFT JOIN `{$posts}` p ON p.ID = w.post_id",
			ARRAY_A
		);
		$current = rf_wishlists_status_filter();
		$search  = trim( (string) ( $_REQUEST['s'] ?? '' ) );

		$link = function ( $status, $label, $count ) use ( $current, $search ) {
			$args = array( 'page' => 'rf-wishlists' );
			if ( $search !== '' ) {
				$args['s'] = $search;
			}
			if ( $status !== '' ) {
				$args['status'] = $status;
			}
			$url   = add_query_arg( $args, admin_url( 'admin.php' ) );
			$class = ( $current === $status ) ? ' class="current"' : '';
			return '<a href="' . esc_url( $url ) . '"' . $class . '>'
				. esc_html( $label ) . ' <span class="count">(' . (int) $count . ')</span></a>';
		};

		return array(
			'all'         => $link( '', __( 'All', 'rom' ), $c['total'] ),
			'published'   => $link( 'published', __( 'Published', 'rom' ), $c['published'] ),
			'unpublished' => $link( 'unpublished', __( 'Unpublished', 'rom' ), $c['unpublished'] ),
			'missing'     => $link( 'missing', __( 'Missing', 'rom' ), $c['missing'] ),
		);
	}

	public function prepare_items() {
		global $wpdb;

		$wl       = rf_member_wishlist_table();
		$posts    = $wpdb->prefix . 'posts';
		$per_page = rf_admin_per_page( 10 );
		$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$offset   = ( $paged - 1 ) * $per_page;

		// Whitelist sort (aliases from the GROUP BY select); never interpolate raw request.
		$orderby = ( ( $_GET['orderby'] ?? '' ) === 'last_added' ) ? 'last_added' : 'wishers';
		$order   = ( strtolower( $_GET['order'] ?? '' ) === 'asc' ) ? 'ASC' : 'DESC';

		$search = trim( (string) ( $_REQUEST['s'] ?? '' ) );
		$status = rf_wishlists_status_filter();
		$conds  = array();
		$params = array();
		if ( $search !== '' ) {
			$conds[]  = 'p.post_title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		if ( $status === 'published' ) {
			$conds[] = "p.post_status = 'publish'";
		} elseif ( $status === 'unpublished' ) {
			$conds[] = "p.ID IS NOT NULL AND p.post_status <> 'publish'";
		} elseif ( $status === 'missing' ) {
			$conds[] = 'p.ID IS NULL';
		}
		$where = $conds ? ( 'WHERE ' . implode( ' AND ', $conds ) ) : '';

		// Total = distinct posts matching the filter (grouped, so count the group set).
		$total_sql = "SELECT COUNT(*) FROM (
			SELECT w.post_id FROM `{$wl}` w LEFT JOIN `{$posts}` p ON p.ID = w.post_id
			{$where} GROUP BY w.post_id ) t";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) )
			: $wpdb->get_var( $total_sql ) );

		$rows_sql = "SELECT w.post_id, COUNT(*) AS wishers, MAX( w.created_at ) AS last_added,
			p.ID AS exists_id, p.post_title, p.post_status, p.post_type
			FROM `{$wl}` w
			LEFT JOIN `{$posts}` p ON p.ID = w.post_id
			{$where}
			GROUP BY w.post_id
			ORDER BY {$orderby} {$order}
			LIMIT %d OFFSET %d";
		$this->items = $wpdb->get_results(
			$wpdb->prepare( $rows_sql, array_merge( $params, array( $per_page, $offset ) ) ),
			ARRAY_A
		);

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		) );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	public function column_post( $item ) {
		$post_id = (int) $item['post_id'];
		if ( empty( $item['exists_id'] ) ) {
			return '<strong><em>#' . $post_id . '</em></strong> '
				. '<span style="color:#b32d2e">(' . esc_html__( 'deleted', 'rom' ) . ')</span>';
		}

		$title     = $item['post_title'] ? esc_html( $item['post_title'] ) : '<em>#' . $post_id . '</em>';
		$permalink = get_permalink( $post_id );
		$edit      = get_edit_post_link( $post_id );

		$out = $permalink
			? '<a href="' . esc_url( $permalink ) . '" target="_blank"><strong>' . $title . '</strong></a>'
			: '<strong>' . $title . '</strong>';
		if ( $edit ) {
			$out .= '<div class="row-actions"><span class="edit">'
				. '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'rom' ) . '</a>'
				. '</span></div>';
		}
		return $out;
	}

	public function column_wishers( $item ) {
		return '<strong>' . esc_html( number_format_i18n( (int) $item['wishers'] ) ) . '</strong>';
	}

	public function column_status( $item ) {
		if ( empty( $item['exists_id'] ) ) {
			return '<span style="color:#b32d2e;font-weight:600">' . esc_html__( 'Missing', 'rom' ) . '</span>';
		}
		$st = (string) $item['post_status'];
		if ( $st === 'publish' ) {
			return '<span style="color:#008a20;font-weight:600">' . esc_html__( 'Published', 'rom' ) . '</span>';
		}
		if ( $st === 'trash' ) {
			return '<span style="color:#b32d2e;font-weight:600">' . esc_html__( 'Trashed', 'rom' ) . '</span>';
		}
		// draft / pending / private / future — show WP's own label where available.
		$obj   = get_post_status_object( $st );
		$label = $obj ? $obj->label : ucfirst( $st );
		return '<span style="color:#bd8600;font-weight:600">' . esc_html( $label ) . '</span>';
	}

	public function column_last_added( $item ) {
		return rf_admin_time_ago( (string) $item['last_added'], false ); // member_wishlist.created_at is site-local
	}

	public function column_default( $item, $column_name ) {
		return esc_html( $item[ $column_name ] ?? '' );
	}

	public function no_items() {
		esc_html_e( 'No wishlisted posts found.', 'rom' );
	}
}

/**
 * Register the top-level "Wishlists" menu (manage_options — admins only),
 * grouped with Members/Report/Ratings above Posts.
 */
function rf_wishlists_admin_menu(): void {
	add_submenu_page(
		'options', // Configs (inc/options.php)
		__( 'Wishlists', 'rom' ),
		__( 'Wishlists', 'rom' ),
		'manage_options',
		'rf-wishlists',
		'rf_wishlists_render_page'
	);
}
// After ACF adds the Configs menu at 99 (see auth/admin-ratings.php).
add_action( 'admin_menu', 'rf_wishlists_admin_menu', 100 );

/**
 * Render the Wishlists list page.
 */
function rf_wishlists_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to view wishlists.', 'rom' ) );
	}

	$table = new RF_Wishlists_List_Table();
	$table->prepare_items();
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Wishlists', 'rom' ); ?></h1>
		<hr class="wp-header-end" />
		<?php $table->views(); ?>
		<form method="get">
			<input type="hidden" name="page" value="rf-wishlists" />
			<input type="hidden" name="status" value="<?php echo esc_attr( rf_wishlists_status_filter() ); ?>" />
			<?php $table->search_box( __( 'Search posts', 'rom' ), 'rf-wishlists' ); ?>
			<?php rf_admin_per_page_field( rf_admin_today_badge( rf_admin_today_count( rf_member_wishlist_table(), false ) ) ); ?>
			<?php $table->display(); ?>
		</form>
	</div>
	<?php
}
