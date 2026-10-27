<?php
/**
 * The header for our theme.
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @package romsvault
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <link rel="profile" href="http://gmpg.org/xfn/11">

    <?php // Member auth (P2.0): mark <html> logged-in BEFORE paint to avoid a guest->avatar flash. Cache-safe (static + reads only client localStorage). ?>
    <script>(function(){try{var r=localStorage.getItem('rf_display');if(r){var d=JSON.parse(r);if(!d.exp||d.exp*1000>Date.now())document.documentElement.classList.add('rf-authed');}}catch(e){}})();</script>

    <?php
    // Icons are re-exported together from logo-mark.svg; the mtime in the URL
    // lets browsers and Cloudflare pick up new art under the same file names.
    $mark_ver = filemtime( get_template_directory() . '/assets/images/logo-mark-192.png' );
    ?>
    <?php if($favicon_url = get_field( 'favicon', 'option' ) ): ?>
        <link rel="shortcut icon" type="image/x-icon" href="<?php echo $favicon_url; ?>">
    <?php else : $icon_dir = get_template_directory_uri() . '/assets/images/'; ?>
        <?php // Default icons, exported from assets/images/logo-mark.svg. ?>
        <link rel="icon" href="<?php echo esc_url( $icon_dir . 'favicon.ico?v=' . $mark_ver ); ?>" sizes="any">
        <link rel="icon" type="image/png" sizes="64x64" href="<?php echo esc_url( $icon_dir . 'favicon-64.png?v=' . $mark_ver ); ?>">
        <link rel="apple-touch-icon" href="<?php echo esc_url( $icon_dir . 'apple-touch-icon.png?v=' . $mark_ver ); ?>">
    <?php endif; ?>

	<?php wp_head(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

	<?php
	if ( is_singular() && valid_post_type(get_post_type()) ) :
		// The SoftwareApplication node normally joins Yoast's graph (inc/utility.php).
		// Only when Yoast isn't active is it printed on its own here, so it never doubles up.
		if ( ! defined( 'WPSEO_VERSION' ) && ! get_query_var( 'download' ) ) :
			?>
        <script type="application/ld+json"><?php echo wp_json_encode( array( '@context' => 'https://schema.org' ) + rom_software_schema( $post ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ); ?></script>
		<?php endif; ?>

        <?php if ( isset( $_GET['fast'] ) || isset( $_GET['slow'] ) ) : ?>
        <link rel="canonical" href="<?php the_permalink(); ?>">
        <?php endif; ?>
	<?php endif; ?>

	<?php echo get_field('header_js', 'option'); ?>
</head>

<body <?php body_class('bg-slate-50'); ?>>

<?php
$logo_url  = get_field( 'logo', 'option' );
$site_name = get_bloginfo( 'name' );

/**
 * Header search. The Browse ROMs page template reads ?q= and layers its facet
 * filters on top of it (browse-roms.php:10). When no page uses that template
 * get_brows_page_url() returns '' and an empty action would post back to the
 * current URL, so fall back to WP's own ?s= search.
 */
$browse_url    = get_brows_page_url();
$search_action = $browse_url ? $browse_url : home_url( '/' );
$search_param  = $browse_url ? 'q' : 's';
?>

<header class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-[0_4px_20px_-2px_rgba(15,23,42,0.06)]">
    <div class="h-0.5 w-full bg-gradient-to-r from-primary via-primary-accent to-primary"></div>

    <?php // relative: the search suggestions panel spans this container. ?>
    <div class="relative max-w-site mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-[72px] sm:h-[88px] gap-6">

            <!-- Logo -->
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-brand flex items-center gap-2.5 group flex-shrink-0">
                <?php if ( $logo_url ) : ?>
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" class="h-8 sm:h-9 w-auto">
                <?php else : ?>
                    <?php // Mark only; the site name beside it stays live text. 192px covers 3x screens. ?>
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo-mark-192.png?v=' . $mark_ver ); ?>" alt="" width="48" height="48"
                         class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl shadow-sm shadow-primary/30 group-hover:scale-105 transition-transform flex-shrink-0">
                <?php endif; ?>

                <?php
                // has logo -> hide the name. Not a heading: the page's own title is its h1.
                $class = $logo_url
                        ? 'hidden'
                        : 'flex flex-col';
                ?>

                <div class="<?php echo esc_attr( $class ); ?>">
                    <span class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 font-display"><?php echo esc_html( $site_name ); ?></span>
                    <span class="text-[9px] sm:text-[10px] tracking-widest uppercase font-bold text-slate-400 -mt-1"><?php esc_html_e( 'Game Archive', 'rom' ); ?></span>
                </div>
            </a>

            <nav class="hidden md:flex items-center gap-1 font-semibold text-meta text-slate-600">
                <?php
                if ( has_nav_menu( 'primary' ) ) {
                    rom_display_header_menu();
                } else {
                    echo '<a href="' . esc_url( home_url( '/' ) ) . '" class="px-3.5 py-1.5 rounded-xl bg-primary text-white font-bold shadow-sm shadow-primary/25">'.__('Home', 'rom').'</a>';
                }
                ?>
            </nav>

            <!-- Desktop search: plain GET so it still works on a cached page with JS off. -->
            <div class="hidden lg:flex flex-1 max-w-xs">
                <form action="<?php echo esc_url( $search_action ); ?>" method="get" role="search" class="w-full">
                    <label class="sr-only" for="header-search"><?php esc_html_e( 'Search ROMs', 'rom' ); ?></label>
                    <div class="relative">
                        <input type="search" id="header-search" name="<?php echo esc_attr( $search_param ); ?>"
                               placeholder="<?php esc_attr_e( 'Search ROMs & ISOs...', 'rom' ); ?>"
                               class="w-full pl-9 pr-4 py-2 rounded-full bg-slate-50 border border-slate-200/90 text-meta text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-inner">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <?php if ( rom_search_location_enabled( 'header' ) ) : ?>
                        <div id="header-search-results" class="search-results search-results--header" role="listbox" aria-label="<?php esc_attr_e( 'Search suggestions', 'rom' ); ?>" hidden></div>
                    <?php endif; ?>
                </form>
            </div>

            <div class="flex items-center gap-3">
                <!-- Account (P2.0): renders guest by default; auth.js swaps to avatar from localStorage. Desktop only. -->
                <div id="rf-account" class="rf-account">
                    <button type="button" class="rf-guest-only rf-icon-btn" data-rf-login aria-label="<?php esc_attr_e( 'Sign in', 'rom' ); ?>" title="<?php esc_attr_e( 'Sign in', 'rom' ); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                    </button>
                    <div class="rf-authed-only rf-menu">
                        <button type="button" class="rf-user-trigger" data-rf-menu-toggle aria-haspopup="true">
                            <img class="rf-avatar" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='12' fill='%23e5e7eb'/%3E%3Ccircle cx='12' cy='9.5' r='3.5' fill='%23cbd5e1'/%3E%3Cpath d='M5 20c0-3.6 3.2-5.5 7-5.5s7 1.9 7 5.5z' fill='%23cbd5e1'/%3E%3C/svg%3E" alt="" data-rf-avatar>
                            <span class="rf-name" data-rf-firstname><?php esc_html_e( 'Account', 'rom' ); ?></span>
                            <span class="rf-caret">&#9662;</span>
                        </button>
                        <div class="rf-dropdown">
                            <?php rf_render_member_menu( 'desktop' ); ?>
                        </div>
                    </div>
                </div>

                <button class="md:hidden p-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-700 hover:text-primary hover:bg-rose-50/50 transition-colors" id="mobile-menu-btn" aria-label="<?php esc_attr_e( 'Toggle menu', 'rom' ); ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</header>

<div class="mobile-menu-overlay" id="mobile-menu-overlay"></div>

<div class="mobile-menu-panel" id="mobile-menu-panel">
    <div class="mobile-menu-header">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="inline-flex items-center gap-2.5 min-w-0">
            <?php if ($logo_url) : ?>
                <img class="h-8 w-auto" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>">
            <?php else: ?>
                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo-mark-192.png?v=' . $mark_ver ); ?>" alt="" width="36" height="36" class="w-9 h-9 rounded-xl flex-shrink-0">
                <span class="text-lg font-bold tracking-tight text-slate-900 font-display truncate"><?php echo esc_html( $site_name ); ?></span>
            <?php endif; ?>
        </a>
        <button type="button" class="mobile-menu-close" id="mobile-menu-close" aria-label="<?php esc_attr_e( 'Close menu', 'rom' ); ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <?php // The header search box only shows from lg up, so phones and tablets search from here. ?>
    <form action="<?php echo esc_url( $search_action ); ?>" method="get" role="search" class="mobile-menu-search">
        <label class="sr-only" for="mobile-search"><?php esc_html_e( 'Search ROMs', 'rom' ); ?></label>
        <div class="relative">
            <input type="search" id="mobile-search" name="<?php echo esc_attr( $search_param ); ?>"
                   placeholder="<?php esc_attr_e( 'Search ROMs & ISOs...', 'rom' ); ?>"
                   class="w-full pl-10 pr-4 py-2 rounded-full bg-slate-50 border border-slate-200 text-base placeholder:text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
        <?php if ( rom_search_location_enabled( 'header' ) ) : ?>
            <div id="mobile-search-results" class="search-results search-results--mobile" role="listbox" aria-label="<?php esc_attr_e( 'Search suggestions', 'rom' ); ?>" hidden></div>
        <?php endif; ?>
    </form>

    <div class="mobile-menu-content">
        <!-- Logged-in member card (top): tap to toggle the member submenu. auth.js swaps from localStorage. -->
        <div class="rf-authed-only rf-m-account">
            <button type="button" class="rf-m-user" data-rf-mtoggle aria-expanded="false">
                <img class="rf-avatar" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='12' fill='%23e5e7eb'/%3E%3Ccircle cx='12' cy='9.5' r='3.5' fill='%23cbd5e1'/%3E%3Cpath d='M5 20c0-3.6 3.2-5.5 7-5.5s7 1.9 7 5.5z' fill='%23cbd5e1'/%3E%3C/svg%3E" alt="" data-rf-avatar>
                <div class="rf-m-meta">
                    <div class="rf-m-name" data-rf-name></div>
                    <div class="rf-m-email" data-rf-email></div>
                </div>
                <svg class="rf-m-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="rf-m-submenu">
                <?php rf_render_member_menu( 'mobile' ); ?>
            </div>
        </div>

        <nav class="mobile-menu-nav" aria-label="<?php esc_attr_e( 'Menu', 'rom' ); ?>">
            <span class="mobile-menu-label"><?php esc_html_e( 'Menu', 'rom' ); ?></span>
            <?php
            if ( has_nav_menu( 'primary' ) ) :
                rom_display_mobile_menu();
            else :
                echo '<a href="' . esc_url( home_url( '/' ) ) . '" class="mobile-menu-item active">' . esc_html__( 'Home', 'rom' ) . '</a>';
            endif;
            ?>
        </nav>

        <!-- Guest sign-in pinned to the bottom (auto width, centered). -->
        <div class="rf-guest-only rf-mobile-login">
            <button type="button" class="rf-login-btn" data-rf-login>
                <svg class="rf-g" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.6 2.4 30.2 0 24 0 14.6 0 6.4 5.4 2.5 13.2l7.9 6.1C12.2 13.2 17.6 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.5 3-2.2 5.5-4.7 7.2l7.3 5.7C43.7 37.9 46.5 31.8 46.5 24.5z"/><path fill="#FBBC05" d="M10.4 28.3c-.5-1.4-.7-2.9-.7-4.3s.3-2.9.7-4.3l-7.9-6.1C.9 16.7 0 20.2 0 24s.9 7.3 2.5 10.4l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.2 0 11.5-2 15.3-5.6l-7.3-5.7c-2 1.4-4.7 2.3-8 2.3-6.4 0-11.8-3.7-13.6-9.8l-7.9 6.1C6.4 42.6 14.6 48 24 48z"/></svg>
                <span><?php esc_html_e( 'Sign in with Google', 'rom' ); ?></span>
            </button>
        </div>
    </div>
</div>
