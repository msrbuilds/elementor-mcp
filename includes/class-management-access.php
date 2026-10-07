<?php
/**
 * One management gate for plugin screens and human-only mutation endpoints.
 * MCP execution continues to use the authenticated WordPress user's capabilities.
 *
 * @package EMCP_Tools
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class EMCP_Tools_Management_Access {

	const CAPABILITY = 'manage_emcp_tools';

	/** Register before admin callbacks, including callbacks supplied by the Pro overlay. */
	public static function init(): void {
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_capability' ), 10, 4 );
		add_action( 'admin_init', array( __CLASS__, 'guard_admin_request' ), -1000 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'guard_admin_rest' ), -1000, 3 );
		if ( is_admin() ) {
			require_once EMCP_TOOLS_DIR . 'includes/admin/class-management-access-admin.php';
			EMCP_Tools_Management_Access_Admin::init();
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once EMCP_TOOLS_DIR . 'includes/class-management-access-cli.php';
			WP_CLI::add_command( 'emcp management-access', 'EMCP_Tools_Management_Access_CLI' );
		}
	}

	/** The default remains administrator access; a configured policy adds a local allowlist. */
	public static function can_manage(): bool {
		if ( ! current_user_can( 'manage_options' ) ) { return false; }
		$policy = EMCP_Tools_Management_Policy::read();
		return ! is_wp_error( $policy ) && EMCP_Tools_Management_Policy::allows( $policy, (int) get_current_user_id() );
	}

	/** Map the dedicated menu capability without persistently changing WordPress roles. */
	public static function map_capability( array $caps, string $cap, int $user_id, array $args ): array {
		if ( self::CAPABILITY !== $cap ) { return $caps; }
		$policy = EMCP_Tools_Management_Policy::read();
		if ( is_wp_error( $policy ) || ! EMCP_Tools_Management_Policy::allows( $policy, $user_id ) ) {
			return array( 'do_not_allow' ); // Also denies a non-listed Multisite super admin.
		}
		return array( 'manage_options' );
	}

	/** Pure request classification; public OAuth/MCP and signed job execution are separate. */
	public static function is_management_request( string $page, string $action ): bool {
		if ( 'emcp-tools' === $page || str_starts_with( $page, 'emcp-tools-' ) || 'elementor-mcp' === $page || str_starts_with( $page, 'elementor-mcp-' ) || 'emcp-themer-php' === $page ) { return true; }
		// This existing action verifies a scoped job token, including for logged-out workers.
		if ( 'emcp_tools_migrate_restore_chunk_token' === $action ) { return false; }
		return str_starts_with( $action, 'emcp_tools_' ) || str_starts_with( $action, 'emcp_themer_' ) || in_array( $action, array( 'emcp_backup_chunk', 'emcp_restore_chunk' ), true );
	}

	/** Block direct URLs, admin-post and AJAX before their existing nonce/capability handlers. */
	public static function guard_admin_request(): void {
		// AJAX dispatches $_REQUEST; admin-post uses its own GET/POST precedence.
		// Check every candidate, so conflicting parameters cannot bypass the gate.
		foreach ( array( $_GET, $_POST, $_REQUEST ) as $source ) {
			$page   = isset( $source['page'] ) && is_string( $source['page'] ) ? sanitize_key( wp_unslash( $source['page'] ) ) : '';
			$action = isset( $source['action'] ) && is_string( $source['action'] ) ? sanitize_key( wp_unslash( $source['action'] ) ) : '';
			if ( self::is_management_request( $page, $action ) && ! self::can_manage() ) {
				wp_die( esc_html__( 'Your account cannot manage EMCP Tools on this site.', 'emcp-tools' ), '', array( 'response' => 403 ) );
			}
		}
	}

	/** Protect every route under the admin namespace even if a controller overrides its callback. */
	public static function guard_admin_rest( $result, $server, $request ) {
		// WordPress matches registered REST routes case-insensitively.
		$route = strtolower( $request->get_route() );
		if ( ( '/emcp-tools/v1/admin' === $route || str_starts_with( $route, '/emcp-tools/v1/admin/' ) ) && ! self::can_manage() ) {
			return new WP_Error( 'emcp_management_forbidden', __( 'Your account cannot manage EMCP Tools on this site.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		return $result;
	}
}
