<?php
/**
 * Admin settings page for MCP Tools for Elementor.
 *
 * Provides a UI to toggle individual MCP tools on/off and view
 * connection information for various MCP clients.
 *
 * @package EMCP_Tools
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Keep direct includes (including the test harness and Pro overlay) self-contained.
require_once __DIR__ . '/trait-admin-history.php';
require_once __DIR__ . '/trait-admin-redirects.php';
require_once __DIR__ . '/trait-admin-cloud.php';
require_once __DIR__ . '/trait-admin-connection.php';
require_once __DIR__ . '/trait-admin-settings.php';
require_once __DIR__ . '/trait-admin-sandbox.php';
require_once __DIR__ . '/trait-admin-tool-groups.php';
require_once __DIR__ . '/trait-admin-integrations.php';
require_once __DIR__ . '/trait-admin-catalog.php';

/**
 * Admin page orchestrator.
 *
 * @since 1.0.0
 */
class EMCP_Tools_Admin {

	use EMCP_Tools_Admin_History_Trait;
	use EMCP_Tools_Admin_Redirects_Trait;
	use EMCP_Tools_Admin_Cloud_Trait;
	use EMCP_Tools_Admin_Connection_Trait;
	use EMCP_Tools_Admin_Settings_Trait;
	use EMCP_Tools_Admin_Sandbox_Trait;
	use EMCP_Tools_Admin_Tool_Groups_Trait;
	use EMCP_Tools_Admin_Integrations_Trait;
	use EMCP_Tools_Admin_Catalog_Trait;

	/**
	 * Hook suffixes returned by add_menu_page() / add_submenu_page(),
	 * used to scope asset enqueues to our screens only.
	 *
	 * @var string[]
	 */
	private $hook_suffixes = array();

	/**
	 * Option name for storing disabled tools.
	 *
	 * @var string
	 */
	const OPTION_DISABLED_TOOLS = 'emcp_tools_disabled_tools';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'emcp_tools_settings';

	/**
	 * Dedicated settings group for the "Activate Abilities API for EMCP" server
	 * gate. Kept separate from SETTINGS_GROUP so the Connection-tab toggle form
	 * submits only that option and can't wipe the Tools-page options on save.
	 *
	 * @since 1.7.4
	 * @var string
	 */
	const SETTINGS_GROUP_SERVER = 'emcp_tools_server_settings';

	/** Settings group for the Context page. */
	const SETTINGS_GROUP_CONTEXT = 'emcp_tools_context_settings';

	/** Settings group for the Modules tab (active-modules list + each module's knobs). */
	const SETTINGS_GROUP_MODULES = 'emcp_tools_modules_settings';

	/**
	 * Settings group for third-party service credentials (stock-image provider
	 * keys). Separate from SETTINGS_GROUP_SERVER so the "3rd Party Services"
	 * sub-tab form saves independently of the server-gate toggles.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP_SERVICES = 'emcp_tools_services_settings';

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'emcp-tools';

	/**
	 * Map of sub-screen slug => label. The first entry is the dashboard
	 * (rendered when the parent menu item is clicked).
	 *
	 * @var array<string, string>|null
	 */
	private $submenus = null;

	/**
	 * Returns the map of submenu slugs to translated labels.
	 *
	 * Initialised lazily so the strings are localised at call time.
	 *
	 * @return array<string, string>
	 */
	/**
	 * Whether a module-backed admin tab should show. Visible when the module is
	 * not registered (free build / no overlay → keep the tab, e.g. an upsell) or
	 * it is active and available; hidden when registered but off or unavailable.
	 *
	 * @param string $module_id Module id.
	 * @return bool
	 */
	private function module_tab_visible( string $module_id ): bool {
		// Templates and Brand Kits follow their module switch only, never the
		// selected page builder; each page explains itself when Elementor is off.
		if ( ! class_exists( 'EMCP_Tools_Modules_Registry' ) ) {
			return true;
		}
		$module = EMCP_Tools_Modules_Registry::instance()->get( $module_id );
		if ( ! $module ) {
			return true;
		}
		return $module->is_active() && $module->is_available();
	}

	/**
	 * Whether the AI Chat submenu tab should show.
	 *
	 * @return bool
	 */
	private function ai_chat_tab_visible(): bool {
		return $this->module_tab_visible( 'ai-chat' );
	}

	/**
	 * Whether the Project Memory submenu tab should show (module active + Pro).
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	public function memory_tab_visible(): bool {
		return $this->module_tab_visible( 'memory' );
	}

	/**
	 * Number of agent-proposed project-memory entries awaiting review (0 when the
	 * Memory tab is hidden or the store is unavailable). Surfaced as a count badge
	 * on the Memory submenu + in-page nav so pending proposals aren't forgotten.
	 *
	 * @return int
	 */
	public function memory_pending_count(): int {
		if ( ! $this->memory_tab_visible() || ! class_exists( 'EMCP_Tools_Memory_Store' ) ) {
			return 0;
		}
		return EMCP_Tools_Memory_Store::instance()->pending_count();
	}

	/**
	 * Dashicon class for a tab id, used by the in-header nav. Falls back to a
	 * generic marker for unknown ids.
	 *
	 * @param string $tab_id Tab id as returned by get_active_tab().
	 * @return string Dashicon class.
	 */
	public static function tab_icon( string $tab_id ): string {
		$icons = array(
			'dashboard'  => 'dashicons-dashboard',
			'tools'      => 'dashicons-admin-tools',
			'page-builders' => 'dashicons-layout',
			'history'    => 'dashicons-undo',
			'redirects'  => 'dashicons-randomize',
			'migrate'    => 'dashicons-migrate',
			'modules'    => 'dashicons-screenoptions',
			'connection' => 'dashicons-admin-links',
			'ai-chat'    => 'dashicons-format-chat',
			'context'    => 'dashicons-info-outline',
			'memory'     => 'dashicons-database',
			'prompts'    => 'dashicons-lightbulb',
			'templates'  => 'dashicons-layout',
			'brand-kits' => 'dashicons-art',
			'skills'     => 'dashicons-superhero',
			'widgets'    => 'dashicons-editor-code',
			'mcp-log'    => 'dashicons-list-view',
			'changelog'  => 'dashicons-backup',
		);
		return $icons[ $tab_id ] ?? 'dashicons-marker';
	}

	private function get_submenus(): array {
		if ( null === $this->submenus ) {
			$this->submenus = array(
				self::PAGE_SLUG                 => __( 'Dashboard', 'emcp-tools' ),
				self::PAGE_SLUG . '-modules'    => __( 'Modules', 'emcp-tools' ),
				self::PAGE_SLUG . '-page-builders' => __( 'Page Builders', 'emcp-tools' ),
				self::PAGE_SLUG . '-tools'      => __( 'Tools', 'emcp-tools' ),
				self::PAGE_SLUG . '-connection' => __( 'Connection', 'emcp-tools' ),
				self::PAGE_SLUG . '-ai-chat'    => __( 'AI Chat', 'emcp-tools' ),
				self::PAGE_SLUG . '-context'    => __( 'Context', 'emcp-tools' ),
				self::PAGE_SLUG . '-redirects'  => __( 'Redirects', 'emcp-tools' ),
				self::PAGE_SLUG . '-migrate'    => __( 'Backup & Migrate', 'emcp-tools' ),
				self::PAGE_SLUG . '-memory'     => __( 'Memory', 'emcp-tools' ),
				self::PAGE_SLUG . '-prompts'    => __( 'Prompts', 'emcp-tools' ),
				self::PAGE_SLUG . '-templates'  => __( 'Templates', 'emcp-tools' ),
				self::PAGE_SLUG . '-brand-kits' => __( 'Brand Kits', 'emcp-tools' ),
				self::PAGE_SLUG . '-skills'     => __( 'Skills', 'emcp-tools' ),
				self::PAGE_SLUG . '-widgets'    => __( 'Sandbox', 'emcp-tools' ),
				self::PAGE_SLUG . '-marketplace' => __( 'Marketplace', 'emcp-tools' ),
				self::PAGE_SLUG . '-mcp-log'    => __( 'MCP Log', 'emcp-tools' ),
				self::PAGE_SLUG . '-history'    => __( 'History', 'emcp-tools' ),
				self::PAGE_SLUG . '-changelog'  => __( 'Changelog', 'emcp-tools' ),
			);
			if ( ! $this->ai_chat_tab_visible() ) {
				unset( $this->submenus[ self::PAGE_SLUG . '-ai-chat' ] );
			}
			// Marketplace is a Cloud feature — drop the tab when the Cloud module is off.
			if ( ! ( class_exists( 'EMCP_Tools_Cloud_Module' ) && EMCP_Tools_Cloud_Module::is_enabled() ) ) {
				unset( $this->submenus[ self::PAGE_SLUG . '-marketplace' ] );
			}
			if ( ! $this->memory_tab_visible() ) {
				unset( $this->submenus[ self::PAGE_SLUG . '-memory' ] );
			}
			// Redirects tab is gated by the Redirect Manager module.
			if ( ! $this->module_tab_visible( 'redirects' ) ) {
				unset( $this->submenus[ self::PAGE_SLUG . '-redirects' ] );
			}
			// Backup & Migrate tab is gated by the Migrate (Pro) module.
			if ( ! $this->module_tab_visible( 'migrate' ) ) {
				unset( $this->submenus[ self::PAGE_SLUG . '-migrate' ] );
			}
			// Module-backed tabs: drop each when its module is off/unavailable.
			foreach ( array( 'prompts', 'templates', 'brand-kits' ) as $emcp_mod_id ) {
				if ( ! $this->module_tab_visible( $emcp_mod_id ) ) {
					unset( $this->submenus[ self::PAGE_SLUG . '-' . $emcp_mod_id ] );
				}
			}
		}
		return $this->submenus;
	}

	/**
	 * Determine which sub-screen is active from $_GET['page'].
	 *
	 * @return string One of 'tools', 'connection', 'prompts', 'changelog'.
	 */
	private function get_active_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		switch ( $page ) {
			case self::PAGE_SLUG . '-page-builders':
				return 'page-builders';
			case self::PAGE_SLUG . '-tools':
				return 'tools';
			case self::PAGE_SLUG . '-history':
				return 'history';
			case self::PAGE_SLUG . '-redirects':
				return 'redirects';
			case self::PAGE_SLUG . '-migrate':
				return 'migrate';
			case self::PAGE_SLUG . '-modules':
				return 'modules';
			case self::PAGE_SLUG . '-connection':
				return 'connection';
			case self::PAGE_SLUG . '-ai-chat':
				return 'ai-chat';
			case self::PAGE_SLUG . '-context':
				return 'context';
			case self::PAGE_SLUG . '-memory':
				return 'memory';
			case self::PAGE_SLUG . '-prompts':
				return 'prompts';
			case self::PAGE_SLUG . '-templates':
				return 'templates';
			case self::PAGE_SLUG . '-brand-kits':
				return 'brand-kits';
			case self::PAGE_SLUG . '-skills':
				return 'skills';
			case self::PAGE_SLUG . '-widgets':
				return 'widgets';
			case self::PAGE_SLUG . '-marketplace':
				return 'marketplace';
			case self::PAGE_SLUG . '-mcp-log':
				return 'mcp-log';
			case self::PAGE_SLUG . '-changelog':
				return 'changelog';
			default:
				return 'dashboard';
		}
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_apply_default_disabled_tools' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		EMCP_Tools_Admin_Locked::register_screen();
		EMCP_Tools_Admin_REST_Tools::register_screen( $this );
		EMCP_Tools_Admin_REST_Builders::register_screen();
		EMCP_Tools_Admin_REST_Modules::register_screen();
		EMCP_Tools_Admin_REST_Connection::register_screen( $this );
		EMCP_Tools_Admin_REST_Prompts::register_screen();
		EMCP_Tools_Admin_REST_Brand_Kits::register_screen();
		add_action( 'admin_head', array( $this, 'print_menu_icon_style' ) );
		add_action( 'wp_ajax_emcp_tools_create_app_password', array( $this, 'ajax_create_app_password' ) );
		add_action( 'wp_ajax_emcp_tools_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_emcp_tools_test_oauth_discovery', array( $this, 'ajax_test_oauth_discovery' ) );
		add_action( 'wp_ajax_emcp_tools_toggle_widget', array( $this, 'ajax_toggle_widget' ) );
		add_action( 'wp_ajax_emcp_tools_delete_widget', array( $this, 'ajax_delete_widget' ) );
		add_action( 'wp_ajax_emcp_tools_toggle_block', array( $this, 'ajax_toggle_block' ) );
		add_action( 'wp_ajax_emcp_tools_delete_block', array( $this, 'ajax_delete_block' ) );
		add_action( 'wp_ajax_emcp_tools_backup_artifact', array( $this, 'ajax_backup_artifact' ) );
		add_action( 'wp_ajax_emcp_tools_bulk_backup_artifacts', array( $this, 'ajax_bulk_backup_artifacts' ) );
		add_action( 'wp_ajax_emcp_tools_push_update', array( $this, 'ajax_push_update' ) );
		add_action( 'wp_ajax_emcp_tools_marketplace_state', array( $this, 'ajax_marketplace_state' ) );
		add_action( 'wp_ajax_emcp_tools_resync_cloud', array( $this, 'ajax_resync_cloud' ) );
		add_action( 'wp_ajax_emcp_tools_cloud_library', array( $this, 'ajax_cloud_library' ) );
		add_action( 'wp_ajax_emcp_tools_cloud_import', array( $this, 'ajax_cloud_import' ) );
		add_action( 'wp_ajax_emcp_tools_memory_set_status', array( $this, 'ajax_memory_set_status' ) );
		add_action( 'wp_ajax_emcp_tools_memory_save_guidance', array( $this, 'ajax_memory_save_guidance' ) );
		add_action( 'wp_ajax_emcp_tools_memory_save_settings', array( $this, 'ajax_memory_save_settings' ) );
		add_action( 'wp_ajax_emcp_tools_save_php_snippet', array( $this, 'ajax_save_php_snippet' ) );
		add_action( 'wp_ajax_emcp_tools_toggle_php_snippet', array( $this, 'ajax_toggle_php_snippet' ) );
		add_action( 'wp_ajax_emcp_tools_delete_php_snippet', array( $this, 'ajax_delete_php_snippet' ) );
		add_action( 'wp_ajax_emcp_tools_notifications_read', array( $this, 'ajax_notifications_read' ) );
		add_action( 'admin_post_emcp_tools_download_mcpb', array( $this, 'handle_download_mcpb' ) );
		add_action( 'admin_post_' . self::ACTION_DISMISS_PROMPTS_NOTICE, array( $this, 'handle_dismiss_prompts_notice' ) );
		add_action( 'admin_post_' . self::ACTION_ROLLBACK_CHANGE, array( $this, 'handle_rollback_change' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE_CHANGE, array( $this, 'handle_delete_change' ) );
		add_action( 'admin_post_' . self::ACTION_CLEAR_CHANGES, array( $this, 'handle_clear_changes' ) );
		add_action( 'admin_post_' . self::ACTION_REVOKE_OAUTH, array( $this, 'handle_revoke_oauth_client' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE_OAUTH_CLIENT, array( $this, 'handle_delete_oauth_client' ) );
		add_action( 'admin_post_' . self::ACTION_EXPORT_ARTIFACT, array( $this, 'handle_export_artifact' ) );
		add_action( 'admin_post_' . self::ACTION_IMPORT_ARTIFACT, array( $this, 'handle_import_artifact' ) );
		add_action( 'admin_post_emcp_tools_settings_push', array( $this, 'handle_settings_push' ) );
		add_action( 'admin_post_emcp_tools_settings_pull', array( $this, 'handle_settings_pull' ) );
		add_action( 'admin_post_emcp_tools_marketplace_install', array( $this, 'handle_marketplace_install' ) );
		add_action( 'admin_post_emcp_tools_redirect_save', array( $this, 'handle_redirect_save' ) );
		add_action( 'admin_post_emcp_tools_redirect_delete', array( $this, 'handle_redirect_delete' ) );
		add_action( 'admin_post_emcp_tools_redirect_toggle', array( $this, 'handle_redirect_toggle' ) );
	}

	/** Nonce action for the .mcpb bundle download. */
	const NONCE_DOWNLOAD_MCPB = 'emcp_tools_download_mcpb';

	/** admin-post action that dismisses the "prompts rewritten" notice. */
	const ACTION_DISMISS_PROMPTS_NOTICE = 'emcp_tools_dismiss_prompts_notice';

	/** admin-post action that rolls back a change from the History tab. */
	const ACTION_ROLLBACK_CHANGE = 'emcp_tools_rollback_change';

	/** admin-post action that deletes one entry from the History ledger. */
	const ACTION_DELETE_CHANGE = 'emcp_tools_delete_change';

	/** admin-post action that clears the whole History ledger. */
	const ACTION_CLEAR_CHANGES = 'emcp_tools_clear_changes';

	/** Nonce action shared by the sandbox artifact export/import admin-post handlers. */
	const NONCE_SANDBOX_BUNDLE = 'emcp_tools_sandbox_bundle';

	/** admin-post action that streams a sandbox artifact as a portable JSON bundle download. */
	const ACTION_EXPORT_ARTIFACT = 'emcp_tools_export_artifact';

	/** admin-post action that imports an uploaded sandbox artifact bundle. */
	const ACTION_IMPORT_ARTIFACT = 'emcp_tools_import_artifact';

	/**
	 * admin-post action: revoke all tokens for one OAuth client.
	 *
	 * @var string
	 */
	const ACTION_REVOKE_OAUTH = 'emcp_tools_revoke_oauth_client';

	/**
	 * Delete an OAuth client registration outright (tokens included).
	 *
	 * Distinct from ACTION_REVOKE_OAUTH, which signs an app out but keeps the
	 * registration so it can sign back in. This one is for a registration that
	 * can never be used again, typically because the app now asks for a
	 * different callback than the one it registered.
	 *
	 * @since 3.15.0
	 */
	const ACTION_DELETE_OAUTH_CLIENT = 'emcp_tools_delete_oauth_client';

	/**
	 * User meta flag recording that the current user has dismissed the notice
	 * announcing the rewritten (v2) prompt library. Per-user, not per-site, so
	 * one administrator dismissing it does not hide it from the others.
	 *
	 * Suffixed with the library generation: a future rewrite bumps the key and
	 * the notice surfaces again rather than staying permanently dismissed.
	 *
	 * @since 3.2.0
	 */
	const META_PROMPTS_NOTICE_DISMISSED = 'emcp_tools_prompts_v2_notice_dismissed';

	/**
	 * Whether the current user has dismissed the rewritten-prompts notice.
	 *
	 * @since 3.2.0
	 * @return bool
	 */
	public static function prompts_notice_dismissed(): bool {
		return (bool) get_user_meta( get_current_user_id(), self::META_PROMPTS_NOTICE_DISMISSED, true );
	}

	/**
	 * Nonce-protected URL that dismisses the rewritten-prompts notice.
	 *
	 * @since 3.2.0
	 * @return string
	 */
	public static function prompts_notice_dismiss_url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION_DISMISS_PROMPTS_NOTICE ),
			self::ACTION_DISMISS_PROMPTS_NOTICE
		);
	}

	/**
	 * Persist the dismissal, then bounce back to the Prompts screen.
	 *
	 * @since 3.2.0
	 */
	public function handle_dismiss_prompts_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION_DISMISS_PROMPTS_NOTICE );

		update_user_meta( get_current_user_id(), self::META_PROMPTS_NOTICE_DISMISSED, '1' );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-prompts' ) );
		exit;
	}

	/**
	 * Option that records which version of the default disabled-tools seeding
	 * has been applied. Stored as an integer-ish string: legacy '1' = the
	 * original Pro-widget defaults; '2' adds the SEO/A11y Pro MCP tools.
	 */
	const OPTION_DEFAULTS_APPLIED = 'emcp_tools_defaults_applied';

	/**
	 * Current defaults-seeding version. Bump when a new batch of slugs should
	 * ship disabled-by-default; add a guarded step in
	 * maybe_apply_default_disabled_tools() for the new version.
	 *
	 * @since 1.8.0
	 */
	const DEFAULTS_VERSION = 53;

	/**
	 * Themer PHP-template tool slugs. The whole feature is gated behind a master
	 * switch (off by default), and even once enabled these 5 tools ship
	 * disabled-by-default like the PHP Snippets — the admin opts in on the Tools tab.
	 *
	 * @since 3.1.0
	 *
	 * @return string[]
	 */
	/**
	 * The EMCP Themer tool slugs.
	 *
	 * Module-gated: they only register while the Themer module is active, so the
	 * drift guard has to treat their absence as expected rather than as a
	 * renamed or removed tool.
	 *
	 * @since 3.13.0
	 * @return string[]
	 */

	/**
	 * Seeds default disabled-tools on install/upgrade so new Pro tool batches
	 * ship off-by-default (keeping sites under client tool caps), then records
	 * the applied version. Each version step adds ONLY its newly-introduced
	 * slugs, so prior user enable/disable choices are preserved (union merge).
	 *
	 * @since 1.6.0
	 */
	/**
	 * The Themes-domain dispatcher slugs (Active Theme + framework packs). Both
	 * write dispatchers ship disabled-by-default; the per-framework packs are
	 * env-gated (register only when that framework is active). Excluded from the
	 * F-019 drift guard for that reason.
	 *
	 * @since 3.4.0
	 * @return string[]
	 */

	/**
	 * Add the settings page under the Settings menu.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page(): void {
		$this->hook_suffixes[] = add_menu_page(
			__( 'MCP Tools for Elementor', 'emcp-tools' ),
			__( 'EMCP Tools', 'emcp-tools' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			EMCP_TOOLS_URL . 'assets/img/icon-xs.png',
			58
		);

		foreach ( $this->get_submenus() as $slug => $label ) {
			$menu_title = $label;
			// Native WordPress count bubble on the Memory submenu for pending proposals.
			if ( self::PAGE_SLUG . '-memory' === $slug ) {
				$pending = $this->memory_pending_count();
				if ( $pending > 0 ) {
					$menu_title = $label . ' <span class="awaiting-mod"><span class="pending-count" aria-hidden="true">' . (int) $pending . '</span></span>';
				}
			}
			$this->hook_suffixes[] = add_submenu_page(
				self::PAGE_SLUG,
				$label,
				$menu_title,
				'manage_options',
				$slug,
				array( $this, 'render_page' )
			);
		}

		// Changelog is surfaced as an app-bar button in the header, not the
		// sidebar. We deliberately do NOT remove_submenu_page() it: that drops
		// the page from $submenu, which breaks both user_can_access_admin_page()
		// (parent no longer resolves) and the render hook (admin.php recomputes
		// the page hook to a name with no attached callback → "Cannot load").
		// Instead the sidebar <li> is hidden with CSS in print_menu_icon_style(),
		// so the page stays a normal, fully-renderable submenu reachable by URL.
	}

	/**
	 * Print a tiny inline style on every admin page that constrains our menu
	 * icon to native-dashicon dimensions.
	 *
	 * WordPress renders a PNG menu icon at its natural size, which makes our
	 * 64×64 brand icon overflow the 34px-tall sidebar row. The native dashicon
	 * box is 20×20 with a small vertical inset — replicating that here keeps
	 * the icon visually aligned with Posts/Pages/etc. We inject globally
	 * (not via the EMCP page enqueue) because the WP sidebar shows on every
	 * admin screen, not just ours.
	 *
	 * @since 1.7.2
	 */
	public function print_menu_icon_style(): void {
		echo '<style>'
			. '#toplevel_page_' . esc_attr( self::PAGE_SLUG ) . ' .wp-menu-image img{'
			. 'width:20px;height:20px;padding:7px 0 0;object-fit:contain;opacity:.95;'
			. '}'
			. '#toplevel_page_' . esc_attr( self::PAGE_SLUG ) . ':hover .wp-menu-image img,'
			. '#toplevel_page_' . esc_attr( self::PAGE_SLUG ) . '.current .wp-menu-image img,'
			. '#toplevel_page_' . esc_attr( self::PAGE_SLUG ) . '.wp-has-current-submenu .wp-menu-image img{'
			. 'opacity:1;'
			. '}'
			// The frame's own sidebar replaces the submenu (spec 5.1). The pages
			// stay registered so every URL and deep link keeps working.
			. '#toplevel_page_' . esc_attr( self::PAGE_SLUG ) . ' .wp-submenu{'
			. 'display:none !important;'
			. '}'
			. '</style>';
	}

	/**
	 * Sidebar registry for the current request (spec 5.1).
	 *
	 * @since 3.18.0
	 */
	public function nav(): EMCP_Tools_Admin_Nav {
		$tabs = array();
		foreach ( array_keys( $this->get_submenus() ) as $slug ) {
			$tabs[] = EMCP_Tools_Admin_Nav::tab_from_slug( $slug );
		}
		return new EMCP_Tools_Admin_Nav( $tabs, $this->nav_counts(), self::affiliation_page_available(), $this->nav_links() );
	}

	/**
	 * Licence links for the sidebar footer. The WordPress submenu that carried
	 * Freemius's Account and Upgrade items is hidden by the frame, so the frame
	 * offers them instead.
	 *
	 * @since 3.18.0
	 *
	 * @return string[] Optional 'account' and 'upgrade' URLs.
	 */
	private function nav_links(): array {
		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return array();
		}
		$fs    = emcp_tools_fs();
		$links = array();
		if ( method_exists( $fs, 'is_registered' ) && method_exists( $fs, 'get_account_url' ) && $fs->is_registered() ) {
			$links['account'] = (string) $fs->get_account_url();
		}
		if ( ! $fs->can_use_premium_code() ) {
			$links['upgrade'] = function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : 'https://emcptools.com/pricing';
		}
		return $links;
	}

	/**
	 * Sidebar counts, cached for five minutes (spec 5.1: cheap cached reads only).
	 *
	 * @since 3.18.0
	 *
	 * @return int[] Tab id => count.
	 */
	public function nav_counts(): array {
		$cached = get_transient( 'emcp_tools_nav_counts' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$counts = array(
			'tools'  => $this->get_enabled_tool_count(),
			'memory' => $this->memory_pending_count(),
		);
		// Cached reads only: the frame must never make a remote call (spec 5.1).
		foreach ( $this->get_dashboard_stats( true ) as $stat ) {
			if ( isset( $stat['key'], $stat['value'] ) && in_array( $stat['key'], array( 'prompts', 'templates', 'brand-kits' ), true ) ) {
				$counts[ $stat['key'] ] = (int) $stat['value'];
			}
		}
		set_transient( 'emcp_tools_nav_counts', $counts, 300 );
		return $counts;
	}

	/**
	 * Whether a hook suffix is one of this plugin's admin pages.
	 *
	 * @since 3.18.0
	 *
	 * @param string $hook Hook suffix.
	 */
	public function is_frame_hook( string $hook ): bool {
		return in_array( $hook, $this->hook_suffixes, true );
	}

	/**
	 * Frame body classes on EMCP screens only.
	 *
	 * @since 3.18.0
	 *
	 * @param string $classes Admin body classes.
	 */
	public function admin_body_class( $classes ): string {
		$classes = (string) $classes;
		$hook    = (string) ( $GLOBALS['hook_suffix'] ?? '' );
		if ( ! $this->is_frame_hook( $hook ) ) {
			return $classes;
		}
		return EMCP_Tools_Admin_Frame::body_class( $classes, (string) get_user_setting( 'mfold', '' ) );
	}

	/**
	 * Data for window.emcpShell: the palette index and the notifications.
	 *
	 * @since 3.18.0
	 */
	public function shell_data(): array {
		$groups = EMCP_Tools_Admin_Nav::group_labels();
		$nav    = array();
		foreach ( $this->nav()->entries() as $entry ) {
			$nav[] = array(
				'id'    => $entry['id'],
				'label' => $entry['label'],
				'group' => $groups[ $entry['group'] ] ?? '',
				'url'   => $entry['url'],
			);
		}
		$tools = array();
		foreach ( $this->get_all_tools() as $category ) {
			foreach ( (array) ( $category['tools'] ?? array() ) as $slug => $tool ) {
				$tools[] = array(
					'slug'     => (string) $slug,
					'name'     => (string) ( $tool['label'] ?? $slug ),
					'category' => (string) ( $category['label'] ?? '' ),
				);
			}
		}
		$user   = get_current_user_id();
		$notifs = array();
		if ( class_exists( 'EMCP_Tools_Notifications' ) ) {
			foreach ( EMCP_Tools_Notifications::get() as $n ) {
				$id       = (string) ( $n['id'] ?? '' );
				$notifs[] = array(
					'id'     => $id,
					'title'  => (string) ( $n['title'] ?? '' ),
					'body'   => (string) ( $n['body'] ?? '' ),
					'url'    => (string) ( $n['url'] ?? '' ),
					'cta'    => (string) ( $n['cta'] ?? '' ),
					'unread' => ! EMCP_Tools_Notifications::is_read( $user, $id ),
				);
			}
		}
		return array(
			'nav'           => $nav,
			'tools'         => $tools,
			'settings'      => array(
				array(
					'label' => __( 'Compact tool mode', 'emcp-tools' ),
					'url'   => EMCP_Tools_Admin_Nav::url( 'tools' ),
				),
				array(
					'label' => __( 'OAuth sign-in', 'emcp-tools' ),
					'url'   => EMCP_Tools_Admin_Nav::url( 'connection' ),
				),
				array(
					'label' => __( 'Server URL override', 'emcp-tools' ),
					'url'   => EMCP_Tools_Admin_Nav::url( 'connection' ),
				),
				array(
					'label' => __( 'Application passwords', 'emcp-tools' ),
					'url'   => EMCP_Tools_Admin_Nav::url( 'connection' ),
				),
				array(
					'label' => __( 'Page builder', 'emcp-tools' ),
					'url'   => EMCP_Tools_Admin_Nav::url( 'page-builders' ),
				),
			),
			'notifications' => $notifs,
			'unread'        => class_exists( 'EMCP_Tools_Notifications' ) ? EMCP_Tools_Notifications::unread_count( $user ) : 0,
		);
	}

	/**
	 * Common part of every React screen's boot payload (spec 5.2).
	 *
	 * @since 3.18.0
	 */
	public function boot_common(): array {
		$user = wp_get_current_user();
		return array(
			'version' => EMCP_TOOLS_VERSION,
			'tier'    => array(
				'premium'  => function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->is_premium(),
				'licensed' => function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code(),
			),
			'user'    => EMCP_Tools_Admin_Frame::user_summary( $user ),
			'site'    => array(
				'name'       => get_bloginfo( 'name' ),
				'adminUrl'   => admin_url(),
				'restRoot'   => rest_url(),
				'timezone'   => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC',
				'dateFormat' => (string) get_option( 'date_format', 'Y-m-d' ),
			),
			'flags'   => array(
				'cloud'     => class_exists( 'EMCP_Tools_Cloud' ) && EMCP_Tools_Cloud::is_connected(),
				'elementor' => defined( 'ELEMENTOR_VERSION' ),
				'debug'     => defined( 'WP_DEBUG' ) && WP_DEBUG,
			),
		);
	}

	/**
	 * Register the shared UI library and enqueue the shell (every EMCP screen).
	 *
	 * @since 3.18.0
	 */
	private function enqueue_frame_bundles(): void {
		$build = EMCP_TOOLS_DIR . 'assets/admin/build/';
		foreach ( array( 'ui' => 'emcp-admin-ui', 'shell' => 'emcp-admin-shell' ) as $file => $handle ) {
			$asset_file = $build . $file . '.asset.php';
			if ( ! is_readable( $asset_file ) ) {
				continue;
			}
			$asset   = include $asset_file;
			$version = (string) ( $asset['version'] ?? EMCP_TOOLS_VERSION );
			$deps    = (array) ( $asset['dependencies'] ?? array() );
			if ( 'shell' === $file ) {
				$deps = array_values( array_unique( array_merge( $deps, array( 'emcp-admin-ui' ) ) ) );
			}
			wp_register_script( $handle, EMCP_TOOLS_URL . 'assets/admin/build/' . $file . '.js', $deps, $version, true );
			wp_set_script_translations( $handle, 'emcp-tools' );
			if ( file_exists( $build . $file . '.css' ) ) {
				wp_enqueue_style( $handle, EMCP_TOOLS_URL . 'assets/admin/build/' . $file . '.css', 'shell' === $file ? array( 'emcp-admin-ui' ) : array(), $version );
			}
		}
		wp_add_inline_script( 'emcp-admin-shell', 'window.emcpShell = ' . wp_json_encode( $this->shell_data(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
		wp_enqueue_script( 'emcp-admin-shell' );
	}

	/**
	 * Enqueue a React screen with its boot payload.
	 *
	 * @since 3.18.0
	 *
	 * @param string $screen Screen id.
	 */
	private function enqueue_screen( string $screen ): void {
		EMCP_Tools_Admin_Screens::enqueue( $screen, $this->boot_common(), array( 'tab' => $this->get_active_tab() ) );
	}

	/**
	 * Enqueue admin CSS on our settings page only.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, $this->hook_suffixes, true ) ) {
			return;
		}

		$this->enqueue_frame_bundles();
		$screen = EMCP_Tools_Admin_Screens::screen_for_tab( $this->get_active_tab() );
		if ( null !== $screen ) {
			$this->enqueue_screen( $screen );
			return;
		}

		// Legacy view: its scoped stylesheet plus the old admin scripts.
		$css_path = EMCP_TOOLS_DIR . 'assets/admin/build/legacy.css';
		$js_path  = EMCP_TOOLS_DIR . 'assets/js/admin.js';

		// Some security software and hosts rename or quarantine .js files on
		// upload (admin.js -> admin.j_), which makes the script 404 and silently
		// breaks JS-driven features like the Connection-tab config generator. If
		// the asset is missing, warn the admin with an actionable fix instead of
		// failing silently. (GitHub #44)
		if ( ! file_exists( $js_path ) ) {
			add_action( 'admin_notices', array( $this, 'notice_missing_js_asset' ) );
		}

		// Use filemtime in dev (when WP_DEBUG is on) so iterating on CSS/JS doesn't get stuck
		// behind a cached file under the same plugin version. Falls back to EMCP_TOOLS_VERSION.
		$css_ver = ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $css_path ) ) ? filemtime( $css_path ) : EMCP_TOOLS_VERSION;
		$js_ver  = ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $js_path ) ) ? filemtime( $js_path ) : EMCP_TOOLS_VERSION;

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'elementor-mcp-admin',
				EMCP_TOOLS_URL . 'assets/admin/build/legacy.css',
				array( 'emcp-admin-ui' ),
				$css_ver
			);
		}

		// No script on disk -> nothing to enqueue or localize (the notice above
		// tells the admin how to fix it).
		if ( ! file_exists( $js_path ) ) {
			return;
		}

		wp_enqueue_script(
			'elementor-mcp-admin',
			EMCP_TOOLS_URL . 'assets/js/admin.js',
			array(),
			$js_ver,
			true
		);

		// Sandbox cloud/marketplace button state machine (no-op unless the page
		// renders .emcp-sb-cloud clusters).
		$sb_js = EMCP_TOOLS_DIR . 'assets/js/sandbox-cloud.js';
		if ( file_exists( $sb_js ) ) {
			wp_enqueue_script( 'emcp-tools-sandbox-cloud', EMCP_TOOLS_URL . 'assets/js/sandbox-cloud.js', array(), (string) filemtime( $sb_js ), true );
		}

		// Cloud Library: lazy list + import of the workspace's cloud artifacts
		// (no-op unless the page renders a .emcp-cloud-lib panel).
		$cl_js = EMCP_TOOLS_DIR . 'assets/js/cloud-library.js';
		if ( file_exists( $cl_js ) ) {
			wp_enqueue_script( 'emcp-tools-cloud-library', EMCP_TOOLS_URL . 'assets/js/cloud-library.js', array(), (string) filemtime( $cl_js ), true );
		}

		wp_localize_script(
			'elementor-mcp-admin',
			'emcpToolsAdmin',
			array(
				'copied'      => __( 'Copied!', 'emcp-tools' ),
				'copy'        => __( 'Copy', 'emcp-tools' ),
				'copyFailed'  => __( 'Copy failed', 'emcp-tools' ),
				'download'    => __( 'Download', 'emcp-tools' ),
				'mcpEndpoint' => class_exists( 'EMCP_Tools_Site_Context' ) ? EMCP_Tools_Site_Context::mcp_endpoint() : rest_url( 'mcp/emcp-tools-server' ),
				'oauthEnabled' => class_exists( 'EMCP_Tools_OAuth_Server' ) && EMCP_Tools_OAuth_Server::is_enabled(),
				'oauthSignin'  => __( 'The next time your AI client connects, your browser opens so you can authorize it. Approve to finish connecting.', 'emcp-tools' ),
				/* translators: %s: client label */
				'genFirst'     => __( 'Generate your credentials above, the config for %s then appears here.', 'emcp-tools' ),
				'siteUrl'     => class_exists( 'EMCP_Tools_Site_Context' ) ? EMCP_Tools_Site_Context::public_base_url() : site_url(),
				// Only the filename — never the absolute server path. The proxy runs
				// on the CLIENT machine, so the server path is both useless to the
				// user and a needless path disclosure (F-020). The UI points users at
				// the npx runner or their own local copy of the proxy.
				'proxyPath'   => 'mcp-proxy.mjs',
				// Full MCP connection + OAuth discovery diagnostics.
				'authTesting' => __( 'Testing the full MCP handshake…', 'emcp-tools' ),
				'authOk'      => __( '✓ Full MCP handshake succeeded: initialize, initialized notification, and tools/list all worked.', 'emcp-tools' ),
				'authError'   => __( 'Could not run the MCP connection test.', 'emcp-tools' ),
				'oauthTesting' => __( 'Checking public OAuth discovery endpoints…', 'emcp-tools' ),
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'createPwNonce' => wp_create_nonce( 'emcp_tools_create_app_password' ),
				'testConnNonce' => wp_create_nonce( 'emcp_tools_test_connection' ),
				'testOAuthNonce' => wp_create_nonce( 'emcp_tools_test_oauth_discovery' ),
				'trackPromptNonce' => wp_create_nonce( 'emcp_tools_track_prompt_copy' ),
				'generating'    => __( 'Generating…', 'emcp-tools' ),
				'pwCreated'     => __( 'Application password created, save it below, it is shown only once.', 'emcp-tools' ),
				'syncing'       => __( 'Syncing…', 'emcp-tools' ),
				// Brand Kits.
				'applying'      => __( 'Applying…', 'emcp-tools' ),
				'restoring'     => __( 'Restoring…', 'emcp-tools' ),
				/* translators: %s: brand kit title */
				'applyKitTitle' => __( 'Apply "%s" brand kit?', 'emcp-tools' ),
				/* translators: %s: brand kit title */
				'kitApplied'    => __( '%s applied.', 'emcp-tools' ),
				'restoreConfirm'     => __( 'Restore global colors and typography from this backup?', 'emcp-tools' ),
				'viewSite'           => __( 'View site →', 'emcp-tools' ),
				// Connection-tab client picker + .mcpb bundle.
				'connectionClients'  => self::connection_clients(),
				'mcpbNonce'          => wp_create_nonce( self::NONCE_DOWNLOAD_MCPB ),
				'adminPostUrl'       => admin_url( 'admin-post.php' ),
				'siteContextBase'      => EMCP_Tools_Site_Context::default_base(),
				'siteContextDelimiter' => EMCP_Tools_Site_Context::DELIMITER,
			)
		);

		// Modules tab: the bulk-optimizer progress UI.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing.
		if ( isset( $_GET['page'] ) && ( self::PAGE_SLUG . '-modules' ) === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			$bulk_path = EMCP_TOOLS_DIR . 'assets/js/modules-bulk.js';
			if ( file_exists( $bulk_path ) && class_exists( 'EMCP_Tools_Bulk_Optimizer' ) ) {
				$bulk_ver = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? filemtime( $bulk_path ) : EMCP_TOOLS_VERSION;
				wp_enqueue_script( 'emcp-tools-modules-bulk', EMCP_TOOLS_URL . 'assets/js/modules-bulk.js', array(), $bulk_ver, true );
				wp_localize_script(
					'emcp-tools-modules-bulk',
					'emcpToolsModules',
					array(
						'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
						'nonce'         => wp_create_nonce( EMCP_Tools_Bulk_Optimizer::NONCE ),
						'batchAction'   => EMCP_Tools_Bulk_Optimizer::ACTION_BATCH,
						'restoreAction' => EMCP_Tools_Bulk_Optimizer::ACTION_RESTORE,
						'batchSize'     => 10,
						'optimizing'    => __( 'Optimizing…', 'emcp-tools' ),
						'restoring'     => __( 'Restoring…', 'emcp-tools' ),
						'done'          => __( 'Done', 'emcp-tools' ),
						'unsaved'       => __( 'Unsaved changes, click Save Modules to apply.', 'emcp-tools' ),
					)
				);
			}
		}
	}

	/**
	 * Admin notice shown when assets/js/admin.js is missing from the plugin
	 * folder — usually because security software or a host renamed/quarantined
	 * the .js file on upload (e.g. admin.js -> admin.j_). Without it, JS-driven
	 * features (the Connection-tab config generator, tool toggles, etc.) silently
	 * do nothing, so we surface a precise, actionable message. (GitHub #44)
	 *
	 * @since 2.1.0
	 */
	public function notice_missing_js_asset(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Detect a mangled copy so we can name the exact file to restore.
		$dir     = EMCP_TOOLS_DIR . 'assets/js/';
		$mangled = '';
		foreach ( array( 'admin.j_', 'admin.js_', 'admin._s', 'admin.js.quarantine' ) as $candidate ) {
			if ( file_exists( $dir . $candidate ) ) {
				$mangled = $candidate;
				break;
			}
		}

		echo '<div class="notice notice-error"><p><strong>EMCP Tools:</strong> ';
		echo esc_html__( 'A required script is missing, assets/js/admin.js was not found in the plugin folder, so admin features like the Connection-tab config generator will not work.', 'emcp-tools' );
		echo ' ';
		if ( '' !== $mangled ) {
			printf(
				/* translators: %s: the mangled filename found, e.g. admin.j_ */
				esc_html__( 'It looks like security software renamed it to assets/js/%s, rename that file back to admin.js.', 'emcp-tools' ),
				esc_html( $mangled )
			);
		} else {
			echo esc_html__( 'Some security software and hosts rename or quarantine .js files on upload. Re-upload a fresh copy of the plugin from the official release, and restore assets/js/admin.js if your host renamed it.', 'emcp-tools' );
		}
		echo '</p></div>';
	}

	/**
	 * AJAX: mark app-bar notifications as read for the current user, called
	 * when the notifications dropdown is opened.
	 *
	 * @since 3.10.0
	 */
	public function ajax_notifications_read(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'emcp-tools' ) ), 403 );
		}
		if ( ! check_ajax_referer( 'emcp_tools_notifications', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'emcp-tools' ) ), 403 );
		}

		$ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
		$ids = array_map( 'sanitize_text_field', $ids );

		$user_id = get_current_user_id();
		EMCP_Tools_Notifications::mark_read( $user_id, $ids );

		wp_send_json_success( array( 'unread' => EMCP_Tools_Notifications::unread_count( $user_id ) ) );
	}

	/**
	 * Guards a Memory AJAX request (nonce + Pro/cap). wp_die/returns on failure.
	 *
	 * @since 3.7.0
	 */
	private function memory_ajax_guard(): void {
		check_ajax_referer( 'emcp_tools_memory', 'nonce' );
		if ( ! class_exists( 'EMCP_Tools_Memory_Store' ) || ! EMCP_Tools_Memory_Store::user_has_access() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
	}

	/**
	 * AJAX: approve/reject/toggle a guidance entry from the Memory tab.
	 *
	 * @since 3.7.0
	 */
	public function ajax_memory_set_status(): void {
		$this->memory_ajax_guard();
		$id     = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! $id || ! in_array( $status, array( 'publish', 'pending', 'draft', 'trash' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'emcp-tools' ) ), 400 );
		}
		$ok = EMCP_Tools_Memory_Store::instance()->set_guidance_status( $id, $status );
		$ok ? wp_send_json_success( array( 'id' => $id, 'status' => $status ) )
			: wp_send_json_error( array( 'message' => __( 'Not found.', 'emcp-tools' ) ), 400 );
	}

	/**
	 * AJAX: create (admin, approved) or edit a guidance entry from the Memory tab.
	 *
	 * @since 3.7.0
	 */
	public function ajax_memory_save_guidance(): void {
		$this->memory_ajax_guard();
		$store = EMCP_Tools_Memory_Store::instance();
		$id    = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$type  = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$body  = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		if ( ! in_array( $type, EMCP_Tools_Memory_Store::TYPES, true ) || '' === trim( $body ) ) {
			wp_send_json_error( array( 'message' => __( 'A type and non-empty guidance are required.', 'emcp-tools' ) ), 400 );
		}
		if ( $id > 0 ) {
			$store->update_guidance( $id, array( 'type' => $type, 'body' => $body, 'title' => wp_trim_words( $body, 8, '' ) ) );
			wp_send_json_success( array( 'id' => $id ) );
		}
		$new = $store->add_guidance( array(
			'title'  => wp_trim_words( $body, 8, '' ),
			'body'   => $body,
			'type'   => $type,
			'source' => 'admin',
			'status' => 'publish',
		) );
		is_wp_error( $new )
			? wp_send_json_error( array( 'message' => $new->get_error_message() ), 400 )
			: wp_send_json_success( array( 'id' => (int) $new ) );
	}

	/**
	 * AJAX: persist Memory settings (auto-summarize, require-approval).
	 *
	 * @since 3.7.0
	 */
	public function ajax_memory_save_settings(): void {
		$this->memory_ajax_guard();
		if ( isset( $_POST['auto_summarize'] ) ) {
			update_option( 'emcp_tools_memory_auto_summarize', '1' === sanitize_text_field( wp_unslash( $_POST['auto_summarize'] ) ) ? '1' : '0' );
		}
		if ( isset( $_POST['require_approval'] ) ) {
			update_option( 'emcp_tools_memory_require_approval', '1' === sanitize_text_field( wp_unslash( $_POST['require_approval'] ) ) ? '1' : '0' );
		}
		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * Build the headline stat cards shown on the Dashboard.
	 *
	 * Always includes catalog, site, enabled, and Pro tool counts. Prompts, Brand Kits,
	 * and Templates are appended only when their module is active (and, for the
	 * Pro-gated counts, when a value is available) — mirroring the module-tab
	 * visibility rules. Each entry is `key`/`value`/`label`; the view maps `key`
	 * to an icon.
	 *
	 * @since 3.1.0
	 * @since 3.18.0 $cached_only: read the templates library from its cache
	 *               only, never fetch it (the frame's sidebar counts).
	 *
	 * @param bool $cached_only Never make a remote call.
	 * @return array<int,array{key:string,value:int,label:string}>
	 */
	public function get_dashboard_stats( bool $cached_only = false ): array {
		$catalog_tools = array();
		$pro_tools     = array();
		foreach ( $this->get_all_tools() as $category ) {
			foreach ( $category['tools'] as $slug => $tool ) {
				$catalog_tools[ $slug ] = true;
				// Elementor Pro is a separate product, not an EMCP Pro tool.
				if ( in_array( 'pro', $tool['badges'] ?? array(), true ) ) {
					$pro_tools[ $slug ] = true;
				}
			}
		}
		$stats = array(
			array( 'key' => 'tools', 'value' => count( $catalog_tools ), 'label' => __( 'Catalog Tools', 'emcp-tools' ) ),
			array( 'key' => 'site', 'value' => $this->get_total_tool_count(), 'label' => __( 'Shown in Tools', 'emcp-tools' ) ),
			array( 'key' => 'active', 'value' => $this->get_enabled_tool_count(), 'label' => __( 'Enabled Tools', 'emcp-tools' ) ),
			array( 'key' => 'pro', 'value' => count( $pro_tools ), 'label' => __( 'EMCP Pro Tools', 'emcp-tools' ) ),
		);

		if ( class_exists( 'EMCP_Tools_Modules_Registry' ) ) {
			$active_modules = 0;
			foreach ( EMCP_Tools_Modules_Registry::instance()->active() as $module ) {
				if ( $module->is_available() ) {
					++$active_modules;
				}
			}
			$stats[] = array( 'key' => 'modules', 'value' => $active_modules, 'label' => __( 'Active Modules', 'emcp-tools' ) );
		}

		// Count prompts. For Pro sites with a synced bundle, use the actual
		// premium-library count (matches the Prompts tab). Otherwise count the
		// bundled sample files in prompts/.
		if ( $this->module_tab_visible( 'prompts' ) ) {
			$prompt_count = 0;
			if ( class_exists( 'EMCP_Tools_Pro_Prompts' ) && EMCP_Tools_Pro_Prompts::user_has_access() ) {
				$prompt_count = EMCP_Tools_Pro_Prompts::cached_count();
			}
			if ( 0 === $prompt_count ) {
				$prompts_dir  = EMCP_TOOLS_DIR . 'prompts/';
				$prompt_files = is_dir( $prompts_dir ) ? glob( $prompts_dir . '*.md' ) : array();
				$prompt_count = count( $prompt_files );
			}
			$stats[] = array( 'key' => 'prompts', 'value' => (int) $prompt_count, 'label' => __( 'Prompts', 'emcp-tools' ) );
		}

		// Brand kits: Pro shows the cached remote library count; everyone else
		// shows the bundled free-kit count (applying is a free feature).
		if ( $this->module_tab_visible( 'brand-kits' ) ) {
			$brand_kit_count = 0;
			$show_brand_kits = false;
			if ( class_exists( 'EMCP_Tools_Pro_Brand_Kits' ) && EMCP_Tools_Pro_Brand_Kits::user_has_access() ) {
				$brand_kit_count = EMCP_Tools_Pro_Brand_Kits::count_cached_kits();
				$show_brand_kits = true;
			} elseif ( class_exists( 'EMCP_Tools_Free_Brand_Kits' ) ) {
				$brand_kit_count = EMCP_Tools_Free_Brand_Kits::count_kits();
				$show_brand_kits = $brand_kit_count > 0;
			}
			if ( $show_brand_kits ) {
				$stats[] = array( 'key' => 'brand-kits', 'value' => (int) $brand_kit_count, 'label' => __( 'Brand Kits', 'emcp-tools' ) );
			}
		}

		// Templates: Pro shows the templates-library total (sum across
		// categories). Hidden for free users and when the bundle can't be fetched.
		if ( $this->module_tab_visible( 'templates' ) && class_exists( 'EMCP_Tools_Pro_Templates' ) && EMCP_Tools_Pro_Templates::user_has_access() ) {
			$template_count  = 0;
			$emcp_tpl_bundle = $cached_only ? get_transient( EMCP_Tools_Pro_Templates::CACHE_KEY ) : EMCP_Tools_Pro_Templates::get_bundle();
			if ( ! is_wp_error( $emcp_tpl_bundle ) && is_array( $emcp_tpl_bundle ) && ! empty( $emcp_tpl_bundle['categories'] ) ) {
				foreach ( $emcp_tpl_bundle['categories'] as $emcp_tpl_cat ) {
					$template_count += is_array( $emcp_tpl_cat['templates'] ?? null ) ? count( $emcp_tpl_cat['templates'] ) : 0;
				}
			}
			if ( $template_count > 0 ) {
				$stats[] = array( 'key' => 'templates', 'value' => $template_count, 'label' => __( 'Templates', 'emcp-tools' ) );
			}
		}

		return $stats;
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = $this->get_active_tab();

		include __DIR__ . '/views/page-shell.php';
	}

	/**
	 * The 12 Forms dispatcher slugs — drift-guard exclusion (registered only when
	 * their plugin is active / Pro, so the drift guard must not flag them as
	 * "missing" tools).
	 *
	 * @since 3.5.0
	 * @return string[]
	 */

	/**
	 * Get all tools grouped by category for the UI.
	 *
	 * Returns the curated catalog (see get_tool_catalog()) and, under WP_DEBUG,
	 * cross-checks it against the live ability registry so the hand-maintained
	 * catalog can't silently drift from the actually-registered tools (F-019).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{label: string, tools: array<string, array{label: string, description: string, badges: string[]}>}> Grouped tools.
	 */
	public function get_all_tools(): array {
		$catalog = $this->get_tool_catalog();

		// F-019 drift guard: the catalog carries admin-UI metadata (labels,
		// descriptions, badges) the bare ability registry doesn't have, so it
		// stays curated rather than derived. To stop it drifting, cross-check
		// each catalog slug against the live registry and log any that isn't a
		// registered ability (a renamed/removed tool, or env-gated).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && class_exists( 'WP_Abilities_Registry' ) ) {
			$emcp_registry = WP_Abilities_Registry::get_instance();
			// Tools that only register when their module/feature/flag is on are
			// legitimately absent — skip them so the guard flags genuine drift
			// (renamed/removed tools) and not expected environment-gating.
			$emcp_conditional = array_merge(
				self::cloud_tool_slugs(),
				self::themer_tool_slugs(),
				self::themer_php_tool_slugs(),
				self::acf_tool_slugs(),
				self::woo_tool_slugs(),
				self::funnelkit_tool_slugs(),
				self::metabox_tool_slugs(),
				self::form_tool_slugs(),
				self::seo_tool_slugs(),
				self::addon_tool_slugs(),
				self::theme_tool_slugs(),
				self::seo_a11y_tool_slugs(),
				self::widget_builder_tool_slugs(),
				self::block_tool_slugs(),
				self::memory_tool_slugs(),
				self::redirect_tool_slugs(),
				self::migrate_tool_slugs(),
				array( 'emcp-tools/list-redirects', 'emcp-tools/find-broken-links', 'emcp-tools/resize-media' )
			);
			foreach ( $catalog as $emcp_group ) {
				foreach ( array_keys( $emcp_group['tools'] ?? array() ) as $emcp_slug ) {
					// is_registered() is a silent isset() check — unlike wp_get_ability()
					// / get_registered(), it does not _doing_it_wrong() "Ability not
					// found" for env-gated tools, which was flooding debug.log (#71).
					if ( ! $emcp_registry->is_registered( $emcp_slug )
						&& ! in_array( $emcp_slug, $emcp_conditional, true ) ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( '[EMCP Tools] get_all_tools: catalog tool "' . $emcp_slug . '" is not in the ability registry (drift or environment-gated).' );
					}
				}
			}
		}

		// Pro sections are always present in the catalog so free users see the
		// (locked) Pro surface. On a build without a usable Pro license, lock
		// every tool in a `pro` category — disable its toggle and swap in a
		// "Requires EMCP Pro" note — and ensure it carries the `pro` badge. On a
		// licensed build the category's own availability (e.g. WooCommerce active)
		// is left untouched, and the abilities themselves stay license-gated.
		$emcp_is_pro = function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code();
		foreach ( $catalog as &$emcp_pro_cat ) {
			if ( empty( $emcp_pro_cat['pro'] ) || empty( $emcp_pro_cat['tools'] ) ) {
				continue;
			}
			foreach ( $emcp_pro_cat['tools'] as &$emcp_pro_tool ) {
				if ( empty( $emcp_pro_tool['badges'] ) || ! is_array( $emcp_pro_tool['badges'] ) ) {
					$emcp_pro_tool['badges'] = array();
				}
				if ( ! in_array( 'pro', $emcp_pro_tool['badges'], true ) ) {
					array_unshift( $emcp_pro_tool['badges'], 'pro' );
				}
				if ( ! $emcp_is_pro ) {
					$emcp_pro_tool['available']        = false;
					$emcp_pro_tool['unavailable_note'] = __( 'Requires EMCP Pro.', 'emcp-tools' );
				}
			}
			unset( $emcp_pro_tool );
		}
		unset( $emcp_pro_cat );

		return $catalog;
	}

	/**
	 * Get a flat list of all tool slugs.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] All tool slugs.
	 */
	public function get_all_tool_slugs(): array {
		$slugs = array();
		foreach ( $this->get_all_tools() as $category ) {
			foreach ( $category['tools'] as $slug => $tool ) {
				$slugs[] = $slug;
			}
		}
		return $slugs;
	}

	/**
	 * Returns only the slugs of tools whose platform group is currently active.
	 *
	 * When Elementor is inactive, Elementor-platform tools are excluded because
	 * they are never registered and must not inflate "X of Y enabled" stats.
	 * Use get_all_tool_slugs() (unfiltered) anywhere the full canonical list is
	 * needed for data-management purposes (e.g. sanitize_disabled_tools).
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public function get_available_tool_slugs(): array {
		$categories = EMCP_Tools_Page_Builders::visible_categories( $this->get_all_tools() );
		$slugs = array();
		foreach ( $categories as $category ) {
			foreach ( $category['tools'] as $slug => $tool ) {
				$slugs[] = $slug;
			}
		}
		return $slugs;
	}

	/**
	 * Count enabled tools.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of enabled tools.
	 */
	public function get_enabled_tool_count(): int {
		$all = $this->get_available_tool_slugs();

		$disabled = get_option( self::OPTION_DISABLED_TOOLS, array() );
		if ( ! is_array( $disabled ) ) {
			$disabled = array();
		}

		return count( array_diff( $all, $disabled ) );
	}

	/**
	 * Count total tools.
	 *
	 * @since 1.0.0
	 *
	 * @return int Total number of tools.
	 */
	public function get_total_tool_count(): int {
		return count( $this->get_available_tool_slugs() );
	}
}
