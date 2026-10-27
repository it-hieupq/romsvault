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
// Windows games are PC games, not ROMs: section headings say "Games" there.
$is_pc = 'windows' === $console->slug;

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
        <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 w-full">

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
                        <div class="mb-4">
                            <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php esc_html_e( 'Recently updated', 'rom' ); ?></span>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5">
                                <?php
                                /* translators: %s: console name */
                                echo esc_html( sprintf( $is_pc ? __( 'Latest %s PC Games', 'rom' ) : __( 'Latest %s ROMs', 'rom' ), $console->name ) );
                                ?>
                            </h2>
                        </div>

                        <div class="horizontal-scroll-container">
                            <div class="horizontal-scroll-wrapper">
                                <?php
                                while ($latest_query->have_posts()) : $latest_query->the_post();
                                    ?>
                                    <div class="flex-shrink-0 w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 min-w-0 px-1.5 md:px-2 box-border grid">
                                        <?php get_template_part('template-parts/rom-card'); ?>
                                    </div>
                                    <?php
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

            $query = build_roms_query($query_args, 'console');
            ?>

                <?php
                $is_filtered = ! empty( $search_term ) || ! empty( $selected_genres ) || ! empty( $selected_collections ) || ! empty( $selected_types );
                // No sort: build_roms_query() orders by the downloads meta, shown as plain "Popular".
                $sort_label = rom_sort_label( $sort_by, __( 'Popular', 'rom' ) );
                if ( $is_pc ) {
                    /* translators: %s: console name */
                    $grid_title = sprintf( $is_filtered ? __( 'Filtered %s PC Games', 'rom' ) : __( 'All %s PC Games', 'rom' ), $console->name );
                    $count_text = sprintf( _n( '%s game', '%s games', $query->found_posts, 'rom' ), number_format_i18n( $query->found_posts ) );
                } else {
                    /* translators: %s: console name */
                    $grid_title = sprintf( $is_filtered ? __( 'Filtered %s ROMs', 'rom' ) : __( 'All %s ROMs', 'rom' ), $console->name );
                    $count_text = sprintf( _n( '%s ROM', '%s ROMs', $query->found_posts, 'rom' ), number_format_i18n( $query->found_posts ) );
                }
                ?>
                <div class="flex items-end justify-between gap-3 mb-6">
                    <div class="min-w-0">
                        <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php echo esc_html( $sort_label ); ?></span>
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
                            <a href="<?php echo get_term_link($console->term_id) ?>" class="inline-block mt-4 px-6 py-2 bg-rom-pink text-white rounded-full hover:bg-rom-pink-dark transition">
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

	    <?php if ( $paged == 1 && !$has_filters && $desc = $console->description ) : ?>
            <section class="py-6 text-gray-900">
                <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8 w-full">
                    <div class="mt-12 bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-7 shadow-card space-y-4">
                        <div class="border-b border-slate-100 pb-4">
                            <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php esc_html_e( 'About', 'rom' ); ?></span>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5">
                                <?php
                                /* translators: %s: console name */
                                echo esc_html( sprintf( $is_pc ? __( 'About %s PC Games', 'rom' ) : __( 'About %s ROMs', 'rom' ), $console->name ) );
                                ?>
                            </h2>
                        </div>
                        <?php // Term descriptions are saved as plain text with blank lines between paragraphs. ?>
                        <div class="rich-text"><?php echo wpautop( $desc ); ?></div>
                    </div>
                </div>
            </section>
	    <?php endif; ?>

    </section>

<?php
get_footer();