<div class="bg-white rounded-lg p-3 flex gap-4 shadow-md transition items-center">
	<div class="w-20 h-20 rounded-lg flex-shrink-0 flex items-center justify-center overflow-hidden">
		<a href="<?php echo get_permalink() ?>" class="block w-full h-full">
			<img src="<?php the_post_thumbnail_url('thumbnail'); ?>" alt="<?php the_title_attribute(); ?>" class="w-full h-full object-cover">
		</a>
	</div>
	<div class="flex-1 min-w-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
		<div class="flex-1">
			<h3 class="font-black text-sm mb-2 text-gray-900">
				<a href="<?php echo get_permalink() ?>" class="hover:text-rom-pink">
					<?php the_title() ?>
				</a>
			</h3>
			<div class="flex flex-wrap items-center gap-2 mb-2">
                <?php
					$terms = wp_get_post_terms(get_the_ID(), 'emulator_console', ['fields' => 'id=>name']);
					if ( !is_wp_error($terms) && !empty($terms) ) : ?>
                        <a href="<?php echo get_term_link(array_key_first($terms)) ?>" class="h-8 w-8 bg-gray-200 rounded flex items-center justify-center flex-shrink-0">
                            <img src="<?php echo get_field('emulator_console_image', 'term_'. array_key_first($terms)) ?>" alt="<?php echo $terms[array_key_first($terms)]; ?>" class="w-full h-full object-contain">
                        </a>
                <?php
                    endif;

				$terms = wp_get_post_terms(get_the_ID(), 'os', ['fields' => 'id=>name']);
				if ( !is_wp_error($terms) && !empty($terms) ) :
					foreach ($terms as $term_id => $term_name) :
                        echo '<a class="px-3 py-1 rounded-full text-xs border border-rom-pink font-semibold text-rom-pink" href="' . get_term_link($term_id) . '">' . $term_name . '</a>';
					endforeach;
				endif;
				?>
			</div>
		</div>
		<div class="flex items-center gap-3 sm:items-end">
			<span class="badge badge-info text-xs">
                <span class="text-rom-pink"><?php echo get_svg_icon('download')?></span>
                <?php
                $downloads = get_field('downloads', get_the_ID());
                echo number_format($downloads ?: 0, 0, '', ',') . ' ' . __('downloads', 'rom');
                ?>
            </span>
			<span class="badge badge-info text-xs inline-flex items-center">
                <span class="text-rom-pink"><?php echo get_svg_icon('size')?></span>
				<?php the_field('file_size', get_the_ID()); ?>
			</span>
		</div>
	</div>
</div>