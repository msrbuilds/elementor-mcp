<?php
/**
 * Admin settings page for Elementor MCP.
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
	}

	/**
	 * Add the settings page under the Settings menu.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page(): void {
		$this->hook_suffix = add_options_page(
			__( 'Elementor MCP', 'elementor-mcp' ),
			__( 'Elementor MCP', 'elementor-mcp' ),
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

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'tools'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap elementor-mcp-admin">
			<h1><?php esc_html_e( 'Elementor MCP', 'elementor-mcp' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=tools' ) ); ?>"
				   class="nav-tab <?php echo esc_attr( 'tools' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					<?php esc_html_e( 'Tools', 'elementor-mcp' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=connection' ) ); ?>"
				   class="nav-tab <?php echo esc_attr( 'connection' === $active_tab ? 'nav-tab-active' : '' ); ?>">
					<?php esc_html_e( 'Connection', 'elementor-mcp' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				if ( 'connection' === $active_tab ) {
					include ELEMENTOR_MCP_DIR . 'includes/admin/views/page-connection.php';
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
					'elementor-mcp/duplicate-element'  => array(
						'label'       => __( 'Duplicate Element', 'elementor-mcp' ),
						'description' => __( 'Creates a deep copy of an element and inserts it after the original.', 'elementor-mcp' ),
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
					'elementor-mcp/apply-template'   => array(
						'label'       => __( 'Apply Template', 'elementor-mcp' ),
						'description' => __( 'Applies a saved template to a target page.', 'elementor-mcp' ),
						'badges'      => array(),
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
}
