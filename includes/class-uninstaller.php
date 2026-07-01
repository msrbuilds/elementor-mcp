<?php
/**
 * Uninstall cleanup.
 *
 * Wired to Freemius's `after_uninstall` action from the bootstrap file. Removes
 * plugin-owned options/transients/user-meta and, critically, the generated
 * executable PHP (custom widgets + PHP snippets) which must never survive an
 * uninstall.
 *
 * @package EMCP_Tools
 * @since   2.1.0 (extracted from emcp_tools_after_uninstall, since 1.6.1)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes plugin-owned data on uninstall.
 *
 * @since 2.1.0
 */
class EMCP_Tools_Uninstaller {

	/**
	 * Runs the uninstall cleanup.
	 *
	 * @since 2.1.0
	 */
	public static function run(): void {
		delete_option( 'emcp_tools_disabled_tools' );
		delete_option( 'emcp_tools_low_tool_mode' );
		delete_option( 'emcp_tools_defaults_applied' );
		delete_transient( 'emcp_tools_pro_prompts_bundle' );
		delete_transient( 'emcp_tools_pro_templates_bundle' );
		delete_transient( 'emcp_tools_pro_brand_kits_bundle' );
		// Drop the dismissal flags from every user.
		delete_metadata( 'user', 0, 'emcp_tools_upgrade_notice_dismissed', '', true );
		delete_metadata( 'user', 0, 'emcp_tools_community_notice_dismissed', '', true );
		// Brand-kit backups (emcp_kit_backup CPT) are intentionally LEFT in place
		// on uninstall — treated as recoverable user content so a user who removes
		// the plugin can still roll back their pre-kit brand after reinstalling.

		// Widget Builder: generated executable PHP must NOT survive uninstall —
		// delete every emcp_widget post and remove the uploads sandbox tree.
		if ( ! class_exists( 'EMCP_Tools_Widget_Store' ) ) {
			require_once EMCP_TOOLS_DIR . 'includes/class-widget-store.php';
		}
		if ( class_exists( 'EMCP_Tools_Widget_Store' ) ) {
			EMCP_Tools_Widget_Store::uninstall_cleanup();
		}

		// PHP Snippets: generated executable PHP must NOT survive uninstall either.
		if ( ! class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) ) {
			require_once EMCP_TOOLS_DIR . 'includes/class-php-snippet-store.php';
		}
		if ( class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) ) {
			EMCP_Tools_PHP_Snippet_Store::uninstall_cleanup();
		}

		// AI Chat: saved conversations + per-user API keys + cached model lists.
		// The store class ships in the private Pro overlay; on a free install the
		// file is absent, so resolve it defensively (dual-root) instead of a hard
		// require that would fatal uninstall. The option/meta deletes below still
		// clean up regardless.
		if ( ! class_exists( 'EMCP_Tools_AI_Chat_Store' ) && class_exists( 'EMCP_Tools_Pro_Loader' ) ) {
			$emcp_store = EMCP_Tools_Pro_Loader::path( 'includes/ai-chat/class-ai-chat-store.php' );
			if ( '' !== $emcp_store ) {
				require_once $emcp_store;
			}
		}
		if ( class_exists( 'EMCP_Tools_AI_Chat_Store' ) ) {
			EMCP_Tools_AI_Chat_Store::uninstall_cleanup();
		}
		delete_option( 'emcp_tools_ai_models' );
		delete_metadata( 'user', 0, 'emcp_tools_ai_keys', '', true );
		delete_metadata( 'user', 0, 'emcp_tools_ai_defaults', '', true );
	}
}
