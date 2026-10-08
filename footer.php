
<footer class="bg-romfun-pink text-white py-12 lg:py-16">
    <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
        <div class="mb-8">
            <h3 class="text-center text-xl lg:text-2xl font-bold mb-6"><?php bloginfo('name'); ?></h3>
            <?php if ( has_nav_menu( 'footer-nav' ) ) : ?>
            <div class="flex flex-wrap gap-4 lg:gap-6 text-sm justify-center">
                <?php
                    $locations = get_nav_menu_locations();
                    if ( isset( $locations['footer-nav'] ) ) :
                        $menu = wp_get_nav_menu_object( $locations['footer-nav'] );
                        if ( $menu ) :
                            $menu_items = wp_get_nav_menu_items( $menu->term_id );
                            
                            if ( !empty( $menu_items ) && !is_wp_error( $menu_items ) ) :
                                foreach ( $menu_items as $item ) :
                                    if ( $item->menu_item_parent == 0 ) :
                                        $classes = !empty( $item->classes ) ? implode( ' ', array_filter( $item->classes ) ) : '';
                                        $classes = $classes ? ' class="' . esc_attr( $classes ) . ' hover:underline font-semibold"' : ' class="hover:underline font-semibold"';
                                        echo '<a href="' . esc_url( $item->url ) . '"' . $classes . '>' . esc_html( $item->title ) . '</a>';
                                    endif;
                                endforeach;
                            endif;
                        endif;
                    endif;
                ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="border-t border-romfun-pink-dark pt-8">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 text-sm">
                <div class="text-center sm:text-left">
                    <?php the_field('copyright', 'option'); ?>
                </div>
                <?php 
                $social_networks = get_field('social_networks', 'option');
                $social_icons = k_get_social_icons();
                if ( !empty( $social_networks ) ) : 
                ?>
                <div class="flex gap-4 items-center">
                    <?php 
                    foreach ( $social_networks as $social ) :
                        if ( !empty( $social['social_network_icon'] ) && !empty( $social['social_network_url'] ) ) :
                            $icon_key = $social['social_network_icon'];
                            $icon_svg = $social_icons[ $icon_key ] ?? '';
                            if ( !empty( $icon_svg ) ) :
                    ?>
                    <a href="<?php echo esc_url( $social['social_network_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="text-white hover:opacity-80 transition-opacity flex items-center justify-center" aria-label="<?php echo esc_attr( ucfirst( $icon_key ) ); ?>">
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
    </div>
</footer>

<button class="scroll-to-top hidden" id="scrollToTop">
    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
    </svg>
</button>

<button class="scroll-to-top" id="float-search-button" style="left: 30px;">
    <svg height="24px" viewBox="0 -960 960 960" width="24px" fill="#fff">
        <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z" />
    </svg>
</button>

<div class="float-search float-search__hide">
    
    <div class="flex flex-col lg:flex-row gap-3">
        
        <div class="relative flex-1">
            
            <div class="float-search-result"></div>
            
            <input style="padding-left: 60px; height: 50px;" type="text" id="float-search-input" placeholder="Search for ROMs, consoles, emulators…" name="q" value="" class="w-full rounded-full border border-border px-4 sm:px-6 py-4 text-gray-900 outline-none focus:ring-2 focus:ring-brand/40 text-sm">
            
        </div>
        
    </div>
    
</div>

<div class="float-search-bg float-search-bg__hide"></div>
                    
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
