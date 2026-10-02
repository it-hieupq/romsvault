<?php
/**
 * Member schema (plan §14.1 / §15). Creates the custom member tables and migrates
 * the reports table. Self-healing: runs once per RF_MEMBER_DB_VERSION on theme
 * switch, and guarded on admin_init so it also applies to the already-active theme.
 * Front-end never triggers this (no DB writes on cached page loads).
 *
 *   {prefix}member           — source of truth for members (NOT wp_users)
 *   {prefix}member_comments  — fast "my comments" index
 *   {prefix}member_wishlist  — per-member wishlist rows
 *   {prefix}member_report    — member-owned post reports (Report admin page)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RF_MEMBER_DB_VERSION' ) ) {
	define( 'RF_MEMBER_DB_VERSION', '7' );
}

function rf_member_table() {
	global $wpdb;
	return $wpdb->prefix . 'member';
}

function rf_member_comments_table() {
	global $wpdb;
	return $wpdb->prefix . 'member_comments';
}

function rf_member_wishlist_table() {
	global $wpdb;
	return $wpdb->prefix . 'member_wishlist';
}

function rf_member_report_table() {
	global $wpdb;
	return $wpdb->prefix . 'member_report';
}

function rf_member_rating_table() {
	global $wpdb;
	return $wpdb->prefix . 'member_rating';
}

function rf_ticket_table() {
	global $wpdb;
	return $wpdb->prefix . 'member_ticket';
}

function rf_member_contact_table() {
	global $wpdb;
	return $wpdb->prefix . 'member_contact';
}

/**
 * Ticket status map (stored tinyint => UI label). Terminal statuses are 3 (Closed)
 * and 4 (Cancelled); open statuses are 0/1/2. Shared by the REST layer, the /ticket
 * page and the admin page so the code<->label mapping lives in one place.
 */
function rf_ticket_statuses(): array {
	return array(
		0 => __( 'Init', 'romsfun' ),
		1 => __( 'Processing', 'romsfun' ),
		2 => __( 'Solved', 'romsfun' ),
		3 => __( 'Closed', 'romsfun' ),
		4 => __( 'Cancelled', 'romsfun' ),
	);
}

/**
 * Request-type map (stored tinyint => UI label). Fixed enum (not admin-configurable).
 */
function rf_ticket_types(): array {
	return array(
		0 => __( 'Add new game', 'romsfun' ),
		1 => __( 'Add / Update file for existing game', 'romsfun' ),
		2 => __( 'Other', 'romsfun' ),
	);
}

/**
 * Per-status color map (keyed by the same status codes as rf_ticket_statuses()). Single
 * source of truth for status colors — mirrored to the client as JSON on the /request page
 * and reused by the admin table. `bg`/`text` = pill; `main` = the stepper fill color.
 */
function rf_ticket_status_colors() : array {
	return array(
		0 => array( 'bg' => '#f1f5f9', 'text' => '#475569', 'main' => '#94a3b8' ), // Init — slate (neutral)
		1 => array( 'bg' => '#fef3c7', 'text' => '#b45309', 'main' => '#f59e0b' ), // Processing — amber
		2 => array( 'bg' => '#ffe4e6', 'text' => '#be123c', 'main' => '#e11d48' ), // Solved — rose (brand)
		3 => array( 'bg' => '#e2e8f0', 'text' => '#334155', 'main' => '#64748b' ), // Closed — slate (dark)
		4 => array( 'bg' => '#fee2e2', 'text' => '#991b1b', 'main' => '#b91c1c' ), // Cancelled — deep red
	);
}

/**
 * Per-member cache-key convention. One structured, colon-namespaced shape for every
 * member-scoped cache:  member:{member_id}:{entity}  (entity = short feature slug, e.g.
 * 'wishlist', 'tickets', 'stats'). No theme prefix on the key itself — Redis keys are
 * already namespaced by WP_REDIS_PREFIX (wp-config). Bump the slug if a payload's shape
 * changes. NOTE: this is the transient name, so the fn name keeps its rf_ prefix.
 */
function rf_member_cache_key( string $entity, $member_id ): string {
	return 'member:' . (int) $member_id . ':' . $entity;
}

/**
 * Create/upgrade the member schema. Idempotent (CREATE TABLE IF NOT EXISTS +
 * column/index existence checks). Table names come from $wpdb->prefix only —
 * no user input — so string-built DDL here is safe.
 */
function rf_member_install_schema() {
	global $wpdb;

	$member          = rf_member_table();
	$member_comments = rf_member_comments_table();
	$charset         = $wpdb->get_charset_collate();

	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS `{$member}` (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			google_sub    VARCHAR(32)     NOT NULL,
			email         VARCHAR(191)    NOT NULL,
			name          VARCHAR(255)    NULL,
			avatar_url    VARCHAR(512)    NULL,
			blocked       TINYINT(1)      NOT NULL DEFAULT 0,
			created_at    DATETIME        NOT NULL,
			last_login_at DATETIME        NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_google_sub (google_sub),
			KEY idx_email (email),
			KEY idx_created (created_at),
			KEY idx_blocked_created (blocked, created_at)
		) ENGINE=InnoDB {$charset}"
	);

	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS `{$member_comments}` (
			comment_id BIGINT UNSIGNED NOT NULL,
			member_id  BIGINT UNSIGNED NOT NULL,
			post_id    BIGINT UNSIGNED NOT NULL,
			status     VARCHAR(20)     NOT NULL,
			created_at DATETIME        NOT NULL,
			PRIMARY KEY (comment_id),
			KEY idx_member_created (member_id, created_at)
		) ENGINE=InnoDB {$charset}"
	);

	// Wishlist: one row per (member, post). PK prevents duplicates; the
	// member+created index lists a member's wishlist newest-first. idx_post
	// serves the post-lifecycle fan-out (SELECT/DELETE WHERE post_id — PK can't,
	// post_id isn't its leftmost column). Cap (30) is enforced in the add
	// endpoint, not the schema.
	$wishlist = rf_member_wishlist_table();
	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS `{$wishlist}` (
			member_id  BIGINT UNSIGNED NOT NULL,
			post_id    BIGINT UNSIGNED NOT NULL,
			created_at DATETIME        NOT NULL,
			PRIMARY KEY (member_id, post_id),
			KEY idx_member_created (member_id, created_at),
			KEY idx_post (post_id)
		) ENGINE=InnoDB {$charset}"
	);

	// Reports: one row per submitted report (member-owned). idx_member_created
	// serves BOTH the member profile ("my reports", newest-first) and the per-submit
	// de-dup check (member_id [+ post_id] within a window); idx_created sorts the
	// admin Report table (all reports) newest-first. url_report stores the post
	// permalink (denormalized) so a member still sees their report after the post is
	// deleted. No post_id index: nothing queries reports by post (reports are a kept
	// audit log, not cleaned up on post deletion) — add one only if that changes.
	$report = rf_member_report_table();
	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS `{$report}` (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id      BIGINT UNSIGNED NOT NULL,
			post_id        BIGINT UNSIGNED NOT NULL,
			report_type    VARCHAR(191)    NOT NULL,
			report_content TEXT            NULL,
			url_report     VARCHAR(128)    NOT NULL,
			created_at     DATETIME        NOT NULL,
			PRIMARY KEY (id),
			KEY idx_member_created (member_id, created_at),
			KEY idx_created (created_at)
		) ENGINE=InnoDB {$charset}"
	);

	// Member ratings: one review per (member, post) — UNIQUE enforces "rate once".
	// The table is storage-only (review score + text for the Play-Store list and the
	// profile "my ratings"); it does NOT compute the star average — that stays the
	// incremental rating_avg/rating_count post_meta (§ RATING_PLAN §3.2). status:
	// 1=visible, 0=hidden by admin (hides from the list only). idx_post_status_created
	// serves the public reviews list (visible, newest-first); idx_member_created serves
	// the member's own list. content is capped at 164 chars in the endpoint (VARCHAR 191
	// leaves headroom for utf8mb4).
	$rating = rf_member_rating_table();
	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS `{$rating}` (
			id         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
			member_id  BIGINT UNSIGNED  NOT NULL,
			post_id    BIGINT UNSIGNED  NOT NULL,
			score      TINYINT UNSIGNED NOT NULL,
			content    VARCHAR(191)     NULL,
			status     TINYINT(1)       NOT NULL DEFAULT 1,
			created_at DATETIME         NOT NULL,
			updated_at DATETIME         NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_member_post (member_id, post_id),
			KEY idx_post_status_created (post_id, status, created_at),
			KEY idx_member_created (member_id, created_at)
		) ENGINE=InnoDB {$charset}"
	);

	// Member request tickets: one row per submitted request. status is a tinyint
	// (0 Init,1 Processing,2 Solved,3 Closed,4 Cancelled); terminal = 3/4. game_title
	// is free text (no post link). response_content is admin-authored progress the
	// member reads back. idx_member_created serves the member's list + the daily-count
	// check (filters member_id); idx_status_created serves the admin list/filter by
	// status, newest-first. open_slot is a STORED generated column = member_id while the
	// ticket is open (status 0/1/2), NULL once terminal (3/4); the UNIQUE key uq_open_slot
	// makes "at most one open ticket per member" a DB-level invariant — two concurrent
	// creates can't both win (the 2nd hits a duplicate key), and because a new ticket
	// can't be created until the previous one is closed, it also bounds the create rate.
	$ticket = rf_ticket_table();
	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS `{$ticket}` (
			id               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
			member_id        BIGINT UNSIGNED  NOT NULL,
			request_type     TINYINT UNSIGNED NOT NULL,
			game_title       VARCHAR(255)     NOT NULL,
			reference_url    VARCHAR(255)     NULL,
			description      VARCHAR(500)     NULL,
			response_content TEXT             NULL,
			status           TINYINT UNSIGNED NOT NULL DEFAULT 0,
			closed_by        TINYINT UNSIGNED NULL,
			created_at       DATETIME         NOT NULL,
			updated_at       DATETIME         NOT NULL,
			open_slot        BIGINT UNSIGNED  AS ( CASE WHEN status IN (0,1,2) THEN member_id END ) STORED,
			PRIMARY KEY (id),
			UNIQUE KEY uq_open_slot (open_slot),
			KEY idx_member_created (member_id, created_at),
			KEY idx_status_created (status, created_at)
		) ENGINE=InnoDB {$charset}"
	);

	// Member contact messages: one row per message a member sends the admin from the
	// /contact-us page. Free-form title/content, no post link, no lifecycle. The admin
	// reads them in the Contact inbox and may write response (TEXT) — a non-NULL response
	// IS the "admin replied" signal, so no status column. title is capped at 64 and content
	// at 512 in the endpoint (matching the VARCHAR sizes). idx_member_created serves the
	// profile history list + the daily-count cap (filters member_id, newest-first);
	// idx_created sorts the admin inbox (all messages) newest-first.
	$contact = rf_member_contact_table();
	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS `{$contact}` (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id  BIGINT UNSIGNED NOT NULL,
			title      VARCHAR(64)     NOT NULL,
			content    VARCHAR(512)    NOT NULL,
			response   TEXT            NULL,
			created_at DATETIME        NOT NULL,
			updated_at DATETIME        NOT NULL,
			PRIMARY KEY (id),
			KEY idx_member_created (member_id, created_at),
			KEY idx_created (created_at)
		) ENGINE=InnoDB {$charset}"
	);

	// Existing installs (member table created at v2) miss idx_created — the admin
	// list sorts by created_at DESC, so add it defensively to avoid a filesort.
	if ( empty( $wpdb->get_results( "SHOW INDEX FROM `{$member}` WHERE Key_name = 'idx_created'" ) ) ) {
		$wpdb->query( "ALTER TABLE `{$member}` ADD KEY idx_created (created_at)" );
	}
}

/**
 * Run the migration only when the stored version is behind.
 */
function rf_member_maybe_upgrade() {
	if ( get_option( 'rf_member_db_version' ) === RF_MEMBER_DB_VERSION ) {
		return;
	}
	rf_member_install_schema();
	update_option( 'rf_member_db_version', RF_MEMBER_DB_VERSION, false );
}

add_action( 'after_switch_theme', 'rf_member_install_schema' );
add_action( 'admin_init', 'rf_member_maybe_upgrade' );
