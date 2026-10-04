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
require_once __DIR__ . '/trait-admin-cloud.php';
require_once __DIR__ . '/trait-admin-connection.php';
require_once __DIR__ . '/trait-admin-settings.php';
require_once __DIR__ . '/trait-admin-tool-groups.php';
require_once __DIR__ . '/trait-admin-integrations.php';
require_once __DIR__ . '/trait-admin-catalog.php';

/**
 * Admin page orchestrator.
 *
 * @since 1.0.0
 */
class EMCP_Tools_Admin {

	use EMCP_Tools_Admin_Cloud_Trait;
	use EMCP_Tools_Admin_Connection_Trait;
	use EMCP_Tools_Admin_Settings_Trait;
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
	public function module_tab_visible( string $module_id ): bool {
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
	 * Whether a module is switched on, whether or not it is available here. A
	 * Pro-only tab stays in the menu on free builds and shows the locked screen.
	 *
	 * @param string $module_id Module id.
	 */
	private function module_switched_on( string $module_id ): bool {
		if ( ! class_exists( 'EMCP_Tools_Modules_Registry' ) ) {
			return true;
		}
		$module = EMCP_Tools_Modules_Registry::instance()->get( $module_id );
		return ! $module || $module->is_active();
	}

	/**
	 * Whether the AI Chat submenu tab should show.
	 *
	 * @return bool
	 */
	public function ai_chat_tab_visible(): bool {
		return $this->module_tab_visible( 'ai-chat' );
	}

	/**
	 * Whether the Skills tab should show: it follows the Agent Skills module
	 * switch. A switched-on module on a free or unlicensed build keeps the tab,
	 * which opens the locked screen.
	 */
	public function skills_tab_visible(): bool {
		return $this->module_switched_on( 'agent-skills' );
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
			if ( ! $this->skills_tab_visible() ) {
				unset( $this->submenus[ self::PAGE_SLUG . '-skills' ] );
			}
			// Module-backed tabs: drop each when its module is off/unavailable.
			// Templates stays in the menu whenever its module is switched on, so
			// free and unlicensed builds reach the locked screen (spec 8.25).
			foreach ( array( 'prompts', 'templates', 'brand-kits' ) as $emcp_mod_id ) {
				$emcp_visible = 'templates' === $emcp_mod_id
					? $this->module_switched_on( 'templates' )
					: $this->module_tab_visible( $emcp_mod_id );
				if ( ! $emcp_visible ) {
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
		EMCP_Tools_Admin_REST_Marketplace::register_screen();
		EMCP_Tools_Admin_REST_Context::register_screen();
		EMCP_Tools_Admin_REST_History::register_screen();
		EMCP_Tools_Admin_REST_Log::register_screen();
		EMCP_Tools_Admin_REST_Dashboard::register_screen( $this );
		EMCP_Tools_Admin_REST_Changelog::register_screen();
		if ( class_exists( 'EMCP_Tools_Redirect_Module' ) && EMCP_Tools_Redirect_Module::is_enabled() ) {
			EMCP_Tools_Admin_REST_Redirects::register_screen();
		}
		EMCP_Tools_Admin_REST_Sandbox::register_screens();
		if ( class_exists( 'EMCP_Tools_Admin_REST_Templates' ) ) {
			EMCP_Tools_Admin_REST_Templates::register_screen();
		}
		if ( class_exists( 'EMCP_Tools_Admin_REST_Backup' ) && class_exists( 'EMCP_Tools_Migrate_Module' ) && EMCP_Tools_Migrate_Module::is_enabled() ) {
			EMCP_Tools_Admin_REST_Backup::register_screen();
		}
		if ( class_exists( 'EMCP_Tools_Admin_REST_Skills' ) ) {
			EMCP_Tools_Admin_REST_Skills::register_screen();
		}
		if ( class_exists( 'EMCP_Tools_Admin_REST_Memory' ) ) {
			EMCP_Tools_Admin_REST_Memory::register_screen();
		}
		if ( class_exists( 'EMCP_Tools_Admin_AI_Chat_Data' ) ) {
			EMCP_Tools_Admin_AI_Chat_Data::register_screen();
		}
		if ( class_exists( 'EMCP_Tools_Admin_REST_Sandbox_Export' ) ) {
			EMCP_Tools_Admin_REST_Sandbox_Export::register_screen();
		}
		add_action( 'admin_head', array( $this, 'print_menu_icon_style' ) );
		add_action( 'admin_post_emcp_tools_download_mcpb', array( $this, 'handle_download_mcpb' ) );
		add_action( 'admin_post_emcp_tools_settings_push', array( $this, 'handle_settings_push' ) );
		add_action( 'admin_post_emcp_tools_settings_pull', array( $this, 'handle_settings_pull' ) );
	}

	/** Nonce action for the .mcpb bundle download. */
	const NONCE_DOWNLOAD_MCPB = 'emcp_tools_download_mcpb';


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
	const DEFAULTS_VERSION = 58;

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
			'tools'   => $this->get_enabled_tool_count(),
			'memory'  => $this->memory_pending_count(),
			'widgets' => class_exists( 'EMCP_Tools_Admin_Sandbox_Data' ) ? EMCP_Tools_Admin_Sandbox_Data::flagged_count() : 0,
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
		$tab  = $this->get_active_tab();
		$view = EMCP_Tools_Admin_Screens::current_view();
		EMCP_Tools_Admin_Screens::enqueue(
			$screen,
			$this->boot_common(),
			array(
				'tab'  => $tab,
				'view' => $view,
				'key'  => EMCP_Tools_Admin_Screens::route_key( $tab, $view ),
			)
		);
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
		$screen = EMCP_Tools_Admin_Screens::screen_for_tab( $this->get_active_tab(), null, EMCP_Tools_Admin_Screens::current_view() );
		if ( null !== $screen ) {
			$this->enqueue_screen( $screen );
		}
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
				self::funnelkit_automations_tool_slugs(),
				self::polylang_tool_slugs(),
				self::translatepress_tool_slugs(),
				self::tablepress_tool_slugs(),
				self::tutor_tool_slugs(),
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
