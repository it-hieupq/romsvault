<div class="bg-white rounded-lg p-3 flex gap-4 shadow-md transition items-center">
	<div class="w-20 h-20 rounded-lg flex-shrink-0 flex items-center justify-center overflow-hidden">
		<a href="<?php echo get_permalink() ?>" class="block w-full h-full">
			<img src="<?php the_post_thumbnail_url('thumbnail'); ?>" alt="<?php the_title_attribute(); ?>" class="w-full h-full object-cover">
		</a>
	</div>
	<div class="flex-1 min-w-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
		<div class="flex-1">
			<h3 class="font-black text-sm mb-2 text-gray-900">
				<a href="<?php echo get_permalink() ?>" class="hover:text-romfun-pink">
					<?php the_title() ?>
				</a>
			</h3>
			<div class="flex flex-wrap items-center gap-2 mb-2">
				<div>
					<?php
					$terms = wp_get_post_terms(get_the_ID(), 'pack_console', ['fields' => 'id=>name']);
					if ( !is_wp_error( $terms ) && !empty( $terms ) ) :
						foreach ( $terms as $term_id => $name ) :
							?>
                            <a href="<?php echo get_term_link($term_id) ?>" class="px-3 py-1 rounded-full text-xs bg-romfun-pink text-white mr-1"> <?php echo $name; ?> </a>
						<?php
						endforeach;
					endif;
					?>
                </div>
                <div class="badge badge-info text-xs">
				<?php
                    $file = get_field('pack_number_of_files', get_the_ID());
				    echo get_svg_icon('file') . ' ' . $file ? : 0 . ' ' . __('files', 'romsfun');
				?>
                </div>
			</div>
		</div>
		<div class="flex items-center gap-3 sm:items-end">
			<span class="badge badge-info text-xs">
                <span class="text-romfun-pink"><?php echo get_svg_icon('download')?></span>
                <?php
                $downloads = get_field('downloads', get_the_ID());
                echo number_format($downloads ?: 0, 0, '', ',') . ' ' . __('downloads', 'romsfun');
                ?>
            </span>
			<span class="badge badge-info text-xs inline-flex items-center">
                <span class="text-romfun-pink"><?php echo get_svg_icon('size')?></span>
				<?php the_field('pack_size', get_the_ID()); ?>
			</span>
		</div>
	</div>
</div>