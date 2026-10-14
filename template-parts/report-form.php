<?php
// Report modal — member-only (plan §4.7). Submits to REST rf/v1/report via the
// auth.js fetch wrapper; identity comes from the session and the reported URL is
// the post permalink (server-derived), so no email/URL/nonce is collected here.
// Cache-safe: rendered the same for everyone, JS toggles html.rf-authed. Guests
// never get the modal — they see an inline sign-in line instead (rf-guest-only);
// only members see the "Request help" bar + modal (rf-authed-only).
$post_id = isset($args['post_id']) ? absint($args['post_id']) : get_the_ID();
?>

<!-- Guests see no report section at all — the single shared sign-in prompt lives in
     the comment area. Only logged-in members get the Request help bar + modal. -->

<!-- Member: Request help bar + report modal -->
<div class="rf-authed-only">
    <div class="help-block flex items-center justify-between px-4 py-3 bg-white my-6 rounded-lg shadow-md">
        <span class="text-sm text-gray-700 flex items-center font-semibold">
            <?php _e('Problems with download or installation?', 'rom'); ?>
        </span>
        <button
            type="button"
            id="open-help-modal"
            class="inline-flex items-center gap-2 px-4 py-2 bg-rom-pink text-white rounded-full hover:bg-rom-pink-dark transition font-semibold shadow-lg hover:shadow-xl transform hover:scale-105 text-sm"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                <path d="M14.778.085A.5.5 0 0 1 15 .5V8a.5.5 0 0 1-.314.464L14.5 8l.186.464-.003.001-.006.003-.023.009a12.435 12.435 0 0 1-.397.15c-.264.095-.631.223-1.047.35-.816.252-1.879.523-2.71.523-.847 0-1.548-.28-2.158-.525l-.028-.01C7.68 8.71 7.14 8.5 6.5 8.5c-.7 0-1.638.23-2.437.477A19.626 19.626 0 0 0 3 9.342V15.5a.5.5 0 0 1-1 0V.5a.5.5 0 0 1 1 0v.282c.226-.079.496-.17.79-.26C4.606.272 5.67 0 6.5 0c.84 0 1.524.277 2.121.519l.043.018C9.286.788 9.828 1 10.5 1c.7 0 1.638-.23 2.437-.477a19.587 19.587 0 0 0 1.349-.476l.019-.007.004-.002h.001M14 1.221c-.22.078-.48.167-.766.255-.81.252-1.872.523-2.734.523-.886 0-1.592-.286-2.203-.534l-.008-.003C7.662 1.21 7.139 1 6.5 1c-.669 0-1.606.229-2.415.478A21.294 21.294 0 0 0 3 1.845v6.433c.22-.078.48-.167.766-.255C4.576 7.77 5.638 7.5 6.5 7.5c.847 0 1.548.28 2.158.525l.028.01C9.32 8.29 9.86 8.5 10.5 8.5c.668 0 1.606-.229 2.415-.478A21.317 21.317 0 0 0 14 7.655V1.222z"/>
            </svg>
            <?php _e('Request help', 'rom'); ?>
        </button>
    </div>

    <!-- Modal Overlay -->
    <div id="help-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-50">
        <div class="modal-overlay absolute inset-0" id="modal-overlay"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto z-10 transform transition-all duration-200 scale-95 opacity-0">

            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h4 class="text-xl font-bold text-gray-900"><?php _e('Help center', 'rom'); ?></h4>
                <button
                    type="button"
                    id="close-help-modal"
                    class="text-gray-400 hover:text-gray-600 transition-colors"
                    aria-label="Close"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="modal-body p-6">
                <!-- Report form (identity from session; URL derived server-side) -->
                <form id="report-form" class="space-y-4" method="post" action="" novalidate>
                    <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">

                    <!-- Inline message (e.g. blocked account) -->
                    <div id="reportFormMsg" class="hidden p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>

                    <div class="form-group">
                        <label for="report-type" class="block text-sm font-semibold text-gray-700 mb-2">
                            <?php _e('Select Issue', 'rom'); ?>
                        </label>
                        <select
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 outline-none focus:ring-2 focus:ring-rom-pink/40 focus:border-rom-pink bg-white"
                            id="report-type"
                            name="report-type"
                            required
                        >
                            <?php if($issues = get_field('page_report_issues', 'option')) :
                                foreach ($issues as $issue) : ?>
                                    <option value="<?php echo esc_attr($issue['page_report_issue']); ?>">
                                        <?php echo esc_html($issue['page_report_issue']); ?>
                                    </option>
                                <?php endforeach;
                            endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="report-content" class="block text-sm font-semibold text-gray-700 mb-2">
                            <?php _e('Feedback - Please describe the problem (optional)', 'rom'); ?>
                        </label>
                        <textarea
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 outline-none focus:ring-2 focus:ring-rom-pink/40 focus:border-rom-pink resize-none"
                            id="report-content"
                            name="report-content"
                            rows="4"
                            maxlength="255"
                        ></textarea>
                        <p class="text-xs text-gray-500 mt-1"><?php _e('Maximum 255 characters', 'rom'); ?></p>
                    </div>

                    <button
                        type="submit"
                        class="report-btn w-full inline-flex items-center justify-center gap-2 px-6 py-3 bg-rom-pink text-white rounded-full hover:bg-rom-pink-dark transition font-semibold shadow-lg hover:shadow-xl transform hover:scale-105"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M15.854.146a.5.5 0 0 1 .11.54l-5.819 14.547a.75.75 0 0 1-1.329.124l-3.178-4.995L.643 7.184a.75.75 0 0 1 .124-1.33L15.314.037a.5.5 0 0 1 .54.11ZM6.636 10.07l2.761 4.338L14.13 2.576 6.636 10.07Zm6.787-8.201L1.591 6.602l4.339 2.76 7.494-7.493Z"/>
                        </svg>
                        <?php _e('Send Report', 'rom'); ?>
                    </button>
                </form>
            </div>

            <div class="modal-footer p-6 border-t border-gray-200">
                <small class="text-gray-500 italic">
                    <i><?php printf( esc_html__('You can send up to %d reports per day.', 'rom'), defined('RF_REPORT_DAILY_MAX') ? RF_REPORT_DAILY_MAX : 30 ); ?></i>
                </small>
            </div>
        </div>
    </div>
</div>
