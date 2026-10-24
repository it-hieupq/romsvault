<?php
/**
 * /request — "Create Request" page. Cache-safe shell: identical HTML for everyone;
 * auth.js gates guest vs member (html.rf-authed). The create form is shown to ALL
 * visitors — a guest's submit is gated to sign-in, a member's submits to POST rf/v1/ticket.
 * A member also sees their CURRENT open request (with a lifecycle stepper) and a lazy
 * "Request history" panel that only calls GET rf/v1/my-tickets when opened. When the
 * member already has an open request, the form is locked and the notice is shown.
 *
 * @package romsvault
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
$rf_ticket_types = rf_ticket_types();
?>
<style>
	/* Lifecycle stepper for each request card (custom CSS: JS-built class names would be
	   purged by Tailwind). Steps up to the current status are filled; the current one glows.
	   Cancelled leaves the flow, so it renders a distinct terminal pill instead. */
	.rf-tk-steps { display:flex; align-items:flex-start; margin:.85rem 0 .35rem; }
	.rf-tk-step { flex:1; position:relative; text-align:center; font-size:11px; min-width:0; }
	.rf-tk-step::before { content:""; position:absolute; top:14px; right:50%; width:100%; height:3px; background:#e5e7eb; z-index:0; }
	.rf-tk-step:first-child::before { display:none; }
	.rf-tk-dot { position:relative; z-index:1; display:inline-block; width:30px; height:30px; line-height:30px; border-radius:9999px; background:#e5e7eb; color:#9ca3af; font-weight:700; font-size:13px; }
	.rf-tk-lbl { display:block; margin-top:5px; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
	.rf-tk-step.is-done .rf-tk-dot { background:var(--rf-tk-c,#ec4899); color:#fff; }
	.rf-tk-step.is-done .rf-tk-lbl { color:#374151; }
	.rf-tk-step.is-done::before { background:var(--rf-tk-c,#ec4899); }
	.rf-tk-step.is-active .rf-tk-dot { box-shadow:0 0 0 3px var(--rf-tk-glow,rgba(236,72,153,.25)); }
	.rf-tk-step.is-active .rf-tk-lbl { color:var(--rf-tk-c,#ec4899); font-weight:600; }
	.rf-tk-cancel { display:inline-flex; align-items:center; gap:6px; margin:.85rem 0 .35rem; padding:4px 12px; border-radius:9999px; background:#fee2e2; color:#991b1b; font-size:12px; font-weight:600; }
	/* Row action buttons — Close = red, Cancel = yellow (raw CSS so JS-built class names aren't purged) */
	.rf-tk-btn { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; padding:7px 16px; border-radius:9999px; border:1px solid; cursor:pointer; transition:background .15s, border-color .15s; }
	.rf-tk-btn svg { width:15px; height:15px; }
	.rf-tk-btn:disabled { opacity:.5; cursor:not-allowed; }
	.rf-tk-btn-close { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
	.rf-tk-btn-close:hover { background:#fee2e2; border-color:#fca5a5; }
	.rf-tk-btn-cancel { background:#f8fafc; border-color:#e2e8f0; color:#475569; }
	.rf-tk-btn-cancel:hover { background:#f1f5f9; border-color:#cbd5e1; }
	/* Current-request card: type pill + labelled detail sections (icon + heading + body) */
	.rf-tk-type { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:600; color:#64748b; background:#f1f5f9; padding:3px 10px; border-radius:9999px; white-space:nowrap; }
	.rf-tk-type svg { width:14px; height:14px; }
	.rf-tk-sec { margin-top:1rem; padding-top:1rem; border-top:1px solid #f1f5f9; }
	.rf-tk-sec-h { display:flex; align-items:center; gap:6px; font-size:11px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:#94a3b8; margin-bottom:6px; }
	.rf-tk-sec-h svg { width:15px; height:15px; }
	.rf-tk-sec-b { font-size:14px; line-height:1.6; color:#374151; white-space:pre-line; word-break:break-word; }
	.rf-tk-reply { margin-top:1rem; border-radius:12px; background:#fffbeb; border:1px solid #fde68a; border-left:3px solid #f59e0b; padding:12px 14px; }
	.rf-tk-reply .rf-tk-sec-h { color:#b45309; }
	.rf-tk-reply .rf-tk-sec-b { color:#92400e; font-style:italic; }
	/* History accordion: chevron rotates when the panel is open */
	.rf-tk-chev { transition:transform .2s; }
	[data-rf-history-toggle][aria-expanded="true"] .rf-tk-chev { transform:rotate(180deg); }
</style>
<section class="py-8 text-gray-900" data-rf-ticket-page data-rf-status-labels="<?php echo esc_attr( wp_json_encode( array_values( rf_ticket_statuses() ) ) ); ?>" data-rf-status-colors="<?php echo esc_attr( wp_json_encode( rf_ticket_status_colors() ) ); ?>">
	<div class="content-container px-4 sm:px-6 lg:px-8 w-full max-w-6xl">
		<?php // One column: the form for everyone, then the member's own requests underneath. ?>
		<div class="max-w-2xl mx-auto space-y-6">

			<h1 class="text-2xl font-bold text-rom-pink"><?php esc_html_e( 'Create Request', 'rom' ); ?></h1>

			<!-- Create form (shown to everyone; submit is gated to sign-in for guests) -->
			<div class="space-y-4">

				<!-- Lock notice: member-only; auth.js reveals it when an open request exists -->
				<div data-rf-ticket-lock style="display:none" class="rf-authed-only flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-sm text-amber-700 mb-4">

                    <div class="flex items-center">
                        <svg class="w-8 h-8 block mr-1 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <?php esc_html_e( 'You already have an open request. Please close it before creating a new one.', 'rom' ); ?>
                    </div>
                </div>

                <div style="margin: 0">
                    <form data-rf-ticket-form class="bg-white rounded-2xl shadow-md p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Request type', 'rom' ); ?> <span class="text-red-500">*</span></label>
                            <select name="type" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option value="" disabled selected><?php esc_html_e( '— Select a type —', 'rom' ); ?></option>
                                <?php foreach ( $rf_ticket_types as $rf_k => $rf_label ) : ?>
                                    <option value="<?php echo esc_attr( $rf_k ); ?>"><?php echo esc_html( $rf_label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Game title', 'rom' ); ?> <span class="text-red-500">*</span></label>
                            <input type="text" name="game_title" maxlength="255" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="<?php esc_attr_e( 'e.g. The Legend of Zelda', 'rom' ); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Reference link', 'rom' ); ?> <span class="text-gray-400 font-normal">(<?php esc_html_e( 'optional', 'rom' ); ?>)</span></label>
                            <input type="url" name="reference_url" maxlength="512" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="https://">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Details', 'rom' ); ?></label>
                            <textarea name="description" rows="4" maxlength="500" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="<?php esc_attr_e( 'Describe your request…', 'rom' ); ?>"></textarea>
                        </div>
                        <!-- Member: real submit. Guest: sign-in gate (swapped by html.rf-authed). -->
                        <button type="submit" data-rf-ticket-submit class="rf-authed-only w-full px-6 py-3 bg-rom-pink text-white rounded-full font-semibold hover:bg-rom-pink-dark transition disabled:opacity-50 disabled:cursor-not-allowed"><?php esc_html_e( 'Submit request', 'rom' ); ?></button>
                        <button type="button" data-rf-login class="rf-guest-only w-full px-6 py-3 bg-rom-pink text-white rounded-full font-semibold hover:bg-rom-pink-dark transition"><?php esc_html_e( 'Sign in to submit', 'rom' ); ?></button>
                    </form>
                </div>

			</div>

				<!-- Member: current request + lazy history -->
				<div class="rf-authed-only">

					<div data-rf-current-loading class="animate-pulse bg-white rounded-xl shadow-md p-5 space-y-3">
						<div class="h-4 bg-gray-200 rounded w-1/2"></div>
						<div class="h-6 bg-gray-100 rounded"></div>
						<div class="h-3 bg-gray-200 rounded w-3/4"></div>
					</div>
					<div data-rf-current-card class="hidden"></div>
					<div data-rf-current-none class="hidden bg-white rounded-xl shadow-md p-10 text-center text-gray-500">
						<?php esc_html_e( 'You have no open request. Submit one above.', 'rom' ); ?>
					</div>

					<!-- Request history: one accordion card — toggle header + divided item rows -->
					<div class="mt-6 bg-white rounded-2xl shadow-md overflow-hidden">
						<button type="button" data-rf-history-toggle aria-expanded="false"
								class="w-full flex items-center justify-between gap-2 p-4 text-left font-bold text-gray-700 hover:bg-gray-50 transition">
							<span><?php esc_html_e( 'Recently request history', 'rom' ); ?></span>
							<svg class="rf-tk-chev w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
						</button>

						<div data-rf-history-panel class="hidden border-t border-gray-100">
							<div data-rf-history-loading class="animate-pulse p-5 space-y-3">
								<div class="h-4 bg-gray-200 rounded w-1/2"></div>
								<div class="h-6 bg-gray-100 rounded"></div>
							</div>
							<div data-rf-ticket-list class="hidden divide-y divide-gray-100"></div>
							<div data-rf-ticket-empty class="hidden p-8 text-center text-gray-500"><?php esc_html_e( 'No past requests yet.', 'rom' ); ?></div>
						</div>
					</div>

				</div>

		</div>
	</div>
</section>
<?php
get_footer();
