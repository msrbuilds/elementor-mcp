<?php
/**
 * Dynamic source definitions.
 *
 * Split out of EMCP_Tools_Themer_Dynamic so the four surfaces (Gutenberg
 * blocks, Elementor widgets, Elementor dynamic tags, block bindings) have one
 * file to agree with. Every source declares a value TYPE, which decides where
 * it may be bound: a text source can fill a heading, an image source cannot.
 *
 * @package EMCP_Tools
 * @since   3.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.13.0
 */
class EMCP_Tools_Themer_Dynamic_Catalog {

	/** Value types a source may declare. */
	const TYPES = array( 'text', 'url', 'image', 'date', 'html' );

	/**
	 * All sources, free plus any registered by Pro.
	 *
	 * @return array<string,array{label:string,icon:string,type:string,args:string[],bindable:bool}>
	 */
	public static function all(): array {
		$sources = array(
			'post-title'    => array( 'label' => __( 'Post/Page Title', 'emcp-tools' ), 'icon' => 'heading', 'type' => 'text', 'args' => array( 'tag', 'link' ), 'element' => true ),
			'archive-title' => array( 'label' => __( 'Archive Title', 'emcp-tools' ), 'icon' => 'archive', 'type' => 'text', 'args' => array( 'tag', 'show_prefix' ), 'element' => true ),
			'site-title'    => array( 'label' => __( 'Site Title', 'emcp-tools' ), 'icon' => 'admin-home', 'type' => 'text', 'args' => array( 'tag', 'link' ), 'element' => true ),
			'description'   => array( 'label' => __( 'Description', 'emcp-tools' ), 'icon' => 'text', 'type' => 'text', 'args' => array(), 'element' => true ),
			'post-excerpt'   => array( 'label' => __( 'Post Excerpt', 'emcp-tools' ), 'icon' => 'excerpt-view', 'type' => 'text', 'args' => array(), 'element' => true ),
			'post-url'       => array( 'label' => __( 'Post URL', 'emcp-tools' ), 'icon' => 'admin-links', 'type' => 'url', 'args' => array() ),
			'post-date'      => array( 'label' => __( 'Post Date', 'emcp-tools' ), 'icon' => 'calendar-alt', 'type' => 'date', 'args' => array( 'format' ) ),
			'post-id'        => array( 'label' => __( 'Post ID', 'emcp-tools' ), 'icon' => 'info', 'type' => 'text', 'args' => array() ),
			'featured-image' => array( 'label' => __( 'Featured Image', 'emcp-tools' ), 'icon' => 'format-image', 'type' => 'image', 'args' => array( 'size' ), 'element' => true ),
			'site-logo'     => array( 'label' => __( 'Site Logo', 'emcp-tools' ), 'icon' => 'format-image', 'type' => 'image', 'args' => array( 'link' ), 'element' => true ),
			'post-content'  => array( 'label' => __( 'Post Content', 'emcp-tools' ), 'icon' => 'media-document', 'type' => 'html', 'args' => array(), 'element' => true ),
			'post-meta'     => array( 'label' => __( 'Post Meta', 'emcp-tools' ), 'icon' => 'list-view', 'type' => 'html', 'args' => array( 'show_date', 'show_author' ), 'element' => true ),
			'breadcrumbs'   => array( 'label' => __( 'Breadcrumbs', 'emcp-tools' ), 'icon' => 'admin-links', 'type' => 'html', 'args' => array( 'separator', 'home_label' ), 'element' => true ),
			'nav-menu'      => array( 'label' => __( 'Menu', 'emcp-tools' ), 'icon' => 'menu', 'type' => 'html', 'args' => array( 'menu' ), 'element' => true ),
			'author-box'    => array( 'label' => __( 'Author Box', 'emcp-tools' ), 'icon' => 'admin-users', 'type' => 'html', 'args' => array( 'show_avatar', 'avatar_size', 'show_name', 'name_tag', 'show_bio', 'show_website', 'show_posts_link', 'layout', 'alignment' ), 'element' => true ),
			'post-navigation' => array( 'label' => __( 'Post Navigation', 'emcp-tools' ), 'icon' => 'leftright', 'type' => 'html', 'args' => array( 'in_same_term', 'taxonomy', 'show_label', 'show_title', 'show_arrow', 'prev_label', 'next_label' ), 'element' => true ),
			'post-comments'  => array( 'label' => __( 'Post Comments', 'emcp-tools' ), 'icon' => 'admin-comments', 'type' => 'html', 'args' => array( 'hide_when_closed' ), 'element' => true ),
			'search-form'    => array( 'label' => __( 'Search Form', 'emcp-tools' ), 'icon' => 'search', 'type' => 'html', 'args' => array( 'placeholder', 'button_type', 'button_text', 'full_width' ), 'element' => true ),
			'sitemap'        => array( 'label' => __( 'Sitemap', 'emcp-tools' ), 'icon' => 'list-view', 'type' => 'html', 'args' => array( 'post_types', 'taxonomies', 'hide_empty', 'exclude_ids', 'nofollow', 'limit' ), 'element' => true ),
			'post-info'      => array( 'label' => __( 'Post Info', 'emcp-tools' ), 'icon' => 'list-view', 'type' => 'html', 'args' => array( 'items', 'taxonomy', 'meta_key', 'separator', 'layout', 'link_author', 'link_terms' ), 'element' => true ),
			'archive-posts'  => array( 'label' => __( 'Archive Posts', 'emcp-tools' ), 'icon' => 'grid-view', 'type' => 'html', 'args' => array( 'layout', 'columns', 'show_image', 'show_excerpt', 'excerpt_words', 'show_more', 'more_text', 'show_pagination' ), 'element' => true ),
			'archive-loop'  => array( 'label' => __( 'Archive Posts (classic)', 'emcp-tools' ), 'icon' => 'grid-view', 'type' => 'html', 'args' => array( 'columns', 'show_excerpt' ), 'element' => true ),
			'loop-grid'      => array( 'label' => __( 'Loop Grid', 'emcp-tools' ), 'icon' => 'grid-view', 'type' => 'html', 'args' => array( 'template_id', 'query', 'local_id', 'anchor', 'columns', 'columns_tablet', 'columns_mobile', 'gap_x', 'gap_y', 'masonry', 'equal_height', 'first_item_span', 'hover_effect', 'animation', 'animation_step', 'pagination', 'page_limit', 'shorten', 'prev_label', 'next_label', 'load_more_label', 'ajax', 'infinite_offset', 'inline_vars', 'empty_message', 'tag', 'alternates' ), 'element' => true ),
			'loop-carousel'  => array( 'label' => __( 'Loop Carousel', 'emcp-tools' ), 'icon' => 'slides', 'type' => 'html', 'args' => array( 'template_id', 'query', 'local_id', 'anchor', 'slides', 'slides_tablet', 'slides_mobile', 'slides_to_scroll', 'slides_to_scroll_tablet', 'slides_to_scroll_mobile', 'gap', 'gap_tablet', 'gap_mobile', 'height', 'autoplay', 'autoplay_delay', 'pause_on_hover', 'pause_on_interaction', 'loop', 'speed', 'direction', 'centered', 'offset_sides', 'offset_width', 'effect', 'keyboard', 'mousewheel', 'arrows', 'arrows_position', 'arrow_prev_svg', 'arrow_next_svg', 'dots', 'dots_position', 'empty_message' ), 'element' => true ),
		);

		/**
		 * Register additional dynamic sources (Pro attaches here).
		 *
		 * @since 3.13.0
		 * @param array $sources Source definitions keyed by source key.
		 */
		$sources = (array) apply_filters( 'emcp_themer_dynamic_sources', $sources );

		$out = array();
		foreach ( $sources as $key => $def ) {
			$def  = (array) $def;
			$type = isset( $def['type'] ) && in_array( $def['type'], self::TYPES, true ) ? $def['type'] : 'text';

			$out[ (string) $key ] = array(
				'label'    => isset( $def['label'] ) ? (string) $def['label'] : (string) $key,
				'icon'     => isset( $def['icon'] ) ? (string) $def['icon'] : 'admin-generic',
				'type'     => $type,
				'args'     => isset( $def['args'] ) ? array_values( (array) $def['args'] ) : array(),
				// html sources emit markup, so they can never fill a typed field.
				'bindable' => 'html' !== $type,
				// Does a rendered block and widget exist for this key? A source can
				// be bindable without having its own element, and saying otherwise
				// sends an agent looking for a block that was never registered.
				'element'  => ! empty( $def['element'] ),
			);
		}
		return $out;
	}

	/**
	 * One source definition.
	 *
	 * @param string $key Source key.
	 * @return array|null
	 */
	public static function get( string $key ): ?array {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sources that can fill a typed field.
	 *
	 * @return array
	 */
	public static function bindable(): array {
		return array_filter(
			self::all(),
			static function ( $def ) {
				return ! empty( $def['bindable'] );
			}
		);
	}
}
