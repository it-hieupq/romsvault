<?php
/**
 * The template for displaying archive posts.
 *
 * @package romsvault
 */
get_header();
get_template_part( 'template-parts/hero' );
$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
?>

    <section class="pb-6 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
            <h1 class="text-lg font-semibold py-6 text-center uppercase tracking-widest">
				<?php echo __('Get more information in') . ' ' . get_queried_object()->name; ?>
            </h1>

			<?php if ( $paged == 1 && $desc = get_queried_object()->description ) : ?>
                <div class="pb-8 entry-content"><?php echo $desc; ?></div>
			<?php endif; ?>

			<?php if ( have_posts() ) : ?>
                <div class="grid md:grid-cols-2 gap-4">
					<?php while ( have_posts() ) : the_post();
						get_template_part( 'template-parts/blog-item-2' );
					endwhile; ?>
                </div>
				<?php
				k_pagination();
			else :
				get_template_part( 'template-parts/content', 'none' );
			endif;
			?>
        </div>
    </section>
<?php
get_footer();