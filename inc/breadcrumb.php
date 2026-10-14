<?php
// Breadcrumbs
function k_breadcrumb() {

    // Settings
    $breadcrums_id = 'breadcrumb';
    $home_title = __('Home', 'rom');

    $post_type = get_post_type();
    // Initialize custom taxonomy variable
    $custom_taxonomy = get_custom_console_name( $post_type );

    // Get the query & post information
    global $post, $wp_query;
    ?>

    <ul id="<?php echo $breadcrums_id; ?>" class="pb-3 flex flex-wrap items-center text-sm [&_a:hover]:underline font-bold">
        <li class="flex items-center">
            <a href="<?php echo get_home_url(); ?>" title="<?php echo $home_title; ?>" class="text-rom-pink hover:underline inline-flex items-center">
                <?php echo get_svg_icon('home').$home_title; ?>
            </a>
            <span class="mx-2 text-gray-400">/</span></li>

        <?php
        if (is_archive() && !is_tax() && !is_category() && !is_tag()) {
            ?>
            <li class="flex items-center"><span class="text-gray-500"><?php echo post_type_archive_title('', false); ?></span></li>
            <?php
        } else if (is_archive() && is_tax() && !is_category() && !is_tag()) {

            if (is_tax('console')) {
                $args = array(
                    'post_type' => 'page',
                    'posts_per_page' => 1,
                    'no_found_rows' => true,
                    'meta_key' => '_wp_page_template',
                    'meta_value' => 'page-roms.php'
                );
                $query = new WP_Query($args);
                if ($query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        ?>
                        <li class="flex items-center"><a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>" class="text-gray-500 hover:underline"><?php the_title(); ?></a><span class="mx-2 text-gray-400">/</span></li>
                        <?php
                    }
                    wp_reset_postdata();
                }
            } elseif (is_tax('emulator_console')) {
                $args = array(
                    'post_type' => 'page',
                    'posts_per_page' => 1,
                    'no_found_rows' => true,
                    'meta_key' => '_wp_page_template',
                    'meta_value' => 'page-emulators.php'
                );
                $query = new WP_Query($args);
                if ($query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        ?>
                        <li class="flex items-center"><a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>" class="text-gray-500 hover:underline"><?php the_title(); ?></a><span class="mx-2 text-gray-400">/</span></li>
                        <?php
                    }
                    wp_reset_postdata();
                }
            }

            // If post is a custom post type
            $post_type = get_post_type();

            $parent = get_queried_object()->parent;
            $terms = array();
            while ($parent) {
                $term = get_term($parent);
                $parent = $term->parent;
                array_unshift($terms, $term);
            }
            foreach ($terms as $term) {
	            $term_link = get_term_link($term);
                ?>
                <li class="flex items-center">
                    <a href="<?php echo esc_url($term_link); ?>" class="text-gray-500 hover:underline"><?php echo esc_html($term->name); ?></a><span class="mx-2 text-gray-400">/</span>
                </li>
                <?php
            }
            $custom_tax_name = get_queried_object()->name;
            ?>
            <li class="flex items-center"><span class="text-gray-500"><?php echo $custom_tax_name; ?></span></li>
            <?php
        } else if (is_single() && empty($wp_query->query_vars['download'])) {

            // If post is a custom post type
            $post_type = get_post_type();

            // Get post category info
            $category = get_the_category();

            if (!empty($category)) {

                $last_category = end($category);

                // Get parent any categories and create array
                $get_cat_parents = rtrim(get_category_parents($last_category->term_id, true, ','), ',');
                $cat_parents = explode(',', $get_cat_parents);

                // Loop through parent categories and store in variable $cat_display
                $cat_display = '';
                foreach ($cat_parents as $parents) {
                    $cat_display .= '<li class="text-gray-500">' . $parents . '<span class="mx-2 text-gray-500">/</span></li>';
                }
            }

            // If it's a custom post type within a custom taxonomy
            $taxonomy_exists = !empty($custom_taxonomy) ? taxonomy_exists($custom_taxonomy) : false;

            if (empty($last_category) && !empty($custom_taxonomy) && $taxonomy_exists) {

                if (is_singular('rom')) {
                    $args = array(
                        'post_type' => 'page',
                        'posts_per_page' => 1,
                        'no_found_rows' => true,
                        'meta_key' => '_wp_page_template',
                        'meta_value' => 'page-roms.php'
                    );
                    $query = new WP_Query($args);
                    if ($query->have_posts()) {
                        while ($query->have_posts()) {
                            $query->the_post();
                            ?>
                            <li class="flex items-center"><a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>" class="text-gray-500 hover:underline"><?php the_title(); ?></a><span class="mx-2 text-gray-400">/</span></li>
                            <?php
                        }
                        wp_reset_postdata();
                    }
                } elseif (is_singular('emulator')) {
                    $args = array(
                        'post_type' => 'page',
                        'posts_per_page' => 1,
                        'no_found_rows' => true,
                        'meta_key' => '_wp_page_template',
                        'meta_value' => 'page-emulators.php'
                    );
                    $query = new WP_Query($args);
                    if ($query->have_posts()) {
                        while ($query->have_posts()) {
                            $query->the_post();
                            ?>
                            <li class="flex items-center"><a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>" class="text-gray-500 hover:underline"><?php the_title(); ?></a><span class="mx-2 text-gray-400">/</span></li>
                            <?php
                        }
                        wp_reset_postdata();
                    }
                }

                // Get terms for custom taxonomy
                $taxonomy_terms = get_the_terms($post->ID, $custom_taxonomy);

                if ($taxonomy_terms && !is_wp_error($taxonomy_terms)) {
                    // Handle both array of terms and single term
                    $term = is_array($taxonomy_terms) ? $taxonomy_terms[0] : $taxonomy_terms;
                    
                    $cat_id = $term->term_id;
                    $cat_nicename = $term->slug;
                    $cat_link = get_term_link($term->term_id, $custom_taxonomy);
                    $cat_name = $term->name;
                }

            }

            // Check if the post is in a category
            if (!empty($last_category)) {
                echo $cat_display;
                ?>
                <li class="flex items-center"><span class="text-gray-500">
                    <?php
                    if ($title = get_field('_app_name'))
                        echo $title;
                    else
                        the_title();
                    ?>
                </span></li>
                <?php

                // Else if post is in a custom taxonomy
            } else if (!empty($cat_id)) {
                ?>
                <li class="flex items-center"><a href="<?php echo $cat_link; ?>" title="<?php echo $cat_name; ?>" class="text-gray-500 hover:underline"><?php echo $cat_name; ?></a><span class="mx-2 text-gray-400">/</span></li>
                <li class="flex items-center"><span class="text-gray-500"><?php echo get_the_title(); ?></span></li>
                <?php
            } else {
                ?>
                <li class="flex items-center"><span class="text-gray-500"><?php the_field('_app_name'); ?></span></li>
                <?php
            }
        } else if (!empty($wp_query->query_vars['download'])) {
            $post_id = get_queried_object_id();

            // Get post type and corresponding taxonomy
            $post = get_post($post_id);
            $post_type = $post ? $post->post_type : '';
            $taxonomy = get_custom_console_name($post_type);

            // Get taxonomy terms
            $taxonomy_terms = !empty($taxonomy) ? wp_get_post_terms($post_id, $taxonomy) : array();
            
            // Initialize variables
            $cat_id = '';
            $cat_link = '';
            $cat_name = '';
            
            if (!empty($taxonomy_terms) && !is_wp_error($taxonomy_terms)) {
                $term = is_array($taxonomy_terms) ? $taxonomy_terms[0] : $taxonomy_terms;
                $cat_id = $term->term_id;
                $cat_nicename = $term->slug;
                $cat_link = get_term_link($term->term_id, $taxonomy);
                $cat_name = $term->name;
                
                if ($taxonomy_terms[0]->taxonomy == 'console') {
                    $args = array(
                        'post_type' => 'page',
                        'posts_per_page' => 1,
                        'no_found_rows' => true,
                        'meta_key' => '_wp_page_template',
                        'meta_value' => 'page-roms.php'
                    );
                    $query = new WP_Query($args);
                    if ($query->have_posts()) {
                        while ($query->have_posts()) {
                            $query->the_post();
                            ?>
                            <li class="flex items-center"><a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>" class="text-gray-500 hover:underline"><?php the_title(); ?></a><span class="mx-2 text-gray-400">/</span></li>
                            <?php
                        }
                        wp_reset_postdata();
                    }
                } elseif ($taxonomy_terms[0]->taxonomy == 'emulator_console') {
                    $args = array(
                        'post_type' => 'page',
                        'posts_per_page' => 1,
                        'no_found_rows' => true,
                        'meta_key' => '_wp_page_template',
                        'meta_value' => 'page-emulators.php'
                    );
                    $query = new WP_Query($args);
                    if ($query->have_posts()) {
                        while ($query->have_posts()) {
                            $query->the_post();
                            ?>
                            <li class="flex items-center"><a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>" class="text-gray-500 hover:underline"><?php the_title(); ?></a><span class="mx-2 text-gray-400">/</span></li>
                            <?php
                        }
                        wp_reset_postdata();
                    }
                }

                if (!empty($cat_id)) {
                    ?>
                    <li class="flex items-center">
                        <a href="<?php echo $cat_link; ?>" title="<?php echo $cat_name; ?>" class="text-gray-500 hover:underline"><?php echo $cat_name; ?></a><span class="mx-2 text-gray-400">/</span>
                    </li>
                <?php }
            }
            ?>

            <li class="flex items-center">
                <a href="<?php echo get_the_permalink($post_id); ?>" class="text-gray-500 hover:underline"><?php echo get_the_title($post_id); ?></a><span class="mx-2 text-gray-400">/</span>
            </li>
            <li class="flex items-center"><span class="text-gray-500"><?php _e('Download', 'rom'); ?></span></li>
            <?php
        } else if (is_category()) {
            // Category page
            ?>
            <li class="flex items-center"><span class="text-gray-500"><?php echo single_cat_title('', false); ?></span></li>

            <?php
        } else if (is_page()) {

            // Standard page
            if ($post->post_parent) {

                // If child page, get parents 
                $anc = get_post_ancestors($post->ID);

                // Get parents in the right order
                $anc = array_reverse($anc);

                // Parent page loop
                foreach ($anc as $ancestor) {
                    ?>
                    <li class="flex items-center"><a href="<?php echo get_permalink($ancestor); ?>" title="<?php echo get_the_title($ancestor); ?>" class="text-gray-500 hover:underline"><?php echo get_the_title($ancestor); ?></a><span class="mx-2 text-gray-400">/</span></li>
                    <?php
                }

                // Display parent pages
                echo $parents;

                // Current page
                ?>
                <li class="flex items-center"><span class="text-gray-500"><?php echo get_the_title(); ?></span></li>
                <?php
            } else {

                // Just display current page if not parents
                ?>
                <li class="flex items-center"><span class="text-gray-500"><?php echo get_the_title(); ?></span></li>
                <?php
            }
        } else if (is_tag()) {

            // Tag page

            // Get tag information
            $term_id = get_query_var('tag_id');
            $taxonomy = 'post_tag';
            $args = 'include=' . $term_id;
            $terms = get_terms($taxonomy, $args);
            $get_term_id = $terms[0]->term_id;
            $get_term_slug = $terms[0]->slug;
            $get_term_name = $terms[0]->name;

            // Display the tag name
            ?>
            <li class="flex items-center"><span class="text-gray-500"><?php echo __('Tag', 'rom') . ': ' . $get_term_name; ?></span></li>
            <?php
        } elseif (is_day()) {

            // Day archive
            ?>
            <li class="flex items-center"><a href="<?php echo get_year_link(get_the_time('Y')); ?>" title="<?php echo get_the_time('Y'); ?>" class="text-gray-500 hover:underline"><?php echo get_the_time('Y'); ?></a><span class="mx-2 text-gray-400">/</span></li>
            <li class="flex items-center"><a href="<?php echo get_month_link(get_the_time('Y'), get_the_time('m')); ?>" title="<?php echo get_the_time('M'); ?>" class="text-gray-500 hover:underline"><?php echo get_the_time('M'); ?></a><span class="mx-2 text-gray-400">/</span></li>
            <li class="flex items-center"><span class="text-gray-500"><?php echo get_the_time('jS') . ' ' . get_the_time('M'); ?></span></li>
            <?php
        } else if (is_month()) {

            // Month Archive
            ?>
            <li class="flex items-center"><a href="<?php echo get_year_link(get_the_time('Y')); ?>" title="<?php echo get_the_time('Y'); ?>" class="text-gray-500 hover:underline"><?php echo get_the_time('Y'); ?></a><span class="mx-2 text-gray-400">/</span></li>
            <li class="flex items-center"><span class="text-gray-500"><?php echo get_the_time('M'); ?></span></li>
            <?php
        } else if (is_year()) {
            // Display year archive
            ?>
            <li class="flex items-center"><span class="text-gray-500"><?php echo get_the_time('Y'); ?></span></li>
            <?php
        } else if (is_author()) {

            // Auhor archive

            // Get the author information
            global $author;
            $userdata = get_userdata($author);

            // Display author name
            ?>
            <li class="flex items-center"><span class="text-gray-500"><?php echo __('Author', 'rom') . ': ' . $userdata->display_name; ?></span></li>
            <?php
        } else if (get_query_var('paged')) {

            // Paginated archives
            ?>
            <li class="flex items-center"><span class="text-gray-500"><?php echo __('Page', 'rom') . ' ' . get_query_var('paged'); ?></span></li>
            <?php
        } else if (is_search()) {

            // Search results page
            ?>
            <li class="flex items-center"><span class="text-gray-500"><?php echo __('Search results for', 'rom') . ': ' . get_search_query(); ?></span></li>
            <?php
        } elseif (is_404()) {

            // 404 page
            ?>
            <li class="flex items-center"><span class="text-gray-500"><?php _e('Error 404', 'rom'); ?></span></li>
            <?php
        }
        ?>
    </ul>
    <?php
}
