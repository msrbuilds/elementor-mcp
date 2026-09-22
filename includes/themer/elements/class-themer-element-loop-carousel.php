<?php
/**
 * Loop Carousel element: a Loop Item once per post in a Swiper carousel.
 *
 * Server-side output is a static row of slides (the Gutenberg preview and a
 * no-JS page still show content); themer-loop.js reads data-emcp-carousel
 * and starts Swiper.
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
class EMCP_Tools_Themer_Element_Loop_Carousel extends EMCP_Tools_Themer_Element_Loop_Base {

	const EFFECTS  = array( 'slide', 'fade', 'coverflow' );
	const DOTS     = array( 'none', 'bullets', 'fraction', 'progress' );
	const OFFSETS  = array( 'none', 'both', 'left', 'right' );
	const ARROWS   = array( 'inside', 'outside', 'bottom' );
	const DOTS_POS = array( 'inside', 'outside' );

	const PREV_SVG = '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
	const NEXT_SVG = '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>';

	/** @return array */
	public static function defaults(): array {
		return array(
			'slides'                  => 3,
			'slides_tablet'           => 2,
			'slides_mobile'           => 1,
			'slides_to_scroll'        => 1,
			'slides_to_scroll_tablet' => 1,
			'slides_to_scroll_mobile' => 1,
			'gap'                     => 24,
			'gap_tablet'              => 20,
			'gap_mobile'              => 16,
			'height'                  => 'auto',
			'autoplay'                => false,
			'autoplay_delay'          => 5000,
			'pause_on_hover'          => true,
			'pause_on_interaction'    => true,
			'loop'                    => false,
			'speed'                   => 500,
			'direction'               => 'ltr',
			'centered'                => false,
			'offset_sides'            => 'none',
			'offset_width'            => 40,
			'effect'                  => 'slide',
			'keyboard'                => false,
			'mousewheel'              => false,
			'arrows'                  => true,
			'arrows_position'         => 'inside',
			'arrow_prev_svg'          => '',
			'arrow_next_svg'          => '',
			'dots'                    => 'bullets',
			'dots_position'           => 'inside',
		);
	}

	/**
	 * @param array $args See the plan's interface list.
	 * @return string
	 */
	public static function render( array $args = array() ): string {
		$args = array_merge( self::defaults(), $args );
		$p    = self::prepare( $args, 'carousel' );
		if ( null === $p ) {
			return self::admin_comment( 'no_template', 'choose a published Loop Item' );
		}
		$attrs = 'class="' . esc_attr( implode( ' ', self::class_list( $args ) ) ) . '" id="emcp-loop-' . esc_attr( $p['uid'] ) . '" ' . self::data_attributes( $p )
			. ' data-emcp-carousel="' . esc_attr( (string) wp_json_encode( self::swiper_options( $args ) ) ) . '"'
			. ' style="--emcp-offset:' . max( 0, (int) $args['offset_width'] ) . 'px;--emcp-gap:' . max( 0, (int) $args['gap'] ) . 'px"';
		$html  = '<div ' . $attrs . '>' . implode( '', $p['notes'] );
		if ( ! $p['items'] ) {
			return $html . self::empty_html( $args ) . '</div>';
		}
		// The renderer already stamped 'swiper-slide' onto each of THIS call's
		// own items via item_classes() below (never onto a card's own nested
		// Loop Grid/Carousel, which builds its items through a separate call).
		// Swiper reads RTL only from the container's own dir attribute or
		// computed CSS direction, never from an init option, so it is set here.
		$swiper_attrs = 'class="swiper"';
		if ( 'rtl' === (string) $args['direction'] ) {
			$swiper_attrs .= ' dir="' . esc_attr( 'rtl' ) . '"';
		}
		$html .= '<div ' . $swiper_attrs . '><div class="swiper-wrapper">' . self::items_html( $p ) . '</div></div>';
		if ( self::truthy( $args['arrows'] ) ) {
			$html .= '<button type="button" class="emcp-loop__arrow emcp-loop__arrow--prev" aria-label="' . esc_attr__( 'Previous', 'emcp-tools' ) . '">' . self::svg( (string) $args['arrow_prev_svg'], self::PREV_SVG ) . '</button>';
			$html .= '<button type="button" class="emcp-loop__arrow emcp-loop__arrow--next" aria-label="' . esc_attr__( 'Next', 'emcp-tools' ) . '">' . self::svg( (string) $args['arrow_next_svg'], self::NEXT_SVG ) . '</button>';
		}
		if ( in_array( (string) $args['dots'], self::DOTS, true ) && 'none' !== $args['dots'] ) {
			$html .= '<div class="emcp-loop__dots swiper-pagination"></div>';
		}
		return $html . '</div>';
	}

	/**
	 * @param array $args Merged args.
	 * @return string[]
	 */
	public static function class_list( array $args ): array {
		$classes = array( 'emcp-loop', 'emcp-loop--carousel' );
		if ( self::truthy( $args['arrows'] ) ) {
			$classes[] = 'arrows-' . ( in_array( (string) $args['arrows_position'], self::ARROWS, true ) ? (string) $args['arrows_position'] : 'inside' );
		}
		if ( 'none' !== (string) $args['dots'] && in_array( (string) $args['dots'], self::DOTS, true ) ) {
			$classes[] = 'dots-' . ( in_array( (string) $args['dots_position'], self::DOTS_POS, true ) ? (string) $args['dots_position'] : 'inside' );
		}
		$offset = (string) $args['offset_sides'];
		if ( in_array( $offset, self::OFFSETS, true ) && 'none' !== $offset ) {
			$classes[] = 'offset-' . $offset;
		}
		if ( 'equal' === (string) $args['height'] ) {
			$classes[] = 'is-equal-height';
		}
		return $classes;
	}

	/**
	 * Swiper needs its slide class on the outer wrapper of each of THIS
	 * carousel's own items (never a card's own nested loop, which renders its
	 * items through a separate render_items() call and never sees this).
	 *
	 * @param array $args Element args (unused; every carousel item is a slide).
	 * @return string[]
	 */
	protected static function item_classes( array $args ): array {
		unset( $args );
		return array( 'swiper-slide' );
	}

	/**
	 * Swiper 8 options (only keys common to Swiper 8 and 11).
	 *
	 * @param array $args Merged args.
	 * @return array
	 */
	public static function swiper_options( array $args ): array {
		$effect = in_array( (string) $args['effect'], self::EFFECTS, true ) ? (string) $args['effect'] : 'slide';
		$slides = self::slides_value( $args['slides'], 3 );
		if ( 'fade' === $effect ) {
			$slides = 1;
		}
		$o = array(
			'slidesPerView'  => $slides,
			'slidesPerGroup' => self::slides_to_scroll_value( $args['slides_to_scroll'] ),
			'spaceBetween'   => max( 0, (int) $args['gap'] ),
			'speed'          => max( 100, min( 5000, (int) $args['speed'] ) ),
			'loop'           => self::truthy( $args['loop'] ),
			// No 'dir' key: Swiper has no such init option in any version. It
			// detects RTL only from the container element's own dir attribute
			// or computed CSS direction, which render() sets on .swiper.
			'centeredSlides' => self::truthy( $args['centered'] ),
			'effect'         => $effect,
			'autoHeight'     => 'equal' !== (string) $args['height'],
			'autoplay'       => false,
			'breakpoints'    => array(
				0   => array(
					'slidesPerView'  => 'fade' === $effect ? 1 : self::slides_value( $args['slides_mobile'], 1 ),
					'slidesPerGroup' => self::slides_to_scroll_value( $args['slides_to_scroll_mobile'] ),
					'spaceBetween'   => max( 0, (int) $args['gap_mobile'] ),
				),
				768 => array(
					'slidesPerView'  => 'fade' === $effect ? 1 : self::slides_value( $args['slides_tablet'], 2 ),
					'slidesPerGroup' => self::slides_to_scroll_value( $args['slides_to_scroll_tablet'] ),
					'spaceBetween'   => max( 0, (int) $args['gap_tablet'] ),
				),
				1025 => array(
					'slidesPerView'  => $slides,
					'slidesPerGroup' => self::slides_to_scroll_value( $args['slides_to_scroll'] ),
					'spaceBetween'   => max( 0, (int) $args['gap'] ),
				),
			),
		);
		if ( self::truthy( $args['autoplay'] ) ) {
			$o['autoplay'] = array(
				'delay'                => max( 500, (int) $args['autoplay_delay'] ),
				'disableOnInteraction' => self::truthy( $args['pause_on_interaction'] ),
				'pauseOnMouseEnter'    => self::truthy( $args['pause_on_hover'] ),
			);
		}
		if ( 'fade' === $effect ) {
			$o['fadeEffect'] = array( 'crossFade' => true );
		}
		if ( 'coverflow' === $effect ) {
			$o['coverflowEffect'] = array( 'rotate' => 30, 'stretch' => 0, 'depth' => 100, 'modifier' => 1, 'slideShadows' => false );
		}
		if ( self::truthy( $args['keyboard'] ) ) {
			$o['keyboard'] = array( 'enabled' => true );
		}
		if ( self::truthy( $args['mousewheel'] ) ) {
			$o['mousewheel'] = array( 'forceToAxis' => true );
		}
		$dots = (string) $args['dots'];
		if ( in_array( $dots, self::DOTS, true ) && 'none' !== $dots ) {
			$o['pagination'] = array( 'type' => 'progress' === $dots ? 'progressbar' : $dots, 'clickable' => true );
		}
		if ( self::truthy( $args['arrows'] ) ) {
			$o['navigation'] = true; // the script wires the element's own buttons.
		}
		return $o;
	}

	/**
	 * @param mixed $v       Slides setting.
	 * @param int   $default Fallback.
	 * @return int|string
	 */
	private static function slides_value( $v, int $default ) {
		if ( 'auto' === $v ) {
			return 'auto';
		}
		$n = (int) $v;
		return $n >= 1 && $n <= 10 ? $n : $default;
	}

	/**
	 * Slides-to-scroll (slidesPerGroup), clamped 1..10 the same way at every
	 * breakpoint: it was previously only floored at 1 for mobile and tablet,
	 * so a caller-supplied value above 10 escaped the desktop-and-top-level
	 * ceiling on those two.
	 *
	 * @param mixed $v Slides-to-scroll setting.
	 * @return int
	 */
	private static function slides_to_scroll_value( $v ): int {
		return max( 1, min( 10, (int) $v ) );
	}

	/**
	 * A safe inline SVG (or the default).
	 *
	 * @param string $svg     Custom markup.
	 * @param string $default Default markup.
	 * @return string
	 */
	private static function svg( string $svg, string $default ): string {
		$svg = trim( $svg );
		if ( '' === $svg || false === stripos( $svg, '<svg' ) ) {
			return $default;
		}
		$allowed = array(
			'svg'      => array( 'viewbox' => true, 'width' => true, 'height' => true, 'xmlns' => true, 'fill' => true, 'stroke' => true, 'aria-hidden' => true, 'focusable' => true, 'class' => true ),
			'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true ),
			'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true ),
			'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true ),
			'g'        => array( 'fill' => true, 'stroke' => true ),
		);
		$clean = wp_kses( $svg, $allowed );
		return '' !== trim( $clean ) ? $clean : $default;
	}
}
