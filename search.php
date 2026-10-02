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
                <?php echo __('Found ', 'romsfun') . '  ' . $wp_query->found_posts . ' ' . __('results', 'romsfun'); ?>
            </h2>

            <div class="space-y-4">
                <?php if ( have_posts() ) :
                    while ( have_posts() ) : the_post();
                        get_template_part( 'template-parts/rom-item-list' );
                    endwhile;
                    k_pagination();
                else :
                    get_template_part( 'template-parts/content', 'none' );
                endif;
                ?>
            </div>

    </section>
<?php
get_footer();