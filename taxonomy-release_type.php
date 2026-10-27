<?php
/**
 * Release type archive
 *
 * @package romsvault
 */
global $post;
$type = get_queried_object();
// Validate type object
if (!is_tax('release_type') || !isset($type->term_id)) {
    wp_die(__('Invalid type taxonomy.', 'rom'));
}
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

$selected_consoles = isset($_GET['consoles']) ? $_GET['consoles'] : array();
if (!empty($selected_consoles) && !is_array($selected_consoles)) {
	$selected_consoles = explode(',', $selected_consoles);
}
$selected_consoles = array_map('intval', (array)$selected_consoles);
$selected_consoles = array_filter($selected_consoles); // Remove empty values

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
	'types' => array($type->term_id),
	'consoles' => $selected_consoles,
	'sort' => $sort_by,
);
?>

    <?php get_template_part( 'template-parts/rom-filter', null, $filter_data ); ?>

    <section class="py-6 text-gray-900">
        <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 w-full">

            <?php
            // Display carousel with latest 10 ROMs if no filter selected && first page
            $has_filters = !empty($search_term) || !empty($selected_genres) || !empty($selected_collections) || !empty($selected_consoles) || !empty($sort_by);
            $is_first_page = $paged == 1;
            
            if (!$has_filters && $is_first_page) :
                $latest_roms_query = new WP_Query(array(
                    'post_type' => 'rom',
                    'posts_per_page' => 10,
                    'post_status' => 'publish',
                    'no_found_rows' => true,
                    'orderby' => 'modified',
                    'order' => 'DESC',
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'release_type',
                            'field' => 'term_id',
                            'terms' => $type->term_id,
                        ),
                    ),
                ));
                
                if ($latest_roms_query->have_posts()) : ?>
                    <section class="mb-8">
                        <div class="mb-4">
                            <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php esc_html_e( 'Recently updated', 'rom' ); ?></span>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5"><?php /* translators: %s: term name */ echo esc_html( sprintf( __( 'Latest %s ROMs', 'rom' ), $type->name ) ); ?></h2>
                        </div>

                        <div class="carousel-container relative mx-auto" data-carousel-id="consoleLatestROMs">
                            <div class="carousel-wrapper">
                                <?php
                                while ($latest_roms_query->have_posts()) : $latest_roms_query->the_post();
                                    ?>
                                    <div class="flex-shrink-0 w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 min-w-0 px-1.5 md:px-2 box-border grid">
                                        <?php get_template_part('template-parts/rom-card'); ?>
                                    </div>
                                    <?php
                                endwhile;
                                wp_reset_postdata();
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
	            'types' => array($type->term_id),
	            'consoles' => $selected_consoles,
	            'search' => $search_term,
            );

            $query_args = array(
	            'type_id' => $type->term_id,
	            'post_status'    => 'publish',
	            'filters' => $filters,
	            'sort' => $sort_by,
	            'paged' => $paged,
            );

            $query = build_roms_query($query_args, 'type');
            ?>

                <?php
                $is_filtered = ! empty( $search_term ) || ! empty( $selected_genres ) || ! empty( $selected_collections ) || ! empty( $selected_consoles );
                $grid_title  = sprintf( $is_filtered ? __( 'Filtered %s ROMs', 'rom' ) : __( 'All %s ROMs', 'rom' ), $type->name );
                $count_text  = sprintf( _n( '%s ROM', '%s ROMs', $query->found_posts, 'rom' ), number_format_i18n( $query->found_posts ) );
                ?>
                <div class="flex items-end justify-between gap-3 mb-6">
                    <div class="min-w-0">
                        <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php echo esc_html( rom_sort_label( $sort_by, __( 'Popular', 'rom' ) ) ); ?></span>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5"><?php echo esc_html( $grid_title ); ?></h2>
                    </div>
                    <span class="flex-shrink-0 px-2.5 py-1 rounded-lg bg-white border border-slate-200/80 text-caption font-bold text-slate-600"><?php echo esc_html( $count_text ); ?></span>
                </div>

                <div class="<?php echo $query->have_posts() ? 'grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-4' : ''; ?>">
					<?php
                    if ($query->have_posts()) :
                        while ($query->have_posts()) : $query->the_post();
                            get_template_part( 'template-parts/rom-card' );
                        endwhile;
                    else : ?>
                        <div class="bg-white rounded-lg shadow-md p-8 text-center">
                            <p class="text-gray-600 text-lg"><?php _e('No ROMs found matching your filters.', 'rom'); ?></p>
                            <a href="<?php echo get_term_link($type->term_id, 'release_type'); ?>" class="inline-block mt-4 px-6 py-2 bg-rom-pink text-white rounded-full hover:bg-rom-pink-dark transition">
                                <?php _e('Clear Filters', 'rom'); ?>
                            </a>
                        </div>
                    <?php endif;
                    wp_reset_postdata(); ?>

                </div>

        </div>

        <?php if ($query->max_num_pages > 1) : ?>
            <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 w-full">
                <?php rom_pagination($query->max_num_pages); ?>
            </div>
        <?php endif; ?>
    </section>

<?php
get_footer();