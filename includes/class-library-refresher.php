<?php
/**
 * WP-Cron background refresh of the Pro Prompts / Brand Kits libraries.
 *
 * When a cached library bundle expires, a scheduled event re-fetches it in the
 * (non-admin) cron context so the admin cards self-heal without the user
 * clicking "Sync Library". The fetcher classes live under admin/ and aren't
 * loaded in cron, so each callback requires its fetcher on demand.
 *
 * @package EMCP_Tools
 * @since   2.1.0 (extracted from the bootstrap file)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Background library refresh cron callbacks.
 *
 * @since 2.1.0
 */
class EMCP_Tools_Library_Refresher {

	/**
	 * Hooks the scheduled refresh events. Registered unconditionally (cron runs
	 * in a non-admin context).
	 *
	 * @since 2.1.0
	 */
	public static function register(): void {
		add_action( 'emcp_tools_refresh_pro_prompts', array( __CLASS__, 'refresh_prompts' ) );
		add_action( 'emcp_tools_refresh_pro_brand_kits', array( __CLASS__, 'refresh_brand_kits' ) );
	}

	/**
	 * Re-fetches the Pro Prompts library.
	 *
	 * @since 2.1.0
	 */
	public static function refresh_prompts(): void {
		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return;
		}
		$file = class_exists( 'EMCP_Tools_Pro_Loader' ) ? EMCP_Tools_Pro_Loader::path( 'includes/admin/class-pro-prompts.php' ) : '';
		if ( '' === $file || ! file_exists( $file ) ) {
			return;
		}
		require_once $file;
		if ( class_exists( 'EMCP_Tools_Pro_Prompts' ) && EMCP_Tools_Pro_Prompts::user_has_access() ) {
			EMCP_Tools_Pro_Prompts::get_bundle( true );
		}
	}

	/**
	 * Re-fetches the Pro Brand Kits library.
	 *
	 * @since 2.1.0
	 */
	public static function refresh_brand_kits(): void {
		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return;
		}
		$file = class_exists( 'EMCP_Tools_Pro_Loader' ) ? EMCP_Tools_Pro_Loader::path( 'includes/admin/class-pro-brand-kits.php' ) : '';
		if ( '' === $file || ! file_exists( $file ) ) {
			return;
		}
		require_once $file;
		if ( class_exists( 'EMCP_Tools_Pro_Brand_Kits' ) && EMCP_Tools_Pro_Brand_Kits::user_has_access() ) {
			EMCP_Tools_Pro_Brand_Kits::get_bundle( true );
		}
	}
}
