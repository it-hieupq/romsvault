<?php
/**
 * Taxonomy console
 *
 * @package romsvault
 */
global $post;
$console = get_queried_object();

get_header();
get_template_part( 'template-parts/hero');
?>

	<section class="py-6 text-gray-900">
		<div class="content-container px-4 sm:px-6 lg:px-8 w-full">

			<div class="flex items-center justify-between mb-8">
				<h2 class="text-xl font-bold flex items-center">
					<?php echo $console->name . ' ' . __('Emulator','')?>
				</h2>
			</div>

			<div>
				<?php
				$term = get_queried_object();
				$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

				// Custom query args
				$args = array(
					'post_type'      => 'emulator',
					'posts_per_page' => 10,
                    'no_found_rows'  => true,
					'paged'          => $paged,
					'tax_query'      => array(
						array(
							'taxonomy' => $term->taxonomy,
							'field'    => 'term_id',
							'terms'    => $term->term_id,
						),
					),
				);

				$query = new WP_Query( $args );

				if ( $query->have_posts() ) :
					echo '<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-4">';
					while ( $query->have_posts() ) :
						$query->the_post();
						get_template_part( 'template-parts/emu-item' );
					endwhile;
					echo '</div>';

					k_pagination( $query->max_num_pages );

					wp_reset_postdata();
				else :
					get_template_part( 'template-parts/content', 'none' );
				endif;
				?>
			</div>
		</div>
	</section>
<?php
get_footer();