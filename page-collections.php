<?php
/**
 * Template Name: Collections
 *
 * @package romsvault
 */

get_header();
// The page excerpt is the lead line under the breadcrumb, editable in wp-admin.
get_template_part( 'template-parts/hero', null, array( 'intro' => get_post_field( 'post_excerpt', get_queried_object_id() ) ) );
global $post;
while ( have_posts() ) : the_post();
    ?>
    <section class="pt-8 pb-6 text-gray-900">
        <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <?php
            $terms = get_terms( array( 'taxonomy' => 'collection', 'fields' => 'all' ) );
            if ( !is_wp_error( $terms ) && !empty( $terms ) ) : ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
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

    <?php // The page's own text, below the grid. The hero already holds the h1. ?>
    <?php if ( $post->post_content ) : ?>
        <section class="pt-10 md:pt-14 pb-12 text-gray-900">
            <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 w-full">
                <div class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-7 lg:p-9 shadow-card space-y-7">
                    <div class="border-b border-slate-100 pb-4">
                        <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php esc_html_e( 'About', 'rom' ); ?></span>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5"><?php esc_html_e( 'ROM collections by game series', 'rom' ); ?></h2>
                    </div>
                    <div class="rich-text max-w-content">
                        <?php the_content(); ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php
endwhile;

get_footer();