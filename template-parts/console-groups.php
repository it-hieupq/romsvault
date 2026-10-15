<?php
$rows = get_field( 'console_groups', 'option' );
if ( $rows ) :
	foreach ( $rows as $row ) :
		$sub_title = $row['title'];
		$term_ids = $row['consoles'];
		$terms = get_cached_terms_by_ids( 'console', $term_ids );
		
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}
		?>
        <section class="pb-6 text-gray-900">

            <div class="content-container p-4 sm:px-6 lg:px-8 w-full bg-white rounded-xl shadow-lg">

                <h3 class="text-lg font-semibold mb-8 flex items-center text-gray-600">
                    <span class="w-3 h-3 rounded-full bg-rom-pink mr-2"></span>
		            <?php echo $row['title'] ?>
                </h3>

                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
		            <?php foreach ( $terms as $term ) :
                        if ($term->count <= 0) {
                            continue;
                        }
                        // A term that went away between two cache writes still
                        // reaches here; get_term_link() answers WP_Error for it and
                        // echoing that is a fatal.
                        $term_link = get_term_link( $term->term_id );
                        if ( is_wp_error( $term_link ) ) {
                            continue;
                        }
			            $image = get_field('cat_image', 'term_' . $term->term_id);
			            ?>
                        <a href="<?php echo esc_url( $term_link ) ?>"
                           class="border border-gray-200 rounded-xl block shadow-sm hover:shadow-lg transition overflow-hidden">

                            <div class="flex items-center justify-center h-[160px] overflow-hidden bg-gray-100 p-4">
                                <img src="<?php echo $image; ?>" alt="<?php echo $term->name; ?>" />
                            </div>
                            <div class="console-info bg-white px-3 py-6">
                                <div class="font-bold text-base text-center mb-6 hover:text-rom-pink"><?php echo $term->name ?></div>
                                <div class="text-xs text-gray-500 flex justify-between">
                                    <span class="inline-flex items-center">
                                        <?php echo get_svg_icon( 'joystick' ) . number_format( $term->count ?: 0, 0, '' ) . ' ' . __('ROM', 'rom') ?>
                                    </span>
                                    <span class="inline-flex items-center">
                                        <?php
                                        $console_downloads = get_field('console_rom_downloads', 'term_' . $term->term_id);
                                        echo get_svg_icon( 'download' ) . number_format( $console_downloads ?: 0, 0, '' ) . ' ' . __( '', 'rom' );
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </a>
		            <?php endforeach; ?>
                </div>

            </div>

        </section>
	<?php endforeach; ?>

<?php endif; ?>