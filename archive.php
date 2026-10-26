<?php
/**
 * The template for displaying archive posts.
 *
 * @package romsvault
 */
get_header();
get_template_part( 'template-parts/hero' );
$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
global $wp_query;
?>

    <section class="pb-12 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
            <div class="flex items-end justify-between gap-4 py-8">
                <h2 class="text-xl font-semibold">
                    <span class="block text-xs font-semibold uppercase tracking-wider text-brand"><?php esc_html_e( 'Latest articles', 'rom' ); ?></span>
					<?php echo esc_html( is_home() ? single_post_title( '', false ) : ( get_queried_object()->name ?? get_the_archive_title() ) ); ?>
                </h2>
				<?php if ( $wp_query->found_posts ) : ?>
                    <span class="text-meta font-semibold text-slate-500 flex-shrink-0">
                        <?php echo esc_html( sprintf( _n( '%s article', '%s articles', $wp_query->found_posts, 'rom' ), number_format_i18n( $wp_query->found_posts ) ) ); ?>
                    </span>
				<?php endif; ?>
            </div>

			<?php if ( $paged == 1 && $desc = get_queried_object()->description ?? '' ) : ?>
                <div class="pb-8 entry-content"><?php echo wp_kses_post( $desc ); ?></div>
			<?php endif; ?>

			<?php if ( have_posts() ) : ?>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
					<?php while ( have_posts() ) : the_post();
						get_template_part( 'template-parts/post-card' );
					endwhile; ?>
                </div>
				<?php
				rom_pagination();
			else :
				get_template_part( 'template-parts/content', 'none' );
			endif;
			?>
        </div>
    </section>
<?php
get_footer();
