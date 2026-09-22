<?php
/**
 * Base for the Loop Grid and Loop Carousel Elementor widgets.
 *
 * Holds the controls both share: the Loop Item picker, the query section, the
 * item style section and the nothing-found controls. Rendering delegates to
 * the builder-agnostic element classes, so a widget and its block cannot
 * diverge. Settings become element args in EMCP_Tools_Themer_Loop_Widget_Map,
 * and every selector is built there too, scoped to the widget's own loop so
 * a Loop Grid or Carousel inside one of its cards is never styled by it.
 *
 * Required only from inside the Elementor registration callback.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EMCP_Tools_Themer_Widget_Loop_Base' ) && class_exists( '\Elementor\Widget_Base' ) ) {

	/**
	 * @since 3.18.0
	 */
	abstract class EMCP_Tools_Themer_Widget_Loop_Base extends \Elementor\Widget_Base {

		/** Devices the responsive controls offer (the stylesheet's breakpoints). */
		const DEVICES = array( 'desktop', 'tablet', 'mobile' );

		/** @var array<string,string[]>|null Saved option values in the edited document. */
		private static $saved = null;

		/** grid|carousel. */
		abstract protected function emcp_kind(): string;

		/**
		 * Settings to element args.
		 *
		 * @param array $s Settings.
		 * @return array
		 */
		abstract protected function emcp_args( array $s ): array;

		/** @return string[] */
		public static function widget_classes(): array {
			return array( 'EMCP_Tools_Themer_Widget_Loop_Grid', 'EMCP_Tools_Themer_Widget_Loop_Carousel' );
		}

		/** @return string[] */
		public function get_categories(): array {
			return array( EMCP_Tools_Themer_Widgets::CATEGORY );
		}

		/**
		 * Handles the editor preview must load: it renders widgets over AJAX,
		 * where an enqueue from render never reaches the page. Registered by
		 * EMCP_Tools_Themer_Loop_Assets on elementor/frontend/after_register_scripts,
		 * which the preview fires before it enqueues widget dependencies.
		 *
		 * @return string[]
		 */
		public function get_script_depends(): array {
			$deps = array( EMCP_Tools_Themer_Loop_Assets::SCRIPT );
			if ( 'carousel' === $this->emcp_kind() ) {
				array_unshift( $deps, EMCP_Tools_Themer_Loop_Assets::swiper_handle() );
			}
			return $deps;
		}

		/** @return string[] */
		public function get_style_depends(): array {
			$deps = array( EMCP_Tools_Themer_Loop_Assets::STYLE );
			if ( 'carousel' === $this->emcp_kind() ) {
				array_unshift( $deps, EMCP_Tools_Themer_Loop_Assets::swiper_style_handle() );
			}
			return $deps;
		}

		/** Render through the shared element. */
		protected function render(): void {
			$args = $this->emcp_args( (array) $this->get_settings_for_display() );
			if ( self::emcp_is_editor() && ! EMCP_Tools_Themer_CPT::is_published_loop_template( (int) ( $args['template_id'] ?? 0 ) ) ) {
				echo '<div class="emcp-loop__placeholder">' . esc_html__( 'Choose a Loop Item to display.', 'emcp-tools' ) . '</div>';
				return;
			}
			$html = 'carousel' === $this->emcp_kind()
				? EMCP_Tools_Themer_Element_Loop_Carousel::render( $args )
				: EMCP_Tools_Themer_Element_Loop_Grid::render( $args );
			// The element renderers escape their own output.
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/** The element id, used as the instance id. */
		protected function emcp_uid(): string {
			return (string) $this->get_id();
		}

		/** @return bool Whether this render is for the Elementor editor. */
		protected static function emcp_is_editor(): bool {
			if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
				return false;
			}
			$plugin = \Elementor\Plugin::$instance;
			if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
				return true;
			}
			return isset( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode();
		}

		/**
		 * Whether option lists are needed. Elementor builds a widget's controls
		 * on the front end too (to resolve settings), where option lists are
		 * never shown; the panel config is only built in wp-admin (the editor
		 * page and its admin-ajax calls). Skipping the term, user and Loop
		 * Item queries elsewhere keeps a page view free of them.
		 *
		 * @return bool
		 */
		protected static function emcp_needs_options(): bool {
			return is_admin();
		}

		// ---- selectors -----------------------------------------------------

		/** @return string The widget's own loop root. */
		protected function emcp_root(): string {
			return EMCP_Tools_Themer_Loop_Widget_Map::root_selector();
		}

		/**
		 * This widget's own items (never a nested loop's).
		 *
		 * @param string $state Appended to the item, e.g. ':hover'.
		 * @return string
		 */
		protected function emcp_item_selector( string $state = '' ): string {
			return 'carousel' === $this->emcp_kind()
				? EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .swiper > .swiper-wrapper > .emcp-loop__item' . $state )
				: EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .emcp-loop__items > .emcp-loop__item' . $state );
		}

		// ---- option lists --------------------------------------------------

		/**
		 * Saved option values of every loop widget in the document being
		 * edited (controls are built once per widget type, so this is the
		 * only way a saved value outside an option list keeps showing).
		 *
		 * @return array<string,string[]>
		 */
		protected static function emcp_saved(): array {
			if ( null !== self::$saved ) {
				return self::$saved;
			}
			self::$saved = EMCP_Tools_Themer_Loop_Widget_Map::collect_saved( array() );
			$id          = EMCP_Tools_Themer_Widgets::edited_post_id();
			if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) || ! isset( \Elementor\Plugin::$instance->documents ) ) {
				return self::$saved;
			}
			$doc  = \Elementor\Plugin::$instance->documents->get( $id );
			$data = is_object( $doc ) && method_exists( $doc, 'get_elements_data' ) ? $doc->get_elements_data() : array();
			if ( is_array( $data ) ) {
				self::$saved = EMCP_Tools_Themer_Loop_Widget_Map::collect_saved( $data );
			}
			return self::$saved;
		}

		/**
		 * SELECT option labels, escaped: Elementor prints SELECT option text
		 * unescaped (SELECT2 escapes its own).
		 *
		 * @param array $options value => plain label.
		 * @return array
		 */
		protected static function emcp_select_options( array $options ): array {
			return array_map(
				static function ( $label ) {
					return esc_html( (string) $label );
				},
				$options
			);
		}

		/** @return array<int|string,string> Loop Items, saved ones included. */
		protected static function emcp_template_options(): array {
			if ( ! self::emcp_needs_options() ) {
				return array();
			}
			return EMCP_Tools_Themer_Loop_Widget_Map::merge_saved(
				EMCP_Tools_Themer_Loop_Options::loop_templates(),
				self::emcp_saved()['templates'],
				static function ( $id ) {
					$title = wp_strip_all_tags( (string) get_the_title( (int) $id ) );
					/* translators: %d: Loop Item id */
					return '' !== trim( $title ) ? $title : sprintf( __( 'Loop Item #%d', 'emcp-tools' ), (int) $id );
				}
			);
		}

		/** @return array<string,string> Terms as refs, saved ones included. */
		protected static function emcp_term_options(): array {
			if ( ! self::emcp_needs_options() ) {
				return array();
			}
			return EMCP_Tools_Themer_Loop_Widget_Map::merge_saved(
				EMCP_Tools_Themer_Loop_Options::flat_terms(),
				self::emcp_saved()['terms'],
				static function ( $ref ) {
					$parts = explode( ':', (string) $ref, 2 );
					$term  = 2 === count( $parts ) ? get_term( (int) $parts[1], $parts[0] ) : null;
					if ( $term instanceof WP_Term ) {
						return $parts[0] . ': ' . wp_strip_all_tags( $term->name );
					}
					return (string) $ref;
				}
			);
		}

		/** @return array<int|string,string> Authors, saved ones included. */
		protected static function emcp_author_options(): array {
			if ( ! self::emcp_needs_options() ) {
				return array();
			}
			return EMCP_Tools_Themer_Loop_Widget_Map::merge_saved(
				EMCP_Tools_Themer_Loop_Options::authors(),
				self::emcp_saved()['authors'],
				static function ( $id ) {
					$user = get_userdata( (int) $id );
					/* translators: %d: user id */
					return $user ? wp_strip_all_tags( (string) $user->display_name ) : sprintf( __( 'User #%d', 'emcp-tools' ), (int) $id );
				}
			);
		}

		/** @return array<string,string> Post types, saved ones included. */
		protected static function emcp_post_type_options(): array {
			if ( ! self::emcp_needs_options() ) {
				return array();
			}
			return EMCP_Tools_Themer_Loop_Widget_Map::merge_saved(
				EMCP_Tools_Themer_Loop_Options::post_types(),
				self::emcp_saved()['post_types'],
				'strval'
			);
		}

		/** @return array<string,string> Public taxonomies, saved ones included. */
		protected static function emcp_taxonomy_options(): array {
			if ( ! self::emcp_needs_options() ) {
				return array( 'category' => __( 'Categories', 'emcp-tools' ) );
			}
			$out = array();
			foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
				$name = is_object( $tax ) ? (string) ( $tax->name ?? '' ) : '';
				if ( preg_match( '/^[a-z0-9_-]+$/', $name ) ) {
					$out[ $name ] = wp_strip_all_tags( (string) ( $tax->label ?? $name ) );
				}
			}
			return EMCP_Tools_Themer_Loop_Widget_Map::merge_saved( $out, self::emcp_saved()['taxonomies'], 'strval' );
		}

		/** Test seam and cache reset. */
		public static function reset_saved(): void {
			self::$saved = null;
		}

		// ---- shared control sections ---------------------------------------

		/** The Loop Item picker plus the edit link. */
		protected function emcp_register_template_control(): void {
			$templates = self::emcp_template_options();
			$desc      = $templates
				? sprintf(
					/* translators: %s: link to the Loop Items admin screen */
					__( 'The card rendered once per post. %s', 'emcp-tools' ),
					'<a href="' . esc_url( admin_url( 'edit.php?post_type=' . EMCP_Tools_Themer_CPT::POST_TYPE ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Manage Loop Items', 'emcp-tools' ) . '</a>'
				)
				: sprintf(
					/* translators: %s: link to create a Loop Item */
					__( 'No Loop Items yet. %s', 'emcp-tools' ),
					'<a href="' . esc_url( admin_url( 'post-new.php?post_type=' . EMCP_Tools_Themer_CPT::POST_TYPE . '&emcp_themer_type=loop' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Create one', 'emcp-tools' ) . '</a>'
				);

			$this->add_control(
				'emcp_template_id',
				array(
					'label'       => __( 'Loop Item', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => self::emcp_select_options( array( 0 => __( 'Select a Loop Item', 'emcp-tools' ) ) + $templates ),
					'default'     => '0',
					'description' => $desc,
				)
			);
		}

		/** The query section: source, filters, ordering. */
		protected function emcp_register_query_section(): void {
			$woo = EMCP_Tools_Themer_Loop_Query::woocommerce_active();

			$this->start_controls_section( 'emcp_section_query', array( 'label' => __( 'Query', 'emcp-tools' ) ) );

			$sources = array(
				'posts'   => __( 'Posts', 'emcp-tools' ),
				'current' => __( 'Current query (archive)', 'emcp-tools' ),
				'related' => __( 'Related to current post', 'emcp-tools' ),
				'manual'  => __( 'Manual selection', 'emcp-tools' ),
			);
			if ( $woo ) {
				$sources['products'] = __( 'Products', 'emcp-tools' );
			}
			$this->add_control(
				'emcp_source',
				array(
					'label'       => __( 'Source', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => self::emcp_select_options( $sources ),
					'default'     => 'posts',
					'description' => __( 'Current query repeats the archive this template is on. Related uses the viewed post\'s terms.', 'emcp-tools' ),
				)
			);

			$this->add_control(
				'emcp_post_types',
				array(
					'label'       => __( 'Post types', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'multiple'    => true,
					'options'     => self::emcp_post_type_options(),
					'default'     => array( 'post' ),
					'label_block' => true,
					'condition'   => array( 'emcp_source' => array( 'posts' ) ),
				)
			);

			$this->add_control(
				'emcp_per_page',
				array(
					'label'     => __( 'Items per page', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'min'       => 1,
					'max'       => EMCP_Tools_Themer_Loop_Query::MAX_PER_PAGE,
					'default'   => EMCP_Tools_Themer_Loop_Query::DEFAULT_PER_PAGE,
					'condition' => array( 'emcp_source!' => 'current' ),
				)
			);

			$this->add_control(
				'emcp_offset',
				array(
					'label'       => __( 'Skip first', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 0,
					'max'         => EMCP_Tools_Themer_Loop_Query::MAX_OFFSET,
					'default'     => 0,
					'description' => __( 'Useful when a featured block above already shows the newest posts.', 'emcp-tools' ),
					'condition'   => array( 'emcp_source!' => array( 'current', 'manual' ) ),
				)
			);

			$terms = self::emcp_term_options();
			$this->add_control(
				'emcp_terms',
				array(
					'label'       => __( 'Include terms', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'multiple'    => true,
					'options'     => $terms,
					'label_block' => true,
					'condition'   => array( 'emcp_source' => array( 'posts', 'products' ) ),
				)
			);
			$this->add_control(
				'emcp_exclude_terms',
				array(
					'label'       => __( 'Exclude terms', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'multiple'    => true,
					'options'     => $terms,
					'label_block' => true,
					'condition'   => array( 'emcp_source' => array( 'posts', 'products' ) ),
				)
			);
			$this->add_control(
				'emcp_authors',
				array(
					'label'       => __( 'Authors', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'multiple'    => true,
					'options'     => self::emcp_author_options(),
					'label_block' => true,
					'condition'   => array( 'emcp_source' => array( 'posts' ) ),
				)
			);
			$this->add_control(
				'emcp_related_taxonomy',
				array(
					'label'     => __( 'Related by', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'options'   => self::emcp_select_options( self::emcp_taxonomy_options() ),
					'default'   => 'category',
					'condition' => array( 'emcp_source' => 'related' ),
				)
			);
			$this->add_control(
				'emcp_include_ids',
				array(
					'label'       => __( 'Include IDs', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => '12, 44, 7',
					'description' => __( 'Comma separated. With Manual selection this is also the display order.', 'emcp-tools' ),
					'label_block' => true,
				)
			);
			$this->add_control(
				'emcp_exclude_ids',
				array(
					'label'       => __( 'Exclude IDs', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => '9, 10',
					'label_block' => true,
					'condition'   => array( 'emcp_source!' => 'manual' ),
				)
			);
			$this->add_control(
				'emcp_exclude_current',
				array(
					'label'     => __( 'Exclude current post', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => '',
					'condition' => array( 'emcp_source!' => array( 'current', 'manual' ) ),
				)
			);
			$this->add_control(
				'emcp_ignore_sticky',
				array(
					'label'     => __( 'Ignore sticky posts', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'condition' => array( 'emcp_source' => array( 'posts' ) ),
				)
			);

			$this->add_control(
				'emcp_date',
				array(
					'label'     => __( 'Date range', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'options'   => self::emcp_select_options( EMCP_Tools_Themer_Loop_Options::date_ranges() ),
					'default'   => 'all',
					'separator' => 'before',
					'condition' => array( 'emcp_source!' => array( 'current', 'manual' ) ),
				)
			);
			$this->add_control(
				'emcp_after',
				array(
					'label'       => __( 'After', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => '2026-01-01',
					'condition'   => array(
						'emcp_date'     => 'custom',
						'emcp_source!' => array( 'current', 'manual' ),
					),
				)
			);
			$this->add_control(
				'emcp_before',
				array(
					'label'       => __( 'Before', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => '2026-12-31',
					'condition'   => array(
						'emcp_date'     => 'custom',
						'emcp_source!' => array( 'current', 'manual' ),
					),
				)
			);

			$this->add_control(
				'emcp_orderby',
				array(
					'label'     => __( 'Order by', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'options'   => self::emcp_select_options( EMCP_Tools_Themer_Loop_Options::orderby( $woo ) ),
					'default'   => 'date',
					'separator' => 'before',
					'condition' => array( 'emcp_source!' => array( 'current', 'manual' ) ),
				)
			);
			$this->add_control(
				'emcp_meta_key',
				array(
					'label'     => __( 'Custom field key', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::TEXT,
					'condition' => array(
						'emcp_orderby' => array( 'meta_value', 'meta_value_num' ),
						'emcp_source!' => array( 'current', 'manual' ),
					),
				)
			);
			$this->add_control(
				'emcp_order',
				array(
					'label'     => __( 'Order', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'options'   => self::emcp_select_options(
						array(
							'desc' => __( 'Descending', 'emcp-tools' ),
							'asc'  => __( 'Ascending', 'emcp-tools' ),
						)
					),
					'default'   => 'desc',
					'condition' => array( 'emcp_source!' => array( 'current', 'manual' ) ),
				)
			);

			if ( $woo ) {
				$this->add_control(
					'emcp_hide_out_of_stock',
					array(
						'label'     => __( 'Hide out of stock', 'emcp-tools' ),
						'type'      => \Elementor\Controls_Manager::SWITCHER,
						'separator' => 'before',
						'condition' => array( 'emcp_source' => 'products' ),
					)
				);
				$this->add_control(
					'emcp_on_sale_only',
					array(
						'label'     => __( 'On sale only', 'emcp-tools' ),
						'type'      => \Elementor\Controls_Manager::SWITCHER,
						'condition' => array( 'emcp_source' => 'products' ),
					)
				);
				$this->add_control(
					'emcp_featured_only',
					array(
						'label'     => __( 'Featured only', 'emcp-tools' ),
						'type'      => \Elementor\Controls_Manager::SWITCHER,
						'condition' => array( 'emcp_source' => 'products' ),
					)
				);
			}

			$this->end_controls_section();
		}

		/**
		 * The alternate-templates slot. Free prints a note; the Pro overlay
		 * injects its repeater after this section.
		 */
		protected function emcp_register_alternates_section(): void {
			$this->start_controls_section( 'emcp_section_alternates', array( 'label' => __( 'Alternate templates', 'emcp-tools' ) ) );
			$this->add_control(
				'emcp_alternates_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Show a different Loop Item at chosen positions, for example a wide card every third item. Available in EMCP Pro, which also allows more than one Loop Item.', 'emcp-tools' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->end_controls_section();
		}

		/** Additional options: nothing-found message and the item tag. */
		protected function emcp_register_additional_section(): void {
			$this->start_controls_section( 'emcp_section_additional', array( 'label' => __( 'Additional options', 'emcp-tools' ) ) );
			$this->add_control(
				'emcp_empty_message',
				array(
					'label'       => __( 'Nothing found message', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => __( 'No posts found.', 'emcp-tools' ),
					'label_block' => true,
				)
			);
			$this->add_control(
				'emcp_item_tag',
				array(
					'label'   => __( 'Item HTML tag', 'emcp-tools' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'options' => array(
						'div'     => 'div',
						'article' => 'article',
						'li'      => 'li',
						'section' => 'section',
					),
					'default' => 'div',
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Item style: spacing, background, border, shadow, hover. The hover
		 * effect, its distances and its duration are grid features (the
		 * carousel element prints no hover classes), so only the grid offers
		 * them.
		 */
		protected function emcp_register_item_style_section(): void {
			$sel   = $this->emcp_item_selector();
			$hover = $this->emcp_item_selector( ':hover' );
			$root  = $this->emcp_root();

			$this->start_controls_section(
				'emcp_style_items',
				array(
					'label' => __( 'Items', 'emcp-tools' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);

			$this->add_responsive_control(
				'emcp_item_padding',
				array(
					'label'      => __( 'Padding', 'emcp-tools' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'devices'    => self::DEVICES,
					'selectors'  => array( $sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'emcp_item_radius',
				array(
					'label'      => __( 'Border radius', 'emcp-tools' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;' ),
				)
			);

			$this->start_controls_tabs( 'emcp_item_tabs' );

			$this->start_controls_tab( 'emcp_item_normal', array( 'label' => __( 'Normal', 'emcp-tools' ) ) );
			$this->add_control(
				'emcp_item_bg',
				array(
					'label'     => __( 'Background', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $sel => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'emcp_item_border',
					'selector' => $sel,
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'emcp_item_shadow',
					'selector' => $sel,
				)
			);
			$this->end_controls_tab();

			$this->start_controls_tab( 'emcp_item_hover', array( 'label' => __( 'Hover', 'emcp-tools' ) ) );
			$this->add_control(
				'emcp_item_bg_hover',
				array(
					'label'     => __( 'Background', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $hover => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'emcp_item_border_hover',
				array(
					'label'     => __( 'Border color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $hover => 'border-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'emcp_item_shadow_hover',
					'selector' => $hover,
				)
			);
			if ( 'grid' === $this->emcp_kind() ) {
				$this->add_control(
					'emcp_hover_effect',
					array(
						'label'   => __( 'Hover effect', 'emcp-tools' ),
						'type'    => \Elementor\Controls_Manager::SELECT,
						'options' => self::emcp_select_options(
							array(
								'none'   => __( 'None', 'emcp-tools' ),
								'lift'   => __( 'Lift', 'emcp-tools' ),
								'zoom'   => __( 'Zoom', 'emcp-tools' ),
								'shadow' => __( 'Shadow', 'emcp-tools' ),
							)
						),
						'default' => 'none',
					)
				);
				$this->add_control(
					'emcp_hover_lift',
					array(
						'label'     => __( 'Lift distance', 'emcp-tools' ),
						'type'      => \Elementor\Controls_Manager::SLIDER,
						'range'     => array(
							'px' => array(
								'min' => 0,
								'max' => 40,
							),
						),
						'default'   => array(
							'size' => 6,
							'unit' => 'px',
						),
						'selectors' => array( $root => '--emcp-lift: {{SIZE}}{{UNIT}};' ),
						'condition' => array( 'emcp_hover_effect' => 'lift' ),
					)
				);
				$this->add_control(
					'emcp_hover_zoom',
					array(
						'label'     => __( 'Zoom scale', 'emcp-tools' ),
						'type'      => \Elementor\Controls_Manager::SLIDER,
						'range'     => array(
							'px' => array(
								'min'  => 1,
								'max'  => 1.3,
								'step' => 0.01,
							),
						),
						'default'   => array( 'size' => 1.03 ),
						'selectors' => array( $root => '--emcp-zoom: {{SIZE}};' ),
						'condition' => array( 'emcp_hover_effect' => 'zoom' ),
					)
				);
				$this->add_control(
					'emcp_hover_duration',
					array(
						'label'     => __( 'Transition duration (ms)', 'emcp-tools' ),
						'type'      => \Elementor\Controls_Manager::SLIDER,
						'range'     => array(
							'px' => array(
								'min'  => 0,
								'max'  => 2000,
								'step' => 50,
							),
						),
						'default'   => array( 'size' => 250 ),
						'selectors' => array( $root => '--emcp-hover-duration: {{SIZE}}ms;' ),
						'condition' => array( 'emcp_hover_effect!' => 'none' ),
					)
				);
			}
			$this->end_controls_tab();

			$this->end_controls_tabs();
			$this->end_controls_section();
		}

		/** Nothing-found style. */
		protected function emcp_register_empty_style_section(): void {
			$this->start_controls_section(
				'emcp_style_empty',
				array(
					'label' => __( 'Nothing found', 'emcp-tools' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'emcp_empty_typography',
					'selector' => EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .emcp-loop__empty' ),
				)
			);
			$this->add_control(
				'emcp_empty_color',
				array(
					'label'     => __( 'Color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .emcp-loop__empty' ) => 'color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'emcp_empty_align',
				array(
					'label'     => __( 'Alignment', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::CHOOSE,
					'devices'   => self::DEVICES,
					'options'   => array(
						'left'   => array(
							'title' => __( 'Left', 'emcp-tools' ),
							'icon'  => 'eicon-text-align-left',
						),
						'center' => array(
							'title' => __( 'Center', 'emcp-tools' ),
							'icon'  => 'eicon-text-align-center',
						),
						'right'  => array(
							'title' => __( 'Right', 'emcp-tools' ),
							'icon'  => 'eicon-text-align-right',
						),
					),
					'selectors' => array( $this->emcp_root() => '--emcp-empty-align: {{VALUE}};' ),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * A left / center / right CHOOSE control's options, as flex values.
		 *
		 * @return array
		 */
		protected static function emcp_flex_align_options(): array {
			return array(
				'flex-start' => array(
					'title' => __( 'Left', 'emcp-tools' ),
					'icon'  => 'eicon-text-align-left',
				),
				'center'     => array(
					'title' => __( 'Center', 'emcp-tools' ),
					'icon'  => 'eicon-text-align-center',
				),
				'flex-end'   => array(
					'title' => __( 'Right', 'emcp-tools' ),
					'icon'  => 'eicon-text-align-right',
				),
			);
		}

		/**
		 * A plain slider range.
		 *
		 * @param float $min  Min.
		 * @param float $max  Max.
		 * @param float $step Step.
		 * @return array
		 */
		protected static function emcp_px( float $min, float $max, float $step = 1 ): array {
			return array(
				'px' => array(
					'min'  => $min,
					'max'  => $max,
					'step' => $step,
				),
			);
		}
	}
}
