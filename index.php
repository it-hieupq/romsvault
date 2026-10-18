<?php
/**
 * Template Name: Index
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 *
 * @package romsvault
 */
get_header();

    get_template_part('template-parts/rom-filter');
    ?>


    <?php
    /**
     * Collections with most ROMs
     */
    $selected_collections = get_field('home_collections', 'option');
    $terms = !empty($selected_collections)
        ? get_terms(array(
            'taxonomy'   => 'collection',
            'include'    => $selected_collections,
            'orderby'    => 'include',
            'hide_empty' => false,
        ))
        : array();
    if (!is_wp_error($terms) && !empty($terms)) :

        $pages = get_pages([
                'meta_key'   => '_wp_page_template',
                'meta_value' => 'page-collections.php', // template name
                'number'     => 1, // Only get 1 page
                'hierarchical' => 0
        ]);
        $collection_link = isset($pages[0]) ? get_permalink($pages[0]->ID) : '#';
        ?>
        <section class="mb-16">
            <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">
                        <p class="text-xs font-semibold uppercase tracking-wider text-brand"><?php _e('Choice for you', 'rom') ?></p>
                        <?php _e('Best ROM collections', 'rom'); ?>
                    </h2>
                    <a href="<?php echo $collection_link ?>"
                       class="inline-flex button button-primary bg-rom-pink px-4 py-1 text-white rounded-full font-medium text-sm">
                        <?php echo __('View all', 'rom') . get_svg_icon('more'); ?>
                    </a>
                </div>
            </div>

            <div class="content-container p-4 sm:px-6 lg:px-8 w-full">
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                    <?php foreach ($terms as $term) : ?>
                        <a href="<?php echo get_term_link($term) ?>"
                           class="border border-gray-200 rounded-xl block shadow-sm hover:shadow-lg transition overflow-hidden">
                            <div class="h-[140px]">
                                <img src="<?php
                                $banner = get_field('collection_banner', 'term_' . $term->term_id);
                                if ($banner) {
                                    echo $banner['sizes']['thumbnail'];
                                }
                                ?>" alt="<?php echo $term->name ?>" class="w-full h-full object-cover">
                            </div>
                            <div class="console-info p-4 bg-white">
                                <div class="font-semibold text-base mb-4 hover:text-rom-pink"><?php echo $term->name ?></div>
                                <div class="text-sm text-muted flex justify-between">
                                <span class="inline-flex items-center">
                                    <?php echo get_svg_icon('joystick') . number_format($term->count ?: 0, 0, '') . ' ' . __('ROM', 'rom') ?>
                                </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

        </section>
    <?php endif; ?>

    <?php if (is_page()) : ?>
        <section class="mb-16">
            <div class="content-container px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl p-6 shadow-lg">
                    <h2 class="text-xl font-semibold mb-6">
                        <p class="text-xs font-semibold uppercase tracking-wider text-brand"><?php _e('Announcement', 'rom') ?></p>
                        <?php echo $post->post_title; ?>
                    </h2>
                    <div class="text-gray-700 revert">
                        <?php echo $post->post_content; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php
    /**
     * Latest posts
     */
    $query = new WP_Query(array(
        'post_type' => 'post',
        'posts_per_page' => 6,
        'no_found_rows' => true,
        'post_status' => 'publish',
        'orderby' => 'modified',
        'order' => 'DESC',
    ));
    if ($query->have_posts()) :
        ?>
        <section class="mb-16">
            <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
                <div class="flex items-center justify-between mb-6">

                    <h2 class="text-xl font-semibold">
                        <p class="text-xs font-semibold uppercase tracking-wider text-brand"><?php _e('Featured', 'rom') ?></p>
                        <?php echo __('Latest News', 'rom') ?>
                    </h2>

                    <a href="<?php echo home_url('/blog') ?>"
                       class="flex button button-primary bg-rom-pink px-4 py-1 text-white rounded-full font-medium text-sm">
                        <?php echo __('View all', 'rom') . get_svg_icon('more'); ?>
                    </a>
                </div>

                <div class="horizontal-scroll-container">
                    <div class="horizontal-scroll-wrapper">
                        <?php
                        while ($query->have_posts()) : $query->the_post(); ?>
                            <div class="flex-shrink-0 w-full md:w-1/2 lg:w-1/3 min-w-0 px-2 box-border grid">
                                <?php get_template_part('template-parts/post-card'); ?>
                            </div>
                        <?php endwhile;
                        wp_reset_postdata(); ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php
    $base_link = get_filter_base_url();
    $args = array(
        'post_type' => 'rom',
        'posts_per_page' => 10,
        'no_found_rows' => true,
        'post_status' => 'publish',
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
        'meta_key' => 'downloads',
        'orderby' => 'meta_value_num',
        'order' => 'DESC',
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) : ?>
        <section class="mb-16">
            <div class="content-container px-4 sm:px-6 lg:px-8 w-full">

                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold">
                        <p class="text-xs font-semibold uppercase tracking-wider text-brand">
                            <?php _e('Trending', 'rom') ?>
                        </p>
                        <?php echo _e('Popular ROM', 'rom'); ?>
                    </h2>
                    <a href="<?php echo $base_link . '?sort=downloads' ?>"
                       class="flex button button-primary bg-rom-pink px-4 py-1 text-white rounded-full hover:underline font-medium text-sm">
                        <?php echo __('More', 'rom') . get_svg_icon('more'); ?>
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 sm:gap-6">
                    <?php
                    while ($query->have_posts()) : $query->the_post();
                        get_template_part('template-parts/rom-card');
                    endwhile;
                    wp_reset_postdata(); ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php
    $base_link = get_filter_base_url();
    $args = array(
        'post_type' => 'rom',
        'posts_per_page' => 10,
        'no_found_rows' => true,
        'post_status' => 'publish',
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
        'meta_key' => 'downloads',
        'orderby' => 'date',
        'order' => 'DESC',
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) : ?>
        <section class="mb-16">
            <div class="content-container px-4 sm:px-6 lg:px-8 w-full">

                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold">
                        <p class="text-xs font-semibold uppercase tracking-wider text-brand">
                            <?php _e('Fresh upload', 'rom') ?>
                        </p>
                        <?php _e('Latest ROM', 'rom'); ?>
                    </h2>
                    <a href="<?php echo $base_link . '?sort=latest' ?>"
                       class="flex button button-primary bg-rom-pink px-4 py-1 text-white rounded-full hover:underline font-medium text-sm">
                        <?php echo __('More', 'rom') . get_svg_icon('more'); ?>
                    </a>
                </div>

                <div class="horizontal-scroll-container">
                    <div class="horizontal-scroll-wrapper">
                        <?php
                        while ($query->have_posts()) : $query->the_post(); ?>
                            <div class="flex-shrink-0 w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 min-w-0 px-2 box-border grid">
                                <?php get_template_part('template-parts/rom-card'); ?>
                            </div>
                        <?php endwhile;
                        wp_reset_postdata(); ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

<?php
get_footer();
