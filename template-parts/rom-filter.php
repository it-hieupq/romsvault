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

        // An empty term is not cached, so its first rom shows up in the
        // options on the next render rather than a day later.
        if ($post_ids) {
            set_transient('post_ids_of_' . $current_term->term_id, $post_ids, DAY_IN_SECONDS);
        }
    endif;
else :
    $filter_url =  get_filter_base_url();
endif;
?>

<section class="hero-container relative mb-10">
	<?php // Hero art matches template-parts/hero.php: 500px tall, bottom 60% fading into the page. ?>
	<div class="absolute top-0 w-full h-[350px] -z-10">
		<img src="<?php echo get_stylesheet_directory_uri().'/assets/images/hero.png' ?>" class="h-full w-full object-cover object-bottom" alt="">
		<div class="absolute inset-x-0 bottom-0 h-[60%] bg-gradient-to-t from-slate-50 to-transparent pointer-events-none"></div>
	</div>
	<div class="relative w-full z-10 sm:pt-[10rem] pt-[6rem]">

		<div class="content-container px-4 sm:px-6 lg:px-8 w-full rounded-2xl">
			<div class="bg-white rounded-2xl shadow-md p-5 lg:p-6">

                <?php if (is_front_page() || is_home()) : ?>

                     <div class="text-center space-y-2 text-gray-900 mb-5">
                         <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-brand">
                             <span class="w-2 h-2 rounded-full bg-brand"></span>
                             <?php esc_html_e( 'Free ROMs & Emulators', 'rom' ); ?>
                         </p>
                         <h1 class="text-xl sm:text-3xl font-bold text-gray-900">
                             <?php
                             /* translators: %1$s and %2$s wrap the highlighted words in a span */
                             printf( esc_html__( 'Download %1$sFree ROMs%2$s & ISOs for Retro Consoles', 'rom' ), '<span class="text-rom-pink">', '</span>' );
                             ?>
                         </h1>
                         <p class="text-sm text-muted max-w-2xl mx-auto">
                             <?php esc_html_e( 'Play classic Nintendo, PlayStation, Sega and Game Boy ROMs on PC, Android or iOS. Every game comes with the emulator it needs.', 'rom' ); ?>
                         </p>
                     </div>

				<?php
				else :
					if (is_search()) :
						$title = __('Search Results for: ', 'rom') . get_search_query();
					elseif(is_page()) :
						$title = get_the_title();
					elseif (is_tax() || is_category() || is_tag()) :
						$title = single_term_title('', false);
						// "Nintendo DS ROMs" is what people search for; skip the suffix if the name already says it.
						// Windows games are PC games, not ROMs.
						if ( is_tax( 'console', 'windows' ) ) :
							/* translators: %s: console name (Windows) */
							$title = sprintf( __( '%s PC Games', 'rom' ), $title );
						elseif ( false === stripos( $title, 'rom' ) ) :
							/* translators: %s: console, collection or type name */
							$title = sprintf( __( '%s ROMs', 'rom' ), $title );
						endif;
					elseif(is_archive()) :
						$title = post_type_archive_title('', false);
					endif;
					?>

					<h1 id="filter-title" class="text-2xl font-bold text-rom-pink text-center"><?php echo esc_html( $title ); ?></h1>
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
                    
                    
                    <div class="relative flex flex-col lg:flex-row gap-3">
                        <label class="relative flex-1">
                            <input type="text" id="searchInput" placeholder="<?php esc_attr_e( 'Search games, ROMs or ISOs…', 'rom' ); ?>"
                                   name="q"
                                   value="<?php echo $current_search; ?>"
                                   class="w-full rounded-full border border-border px-4 sm:px-6 py-3 text-gray-900 outline-none focus:ring-2 focus:ring-brand/40 text-sm">
                            <span class="absolute inset-y-0 right-1 flex items-center pr-1">
                                <button id="searchButton" class="px-5 sm:px-6 py-2.5 rounded-full text-white font-semibold shadow-soft bg-rom-pink hover:bg-rom-pink-dark transition text-xs">
                                  <?php _e('Search', 'rom') ?>
                                </button>
                          </span>
                        </label>
                        <?php if ( rom_search_location_enabled( 'filter' ) ) : ?>
                            <div id="filter-search-results" class="search-results" role="listbox" aria-label="<?php esc_attr_e( 'Search suggestions', 'rom' ); ?>" hidden></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4 pt-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[repeat(auto-fit,minmax(0,1fr))] gap-3">

                        <?php if (!is_tax('console')) : ?>
                            <div class="custom-select-wrapper" id="console-select-wrapper">
                                <label class="block text-sm font-semibold mb-2 text-gray-900"><?php _e('Console', 'rom'); ?></label>
                                <div class="custom-select-trigger rounded-full" id="console-select-trigger">
                                    <span class="selected-text" id="console-selected-text"><?php _e('Select Console', 'rom'); ?></span>
                                    <span class="arrow"></span>
                                </div>
                                <div class="custom-select-dropdown" id="console-dropdown">
                                    <div class="custom-select-search sticky top-0 bg-white z-10 px-3 py-2 border-b border-gray-100">
                                        <input type="text" class="custom-select-search-input w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-rom-pink/40" placeholder="<?php esc_attr_e('Search consoles…', 'rom'); ?>" aria-label="<?php esc_attr_e('Search consoles', 'rom'); ?>">
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
                                            <span class="text-gray-500"><?php _e('No consoles available', 'rom'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="custom-select-clear-wrapper sticky bottom-0 bg-white z-10 px-3 py-2 border-t border-gray-100">
                                        <button type="button" class="custom-select-clear-all w-full px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition"><?php esc_html_e('Clear all', 'rom'); ?></button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>


                        <div class="custom-select-wrapper" id="genre-select-wrapper">
                            <label class="block text-sm font-semibold mb-2 text-gray-900"><?php _e('Genre', 'rom'); ?></label>
                            <div class="custom-select-trigger rounded-full" id="genre-select-trigger">
                                <span class="selected-text" id="genre-selected-text"><?php _e('Select Genre', 'rom'); ?></span>
                                <span class="arrow"></span>
                            </div>
                            <div class="custom-select-dropdown" id="genre-dropdown">
                                <div class="custom-select-search sticky top-0 bg-white z-10 px-3 py-2 border-b border-gray-100">
                                    <input type="text" class="custom-select-search-input w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-rom-pink/40" placeholder="<?php esc_attr_e('Search genres…', 'rom'); ?>" aria-label="<?php esc_attr_e('Search genres', 'rom'); ?>">
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
                                        <span class="text-gray-500"><?php _e('No genres available', 'rom'); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="custom-select-clear-wrapper sticky bottom-0 bg-white z-10 px-3 py-2 border-t border-gray-100">
                                    <button type="button" class="custom-select-clear-all w-full px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition"><?php esc_html_e('Clear all', 'rom'); ?></button>
                                </div>
                            </div>
                        </div>

	                        <?php if (!is_tax('collection')) : ?>
                            <div class="custom-select-wrapper" id="collection-select-wrapper">
                                <label class="block text-sm font-semibold mb-2 text-gray-900"><?php _e('Collection', 'rom'); ?></label>
                                <div class="custom-select-trigger rounded-full" id="collection-select-trigger">
                                    <span class="selected-text" id="collection-selected-text"><?php _e('Select Collection', 'rom'); ?></span>
                                    <span class="arrow"></span>
                                </div>
                                <div class="custom-select-dropdown" id="collection-dropdown">
                                    <div class="custom-select-search sticky top-0 bg-white z-10 px-3 py-2 border-b border-gray-100">
                                        <input type="text" class="custom-select-search-input w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-rom-pink/40" placeholder="<?php esc_attr_e('Search collections…', 'rom'); ?>" aria-label="<?php esc_attr_e('Search collections', 'rom'); ?>">
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
                                            <span class="text-gray-500"><?php _e('No collections available', 'rom'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="custom-select-clear-wrapper sticky bottom-0 bg-white z-10 px-3 py-2 border-t border-gray-100">
                                        <button type="button" class="custom-select-clear-all w-full px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition"><?php esc_html_e('Clear all', 'rom'); ?></button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!is_tax('type')) : ?>
                            <div class="custom-select-wrapper" id="type-select-wrapper">
                                <label class="block text-sm font-semibold mb-2 text-gray-900"><?php _e('Type', 'rom'); ?></label>
                                <div class="custom-select-trigger rounded-full" id="type-select-trigger">
                                    <span class="selected-text" id="type-selected-text"><?php _e('Select Type', 'rom'); ?></span>
                                    <span class="arrow"></span>
                                </div>
                                <div class="custom-select-dropdown" id="type-dropdown">
                                    <div class="custom-select-search sticky top-0 bg-white z-10 px-3 py-2 border-b border-gray-100">
                                        <input type="text" class="custom-select-search-input w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-rom-pink/40" placeholder="<?php esc_attr_e('Search types…', 'rom'); ?>" aria-label="<?php esc_attr_e('Search types', 'rom'); ?>">
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
                                            <span class="text-gray-500"><?php _e('No types available', 'rom'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="custom-select-clear-wrapper sticky bottom-0 bg-white z-10 px-3 py-2 border-t border-gray-100">
                                        <button type="button" class="custom-select-clear-all w-full px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition"><?php esc_html_e('Clear all', 'rom'); ?></button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="custom-select-wrapper" id="sort-select-wrapper">
                                <label class="block text-sm font-semibold mb-2 text-gray-900"><?php _e('Sort By', 'rom'); ?></label>
                                <div class="custom-select-trigger rounded-full" id="sort-select-trigger">
                                    <span class="selected-text" id="sort-selected-text"><?php _e('Select Sort', 'rom'); ?></span>
                                    <span class="arrow"></span>
                                </div>
                                <div class="custom-select-dropdown" id="sort-dropdown">
                                    <div class="custom-select-option">
                                        <input type="radio" id="sort-popular" name="sort" value="popular" <?php checked($current_sort, 'popular', true); ?>>
                                        <label for="sort-popular"><?php _e('Most Popular', 'rom'); ?></label>
                                    </div>
                                    <div class="custom-select-option">
                                        <input type="radio" id="sort-downloads" name="sort" value="downloads" <?php checked($current_sort, 'downloads'); ?>>
                                        <label for="sort-downloads"><?php _e('Most Downloads', 'rom'); ?></label>
                                    </div>
                                    <div class="custom-select-option">
                                        <input type="radio" id="sort-latest" name="sort" value="latest" <?php checked($current_sort, 'latest'); ?>>
                                        <label for="sort-latest"><?php _e('Latest Update', 'rom'); ?></label>
                                    </div>
                                    <div class="custom-select-option">
                                        <input type="radio" id="sort-newest" name="sort" value="newest" <?php checked($current_sort, 'newest'); ?>>
                                        <label for="sort-newest"><?php _e('Newest', 'rom'); ?></label>
                                    </div>
                                    <div class="custom-select-option">
                                        <input type="radio" id="sort-oldest" name="sort" value="oldest" <?php checked($current_sort, 'oldest'); ?>>
                                        <label for="sort-oldest"><?php _e('Oldest', 'rom'); ?></label>
                                    </div>
                                    <div class="custom-select-option">
                                        <input type="radio" id="sort-alphabetical" name="sort" value="alphabetical" <?php checked($current_sort, 'alphabetical'); ?>>
                                        <label for="sort-alphabetical"><?php _e('Alphabetical', 'rom'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="selected-filters-container" class="border-t border-gray-200 py-4 hidden">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="text-sm font-semibold text-gray-900"> <?php _e('Selected Filters', 'rom') ?>:</span>
                        </div>
                        <div id="selected-filters" class="flex flex-wrap gap-2"></div>
                    </div>
                </form>
				<div>
					<button id="clearFilters"
						class="inline-flex justify-center px-4 py-2 text-xs bg-gray-200 text-gray-800 rounded-full hover:bg-gray-300 transition font-medium">
						<?php echo get_svg_icon('reload').' '.__('Clear Filters', 'rom') ?>
					</button>
				</div>
			</div>
		</div>
	</div>
</section>