<section class="hero-container relative">
	<?php // Hero art matches template-parts/rom-filter.php: 500px tall, bottom 60% fading into the page. ?>
	<div class="absolute top-0 w-full h-[350px] -z-10">
		<img class="h-full w-full object-cover object-bottom" src="<?php echo get_stylesheet_directory_uri().'/assets/images/hero.png' ?>" alt="">
		<div class="absolute inset-x-0 bottom-0 h-[60%] bg-gradient-to-t from-slate-50 to-transparent pointer-events-none"></div>
	</div>
	<div class="relative w-full z-10 sm:pt-[10rem] pt-[6rem]">
		<!-- Search Section inside Hero -->
		<div class="content-container px-4 sm:px-6 lg:px-8 w-full rounded-2xl">
			<div class="bg-white rounded-2xl shadow-md p-5 lg:p-6">

                <?php
				 if (is_search()) :
					$title = __('Search Results for: ', 'rom') . get_search_query();
				elseif (is_home()) :
					$title = single_post_title('', false);
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

	            <h1 class="text-xl lg:text-2xl font-bold text-rom-pink text-center"><?php echo $title; ?></h1>
			
                <div class="flex justify-center py-5">
                    <?php k_breadcrumb(); ?>
                </div>

                <?php // Optional one-line lead under the breadcrumb, passed as $args['intro']. ?>
                <?php if ( ! empty( $args['intro'] ) ) : ?>
                    <p class="max-w-2xl mx-auto -mt-2 text-center text-body text-slate-600"><?php echo esc_html( $args['intro'] ); ?></p>
                <?php endif; ?>

                <?php if (is_search()) : ?>
                    <form class="flex flex-col lg:flex-row gap-3" method="GET" action="<?php home_url() ?>">
                        <label class="relative flex-1">
                            <input name="s" type="text" placeholder="Search for ROMs, consoles, emulators…"
                                   value="<?php echo get_search_query(); ?>"
                                   class="w-full rounded-full border border-border px-4 sm:px-6 py-4 text-gray-900 outline-none focus:ring-2 focus:ring-brand/40">
                            <span class="absolute inset-y-0 right-1 flex items-center pr-1">
                                <button class="px-5 sm:px-6 py-3 rounded-full text-white font-semibold shadow-soft bg-rom-pink hover:bg-rom-pink-dark transition">
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