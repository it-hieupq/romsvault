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
            <div class="content-container px-4 sm:px-6 lg:px-8 w-full rounded-lg">
                <h1 class="text-lg font-bold"><?php _e( 'Explore our best ROM\'s Collection ' ); ?></h1>
                <?php the_content(); ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="pb-6 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
            <?php
            $terms = get_terms( array( 'taxonomy' => 'collection', 'fields' => 'all' ) );
            if ( !is_wp_error( $terms ) && !empty( $terms ) ) : ?>

                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                    <?php foreach ( $terms as $term ) : ?>
                        <a href="<?php echo get_term_link($term) ?>"
                           class="border border-gray-200 rounded-xl block shadow-sm hover:shadow-lg transition overflow-hidden">
                            <div class="h-[140px]">
                                <img src="<?php
                                    $banner = get_field('collection_banner', 'term_'.$term->term_id );
                                    if( $banner ) {
                                        echo $banner['sizes']['thumbnail'];
                                    }
                                    ?>"
                                     alt="<?php echo $term->name ?>" class="w-full h-full object-cover">
                            </div>
                            <div class="console-info p-4 bg-white">
                                <div class="font-semibold text-base mb-4 hover:text-romfun-pink"><?php echo $term->name ?></div>
                                <div class="text-sm text-muted flex justify-between">
                                        <span class="inline-flex items-center">
                                            <?php echo get_svg_icon( 'joystick' ) . number_format( $term->count ?: 0, 0, '' ) . ' ' . __('ROM', 'rom') ?>
                                        </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
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