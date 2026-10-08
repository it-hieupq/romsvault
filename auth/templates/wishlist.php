<?php
/**
 * /wishlist — the member's saved games. Cache-safe shell: auth.js fills the grid
 * from GET /rf/v1/wishlist. Guests see a sign-in prompt (toggled by html.rf-authed).
 *
 * @package romsvault
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
?>
<section class="py-8 text-gray-900">
    <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
        <h1 class="text-2xl font-bold mb-6 text-romfun-pink"><?php esc_html_e( 'My Wishlist', 'rom' ); ?></h1>

        <div class="rf-guest-only bg-white rounded-2xl shadow-md p-8 text-center">
            <p class="text-gray-600 mb-4"><?php esc_html_e( 'Sign in to view your wishlist.', 'rom' ); ?></p>
            <button type="button" data-rf-login class="px-6 py-3 bg-romfun-pink text-white rounded-full font-semibold hover:bg-romfun-pink-dark transition"><?php esc_html_e( 'Sign in', 'rom' ); ?></button>
        </div>

        <div class="rf-authed-only" data-rf-wishlist-page>
            <p class="rf-wish-loading text-gray-500 py-8 text-center"><?php esc_html_e( 'Loading…', 'rom' ); ?></p>
            <div class="rf-wish-list hidden"></div>
            <div class="rf-wish-empty hidden bg-white rounded-2xl shadow-md p-8 text-center text-gray-600"><?php esc_html_e( 'Your wishlist is empty.', 'rom' ); ?></div>
        </div>
    </div>
</section>
<?php
get_footer();
