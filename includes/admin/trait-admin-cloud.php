<?php
/**
 * Cloud backup, library, marketplace, and settings sync actions.
 *
 * Internal implementation of EMCP_Tools_Admin; loaded by class-admin.php.
 * Methods retain the admin class scope for existing callbacks and callers.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloud backup, library, marketplace, and settings sync actions.
 */
trait EMCP_Tools_Admin_Cloud_Trait {

	/**
	 * Push the local EMCP settings to EMCP Cloud (paid Cloud feature).
	 */
	public function handle_settings_push(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'emcp_tools_settings_sync' );
		$res = class_exists( 'EMCP_Tools_Settings_Sync' ) ? EMCP_Tools_Settings_Sync::push() : new \WP_Error( 'unavailable', '' );
		$this->redirect_settings_sync( is_wp_error( $res ) ? 'err' : 'push', is_wp_error( $res ) ? $res->get_error_code() : '' );
	}

	/**
	 * Pull the EMCP settings from EMCP Cloud and apply them (paid Cloud feature).
	 */
	public function handle_settings_pull(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'emcp_tools_settings_sync' );
		$res = class_exists( 'EMCP_Tools_Settings_Sync' ) ? EMCP_Tools_Settings_Sync::pull_and_apply() : new \WP_Error( 'unavailable', '' );
		$this->redirect_settings_sync( is_wp_error( $res ) ? 'err' : 'pull', is_wp_error( $res ) ? $res->get_error_code() : '', is_wp_error( $res ) ? 0 : (int) ( $res['applied'] ?? 0 ) );
	}

	/**
	 * Redirect back to the Connection tab after a settings-sync action.
	 *
	 * @param string $status     push|pull|err.
	 * @param string $error_code Optional safe WP_Error code for a useful notice.
	 * @param int    $applied    Settings a pull applied.
	 */
	private function redirect_settings_sync( string $status, string $error_code = '', int $applied = 0 ): void {
		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-connection' );
		}
		$back = remove_query_arg( array( 'synced', 'sync_error', 'sync_applied' ), $back );
		$args = array( 'synced' => $status );
		if ( 'pull' === $status ) {
			$args['sync_applied'] = $applied;
		}
		if ( 'err' === $status && '' !== $error_code ) {
			$args['sync_error'] = sanitize_key( $error_code );
		}
		wp_safe_redirect( add_query_arg( $args, $back ) );
		exit;
	}
}
