<?php
/**
 * Template Name: Contact Us
 *
 * /contact-us — a member sends a message to the site admin (title + content). Mirrors the
 * default page.php shell (hero + content-container) and adds the contact form. Cache-safe:
 * identical HTML for everyone; auth.js gates guest vs member via html.rf-authed. A guest
 * sees a sign-in prompt; a member sees the form, which POSTs to rf/v1/contact
 * (auth/actions/contact.php). Any admin reply is read back on the member's /profile page
 * (Contact history). Assign this template to a Page whose slug is "contact-us".
 *
 * @package romsvault
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) : the_post();
	global $post;
?>

<?php get_template_part( 'template-parts/hero' ); ?>

	<section class="py-6 text-gray-900">
		<div class="content-container px-4 sm:px-6 lg:px-8 w-full">

			<?php if ( $post->post_content ) : ?>
				<article class="prose max-w-none bg-white p-6 lg:p-8 mb-8 rounded-2xl shadow-xl">
					<?php the_content(); ?>
				</article>
			<?php endif; ?>

			<div class="w-full">

				<!-- Guest: sign-in prompt (auth.js reveals it for signed-out visitors) -->
				<div class="rf-guest-only bg-white rounded-2xl shadow-md p-8 text-center">
					<svg class="w-10 h-10 mx-auto mb-3 text-romfun-pink" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
					<p class="text-gray-600 mb-4"><?php esc_html_e( 'Sign in to send us a message.', 'romsfun' ); ?></p>
					<button type="button" data-rf-login class="px-6 py-3 bg-romfun-pink text-white rounded-full font-semibold hover:bg-romfun-pink-dark transition"><?php esc_html_e( 'Sign in', 'romsfun' ); ?></button>
				</div>

				<!-- Member: contact form (submit => POST rf/v1/contact) -->
				<form data-rf-contact-form class="rf-authed-only bg-white rounded-2xl shadow-md p-6 space-y-4">
					<div>
						<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Title', 'romsfun' ); ?> <span class="text-red-500">*</span></label>
						<input type="text" name="title" maxlength="64" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="<?php esc_attr_e( 'Subject of your message', 'romsfun' ); ?>">
					</div>
					<div>
						<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Message', 'romsfun' ); ?> <span class="text-red-500">*</span></label>
						<textarea name="content" rows="6" maxlength="512" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="<?php esc_attr_e( 'How can we help you?', 'romsfun' ); ?>"></textarea>
					</div>
					<button type="submit" data-rf-contact-submit class="w-full px-6 py-3 bg-romfun-pink text-white rounded-full font-semibold hover:bg-romfun-pink-dark transition disabled:opacity-50 disabled:cursor-not-allowed"><?php esc_html_e( 'Send message', 'romsfun' ); ?></button>
				</form>

			</div>
		</div>
	</section>

<?php
endwhile;

get_footer();
