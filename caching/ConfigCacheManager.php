<?php
/**
 * Config Cache Manager
 *
 * Cache tất cả ACF Options Page fields bằng Transient API.
 * Batch load 1 lần, serve cho mọi request.
 * Khi apply Redis, Transient API tự switch — không cần sửa code.
 *
 * @package romsvault
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConfigCacheManager {

	const string TRANSIENT_KEY = 'romsfun_site_options';
	const int CACHE_TTL     = 3 * DAY_IN_SECONDS;

	/**
	 * ACF Options post_id (khớp với inc/options.php)
	 */
	const string OPTIONS_POST_ID = 'options';

	/**
	 * In-memory cache để tránh gọi get_transient nhiều lần trong cùng request
	 */
	private static ?array $runtime_cache = null;

	public static function init(): void {
		add_action( 'acf/save_post', array( __CLASS__, 'handle_options_save' ), 20, 1 );
		add_action( 'updated_option', array( __CLASS__, 'handle_option_update' ), 10, 1 );
	}

	/**
	 * Load toàn bộ options page fields, cache vào transient
	 */
	private static function load_all( bool $force = false ): array {
		if ( ! $force && self::$runtime_cache !== null ) {
			return self::$runtime_cache;
		}

		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( is_array( $cached ) ) {
				self::$runtime_cache = $cached;
				return $cached;
			}
		}

		$configs = get_fields( self::OPTIONS_POST_ID ) ?: array();

		set_transient( self::TRANSIENT_KEY, $configs, self::CACHE_TTL );
		self::$runtime_cache = $configs;

		return $configs;
	}

	/**
	 * Get config field theo key
	 *
	 * Usage: ConfigCacheManager::get('logo')
	 *        ConfigCacheManager::get('affs', [])
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$configs = self::load_all();
		return $configs[ $key ] ?? $default;
	}

	// =========================================
	// Convenience getters
	// =========================================

	public static function copyright(): string {
		return self::get( 'copyright', '' ) ?: '';
	}

	public static function header_js(): string {
		return self::get( 'header_js', '' ) ?: '';
	}

	public static function footer_js(): string {
		return self::get( 'footer_js', '' ) ?: '';
	}

	public static function ads_banner(): string {
		return self::get( 'ads_banner', '' ) ?: '';
	}

	public static function social_networks(): array {
		return self::get( 'social_networks', array() );
	}

	public static function affs(): array {
		return self::get( 'affs', array() );
	}

	public static function report_issues(): array {
		return self::get( 'page_report_issues', array() );
	}

	/**
	 * Tìm affiliate code theo host name
	 */
	public static function aff_code_by_host( string $host ): ?string {
		foreach ( self::affs() as $aff ) {
			if ( ! empty( $aff['aff_host'] ) && $aff['aff_host'] === $host ) {
				return $aff['aff_code'] ?? null;
			}
		}
		return null;
	}

	// =========================================
	// Cache invalidation
	// =========================================

	public static function clear_cache(): bool {
		self::$runtime_cache = null;
		return delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * ACF save hook — clear khi save options page
	 */
	public static function handle_options_save( int|string $post_id ): void {
		if ( $post_id === 'options' || $post_id === 'option' ) {
			self::clear_cache();
		}
	}

	/**
	 * WordPress option update hook — clear khi ACF options thay đổi
	 */
	public static function handle_option_update( string $option_name ): void {
		if ( str_starts_with( $option_name, 'options_' ) ) {
			self::clear_cache();
		}
	}
}

ConfigCacheManager::init();
