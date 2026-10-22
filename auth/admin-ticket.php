<?php
/**
 * Requests admin — wp-admin management for member request tickets (member_ticket).
 * Native top-level admin page (NOT an ACF options page: no default ACF form, no Update
 * button, no empty field-group box). Renders a paginated table with WP-native .subsubsub
 * status filter. OPEN tickets get an inline status <select> + reply textarea + Save;
 * terminal tickets (Closed/Cancelled) are read-only — a status badge and the reply text.
 * Row/bulk delete via admin-ajax. Schema/maps live in setting-member.php; front-end
 * submission is the REST endpoint rf/v1/ticket (auth/actions/ticket.php). Admin-only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Admin reply cap (response_content is a TEXT column; admin is trusted but still bounded).
if ( ! defined( 'RF_TICKET_RESPONSE_MAXLEN' ) ) {
	define( 'RF_TICKET_RESPONSE_MAXLEN', 1000 );
}

/**
 * Register the top-level "Requests" admin menu (native page + own render callback).
 */
function app_ticket_menu(): void
{
	add_submenu_page(
		'options', // Configs (inc/options.php)
		__( 'Requests', 'rom' ),
		__( 'Requests', 'rom' ),
		'manage_options',
		'ticket',
		'app_ticket_render_page'
	);
}
// After ACF adds the Configs menu at 99 (see auth/admin-ratings.php).
add_action( 'admin_menu', 'app_ticket_menu', 100 );

/**
 * Update a ticket's status + admin reply. Only non-terminal tickets can change; admins
 * set status 0..3 (Cancel is member-only). Moving to Closed (3) stamps closed_by=2.
 */
function app_ticket_update_status(): void
{
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'rom' ) ) );
	}
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ticket_update_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'rom' ) ) );
	}
	$id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$status   = isset( $_POST['status'] ) ? intval( $_POST['status'] ) : -1;
	$response = isset( $_POST['response'] ) ? trim( wp_strip_all_tags( wp_unslash( $_POST['response'] ) ) ) : '';
	if ( $id <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'rom' ) ) );
	}
	if ( $status < 0 || $status > 3 ) { // admin may set 0..3 only (Cancel is member-only)
		wp_send_json_error( array( 'message' => __( 'Invalid status.', 'rom' ) ) );
	}
	if ( mb_strlen( $response ) > RF_TICKET_RESPONSE_MAXLEN ) {
		$response = mb_substr( $response, 0, RF_TICKET_RESPONSE_MAXLEN );
	}

	global $wpdb;
	$table = rf_ticket_table();
	$now   = current_time( 'mysql' );

	// Guard: only non-terminal tickets can change. closed_by=2 when moving to Closed,
	// NULL otherwise (ticket wasn't terminal, so it had no closer).
	if ( $status === 3 ) {
		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE `{$table}` SET status = 3, response_content = %s, closed_by = 2, updated_at = %s
			 WHERE id = %d AND status NOT IN (3,4)",
			$response, $now, $id
		) );
	} else {
		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE `{$table}` SET status = %d, response_content = %s, closed_by = NULL, updated_at = %s
			 WHERE id = %d AND status NOT IN (3,4)",
			$status, $response, $now, $id
		) );
	}

	if ( false === $updated ) {
		wp_send_json_error( array( 'message' => __( 'Error. Please contact administrator.', 'rom' ) ) );
	}
	if ( 0 === $updated ) {
		wp_send_json_error( array( 'message' => __( 'This request is already closed/cancelled.', 'rom' ) ) );
	}
	if ( $status === 3 && function_exists( 'rf_ticket_flush_history' ) ) {
		// Admin closed the ticket -> it entered the member's finished list; drop that cache.
		rf_ticket_flush_history( (int) $wpdb->get_var( $wpdb->prepare( "SELECT member_id FROM `{$table}` WHERE id = %d", $id ) ) );
	}
	wp_send_json_success( array( 'message' => __( 'Updated.', 'rom' ) ) );
}
add_action( 'wp_ajax_app_ticket_update_status', 'app_ticket_update_status' );

/**
 * Delete a single ticket row.
 */
function app_ticket_delete_row() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'rom' ) ) );
	}
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ticket_delete_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'rom' ) ) );
	}
	global $wpdb;
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( $id <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'rom' ) ) );
	}
	$table  = rf_ticket_table();
	$owner  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT member_id FROM `{$table}` WHERE id = %d", $id ) );
	$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	if ( $result ) {
		if ( function_exists( 'rf_ticket_flush_history' ) ) { rf_ticket_flush_history( $owner ); }
		wp_send_json_success( array( 'message' => __( 'Deleted.', 'rom' ) ) );
	}
	wp_send_json_error( array( 'message' => __( 'Error. Please contact administrator.', 'rom' ) ) );
}
add_action( 'wp_ajax_app_ticket_delete_row', 'app_ticket_delete_row' );

/**
 * Bulk delete tickets.
 */
function app_ticket_delete_rows() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'rom' ) ) );
	}
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ticket_delete_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'rom' ) ) );
	}
	if ( ! isset( $_POST['ids'] ) || ! is_array( $_POST['ids'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid IDs.', 'rom' ) ) );
	}
	$ids = array_filter( array_map( 'absint', $_POST['ids'] ) );
	if ( empty( $ids ) ) {
		wp_send_json_error( array( 'message' => __( 'No valid IDs provided.', 'rom' ) ) );
	}
	global $wpdb;
	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$table        = rf_ticket_table();
	$owners       = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT member_id FROM {$table} WHERE id IN({$placeholders})", ...$ids ) );
	$result       = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN({$placeholders})", ...$ids ) );
	if ( $result !== false ) {
		if ( function_exists( 'rf_ticket_flush_history' ) ) {
			foreach ( (array) $owners as $o ) { rf_ticket_flush_history( $o ); }
		}
		wp_send_json_success( array( 'message' => sprintf( __( '%d item(s) deleted.', 'rom' ), $result ) ) );
	}
	wp_send_json_error( array( 'message' => __( 'Error. Please contact administrator.', 'rom' ) ) );
}
add_action( 'wp_ajax_app_ticket_delete_rows', 'app_ticket_delete_rows' );

/**
 * Render the Requests admin page: .subsubsub status filter + paginated table.
 */
function app_ticket_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;

	$statuses      = rf_ticket_statuses();
	$types         = rf_ticket_types();
	$status_colors = rf_ticket_status_colors();

	$per_page     = rf_admin_per_page( 10 );
	$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$offset       = ( $current_page - 1 ) * $per_page;

	$table        = rf_ticket_table();
	$member_table = rf_member_table();

	// Optional status filter.
	$filter = isset( $_GET['status'] ) && $_GET['status'] !== '' ? intval( $_GET['status'] ) : -1;
	$where  = ( $filter >= 0 && $filter <= 4 ) ? $wpdb->prepare( 'WHERE t.status = %d', $filter ) : '';

	$total_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} t " . $where );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT t.id, t.request_type, t.game_title, t.reference_url, t.description, t.response_content,
				t.status, t.closed_by, t.created_at, t.updated_at,
				m.email AS member_email, m.name AS member_name
		 FROM {$table} t
		 LEFT JOIN {$member_table} m ON m.id = t.member_id
		 {$where}
		 ORDER BY t.created_at DESC
		 LIMIT %d OFFSET %d",
		$per_page, $offset
	) );

	// Status filter counts (one aggregate pass over idx_status_created).
	$counts    = $wpdb->get_row( "SELECT COUNT(*) AS total,
		SUM(status=0) AS s0, SUM(status=1) AS s1, SUM(status=2) AS s2,
		SUM(status=3) AS s3, SUM(status=4) AS s4 FROM {$table}", ARRAY_A );
	$view_link = function ( $val, $label, $count ) use ( $filter, $per_page ) {
		$args = array( 'page' => 'ticket', 'per_page' => $per_page );
		if ( $val !== '' ) {
			$args['status'] = $val;
		}
		$is_cur = ( $val === '' ) ? ( $filter < 0 ) : ( (int) $val === $filter );
		$class  = $is_cur ? ' class="current"' : '';
		return '<a href="' . esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) ) . '"' . $class . '>'
			. esc_html( $label ) . ' <span class="count">(' . (int) $count . ')</span></a>';
	};
	$status_keys = array_keys( $statuses );
	$last_key    = end( $status_keys );

	$update_nonce = wp_create_nonce( 'ticket_update_nonce' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Requests', 'rom' ); ?></h1>
		<hr class="wp-header-end">

		<style>
			.rf-ticket-wrap { overflow-x: auto; margin: 16px 0; width: 100%; }
			.rf-ticket-wrap table { min-width: 1100px; width: 100%; }
			.rf-ticket-wrap th, .rf-ticket-wrap td { padding: 10px; vertical-align: top; word-wrap: break-word; }
			.rf-ticket-wrap textarea { width: 100%; min-height: 54px; }
			.rf-ticket-wrap .col-title { min-width: 160px; }
			.rf-ticket-wrap .col-desc, .rf-ticket-wrap .col-resp { min-width: 220px; max-width: 320px; }
			.rf-ticket-badge { display:inline-block; padding:2px 10px; border-radius:9999px; font-size:11px; font-weight:600; }
			.rf-ticket-pagination { margin: 20px 0; display: flex; align-items: center; gap: 10px; }
			.rf-ticket-pagination .page-numbers { padding: 5px 10px; border: 1px solid #ccc; border-radius: 3px; text-decoration: none; }
			.rf-ticket-pagination .page-numbers.current { background: #0073aa; color: #fff; border-color: #0073aa; }
		</style>

		<ul class="subsubsub" style="margin:8px 0 4px">
			<li><?php echo $view_link( '', __( 'All', 'rom' ), $counts['total'] ); ?> |</li>
			<?php foreach ( $statuses as $rf_k => $rf_label ) : ?>
				<li><?php echo $view_link( (string) $rf_k, $rf_label, $counts[ 's' . $rf_k ] ); ?><?php echo $rf_k === $last_key ? '' : ' |'; ?></li>
			<?php endforeach; ?>
		</ul>

		<form method="get" style="margin:8px 0; clear:both">
			<input type="hidden" name="page" value="ticket" />
			<?php if ( $filter >= 0 ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $filter ); ?>" /><?php endif; ?>
			<?php rf_admin_per_page_field( rf_admin_today_badge( rf_admin_today_count( rf_ticket_table(), false ) ) ); ?>
		</form>

		<?php if ( $rows ) : ?>
		<div class="rf-ticket-wrap" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ticket_delete_nonce' ) ); ?>" data-update-nonce="<?php echo esc_attr( $update_nonce ); ?>">
			<table class="widefat striped">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'rom' ); ?></th>
					<th><?php esc_html_e( 'Member', 'rom' ); ?></th>
					<th class="col-title"><?php esc_html_e( 'Game', 'rom' ); ?></th>
					<th class="col-desc"><?php esc_html_e( 'Details', 'rom' ); ?></th>
					<th><?php esc_html_e( 'Status', 'rom' ); ?></th>
					<th class="col-resp"><?php esc_html_e( 'Response', 'rom' ); ?></th>
					<th><?php esc_html_e( 'Time', 'rom' ); ?></th>
					<th>
						<button class="button button-small delete-rows" type="button"><?php esc_html_e( 'Delete', 'rom' ); ?></button>
						<button class="button button-small button-primary" id="ticketCheckAll" type="button"><?php esc_html_e( 'Check', 'rom' ); ?></button>
					</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $row ) :
					$st          = (int) $row->status;
					$is_terminal = in_array( $st, array( 3, 4 ), true );
					$sc          = isset( $status_colors[ $st ] ) ? $status_colors[ $st ] : array( 'bg' => '#f3f4f6', 'text' => '#374151' );
				?>
					<tr data-id="<?php echo esc_attr( $row->id ); ?>">
						<td><?php echo esc_html( isset( $types[ (int) $row->request_type ] ) ? $types[ (int) $row->request_type ] : '—' ); ?></td>
						<td>
							<?php echo esc_html( $row->member_email ?: '—' ); ?>
							<?php if ( ! empty( $row->member_name ) ) : ?><br><small><?php echo esc_html( $row->member_name ); ?></small><?php endif; ?>
						</td>
						<td class="col-title">
							<strong><?php echo esc_html( $row->game_title ); ?></strong>
							<?php if ( ! empty( $row->reference_url ) ) : ?>
								<br><a href="<?php echo esc_url( $row->reference_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'reference', 'rom' ); ?></a>
							<?php endif; ?>
						</td>
						<td class="col-desc"><?php echo $row->description ? esc_html( $row->description ) : '<em>—</em>'; ?></td>
						<td>
							<span class="rf-ticket-badge" style="background:<?php echo esc_attr( $sc['bg'] ); ?>;color:<?php echo esc_attr( $sc['text'] ); ?>"><?php echo esc_html( isset( $statuses[ $st ] ) ? $statuses[ $st ] : '' ); ?></span>
							<?php if ( ! $is_terminal ) : ?>
								<select class="rf-ticket-status" style="margin-top:6px">
									<?php foreach ( $statuses as $rf_k => $rf_label ) :
										if ( $rf_k === 4 ) { continue; } // admin sets 0..3 only; Cancel is member-only ?>
										<option value="<?php echo esc_attr( $rf_k ); ?>" <?php selected( $st, $rf_k ); ?>><?php echo esc_html( $rf_label ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</td>
						<td class="col-resp">
							<?php if ( $is_terminal ) : ?>
								<?php echo $row->response_content ? esc_html( $row->response_content ) : '<em>—</em>'; ?>
							<?php else : ?>
								<textarea class="rf-ticket-response" maxlength="1000"><?php echo esc_textarea( $row->response_content ); ?></textarea>
								<button type="button" class="button button-small button-primary rf-ticket-save"><?php esc_html_e( 'Save', 'rom' ); ?></button>
							<?php endif; ?>
						</td>
						<td style="white-space:nowrap">
							<?php echo rf_admin_time_ago( (string) $row->created_at, false ); // returns pre-escaped HTML ?>
							<?php if ( $row->updated_at && $row->updated_at !== $row->created_at ) : ?>
								<br><small style="color:#888"><?php esc_html_e( 'upd', 'rom' ); ?> <?php echo rf_admin_time_ago( (string) $row->updated_at, false ); // pre-escaped HTML ?></small>
							<?php endif; ?>
						</td>
						<td style="text-align:center"><input class="check-item" type="checkbox" value="<?php echo esc_attr( $row->id ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<script>
		(function ($) {
			var wrap = document.querySelector('.rf-ticket-wrap');
			if (!wrap) return;
			var delNonce = wrap.getAttribute('data-nonce');
			var updNonce = wrap.getAttribute('data-update-nonce');

			wrap.addEventListener('click', function (e) {
				var save = e.target.closest('.rf-ticket-save');
				if (save) {
					var tr = save.closest('tr');
					var id = tr.getAttribute('data-id');
					var status = tr.querySelector('.rf-ticket-status').value;
					var response = tr.querySelector('.rf-ticket-response').value;
					save.disabled = true;
					$.post(ajaxurl, { action: 'app_ticket_update_status', nonce: updNonce, id: id, status: status, response: response })
						.done(function (r) { if (r && r.success) { location.reload(); } else { alert(r && r.data ? r.data.message : 'Update failed'); save.disabled = false; } })
						.fail(function () { alert('Request failed'); save.disabled = false; });
					return;
				}
				var del = e.target.closest('.delete-rows');
				if (del) {
					var ids = Array.prototype.map.call(wrap.querySelectorAll('.check-item:checked'), function (c) { return c.value; });
					if (!ids.length) { alert('Select at least one row.'); return; }
					if (!confirm('Delete ' + ids.length + ' item(s)?')) return;
					$.post(ajaxurl, { action: 'app_ticket_delete_rows', nonce: delNonce, ids: ids })
						.done(function (r) { alert(r && r.data ? r.data.message : 'Done'); if (r && r.success) location.reload(); })
						.fail(function () { alert('Request failed'); });
				}
			});
			var checkAll = document.getElementById('ticketCheckAll');
			if (checkAll) checkAll.addEventListener('click', function () {
				wrap.querySelectorAll('.check-item').forEach(function (c) { c.checked = !c.checked; });
			});
		})(jQuery);
		</script>

		<?php
		$total_pages = ceil( $total_count / $per_page );
		if ( $total_pages > 1 ) :
			$base_url = add_query_arg( array( 'per_page' => $per_page, 'status' => ( $filter >= 0 ? $filter : '' ) ), admin_url( 'admin.php?page=ticket' ) );
			?>
			<div class="rf-ticket-pagination">
				<span class="displaying-num">
					<?php
					$start = ( $current_page - 1 ) * $per_page + 1;
					$end   = min( $current_page * $per_page, $total_count );
					printf( esc_html__( 'Showing %1$d-%2$d of %3$d requests', 'rom' ), $start, $end, $total_count );
					?>
				</span>
				<div class="pagination-links">
					<?php
					if ( $current_page > 1 ) {
						echo '<a class="page-numbers" href="' . esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ) . '">« Prev</a>';
					}
					for ( $i = 1; $i <= $total_pages; $i++ ) {
						if ( $i == 1 || $i == $total_pages || ( $i >= $current_page - 2 && $i <= $current_page + 2 ) ) {
							$cls = ( $i == $current_page ) ? 'current' : '';
							echo '<a class="page-numbers ' . esc_attr( $cls ) . '" href="' . esc_url( add_query_arg( 'paged', $i, $base_url ) ) . '">' . (int) $i . '</a>';
						} elseif ( $i == $current_page - 3 || $i == $current_page + 3 ) {
							echo '<span class="page-numbers dots">…</span>';
						}
					}
					if ( $current_page < $total_pages ) {
						echo '<a class="page-numbers" href="' . esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ) . '">Next »</a>';
					}
					?>
				</div>
			</div>
		<?php endif; ?>

		<?php else : ?>
			<div class="notice notice-info"><p><?php esc_html_e( 'No requests yet.', 'rom' ); ?></p></div>
		<?php endif; ?>
	</div>
	<?php
}
