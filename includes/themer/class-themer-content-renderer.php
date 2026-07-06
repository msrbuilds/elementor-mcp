<?php
/**
 * Render a Themer template's built content, whatever builder made it.
 *
 * detect_builder() inspects post meta/content; render() dispatches to the right
 * strategy (Elementor frontend + per-post CSS; Gutenberg do_blocks; else the
 * the_content filter). The default the_content path guarantees nothing ever fatals
 * on an unknown builder.
 *
 * @package EMCP_Tools
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.2.0
 */
class EMCP_Tools_Themer_Content_Renderer {

	/**
	 * Detect the owning builder for a post id.
	 *
	 * @param int $post_id Post id.
	 * @return string elementor|gutenberg|classic
	 */
	public static function detect_builder( int $post_id ): string {
		if ( 'builder' === (string) get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			return 'elementor';
		}
		$post = get_post( $post_id );
		return self::detect_builder_for_content( $post ? (string) $post->post_content : '' );
	}

	/**
	 * Detect builder from raw content (Elementor already ruled out).
	 *
	 * @param string $content Post content.
	 * @return string gutenberg|classic
	 */
	public static function detect_builder_for_content( string $content ): string {
		return has_blocks( $content ) ? 'gutenberg' : 'classic';
	}

	/**
	 * Render a template's built content.
	 *
	 * @param int $post_id Template post id.
	 * @return string HTML.
	 */
	public static function render( int $post_id ): string {
		// A PHP template attached to this Themer post takes over the region's render
		// (feature-gated + human-attached). Empty/error output falls back to builder.
		if ( class_exists( 'EMCP_Tools_Themer_PHP' ) && EMCP_Tools_Themer_PHP::enabled() ) {
			$php_id = (int) get_post_meta( $post_id, '_emcp_themer_php_template', true );
			if ( $php_id > 0 && class_exists( 'EMCP_Tools_Themer_PHP_Renderer' ) ) {
				$php_out = EMCP_Tools_Themer_PHP_Renderer::render( $php_id );
				if ( '' !== $php_out ) {
					return $php_out;
				}
			}
		}

		$builder = self::detect_builder( $post_id );

		if ( 'elementor' === $builder && class_exists( '\\Elementor\\Plugin' ) ) {
			// Ensure the template's own generated CSS is enqueued out of context.
			if ( class_exists( '\\Elementor\\Core\\Files\\CSS\\Post' ) ) {
				\Elementor\Core\Files\CSS\Post::create( $post_id )->enqueue();
			}
			return \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		// Gutenberg + classic both run through the the_content filter (do_blocks is
		// attached there), which resolves blocks and shortcodes.
		return apply_filters( 'the_content', $post->post_content );
	}
}
