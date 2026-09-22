<?php
/**
 * Loop Grid element: a Loop Item once per post in a responsive grid, with
 * masonry, equal height, first-item span, hover and entrance effects, and
 * six pagination styles: none, numbers, previous/next, numbers with
 * previous/next, load more, and infinite scroll.
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
class EMCP_Tools_Themer_Element_Loop_Grid extends EMCP_Tools_Themer_Element_Loop_Base {

	const HOVER      = array( 'none', 'lift', 'zoom', 'shadow' );
	const ANIMATIONS = array( 'none', 'fade-up', 'fade-in', 'zoom-in' );

	/** @return array */
	public static function defaults(): array {
		return array(
			'columns'         => 3,
			'columns_tablet'  => 2,
			'columns_mobile'  => 1,
			'gap_x'           => 24,
			'gap_y'           => 24,
			'masonry'         => false,
			'equal_height'    => false,
			'first_item_span' => 1,
			'hover_effect'    => 'none',
			'animation'       => 'none',
			'animation_step'  => 80,
			'pagination'      => 'none',
			'page_limit'      => 0,
			'shorten'         => false,
			'prev_label'      => '',
			'next_label'      => '',
			'load_more_label' => '',
			'ajax'            => false,
			'infinite_offset' => 200,
			'inline_vars'     => true,
			'tag'             => 'div',
		);
	}

	/**
	 * @param array $args See the plan's interface list.
	 * @return string
	 */
	public static function render( array $args = array() ): string {
		$args = array_merge( self::defaults(), $args );
		$p    = self::prepare( $args, 'grid' );
		if ( null === $p ) {
			return self::admin_comment( 'no_template', 'choose a published Loop Item' );
		}
		$attrs = 'class="' . esc_attr( implode( ' ', self::class_list( $args ) ) ) . '" id="emcp-loop-' . esc_attr( $p['uid'] ) . '" ' . self::data_attributes( $p );
		if ( 'none' !== $p['pagination'] ) {
			// The page URL scheme, for the script: it turns a pagination link
			// into a page number, rebuilds the numbers nav after an AJAX
			// replace, and builds the reload-fallback URL. Printed for every
			// pagination type, since load more and infinite scroll fall back
			// to a reload too. %#% stands for the page number.
			$scheme = self::url_scheme( $p );
			$attrs .= ' data-emcp-url="' . esc_attr( str_replace( '%_%', $scheme['format'], $scheme['base'] ) ) . '"'
				. ' data-emcp-url-first="' . esc_attr( $scheme['first'] ) . '"';
		}
		if ( self::truthy( $args['inline_vars'] ) ) {
			$attrs .= ' style="' . esc_attr( self::style_vars( $args ) ) . '"';
		}
		$html = '<div ' . $attrs . '>' . implode( '', $p['notes'] );

		if ( ! $p['items'] ) {
			return $html . self::empty_html( $args ) . '</div>';
		}
		$html .= '<div class="emcp-loop__items">' . self::items_html( $p ) . '</div>';
		$html .= self::pagination_html( $p );
		return $html . '</div>';
	}

	/**
	 * The first-item column span, clamped to the column count. The single
	 * source both the has-first-span modifier class and the --emcp-first-span
	 * custom property must agree on; a span clamped in only one of them is
	 * a modifier class the CSS property disagrees with.
	 *
	 * @param array $args Element args (defaults not required: falls back).
	 * @return int
	 */
	protected static function first_item_span( array $args ): int {
		$cols = max( 1, min( 6, (int) ( $args['columns'] ?? 3 ) ) );
		return max( 1, min( $cols, (int) ( $args['first_item_span'] ?? 1 ) ) );
	}

	/**
	 * @param array $args Merged args.
	 * @return string[]
	 */
	public static function class_list( array $args ): array {
		$classes = array( 'emcp-loop', 'emcp-loop--grid' );
		if ( self::truthy( $args['masonry'] ?? false ) ) {
			$classes[] = 'is-masonry';
		}
		if ( self::truthy( $args['equal_height'] ?? false ) ) {
			$classes[] = 'is-equal-height';
		}
		if ( self::first_item_span( $args ) > 1 ) {
			$classes[] = 'has-first-span';
		}
		$hover = (string) ( $args['hover_effect'] ?? 'none' );
		if ( in_array( $hover, self::HOVER, true ) && 'none' !== $hover ) {
			$classes[] = 'has-hover-' . $hover;
		}
		$anim = (string) ( $args['animation'] ?? 'none' );
		if ( in_array( $anim, self::ANIMATIONS, true ) && 'none' !== $anim ) {
			$classes[] = 'has-anim-' . $anim;
		}
		return $classes;
	}

	/**
	 * The custom properties the stylesheet reads.
	 *
	 * @param array $args Merged args.
	 * @return string
	 */
	public static function style_vars( array $args ): string {
		return sprintf(
			'--emcp-cols:%d;--emcp-cols-t:%d;--emcp-cols-m:%d;--emcp-gap-x:%dpx;--emcp-gap-y:%dpx;--emcp-first-span:%d;--emcp-anim-step:%dms',
			max( 1, min( 6, (int) ( $args['columns'] ?? 3 ) ) ),
			max( 1, min( 6, (int) ( $args['columns_tablet'] ?? 2 ) ) ),
			max( 1, min( 6, (int) ( $args['columns_mobile'] ?? 1 ) ) ),
			max( 0, (int) ( $args['gap_x'] ?? 24 ) ),
			max( 0, (int) ( $args['gap_y'] ?? 24 ) ),
			self::first_item_span( $args ),
			max( 0, (int) ( $args['animation_step'] ?? 80 ) )
		);
	}

	/**
	 * @param array $args Element args.
	 * @return array
	 */
	protected static function layout_config( array $args ): array {
		return array( 'first_item_span' => self::first_item_span( $args ) );
	}

	/**
	 * Pagination, load more or the infinite sentinel.
	 *
	 * @param array $p Prepared render.
	 * @return string
	 */
	public static function pagination_html( array $p ): string {
		$args  = $p['args'];
		$type  = $p['pagination'];
		// prepare() has already clamped max_pages to page_limit; this is the
		// single source of truth so the attribute and this markup agree.
		$pages = (int) $p['result']['max_pages'];
		if ( 'none' === $type || $pages <= 1 ) {
			return '';
		}
		if ( 'load_more' === $type ) {
			if ( $p['page'] >= $pages ) {
				return '';
			}
			$label = '' !== trim( (string) ( $args['load_more_label'] ?? '' ) ) ? (string) $args['load_more_label'] : __( 'Load more', 'emcp-tools' );
			return '<div class="emcp-loop__more"><button type="button" class="emcp-loop__more-btn">' . esc_html( $label ) . '</button></div>';
		}
		if ( 'infinite' === $type ) {
			return $p['page'] >= $pages ? '' : '<div class="emcp-loop__sentinel" data-offset="' . max( 0, (int) ( $args['infinite_offset'] ?? 200 ) ) . '" hidden></div>';
		}

		$scheme  = self::url_scheme( $p );
		$shorten = self::truthy( $args['shorten'] ?? false );
		$labels  = self::nav_labels( $args );

		$links = paginate_links(
			array(
				'type'      => 'array',
				'total'     => $pages,
				'current'   => $p['page'],
				'show_all'  => false,
				'end_size'  => $shorten ? 1 : 2,
				'mid_size'  => $shorten ? 1 : 2,
				'prev_next' => 'numbers' !== $type,
				'prev_text' => $labels['prev'],
				'next_text' => $labels['next'],
				'format'    => $scheme['format'],
				'base'      => $scheme['base'],
				'add_args'  => false,
			)
		);
		if ( ! is_array( $links ) || ! $links ) {
			return '';
		}
		if ( 'prev_next' === $type ) {
			$links = array_values( array_filter( $links, static function ( $l ) {
				return false !== strpos( (string) $l, 'prev page-numbers' ) || false !== strpos( (string) $l, 'next page-numbers' );
			} ) );
			if ( ! $links ) {
				return '';
			}
		}
		// What the script needs to rebuild this nav after an AJAX replace the
		// way paginate_links() would: the kind, the window sizes and the
		// resolved labels as plain text (the script inserts them with
		// textContent, never as markup).
		$nav_attrs = ' data-kind="' . esc_attr( $type ) . '"'
			. ' data-end="' . ( $shorten ? 1 : 2 ) . '" data-mid="' . ( $shorten ? 1 : 2 ) . '"'
			. ' data-prev="' . esc_attr( self::plain_text( $labels['prev'] ) ) . '"'
			. ' data-next="' . esc_attr( self::plain_text( $labels['next'] ) ) . '"';
		return '<nav class="emcp-loop__pagination" aria-label="' . esc_attr__( 'Pagination', 'emcp-tools' ) . '"' . $nav_attrs . '>' . implode( '', array_map( 'wp_kses_post', $links ) ) . '</nav>';
	}

	/**
	 * The pagination URL scheme, as paginate_links() takes it: a base with a
	 * literal %_% where the page segment goes, and a format holding %#% for
	 * the number. The single source for both pagination_html() and the
	 * data-emcp-url attributes, so the links and the script always agree.
	 *
	 * The current source paginates the way the main query does (/page/N/ or
	 * ?paged=N); every other source by the per-element query var.
	 *
	 * @param array $p Prepared render (needs query.source and uid).
	 * @return array{base:string,format:string,first:string}
	 */
	public static function url_scheme( array $p ): array {
		// Core only substitutes format into base at a literal %_% placeholder;
		// a base built with %#% directly (an earlier approach here) leaves
		// format inert and never special-cases page one, so page one links to
		// /page/1/ and takes a redirect. Building base with %_% and letting
		// format supply the page segment is how paginate_links() itself
		// composes its own defaults.
		if ( 'current' === (string) ( $p['query']['source'] ?? 'posts' ) ) {
			return self::current_url_scheme();
		}
		$page_var = self::page_var( (string) ( $p['uid'] ?? '' ) );
		$clean    = (string) remove_query_arg( $page_var );
		$sep      = ( false === strpos( $clean, '?' ) ) ? '?' : '&';
		$base     = $clean . '%_%' . '#emcp-loop-' . ( $p['uid'] ?? '' );
		return array(
			'base'   => $base,
			'format' => $sep . $page_var . '=%#%',
			'first'  => str_replace( '%_%', '', $base ),
		);
	}

	/**
	 * The main query's own pagination scheme, read from get_pagenum_link()
	 * (pretty or plain permalinks) instead of guessing it.
	 *
	 * The query string is split off both links first: with pretty
	 * permalinks page one is /path/?s=foo and page two /path/page/2/?s=foo,
	 * so comparing the whole URLs would never find the page segment. The
	 * page segment is taken relative to page one's own path, so page one
	 * keeps its canonical form (trailing slash included) and never takes a
	 * redirect. Under plain permalinks the page is one more query argument
	 * (paged, or page on a static front page), appended with & when a query
	 * already exists.
	 *
	 * @return array{base:string,format:string,first:string}
	 */
	private static function current_url_scheme(): array {
		$first = (string) get_pagenum_link( 1, false );
		$two   = (string) get_pagenum_link( 2, false );
		list( $path1, $qs1 ) = array_pad( explode( '?', $first, 2 ), 2, '' );
		list( $path2, $qs2 ) = array_pad( explode( '?', $two, 2 ), 2, '' );
		$query = '' !== $qs1 ? '?' . $qs1 : '';

		if ( $path2 !== $path1 && 0 === strpos( $path2, $path1 ) ) {
			// Pretty permalinks: the page is a path segment after page one's path.
			$suffix = substr( $path2, strlen( $path1 ) );
			return array(
				'base'   => $path1 . '%_%' . $query,
				'format' => (string) preg_replace( '/2(?!.*2)/', '%#%', $suffix ),
				'first'  => $first,
			);
		}

		// Plain permalinks: the page is the one query argument page two adds.
		$key = 'paged';
		if ( $path2 === $path1 ) {
			$args1 = array();
			$args2 = array();
			parse_str( $qs1, $args1 );
			parse_str( $qs2, $args2 );
			foreach ( $args2 as $k => $v ) {
				if ( ! array_key_exists( $k, $args1 ) && '2' === (string) $v ) {
					$key = (string) $k;
					break;
				}
			}
		}
		return array(
			'base'   => $path1 . $query . '%_%',
			'format' => ( '' !== $query ? '&' : '?' ) . $key . '=%#%',
			'first'  => $first,
		);
	}

	/**
	 * The previous and next labels, resolved.
	 *
	 * @param array $args Element args.
	 * @return array{prev:string,next:string}
	 */
	protected static function nav_labels( array $args ): array {
		return array(
			'prev' => '' !== trim( (string) ( $args['prev_label'] ?? '' ) ) ? (string) $args['prev_label'] : __( 'Previous', 'emcp-tools' ),
			'next' => '' !== trim( (string) ( $args['next_label'] ?? '' ) ) ? (string) $args['next_label'] : __( 'Next', 'emcp-tools' ),
		);
	}

	/**
	 * A label as plain text: tags stripped, entities decoded (esc_attr then
	 * encodes it once for the attribute).
	 *
	 * @param string $label Label.
	 * @return string
	 */
	private static function plain_text( string $label ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $label ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}
}
