<?php
/**
 * Filter
 */
$is_tax = is_tax();
$post_ids = array();
if ($is_tax) :
    $current_term = get_queried_object();
    $filter_url = get_term_link($current_term, $current_term->taxonomy);
    $post_ids = get_transient('post_ids_of_' . $current_term->term_id);
    if ( $post_ids === false ) :

        $args = array(
            'post_type' 	 => 'rom',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'fields'   		 => 'ids',
            'tax_query' 	 => array(
                array(
                    'taxonomy' => $current_term->taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $current_term->term_id
                )
            )
        );
        
        $post_ids = get_posts($args);
        if (is_wp_error($post_ids)) {
            $post_ids = array();
        }

        set_transient('post_ids_of_' . $current_term->term_id, $post_ids, DAY_IN_SECONDS);
    endif;
else :
    $filter_url =  get_filter_base_url();
endif;
?>

<section class="hero-container relative mb-10">
	<div class="absolute top-0 w-full h-[20rem]"><img src="<?php echo get_stylesheet_directory_uri().'/assets/images/hero.png' ?>" class="h-full w-full"></div>
	<div class="relative w-full z-10 sm:pt-[18rem] pt-[10rem]">

		<div class="content-container px-4 sm:px-6 lg:px-8 w-full rounded-2xl">
			<div class="bg-white rounded-2xl shadow-md p-6 lg:p-8">

                <?php if (is_front_page() || is_home()) : ?>

                     <div class="text-center space-y-4 text-gray-900 mb-8">
                         <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-brand">
                             <span class="w-2 h-2 rounded-full bg-brand"></span>
                             <?php _e('Play Retro ROMs', 'romsfun') ?>
                         </p>
                         <h1 class="text-2xl sm:text-4xl font-bold text-gray-900">
                             <?php echo __('Play') . '<span class="text-romfun-pink">' . ' ' . __('Video Game Roms', 'romsfun') . '</span>' . ' ' . __('on your Computer or Phone', 'romsfun') ?>
                         </h1>
                         <p class="text-base text-muted max-w-2xl mx-auto">
                             <?php _e('Search, filter, and download your favorite retro games from 100,000+ ROMs curated for nostalgia lovers.', 'romsfun') ?>
                         </p>
                     </div>

				<?php
				else :
					if (is_search()) :
						$title = __('Search Results for: ', 'romsfun') . get_search_query();
					elseif(is_page()) :
						$title = get_the_title();
					elseif (is_tax() || is_category() || is_tag()) :
						$title = single_term_title('', false);
					elseif(is_archive()) :
						$title = post_type_archive_title('', false);
					endif;
					?>

					<h2 id="filter-title" class="text-2xl font-bold text-romfun-pink text-center"><?php echo $title; ?></h2>
				<?php endif; ?>
			
				<?php if (!is_front_page() && !is_home()) : ?>
					<div class="flex justify-center py-3">
						<?php k_breadcrumb(); ?>
					</div>
				<?php endif; ?>

				<?php
				// Get filter data from template args
				$filter_data = isset($args) ? $args : array();
				$current_search = isset($filter_data['search']) ? esc_attr($filter_data['search']) : '';
				$current_genres = isset($filter_data['genres']) ? (array)$filter_data['genres'] : array();
				$current_collections = isset($filter_data['collections']) ? (array)$filter_data['collections'] : array();
				$current_types = isset($filter_data['types']) ? (array)$filter_data['types'] : array();
				$current_consoles = isset($filter_data['consoles']) ? (array)$filter_data['consoles'] : array();
				$current_sort = isset($filter_data['sort']) ? esc_attr($filter_data['sort']) : '';
				
				?>
                <form action="<?php echo $filter_url ?>" method="get" style="position: relative;">
                    
                    <div id="magic-search-results"></div>
                    
                    <div class="flex flex-col lg:flex-row gap-3">
                        <label class="relative flex-1">
                            <input type="text" id="searchInput" placeholder="Search for ROMs, consoles, emulators…"
                                   name="q"
                                   value="<?php echo $current_search; ?>"
                                   class="w-full rounded-full border border-border px-4 sm:px-6 py-4 text-gray-900 outline-none focus:ring-2 focus:ring-brand/40 text-sm">
                            <span class="absolute inset-y-0 right-1 flex items-center pr-1">
                                <button id="searchButton" class="px-5 sm:px-6 py-3 rounded-full text-white font-semibold shadow-soft bg-romfun-pink hover:bg-romfun-pink-dark transition text-xs">
                                  <?php _e('Search', 'romsfun') ?>
                                </button>
                          </span>
                        </label>
                    </div>

                    <div class="mb-8 pt-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-[repeat(auto-fit,minmax(0,1fr))] gap-4">

                        <?php if (!is_tax('console')) : ?>
                            <div class="custom-select-wrapper" id="console-select-wrapper">
                                <label class="block text-sm font-semibold mb-2 text-gray-900"><?php _e('Console', 'romsfun'); ?></label>
                                <div class="custom-select-trigger rounded-full" id="console-select-trigger">
                                    <span class="selected-text" id="console-selected-text"><?php _e('Select Console', 'romsfun'); ?></span>
                                    <span class="arrow"></span>
                                </div>
                                <div class="custom-select-dropdown" id="console-dropdown">
                                    <div class="custom-select-search sticky top-0 bg-white z-10 px-3 py-2 border-b border-gray-100">
                                        <input type="text" class="custom-select-search-input w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-romfun-pink/40" placeholder="<?php esc_attr_e('Search consoles…', 'romsfun'); ?>" aria-label="<?php esc_attr_e('Search consoles', 'romsfun'); ?>">
                                    </div>
                                    <?php
                                    $terms = ($is_tax && isset($current_term)) ? get_cached_terms('console', $post_ids, $current_term->term_id) : get_cached_all_terms('console');
                                    if ( !is_wp_error($terms) && !empty($terms)) :
                                        foreach ( $terms as $term_id => $name ) :
                                            $is_checked = in_array($term_id, $current_consoles);
                                        ?>
                                            <div class="custom-select-option">
                                                <input type="checkbox" id="console-<?php echo esc_attr($term_id); ?>"
                                                       name="consoles[]" value="<?php echo esc_attr($term_id); ?>"
                                                       data-taxonomy="console" data-term-id="<?php echo esc_attr($term_id); ?>"
                                                       <?php checked($is_checked, true); ?>>
                                                <label for="console-<?php echo esc_attr($term_id); ?>"><?php echo esc_html($name); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <div class="custom-select-option">
                                            <span class="text-gray-500"><?php _e('No consoles available', 'romsfun'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="custom-select-clear-wrapper sticky bottom-0 bg-white z-10 px-3 py-2 border-t border-gray-100">
                                        <button type="button" class="custom-select-clear-all w-full px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition"><?php esc_html_e('Clear all', 'romsfun'); ?></button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>


                        <div class="custom-select-wrapper" id="genre-select-wrapper">
                            <label class="block text-sm font-semibold mb-2 text-gray-900"><?php _e('Genre', 'romsfun'); ?></label>
                            <div class="custom-select-trigger rounded-full" id="genre-select-trigger">
                                <span class="selected-text" id="genre-selected-text"><?php _e('Select Genre', 'romsfun'); ?></span>
                                <span class="arrow"></span>
                            </div>
                            <div class="custom-select-dropdown" id="genre-dropdown">
                                <div class="custom-select-search sticky top-0 bg-white z-10 px-3 py-2 border-b border-gray-100">
                                    <input type="text" class="custom-select-search-input w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-romfun-pink/40" placeholder="<?php esc_attr_e('Search genres…', 'romsfun'); ?>" aria-label="<?php esc_attr_e('Search genres', 'romsfun'); ?>">
                                </div>
                                <?php
                                $terms = ($is_tax && isset($current_term)) ? get_cached_terms('genre', $post_ids, $current_term->term_id) : get_cached_all_terms('genre');
                                if ( !is_wp_error($terms) && !empty($terms)) :
                                    foreach ($terms as $term_id => $name) :
                                        $is_checked = in_array($term_id, $current_genres);
                                    ?>
                                        <div class="custom-select-option">
                                            <input type="checkbox" id="genre-<?php echo esc_attr($term_id); ?>"
                                                   name="genres[]" value="<?php echo esc_attr($term_id); ?>"
                                                   data-taxonomy="genre" data-term-id="<?php echo esc_attr($term_id); ?>"
                                                   <?php checked($is_checked, true); ?>>
                                            <label for="genre-<?php echo esc_attr($term_id); ?>"><?php echo esc_html($name); ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <div class="custom-select-option">
                                        <span class="text-gray-500"><?php _e('No genres available', 'romsfun'); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="custom-select-clear-wrapper sticky bottom-0 bg-white z-10 px-3 py-2 border-t border-gray-100">
                                    <button type="button" class="custom-select-clear-all w-full px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition"><?php esc_html_e('Clear all', 'romsfun'); ?></button>
                                </div>
                            </div>
                        </div>

	                        <?php if (!is_tax('collection')) : ?>
                            <div class="custom-select-wrapper" id="collection-select-wrapper">
                                <label class="block text-sm font-semibold mb-2 text-gray-900"><?php _e('Collection', 'romsfun'); ?></label>
                                <div class="custom-select-trigger rounded-full" id="collection-select-trigger">
                                    <span class="selected-text" id="collection-selected-text"><?php _e('Select Collection', 'romsfun'); ?></span>
                                    <span class="arrow"></span>
                                </div>
                                <div class="custom-select-dropdown" id="collection-dropdown">
                                    <div class="custom-select-search sticky top-0 bg-white z-10 px-3 py-2 border-b border-gray-100">
                                        <input type="text" class="custom-select-search-input w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-romfun-pink/40" placeholder="<?php esc_attr_e('Search collections…', 'romsfun'); ?>" aria-label="<?php esc_attr_e('Search collections', 'romsfun'); ?>">
                                    </div>
                                    <?php
                                    $terms = ($is_tax && isset($current_term)) ? get_cached_terms('collection', $post_ids, $current_term->term_id) : get_cached_all_terms('collection');
                                    if ( !is_wp_error($terms) && !empty($terms)) :
                                        foreach ($terms as $term_id => $name ) :
                                            $is_checked = in_array($term_id, $current_collections);
                                        ?>
                                            <div class="custom-select-option">
                                                <input type="checkbox" id="collection-<?php echo esc_attr($term_id); ?>"
                                                       name="collections[]" value="<?php echo esc_attr($term_id); ?>"
                                                       data-taxonomy="collection" data-term-id="<?php echo esc_attr($term_id); ?>"
                                                       <?php checked($is_checked, true); ?>>
                                                <label for="collection-<?php echo esc_attr($term_id); ?>"><?php echo esc_html($name); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <div class="custom-select-option">
                                            <span class="text-gray-500"><?php _e('No collections available', 'romsfun'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="custom-select-clear-wrapper sticky bottom-0 bg-white z-10 px-3 py-2 border-t border-gray-100">
                                        <button type="button" class="custom-select-clear-all w-full px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition"><?php esc_html_e('Clear all', 'romsfun'); ?></button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!is_tax('type')) : ?>
                            <div class="custom-select-wrapper" id="type-select-wrapper">
                                <label class="block text-sm font-semibold mb-2 text-gray-900"><?php _e('Type', 'romsfun'); ?></label>
                                <div class="custom-select-trigger rounded-full" id="type-select-trigger">
                                    <span class="selected-text" id="type-selected-text"><?php _e('Select Type', 'romsfun'); ?></span>
                                    <span class="arrow"></span>
                                </div>
                                <div class="custom-select-dropdown" id="type-dropdown">
                                    <div class="custom-select-search sticky top-0 bg-white z-10 px-3 py-2 border-b border-gray-100">
                                        <input type="text" class="custom-select-search-input w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-romfun-pink/40" placeholder="<?php esc_attr_e('Search types…', 'romsfun'); ?>" aria-label="<?php esc_attr_e('Search types', 'romsfun'); ?>">
                                    </div>
                                    <?php
                                    $terms = ($is_tax && isset($current_term)) ? get_cached_terms('type', $post_ids, $current_term->term_id) : get_cached_all_terms('type');
                                    if ( !is_wp_error($terms) && !empty($terms)) :
                                        foreach ($terms as $term_id => $name) :
                                            $is_checked = in_array($term_id, $current_types);
                                        ?>
                                            <div class="custom-select-option">
                                                <input type="checkbox" id="type-<?php echo esc_attr($term_id); ?>"
                                                       name="types[]" value="<?php echo esc_attr($term_id); ?>"
                                                       data-taxonomy="type" data-term-id="<?php echo esc_attr($term_id); ?>"
                                                       <?php checked($is_checked, true); ?>>
                                                <label for="type-<?php echo esc_attr($term_id); ?>"><?php echo esc_html($name); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <div class="custom-select-option">
                                            <span class="text-gray-500"><?php _e('No types available', 'romsfun'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="custom-select-clear-wrapper sticky bottom-0 bg-white z-10 px-3 py-2 border-t border-gray-100">
                                        <button type="button" class="custom-select-clear-all w-full px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition"><?php esc_html_e('Clear all', 'romsfun'); ?></button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="custom-select-wrapper" id="sort-select-wrapper">
                                <label class="block text-sm font-semibold mb-2 text-gray-900"><?php _e('Sort By', 'romsfun'); ?></label>
                                <div class="custom-select-trigger rounded-full" id="sort-select-trigger">
                                    <span class="selected-text" id="sort-selected-text"><?php _e('Select Sort', 'romsfun'); ?></span>
                                    <span class="arrow"></span>
                                </div>
                                <div class="custom-select-dropdown" id="sort-dropdown">
                                    <div class="custom-select-option">
                                        <input type="radio" id="sort-popular" name="sort" value="popular" <?php checked($current_sort, 'popular', true); ?>>
                                        <label for="sort-popular"><?php _e('Most Popular', 'romsfun'); ?></label>
                                    </div>
                                    <div class="custom-select-option">
                                        <input type="radio" id="sort-downloads" name="sort" value="downloads" <?php checked($current_sort, 'downloads'); ?>>
                                        <label for="sort-downloads"><?php _e('Most Downloads', 'romsfun'); ?></label>
                                    </div>
                                    <div class="custom-select-option">
                                        <input type="radio" id="sort-latest" name="sort" value="latest" <?php checked($current_sort, 'latest'); ?>>
                                        <label for="sort-latest"><?php _e('Latest Update', 'romsfun'); ?></label>
                                    </div>
                                    <div class="custom-select-option">
                                        <input type="radio" id="sort-newest" name="sort" value="newest" <?php checked($current_sort, 'newest'); ?>>
                                        <label for="sort-newest"><?php _e('Newest', 'romsfun'); ?></label>
                                    </div>
                                    <div class="custom-select-option">
                                        <input type="radio" id="sort-oldest" name="sort" value="oldest" <?php checked($current_sort, 'oldest'); ?>>
                                        <label for="sort-oldest"><?php _e('Oldest', 'romsfun'); ?></label>
                                    </div>
                                    <div class="custom-select-option">
                                        <input type="radio" id="sort-alphabetical" name="sort" value="alphabetical" <?php checked($current_sort, 'alphabetical'); ?>>
                                        <label for="sort-alphabetical"><?php _e('Alphabetical', 'romsfun'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="selected-filters-container" class="border-t border-gray-200 py-6 hidden">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="text-sm font-semibold text-gray-900"> <?php _e('Selected Filters', 'romsfun') ?>:</span>
                        </div>
                        <div id="selected-filters" class="flex flex-wrap gap-2"></div>
                    </div>
                </form>
				<div>
					<button id="clearFilters"
						class="inline-flex justify-center px-4 py-2 text-xs bg-gray-200 text-gray-800 rounded-full hover:bg-gray-300 transition font-medium">
						<?php echo get_svg_icon('reload').' '.__('Clear Filters', 'romsfun') ?>
					</button>
				</div>
			</div>

			<?php 
			if (!is_tax('type')) :
				// Ensure console is included in filter_data when on taxonomy console page
				$suggested_filter_data = $filter_data;
				if ($is_tax && isset($current_term) && $current_term->taxonomy == 'console' ) {
					$suggested_filter_data['consoles'] = array($current_term->term_id);
				} elseif($is_tax && isset($current_term) && $current_term->taxonomy == 'collection' ) {
                    $suggested_filter_data['collections'] = array($current_term->term_id);
                } elseif($is_tax && isset($current_term) && $current_term->taxonomy == 'type' ) {
                    $suggested_filter_data['types'] = array($current_term->term_id);
                }
                // suggested filters for type taxonomy
				$suggested_types = get_filtered_counts( 'type', $suggested_filter_data);
				if (!empty($suggested_types)) : ?>
				<div class="suggest-filters pt-6">
					<h3 class="text-base font-semibold text-gray-900 mb-4"><?php _e('Suggested Filters', 'romsfun'); ?></h3>
					<div class="suggest-filters-list flex flex-wrap gap-2"> 
						<?php foreach ($suggested_types as $term) :
							$is_selected = in_array($term->term_id, $current_types);
						?>
							<button 
								type="button"
								class="suggested-filter-badge inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-colors <?php echo $is_selected ? 'bg-romfun-pink text-white' : 'bg-gray-200 text-gray-800 hover:bg-romfun-pink hover:text-white'; ?>"
								data-term-id="<?php echo esc_attr($term->term_id); ?>"
								data-taxonomy="type"
								data-selected="<?php echo $is_selected ? 'true' : 'false'; ?>">
								<span><?php echo esc_html($term->name); ?></span>
								<span class="text-xs opacity-75">(<?php echo number_format($term->count); ?>)</span>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif;
			endif;
			?>
		</div>
	</div>
</section>