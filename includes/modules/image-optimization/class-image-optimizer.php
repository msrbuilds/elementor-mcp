<?php
/**
 * Compress-on-upload pipeline for the Image Optimization module.
 *
 * Runs on `wp_generate_attachment_metadata`: preserves the full-size (only
 * trimmed when a max-dimension cap is set), backs up each file it touches under
 * `uploads/emcp-originals/`, re-compresses the generated sub-sizes in place, and
 * (when enabled) generates a `.webp` sibling per size. Records per-attachment
 * results in `_emcp_optim` meta and is idempotent (status=done short-circuits).
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image optimizer.
 *
 * @since 3.1.0
 */
class EMCP_Tools_Image_Optimizer {

	const META_KEY = '_emcp_optim';

	/** @var array{compress:bool,webp:bool,quality:int,max_dimension:int,keep_originals:bool} */
	private $settings;

	/**
	 * @param array $settings Module settings (see module option map).
	 */
	public function __construct( array $settings ) {
		$this->settings = array(
			'compress'       => ! empty( $settings['compress'] ),
			'webp'           => ! empty( $settings['webp'] ),
			'quality'        => self::clamp_quality( (int) ( $settings['quality'] ?? 60 ) ),
			'max_dimension'  => max( 0, (int) ( $settings['max_dimension'] ?? 0 ) ),
			'keep_originals' => ! empty( $settings['keep_originals'] ),
		);
	}

	/**
	 * Clamp a quality value to the supported 1–100 range (matches the slider).
	 *
	 * @param int $q Raw quality.
	 * @return int Clamped quality.
	 */
	public static function clamp_quality( int $q ): int {
		return max( 1, min( 100, $q ) );
	}

	/**
	 * Absolute paths of every size file to process (full + generated sub-sizes).
	 *
	 * @param array  $metadata Attachment metadata (`file`, `sizes`).
	 * @param string $basedir  Uploads base dir (no trailing slash).
	 * @return string[] Absolute file paths.
	 */
	public function sizes_to_process( array $metadata, string $basedir ): array {
		$basedir = rtrim( $basedir, '/\\' );
		$files   = array();

		if ( empty( $metadata['file'] ) ) {
			return $files;
		}

		$full    = $basedir . '/' . $metadata['file'];
		$files[] = $full;
		$dir     = dirname( $full );

		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$files[] = $dir . '/' . $size['file'];
				}
			}
		}
		return array_values( array_unique( $files ) );
	}

	/**
	 * Backup path for a file, mirroring its uploads-relative path under
	 * `uploads/emcp-originals/`.
	 *
	 * @param string $file   Absolute file inside uploads.
	 * @param array  $upload wp_upload_dir() array (needs `basedir`).
	 * @return string Absolute backup path.
	 */
	public static function backup_path( string $file, array $upload ): string {
		$basedir = rtrim( $upload['basedir'] ?? '', '/\\' );
		$rel     = ltrim( str_replace( $basedir, '', $file ), '/\\' );
		return $basedir . '/emcp-originals/' . $rel;
	}

	/**
	 * @param array $optim Existing `_emcp_optim` meta.
	 * @return bool Whether processing should be skipped (already done).
	 */
	public function should_skip( array $optim ): bool {
		return isset( $optim['status'] ) && 'done' === $optim['status'];
	}

	/**
	 * Hook callback for `wp_generate_attachment_metadata`.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array The (unchanged) metadata — WordPress expects it returned.
	 */
	public function on_generate_metadata( $metadata, $attachment_id ) {
		if ( ! is_array( $metadata ) ) {
			return $metadata;
		}
		if ( ! $this->settings['compress'] && ! $this->settings['webp'] ) {
			return $metadata;
		}
		$mime = get_post_mime_type( (int) $attachment_id );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
			return $metadata;
		}
		$existing = get_post_meta( (int) $attachment_id, self::META_KEY, true );
		if ( is_array( $existing ) && $this->should_skip( $existing ) ) {
			return $metadata;
		}

		$upload  = wp_upload_dir();
		$basedir = $upload['basedir'] ?? '';
		$files   = $this->sizes_to_process( $metadata, $basedir );
		$result  = $this->process_files( $files, $upload, $metadata['file'] ?? '', $basedir );

		update_post_meta( (int) $attachment_id, self::META_KEY, $result );
		return $metadata;
	}

	/**
	 * Process a concrete list of files (used by both upload + bulk paths).
	 *
	 * @param string[] $files    Absolute paths.
	 * @param array    $upload   wp_upload_dir() array.
	 * @param string   $full_rel Uploads-relative path of the full-size (for the cap).
	 * @param string   $basedir  Uploads base dir.
	 * @return array Result meta.
	 */
	public function process_files( array $files, array $upload, string $full_rel, string $basedir ): array {
		$generator  = new EMCP_Tools_Webp_Generator( $this->settings['quality'] );
		$webp_ok    = $this->settings['webp'] && $generator->is_available();
		$full_abs   = '' !== $full_rel ? rtrim( $basedir, '/\\' ) . '/' . $full_rel : '';
		$before     = 0;
		$after      = 0;
		$webp_bytes = 0;
		$backups    = array();
		$webps      = array();

		foreach ( $files as $file ) {
			if ( ! file_exists( $file ) ) {
				continue;
			}
			$before += (int) filesize( $file );

			if ( $this->settings['keep_originals'] ) {
				$backup = self::backup_path( $file, $upload );
				if ( ! file_exists( $backup ) ) {
					wp_mkdir_p( dirname( $backup ) );
					@copy( $file, $backup );
					$backups[] = $backup;
				}
			}

			$editor = wp_get_image_editor( $file );
			if ( ! is_wp_error( $editor ) ) {
				$editor->set_quality( $this->settings['quality'] );
				if ( $this->settings['max_dimension'] > 0 && $file === $full_abs ) {
					$editor->resize( $this->settings['max_dimension'], $this->settings['max_dimension'], false );
				}
				if ( $this->settings['compress'] ) {
					$editor->save( $file );
				}
			}
			clearstatcache( true, $file );
			$after += (int) filesize( $file );

			if ( $webp_ok ) {
				$sibling = $generator->generate( $file );
				if ( ! is_wp_error( $sibling ) && file_exists( $sibling ) ) {
					$webps[]     = $sibling;
					$webp_bytes += (int) filesize( $sibling );
				}
			}
		}

		return array(
			'status'          => 'done',
			'original_bytes'  => $before,
			'optimized_bytes' => $after,
			'webp_bytes'      => $webp_bytes,
			'backups'         => $backups,
			'webps'           => $webps,
		);
	}
}
