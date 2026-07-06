<?php
/**
 * Themer dynamic Elementor widget classes (base + one thin subclass per element).
 *
 * Required only from EMCP_Tools_Themer_Widgets::register_widgets(), i.e. inside
 * `elementor/widgets/register`, so \Elementor\Widget_Base is guaranteed loaded.
 * The base builds its content controls from the SAME descriptors the Gutenberg
 * blocks use (EMCP_Tools_Themer_Blocks::blocks()) and renders through the shared
 * provider, plus a Style tab (color/typography/alignment) via Elementor selectors.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	return;
}

/**
 * Base dynamic widget. Subclasses only declare their catalog key.
 *
 * @since 3.1.0
 */
abstract class EMCP_Tools_Themer_Widget_Base extends \Elementor\Widget_Base {

	/** The catalog key (post-title, archive-loop, …). */
	abstract protected function emcp_key(): string;

	/** The fully-qualified subclass names to register. */
	public static function widget_classes(): array {
		return array(
			'EMCP_Tools_Themer_Widget_Post_Title',
			'EMCP_Tools_Themer_Widget_Archive_Title',
			'EMCP_Tools_Themer_Widget_Breadcrumbs',
			'EMCP_Tools_Themer_Widget_Post_Meta',
			'EMCP_Tools_Themer_Widget_Site_Logo',
			'EMCP_Tools_Themer_Widget_Site_Title',
			'EMCP_Tools_Themer_Widget_Nav_Menu',
			'EMCP_Tools_Themer_Widget_Description',
			'EMCP_Tools_Themer_Widget_Post_Content',
			'EMCP_Tools_Themer_Widget_Archive_Loop',
		);
	}

	/** key => [title, eicon]. */
	private static function meta(): array {
		return array(
			'post-title'    => array( __( 'Post/Page Title', 'emcp-tools' ), 'eicon-post-title' ),
			'archive-title' => array( __( 'Archive Title', 'emcp-tools' ), 'eicon-archive-title' ),
			'breadcrumbs'   => array( __( 'Breadcrumbs', 'emcp-tools' ), 'eicon-navigation-horizontal' ),
			'post-meta'     => array( __( 'Post Meta', 'emcp-tools' ), 'eicon-post-info' ),
			'site-logo'     => array( __( 'Site Logo', 'emcp-tools' ), 'eicon-site-logo' ),
			'site-title'    => array( __( 'Site Title', 'emcp-tools' ), 'eicon-site-title' ),
			'nav-menu'      => array( __( 'Menu', 'emcp-tools' ), 'eicon-nav-menu' ),
			'description'   => array( __( 'Description', 'emcp-tools' ), 'eicon-text' ),
			'post-content'  => array( __( 'Post Content', 'emcp-tools' ), 'eicon-post-content' ),
			'archive-loop'  => array( __( 'Archive Posts', 'emcp-tools' ), 'eicon-posts-grid' ),
		);
	}

	public function get_name(): string {
		return 'emcp-' . $this->emcp_key();
	}

	public function get_title(): string {
		$m = self::meta();
		return $m[ $this->emcp_key() ][0] ?? $this->emcp_key();
	}

	public function get_icon(): string {
		$m = self::meta();
		return $m[ $this->emcp_key() ][1] ?? 'eicon-code';
	}

	public function get_categories(): array {
		return array( EMCP_Tools_Themer_Widgets::CATEGORY );
	}

	public function get_keywords(): array {
		return array( 'emcp', 'themer', 'dynamic', 'theme', $this->emcp_key() );
	}

	/** Build content controls from the shared descriptors, then the style tab. */
	protected function register_controls(): void {
		$key    = $this->emcp_key();
		$blocks = class_exists( 'EMCP_Tools_Themer_Blocks' ) ? EMCP_Tools_Themer_Blocks::blocks() : array();
		$def    = $blocks[ $key ] ?? array( 'controls' => array(), 'attributes' => array() );

		$this->start_controls_section(
			'emcp_content',
			array( 'label' => __( 'Content', 'emcp-tools' ), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT )
		);

		foreach ( (array) $def['controls'] as $ctrl ) {
			$args = $this->control_args( $ctrl, $def['attributes'] ?? array() );
			if ( $args ) {
				$this->add_control( $ctrl['key'], $args );
			}
		}
		if ( empty( $def['controls'] ) ) {
			$this->add_control(
				'emcp_note',
				array(
					'type' => \Elementor\Controls_Manager::RAW_HTML,
					'raw'  => esc_html__( 'Renders the current post content dynamically.', 'emcp-tools' ),
				)
			);
		}
		$this->end_controls_section();

		$this->register_style_controls();
	}

	/**
	 * Map a shared control descriptor to Elementor control args.
	 *
	 * @param array $ctrl  Descriptor ({key,type,label,options}).
	 * @param array $attrs The block attributes (for defaults).
	 * @return array|null
	 */
	private function control_args( array $ctrl, array $attrs ): ?array {
		$key     = $ctrl['key'];
		$default = $attrs[ $key ]['default'] ?? null;

		switch ( $ctrl['type'] ) {
			case 'select':
				$options = array();
				foreach ( (array) ( $ctrl['options'] ?? array() ) as $opt ) {
					$options[ $opt ] = ucfirst( $opt );
				}
				return array(
					'label'   => $ctrl['label'],
					'type'    => \Elementor\Controls_Manager::SELECT,
					'options' => $options,
					'default' => (string) $default,
				);
			case 'toggle':
				return array(
					'label'        => $ctrl['label'],
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => $default ? 'yes' : '',
					'return_value' => 'yes',
				);
			case 'text':
				return array(
					'label'   => $ctrl['label'],
					'type'    => \Elementor\Controls_Manager::TEXT,
					'default' => (string) $default,
				);
			case 'number':
				return array(
					'label'   => $ctrl['label'],
					'type'    => \Elementor\Controls_Manager::NUMBER,
					'default' => (int) $default,
					'min'     => 0,
				);
			case 'menu':
				return array(
					'label'   => $ctrl['label'],
					'type'    => \Elementor\Controls_Manager::SELECT,
					'options' => $this->menu_options(),
					'default' => '0',
				);
		}
		return null;
	}

	/** Available nav menus for the menu picker. */
	private function menu_options(): array {
		$out   = array( '0' => __( '— Auto (first menu) —', 'emcp-tools' ) );
		$menus = function_exists( 'wp_get_nav_menus' ) ? wp_get_nav_menus() : array();
		foreach ( $menus as $menu ) {
			$out[ (string) $menu->term_id ] = $menu->name;
		}
		return $out;
	}

	/** A shared Style tab: color, link color, typography, alignment. */
	private function register_style_controls(): void {
		$this->start_controls_section(
			'emcp_style',
			array( 'label' => __( 'Style', 'emcp-tools' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_responsive_control(
			'emcp_align',
			array(
				'label'     => __( 'Alignment', 'emcp-tools' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array( 'title' => __( 'Left', 'emcp-tools' ), 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => __( 'Center', 'emcp-tools' ), 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => __( 'Right', 'emcp-tools' ), 'icon' => 'eicon-text-align-right' ),
				),
				'selectors' => array( '{{WRAPPER}}' => 'text-align: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'emcp_color',
			array(
				'label'     => __( 'Text color', 'emcp-tools' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .emcp-dyn' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'emcp_link_color',
			array(
				'label'     => __( 'Link color', 'emcp-tools' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .emcp-dyn a' => 'color: {{VALUE}};' ),
			)
		);
		if ( class_exists( '\\Elementor\\Group_Control_Typography' ) ) {
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'emcp_typography',
					'selector' => '{{WRAPPER}} .emcp-dyn',
				)
			);
		}

		$this->end_controls_section();

		if ( 'archive-loop' === $this->emcp_key() ) {
			$this->register_archive_loop_style();
		}
	}

	/** A "Cards" style section for the Archive Posts widget. */
	private function register_archive_loop_style(): void {
		$this->start_controls_section(
			'emcp_cards',
			array( 'label' => __( 'Cards', 'emcp-tools' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_responsive_control(
			'emcp_gap',
			array(
				'label'      => __( 'Gap between cards', 'emcp-tools' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}} .emcp-dyn-archive-loop' => '--emcp-card-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'emcp_media_width',
			array(
				'label'       => __( 'Image width (list)', 'emcp-tools' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( '%', 'px' ),
				'range'       => array( '%' => array( 'min' => 15, 'max' => 60 ), 'px' => array( 'min' => 120, 'max' => 480 ) ),
				'selectors'   => array( '{{WRAPPER}} .emcp-dyn-archive-loop--list .emcp-dyn-card-media' => 'flex-basis: {{SIZE}}{{UNIT}}; max-width: {{SIZE}}{{UNIT}};' ),
				'description' => __( 'Only affects the List layout.', 'emcp-tools' ),
			)
		);

		$this->add_control(
			'emcp_card_bg',
			array(
				'label'     => __( 'Card background', 'emcp-tools' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .emcp-dyn-card' => 'background: {{VALUE}};' ),
			)
		);
		if ( class_exists( '\\Elementor\\Group_Control_Border' ) ) {
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'emcp_card_border',
					'selector' => '{{WRAPPER}} .emcp-dyn-card',
				)
			);
		}
		$this->add_responsive_control(
			'emcp_card_radius',
			array(
				'label'      => __( 'Border radius', 'emcp-tools' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .emcp-dyn-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;' ),
			)
		);
		$this->add_responsive_control(
			'emcp_card_padding',
			array(
				'label'      => __( 'Content padding', 'emcp-tools' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .emcp-dyn-card-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		if ( class_exists( '\\Elementor\\Group_Control_Box_Shadow' ) ) {
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'emcp_card_shadow',
					'selector' => '{{WRAPPER}} .emcp-dyn-card',
				)
			);
		}

		$this->add_control(
			'emcp_title_color',
			array(
				'label'     => __( 'Title color', 'emcp-tools' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array( '{{WRAPPER}} .emcp-dyn-card-title, {{WRAPPER}} .emcp-dyn-card-title a' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'emcp_meta_color',
			array(
				'label'     => __( 'Meta color', 'emcp-tools' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .emcp-dyn-card-meta' => 'color: {{VALUE}}; opacity: 1;' ),
			)
		);
		$this->add_control(
			'emcp_excerpt_color',
			array(
				'label'     => __( 'Excerpt color', 'emcp-tools' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .emcp-dyn-card-excerpt' => 'color: {{VALUE}}; opacity: 1;' ),
			)
		);
		$this->add_control(
			'emcp_more_color',
			array(
				'label'     => __( 'Read-more color', 'emcp-tools' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .emcp-dyn-card-more' => 'color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/** Render via the shared provider (output already escaped in the provider). */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$args     = EMCP_Tools_Themer_Dynamic::args_from( $this->emcp_key(), is_array( $settings ) ? $settings : array() );
		echo EMCP_Tools_Themer_Dynamic::render( $this->emcp_key(), $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/** One thin subclass per element — the base does all the work. */
class EMCP_Tools_Themer_Widget_Post_Title extends EMCP_Tools_Themer_Widget_Base {
	protected function emcp_key(): string { return 'post-title'; }
}
class EMCP_Tools_Themer_Widget_Archive_Title extends EMCP_Tools_Themer_Widget_Base {
	protected function emcp_key(): string { return 'archive-title'; }
}
class EMCP_Tools_Themer_Widget_Breadcrumbs extends EMCP_Tools_Themer_Widget_Base {
	protected function emcp_key(): string { return 'breadcrumbs'; }
}
class EMCP_Tools_Themer_Widget_Post_Meta extends EMCP_Tools_Themer_Widget_Base {
	protected function emcp_key(): string { return 'post-meta'; }
}
class EMCP_Tools_Themer_Widget_Site_Logo extends EMCP_Tools_Themer_Widget_Base {
	protected function emcp_key(): string { return 'site-logo'; }
}
class EMCP_Tools_Themer_Widget_Site_Title extends EMCP_Tools_Themer_Widget_Base {
	protected function emcp_key(): string { return 'site-title'; }
}
class EMCP_Tools_Themer_Widget_Nav_Menu extends EMCP_Tools_Themer_Widget_Base {
	protected function emcp_key(): string { return 'nav-menu'; }
}
class EMCP_Tools_Themer_Widget_Description extends EMCP_Tools_Themer_Widget_Base {
	protected function emcp_key(): string { return 'description'; }
}
class EMCP_Tools_Themer_Widget_Post_Content extends EMCP_Tools_Themer_Widget_Base {
	protected function emcp_key(): string { return 'post-content'; }
}
class EMCP_Tools_Themer_Widget_Archive_Loop extends EMCP_Tools_Themer_Widget_Base {
	protected function emcp_key(): string { return 'archive-loop'; }
}
