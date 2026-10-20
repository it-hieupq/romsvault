<?php
/**
 * Template Name: Home
 *
 * @package romsvault
 */

get_header();

global $post;

while ( have_posts() ) :
    the_post();

    get_template_part( 'template-parts/rom-filter' );
    ?>

    <?php

    if ( have_rows( 'home_content' ) ) :
        while (  have_rows( 'home_content' ) ) : the_row();

            if ( get_row_layout() == 'home_new_posts' ) :
                $title      = get_sub_field('home_new_posts_title');
                $number     = get_sub_field('home_new_posts_number');
                $link       = get_sub_field('home_new_posts_link');

                // Generate unique carousel ID for this section
                $carousel_id = 'postCarousel' . get_row_index();

                $args = array(
                        'post_type'      => 'post',
                        'posts_per_page' => $number,
                        'no_found_rows'  => true,
                        'post_status'    => 'publish',
                        'orderby'        => 'modified',
                        'order'          => 'DESC',
                );

                $query = new WP_Query( $args );
                if ( $query->have_posts() ) :
                    ?>
                    <section class="mb-16">
                        <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 w-full">
                            <div class="flex items-center justify-between mb-6">
                                
                                <h2 class="text-xl font-semibold">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-brand"><?php _e('Featured', 'rom') ?></p>
                                    <?php echo $title ?>
                                </h2>
                                
                                <a href="<?php echo $link['url'] ?>"
                                   class="flex button button-primary bg-rom-pink px-4 py-1 text-white rounded-full hover:underline font-medium text-sm">
                                    <?php echo $link['title'] . get_svg_icon('more'); ?>
                                </a>
                            </div>

                            <div class="horizontal-scroll-container">
                                <div class="horizontal-scroll-wrapper">
                                    <?php
                                    while ( $query->have_posts() ) : $query->the_post(); ?>
                                        <div class="flex-shrink-0 w-full md:w-1/2 lg:w-1/3 min-w-0 px-1.5 md:px-2 box-border grid">
                                            <?php get_template_part( 'template-parts/post-card' ); ?>
                                        </div>
                                    <?php endwhile;
                                    wp_reset_postdata(); ?>
                                </div>
                            </div>
                        </div>
                    </section>
                    <?php
                    wp_reset_postdata();
                endif;
            elseif ( get_row_layout() == 'home_intro' ) :
	            $title = get_sub_field('home_intro_title');
	            $desc = get_sub_field('home_intro_desc');
                ?>
                <section class="mb-16">
                    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="bg-white rounded-2xl p-6 shadow-lg">
                            <h2 class="text-xl font-semibold mb-6">
                                <p class="text-xs font-semibold uppercase tracking-wider text-brand"><?php _e('Announcement', 'rom') ?></p>
                                <?php echo $title; ?>
                            </h2>
                            <div class="text-gray-700 revert">
                                <?php echo $desc; ?>
                            </div>
                        </div>
                    </div>
                </section>
            <?php
            elseif ( get_row_layout() == 'home_console_groups' ) :
                $title = get_sub_field('home_console_groups_title');
                ?>
                <section class="mb-16">
                    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 w-full">

                        <h2 class="text-xl font-semibold mb-6">
                            <p class="text-xs font-semibold uppercase tracking-wider text-brand"><?php _e('Browse by systems', 'rom') ?></p>
                            <?php echo $title ?>
                        </h2>

                        <?php get_template_part( 'template-parts/console-groups' ) ?>
                    </div>
                </section>

            <?php
            elseif ( get_row_layout() == 'home_roms_collection' ) :
                $title = get_sub_field('home_roms_collection_title');
                $link = get_sub_field('home_roms_collection_link');
                $term_ids = get_sub_field('home_roms_collection_items');
                $terms = get_terms( array( 'taxonomy' => 'collection', 'include' => $term_ids, 'fields' => 'all' ) );
                if ( !is_wp_error( $terms ) && !empty( $terms ) ) :
                    $term_ids = wp_list_pluck( $terms, 'term_id' );
                    // Preload all term meta
                    update_termmeta_cache( $term_ids );
                    ?>
                <section class="mb-16">
                    <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 w-full">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold">
                                <p class="text-xs font-semibold uppercase tracking-wider text-brand"><?php _e('Choice for you', 'rom') ?></p>
                                <?php echo $title; ?>
                            </h2>
                            <a href="<?php echo $link['url'] ?>" class="inline-flex button button-primary bg-rom-pink px-4 py-1 text-white rounded-full hover:underline font-medium text-sm">
                                <?php echo $link['title'] . get_svg_icon('more'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="max-w-site mx-auto p-4 sm:px-6 lg:px-8 w-full">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
                            <?php foreach ( $terms as $term ) :
                                get_template_part( 'template-parts/collection-card', null, array( 'term' => $term ) );
                            endforeach; ?>
                        </div>
                    </div>

                </section>

                <?php endif; ?>

            <?php
            elseif ( get_row_layout() == 'home_roms' ) :
                $title      = get_sub_field('home_roms_title');
                $number     = get_sub_field('home_roms_number');
                $sort       = get_sub_field('home_roms_sort');
                $base_link  = get_filter_base_url();

                $args = array(
                        'post_type'      => 'rom',
                        'posts_per_page' => min( $number, 12 ),
                        'no_found_rows'  => true,
                        'post_status'    => 'publish',
                );

                if ( $sort == 'popular' ) {
                    $args['meta_key'] = 'downloads';
                    $args['orderby']  = 'meta_value_num';
                    $args['order']    = 'DESC';

                    $base_link .= '?sort=downloads';
                } else {
                    $args['orderby'] = 'date';
                    $args['order']   = 'DESC';
                    $base_link .= '?sort=latest';
                }

                $query = new WP_Query( $args );
                if ( $query->have_posts() ) : ?>
                    <section class="mb-16">
                        <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 w-full">

                            <div class="flex items-center justify-between mb-6">
                                <h2 class="text-xl font-semibold">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-brand">
                                        <?php echo $sort == 'popular' ? __('Trending', 'rom') : __('Fresh upload', 'rom') ?>
                                    </p>
                                    <?php echo $title; ?>
                                </h2>
                                <a href="<?php echo $base_link ?>"
                                   class="flex button button-primary bg-rom-pink px-4 py-1 text-white rounded-full hover:underline font-medium text-sm">
                                    <?php echo __( 'More', 'rom' ) . get_svg_icon('more'); ?>
                                </a>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-4">
                                <?php
                                while ( $query->have_posts() ) : $query->the_post();
                                    get_template_part( 'template-parts/rom-card' );
                                endwhile;
                                wp_reset_postdata(); ?>
                            </div>
                        </div>
                    </section>
                    <?php
                    wp_reset_postdata();
                endif;
            endif;
        endwhile;
        wp_reset_postdata();
    endif;
endwhile;
get_footer();
?>

