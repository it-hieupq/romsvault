<?php
/**
 * Members admin page (plan §4.4 / §4.3). Lists members from the custom `member`
 * table and lets an admin block/unblock them. Blocking only flips
 * `member.blocked = 1`; enforcement is live per-endpoint (§4.3) + login handler,
 * so no session enumeration is needed here (no session index — §13.T).
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
 * Whitelisted status filter from the request: '' (all) | 'active' | 'blocked'.
 * Shared by the list query, the status views, and the search form's hidden input.
 */
function rf_members_status_filter(): string {
	$s = (string) ( $_REQUEST['status'] ?? '' );
	return in_array( $s, array( 'active', 'blocked' ), true ) ? $s : '';
}

/**
 * Per-page count from the request, clamped to [1, 200] (default 10). Shared by the
 * three member-admin list pages (members / ratings / report) so pagination behaves
 * identically. function_exists guard: the file is required first in the is_admin
 * block, but stay defensive against load-order changes.
 */
if ( ! function_exists( 'rf_admin_per_page' ) ) {
	function rf_admin_per_page( int $default = 10 ): int {
		$v = isset( $_GET['per_page'] ) ? (int) $_GET['per_page'] : $default;
		return max( 1, min( 200, $v ) );
	}
}

/**
 * Relative "x ago" label for an admin list cell. $is_gmt says whether the stored
 * datetime is GMT (member table) or site-local (rating/report tables) so the diff
 * is always computed against a real UNIX timestamp. Returns escaped HTML with the
 * full timestamp as a hover title.
 */
if ( ! function_exists( 'rf_admin_time_ago' ) ) {
	function rf_admin_time_ago( string $datetime, bool $is_gmt ): string {
		if ( $datetime === '' || $datetime === '0000-00-00 00:00:00' ) {
			return '—';
		}
		$gmt = $is_gmt ? $datetime : get_gmt_from_date( $datetime );
		$ts  = strtotime( $gmt . ' UTC' );
		if ( ! $ts ) {
			return esc_html( $datetime );
		}
		/* translators: %s: human-readable time difference, e.g. "5 mins". */
		return '<span title="' . esc_attr( $datetime ) . '">'
			. esc_html( sprintf( __( '%s ago', 'rom' ), human_time_diff( $ts, time() ) ) )
			. '</span>';
	}
}

/**
 * Count rows created since local midnight today. $is_gmt shifts the local day
 * boundary into GMT for tables that store created_at in GMT. $table/$column are
 * internal rf_*_table() values (no user input); the threshold is still prepared.
 */
if ( ! function_exists( 'rf_admin_today_count' ) ) {
	function rf_admin_today_count( string $table, bool $is_gmt, string $column = 'created_at' ): int {
		global $wpdb;
		$local_midnight = current_time( 'Y-m-d' ) . ' 00:00:00';
		$threshold      = $is_gmt ? get_gmt_from_date( $local_midnight ) : $local_midnight;
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` >= %s", $threshold )
		);
	}
}

/**
 * "Added today: N" badge for the left of the per-page row. Returns escaped HTML.
 */
if ( ! function_exists( 'rf_admin_today_badge' ) ) {
	function rf_admin_today_badge( int $count ): string {
		return '<span class="rf-added-today">'
			. esc_html__( 'Added today:', 'rom' ) . ' <strong>' . esc_html( number_format_i18n( $count ) ) . '</strong>'
			. '</span>';
	}
}

/**
 * Render one row holding the "added today" badge (left) and the "Items per page"
 * input (right, default 10). Meant to sit inside a GET form below the search box;
 * submitting re-queries with the new count. $left_html is pre-escaped caller HTML.
 */
if ( ! function_exists( 'rf_admin_per_page_field' ) ) {
	function rf_admin_per_page_field( string $left_html = '' ): void {
		$v = rf_admin_per_page();
		echo '<div class="rf-per-page" style="clear:both;margin:8px 0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">'
			. '<span>' . $left_html . '</span>'
			. '<span>'
			. '<label>' . esc_html__( 'Items per page:', 'rom' ) . ' '
			. '<input type="number" name="per_page" value="' . esc_attr( $v ) . '" min="1" max="200" step="1" style="width:70px" />'
			. '</label> '
			. '<button type="submit" class="button">' . esc_html__( 'Apply', 'rom' ) . '</button>'
			. '</span>'
			. '</div>';
	}
}

/**
 * List table backed by a direct query on the `member` table (indexed columns —
 * §14). NOT get_users(); members do not live in wp_users.
 */
class RF_Members_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'member',
			'plural'   => 'members',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'name'       => __( 'Member', 'rom' ),
			'email'      => __( 'Email', 'rom' ),
			'created_at' => __( 'Registered', 'rom' ),
			'google_sub' => __( 'Google ID', 'rom' ),
			'status'     => __( 'Status', 'rom' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
			'email'      => array( 'email', false ),
		);
	}

	/**
	 * Status filter links (All / Active / Blocked) with live counts, shown above
	 * the table. Each preserves the current search term; the active view is marked
	 * so WP styles it. Counts come from one aggregate query on the indexed `blocked`.
	 */
	protected function get_views() {
		global $wpdb;
		$table   = rf_member_table();
		$c       = $wpdb->get_row(
			"SELECT COUNT(*) AS total, SUM( blocked = 0 ) AS active, SUM( blocked = 1 ) AS blocked FROM `{$table}`",
			ARRAY_A
		);
		$current = rf_members_status_filter();
		$search  = trim( (string) ( $_REQUEST['s'] ?? '' ) );

		$link = function ( $status, $label, $count ) use ( $current, $search ) {
			$args = array( 'page' => 'rf-members' );
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
			'active'  => $link( 'active', __( 'Active', 'rom' ), $c['active'] ),
			'blocked' => $link( 'blocked', __( 'Blocked', 'rom' ), $c['blocked'] ),
		);
	}

	public function prepare_items() {
		global $wpdb;

		$table    = rf_member_table();
		$per_page = rf_admin_per_page( 10 );
		$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$offset   = ( $paged - 1 ) * $per_page;

		// Whitelist sort inputs (never interpolate raw request into SQL).
		$orderby = ( ( $_GET['orderby'] ?? '' ) === 'email' ) ? 'email' : 'created_at';
		$order   = ( strtolower( $_GET['order'] ?? '' ) === 'asc' ) ? 'ASC' : 'DESC';

		$search = trim( (string) ( $_REQUEST['s'] ?? '' ) );
		$status = rf_members_status_filter();
		$conds  = array();
		$params = array();
		if ( $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$conds[]  = '( email LIKE %s OR name LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}
		if ( $status === 'active' ) {
			$conds[] = 'blocked = 0';
		} elseif ( $status === 'blocked' ) {
			$conds[] = 'blocked = 1';
		}
		$where = $conds ? ( 'WHERE ' . implode( ' AND ', $conds ) ) : '';

		$total_sql = "SELECT COUNT(*) FROM `{$table}` {$where}";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) )
			: $wpdb->get_var( $total_sql ) );

		// Deep OFFSET pagination is acceptable while the table is small; revisit
		// with keyset pagination at scale (§4.4 / §13.3.4).
		$rows_sql = "SELECT id, google_sub, email, name, avatar_url, blocked, created_at
			FROM `{$table}` {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
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

	public function column_name( $item ) {
		$avatar = $item['avatar_url']
			? '<img src="' . esc_url( $item['avatar_url'] ) . '" width="32" height="32" style="border-radius:50%;vertical-align:middle;margin-right:8px" alt="" />'
			: '';
		$name = $item['name'] ? esc_html( $item['name'] ) : '<em>' . esc_html__( '(no name)', 'rom' ) . '</em>';

		$blocked = (int) $item['blocked'] === 1;
		$action  = $blocked ? 'unblock' : 'block';
		$label   = $blocked ? __( 'Unblock', 'rom' ) : __( 'Block', 'rom' );
		$url     = wp_nonce_url(
			admin_url( 'admin.php?page=rf-members&action=' . $action . '&member=' . (int) $item['id'] ),
			'rf_member_' . $action . '_' . (int) $item['id']
		);
		$class      = $blocked ? '' : ' style="color:#b32d2e"';
		$row_action = array(
			$action => '<a href="' . esc_url( $url ) . '"' . $class . '>' . esc_html( $label ) . '</a>',
		);

		return $avatar . '<strong>' . $name . '</strong>' . $this->row_actions( $row_action );
	}

	public function column_email( $item ) {
		return esc_html( $item['email'] );
	}

	public function column_created_at( $item ) {
		return rf_admin_time_ago( (string) $item['created_at'], true ); // member.created_at is GMT
	}

	public function column_google_sub( $item ) {
		return '<code>' . esc_html( $item['google_sub'] ) . '</code>';
	}

	public function column_status( $item ) {
		return (int) $item['blocked'] === 1
			? '<span style="color:#b32d2e;font-weight:600">' . esc_html__( 'Blocked', 'rom' ) . '</span>'
			: '<span style="color:#008a20">' . esc_html__( 'Active', 'rom' ) . '</span>';
	}

	public function column_default( $item, $column_name ) {
		return esc_html( $item[ $column_name ] ?? '' );
	}

	public function no_items() {
		esc_html_e( 'No members found.', 'rom' );
	}
}

/**
 * Register the top-level "Members" menu (cap edit_users — admins only).
 */
function rf_members_admin_menu(): void
{
	add_submenu_page(
		'options', // Configs (inc/options.php)
		__( 'Members', 'rom' ),
		__( 'Members', 'rom' ),
		'edit_users',
		'rf-members',
		'rf_members_render_page',
		1 // right after Configs itself, ahead of Report which ACF adds first
	);
}
// After ACF adds the Configs menu at 99 (see auth/admin-ratings.php).
add_action( 'admin_menu', 'rf_members_admin_menu', 100 );

/**
 * Handle block/unblock before any output (nonce + capability guarded). Only
 * flips member.blocked; live re-check on every endpoint makes it take effect
 * immediately without touching sessions (§4.3).
 */
function rf_members_handle_action(): void
{
	if ( ( $_GET['page'] ?? '' ) !== 'rf-members' ) {
		return;
	}
	$action = $_GET['action'] ?? '';
	if ( $action !== 'block' && $action !== 'unblock' ) {
		return;
	}
	if ( ! current_user_can( 'edit_users' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage members.', 'rom' ) );
	}

	$member_id = (int) ( $_GET['member'] ?? 0 );
	check_admin_referer( 'rf_member_' . $action . '_' . $member_id );

	if ( $member_id > 0 ) {
		global $wpdb;
		$wpdb->update(
			rf_member_table(),
			array( 'blocked' => $action === 'block' ? 1 : 0 ),
			array( 'id' => $member_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	wp_safe_redirect( admin_url( 'admin.php?page=rf-members&updated=' . $action ) );
	exit;
}
add_action( 'admin_init', 'rf_members_handle_action' );

/**
 * Render the Members list page.
 */
function rf_members_render_page(): void {
	if ( ! current_user_can( 'edit_users' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage members.', 'rom' ) );
	}

	$table = new RF_Members_List_Table();
	$table->prepare_items();
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Members', 'rom' ); ?></h1>
		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php echo $_GET['updated'] === 'block'
					? esc_html__( 'Member blocked.', 'rom' )
					: esc_html__( 'Member unblocked.', 'rom' ); ?>
			</p></div>
		<?php endif; ?>
		<hr class="wp-header-end" />
		<?php $table->views(); ?>
		<form method="get">
			<input type="hidden" name="page" value="rf-members" />
			<input type="hidden" name="status" value="<?php echo esc_attr( rf_members_status_filter() ); ?>" />
			<?php $table->search_box( __( 'Search members', 'rom' ), 'rf-members' ); ?>
			<?php rf_admin_per_page_field( rf_admin_today_badge( rf_admin_today_count( rf_member_table(), true ) ) ); ?>
			<?php $table->display(); ?>
		</form>
	</div>
	<?php
}
