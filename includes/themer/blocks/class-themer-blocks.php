<?php
/**
 * Themer dynamic Gutenberg blocks.
 *
 * Registers server-rendered blocks (`emcp/post-title`, `emcp/archive-loop`, …)
 * that output dynamic content via EMCP_Tools_Themer_Dynamic against the current
 * main query. Blocks carry native `supports` (align/color/typography/spacing/
 * border) so the editor's own controls handle styling; each block's own
 * attributes (tag, columns, which meta items, …) get an inline InspectorControls
 * panel from assets/js/themer-blocks.js (no build step). A `ServerSideRender`
 * preview shows real output in the editor.
 *
 * Registration uses the canonical `editor_script` pattern: one script owns the
 * client-side registerBlockType (edit/save); attributes + supports + per-block
 * controls are localized from PHP so nothing is hand-duplicated.
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
class EMCP_Tools_Themer_Blocks {

	const CATEGORY = 'emcp-themer';
	const SCRIPT   = 'emcp-themer-blocks';
	const STYLE    = 'emcp-themer-blocks';

	/** Register blocks, the block category, and editor assets. */
	public function init(): void {
		add_filter( 'block_categories_all', array( $this, 'register_category' ), 10, 1 );
		// The module boots on init:5, so init has already started — register now;
		// otherwise defer to init.
		if ( did_action( 'init' ) ) {
			$this->register_blocks();
		} else {
			add_action( 'init', array( $this, 'register_blocks' ) );
		}
	}

	/**
	 * Add an "EMCP Themer" block category.
	 *
	 * @param array $categories Existing categories.
	 * @return array
	 */
	public function register_category( $categories ) {
		foreach ( (array) $categories as $cat ) {
			if ( isset( $cat['slug'] ) && self::CATEGORY === $cat['slug'] ) {
				return $categories;
			}
		}
		array_unshift(
			$categories,
			array(
				'slug'  => self::CATEGORY,
				'title' => __( 'EMCP Themer', 'emcp-tools' ),
				'icon'  => null,
			)
		);
		return $categories;
	}

	/**
	 * The block config: title, dashicon, attributes, supports, and the editor
	 * control descriptors, per block. Single source of truth for both PHP
	 * registration and the localized editor config.
	 *
	 * @return array
	 */
	public static function blocks(): array {
		$tags          = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );
		$text_supports = array(
			'align'                => array( 'wide', 'full' ),
			'color'                => array( 'text' => true, 'background' => true, 'link' => true, 'gradients' => true ),
			'spacing'              => array( 'margin' => true, 'padding' => true ),
			'typography'           => array(
				'fontSize'                     => true,
				'lineHeight'                   => true,
				'__experimentalFontFamily'     => true,
				'__experimentalFontWeight'     => true,
				'__experimentalFontStyle'      => true,
				'__experimentalTextTransform'  => true,
				'__experimentalLetterSpacing'  => true,
				'__experimentalTextDecoration' => true,
			),
			'__experimentalBorder' => array( 'color' => true, 'radius' => true, 'style' => true, 'width' => true ),
		);
		$box_supports = array(
			'align'                => array( 'wide', 'full' ),
			'color'                => array( 'text' => true, 'background' => true, 'link' => true, 'gradients' => true ),
			'spacing'              => array( 'margin' => true, 'padding' => true, 'blockGap' => true ),
			'typography'           => array( 'fontSize' => true, 'lineHeight' => true ),
			'__experimentalBorder' => array( 'color' => true, 'radius' => true, 'style' => true, 'width' => true ),
		);

		$tag_ctrl = array( 'key' => 'tag', 'type' => 'select', 'label' => __( 'HTML tag', 'emcp-tools' ), 'options' => $tags );

		return array(
			'post-title'    => array(
				'title'      => __( 'Post/Page Title', 'emcp-tools' ),
				'icon'       => 'heading',
				'supports'   => $text_supports,
				'attributes' => array( 'tag' => array( 'type' => 'string', 'default' => 'h1' ), 'link' => array( 'type' => 'boolean', 'default' => false ) ),
				'controls'   => array( $tag_ctrl, array( 'key' => 'link', 'type' => 'toggle', 'label' => __( 'Link to post', 'emcp-tools' ) ) ),
			),
			'archive-title' => array(
				'title'      => __( 'Archive Title', 'emcp-tools' ),
				'icon'       => 'archive',
				'supports'   => $text_supports,
				'attributes' => array( 'tag' => array( 'type' => 'string', 'default' => 'h1' ), 'showPrefix' => array( 'type' => 'boolean', 'default' => false ) ),
				'controls'   => array( $tag_ctrl, array( 'key' => 'showPrefix', 'type' => 'toggle', 'label' => __( 'Show "Category:" prefix', 'emcp-tools' ) ) ),
			),
			'breadcrumbs'   => array(
				'title'      => __( 'Breadcrumbs', 'emcp-tools' ),
				'icon'       => 'admin-links',
				'supports'   => $text_supports,
				'attributes' => array( 'separator' => array( 'type' => 'string', 'default' => '/' ), 'homeLabel' => array( 'type' => 'string', 'default' => '' ) ),
				'controls'   => array(
					array( 'key' => 'separator', 'type' => 'text', 'label' => __( 'Separator', 'emcp-tools' ) ),
					array( 'key' => 'homeLabel', 'type' => 'text', 'label' => __( 'Home label', 'emcp-tools' ) ),
				),
			),
			'post-meta'     => array(
				'title'      => __( 'Post Meta', 'emcp-tools' ),
				'icon'       => 'list-view',
				'supports'   => $text_supports,
				'attributes' => array(
					'showDate'       => array( 'type' => 'boolean', 'default' => true ),
					'showAuthor'     => array( 'type' => 'boolean', 'default' => true ),
					'showCategories' => array( 'type' => 'boolean', 'default' => true ),
					'showTags'       => array( 'type' => 'boolean', 'default' => false ),
					'showComments'   => array( 'type' => 'boolean', 'default' => false ),
				),
				'controls'   => array(
					array( 'key' => 'showDate', 'type' => 'toggle', 'label' => __( 'Date', 'emcp-tools' ) ),
					array( 'key' => 'showAuthor', 'type' => 'toggle', 'label' => __( 'Author', 'emcp-tools' ) ),
					array( 'key' => 'showCategories', 'type' => 'toggle', 'label' => __( 'Categories', 'emcp-tools' ) ),
					array( 'key' => 'showTags', 'type' => 'toggle', 'label' => __( 'Tags', 'emcp-tools' ) ),
					array( 'key' => 'showComments', 'type' => 'toggle', 'label' => __( 'Comments', 'emcp-tools' ) ),
				),
			),
			'site-logo'     => array(
				'title'      => __( 'Site Logo', 'emcp-tools' ),
				'icon'       => 'format-image',
				'supports'   => array( 'align' => array( 'left', 'center', 'right' ), 'spacing' => array( 'margin' => true, 'padding' => true ) ),
				'attributes' => array( 'maxWidth' => array( 'type' => 'number', 'default' => 160 ) ),
				'controls'   => array( array( 'key' => 'maxWidth', 'type' => 'number', 'label' => __( 'Max width (px)', 'emcp-tools' ) ) ),
			),
			'site-title'    => array(
				'title'      => __( 'Site Title', 'emcp-tools' ),
				'icon'       => 'admin-home',
				'supports'   => $text_supports,
				'attributes' => array( 'tag' => array( 'type' => 'string', 'default' => 'span' ), 'showTagline' => array( 'type' => 'boolean', 'default' => false ) ),
				'controls'   => array( $tag_ctrl, array( 'key' => 'showTagline', 'type' => 'toggle', 'label' => __( 'Show tagline', 'emcp-tools' ) ) ),
			),
			'nav-menu'      => array(
				'title'      => __( 'Menu', 'emcp-tools' ),
				'icon'       => 'menu',
				'supports'   => $box_supports,
				'attributes' => array( 'menuId' => array( 'type' => 'number', 'default' => 0 ) ),
				'controls'   => array( array( 'key' => 'menuId', 'type' => 'menu', 'label' => __( 'Menu', 'emcp-tools' ) ) ),
			),
			'description'   => array(
				'title'      => __( 'Description', 'emcp-tools' ),
				'icon'       => 'text',
				'supports'   => $text_supports,
				'attributes' => array( 'length' => array( 'type' => 'number', 'default' => 0 ) ),
				'controls'   => array( array( 'key' => 'length', 'type' => 'number', 'label' => __( 'Max words (0 = full)', 'emcp-tools' ) ) ),
			),
			'post-content'  => array(
				'title'      => __( 'Post Content', 'emcp-tools' ),
				'icon'       => 'media-document',
				'supports'   => $box_supports,
				'attributes' => array(),
				'controls'   => array(),
			),
			'archive-loop'  => array(
				'title'      => __( 'Archive Posts', 'emcp-tools' ),
				'icon'       => 'grid-view',
				'supports'   => $box_supports,
				'attributes' => array(
					'layout'      => array( 'type' => 'string', 'default' => 'grid' ),
					'columns'     => array( 'type' => 'number', 'default' => 3 ),
					'showImage'   => array( 'type' => 'boolean', 'default' => true ),
					'showTitle'   => array( 'type' => 'boolean', 'default' => true ),
					'showExcerpt' => array( 'type' => 'boolean', 'default' => true ),
					'showMeta'    => array( 'type' => 'boolean', 'default' => true ),
					'showMore'    => array( 'type' => 'boolean', 'default' => true ),
					'moreText'    => array( 'type' => 'string', 'default' => '' ),
					'pagination'  => array( 'type' => 'boolean', 'default' => true ),
				),
				'controls'   => array(
					array( 'key' => 'layout', 'type' => 'select', 'label' => __( 'Layout', 'emcp-tools' ), 'options' => array( 'grid', 'list' ) ),
					array( 'key' => 'columns', 'type' => 'number', 'label' => __( 'Columns (grid)', 'emcp-tools' ) ),
					array( 'key' => 'showImage', 'type' => 'toggle', 'label' => __( 'Featured image', 'emcp-tools' ) ),
					array( 'key' => 'showTitle', 'type' => 'toggle', 'label' => __( 'Title', 'emcp-tools' ) ),
					array( 'key' => 'showExcerpt', 'type' => 'toggle', 'label' => __( 'Excerpt', 'emcp-tools' ) ),
					array( 'key' => 'showMeta', 'type' => 'toggle', 'label' => __( 'Meta (date)', 'emcp-tools' ) ),
					array( 'key' => 'showMore', 'type' => 'toggle', 'label' => __( 'Read-more link', 'emcp-tools' ) ),
					array( 'key' => 'moreText', 'type' => 'text', 'label' => __( 'Read-more text', 'emcp-tools' ) ),
					array( 'key' => 'pagination', 'type' => 'toggle', 'label' => __( 'Pagination', 'emcp-tools' ) ),
				),
			),
		);
	}

	/** Register the editor script/style + every block. Hooked on init. */
	public function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$js  = EMCP_TOOLS_DIR . 'assets/js/themer-blocks.js';
		$css = EMCP_TOOLS_DIR . 'assets/css/themer-blocks.css';
		$jsv = ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $js ) ) ? (string) filemtime( $js ) : EMCP_TOOLS_VERSION;
		$csv = ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $css ) ) ? (string) filemtime( $css ) : EMCP_TOOLS_VERSION;

		wp_register_script(
			self::SCRIPT,
			EMCP_TOOLS_URL . 'assets/js/themer-blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			$jsv,
			true
		);
		wp_register_style( self::STYLE, EMCP_TOOLS_URL . 'assets/css/themer-blocks.css', array(), $csv );

		wp_localize_script(
			self::SCRIPT,
			'emcpThemerBlocks',
			array(
				'category' => self::CATEGORY,
				'blocks'   => self::editor_payload(),
				'menus'    => self::menu_choices(),
			)
		);

		foreach ( self::blocks() as $key => $def ) {
			if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'emcp/' . $key ) ) {
				continue;
			}
			register_block_type(
				'emcp/' . $key,
				array(
					'api_version'     => 2,
					'title'           => $def['title'],
					'category'        => self::CATEGORY,
					'icon'            => $def['icon'],
					'attributes'      => $def['attributes'],
					'supports'        => $def['supports'],
					'editor_script'   => self::SCRIPT,
					'editor_style'    => self::STYLE,
					'style'           => self::STYLE,
					'render_callback' => static function ( $attributes ) use ( $key ) {
						return EMCP_Tools_Themer_Blocks::render_block( $key, is_array( $attributes ) ? $attributes : array() );
					},
				)
			);
		}
	}

	/**
	 * The editor-side payload: title/icon/attributes/supports/controls per block,
	 * consumed by themer-blocks.js to registerBlockType on the client.
	 *
	 * @return array
	 */
	private static function editor_payload(): array {
		$out = array();
		foreach ( self::blocks() as $key => $def ) {
			$out[ $key ] = array(
				'title'      => $def['title'],
				'icon'       => $def['icon'],
				'attributes' => $def['attributes'],
				'supports'   => $def['supports'],
				'controls'   => $def['controls'],
			);
		}
		return $out;
	}

	/**
	 * Map a block's attributes to provider args, render, and wrap with the block
	 * supports' generated classes/styles.
	 *
	 * @param string $key        Block key.
	 * @param array  $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( string $key, array $attributes ): string {
		$args  = EMCP_Tools_Themer_Dynamic::args_from( $key, $attributes );
		$inner = EMCP_Tools_Themer_Dynamic::render( $key, $args );
		if ( '' === $inner ) {
			// In the editor preview, show the block name so it isn't invisible;
			// on the front end, render nothing.
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				$titles = self::blocks();
				$inner  = '<span class="emcp-dyn-placeholder">' . esc_html( $titles[ $key ]['title'] ?? $key ) . '</span>';
			} else {
				return '';
			}
		}
		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';
		return '<div ' . $wrapper . '>' . $inner . '</div>';
	}

	/**
	 * Available nav menus for the menu picker.
	 *
	 * @return array<int,array{value:int,label:string}>
	 */
	private static function menu_choices(): array {
		$out   = array( array( 'value' => 0, 'label' => __( '— Auto (first menu) —', 'emcp-tools' ) ) );
		$menus = function_exists( 'wp_get_nav_menus' ) ? wp_get_nav_menus() : array();
		foreach ( $menus as $menu ) {
			$out[] = array( 'value' => (int) $menu->term_id, 'label' => $menu->name );
		}
		return $out;
	}
}
