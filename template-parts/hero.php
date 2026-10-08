<section class="hero-container relative">
	<div class="absolute top-0 w-full h-[20rem]"><img class="h-full w-full" src="<?php echo get_stylesheet_directory_uri().'/assets/images/hero.png' ?>" alt=""></div>
	<div class="relative w-full z-10 pt-[18rem]">
		<!-- Search Section inside Hero -->
		<div class="content-container px-4 sm:px-6 lg:px-8 w-full rounded-2xl">
			<div class="bg-white rounded-2xl shadow-md p-6 lg:p-8">

                <?php
				 if (is_search()) :
					$title = __('Search Results for: ', 'rom') . get_search_query();
				elseif(is_page() || is_singular('post')) :
					$title = get_the_title();
				elseif (is_tax() || is_category() || is_tag()) :
					$title = single_term_title('', false);
				elseif(is_archive()) :
					$title = post_type_archive_title('', false);
                else:
                    $title = __('Not found', 'rom');
				endif;
				?>

	            <h1 class="text-xl lg:text-2xl font-bold text-romfun-pink text-center"><?php echo $title; ?></h1>
			
                <div class="flex justify-center py-5">
                    <?php k_breadcrumb(); ?>
                </div>

                <?php if (is_search()) : ?>
                    <form class="flex flex-col lg:flex-row gap-3" method="GET" action="<?php home_url() ?>">
                        <label class="relative flex-1">
                            <input name="s" type="text" placeholder="Search for ROMs, consoles, emulators…"
                                   value="<?php echo get_search_query(); ?>"
                                   class="w-full rounded-full border border-border px-4 sm:px-6 py-4 text-gray-900 outline-none focus:ring-2 focus:ring-brand/40">
                            <span class="absolute inset-y-0 right-1 flex items-center pr-1">
                                <button class="px-5 sm:px-6 py-3 rounded-full text-white font-semibold shadow-soft bg-romfun-pink hover:bg-romfun-pink-dark transition">
                                  <?php _e('Search', 'rom') ?>
                                </button>
                              </span>
                        </label>
                    </form>

                <?php elseif(is_singular(array('post', 'page')) && !is_page_template(get_rom_page_templates())) : ?>
                    <div class="text-gray-500 font-bold flex items-center justify-center">
                        <?php echo get_svg_icon('time'). get_the_modified_date(); ?>
                    </div>
                <?php endif; ?>

			</div>
		</div>
	</div>
</section>