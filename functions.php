<?php
/**
 * Theme setup, assets and module loading.
 *
 * @package romsvault
 */

if ( ! function_exists( 'k_setup' ) ) :
function k_setup() {
	load_theme_textdomain( 'rom', get_template_directory() . '/languages' );

	add_theme_support( 'automatic-feed-links' );

	add_theme_support( 'title-tag' );

	add_theme_support( 'post-thumbnails' );

	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
	) );

	add_image_size( 'thumbnail', 300, 9999, false );

	add_post_type_support( 'page', 'excerpt' );
}
endif;
add_action( 'after_setup_theme', 'k_setup' );

// On init, not after_setup_theme: translating the labels earlier triggers WP 6.7's
// _load_textdomain_just_in_time notice.
function k_register_nav_menus() {
	register_nav_menus( array(
		'primary'    => esc_html__( 'Primary Navigation', 'rom' ),
		'site-nav'   => esc_html__( 'Site navigation', 'rom' ),
		'footer-nav' => esc_html__( 'Footer navigation', 'rom' ),
	) );
}
add_action( 'init', 'k_register_nav_menus' );

// Don't generate WP's default intermediate sizes.
function k_remove_image_sizes( $sizes) {
	unset( $sizes['medium'] );
	unset( $sizes['medium_large'] );
    unset( $sizes['large'] );
    unset( $sizes['1536x1536'] );
    unset( $sizes['2048x2048'] );
	return $sizes;
}
add_filter('intermediate_image_sizes_advanced', 'k_remove_image_sizes');

function k_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'k_content_width', 640 );
}
add_action( 'after_setup_theme', 'k_content_width', 0 );

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
		array(),
		filemtime(get_template_directory() . '/assets/css/output.css')
	);

	// Toast first: auth.js and main.js both use it.
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
	// Same for every visitor (cache-safe). The GSI ID-token flow needs only the public client id.
	wp_localize_script(
		'rf-auth',
		'RF_AUTH',
		array(
			'clientId' => defined( 'GOOGLE_CLIENT_ID' ) ? GOOGLE_CLIENT_ID : '',
			'rest'     => esc_url_raw( rest_url( 'rf/v1/' ) ),
		)
	);

	global $wp_query;
	$post_type = get_post_type();

	$is_download_page = ! empty( $wp_query->query_vars['download'] );

    wp_enqueue_script(
        'main-js',
        get_template_directory_uri() . '/js/main.js',
        array( 'jquery', 'rf-toast' ),
        filemtime( get_template_directory() . '/js/main.js' ),
        true
    );

	wp_localize_script( 'main-js', 'ajax', array(
		'ajax_url'             => admin_url( 'admin-ajax.php' ),
		'site_url'             => untrailingslashit( site_url() ),
		'enable_dynamic_nonce' => should_load_dynamic_nonce(),
	) );

	// Inline JSON: wp_localize_script would turn numbers and booleans into strings.
	$search_config = rom_search_js_config();
	if ( $search_config ) {
		wp_add_inline_script( 'main-js', 'window.romSearch = ' . wp_json_encode( $search_config, JSON_UNESCAPED_SLASHES ) . ';', 'before' );
	}

	if ( ( is_singular() && valid_post_type( $post_type ) ) || $is_download_page ) {
		wp_enqueue_script(
			'rf-rating',
			get_template_directory_uri() . '/js/rating.js',
			array( 'rf-auth' ),
			filemtime( get_template_directory() . '/js/rating.js' ),
			true
		);
	}

	// lightGallery only on singles with screenshots or a video (~7% of posts).
	$has_gallery = is_singular() && valid_post_type( $post_type ) && ! $is_download_page
		&& ( get_field( 'image', get_queried_object_id() ) || get_field( 'youtube_url', get_queried_object_id() ) );
	if ( $has_gallery ) {

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

		wp_enqueue_style(
			'lg-video',
			get_template_directory_uri() . '/assets/css/lg-video.min.css',
			['lightgallery'],
			'2.8.3'
		);

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

        wp_enqueue_script(
            'lightgallery-init',
            get_template_directory_uri() . '/assets/js/lightgallery-init.js',
            ['lightgallery', 'lg-thumbnail', 'lg-video'],
            filemtime( get_template_directory() . '/assets/js/lightgallery-init.js' ),
            true
        );
	}
}
add_action( 'wp_enqueue_scripts', 'rom_enqueue_scripts' );

/**
 * Front end: jQuery without Migrate, in the footer. The Configs "Header js" field
 * prints in <head>, so it runs before jQuery.
 */
function rom_frontend_jquery( $scripts ) {
	if ( is_admin() ) {
		return;
	}
	$jquery = $scripts->query( 'jquery', 'registered' );
	if ( $jquery ) {
		$jquery->deps = array_values( array_diff( $jquery->deps, array( 'jquery-migrate' ) ) );
	}
	foreach ( array( 'jquery', 'jquery-core' ) as $handle ) {
		$scripts->add_data( $handle, 'group', 1 );
	}
}
add_action( 'wp_default_scripts', 'rom_frontend_jquery' );

// Defer the theme's scripts. Priority 99 so ones enqueued on other hooks (rf-tracking) are covered.
function rom_defer_theme_scripts() {
	$handles = array( 'rf-toast', 'rf-auth', 'main-js', 'rf-rating', 'rf-tracking',
		'lightgallery', 'lg-thumbnail', 'lg-video', 'lightgallery-init' );
	foreach ( $handles as $handle ) {
		if ( wp_script_is( $handle, 'enqueued' ) ) {
			wp_script_add_data( $handle, 'strategy', 'defer' );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'rom_defer_theme_scripts', 99 );

function rom_admin_scripts() {
	wp_enqueue_script( 'admin-js', get_template_directory_uri() . '/js/admin.js', array('jquery'), 2, true );
	wp_localize_script( 'admin-js', 'ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
}
add_action( 'admin_enqueue_scripts', 'rom_admin_scripts' );

// ACF PRO and ACF QuickEdit Fields ship in inc/. Both build asset URLs with
// plugin_dir_url(), so map files under the theme to the theme URL.
add_filter( 'plugins_url', function ( $url, $path, $plugin ) {
	$theme_dir = wp_normalize_path( get_template_directory() );
	$plugin    = wp_normalize_path( (string) $plugin );
	if ( 0 === strpos( $plugin, $theme_dir . '/' ) ) {
		$url = get_template_directory_uri() . substr( dirname( $plugin ), strlen( $theme_dir ) ) . ( $path ? '/' . ltrim( $path, '/' ) : '' );
	}
	return $url;
}, 10, 3 );
add_filter( 'acf/settings/show_admin', '__return_false' );
add_filter( 'acf/settings/show_updates', '__return_false', 100 );
require_once get_template_directory() . '/inc/acf/acf.php';
require_once get_template_directory() . '/inc/acf-quick-edit-fields/index.php';

require get_template_directory() . '/setting/setting-post.php';
require get_template_directory() . '/setting/setting-rom.php';
require get_template_directory() . '/setting/setting-emulator.php';
require get_template_directory() . '/setting/setting-page-home.php';

require get_template_directory() . '/inc/download-token.php';

require get_template_directory() . '/auth/setting-member.php';

// member-core must load before auth-google and the action modules, which depend on it.
require get_template_directory() . '/auth/member-core.php';
require get_template_directory() . '/auth/auth-google.php';
require get_template_directory() . '/auth/actions/comment.php';
require get_template_directory() . '/auth/actions/wishlist.php';
require get_template_directory() . '/auth/actions/download.php';
require get_template_directory() . '/auth/actions/report.php';
require get_template_directory() . '/auth/actions/profile.php';
require get_template_directory() . '/auth/actions/rating.php';
require get_template_directory() . '/auth/actions/ticket.php';
require get_template_directory() . '/auth/actions/contact.php';
require get_template_directory() . '/auth/member-ui.php';
require get_template_directory() . '/auth/member-pages.php';

if ( is_admin() ) {
	require get_template_directory() . '/auth/admin-members.php';
	require get_template_directory() . '/auth/admin-report.php';
	require get_template_directory() . '/auth/admin-ratings.php';
	require get_template_directory() . '/auth/admin-wishlists.php';
	require get_template_directory() . '/auth/admin-ticket.php';
	require get_template_directory() . '/auth/admin-contact.php';
	require get_template_directory() . '/tracking/status.php';
}

// Loaded everywhere: its before_delete_post hook must also catch deletes over REST,
// WP-CLI and cron, where is_admin() is false.
require get_template_directory() . '/inc/admin-delete.php';

require get_template_directory() . '/inc/ajax.php';

require get_template_directory() . '/inc/nav-walker.php';
require get_template_directory() . '/inc/menu-icons.php';

require get_template_directory() . '/inc/utility.php';
require get_template_directory() . '/inc/utility-post-type.php';

require get_template_directory() . '/inc/options.php';

require get_template_directory() . '/inc/search-index.php';

require get_template_directory() . '/inc/breadcrumb.php';

require get_template_directory() . '/inc/pagination.php';

require get_template_directory() . '/grabber/grabber.php';
require get_template_directory() . '/grabber/grabber-multi.php';

require get_template_directory() . '/inc/insert-seo.php';

require get_template_directory() . '/inc/insert-link.php';
require get_template_directory() . '/inc/insert-link-s3.php';

require get_template_directory() . '/inc/insert-link-aff.php';

require get_template_directory() . '/inc/insert-external-link-aff.php';

require get_template_directory() . '/inc/get-file.php';

require get_template_directory() . '/inc/img-compressor.php';

require get_template_directory() . '/caching/caching-rom.php';

require get_template_directory() . '/caching/AppSecurity.php';

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
    elseif (is_singular(['rom', 'emulator', 'post'])) {
        $post_id = get_the_ID();
        $track_type = 'view';
    }

    if ($track_type && $post_id > 0) {
        wp_enqueue_script(
            'rf-tracking',
            get_template_directory_uri() . '/js/tracking.js',
            [],
            filemtime(get_template_directory() . '/js/tracking.js'),
            true
        );

        wp_localize_script('rf-tracking', 'rfTracking', [
            // nginx maps /t straight to tracking/track.php, so a view never boots WordPress.
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

function rom_modify_main_query( $query ): void {

	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// Search covers roms only.
	if ( $query->is_search() ) {
		$query->set( 'post_type', 'rom' );
	}

	if ( is_tax() || is_archive() || $query->is_search() || $query->is_home() ) {
		$query->set( 'posts_per_page', ROM_ARCHIVE_PER_PAGE );
	}
}
add_action( 'pre_get_posts', 'rom_modify_main_query' );

// Items per page on every listing. build_roms_query() must use the same value, or
// the last pages of one 404 under the other.
defined( 'ROM_ARCHIVE_PER_PAGE' ) || define( 'ROM_ARCHIVE_PER_PAGE', 20 );


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
	$content = '<div class="rich-text">' . $content . '</div>';
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

add_filter( 'pre_transient_wp_rocket_customer_data', function() {
    return (object) [
        'licence_account'    => -1,          // -1: Infinite, 1: Single, 3: Plus
        'licence_expiration' => 1954285973,  // 06/12/2031
        'date_created'       => 1600000000,
        'has_auto_renew'     => false,
        'is_reseller'        => false,
    ];
} );
