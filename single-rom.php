<?php
get_header();

while ( have_posts() ) : the_post();
    global $post;
	$consoles = wp_get_post_terms($post->ID, 'console', array('fields' => 'all'));
	if ( ! is_wp_error($consoles) && ! empty($consoles) ) {
        $console = $consoles[0];
        $console_link = get_term_link($console->term_id);
        $console_image = get_field('cat_image', 'term_' . $console->term_id);
        $download_list = get_field('download_list', $post->ID);
    } else {
        rf_force_404();
    }

	$views = (int) get_field('views');
    ?>

    <section class="py-8 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">

            <div class="overflow-hidden pb-8">
                <div class="flex flex-col lg:flex-row gap-4">

                    <div class="lg:w-1/3 flex items-center justify-center p-6 bg-white rounded-xl shadow-md">
                        <img src="<?php the_post_thumbnail_url(); ?>" alt="<?php the_title() ?>"
                             class="w-full h-auto max-w-full object-contain">
                    </div>

                    <?php
                    $rating = false;
                    if ( isset($_COOKIE['ratings']) ) {
	                    $ratings = json_decode(stripslashes($_COOKIE['ratings']), true);
	                    if ( in_array($post->ID, $ratings) )
		                    $rating = true;
                    }
                    $count = (int)get_post_meta($post->ID, 'rating_count', true);
                    $count = max(0, $count);
                    $score = (float)get_post_meta($post->ID, 'rating_avg', true);
                    $score = round($score, 1, PHP_ROUND_HALF_DOWN);
                    ?>

                    <div class="lg:w-2/3 p-6 lg:p-8 bg-white rounded-xl shadow-md">
                        <?php k_breadcrumb(); ?>
                        <h1 class="text-xl lg:text-2xl font-bold mb-4 text-romfun-pink"><?php the_title() ?></h1>
                        <div class="flex flex-wrap items-center gap-4 mb-4">
                            <div class="flex items-center">
                                <div class="d-flex align-items-center min-w-[5rem]">
                                    <div class="rating" data-post_id="<?php echo $post->ID; ?>"
                                         data-rateyo-rating="<?php echo $score; ?>"
                                         <?php if ( $rating ) : ?>data-rateyo-read-only="true"<?php endif; ?>></div>
                                </div>

                                <span class="ml-2 text-sm text-gray-600"><?php echo $score; ?></span>
                                <span class="ml-2 text-sm text-gray-500">(<?php echo $count . ' ' . _n( 'review', 'reviews', $count, 'romsfun' ); ?>)</span>
                            </div>

                            <span class="text-gray-400">•</span>
                            <span class="text-sm text-gray-600">
                                <?php
                                $download = get_field('downloads', $post->ID);
                                echo number_format( $download ?: 0, 0, '', ',' ) . ' ' . __( 'downloads', 'romsfun' ); ?>
                            </span>

                            <span class="text-gray-400">•</span>
                            <span class="text-sm text-gray-600">
                            <?php
                                if ( $file_size = get_field('file_size') ) :
                                    echo $file_size;
                                elseif( is_array( $download_list ) && !empty( $download_list ) ) :
                                    echo $download_list[0]['file_size'];
                                endif;
                            ?>
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-2 mb-6">
                            <a class="px-4 py-1 rounded-full text-romfun-pink border border-brand/50 text-xs font-semibold"
                                href="<?php echo esc_url( $console_link ); ?>"> <?php echo esc_html( $console->name ); ?> </a>
                            
                            <?php
                            $terms    = wp_get_post_terms( $post->ID, 'genre', array( 'fields' => 'id=>name' ) );
                            if ( !is_wp_error( $terms ) && !empty( $terms ) ) :
                                $links = array();
                                foreach ( $terms as $term_id => $name ) {
                                    $links[] = sprintf(
                                        '<a class="px-4 py-1 rounded-full bg-green-50 text-green-500 border border-green-300 text-xs font-semibold" href="%s">%s</a>',
                                        $console_link . '?genres[]=' . $term_id,
                                        $name
                                    );
                                }
                                echo implode( '', $links );
                            endif;
                            ?>
                        </div>

                        <div class="mb-6">
                            <table class="w-full">
                                <tbody>

                                <?php if ( $released = get_field('released', $post->ID) ) : ?>
                                    <tr class="odd:bg-gray-100">
                                        <td class="rounded-l-full font-semibold py-3 pl-4 text-gray-600">
                                            <div class="flex">
                                                <?php echo get_svg_icon( 'released' ) . __( 'Release Date', 'romsfun' ) ?>
                                            </div>
                                        </td>
                                        <td class="font-semibold text-gray-900 rounded-r-full text-right pr-4"><?php echo wpautop( $released ); ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php
                                $terms   = wp_get_post_terms( $post->ID, 'collection', array( 'fields' => 'id=>name' ) );
                                if ( !is_wp_error( $terms ) && !empty( $terms ) ) : ?>
                                    <tr class="odd:bg-gray-100">
                                        <td class="rounded-l-full font-semibold py-3 pl-4 text-gray-600">
                                            <div class="flex">
                                                <?php echo get_svg_icon( 'collection' ) . __( 'Collection', 'romsfun' ) ?>
                                            </div>
                                        </td>
                                        <td class="text-romfun-pink rounded-r-full text-right pr-4 font-semibold">
                                            <?php
                                            $links = array();
                                            foreach ( $terms as $term_id => $name ) {
                                                $links[] = sprintf(
                                                    '<a href="%s">%s</a>',
                                                    $console_link . '?collections[]=' . $term_id,
                                                    $name
                                                );
                                            }
                                            echo implode( ', ', $links );
                                            ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php
                                $terms   = wp_get_post_terms( $post->ID, 'type', array( 'fields' => 'id=>name' ) );
                                if ( !is_wp_error( $terms ) && !empty( $terms ) ) : ?>
                                    <tr class="odd:bg-gray-100">
                                        <td class="rounded-l-full font-semibold py-3 pl-4 text-gray-600">
                                            <?php echo get_svg_icon( 'type' ) . __( 'Type', 'romsfun' ) ?>
                                        </td>
                                        <td class="text-romfun-pink rounded-r-full text-right pr-4 font-semibold">
                                            <?php
                                            $links = array();
                                            foreach ( $terms as $term_id => $name ) {
                                                $links[] = sprintf(
                                                    '<a href="%s">%s</a>',
                                                    $console_link . '?types[]=' . $term_id,
                                                    $name
                                                );
                                            }
                                            echo implode( ', ', $links );
                                            ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php
                                $terms = wp_get_post_terms( $post->ID, 'pub', array( 'fields' => 'id=>name' ) );
                                if ( !is_wp_error( $terms ) && !empty( $terms ) ) :
                                    ?>
                                    <tr class="odd:bg-gray-100">
                                        <td class="rounded-l-full font-semibold py-3 pl-4 text-gray-600">
                                            <div class="flex">
                                                <?php echo get_svg_icon( 'joystick' ) . __( 'Publisher', 'romsfun' ) ?>
                                            </div>
                                        </td>
                                        <td class="text-romfun-pink rounded-r-full text-right pr-4 font-semibold">
                                            <?php
                                            $links = array();
                                            foreach ( $terms as $term_id => $name ) {
                                                $links[] = sprintf(
                                                    '<a href="%s">%s</a>',
                                                    get_term_link( $term_id ),
                                                    $name
                                                );
                                            }
                                            echo implode( ', ', $links );
                                            ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php
                                $terms = wp_get_post_terms( $post->ID, 'dev', array( 'fields' => 'id=>name' ) );
                                if ( !is_wp_error( $terms ) && !empty( $terms ) ) :
                                    ?>
                                    <tr class="odd:bg-gray-100">
                                        <td class=" rounded-l-full font-semibold py-3 pl-4 text-gray-600">
                                            <?php echo get_svg_icon( 'dev' ) . __( 'Developer', 'romsfun' ) ?>
                                        </td>
                                        <td class="text-romfun-pink rounded-r-full text-right pr-4 font-semibold">
                                            <?php
                                            $links = array();
                                            foreach ( $terms as $term_id => $name ) {
                                                $links[] = sprintf(
                                                    '<a href="%s">%s</a>',
                                                    get_term_link( $term_id ),
                                                    $name
                                                );
                                            }

                                            echo implode( ', ', $links );
                                            ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php
                                $terms = wp_get_post_terms( $post->ID, 'region', array( 'fields' => 'id=>name' ) );
                                if ( !is_wp_error( $terms ) && !empty( $terms ) ) :
                                    ?>
                                    <tr class="odd:bg-gray-100">
                                        <td class="rounded-l-full font-semibold py-3 pl-4 text-gray-600">
                                            <?php echo get_svg_icon( 'region' ) . __( 'Region', 'romsfun' ) ?>
                                        </td>
                                        <td class="text-romfun-pink rounded-r-full text-right pr-4 font-semibold">
                                            <?php
                                            $links = array();
                                            foreach ( $terms as $term_id => $name ) {
                                                $links[] = sprintf(
                                                    '<a href="%s">%s</a>',
                                                    get_term_link( $term_id ),
                                                    $name
                                                );
                                            }
                                            echo implode( ', ', $links );
                                            ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

	                    <?php if ( !empty($download_list) ) :
			                    $download_slug = $post->post_name . '-' . $post->ID;
			                    $rf_restricted = rf_is_restricted( $post->ID );
			                    if ( $rf_restricted ) rf_render_restricted_notice(); ?>
                                <span class="<?php echo $rf_restricted ? 'rf-authed-only rf-hide-blocked' : ''; ?>">
                                    <a href="<?php echo home_url( 'download/' . $download_slug ); ?>"
                                       class="inline-flex px-8 py-2 mb-2 mr-2 bg-romfun-pink text-white rounded-full hover:bg-romfun-pink-dark transition font-semibold">
				                        <?php echo get_svg_icon('save') . __( 'Download ROM', 'romsfun' ) ?>
                                    </a>

			                        <?php $term = get_term_by('slug', $console->slug, 'emulator_console');
			                        if ( !is_wp_error( $term ) && !empty( $term ) && $term->count > 0 ) : ?>
                                        <a href="<?php echo get_term_link( $term->term_id ); ?>"
                                           class="inline-flex px-6 py-2 bg-gray-100 text-gray-800 rounded-full hover:bg-gray-200 transition font-semibold">
					                        <?php echo get_svg_icon('emulator') . __( 'Download', 'romsfun' ) .' ' . $term->name .' ' . __('Emulator', 'romsfun') ?>
                                        </a>
			                        <?php endif; ?>
                                </span>
	                    <?php endif; ?>

	                    <?php rf_render_wishlist_button( $post->ID ); ?>
                    </div>
                </div>
            </div>

            <?php if ( $screenshots = get_field( 'image' ) ): ?>
                <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                    <h2 class="text-lg font-bold mb-4 text-gray-900"><?php _e( 'Screenshots', 'romsfun' ); ?></h2>

                    <div id="romGallery" class="flex flex-nowrap gap-3 overflow-x-auto py-2 px-1">
                        <?php
                        foreach ( $screenshots as $img ) :
                            $caption = $img['caption'] ?? $img['alt'] ?? get_the_title();
                            $alt_text = $img['alt'] ?: get_the_title() . ' - Screenshot';
                            ?>
                            <a href="<?php echo esc_url( $img['url'] ); ?>"
                               class="flex-shrink-0 group cursor-pointer block"
                               data-src="<?php echo esc_url( $img['url'] ); ?>"
                               data-sub-html="<?php echo esc_attr( $caption ); ?>">
                                <img src="<?php echo esc_url( $img['url'] ); ?>"
                                     alt="<?php echo esc_attr( $alt_text ); ?>"
                                     class="w-auto h-36 object-cover rounded-lg border-2 border-gray-200 transition-all duration-200 group-hover:shadow-lg group-hover:scale-105"
                                     loading="lazy"/>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( $post->post_content !== '' ) : ?>
                <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                    <h2 class="text-lg font-bold mb-4 text-gray-900"><?php _e( 'Description', 'romsfun' ); ?></h2>
                    <div class="prose max-w-none text-gray-700">
                        <?php the_content(); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php get_template_part( 'template-parts/rating-section' ); ?>

	        <?php get_template_part( 'template-parts/report-form' ); ?>

            <?php
            $collections = wp_get_post_terms( $post->ID, 'collection' );
            if ( ! is_wp_error( $collections ) && ! empty( $collections ) ) :
                $primary_collection = $collections[0];
                
                $collection_roms_query = new WP_Query( array(
                        'post_type'      => 'rom',
                        'posts_per_page' => 10,
                        'post__not_in'   => array( $post->ID ),
                        'post_status'    => 'publish',
                        'no_found_rows'  => true,
                        'tax_query'      => array(
                                array(
                                        'taxonomy' => 'collection',
                                        'field'    => 'term_id',
                                        'terms'    => $primary_collection->term_id,
                                ),
                        )
                ) );

                if ( $collection_roms_query->have_posts() ) : ?>

                    <div class="mb-8">
                        <h2 class="text-lg font-bold mb-4 text-gray-900">
                            <?php echo esc_html( sprintf( __( 'Others ROM in %s Collection', 'romsfun' ), $primary_collection->name ) ); ?>
                        </h2>
                        <div class="horizontal-scroll-container -mx-2 px-2">
                            <div class="horizontal-scroll-wrapper">
                                <?php
                                while ( $collection_roms_query->have_posts() ) : $collection_roms_query->the_post();
                                    get_template_part( 'template-parts/rom-item-grid' );
                                endwhile;
                                wp_reset_postdata(); ?>
                            </div>
                        </div>
                    </div>
                <?php
                endif;
            endif;
            ?>

            <?php
                $query = new WP_Query( array(
                        'post_type'      => 'rom',
                        'posts_per_page' => 6,
                        'post__not_in'   => array( $post->ID ),
                        'post_status'    => 'publish',
                        'no_found_rows'  => true,
                        'tax_query'      => array(
                                array(
                                        'taxonomy' => 'console',
                                        'field'    => 'term_id',
                                        'terms'    => $console->term_id,
                                ),
                        )
                ) );

                if ( $query->have_posts() ) :?>

                    <div class="mb-8">
                        <h2 class="text-lg font-bold mb-4 text-gray-900"><?php _e( 'Related ROMs', 'romsfun' ) ?></h2>
                        <div class="horizontal-scroll-container -mx-2 px-2">
                            <div class="horizontal-scroll-wrapper">
                                <?php
                                while ( $query->have_posts() ) : $query->the_post();
                                    get_template_part( 'template-parts/rom-item-grid' );
                                endwhile;
                                wp_reset_postdata(); ?>
                            </div>
                        </div>
                    </div>
                <?php
                endif; ?>

            <?php comments_template(); ?>
        </div>
    </section>

    <?php
    $related_post_ids = get_field( 'related_posts', $post->ID );

    if ( ! empty( $related_post_ids ) ) :

        $related_posts_query = new WP_Query( array(
                'post__in'               => $related_post_ids,
                'orderby'                => 'post__in',
                'posts_per_page'         => - 1,
                'no_found_rows'          => true,
                'post_status'            => 'publish',
                'post_type'              => 'post',
        ) );

        if ( $related_posts_query->have_posts() ) : ?>
            <section class="py- text-gray-900 mt-8 mb-8">
                <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
                    <h2 class="text-lg font-semibold mb-4">
                        <?php _e( 'Related Articles', 'romsfun' ); ?>
                    </h2>

                    <div class="horizontal-scroll-container">
                        <div class="horizontal-scroll-wrapper">
                            <?php
                            while ( $related_posts_query->have_posts() ) : $related_posts_query->the_post();
                                get_template_part( 'template-parts/blog-item' );
                            endwhile;
                            wp_reset_postdata(); ?>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>

<?php
endwhile;
get_footer();
