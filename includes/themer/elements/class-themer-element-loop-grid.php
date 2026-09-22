<?php
/**
 * Loop Grid element: a Loop Item once per post in a responsive grid, with
 * masonry, equal height, first-item span, hover and entrance effects, and
 * five pagination styles.
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
	 * @param array $args Merged args.
	 * @return string[]
	 */
	public static function class_list( array $args ): array {
		$classes = array( 'emcp-loop', 'emcp-loop--grid' );
		if ( self::truthy( $args['masonry'] ) ) {
			$classes[] = 'is-masonry';
		}
		if ( self::truthy( $args['equal_height'] ) ) {
			$classes[] = 'is-equal-height';
		}
		if ( (int) $args['first_item_span'] > 1 ) {
			$classes[] = 'has-first-span';
		}
		$hover = (string) $args['hover_effect'];
		if ( in_array( $hover, self::HOVER, true ) && 'none' !== $hover ) {
			$classes[] = 'has-hover-' . $hover;
		}
		$anim = (string) $args['animation'];
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
		$cols = max( 1, min( 6, (int) $args['columns'] ) );
		$span = max( 1, min( $cols, (int) $args['first_item_span'] ) );
		return sprintf(
			'--emcp-cols:%d;--emcp-cols-t:%d;--emcp-cols-m:%d;--emcp-gap-x:%dpx;--emcp-gap-y:%dpx;--emcp-first-span:%d;--emcp-anim-step:%dms',
			$cols,
			max( 1, min( 6, (int) $args['columns_tablet'] ) ),
			max( 1, min( 6, (int) $args['columns_mobile'] ) ),
			max( 0, (int) $args['gap_x'] ),
			max( 0, (int) $args['gap_y'] ),
			$span,
			max( 0, (int) $args['animation_step'] )
		);
	}

	/**
	 * @param array $args Element args.
	 * @return array
	 */
	protected static function layout_config( array $args ): array {
		$cols = max( 1, min( 6, (int) ( $args['columns'] ?? 3 ) ) );
		return array( 'first_item_span' => max( 1, min( $cols, (int) ( $args['first_item_span'] ?? 1 ) ) ) );
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
			$label = '' !== trim( (string) $args['load_more_label'] ) ? (string) $args['load_more_label'] : __( 'Load more', 'emcp-tools' );
			return '<div class="emcp-loop__more"><button type="button" class="emcp-loop__more-btn">' . esc_html( $label ) . '</button></div>';
		}
		if ( 'infinite' === $type ) {
			return $p['page'] >= $pages ? '' : '<div class="emcp-loop__sentinel" data-offset="' . max( 0, (int) $args['infinite_offset'] ) . '" hidden></div>';
		}

		$is_current = 'current' === $p['query']['source'];

		// Core only substitutes format into base at a literal %_% placeholder;
		// a base built with %#% directly (the earlier approach here) leaves
		// format inert and never special-cases page one, so page one links to
		// /page/1/ and takes a redirect. Building base with %_% and letting
		// format supply the page segment is how paginate_links() itself
		// composes its own defaults.
		if ( $is_current ) {
			// Reuses whatever pagination scheme get_pagenum_link() already
			// resolves (pretty or plain permalinks) instead of guessing it.
			$clean  = untrailingslashit( (string) get_pagenum_link( 1 ) );
			$tagged = (string) get_pagenum_link( 2 );
			$suffix = ( 0 === strpos( $tagged, $clean ) ) ? substr( $tagged, strlen( $clean ) ) : '?paged=2';
			$base   = $clean . '%_%';
			$format = str_replace( '2', '%#%', $suffix );
		} else {
			$page_var = self::page_var( $p['uid'] );
			$clean    = (string) remove_query_arg( $page_var );
			$sep      = ( false === strpos( $clean, '?' ) ) ? '?' : '&';
			$base     = $clean . '%_%' . '#emcp-loop-' . $p['uid'];
			$format   = $sep . $page_var . '=%#%';
		}

		$links = paginate_links(
			array(
				'type'      => 'array',
				'total'     => $pages,
				'current'   => $p['page'],
				'show_all'  => false,
				'end_size'  => self::truthy( $args['shorten'] ) ? 1 : 2,
				'mid_size'  => self::truthy( $args['shorten'] ) ? 1 : 2,
				'prev_next' => 'numbers' !== $type,
				'prev_text' => '' !== trim( (string) $args['prev_label'] ) ? (string) $args['prev_label'] : __( 'Previous', 'emcp-tools' ),
				'next_text' => '' !== trim( (string) $args['next_label'] ) ? (string) $args['next_label'] : __( 'Next', 'emcp-tools' ),
				'format'    => $format,
				'base'      => $base,
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
		return '<nav class="emcp-loop__pagination" aria-label="' . esc_attr__( 'Pagination', 'emcp-tools' ) . '">' . implode( '', array_map( 'wp_kses_post', $links ) ) . '</nav>';
	}
}
