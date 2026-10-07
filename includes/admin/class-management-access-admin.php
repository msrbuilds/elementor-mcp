<?php
/** Human-only local management policy form. @package EMCP_Tools */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class EMCP_Tools_Management_Access_Admin {
	const ACTION = 'emcp_tools_management_policy_save';

	/** The main admin registry supplies the menu and frame. */
	public static function init(): void {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'save' ) );
	}

	/** Render inside the existing plugin frame, using current local policy. */
	public static function render(): void {
		if ( ! EMCP_Tools_Management_Access::can_manage() ) { return; }
		$policy = EMCP_Tools_Management_Policy::read();
		if ( is_wp_error( $policy ) ) { echo '<p>' . esc_html( $policy->get_error_message() ) . '</p>'; return; }
		$actor = wp_get_current_user();
		$users = get_users( array( 'capability' => 'manage_options', 'number' => 100, 'orderby' => 'ID', 'order' => 'ASC' ) );
		include __DIR__ . '/views/management-access.php';
	}

	/** Require both current local authorization and a fresh form nonce. */
	public static function save(): void {
		if ( ! EMCP_Tools_Management_Access::can_manage() ) {
			wp_die( esc_html__( 'Your account cannot manage EMCP Tools on this site.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );
		$mode = isset( $_POST['mode'] ) && is_string( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$version = isset( $_POST['version'] ) && is_string( $_POST['version'] ) ? filter_var( wp_unslash( $_POST['version'] ), FILTER_VALIDATE_INT ) : false;
		$text = isset( $_POST['users'] ) && is_string( $_POST['users'] ) ? trim( wp_unslash( $_POST['users'] ) ) : '';
		$users = 'all_admins' === $mode ? array() : EMCP_Tools_Management_Policy::parse_users( $text );
		if ( false === $version || $version < 0 || '1' !== ( $_POST['confirm'] ?? '' ) || is_wp_error( $users ) ) {
			wp_die( esc_html__( 'Confirm the policy and supply valid administrator IDs. Reload before trying again.', 'emcp-tools' ), '', array( 'response' => 400 ) );
		}
		$result = EMCP_Tools_Management_Policy::save( $mode, $users, $version, (int) get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => $data['status'] ?? 503 ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=emcp-tools-management&saved=1' ) );
		exit;
	}
}
