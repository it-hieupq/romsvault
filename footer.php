<?php
/**
 * Footer link columns come from the 'footer-nav' menu: every top-level item that
 * has children becomes a column (the item is the heading, its children the
 * links). Top-level items without children have no heading to sit under, so they
 * fall back to a single inline row the way the footer rendered before.
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

$footer_children = array();
foreach ( $footer_items as $item ) {
	if ( (int) $item->menu_item_parent ) {
		$footer_children[ (int) $item->menu_item_parent ][] = $item;
	}
}

$footer_columns = array();
$footer_loose   = array();
foreach ( $footer_items as $item ) {
	if ( (int) $item->menu_item_parent ) {
		continue;
	}
	if ( ! empty( $footer_children[ $item->ID ] ) ) {
		$footer_columns[] = array(
			'heading' => $item,
			'links'   => $footer_children[ $item->ID ],
		);
	} else {
		$footer_loose[] = $item;
	}
}
?>
<footer class="bg-white border-t border-slate-200 mt-auto text-slate-500 text-caption">
    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 py-10">

		<?php if ( $footer_columns ) : ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-8">
				<?php foreach ( $footer_columns as $column ) : ?>
                    <div>
                        <span class="font-bold text-body text-slate-900 block mb-2.5 font-display tracking-wide"><?php echo esc_html( $column['heading']->title ); ?></span>
                        <ul class="space-y-1.5">
							<?php foreach ( $column['links'] as $link ) : ?>
                                <li><a href="<?php echo esc_url( $link->url ); ?>" class="hover:text-primary transition-colors"><?php echo esc_html( $link->title ); ?></a></li>
							<?php endforeach; ?>
                        </ul>
                    </div>
				<?php endforeach; ?>
            </div>
		<?php endif; ?>

		<?php if ( $footer_loose ) : ?>
            <div class="flex flex-wrap gap-4 lg:gap-6 justify-center mb-8">
				<?php foreach ( $footer_loose as $item ) : ?>
                    <a href="<?php echo esc_url( $item->url ); ?>" class="font-semibold text-slate-600 hover:text-primary transition-colors"><?php echo esc_html( $item->title ); ?></a>
				<?php endforeach; ?>
            </div>
		<?php endif; ?>

        <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
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
    </div>
</footer>

<button class="scroll-to-top hidden" id="scrollToTop">
    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
    </svg>
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
