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

	/** Largest uploaded arrow SVG read without Elementor (100 KB). */
	const MAX_SVG_BYTES = 102400;

	/**
	 * Arguments the loop element base reads for both kinds, in addition to
	 * each element's own defaults(): identity (template, query, anchor,
	 * local id) and the shared render args (item tag, empty message,
	 * alternate templates).
	 */
	const SHARED_ARGS = array( 'template_id', 'query', 'anchor', 'local_id', 'tag', 'empty_message', 'alternates' );

	/** The two loop widgets' Elementor names (the script listens on them). */
	const WIDGET_NAMES = array( 'emcp-loop-grid', 'emcp-loop-carousel' );

	/**
	 * Option-backed settings whose saved values collect_saved() gathers,
	 * grouped by the option list they belong to.
	 */
	const SAVED_GROUPS = array(
		'templates'  => array( 'emcp_template_id' ),
		'terms'      => array( 'emcp_terms', 'emcp_exclude_terms' ),
		'authors'    => array( 'emcp_authors' ),
		'post_types' => array( 'emcp_post_types' ),
		'taxonomies' => array( 'emcp_related_taxonomy' ),
	);

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
	 * comma list (split on top-level commas only, so `:is(.a, .b)` stays
	 * whole) is crossed with the branches. A part starting with a
	 * combinator (`>`, `+`, `~`) is joined with a space; a part starting
	 * with `:`, `.`, `[` or `#` (a pseudo-class, class, attribute or id on
	 * the root itself) is appended directly, unless it goes on to use a
	 * whitespace descendant combinator. Any other part would be a
	 * descendant selector that also reaches nested loops: it is skipped and
	 * reported with _doing_it_wrong(). Write child chains that match the
	 * stylesheet's own.
	 *
	 * @param string $suffix For example '> .emcp-loop__items > .emcp-loop__item'.
	 * @return string
	 */
	public static function selector( string $suffix = '' ): string {
		$parts = array();
		foreach ( self::split_top_level( $suffix ) as $part ) {
			if ( in_array( $part[0], array( '>', '+', '~' ), true ) ) {
				$parts[] = $part;
				continue;
			}
			if ( in_array( $part[0], array( ':', '.', '[', '#' ), true ) ) {
				if ( ! self::has_descendant_combinator( $part ) ) {
					$parts[] = $part;
					continue;
				}
				// '.is-masonry .emcp-loop__item' starts on the root but then
				// reaches every descendant, nested loops included.
				_doing_it_wrong(
					__METHOD__,
					sprintf( 'Loop widget selector part "%s" uses a descendant combinator; use > (or + / ~) so it cannot reach a nested loop.', esc_html( $part ) ),
					'3.18.0'
				);
				continue;
			}
			_doing_it_wrong(
				__METHOD__,
				sprintf( 'Loop widget selector part "%s" must start with a combinator (>, +, ~) or with :, ., [ or #.', esc_html( $part ) ),
				'3.18.0'
			);
		}
		if ( ! $parts ) {
			// An empty suffix means the root; a suffix whose every part was
			// refused means nothing, never the root by accident.
			return '' === trim( $suffix ) ? self::root_selector() : '';
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
	 * Whether a selector uses a whitespace (descendant) combinator outside
	 * parentheses, brackets and quotes. Whitespace next to an explicit
	 * combinator (`>`, `+`, `~`) is only padding and does not count.
	 *
	 * @param string $sel Selector part.
	 * @return bool
	 */
	private static function has_descendant_combinator( string $sel ): bool {
		$sel   = trim( $sel );
		$len   = strlen( $sel );
		$depth = 0;
		$quote = '';
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $sel[ $i ];
			if ( '' !== $quote ) {
				if ( '\\' === $c ) {
					++$i;
				} elseif ( $c === $quote ) {
					$quote = '';
				}
				continue;
			}
			if ( '"' === $c || "'" === $c ) {
				$quote = $c;
			} elseif ( '(' === $c || '[' === $c ) {
				++$depth;
			} elseif ( ( ')' === $c || ']' === $c ) && $depth > 0 ) {
				--$depth;
			} elseif ( 0 === $depth && ctype_space( $c ) ) {
				$j = $i;
				while ( $j < $len && ctype_space( $sel[ $j ] ) ) {
					++$j;
				}
				$prev = $sel[ $i - 1 ] ?? '';
				$next = $sel[ $j ] ?? '';
				if ( ! in_array( $prev, array( '>', '+', '~' ), true ) && ! in_array( $next, array( '>', '+', '~' ), true ) ) {
					return true;
				}
				$i = $j - 1;
			}
		}
		return false;
	}

	/**
	 * Saved option values of every loop widget in an Elementor element tree.
	 *
	 * Elementor builds a widget's controls once per type, not per instance,
	 * so an option list cannot know one widget's saved value. The widget
	 * base therefore merges the values saved anywhere in the document being
	 * edited into its option lists: a Loop Item beyond the first 100, or a
	 * term beyond the cap, still shows and survives a panel edit. Alternate
	 * template rows count as templates.
	 *
	 * @param array $elements Elementor elements data (a list of elements).
	 * @return array<string,string[]> SAVED_GROUPS key => unique non-empty values.
	 */
	public static function collect_saved( array $elements ): array {
		$out = array_fill_keys( array_keys( self::SAVED_GROUPS ), array() );
		self::walk_saved( $elements, $out, 0 );
		foreach ( $out as $group => $values ) {
			$out[ $group ] = array_values( array_unique( $values ) );
		}
		return $out;
	}

	/**
	 * @param array $elements Elements.
	 * @param array $out      Collected values, by reference.
	 * @param int   $depth    Nesting depth (bounded).
	 */
	private static function walk_saved( array $elements, array &$out, int $depth ): void {
		if ( $depth > 50 ) {
			return;
		}
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$settings = is_array( $el['settings'] ?? null ) ? $el['settings'] : array();
			if ( in_array( (string) ( $el['widgetType'] ?? '' ), self::WIDGET_NAMES, true ) ) {
				foreach ( self::SAVED_GROUPS as $group => $keys ) {
					foreach ( $keys as $key ) {
						foreach ( (array) ( $settings[ $key ] ?? array() ) as $value ) {
							if ( is_scalar( $value ) && '' !== (string) $value && '0' !== (string) $value ) {
								$out[ $group ][] = (string) $value;
							}
						}
					}
				}
				foreach ( (array) ( $settings['emcp_alternates'] ?? array() ) as $row ) {
					$tid = is_array( $row ) ? (int) ( $row['emcp_alt_template'] ?? 0 ) : 0;
					if ( $tid > 0 ) {
						$out['templates'][] = (string) $tid;
					}
				}
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::walk_saved( $el['elements'], $out, $depth + 1 );
			}
		}
	}

	/**
	 * Add saved values missing from an option list, labelled by $label.
	 *
	 * @param array    $options Option list (value => label).
	 * @param string[] $values  Saved values.
	 * @param callable $label   Label for a value the list does not have.
	 * @return array
	 */
	public static function merge_saved( array $options, array $values, callable $label ): array {
		foreach ( $values as $value ) {
			$value = (string) $value;
			if ( '' === $value || array_key_exists( $value, $options ) ) {
				continue;
			}
			$options[ $value ] = (string) $label( $value );
		}
		return $options;
	}

	/**
	 * Split a selector list on commas outside parentheses.
	 *
	 * @param string $list Selector list.
	 * @return string[] Trimmed, non-empty parts.
	 */
	private static function split_top_level( string $list ): array {
		$parts = array();
		$buf   = '';
		$depth = 0;
		$len   = strlen( $list );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $list[ $i ];
			if ( '(' === $c ) {
				++$depth;
			} elseif ( ')' === $c && $depth > 0 ) {
				--$depth;
			} elseif ( ',' === $c && 0 === $depth ) {
				$parts[] = $buf;
				$buf     = '';
				continue;
			}
			$buf .= $c;
		}
		$parts[] = $buf;
		return array_values( array_filter( array_map( 'trim', $parts ), 'strlen' ) );
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
	 * A date control. Elementor's DATE_TIME control can carry a time part
	 * ("2026-01-01 00:00"); the query takes Y-m-d, so keep the date part.
	 *
	 * @param array  $s   Settings.
	 * @param string $key Key.
	 * @return string Y-m-d, or the trimmed raw value (the query blanks it).
	 */
	private static function date( array $s, string $key ): string {
		$raw = trim( self::text( $s, $key, '' ) );
		return preg_match( '/^(\d{4}-\d{2}-\d{2})/', $raw, $m ) ? $m[1] : $raw;
	}

	/**
	 * One control value, or null when it is empty ('' , null, or a slider
	 * whose size is empty).
	 *
	 * @param mixed $raw        Control value.
	 * @param bool  $allow_auto Whether the string 'auto' is a value.
	 * @return int|string|null
	 */
	private static function present( $raw, bool $allow_auto ) {
		if ( $allow_auto && 'auto' === $raw ) {
			return 'auto';
		}
		if ( is_array( $raw ) ) {
			$raw = $raw['size'] ?? null;
		}
		if ( null === $raw || '' === $raw || ! is_scalar( $raw ) ) {
			return null;
		}
		return (int) $raw;
	}

	/**
	 * A responsive control's desktop, tablet and mobile values.
	 *
	 * Mirrors Elementor's getResponsiveControlValue(): an empty device value
	 * means "use the next larger device", so tablet inherits a set desktop
	 * value and mobile inherits the resolved tablet value. Only when nothing
	 * at or above a device is set does that device take the element default.
	 *
	 * @param array $s          Settings.
	 * @param string $key       Desktop key; `{$key}_tablet` / `{$key}_mobile` follow.
	 * @param array $defaults   Element defaults: [desktop, tablet, mobile].
	 * @param bool  $allow_auto Whether 'auto' is a value (slides per view).
	 * @return array{0:int|string,1:int|string,2:int|string}
	 */
	private static function responsive( array $s, string $key, array $defaults, bool $allow_auto = false ): array {
		$d = self::present( $s[ $key ] ?? null, $allow_auto );
		$t = self::present( $s[ $key . '_tablet' ] ?? null, $allow_auto );
		$m = self::present( $s[ $key . '_mobile' ] ?? null, $allow_auto );
		$t = $t ?? $d;
		$m = $m ?? $t;
		return array( $d ?? $defaults[0], $t ?? $defaults[1], $m ?? $defaults[2] );
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
			'after'             => self::date( $s, 'emcp_after' ),
			'before'            => self::date( $s, 'emcp_before' ),
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
		$d    = EMCP_Tools_Themer_Element_Loop_Grid::defaults();
		$cols = self::responsive( $s, 'emcp_columns', array( (int) $d['columns'], (int) $d['columns_tablet'], (int) $d['columns_mobile'] ) );
		return array_merge(
			self::common( $s, $uid ),
			array(
				'columns'         => $cols[0],
				'columns_tablet'  => $cols[1],
				'columns_mobile'  => $cols[2],
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
		$d      = EMCP_Tools_Themer_Element_Loop_Carousel::defaults();
		$slides = self::responsive( $s, 'emcp_slides', array( (int) $d['slides'], (int) $d['slides_tablet'], (int) $d['slides_mobile'] ), true );
		$scroll = self::responsive( $s, 'emcp_slides_to_scroll', array( (int) $d['slides_to_scroll'], (int) $d['slides_to_scroll_tablet'], (int) $d['slides_to_scroll_mobile'] ) );
		$gap    = self::responsive( $s, 'emcp_gap', array( (int) $d['gap'], (int) $d['gap_tablet'], (int) $d['gap_mobile'] ) );
		return array_merge(
			self::common( $s, $uid ),
			array(
				'slides'                  => $slides[0],
				'slides_tablet'           => $slides[1],
				'slides_mobile'           => $slides[2],
				'slides_to_scroll'        => $scroll[0],
				'slides_to_scroll_tablet' => $scroll[1],
				'slides_to_scroll_mobile' => $scroll[2],
				'gap'                     => $gap[0],
				'gap_tablet'              => $gap[1],
				'gap_mobile'              => $gap[2],
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
				'arrows_hide_mobile'      => self::flag( $s, 'emcp_hide_arrows_mobile', (bool) $d['arrows_hide_mobile'] ),
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
	 * Inline SVG from an Elementor ICONS control, when the icon is an
	 * uploaded SVG (the widget offers SVG upload only). Any other library,
	 * or a failed read, returns '' and the element prints its default arrow.
	 *
	 * With Elementor loaded, its own Svg::get_inline_svg() reads and
	 * sanitizes the file (and caches it). Without it, the attachment must be
	 * an SVG by mime type and at most MAX_SVG_BYTES. The element sanitizes
	 * the result again (wp_kses allowlist) before printing.
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
		if ( class_exists( '\Elementor\Core\Files\File_Types\Svg' ) ) {
			$svg = \Elementor\Core\Files\File_Types\Svg::get_inline_svg( $id );
			$svg = is_string( $svg ) ? $svg : '';
		} else {
			if ( 'image/svg+xml' !== (string) get_post_mime_type( $id ) ) {
				return '';
			}
			$path = (string) get_attached_file( $id );
			if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
				return '';
			}
			$bytes = filesize( $path );
			if ( false === $bytes || $bytes > self::MAX_SVG_BYTES ) {
				return '';
			}
			$svg = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local attachment, size-capped, sanitized by the element.
		}
		return strlen( $svg ) <= self::MAX_SVG_BYTES && false !== stripos( $svg, '<svg' ) ? $svg : '';
	}
}
