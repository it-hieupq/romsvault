<?php
/**
 * The template for displaying all single posts.
 *
 * @package romsvault
 */

get_header();
get_template_part( 'template-parts/hero' );
while ( have_posts() ) : the_post();
	global $post;
    $views = (int) get_post_meta( $post->ID, 'views', true );
	?>

    <section class="py-6 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
            <article class="pb-8">
                <div class="bg-white p-6 lg:p-8 rounded-2xl shadow-md">
					<?php the_content(); ?>
                </div>
            </article>

			<?php
			$categories = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) :
				$args = array(
					'posts_per_page' => 6,
					'post__not_in'   => array( $post->ID ),
					'no_found_rows'  => true,
					'tax_query'      => array(
						array(
							'taxonomy' => 'category',
							'field'    => 'term_id',
							'terms'    => $categories
						)
					)
				);
				$query  = new WP_Query( $args );
				if ( $query->have_posts() ) :
					?>
                    <div class="space-x-2 pb-6">
                        <h2 class="text-xl font-bold flex items-center mb-4">
                            <?php _e( 'Related posts', 'rom' ); ?>
                        </h2>

                        <div class="horizontal-scroll-container">
                            <div class="horizontal-scroll-wrapper">
								<?php
								while ( $query->have_posts() ) : $query->the_post(); ?>
                                    <div class="flex-shrink-0 w-full md:w-1/2 lg:w-1/3 min-w-0 px-1.5 md:px-2 box-border grid">
										<?php get_template_part( 'template-parts/post-card' ); ?>
                                    </div>
								<?php endwhile;
								wp_reset_postdata();
								?>
                            </div>
                        </div>
                    </div>
				<?php
				endif;
			endif;
			?>

			<?php comments_template(); ?>
        </div>

    </section>

<?php
endwhile;

get_footer();