<?php
/**
 * Loop Carousel Elementor widget.
 *
 * The slide counts, gaps and side offset are value-only controls (no
 * selectors): the element prints them as inline custom properties, which
 * outrank any selector, and Swiper's own options come from the same values,
 * so both always agree. Their responsive controls are frontend_available so
 * Elementor keeps a per-device setting for each (the settings map reads
 * them). Every other style control writes through a selector scoped to this
 * carousel's own loop.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EMCP_Tools_Themer_Widget_Loop_Carousel' ) && class_exists( 'EMCP_Tools_Themer_Widget_Loop_Base' ) ) {

	/**
	 * @since 3.18.0
	 */
	class EMCP_Tools_Themer_Widget_Loop_Carousel extends EMCP_Tools_Themer_Widget_Loop_Base {

		/** @return string */
		protected function emcp_kind(): string {
			return 'carousel';
		}

		/** @return string */
		public function get_name(): string {
			return 'emcp-loop-carousel';
		}

		/** @return string */
		public function get_title(): string {
			return __( 'Loop Carousel', 'emcp-tools' );
		}

		/** @return string */
		public function get_icon(): string {
			return 'eicon-slides';
		}

		/** @return string[] */
		public function get_keywords(): array {
			return array( 'loop', 'carousel', 'slider', 'posts', 'emcp' );
		}

		/**
		 * @param array $s Settings.
		 * @return array
		 */
		protected function emcp_args( array $s ): array {
			return EMCP_Tools_Themer_Loop_Widget_Map::carousel( $s, $this->emcp_uid() );
		}

		/** Controls. */
		protected function register_controls(): void {
			$d = EMCP_Tools_Themer_Element_Loop_Carousel::defaults();

			// ---- Content: layout ----
			$this->start_controls_section( 'emcp_section_layout', array( 'label' => __( 'Layout', 'emcp-tools' ) ) );
			$this->emcp_register_template_control();

			$slides = array( 'auto' => __( 'Auto (card width)', 'emcp-tools' ) );
			for ( $i = 1; $i <= 10; $i++ ) {
				$slides[ (string) $i ] = (string) $i;
			}
			$this->add_responsive_control(
				'emcp_slides',
				array(
					'label'              => __( 'Slides per view', 'emcp-tools' ),
					'type'               => \Elementor\Controls_Manager::SELECT,
					'options'            => self::emcp_select_options( $slides ),
					'devices'            => self::DEVICES,
					'default'            => (string) $d['slides'],
					'tablet_default'     => (string) $d['slides_tablet'],
					'mobile_default'     => (string) $d['slides_mobile'],
					'frontend_available' => true,
				)
			);
			$this->add_responsive_control(
				'emcp_slides_to_scroll',
				array(
					'label'              => __( 'Slides to scroll', 'emcp-tools' ),
					'type'               => \Elementor\Controls_Manager::NUMBER,
					'min'                => 1,
					'max'                => 10,
					'devices'            => self::DEVICES,
					'default'            => (int) $d['slides_to_scroll'],
					'tablet_default'     => (int) $d['slides_to_scroll_tablet'],
					'mobile_default'     => (int) $d['slides_to_scroll_mobile'],
					'frontend_available' => true,
				)
			);
			$this->add_responsive_control(
				'emcp_gap',
				array(
					'label'              => __( 'Gap between slides', 'emcp-tools' ),
					'type'               => \Elementor\Controls_Manager::SLIDER,
					'size_units'         => array( 'px' ),
					'range'              => self::emcp_px( 0, 120 ),
					'devices'            => self::DEVICES,
					'default'            => array(
						'size' => (int) $d['gap'],
						'unit' => 'px',
					),
					'tablet_default'     => array(
						'size' => (int) $d['gap_tablet'],
						'unit' => 'px',
					),
					'mobile_default'     => array(
						'size' => (int) $d['gap_mobile'],
						'unit' => 'px',
					),
					'frontend_available' => true,
				)
			);
			$this->add_control(
				'emcp_height',
				array(
					'label'   => __( 'Slide height', 'emcp-tools' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'options' => self::emcp_select_options(
						array(
							'auto'  => __( 'Fit each card', 'emcp-tools' ),
							'equal' => __( 'Equal height', 'emcp-tools' ),
						)
					),
					'default' => (string) $d['height'],
				)
			);
			$this->end_controls_section();

			$this->emcp_register_query_section();

			// ---- Content: carousel settings ----
			$this->start_controls_section( 'emcp_section_settings', array( 'label' => __( 'Carousel settings', 'emcp-tools' ) ) );
			$this->add_control(
				'emcp_autoplay',
				array(
					'label' => __( 'Autoplay', 'emcp-tools' ),
					'type'  => \Elementor\Controls_Manager::SWITCHER,
				)
			);
			$this->add_control(
				'emcp_autoplay_delay',
				array(
					'label'     => __( 'Autoplay delay (ms)', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'min'       => 500,
					'max'       => 20000,
					'step'      => 100,
					'default'   => (int) $d['autoplay_delay'],
					'condition' => array( 'emcp_autoplay' => 'yes' ),
				)
			);
			$this->add_control(
				'emcp_pause_on_hover',
				array(
					'label'     => __( 'Pause on hover', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'condition' => array( 'emcp_autoplay' => 'yes' ),
				)
			);
			$this->add_control(
				'emcp_pause_on_interaction',
				array(
					'label'     => __( 'Stop on interaction', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'condition' => array( 'emcp_autoplay' => 'yes' ),
				)
			);
			$this->add_control(
				'emcp_loop',
				array(
					'label' => __( 'Infinite loop', 'emcp-tools' ),
					'type'  => \Elementor\Controls_Manager::SWITCHER,
				)
			);
			$this->add_control(
				'emcp_speed',
				array(
					'label'   => __( 'Transition speed (ms)', 'emcp-tools' ),
					'type'    => \Elementor\Controls_Manager::SLIDER,
					'range'   => self::emcp_px( 100, 3000, 50 ),
					'default' => array( 'size' => (int) $d['speed'] ),
				)
			);
			$this->add_control(
				'emcp_direction',
				array(
					'label'   => __( 'Direction', 'emcp-tools' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'options' => self::emcp_select_options(
						array(
							'ltr' => __( 'Left to right', 'emcp-tools' ),
							'rtl' => __( 'Right to left', 'emcp-tools' ),
						)
					),
					'default' => (string) $d['direction'],
				)
			);
			$this->add_control(
				'emcp_centered',
				array(
					'label' => __( 'Centered slides', 'emcp-tools' ),
					'type'  => \Elementor\Controls_Manager::SWITCHER,
				)
			);
			$this->add_control(
				'emcp_offset_sides',
				array(
					'label'       => __( 'Peek next slides', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => self::emcp_select_options(
						array(
							'none'  => __( 'None', 'emcp-tools' ),
							'both'  => __( 'Both sides', 'emcp-tools' ),
							'left'  => __( 'Left', 'emcp-tools' ),
							'right' => __( 'Right', 'emcp-tools' ),
						)
					),
					'default'     => (string) $d['offset_sides'],
					'description' => __( 'Leaves room at the side so part of the next slide shows.', 'emcp-tools' ),
				)
			);
			$this->add_control(
				'emcp_offset_width',
				array(
					'label'     => __( 'Peek width', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 0, 200 ),
					'default'   => array(
						'size' => (int) $d['offset_width'],
						'unit' => 'px',
					),
					'condition' => array( 'emcp_offset_sides!' => 'none' ),
				)
			);
			$this->add_control(
				'emcp_effect',
				array(
					'label'   => __( 'Effect', 'emcp-tools' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'options' => self::emcp_select_options(
						array(
							'slide'     => __( 'Slide', 'emcp-tools' ),
							'fade'      => __( 'Fade (one slide at a time)', 'emcp-tools' ),
							'coverflow' => __( 'Coverflow', 'emcp-tools' ),
						)
					),
					'default' => (string) $d['effect'],
				)
			);
			$this->add_control(
				'emcp_keyboard',
				array(
					'label' => __( 'Keyboard navigation', 'emcp-tools' ),
					'type'  => \Elementor\Controls_Manager::SWITCHER,
				)
			);
			$this->add_control(
				'emcp_mousewheel',
				array(
					'label' => __( 'Mouse wheel', 'emcp-tools' ),
					'type'  => \Elementor\Controls_Manager::SWITCHER,
				)
			);
			$this->end_controls_section();

			// ---- Content: navigation ----
			$this->start_controls_section( 'emcp_section_navigation', array( 'label' => __( 'Navigation', 'emcp-tools' ) ) );
			$this->add_control(
				'emcp_arrows',
				array(
					'label'   => __( 'Arrows', 'emcp-tools' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'emcp_arrows_position',
				array(
					'label'     => __( 'Arrows position', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'options'   => self::emcp_select_options(
						array(
							'inside'  => __( 'Inside', 'emcp-tools' ),
							'outside' => __( 'Outside', 'emcp-tools' ),
							'bottom'  => __( 'Below', 'emcp-tools' ),
						)
					),
					'default'   => (string) $d['arrows_position'],
					'condition' => array( 'emcp_arrows' => 'yes' ),
				)
			);
			foreach ( array(
				'emcp_arrow_prev' => __( 'Previous arrow icon', 'emcp-tools' ),
				'emcp_arrow_next' => __( 'Next arrow icon', 'emcp-tools' ),
			) as $key => $label ) {
				$this->add_control(
					$key,
					array(
						'label'                  => $label,
						'type'                   => \Elementor\Controls_Manager::ICONS,
						'skin'                   => 'inline',
						'label_block'            => false,
						// SVG upload only: a font icon would need its icon font
						// loaded, which the loop does not do.
						'exclude_inline_options' => array( 'icon' ),
						'condition'              => array( 'emcp_arrows' => 'yes' ),
					)
				);
			}
			$this->add_control(
				'emcp_hide_arrows_mobile',
				array(
					'label'       => __( 'Hide arrows on mobile', 'emcp-tools' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'description' => __( 'Below 768px, where swiping is the usual way to move.', 'emcp-tools' ),
					'condition'   => array( 'emcp_arrows' => 'yes' ),
				)
			);
			$this->add_control(
				'emcp_dots',
				array(
					'label'     => __( 'Pagination', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'options'   => self::emcp_select_options(
						array(
							'none'     => __( 'None', 'emcp-tools' ),
							'bullets'  => __( 'Dots', 'emcp-tools' ),
							'fraction' => __( 'Fraction (1 / 5)', 'emcp-tools' ),
							'progress' => __( 'Progress bar', 'emcp-tools' ),
						)
					),
					'default'   => (string) $d['dots'],
					'separator' => 'before',
				)
			);
			$this->add_control(
				'emcp_dots_position',
				array(
					'label'     => __( 'Pagination position', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'options'   => self::emcp_select_options(
						array(
							'inside'  => __( 'Inside', 'emcp-tools' ),
							'outside' => __( 'Below', 'emcp-tools' ),
						)
					),
					'default'   => (string) $d['dots_position'],
					'condition' => array( 'emcp_dots!' => 'none' ),
				)
			);
			$this->end_controls_section();

			$this->emcp_register_additional_section();

			// ---- Style ----
			$this->emcp_register_item_style_section();
			$this->emcp_register_slides_style_section();
			$this->emcp_register_arrows_style_section();
			$this->emcp_register_dots_style_section();
			$this->emcp_register_empty_style_section();
		}

		/** The active slide's scale, for centered carousels. */
		private function emcp_register_slides_style_section(): void {
			$this->start_controls_section(
				'emcp_style_slides',
				array(
					'label'     => __( 'Active slide', 'emcp-tools' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array(
						'emcp_centered' => 'yes',
						'emcp_effect'   => 'slide',
					),
				)
			);
			$this->add_control(
				'emcp_active_scale',
				array(
					'label'     => __( 'Active slide scale', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 1, 1.3, 0.01 ),
					// Swiper puts its active class on the item itself. Slide
					// effect only: fade and coverflow write inline transforms.
					'selectors' => array(
						EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .swiper > .swiper-wrapper > .emcp-loop__item.swiper-slide-active' ) => 'transform: scale({{SIZE}});',
						EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .swiper > .swiper-wrapper > .emcp-loop__item' ) => 'transition: transform 300ms ease;',
					),
					'condition' => array(
						'emcp_centered' => 'yes',
						'emcp_effect'   => 'slide',
					),
				)
			);
			$this->end_controls_section();
		}

		/** Arrow style. */
		private function emcp_register_arrows_style_section(): void {
			$root  = $this->emcp_root();
			$hover = EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .emcp-loop__arrow:hover' );
			$this->start_controls_section(
				'emcp_style_arrows',
				array(
					'label'     => __( 'Arrows', 'emcp-tools' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'emcp_arrows' => 'yes' ),
				)
			);
			$this->add_control(
				'emcp_arrow_size',
				array(
					'label'     => __( 'Size', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 20, 100 ),
					'selectors' => array( $root => '--emcp-arrow-size: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->start_controls_tabs( 'emcp_arrow_tabs' );
			$this->start_controls_tab( 'emcp_arrow_normal', array( 'label' => __( 'Normal', 'emcp-tools' ) ) );
			$this->add_control(
				'emcp_arrow_color',
				array(
					'label'     => __( 'Icon color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $root => '--emcp-arrow-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'emcp_arrow_bg',
				array(
					'label'     => __( 'Background', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $root => '--emcp-arrow-bg: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'emcp_arrow_border_color',
				array(
					'label'     => __( 'Border color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $root => '--emcp-arrow-border: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab( 'emcp_arrow_hover', array( 'label' => __( 'Hover', 'emcp-tools' ) ) );
			// Set on the hovered arrow itself, which reads the same properties.
			$this->add_control(
				'emcp_arrow_color_hover',
				array(
					'label'     => __( 'Icon color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $hover => '--emcp-arrow-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'emcp_arrow_bg_hover',
				array(
					'label'     => __( 'Background', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $hover => '--emcp-arrow-bg: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'emcp_arrow_border_color_hover',
				array(
					'label'     => __( 'Border color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $hover => '--emcp-arrow-border: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_control(
				'emcp_arrow_radius',
				array(
					'label'      => __( 'Border radius', 'emcp-tools' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( '%', 'px' ),
					'range'      => array(
						'%'  => array(
							'min' => 0,
							'max' => 50,
						),
						'px' => array(
							'min' => 0,
							'max' => 50,
						),
					),
					'separator'  => 'before',
					'selectors'  => array( $root => '--emcp-arrow-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'emcp_arrow_offset',
				array(
					'label'     => __( 'Distance from edge', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( -60, 60 ),
					'selectors' => array( $root => '--emcp-arrow-offset: {{SIZE}}{{UNIT}};' ),
					'condition' => array( 'emcp_arrows_position' => 'inside' ),
				)
			);
			$this->end_controls_section();
		}

		/** Dots, fraction and progress bar style. */
		private function emcp_register_dots_style_section(): void {
			$root = $this->emcp_root();
			$this->start_controls_section(
				'emcp_style_dots',
				array(
					'label'     => __( 'Pagination', 'emcp-tools' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'emcp_dots!' => 'none' ),
				)
			);
			$this->add_control(
				'emcp_dot_size',
				array(
					'label'     => __( 'Dot size', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 4, 24 ),
					'selectors' => array( $root => '--emcp-dot-size: {{SIZE}}{{UNIT}};' ),
					'condition' => array( 'emcp_dots' => 'bullets' ),
				)
			);
			$this->add_control(
				'emcp_dot_gap',
				array(
					'label'     => __( 'Space between dots', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 0, 40 ),
					'selectors' => array( $root => '--emcp-dot-gap: {{SIZE}}{{UNIT}};' ),
					'condition' => array( 'emcp_dots' => 'bullets' ),
				)
			);
			$this->add_control(
				'emcp_dot_color',
				array(
					'label'     => __( 'Color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $root => '--emcp-dot-color: {{VALUE}};' ),
					'condition' => array( 'emcp_dots' => array( 'bullets', 'fraction' ) ),
				)
			);
			$this->add_control(
				'emcp_dot_active_color',
				array(
					'label'     => __( 'Active dot color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $root => '--emcp-dot-active-color: {{VALUE}};' ),
					'condition' => array( 'emcp_dots' => 'bullets' ),
				)
			);
			$this->add_control(
				'emcp_dot_active_width',
				array(
					'label'     => __( 'Active dot width', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 4, 60 ),
					'selectors' => array( $root => '--emcp-dot-active-width: {{SIZE}}{{UNIT}};' ),
					'condition' => array( 'emcp_dots' => 'bullets' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'      => 'emcp_fraction_typography',
					'selector'  => EMCP_Tools_Themer_Loop_Widget_Map::selector( '> .emcp-loop__dots' ),
					'condition' => array( 'emcp_dots' => 'fraction' ),
				)
			);
			$this->add_control(
				'emcp_progress_height',
				array(
					'label'     => __( 'Bar height', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 1, 20 ),
					'selectors' => array( $root => '--emcp-progress-height: {{SIZE}}{{UNIT}};' ),
					'condition' => array( 'emcp_dots' => 'progress' ),
				)
			);
			$this->add_control(
				'emcp_progress_track',
				array(
					'label'     => __( 'Track color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $root => '--emcp-progress-track: {{VALUE}};' ),
					'condition' => array( 'emcp_dots' => 'progress' ),
				)
			);
			$this->add_control(
				'emcp_progress_fill',
				array(
					'label'     => __( 'Fill color', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $root => '--emcp-progress-fill: {{VALUE}};' ),
					'condition' => array( 'emcp_dots' => 'progress' ),
				)
			);
			$this->add_control(
				'emcp_dots_offset',
				array(
					'label'     => __( 'Distance', 'emcp-tools' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => self::emcp_px( 0, 80 ),
					'separator' => 'before',
					'selectors' => array( $root => '--emcp-dots-offset: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();
		}
	}
}
