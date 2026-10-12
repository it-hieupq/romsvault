<?php
get_header(); 
while ( have_posts() ) : the_post();
    global $post;
	$terms = wp_get_post_terms($post->ID, 'emulator_console', array('fields' => 'all'));
	if ( ! is_wp_error($terms) && ! empty($terms) ) {
		$console = $terms[0];
	} else {
        rf_force_404();
	}

	$views = (int) get_field('views', $post->ID);
?>
    <section class="py-8 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">

            <div class="overflow-hidden pb-8">
                <div class="flex flex-col lg:flex-row gap-4">

                    <div class="lg:w-1/3 flex items-center justify-center p-6 bg-white rounded-xl shadow-md">
                        <img src="<?php the_post_thumbnail_url(); ?>" alt="<?php the_title() ?>" class="w-full h-auto max-w-full object-contain">
                    </div>

					<?php
					$rating = false;
					if ( isset( $_COOKIE['ratings'] ) ) {
						$ratings = json_decode( stripslashes( $_COOKIE['ratings'] ), true );
						if ( in_array( $post->ID, $ratings ) ) {
							$rating = true;
						}
					}
					$count = (int)get_post_meta( get_the_ID(), 'rating_count', true );
					$count = max(0, $count);
					$score = (float)get_post_meta( get_the_ID(), 'rating_avg', true );
					$score = round($score, 1, PHP_ROUND_HALF_DOWN);
					?>

                    <div class="lg:w-2/3 p-6 lg:p-8 bg-white rounded-xl shadow-md">
						<?php k_breadcrumb(); ?>
                        <h1 class="text-xl lg:text-2xl font-bold mb-4 text-romfun-pink"><?php the_title() ?></h1>
                        <div class="flex flex-wrap items-center gap-4 mb-4">
                            <div class="flex items-center">
                                <div class="d-flex align-items-center min-w-[5rem]">
                                    <div class="rating" data-post_id="<?php echo $post->ID; ?>" data-rateyo-rating="<?php echo $score; ?>"
									     <?php if ( $rating ) : ?>data-rateyo-read-only="true"<?php endif; ?>></div>
                                </div>

                                <span class="ml-2 text-sm text-gray-600"><?php echo $score; ?></span>
                                <span class="ml-2 text-sm text-gray-500">(<?php echo $count . ' ' . _n( 'review', 'reviews', $count, 'rom' ); ?>)</span>
                            </div>

							<?php if ( $key = get_field('downloads', $post->ID) ) : ?>
                                <span class="text-gray-400">•</span>
                                <span class="text-sm text-gray-600"><?php echo number_format( $key ?: 0, 0, '', ',' ). ' ' . __( 'downloads', 'rom' ); ?></span>
							<?php endif; ?>

							<?php if ( $key = get_field('file_size', $post->ID) ) : ?>
                                <span class="text-gray-400">•</span>
                                <span class="text-sm text-gray-600"><?php echo $key; ?></span>
							<?php endif; ?>
                        </div>

                        <div class="flex flex-wrap gap-2 mb-6">
							<a class="px-4 py-1 rounded-full text-romfun-pink border border-brand/50 text-xs font-semibold" href="<?php echo get_term_link( $console->term_id ); ?>"> <?php echo $console->name; ?> </a>
							
							<?php
							$terms    = wp_get_post_terms( $post->ID, 'os', array( 'fields' => 'id=>name' ) );
							if ( !is_wp_error($terms) && !empty($terms) ) :
								foreach ( $terms as $term_id => $term_name ) : ?>
                                    <a class="px-4 py-1 rounded-full bg-green-50 text-green-500 border border-green-300 text-xs font-semibold" href="<?php echo get_term_link($term_id); ?>"> <?php echo $term_name; ?> </a>
								<?php endforeach; ?>
							<?php endif; ?>
                        </div>

                        <div class="space-y-3 mb-6">

							<?php if( $key = get_field('released', $post->ID) ) : ?>
                                <div class="flex justify-between py-1">
                                    <span class="text-gray-600"><?php _e('Release Date', 'rom') ?></span>
                                    <span class="font-semibold text-gray-700"><?php echo wpautop($key); ?></span>
                                </div>
							<?php endif; ?>

							<?php
							$terms = wp_get_post_terms( get_the_ID(), 'region', array( 'fields' => 'id=>name' ) );
							if ( !is_wp_error($terms) && !empty($terms) ) :
								?>
                                <div class="flex justify-between py-1">
                                    <span class="text-gray-600"><?php _e('Region', 'rom') ?></span>
                                    <span class="text-romfun-pink">
                                        <?php
                                        foreach ( $terms as $term_id => $term_name ) : ?>
                                            <a href="<?php echo get_term_link($term_id); ?>"><?php echo $term_name; ?></a>
                                        <?php endforeach; ?>
										<?php echo implode(', ', $links); ?>
                                    </span>
                                </div>
							<?php endif; ?>
                        </div>

	                    <?php if ( get_field('download_list', $post->ID) || get_field('download_list_aff', $post->ID) ) :
			                    $download_url = trailingslashit( get_permalink( $post ) ) . 'download/';
			                    $rf_restricted = rf_is_restricted( $post->ID );
			                    if ( $rf_restricted ) rf_render_restricted_notice(); ?>
                                <span class="<?php echo $rf_restricted ? 'rf-authed-only rf-hide-blocked' : ''; ?>">
                                    <a href="<?php echo esc_url( $download_url ); ?>"
                                       class="inline-block px-8 py-2 bg-romfun-pink text-white rounded-full hover:bg-romfun-pink-dark transition font-semibold">
				                        <?php echo get_svg_icon('save') . __( 'Download Emulator', 'rom' ) ?>
                                    </a>
                                </span>
	                    <?php endif; ?>

	                    <?php rf_render_wishlist_button( $post->ID ); ?>
                    </div>
                </div>
            </div>

			<?php if ( $screenshots = get_field('image') ): ?>
                <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                    <h2 class="text-lg font-bold mb-4 text-gray-900"><?php _e( 'Screenshots', 'rom' ); ?></h2>

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
                    <h2 class="text-lg font-bold mb-4 text-gray-900"><?php _e( 'Description', 'rom' ); ?></h2>
                    <div class="prose max-w-none text-gray-700 revert">
						<?php the_content(); ?>
                    </div>
                </div>
			<?php endif; ?>

			<?php get_template_part( 'template-parts/rating-section' ); ?>

			<?php
				$query = new WP_Query(array(
					'post_type' => 'emulator',
					'posts_per_page' => 6,
					'post__not_in' => array($post->ID),
					'post_status' => 'publish',
					'no_found_rows' => true,
					'tax_query' => array(
						array(
							'taxonomy' => 'emulator_console',
							'field' => 'term_id',
							'terms' => $console->term_id,
						),
					),
				));
				if ( $query->have_posts() ) :?>

                    <div class="mb-8">
                        <h2 class="text-lg font-bold mb-4 text-gray-900"><?php _e('Related Emulators', 'rom') ?></h2>
                        <div class="space-y-4">
							<?php
							while ( $query->have_posts() ) : $query->the_post();
								get_template_part( 'template-parts/emu-item' );
							endwhile;
							wp_reset_postdata(); ?>
                        </div>
                    </div>
				<?php
				endif;
			?>

	        <?php get_template_part( 'template-parts/report-form' ); ?>

			<?php comments_template(); ?>
        </div>
    </section>

<?php endwhile;
get_footer();
