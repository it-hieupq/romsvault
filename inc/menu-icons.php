<?php
/**
 * Menu item icons.
 *
 * Appearance > Menus shows an icon picker under every item; the chosen key is
 * stored in the item's `_menu_item_icon` meta and the primary menu (desktop +
 * mobile, inc/nav-walker.php) draws it before the title. Only keys from the
 * list below are ever saved or rendered, so the SVG markup never comes from
 * user input.
 */

/**
 * Icon list: key => label + inner SVG markup (24x24 outline, stroke drawn by
 * the wrapper in rom_menu_icon_svg()). Add an icon by adding a row.
 */
function rom_menu_icons() {
	static $icons = null;
	if ( null !== $icons ) {
		return $icons;
	}

	$icons = array(
		'home'     => array( 'label' => __( 'Home', 'rom' ), 'svg' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>' ),
		'gamepad'  => array( 'label' => __( 'Gamepad', 'rom' ), 'svg' => '<rect x="2" y="6" width="20" height="12" rx="4"/><path d="M6 12h4m-2-2v4"/><path d="M15 11h.01M18 13h.01"/>' ),
		'cpu'      => array( 'label' => __( 'Emulator', 'rom' ), 'svg' => '<rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>' ),
		'book'     => array( 'label' => __( 'Blog', 'rom' ), 'svg' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>' ),
		'layers'   => array( 'label' => __( 'Collection', 'rom' ), 'svg' => '<path d="m12 2 10 5-10 5L2 7l10-5z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>' ),
		'star'     => array( 'label' => __( 'Star', 'rom' ), 'svg' => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>' ),
		'flame'    => array( 'label' => __( 'Trending', 'rom' ), 'svg' => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>' ),
		'download' => array( 'label' => __( 'Download', 'rom' ), 'svg' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>' ),
		'search'   => array( 'label' => __( 'Search', 'rom' ), 'svg' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/>' ),
		'heart'    => array( 'label' => __( 'Heart', 'rom' ), 'svg' => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>' ),
		'user'     => array( 'label' => __( 'User', 'rom' ), 'svg' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>' ),
		'info'     => array( 'label' => __( 'Info', 'rom' ), 'svg' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>' ),
		'mail'     => array( 'label' => __( 'Mail', 'rom' ), 'svg' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>' ),
		'grid'     => array( 'label' => __( 'Grid', 'rom' ), 'svg' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>' ),
		'tag'      => array( 'label' => __( 'Tag', 'rom' ), 'svg' => '<path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><path d="M7 7h.01"/>' ),
		'clock'    => array( 'label' => __( 'Clock', 'rom' ), 'svg' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>' ),
	);

	return $icons;
}

/**
 * Full <svg> for an icon key, or '' for an empty/unknown key. The markup comes
 * from rom_menu_icons() only, so it is safe to echo.
 */
function rom_menu_icon_svg( $key, $class = '' ) {
	$icons = rom_menu_icons();
	if ( ! $key || ! isset( $icons[ $key ] ) ) {
		return '';
	}

	return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[ $key ]['svg'] . '</svg>';
}

/**
 * Icon picker under each item in Appearance > Menus.
 */
function rom_menu_item_icon_field( $item_id, $menu_item ) {
	$current = (string) get_post_meta( $item_id, '_menu_item_icon', true );
	$name    = 'menu-item-rom-icon[' . (int) $item_id . ']';
	?>
	<fieldset class="field-rom-icon description description-wide">
		<legend><?php esc_html_e( 'Icon', 'rom' ); ?></legend>
		<div class="rom-icon-grid">
			<label class="rom-icon-choice" title="<?php esc_attr_e( 'No icon', 'rom' ); ?>">
				<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="" <?php checked( $current, '' ); ?>>
				<span aria-hidden="true">&times;</span>
				<span class="screen-reader-text"><?php esc_html_e( 'No icon', 'rom' ); ?></span>
			</label>
			<?php foreach ( rom_menu_icons() as $key => $icon ) : ?>
				<label class="rom-icon-choice" title="<?php echo esc_attr( $icon['label'] ); ?>">
					<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current, $key ); ?>>
					<span><?php echo rom_menu_icon_svg( $key ); ?></span>
					<span class="screen-reader-text"><?php echo esc_html( $icon['label'] ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
	</fieldset>
	<?php
}
add_action( 'wp_nav_menu_item_custom_fields', 'rom_menu_item_icon_field', 10, 2 );

/**
 * Save the picked icon. Runs for every item on the Menus screen save; anything
 * outside the icon list clears the meta.
 */
function rom_menu_item_icon_save( $menu_id, $item_id ) {
	if ( ! current_user_can( 'edit_theme_options' )
		|| ! isset( $_POST['update-nav-menu-nonce'], $_POST['menu-item-rom-icon'][ $item_id ] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['update-nav-menu-nonce'] ) ), 'update-nav_menu' ) ) {
		return;
	}

	$key = sanitize_key( wp_unslash( $_POST['menu-item-rom-icon'][ $item_id ] ) );
	if ( isset( rom_menu_icons()[ $key ] ) ) {
		update_post_meta( $item_id, '_menu_item_icon', $key );
	} else {
		delete_post_meta( $item_id, '_menu_item_icon' );
	}
}
add_action( 'wp_update_nav_menu_item', 'rom_menu_item_icon_save', 10, 2 );

/**
 * Picker styles, Menus screen only.
 */
function rom_menu_item_icon_admin_style() {
	?>
	<style>
		.field-rom-icon { margin: 6px 0 10px; }
		.field-rom-icon legend { margin-bottom: 4px; }
		.rom-icon-grid { display: flex; flex-wrap: wrap; gap: 4px; }
		.rom-icon-choice { position: relative; cursor: pointer; }
		.rom-icon-choice input { position: absolute; opacity: 0; }
		.rom-icon-choice span:not(.screen-reader-text) { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; color: #1d2327; font-size: 18px; }
		.rom-icon-choice svg { width: 18px; height: 18px; }
		.rom-icon-choice input:checked + span { border-color: #d81b60; box-shadow: 0 0 0 1px #d81b60; color: #d81b60; }
		.rom-icon-choice input:focus-visible + span { outline: 2px solid #2271b1; outline-offset: 1px; }
	</style>
	<?php
}
add_action( 'admin_head-nav-menus.php', 'rom_menu_item_icon_admin_style' );
