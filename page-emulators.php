<?php
/**
 * Template Name: Emulators
 *
 * @package romsvault
 */

get_header();
get_template_part( 'template-parts/hero' );
global $post;
?>
<?php if ( $post->post_content ) : ?>
    <section class="pt-6 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full rounded-lg">
			<?php the_content(); ?>
        </div>
    </section>
<?php endif; ?>

    <section class="py-6 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
			<?php
			$terms = get_terms( array( 'taxonomy' => 'emulator_console' ) );
			if ( $terms && is_array( $terms ) ):
				?>
                <section class="content-container w-full text-gray-900 rounded-lg">
                    <h3 class="text-lg font-semibold mb-8 flex items-center">
                        <span class="w-3 h-3 rounded-full bg-rom-pink mr-2"></span>
						<?php _e( 'List of available consoles', 'rom' ) ?>
                    </h3>

                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-4">
						<?php foreach ( $terms as $term ) :
							$image = get_field( 'emulator_console_image', 'term_' . $term->term_id );
							?>
                            <a href="<?php echo get_term_link( $term->term_id ) ?>"
                               class="border border-gray-200 rounded-xl block shadow-sm hover:shadow-lg transition overflow-hidden">

                                <div class="flex items-center justify-center h-[160px] overflow-hidden bg-gray-100 p-4">
                                    <img src="<?php echo $image; ?>" alt="<?php echo $term->name; ?>"/>
                                </div>
                                <div class="console-info bg-white px-3 py-6">
                                    <div class="font-bold text-base text-center mb-6 hover:text-rom-pink"><?php echo $term->name ?></div>
                                    <div class="text-xs text-gray-500 flex justify-between">
                                        <span class="inline-flex items-center">
                                            <?php echo get_svg_icon( 'joystick' ) . number_format( $term->count ?: 0, 0, '' ) . ' ' . __( 'Emulators', 'rom' ) ?>
                                        </span>

                                        <span class="inline-flex items-center">
                                        <?php
                                        $console_downloads = get_field( 'emulator_console_downloads', 'term_' . $term->term_id );
                                        echo get_svg_icon( 'download' ) . number_format( $console_downloads ?: 0, 0, '' ) . ' ' . __( '', 'rom' );
                                        ?>
                                    </span>
                                    </div>
                                </div>
                            </a>
						<?php endforeach; ?>
                    </div>
                </section>
			<?php
			else :
				get_template_part( 'template-parts/content', 'none' );
			endif;
			?>
        </div>
    </section>
<?php
get_footer();