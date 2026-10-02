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

    <?php if($favicon_url = get_field( 'favicon', 'option' ) ): ?>
        <link rel="shortcut icon" type="image/x-icon" href="<?php echo $favicon_url; ?>">
    <?php endif; ?>

	<?php wp_head(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

	<?php
	if ( is_singular() && valid_post_type(get_post_type()) ) :
		$count = (int) get_post_meta($post->ID, 'rating_count', true);
		$score = (float) get_post_meta($post->ID, 'rating_avg', true);
		?>
        <script type="application/ld+json">
            {
				"@context": "http://schema.org/",
				"@type": "SoftwareApplication",
				"name": "<?php the_title(); ?>",
	    "applicationCategory": "GameApplication",
	    "operatingSystem": "",
	    "softwareVersion": "",
	    "offers": {
	        "@type": "Offer",
	        "price": "0",
	        "priceCurrency": "USD"
	    } <?php if ( $count > 0 ) : ?>,
	    "aggregateRating": {
	        "@type": "AggregateRating",
	        "bestRating": 5,
	        "worstRating": 1,
	        "ratingCount": "<?php echo $count; ?>",
	        "ratingValue": "<?php echo $score; ?>"
	    }<?php endif; ?>
	}
        </script>

        <?php if ( isset( $_GET['fast'] ) || isset( $_GET['slow'] ) ) : ?>
        <link rel="canonical" href="<?php the_permalink(); ?>">
        <?php endif; ?>
	<?php endif; ?>

	<?php echo get_field('header_js', 'option'); ?>
</head>

<body <?php body_class('bg-gray-50'); ?>>

<header class="bg-romfun-pink text-white sticky top-0 z-50 shadow-lg">
    <div class="content-container px-4 sm:px-6 lg:px-8 py-1">
        <div class="flex items-center justify-between h-16 lg:h-20">
            <!-- Logo -->
            <div class="flex items-center">
                <?php
                $logo_url  = get_field( 'logo', 'option' );
                $site_name = get_bloginfo('name');
                $is_home   = is_front_page() || is_home();
                ?>

                <a href="<?php echo esc_url(home_url('/')); ?>" class="site-brand flex items-center gap-2">

                    <?php if ($logo_url) : ?>
                        <img style="height: 30px;" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>">
                    <?php endif; ?>

                    <?php
                    $tag = $is_home ? 'h1' : 'h2';

                    // has logo -> hide heading
                    $class = $logo_url
                            ? 'hidden'
                            : 'text-xl lg:text-3xl font-bold';
                    ?>

                    <<?php echo $tag; ?> class="<?php echo esc_attr($class); ?>">
                    <?php echo esc_html($site_name); ?>
                </<?php echo $tag; ?>>

                </a>

            </div>

            <nav class="hidden md:flex items-center space-x-2 bg-white rounded-full px-4 py-2 shadow-lg">
                <?php
                if ( has_nav_menu( 'primary' ) ) {
                    romsfun_display_header_menu( 'primary' );
                } else {
                    echo '<a href="' . esc_url( home_url( '/' ) ) . '" class="header-nav-link font-bold text-romfun-pink active rounded-full">'.__('Home', 'romsfun').'</a>';
                }
                ?>
            </nav>

            <!-- Account (P2.0): renders guest by default; auth.js swaps to avatar from localStorage. Desktop only. -->
            <div id="rf-account" class="rf-account">
                <button type="button" class="rf-guest-only rf-icon-btn" data-rf-login aria-label="<?php esc_attr_e( 'Sign in', 'romsfun' ); ?>" title="<?php esc_attr_e( 'Sign in', 'romsfun' ); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                </button>
                <div class="rf-authed-only rf-menu">
                    <button type="button" class="rf-user-trigger" data-rf-menu-toggle aria-haspopup="true">
                        <img class="rf-avatar" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='12' fill='%23e5e7eb'/%3E%3Ccircle cx='12' cy='9.5' r='3.5' fill='%23cbd5e1'/%3E%3Cpath d='M5 20c0-3.6 3.2-5.5 7-5.5s7 1.9 7 5.5z' fill='%23cbd5e1'/%3E%3C/svg%3E" alt="" data-rf-avatar>
                        <span class="rf-name" data-rf-firstname><?php esc_html_e( 'Account', 'romsfun' ); ?></span>
                        <span class="rf-caret">&#9662;</span>
                    </button>
                    <div class="rf-dropdown">
                        <?php rf_render_member_menu( 'desktop' ); ?>
                    </div>
                </div>
            </div>

            <button class="md:hidden p-2 hover:bg-romfun-pink-dark rounded transition" id="mobile-menu-btn">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>
    </div>
</header>

<div class="mobile-menu-overlay" id="mobile-menu-overlay"></div>

<div class="mobile-menu-panel" id="mobile-menu-panel">
    <div class="mobile-menu-header">
        <?php if ($logo_url) : ?>
            <img style="height: 30px" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>">
        <?php else: ?>
            <h2 class="text-xl font-bold text-white"><?php bloginfo('name'); ?></h2>
        <?php endif; ?>
        <button class="mobile-menu-close" id="mobile-menu-close">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
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

        <?php
        if ( has_nav_menu( 'primary' ) ) :
            romsfun_display_mobile_menu( 'primary' );
        else :
            echo '<a href="' . esc_url( home_url( '/' ) ) . '" class="mobile-menu-item text-center active">'.__('Home', 'romsfun').'</a>';
        endif;
        ?>

        <!-- Guest sign-in pinned to the bottom (auto width, centered). -->
        <div class="rf-guest-only rf-mobile-login">
            <button type="button" class="rf-login-btn" data-rf-login>
                <svg class="rf-g" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.6 2.4 30.2 0 24 0 14.6 0 6.4 5.4 2.5 13.2l7.9 6.1C12.2 13.2 17.6 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.5 3-2.2 5.5-4.7 7.2l7.3 5.7C43.7 37.9 46.5 31.8 46.5 24.5z"/><path fill="#FBBC05" d="M10.4 28.3c-.5-1.4-.7-2.9-.7-4.3s.3-2.9.7-4.3l-7.9-6.1C.9 16.7 0 20.2 0 24s.9 7.3 2.5 10.4l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.2 0 11.5-2 15.3-5.6l-7.3-5.7c-2 1.4-4.7 2.3-8 2.3-6.4 0-11.8-3.7-13.6-9.8l-7.9 6.1C6.4 42.6 14.6 48 24 48z"/></svg>
                <span><?php esc_html_e( 'Sign in with Google', 'romsfun' ); ?></span>
            </button>
        </div>
    </div>
</div>