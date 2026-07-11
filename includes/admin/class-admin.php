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

/**
 * Admin page orchestrator.
 *
 * @since 1.0.0
 */
class EMCP_Tools_Admin {

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
			'modules'    => 'dashicons-screenoptions',
			'connection' => 'dashicons-admin-links',
			'ai-chat'    => 'dashicons-format-chat',
			'context'    => 'dashicons-info-outline',
			'prompts'    => 'dashicons-lightbulb',
			'templates'  => 'dashicons-layout',
			'brand-kits' => 'dashicons-art',
			'skills'     => 'dashicons-superhero',
			'widgets'    => 'dashicons-editor-code',
			'changelog'  => 'dashicons-backup',
		);
		return $icons[ $tab_id ] ?? 'dashicons-marker';
	}

	private function get_submenus(): array {
		if ( null === $this->submenus ) {
			$this->submenus = array(
				self::PAGE_SLUG                 => __( 'Dashboard', 'emcp-tools' ),
				self::PAGE_SLUG . '-modules'    => __( 'Modules', 'emcp-tools' ),
				self::PAGE_SLUG . '-tools'      => __( 'Tools', 'emcp-tools' ),
				self::PAGE_SLUG . '-connection' => __( 'Connection', 'emcp-tools' ),
				self::PAGE_SLUG . '-ai-chat'    => __( 'AI Chat', 'emcp-tools' ),
				self::PAGE_SLUG . '-context'    => __( 'Context', 'emcp-tools' ),
				self::PAGE_SLUG . '-prompts'    => __( 'Prompts', 'emcp-tools' ),
				self::PAGE_SLUG . '-templates'  => __( 'Templates', 'emcp-tools' ),
				self::PAGE_SLUG . '-brand-kits' => __( 'Brand Kits', 'emcp-tools' ),
				self::PAGE_SLUG . '-skills'     => __( 'Skills', 'emcp-tools' ),
				self::PAGE_SLUG . '-widgets'    => __( 'Sandbox', 'emcp-tools' ),
				self::PAGE_SLUG . '-changelog'  => __( 'Changelog', 'emcp-tools' ),
			);
			if ( ! $this->ai_chat_tab_visible() ) {
				unset( $this->submenus[ self::PAGE_SLUG . '-ai-chat' ] );
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
			case self::PAGE_SLUG . '-tools':
				return 'tools';
			case self::PAGE_SLUG . '-modules':
				return 'modules';
			case self::PAGE_SLUG . '-connection':
				return 'connection';
			case self::PAGE_SLUG . '-ai-chat':
				return 'ai-chat';
			case self::PAGE_SLUG . '-context':
				return 'context';
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
		add_action( 'admin_head', array( $this, 'print_menu_icon_style' ) );
		add_action( 'wp_ajax_emcp_tools_create_app_password', array( $this, 'ajax_create_app_password' ) );
		add_action( 'wp_ajax_emcp_tools_toggle_widget', array( $this, 'ajax_toggle_widget' ) );
		add_action( 'wp_ajax_emcp_tools_delete_widget', array( $this, 'ajax_delete_widget' ) );
		add_action( 'wp_ajax_emcp_tools_save_php_snippet', array( $this, 'ajax_save_php_snippet' ) );
		add_action( 'wp_ajax_emcp_tools_toggle_php_snippet', array( $this, 'ajax_toggle_php_snippet' ) );
		add_action( 'wp_ajax_emcp_tools_delete_php_snippet', array( $this, 'ajax_delete_php_snippet' ) );
		add_action( 'admin_post_emcp_tools_download_mcpb', array( $this, 'handle_download_mcpb' ) );
		add_action( 'admin_post_' . self::ACTION_DISMISS_PROMPTS_NOTICE, array( $this, 'handle_dismiss_prompts_notice' ) );
	}

	/** Nonce action for the .mcpb bundle download. */
	const NONCE_DOWNLOAD_MCPB = 'emcp_tools_download_mcpb';

	/** admin-post action that dismisses the "prompts rewritten" notice. */
	const ACTION_DISMISS_PROMPTS_NOTICE = 'emcp_tools_dismiss_prompts_notice';

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
	const DEFAULTS_VERSION = 15;

	/**
	 * SEO/A11y Pro MCP tool slugs that ship disabled-by-default (v2 defaults).
	 *
	 * @since 1.8.0
	 *
	 * @return string[]
	 */
	public static function seo_a11y_tool_slugs(): array {
		return array(
			'emcp-tools/audit-page-seo',
			'emcp-tools/extract-keywords-from-content',
			'emcp-tools/generate-meta-tags',
			'emcp-tools/generate-schema-markup',
			'emcp-tools/set-social-image',
			'emcp-tools/audit-page-a11y',
			'emcp-tools/fix-color-contrast',
			'emcp-tools/add-alt-text-from-context',
		);
	}

	/**
	 * Widget Builder Pro MCP tool slugs that ship disabled-by-default (v3).
	 *
	 * @since 1.9.0
	 *
	 * @return string[]
	 */
	public static function widget_builder_tool_slugs(): array {
		return array(
			'emcp-tools/list-control-types',
			'emcp-tools/validate-widget-spec',
			'emcp-tools/create-custom-widget',
			'emcp-tools/update-custom-widget',
			'emcp-tools/get-custom-widget',
			'emcp-tools/list-custom-widgets',
			'emcp-tools/set-widget-status',
			'emcp-tools/delete-custom-widget',
		);
	}

	/**
	 * The PHP Snippet (Sandbox) tool slugs. Free, but powerful, so they ship
	 * disabled-by-default and the admin opts in on the Tools tab.
	 *
	 * @since 2.1.0
	 *
	 * @return string[]
	 */
	public static function php_snippet_tool_slugs(): array {
		return array(
			'emcp-tools/validate-php-snippet',
			'emcp-tools/create-php-snippet',
			'emcp-tools/update-php-snippet',
			'emcp-tools/get-php-snippet',
			'emcp-tools/list-php-snippets',
			'emcp-tools/delete-php-snippet',
		);
	}

	/**
	 * Themer PHP-template tool slugs. The whole feature is gated behind a master
	 * switch (off by default), and even once enabled these 5 tools ship
	 * disabled-by-default like the PHP Snippets — the admin opts in on the Tools tab.
	 *
	 * @since 3.1.0
	 *
	 * @return string[]
	 */
	public static function themer_php_tool_slugs(): array {
		return array(
			'emcp-tools/create-theme-php-template',
			'emcp-tools/list-theme-php-templates',
			'emcp-tools/get-theme-php-template',
			'emcp-tools/update-theme-php-template',
			'emcp-tools/delete-theme-php-template',
		);
	}

	/**
	 * The 9 Plugins & Themes mutation tool slugs. Powerful (install/delete/
	 * activate), so they ship disabled-by-default; reads stay enabled. The admin
	 * opts in on the Tools tab.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function package_write_tool_slugs(): array {
		return array(
			'emcp-tools/install-plugin',
			'emcp-tools/activate-plugin',
			'emcp-tools/deactivate-plugin',
			'emcp-tools/update-plugin',
			'emcp-tools/delete-plugin',
			'emcp-tools/install-theme',
			'emcp-tools/switch-theme',
			'emcp-tools/update-theme',
			'emcp-tools/delete-theme',
		);
	}

	/**
	 * Media tool slugs that ship disabled-by-default. Only delete-media (the
	 * destructive, effectively-permanent op); get-media / update-media stay on.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function media_write_tool_slugs(): array {
		return array( 'emcp-tools/delete-media' );
	}

	/**
	 * Users mutation tool slugs that ship disabled-by-default. The reads
	 * (list-users/get-user) stay enabled. The admin opts in on the Tools tab.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function user_write_tool_slugs(): array {
		return array( 'emcp-tools/create-user', 'emcp-tools/update-user' );
	}

	/**
	 * Filesystem mutation tool slugs that ship disabled-by-default. The reads
	 * (read-file/list-directory/search-files) stay enabled.
	 *
	 * @since 3.0.0
	 * @return string[]
	 */
	public static function filesystem_write_tool_slugs(): array {
		return array( 'emcp-tools/write-file', 'emcp-tools/edit-file', 'emcp-tools/delete-file' );
	}

	/**
	 * Database mutation tool slugs that ship disabled-by-default. The reads
	 * (list-tables/describe-table/query) stay enabled.
	 *
	 * @since 3.0.0
	 * @return string[]
	 */
	public static function database_write_tool_slugs(): array {
		return array( 'emcp-tools/insert-row', 'emcp-tools/update-rows', 'emcp-tools/delete-rows' );
	}

	/**
	 * The ACF dispatcher tool slugs. The domain registers as two dispatcher
	 * tools (acf-read enabled by default, acf-write disabled by default); the
	 * 15 operations live behind them. Both slugs are excluded from the drift
	 * guard since the domain only registers when ACF (free or Pro) is active.
	 *
	 * @since 3.2.1
	 * @return string[]
	 */
	public static function acf_tool_slugs(): array {
		return array(
			'emcp-tools/acf-read',
			'emcp-tools/acf-write',
		);
	}

	/**
	 * The pre-release per-operation ACF slugs (the earlier 15-tool layout).
	 * Kept only so the defaults step can strip them from the stored option on
	 * sites that seeded them before the 2-dispatcher consolidation.
	 *
	 * @since 3.2.1
	 * @return string[]
	 */
	public static function legacy_acf_operation_slugs(): array {
		return array(
			'emcp-tools/list-acf-field-groups',
			'emcp-tools/get-acf-field-group',
			'emcp-tools/list-acf-options-pages',
			'emcp-tools/get-acf-fields',
			'emcp-tools/update-acf-fields',
			'emcp-tools/create-acf-field-group',
			'emcp-tools/update-acf-field-group',
			'emcp-tools/list-acf-post-types',
			'emcp-tools/get-acf-post-type',
			'emcp-tools/create-acf-post-type',
			'emcp-tools/update-acf-post-type',
			'emcp-tools/list-acf-taxonomies',
			'emcp-tools/get-acf-taxonomy',
			'emcp-tools/create-acf-taxonomy',
			'emcp-tools/update-acf-taxonomy',
		);
	}

	/**
	 * Seeds default disabled-tools on install/upgrade so new Pro tool batches
	 * ship off-by-default (keeping sites under client tool caps), then records
	 * the applied version. Each version step adds ONLY its newly-introduced
	 * slugs, so prior user enable/disable choices are preserved (union merge).
	 *
	 * @since 1.6.0
	 */
	public function maybe_apply_default_disabled_tools(): void {
		$applied = (int) get_option( self::OPTION_DEFAULTS_APPLIED, 0 );
		if ( $applied >= self::DEFAULTS_VERSION ) {
			return;
		}

		$existing = get_option( self::OPTION_DISABLED_TOOLS, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$add = array();

		// v1 — every Pro-badged tool. Only seeded on a truly fresh install
		// (applied < 1); re-running on an upgrade would clobber user re-enables.
		if ( $applied < 1 ) {
			foreach ( $this->get_all_tools() as $category ) {
				foreach ( $category['tools'] as $slug => $tool ) {
					if ( in_array( 'pro', $tool['badges'], true ) || in_array( 'elementor-pro', $tool['badges'], true ) ) {
						$add[] = $slug;
					}
				}
			}
		}

		// v2 — SEO/A11y Pro MCP tools ship disabled-by-default. Adding only the
		// new slugs means an existing user's other choices survive the upgrade.
		if ( $applied < 2 ) {
			$add = array_merge( $add, self::seo_a11y_tool_slugs() );
		}

		// v3 — Widget Builder Pro MCP tools ship disabled-by-default.
		if ( $applied < 3 ) {
			$add = array_merge( $add, self::widget_builder_tool_slugs() );
		}

		// v4 — PHP Snippet (Sandbox) MCP tools ship disabled-by-default.
		if ( $applied < 4 ) {
			$add = array_merge( $add, self::php_snippet_tool_slugs() );
		}

		// v5 — Widget consolidation (3.0.0). The 62 per-widget Pro slugs seeded
		// disabled in v1 no longer exist; strip them so they don't linger in the
		// stored option. add-pro-widget is a single tool, left ENABLED by default
		// (it only registers when Elementor Pro is active anyway).
		if ( $applied < 5 ) {
			$existing = array_values( array_diff( $existing, self::removed_widget_tool_slugs() ) );
		}

		// v6 — Plugins & Themes mutation tools ship disabled-by-default
		// (powerful: install/activate/deactivate/update/delete). Reads stay on.
		if ( $applied < 6 ) {
			$add = array_merge( $add, self::package_write_tool_slugs() );
		}

		// v7 — delete-media ships disabled-by-default (permanent deletion).
		if ( $applied < 7 ) {
			$add = array_merge( $add, self::media_write_tool_slugs() );
		}

		// v8 — Users mutation tools ship disabled-by-default (account changes).
		if ( $applied < 8 ) {
			$add = array_merge( $add, self::user_write_tool_slugs() );
		}

		// v9 — Filesystem mutation tools ship disabled-by-default (write/edit/delete).
		if ( $applied < 9 ) {
			$add = array_merge( $add, self::filesystem_write_tool_slugs() );
		}

		// v10 — Database mutation tools ship disabled-by-default (insert/update/delete).
		if ( $applied < 10 ) {
			$add = array_merge( $add, self::database_write_tool_slugs() );
		}

		// v11 — Themer PHP-template tools ship disabled-by-default (raw PHP; gated
		// behind the master switch too). The admin opts in on the Tools tab.
		if ( $applied < 11 ) {
			$add = array_merge( $add, self::themer_php_tool_slugs() );
		}

		// v14 — ACF is exposed as two dispatcher tools (acf-read / acf-write).
		// The write dispatcher ships disabled-by-default; the read dispatcher
		// stays on. Also strip any pre-release per-operation ACF slugs left in
		// the stored option from the earlier 15-tool layout. (Supersedes the
		// v12/v13 per-tool ACF seeding, which targeted slugs that no longer
		// exist as individual tools.)
		if ( $applied < 14 ) {
			$existing = array_values( array_diff( $existing, self::legacy_acf_operation_slugs() ) );
			$add[]    = 'emcp-tools/acf-write';
		}

		// v15 — set-social-image (Pro SEO) ships disabled-by-default, consistent
		// with the rest of the SEO/A11y toolkit.
		if ( $applied < 15 ) {
			$add[] = 'emcp-tools/set-social-image';
		}

		$merged = array_values( array_unique( array_merge( $existing, $add ) ) );
		update_option( self::OPTION_DISABLED_TOOLS, $merged );
		update_option( self::OPTION_DEFAULTS_APPLIED, (string) self::DEFAULTS_VERSION );
	}

	/**
	 * The per-widget convenience tool slugs removed in 3.0.0 (widget
	 * consolidation). Used by the v5 defaults step to clear orphaned disabled
	 * entries from the stored option.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function removed_widget_tool_slugs(): array {
		return array(
			'emcp-tools/add-widget',
			'emcp-tools/add-heading', 'emcp-tools/add-text-editor', 'emcp-tools/add-image',
			'emcp-tools/add-button', 'emcp-tools/add-video', 'emcp-tools/add-icon',
			'emcp-tools/add-spacer', 'emcp-tools/add-divider', 'emcp-tools/add-icon-box',
			'emcp-tools/add-accordion', 'emcp-tools/add-alert', 'emcp-tools/add-counter',
			'emcp-tools/add-google-maps', 'emcp-tools/add-icon-list', 'emcp-tools/add-image-box',
			'emcp-tools/add-image-carousel', 'emcp-tools/add-progress', 'emcp-tools/add-social-icons',
			'emcp-tools/add-star-rating', 'emcp-tools/add-tabs', 'emcp-tools/add-testimonial',
			'emcp-tools/add-toggle', 'emcp-tools/add-html', 'emcp-tools/add-menu-anchor',
			'emcp-tools/add-shortcode', 'emcp-tools/add-rating', 'emcp-tools/add-text-path',
			'emcp-tools/add-form', 'emcp-tools/add-posts-grid', 'emcp-tools/add-countdown',
			'emcp-tools/add-price-table', 'emcp-tools/add-flip-box', 'emcp-tools/add-animated-headline',
			'emcp-tools/add-call-to-action', 'emcp-tools/add-slides', 'emcp-tools/add-testimonial-carousel',
			'emcp-tools/add-price-list', 'emcp-tools/add-gallery', 'emcp-tools/add-share-buttons',
			'emcp-tools/add-table-of-contents', 'emcp-tools/add-blockquote', 'emcp-tools/add-lottie',
			'emcp-tools/add-hotspot', 'emcp-tools/add-nav-menu', 'emcp-tools/add-loop-grid',
			'emcp-tools/add-loop-carousel', 'emcp-tools/add-media-carousel', 'emcp-tools/add-nested-tabs',
			'emcp-tools/add-nested-accordion', 'emcp-tools/add-portfolio', 'emcp-tools/add-author-box',
			'emcp-tools/add-login', 'emcp-tools/add-code-highlight', 'emcp-tools/add-reviews',
			'emcp-tools/add-off-canvas', 'emcp-tools/add-progress-tracker', 'emcp-tools/add-search',
			'emcp-tools/add-wc-products', 'emcp-tools/add-wc-add-to-cart', 'emcp-tools/add-wc-cart',
			'emcp-tools/add-wc-checkout', 'emcp-tools/add-wc-menu-cart',
		);
	}

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
			$this->hook_suffixes[] = add_submenu_page(
				self::PAGE_SLUG,
				$label,
				$label,
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
			// Changelog lives in the header app-bar, not the sidebar. It stays a
			// real submenu (so it renders + is URL-accessible); we only hide its
			// sidebar row. :has() hides the whole <li>; the anchor rule is a
			// fallback for browsers without :has() (collapses the row to 0).
			. '#toplevel_page_' . esc_attr( self::PAGE_SLUG ) . ' .wp-submenu li:has(> a[href$="page=' . esc_attr( self::PAGE_SLUG ) . '-changelog"]),'
			. '#toplevel_page_' . esc_attr( self::PAGE_SLUG ) . ' .wp-submenu a[href$="page=' . esc_attr( self::PAGE_SLUG ) . '-changelog"]{'
			. 'display:none !important;'
			. '}'
			. '</style>';
	}

	/**
	 * Register the settings with the WordPress Settings API.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_DISABLED_TOOLS,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_disabled_tools' ),
			)
		);

		// Compact tool mode (dispatcher) — Tools tab. OFF by default; surfaces 3
		// meta-tools (list-tools / get-tool-schema / call-tool) instead of every
		// individual tool for clients that cap the tool count. Registered under the
		// Tools form group so its toggle lives alongside the per-tool grid.
		register_setting(
			self::SETTINGS_GROUP,
			EMCP_Tools_Plugin::OPTION_DISPATCHER_MODE,
			array(
				'type'              => 'string',
				'default'           => '0',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
			)
		);

		// Themer PHP Templates master switch (Tools tab). Off by default — the
		// feature lets AI author raw PHP region templates, so the admin opts in.
		register_setting(
			self::SETTINGS_GROUP,
			EMCP_Tools_Themer_PHP::OPTION_ENABLED,
			array(
				'type'              => 'string',
				'default'           => '0',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
			)
		);

		// "Activate Abilities API for EMCP" server gate (Connection tab). On by
		// default; an absent checkbox on submit sanitizes to '0' (off).
		register_setting(
			self::SETTINGS_GROUP_SERVER,
			EMCP_Tools_Plugin::OPTION_SERVER_ENABLED,
			array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
			)
		);

		// OpenAI-strict tool schemas (Connection tab). OFF by default — it's only
		// for OpenAI-compatible strict function-calling clients (CrewAI, etc.) and
		// would otherwise break Gemini/Antigravity. (GitHub #42)
		register_setting(
			self::SETTINGS_GROUP_SERVER,
			'emcp_tools_strict_schemas',
			array(
				'type'              => 'string',
				'default'           => '0',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
			)
		);

		// Stock-image provider API keys (Connection → 3rd Party Services sub-tab)
		// — power the stock-image tools (search-images / add-stock-image). All
		// three are free keys. Registered in their own group so that sub-tab's
		// form saves without touching the server-gate toggles. Keys are stored
		// encrypted at rest (EMCP_Tools_Secret) and never rendered back to the
		// form: the field posts empty when unchanged (we keep the stored value),
		// a per-field "__clear" checkbox removes it, and a new value is encrypted.
		foreach ( array( EMCP_Tools_Unsplash_Client::OPTION, EMCP_Tools_Pexels_Client::OPTION, EMCP_Tools_Pixabay_Client::OPTION ) as $emcp_stock_option ) {
			register_setting(
				self::SETTINGS_GROUP_SERVICES,
				$emcp_stock_option,
				array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => static function ( $value ) use ( $emcp_stock_option ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verifies the settings-group nonce before this runs.
						if ( ! empty( $_POST[ $emcp_stock_option . '__clear' ] ) ) {
							return '';
						}
						$value = sanitize_text_field( (string) $value );
						if ( '' === $value ) {
							// Unchanged (masked) submit — keep the stored value.
							return (string) get_option( $emcp_stock_option, '' );
						}
						// The Settings API can run this callback twice per save;
						// don't re-encrypt an already-encrypted token (would nest).
						if ( EMCP_Tools_Secret::is_encrypted( $value ) ) {
							return $value;
						}
						return EMCP_Tools_Secret::encrypt( $value );
					},
				)
			);
		}

		// Context page — the site-wide guidance + its on/off toggle.
		register_setting(
			self::SETTINGS_GROUP_CONTEXT,
			EMCP_Tools_Site_Context::OPTION_CONTEXT,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => static function ( $value ) {
					$value = sanitize_textarea_field( (string) $value );
					return mb_substr( $value, 0, EMCP_Tools_Site_Context::MAX_CHARS );
				},
			)
		);
		register_setting(
			self::SETTINGS_GROUP_CONTEXT,
			EMCP_Tools_Site_Context::OPTION_ENABLED,
			array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
			)
		);

		// Modules tab — the active-modules list + each registered module's own
		// option keys (declared by the module's settings_fields()).
		register_setting(
			self::SETTINGS_GROUP_MODULES,
			EMCP_Tools_Module::OPTION_ACTIVE,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => static function ( $value ) {
					$value = is_array( $value ) ? $value : array();
					return array_values( array_map( 'sanitize_key', $value ) );
				},
			)
		);
		if ( class_exists( 'EMCP_Tools_Modules_Registry' ) ) {
			foreach ( EMCP_Tools_Modules_Registry::instance()->all() as $emcp_module ) {
				// Each module's keys live in the module's own group so its overlay
				// settings form saves independently of the active-modules toggles.
				$emcp_group = $emcp_module->settings_group();
				foreach ( $emcp_module->settings_fields() as $emcp_key => $emcp_args ) {
					register_setting( $emcp_group, $emcp_key, $emcp_args );
				}
			}
		}
	}

	/**
	 * Sanitize the disabled tools option value.
	 *
	 * The form submits an array of enabled tool slugs. We compute the
	 * disabled list as the difference between all known tools and the
	 * enabled ones submitted.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input The raw form input.
	 * @return string[] Sanitized array of disabled tool slugs.
	 */
	public function sanitize_disabled_tools( $input ): array {
		$all_tools = $this->get_all_tool_slugs();

		// Only when the Tools settings form is being submitted do we INVERT the
		// posted "enabled" checkboxes into a disabled list. We read the enabled
		// set straight from $_POST (not from $input) so this callback is
		// IDEMPOTENT: WordPress re-runs sanitize_option a second time via
		// add_option() the first time the option is created, and inverting
		// $input twice would zero the result (all -> none). It also keeps
		// programmatic update_option() calls (e.g. the default-disabled seeder)
		// from being inverted at all.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verifies the settings nonce before sanitization runs.
		$is_settings_form = isset( $_POST['option_page'] )
			&& self::SETTINGS_GROUP === sanitize_text_field( wp_unslash( $_POST['option_page'] ) );

		if ( $is_settings_form ) {
			$enabled = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ self::OPTION_DISABLED_TOOLS ] ) && is_array( $_POST[ self::OPTION_DISABLED_TOOLS ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$enabled = array_map( 'sanitize_text_field', wp_unslash( $_POST[ self::OPTION_DISABLED_TOOLS ] ) );
			}
			// Disabled = all tools minus the ones that were checked (enabled).
			return array_values( array_diff( $all_tools, $enabled ) );
		}

		// Any other context: $input is already the final disabled list (e.g. the
		// default-disabled seeder). Clean against the known slugs and return —
		// this is idempotent, so a second sanitize pass leaves it unchanged.
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_values( array_intersect( $all_tools, array_map( 'sanitize_text_field', $input ) ) );
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

		$css_path = EMCP_TOOLS_DIR . 'assets/css/admin.css';
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
				EMCP_TOOLS_URL . 'assets/css/admin.css',
				array(),
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

		wp_localize_script(
			'elementor-mcp-admin',
			'emcpToolsAdmin',
			array(
				'copied'      => __( 'Copied!', 'emcp-tools' ),
				'copy'        => __( 'Copy', 'emcp-tools' ),
				'download'    => __( 'Download', 'emcp-tools' ),
				'mcpEndpoint' => rest_url( 'mcp/emcp-tools-server' ),
				'siteUrl'     => site_url(),
				'restMeUrl'   => rest_url( 'wp/v2/users/me' ),
				// Only the filename — never the absolute server path. The proxy runs
				// on the CLIENT machine, so the server path is both useless to the
				// user and a needless path disclosure (F-020). The UI points users at
				// the npx runner or their own local copy of the proxy.
				'proxyPath'   => 'mcp-proxy.mjs',
				// Connection auth self-test (#41).
				'authTesting' => __( 'Testing…', 'emcp-tools' ),
				'authOk'      => __( '✓ Authentication works — your AI client should connect successfully.', 'emcp-tools' ),
				'authFail'    => __( '✗ Authentication failed (HTTP %d). If the credentials are correct, your server is stripping the Authorization header — see the fix below.', 'emcp-tools' ),
				'authError'   => __( 'Could not reach the REST API to test. Check the site URL and that the REST API is enabled.', 'emcp-tools' ),
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'createPwNonce' => wp_create_nonce( 'emcp_tools_create_app_password' ),
				'generating'    => __( 'Generating…', 'emcp-tools' ),
				'pwCreated'     => __( 'Application password created — save it below, it is shown only once.', 'emcp-tools' ),
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
						'unsaved'       => __( 'Unsaved changes — click Save Modules to apply.', 'emcp-tools' ),
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
		echo esc_html__( 'A required script is missing — assets/js/admin.js was not found in the plugin folder, so admin features like the Connection-tab config generator will not work.', 'emcp-tools' );
		echo ' ';
		if ( '' !== $mangled ) {
			printf(
				/* translators: %s: the mangled filename found, e.g. admin.j_ */
				esc_html__( 'It looks like security software renamed it to assets/js/%s — rename that file back to admin.js.', 'emcp-tools' ),
				esc_html( $mangled )
			);
		} else {
			echo esc_html__( 'Some security software and hosts rename or quarantine .js files on upload. Re-upload a fresh copy of the plugin from the official release, and restore assets/js/admin.js if your host renamed it.', 'emcp-tools' );
		}
		echo '</p></div>';
	}

	/**
	 * AJAX: create a fresh Application Password for a chosen administrator.
	 *
	 * Returns the chunked plaintext password once so the Connection tab can drop
	 * it straight into the generated client configs — no profile visit needed.
	 *
	 * @since 1.8.3
	 */
	public function ajax_create_app_password(): void {
		check_ajax_referer( 'emcp_tools_create_app_password', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'No user selected.', 'emcp-tools' ) ), 400 );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'That user no longer exists.', 'emcp-tools' ) ), 404 );
		}

		// Only administrators, and only those the current user is allowed to edit.
		if ( ! user_can( $user, 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Application passwords can only be generated for administrator accounts here.', 'emcp-tools' ) ), 403 );
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot manage application passwords for this user.', 'emcp-tools' ) ), 403 );
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_send_json_error( array( 'message' => __( 'Application Passwords are not supported on this WordPress version.', 'emcp-tools' ) ), 400 );
		}

		// Application passwords only authenticate over HTTPS (or a local environment),
		// so refuse to mint one that could not actually be used to connect.
		if ( ! is_ssl() && 'local' !== wp_get_environment_type() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Application Passwords require HTTPS. Load this site over https:// (or use the WP-CLI connection method for local development).', 'emcp-tools' ),
				),
				400
			);
		}

		$app_name = sprintf(
			/* translators: %s: current date and time */
			__( 'EMCP Tools (MCP) — %s', 'emcp-tools' ),
			gmdate( 'Y-m-d H:i' )
		);

		$created = \WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => $app_name ) );

		if ( is_wp_error( $created ) ) {
			wp_send_json_error( array( 'message' => $created->get_error_message() ), 400 );
		}

		$raw_password = isset( $created[0] ) ? $created[0] : '';
		if ( '' === $raw_password ) {
			wp_send_json_error( array( 'message' => __( 'Could not create an application password.', 'emcp-tools' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'username' => $user->user_login,
				'password' => \WP_Application_Passwords::chunk_password( $raw_password ),
				'name'     => $app_name,
			)
		);
	}

	/**
	 * AJAX: activate/deactivate a generated widget from the Widget Builder tab.
	 *
	 * @since 1.9.0
	 */
	public function ajax_toggle_widget(): void {
		check_ajax_referer( 'emcp_tools_widgets', 'nonce' );
		if ( ! class_exists( 'EMCP_Tools_Widget_Store' ) || ! EMCP_Tools_Widget_Store::user_has_access() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
		$widget_id = isset( $_POST['widget_id'] ) ? absint( wp_unslash( $_POST['widget_id'] ) ) : 0;
		$status    = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! $widget_id || ! in_array( $status, array( 'active', 'draft' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'emcp-tools' ) ), 400 );
		}
		$res = EMCP_Tools_Widget_Store::set_status( $widget_id, $status );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		wp_send_json_success( $res );
	}

	/**
	 * AJAX: delete a generated widget from the Widget Builder tab.
	 *
	 * @since 1.9.0
	 */
	public function ajax_delete_widget(): void {
		check_ajax_referer( 'emcp_tools_widgets', 'nonce' );
		if ( ! class_exists( 'EMCP_Tools_Widget_Store' ) || ! EMCP_Tools_Widget_Store::user_has_access() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
		$widget_id = isset( $_POST['widget_id'] ) ? absint( wp_unslash( $_POST['widget_id'] ) ) : 0;
		if ( ! $widget_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'emcp-tools' ) ), 400 );
		}
		$res = EMCP_Tools_Widget_Store::delete( $widget_id );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		wp_send_json_success( $res );
	}

	/**
	 * AJAX: create or update a PHP snippet draft from the Sandbox tab. Validates
	 * and refuses critical findings (returning them so the form can show why).
	 *
	 * @since 2.1.0
	 */
	public function ajax_save_php_snippet(): void {
		check_ajax_referer( 'emcp_tools_php_snippets', 'nonce' );
		if ( ! class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) || ! EMCP_Tools_PHP_Snippet_Store::can_edit() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage PHP snippets (requires manage_options and unfiltered_html).', 'emcp-tools' ) ), 403 );
		}
		$id = isset( $_POST['snippet_id'] ) ? absint( wp_unslash( $_POST['snippet_id'] ) ) : 0;
		// Code is raw PHP: keep it verbatim (unslash only). It is never executed
		// here — it is validated and stored; execution requires later activation.
		$args = array(
			'title'    => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'code'     => isset( $_POST['code'] ) ? wp_unslash( (string) $_POST['code'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw PHP source, validated by the snippet validator, never executed here.
			'context'  => isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : 'shortcode',
			'hook'     => isset( $_POST['hook'] ) ? sanitize_text_field( wp_unslash( $_POST['hook'] ) ) : '',
			'priority' => isset( $_POST['priority'] ) ? absint( wp_unslash( $_POST['priority'] ) ) : 10,
		);

		$res = $id
			? EMCP_Tools_PHP_Snippet_Store::update( $id, $args )
			: EMCP_Tools_PHP_Snippet_Store::create_draft( $args );

		if ( is_wp_error( $res ) ) {
			$data    = $res->get_error_data();
			$payload = array( 'message' => $res->get_error_message() );
			if ( is_array( $data ) && isset( $data['validation'] ) ) {
				$payload['validation'] = $data['validation'];
			}
			wp_send_json_error( $payload, 400 );
		}
		wp_send_json_success( $res );
	}

	/**
	 * AJAX: activate/deactivate a PHP snippet (the human approval gate).
	 * Activation re-validates and writes the executable file.
	 *
	 * @since 2.1.0
	 */
	public function ajax_toggle_php_snippet(): void {
		check_ajax_referer( 'emcp_tools_php_snippets', 'nonce' );
		if ( ! class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) || ! EMCP_Tools_PHP_Snippet_Store::can_edit() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
		$id     = isset( $_POST['snippet_id'] ) ? absint( wp_unslash( $_POST['snippet_id'] ) ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! $id || ! in_array( $status, array( 'active', 'draft' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'emcp-tools' ) ), 400 );
		}
		$res = EMCP_Tools_PHP_Snippet_Store::set_status( $id, $status );
		if ( is_wp_error( $res ) ) {
			$data    = $res->get_error_data();
			$payload = array( 'message' => $res->get_error_message() );
			if ( is_array( $data ) && isset( $data['validation'] ) ) {
				$payload['validation'] = $data['validation'];
			}
			wp_send_json_error( $payload, 400 );
		}
		wp_send_json_success( $res );
	}

	/**
	 * AJAX: delete a PHP snippet from the Sandbox tab.
	 *
	 * @since 2.1.0
	 */
	public function ajax_delete_php_snippet(): void {
		check_ajax_referer( 'emcp_tools_php_snippets', 'nonce' );
		if ( ! class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) || ! EMCP_Tools_PHP_Snippet_Store::can_edit() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
		$id = isset( $_POST['snippet_id'] ) ? absint( wp_unslash( $_POST['snippet_id'] ) ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'emcp-tools' ) ), 400 );
		}
		$res = EMCP_Tools_PHP_Snippet_Store::delete( $id );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		wp_send_json_success( $res );
	}

	/**
	 * admin-post.php callback: build + stream a Claude Desktop .mcpb bundle
	 * with the chosen admin's credentials baked in. POST body: user_id,
	 * app_password, _emcp_nonce. Halts execution at the end.
	 *
	 * @since 3.0.0
	 */
	public function handle_download_mcpb(): void {
		if (
			! isset( $_POST['_emcp_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_emcp_nonce'] ) ), self::NONCE_DOWNLOAD_MCPB )
		) {
			wp_die( esc_html__( 'Invalid request.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$user    = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user || ! current_user_can( 'edit_user', $user_id ) || ! user_can( $user_id, 'manage_options' ) ) {
			wp_die( esc_html__( 'Pick a valid administrator account.', 'emcp-tools' ), '', array( 'response' => 400 ) );
		}

		// The app password was generated on the page (Step 1) and POSTed back —
		// same-origin, nonce-gated, the admin's own credential.
		$app_password = isset( $_POST['app_password'] ) ? sanitize_text_field( wp_unslash( $_POST['app_password'] ) ) : '';
		if ( '' === $app_password ) {
			wp_die( esc_html__( 'Generate an Application Password first, then download the bundle.', 'emcp-tools' ), '', array( 'response' => 400 ) );
		}

		$manifest = EMCP_Tools_Mcpb_Builder::build_manifest( home_url(), $user->user_login, $app_password );
		$tmp      = EMCP_Tools_Mcpb_Builder::build_zip( $manifest );
		if ( is_wp_error( $tmp ) ) {
			wp_die( esc_html( $tmp->get_error_message() ), '', array( 'response' => 500 ) );
		}

		// Safety net: the temp file holds a live Application Password. Guarantee
		// it is removed even if streaming aborts (fatal, memory limit, etc.) —
		// the explicit unlink after readfile() handles the normal fast path.
		register_shutdown_function(
			static function () use ( $tmp ) {
				if ( file_exists( $tmp ) ) {
					@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
			}
		);

		$host     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$filename = 'emcp-tools-' . sanitize_file_name( $host ?: 'site' ) . '.mcpb';

		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		header( 'X-Content-Type-Options: nosniff' );
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		readfile( $tmp );
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		exit;
	}

	/**
	 * Build the headline stat cards shown on the Dashboard.
	 *
	 * Always includes Total Tools, Active, and Pro Tools. Prompts, Brand Kits,
	 * and Templates are appended only when their module is active (and, for the
	 * Pro-gated counts, when a value is available) — mirroring the module-tab
	 * visibility rules. Each entry is `key`/`value`/`label`; the view maps `key`
	 * to an icon.
	 *
	 * @since 3.1.0
	 * @return array<int,array{key:string,value:int,label:string}>
	 */
	public function get_dashboard_stats(): array {
		$stats = array(
			array( 'key' => 'tools', 'value' => (int) $this->get_total_tool_count(), 'label' => __( 'Total Tools', 'emcp-tools' ) ),
			array( 'key' => 'active', 'value' => (int) $this->get_enabled_tool_count(), 'label' => __( 'Active', 'emcp-tools' ) ),
		);

		// Count Pro tools.
		$pro_count = 0;
		foreach ( $this->get_all_tools() as $category ) {
			foreach ( $category['tools'] as $tool ) {
				if ( in_array( 'pro', $tool['badges'], true ) || in_array( 'elementor-pro', $tool['badges'], true ) ) {
					$pro_count++;
				}
			}
		}
		$stats[] = array( 'key' => 'pro', 'value' => $pro_count, 'label' => __( 'Pro Tools', 'emcp-tools' ) );

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
			$emcp_tpl_bundle = EMCP_Tools_Pro_Templates::get_bundle();
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

		?>
		<div class="wrap elementor-mcp-admin">
			<h1><?php esc_html_e( 'MCP Tools for WordPress & Page Builders', 'emcp-tools' ); ?></h1>

			<?php
			// Success notice after a Settings API save (options.php redirects back
			// with settings-updated=true). Shown for any EMCP settings tab.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- options.php verifies the settings nonce before redirecting.
			if ( isset( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) :
				?>
				<div class="notice notice-success is-dismissible">
					<p><strong><?php esc_html_e( 'Settings saved.', 'emcp-tools' ); ?></strong></p>
				</div>
				<?php
			endif;
			?>

			<?php
			// Only show the upgrade CTA to sites without a valid Pro license.
			// Freemius adds its own Contact / Account / Upgrade items to the
			// EMCP Tools menu, so we don't need a redundant header link.
			$emcp_tools_show_upgrade = ! function_exists( 'emcp_tools_fs' )
				|| ! emcp_tools_fs()->can_use_premium_code();
			?>

			<!-- App bar -->
			<div class="emcp-appbar">
				<div class="emcp-appbar-brand">
					<img class="emcp-appbar-logo" src="<?php echo esc_url( EMCP_TOOLS_URL . 'assets/img/icon-sm.png' ); ?>" alt="" />
					<span class="emcp-appbar-title emcp-appbar-title--full"><?php esc_html_e( 'MCP Tools for WordPress & Page Builders', 'emcp-tools' ); ?></span>
					<span class="emcp-appbar-title emcp-appbar-title--short"><?php esc_html_e( 'MCP Tools', 'emcp-tools' ); ?></span>
					<span class="emcp-appbar-version">v<?php echo esc_html( EMCP_TOOLS_VERSION ); ?></span>
				</div>
				<div class="emcp-appbar-actions">
					<a class="emcp-appbar-changelog<?php echo 'changelog' === $active_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-changelog' ) ); ?>">
						<span class="dashicons dashicons-backup" aria-hidden="true"></span>
						<?php esc_html_e( 'Changelog', 'emcp-tools' ); ?>
					</a>
					<?php if ( function_exists( 'emcp_tools_fs' ) ) : ?>
						<a class="emcp-appbar-changelog" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-affiliation' ) ); ?>">
							<span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
							<?php esc_html_e( 'Affiliate Program', 'emcp-tools' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $emcp_tools_show_upgrade ) : ?>
						<a class="emcp-appbar-upgrade" href="<?php echo esc_url( emcp_tools_upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
							<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
							<?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?>
						</a>
					<?php endif; ?>
					<div class="emcp-help-menu">
						<button type="button" class="emcp-help-toggle" aria-haspopup="true">
							<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
							<?php esc_html_e( 'Help & Support', 'emcp-tools' ); ?>
							<span class="dashicons dashicons-arrow-down-alt2 emcp-help-caret" aria-hidden="true"></span>
						</button>
						<div class="emcp-help-dropdown" role="menu">
							<a role="menuitem" href="https://support.msrbuilds.com/" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-sos" aria-hidden="true"></span><?php esc_html_e( 'Ticket Support', 'emcp-tools' ); ?></a>
							<a role="menuitem" href="https://emcptools.com/docs" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-book" aria-hidden="true"></span><?php esc_html_e( 'Documentation', 'emcp-tools' ); ?></a>
							<a role="menuitem" href="https://www.facebook.com/groups/emcptools" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-groups" aria-hidden="true"></span><?php esc_html_e( 'Community', 'emcp-tools' ); ?></a>
							<a role="menuitem" href="https://discord.gg/vJfksd3S9j" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><?php esc_html_e( 'Discord', 'emcp-tools' ); ?></a>
							<a role="menuitem" href="https://emcptools.com/tutorials" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-video-alt3" aria-hidden="true"></span><?php esc_html_e( 'Tutorials', 'emcp-tools' ); ?></a>
						</div>
					</div>
				</div>
			</div>

			<!-- Tab nav -->
						<div class="emcp-appnav-wrap">
				<button type="button" class="emcp-appnav-arrow emcp-appnav-arrow--prev" aria-label="<?php esc_attr_e( 'Scroll tabs left', 'emcp-tools' ); ?>" hidden><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button>
<nav class="emcp-appnav" aria-label="<?php esc_attr_e( 'EMCP Tools sections', 'emcp-tools' ); ?>">
				<?php
				foreach ( $this->get_submenus() as $emcp_slug => $emcp_label ) :
					$emcp_tab_id = ( self::PAGE_SLUG === $emcp_slug ) ? 'dashboard' : substr( $emcp_slug, strlen( self::PAGE_SLUG . '-' ) );
					// Changelog lives in the app-bar top-right, not the tab nav.
					if ( 'changelog' === $emcp_tab_id ) {
						continue;
					}
					$emcp_is_on = ( $emcp_tab_id === $active_tab );
					?>
					<a class="emcp-appnav-item<?php echo $emcp_is_on ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=' . $emcp_slug ) ); ?>"
						<?php echo $emcp_is_on ? 'aria-current="page"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( self::tab_icon( $emcp_tab_id ) ); ?>" aria-hidden="true"></span>
						<span class="emcp-appnav-label"><?php echo esc_html( $emcp_label ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
				<button type="button" class="emcp-appnav-arrow emcp-appnav-arrow--next" aria-label="<?php esc_attr_e( 'Scroll tabs right', 'emcp-tools' ); ?>" hidden><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>
			</div>

			<!-- Content -->
			<div class="tab-content<?php echo 'dashboard' === $active_tab ? ' tab-content--flush' : ''; ?>">
				<?php
				if ( 'dashboard' === $active_tab ) {
					include EMCP_TOOLS_DIR . 'includes/admin/views/page-dashboard.php';
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
				} elseif ( 'widgets' === $active_tab ) {
					include EMCP_TOOLS_DIR . 'includes/admin/views/page-widgets.php';
				} elseif ( 'changelog' === $active_tab ) {
					include EMCP_TOOLS_DIR . 'includes/admin/views/page-changelog.php';
				} else {
					include EMCP_TOOLS_DIR . 'includes/admin/views/page-tools.php';
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * The ordered platform sub-tabs for the Tools page. Keyed by the `platform`
	 * value a category carries; the value is the display label. A future page
	 * builder is added by giving its categories a new platform value and adding
	 * a matching entry here.
	 *
	 * @since 3.0.0
	 * @return array<string,string>
	 */
	public static function platform_tabs(): array {
		return array(
			'elementor' => __( 'Elementor', 'emcp-tools' ),
			'wordpress' => __( 'WordPress', 'emcp-tools' ),
			'plugins'   => __( 'Plugins', 'emcp-tools' ),
			'gutenberg' => __( 'Gutenberg', 'emcp-tools' ),
		);
	}

	/**
	 * Connection-tab client registry: the single source of truth for the
	 * client cards grid + per-client reveal. `methods` declares WHICH options
	 * a client supports; the actual JSON/CLI/prompt strings are assembled
	 * client-side in admin.js from the generated credentials.
	 *
	 * `cli` is a printf-style template with these tokens, substituted in JS:
	 *   %ENDPOINT% (REST MCP url), %B64% (base64 user:app-password).
	 *
	 * @since 3.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function connection_clients(): array {
		$claude_cli = 'claude mcp add --transport http %NAME% "%ENDPOINT%" --header "Authorization: Basic %B64%"';
		$codex_cli  = 'codex mcp add %NAME% --transport http --url "%ENDPOINT%" --header "Authorization=Basic %B64%"';

		// Codex's "Connect to a custom MCP" UI form — a field-by-field mapping so
		// users know which Connection value goes where. %ENDPOINT%/%B64% are filled
		// with the live endpoint + Basic-auth token in JS (escaped). HTML tags are
		// kept outside the translation calls so they are not escaped.
		$codex_guide = '<p class="description">'
			. esc_html__( 'Prefer Codex\'s UI? Choose “Connect to a custom MCP” → “Streamable HTTP”, then fill the form like this:', 'emcp-tools' )
			. '</p>'
			. '<table class="emcp-conn-guide"><tbody>'
			. '<tr><th>' . esc_html__( 'Name', 'emcp-tools' ) . '</th><td><code>%NAME%</code></td></tr>'
			. '<tr><th>' . esc_html__( 'Transport', 'emcp-tools' ) . '</th><td>' . esc_html__( 'Streamable HTTP', 'emcp-tools' ) . '</td></tr>'
			. '<tr><th>' . esc_html__( 'URL', 'emcp-tools' ) . '</th><td><code>%ENDPOINT%</code></td></tr>'
			. '<tr><th>' . esc_html__( 'Bearer token env var', 'emcp-tools' ) . '</th><td>' . esc_html__( 'Leave blank — EMCP uses a WordPress Application Password (HTTP Basic), not a bearer token.', 'emcp-tools' ) . '</td></tr>'
			. '<tr><th>' . esc_html__( 'Headers', 'emcp-tools' ) . '</th><td>' . esc_html__( 'Key', 'emcp-tools' ) . ' <code>Authorization</code> &middot; ' . esc_html__( 'Value', 'emcp-tools' ) . ' <code>Basic %B64%</code></td></tr>'
			. '</tbody></table>'
			. '<p class="description">' . esc_html__( 'Then Save. The config blocks below do the same thing — “direct HTTP” for the URL + header approach, or the “Node proxy / npx” config if the HTTP transport gives you handshake trouble.', 'emcp-tools' ) . '</p>';

		return array(
			array(
				'id'      => 'claude-desktop',
				'label'   => __( 'Claude Desktop', 'emcp-tools' ),
				'icon'    => 'desktop',
				'image'   => 'claude.png',
				'methods' => array( 'bundle' => true, 'cli' => null, 'ai_prompt' => true, 'json' => array( 'npx', 'http' ) ),
			),
			array(
				'id'      => 'claude-code',
				'label'   => __( 'Claude Code', 'emcp-tools' ),
				'icon'    => 'editor-code',
				'image'   => 'claude.png',
				'methods' => array( 'bundle' => false, 'cli' => $claude_cli, 'ai_prompt' => false, 'json' => array( 'npx', 'http' ) ),
			),
			array(
				'id'      => 'cursor',
				'label'   => __( 'Cursor', 'emcp-tools' ),
				'icon'    => 'editor-code',
				'image'   => 'cursor.png',
				'methods' => array( 'bundle' => false, 'cli' => null, 'ai_prompt' => true, 'json' => array( 'http' ) ),
			),
			array(
				'id'          => 'codex',
				'label'       => __( 'Codex', 'emcp-tools' ),
				'icon'        => 'editor-code',
				'image'       => 'gpt.png',
				'guide_title' => __( 'Using the Codex “Custom MCP” form', 'emcp-tools' ),
				'guide'       => $codex_guide,
				'methods'     => array( 'bundle' => false, 'cli' => $codex_cli, 'ai_prompt' => false, 'json' => array( 'toml', 'toml-stdio' ) ),
			),
			array(
				'id'      => 'antigravity',
				'label'   => __( 'Antigravity', 'emcp-tools' ),
				'icon'    => 'editor-code',
				'image'   => 'antigravity.png',
				'methods' => array( 'bundle' => false, 'cli' => null, 'ai_prompt' => false, 'json' => array( 'http' ) ),
			),
			array(
				'id'      => 'mcp-remote',
				'label'   => __( 'npx mcp-remote', 'emcp-tools' ),
				'icon'    => 'admin-links',
				'methods' => array( 'bundle' => false, 'cli' => null, 'ai_prompt' => false, 'json' => array( 'remote' ) ),
			),
		);
	}

	/**
	 * Group a tool-category map into one bucket per platform tab, preserving
	 * category order within each bucket. A category with a missing or unknown
	 * `platform` falls into the default ('elementor') bucket.
	 *
	 * @since 3.0.0
	 * @param array $categories Category map (id => category array) from get_all_tools().
	 * @return array<string,array> [ 'elementor' => [...], 'wordpress' => [...] ]
	 */
	public static function partition_by_platform( array $categories ): array {
		$buckets = array();
		foreach ( array_keys( self::platform_tabs() ) as $tab_id ) {
			$buckets[ $tab_id ] = array();
		}
		foreach ( $categories as $id => $cat ) {
			$platform = ( isset( $cat['platform'] ) && isset( $buckets[ $cat['platform'] ] ) ) ? $cat['platform'] : 'elementor';
			$buckets[ $platform ][ $id ] = $cat;
		}
		// Sort danger categories (filesystem/database) to the end of their tab —
		// the most powerful/destructive groups live at the bottom. Relative order
		// is otherwise preserved.
		foreach ( $buckets as $tab_id => $cats ) {
			$normal = array();
			$danger = array();
			foreach ( $cats as $id => $cat ) {
				if ( ! empty( $cat['danger'] ) ) {
					$danger[ $id ] = $cat;
				} else {
					$normal[ $id ] = $cat;
				}
			}
			$buckets[ $tab_id ] = $normal + $danger;
		}
		return $buckets;
	}

	/**
	 * Whether a tool category belongs to the Elementor platform (the default
	 * when no platform key is set), i.e. it is unavailable when Elementor
	 * is inactive.
	 *
	 * @since 3.0.0
	 *
	 * @param array $category A get_all_tools() category entry.
	 * @return bool
	 */
	public static function is_elementor_category( array $category ): bool {
		return 'elementor' === ( $category['platform'] ?? 'elementor' );
	}

	/**
	 * Returns the categories with the Elementor-platform ones removed. Used for
	 * truthful tool counts when Elementor is inactive (those tools never register).
	 *
	 * @since 3.0.0
	 *
	 * @param array $categories get_all_tools() output.
	 * @return array
	 */
	public static function filter_out_elementor( array $categories ): array {
		return array_filter(
			$categories,
			static function ( $cat ) {
				return ! self::is_elementor_category( $cat );
			}
		);
	}

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
				self::themer_php_tool_slugs(),
				self::acf_tool_slugs(),
				array( 'emcp-tools/resize-media' )
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

		return $catalog;
	}

	/**
	 * The curated admin tool catalog: every tool grouped by category with its
	 * label, description, and badges for the Tools admin screen. This is the
	 * source of the admin-UI metadata; get_all_tools() keeps it honest against
	 * the ability registry.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{label: string, tools: array<string, array{label: string, description: string, badges: string[]}>}> Grouped tools.
	 */
	private function get_tool_catalog(): array {
		$tools = array(
			'query'            => array(
				'platform' => 'elementor',
				'label' => __( 'Query & Discovery', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-widgets'         => array(
						'label'       => __( 'List Widgets', 'emcp-tools' ),
						'description' => __( 'Lists all available Elementor widget types and their names.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-widget-schema'    => array(
						'label'       => __( 'Get Widget Schema', 'emcp-tools' ),
						'description' => __( 'Returns the JSON schema for a specific widget type.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-page-structure'   => array(
						'label'       => __( 'Get Page Structure', 'emcp-tools' ),
						'description' => __( 'Returns the full Elementor element tree for a page.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-page-snapshot'    => array(
						'label'       => __( 'Get Page Snapshot', 'emcp-tools' ),
						'description' => __( 'One normalized page digest: structure, tokens-in-use, responsive overrides, content outline, SEO-lite (+ opt-in performance/a11y/seo).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-element-settings' => array(
						'label'       => __( 'Get Element Settings', 'emcp-tools' ),
						'description' => __( 'Returns the settings of a specific element by ID.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/list-pages'           => array(
						'label'       => __( 'List Pages', 'emcp-tools' ),
						'description' => __( 'Lists all pages/posts that use Elementor.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/list-templates'       => array(
						'label'       => __( 'List Templates', 'emcp-tools' ),
						'description' => __( 'Lists all saved Elementor templates.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-global-settings'  => array(
						'label'       => __( 'Get Global Settings', 'emcp-tools' ),
						'description' => __( 'Returns global colors, typography, and theme settings.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
				),
			),
			'gutenberg_blocks' => array(
				'platform' => 'gutenberg',
				'label' => __( 'Gutenberg Blocks', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-blocks'      => array(
						'label'       => __( 'List Blocks', 'emcp-tools' ),
						'description' => __( 'Lists registered block types (name, title, category).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-block-schema' => array(
						'label'       => __( 'Get Block Schema', 'emcp-tools' ),
						'description' => __( 'Returns a block\'s attributes, supports, and a markup example.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-post-blocks'  => array(
						'label'       => __( 'Get Post Blocks', 'emcp-tools' ),
						'description' => __( 'Returns a post\'s block tree with an index path per block.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/list-patterns'    => array(
						'label'       => __( 'List Patterns', 'emcp-tools' ),
						'description' => __( 'Lists registered block patterns.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/add-block'        => array(
						'label'       => __( 'Add Block', 'emcp-tools' ),
						'description' => __( 'Inserts block markup into a post at a position.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-block'     => array(
						'label'       => __( 'Update Block', 'emcp-tools' ),
						'description' => __( 'Replaces the block at an index path with new markup.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/remove-block'     => array(
						'label'       => __( 'Remove Block', 'emcp-tools' ),
						'description' => __( 'Deletes the block at an index path.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/move-block'       => array(
						'label'       => __( 'Move Block', 'emcp-tools' ),
						'description' => __( 'Moves a block to a new position.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/duplicate-block'  => array(
						'label'       => __( 'Duplicate Block', 'emcp-tools' ),
						'description' => __( 'Clones the block at a path and inserts the copy after it.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/insert-pattern'   => array(
						'label'       => __( 'Insert Pattern', 'emcp-tools' ),
						'description' => __( 'Inserts a registered block pattern into a post.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'wp_content'       => array(
				'platform' => 'wordpress',
				'label' => __( 'WordPress Content', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-post-types' => array(
						'label'       => __( 'List Post Types', 'emcp-tools' ),
						'description' => __( 'Lists registered post types (posts, pages, CPTs).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/list-taxonomies' => array(
						'label'       => __( 'List Taxonomies', 'emcp-tools' ),
						'description' => __( 'Lists taxonomies and optionally their terms.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/create-post'     => array(
						'label'       => __( 'Create Post', 'emcp-tools' ),
						'description' => __( 'Creates a post/page/CPT with content, terms, meta, featured image.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/get-post'        => array(
						'label'       => __( 'Get Post', 'emcp-tools' ),
						'description' => __( 'Returns a post\'s content, terms, meta, and featured image.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/update-post'     => array(
						'label'       => __( 'Update Post', 'emcp-tools' ),
						'description' => __( 'Partial update of a post/page/CPT.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/list-posts'      => array(
						'label'       => __( 'List Posts', 'emcp-tools' ),
						'description' => __( 'Lists/searches posts, pages, or any CPT (compact).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/delete-post'     => array(
						'label'       => __( 'Delete Post', 'emcp-tools' ),
						'description' => __( 'Trashes (or force-deletes) a post.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/set-post-terms'  => array(
						'label'       => __( 'Set Post Terms', 'emcp-tools' ),
						'description' => __( 'Assigns category/tag/custom terms to a post.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'wp_settings'      => array(
				'platform' => 'wordpress',
				'label' => __( 'WordPress Settings', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/get-settings'    => array(
						'label'       => __( 'Get Settings', 'emcp-tools' ),
						'description' => __( 'Reads curated site settings (general, reading, writing, discussion, media, permalinks).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/update-settings' => array(
						'label'       => __( 'Update Settings', 'emcp-tools' ),
						'description' => __( 'Updates curated site settings; auto-flushes rewrite rules on permalink changes.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'performance'      => array(
				'platform' => 'wordpress',
				'label' => __( 'Performance & Security', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/analyze-performance' => array(
						'label'       => __( 'Analyze Performance', 'emcp-tools' ),
						'description' => __( 'Audits server config, WordPress internals, and a target page; returns a scored report with recommendations.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/scan-security' => array(
						'label'       => __( 'Scan Security', 'emcp-tools' ),
						'description' => __( 'Scans for malware heuristics, core file integrity, configuration hardening, and outdated/abandoned software; returns a scored report with recommendations.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
				),
			),
			'filesystem'       => array(
				'platform' => 'wordpress',
				'danger'   => true,
				'label' => __( 'Filesystem', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/read-file'      => array( 'label' => __( 'Read File', 'emcp-tools' ),      'description' => __( 'Read a file in the WordPress install.', 'emcp-tools' ),          'badges' => array( 'read-only' ) ),
					'emcp-tools/list-directory' => array( 'label' => __( 'List Directory', 'emcp-tools' ), 'description' => __( 'List a directory in the WordPress install.', 'emcp-tools' ),      'badges' => array( 'read-only' ) ),
					'emcp-tools/search-files'   => array( 'label' => __( 'Search Files', 'emcp-tools' ),   'description' => __( 'Search file contents across the install.', 'emcp-tools' ),        'badges' => array( 'read-only' ) ),
					'emcp-tools/write-file'     => array( 'label' => __( 'Write File', 'emcp-tools' ),     'description' => __( 'Create/overwrite a file (backs up first). Disabled by default.', 'emcp-tools' ), 'badges' => array() ),
					'emcp-tools/edit-file'      => array( 'label' => __( 'Edit File', 'emcp-tools' ),      'description' => __( 'Replace a string in a file (backs up first). Disabled by default.', 'emcp-tools' ),  'badges' => array() ),
					'emcp-tools/delete-file'    => array( 'label' => __( 'Delete File', 'emcp-tools' ),    'description' => __( 'Delete a file (backs up; needs confirm). Disabled by default.', 'emcp-tools' ),     'badges' => array() ),
				),
			),
			'database'         => array(
				'platform' => 'wordpress',
				'danger'   => true,
				'label' => __( 'Database', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-tables'    => array( 'label' => __( 'List Tables', 'emcp-tools' ),    'description' => __( 'List database tables with sizes.', 'emcp-tools' ),                'badges' => array( 'read-only' ) ),
					'emcp-tools/describe-table' => array( 'label' => __( 'Describe Table', 'emcp-tools' ), 'description' => __( 'Show a table\'s columns and keys.', 'emcp-tools' ),               'badges' => array( 'read-only' ) ),
					'emcp-tools/query'          => array( 'label' => __( 'Query (read-only)', 'emcp-tools' ), 'description' => __( 'Run a read-only SQL query (SELECT/SHOW/etc.).', 'emcp-tools' ), 'badges' => array( 'read-only' ) ),
					'emcp-tools/insert-row'     => array( 'label' => __( 'Insert Row', 'emcp-tools' ),     'description' => __( 'Insert a row (parameterized). Disabled by default.', 'emcp-tools' ),   'badges' => array() ),
					'emcp-tools/update-rows'    => array( 'label' => __( 'Update Rows', 'emcp-tools' ),    'description' => __( 'Update rows matching a WHERE. Disabled by default.', 'emcp-tools' ),   'badges' => array() ),
					'emcp-tools/delete-rows'    => array( 'label' => __( 'Delete Rows', 'emcp-tools' ),    'description' => __( 'Delete rows matching a WHERE (confirm). Disabled by default.', 'emcp-tools' ), 'badges' => array() ),
				),
			),
			'transactions'     => array(
				'platform' => 'wordpress',
				'label' => __( 'Changes & Rollback', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-changes'    => array( 'label' => __( 'List Changes', 'emcp-tools' ),    'description' => __( 'List recent AI-made changes (Elementor/filesystem/database), newest first.', 'emcp-tools' ), 'badges' => array( 'read-only' ) ),
					'emcp-tools/get-change'      => array( 'label' => __( 'Get Change', 'emcp-tools' ),      'description' => __( 'Full detail of one change-ledger entry, including its rollback reference.', 'emcp-tools' ), 'badges' => array( 'read-only' ) ),
					'emcp-tools/rollback-change' => array( 'label' => __( 'Roll Back Change', 'emcp-tools' ), 'description' => __( 'Undo one recorded change by id (page/file/database). Only reverts changes EMCP recorded.', 'emcp-tools' ), 'badges' => array() ),
				),
			),
			'search'           => array(
				'platform' => 'wordpress',
				'label' => __( 'Content Search', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/search-content'  => array( 'label' => __( 'Search Content', 'emcp-tools' ),  'description' => __( 'Search the site\'s pages, templates, widgets, and global styles to reuse existing content.', 'emcp-tools' ), 'badges' => array( 'read-only' ) ),
					'emcp-tools/reindex-search'  => array( 'label' => __( 'Reindex Search', 'emcp-tools' ),  'description' => __( 'Rebuild the content-search index (also updates on save).', 'emcp-tools' ), 'badges' => array() ),
				),
			),
			'wp_packages'      => array(
				'platform' => 'wordpress',
				'label' => __( 'Plugins & Themes', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-plugins'      => array(
						'label'       => __( 'List Plugins', 'emcp-tools' ),
						'description' => __( 'Lists installed plugins, status, versions, and updates.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/search-plugins'    => array(
						'label'       => __( 'Search Plugins', 'emcp-tools' ),
						'description' => __( 'Searches the wordpress.org plugin directory.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/install-plugin'    => array(
						'label'       => __( 'Install Plugin', 'emcp-tools' ),
						'description' => __( 'Installs a plugin from wordpress.org by slug.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/activate-plugin'   => array(
						'label'       => __( 'Activate Plugin', 'emcp-tools' ),
						'description' => __( 'Activates an installed plugin.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/deactivate-plugin' => array(
						'label'       => __( 'Deactivate Plugin', 'emcp-tools' ),
						'description' => __( 'Deactivates a plugin (never EMCP Tools or Elementor).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-plugin'     => array(
						'label'       => __( 'Update Plugin', 'emcp-tools' ),
						'description' => __( 'Updates a plugin to the latest wordpress.org version.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-plugin'     => array(
						'label'       => __( 'Delete Plugin', 'emcp-tools' ),
						'description' => __( 'Permanently deletes an inactive plugin.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/list-themes'       => array(
						'label'       => __( 'List Themes', 'emcp-tools' ),
						'description' => __( 'Lists installed themes, active status, and updates.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/search-themes'     => array(
						'label'       => __( 'Search Themes', 'emcp-tools' ),
						'description' => __( 'Searches the wordpress.org theme directory.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/install-theme'     => array(
						'label'       => __( 'Install Theme', 'emcp-tools' ),
						'description' => __( 'Installs a theme from wordpress.org by slug.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/switch-theme'      => array(
						'label'       => __( 'Switch Theme', 'emcp-tools' ),
						'description' => __( 'Activates an installed theme.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-theme'      => array(
						'label'       => __( 'Update Theme', 'emcp-tools' ),
						'description' => __( 'Updates a theme to the latest wordpress.org version.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-theme'      => array(
						'label'       => __( 'Delete Theme', 'emcp-tools' ),
						'description' => __( 'Permanently deletes an inactive theme.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
				),
			),
			'wp_users'         => array(
				'platform' => 'wordpress',
				'label' => __( 'Users', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-users'   => array(
						'label'       => __( 'List Users', 'emcp-tools' ),
						'description' => __( 'Lists users (admin-only); filter by role/search.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-user'     => array(
						'label'       => __( 'Get User', 'emcp-tools' ),
						'description' => __( 'Returns one user\'s profile detail.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/create-user'  => array(
						'label'       => __( 'Create User', 'emcp-tools' ),
						'description' => __( 'Creates a non-admin user; auto-password + email.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-user'  => array(
						'label'       => __( 'Update User', 'emcp-tools' ),
						'description' => __( 'Edits a non-admin user\'s profile (no role/password; admins refused).', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'wp_acf'           => array(
				'platform' => 'plugins',
				'label'    => __( 'ACF (Advanced Custom Fields)', 'emcp-tools' ),
				'note'     => __( 'Plugin integrations are exposed as two tools — one Read, one Write. The AI calls a tool with an operation name; each tool bundles the operations listed on its card. Toggle a tool to allow or block all of its operations at once. Post-type & taxonomy operations need ACF 6.1+.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/acf-read'  => array(
						'label'       => __( 'ACF Read', 'emcp-tools' ),
						'description' => __( 'Read Advanced Custom Fields data — field groups, field values, options pages, and (ACF 6.1+) ACF-managed post types and taxonomies.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
						'operations'  => array(
							'list-field-groups',
							'get-field-group',
							'list-options-pages',
							'get-fields',
							'list-post-types',
							'get-post-type',
							'list-taxonomies',
							'get-taxonomy',
						),
					),
					'emcp-tools/acf-write' => array(
						'label'       => __( 'ACF Write', 'emcp-tools' ),
						'description' => __( 'Write Advanced Custom Fields data — field values, field groups, and (ACF 6.1+) ACF-managed post types and taxonomies. No delete operations; slugs and field keys are immutable.', 'emcp-tools' ),
						'badges'      => array(),
						'operations'  => array(
							'update-fields',
							'create-field-group',
							'update-field-group',
							'create-post-type',
							'update-post-type',
							'create-taxonomy',
							'update-taxonomy',
						),
					),
				),
			),
			'page'             => array(
				'platform' => 'elementor',
				'label' => __( 'Page Management', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/create-page'          => array(
						'label'       => __( 'Create Page', 'emcp-tools' ),
						'description' => __( 'Creates a new WordPress page with Elementor enabled.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-page-settings' => array(
						'label'       => __( 'Update Page Settings', 'emcp-tools' ),
						'description' => __( 'Updates Elementor page-level settings (layout, canvas, etc).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-page-content'  => array(
						'label'       => __( 'Delete Page Content', 'emcp-tools' ),
						'description' => __( 'Removes all Elementor content from a page.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/import-template'      => array(
						'label'       => __( 'Import Template', 'emcp-tools' ),
						'description' => __( 'Imports an Elementor template JSON into a page.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/export-page'          => array(
						'label'       => __( 'Export Page', 'emcp-tools' ),
						'description' => __( 'Exports a page\'s Elementor data as JSON.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
				),
			),
			'layout'           => array(
				'platform' => 'elementor',
				'label' => __( 'Layout & Structure', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/add-container'     => array(
						'label'       => __( 'Add Container', 'emcp-tools' ),
						'description' => __( 'Adds a new flexbox container to a page or inside another container.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/move-element'      => array(
						'label'       => __( 'Move Element', 'emcp-tools' ),
						'description' => __( 'Moves an element to a new parent or position.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/remove-element'    => array(
						'label'       => __( 'Remove Element', 'emcp-tools' ),
						'description' => __( 'Removes an element and all its children from the page.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/duplicate-element'    => array(
						'label'       => __( 'Duplicate Element', 'emcp-tools' ),
						'description' => __( 'Creates a deep copy of an element and inserts it after the original.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-container'     => array(
						'label'       => __( 'Update Container', 'emcp-tools' ),
						'description' => __( 'Updates settings on an existing container element.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/get-container-schema' => array(
						'label'       => __( 'Get Container Schema', 'emcp-tools' ),
						'description' => __( 'Returns the JSON schema for container settings.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/find-element'         => array(
						'label'       => __( 'Find Element', 'emcp-tools' ),
						'description' => __( 'Finds elements by type, settings, or CSS class within a page.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/update-element'       => array(
						'label'       => __( 'Update Element', 'emcp-tools' ),
						'description' => __( 'Updates settings on any element (widget or container) by ID. Also writes v4 atomic styles / editor_settings when included.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/batch-update'         => array(
						'label'       => __( 'Batch Update', 'emcp-tools' ),
						'description' => __( 'Applies multiple element updates in a single call.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/set-element-label'    => array(
						'label'       => __( 'Set Element Label', 'emcp-tools' ),
						'description' => __( 'Sets an element\'s Navigator label (editor_settings.title).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/reorder-elements'     => array(
						'label'       => __( 'Reorder Elements', 'emcp-tools' ),
						'description' => __( 'Reorders child elements within a container.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'widgets'          => array(
				'platform' => 'elementor',
				'label' => __( 'Widgets', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/add-free-widget' => array(
						'label'       => __( 'Add Widget', 'emcp-tools' ),
						'description' => __( 'Adds any free/core Elementor widget by type (discover with list-widgets / get-widget-schema).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-pro-widget'  => array(
						'label'       => __( 'Add Pro Widget', 'emcp-tools' ),
						'description' => __( 'Adds an Elementor Pro / WooCommerce widget by type. Registers only when Elementor Pro is active.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/update-widget'   => array(
						'label'       => __( 'Update Widget', 'emcp-tools' ),
						'description' => __( 'Updates settings on an existing widget (partial merge).', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'template'         => array(
				'platform' => 'elementor',
				'label' => __( 'Templates', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/save-as-template' => array(
						'label'       => __( 'Save as Template', 'emcp-tools' ),
						'description' => __( 'Saves the current page content as a reusable template.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/apply-template'       => array(
						'label'       => __( 'Apply Template', 'emcp-tools' ),
						'description' => __( 'Applies a saved template to a target page.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/create-elementor-theme-template' => array(
						'label'       => __( 'Create Elementor Theme Template', 'emcp-tools' ),
						'description' => __( 'Creates a native Elementor Pro theme builder template (header, footer, single, archive, etc). For the builder-agnostic EMCP Themer, use create-theme-template.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/set-elementor-template-conditions' => array(
						'label'       => __( 'Set Elementor Template Conditions', 'emcp-tools' ),
						'description' => __( 'Sets display conditions on a native Elementor Pro theme builder template.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/list-dynamic-tags'    => array(
						'label'       => __( 'List Dynamic Tags', 'emcp-tools' ),
						'description' => __( 'Lists all available dynamic tags and their categories.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro', 'read-only' ),
					),
					'emcp-tools/set-dynamic-tag'      => array(
						'label'       => __( 'Set Dynamic Tag', 'emcp-tools' ),
						'description' => __( 'Sets a dynamic tag on a specific element setting.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/create-popup'         => array(
						'label'       => __( 'Create Popup', 'emcp-tools' ),
						'description' => __( 'Creates an Elementor popup template.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/set-popup-settings'   => array(
						'label'       => __( 'Set Popup Settings', 'emcp-tools' ),
						'description' => __( 'Sets triggers, conditions, and timing on a popup template.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
				),
			),
			'global'           => array(
				'platform' => 'elementor',
				'label' => __( 'Global Settings', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/update-global-colors'     => array(
						'label'       => __( 'Update Global Colors', 'emcp-tools' ),
						'description' => __( 'Updates the site-wide Elementor color palette.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-global-typography' => array(
						'label'       => __( 'Update Global Typography', 'emcp-tools' ),
						'description' => __( 'Updates the site-wide Elementor typography presets.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'composite'        => array(
				'platform' => 'elementor',
				'label' => __( 'Composite', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/build-page' => array(
						'label'       => __( 'Build Page', 'emcp-tools' ),
						'description' => __( 'Creates a complete page from a declarative structure in one call.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'stock_images'     => array(
				'platform' => 'wordpress',
				'label' => __( 'Stock & Media Images', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-media'       => array(
						'label'       => __( 'List Media', 'emcp-tools' ),
						'description' => __( 'Lists and searches images already in the WordPress Media Library (the site\'s own uploads).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-media'        => array(
						'label'       => __( 'Get Media', 'emcp-tools' ),
						'description' => __( 'Full detail of one attachment (sizes, metadata, alt/caption).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/update-media'     => array(
						'label'       => __( 'Update Media', 'emcp-tools' ),
						'description' => __( 'Edit an attachment\'s alt text, title, caption, description.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/resize-media'     => array(
						'label'       => __( 'Resize Media', 'emcp-tools' ),
						'description' => __( 'Resize a Media Library image in place (scale to fit or crop), reversible via backup. Registers only when the Image Optimization module is enabled.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-media'     => array(
						'label'       => __( 'Delete Media', 'emcp-tools' ),
						'description' => __( 'Delete an attachment (permanent; requires confirm).', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/search-images'    => array(
						'label'       => __( 'Search Images', 'emcp-tools' ),
						'description' => __( 'Searches Openverse for Creative Commons licensed images.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/sideload-image'   => array(
						'label'       => __( 'Sideload Image', 'emcp-tools' ),
						'description' => __( 'Downloads an external image into the WordPress Media Library.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-stock-image'  => array(
						'label'       => __( 'Add Stock Image', 'emcp-tools' ),
						'description' => __( 'Searches, downloads, and adds a stock image to the page in one call.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'svg_icons'        => array(
				'platform' => 'elementor',
				'label' => __( 'SVG Icons', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/upload-svg-icon'  => array(
						'label'       => __( 'Upload SVG Icon', 'emcp-tools' ),
						'description' => __( 'Uploads an SVG icon (from URL or raw markup) for use with icon/icon-box widgets.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'custom_code'      => array(
				'platform' => 'elementor',
				'label' => __( 'Custom Code', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/add-custom-css'     => array(
						'label'       => __( 'Add Custom CSS', 'emcp-tools' ),
						'description' => __( 'Adds custom CSS to a specific element or the entire page.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/add-custom-js'      => array(
						'label'       => __( 'Add Custom JavaScript', 'emcp-tools' ),
						'description' => __( 'Adds a JavaScript snippet to a page via an HTML widget.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-code-snippet'   => array(
						'label'       => __( 'Add Code Snippet', 'emcp-tools' ),
						'description' => __( 'Creates a site-wide Custom Code snippet for head/body injection.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/list-code-snippets' => array(
						'label'       => __( 'List Code Snippets', 'emcp-tools' ),
						'description' => __( 'Lists all existing Custom Code snippets.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro', 'read-only' ),
					),
				),
			),
		);

		// Atomic elements (Elementor 4.0+). The underlying abilities are only
		// registered when Elementor >= 4.0 is active, so we mirror that gate
		// here to avoid showing toggles for tools that don't exist.
		if ( class_exists( 'EMCP_Tools_Atomic_Props' ) && EMCP_Tools_Atomic_Props::is_atomic_supported() ) {
			$tools['atomic_layout'] = array(
				'platform' => 'elementor',
				'label' => __( 'Atomic Layout (Elementor 4.0+)', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/detect-elementor-version' => array(
						'label'       => __( 'Detect Elementor Version', 'emcp-tools' ),
						'description' => __( 'Returns the Elementor version and whether atomic elements are supported.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/list-global-classes'      => array(
						'label'       => __( 'List Global Classes', 'emcp-tools' ),
						'description' => __( 'Resolves Class Manager "g-" class IDs to their names and CSS properties.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/add-flexbox'              => array(
						'label'       => __( 'Add Flexbox', 'emcp-tools' ),
						'description' => __( 'Adds an atomic flexbox container (e-flexbox).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-div-block'            => array(
						'label'       => __( 'Add Div Block', 'emcp-tools' ),
						'description' => __( 'Adds an atomic div-block container (e-div-block).', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			);

			$tools['atomic_widgets'] = array(
				'platform' => 'elementor',
				'label' => __( 'Atomic Widgets (Elementor 4.0+)', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/add-atomic-widget'    => array(
						'label'       => __( 'Add Atomic Widget', 'emcp-tools' ),
						'description' => __( 'Universal: adds any atomic widget by type with raw $$type settings.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-atomic-widget' => array(
						'label'       => __( 'Update Atomic Widget', 'emcp-tools' ),
						'description' => __( 'Universal: partial-merge update on an existing atomic widget.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-heading'   => array(
						'label'       => __( 'Add Atomic Heading', 'emcp-tools' ),
						'description' => __( 'Adds an atomic heading element (e-heading).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-paragraph' => array(
						'label'       => __( 'Add Atomic Paragraph', 'emcp-tools' ),
						'description' => __( 'Adds an atomic paragraph element (e-paragraph).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-button'    => array(
						'label'       => __( 'Add Atomic Button', 'emcp-tools' ),
						'description' => __( 'Adds an atomic button element (e-button).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-image'     => array(
						'label'       => __( 'Add Atomic Image', 'emcp-tools' ),
						'description' => __( 'Adds an atomic image element (e-image).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-svg'       => array(
						'label'       => __( 'Add Atomic SVG', 'emcp-tools' ),
						'description' => __( 'Adds an atomic SVG element (e-svg).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-youtube'   => array(
						'label'       => __( 'Add Atomic YouTube', 'emcp-tools' ),
						'description' => __( 'Adds an atomic YouTube embed (e-youtube).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-video'     => array(
						'label'       => __( 'Add Atomic Video', 'emcp-tools' ),
						'description' => __( 'Adds an atomic self-hosted video (e-self-hosted-video).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-divider'   => array(
						'label'       => __( 'Add Atomic Divider', 'emcp-tools' ),
						'description' => __( 'Adds an atomic divider element (e-divider).', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			);
		}

		// Brand Kits (Pro). Only shown to licensed sites — the underlying
		// abilities register only for Pro, matching this gate. No 'pro' badge so
		// they are NOT auto-disabled by maybe_apply_default_disabled_tools (this
		// is a headline Pro feature, on by default for licensed users).
		if (
			class_exists( 'EMCP_Tools_Pro_Brand_Kits' )
			&& EMCP_Tools_Pro_Brand_Kits::user_has_access()
		) {
			$tools['brand_kits'] = array(
				'platform' => 'elementor',
				'label' => __( 'Brand Kits', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-brand-kits'           => array(
						'label'       => __( 'List Brand Kits', 'emcp-tools' ),
						'description' => __( 'Lists available premium brand kits from the cached library.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/apply-brand-kit'           => array(
						'label'       => __( 'Apply Brand Kit', 'emcp-tools' ),
						'description' => __( 'Applies a brand kit: replaces system colors + typography site-wide.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/replace-system-colors'     => array(
						'label'       => __( 'Replace System Colors', 'emcp-tools' ),
						'description' => __( 'Replaces the four Elementor system color slots atomically.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/replace-system-typography' => array(
						'label'       => __( 'Replace System Typography', 'emcp-tools' ),
						'description' => __( 'Replaces the four Elementor system typography slots atomically.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
				),
			);
		}

		// PHP Code Snippets (Sandbox) — free, but capability-gated and powerful,
		// so all six ship disabled-by-default (maybe_apply_default_disabled_tools
		// v4) and the admin re-enables them here. There is no "activate" tool: an
		// AI can only create drafts; a human admin activates them on the Sandbox tab.
		$tools['php_snippets'] = array(
			'platform' => 'wordpress',
			'label' => __( 'PHP Snippets (Sandbox)', 'emcp-tools' ),
			'tools' => array(
				'emcp-tools/validate-php-snippet' => array(
					'label'       => __( 'Validate PHP Snippet', 'emcp-tools' ),
					'description' => __( 'Statically checks snippet code (parse + security scan) without storing or running it.', 'emcp-tools' ),
					'badges'      => array( 'read-only' ),
				),
				'emcp-tools/create-php-snippet'   => array(
					'label'       => __( 'Create PHP Snippet', 'emcp-tools' ),
					'description' => __( 'Creates an INACTIVE draft snippet (validated; an admin must activate it before it runs).', 'emcp-tools' ),
					'badges'      => array(),
				),
				'emcp-tools/update-php-snippet'   => array(
					'label'       => __( 'Update PHP Snippet', 'emcp-tools' ),
					'description' => __( 'Updates a snippet\'s code/settings and re-validates.', 'emcp-tools' ),
					'badges'      => array(),
				),
				'emcp-tools/get-php-snippet'      => array(
					'label'       => __( 'Get PHP Snippet', 'emcp-tools' ),
					'description' => __( 'Returns a snippet\'s code, status, shortcode, and validation report.', 'emcp-tools' ),
					'badges'      => array( 'read-only' ),
				),
				'emcp-tools/list-php-snippets'    => array(
					'label'       => __( 'List PHP Snippets', 'emcp-tools' ),
					'description' => __( 'Lists PHP snippets with their status and run context.', 'emcp-tools' ),
					'badges'      => array( 'read-only' ),
				),
				'emcp-tools/delete-php-snippet'   => array(
					'label'       => __( 'Delete PHP Snippet', 'emcp-tools' ),
					'description' => __( 'Permanently deletes a snippet and its sandbox file.', 'emcp-tools' ),
					'badges'      => array( 'destructive' ),
				),
			),
		);

		// Themer PHP Templates — free, capability-gated + master-switch-gated;
		// disabled by default. AI authors DRAFTS; a human attaches one in a
		// template metabox (the execution gate). Registered only when the Themer
		// module is active, alongside where the feature actually lives.
		if ( class_exists( 'EMCP_Tools_Themer_Module' ) && EMCP_Tools_Themer_Module::is_enabled() ) {
			$tools['themer_php'] = array(
				'platform' => 'wordpress',
				'label'    => __( 'Themer PHP Templates', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/create-theme-php-template' => array(
						'label'       => __( 'Create Theme PHP Template', 'emcp-tools' ),
						'description' => __( 'Create a validated DRAFT PHP region template (never runs until a human attaches it).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/list-theme-php-templates'  => array(
						'label'       => __( 'List Theme PHP Templates', 'emcp-tools' ),
						'description' => __( 'List draft PHP templates.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-theme-php-template'    => array(
						'label'       => __( 'Get Theme PHP Template', 'emcp-tools' ),
						'description' => __( 'Return one PHP template with its validation report.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/update-theme-php-template' => array(
						'label'       => __( 'Update Theme PHP Template', 'emcp-tools' ),
						'description' => __( 'Update a PHP template and re-validate.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-theme-php-template' => array(
						'label'       => __( 'Delete Theme PHP Template', 'emcp-tools' ),
						'description' => __( 'Delete a PHP template and its sandbox file.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
				),
			);
		}

		// SEO & Accessibility toolkit (Pro). Shown to licensed sites only —
		// matching the ability gate. Carries the 'pro' badge so they ship
		// disabled-by-default (see maybe_apply_default_disabled_tools v2);
		// users re-enable individual tools here. All five are read-only.
		if ( function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code() ) {
			$tools['seo'] = array(
				'platform' => 'wordpress',
				'label' => __( 'SEO', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/audit-page-seo'                => array(
						'label'       => __( 'Audit Page SEO', 'emcp-tools' ),
						'description' => __( 'Scored on-page SEO report (H1, title/meta, canonical, alts, links, word count).', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/extract-keywords-from-content' => array(
						'label'       => __( 'Extract Keywords', 'emcp-tools' ),
						'description' => __( 'Frequency keyword + phrase extraction from page content.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/generate-meta-tags'            => array(
						'label'       => __( 'Generate Meta Tags', 'emcp-tools' ),
						'description' => __( 'Proposes (apply:true writes to Yoast/Rank Math) an SEO title and meta description. Dry-run by default.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/generate-schema-markup'        => array(
						'label'       => __( 'Generate Schema Markup', 'emcp-tools' ),
						'description' => __( 'Generates (apply:true injects) JSON-LD structured data (Article, LocalBusiness, FAQPage, etc.). Dry-run by default.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/set-social-image'              => array(
						'label'       => __( 'Set Social Image', 'emcp-tools' ),
						'description' => __( 'Sets the Open Graph + Twitter share image (Yoast / Rank Math) so link previews use the image you choose, not the first content image.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
				),
			);

			$tools['a11y'] = array(
				'platform' => 'elementor',
				'label' => __( 'Accessibility', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/audit-page-a11y'           => array(
						'label'       => __( 'Audit Page Accessibility', 'emcp-tools' ),
						'description' => __( 'WCAG-oriented report: contrast, alts, heading order, link text, form labels.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/fix-color-contrast'        => array(
						'label'       => __( 'Fix Color Contrast', 'emcp-tools' ),
						'description' => __( 'Proposes (apply:true to write) adjusted text colors so failing pairs meet WCAG AA. Dry-run by default.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'destructive' ),
					),
					'emcp-tools/add-alt-text-from-context' => array(
						'label'       => __( 'Add Alt Text from Context', 'emcp-tools' ),
						'description' => __( 'Proposes (apply:true to write) alt text for images lacking it, from filename/heading/title. Dry-run by default.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'destructive' ),
					),
				),
			);

			$tools['widget_builder'] = array(
				'platform' => 'elementor',
				'label' => __( 'Widget Builder (Pro)', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-control-types'   => array(
						'label'       => __( 'List Control Types', 'emcp-tools' ),
						'description' => __( 'Returns the control types and template syntax for building widget specs.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/validate-widget-spec' => array(
						'label'       => __( 'Validate Widget Spec', 'emcp-tools' ),
						'description' => __( 'Validates a widget spec and dry-runs the generator without saving.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/create-custom-widget' => array(
						'label'       => __( 'Create Custom Widget', 'emcp-tools' ),
						'description' => __( 'Generates a custom Elementor widget from a spec into an isolated sandbox and activates it.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/update-custom-widget' => array(
						'label'       => __( 'Update Custom Widget', 'emcp-tools' ),
						'description' => __( 'Replaces a custom widget\'s spec and regenerates its code.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/get-custom-widget'    => array(
						'label'       => __( 'Get Custom Widget', 'emcp-tools' ),
						'description' => __( 'Returns a custom widget\'s spec, generated PHP, status, and last error.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/list-custom-widgets'  => array(
						'label'       => __( 'List Custom Widgets', 'emcp-tools' ),
						'description' => __( 'Lists all generated custom widgets with their status.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/set-widget-status'    => array(
						'label'       => __( 'Set Widget Status', 'emcp-tools' ),
						'description' => __( 'Activates or deactivates a custom widget.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/delete-custom-widget' => array(
						'label'       => __( 'Delete Custom Widget', 'emcp-tools' ),
						'description' => __( 'Permanently deletes a custom widget and its sandbox file.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'destructive' ),
					),
				),
			);
		}

		return $tools;
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
		$categories = $this->get_all_tools();
		if ( ! EMCP_Tools_Bootstrap::elementor_active() ) {
			$categories = self::filter_out_elementor( $categories );
		}
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
