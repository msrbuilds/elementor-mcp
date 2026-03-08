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
	 * The page hook suffix returned by add_options_page().
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Option name for storing disabled tools.
	 *
	 * @var string
	 */
	const OPTION_DISABLED_TOOLS = 'elementor_mcp_disabled_tools';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'elementor_mcp_settings';

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'elementor-mcp';

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'elementor_mcp_ability_names', array( $this, 'filter_ability_names' ) );

		// AJAX handlers for the Settings tab.
		add_action( 'wp_ajax_emcp_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_emcp_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_emcp_take_screenshot', array( $this, 'ajax_take_screenshot' ) );
		add_action( 'wp_ajax_emcp_refresh_usage', array( $this, 'ajax_refresh_usage' ) );
		add_action( 'wp_ajax_emcp_openverse_register', array( $this, 'ajax_openverse_register' ) );

		// AJAX handlers for the Connection tab.
		add_action( 'wp_ajax_emcp_generate_connection', array( $this, 'ajax_generate_connection' ) );
		add_action( 'wp_ajax_emcp_revoke_connection', array( $this, 'ajax_revoke_connection' ) );
		add_action( 'wp_ajax_emcp_delete_connection', array( $this, 'ajax_delete_connection' ) );
	}

	/**
	 * Add the settings page under the Settings menu.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page(): void {
		$this->hook_suffix = add_options_page(
			__( 'MCP Tools for Elementor', 'elementor-mcp' ),
			__( 'EMCP Tools', 'elementor-mcp' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
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

		// Screenshot API settings.
		register_setting(
			'elementor_mcp_settings_group',
			'elementor_mcp_screenshot_enabled',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'elementor_mcp_settings_group',
			'elementor_mcp_screenshot_api_url',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		register_setting(
			'elementor_mcp_settings_group',
			'elementor_mcp_screenshot_api_key',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Stock image provider settings.
		register_setting(
			'elementor_mcp_settings_group',
			'elementor_mcp_stock_enabled',
			array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'elementor_mcp_settings_group',
			'elementor_mcp_stock_provider',
			array(
				'type'              => 'string',
				'default'           => 'openverse',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'elementor_mcp_settings_group',
			'elementor_mcp_stock_api_key',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
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
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'elementor-mcp-admin',
			ELEMENTOR_MCP_URL . 'assets/css/admin.css',
			array(),
			ELEMENTOR_MCP_VERSION
		);

		wp_enqueue_script(
			'elementor-mcp-admin',
			ELEMENTOR_MCP_URL . 'assets/js/admin.js',
			array(),
			ELEMENTOR_MCP_VERSION,
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
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'emcp_settings_nonce' ),
				'siteUrl'     => home_url( '/' ),
				'i18n'        => array(
					'saving'           => __( 'Saving…', 'elementor-mcp' ),
					'saved'            => __( 'Settings saved', 'elementor-mcp' ),
					'saveFailed'       => __( 'Save failed', 'elementor-mcp' ),
					'testing'          => __( 'Testing…', 'elementor-mcp' ),
					'connected'        => __( 'Connected', 'elementor-mcp' ),
					'disconnected'     => __( 'Disconnected', 'elementor-mcp' ),
					'disabled'         => __( 'Disabled', 'elementor-mcp' ),
					'invalidKey'       => __( 'Invalid Key', 'elementor-mcp' ),
					'capturing'        => __( 'Capturing…', 'elementor-mcp' ),
					'captureSuccess'   => __( 'Screenshot captured', 'elementor-mcp' ),
					'captureFailed'    => __( 'Capture failed', 'elementor-mcp' ),
					'refreshing'       => __( 'Refreshing…', 'elementor-mcp' ),
					'enableFirst'      => __( 'Enable the feature first', 'elementor-mcp' ),
					'saveSettings'     => __( 'Save Settings', 'elementor-mcp' ),
					'testConnection'   => __( 'Test Connection', 'elementor-mcp' ),
					'takeScreenshot'   => __( 'Take Test Screenshot', 'elementor-mcp' ),
				),
			)
		);
	}

	/**
	 * Filter ability names to remove disabled tools.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $names The registered ability names.
	 * @return string[] Filtered ability names.
	 */
	public function filter_ability_names( array $names ): array {
		$disabled = get_option( self::OPTION_DISABLED_TOOLS, array() );

		if ( empty( $disabled ) ) {
			return $names;
		}

		return array_values( array_diff( $names, $disabled ) );
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

		$active_tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'tools'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

		// Count sample prompts.
		$prompts_dir   = ELEMENTOR_MCP_DIR . 'prompts/';
		$prompt_files  = is_dir( $prompts_dir ) ? glob( $prompts_dir . '*.md' ) : array();
		$prompt_count  = count( $prompt_files );

		?>
		<div class="wrap elementor-mcp-admin">
			<h1><?php esc_html_e( 'MCP Tools for Elementor', 'elementor-mcp' ); ?></h1>

			<!-- Header -->
			<div class="elementor-mcp-header">
				<span class="elementor-mcp-header-icon">
					<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
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
					<a href="https://msrbuilds.com/lets-talk/" class="elementor-mcp-header-btn elementor-mcp-header-btn--secondary">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
						<?php esc_html_e( 'Contact Me', 'elementor-mcp' ); ?>
					</a>
					<a href="https://wpacademy.gumroad.com/l/vlrihk" class="elementor-mcp-header-btn elementor-mcp-header-btn--primary" target="_blank" rel="noopener noreferrer">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
						<?php esc_html_e( 'Get Premium Prompts', 'elementor-mcp' ); ?>
					</a>
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
			</div>

			<!-- Tabs -->
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=tools' ) ); ?>"
				   class="nav-tab <?php echo esc_attr( 'tools' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					<?php esc_html_e( 'Tools', 'elementor-mcp' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=connection' ) ); ?>"
				   class="nav-tab <?php echo esc_attr( 'connection' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					<?php esc_html_e( 'Connection', 'elementor-mcp' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=prompts' ) ); ?>"
				   class="nav-tab <?php echo esc_attr( 'prompts' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					<?php esc_html_e( 'Prompts', 'elementor-mcp' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=changelog' ) ); ?>"
				   class="nav-tab <?php echo esc_attr( 'changelog' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					<?php esc_html_e( 'Changelog', 'elementor-mcp' ); ?>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=settings' ) ); ?>"
				   class="nav-tab <?php echo esc_attr( 'settings' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					<?php esc_html_e( 'Settings', 'elementor-mcp' ); ?>
				</a>
			</nav>

			<!-- Content -->
			<div class="tab-content">
				<?php
				if ( 'connection' === $active_tab ) {
					include ELEMENTOR_MCP_DIR . 'includes/admin/views/page-connection.php';
				} elseif ( 'prompts' === $active_tab ) {
					include ELEMENTOR_MCP_DIR . 'includes/admin/views/page-prompts.php';
				} elseif ( 'changelog' === $active_tab ) {
					include ELEMENTOR_MCP_DIR . 'includes/admin/views/page-changelog.php';
				} elseif ( 'settings' === $active_tab ) {
					include ELEMENTOR_MCP_DIR . 'includes/admin/views/page-settings.php';
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
		return array(
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
				),
			),
			'widget_free_extra' => array(
				'label' => __( 'Additional Free Widgets', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/add-image-gallery'   => array(
						'label'       => __( 'Add Image Gallery', 'elementor-mcp' ),
						'description' => __( 'Adds a basic image gallery widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-audio'           => array(
						'label'       => __( 'Add Audio', 'elementor-mcp' ),
						'description' => __( 'Adds an audio player widget.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-read-more'       => array(
						'label'       => __( 'Add Read More', 'elementor-mcp' ),
						'description' => __( 'Adds a read more button for posts.', 'elementor-mcp' ),
						'badges'      => array(),
					),
					'elementor-mcp/add-sidebar'         => array(
						'label'       => __( 'Add Sidebar', 'elementor-mcp' ),
						'description' => __( 'Adds a widget area/sidebar.', 'elementor-mcp' ),
						'badges'      => array(),
					),
				),
			),
			'theme_builder'    => array(
				'label' => __( 'Theme Builder Widgets', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/add-site-logo'            => array(
						'label'       => __( 'Add Site Logo', 'elementor-mcp' ),
						'description' => __( 'Adds the site logo widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-site-title'           => array(
						'label'       => __( 'Add Site Title', 'elementor-mcp' ),
						'description' => __( 'Adds the site title widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-page-title'           => array(
						'label'       => __( 'Add Page Title', 'elementor-mcp' ),
						'description' => __( 'Adds the page title widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-search-form'          => array(
						'label'       => __( 'Add Search Form', 'elementor-mcp' ),
						'description' => __( 'Adds a search form widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-post-title'           => array(
						'label'       => __( 'Add Post Title', 'elementor-mcp' ),
						'description' => __( 'Adds the post title widget for single templates.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-post-content'         => array(
						'label'       => __( 'Add Post Content', 'elementor-mcp' ),
						'description' => __( 'Adds the post content widget for single templates.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-post-featured-image'  => array(
						'label'       => __( 'Add Post Featured Image', 'elementor-mcp' ),
						'description' => __( 'Adds the post featured image widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-post-excerpt'         => array(
						'label'       => __( 'Add Post Excerpt', 'elementor-mcp' ),
						'description' => __( 'Adds the post excerpt widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-code-highlight'       => array(
						'label'       => __( 'Add Code Highlight', 'elementor-mcp' ),
						'description' => __( 'Adds a code syntax highlighting widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
				),
			),
			'theme_elements'   => array(
				'label' => __( 'Theme Elements', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/add-author-box'        => array(
						'label'       => __( 'Add Author Box', 'elementor-mcp' ),
						'description' => __( 'Adds an author info box widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-post-info'         => array(
						'label'       => __( 'Add Post Info', 'elementor-mcp' ),
						'description' => __( 'Adds a post meta info widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-post-navigation'   => array(
						'label'       => __( 'Add Post Navigation', 'elementor-mcp' ),
						'description' => __( 'Adds previous/next post navigation.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-post-comments'     => array(
						'label'       => __( 'Add Post Comments', 'elementor-mcp' ),
						'description' => __( 'Adds the comments section widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-sitemap'           => array(
						'label'       => __( 'Add Sitemap', 'elementor-mcp' ),
						'description' => __( 'Adds an HTML sitemap widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-archive-title'     => array(
						'label'       => __( 'Add Archive Title', 'elementor-mcp' ),
						'description' => __( 'Adds the archive title widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-archive-posts'     => array(
						'label'       => __( 'Add Archive Posts', 'elementor-mcp' ),
						'description' => __( 'Adds the archive posts listing widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-video-playlist'    => array(
						'label'       => __( 'Add Video Playlist', 'elementor-mcp' ),
						'description' => __( 'Adds a video playlist widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-progress-tracker'  => array(
						'label'       => __( 'Add Progress Tracker', 'elementor-mcp' ),
						'description' => __( 'Adds a reading progress tracker widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
				),
			),
			'nav_social'       => array(
				'label' => __( 'Navigation & Social', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/add-paypal-button'     => array(
						'label'       => __( 'Add PayPal Button', 'elementor-mcp' ),
						'description' => __( 'Adds a PayPal payment button.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-stripe-button'     => array(
						'label'       => __( 'Add Stripe Button', 'elementor-mcp' ),
						'description' => __( 'Adds a Stripe payment button.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-off-canvas'        => array(
						'label'       => __( 'Add Off Canvas', 'elementor-mcp' ),
						'description' => __( 'Adds an off-canvas sidebar/panel.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-mega-menu'         => array(
						'label'       => __( 'Add Mega Menu', 'elementor-mcp' ),
						'description' => __( 'Adds a mega menu widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-nested-carousel'   => array(
						'label'       => __( 'Add Nested Carousel', 'elementor-mcp' ),
						'description' => __( 'Adds a container-based nested carousel.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-portfolio'         => array(
						'label'       => __( 'Add Portfolio', 'elementor-mcp' ),
						'description' => __( 'Adds a portfolio/filterable grid widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-facebook-page'     => array(
						'label'       => __( 'Add Facebook Page', 'elementor-mcp' ),
						'description' => __( 'Embeds a Facebook page feed widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-taxonomy-filter'   => array(
						'label'       => __( 'Add Taxonomy Filter', 'elementor-mcp' ),
						'description' => __( 'Adds a taxonomy filter widget for loop grids.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
				),
			),
			'woocommerce'      => array(
				'label' => __( 'WooCommerce Widgets', 'elementor-mcp' ),
				'tools' => array(
					'elementor-mcp/add-wc-product-images'     => array(
						'label'       => __( 'Add Product Images', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce product images gallery.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-product-title'      => array(
						'label'       => __( 'Add Product Title', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce product title widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-product-price'      => array(
						'label'       => __( 'Add Product Price', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce product price widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-product-add-to-cart' => array(
						'label'       => __( 'Add to Cart Button', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce add-to-cart button.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-product-rating'     => array(
						'label'       => __( 'Add Product Rating', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce product rating widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-product-meta'       => array(
						'label'       => __( 'Add Product Meta', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce product meta info.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-product-tabs'       => array(
						'label'       => __( 'Add Product Tabs', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce product data tabs.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-product-short-desc'  => array(
						'label'       => __( 'Add Product Short Description', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce product short description.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-product-related'    => array(
						'label'       => __( 'Add Related Products', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce related products widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-product-upsell'     => array(
						'label'       => __( 'Add Product Upsells', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce product upsells widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-archive-products'   => array(
						'label'       => __( 'Add Archive Products', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce archive products grid.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-categories'         => array(
						'label'       => __( 'Add Product Categories', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce product categories grid.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-my-account'         => array(
						'label'       => __( 'Add My Account', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce My Account page widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-products'           => array(
						'label'       => __( 'Add Products', 'elementor-mcp' ),
						'description' => __( 'Adds a WooCommerce products listing widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-cart'               => array(
						'label'       => __( 'Add Cart', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce cart widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-checkout'           => array(
						'label'       => __( 'Add Checkout', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce checkout widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-menu-cart'          => array(
						'label'       => __( 'Add Menu Cart', 'elementor-mcp' ),
						'description' => __( 'Adds WooCommerce menu cart icon widget.', 'elementor-mcp' ),
						'badges'      => array( 'pro' ),
					),
					'elementor-mcp/add-wc-add-to-cart'        => array(
						'label'       => __( 'Add Custom Add to Cart', 'elementor-mcp' ),
						'description' => __( 'Adds a custom add-to-cart button for any product.', 'elementor-mcp' ),
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

	// ─── AJAX Handlers ───────────────────────────────────────────────────────

	/**
	 * Verify AJAX request nonce and permissions.
	 *
	 * @since 2.7.0
	 */
	private function verify_ajax_request(): void {
		check_ajax_referer( 'emcp_settings_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'elementor-mcp' ) ), 403 );
		}
	}

	/**
	 * AJAX: Save settings without page reload.
	 *
	 * @since 2.7.0
	 */
	public function ajax_save_settings(): void {
		$this->verify_ajax_request();

		$group = isset( $_POST['group'] ) ? sanitize_text_field( wp_unslash( $_POST['group'] ) ) : '';

		if ( 'screenshot' === $group ) {
			$enabled = isset( $_POST['screenshot_enabled'] ) ? '1' : '';
			$api_url = isset( $_POST['screenshot_api_url'] ) ? esc_url_raw( wp_unslash( $_POST['screenshot_api_url'] ) ) : '';
			$api_key = isset( $_POST['screenshot_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['screenshot_api_key'] ) ) : '';

			update_option( 'elementor_mcp_screenshot_enabled', $enabled );
			update_option( 'elementor_mcp_screenshot_api_url', $api_url );
			update_option( 'elementor_mcp_screenshot_api_key', $api_key );

			wp_send_json_success( array(
				'message' => __( 'Screenshot settings saved.', 'elementor-mcp' ),
				'enabled' => ! empty( $enabled ),
			) );
		} elseif ( 'stock' === $group ) {
			$enabled  = isset( $_POST['stock_enabled'] ) ? '1' : '';
			$provider = isset( $_POST['stock_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['stock_provider'] ) ) : 'openverse';
			$api_key  = isset( $_POST['stock_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stock_api_key'] ) ) : '';

			update_option( 'elementor_mcp_stock_enabled', $enabled );
			update_option( 'elementor_mcp_stock_provider', $provider );
			update_option( 'elementor_mcp_stock_api_key', $api_key );

			// Save per-provider key so switching providers preserves keys.
			$allowed = array( 'pexels', 'pixabay', 'unsplash', 'openverse' );
			if ( in_array( $provider, $allowed, true ) ) {
				update_option( 'elementor_mcp_stock_key_' . $provider, $api_key );
			}

			wp_send_json_success( array(
				'message' => __( 'Stock image settings saved.', 'elementor-mcp' ),
				'enabled' => ! empty( $enabled ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Unknown settings group.', 'elementor-mcp' ) ) );
		}
	}

	/**
	 * AJAX: Test screenshot API connection.
	 *
	 * @since 2.7.0
	 */
	public function ajax_test_connection(): void {
		$this->verify_ajax_request();

		$api_url = get_option( 'elementor_mcp_screenshot_api_url', '' );
		$api_key = get_option( 'elementor_mcp_screenshot_api_key', '' );

		if ( empty( $api_url ) || empty( $api_key ) ) {
			wp_send_json_error( array(
				'message' => __( 'API URL and Key are required.', 'elementor-mcp' ),
				'status'  => 'disconnected',
			) );
		}

		$response = wp_remote_get(
			rtrim( $api_url, '/' ) . '/api/validate',
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array(
				'message' => sprintf( __( 'Connection failed: %s', 'elementor-mcp' ), $response->get_error_message() ),
				'status'  => 'disconnected',
			) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && ! empty( $body['valid'] ) ) {
			$data = isset( $body['data'] ) ? $body['data'] : array();
			wp_send_json_success( array(
				'message' => __( 'Connection verified successfully.', 'elementor-mcp' ),
				'status'  => 'connected',
				'usage'   => array(
					'plan'          => isset( $data['plan'] ) ? $data['plan'] : ( isset( $data['name'] ) ? $data['name'] : 'unknown' ),
					'current_usage' => isset( $data['current_usage'] ) ? (int) $data['current_usage'] : 0,
					'monthly_limit' => isset( $data['monthly_limit'] ) ? (int) $data['monthly_limit'] : 0,
					'remaining'     => isset( $data['remaining'] ) ? (int) $data['remaining'] : 0,
					'rate_limit'    => isset( $data['rate_limit'] ) ? (int) $data['rate_limit'] : 0,
					'is_active'     => isset( $data['is_active'] ) ? (bool) $data['is_active'] : false,
					'last_used_at'  => isset( $data['last_used_at'] ) ? $data['last_used_at'] : null,
					'key_name'      => isset( $data['name'] ) ? $data['name'] : '',
				),
			) );
		} else {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'API key is invalid or disabled.', 'elementor-mcp' );
			wp_send_json_error( array(
				'message' => $msg,
				'status'  => 'invalid',
			) );
		}
	}

	/**
	 * AJAX: Take a test screenshot of the homepage.
	 *
	 * @since 2.7.0
	 */
	public function ajax_take_screenshot(): void {
		$this->verify_ajax_request();

		$api_url = get_option( 'elementor_mcp_screenshot_api_url', '' );
		$api_key = get_option( 'elementor_mcp_screenshot_api_key', '' );

		if ( empty( $api_url ) || empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Configure API credentials first.', 'elementor-mcp' ) ) );
		}

		$target_url = home_url( '/' );
		$endpoint   = rtrim( $api_url, '/' ) . '/api/screenshot?' . http_build_query( array(
			'url'       => $target_url,
			'width'     => 1280,
			'height'    => 800,
			'full_page' => 'true',
			'scroll'    => 'true',
			'format'    => 'png',
			'output'    => 'json',
		) );

		$response = wp_remote_get( $endpoint, array(
			'timeout' => 30,
			'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array(
				'message' => sprintf( __( 'Screenshot failed: %s', 'elementor-mcp' ), $response->get_error_message() ),
			) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['success'] ) && ! empty( $body['data'] ) ) {
			$image_url = isset( $body['data']['image'] ) ? $body['data']['image'] : ( isset( $body['data']['url'] ) ? $body['data']['url'] : '' );
			wp_send_json_success( array(
				'message'   => __( 'Screenshot captured successfully.', 'elementor-mcp' ),
				'image_url' => $image_url,
				'timestamp' => current_time( 'mysql' ),
			) );
		} else {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unknown error during capture.', 'elementor-mcp' );
			wp_send_json_error( array( 'message' => $msg ) );
		}
	}

	/**
	 * AJAX: Refresh API usage / credits.
	 *
	 * @since 2.7.0
	 */
	public function ajax_refresh_usage(): void {
		$this->verify_ajax_request();

		$api_url = get_option( 'elementor_mcp_screenshot_api_url', '' );
		$api_key = get_option( 'elementor_mcp_screenshot_api_key', '' );

		if ( empty( $api_url ) || empty( $api_key ) ) {
			wp_send_json_error( array(
				'message' => __( 'API not configured.', 'elementor-mcp' ),
				'status'  => 'disconnected',
			) );
		}

		$response = wp_remote_get(
			rtrim( $api_url, '/' ) . '/api/validate',
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array(
				'message' => $response->get_error_message(),
				'status'  => 'disconnected',
			) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['valid'] ) && ! empty( $body['data'] ) ) {
			$d = $body['data'];
			wp_send_json_success( array(
				'status' => 'connected',
				'usage'  => array(
					'plan'          => isset( $d['plan'] ) ? $d['plan'] : ( isset( $d['name'] ) ? $d['name'] : 'unknown' ),
					'current_usage' => isset( $d['current_usage'] ) ? (int) $d['current_usage'] : 0,
					'monthly_limit' => isset( $d['monthly_limit'] ) ? (int) $d['monthly_limit'] : 0,
					'remaining'     => isset( $d['remaining'] ) ? (int) $d['remaining'] : 0,
					'rate_limit'    => isset( $d['rate_limit'] ) ? (int) $d['rate_limit'] : 0,
					'is_active'     => isset( $d['is_active'] ) ? (bool) $d['is_active'] : false,
					'last_used_at'  => isset( $d['last_used_at'] ) ? $d['last_used_at'] : null,
					'key_name'      => isset( $d['name'] ) ? $d['name'] : '',
				),
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Could not fetch usage data.', 'elementor-mcp' ),
				'status'  => 'invalid',
			) );
		}
	}

	/**
	 * AJAX: Register with Openverse and exchange for access token.
	 *
	 * Flow:
	 *   1. POST /v1/auth_tokens/register/ → client_id + client_secret
	 *   2. POST /v1/auth_tokens/token/    → access_token
	 *   3. Auto-save token to stock_api_key option
	 *
	 * @since 2.7.0
	 */
	public function ajax_openverse_register(): void {
		$this->verify_ajax_request();

		$name  = isset( $_POST['ov_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ov_name'] ) ) : '';
		$desc  = isset( $_POST['ov_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ov_desc'] ) ) : '';
		$email = isset( $_POST['ov_email'] ) ? sanitize_email( wp_unslash( $_POST['ov_email'] ) ) : '';

		if ( empty( $name ) || empty( $desc ) || empty( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'All fields are required.', 'elementor-mcp' ) ) );
		}

		// Step 1: Register application.
		$reg_response = wp_remote_post(
			'https://api.openverse.org/v1/auth_tokens/register/',
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array(
					'name'        => $name,
					'description' => $desc,
					'email'       => $email,
				) ),
			)
		);

		if ( is_wp_error( $reg_response ) ) {
			wp_send_json_error( array(
				'message' => sprintf( __( 'Registration failed: %s', 'elementor-mcp' ), $reg_response->get_error_message() ),
			) );
		}

		$reg_code = wp_remote_retrieve_response_code( $reg_response );
		$reg_body = json_decode( wp_remote_retrieve_body( $reg_response ), true );

		if ( 201 !== $reg_code || empty( $reg_body['client_id'] ) || empty( $reg_body['client_secret'] ) ) {
			$err = '';
			if ( is_array( $reg_body ) ) {
				foreach ( $reg_body as $field => $msgs ) {
					if ( is_array( $msgs ) ) {
						$err .= ucfirst( $field ) . ': ' . implode( ', ', $msgs ) . ' ';
					}
				}
			}
			wp_send_json_error( array(
				'message' => trim( $err ) ?: __( 'Registration failed. Please try again.', 'elementor-mcp' ),
			) );
		}

		$client_id     = sanitize_text_field( $reg_body['client_id'] );
		$client_secret = sanitize_text_field( $reg_body['client_secret'] );

		// Step 2: Exchange for access token.
		$token_response = wp_remote_post(
			'https://api.openverse.org/v1/auth_tokens/token/',
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'grant_type'    => 'client_credentials',
				),
			)
		);

		if ( is_wp_error( $token_response ) ) {
			wp_send_json_error( array(
				'message' => sprintf( __( 'Token exchange failed: %s', 'elementor-mcp' ), $token_response->get_error_message() ),
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
			) );
		}

		$token_body = json_decode( wp_remote_retrieve_body( $token_response ), true );

		if ( empty( $token_body['access_token'] ) ) {
			wp_send_json_error( array(
				'message'       => __( 'Token exchange failed. You can manually use your client credentials.', 'elementor-mcp' ),
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
			) );
		}

		$access_token = sanitize_text_field( $token_body['access_token'] );

		// Step 3: Auto-save to stock API key.
		update_option( 'elementor_mcp_stock_api_key', $access_token );
		update_option( 'elementor_mcp_stock_key_openverse', $access_token );

		wp_send_json_success( array(
			'message'      => __( 'Registered successfully! Token saved automatically.', 'elementor-mcp' ),
			'access_token' => $access_token,
			'client_id'    => $client_id,
			'expires_in'   => isset( $token_body['expires_in'] ) ? (int) $token_body['expires_in'] : null,
		) );
	}

	// =========================================================================
	//  Connection Management AJAX Handlers
	// =========================================================================

	/**
	 * AJAX: Generate a new connection token.
	 *
	 * Validates WP credentials first, then generates a plugin-managed Bearer token.
	 *
	 * @since 2.8.0
	 */
	public function ajax_generate_connection(): void {
		$this->verify_ajax_request();

		$label    = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
		$password = isset( $_POST['app_password'] ) ? wp_unslash( $_POST['app_password'] ) : '';

		if ( empty( $label ) ) {
			wp_send_json_error( array( 'message' => __( 'Connection label is required.', 'elementor-mcp' ) ) );
		}

		if ( empty( $username ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Username and Application Password are required.', 'elementor-mcp' ) ) );
		}

		// Admin is already nonce-authenticated. No need to validate the app password
		// server-side — wp_authenticate() doesn't support app passwords in admin AJAX
		// context. The credentials are passed to the client for btoa() config generation.
		$user_id = get_current_user_id();

		// Generate plugin-managed Bearer token for history/revoke tracking.
		$tokens = new Elementor_MCP_Connection_Tokens();
		$result = $tokens->generate( $user_id, $label );

		$endpoint = rest_url( 'mcp/elementor-mcp-server' );

		// Strip spaces from password for Base64 encoding.
		$clean_password = preg_replace( '/\s+/', '', $password );

		wp_send_json_success( array(
			'message'    => __( 'Connection created successfully! Copy your token now — it won\'t be shown again.', 'elementor-mcp' ),
			'connection' => $result['connection'],
			'raw_token'  => $result['raw_token'],
			'endpoint'   => $endpoint,
			'b64_token'  => base64_encode( $username . ':' . $clean_password ),
			'configs'    => $this->build_client_configs( $endpoint, $result['raw_token'] ),
		) );
	}

	/**
	 * AJAX: Revoke a connection token.
	 *
	 * @since 2.8.0
	 */
	public function ajax_revoke_connection(): void {
		$this->verify_ajax_request();

		$conn_id = isset( $_POST['conn_id'] ) ? sanitize_text_field( wp_unslash( $_POST['conn_id'] ) ) : '';

		if ( empty( $conn_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Connection ID is required.', 'elementor-mcp' ) ) );
		}

		$tokens  = new Elementor_MCP_Connection_Tokens();
		$revoked = $tokens->revoke( $conn_id, get_current_user_id() );

		if ( ! $revoked ) {
			wp_send_json_error( array( 'message' => __( 'Connection not found.', 'elementor-mcp' ) ) );
		}

		$connection = $tokens->get( $conn_id );

		wp_send_json_success( array(
			'message'    => __( 'Connection revoked. It will no longer authenticate MCP requests.', 'elementor-mcp' ),
			'connection' => $connection,
		) );
	}

	/**
	 * AJAX: Permanently delete a connection record.
	 *
	 * @since 2.8.0
	 */
	public function ajax_delete_connection(): void {
		$this->verify_ajax_request();

		$conn_id = isset( $_POST['conn_id'] ) ? sanitize_text_field( wp_unslash( $_POST['conn_id'] ) ) : '';

		if ( empty( $conn_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Connection ID is required.', 'elementor-mcp' ) ) );
		}

		$tokens  = new Elementor_MCP_Connection_Tokens();
		$deleted = $tokens->delete( $conn_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Connection not found.', 'elementor-mcp' ) ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Connection deleted permanently.', 'elementor-mcp' ),
		) );
	}

	/**
	 * Build client config JSON for all supported AI clients.
	 *
	 * @param string $endpoint   MCP endpoint URL.
	 * @param string $raw_token  Bearer token.
	 *
	 * @return array<string, string> Client configs keyed by client name.
	 */
	private function build_client_configs( string $endpoint, string $raw_token ): array {
		$bearer = 'Bearer ' . $raw_token;

		// Claude Code / Claude Desktop.
		$claude = array(
			'mcpServers' => array(
				'elementor-mcp' => array(
					'type'    => 'http',
					'url'     => $endpoint,
					'headers' => array( 'Authorization' => $bearer ),
				),
			),
		);

		// Cursor.
		$cursor = array(
			'mcpServers' => array(
				'elementor-mcp' => array(
					'url'     => $endpoint,
					'headers' => array( 'Authorization' => $bearer ),
				),
			),
		);

		// Windsurf / Antigravity.
		$windsurf = array(
			'mcpServers' => array(
				'elementor-mcp' => array(
					'serverUrl' => $endpoint,
					'headers'   => array( 'Authorization' => $bearer ),
				),
			),
		);

		// Codex (TOML).
		$codex = "[mcp_servers.elementor-mcp]\n" .
			'url = "' . $endpoint . "\"\n\n" .
			"[mcp_servers.elementor-mcp.http_headers]\n" .
			'"Authorization" = "' . $bearer . '"';

		// npx mcp-remote.
		$mcp_remote = array(
			'mcpServers' => array(
				'elementor-mcp' => array(
					'command' => 'npx',
					'args'    => array(
						'-y',
						'mcp-remote',
						$endpoint,
						'--header',
						'Authorization: ' . $bearer,
					),
				),
			),
		);

		return array(
			'claude_code'    => wp_json_encode( $claude, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			'claude_desktop' => wp_json_encode( $claude, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			'cursor'         => wp_json_encode( $cursor, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			'windsurf'       => wp_json_encode( $windsurf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			'antigravity'    => wp_json_encode( $windsurf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			'codex'          => $codex,
			'mcp_remote'     => wp_json_encode( $mcp_remote, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
		);
	}
}
