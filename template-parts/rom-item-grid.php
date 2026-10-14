<div class="flex-shrink-0 w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 min-w-0 px-2 box-border">
    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition h-full">
        <div class="aspect-[5/6] relative overflow-hidden">
            <a href="<?php the_permalink(); ?>" class="absolute inset-0 flex items-center justify-center">
                <img src="<?php the_post_thumbnail_url('thumbnail'); ?>"
                     alt="<?php the_title_attribute(); ?>"
                     class="w-full h-full object-cover transform hover:scale-105 transition duration-300 ease-in-out">
            </a>
        </div>
        <div class="p-4">

            <h3 class="font-bold text-sm pb-3 text-gray-800 hover:text-rom-pink">
                <a href="<?php the_permalink(); ?>">
                    <?php the_title() ?>
                </a>
            </h3>

            <?php
            $terms = get_the_terms(get_the_ID(), 'console');
            if (!is_wp_error($terms) && !empty($terms)) :
                $term = $terms[0];
                echo '<a class="inline-block bg-white text-rom-pink text-xs border font-semibold border-rom-pink px-3 py-1 mr-1 mb-1 rounded-full" href="' . get_term_link($term->term_id) . '">' . $term->name . '</a>';
            endif;

            $terms = get_the_terms(get_the_ID(), 'type');
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) :
                foreach ( $terms as $term ) :
	                echo '<a class="inline-block  px-3 py-1 rounded-full bg-hell text-gray-700 border text-xs font-semibold" href="' . get_brows_page_url().'?types[]='. $term->term_id . '">' . $term->name . '</a>';
                endforeach;
            endif;
            ?>

            <div class="flex items-center justify-between pt-4">
                <span class="text-xs">
                    <span class="text-rom-pink"><?php echo get_svg_icon( 'download' ) ?></span>
                    <?php
                    $download = get_post_meta(get_the_ID(), 'downloads', true);
                    echo number_format( $download ?: 0, 0, '', ',' ); ?>
                </span>
                <span class="text-xs inline-flex items-center">
                    <span class="text-rom-pink"><?php echo get_svg_icon( 'size' ) ?></span>
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
</div>