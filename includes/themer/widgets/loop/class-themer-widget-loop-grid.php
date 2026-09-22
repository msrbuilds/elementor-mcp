<?php
/**
 * Loop Grid Elementor widget.
 *
 * The element prints no inline custom properties for this widget
 * (inline_vars => false in the settings map), so every layout value below is
 * written by Elementor's own selectors and keeps its responsive and unit
 * choices. The first-item span is the exception: the element prints it,
 * clamped to the column count.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EMCP_Tools_Themer_Widget_Loop_Grid' ) && class_exists( 'EMCP_Tools_Themer_Widget_Loop_Base' ) ) {

	/**
	 * @since 3.18.0
	 */
	class EMCP_Tools_Themer_Widget_Loop_Grid extends EMCP_Tools_Themer_Widget_Loop_Base {

		/** @return string */
		protected function emcp_kind(): string {
			return 'grid';
		}

		/** @return string */
		public function get_name(): string {
			return 'emcp-loop-grid';
		}

		/** @return string */
		public function get_title(): string {
			return __( 'Loop Grid', 'emcp-tools' );
		}

		/** @return string */
		public function get_icon(): string {
			return 'eicon-posts-grid';
		}

		/** @return string[] */
		public function get_keywords(): array {
			return array( 'loop', 'grid', 'posts', 'archive', 'cards', 'emcp' );
		}

		/**
		 * @param array $s Settings.
		 * @return array
		 */
		protected function emcp_args( array $s ): array {
			return EMCP_Tools_Themer_Loop_Widget_Map::grid( $s, $this->emcp_uid() );
		}

		/** Controls. */
		protected function register_controls(): void {
			$d    = EMCP_Tools_Themer_Element_Loop_Grid::defaults();
			$root = $this->emcp_root();

			// ---- Content: layout ----
			$this->start_controls_section( 'emcp_section_layout', array( 'label' => __( 'Layout', 'emcp-tools' ) ) );
			$this->emcp_register_template_control();
			$this->add_responsive_control(
				'emcp_columns',
				array(
					'label'          => __( 'Columns', 'emcp-tools' ),
					'type'           => \Elementor\Controls_Manager::SELECT,
					'options'        => array(
						'1' => '1',
						'2' => '2',
						'3' => '3',
						'4' => '4',
						'5' => '5',
						'6' => '6',
					),
					'devices'        => self::DEVICES,
					'default'        => (string) $d['columns'],
					'tablet_default' => (string) $d['columns_tablet'],
					'mobile_default' => (string) $d['columns_mobile'],
					// Each device's rule sets all three: the stylesheet reads
					// --emcp-cols-t inside its tablet query and --emcp-cols-m
					// inside its mobile one, and Elementor's device rules sit in
					// matching media queries, so each device's value wins there.
					'selectors'      => array( $root => '--emcp-cols: {{VALUE}}; --emcp-cols-t: {{VALUE}}; --emcp-cols-m: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'emcp_masonry',
				array(
					'label'       => __( 'Masonry', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'description' => __( 'Cards keep their own height and fill the gaps.', 'emcp-tools' ),
				)
			);
			$this->add_control(
				'emcp_equal_height',
				array(
					'label'     => __( 'Equal height', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'condition' => array( 'emcp_masonry' => '' ),
				)
			);
			$this->add_control(
				'emcp_first_item_span',
				array(
					'label'       => __( 'First item spans', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 1,
					'max'         => 6,
					'default'     => (int) $d['first_item_span'],
					'description' => __( 'Columns the first card occupies on desktop, for a featured post at the top of the grid. Never more than the column count.', 'emcp-tools' ),
					'condition'   => array( 'emcp_masonry' => '' ),
				)
			);
			$this->end_controls_section();

			$this->emcp_register_query_section();

			// ---- Content: pagination ----
			$this->start_controls_section( 'emcp_section_pagination', array( 'label' => __( 'Pagination', 'emcp-tools' ) ) );
			$this->add_control(
				'emcp_pagination',
				array(
					'label'   => __( 'Pagination', 'emcp-tools' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'options' => self::emcp_select_options(
						array(
							'none'              => __( 'None', 'emcp-tools' ),
							'numbers'           => __( 'Numbers', 'emcp-tools' ),
							'prev_next'         => __( 'Previous / Next', 'emcp-tools' ),
							'numbers_prev_next' => __( 'Numbers + Previous / Next', 'emcp-tools' ),
							'load_more'         => __( 'Load more button', 'emcp-tools' ),
							'infinite'          => __( 'Infinite scroll', 'emcp-tools' ),
						)
					),
					'default' => 'none',
				)
			);
			$this->add_control(
				'emcp_load_type',
				array(
					'label'       => __( 'Load type', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => self::emcp_select_options(
						array(
							'reload' => __( 'Page reload', 'emcp-tools' ),
							'ajax'   => __( 'AJAX', 'emcp-tools' ),
						)
					),
					'default'     => 'reload',
					'description' => __( 'AJAX replaces the cards without reloading the page and keeps the URL in step.', 'emcp-tools' ),
					'condition'   => array( 'emcp_pagination' => array( 'numbers', 'prev_next', 'numbers_prev_next' ) ),
				)
			);
			$this->add_control(
				'emcp_page_limit',
				array(
					'label'       => __( 'Page limit', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 0,
					'default'     => (int) $d['page_limit'],
					'description' => __( '0 shows every page.', 'emcp-tools' ),
					'condition'   => array( 'emcp_pagination!' => 'none' ),
				)
			);
			$this->add_control(
				'emcp_shorten',
				array(
					'label'     => __( 'Shorten', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'condition' => array( 'emcp_pagination' => array( 'numbers', 'numbers_prev_next' ) ),
				)
			);
			$this->add_control(
				'emcp_prev_label',
				array(
					'label'     => __( 'Previous label', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::TEXT,
					'default'   => __( 'Previous', 'emcp-tools' ),
					'condition' => array( 'emcp_pagination' => array( 'prev_next', 'numbers_prev_next' ) ),
				)
			);
			$this->add_control(
				'emcp_next_label',
				array(
					'label'     => __( 'Next label', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::TEXT,
					'default'   => __( 'Next', 'emcp-tools' ),
					'condition' => array( 'emcp_pagination' => array( 'prev_next', 'numbers_prev_next' ) ),
				)
			);
			$this->add_control(
				'emcp_load_more_label',
				array(
					'label'     => __( 'Button text', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::TEXT,
					'default'   => __( 'Load more', 'emcp-tools' ),
					'condition' => array( 'emcp_pagination' => 'load_more' ),
				)
			);
			$this->add_control(
				'emcp_infinite_offset',
				array(
					'label'       => __( 'Trigger offset (px)', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 0,
					'max'         => 2000,
					'default'     => (int) $d['infinite_offset'],
					'description' => __( 'How far before the end of the grid the next page starts loading.', 'emcp-tools' ),
					'condition'   => array( 'emcp_pagination' => 'infinite' ),
				)
			);
			$this->end_controls_section();

			$this->emcp_register_alternates_section();
			$this->emcp_register_additional_section();

			// ---- Style: layout ----
			$this->start_controls_section(
				'emcp_style_layout',
				array(
					'label' => __( 'Layout', 'emcp-tools' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_responsive_control(
				'emcp_gap_x',
				array(
					'label'     => __( 'Gap between columns', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 0, 120 ),
					'devices'   => self::DEVICES,
					'default'   => array(
						'size' => (int) $d['gap_x'],
						'unit' => 'px',
					),
					'selectors' => array( $root => '--emcp-gap-x: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'emcp_gap_y',
				array(
					'label'     => __( 'Gap between rows', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 0, 120 ),
					'devices'   => self::DEVICES,
					'default'   => array(
						'size' => (int) $d['gap_y'],
						'unit' => 'px',
					),
					'selectors' => array( $root => '--emcp-gap-y: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'emcp_max_width',
				array(
					'label'      => __( 'Max grid width', 'emcp-tools' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%' ),
					'range'      => self::emcp_px( 200, 2000 ),
					'devices'    => self::DEVICES,
					'selectors'  => array( EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .emcp-loop__items' ) => 'max-width: {{SIZE}}{{UNIT}}; margin-inline: auto;' ),
				)
			);
			$this->end_controls_section();

			$this->emcp_register_item_style_section();
			$this->emcp_register_animation_section();
			$this->emcp_register_pagination_style_section();
			$this->emcp_register_more_style_section();
			$this->emcp_register_empty_style_section();
		}

		/** Entrance animation. */
		private function emcp_register_animation_section(): void {
			$root = $this->emcp_root();
			$this->start_controls_section(
				'emcp_style_animation',
				array(
					'label' => __( 'Entrance animation', 'emcp-tools' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'emcp_animation',
				array(
					'label'   => __( 'Animation', 'emcp-tools' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'options' => self::emcp_select_options(
						array(
							'none'    => __( 'None', 'emcp-tools' ),
							'fade-up' => __( 'Fade up', 'emcp-tools' ),
							'fade-in' => __( 'Fade in', 'emcp-tools' ),
							'zoom-in' => __( 'Zoom in', 'emcp-tools' ),
						)
					),
					'default' => 'none',
				)
			);
			$this->add_control(
				'emcp_animation_duration',
				array(
					'label'     => __( 'Duration (ms)', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 100, 2000, 50 ),
					'default'   => array( 'size' => 500 ),
					'selectors' => array( $root => '--emcp-anim-duration: {{SIZE}}ms;' ),
					'condition' => array( 'emcp_animation!' => 'none' ),
				)
			);
			$this->add_control(
				'emcp_animation_step',
				array(
					'label'       => __( 'Stagger step (ms)', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'range'       => self::emcp_px( 0, 500, 10 ),
					'default'     => array( 'size' => (int) EMCP_Tools_Themer_Element_Loop_Grid::defaults()['animation_step'] ),
					// Read by the script (getComputedStyle), not the stylesheet.
					'selectors'   => array( $root => '--emcp-anim-step: {{SIZE}}ms;' ),
					'description' => __( 'Delay added per item, so the cards appear one after another.', 'emcp-tools' ),
					'condition'   => array( 'emcp_animation!' => 'none' ),
				)
			);
			$this->end_controls_section();
		}

		/** Numbered and previous / next pagination style. */
		private function emcp_register_pagination_style_section(): void {
			$root = $this->emcp_root();
			$this->start_controls_section(
				'emcp_style_pagination',
				array(
					'label'     => __( 'Pagination', 'emcp-tools' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'emcp_pagination' => array( 'numbers', 'prev_next', 'numbers_prev_next' ) ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'emcp_pagination_typography',
					'selector' => EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .emcp-loop__pagination .page-numbers' ),
				)
			);
			$this->add_control(
				'emcp_pagination_color',
				array(
					'label'     => __( 'Color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					// Not the current page: its own colours come from the two
					// current-page controls below, and this rule is more
					// specific than the stylesheet's current-page rule.
					'selectors' => array( EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .emcp-loop__pagination .page-numbers:not(.current)' ) => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'emcp_pagination_hover',
				array(
					'label'     => __( 'Hover color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .emcp-loop__pagination a.page-numbers:hover' ) => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'emcp_pagination_active_bg',
				array(
					'label'     => __( 'Current page background', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $root => '--emcp-pagination-active-bg: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'emcp_pagination_active_color',
				array(
					'label'     => __( 'Current page text', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $root => '--emcp-pagination-active-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'emcp_pagination_gap',
				array(
					'label'     => __( 'Space between', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 0, 60 ),
					'separator' => 'before',
					'selectors' => array( $root => '--emcp-pagination-gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'emcp_pagination_margin',
				array(
					'label'     => __( 'Space from grid', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 0, 160 ),
					'selectors' => array( $root => '--emcp-pagination-margin: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'emcp_pagination_align',
				array(
					'label'     => __( 'Alignment', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::CHOOSE,
					'options'   => self::emcp_flex_align_options(),
					'devices'   => self::DEVICES,
					'selectors' => array( $root => '--emcp-pagination-align: {{VALUE}};' ),
				)
			);
			$this->end_controls_section();
		}

		/** Load more button style. */
		private function emcp_register_more_style_section(): void {
			$root = $this->emcp_root();
			$btn  = EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .emcp-loop__more > .emcp-loop__more-btn' );
			$this->start_controls_section(
				'emcp_style_more',
				array(
					'label'     => __( 'Load more button', 'emcp-tools' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'emcp_pagination' => 'load_more' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'emcp_more_typography',
					'selector' => $btn,
				)
			);
			$this->add_responsive_control(
				'emcp_more_padding',
				array(
					'label'      => __( 'Padding', 'emcp-tools' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'devices'    => self::DEVICES,
					'selectors'  => array( $btn => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->start_controls_tabs( 'emcp_more_tabs' );
			$this->start_controls_tab( 'emcp_more_normal', array( 'label' => __( 'Normal', 'emcp-tools' ) ) );
			$this->add_control(
				'emcp_more_color',
				array(
					'label'     => __( 'Text color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $btn => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'emcp_more_bg',
				array(
					'label'     => __( 'Background', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $btn => 'background-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab( 'emcp_more_hover_tab', array( 'label' => __( 'Hover', 'emcp-tools' ) ) );
			$this->add_control(
				'emcp_more_color_hover',
				array(
					'label'     => __( 'Text color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .emcp-loop__more > .emcp-loop__more-btn:hover' ) => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'emcp_more_bg_hover',
				array(
					'label'     => __( 'Background', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .emcp-loop__more > .emcp-loop__more-btn:hover' ) => 'background-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'      => 'emcp_more_border',
					'selector'  => $btn,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'emcp_more_radius',
				array(
					'label'      => __( 'Border radius', 'emcp-tools' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( $btn => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'emcp_more_margin',
				array(
					'label'     => __( 'Space from grid', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 0, 160 ),
					'selectors' => array( $root => '--emcp-more-margin: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'emcp_more_align',
				array(
					'label'     => __( 'Alignment', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::CHOOSE,
					'options'   => self::emcp_flex_align_options(),
					'devices'   => self::DEVICES,
					'selectors' => array( $root => '--emcp-more-align: {{VALUE}};' ),
				)
			);
			$this->end_controls_section();
		}
	}
}
