<?php
/**
 * A post's files, one card per group (direct downloads, mirror links), each row
 * linking to its download page. Shared by the single rom / emulator pages and the
 * download list / file pages.
 *
 * Args: post_id, groups (rom_download_groups()), skip (file number already open, 0 = none).
 *
 * @package romsvault
 */

$post_id = (int) $args['post_id'];
$skip    = isset( $args['skip'] ) ? (int) $args['skip'] : 0;

foreach ( $args['groups'] as $group ) :
    $items = array_filter( $group['items'], function ( $item ) use ( $skip ) {
        return $item['n'] !== $skip;
    } );
    if ( ! $items ) {
        continue;
    }
    ?>
    <div>
        <h3 class="flex items-center gap-2 text-caption font-bold uppercase tracking-widest text-slate-400 mb-2.5">
            <?php echo esc_html( $group['label'] ); ?>
            <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-500 tracking-normal"><?php echo esc_html( count( $items ) ); ?></span>
        </h3>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-card divide-y divide-slate-100 overflow-hidden">
            <?php foreach ( $items as $item ) :
                $f   = $item['file'];
                $ext = rom_file_ext( $f['url'] );
                ?>
                <a href="<?php echo esc_url( rom_download_url( $post_id, $item['n'] ) ); ?>" target="_blank" rel="nofollow"
                   class="group flex items-center gap-3 sm:gap-4 p-4 sm:px-5 hover:bg-primary-light/40 transition-colors">
                    <span class="w-10 h-10 rounded-xl bg-primary-light text-primary flex items-center justify-center font-bold text-caption uppercase flex-shrink-0">
                        <?php echo esc_html( $ext ? $ext : '#' . $item['n'] ); ?>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-bold text-meta sm:text-body text-slate-900 group-hover:text-primary transition-colors break-words"><?php echo esc_html( $f['name'] ); ?></span>
                        <span class="flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-caption text-slate-500 mt-0.5">
                            <?php if ( ! empty( $f['file_type'] ) ) : ?>
                                <span><?php esc_html_e( 'Format:', 'rom' ); ?> <strong class="text-slate-700"><?php echo esc_html( $f['file_type'] ); ?></strong></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $f['file_size'] ) ) : ?>
                                <span><?php esc_html_e( 'Size:', 'rom' ); ?> <strong class="text-slate-700"><?php echo esc_html( $f['file_size'] ); ?></strong></span>
                            <?php endif; ?>
                        </span>
                    </span>
                    <span class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 shadow-sm shadow-primary/25 group-hover:bg-primary-hover transition-colors" aria-hidden="true">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11m0 0-4.5-4.5M12 15l4.5-4.5M5 19h14"/></svg>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach;
