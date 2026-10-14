<?php
/**
 * Member UI helpers — server-rendered, cache-safe markup toggled client-side by
 * auth.js (html.rf-authed / rf-guest-only): the logged-in member menu (single
 * source for the desktop dropdown + mobile submenu) and the guest "restricted
 * content" sign-in notice.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The logged-in member menu items. Each: label + (url | logout=true) + icon key.
 */
function rf_member_menu_items(): array {
	return array(
		array( 'label' => __( 'Profile', 'rom' ),        'url' => home_url( '/profile/' ),  'icon' => 'user' ),
		array( 'label' => __( 'Wishlist', 'rom' ),       'url' => home_url( '/wishlist/' ), 'icon' => 'heart' ),
		array( 'label' => __( 'Create Request', 'rom' ), 'url' => home_url( '/request/' ),   'icon' => 'ticket' ),
		array( 'label' => __( 'Logout', 'rom' ),         'logout' => true,                  'icon' => 'logout' ),
	);
}

/**
 * Inline SVG (currentColor) for a member-menu icon key.
 */
function rf_member_menu_icon( $key ): string {
	$icons = array(
		'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
		'heart'  => '<path d="M20.8 5.6a5.4 5.4 0 0 0-7.7 0L12 6.7l-1.1-1.1a5.4 5.4 0 1 0-7.7 7.7L12 22l8.8-8.7a5.4 5.4 0 0 0 0-7.7z"/>',
		'ticket' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
		'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
	);
	$paths = isset( $icons[ $key ] ) ? $icons[ $key ] : '';
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
}

/**
 * Render the member menu. $variant: 'desktop' (.rf-di) | 'mobile' (.rf-m-sub-item).
 * Logout is a button (data-rf-logout); the rest are links.
 */
function rf_render_member_menu( $variant = 'mobile' ): void {
	$item_class = $variant === 'desktop' ? 'rf-di' : 'rf-m-sub-item';
	foreach ( rf_member_menu_items() as $it ) {
		$icon  = rf_member_menu_icon( $it['icon'] );
		$label = '<span>' . esc_html( $it['label'] ) . '</span>';
		if ( ! empty( $it['logout'] ) ) {
			echo '<button type="button" class="' . esc_attr( $item_class ) . '" data-rf-logout>' . $icon . $label . '</button>';
		} else {
			echo '<a class="' . esc_attr( $item_class ) . '" href="' . esc_url( $it['url'] ) . '">' . $icon . $label . '</a>';
		}
	}
}

/**
 * Notice for members-only (restricted) downloads. Cache-safe: rendered for everyone
 * and shown (rf-inactive-only) to anyone who is NOT an active member — guests AND
 * blocked members. Active (signed-in, not-blocked) members see the real download
 * button instead. The server (download-url) remains the real gate.
 */
function rf_render_restricted_notice(): void {
	echo '<div class="rf-inactive-only flex items-center gap-3 p-4 my-4 bg-gray-50 border border-gray-200 rounded-xl">'
		. '<span class="shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-full bg-rom-pink/10 text-rom-pink">'
		. '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>'
		. '</span>'
		. '<p class="text-sm text-gray-600 m-0"><span class="font-semibold text-gray-800">' . esc_html__( 'Members only.', 'rom' ) . '</span> '
		. esc_html__( 'This is restricted content, available to active members only.', 'rom' )
		. '</p>'
		. '</div>';
}
