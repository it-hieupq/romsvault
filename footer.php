<?php
/**
 * Footer: brand + copyright on the left, the 'footer-nav' menu on the right as
 * one wrapping row of quick links (every item, children included, in menu order).
 */
$footer_items = array();
if ( has_nav_menu( 'footer-nav' ) ) {
	$locations = get_nav_menu_locations();
	$menu      = ! empty( $locations['footer-nav'] ) ? wp_get_nav_menu_object( $locations['footer-nav'] ) : false;
	if ( $menu ) {
		$items = wp_get_nav_menu_items( $menu->term_id );
		if ( $items && ! is_wp_error( $items ) ) {
			$footer_items = $items;
		}
	}
}

// Same brand block as the header: the uploaded logo when there is one, else the mark + name.
$footer_logo = get_field( 'logo', 'option' );
?>
<footer class="bg-white border-t border-slate-200 mt-auto text-slate-500 text-caption">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-12 flex flex-col-reverse gap-10 lg:grid lg:grid-cols-3 lg:gap-16">

        <div class="space-y-4">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="inline-flex items-center gap-2.5">
				<?php if ( $footer_logo ) : ?>
                    <img src="<?php echo esc_url( $footer_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="h-9 w-auto" loading="lazy">
				<?php else : ?>
                    <?php echo rom_logo_mark( 'w-10 h-10 rounded-xl flex-shrink-0' ); ?>
                    <span class="flex flex-col">
                        <span class="text-lg font-bold tracking-tight text-slate-900 font-display"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
                        <span class="text-[9px] tracking-widest uppercase font-bold text-slate-400 -mt-1"><?php esc_html_e( 'Game Archive', 'rom' ); ?></span>
                    </span>
				<?php endif; ?>
            </a>

            <div class="text-slate-400">
				<?php the_field('copyright', 'option'); ?>
            </div>

			<?php
			$social_networks = get_field('social_networks', 'option');
			$social_icons = k_get_social_icons();
			if ( !empty( $social_networks ) ) :
				?>
                <div class="flex gap-3 items-center">
					<?php
					foreach ( $social_networks as $social ) :
						if ( !empty( $social['social_network_icon'] ) && !empty( $social['social_network_url'] ) ) :
							$icon_key = $social['social_network_icon'];
							$icon_svg = $social_icons[ $icon_key ] ?? '';
							if ( !empty( $icon_svg ) ) :
								?>
                                <a href="<?php echo esc_url( $social['social_network_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="text-slate-400 hover:text-primary transition-colors flex items-center justify-center" aria-label="<?php echo esc_attr( ucfirst( $icon_key ) ); ?>">
									<?php echo $icon_svg; ?>
                                </a>
							<?php
							endif;
						endif;
					endforeach;
					?>
                </div>
			<?php endif; ?>
        </div>

		<?php if ( $footer_items ) : ?>
            <nav class="lg:col-span-2" aria-label="<?php esc_attr_e( 'Quick links', 'rom' ); ?>">
                <span class="flex items-center gap-1.5 mb-4 font-bold text-body text-slate-900 font-display tracking-wide">
                    <svg class="w-4 h-4 text-red-500 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M11.983 1.907a.75.75 0 0 0-1.292-.657l-8.5 9.5A.75.75 0 0 0 2.75 12h6.572l-1.305 6.093a.75.75 0 0 0 1.292.657l8.5-9.5A.75.75 0 0 0 17.25 8h-6.572l1.305-6.093z"/></svg>
                    <?php esc_html_e( 'Quick links', 'rom' ); ?>
                </span>
                <?php // Every link carries its dot in front; -ml-6 + overflow-hidden tucks the
                      // first dot of each wrapped line out of view, so none dangles. ?>
                <div class="overflow-hidden">
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-3 -ml-6">
						<?php foreach ( $footer_items as $item ) : ?>
                            <span class="inline-flex items-center gap-5">
                                <span class="w-1 h-1 rounded-full bg-red-500 flex-shrink-0" aria-hidden="true"></span>
                                <a href="<?php echo esc_url( $item->url ); ?>" class="font-semibold text-slate-600 hover:text-primary transition-colors"><?php echo esc_html( $item->title ); ?></a>
                            </span>
						<?php endforeach; ?>
                    </div>
                </div>
            </nav>
		<?php endif; ?>
    </div>
</footer>

<button type="button" class="scroll-to-top hidden" id="scrollToTop" aria-label="<?php esc_attr_e( 'Back to top', 'rom' ); ?>">
    <span class="scroll-to-top__inner">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m6 15 6-6 6 6"></path>
        </svg>
    </span>
</button>

<?php echo get_field('footer_js', 'option'); ?>
<!-- Login modal (P2.0 scaffold; Google button wired in P2.1). Shared by header + single "login to download". -->
<div id="rf-modal" class="rf-modal" aria-hidden="true">
    <div class="rf-modal-overlay" data-rf-close></div>
    <div class="rf-modal-card" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Sign in', 'rom' ); ?>">
        <button type="button" class="rf-modal-close" data-rf-close aria-label="<?php esc_attr_e( 'Close', 'rom' ); ?>">&times;</button>
        <div class="rf-modal-title"><?php esc_html_e( 'Sign in to system', 'rom' ); ?></div>
        <div class="rf-modal-sub"><?php esc_html_e( 'Sign in to download, comment and rate.', 'rom' ); ?></div>
        <div class="rf-gsi-slot" id="rf-gsi-slot">
            <span class="rf-gsi-note"><?php esc_html_e( 'Loading Google sign-in…', 'rom' ); ?></span>
        </div>
        <div class="rf-webview-banner"><?php esc_html_e( 'Please open in Chrome or Safari to sign in with Google.', 'rom' ); ?></div>
    </div>
</div>

<?php wp_footer(); ?>

</body>
</html>
