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
                        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
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
                                    while ( $query->have_posts() ) : $query->the_post();
                                        get_template_part( 'template-parts/blog-item' );
                                    endwhile;
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
                    <div class="content-container px-4 sm:px-6 lg:px-8">
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
                    <div class="content-container px-4 sm:px-6 lg:px-8 w-full">

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
                    <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
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

                    <div class="content-container p-4 sm:px-6 lg:px-8 w-full">
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
                                        ?>" alt="<?php echo $term->name ?>" class="w-full h-full object-cover">
                                    </div>
                                    <div class="console-info p-4 bg-white">
                                        <div class="font-semibold text-base mb-4 hover:text-rom-pink"><?php echo $term->name ?></div>
                                        <div class="text-sm text-muted flex justify-between">
                                            <span class="inline-flex items-center">
                                                <?php echo get_svg_icon( 'joystick' ) . number_format( $term->count ?: 0, 0, '' ) . ' ' . __('ROM', 'rom') ?>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </section>

                <?php endif; ?>

            <?php
            elseif ( get_row_layout() == 'home_roms' ) :
                $title      = get_sub_field('home_roms_title');
                $number     = get_sub_field('home_roms_number');
                $sort       = get_sub_field('home_roms_sort');
                $rom_layout = get_sub_field('home_roms_layout');
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
                        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">

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

                            <?php if ( $rom_layout == 'carousel' ) : 
                                $carousel_id = 'romCarousel' . get_row_index();
                                ?>
                                <div class="horizontal-scroll-container">
                                    <div class="horizontal-scroll-wrapper">
                                        <?php
                                        while ( $query->have_posts() ) : $query->the_post();
                                            get_template_part( 'template-parts/rom-item-grid' );
                                        endwhile;
                                        wp_reset_postdata(); ?>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="space-y-4">
                                    <?php
                                    while ( $query->have_posts() ) : $query->the_post();
                                        get_template_part( 'template-parts/rom-item-list' );
                                    endwhile;
                                    wp_reset_postdata(); ?>
                                </div>

                            <?php endif; ?>
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

