<?php
/**
 * Template Name: Roms
 *
 * @package romsvault
 */

get_header();
global $post;
get_template_part( 'template-parts/hero' );

while ( have_posts() ) : the_post();
    ?>
    <section class="pb-6 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">

            <?php if ( $post->post_content ) : ?>
                <div class="pt-6">
                    <?php the_content(); ?>
                </div>
            <?php endif; ?>

            <h2 class="text-xl py-6 font-bold text-gray-800">
                <?php _e('List of available consoles', 'rom'); ?>
            </h2>

            <?php get_template_part( 'template-parts/console-groups' ) ?>
        </div>
    </section>
<?php
endwhile;
get_footer();