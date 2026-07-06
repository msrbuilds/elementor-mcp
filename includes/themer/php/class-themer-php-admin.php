<?php
/**
 * EMCP Themer PHP Templates — review admin page (submenu under EMCP Themer).
 *
 * Lists draft PHP templates with type/compiled state/last error, a read-only code +
 * validation view, and a delete action. Registered only when the feature is enabled.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.1.0
 */
class EMCP_Tools_Themer_PHP_Admin {

	const PAGE = 'emcp-themer-php';

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_post_emcp_themer_php_delete', array( $this, 'handle_delete' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . EMCP_Tools_Themer_CPT::POST_TYPE,
			__( 'PHP Templates', 'emcp-tools' ),
			__( 'PHP Templates', 'emcp-tools' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'emcp-tools' ) );
		}
		$templates = EMCP_Tools_Themer_PHP_Store::list_templates();
		$view      = isset( $_GET['view'] ) ? absint( wp_unslash( $_GET['view'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$detail    = $view ? EMCP_Tools_Themer_PHP_Store::get( $view ) : null;
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-themer-php.php';
	}

	public function handle_delete(): void {
		$id = isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0;
		check_admin_referer( 'emcp_themer_php_delete_' . $id );
		if ( current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' ) ) {
			EMCP_Tools_Themer_PHP_Store::delete( $id );
		}
		wp_safe_redirect( add_query_arg( array( 'post_type' => EMCP_Tools_Themer_CPT::POST_TYPE, 'page' => self::PAGE ), admin_url( 'edit.php' ) ) );
		exit;
	}
}
