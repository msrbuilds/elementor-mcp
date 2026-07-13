<?php
/**
 * Dismissible "Install Elementor to enable the page-building tools" admin
 * notice.
 *
 * Elementor is OPTIONAL — the plugin and every beyond-Elementor tool work
 * without it. This non-blocking warning nudges admins who have not installed
 * Elementor, but it is purely informational, so it is dismissible per-user via
 * user_meta — once dismissed, it stays dismissed.
 *
 * @package EMCP_Tools
 * @since   3.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Elementor_Notice {

	/**
	 * User-meta key recording when this user dismissed the notice.
	 * Value: Unix timestamp, or empty/false if never dismissed.
	 */
	const META_KEY = 'emcp_tools_elementor_notice_dismissed';

	/**
	 * AJAX nonce action name.
	 */
	const NONCE_ACTION = 'emcp_tools_dismiss_elementor_notice';

	public function init(): void {
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
		add_action( 'wp_ajax_emcp_tools_dismiss_elementor_notice', array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Decide whether the notice should render on the current request.
	 */
	private function should_show(): bool {
		// Admins only.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Only when Elementor is absent.
		if ( class_exists( 'EMCP_Tools_Bootstrap' ) && EMCP_Tools_Bootstrap::elementor_active() ) {
			return false;
		}

		// Hide if the current user has dismissed it.
		if ( get_user_meta( get_current_user_id(), self::META_KEY, true ) ) {
			return false;
		}

		return true;
	}

	public function maybe_render(): void {
		if ( ! $this->should_show() ) {
			return;
		}

		$install = self_admin_url( 'plugin-install.php?s=Elementor&tab=search&type=term' );
		$nonce   = wp_create_nonce( self::NONCE_ACTION );

		printf(
			'<div class="notice notice-warning is-dismissible" data-emcp-elementor-notice="1" data-emcp-nonce="%s"><p>%s</p><p><a class="button button-secondary" href="%s">%s</a></p></div>',
			esc_attr( $nonce ),
			esc_html__( 'EMCP Tools is active. Install and activate Elementor to enable the Elementor page-building tools (widgets, layout, templates, brand kits). All other tools — WordPress content, plugins & themes, users, media, performance, security, filesystem, and database — work without it.', 'emcp-tools' ),
			esc_url( $install ),
			esc_html__( 'Install Elementor', 'emcp-tools' )
		);
		?>
		<script>
		( function () {
			var notice = document.querySelector( '[data-emcp-elementor-notice]' );
			if ( ! notice ) return;
			// WordPress renders the dismiss button asynchronously; delegate on the
			// notice so we catch the click whenever the button is added.
			notice.addEventListener( 'click', function ( e ) {
				if ( ! e.target.closest( '.notice-dismiss' ) ) return;
				var body = new URLSearchParams();
				body.append( 'action', 'emcp_tools_dismiss_elementor_notice' );
				body.append( 'nonce', notice.getAttribute( 'data-emcp-nonce' ) || '' );
				fetch( ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString(),
				} );
			} );
		} )();
		</script>
		<?php
	}

	public function ajax_dismiss(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		update_user_meta( get_current_user_id(), self::META_KEY, time() );
		wp_send_json_success();
	}
}
