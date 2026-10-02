<?php
/**
 * Taxonomy console
 *
 * @package romsvault
 */
global $post;
$console = get_queried_object();
// Validate console object or redirect to 404
if (!is_tax('console') || !isset($console->term_id)) :
    rf_force_404();
endif;

get_header();

$search_term = isset($_GET['q']) ? trim(sanitize_text_field($_GET['q'])) : '';

$selected_genres = isset($_GET['genres']) ? $_GET['genres'] : array();
if (!empty($selected_genres) && !is_array($selected_genres)) {
	$selected_genres = explode(',', $selected_genres);
}
$selected_genres = array_map('intval', (array)$selected_genres);
$selected_genres = array_filter($selected_genres); // Remove empty values

$selected_collections = isset($_GET['collections']) ? $_GET['collections'] : array();
if (!empty($selected_collections) && !is_array($selected_collections)) {
	$selected_collections = explode(',', $selected_collections);
}
$selected_collections = array_map('intval', (array)$selected_collections);
$selected_collections = array_filter($selected_collections); // Remove empty values

$selected_types = isset($_GET['types']) ? $_GET['types'] : array();
if (!empty($selected_types) && !is_array($selected_types)) {
	$selected_types = explode(',', $selected_types);
}
$selected_types = array_map('intval', (array)$selected_types);
$selected_types = array_filter($selected_types); // Remove empty values

$allowed_sorts = get_sort_list();
$sort_by = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : '';
if (!empty($sort_by) && !in_array($sort_by, $allowed_sorts)) {
	$sort_by = '';
}

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

$filter_data = array(
	'search' => $search_term,
	'genres' => $selected_genres,
	'collections' => $selected_collections,
	'types' => $selected_types,
    'consoles' => array($console->term_id),
	'sort' => $sort_by,
);
?>

    <?php get_template_part( 'template-parts/rom-filter', null, $filter_data ); ?>

    <section class="py-6 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">

            <?php
            // Display carousel with latest 10 ROMs if no filter selected && first page
            $has_filters = !empty($search_term) || !empty($selected_genres) || !empty($selected_collections) || !empty($selected_types) || !empty($sort_by);
            $is_first_page = $paged == 1;

            if (!$has_filters && $is_first_page) :
                $latest_query = new WP_Query(array(
                    'post_type' => 'rom',
                    'posts_per_page' => 10,
                    'post_status' => 'publish',
                    'no_found_rows' => true,
                    'orderby' => 'modified',
                    'order' => 'DESC',
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'console',
                            'field' => 'term_id',
                            'terms' => $console->term_id,
                        ),
                    ),
                    'update_post_meta_cache' => true,
                    'update_post_term_cache' => true,
                    'cache_results' => true,
                ));

                if ($latest_query->have_posts()) : ?>
                    <section class="mb-8">
                        <h2 class="text-xl font-bold flex items-center mb-4">
                            <?php echo esc_html($console->name . ' ' . __('Latest ROMs', 'romsfun')); ?>
                        </h2>

                        <div class="horizontal-scroll-container">
                            <div class="horizontal-scroll-wrapper">
                                <?php
                                while ($latest_query->have_posts()) : $latest_query->the_post();
                                    get_template_part('template-parts/rom-item-grid');
                                endwhile;
                                wp_reset_postdata();
                                unset($latest_query)
                                ?>
                            </div>
                        </div>
                    </section>
                <?php endif;
            endif;
            ?>

            <?php
            $filters = array(
	            'genres' => $selected_genres,
	            'collections' => $selected_collections,
	            'types' => $selected_types,
	            'search' => $search_term,
            );

            $query_args = array(
	            'console_id' => $console->term_id,
	            'post_status'    => 'publish',
	            'filters' => $filters,
	            'sort' => $sort_by,
	            'paged' => $paged,
            );

            $query = k_build_roms_query($query_args, 'console');
            ?>

                <div class="flex items-center justify-between mb-8">
                    <h2 class="text-xl font-bold flex items-center">
						<?php
                        $title = $console->name;
                        if (!empty($search_term) || !empty($selected_genres) || !empty($selected_collections) || !empty($selected_types)) {
                            $title .= ' ' . __('Filtered ROMs', 'romsfun');
                        } else {
                            $title .= ' ' . __('Popular ROMs', 'romsfun');
                        }
                        echo $title . ' (' .$query->found_posts.')';
                        ?>
                    </h2>
                </div>

                <div class="space-y-4">
					<?php
                    if ($query->have_posts()) :
                        while ($query->have_posts()) : $query->the_post();
                            get_template_part( 'template-parts/rom-item-list' );
                        endwhile;
                    else : ?>
                        <div class="bg-white rounded-lg shadow-md p-8 text-center">
                            <p class="text-gray-600 text-lg"><?php _e('No ROMs found matching your filters.', 'romsfun'); ?></p>
                            <a href="<?php echo get_term_link($console->term_id) ?>" class="inline-block mt-4 px-6 py-2 bg-romfun-pink text-white rounded-full hover:bg-romfun-pink-dark transition">
                                <?php _e('Clear Filters', 'romsfun'); ?>
                            </a>
                        </div>
                    <?php endif;
                    wp_reset_postdata(); ?>

                </div>

        </div>

        <?php if ($query->max_num_pages > 1) : ?>
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
            <?php k_pagination($query->max_num_pages); ?>
        </div>
        <?php endif; ?>

	    <?php if ( $paged == 1 && !$has_filters && $desc = $console->description ) : ?>
            <section class="py-6 text-gray-900">
                <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
                    <div class="px-6 py-4 mt-12 rounded-xl bg-white">
                        <h2 class="text-xl mb-4 font-semibold text-gray-800"><?php _e('Information', 'romsfun') ?></h2>
                        <?php echo $desc; ?>
                    </div>
                </div>
            </section>
	    <?php endif; ?>

    </section>

<?php
get_footer();