<?php
/**
 * The template default page.
 *
 * @package romsvault
 */

get_header(); 

while ( have_posts() ) : the_post();
	global $post;
?>

<?php get_template_part('template-parts/hero'); ?>

    <section class="py-6 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
            <article class="prose max-w-none bg-white p-6 lg:p-8 mb-8 rounded-2xl shadow-xl">
			        <?php the_content(); ?>
            </article>
        </div>
    </section>

<?php
endwhile;

get_footer();
