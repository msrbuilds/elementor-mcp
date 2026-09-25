<?php
/**
 * Prompts as a module.
 *
 * A tab-only feature: the module gates the Prompts admin tab (and its stat
 * card); the free/Pro content inside the tab is unchanged. register() is a
 * no-op — the admin class reads the module's active state to show/hide the tab.
 * Free tier; on by default.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prompts module.
 *
 * @since 3.1.0
 */
class EMCP_Tools_Prompts_Module extends EMCP_Tools_Module {

	public function id(): string {
		return 'prompts';
	}

	public function title(): string {
		return __( 'Prompts', 'emcp-tools' );
	}

	public function description(): string {
		return __( 'Ready-to-use AI prompt blueprints for building pages, bundled samples plus the premium library.', 'emcp-tools' );
	}

	public function tier(): string {
		return 'free';
	}

	/** Modules screen group. */
	public function group(): string {
		return 'content';
	}

	/** Modules screen icon. */
	public function icon(): string {
		return 'lightbulb';
	}

	public function default_active(): bool {
		return true;
	}

	/** The dedicated Prompts admin tab is the config surface. */
	public function settings_url(): string {
		return admin_url( 'admin.php?page=' . EMCP_Tools_Admin::PAGE_SLUG . '-prompts' );
	}

	/** No overlay settings; the feature lives on its admin tab. */
	public function render_settings(): void {}

	/** Tab-only feature — the admin class gates the tab; nothing to wire here. */
	public function register(): void {}
}
