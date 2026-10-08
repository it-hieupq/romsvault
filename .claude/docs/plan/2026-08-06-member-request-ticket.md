# Member Request Ticket — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cho member đã đăng nhập tạo 1 "Request" (thêm game / thêm-cập nhật file), theo dõi trạng thái, và admin xử lý + phản hồi trong wp-admin.

**Architecture:** Bám kiến trúc member sẵn có — bảng custom trong `auth/setting-member.php`, REST `rf/v1/*` (verify origin + session trong handler, không admin-ajax cho front-end), trang cache-safe shell + data client-side qua `auth/js/auth.js`, admin page kiểu `auth/admin-report.php` (ACF options page + bảng render bằng `ob_start`). Spec gốc: `.claude/docs/spec/2026-08-06-member-request-ticket-design.md`.

**Tech Stack:** WordPress theme (PHP), `$wpdb` (custom table), REST API, vanilla JS (IIFE trong auth.js), Tailwind (utility classes có sẵn — KHÔNG thêm class cần rebuild).

## Global Constraints

- **Ngôn ngữ trả lời user: tiếng Việt.** Code/comment/biến: tiếng Anh.
- **i18n:** mọi string UI qua `__()/esc_html__()` textdomain **`rom`**. Không tiếng Việt trong UI theme.
- **Security:** mọi `$wpdb` query dùng `prepare()`; escape output (`esc_html/esc_attr/esc_url`); sanitize input; nonce cho admin-ajax; REST verify origin + session.
- **Cache-aware:** trang front-end bị Cloudflare cache. KHÔNG dùng `is_user_logged_in()`/cookie WP trong template. State member = client-side + REST.
- **Messaging:** 1 notification / action; text lỗi lấy từ `message` của server → `window.showToast` (qua `toast()` trong auth.js).
- **Naming:** code/DB/route = `ticket`; UI hiển thị = "Request".
- **Verify:** sau sửa PHP chạy `php -l <file>`; sau sửa JS chạy `node --check <file>`. Không report "done" nếu check chưa pass.
- **Commit rules:** KHÔNG thêm AI attribution/co-author. Chỉ stage file thuộc task. KHÔNG stage `assets/css/output.css`, `tailwindcss/node_modules`, lockfiles.
- **Status codes:** 0 Init, 1 Processing, 2 Solved, 3 Closed, 4 Cancelled. Terminal = {3,4}. Open = {0,1,2}.
- **Local env:** docker; domain `https://romsvault.local`; container php `wp_phpfpm`, DB `wp_mariadb`. wp-admin visit tự trigger `rf_member_maybe_upgrade` (admin_init).

---

### Task 1: Schema, table helper, mapping arrays, version bump

**Files:**
- Modify: `auth/setting-member.php` (thêm helpers + CREATE TABLE trong `rf_member_install_schema()`; bump `RF_MEMBER_DB_VERSION`)

**Interfaces:**
- Produces: `rf_ticket_table(): string`, `rf_ticket_statuses(): array<int,string>`, `rf_ticket_types(): array<int,string>`, bảng `{prefix}member_ticket`.

- [ ] **Step 1: Bump version.** Ở dòng `define( 'RF_MEMBER_DB_VERSION', '5' );` đổi `'5'` → `'6'`.

- [ ] **Step 2: Thêm table + mapping helpers.** Ngay sau `rf_member_rating_table()` (kết thúc dòng 45), thêm:

```php
function rf_ticket_table() {
	global $wpdb;
	return $wpdb->prefix . 'member_ticket';
}

/**
 * Ticket status map (stored tinyint => UI label). Terminal statuses are 3 (Closed)
 * and 4 (Cancelled); open statuses are 0/1/2. Shared by the REST layer, the /ticket
 * page and the admin page so the code<->label mapping lives in one place.
 */
function rf_ticket_statuses() {
	return array(
		0 => __( 'Init', 'rom' ),
		1 => __( 'Processing', 'rom' ),
		2 => __( 'Solved', 'rom' ),
		3 => __( 'Closed', 'rom' ),
		4 => __( 'Cancelled', 'rom' ),
	);
}

/**
 * Request-type map (stored tinyint => UI label). Fixed enum (not admin-configurable).
 */
function rf_ticket_types() {
	return array(
		0 => __( 'Add new game', 'rom' ),
		1 => __( 'Add / Update file for existing game', 'rom' ),
	);
}
```

- [ ] **Step 3: Thêm CREATE TABLE.** Trong `rf_member_install_schema()`, ngay trước dòng comment "Existing installs (member table created at v2)…" (khối `SHOW INDEX ... idx_created`), thêm:

```php
	// Member request tickets: one row per submitted request. status is a tinyint
	// (0 Init,1 Processing,2 Solved,3 Closed,4 Cancelled); terminal = 3/4. game_title
	// is free text (no post link). response_content is admin-authored progress the
	// member reads back. idx_member_created serves the member's list + the open-ticket
	// / daily-count checks (both filter member_id); idx_status_created serves the admin
	// list/filter by status, newest-first.
	$ticket = rf_ticket_table();
	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS `{$ticket}` (
			id               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
			member_id        BIGINT UNSIGNED  NOT NULL,
			request_type     TINYINT UNSIGNED NOT NULL,
			game_title       VARCHAR(255)     NOT NULL,
			reference_url    VARCHAR(512)     NULL,
			description      TEXT             NULL,
			response_content TEXT             NULL,
			status           TINYINT UNSIGNED NOT NULL DEFAULT 0,
			closed_by        TINYINT UNSIGNED NULL,
			created_at       DATETIME         NOT NULL,
			updated_at       DATETIME         NOT NULL,
			PRIMARY KEY (id),
			KEY idx_member_created (member_id, created_at),
			KEY idx_status_created (status, created_at)
		) ENGINE=InnoDB {$charset}"
	);
```

(`$charset` đã có sẵn ở đầu hàm.)

- [ ] **Step 4: Lint.**

Run: `php -l auth/setting-member.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Trigger migration + verify bảng tồn tại.** Mở bất kỳ trang wp-admin (login admin) để `admin_init` chạy `rf_member_maybe_upgrade`. Sau đó kiểm tra schema:

Run: `docker exec wp_phpfpm bash -lc "cd /var/www/sites/romsvault && wp db query 'DESCRIBE wp_member_ticket' --allow-root"`
Expected: liệt kê đủ 11 cột (id, member_id, request_type, game_title, reference_url, description, response_content, status, closed_by, created_at, updated_at).
(Nếu WP-CLI không có, verify bằng `wp eval 'global $wpdb; var_dump($wpdb->get_col("SHOW TABLES LIKE \"%member_ticket\""));'` hoặc mở Adminer.)

- [ ] **Step 6: Commit.**

```bash
git add auth/setting-member.php
git commit -m "feat(member): add member_ticket table + status/type maps

New request-ticket schema (v6): free-text game_title, tinyint status
(0 Init..4 Cancelled), admin response_content. Adds rf_ticket_table(),
rf_ticket_statuses(), rf_ticket_types() shared by REST + admin."
```

---

### Task 2: REST endpoints (`/ticket`, `/my-tickets`, `/ticket/close`)

**Files:**
- Create: `auth/actions/ticket.php`
- Modify: `functions.php` (thêm `require` sau dòng 304 `.../rating.php`)

**Interfaces:**
- Consumes (từ Task 1): `rf_ticket_table()`, `rf_ticket_statuses()`, `rf_ticket_types()`. Từ core: `rf_verify_request_origin()`, `rf_current_member()` (trả `['id','blocked',...]`), `rf_check_action_rate()`, hằng `RF_REST_NS`.
- Produces: `POST rf/v1/ticket`, `GET rf/v1/my-tickets`, `POST rf/v1/ticket/close`; hằng `RF_TICKET_DAILY_MAX`, `RF_TICKET_DESC_MAXLEN`.

- [ ] **Step 1: Tạo `auth/actions/ticket.php`** với nội dung đầy đủ:

```php
<?php
/**
 * Member action — request tickets. A member submits a "Request" (add a new game, or
 * add/update a file for an existing game); admins process it and reply. Member-only
 * REST (never admin-ajax): identity comes from the session. Three creation limits:
 * the shared burst throttle (rf_check_action_rate), at most one OPEN ticket per member
 * at a time (status NOT IN 3,4), and a soft cap of RF_TICKET_DAILY_MAX creations per
 * calendar day (anti open/close spam). Rows land in {prefix}member_ticket for the
 * wp-admin Requests page (auth/admin-ticket.php). Depends on member-core.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RF_TICKET_DAILY_MAX' ) ) {
	define( 'RF_TICKET_DAILY_MAX', 10 ); // max ticket creations per member per calendar day
}
if ( ! defined( 'RF_TICKET_DESC_MAXLEN' ) ) {
	define( 'RF_TICKET_DESC_MAXLEN', 2000 ); // hard cap for description / admin response (chars)
}

/**
 * Ticket error response — carries a human message (shown as-is by the client toast).
 */
function rf_ticket_error( $code, $status ) {
	$messages = array(
		'blocked'       => __( 'Your account is locked and cannot submit requests.', 'rom' ),
		'rate_limited'  => __( 'You are doing that too fast. Please wait a moment.', 'rom' ),
		'auth_required' => __( 'Please sign in to submit a request.', 'rom' ),
		'bad_type'      => __( 'Please select a valid request type.', 'rom' ),
		'missing_title' => __( 'Please enter the game title.', 'rom' ),
		'open_exists'   => __( 'You already have an open request. Please close it before creating a new one.', 'rom' ),
		'daily_limit'   => __( 'You have reached today\'s request limit. Please try again tomorrow.', 'rom' ),
		'not_open'      => __( 'This request can no longer be changed.', 'rom' ),
	);
	$message = isset( $messages[ $code ] ) ? $messages[ $code ] : __( 'Could not process your request. Please try again.', 'rom' );
	return new WP_REST_Response( array( 'ok' => false, 'error' => $code, 'message' => $message ), $status );
}

add_action( 'rest_api_init', function () {
	register_rest_route( RF_REST_NS, '/ticket', array(
		'methods'             => 'POST',
		'callback'            => 'rf_rest_ticket_create',
		'permission_callback' => '__return_true', // member session + origin checked in-handler
	) );
	register_rest_route( RF_REST_NS, '/my-tickets', array(
		'methods'             => 'GET',
		'callback'            => 'rf_rest_my_tickets',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( RF_REST_NS, '/ticket/close', array(
		'methods'             => 'POST',
		'callback'            => 'rf_rest_ticket_close',
		'permission_callback' => '__return_true',
	) );
} );

/**
 * POST /wp-json/rf/v1/ticket  { type:int(0|1), game_title, reference_url?, description? }
 * Verify member + origin + block + throttle, validate type/title, then INSERT atomically
 * only while the member has NO open ticket (status NOT IN 3,4) AND fewer than
 * RF_TICKET_DAILY_MAX creations today. New tickets start at status 0 (Init).
 */
function rf_rest_ticket_create( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return rf_ticket_error( 'bad_origin', 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return rf_ticket_error( 'auth_required', 401 );
	}
	if ( (int) $member['blocked'] === 1 ) {
		return rf_ticket_error( 'blocked', 403 );
	}
	if ( ! rf_check_action_rate( (int) $member['id'] ) ) {
		return rf_ticket_error( 'rate_limited', 429 );
	}

	$type  = (int) $request->get_param( 'type' );
	$types = rf_ticket_types();
	if ( ! isset( $types[ $type ] ) ) {
		return rf_ticket_error( 'bad_type', 400 );
	}

	$game_title = trim( wp_strip_all_tags( (string) $request->get_param( 'game_title' ) ) );
	if ( $game_title === '' ) {
		return rf_ticket_error( 'missing_title', 400 );
	}
	if ( mb_strlen( $game_title ) > 255 ) {
		$game_title = mb_substr( $game_title, 0, 255 );
	}

	$reference_url = esc_url_raw( (string) $request->get_param( 'reference_url' ) );
	if ( strlen( $reference_url ) > 512 ) {
		$reference_url = ''; // over-long / malformed -> drop the optional link
	}

	$description = trim( wp_strip_all_tags( (string) $request->get_param( 'description' ) ) );
	if ( mb_strlen( $description ) > RF_TICKET_DESC_MAXLEN ) {
		$description = mb_substr( $description, 0, RF_TICKET_DESC_MAXLEN );
	}

	global $wpdb;
	$table       = rf_ticket_table();
	$member_id   = (int) $member['id'];
	$now         = current_time( 'mysql' );
	$today_start = date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );

	// Atomic: insert only while there is NO open ticket for this member AND today's
	// creation count is under the cap. Sub-selects are wrapped in derived tables so
	// MySQL/MariaDB allows reading the same table we insert into (avoids error 1093).
	// rows-affected: 1 = inserted, 0 = a guard blocked it, false = SQL error.
	$inserted = $wpdb->query( $wpdb->prepare(
		"INSERT INTO `{$table}`
			(member_id, request_type, game_title, reference_url, description, status, created_at, updated_at)
		 SELECT %d, %d, %s, %s, %s, 0, %s, %s FROM ( SELECT 1 ) d
		 WHERE NOT EXISTS (
			SELECT 1 FROM ( SELECT id FROM `{$table}` WHERE member_id = %d AND status NOT IN (3,4) LIMIT 1 ) o
		 )
		 AND ( SELECT c FROM ( SELECT COUNT(*) c FROM `{$table}`
				WHERE member_id = %d AND created_at >= %s ) t ) < %d",
		$member_id, $type, $game_title, $reference_url, $description, $now, $now,
		$member_id, $member_id, $today_start, RF_TICKET_DAILY_MAX
	) );

	if ( false === $inserted ) {
		return rf_ticket_error( 'insert_failed', 500 );
	}
	if ( 0 === $inserted ) {
		// Distinguish the two block reasons (cheap, only on the rejected path).
		$open = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE member_id = %d AND status NOT IN (3,4)",
			$member_id
		) );
		return $open > 0 ? rf_ticket_error( 'open_exists', 409 ) : rf_ticket_error( 'daily_limit', 429 );
	}

	return new WP_REST_Response( array(
		'ok'      => true,
		'message' => __( 'Your request has been submitted. Thank you!', 'rom' ),
	), 200 );
}

/**
 * GET /wp-json/rf/v1/my-tickets?offset=0&limit=10 — the member's own tickets, newest
 * first, for the /ticket page. Each row carries both the code and the resolved label
 * for status/type, plus is_open so the client knows whether to show Close/Cancel.
 */
function rf_rest_my_tickets( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_origin' ), 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'auth_required' ), 401 );
	}
	// Blocked members may still VIEW their own tickets (read-only).

	$limit = absint( $request->get_param( 'limit' ) );
	if ( $limit < 1 || $limit > 50 ) {
		$limit = 10;
	}
	$offset = absint( $request->get_param( 'offset' ) );

	global $wpdb;
	$table     = rf_ticket_table();
	$member_id = (int) $member['id'];

	$total = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM `{$table}` WHERE member_id = %d",
		$member_id
	) );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, request_type, game_title, reference_url, description, response_content,
				status, closed_by, created_at, updated_at
		 FROM `{$table}`
		 WHERE member_id = %d
		 ORDER BY created_at DESC, id DESC
		 LIMIT %d OFFSET %d",
		$member_id, $limit, $offset
	), ARRAY_A );

	$statuses = rf_ticket_statuses();
	$types    = rf_ticket_types();
	$out      = array();
	foreach ( (array) $rows as $r ) {
		$st = (int) $r['status'];
		$ty = (int) $r['request_type'];
		$out[] = array(
			'id'            => (int) $r['id'],
			'type'          => $ty,
			'type_label'    => isset( $types[ $ty ] ) ? $types[ $ty ] : '',
			'status'        => $st,
			'status_label'  => isset( $statuses[ $st ] ) ? $statuses[ $st ] : '',
			'is_open'       => ! in_array( $st, array( 3, 4 ), true ),
			'game_title'    => $r['game_title'],
			'reference_url' => $r['reference_url'],
			'description'   => $r['description'],
			'response'      => $r['response_content'],
			'created_at'    => $r['created_at'],
			'updated_at'    => $r['updated_at'],
		);
	}

	return new WP_REST_Response( array(
		'ok'       => true,
		'tickets'  => $out,
		'has_more' => ( $offset + count( $out ) ) < $total,
		'total'    => $total,
	), 200 );
}

/**
 * POST /wp-json/rf/v1/ticket/close  { id:int, mode:'close'|'cancel' }
 * Member closes (status 3) or cancels (status 4) their OWN ticket while it is still
 * open. One UPDATE guarded by member_id + status NOT IN (3,4): rows-affected 0 means
 * not owned / not found / already terminal.
 */
function rf_rest_ticket_close( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return rf_ticket_error( 'bad_origin', 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return rf_ticket_error( 'auth_required', 401 );
	}
	if ( (int) $member['blocked'] === 1 ) {
		return rf_ticket_error( 'blocked', 403 );
	}
	if ( ! rf_check_action_rate( (int) $member['id'] ) ) {
		return rf_ticket_error( 'rate_limited', 429 );
	}

	$id         = absint( $request->get_param( 'id' ) );
	$mode       = sanitize_key( (string) $request->get_param( 'mode' ) );
	$new_status = ( $mode === 'cancel' ) ? 4 : 3; // anything but 'cancel' => close

	global $wpdb;
	$table     = rf_ticket_table();
	$member_id = (int) $member['id'];

	$updated = $wpdb->query( $wpdb->prepare(
		"UPDATE `{$table}` SET status = %d, closed_by = 1, updated_at = %s
		 WHERE id = %d AND member_id = %d AND status NOT IN (3,4)",
		$new_status, current_time( 'mysql' ), $id, $member_id
	) );

	if ( false === $updated ) {
		return rf_ticket_error( 'update_failed', 500 );
	}
	if ( 0 === $updated ) {
		return rf_ticket_error( 'not_open', 409 );
	}

	$message = ( $new_status === 4 )
		? __( 'Your request has been cancelled.', 'rom' )
		: __( 'Your request has been closed.', 'rom' );

	return new WP_REST_Response( array( 'ok' => true, 'status' => $new_status, 'message' => $message ), 200 );
}
```

- [ ] **Step 2: Wire require.** Trong `functions.php`, ngay sau dòng 304 (`.../actions/rating.php`), thêm:

```php
require get_template_directory() . '/auth/actions/ticket.php';    // POST /ticket, GET /my-tickets, POST /ticket/close
```

- [ ] **Step 3: Lint.**

Run: `php -l auth/actions/ticket.php && php -l functions.php`
Expected: cả hai `No syntax errors detected`.

- [ ] **Step 4: Verify endpoint được đăng ký + auth gate.** (Không có cookie session → phải trả 401.)

Run: `curl -sk -X POST https://romsvault.local/wp-json/rf/v1/ticket -H 'Content-Type: application/json' -d '{"type":0,"game_title":"Test"}'`
Expected: JSON `{"ok":false,"error":"auth_required", ...}` (HTTP 401) — chứng tỏ route tồn tại và gate hoạt động (KHÔNG phải 404 rest_no_route).

- [ ] **Step 5: Verify full-flow trong browser (đăng nhập member).** Console tab, khi đã login:

```js
fetch('/wp-json/rf/v1/ticket', {method:'POST', credentials:'include',
  headers:{'Content-Type':'application/json','X-RF-Auth':'1'},
  body: JSON.stringify({type:0, game_title:'Zelda demo', description:'test'})
}).then(r=>r.json()).then(console.log)
```
Expected lần 1: `{ok:true,...}`. Lần 2 ngay sau (đã có ticket mở): `{ok:false,error:"open_exists"}` (409). Sau đó `GET /wp-json/rf/v1/my-tickets` (credentials include) trả ticket vừa tạo với `status:0,status_label:"Init",is_open:true`.

- [ ] **Step 6: Commit.**

```bash
git add auth/actions/ticket.php functions.php
git commit -m "feat(member): request-ticket REST endpoints

POST /ticket (atomic: no open ticket + <=10/day + burst throttle),
GET /my-tickets (own list w/ status+type labels), POST /ticket/close
(close=3 / cancel=4, owner + still-open guarded)."
```

---

### Task 3: Route + `/ticket` page shell

**Files:**
- Modify: `auth/member-pages.php` (thêm slug `ticket` + rewrite; bump flush guard)
- Create: `auth/templates/ticket.php`

**Interfaces:**
- Consumes: `rf_ticket_types()` (render options). Route var `rf_page=ticket`.
- Produces: DOM hooks cho Task 4 — `[data-rf-ticket-page]`, form `[data-rf-ticket-form]` (fields `type`,`game_title`,`reference_url`,`description`), `[data-rf-ticket-lock]`, `[data-rf-ticket-loading]`, `[data-rf-ticket-list]`, `[data-rf-ticket-empty]`, `[data-rf-ticket-loadmore-wrap]`, `[data-rf-ticket-loadmore]`.

- [ ] **Step 1: Thêm slug vào `rf_member_pages()`.** Đổi `return array( 'wishlist', 'profile' );` → `return array( 'wishlist', 'profile', 'ticket' );`

- [ ] **Step 2: Thêm rewrite rule.** Trong callback `add_action('init', …)`, sau dòng profile rewrite, thêm:

```php
	add_rewrite_rule( '^ticket/?$', 'index.php?rf_page=ticket', 'top' );
```

- [ ] **Step 3: Bump flush guard.** Đổi option key `'rf_member_pages_rw'` (cả `get_option` và `update_option`) `!== '1'` / `'1'` → `'2'` (2 chỗ), để rewrite mới được flush 1 lần:

```php
	if ( get_option( 'rf_member_pages_rw' ) !== '2' ) {
		flush_rewrite_rules();
		update_option( 'rf_member_pages_rw', '2', false );
	}
```

- [ ] **Step 4: Tạo `auth/templates/ticket.php`:**

```php
<?php
/**
 * /ticket — "Create Request" page. Cache-safe shell: identical HTML for everyone.
 * auth.js gates the guest/member views (html.rf-authed), submits the create form to
 * POST rf/v1/ticket, and lists the member's own tickets (with status) from
 * GET rf/v1/my-tickets, wiring the Close/Cancel buttons. When the member already has
 * an open ticket, the form is locked and the notice is shown.
 *
 * @package romsvault
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
$rf_ticket_types = rf_ticket_types();
?>
<section class="py-8 text-gray-900" data-rf-ticket-page>
	<div class="content-container px-4 sm:px-6 lg:px-8 w-full max-w-3xl">
		<h1 class="text-2xl font-bold mb-6 text-romfun-pink"><?php esc_html_e( 'Create Request', 'rom' ); ?></h1>

		<!-- Guest -->
		<div class="rf-guest-only bg-white rounded-2xl shadow-md p-8 text-center">
			<p class="text-gray-600 mb-4"><?php esc_html_e( 'Sign in to submit a request.', 'rom' ); ?></p>
			<button type="button" data-rf-login class="px-6 py-3 bg-romfun-pink text-white rounded-full font-semibold hover:bg-romfun-pink-dark transition"><?php esc_html_e( 'Sign in', 'rom' ); ?></button>
		</div>

		<div class="rf-authed-only">

			<!-- Lock notice: shown by JS when the member already has an open request -->
			<div data-rf-ticket-lock class="hidden mb-4 flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-sm text-amber-700">
				<svg class="w-5 h-5 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				<span><?php esc_html_e( 'You already have an open request. Please close it before creating a new one.', 'rom' ); ?></span>
			</div>

			<!-- Create form -->
			<form data-rf-ticket-form class="bg-white rounded-2xl shadow-md p-6 space-y-4">
				<div>
					<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Request type', 'rom' ); ?></label>
					<select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2">
						<?php foreach ( $rf_ticket_types as $rf_k => $rf_label ) : ?>
							<option value="<?php echo esc_attr( $rf_k ); ?>"><?php echo esc_html( $rf_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Game title', 'rom' ); ?> <span class="text-red-500">*</span></label>
					<input type="text" name="game_title" maxlength="255" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="<?php esc_attr_e( 'e.g. The Legend of Zelda', 'rom' ); ?>">
				</div>
				<div>
					<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Reference link', 'rom' ); ?> <span class="text-gray-400 font-normal">(<?php esc_html_e( 'optional', 'rom' ); ?>)</span></label>
					<input type="url" name="reference_url" maxlength="512" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="https://">
				</div>
				<div>
					<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Details', 'rom' ); ?></label>
					<textarea name="description" rows="4" maxlength="2000" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="<?php esc_attr_e( 'Describe your request…', 'rom' ); ?>"></textarea>
				</div>
				<button type="submit" data-rf-ticket-submit class="px-6 py-3 bg-romfun-pink text-white rounded-full font-semibold hover:bg-romfun-pink-dark transition disabled:opacity-50 disabled:cursor-not-allowed"><?php esc_html_e( 'Submit request', 'rom' ); ?></button>
			</form>

			<!-- Member's own tickets -->
			<h2 class="text-sm font-semibold text-gray-500 mt-8 mb-3"><?php esc_html_e( 'My requests', 'rom' ); ?></h2>
			<div class="bg-white rounded-2xl shadow-md p-5">
				<div data-rf-ticket-loading class="animate-pulse space-y-4">
					<?php for ( $i = 0; $i < 2; $i++ ) : ?>
						<div class="space-y-2"><div class="h-3 bg-gray-200 rounded w-3/4"></div><div class="h-3 bg-gray-200 rounded w-1/2"></div></div>
					<?php endfor; ?>
				</div>
				<ul data-rf-ticket-list class="hidden divide-y divide-gray-100"></ul>
				<div data-rf-ticket-empty class="hidden text-center text-gray-500 py-8"><?php esc_html_e( 'You have not submitted any requests yet.', 'rom' ); ?></div>
				<div data-rf-ticket-loadmore-wrap class="hidden text-center mt-4 pt-4 border-t border-gray-100">
					<button type="button" data-rf-ticket-loadmore class="px-6 py-2 border border-romfun-pink text-romfun-pink rounded-full font-semibold hover:bg-romfun-pink hover:text-white transition text-sm"><?php esc_html_e( 'Load more', 'rom' ); ?></button>
				</div>
			</div>

		</div>
	</div>
</section>
<?php
get_footer();
```

- [ ] **Step 5: Lint.**

Run: `php -l auth/member-pages.php && php -l auth/templates/ticket.php`
Expected: cả hai `No syntax errors detected`.

- [ ] **Step 6: Verify trang load 200 (guest shell).** Mở `https://romsfun.local/ticket/` (đăng xuất). Thấy heading "Create Request" + block "Sign in to submit a request." Không phải 404.
(Nếu 404: option `rf_member_pages_rw` chưa flush — vào wp-admin 1 lần để `admin_init` chạy flush, hoặc Settings → Permalinks → Save.)

- [ ] **Step 7: Commit.**

```bash
git add auth/member-pages.php auth/templates/ticket.php
git commit -m "feat(member): /ticket page route + cache-safe shell

Adds the ticket slug/rewrite (flush guard bumped) and the Create Request
page: guest sign-in prompt, create form, and a container for the member's
own ticket list (filled client-side)."
```

---

### Task 4: JS module (submit form, list, close/cancel, lock)

**Files:**
- Modify: `auth/js/auth.js` (thêm 1 module trang ticket bên trong IIFE/`bind()`)

**Interfaces:**
- Consumes: `rfFetch`, `toast`, `escapeHtml`, `readDisplay` (đã có trong auth.js); endpoints Task 2; DOM hooks Task 3.
- Produces: hành vi trang /ticket (không export gì).

- [ ] **Step 1: Kích hoạt module.** Ngay sau dòng `if (wishPage && readDisplay()) { renderWishlistPage(wishPage); }` (khoảng dòng 318), thêm:

```javascript
    var ticketPage = document.querySelector('[data-rf-ticket-page]');
    if (ticketPage && readDisplay()) { initTicketPage(ticketPage); }
```

- [ ] **Step 2: Thêm hàm `initTicketPage`.** Đặt ngay sau khi hàm `renderWishlistPage` kết thúc (sau dòng `}` đóng renderWishlistPage, ~dòng 444), trong cùng scope của `bind()`:

```javascript
    // ---- /ticket page: create form + own ticket list (status) + close/cancel ----
    function initTicketPage(root) {
      var form     = root.querySelector('[data-rf-ticket-form]');
      var submit   = root.querySelector('[data-rf-ticket-submit]');
      var lock     = root.querySelector('[data-rf-ticket-lock]');
      var loading  = root.querySelector('[data-rf-ticket-loading]');
      var list     = root.querySelector('[data-rf-ticket-list]');
      var empty    = root.querySelector('[data-rf-ticket-empty]');
      var moreWrap = root.querySelector('[data-rf-ticket-loadmore-wrap]');
      var moreBtn  = root.querySelector('[data-rf-ticket-loadmore]');
      var LIMIT = 10;
      var offset = 0;
      var hasOpen = false;

      // Status code -> badge inline colors (inline style avoids Tailwind purge on
      // JS-generated class names). Keys match rf_ticket_statuses().
      var STATUS_STYLE = {
        0: 'background:#eff6ff;color:#1d4ed8', // Init
        1: 'background:#fef9c3;color:#854d0e', // Processing
        2: 'background:#dcfce7;color:#166534', // Solved
        3: 'background:#f3f4f6;color:#374151', // Closed
        4: 'background:#fee2e2;color:#991b1b'  // Cancelled
      };

      function setFormLocked(locked) {
        hasOpen = locked;
        if (submit) submit.disabled = locked;
        if (lock) lock.classList.toggle('hidden', !locked);
      }

      function rowHtml(t) {
        var badge = '<span style="' + (STATUS_STYLE[t.status] || STATUS_STYLE[3])
          + ';font-size:11px;font-weight:600;padding:2px 8px;border-radius:9999px">'
          + escapeHtml(t.status_label) + '</span>';
        var ref = t.reference_url
          ? '<a href="' + encodeURI(t.reference_url) + '" target="_blank" rel="nofollow noopener" class="text-romfun-pink underline break-all">' + escapeHtml(t.reference_url) + '</a>'
          : '';
        var resp = t.response
          ? '<div class="mt-2 text-sm bg-gray-50 border border-gray-100 rounded-lg p-2"><span class="font-semibold text-gray-500">' + escapeHtml('Admin: ') + '</span>' + escapeHtml(t.response) + '</div>'
          : '';
        var actions = t.is_open
          ? '<div class="mt-2 flex gap-2">'
            + '<button type="button" class="rf-ticket-act text-xs px-3 py-1 rounded-full border border-gray-300 hover:bg-gray-100" data-id="' + t.id + '" data-mode="close">' + escapeHtml('Close') + '</button>'
            + '<button type="button" class="rf-ticket-act text-xs px-3 py-1 rounded-full border border-red-300 text-red-600 hover:bg-red-50" data-id="' + t.id + '" data-mode="cancel">' + escapeHtml('Cancel') + '</button>'
            + '</div>'
          : '';
        return '<li class="py-3">'
          + '<div class="flex items-center justify-between gap-2">'
          + '<span class="font-semibold">' + escapeHtml(t.game_title) + '</span>' + badge
          + '</div>'
          + '<div class="text-xs text-gray-400 mt-0.5">' + escapeHtml(t.type_label) + '</div>'
          + (t.description ? '<div class="text-sm text-gray-600 mt-1">' + escapeHtml(t.description) + '</div>' : '')
          + (ref ? '<div class="text-xs mt-1">' + ref + '</div>' : '')
          + resp + actions
          + '</li>';
      }

      function loadPage(reset) {
        if (reset) { offset = 0; hasOpen = false; }
        rfFetch('my-tickets?offset=' + offset + '&limit=' + LIMIT)
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (j) {
            if (loading) loading.classList.add('hidden');
            if (!j || !j.ok) { if (empty) empty.classList.remove('hidden'); return; }
            if (reset) list.innerHTML = '';
            j.tickets.forEach(function (t) {
              if (t.is_open) hasOpen = true;
              list.insertAdjacentHTML('beforeend', rowHtml(t));
            });
            offset += j.tickets.length;
            var any = list.children.length > 0;
            list.classList.toggle('hidden', !any);
            if (empty) empty.classList.toggle('hidden', any);
            if (moreWrap) moreWrap.classList.toggle('hidden', !j.has_more);
            setFormLocked(hasOpen);
          })
          .catch(function () { if (loading) loading.classList.add('hidden'); if (empty) empty.classList.remove('hidden'); });
      }

      if (moreBtn) moreBtn.addEventListener('click', function () { loadPage(false); });

      // Create
      if (form) form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (hasOpen) { toast('You already have an open request.'); return; }
        var fd = new FormData(form);
        var title = String(fd.get('game_title') || '').trim();
        if (!title) { toast('Please enter the game title.'); return; }
        if (submit) submit.disabled = true;
        rfFetch('ticket', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            type: parseInt(fd.get('type'), 10) || 0,
            game_title: title,
            reference_url: String(fd.get('reference_url') || '').trim(),
            description: String(fd.get('description') || '').trim()
          })
        })
          .then(function (r) { return r.json().then(function (b) { return { status: r.status, body: b }; }); })
          .then(function (res) {
            if (res.status === 200 && res.body && res.body.ok) {
              toast(res.body.message, 'success');
              form.reset();
              loadPage(true); // re-list -> will lock the form (now has an open ticket)
            } else {
              if (submit) submit.disabled = false;
              toast(res.body && res.body.message ? res.body.message : 'Could not submit your request.');
              if (res.status === 401) openModal();
            }
          })
          .catch(function () { if (submit) submit.disabled = false; toast('Network error. Please try again.'); });
      });

      // Close / Cancel (delegated)
      if (list) list.addEventListener('click', function (e) {
        var btn = e.target.closest('.rf-ticket-act');
        if (!btn) return;
        var id = parseInt(btn.getAttribute('data-id'), 10) || 0;
        var mode = btn.getAttribute('data-mode');
        if (!id) return;
        btn.disabled = true;
        rfFetch('ticket/close', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: id, mode: mode })
        })
          .then(function (r) { return r.json().then(function (b) { return { status: r.status, body: b }; }); })
          .then(function (res) {
            if (res.status === 200 && res.body && res.body.ok) {
              toast(res.body.message, 'success');
              loadPage(true); // re-list -> unlocks the form
            } else {
              btn.disabled = false;
              toast(res.body && res.body.message ? res.body.message : 'Could not update your request.');
            }
          })
          .catch(function () { btn.disabled = false; toast('Network error. Please try again.'); });
      });

      loadPage(true);
    }
```

- [ ] **Step 3: Lint.**

Run: `node --check auth/js/auth.js`
Expected: (không output) exit 0.

- [ ] **Step 4: Verify trong browser (member đăng nhập).** Mở `/ticket/`:
  - Danh sách load; nếu chưa có ticket → "You have not submitted any requests yet."
  - Submit form 1 request → toast success, form khóa (submit disabled) + notice vàng hiện, ticket xuất hiện trong list với badge "Init".
  - Bấm **Cancel** trên ticket đó → toast, list refresh (status "Cancelled", nút biến mất), form mở khóa lại → tạo được request mới.

- [ ] **Step 5: Commit.**

```bash
git add auth/js/auth.js
git commit -m "feat(member): /ticket page client logic

Submit create form to POST /ticket, list own tickets from /my-tickets with
status badges, wire Close/Cancel, and lock the form while an open ticket
exists. Reuses rfFetch/toast/escapeHtml."
```

> **Lưu ý:** không sửa `output.css`/không thêm class Tailwind mới — badge status dùng inline style; các class còn lại đều là utility có sẵn.

---

### Task 5: Profile menu card "Create Request"

**Files:**
- Modify: `auth/templates/profile.php` (thêm 1 card link `/ticket`)

**Interfaces:**
- Consumes: route `/ticket` (Task 3). Không JS mới.

- [ ] **Step 1: Thêm card.** Ngay sau khối card Wishlist (thẻ `</a>` đóng card Wishlist, trước comment "Activity: inline accordion cards"), thêm:

```php
            <!-- Create Request: navigates to the dedicated /ticket page -->
            <a href="<?php echo esc_url( home_url( '/ticket/' ) ); ?>"
               class="rf-stat-card flex items-center justify-between gap-4 bg-white rounded-2xl shadow-md p-5 mt-4 hover:shadow-lg transition">
                <span class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-romfun-pink/10 text-romfun-pink shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-gray-700"><?php esc_html_e( 'Create Request', 'rom' ); ?></span>
                        <span class="block text-xs text-gray-400"><?php esc_html_e( 'Request a game or file', 'rom' ); ?></span>
                    </span>
                </span>
                <svg class="w-5 h-5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
```

- [ ] **Step 2: Lint.**

Run: `php -l auth/templates/profile.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Verify.** Mở `/profile/` (member đăng nhập) → thấy card "Create Request" dưới card Wishlist, click sang `/ticket/`.

- [ ] **Step 4: Commit.**

```bash
git add auth/templates/profile.php
git commit -m "feat(member): add Create Request card to profile"
```

---

### Task 6: Admin page (Requests) — list + change status + reply + delete

**Files:**
- Create: `auth/admin-ticket.php`
- Modify: `functions.php` (require trong khối admin, sau dòng 312 `admin-wishlists.php`)

**Interfaces:**
- Consumes: `rf_ticket_table()`, `rf_ticket_statuses()`, `rf_ticket_types()`, `rf_member_table()`, `RF_TICKET_DESC_MAXLEN`; admin utils `rf_admin_per_page`, `rf_admin_per_page_field`, `rf_admin_today_badge`, `rf_admin_today_count`, `rf_admin_time_ago`.
- Produces: options page `ticket`; AJAX `app_ticket_update_status`, `app_ticket_delete_row`, `app_ticket_delete_rows`.

- [ ] **Step 1: Tạo `auth/admin-ticket.php`:**

```php
<?php
/**
 * Requests admin — wp-admin management for member request tickets (member_ticket).
 * ACF options page + paginated table with inline status change + admin reply +
 * row/bulk delete. Schema/maps live in setting-member.php; front-end submission is
 * the REST endpoint rf/v1/ticket (auth/actions/ticket.php). Admin-only (manage_options).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function app_ticket_init() {
	if ( function_exists( 'acf_add_options_page' ) ) {
		acf_add_options_page( array(
			'page_title' => 'Requests',
			'menu_title' => 'Requests',
			'menu_slug'  => 'ticket',
			'icon_url'   => 'dashicons-tickets',
			'capability' => 'manage_options',
			'id'         => 'ticket',
			'post_id'    => 'ticket',
			'position'   => 3.2,
		) );
	}
	if ( function_exists( 'acf_add_local_field_group' ) ) {
		acf_add_local_field_group( array(
			'key'      => 'group_ticket_admin',
			'title'    => 'Requests',
			'fields'   => array(),
			'location' => array(
				array(
					array( 'param' => 'options_page', 'operator' => '==', 'value' => 'ticket' ),
				),
			),
		) );
	}
}
add_action( 'init', 'app_ticket_init' );

/**
 * Update a ticket's status + admin reply. Rejects tickets already terminal (3,4) and
 * the Cancelled status (4) — admins finish via Closed (3), which stamps closed_by=2.
 */
function app_ticket_update_status() {
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
	if ( mb_strlen( $response ) > RF_TICKET_DESC_MAXLEN ) {
		$response = mb_substr( $response, 0, RF_TICKET_DESC_MAXLEN );
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
	$result = $wpdb->delete( rf_ticket_table(), array( 'id' => $id ), array( '%d' ) );
	if ( $result ) {
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
	$result       = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN({$placeholders})", ...$ids ) );
	if ( $result !== false ) {
		wp_send_json_success( array( 'message' => sprintf( __( '%d item(s) deleted.', 'rom' ), $result ) ) );
	}
	wp_send_json_error( array( 'message' => __( 'Error. Please contact administrator.', 'rom' ) ) );
}
add_action( 'wp_ajax_app_ticket_delete_rows', 'app_ticket_delete_rows' );

/**
 * Render the table below the ACF options form (ob_start capture pattern, like Report).
 */
add_action( 'toplevel_page_ticket', 'app_before_ticket_page', 5 );
function app_before_ticket_page() {
	ob_start();
}

add_action( 'toplevel_page_ticket', 'app_after_ticket_page', 20 );
function app_after_ticket_page() {
	$content = ob_get_clean();
	$count   = 1;

	ob_start();
	global $wpdb;

	$statuses = rf_ticket_statuses();
	$types    = rf_ticket_types();

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

	$update_nonce = wp_create_nonce( 'ticket_update_nonce' );
	?>
	<style>
		.rf-ticket-wrap { overflow-x: auto; margin: 20px 0; width: 100%; }
		.rf-ticket-wrap table { min-width: 1100px; width: 100%; }
		.rf-ticket-wrap th, .rf-ticket-wrap td { padding: 10px; vertical-align: top; word-wrap: break-word; }
		.rf-ticket-wrap textarea { width: 100%; min-height: 54px; }
		.rf-ticket-wrap .col-title { min-width: 160px; }
		.rf-ticket-wrap .col-desc, .rf-ticket-wrap .col-resp { min-width: 220px; max-width: 320px; }
		.rf-ticket-pagination { margin: 20px 0; display: flex; align-items: center; gap: 10px; }
		.rf-ticket-pagination .page-numbers { padding: 5px 10px; border: 1px solid #ccc; border-radius: 3px; text-decoration: none; }
		.rf-ticket-pagination .page-numbers.current { background: #0073aa; color: #fff; border-color: #0073aa; }
	</style>

	<form method="get" style="margin:12px 0">
		<input type="hidden" name="page" value="ticket" />
		<label><?php esc_html_e( 'Status', 'rom' ); ?>:
			<select name="status" onchange="this.form.submit()">
				<option value=""><?php esc_html_e( 'All', 'rom' ); ?></option>
				<?php foreach ( $statuses as $rf_k => $rf_label ) : ?>
					<option value="<?php echo esc_attr( $rf_k ); ?>" <?php selected( $filter, $rf_k ); ?>><?php echo esc_html( $rf_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
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
						<select class="rf-ticket-status" <?php disabled( $is_terminal ); ?>>
							<?php foreach ( $statuses as $rf_k => $rf_label ) :
								// Admin can pick 0..3; Cancelled(4) only shown (disabled) when already set.
								if ( $rf_k === 4 && $st !== 4 ) { continue; } ?>
								<option value="<?php echo esc_attr( $rf_k ); ?>" <?php selected( $st, $rf_k ); ?> <?php disabled( $rf_k === 4 ); ?>><?php echo esc_html( $rf_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
					<td class="col-resp">
						<textarea class="rf-ticket-response" <?php disabled( $is_terminal ); ?>><?php echo esc_textarea( $row->response_content ); ?></textarea>
						<button type="button" class="button button-small button-primary rf-ticket-save" <?php disabled( $is_terminal ); ?>><?php esc_html_e( 'Save', 'rom' ); ?></button>
					</td>
					<td style="white-space:nowrap">
						<?php echo esc_html( rf_admin_time_ago( (string) $row->created_at, false ) ); ?>
						<?php if ( $row->updated_at && $row->updated_at !== $row->created_at ) : ?>
							<br><small style="color:#888"><?php esc_html_e( 'upd', 'rom' ); ?> <?php echo esc_html( rf_admin_time_ago( (string) $row->updated_at, false ) ); ?></small>
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
					.done(function (r) { alert(r && r.data ? r.data.message : 'Done'); if (r && r.success) location.reload(); else save.disabled = false; })
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
		<div class="acf-admin-notice notice notice-info"><p><?php esc_html_e( 'No requests yet.', 'rom' ); ?></p></div>
	<?php endif;

	$my_content = ob_get_clean();
	$content    = str_replace(
		'<div id="postbox-container-2" class="postbox-container">',
		'<div id="postbox-container-2" class="postbox-container">' . $my_content,
		$content,
		$count
	);
	echo $content;
}
```

- [ ] **Step 2: Wire require.** Trong `functions.php`, khối admin, ngay sau dòng 312 (`.../admin-wishlists.php`), thêm:

```php
	require get_template_directory() . '/auth/admin-ticket.php'; // Requests admin page (member_ticket)
```

- [ ] **Step 3: Lint.**

Run: `php -l auth/admin-ticket.php && php -l functions.php`
Expected: cả hai `No syntax errors detected`.

- [ ] **Step 4: Verify trong wp-admin.**
  - Menu **Requests** xuất hiện; bảng liệt kê ticket đã tạo ở Task 2/4.
  - Đổi status 1 ticket sang "Processing", nhập Response "Đang tìm file", bấm **Save** → reload thấy status mới + response.
  - Về trang `/ticket/` (member) → ticket đó hiện badge "Processing" + dòng "Admin: Đang tìm file".
  - Đổi 1 ticket sang "Closed" → member thấy status Closed, nút Close/Cancel biến mất, form mở khóa. Row admin của ticket Closed: select + textarea + Save bị disabled.
  - Bấm Delete (check 1 row) → xóa được.

- [ ] **Step 5: Commit.**

```bash
git add auth/admin-ticket.php functions.php
git commit -m "feat(admin): Requests admin page for member tickets

ACF options page + paginated table with status filter, inline status
change + admin reply (closed_by=2 on Closed, terminal-guarded), and
row/bulk delete. Mirrors the Report admin pattern."
```

---

## Self-Review

**1. Spec coverage:**
- §2 schema → Task 1 (11 cột, 2 index, version bump).
- §3 mapping arrays → Task 1.
- §4.1 rate (throttle + 1-open + 10/day, atomic) → Task 2 `rf_rest_ticket_create`.
- §4.2 lifecycle (Closed/Cancelled terminal) → Task 2 (close/cancel guarded) + Task 6 (admin 0..3, terminal-guarded, closed_by).
- §4.3 quyền (blocked read-only, member close own, admin manage) → Task 2 + Task 6.
- §5 REST 3 endpoints → Task 2.
- §6 /ticket page → Task 3 + Task 4.
- §7 profile card → Task 5.
- §8 admin page (status select + response + delete + filter) → Task 6.
- §9 guardrails → Global Constraints + mỗi task có lint/escape/prepare/nonce.

**2. Placeholder scan:** không có TBD/TODO; mọi step có code thật hoặc lệnh verify cụ thể.

**3. Type consistency:**
- `rf_ticket_table/_statuses/_types` định nghĩa Task 1, dùng nhất quán Task 2/6.
- Endpoint paths `ticket`, `my-tickets`, `ticket/close` khớp giữa Task 2 (đăng ký) và Task 4 (gọi qua rfFetch, relative tới `RF_AUTH.rest`).
- JSON keys `/my-tickets` (`status,status_label,is_open,type_label,game_title,reference_url,description,response,created_at,updated_at`) khớp giữa Task 2 (trả) và Task 4 (đọc).
- Nonce actions `ticket_update_nonce` / `ticket_delete_nonce` khớp giữa render (Task 6) và AJAX handler (Task 6).
- DOM hooks `[data-rf-ticket-*]` khớp giữa Task 3 (template) và Task 4 (JS query).

---

## Ghi chú thực thi

- **Không có test framework** trong theme này → mỗi task verify bằng `php -l` / `node --check` + curl/browser thực tế (đúng chuẩn CLAUDE.md, không viết PHPUnit — YAGNI).
- **Thứ tự bắt buộc:** 1 → 2 → 3 → 4 → 6 (Task 5 độc lập, chèn đâu cũng được sau Task 3). Task 4 cần Task 2+3; Task 6 cần Task 1 (+ dữ liệu từ 2/4 để test).
- **Rewrite flush:** nếu `/ticket` ra 404, vào wp-admin 1 lần (trigger `admin_init` flush) hoặc Settings → Permalinks → Save.

---

## Amendments — 2026-08-06 (đã áp vào Task 1 & 2 sau security review)

- **Task 1 CREATE TABLE:** `description TEXT` → `VARCHAR(500)`; thêm `open_slot BIGINT UNSIGNED AS ( CASE WHEN status IN (0,1,2) THEN member_id END ) STORED` + `UNIQUE KEY uq_open_slot` → DB-enforce 1 open ticket/member (race-safe).
- **Task 2:** `RF_TICKET_DESC_MAXLEN` 2000 → 500; `reference_url` dùng `mb_strlen`; sau INSERT nếu `false` + `$wpdb->last_error` chứa "Duplicate" → trả `open_exists` 409; bọc INSERT bằng `$wpdb->suppress_errors()`.
- Bảng local đã `ALTER` tương ứng; invariant đã test trực tiếp (insert open thứ 2 cùng member bị duplicate reject, insert terminal OK). Fresh install lấy schema mới từ CREATE TABLE.
- Chi tiết lý do xem spec §Amendments.