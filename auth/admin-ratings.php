<?php
/**
 * Ratings & Reviews admin page. Lists member reviews from the custom `member_rating`
 * table and lets an admin moderate them: Hide/Unhide (status 1↔0 — hidden reviews
 * drop out of the public list AND the per-star histogram, which both filter status=1)
 * and Delete (removes the row; also frees uq_member_post so that member could rate
 * again). Ratings are otherwise immutable and the post's rating_avg/rating_count
 * post_meta (a running average incl. the legacy baseline) is NOT recomputed here.
 *
 * Admin pages are never cached, so WP nonces are used normally for the actions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Whitelisted status filter from the request: '' (all) | 'visible' | 'hidden'.
 */
function rf_ratings_status_filter(): string {
	$s = (string) ( $_REQUEST['status'] ?? '' );
	return in_array( $s, array( 'visible', 'hidden' ), true ) ? $s : '';
}

/**
 * List table backed by a direct query on the `member_rating` table, joined to the
 * member (author) and post (title). LEFT JOINs so a review survives member/post
 * deletion — the row still shows with a fallback label.
 */
class RF_Ratings_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'review',
			'plural'   => 'reviews',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'member'     => __( 'Member', 'rom' ),
			'post'       => __( 'Post', 'rom' ),
			'score'      => __( 'Rating', 'rom' ),
			'content'    => __( 'Review', 'rom' ),
			'created_at' => __( 'Date', 'rom' ),
			'status'     => __( 'Status', 'rom' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
			'score'      => array( 'score', false ),
		);
	}

	/**
	 * Status views (All / Visible / Hidden) with live counts, preserving search.
	 */
	protected function get_views() {
		global $wpdb;
		$table   = rf_member_rating_table();
		$c       = $wpdb->get_row(
			"SELECT COUNT(*) AS total, SUM( status = 1 ) AS visible, SUM( status = 0 ) AS hidden FROM `{$table}`",
			ARRAY_A
		);
		$current = rf_ratings_status_filter();
		$search  = trim( (string) ( $_REQUEST['s'] ?? '' ) );

		$link = function ( $status, $label, $count ) use ( $current, $search ) {
			$args = array( 'page' => 'rf-ratings' );
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
			'all'     => $link( '', __( 'All', 'rom' ), $c['total'] ),
			'visible' => $link( 'visible', __( 'Visible', 'rom' ), $c['visible'] ),
			'hidden'  => $link( 'hidden', __( 'Hidden', 'rom' ), $c['hidden'] ),
		);
	}

	public function prepare_items() {
		global $wpdb;

		$table    = rf_member_rating_table();
		$members  = rf_member_table();
		$posts    = $wpdb->prefix . 'posts';
		$per_page = rf_admin_per_page( 10 );
		$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$offset   = ( $paged - 1 ) * $per_page;

		// Whitelist sort inputs (never interpolate raw request into SQL).
		$orderby = ( ( $_GET['orderby'] ?? '' ) === 'score' ) ? 'r.score' : 'r.created_at';
		$order   = ( strtolower( $_GET['order'] ?? '' ) === 'asc' ) ? 'ASC' : 'DESC';

		$search = trim( (string) ( $_REQUEST['s'] ?? '' ) );
		$status = rf_ratings_status_filter();
		$conds  = array();
		$params = array();
		if ( $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$conds[]  = '( m.name LIKE %s OR m.email LIKE %s OR r.content LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( $status === 'visible' ) {
			$conds[] = 'r.status = 1';
		} elseif ( $status === 'hidden' ) {
			$conds[] = 'r.status = 0';
		}
		$where = $conds ? ( 'WHERE ' . implode( ' AND ', $conds ) ) : '';

		$total_sql = "SELECT COUNT(*) FROM `{$table}` r
			LEFT JOIN `{$members}` m ON m.id = r.member_id {$where}";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) )
			: $wpdb->get_var( $total_sql ) );

		$rows_sql = "SELECT r.id, r.member_id, r.post_id, r.score, r.content, r.status, r.created_at,
			m.name AS member_name, m.email AS member_email, p.post_title
			FROM `{$table}` r
			LEFT JOIN `{$members}` m ON m.id = r.member_id
			LEFT JOIN `{$posts}` p ON p.ID = r.post_id
			{$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
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

	public function column_member( $item ) {
		$name = $item['member_name']
			? esc_html( $item['member_name'] )
			: '<em>' . esc_html__( '(deleted member)', 'rom' ) . '</em>';
		$email = $item['member_email'] ? '<br><small>' . esc_html( $item['member_email'] ) . '</small>' : '';

		$id         = (int) $item['id'];
		$hidden     = (int) $item['status'] === 0;
		$toggle     = $hidden ? 'unhide' : 'hide';
		$label      = $hidden ? __( 'Unhide', 'rom' ) : __( 'Hide', 'rom' );
		$toggle_url = wp_nonce_url(
			admin_url( 'admin.php?page=rf-ratings&action=' . $toggle . '&review=' . $id ),
			'rf_rating_' . $toggle . '_' . $id
		);
		$delete_url = wp_nonce_url(
			admin_url( 'admin.php?page=rf-ratings&action=delete&review=' . $id ),
			'rf_rating_delete_' . $id
		);
		$confirm = esc_js( __( 'Delete this review permanently?', 'rom' ) );
		$actions = array(
			$toggle  => '<a href="' . esc_url( $toggle_url ) . '">' . esc_html( $label ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete_url ) . '" style="color:#b32d2e"'
				. ' onclick="return confirm(\'' . $confirm . '\')">'
				. esc_html__( 'Delete', 'rom' ) . '</a>',
		);

		return '<strong>' . $name . '</strong>' . $email . $this->row_actions( $actions );
	}

	public function column_post( $item ) {
		$post_id   = (int) $item['post_id'];
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

	public function column_score( $item ) {
		$score = max( 0, min( 5, (int) $item['score'] ) );
		return '<span style="color:#f5b301;font-size:15px;letter-spacing:1px" title="' . $score . '/5">'
			. str_repeat( '★', $score ) . '<span style="color:#d1d5db">' . str_repeat( '★', 5 - $score ) . '</span>'
			. '</span>';
	}

	public function column_content( $item ) {
		return $item['content'] ? esc_html( $item['content'] ) : '<em>' . esc_html__( '(no text)', 'rom' ) . '</em>';
	}

	public function column_created_at( $item ) {
		return rf_admin_time_ago( (string) $item['created_at'], false ); // member_rating.created_at is site-local
	}

	public function column_status( $item ) {
		return (int) $item['status'] === 0
			? '<span style="color:#b32d2e;font-weight:600">' . esc_html__( 'Hidden', 'rom' ) . '</span>'
			: '<span style="color:#008a20">' . esc_html__( 'Visible', 'rom' ) . '</span>';
	}

	public function column_default( $item, $column_name ) {
		return esc_html( $item[ $column_name ] ?? '' );
	}

	public function no_items() {
		esc_html_e( 'No reviews found.', 'rom' );
	}
}

/**
 * Register the top-level "Ratings & Reviews" menu (manage_options — admins only),
 * grouped with Members/Report above Posts.
 */
function rf_ratings_admin_menu(): void {
	add_submenu_page(
		'options', // Configs (inc/options.php)
		__( 'Ratings & Reviews', 'rom' ),
		__( 'Ratings & Reviews', 'rom' ),
		'manage_options',
		'rf-ratings',
		'rf_ratings_render_page'
	);
}
// After ACF adds the Configs menu at 99: a submenu registered before its parent
// gets the wrong hook name and the page refuses access.
add_action( 'admin_menu', 'rf_ratings_admin_menu', 100 );

/**
 * Handle hide/unhide/delete before any output (nonce + capability guarded).
 */
function rf_ratings_handle_action(): void {
	if ( ( $_GET['page'] ?? '' ) !== 'rf-ratings' ) {
		return;
	}
	$action = $_GET['action'] ?? '';
	if ( ! in_array( $action, array( 'hide', 'unhide', 'delete' ), true ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage reviews.', 'rom' ) );
	}

	$review_id = (int) ( $_GET['review'] ?? 0 );
	check_admin_referer( 'rf_rating_' . $action . '_' . $review_id );

	if ( $review_id > 0 ) {
		global $wpdb;
		$table = rf_member_rating_table();
		if ( $action === 'delete' ) {
			$wpdb->delete( $table, array( 'id' => $review_id ), array( '%d' ) );
		} else {
			$wpdb->update(
				$table,
				array( 'status' => $action === 'hide' ? 0 : 1 ),
				array( 'id' => $review_id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	wp_safe_redirect( admin_url( 'admin.php?page=rf-ratings&updated=' . $action ) );
	exit;
}
add_action( 'admin_init', 'rf_ratings_handle_action' );

/**
 * Render the Ratings & Reviews list page.
 */
function rf_ratings_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage reviews.', 'rom' ) );
	}

	$table = new RF_Ratings_List_Table();
	$table->prepare_items();

	$notices = array(
		'hide'   => __( 'Review hidden.', 'rom' ),
		'unhide' => __( 'Review is visible again.', 'rom' ),
		'delete' => __( 'Review deleted.', 'rom' ),
	);
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Ratings &amp; Reviews', 'rom' ); ?></h1>
		<?php if ( isset( $_GET['updated'] ) && isset( $notices[ $_GET['updated'] ] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php echo esc_html( $notices[ $_GET['updated'] ] ); ?>
			</p></div>
		<?php endif; ?>
		<hr class="wp-header-end" />
		<?php $table->views(); ?>
		<form method="get">
			<input type="hidden" name="page" value="rf-ratings" />
			<input type="hidden" name="status" value="<?php echo esc_attr( rf_ratings_status_filter() ); ?>" />
			<?php $table->search_box( __( 'Search reviews', 'rom' ), 'rf-ratings' ); ?>
			<?php rf_admin_per_page_field( rf_admin_today_badge( rf_admin_today_count( rf_member_rating_table(), false ) ) ); ?>
			<?php $table->display(); ?>
		</form>
	</div>
	<?php
}
