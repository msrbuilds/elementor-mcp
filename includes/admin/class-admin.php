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
	 * Option name for the low-tools-mode toggle. When set to '1', tools
	 * outside the curated essentials list are filtered out so clients
	 * with tight tool caps (e.g. Antigravity) stay under their limit.
	 *
	 * @var string
	 */
	const OPTION_LOW_TOOL_MODE = 'emcp_tools_low_tool_mode';

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
	private function get_submenus(): array {
		if ( null === $this->submenus ) {
			$this->submenus = array(
				self::PAGE_SLUG                 => __( 'Tools', 'emcp-tools' ),
				self::PAGE_SLUG . '-connection' => __( 'Connection', 'emcp-tools' ),
				self::PAGE_SLUG . '-prompts'    => __( 'Prompts', 'emcp-tools' ),
				self::PAGE_SLUG . '-templates'  => __( 'Templates', 'emcp-tools' ),
				self::PAGE_SLUG . '-brand-kits' => __( 'Brand Kits', 'emcp-tools' ),
				self::PAGE_SLUG . '-skills'     => __( 'Skills', 'emcp-tools' ),
				self::PAGE_SLUG . '-widgets'    => __( 'Sandbox', 'emcp-tools' ),
				self::PAGE_SLUG . '-changelog'  => __( 'Changelog', 'emcp-tools' ),
			);
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
			case self::PAGE_SLUG . '-connection':
				return 'connection';
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
				return 'tools';
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
	const DEFAULTS_VERSION = 5;

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

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_LOW_TOOL_MODE,
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
			// When low-tools mode is saved ON, the per-tool grid is paused and
			// rendered disabled, so its checkboxes don't post. Preserve the user's
			// stored toggles rather than recomputing them to "all disabled" — the
			// toggles resume when low-tools mode is turned off again.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ self::OPTION_LOW_TOOL_MODE ] ) && '1' === (string) wp_unslash( $_POST[ self::OPTION_LOW_TOOL_MODE ] ) ) {
				$existing = get_option( self::OPTION_DISABLED_TOOLS, array() );
				return is_array( $existing ) ? array_values( array_intersect( $all_tools, $existing ) ) : array();
			}

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
				'restoreConfirm' => __( 'Restore global colors and typography from this backup?', 'emcp-tools' ),
				'viewSite'      => __( 'View site →', 'emcp-tools' ),
			)
		);
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
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab    = $this->get_active_tab();
		$enabled_count = $this->get_enabled_tool_count();
		$total_count   = $this->get_total_tool_count();

		// Count Pro tools.
		$pro_count = 0;
		foreach ( $this->get_all_tools() as $category ) {
			foreach ( $category['tools'] as $tool ) {
				if ( in_array( 'pro', $tool['badges'], true ) || in_array( 'elementor-pro', $tool['badges'], true ) ) {
					$pro_count++;
				}
			}
		}

		// Count prompts. For Pro sites with a synced bundle, use the actual
		// premium-library count (matches what the Prompts tab shows). For
		// everyone else, count the bundled sample files in prompts/.
		$prompt_count = 0;
		if (
			class_exists( 'EMCP_Tools_Pro_Prompts' )
			&& EMCP_Tools_Pro_Prompts::user_has_access()
		) {
			// Durable count: survives transient expiry/eviction and triggers a
			// background refresh when stale (no more resets to the bundled 5).
			$prompt_count = EMCP_Tools_Pro_Prompts::cached_count();
		}
		if ( 0 === $prompt_count ) {
			$prompts_dir  = EMCP_TOOLS_DIR . 'prompts/';
			$prompt_files = is_dir( $prompts_dir ) ? glob( $prompts_dir . '*.md' ) : array();
			$prompt_count = count( $prompt_files );
		}

		// Brand kits: Pro shows the cached remote library count; everyone else
		// shows the bundled free-kit count (applying is a free feature).
		$brand_kit_count = 0;
		$show_brand_kits = false;
		if ( class_exists( 'EMCP_Tools_Pro_Brand_Kits' ) && EMCP_Tools_Pro_Brand_Kits::user_has_access() ) {
			$brand_kit_count = EMCP_Tools_Pro_Brand_Kits::count_cached_kits();
			$show_brand_kits = true;
		} elseif ( class_exists( 'EMCP_Tools_Free_Brand_Kits' ) ) {
			$brand_kit_count = EMCP_Tools_Free_Brand_Kits::count_kits();
			$show_brand_kits = $brand_kit_count > 0;
		}

		?>
		<div class="wrap elementor-mcp-admin">
			<h1><?php esc_html_e( 'MCP Tools for Elementor', 'emcp-tools' ); ?></h1>

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

			<!-- Header -->
			<div class="elementor-mcp-header">
				<span class="elementor-mcp-header-icon">
					<img src="<?php echo esc_url( EMCP_TOOLS_URL . 'assets/img/icon-sm.png' ); ?>" alt="<?php esc_attr_e( 'EMCP Tools', 'emcp-tools' ); ?>" />
				</span>
				<div class="elementor-mcp-header-info">
					<h2 class="elementor-mcp-header-title">
						<?php esc_html_e( 'MCP Tools for Elementor', 'emcp-tools' ); ?>
						<span class="elementor-mcp-header-version">v<?php echo esc_html( EMCP_TOOLS_VERSION ); ?></span>
					</h2>
					<p class="elementor-mcp-header-subtitle"><?php esc_html_e( 'AI-powered page building tools for Elementor via Model Context Protocol.', 'emcp-tools' ); ?></p>
				</div>
				<div class="elementor-mcp-header-actions">
					<a href="https://www.youtube.com/watch?v=tXCpGa-hqxk" class="elementor-mcp-header-btn elementor-mcp-header-btn--secondary" target="_blank" rel="noopener noreferrer">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'Watch Tutorial', 'emcp-tools' ); ?>
					</a>
					<a href="https://emcp.msrbuilds.com/docs" class="elementor-mcp-header-btn elementor-mcp-header-btn--secondary" target="_blank" rel="noopener noreferrer">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'Read the Docs', 'emcp-tools' ); ?>
					</a>
					<a href="https://support.msrbuilds.com/" class="elementor-mcp-header-btn elementor-mcp-header-btn--secondary" target="_blank" rel="noopener noreferrer">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM8.94 6.94a1.5 1.5 0 012.45 1.16c0 .5-.25.78-.86 1.2-.66.45-1.03 1-1.03 1.7v.25a.75.75 0 001.5 0c0-.4.13-.55.7-.94.7-.48 1.19-1.06 1.19-2.06A3 3 0 006.6 7.34a.75.75 0 101.4.52c.1-.27.26-.66.94-.92zM10 14.5a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'Get Support', 'emcp-tools' ); ?>
					</a>
					<a href="https://www.facebook.com/groups/emcptools" class="elementor-mcp-header-btn elementor-mcp-header-btn--secondary" target="_blank" rel="noopener noreferrer">
						<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.43c0-3.014 1.792-4.679 4.533-4.679 1.313 0 2.686.235 2.686.235v2.96h-1.514c-1.491 0-1.956.93-1.956 1.886v2.264h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
						<?php esc_html_e( 'Community', 'emcp-tools' ); ?>
					</a>
					<?php
					// Only show the upgrade CTA to sites without a valid Pro license.
					// Freemius adds its own Contact / Account / Upgrade items to the
					// EMCP Tools menu, so we don't need a redundant header link.
					$emcp_tools_show_upgrade = ! function_exists( 'emcp_tools_fs' )
						|| ! emcp_tools_fs()->can_use_premium_code();
					if ( $emcp_tools_show_upgrade ) : ?>
						<a href="<?php echo esc_url( emcp_tools_upgrade_url() ); ?>" class="elementor-mcp-header-btn elementor-mcp-header-btn--primary" target="_blank" rel="noopener noreferrer">
							<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
							<?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<!-- Stats Bar -->
			<div class="elementor-mcp-stats">
				<div class="elementor-mcp-stat">
					<span class="elementor-mcp-stat-icon elementor-mcp-stat-icon--tools">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
					</span>
					<span class="elementor-mcp-stat-content">
						<span class="elementor-mcp-stat-value"><?php echo esc_html( $total_count ); ?></span>
						<span class="elementor-mcp-stat-label"><?php esc_html_e( 'Total Tools', 'emcp-tools' ); ?></span>
					</span>
				</div>
				<div class="elementor-mcp-stat">
					<span class="elementor-mcp-stat-icon elementor-mcp-stat-icon--active">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>
					</span>
					<span class="elementor-mcp-stat-content">
						<span class="elementor-mcp-stat-value"><?php echo esc_html( $enabled_count ); ?></span>
						<span class="elementor-mcp-stat-label"><?php esc_html_e( 'Active', 'emcp-tools' ); ?></span>
					</span>
				</div>
				<div class="elementor-mcp-stat">
					<span class="elementor-mcp-stat-icon elementor-mcp-stat-icon--pro">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
					</span>
					<span class="elementor-mcp-stat-content">
						<span class="elementor-mcp-stat-value"><?php echo esc_html( $pro_count ); ?></span>
						<span class="elementor-mcp-stat-label"><?php esc_html_e( 'Pro Tools', 'emcp-tools' ); ?></span>
					</span>
				</div>
				<div class="elementor-mcp-stat">
					<span class="elementor-mcp-stat-icon elementor-mcp-stat-icon--prompts">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
					</span>
					<span class="elementor-mcp-stat-content">
						<span class="elementor-mcp-stat-value"><?php echo esc_html( $prompt_count ); ?></span>
						<span class="elementor-mcp-stat-label"><?php esc_html_e( 'Prompts', 'emcp-tools' ); ?></span>
					</span>
				</div>
				<?php if ( $show_brand_kits ) : ?>
					<div class="elementor-mcp-stat">
						<span class="elementor-mcp-stat-icon elementor-mcp-stat-icon--brand-kits">
							<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 5a2 2 0 012-2h3a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm6.5 9.5L12 6l3.8 1.5a1 1 0 01.56 1.3l-3 7.5a2 2 0 01-2.6 1.1l-2.26-.9zM11 4a2 2 0 114 0 2 2 0 01-4 0z"/></svg>
						</span>
						<span class="elementor-mcp-stat-content">
							<span class="elementor-mcp-stat-value"><?php echo esc_html( $brand_kit_count ); ?></span>
							<span class="elementor-mcp-stat-label"><?php esc_html_e( 'Brand Kits', 'emcp-tools' ); ?></span>
						</span>
					</div>
				<?php endif; ?>
			</div>

			<!-- Content -->
			<div class="tab-content">
				<?php
				if ( 'connection' === $active_tab ) {
					include EMCP_TOOLS_DIR . 'includes/admin/views/page-connection.php';
				} elseif ( 'prompts' === $active_tab ) {
					include EMCP_TOOLS_DIR . 'includes/admin/views/page-prompts.php';
				} elseif ( 'templates' === $active_tab ) {
					include EMCP_TOOLS_DIR . 'includes/admin/views/page-templates.php';
				} elseif ( 'brand-kits' === $active_tab ) {
					include EMCP_TOOLS_DIR . 'includes/admin/views/page-brand-kits.php';
				} elseif ( 'skills' === $active_tab ) {
					include EMCP_TOOLS_DIR . 'includes/admin/views/page-skills.php';
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
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'wp_get_ability' ) ) {
			foreach ( $catalog as $emcp_group ) {
				foreach ( array_keys( $emcp_group['tools'] ?? array() ) as $emcp_slug ) {
					if ( ! wp_get_ability( $emcp_slug ) ) {
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
			'wp_content'       => array(
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
			'page'             => array(
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
						'description' => __( 'Updates settings on any element (widget or container) by ID.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/batch-update'         => array(
						'label'       => __( 'Batch Update', 'emcp-tools' ),
						'description' => __( 'Applies multiple element updates in a single call.', 'emcp-tools' ),
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
					'emcp-tools/create-theme-template' => array(
						'label'       => __( 'Create Theme Template', 'emcp-tools' ),
						'description' => __( 'Creates a theme builder template (header, footer, single, archive, etc).', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/set-template-conditions' => array(
						'label'       => __( 'Set Template Conditions', 'emcp-tools' ),
						'description' => __( 'Sets display conditions on a theme builder template.', 'emcp-tools' ),
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
				'label' => __( 'Stock & Media Images', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-media'       => array(
						'label'       => __( 'List Media', 'emcp-tools' ),
						'description' => __( 'Lists and searches images already in the WordPress Media Library (the site\'s own uploads).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
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

		// SEO & Accessibility toolkit (Pro). Shown to licensed sites only —
		// matching the ability gate. Carries the 'pro' badge so they ship
		// disabled-by-default (see maybe_apply_default_disabled_tools v2);
		// users re-enable individual tools here. All five are read-only.
		if ( function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code() ) {
			$tools['seo_a11y'] = array(
				'label' => __( 'SEO & Accessibility', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/audit-page-seo'                 => array(
						'label'       => __( 'Audit Page SEO', 'emcp-tools' ),
						'description' => __( 'Scored on-page SEO report (H1, title/meta, canonical, alts, links, word count).', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/extract-keywords-from-content'  => array(
						'label'       => __( 'Extract Keywords', 'emcp-tools' ),
						'description' => __( 'Frequency keyword + phrase extraction from page content.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/generate-meta-tags'             => array(
						'label'       => __( 'Generate Meta Tags', 'emcp-tools' ),
						'description' => __( 'Proposes (apply:true writes to Yoast/Rank Math) an SEO title and meta description. Dry-run by default.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/generate-schema-markup'         => array(
						'label'       => __( 'Generate Schema Markup', 'emcp-tools' ),
						'description' => __( 'Generates (apply:true injects) JSON-LD structured data (Article, LocalBusiness, FAQPage, etc.). Dry-run by default.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/audit-page-a11y'                => array(
						'label'       => __( 'Audit Page Accessibility', 'emcp-tools' ),
						'description' => __( 'WCAG-oriented report: contrast, alts, heading order, link text, form labels.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/fix-color-contrast'             => array(
						'label'       => __( 'Fix Color Contrast', 'emcp-tools' ),
						'description' => __( 'Proposes (apply:true to write) adjusted text colors so failing pairs meet WCAG AA. Dry-run by default.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'destructive' ),
					),
					'emcp-tools/add-alt-text-from-context'      => array(
						'label'       => __( 'Add Alt Text from Context', 'emcp-tools' ),
						'description' => __( 'Proposes (apply:true to write) alt text for images lacking it, from filename/heading/title. Dry-run by default.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'destructive' ),
					),
				),
			);

			$tools['widget_builder'] = array(
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
	 * Count enabled tools.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of enabled tools.
	 */
	public function get_enabled_tool_count(): int {
		$all = $this->get_all_tool_slugs();

		// Low-tools mode overrides the per-tool toggles (see filter_disabled_tools):
		// exactly the essentials are active.
		if ( '1' === (string) get_option( self::OPTION_LOW_TOOL_MODE, '0' ) ) {
			return count( array_intersect( $all, EMCP_Tools_Plugin::get_essential_tool_slugs() ) );
		}

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
		return count( $this->get_all_tool_slugs() );
	}
}
