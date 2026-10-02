<?php
/**
 * The template for displaying 404 pages (not found).
 *
 * @package romsvault
 */

get_header(); 
?>
<?php get_template_part('template-parts/hero'); ?>

    <section class="py-6 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
            <div class="prose max-w-none bg-white p-6 py-16 lg:p-8 mb-8 rounded-2xl shadow-xl text-center">
	            <h1 class="text-2xl pb-6 font-semibold "><?php _e( 'Sorry! Page not found.', 'romsfun' ); ?></h1>
                <p> <?php _e( 'The page you are looking for does not exist. You can try to search for the page you are looking for.', 'romsfun' ); ?></p>
                <form class="flex justify-center py-6 relative" method="GET" action="<?php echo get_filter_base_url(); ?>">
                    <input name="q" type="text" placeholder="<?php _e( 'Search for the page you are looking for', 'romsfun' ); ?>" class="w-full rounded-full border border-border px-4 sm:px-6 py-4 text-gray-900 outline-none focus:ring-2 focus:ring-brand/40">
                    <span class="absolute inset-y-0 right-1 flex items-center pr-1">
                        <button class="px-5 sm:px-6 py-3 rounded-full text-white font-semibold shadow-soft bg-romfun-pink hover:bg-romfun-pink-dark transition">
                            <?php _e( 'Search', 'romsfun' ); ?>
                        </button>
                    </span>
                </form>
            </div>
        </div>
    </section>
<?php
get_footer();
