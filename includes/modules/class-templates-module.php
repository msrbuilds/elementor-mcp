<?php
/**
 * Templates as a Pro module.
 *
 * A tab-only feature: the module gates the Templates admin tab (and its stat
 * card). Pro tier — free users see a locked card in Modules and the standalone
 * tab is hidden. register() is a no-op — the admin class reads the module's
 * active + available state to show/hide the tab. On by default. This class is
 * metadata only (no Pro logic), so it lives safely in the free tree.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Templates module.
 *
 * @since 3.1.0
 */
class EMCP_Tools_Templates_Module extends EMCP_Tools_Module {

	public function id(): string {
		return 'templates';
	}

	public function title(): string {
		return __( 'Templates', 'emcp-tools' );
	}

	public function description(): string {
		return __( 'Ready-to-apply Elementor page templates, one-click creates a full page you edit visually.', 'emcp-tools' );
	}

	public function tier(): string {
		return 'pro';
	}

	/** Modules screen group. */
	public function group(): string {
		return 'content';
	}

	/** Modules screen icon. */
	public function icon(): string {
		return 'layout-grid';
	}

	public function default_active(): bool {
		return true;
	}

	/** Requires an active Pro license. */
	public function is_available(): bool {
		return function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code();
	}

	/** The dedicated Templates admin tab is the config surface. */
	public function settings_url(): string {
		return admin_url( 'admin.php?page=' . EMCP_Tools_Admin::PAGE_SLUG . '-templates' );
	}

	/** No overlay settings; the feature lives on its admin tab. */
	public function render_settings(): void {}

	/** Tab-only feature — the admin class gates the tab; nothing to wire here. */
	public function register(): void {}
}
