<?php
/**
 * /profile — the member's account dashboard. Cache-safe shell: auth.js fills the
 * name/email/avatar (applyState) and the per-category counts from GET rf/v1/my-stats.
 * The Wishlist card links to the dedicated /wishlist page; the Comments and Reports
 * cards are accordion toggles — clicking one lazy-loads its list (skeleton -> rows ->
 * "Load more") from REST and caches it client-side. Guests see a sign-in prompt.
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
        <h1 class="text-2xl font-bold mb-6 text-romfun-pink"><?php esc_html_e( 'My Profile', 'romsfun' ); ?></h1>

        <div class="rf-guest-only bg-white rounded-2xl shadow-md p-8 text-center">
            <p class="text-gray-600 mb-4"><?php esc_html_e( 'Sign in to view your profile.', 'romsfun' ); ?></p>
            <button type="button" data-rf-login class="px-6 py-3 bg-romfun-pink text-white rounded-full font-semibold hover:bg-romfun-pink-dark transition"><?php esc_html_e( 'Sign in', 'romsfun' ); ?></button>
        </div>

        <div class="rf-authed-only">

            <!-- Identity card -->
            <div class="bg-white rounded-2xl shadow-md p-6 flex items-center gap-4">
                <img class="rf-avatar shrink-0" style="width:4rem;height:4rem" alt="" data-rf-avatar
                     src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='12' fill='%23e5e7eb'/%3E%3Ccircle cx='12' cy='9.5' r='3.5' fill='%23cbd5e1'/%3E%3Cpath d='M5 20c0-3.6 3.2-5.5 7-5.5s7 1.9 7 5.5z' fill='%23cbd5e1'/%3E%3C/svg%3E">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-lg font-bold truncate" data-rf-name></span>
                        <!-- Status badge: Active by default; JS swaps to Blocked when the member row is blocked -->
                        <span data-rf-status="active" class="shrink-0 inline-flex items-center gap-1 text-[11px] font-semibold text-green-700 bg-green-50 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span><?php esc_html_e( 'Active', 'romsfun' ); ?>
                        </span>
                        <span data-rf-status="blocked" class="hidden shrink-0 inline-flex items-center gap-1 text-[11px] font-semibold text-red-700 bg-red-50 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span><?php esc_html_e( 'Blocked', 'romsfun' ); ?>
                        </span>
                    </div>
                    <div class="text-sm text-gray-500 truncate" data-rf-email></div>
                    <div class="text-xs text-gray-400 mt-0.5 rf-member-since">
                        <?php esc_html_e( 'Member since', 'romsfun' ); ?> <span data-rf-member-since>&mdash;</span>
                    </div>
                </div>
                <button type="button" data-rf-logout class="shrink-0 px-4 py-2 border border-gray-300 rounded-full font-semibold text-gray-700 hover:bg-gray-100 transition text-sm"><?php esc_html_e( 'Logout', 'romsfun' ); ?></button>
            </div>

            <!-- Suspension notice: hidden by default; auth.js reveals it when the member is blocked -->
            <div data-rf-blocked-notice class="hidden mt-4 flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-2xl text-sm text-red-700">
                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.492-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span><?php esc_html_e( 'Your account has been suspended by an administrator. You can still view your profile, but posting comments, sending reports, and updating your wishlist are disabled.', 'romsfun' ); ?></span>
            </div>

            <!-- Wishlist: navigates to its own page, so it is kept visually separate
                 from the inline accordion cards below. -->
            <a href="<?php echo esc_url( home_url( '/wishlist/' ) ); ?>"
               class="rf-stat-card flex items-center justify-between gap-4 bg-white rounded-2xl shadow-md p-5 mt-4 hover:shadow-lg transition">
                <span class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-romfun-pink/10 text-romfun-pink shrink-0">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M9.653 16.915l-.005-.003-.019-.01a20.759 20.759 0 01-1.162-.682 22.045 22.045 0 01-2.582-1.9C4.045 12.733 2 10.352 2 7.5 2 5.015 4.015 3 6.5 3c1.398 0 2.65.638 3.5 1.638A4.494 4.494 0 0113.5 3C15.985 3 18 5.015 18 7.5c0 2.852-2.045 5.233-3.885 6.82a22.049 22.049 0 01-3.744 2.582l-.019.01-.005.003h-.002a.739.739 0 01-.69.001z"/></svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-gray-700"><?php esc_html_e( 'Wishlist', 'romsfun' ); ?></span>
                        <span class="block text-xs text-gray-400"><?php esc_html_e( 'Games you saved', 'romsfun' ); ?></span>
                    </span>
                </span>
                <span class="flex items-center gap-3 shrink-0">
                    <span class="text-2xl font-bold text-romfun-pink" data-rf-count="wishlist">&mdash;</span>
                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            </a>

            <!-- Create Request: navigates to the dedicated /ticket page -->
            <a href="<?php echo esc_url( home_url( '/request/' ) ); ?>"
               class="rf-stat-card flex items-center justify-between gap-4 bg-white rounded-2xl shadow-md p-5 mt-4 hover:shadow-lg transition">
                <span class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-romfun-pink/10 text-romfun-pink shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-gray-700"><?php esc_html_e( 'Create Request', 'romsfun' ); ?></span>
                        <span class="block text-xs text-gray-400"><?php esc_html_e( 'Request a game or file', 'romsfun' ); ?></span>
                    </span>
                </span>
                <svg class="w-5 h-5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>

            <!-- Activity: inline accordion cards. Click Comments/Reports to expand its
                 list below; Ratings is a reserved slot (feature not shipped yet). -->
            <h2 class="text-sm font-semibold text-gray-500 mt-6 mb-3"><?php esc_html_e( 'Activity', 'romsfun' ); ?></h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">

                <button type="button"
                        class="rf-stat-card text-left bg-white rounded-2xl shadow-md p-5 flex flex-col gap-2 hover:shadow-lg transition"
                        data-rf-stat-toggle="comments" aria-expanded="false" aria-controls="rf-panel-comments">
                    <span class="flex items-center justify-between text-sm font-semibold text-gray-500">
                        <?php esc_html_e( 'Comments', 'romsfun' ); ?>
                        <svg class="w-4 h-4 text-gray-400 transition-transform rf-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                    <span class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold text-romfun-pink" data-rf-count="comments">&mdash;</span>
                        <span class="hidden text-xs font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full" data-rf-count-pending></span>
                    </span>
                </button>

                <button type="button"
                        class="rf-stat-card text-left bg-white rounded-2xl shadow-md p-5 flex flex-col gap-2 hover:shadow-lg transition"
                        data-rf-stat-toggle="reports" aria-expanded="false" aria-controls="rf-panel-reports">
                    <span class="flex items-center justify-between text-sm font-semibold text-gray-500">
                        <?php esc_html_e( 'Reports', 'romsfun' ); ?>
                        <svg class="w-4 h-4 text-gray-400 transition-transform rf-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                    <span class="text-3xl font-bold text-romfun-pink" data-rf-count="reports">&mdash;</span>
                </button>

                <button type="button"
                        class="rf-stat-card text-left bg-white rounded-2xl shadow-md p-5 flex flex-col gap-2 hover:shadow-lg transition"
                        data-rf-stat-toggle="ratings" aria-expanded="false" aria-controls="rf-panel-ratings">
                    <span class="flex items-center justify-between text-sm font-semibold text-gray-500">
                        <?php esc_html_e( 'Ratings', 'romsfun' ); ?>
                        <svg class="w-4 h-4 text-gray-400 transition-transform rf-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                    <span class="text-3xl font-bold text-romfun-pink" data-rf-count="ratings">&mdash;</span>
                </button>

                <button type="button"
                        class="rf-stat-card text-left bg-white rounded-2xl shadow-md p-5 flex flex-col gap-2 hover:shadow-lg transition"
                        data-rf-stat-toggle="contacts" aria-expanded="false" aria-controls="rf-panel-contacts">
                    <span class="flex items-center justify-between text-sm font-semibold text-gray-500">
                        <?php esc_html_e( 'Contact', 'romsfun' ); ?>
                        <svg class="w-4 h-4 text-gray-400 transition-transform rf-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                    <span class="text-3xl font-bold text-romfun-pink" data-rf-count="contacts">&mdash;</span>
                </button>

            </div>

            <!-- Detail panels (accordion; one open at a time). JS unhides + fills these. -->
            <div class="mt-4" data-rf-detail>

                <?php
                /**
                 * Shared panel shell for a category. $slug drives the data hooks and the
                 * empty-state copy; the skeleton/list/load-more wiring is identical.
                 */
                $rf_panels = array(
                    'comments' => __( 'You have not posted any comments yet.', 'romsfun' ),
                    'reports'  => __( 'You have not sent any reports yet.', 'romsfun' ),
                    'ratings'  => __( 'You have not rated any posts yet.', 'romsfun' ),
                    'contacts' => __( 'You have not sent any messages yet.', 'romsfun' ),
                );
                foreach ( $rf_panels as $rf_slug => $rf_empty_text ) :
                    ?>
                    <div id="rf-panel-<?php echo esc_attr( $rf_slug ); ?>"
                         class="rf-panel hidden bg-white rounded-2xl shadow-md p-5"
                         data-rf-panel="<?php echo esc_attr( $rf_slug ); ?>">

                        <!-- Skeleton (shown while the first page loads) -->
                        <div class="rf-skeleton animate-pulse space-y-4" data-rf-skeleton>
                            <?php for ( $i = 0; $i < 3; $i++ ) : ?>
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-lg bg-gray-200 shrink-0"></div>
                                    <div class="flex-1 space-y-2">
                                        <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <!-- Rendered rows -->
                        <ul class="rf-list hidden divide-y divide-gray-100" data-rf-list></ul>

                        <!-- Empty state -->
                        <div class="rf-empty hidden text-center text-gray-500 py-8" data-rf-empty>
                            <?php echo esc_html( $rf_empty_text ); ?>
                        </div>

                        <!-- Load more -->
                        <div class="rf-loadmore hidden text-center mt-4 pt-4 border-t border-gray-100" data-rf-loadmore-wrap>
                            <button type="button" data-rf-loadmore
                                    class="px-6 py-2 border border-romfun-pink text-romfun-pink rounded-full font-semibold hover:bg-romfun-pink hover:text-white transition text-sm">
                                <?php esc_html_e( 'Load more', 'romsfun' ); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>
</section>
<?php
get_footer();
