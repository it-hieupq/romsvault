<?php
/**
 * Ratings & reviews section (Play Store style). Appended under single valid-post-type
 * content via the_content (see auth/actions/rating.php). Cache-safe shell: js/rating.js
 * fills the public reviews list + the caller's own rating from GET rf/v1/ratings, and
 * posts a new rating to POST rf/v1/rating. Guests see a sign-in line; a blocked member's
 * form is hidden (html.rf-blocked) and the server refuses the write regardless.
 *
 * @package romsvault
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$post_id = get_the_ID();
// The visible review count, so js/rating.js can skip the reviews request for a guest
// on a post nobody has reviewed. Same for every visitor: cache-safe.
$rf_count = rom_rating( $post_id )['count'];
?>
<section id="rating" class="rf-rating-section bg-white rounded-2xl shadow-md p-6 my-6 scroll-mt-24" data-rf-rating="<?php echo esc_attr( $post_id ); ?>" data-rf-count="<?php echo esc_attr( $rf_count ); ?>">
    <h3 class="text-xl font-bold text-gray-900 mb-4"><?php esc_html_e( 'Ratings & reviews', 'rom' ); ?></h3>

    <!-- Summary (Play Store style): left = score + total, right = per-star analysis
         bars. Filled by js/rating.js from GET rf/v1/ratings (post.avg/count + dist). -->
    <div class="rf-rating-summary flex flex-col sm:flex-row gap-6 mb-6 pb-6 border-b border-gray-100">
        <div class="rf-rating-score shrink-0 text-center sm:text-left sm:pr-8 sm:border-r sm:border-gray-100">
            <div class="text-6xl font-bold text-gray-900 leading-none" data-rf-avg>0.0</div>
            <div class="my-2"><span class="rf-stars" style="font-size:1.15rem"><span class="rf-stars-fill" data-rf-avg-fill style="width:0%"></span></span></div>
            <div class="text-sm text-gray-500"><span data-rf-total>0</span> <?php esc_html_e( 'ratings', 'rom' ); ?></div>
        </div>
        <div class="rf-rating-bars flex-1 self-center w-full">
            <?php for ( $s = 5; $s >= 1; $s-- ) : ?>
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-3 text-right text-xs text-gray-500"><?php echo $s; ?></span>
                    <span class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                        <span class="block h-full rounded-full bg-rom-pink" data-rf-bar="<?php echo $s; ?>" style="width:0%"></span>
                    </span>
                    <span class="w-8 text-right text-xs text-gray-500" data-rf-barcount="<?php echo $s; ?>">0</span>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Member: rating form. js/rating.js hides it and shows the read-only card when
         the member has already rated (ratings are immutable). Guests see no sign-in
         prompt here — the single shared prompt lives in the comment area. -->
    <div class="rf-authed-only">
        <form class="rf-rating-form border border-gray-200 rounded-xl p-4 mb-4" novalidate>
            <div class="rf-rating-msg hidden p-3 mb-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>
            <p class="text-sm font-semibold text-gray-700 mb-2"><?php esc_html_e( 'Write your rating', 'rom' ); ?></p>
            <div class="rf-star-input" role="radiogroup" aria-label="<?php esc_attr_e( 'Write your rating', 'rom' ); ?>">
                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                    <button type="button" class="rf-star-btn" data-score="<?php echo $i; ?>" aria-label="<?php echo esc_attr( sprintf( _n( '%d star', '%d stars', $i, 'rom' ), $i ) ); ?>">&#9733;</button>
                <?php endfor; ?>
            </div>
            <textarea class="rf-rating-content w-full mt-3 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 outline-none focus:ring-2 focus:ring-rom-pink/40 focus:border-rom-pink resize-none" rows="3" maxlength="164" placeholder="<?php esc_attr_e( 'Share a short review (optional)', 'rom' ); ?>"></textarea>
            <button type="submit" class="rf-rating-submit mt-3 px-6 py-2 bg-rom-pink text-white rounded-full font-semibold hover:bg-rom-pink-dark transition text-sm"><?php esc_html_e( 'Submit rating', 'rom' ); ?></button>
        </form>
        <!-- Already rated (filled by JS from `mine`) -->
        <div class="rf-rating-mine hidden border border-gray-200 rounded-xl p-4 mb-4"></div>
    </div>

    <!-- Public reviews list -->
    <div class="rf-reviews">
        <p class="rf-reviews-loading text-sm text-gray-500 py-4"><?php esc_html_e( 'Loading reviews…', 'rom' ); ?></p>
        <ul class="rf-reviews-list hidden divide-y divide-gray-100"></ul>
        <div class="rf-reviews-empty hidden text-sm text-gray-500 py-4"><?php esc_html_e( 'No reviews yet. Be the first to review!', 'rom' ); ?></div>
        <div class="rf-reviews-more hidden text-center mt-4">
            <button type="button" class="rf-reviews-loadmore px-6 py-2 border border-rom-pink text-rom-pink rounded-full font-semibold hover:bg-rom-pink hover:text-white transition text-sm"><?php esc_html_e( 'Load more', 'rom' ); ?></button>
        </div>
    </div>
</section>
