<?php
/**
 * Taxonomy collection
 *
 * @package romsvault
 */

get_header();
get_template_part('template-parts/hero');

$paged = ( get_query_var('paged') ) ? get_query_var('paged') : 1;
$taxonomy_obj       = get_queried_object(); // term object
$taxonomy_post_type = '';

if ( $taxonomy_obj && ! empty( $taxonomy_obj->taxonomy ) ) :
    $taxonomy = get_taxonomy( $taxonomy_obj->taxonomy );

    if ( $taxonomy && ! empty( $taxonomy->object_type ) ) :
        $object_types       = (array) $taxonomy->object_type;
        $taxonomy_post_type = reset( $object_types ); // get first post type
    endif;
endif;
$template_slug = get_taxonomy_template_slug_by_post_type( $taxonomy_post_type );
?>

    <section class="pb-6 text-gray-900">
        <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 w-full">

            <h1 class="text-lg font-semibold py-6">
                <?php
                $title_suffix = get_taxonomy_title_suffix_by_post_type( $taxonomy_post_type );

                echo __( 'Explore', 'rom' ) . ' ' . esc_html( $taxonomy_obj->name ) . ' ' . $title_suffix;
                ?>
            </h1>

			<?php if ( have_posts() ) : ?>
                <div class="<?php echo 'rom-card' === $template_slug ? 'grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 sm:gap-6' : 'space-y-4'; ?>">
					<?php while ( have_posts() ) : the_post();
                        get_template_part( 'template-parts/' . $template_slug );
					endwhile; ?>
                </div>
			<?php
				k_pagination(); 
			else :
				get_template_part('template-parts/content', 'none');
			endif;
			?>

	        <?php if ( $paged == 1 && $desc = get_queried_object()->description ) : ?>

                <div class="px-6 py-4 my-12 rounded-xl bg-white">
                    <h2 class="text-xl mb-4 font-semibold text-gray-800"><?php _e('Information', 'rom') ?></h2>
                    <?php echo $desc; ?>
                </div>

	        <?php endif; ?>
		</div>
	</section>
<?php
get_footer();