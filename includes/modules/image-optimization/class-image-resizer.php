<?php
/**
 * Image Optimization module — in-place attachment resizer.
 *
 * Resizes an existing Media Library attachment's full-size image, backs up the
 * original under `uploads/emcp-originals/` (reversible), then regenerates all
 * sub-sizes + metadata via wp_generate_attachment_metadata() — which re-runs the
 * module's own compress + WebP pipeline (the `_emcp_optim` marker is cleared first
 * so it re-optimizes). The attachment ID and every URL/reference stay the same.
 *
 * @package EMCP_Tools
 * @since   3.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.1.1
 */
class EMCP_Tools_Image_Resizer {

	/** Image mime types we can resize. */
	const RESIZABLE = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );

	/**
	 * Validate resize inputs. Pure — no filesystem/DB access, so it's unit-testable.
	 *
	 * @param string   $mime   Attachment mime type.
	 * @param int|null $width  Target width (>0) or null.
	 * @param int|null $height Target height (>0) or null.
	 * @param bool     $crop   Whether to hard-crop to exact width×height.
	 * @return true|WP_Error
	 */
	public static function validate_input( string $mime, $width, $height, bool $crop ) {
		if ( ! in_array( $mime, self::RESIZABLE, true ) ) {
			return new WP_Error( 'not_image', __( 'That attachment is not a resizable image (JPEG, PNG, WebP or GIF).', 'emcp-tools' ) );
		}
		$w = ( null !== $width && (int) $width > 0 ) ? (int) $width : null;
		$h = ( null !== $height && (int) $height > 0 ) ? (int) $height : null;
		if ( null === $w && null === $h ) {
			return new WP_Error( 'no_dimensions', __( 'Provide a positive width and/or height.', 'emcp-tools' ) );
		}
		if ( $crop && ( null === $w || null === $h ) ) {
			return new WP_Error( 'crop_needs_both', __( 'Cropping requires both a width and a height.', 'emcp-tools' ) );
		}
		return true;
	}

	/**
	 * Resize an attachment in place.
	 *
	 * @param int      $attachment_id Attachment ID.
	 * @param int|null $width         Target width (px) or null.
	 * @param int|null $height        Target height (px) or null.
	 * @param bool     $crop          Hard-crop to exact width×height (default false = scale to fit).
	 * @return array|WP_Error Result summary on success.
	 */
	public static function resize( int $attachment_id, $width, $height, bool $crop = false ) {
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Attachment not found.', 'emcp-tools' ) );
		}
		$mime  = (string) get_post_mime_type( $attachment_id );
		$valid = self::validate_input( $mime, $width, $height, $crop );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$w = ( null !== $width && (int) $width > 0 ) ? (int) $width : null;
		$h = ( null !== $height && (int) $height > 0 ) ? (int) $height : null;

		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return new WP_Error( 'file_missing', __( 'The attachment file could not be found on disk.', 'emcp-tools' ) );
		}

		$before = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$before = array(
			'width'  => is_array( $before ) ? (int) $before[0] : 0,
			'height' => is_array( $before ) ? (int) $before[1] : 0,
			'bytes'  => (int) filesize( $file ),
		);

		// Back up the pre-resize original (once) so the change is reversible.
		$upload = wp_upload_dir();
		$backup = EMCP_Tools_Image_Optimizer::backup_path( $file, $upload );
		if ( ! file_exists( $backup ) ) {
			wp_mkdir_p( dirname( $backup ) );
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_copy
			@copy( $file, $backup );
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}
		$editor->set_quality( EMCP_Tools_Image_Optimizer::clamp_quality( (int) get_option( self::quality_option(), 60 ) ) );
		$resized = $editor->resize( $w, $h, $crop );
		if ( is_wp_error( $resized ) ) {
			return $resized;
		}
		$saved = $editor->save( $file );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		clearstatcache( true, $file );

		// Regenerate sub-sizes + metadata. Clearing the optim marker lets the
		// module's compress + WebP pipeline re-run on the new sizes.
		delete_post_meta( $attachment_id, EMCP_Tools_Image_Optimizer::META_KEY );
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$meta = wp_generate_attachment_metadata( $attachment_id, $file );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( $attachment_id, $meta );
		}

		clearstatcache( true, $file );
		$after = array(
			'width'  => is_array( $meta ) && isset( $meta['width'] ) ? (int) $meta['width'] : 0,
			'height' => is_array( $meta ) && isset( $meta['height'] ) ? (int) $meta['height'] : 0,
			'bytes'  => file_exists( $file ) ? (int) filesize( $file ) : 0,
		);

		return array(
			'attachment_id' => $attachment_id,
			'url'           => (string) wp_get_attachment_url( $attachment_id ),
			'crop'          => (bool) $crop,
			'before'        => $before,
			'after'         => $after,
			'backup'        => $backup,
		);
	}

	/** The module's quality option key (used for the resize save). */
	private static function quality_option(): string {
		return EMCP_Tools_Image_Optimization_Module::PREFIX . 'quality';
	}
}
