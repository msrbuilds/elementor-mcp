<?php
/**
 * Admin settings page for MCP Tools for Elementor.
 *
 * Provides a UI to toggle individual MCP tools on/off and view
 * connection information for various MCP clients.
 *
 * @package Elementor_MCP
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
class Elementor_MCP_Admin {

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
	const OPTION_DISABLED_TOOLS = 'elementor_mcp_disabled_tools';

	/**
	 * Option name for the low-tools-mode toggle. When set to '1', tools
	 * outside the curated essentials list are filtered out so clients
	 * with tight tool caps (e.g. Antigravity) stay under their limit.
	 *
	 * @var string
	 */
	const OPTION_LOW_TOOL_MODE = 'elementor_mcp_low_tool_mode';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'elementor_mcp_settings';

	/**
	 * Dedicated settings group for the "Activate Abilities API for EMCP" server
	 * gate. Kept separate from SETTINGS_GROUP so the Connection-tab toggle form
	 * submits only that option and can't wipe the Tools-page options on save.
	 *
	 * @since 1.7.4
	 * @var string
	 */
	const SETTINGS_GROUP_SERVER = 'elementor_mcp_server_settings';

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'elementor-mcp';

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
				self::PAGE_SLUG                 => __( 'Tools', 'elementor-mcp' ),
				self::PAGE_SLUG . '-connection' => __( 'Connection', 'elementor-mcp' ),
				self::PAGE_SLUG . '-prompts'    => __( 'Prompts', 'elementor-mcp' ),
				self::PAGE_SLUG . '-templates'  => __( 'Templates', 'elementor-mcp' ),
				self::PAGE_SLUG . '-brand-kits' => __( 'Brand Kits', 'elementor-mcp' ),
				self::PAGE_SLUG . '-skills'     => __( 'Skills', 'elementor-mcp' ),
				self::PAGE_SLUG . '-widgets'    => __( 'Widget Builder', 'elementor-mcp' ),
				self::PAGE_SLUG . '-changelog'  => __( 'Changelog', 'elementor-mcp' ),
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
		add_action( 'wp_ajax_elementor_mcp_create_app_password', array( $this, 'ajax_create_app_password' ) );
		add_action( 'wp_ajax_elementor_mcp_toggle_widget', array( $this, 'ajax_toggle_widget' ) );
		add_action( 'wp_ajax_elementor_mcp_delete_widget', array( $this, 'ajax_delete_widget' ) );
	}

	/**
	 * Option that records which version of the default disabled-tools seeding
	 * has been applied. Stored as an integer-ish string: legacy '1' = the
	 * original Pro-widget defaults; '2' adds the SEO/A11y Pro MCP tools.
	 */
	const OPTION_DEFAULTS_APPLIED = 'elementor_mcp_defaults_applied';

	/**
	 * Current defaults-seeding version. Bump when a new batch of slugs should
	 * ship disabled-by-default; add a guarded step in
	 * maybe_apply_default_disabled_tools() for the new version.
	 *
	 * @since 1.8.0
	 */
	const DEFAULTS_VERSION = 3;

	/**
	 * SEO/A11y Pro MCP tool slugs that ship disabled-by-default (v2 defaults).
	 *
	 * @since 1.8.0
	 *
	 * @return string[]
	 */
	public static function seo_a11y_tool_slugs(): array {
		return array(
			'elementor-mcp/audit-page-seo',
			'elementor-mcp/extract-keywords-from-content',
			'elementor-mcp/generate-meta-tags',
			'elementor-mcp/generate-schema-markup',
			'elementor-mcp/audit-page-a11y',
			'elementor-mcp/fix-color-contrast',
			'elementor-mcp/add-alt-text-from-context',
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
			'elementor-mcp/list-control-types',
			'elementor-mcp/validate-widget-spec',
			'elementor-mcp/create-custom-widget',
			'elementor-mcp/update-custom-widget',
			'elementor-mcp/get-custom-widget',
			'elementor-mcp/list-custom-widgets',
			'elementor-mcp/set-widget-status',
			'elementor-mcp/delete-custom-widget',
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
					if ( in_array( 'pro', $tool['badges'], true ) ) {
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

		$merged = array_values( array_unique( array_merge( $existing, $add ) ) );
		update_option( self::OPTION_DISABLED_TOOLS, $merged );
		update_option( self::OPTION_DEFAULTS_APPLIED, (string) self::DEFAULTS_VERSION );
	}

	/**
	 * Add the settings page under the Settings menu.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page(): void {
		$this->hook_suffixes[] = add_menu_page(
			__( 'MCP Tools for Elementor', 'elementor-mcp' ),
			__( 'EMCP Tools', 'elementor-mcp' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			ELEMENTOR_MCP_URL . 'assets/img/icon-xs.png',
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
			Elementor_MCP_Plugin::OPTION_SERVER_ENABLED,
			array(
				'type'              => 'string',
				'default'           => '1',
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
		$enabled_tools = array();

		if ( is_array( $input ) ) {
			$enabled_tools = array_map( 'sanitize_text_field', $input );
		}

		// Get all known tool slugs.
		$all_tools = $this->get_all_tool_slugs();

		// Disabled = all tools minus the ones that were checked.
		$disabled = array_values( array_diff( $all_tools, $enabled_tools ) );

		return $disabled;
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

		$css_path = ELEMENTOR_MCP_DIR . 'assets/css/admin.css';
		$js_path  = ELEMENTOR_MCP_DIR . 'assets/js/admin.js';

		// Use filemtime in dev (when WP_DEBUG is on) so iterating on CSS/JS doesn't get stuck
		// behind a cached file under the same plugin version. Falls back to ELEMENTOR_MCP_VERSION.
		$css_ver = ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $css_path ) ) ? filemtime( $css_path ) : ELEMENTOR_MCP_VERSION;
		$js_ver  = ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $js_path ) ) ? filemtime( $js_path ) : ELEMENTOR_MCP_VERSION;

		wp_enqueue_style(
			'elementor-mcp-admin',
			ELEMENTOR_MCP_URL . 'assets/css/admin.css',
			array(),
			$css_ver
		);

		wp_enqueue_script(
			'elementor-mcp-admin',
			ELEMENTOR_MCP_URL . 'assets/js/admin.js',
			array(),
			$js_ver,
			true
		);

		wp_localize_script(
			'elementor-mcp-admin',
			'elementorMcpAdmin',
			array(
				'copied'      => __( 'Copied!', 'elementor-mcp' ),
				'mcpEndpoint' => rest_url( 'mcp/elementor-mcp-server' ),
				'siteUrl'     => site_url(),
				'proxyPath'   => ELEMENTOR_MCP_DIR . 'bin' . DIRECTORY_SEPARATOR . 'mcp-proxy.mjs',
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'createPwNonce' => wp_create_nonce( 'elementor_mcp_create_app_password' ),
				'generating'    => __( 'Generating…', 'elementor-mcp' ),
				'pwCreated'     => __( 'Application password created — save it below, it is shown only once.', 'elementor-mcp' ),
				'syncing'       => __( 'Syncing…', 'elementor-mcp' ),
				// Brand Kits.
				'applying'      => __( 'Applying…', 'elementor-mcp' ),
				'restoring'     => __( 'Restoring…', 'elementor-mcp' ),
				/* translators: %s: brand kit title */
				'applyKitTitle' => __( 'Apply "%s" brand kit?', 'elementor-mcp' ),
				/* translators: %s: brand kit title */
				'kitApplied'    => __( '%s applied.', 'elementor-mcp' ),
				'restoreConfirm' => __( 'Restore global colors and typography from this backup?', 'elementor-mcp' ),
				'viewSite'      => __( 'View site →', 'elementor-mcp' ),
			)
		);
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
		check_ajax_referer( 'elementor_mcp_create_app_password', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'elementor-mcp' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'No user selected.', 'elementor-mcp' ) ), 400 );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'That user no longer exists.', 'elementor-mcp' ) ), 404 );
		}

		// Only administrators, and only those the current user is allowed to edit.
		if ( ! user_can( $user, 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Application passwords can only be generated for administrator accounts here.', 'elementor-mcp' ) ), 403 );
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot manage application passwords for this user.', 'elementor-mcp' ) ), 403 );
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_send_json_error( array( 'message' => __( 'Application Passwords are not supported on this WordPress version.', 'elementor-mcp' ) ), 400 );
		}

		// Application passwords only authenticate over HTTPS (or a local environment),
		// so refuse to mint one that could not actually be used to connect.
		if ( ! is_ssl() && 'local' !== wp_get_environment_type() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Application Passwords require HTTPS. Load this site over https:// (or use the WP-CLI connection method for local development).', 'elementor-mcp' ),
				),
				400
			);
		}

		$app_name = sprintf(
			/* translators: %s: current date and time */
			__( 'EMCP Tools (MCP) — %s', 'elementor-mcp' ),
			gmdate( 'Y-m-d H:i' )
		);

		$created = \WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => $app_name ) );

		if ( is_wp_error( $created ) ) {
			wp_send_json_error( array( 'message' => $created->get_error_message() ), 400 );
		}

		$raw_password = isset( $created[0] ) ? $created[0] : '';
		if ( '' === $raw_password ) {
			wp_send_json_error( array( 'message' => __( 'Could not create an application password.', 'elementor-mcp' ) ), 500 );
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
		check_ajax_referer( 'elementor_mcp_widgets', 'nonce' );
		if ( ! class_exists( 'Elementor_MCP_Widget_Store' ) || ! Elementor_MCP_Widget_Store::user_has_access() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'elementor-mcp' ) ), 403 );
		}
		$widget_id = isset( $_POST['widget_id'] ) ? absint( wp_unslash( $_POST['widget_id'] ) ) : 0;
		$status    = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! $widget_id || ! in_array( $status, array( 'active', 'draft' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'elementor-mcp' ) ), 400 );
		}
		$res = Elementor_MCP_Widget_Store::set_status( $widget_id, $status );
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
		check_ajax_referer( 'elementor_mcp_widgets', 'nonce' );
		if ( ! class_exists( 'Elementor_MCP_Widget_Store' ) || ! Elementor_MCP_Widget_Store::user_has_access() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'elementor-mcp' ) ), 403 );
		}
		$widget_id = isset( $_POST['widget_id'] ) ? absint( wp_unslash( $_POST['widget_id'] ) ) : 0;
		if ( ! $widget_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'elementor-mcp' ) ), 400 );
		}
		$res = Elementor_MCP_Widget_Store::delete( $widget_id );
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
				if ( in_array( 'pro', $tool['badges'], true ) ) {
					$pro_count++;
				}
			}
		}

		// Count prompts. For Pro sites with a synced bundle, use the actual
		// premium-library count (matches what the Prompts tab shows). For
		// everyone else, count the bundled sample files in prompts/.
		$prompt_count = 0;
		if (
			class_exists( 'Elementor_MCP_Pro_Prompts' )
			&& Elementor_MCP_Pro_Prompts::user_has_access()
		) {
			$bundle = get_transient( Elementor_MCP_Pro_Prompts::CACHE_KEY );
			if ( is_array( $bundle ) && ! empty( $bundle['categories'] ) ) {
				foreach ( $bundle['categories'] as $category ) {
					if ( ! empty( $category['prompts'] ) && is_array( $category['prompts'] ) ) {
						$prompt_count += count( $category['prompts'] );
					}
				}
			}
		}
		if ( 0 === $prompt_count ) {
			$prompts_dir  = ELEMENTOR_MCP_DIR . 'prompts/';
			$prompt_files = is_dir( $prompts_dir ) ? glob( $prompts_dir . '*.md' ) : array();
			$prompt_count = count( $prompt_files );
		}

		// Brand kits: Pro shows the cached remote library count; everyone else
		// shows the bundled free-kit count (applying is a free feature).
		$brand_kit_count = 0;
		$show_brand_kits = false;
		if ( class_exists( 'Elementor_MCP_Pro_Brand_Kits' ) && Elementor_MCP_Pro_Brand_Kits::user_has_access() ) {
			$brand_kit_count = Elementor_MCP_Pro_Brand_Kits::count_cached_kits();
			$show_brand_kits = true;
		} elseif ( class_exists( 'Elementor_MCP_Free_Brand_Kits' ) ) {
			$brand_kit_count = Elementor_MCP_Free_Brand_Kits::count_kits();
			$show_brand_kits = $brand_kit_count > 0;
		}

		?>
		<div class="wrap elementor-mcp-admin">
			<h1><?php esc_html_e( 'MCP Tools for Elementor', 'elementor-mcp' ); ?></h1>

			<!-- Header -->
			<div class="elementor-mcp-header">
				<span class="elementor-mcp-header-icon">
					<img src="<?php echo esc_url( ELEMENTOR_MCP_URL . 'assets/img/icon-sm.png' ); ?>" alt="<?php esc_attr_e( 'EMCP Tools', 'elementor-mcp' ); ?>" />
				</span>
				<div class="elementor-mcp-header-info">
					<h2 class="elementor-mcp-header-title">
						<?php esc_html_e( 'MCP Tools for Elementor', 'elementor-mcp' ); ?>
						<span class="elementor-mcp-header-version">v<?php echo esc_html( ELEMENTOR_MCP_VERSION ); ?></span>
					</h2>
					<p class="elementor-mcp-header-subtitle"><?php esc_html_e( 'AI-powered page building tools for Elementor via Model Context Protocol.', 'elementor-mcp' ); ?></p>
				</div>
				<div class="elementor-mcp-header-actions">
					<a href="https://www.youtube.com/watch?v=tXCpGa-hqxk" class="elementor-mcp-header-btn elementor-mcp-header-btn--secondary" target="_blank" rel="noopener noreferrer">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'Watch Tutorial', 'elementor-mcp' ); ?>
					</a>
					<a href="https://emcp.msrbuilds.com/docs" class="elementor-mcp-header-btn elementor-mcp-header-btn--secondary" target="_blank" rel="noopener noreferrer">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'Read the Docs', 'elementor-mcp' ); ?>
					</a>
					<a href="https://support.msrbuilds.com/" class="elementor-mcp-header-btn elementor-mcp-header-btn--secondary" target="_blank" rel="noopener noreferrer">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM8.94 6.94a1.5 1.5 0 012.45 1.16c0 .5-.25.78-.86 1.2-.66.45-1.03 1-1.03 1.7v.25a.75.75 0 001.5 0c0-.4.13-.55.7-.94.7-.48 1.19-1.06 1.19-2.06A3 3 0 006.6 7.34a.75.75 0 101.4.52c.1-.27.26-.66.94-.92zM10 14.5a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'Get Support', 'elementor-mcp' ); ?>
					</a>
					<?php
					// Only show the upgrade CTA to sites without a valid Pro license.
					// Freemius adds its own Contact / Account / Upgrade items to the
					// EMCP Tools menu, so we don't need a redundant header link.
					$elementor_mcp_show_upgrade = ! function_exists( 'emcp_pro_fs' )
						|| ! emcp_pro_fs()->can_use_premium_code();
					if ( $elementor_mcp_show_upgrade ) : ?>
						<a href="<?php echo esc_url( elementor_mcp_upgrade_url() ); ?>" class="elementor-mcp-header-btn elementor-mcp-header-btn--primary" target="_blank" rel="noopener noreferrer">
							<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
							<?php esc_html_e( 'Upgrade to Pro', 'elementor-mcp' ); ?>
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
						<span class="elementor-mcp-stat-label"><?php esc_html_e( 'Total Tools', 'elementor-mcp' ); ?></span>
					</span>
				</div>
				<div class="elementor-mcp-stat">
					<span class="elementor-mcp-stat-icon elementor-mcp-stat-icon--active">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>
					</span>
					<span class="elementor-mcp-stat-content">
						<span class="elementor-mcp-stat-value"><?php echo esc_html( $enabled_count ); ?></span>
						<span class="elementor-mcp-stat-label"><?php esc_html_e( 'Active', 'elementor-mcp' ); ?></span>
					</span>
				</div>
				<div class="elementor-mcp-stat">
					<span class="elementor-mcp-stat-icon elementor-mcp-stat-icon--pro">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
					</span>
					<span class="elementor-mcp-stat-content">
						<span class="elementor-mcp-stat-value"><?php echo esc_html( $pro_count ); ?></span>
						<span class="elementor-mcp-stat-label"><?php esc_html_e( 'Pro Tools', 'elementor-mcp' ); ?></span>
					</span>
				</div>
				<div class="elementor-mcp-stat">
					<span class="elementor-mcp-stat-icon elementor-mcp-stat-icon--prompts">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
					</span>
					<span class="elementor-mcp-stat-content">
						<span class="elementor-mcp-stat-value"><?php echo esc_html( $prompt_count ); ?></span>
						<span class="elementor-mcp-stat-label"><?php esc_html_e( 'Prompts', 'elementor-mcp' ); ?></span>
					</span>
				</div>
				<?php if ( $show_brand_kits ) : ?>
					<div class="elementor-mcp-stat">
						<span class="elementor-mcp-stat-icon elementor-mcp-stat-icon--brand-kits">
							<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 5a2 2 0 012-2h3a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm6.5 9.5L12 6l3.8 1.5a1 1 0 01.56 1.3l-3 7.5a2 2 0 01-2.6 1.1l-2.26-.9zM11 4a2 2 0 114 0 2 2 0 01-4 0z"/></svg>
						</span>
						<span class="elementor-mcp-stat-content">
							<span class="elementor-mcp-stat-value"><?php echo esc_html( $brand_kit_count ); ?></span>
							<span class="elementor-mcp-stat-label"><?php esc_html_e( 'Brand Kits', 'elementor-mcp' ); ?></span>
						</span>
					</div>
				<?php endif; ?>
			</div>

			<!-- Content -->
			<div class="tab-content">
				<?php
				if ( 'connection' === $active_tab ) {
					include ELEMENTOR_MCP_DIR . 'includes/admin/views/page-connection.php';
				} elseif ( 'prompts' === $active_tab ) {
					include ELEMENTOR_MCP_DIR . 'includes/admin/views/page-prompts.php';
				} elseif ( 'templates' === $active_tab ) {
					include ELEMENTOR_MCP_DIR . 'includes/admin/views/page-templates.php';
				} elseif ( 'brand-kits' === $active_tab ) {
					include ELEMENTOR_MCP_DIR . 'includes/admin/views/page-brand-kits.php';
				} elseif ( 'skills' === $active_tab ) {
					include ELEMENTOR_MCP_DIR . 'includes/admin/views/page-skills.php';
				} elseif ( 'widgets' === $active_tab ) {
					include ELEMENTOR_MCP_DIR . 'includes/admin/views/page-widgets.php';
				} elseif ( 'changelog' === $active_tab ) {
					include ELEMENTOR_MCP_DIR . 'includes/admin/views/page-changelog.php';
				} else {
					include ELEMENTOR_MCP_DIR . 'includes/admin/views/page-tools.php';
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get all tools grouped by category for the UI.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{label: string, tools: array<string, array{label: string, description: string, badges: string[]}>}> Grouped tools.
	 */
	public function get_all_tools(): array {
		$tools = array(
			'query'            => array(
				'label' => __( 'Query & Discovery', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/list-widgets'         => array(
						'label'       => __( 'List Widgets', 'elementor-mcp' ),
						'description' => __( 'Lists all available Elementor widget types and their names.', 'elementor-mcp' ),
						'badges'      => array( 'read-only' ),
					),
					'elementor-mcp/get-widget-schema'    => array(
						'label'       => __( 'Get Widget Schema', 'elementor-mcp' ),
						'description' => __( 'Returns the JSON schema for a specific widget type.', 'elementor-mcp' ),
						'badges'      => array( 'read-only' ),
					),
					'elementor-mcp/get-page-structure'   => array(
						'label'       => __( 'Get Page Structure', 'elementor-mcp' ),
						'description' => __( 'Returns the full Elementor element tree for a page.', 'elementor-mcp' ),
						'badges'      => array( 'read-only' ),
					),
					'elementor-mcp/get-element-settings' => array(
						'label'       => __( 'Get Element Settings', 'elementor-mcp' ),
						'description' => __( 'Returns the settings of a specific element by ID.', 'elementor-mcp' ),
						'badges'      => array( 'read-only' ),
					),
					'elementor-mcp/list-pages'           => array(
						'label'       => __( 'List Pages', 'elementor-mcp' ),
						'description' => __( 'Lists all pages/posts that use Elementor.', 'elementor-mcp' ),
						'badges'      => array( 'read-only' ),
					),
					'elementor-mcp/list-templates'       => array(
						'label'       => __( 'List Templates', 'elementor-mcp' ),
						'description' => __( 'Lists all saved Elementor templates.', 'elementor-mcp' ),
						'badges'      => array( 'read-only' ),
					),
					'elementor-mcp/get-global-settings'  => array(
						'label'       => __( 'Get Global Settings', 'elementor-mcp' ),
						'description' => __( 'Returns global colors, typography, and theme settings.', 'elementor-mcp' ),
						'badges'      => array( 'read-only' ),
					),
				),
			),
			'page'             => array(
				'label' => __( 'Page Management', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/create-page'          => array(
						'label'       => __( 'Create Page', 'elementor-mcp' ),
						'description' => __( 'Creates a new WordPress page with Elementor enabled.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/update-page-settings' => array(
						'label'       => __( 'Update Page Settings', 'elementor-mcp' ),
						'description' => __( 'Updates Elementor page-level settings (layout, canvas, etc).', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/delete-page-content'  => array(
						'label'       => __( 'Delete Page Content', 'elementor-mcp' ),
						'description' => __( 'Removes all Elementor content from a page.', 'elementor-mcp' ),
						'badges'      => array( 'destructive' ),
					),
					'elementor-mcp/import-template'      => array(
						'label'       => __( 'Import Template', 'elementor-mcp' ),
						'description' => __( 'Imports an Elementor template JSON into a page.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/export-page'          => array(
						'label'       => __( 'Export Page', 'elementor-mcp' ),
						'description' => __( 'Exports a page\'s Elementor data as JSON.', 'elementor-mcp' ),
						'badges'      => array( 'read-only' ),
					),
				),
			),
			'layout'           => array(
				'label' => __( 'Layout & Structure', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/add-container'     => array(
						'label'       => __( 'Add Container', 'elementor-mcp' ),
						'description' => __( 'Adds a new flexbox container to a page or inside another container.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/move-element'      => array(
						'label'       => __( 'Move Element', 'elementor-mcp' ),
						'description' => __( 'Moves an element to a new parent or position.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/remove-element'    => array(
						'label'       => __( 'Remove Element', 'elementor-mcp' ),
						'description' => __( 'Removes an element and all its children from the page.', 'elementor-mcp' ),
						'badges'      => array( 'destructive' ),
					),
					'elementor-mcp/duplicate-element'    => array(
						'label'       => __( 'Duplicate Element', 'elementor-mcp' ),
						'description' => __( 'Creates a deep copy of an element and inserts it after the original.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/update-container'     => array(
						'label'       => __( 'Update Container', 'elementor-mcp' ),
						'description' => __( 'Updates settings on an existing container element.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/get-container-schema' => array(
						'label'       => __( 'Get Container Schema', 'elementor-mcp' ),
						'description' => __( 'Returns the JSON schema for container settings.', 'elementor-mcp' ),
						'badges'      => array( 'read-only' ),
					),
					'elementor-mcp/find-element'         => array(
						'label'       => __( 'Find Element', 'elementor-mcp' ),
						'description' => __( 'Finds elements by type, settings, or CSS class within a page.', 'elementor-mcp' ),
						'badges'      => array( 'read-only' ),
					),
					'elementor-mcp/update-element'       => array(
						'label'       => __( 'Update Element', 'elementor-mcp' ),
						'description' => __( 'Updates settings on any element (widget or container) by ID.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/batch-update'         => array(
						'label'       => __( 'Batch Update', 'elementor-mcp' ),
						'description' => __( 'Applies multiple element updates in a single call.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/reorder-elements'     => array(
						'label'       => __( 'Reorder Elements', 'elementor-mcp' ),
						'description' => __( 'Reorders child elements within a container.', 'elementor-mcp' ),
						'badges'      => array(),
					),
				),
			),
			'widget_universal' => array(
				'label' => __( 'Widget Tools', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/add-widget'    => array(
						'label'       => __( 'Add Widget', 'elementor-mcp' ),
						'description' => __( 'Adds any widget type to a container with full settings control.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/update-widget' => array(
						'label'       => __( 'Update Widget', 'elementor-mcp' ),
						'description' => __( 'Updates settings on an existing widget element.', 'elementor-mcp' ),
						'badges'      => array(),
					),
				),
			),
			'widget_core'      => array(
				'label' => __( 'Widget Shortcuts', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/add-heading'     => array(
						'label'       => __( 'Add Heading', 'elementor-mcp' ),
						'description' => __( 'Adds a heading widget with simplified parameters.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-text-editor' => array(
						'label'       => __( 'Add Text Editor', 'elementor-mcp' ),
						'description' => __( 'Adds a text editor (WYSIWYG) widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-image'       => array(
						'label'       => __( 'Add Image', 'elementor-mcp' ),
						'description' => __( 'Adds an image widget by media library ID or URL.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-button'      => array(
						'label'       => __( 'Add Button', 'elementor-mcp' ),
						'description' => __( 'Adds a button widget with text and link.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-video'       => array(
						'label'       => __( 'Add Video', 'elementor-mcp' ),
						'description' => __( 'Adds a video embed widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-icon'        => array(
						'label'       => __( 'Add Icon', 'elementor-mcp' ),
						'description' => __( 'Adds an icon widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-spacer'      => array(
						'label'       => __( 'Add Spacer', 'elementor-mcp' ),
						'description' => __( 'Adds a spacer widget for vertical spacing.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-divider'     => array(
						'label'       => __( 'Add Divider', 'elementor-mcp' ),
						'description' => __( 'Adds a horizontal divider/separator widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-icon-box'        => array(
						'label'       => __( 'Add Icon Box', 'elementor-mcp' ),
						'description' => __( 'Adds an icon box widget (icon + title + description).', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-accordion'       => array(
						'label'       => __( 'Add Accordion', 'elementor-mcp' ),
						'description' => __( 'Adds a collapsible accordion widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-alert'           => array(
						'label'       => __( 'Add Alert', 'elementor-mcp' ),
						'description' => __( 'Adds an alert/notice widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-counter'         => array(
						'label'       => __( 'Add Counter', 'elementor-mcp' ),
						'description' => __( 'Adds an animated counter widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-google-maps'     => array(
						'label'       => __( 'Add Google Maps', 'elementor-mcp' ),
						'description' => __( 'Adds an embedded Google Maps widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-icon-list'       => array(
						'label'       => __( 'Add Icon List', 'elementor-mcp' ),
						'description' => __( 'Adds an icon list widget for feature lists and checklists.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-image-box'       => array(
						'label'       => __( 'Add Image Box', 'elementor-mcp' ),
						'description' => __( 'Adds an image box widget (image + title + description).', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-image-carousel'  => array(
						'label'       => __( 'Add Image Carousel', 'elementor-mcp' ),
						'description' => __( 'Adds a rotating image carousel widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-progress'        => array(
						'label'       => __( 'Add Progress Bar', 'elementor-mcp' ),
						'description' => __( 'Adds an animated progress bar widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-social-icons'    => array(
						'label'       => __( 'Add Social Icons', 'elementor-mcp' ),
						'description' => __( 'Adds social media icon links.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-star-rating'     => array(
						'label'       => __( 'Add Star Rating', 'elementor-mcp' ),
						'description' => __( 'Adds a star rating display widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-tabs'            => array(
						'label'       => __( 'Add Tabs', 'elementor-mcp' ),
						'description' => __( 'Adds a tabbed content widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-testimonial'     => array(
						'label'       => __( 'Add Testimonial', 'elementor-mcp' ),
						'description' => __( 'Adds a testimonial widget with quote and author.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-toggle'          => array(
						'label'       => __( 'Add Toggle', 'elementor-mcp' ),
						'description' => __( 'Adds a toggle/expandable content widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-html'            => array(
						'label'       => __( 'Add HTML', 'elementor-mcp' ),
						'description' => __( 'Adds a custom HTML code widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-menu-anchor'     => array(
						'label'       => __( 'Add Menu Anchor', 'elementor-mcp' ),
						'description' => __( 'Adds an invisible anchor for one-page navigation links.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-shortcode'       => array(
						'label'       => __( 'Add Shortcode', 'elementor-mcp' ),
						'description' => __( 'Adds a shortcode widget to embed WordPress shortcodes.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-rating'          => array(
						'label'       => __( 'Add Rating', 'elementor-mcp' ),
						'description' => __( 'Adds a rating widget with customizable scale and icons.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-text-path'       => array(
						'label'       => __( 'Add Text Path', 'elementor-mcp' ),
						'description' => __( 'Adds a text-on-path widget for curved/circular text.', 'elementor-mcp' ),
						'badges'      => array(),
					),
				),
			),
			'widget_pro'       => array(
				'label' => __( 'Pro Widget Shortcuts', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/add-form'              => array(
						'label'       => __( 'Add Form', 'elementor-mcp' ),
						'description' => __( 'Adds a form widget with configurable fields.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-posts-grid'        => array(
						'label'       => __( 'Add Posts Grid', 'elementor-mcp' ),
						'description' => __( 'Adds a posts grid/listing widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-countdown'         => array(
						'label'       => __( 'Add Countdown', 'elementor-mcp' ),
						'description' => __( 'Adds a countdown timer widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-price-table'       => array(
						'label'       => __( 'Add Price Table', 'elementor-mcp' ),
						'description' => __( 'Adds a pricing table widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-flip-box'          => array(
						'label'       => __( 'Add Flip Box', 'elementor-mcp' ),
						'description' => __( 'Adds a flip box widget with front/back sides.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-animated-headline'    => array(
						'label'       => __( 'Add Animated Headline', 'elementor-mcp' ),
						'description' => __( 'Adds an animated headline widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-call-to-action'       => array(
						'label'       => __( 'Add Call to Action', 'elementor-mcp' ),
						'description' => __( 'Adds a call-to-action widget with title, description, and button.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-slides'               => array(
						'label'       => __( 'Add Slides', 'elementor-mcp' ),
						'description' => __( 'Adds a full-width slides/slider widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-testimonial-carousel'  => array(
						'label'       => __( 'Add Testimonial Carousel', 'elementor-mcp' ),
						'description' => __( 'Adds a testimonial carousel/slider widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-price-list'           => array(
						'label'       => __( 'Add Price List', 'elementor-mcp' ),
						'description' => __( 'Adds a price list widget for menus and services.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-gallery'              => array(
						'label'       => __( 'Add Gallery', 'elementor-mcp' ),
						'description' => __( 'Adds an advanced gallery widget with grid/masonry layout.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-share-buttons'        => array(
						'label'       => __( 'Add Share Buttons', 'elementor-mcp' ),
						'description' => __( 'Adds social share buttons widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-table-of-contents'    => array(
						'label'       => __( 'Add Table of Contents', 'elementor-mcp' ),
						'description' => __( 'Adds an auto-generated table of contents widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-blockquote'           => array(
						'label'       => __( 'Add Blockquote', 'elementor-mcp' ),
						'description' => __( 'Adds a styled blockquote widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-lottie'               => array(
						'label'       => __( 'Add Lottie Animation', 'elementor-mcp' ),
						'description' => __( 'Adds a Lottie animation widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-hotspot'              => array(
						'label'       => __( 'Add Hotspot', 'elementor-mcp' ),
						'description' => __( 'Adds an image hotspot widget with interactive points.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-nav-menu'             => array(
						'label'       => __( 'Add Nav Menu', 'elementor-mcp' ),
						'description' => __( 'Adds a navigation menu widget from registered WordPress menus.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-loop-grid'            => array(
						'label'       => __( 'Add Loop Grid', 'elementor-mcp' ),
						'description' => __( 'Adds a loop grid widget for dynamic post/CPT listings.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-loop-carousel'        => array(
						'label'       => __( 'Add Loop Carousel', 'elementor-mcp' ),
						'description' => __( 'Adds a loop carousel widget for dynamic post/CPT carousels.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-media-carousel'       => array(
						'label'       => __( 'Add Media Carousel', 'elementor-mcp' ),
						'description' => __( 'Adds a media carousel widget for images and videos.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-nested-tabs'          => array(
						'label'       => __( 'Add Nested Tabs', 'elementor-mcp' ),
						'description' => __( 'Adds nested tabs widget where each tab is a container.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-nested-accordion'     => array(
						'label'       => __( 'Add Nested Accordion', 'elementor-mcp' ),
						'description' => __( 'Adds nested accordion widget where each item is a container.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-code-highlight'       => array(
						'label'       => __( 'Add Code Highlight', 'elementor-mcp' ),
						'description' => __( 'Adds a syntax-highlighted code block widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-reviews'              => array(
						'label'       => __( 'Add Reviews', 'elementor-mcp' ),
						'description' => __( 'Adds a reviews/testimonials carousel widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-off-canvas'           => array(
						'label'       => __( 'Add Off-Canvas', 'elementor-mcp' ),
						'description' => __( 'Adds an off-canvas panel widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-progress-tracker'     => array(
						'label'       => __( 'Add Progress Tracker', 'elementor-mcp' ),
						'description' => __( 'Adds a scroll progress tracker widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-search'               => array(
						'label'       => __( 'Add Search', 'elementor-mcp' ),
						'description' => __( 'Adds a search widget with live results support.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
				),
			),
			'template'         => array(
				'label' => __( 'Templates', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/save-as-template' => array(
						'label'       => __( 'Save as Template', 'elementor-mcp' ),
						'description' => __( 'Saves the current page content as a reusable template.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/apply-template'       => array(
						'label'       => __( 'Apply Template', 'elementor-mcp' ),
						'description' => __( 'Applies a saved template to a target page.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/create-theme-template' => array(
						'label'       => __( 'Create Theme Template', 'elementor-mcp' ),
						'description' => __( 'Creates a theme builder template (header, footer, single, archive, etc).', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/set-template-conditions' => array(
						'label'       => __( 'Set Template Conditions', 'elementor-mcp' ),
						'description' => __( 'Sets display conditions on a theme builder template.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/list-dynamic-tags'    => array(
						'label'       => __( 'List Dynamic Tags', 'elementor-mcp' ),
						'description' => __( 'Lists all available dynamic tags and their categories.', 'elementor-mcp' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'elementor-mcp/set-dynamic-tag'      => array(
						'label'       => __( 'Set Dynamic Tag', 'elementor-mcp' ),
						'description' => __( 'Sets a dynamic tag on a specific element setting.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/create-popup'         => array(
						'label'       => __( 'Create Popup', 'elementor-mcp' ),
						'description' => __( 'Creates an Elementor popup template.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/set-popup-settings'   => array(
						'label'       => __( 'Set Popup Settings', 'elementor-mcp' ),
						'description' => __( 'Sets triggers, conditions, and timing on a popup template.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
				),
			),
			'global'           => array(
				'label' => __( 'Global Settings', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/update-global-colors'     => array(
						'label'       => __( 'Update Global Colors', 'elementor-mcp' ),
						'description' => __( 'Updates the site-wide Elementor color palette.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/update-global-typography' => array(
						'label'       => __( 'Update Global Typography', 'elementor-mcp' ),
						'description' => __( 'Updates the site-wide Elementor typography presets.', 'elementor-mcp' ),
						'badges'      => array(),
					),
				),
			),
			'composite'        => array(
				'label' => __( 'Composite', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/build-page' => array(
						'label'       => __( 'Build Page', 'elementor-mcp' ),
						'description' => __( 'Creates a complete page from a declarative structure in one call.', 'elementor-mcp' ),
						'badges'      => array(),
					),
				),
			),
			'stock_images'     => array(
				'label' => __( 'Stock Images', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/search-images'    => array(
						'label'       => __( 'Search Images', 'elementor-mcp' ),
						'description' => __( 'Searches Openverse for Creative Commons licensed images.', 'elementor-mcp' ),
						'badges'      => array( 'read-only' ),
					),
					'elementor-mcp/sideload-image'   => array(
						'label'       => __( 'Sideload Image', 'elementor-mcp' ),
						'description' => __( 'Downloads an external image into the WordPress Media Library.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-stock-image'  => array(
						'label'       => __( 'Add Stock Image', 'elementor-mcp' ),
						'description' => __( 'Searches, downloads, and adds a stock image to the page in one call.', 'elementor-mcp' ),
						'badges'      => array(),
					),
				),
			),
			'svg_icons'        => array(
				'label' => __( 'SVG Icons', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/upload-svg-icon'  => array(
						'label'       => __( 'Upload SVG Icon', 'elementor-mcp' ),
						'description' => __( 'Uploads an SVG icon (from URL or raw markup) for use with icon/icon-box widgets.', 'elementor-mcp' ),
						'badges'      => array(),
					),
				),
			),
			'custom_code'      => array(
				'label' => __( 'Custom Code', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/add-custom-css'     => array(
						'label'       => __( 'Add Custom CSS', 'elementor-mcp' ),
						'description' => __( 'Adds custom CSS to a specific element or the entire page.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-custom-js'      => array(
						'label'       => __( 'Add Custom JavaScript', 'elementor-mcp' ),
						'description' => __( 'Adds a JavaScript snippet to a page via an HTML widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-code-snippet'   => array(
						'label'       => __( 'Add Code Snippet', 'elementor-mcp' ),
						'description' => __( 'Creates a site-wide Custom Code snippet for head/body injection.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/list-code-snippets' => array(
						'label'       => __( 'List Code Snippets', 'elementor-mcp' ),
						'description' => __( 'Lists all existing Custom Code snippets.', 'elementor-mcp' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
				),
			),
		);

		// Atomic elements (Elementor 4.0+). The underlying abilities are only
		// registered when Elementor >= 4.0 is active, so we mirror that gate
		// here to avoid showing toggles for tools that don't exist.
		if ( class_exists( 'Elementor_MCP_Atomic_Props' ) && Elementor_MCP_Atomic_Props::is_atomic_supported() ) {
			$tools['atomic_layout'] = array(
				'label' => __( 'Atomic Layout (Elementor 4.0+)', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/detect-elementor-version' => array(
						'label'       => __( 'Detect Elementor Version', 'elementor-mcp' ),
						'description' => __( 'Returns the Elementor version and whether atomic elements are supported.', 'elementor-mcp' ),
						'badges'      => array( 'read-only' ),
					),
					'elementor-mcp/add-flexbox'              => array(
						'label'       => __( 'Add Flexbox', 'elementor-mcp' ),
						'description' => __( 'Adds an atomic flexbox container (e-flexbox).', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-div-block'            => array(
						'label'       => __( 'Add Div Block', 'elementor-mcp' ),
						'description' => __( 'Adds an atomic div-block container (e-div-block).', 'elementor-mcp' ),
						'badges'      => array(),
					),
				),
			);

			$tools['atomic_widgets'] = array(
				'label' => __( 'Atomic Widgets (Elementor 4.0+)', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/add-atomic-widget'    => array(
						'label'       => __( 'Add Atomic Widget', 'elementor-mcp' ),
						'description' => __( 'Universal: adds any atomic widget by type with raw $$type settings.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/update-atomic-widget' => array(
						'label'       => __( 'Update Atomic Widget', 'elementor-mcp' ),
						'description' => __( 'Universal: partial-merge update on an existing atomic widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-atomic-heading'   => array(
						'label'       => __( 'Add Atomic Heading', 'elementor-mcp' ),
						'description' => __( 'Adds an atomic heading element (e-heading).', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-atomic-paragraph' => array(
						'label'       => __( 'Add Atomic Paragraph', 'elementor-mcp' ),
						'description' => __( 'Adds an atomic paragraph element (e-paragraph).', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-atomic-button'    => array(
						'label'       => __( 'Add Atomic Button', 'elementor-mcp' ),
						'description' => __( 'Adds an atomic button element (e-button).', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-atomic-image'     => array(
						'label'       => __( 'Add Atomic Image', 'elementor-mcp' ),
						'description' => __( 'Adds an atomic image element (e-image).', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-atomic-svg'       => array(
						'label'       => __( 'Add Atomic SVG', 'elementor-mcp' ),
						'description' => __( 'Adds an atomic SVG element (e-svg).', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-atomic-youtube'   => array(
						'label'       => __( 'Add Atomic YouTube', 'elementor-mcp' ),
						'description' => __( 'Adds an atomic YouTube embed (e-youtube).', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-atomic-video'     => array(
						'label'       => __( 'Add Atomic Video', 'elementor-mcp' ),
						'description' => __( 'Adds an atomic self-hosted video (e-self-hosted-video).', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-atomic-divider'   => array(
						'label'       => __( 'Add Atomic Divider', 'elementor-mcp' ),
						'description' => __( 'Adds an atomic divider element (e-divider).', 'elementor-mcp' ),
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
			class_exists( 'Elementor_MCP_Pro_Brand_Kits' )
			&& Elementor_MCP_Pro_Brand_Kits::user_has_access()
		) {
			$tools['brand_kits'] = array(
				'label' => __( 'Brand Kits', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/list-brand-kits'           => array(
						'label'       => __( 'List Brand Kits', 'elementor-mcp' ),
						'description' => __( 'Lists available premium brand kits from the cached library.', 'elementor-mcp' ),
						'badges'      => array( 'read-only' ),
					),
					'elementor-mcp/apply-brand-kit'           => array(
						'label'       => __( 'Apply Brand Kit', 'elementor-mcp' ),
						'description' => __( 'Applies a brand kit: replaces system colors + typography site-wide.', 'elementor-mcp' ),
						'badges'      => array( 'destructive' ),
					),
					'elementor-mcp/replace-system-colors'     => array(
						'label'       => __( 'Replace System Colors', 'elementor-mcp' ),
						'description' => __( 'Replaces the four Elementor system color slots atomically.', 'elementor-mcp' ),
						'badges'      => array( 'destructive' ),
					),
					'elementor-mcp/replace-system-typography' => array(
						'label'       => __( 'Replace System Typography', 'elementor-mcp' ),
						'description' => __( 'Replaces the four Elementor system typography slots atomically.', 'elementor-mcp' ),
						'badges'      => array( 'destructive' ),
					),
				),
			);
		}

		// SEO & Accessibility toolkit (Pro). Shown to licensed sites only —
		// matching the ability gate. Carries the 'pro' badge so they ship
		// disabled-by-default (see maybe_apply_default_disabled_tools v2);
		// users re-enable individual tools here. All five are read-only.
		if ( function_exists( 'emcp_pro_fs' ) && emcp_pro_fs()->can_use_premium_code() ) {
			$tools['seo_a11y'] = array(
				'label' => __( 'SEO & Accessibility', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/audit-page-seo'                 => array(
						'label'       => __( 'Audit Page SEO', 'elementor-mcp' ),
						'description' => __( 'Scored on-page SEO report (H1, title/meta, canonical, alts, links, word count).', 'elementor-mcp' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'elementor-mcp/extract-keywords-from-content'  => array(
						'label'       => __( 'Extract Keywords', 'elementor-mcp' ),
						'description' => __( 'Frequency keyword + phrase extraction from page content.', 'elementor-mcp' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'elementor-mcp/generate-meta-tags'             => array(
						'label'       => __( 'Generate Meta Tags', 'elementor-mcp' ),
						'description' => __( 'Proposes (apply:true writes to Yoast/Rank Math) an SEO title and meta description. Dry-run by default.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/generate-schema-markup'         => array(
						'label'       => __( 'Generate Schema Markup', 'elementor-mcp' ),
						'description' => __( 'Generates (apply:true injects) JSON-LD structured data (Article, LocalBusiness, FAQPage, etc.). Dry-run by default.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/audit-page-a11y'                => array(
						'label'       => __( 'Audit Page Accessibility', 'elementor-mcp' ),
						'description' => __( 'WCAG-oriented report: contrast, alts, heading order, link text, form labels.', 'elementor-mcp' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'elementor-mcp/fix-color-contrast'             => array(
						'label'       => __( 'Fix Color Contrast', 'elementor-mcp' ),
						'description' => __( 'Proposes (apply:true to write) adjusted text colors so failing pairs meet WCAG AA. Dry-run by default.', 'elementor-mcp' ),
						'badges'      => array( 'pro', 'destructive' ),
					),
					'elementor-mcp/add-alt-text-from-context'      => array(
						'label'       => __( 'Add Alt Text from Context', 'elementor-mcp' ),
						'description' => __( 'Proposes (apply:true to write) alt text for images lacking it, from filename/heading/title. Dry-run by default.', 'elementor-mcp' ),
						'badges'      => array( 'pro', 'destructive' ),
					),
				),
			);

			$tools['widget_builder'] = array(
				'label' => __( 'Widget Builder', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/list-control-types'   => array(
						'label'       => __( 'List Control Types', 'elementor-mcp' ),
						'description' => __( 'Returns the control types and template syntax for building widget specs.', 'elementor-mcp' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'elementor-mcp/validate-widget-spec' => array(
						'label'       => __( 'Validate Widget Spec', 'elementor-mcp' ),
						'description' => __( 'Validates a widget spec and dry-runs the generator without saving.', 'elementor-mcp' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'elementor-mcp/create-custom-widget' => array(
						'label'       => __( 'Create Custom Widget', 'elementor-mcp' ),
						'description' => __( 'Generates a custom Elementor widget from a spec into an isolated sandbox and activates it.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/update-custom-widget' => array(
						'label'       => __( 'Update Custom Widget', 'elementor-mcp' ),
						'description' => __( 'Replaces a custom widget\'s spec and regenerates its code.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/get-custom-widget'    => array(
						'label'       => __( 'Get Custom Widget', 'elementor-mcp' ),
						'description' => __( 'Returns a custom widget\'s spec, generated PHP, status, and last error.', 'elementor-mcp' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'elementor-mcp/list-custom-widgets'  => array(
						'label'       => __( 'List Custom Widgets', 'elementor-mcp' ),
						'description' => __( 'Lists all generated custom widgets with their status.', 'elementor-mcp' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'elementor-mcp/set-widget-status'    => array(
						'label'       => __( 'Set Widget Status', 'elementor-mcp' ),
						'description' => __( 'Activates or deactivates a custom widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/delete-custom-widget' => array(
						'label'       => __( 'Delete Custom Widget', 'elementor-mcp' ),
						'description' => __( 'Permanently deletes a custom widget and its sandbox file.', 'elementor-mcp' ),
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
		$all      = $this->get_all_tool_slugs();
		$disabled = get_option( self::OPTION_DISABLED_TOOLS, array() );
		if ( ! is_array( $disabled ) ) {
			$disabled = array();
		}

		if ( '1' === (string) get_option( self::OPTION_LOW_TOOL_MODE, '0' ) ) {
			$disabled = array_merge( $disabled, array_diff( $all, Elementor_MCP_Plugin::get_essential_tool_slugs() ) );
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
