<?php
/**
 * Themer dynamic Elementor widgets — loader.
 *
 * Registers a "EMCP Themer" widget category and the dynamic widgets (Post Title,
 * Archive Title, Breadcrumbs, …) that mirror the Gutenberg blocks. The widget
 * classes extend \Elementor\Widget_Base, so they're defined in a separate file
 * required only inside the `elementor/widgets/register` callback (when Elementor
 * is guaranteed loaded). Every widget renders through the shared provider.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.1.0
 */
class EMCP_Tools_Themer_Widgets {

	const CATEGORY = 'emcp-themer';

	/** Hook Elementor's registration points. No-op when Elementor is absent. */
	public function init(): void {
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'enqueue_style' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'enqueue_style' ) );
	}

	/**
	 * Add the EMCP Themer widget category.
	 *
	 * @param object $manager Elementor elements categories manager.
	 */
	public function register_category( $manager ): void {
		if ( is_object( $manager ) && method_exists( $manager, 'add_category' ) ) {
			$manager->add_category(
				self::CATEGORY,
				array(
					'title' => __( 'EMCP Themer', 'emcp-tools' ),
					'icon'  => 'eicon-theme-builder',
				)
			);
		}
	}

	/**
	 * Register every dynamic widget.
	 *
	 * @param object $manager Elementor widgets manager.
	 */
	public function register_widgets( $manager ): void {
		if ( ! class_exists( '\\Elementor\\Widget_Base' ) || ! is_object( $manager ) || ! method_exists( $manager, 'register' ) ) {
			return;
		}
		require_once __DIR__ . '/class-themer-widget-classes.php';
		foreach ( EMCP_Tools_Themer_Widget_Base::widget_classes() as $class ) {
			if ( class_exists( $class ) ) {
				$manager->register( new $class() );
			}
		}
	}

	/** Reuse the block stylesheet for the widgets' shared layout CSS. */
	public function enqueue_style(): void {
		$css = EMCP_TOOLS_DIR . 'assets/css/themer-blocks.css';
		if ( ! file_exists( $css ) ) {
			return;
		}
		$ver = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? (string) filemtime( $css ) : EMCP_TOOLS_VERSION;
		wp_enqueue_style( 'emcp-themer-blocks', EMCP_TOOLS_URL . 'assets/css/themer-blocks.css', array(), $ver );
	}
}
