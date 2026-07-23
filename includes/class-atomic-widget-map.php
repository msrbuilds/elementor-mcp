<?php
/**
 * Shared convenience-param mapping for atomic (Elementor 4.0+) widgets.
 *
 * The atomic convenience tools (add-atomic-heading, add-atomic-image, …) accept
 * friendly params — `title`, `content`, `image_url`, `alt`, `video_url` — and
 * turn them into the typed `$$type` prop shapes Elementor stores. build-page,
 * however, passed widget settings through raw, so an atomic widget given the
 * same friendly params came out empty (its complex props, like `e-image`'s
 * `image` or `e-self-hosted-video`'s `source`, have no matching raw key).
 *
 * This class is the single source of that mapping so the individual tools and
 * build-page produce byte-identical settings for the same input. Each builder
 * is pure apart from `e-image`, which also writes an attachment's alt meta —
 * the one place Elementor reads alt for a media-library image.
 *
 * @package EMCP_Tools
 * @since   3.6.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps friendly params to atomic widget settings, keyed by widget type.
 *
 * @since 3.6.2
 */
class EMCP_Tools_Atomic_Widget_Map {

	/**
	 * Widget types this class knows how to build from convenience params.
	 *
	 * @return string[]
	 */
	public static function atomic_types(): array {
		return array(
			'e-heading',
			'e-paragraph',
			'e-button',
			'e-image',
			'e-svg',
			'e-youtube',
			'e-self-hosted-video',
			'e-divider',
		);
	}

	/**
	 * Whether this class can map convenience params for a widget type.
	 *
	 * @param string $widget_type Widget type, e.g. 'e-image'.
	 * @return bool
	 */
	public static function is_atomic( string $widget_type ): bool {
		return in_array( $widget_type, self::atomic_types(), true );
	}

	/**
	 * Maps convenience params to atomic settings for a widget type.
	 *
	 * @param string $widget_type Widget type.
	 * @param array  $params      Convenience params (title, content, image_url, …).
	 * @return array|null Atomic settings, or null when the type is not handled here.
	 */
	public static function settings( string $widget_type, array $params ): ?array {
		switch ( $widget_type ) {
			case 'e-heading':
				return self::heading( $params );
			case 'e-paragraph':
				return self::paragraph( $params );
			case 'e-button':
				return self::button( $params );
			case 'e-image':
				return self::image( $params );
			case 'e-svg':
				return self::svg( $params );
			case 'e-youtube':
				return self::youtube( $params );
			case 'e-self-hosted-video':
				return self::video( $params );
			case 'e-divider':
				return self::divider( $params );
			default:
				return null;
		}
	}

	/**
	 * Adds the shared link + css_id + classes tail every builder ends with.
	 *
	 * @param array $settings Settings being built (by value).
	 * @param array $params   Convenience params.
	 * @param bool  $link_target_blank Whether to honour a target_blank flag on the link.
	 * @return array
	 */
	private static function finish( array $settings, array $params, bool $link_target_blank = false ): array {
		if ( ! empty( $params['link'] ) ) {
			$target = $link_target_blank && ! empty( $params['target_blank'] );
			$settings['link'] = EMCP_Tools_Atomic_Props::link( esc_url_raw( $params['link'] ), $target );
		}
		if ( ! empty( $params['css_id'] ) ) {
			$settings['_cssid'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $params['css_id'] ) );
		}

		$settings['classes'] = EMCP_Tools_Atomic_Props::classes();
		return $settings;
	}

	/**
	 * @param array $params Convenience params.
	 * @return array
	 */
	private static function heading( array $params ): array {
		$settings = array(
			'title' => EMCP_Tools_Atomic_Props::html( sanitize_text_field( $params['title'] ?? 'Heading' ) ),
			'tag'   => EMCP_Tools_Atomic_Props::string( sanitize_text_field( $params['tag'] ?? 'h2' ) ),
		);
		return self::finish( $settings, $params );
	}

	/**
	 * @param array $params Convenience params.
	 * @return array
	 */
	private static function paragraph( array $params ): array {
		// The e-paragraph content prop is named `paragraph` (Html_V3), not
		// `text`. Writing `text` silently dropped the content (issue #56).
		$settings = array(
			'paragraph' => EMCP_Tools_Atomic_Props::html( sanitize_text_field( $params['content'] ?? 'Paragraph text' ) ),
		);
		return self::finish( $settings, $params );
	}

	/**
	 * @param array $params Convenience params.
	 * @return array
	 */
	private static function button( array $params ): array {
		$settings = array(
			'text' => EMCP_Tools_Atomic_Props::html( sanitize_text_field( $params['text'] ?? 'Click Here' ) ),
		);
		return self::finish( $settings, $params, true );
	}

	/**
	 * @param array $params Convenience params.
	 * @return array
	 */
	private static function image( array $params ): array {
		$settings = array();

		$image_id  = absint( $params['image_id'] ?? 0 );
		$image_url = esc_url_raw( $params['image_url'] ?? '' );
		$alt       = isset( $params['alt'] ) ? sanitize_text_field( $params['alt'] ) : '';

		if ( $image_id ) {
			$settings['image'] = EMCP_Tools_Atomic_Props::image( $image_id, '', $alt );

			// For an attachment Elementor renders the media library's own alt
			// text, so that is the only place setting it has any effect.
			// `e-image` has no top-level `alt` prop.
			if ( '' !== $alt ) {
				update_post_meta( $image_id, '_wp_attachment_image_alt', $alt );
			}
		} elseif ( $image_url ) {
			$settings['image'] = EMCP_Tools_Atomic_Props::image( 0, $image_url, $alt );
		}

		return self::finish( $settings, $params );
	}

	/**
	 * @param array $params Convenience params.
	 * @return array
	 */
	private static function svg( array $params ): array {
		$settings = array();

		$svg_id  = absint( $params['svg_id'] ?? 0 );
		$svg_url = esc_url_raw( $params['svg_url'] ?? '' );

		if ( $svg_id ) {
			$settings['svg'] = EMCP_Tools_Atomic_Props::svg( $svg_id );
		} elseif ( $svg_url ) {
			$settings['svg'] = EMCP_Tools_Atomic_Props::svg( 0, $svg_url );
		}

		return self::finish( $settings, $params );
	}

	/**
	 * @param array $params Convenience params.
	 * @return array
	 */
	private static function youtube( array $params ): array {
		// e-youtube's video prop is `source`, a plain string (union), NOT the
		// video-src shape the self-hosted video widget uses.
		$settings = array(
			'source' => EMCP_Tools_Atomic_Props::string( esc_url_raw( $params['video_url'] ?? '' ) ),
		);
		return self::finish( $settings, $params );
	}

	/**
	 * @param array $params Convenience params.
	 * @return array
	 */
	private static function video( array $params ): array {
		$settings = array();

		$video_id  = absint( $params['video_id'] ?? 0 );
		$video_url = esc_url_raw( $params['video_url'] ?? '' );

		// `source` is a video-src shape, not a plain url. A url envelope made
		// Elementor refuse the element outright.
		if ( $video_id ) {
			$settings['source'] = EMCP_Tools_Atomic_Props::video_src( $video_id );
		} elseif ( $video_url ) {
			$settings['source'] = EMCP_Tools_Atomic_Props::video_src( 0, $video_url );
		}

		return self::finish( $settings, $params );
	}

	/**
	 * @param array $params Convenience params.
	 * @return array
	 */
	private static function divider( array $params ): array {
		return self::finish( array(), $params );
	}
}
