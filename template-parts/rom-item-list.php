<div class="bg-white rounded-xl p-3 flex gap-4 shadow-md transition items-center">
	<div class="w-20 h-20 rounded-lg flex-shrink-0 flex items-center justify-center overflow-hidden">
        <a href="<?php echo get_permalink() ?>" class="block w-full h-full">
		    <img src="<?php the_post_thumbnail_url('thumbnail'); ?>" alt="<?php the_title(); ?>" class="w-full h-full object-cover">
        </a>
    </div>
	<div class="flex-1 min-w-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
		<div class="flex-1">
			<h3 class="font-black mb-2 text-gray-900">
                <a href="<?php echo get_permalink() ?>" class="hover:text-rom-pink">
	                <?php the_title() ?>
                </a>
			</h3>
			<div class="flex flex-wrap items-center gap-2 mb-2">
                <?php
                $terms = get_the_terms(get_the_ID(), 'console');
                if (!is_wp_error($terms) && !empty($terms)) :
                    $term = $terms[0];
                    ?>
                    <a href="<?php echo get_term_link($term->term_id) ?>"
                        class="h-6 w-6 bg-gray-200 rounded flex items-center justify-center flex-shrink-0">
                        <img src="<?php echo get_field('cat_image', 'term_' . $term->term_id) ?>"
                                alt="<?php echo $term->name ?>"
                                class="w-full h-full object-contain">
                    </a>
                <?php endif; ?>

                <?php
				    $terms = get_the_terms(get_the_ID(), 'type');
					if ( !is_wp_error($terms) && !empty($terms) ) :
						foreach ($terms as $term) :
                            echo '<a class="px-4 py-1 rounded-full text-rom-pink border border-brand/50 text-xs font-semibold mr-1" href="' . get_brows_page_url().'?types[]='. $term->term_id . '">' . $term->name . '</a>';
						endforeach;
					else :
						$terms = get_the_terms(get_the_ID(), 'collection');
                        if (!is_wp_error($terms) && !empty($terms)) :
                            foreach ($terms as $term) :
                                echo '<a class="px-4 py-1 rounded-full bg-white text-rom-pink border border-rom-pink text-xs font-semibold mr-1" href="' . get_brows_page_url().'?collections[]='. $term->term_id . '">' . $term->name . '</a>';
                            endforeach;
                        endif;
                    endif;
				?>
			</div>
		</div>
		<div class="flex items-center gap-3 sm:items-end">
            <span class="badge badge-info text-xs">
                <span class="text-rom-pink"><?php echo get_svg_icon('download')?></span>
                <?php
                $download = get_post_meta(get_the_ID(), 'downloads', true);
                echo number_format( $download ?: 0 , 0, '', ','); ?>
            </span>
			<span class="badge badge-info text-xs inline-flex items-center">
                <span class="text-rom-pink"><?php echo get_svg_icon('size')?></span>
				<?php
                $file_size = get_post_meta(get_the_ID(), 'file_size', true);
                if ( empty($file_size) ) {
                    $file_size = get_post_meta(get_the_ID(), 'download_list_0_file_size', true);
                }
                echo !empty($file_size) ? esc_html($file_size) : 'N/A';
                ?>
			</span>
		</div>
	</div>
</div>