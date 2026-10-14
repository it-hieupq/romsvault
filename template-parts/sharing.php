<?php
/**
 * Share bar. Appended to the content of singular post/rom/emulator by the
 * the_content filter in functions.php, so it must stay neutral enough to sit
 * under a blog article as well as under a rom description.
 *
 * The copy button reuses copyToClipboard() from js/main.js, which flashes its
 * confirmation into the button's parent — hence the relative wrapper.
 */
$share_url   = get_permalink();
$share_title = get_the_title();
$icons       = k_get_social_icons();
?>
<div class="pt-5 mt-5 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
    <span class="text-caption font-bold uppercase tracking-wider text-slate-400"><?php esc_html_e( 'Share this:', 'rom' ); ?></span>

    <div class="flex items-center gap-2">
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( $share_url ); ?>"
           target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-caption font-semibold transition-colors no-underline [&_svg]:w-3.5 [&_svg]:h-3.5">
			<?php echo $icons['facebook']; ?>
            Facebook
        </a>

        <a href="https://x.com/intent/tweet?url=<?php echo rawurlencode( $share_url ); ?>&amp;text=<?php echo rawurlencode( $share_title ); ?>"
           target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-caption font-semibold transition-colors no-underline [&_svg]:w-3.5 [&_svg]:h-3.5">
			<?php echo $icons['twitter']; ?>
            X / Twitter
        </a>

        <div class="relative">
            <button type="button"
                    onclick="copyToClipboard('<?php echo esc_js( $share_url ); ?>', this); return false;"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-caption font-semibold transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                </svg>
				<?php esc_html_e( 'Copy Link', 'rom' ); ?>
            </button>
        </div>
    </div>
</div>
