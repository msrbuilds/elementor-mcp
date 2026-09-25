<?php
/**
 * Legacy views, rendered inside the frame's .emcp-legacy wrapper until each
 * screen is ported. Included by page-shell.php with $this and $active_tab in scope.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Every variable the old shell defined; legacy views (page-brand-kits.php and
// others) still read some of them.
$emcp_tools_show_upgrade = ! function_exists( 'emcp_tools_fs' ) || ! emcp_tools_fs()->can_use_premium_code();
$emcp_notifs             = class_exists( 'EMCP_Tools_Notifications' ) ? EMCP_Tools_Notifications::get() : array();
$emcp_uid                = get_current_user_id();
$emcp_unread             = class_exists( 'EMCP_Tools_Notifications' ) ? EMCP_Tools_Notifications::unread_count( $emcp_uid ) : 0;
$emcp_seen               = (array) get_user_meta( $emcp_uid, '_emcp_tools_read_notifications', true );
$emcp_cloud_connected    = class_exists( 'EMCP_Tools_Cloud' ) && EMCP_Tools_Cloud::is_connected();

// Success notice after a Settings API save (options.php redirects back
// with settings-updated=true). Shown for any EMCP settings tab.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- options.php verifies the settings nonce before redirecting.
if ( isset( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) :
	// Rendered as the finished toast rather than as a notice the script then
	// moves, so the first paint is already bottom-right and admin.js only adds
	// the dismissing. `inline` is load-bearing: on jQuery ready core relocates
	// every div.notice that is not .inline to the page header.
	?>
	<div class="emcp-toasts" aria-live="polite">
		<div class="emcp-toast emcp-toast--success" role="status">
			<div class="notice notice-success inline emcp-saved-notice emcp-toast__notice">
				<p><strong><?php esc_html_e( 'Settings saved.', 'emcp-tools' ); ?></strong></p>
			</div>
			<button type="button" class="emcp-toast__close" aria-label="<?php esc_attr_e( 'Dismiss', 'emcp-tools' ); ?>">&times;</button>
		</div>
	</div>
	<?php
endif;
?>
<div class="tab-content<?php echo 'dashboard' === $active_tab ? ' tab-content--flush' : ''; ?>">
	<?php
	if ( 'dashboard' === $active_tab ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-dashboard.php';
	} elseif ( 'page-builders' === $active_tab ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-builders.php';
	} elseif ( 'modules' === $active_tab ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-modules.php';
	} elseif ( 'connection' === $active_tab ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-connection.php';
	} elseif ( 'ai-chat' === $active_tab && $this->ai_chat_tab_visible() ) {
		$emcp_pro_view = EMCP_Tools_Pro_Loader::path( 'includes/admin/views/page-ai-chat.php' );
		if ( '' !== $emcp_pro_view ) {
			include $emcp_pro_view;
		} else {
			include EMCP_TOOLS_DIR . 'includes/admin/views/page-ai-chat-upsell.php';
		}
	} elseif ( 'context' === $active_tab ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-context.php';
	} elseif ( 'memory' === $active_tab && $this->memory_tab_visible() ) {
		$emcp_mem_view = EMCP_Tools_Pro_Loader::path( 'includes/admin/views/page-memory.php' );
		if ( '' !== $emcp_mem_view ) {
			include $emcp_mem_view;
		}
	} elseif ( 'prompts' === $active_tab && $this->module_tab_visible( 'prompts' ) ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-prompts.php';
	} elseif ( 'templates' === $active_tab && $this->module_tab_visible( 'templates' ) ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-templates.php';
	} elseif ( 'brand-kits' === $active_tab && $this->module_tab_visible( 'brand-kits' ) ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-brand-kits.php';
	} elseif ( 'skills' === $active_tab ) {
		$emcp_pro_view = EMCP_Tools_Pro_Loader::path( 'includes/admin/views/page-skills.php' );
		if ( '' !== $emcp_pro_view ) {
			include $emcp_pro_view;
		} else {
			$emcp_upsell_feature = __( 'Skills', 'emcp-tools' );
			include EMCP_TOOLS_DIR . 'includes/admin/views/page-pro-upsell.php';
		}
	} elseif ( 'history' === $active_tab ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-history.php';
	} elseif ( 'redirects' === $active_tab && $this->module_tab_visible( 'redirects' ) ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-redirects.php';
	} elseif ( 'migrate' === $active_tab && $this->module_tab_visible( 'migrate' ) ) {
		$emcp_migrate_view = EMCP_Tools_Pro_Loader::path( 'includes/admin/views/page-migrate.php' );
		if ( '' !== $emcp_migrate_view ) {
			include $emcp_migrate_view;
		}
	} elseif ( 'widgets' === $active_tab ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-widgets.php';
	} elseif ( 'marketplace' === $active_tab ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-marketplace.php';
	} elseif ( 'mcp-log' === $active_tab ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-mcp-log.php';
	} elseif ( 'changelog' === $active_tab ) {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-changelog.php';
	} else {
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-tools.php';
	}
	?>
</div>
