<?php
/**
 * Elementor settings to loop element args, and the widgets' selector root.
 *
 * Plain static methods with no Elementor dependency, so the mapping is
 * unit-testable while the widget classes (which extend Widget_Base) are not
 * loadable outside Elementor. The widget base delegates here.
 *
 * Every key produced is an argument the element reads: a key of the
 * element's own defaults(), or one of SHARED_ARGS (read by the loop element
 * base itself). Fallbacks for absent settings come from the element's
 * defaults(), so a widget saved before a control existed renders exactly
 * as the element would on its own.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.18.0
 */
class EMCP_Tools_Themer_Loop_Widget_Map {

	/**
	 * Arguments the loop element base reads for both kinds, in addition to
	 * each element's own defaults(): identity (template, query, anchor,
	 * local id) and the shared render args (item tag, empty message,
	 * alternate templates).
	 */
	const SHARED_ARGS = array( 'template_id', 'query', 'anchor', 'local_id', 'tag', 'empty_message', 'alternates' );

	/**
	 * The widget's own loop root, never a loop nested inside one of its
	 * cards. Elementor 4's optimized markup drops .elementor-widget-container;
	 * earlier versions keep it, so both branches are listed.
	 */
	const ROOT_BRANCHES = array(
		'{{WRAPPER}} > .emcp-loop',
		'{{WRAPPER}} > .elementor-widget-container > .emcp-loop',
	);

	/**
	 * The selector for the widget's own loop root.
	 *
	 * Custom properties are set here, never on bare {{WRAPPER}}: a custom
	 * property inherits, so it would reach a nested loop too.
	 *
	 * @return string
	 */
	public static function root_selector(): string {
		return implode( ', ', self::ROOT_BRANCHES );
	}

	/**
	 * A selector below (or on) the widget's own loop root.
	 *
	 * The suffix is applied to every root branch. A suffix that is itself a
	 * comma list is crossed with the branches. A part starting with a
	 * combinator (`>`, `+`, `~`) is joined with a space; anything else (a
	 * pseudo-class, a class or an attribute on the root itself) is appended
	 * directly. Write child chains that match the stylesheet's own, so the
	 * selector never reaches a nested loop.
	 *
	 * @param string $suffix For example '> .emcp-loop__items > .emcp-loop__item'.
	 * @return string
	 */
	public static function selector( string $suffix = '' ): string {
		$parts = array_filter( array_map( 'trim', explode( ',', $suffix ) ), 'strlen' );
		if ( ! $parts ) {
			return self::root_selector();
		}
		$out = array();
		foreach ( self::ROOT_BRANCHES as $root ) {
			foreach ( $parts as $part ) {
				$out[] = in_array( $part[0], array( '>', '+', '~' ), true ) ? $root . ' ' . $part : $root . $part;
			}
		}
		return implode( ', ', $out );
	}

	/**
	 * A slider or number control's value.
	 *
	 * @param mixed $raw     Control value.
	 * @param int   $default Fallback when empty.
	 * @return int
	 */
	public static function size( $raw, int $default ): int {
		if ( is_array( $raw ) ) {
			return isset( $raw['size'] ) && '' !== $raw['size'] && null !== $raw['size'] ? (int) $raw['size'] : $default;
		}
		return '' !== $raw && null !== $raw ? (int) $raw : $default;
	}

	/**
	 * A switcher control ('yes' or ''), or the default when absent.
	 *
	 * @param array  $s       Settings.
	 * @param string $key     Key.
	 * @param bool   $default Value when the setting is absent.
	 * @return bool
	 */
	private static function flag( array $s, string $key, bool $default ): bool {
		if ( ! array_key_exists( $key, $s ) || null === $s[ $key ] ) {
			return $default;
		}
		return EMCP_Tools_Themer_Element_Base::truthy( $s[ $key ] );
	}

	/**
	 * A text or select control, or the default when absent.
	 *
	 * @param array  $s       Settings.
	 * @param string $key     Key.
	 * @param string $default Fallback.
	 * @return string
	 */
	private static function text( array $s, string $key, string $default ): string {
		return isset( $s[ $key ] ) && is_scalar( $s[ $key ] ) ? (string) $s[ $key ] : $default;
	}

	/**
	 * Query settings (EMCP_Tools_Themer_Loop_Query::sanitize() input).
	 *
	 * exclude_current and ignore_sticky are only set when the switcher is
	 * present: the query's own defaults for them depend on the source.
	 *
	 * @param array $s Widget settings.
	 * @return array
	 */
	public static function query( array $s ): array {
		$q = array(
			'source'            => self::text( $s, 'emcp_source', 'posts' ),
			'post_types'        => array_values( array_map( 'strval', (array) ( $s['emcp_post_types'] ?? array( 'post' ) ) ) ),
			'per_page'          => self::size( $s['emcp_per_page'] ?? '', EMCP_Tools_Themer_Loop_Query::DEFAULT_PER_PAGE ),
			'offset'            => self::size( $s['emcp_offset'] ?? '', 0 ),
			'orderby'           => self::text( $s, 'emcp_orderby', 'date' ),
			'order'             => self::text( $s, 'emcp_order', 'desc' ),
			'meta_key'          => self::text( $s, 'emcp_meta_key', '' ),
			'terms'             => array_values( (array) ( $s['emcp_terms'] ?? array() ) ),
			'exclude_terms'     => array_values( (array) ( $s['emcp_exclude_terms'] ?? array() ) ),
			'authors'           => array_values( (array) ( $s['emcp_authors'] ?? array() ) ),
			'include_ids'       => self::text( $s, 'emcp_include_ids', '' ),
			'exclude_ids'       => self::text( $s, 'emcp_exclude_ids', '' ),
			'date'              => self::text( $s, 'emcp_date', 'all' ),
			'after'             => self::text( $s, 'emcp_after', '' ),
			'before'            => self::text( $s, 'emcp_before', '' ),
			'related_taxonomy'  => self::text( $s, 'emcp_related_taxonomy', 'category' ),
			'hide_out_of_stock' => self::flag( $s, 'emcp_hide_out_of_stock', false ),
			'on_sale_only'      => self::flag( $s, 'emcp_on_sale_only', false ),
			'featured_only'     => self::flag( $s, 'emcp_featured_only', false ),
		);
		if ( array_key_exists( 'emcp_exclude_current', $s ) ) {
			$q['exclude_current'] = self::flag( $s, 'emcp_exclude_current', false );
		}
		if ( array_key_exists( 'emcp_ignore_sticky', $s ) ) {
			$q['ignore_sticky'] = self::flag( $s, 'emcp_ignore_sticky', true );
		}
		return $q;
	}

	/**
	 * Settings both widgets share.
	 *
	 * @param array  $s   Widget settings.
	 * @param string $uid Elementor element id.
	 * @return array
	 */
	public static function common( array $s, string $uid ): array {
		return array(
			'template_id'   => (int) ( $s['emcp_template_id'] ?? 0 ),
			'empty_message' => self::text( $s, 'emcp_empty_message', '' ),
			'tag'           => self::text( $s, 'emcp_item_tag', 'div' ),
			'anchor'        => self::text( $s, '_element_id', '' ),
			'local_id'      => $uid,
			'query'         => self::query( $s ),
			'alternates'    => self::alternates( $s['emcp_alternates'] ?? array() ),
		);
	}

	/**
	 * Loop Grid args.
	 *
	 * @param array  $s   Widget settings.
	 * @param string $uid Element id.
	 * @return array
	 */
	public static function grid( array $s, string $uid ): array {
		$d = EMCP_Tools_Themer_Element_Loop_Grid::defaults();
		return array_merge(
			self::common( $s, $uid ),
			array(
				'columns'         => self::size( $s['emcp_columns'] ?? '', (int) $d['columns'] ),
				'columns_tablet'  => self::size( $s['emcp_columns_tablet'] ?? '', (int) $d['columns_tablet'] ),
				'columns_mobile'  => self::size( $s['emcp_columns_mobile'] ?? '', (int) $d['columns_mobile'] ),
				'gap_x'           => self::size( $s['emcp_gap_x'] ?? '', (int) $d['gap_x'] ),
				'gap_y'           => self::size( $s['emcp_gap_y'] ?? '', (int) $d['gap_y'] ),
				'masonry'         => self::flag( $s, 'emcp_masonry', (bool) $d['masonry'] ),
				'equal_height'    => self::flag( $s, 'emcp_equal_height', (bool) $d['equal_height'] ),
				'first_item_span' => self::size( $s['emcp_first_item_span'] ?? '', (int) $d['first_item_span'] ),
				'hover_effect'    => self::text( $s, 'emcp_hover_effect', (string) $d['hover_effect'] ),
				'animation'       => self::text( $s, 'emcp_animation', (string) $d['animation'] ),
				'animation_step'  => self::size( $s['emcp_animation_step'] ?? '', (int) $d['animation_step'] ),
				'pagination'      => self::text( $s, 'emcp_pagination', (string) $d['pagination'] ),
				'page_limit'      => self::size( $s['emcp_page_limit'] ?? '', (int) $d['page_limit'] ),
				'shorten'         => self::flag( $s, 'emcp_shorten', (bool) $d['shorten'] ),
				'prev_label'      => self::text( $s, 'emcp_prev_label', (string) $d['prev_label'] ),
				'next_label'      => self::text( $s, 'emcp_next_label', (string) $d['next_label'] ),
				'load_more_label' => self::text( $s, 'emcp_load_more_label', (string) $d['load_more_label'] ),
				'ajax'            => isset( $s['emcp_load_type'] ) ? 'ajax' === (string) $s['emcp_load_type'] : (bool) $d['ajax'],
				'infinite_offset' => self::size( $s['emcp_infinite_offset'] ?? '', (int) $d['infinite_offset'] ),
				// Elementor's own selectors write the custom properties, so the
				// element must not also print an inline style attribute (it
				// would outrank every responsive and unit choice).
				'inline_vars'     => false,
			)
		);
	}

	/**
	 * Loop Carousel args.
	 *
	 * @param array  $s   Widget settings.
	 * @param string $uid Element id.
	 * @return array
	 */
	public static function carousel( array $s, string $uid ): array {
		$d = EMCP_Tools_Themer_Element_Loop_Carousel::defaults();
		return array_merge(
			self::common( $s, $uid ),
			array(
				'slides'                  => self::slides( $s['emcp_slides'] ?? '', (int) $d['slides'] ),
				'slides_tablet'           => self::slides( $s['emcp_slides_tablet'] ?? '', (int) $d['slides_tablet'] ),
				'slides_mobile'           => self::slides( $s['emcp_slides_mobile'] ?? '', (int) $d['slides_mobile'] ),
				'slides_to_scroll'        => self::size( $s['emcp_slides_to_scroll'] ?? '', (int) $d['slides_to_scroll'] ),
				'slides_to_scroll_tablet' => self::size( $s['emcp_slides_to_scroll_tablet'] ?? '', (int) $d['slides_to_scroll_tablet'] ),
				'slides_to_scroll_mobile' => self::size( $s['emcp_slides_to_scroll_mobile'] ?? '', (int) $d['slides_to_scroll_mobile'] ),
				'gap'                     => self::size( $s['emcp_gap'] ?? '', (int) $d['gap'] ),
				'gap_tablet'              => self::size( $s['emcp_gap_tablet'] ?? '', (int) $d['gap_tablet'] ),
				'gap_mobile'              => self::size( $s['emcp_gap_mobile'] ?? '', (int) $d['gap_mobile'] ),
				'height'                  => self::text( $s, 'emcp_height', (string) $d['height'] ),
				'autoplay'                => self::flag( $s, 'emcp_autoplay', (bool) $d['autoplay'] ),
				'autoplay_delay'          => self::size( $s['emcp_autoplay_delay'] ?? '', (int) $d['autoplay_delay'] ),
				'pause_on_hover'          => self::flag( $s, 'emcp_pause_on_hover', (bool) $d['pause_on_hover'] ),
				'pause_on_interaction'    => self::flag( $s, 'emcp_pause_on_interaction', (bool) $d['pause_on_interaction'] ),
				'loop'                    => self::flag( $s, 'emcp_loop', (bool) $d['loop'] ),
				'speed'                   => self::size( $s['emcp_speed'] ?? '', (int) $d['speed'] ),
				'direction'               => self::text( $s, 'emcp_direction', (string) $d['direction'] ),
				'centered'                => self::flag( $s, 'emcp_centered', (bool) $d['centered'] ),
				'offset_sides'            => self::text( $s, 'emcp_offset_sides', (string) $d['offset_sides'] ),
				'offset_width'            => self::size( $s['emcp_offset_width'] ?? '', (int) $d['offset_width'] ),
				'effect'                  => self::text( $s, 'emcp_effect', (string) $d['effect'] ),
				'keyboard'                => self::flag( $s, 'emcp_keyboard', (bool) $d['keyboard'] ),
				'mousewheel'              => self::flag( $s, 'emcp_mousewheel', (bool) $d['mousewheel'] ),
				'arrows'                  => self::flag( $s, 'emcp_arrows', (bool) $d['arrows'] ),
				'arrows_position'         => self::text( $s, 'emcp_arrows_position', (string) $d['arrows_position'] ),
				'arrow_prev_svg'          => self::icon_svg( $s['emcp_arrow_prev'] ?? null ),
				'arrow_next_svg'          => self::icon_svg( $s['emcp_arrow_next'] ?? null ),
				'dots'                    => self::text( $s, 'emcp_dots', (string) $d['dots'] ),
				'dots_position'           => self::text( $s, 'emcp_dots_position', (string) $d['dots_position'] ),
			)
		);
	}

	/**
	 * Alternate-template repeater rows, in the element's alternates shape.
	 *
	 * @param mixed $rows Repeater value.
	 * @return array<int,array{template_id:int,position:int,repeat:bool,column_span:int}>
	 */
	public static function alternates( $rows ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$tid = (int) ( $row['emcp_alt_template'] ?? 0 );
			$pos = (int) ( $row['emcp_alt_position'] ?? 0 );
			if ( $tid <= 0 || $pos <= 0 ) {
				continue;
			}
			$out[] = array(
				'template_id' => $tid,
				'position'    => $pos,
				'repeat'      => isset( $row['emcp_alt_repeat'] ) && EMCP_Tools_Themer_Element_Base::truthy( $row['emcp_alt_repeat'] ),
				'column_span' => max( 1, min( 6, (int) ( $row['emcp_alt_span'] ?? 1 ) ) ),
			);
		}
		return $out;
	}

	/**
	 * Slides per view: an int or the string 'auto'.
	 *
	 * @param mixed $raw     Value.
	 * @param int   $default Fallback.
	 * @return int|string
	 */
	private static function slides( $raw, int $default ) {
		if ( 'auto' === $raw ) {
			return 'auto';
		}
		return self::size( $raw, $default );
	}

	/**
	 * Inline SVG from an Elementor ICONS control, when the icon is an uploaded SVG.
	 *
	 * The element sanitizes it (wp_kses allowlist) before printing.
	 *
	 * @param mixed $icon Control value.
	 * @return string
	 */
	private static function icon_svg( $icon ): string {
		if ( ! is_array( $icon ) || 'svg' !== (string) ( $icon['library'] ?? '' ) ) {
			return '';
		}
		$id = (int) ( is_array( $icon['value'] ?? null ) ? ( $icon['value']['id'] ?? 0 ) : 0 );
		if ( $id <= 0 ) {
			return '';
		}
		$path = get_attached_file( $id );
		if ( ! $path || ! is_readable( $path ) ) {
			return '';
		}
		$svg = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local attachment, sanitized by the element.
		return false !== stripos( $svg, '<svg' ) ? $svg : '';
	}
}
