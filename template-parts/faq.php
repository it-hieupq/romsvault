<?php
/**
 * FAQ section for the download pages (download.php) and the Roms / Emulators
 * listing pages.
 *
 * $args: 'items' (from rom_faq_items()), 'eyebrow', 'title', and 'container' for
 * the wrapper classes (defaults to the single pages' width). Renders nothing
 * without items. Each question is an h3 and every answer is in the HTML, all
 * collapsed by default. The FAQPage schema is added only on the listing pages
 * (inc/utility.php); the download pages show the same FAQ without markup.
 */
$faq_items = $args['items'] ?? array();
if ( ! $faq_items ) {
	return;
}
?>
<section id="faq" class="py-6 sm:py-8 scroll-mt-24">
    <div class="<?php echo esc_attr( $args['container'] ?? 'max-w-content mx-auto px-4 sm:px-6' ); ?>">
        <div class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-7 shadow-card">
            <div class="mb-4">
                <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php echo esc_html( $args['eyebrow'] ?? __( 'FAQ', 'rom' ) ); ?></span>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5"><?php echo esc_html( $args['title'] ?? __( 'Frequently Asked Questions', 'rom' ) ); ?></h2>
            </div>
            <div class="divide-y divide-slate-100 border-y border-slate-100">
				<?php foreach ( $faq_items as $item ) : ?>
                    <details class="group py-1">
                        <summary class="flex items-start justify-between gap-4 py-3 cursor-pointer list-none text-body font-semibold text-slate-900 hover:text-primary transition-colors">
                            <h3 class="text-body font-semibold"><?php echo esc_html( $item['question'] ); ?></h3>
                            <svg class="w-4 h-4 mt-1 flex-shrink-0 text-slate-400 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <div class="pb-4 pr-8 text-meta text-slate-600 leading-relaxed space-y-2"><?php echo wpautop( esc_html( $item['answer'] ) ); ?></div>
                    </details>
				<?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
