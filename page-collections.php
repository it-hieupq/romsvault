<?php
/**
 * Template Name: Collections
 *
 * @package romsvault
 */

get_header();
get_template_part( 'template-parts/hero' );
global $post;
while ( have_posts() ) : the_post();
    ?>
    <?php if ( $post->post_content ) : ?>
        <section class="py-6 text-gray-900">
            <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 w-full">
                <h1 class="text-lg font-bold"><?php esc_html_e( 'Explore our best ROM\'s Collection', 'rom' ); ?></h1>
                <?php the_content(); ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="pb-6 text-gray-900">
        <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <?php
            $terms = get_terms( array( 'taxonomy' => 'collection', 'fields' => 'all' ) );
            if ( !is_wp_error( $terms ) && !empty( $terms ) ) : ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                    <?php foreach ( $terms as $term ) :
                        get_template_part( 'template-parts/collection-card', null, array( 'term' => $term ) );
                    endforeach; ?>
                </div>

            <?php
            else :
                get_template_part( 'template-parts/content', 'none' );
            endif;
            ?>
        </div>
    </section>
<?php
endwhile;

get_footer();