<?php
/** Explicit host operations for local management access. @package EMCP_Tools */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class EMCP_Tools_Management_Access_CLI {
	/**
	 * Show the current policy without credentials.
	 */
	public function status( $args, $assoc_args ): void {
		if ( ! EMCP_Tools_Management_Access::can_manage() ) { WP_CLI::error( 'Use --user with an authorized administrator, or the host recovery command.' ); }
		$policy = EMCP_Tools_Management_Policy::read();
		if ( is_wp_error( $policy ) ) { WP_CLI::error( $policy->get_error_message() ); }
		WP_CLI::log( wp_json_encode( $policy ) );
	}

	/**
	 * Change the policy as an existing manager.
	 *
	 * ## OPTIONS
	 *
	 * --mode=<mode>
	 * : all_admins or allowlist.
	 * [--users=<ids>]
	 * : Comma-separated current administrator IDs; include the acting user.
	 * --version=<version>
	 * : Version from the status command. Prevents overwriting a newer policy.
	 * [--confirm]
	 * : Explicitly consent to the local policy change.
	 */
	public function set( $args, $assoc_args ): void {
		self::confirm( $assoc_args );
		$mode = $assoc_args['mode'] ?? '';
		$version = filter_var( $assoc_args['version'] ?? '', FILTER_VALIDATE_INT );
		$users = 'all_admins' === $mode ? array() : EMCP_Tools_Management_Policy::parse_users( $assoc_args['users'] ?? '' );
		if ( false === $version || $version < 0 || is_wp_error( $users ) ) { WP_CLI::error( 'Supply a valid version and comma-separated administrator IDs.' ); }
		self::output( EMCP_Tools_Management_Policy::save( $mode, $users, $version, (int) get_current_user_id() ) );
	}

	/**
	 * Recover a locked or malformed policy to the administrator default.
	 * Requires host WP-CLI and --user with a current administrator.
	 *
	 * ## OPTIONS
	 *
	 * [--confirm]
	 * : Explicitly consent to restoring all current administrator access.
	 */
	public function recover( $args, $assoc_args ): void {
		self::confirm( $assoc_args );
		$policy = EMCP_Tools_Management_Policy::read();
		$version = is_wp_error( $policy ) ? 0 : $policy['version'];
		self::output( EMCP_Tools_Management_Policy::save( 'all_admins', array(), $version, (int) get_current_user_id(), true ) );
	}

	/** Require an intentional host command, not an HTTP request. */
	private static function confirm( array $args ): void {
		if ( ! EMCP_Tools_Management_Policy::is_host_cli() || ! \WP_CLI\Utils\get_flag_value( $args, 'confirm', false ) ) {
			WP_CLI::error( 'Host WP-CLI and --confirm are required. Select a current administrator with --user.' );
		}
	}

	/** Report only the new policy or a safe error. */
	private static function output( $result ): void {
		if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
		WP_CLI::success( 'Management access saved: ' . wp_json_encode( $result ) );
	}
}
