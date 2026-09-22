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
			$this->promote_category( $manager );
		}
	}

	/**
	 * Move the EMCP Themer category to the top while a theme template is open.
	 *
	 * Elementor gives no supported way to do this. `add_category()` only ever
	 * appends, and Elementor says so in its own source: "Not using the
	 * `add_category` because it doesn't allow 3rd party to inject a category on
	 * top the others." Both `$categories` and the `promote_category_after()`
	 * helper are private, and the `elementor/document/config` filter runs through
	 * array_replace_recursive(), which preserves the original key order and so
	 * cannot reorder either.
	 *
	 * That leaves reflection. It is written to fail into today's behaviour: on
	 * any error the category simply stays where `add_category()` put it, which is
	 * a cosmetic difference and never a broken panel.
	 *
	 * @since 3.13.0
	 * @param object $manager Elementor elements categories manager.
	 */
	public function promote_category( $manager ): void {
		if ( ! self::editing_theme_template() ) {
			return;
		}
		try {
			$prop = new ReflectionProperty( $manager, 'categories' );
			if ( PHP_VERSION_ID < 80100 ) {
				$prop->setAccessible( true );
			}
			$cats = $prop->getValue( $manager );
			if ( ! is_array( $cats ) || ! isset( $cats[ self::CATEGORY ] ) ) {
				return;
			}
			$prop->setValue( $manager, self::reorder( $cats, self::CATEGORY ) );
		} catch ( \Throwable $e ) {
			// Elementor moved or renamed the property. Leave the order alone.
			unset( $e );
		}
	}

	/**
	 * Put one category first, keeping Favorites pinned above it.
	 *
	 * Pure, so the ordering rule is testable without Elementor.
	 *
	 * @since 3.13.0
	 * @param array  $cats Ordered category map.
	 * @param string $key  Category to promote.
	 * @return array
	 */
	public static function reorder( array $cats, string $key ): array {
		if ( ! isset( $cats[ $key ] ) ) {
			return $cats;
		}
		$ours = $cats[ $key ];
		unset( $cats[ $key ] );

		// Favorites is a user's own shortlist, so it stays above ours.
		$at = isset( $cats['favorites'] )
			? (int) array_search( 'favorites', array_keys( $cats ), true ) + 1
			: 0;

		return array_merge(
			array_slice( $cats, 0, $at, true ),
			array( $key => $ours ),
			array_slice( $cats, $at, null, true )
		);
	}

	/**
	 * Is the document being edited one of our theme templates?
	 *
	 * @since 3.13.0
	 * @return bool
	 */
	public static function editing_theme_template(): bool {
		$id = self::edited_post_id();

		return $id > 0
			&& class_exists( 'EMCP_Tools_Themer_CPT' )
			&& get_post_type( $id ) === EMCP_Tools_Themer_CPT::POST_TYPE;
	}

	/**
	 * The post id of the document open in the editor, or 0.
	 *
	 * Four sources, because the panel config is built on two different requests:
	 * the editor page load, and the AJAX call that refreshes the widget list.
	 *
	 * @since 3.13.0
	 * @return int
	 */
	public static function edited_post_id(): int {
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) ) {
			$plugin = \Elementor\Plugin::$instance;

			if ( isset( $plugin->documents ) && method_exists( $plugin->documents, 'get_current' ) ) {
				$doc = $plugin->documents->get_current();
				if ( is_object( $doc ) && method_exists( $doc, 'get_main_id' ) ) {
					$id = (int) $doc->get_main_id();
					if ( $id > 0 ) {
						return $id;
					}
				}
			}

			if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'get_post_id' ) ) {
				$id = (int) $plugin->editor->get_post_id();
				if ( $id > 0 ) {
					return $id;
				}
			}
		}

		// Read-only: this only decides panel ordering, never a state change, so
		// there is nothing here for a nonce to protect.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		foreach ( array( 'editor_post_id', 'post' ) as $k ) {
			if ( ! empty( $_REQUEST[ $k ] ) ) {
				$id = absint( wp_unslash( $_REQUEST[ $k ] ) );
				if ( $id > 0 ) {
					return $id;
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 0;
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

		// The rich widgets have bespoke controls, so they extend a second base
		// and delegate rendering to the shared element classes.
		require_once __DIR__ . '/rich/class-themer-widget-rich-base.php';
		if ( class_exists( 'EMCP_Tools_Themer_Widget_Rich_Base' ) ) {
			require_once __DIR__ . '/rich/class-themer-widget-rich-set.php';
			foreach ( EMCP_Tools_Themer_Widget_Rich_Base::rich_widget_classes() as $class ) {
				if ( class_exists( $class ) ) {
					$manager->register( new $class() );
				}
			}
		}

		// Loop elements: available in every Elementor document, not only
		// Themer templates, because a Loop Grid belongs on ordinary pages too.
		require_once __DIR__ . '/loop/class-themer-widget-loop-base.php';
		if ( class_exists( 'EMCP_Tools_Themer_Widget_Loop_Base' ) ) {
			require_once __DIR__ . '/loop/class-themer-widget-loop-grid.php';
			require_once __DIR__ . '/loop/class-themer-widget-loop-carousel.php';
			foreach ( EMCP_Tools_Themer_Widget_Loop_Base::widget_classes() as $class ) {
				if ( class_exists( $class ) ) {
					$manager->register( new $class() );
				}
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
