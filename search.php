<?php
/**
 * The template for displaying search results.
 * @package romsvault
 */

get_header();
global $wp_query;

$keywords = get_search_query();
?>
<?php get_template_part( 'template-parts/hero' ); ?>
    <section class="py-6 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">

            <h2 class="text-lg font-bold flex items-center mb-8">
                <?php echo __('Found ', 'rom') . '  ' . $wp_query->found_posts . ' ' . __('results', 'rom'); ?>
            </h2>

            <?php if ( have_posts() ) : ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 sm:gap-6">
                    <?php while ( have_posts() ) : the_post();
                        get_template_part( 'template-parts/rom-card' );
                    endwhile; ?>
                </div>
                <?php k_pagination();
            else :
                get_template_part( 'template-parts/content', 'none' );
            endif;
            ?>

    </section>
<?php
get_footer();