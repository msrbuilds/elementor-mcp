<?php
/**
 * Uninstall cleanup.
 *
 * Wired to Freemius's `after_uninstall` action from the bootstrap file. Removes
 * plugin-owned options/transients/user-meta, the generated executable PHP
 * (custom widgets + PHP snippets) which must never survive an uninstall, and
 * the OAuth tables, whose registered clients and issued tokens are live
 * credentials that must not survive one either, and then everything else EMCP
 * keeps in the database (see remove_remaining_data()).
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
		delete_option( 'emcp_tools_cloud_connection' );
		delete_option( 'emcp_tools_site_uuid' );
		delete_option( 'emcp_tools_cloud_base_url' );
		delete_transient( 'emcp_tools_cloud_pending' );
		delete_transient( 'emcp_tools_pro_prompts_bundle' );
		delete_transient( 'emcp_tools_pro_templates_bundle' );
		delete_transient( 'emcp_tools_pro_brand_kits_bundle' );
		// Drop the dismissal flags from every user.
		delete_metadata( 'user', 0, 'emcp_tools_upgrade_notice_dismissed', '', true );
		delete_metadata( 'user', 0, 'emcp_tools_community_notice_dismissed', '', true );
		// Brand-kit backups (emcp_kit_backup CPT) are intentionally LEFT in place
		// on uninstall — treated as recoverable user content so a user who removes
		// the plugin can still roll back their pre-kit brand after reinstalling.

		// The bootstrap does not run during an uninstall, so load what the
		// sandbox stores below need (widgets, snippets and the Pro block store,
		// which extends the shared sandbox store), in the bootstrap's order.
		foreach ( array( 'class-sandbox-paths.php', 'interface-sandbox-artifact.php', 'class-sandbox-store.php' ) as $emcp_file ) {
			require_once EMCP_TOOLS_DIR . 'includes/sandbox/' . $emcp_file;
		}
		// The Pro steps below find their files through the Pro loader, which the
		// bootstrap normally loads; without it they were skipped on Pro installs.
		if ( ! class_exists( 'EMCP_Tools_Pro_Loader' ) ) {
			require_once EMCP_TOOLS_DIR . 'includes/class-pro-loader.php';
		}

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

		// OAuth: registered clients and issued tokens are live credentials,
		// they must not survive uninstall either.
		if ( ! class_exists( 'EMCP_Tools_OAuth_Store' ) ) {
			require_once EMCP_TOOLS_DIR . 'includes/oauth/class-oauth-store.php';
		}
		if ( class_exists( 'EMCP_Tools_OAuth_Store' ) ) {
			EMCP_Tools_OAuth_Store::uninstall_cleanup();
		}

		// Block Builder: generated block source + registry must NOT survive
		// uninstall either. The store class ships in the private Pro overlay; on
		// a free install the file is absent, so resolve it defensively
		// (dual-root, same pattern as AI Chat below) instead of a hard require
		// that would fatal uninstall.
		if ( ! class_exists( 'EMCP_Tools_Block_Store' ) && class_exists( 'EMCP_Tools_Pro_Loader' ) ) {
			$emcp_block_store = EMCP_Tools_Pro_Loader::path( 'includes/class-block-store.php' );
			if ( '' !== $emcp_block_store ) {
				require_once $emcp_block_store;
			}
		}
		if ( class_exists( 'EMCP_Tools_Block_Store' ) ) {
			EMCP_Tools_Block_Store::uninstall_cleanup();
		}

		// Project Memory: guidance CPT + session/injection options + the rollup cron.
		if ( ! class_exists( 'EMCP_Tools_Memory_Store' ) && class_exists( 'EMCP_Tools_Pro_Loader' ) ) {
			$emcp_memory_store = EMCP_Tools_Pro_Loader::path( 'includes/memory/class-memory-store.php' );
			if ( '' !== $emcp_memory_store ) {
				require_once $emcp_memory_store;
			}
		}
		if ( class_exists( 'EMCP_Tools_Memory_Store' ) ) {
			EMCP_Tools_Memory_Store::uninstall_cleanup();
		}
		if ( ! class_exists( 'EMCP_Tools_Memory_Summarizer' ) && class_exists( 'EMCP_Tools_Pro_Loader' ) ) {
			$emcp_memory_sz = EMCP_Tools_Pro_Loader::path( 'includes/memory/class-memory-summarizer.php' );
			if ( '' !== $emcp_memory_sz ) {
				require_once $emcp_memory_sz;
			}
		}
		if ( class_exists( 'EMCP_Tools_Memory_Summarizer' ) ) {
			( new EMCP_Tools_Memory_Summarizer() )->unschedule();
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

		self::remove_remaining_data();
	}

	/**
	 * Removes everything else EMCP keeps in the database, after the stores above
	 * have run: its own tables, every emcp_tools_* option, transient and user
	 * meta row, the config-deployment journals, the builder lock options, and
	 * every emcp_tools_* cron event. User content stays: pages, EMCP Themer
	 * templates, Brand Kit backups and backup archives on disk.
	 *
	 * @since 3.19.1
	 */
	private static function remove_remaining_data(): void {
		global $wpdb;

		// History, redirects, search index and Backup & Migrate (its paired
		// targets hold connector secrets). OAuth's tables went with its store.
		foreach ( array( 'emcp_changes', 'emcp_change_blobs', 'emcp_redirects', 'emcp_search_index', 'emcp_migrate_backups', 'emcp_migrate_jobs', 'emcp_migrate_targets' ) as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $table ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- fixed plugin table names.
		}

		foreach ( array( 'emcp_tools_', '_transient_emcp_tools_', '_transient_timeout_emcp_tools_', '_site_transient_emcp_tools_', '_site_transient_timeout_emcp_tools_', 'emcp_config_operation_', '_transient_emcp_themer_php_notice_', '_transient_timeout_emcp_themer_php_notice_' ) as $prefix ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( array( 'emcp_blocksy_theme_lock', 'emcp_divi_recovery', 'emcp_divi_settings_lock', 'emcp_divi_theme_builder_lock', 'emcp_otter_settings_lock', 'emcp_oxygen_design_revisions', 'emcp_oxygen_import_lock' ) as $option ) {
			delete_option( $option );
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( 'emcp_tools_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$hooks = array();
		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( array_keys( (array) $events ) as $hook ) {
				if ( is_string( $hook ) && 0 === strpos( $hook, 'emcp_tools_' ) ) {
					$hooks[ $hook ] = true;
				}
			}
		}
		foreach ( array_keys( $hooks ) as $hook ) {
			wp_unschedule_hook( $hook );
		}
	}
}
