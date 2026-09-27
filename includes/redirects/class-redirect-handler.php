<?php
/**
 * Front-end redirect handler. On template_redirect (before the theme serves a
 * 404), matches the requested path against enabled redirects and issues a
 * 301/302. Skips wp-admin, REST, cron, and login. The hot path is a single
 * indexed source_path lookup.
 *
 * @package EMCP_Tools
 * @since   3.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect handler.
 *
 * @since 3.11.0
 */
class EMCP_Tools_Redirect_Handler {

	/**
	 * Register the front-end hook (early, before 404 templating).
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
	}

	/**
	 * Contexts where redirects must never fire.
	 *
	 * @return bool
	 */
	public static function should_skip(): bool {
		if ( is_admin() ) {
			return true;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}
		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return (bool) preg_match( '#^(wp-admin|wp-json|wp-login\.php)#', ltrim( $uri, '/' ) );
	}

	/**
	 * Match the current request and redirect when a source is found.
	 */
	public static function maybe_redirect(): void {
		if ( self::should_skip() || ! class_exists( 'EMCP_Tools_Redirect_Store' ) ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = EMCP_Tools_Redirect_Store::normalize_path( $uri );
		if ( '/' === $path ) {
			return;
		}
		$row = EMCP_Tools_Redirect_Store::find_for_request( $path, EMCP_Tools_Redirect_Store::normalize_query( $uri ) );
		if ( ! $row ) {
			return;
		}
		$target = EMCP_Tools_Redirect_Store::resolve_target( $row );
		if ( '' === $target ) {
			return; // Target post is gone, so the rule is inactive.
		}
		if ( EMCP_Tools_Redirect_Store::rule_loops( $path, (string) ( $row['source_query'] ?? '' ), $target ) ) {
			return;
		}
		// A path rule forwards the original query to a query-less target; a query rule forwards nothing (spec 9.8).
		$target = EMCP_Tools_Redirect_Store::target_for( $row, $target, (string) wp_parse_url( $uri, PHP_URL_QUERY ) );
		EMCP_Tools_Redirect_Store::record_hit( (int) $row['id'] );
		$code = in_array( (int) $row['status_code'], array( 301, 302 ), true ) ? (int) $row['status_code'] : 301;
		wp_redirect( $target, $code ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}
}
