<?php
/**
 * Generates `.webp` siblings for image files via WP_Image_Editor.
 *
 * A sibling is `name-800x600.jpg.webp` next to `name-800x600.jpg`, so the
 * original extension is preserved and the rewriter can find it deterministically.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WebP sibling generator.
 *
 * @since 3.1.0
 */
class EMCP_Tools_Webp_Generator {

	/** @var int JPEG/WebP quality, already clamped by the caller. */
	private $quality;

	/**
	 * @param int $quality Encode quality (40–95).
	 */
	public function __construct( int $quality ) {
		$this->quality = $quality;
	}

	/**
	 * The `.webp` sibling path for a given image file.
	 *
	 * @param string $file Absolute image path.
	 * @return string Sibling path (`$file . '.webp'`).
	 */
	public static function sibling_path( string $file ): string {
		return $file . '.webp';
	}

	/** @return bool Whether this server's image editor can output WebP. */
	public function is_available(): bool {
		return (bool) wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
	}

	/**
	 * Generate a `.webp` sibling for one file. Skips if the sibling already
	 * exists or WebP is unsupported.
	 *
	 * @param string $file Absolute image path.
	 * @return string|\WP_Error Sibling path on success, WP_Error otherwise.
	 */
	public function generate( string $file ) {
		if ( ! $this->is_available() ) {
			return new \WP_Error( 'webp_unsupported', __( 'This server cannot generate WebP images.', 'emcp-tools' ) );
		}
		$sibling = self::sibling_path( $file );
		if ( file_exists( $sibling ) ) {
			return $sibling;
		}
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}
		$editor->set_quality( $this->quality );
		$saved = $editor->save( $sibling, 'image/webp' );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return $sibling;
	}
}
