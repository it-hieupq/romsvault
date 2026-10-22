<?php
/**
 * k functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package romsvault
 */

if ( ! function_exists( 'k_setup' ) ) :
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function k_setup() {
	load_theme_textdomain( 'rom', get_template_directory() . '/languages' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	add_theme_support( 'title-tag' );

	add_theme_support( 'post-thumbnails' );

	/*
	 * Switch default core markup for search form, comment form, and comments
	 * to output valid HTML5.
	 */
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
	) );
	
	/**
	 * Add image sizes
	 */
	add_image_size( 'thumbnail', 300, 9999, false );

	/**
	 * Add excerpt to page
	 */
	add_post_type_support( 'page', 'excerpt' );
}
endif;
add_action( 'after_setup_theme', 'k_setup' );

/**
 * Register nav menu locations on init instead of after_setup_theme so the
 * translated labels don't load the 'rom' textdomain before init
 * (WP 6.7 _load_textdomain_just_in_time notice).
 */
function k_register_nav_menus() {
	// This theme uses wp_nav_menu() in one location.
	register_nav_menus( array(
		'primary'    => esc_html__( 'Primary Navigation', 'rom' ),
		'site-nav'   => esc_html__( 'Site navigation', 'rom' ),
		'footer-nav' => esc_html__( 'Footer navigation', 'rom' ),
	) );
}
add_action( 'init', 'k_register_nav_menus' );

/**
 * Remove medium_large size
 */
function k_remove_image_sizes( $sizes) {
	unset( $sizes['medium'] );
	unset( $sizes['medium_large'] );
    unset( $sizes['large'] );
    unset( $sizes['1536x1536'] );
    unset( $sizes['2048x2048'] );
	return $sizes;
}
add_filter('intermediate_image_sizes_advanced', 'k_remove_image_sizes');

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function k_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'k_content_width', 640 );
}
add_action( 'after_setup_theme', 'k_content_width', 0 );

/**
 * Enqueue scripts and styles.
 */
function rom_enqueue_scripts() {
    wp_enqueue_style(
        'main-style',
        get_stylesheet_uri(),
        [],
        filemtime( get_stylesheet_directory() . '/style.css' )
    );


    wp_enqueue_style(
		'tailwind',
		get_template_directory_uri() . '/assets/css/output.css',
		array(), // Dependencies
		filemtime(get_template_directory() . '/assets/css/output.css')
	);

	// Member auth UI (P2.0). Cache-safe: no login state in PHP; JS + localStorage only.
	// Auth CSS lives in style.css (enqueued above as 'main-style').
	// Toast notifications — standalone (no jQuery). Loaded first so auth + main can use it.
	wp_enqueue_script(
		'rf-toast',
		get_template_directory_uri() . '/js/toast.js',
		array(),
		filemtime( get_template_directory() . '/js/toast.js' ),
		true
	);

	wp_enqueue_script(
		'rf-auth',
		get_template_directory_uri() . '/auth/js/auth.js',
		array( 'rf-toast' ),
		filemtime( get_template_directory() . '/auth/js/auth.js' ),
		true
	);
	// Static config for the auth UI (same for everyone -> stays cache-safe).
	// Uses GOOGLE_CLIENT_ID from wp-config.php (public client id). The GSI ID-token
	// flow needs only the client id; GOOGLE_CLIENT_SECRET / GOOGLE_REDIRECT_URI are unused here.
	wp_localize_script(
		'rf-auth',
		'RF_AUTH',
		array(
			'clientId' => defined( 'GOOGLE_CLIENT_ID' ) ? GOOGLE_CLIENT_ID : '',
			'rest'     => esc_url_raw( rest_url( 'rf/v1/' ) ),
		)
	);
				
	// Check post type first to determine dependencies
	global $wp_query;
	$post_type = get_post_type();
	$site_js_deps = array('jquery', 'rf-toast');
	
	$is_download_page = ! empty( $wp_query->query_vars['download'] );
	
	// RateYo removed: rating stars are display-only (custom renderer in js/main.js).

    wp_enqueue_script(
        'main-js',
        get_template_directory_uri() . '/js/main.js',
        $site_js_deps,
        filemtime( get_template_directory() . '/js/main.js' ),
        true
    );
	
	// Localize script with AJAX URL, comment nonce, and rating nonce.
	// site_url comes from WP_SITEURL (wp-config) so JS never hardcodes the domain.
	$localize_data = array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'site_url' => untrailingslashit( site_url() ),
	);
	
	// Check if dynamic nonce should be enabled (for specific pages)
	require_once get_template_directory() . '/inc/utility-post-type.php';
	$enable_dynamic_nonce = should_load_dynamic_nonce();
	$localize_data['enable_dynamic_nonce'] = $enable_dynamic_nonce;
	
	// Rating is member-only now (POST rf/v1/rating) — no k_rating nonce needed.

	wp_localize_script( 'main-js', 'ajax', $localize_data );

	// Live search suggestions. Inline JSON rather than wp_localize_script, which
	// would turn the numbers and booleans into strings.
	$search_config = rom_search_js_config();
	if ( $search_config ) {
		wp_add_inline_script( 'main-js', 'window.romSearch = ' . wp_json_encode( $search_config, JSON_UNESCAPED_SLASHES ) . ';', 'before' );
	}

	// Member rating & reviews UI (rf/v1/rating) — single valid-post-type + download page.
	if ( ( is_singular() && valid_post_type( $post_type ) ) || $is_download_page ) {
		wp_enqueue_script(
			'rf-rating',
			get_template_directory_uri() . '/js/rating.js',
			array( 'rf-auth' ),
			filemtime( get_template_directory() . '/js/rating.js' ),
			true
		);
	}

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	// lightGallery for post/rom
	if (is_singular() && valid_post_type($post_type) ) {

		wp_enqueue_style(
			'lightgallery',
			get_template_directory_uri() . '/assets/css/lightgallery.min.css',
			[],
            '2.8.3'
		);

		wp_enqueue_style(
			'lg-thumbnail',
			get_template_directory_uri() . '/assets/css/lg-thumbnail.min.css',
			['lightgallery'],
			'2.8.3'
		);

		// Plays the youtube_url preview inside the lightbox instead of leaving the site.
		wp_enqueue_style(
			'lg-video',
			get_template_directory_uri() . '/assets/css/lg-video.min.css',
			['lightgallery'],
			'2.8.3'
		);

		// lightGallery JS - Must be enqueued with proper dependencies
		wp_enqueue_script(
			'lightgallery',
			get_template_directory_uri() . '/assets/js/lightgallery.min.js',
			[],
			'2.8.3',
			true
		);

		wp_enqueue_script(
			'lg-thumbnail',
			get_template_directory_uri() . '/assets/js/lg-thumbnail.min.js',
			['lightgallery'],
			'2.8.3',
			true
		);

		wp_enqueue_script(
			'lg-video',
			get_template_directory_uri() . '/assets/js/lg-video.min.js',
			['lightgallery'],
			'2.8.3',
			true
		);

		// File JS khởi tạo (tự viết)
        wp_enqueue_script(
            'lightgallery-init',
            get_template_directory_uri() . '/assets/js/lightgallery-init.js',
            ['jquery', 'lightgallery', 'lg-thumbnail', 'lg-video'],
            filemtime( get_template_directory() . '/assets/js/lightgallery-init.js' ),
            true
        );
	}
}
add_action( 'wp_enqueue_scripts', 'rom_enqueue_scripts' );

/**
 * Enqueue admin scripts and styles.
 */
function rom_admin_scripts() {
	wp_enqueue_script( 'admin-js', get_template_directory_uri() . '/js/admin.js', array('jquery'), 2, true );
	wp_localize_script( 'admin-js', 'ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
}
add_action( 'admin_enqueue_scripts', 'rom_admin_scripts' );

// 1. customize ACF path
//add_filter('acf/settings/path', 'k_acf_settings_path');
function k_acf_settings_path( $path ) {
	$path = get_stylesheet_directory() . '/inc/acf/';
	return $path;
}

// 2. customize ACF dir
//add_filter('acf/settings/dir', 'k_acf_settings_dir');
function k_acf_settings_dir( $dir ) {
	$dir = get_stylesheet_directory_uri() . '/inc/acf/';
	return $dir;
}

// 3. Hide ACF field group menu item
 add_filter('acf/settings/show_admin', '__return_false');

// 4. Include ACF
//include_once( get_stylesheet_directory() . '/inc/acf/acf.php' );
//include_once( get_stylesheet_directory() . '/inc/acf-quick-edit-fields/index.php' );

/**
 * Custom fields
 */
require get_template_directory() . '/setting/setting-post.php';
require get_template_directory() . '/setting/setting-rom.php';
require get_template_directory() . '/setting/setting-emulator.php';
require get_template_directory() . '/setting/setting-page-home.php';

/**
 * Download token functions.
 */
require get_template_directory() . '/inc/download-token.php';

require get_template_directory() . '/auth/setting-member.php';

// Member subsystem. member-core (session, origin guard, current-member, throttle)
// must load before auth-google and every action module, which depend on it.
require get_template_directory() . '/auth/member-core.php';
require get_template_directory() . '/auth/auth-google.php';       // Google login / logout
require get_template_directory() . '/auth/actions/comment.php';   // POST /comment, GET /my-comments
require get_template_directory() . '/auth/actions/wishlist.php';  // GET/POST /wishlist
require get_template_directory() . '/auth/actions/download.php';  // GET /download-url (gated)
require get_template_directory() . '/auth/actions/report.php';    // POST /report, GET /my-reports
require get_template_directory() . '/auth/actions/profile.php';   // GET /my-stats (dashboard)
require get_template_directory() . '/auth/actions/rating.php';    // POST /rating, GET /ratings, /my-ratings
require get_template_directory() . '/auth/actions/ticket.php';    // POST /ticket, GET /my-tickets, POST /ticket/close
require get_template_directory() . '/auth/actions/contact.php';   // POST /contact, GET /my-contacts
require get_template_directory() . '/auth/member-ui.php';         // member menu + restricted notice
require get_template_directory() . '/auth/member-pages.php';

if ( is_admin() ) {
	require get_template_directory() . '/auth/admin-members.php';
	require get_template_directory() . '/auth/admin-report.php'; // Report admin page (member_report)
	require get_template_directory() . '/auth/admin-ratings.php'; // Ratings & Reviews admin page (member_rating)
	require get_template_directory() . '/auth/admin-wishlists.php'; // Wishlists admin page (member_wishlist, grouped by post)
	require get_template_directory() . '/auth/admin-ticket.php'; // Requests admin page (member_ticket)
	require get_template_directory() . '/auth/admin-contact.php'; // Contact admin page (member_contact)
	require get_template_directory() . '/tracking/status.php'; // tracking status in Configs > General + stale flush notice
}

/**
 * Permanent delete of a post plus the media only it uses: row action, bulk
 * action and the Actions box on the edit screen.
 *
 * Loaded everywhere, not just in admin: the before_delete_post hook in there has
 * to catch a delete that arrives over REST, WP-CLI or cron too, where is_admin()
 * is false. The admin-only pieces gate themselves on their own hooks.
 */
require get_template_directory() . '/inc/admin-delete.php';

/**
 * Ajax functions
 */
require get_template_directory() . '/inc/ajax.php';

/**
 * Navigation Walker.
 */
require get_template_directory() . '/inc/nav-walker.php';
require get_template_directory() . '/inc/menu-icons.php';

/**
 * Utility functions.
 */
require get_template_directory() . '/inc/utility.php';
require get_template_directory() . '/inc/utility-post-type.php';

/**
 * Theme Options.
 */
require get_template_directory() . '/inc/options.php';

/**
 * Quick search index (static JSON shards in uploads/search-index/).
 */
require get_template_directory() . '/inc/search-index.php';

/**
 * Load Breadcrumb
 */
require get_template_directory() . '/inc/breadcrumb.php';

/**
 * Load Pagination
 */
require get_template_directory() . '/inc/pagination.php';

/**
 * Load Grabber
 */
require get_template_directory() . '/grabber/grabber.php';
// require get_template_directory() . '/grabber/grabber-rom.php';
// require get_template_directory() . '/grabber/grabber-mobygames.php';
// require get_template_directory() . '/grabber/grabber-igdb.php';
// require get_template_directory() . '/grabber/grabber-retrostic.php';
require get_template_directory() . '/grabber/grabber-multi.php';
/**
 * Load Insert SEO
 */
require get_template_directory() . '/inc/insert-seo.php';

/**
 * Load Insert Link
 */
require get_template_directory() . '/inc/insert-link.php';
require get_template_directory() . '/inc/insert-link-s3.php';

/**
 * Load Insert Link Aff
 */
require get_template_directory() . '/inc/insert-link-aff.php';

/**
 * Load Insert External Link Aff
 */
require get_template_directory() . '/inc/insert-external-link-aff.php';

/**
 * Load Get File
 */
require get_template_directory() . '/inc/get-file.php';

/**
 * In-memory WebP compression. Declares functions only — no hooks, no queries —
 * so loading it everywhere costs nothing; the grabber is meant to call it.
 */
require get_template_directory() . '/inc/img-compressor.php';

/**
 * Caching
 */
require get_template_directory() . '/setting/setting-console-group.php';

require get_template_directory() . '/caching/caching-rom.php';


/**
 * Security
 */
require get_template_directory() . '/caching/AppSecurity.php';

/**
 * Enqueue AJAX tracking script
 */
function rf_enqueue_tracking_script(): void {
    global $wp_query;
    
    $track_type = null;
    $post_id = 0;

    // Only a single-file download page counts as a download, not the file list.
    if (!empty($wp_query->query_vars['download'])) {
        if (absint(get_query_var('download_file')) > 0) {
            $post_id = get_queried_object_id();
            if ($post_id > 0) {
                $track_type = 'download';
            }
        }
    }
    // Check for single post pages
    elseif (is_singular(['rom', 'emulator', 'post'])) {
        $post_id = get_the_ID();
        $track_type = 'view';
    }
    
    // Enqueue script if tracking is needed
    if ($track_type && $post_id > 0) {
        wp_enqueue_script(
            'rf-tracking',
            get_template_directory_uri() . '/js/tracking.js',
            [],
            filemtime(get_template_directory() . '/js/tracking.js'),
            true
        );
        
        wp_localize_script('rf-tracking', 'rfTracking', [
            // Beacon endpoint, mapped by nginx straight at tracking/track.php so
            // counting a view never bootstraps WordPress.
            'endpoint' => '/t',
            'postId' => $post_id,
            'trackType' => $track_type
        ]);
    }
}
add_action('wp_enqueue_scripts', 'rf_enqueue_tracking_script');


function get_sort_list(): array {
	return array('newest', 'oldest', 'popular', 'downloads', 'alphabetical', 'latest');
}

//add_action('save_post', 'update_post_date_now');
function update_post_date_now($post_id) {

    if (get_post_status($post_id) !== 'publish') {
        return;
    }

    remove_action( 'save_post', 'update_post_date_now' );

    $update_post = array(
        'ID' => $post_id,
        'post_date'     => current_time( 'mysql' ),
        'post_date_gmt' => current_time( 'mysql', 1 ),
    );

    wp_update_post( $update_post );

    add_action( 'save_post', 'update_post_date_now' );
}


function k_modify_main_query( $query ): void {

	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// Search → chỉ rom
	if ( $query->is_search() ) {
		$query->set( 'post_type', 'rom' );
	}

	if ( is_tax() || is_archive() || $query->is_search() ) {
		$query->set( 'posts_per_page', ROM_ARCHIVE_PER_PAGE );
	}
}
add_action( 'pre_get_posts', 'k_modify_main_query' );

/**
 * Items per page on every archive and search listing. The main query above and
 * the filtered query in k_build_roms_query() must agree, or the last pages of
 * one 404 under the other.
 */
defined( 'ROM_ARCHIVE_PER_PAGE' ) || define( 'ROM_ARCHIVE_PER_PAGE', 15 );


function get_rom_page_templates(): array {
	return array(
		'page-emulators.php',
		'page-roms.php',
		'page-collections.php' );
}

add_filter('the_content', function ($content) {

	if (
		! is_singular(array('post', 'rom', 'emulator')) ||
		! in_the_loop() ||
		! is_main_query()
	) {
		return $content;
	}
	$content = '<div class="revert page-content">' . $content . '</div>';
	ob_start();
	get_template_part('template-parts/sharing');
	$sharing = ob_get_clean();

	return $content . $sharing;

});

function rf_force_404() {
    global $wp_query;

    if ( ! is_404() ) {
        $wp_query->set_404();
    }

    status_header(404);
    nocache_headers();

    include get_404_template();
    exit;
}

function dump($data, $message = '') {
	echo '<pre class="debug-dump text-black">';

	if ($message) echo '<h1>' . $message . '</h1>';

	var_dump($data);
	echo '</pre>';
}