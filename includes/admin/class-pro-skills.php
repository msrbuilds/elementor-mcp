<?php
/**
 * Premium Skills download — bundles plugin's skills/emcp-skills folder on the
 * fly and streams it as emcp-skills.zip for licensed Pro users.
 *
 * The skills/ folder ships only in the premium build (.emcp-pro marker
 * file present); the free zip excludes it. Even on a premium install the
 * download is also gated by emcp_tools_fs()->can_use_premium_code() so a
 * leaked premium zip on a non-licensed site can't serve the skill.
 *
 * @package EMCP_Tools
 * @since   1.7.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Pro_Skills {

	/**
	 * Relative path (inside the plugin directory) to the skill source folder.
	 * Folder contents get zipped and streamed.
	 */
	const SOURCE_RELATIVE = 'skills/emcp-skills';

	/**
	 * Filename sent to the user.
	 */
	const DOWNLOAD_FILENAME = 'emcp-skills.zip';

	/**
	 * Action name used by admin-post.php — single source of truth.
	 */
	const ACTION = 'emcp_tools_download_skills';

	public function init(): void {
		// admin-post.php handles both logged-in and logged-out cases. Skills
		// download is admin-only, so register the authed handler only.
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_download' ) );
	}

	/**
	 * Site can serve the skills zip iff:
	 *   1. Freemius license grants Pro access on this site, AND
	 *   2. The skills folder is actually present (premium build).
	 */
	public static function user_has_access(): bool {
		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return false;
		}
		if ( ! emcp_tools_fs()->can_use_premium_code() ) {
			return false;
		}
		return self::skills_dir_exists();
	}

	/**
	 * Whether the skills folder is present on disk. True only on premium
	 * builds (the marker `.emcp-pro` ships in the same release zip that
	 * carries this folder).
	 */
	public static function skills_dir_exists(): bool {
		$path = self::source_path();
		return is_dir( $path ) && is_readable( $path );
	}

	/**
	 * Returns the absolute path to the skill source folder.
	 */
	private static function source_path(): string {
		return EMCP_TOOLS_DIR . self::SOURCE_RELATIVE;
	}

	/**
	 * URL for the download action — used by the button on the Skills tab.
	 */
	public static function download_url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION ),
			self::ACTION,
			'_emcp_nonce'
		);
	}

	/**
	 * admin-post.php callback. Validates auth + license, builds a zip in
	 * a temp file, streams it to the client. Halts execution at the end —
	 * standard for file-download handlers.
	 */
	public function handle_download(): void {
		// Nonce + cap check.
		if (
			! isset( $_GET['_emcp_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_emcp_nonce'] ) ), self::ACTION )
		) {
			wp_die( esc_html__( 'Invalid request.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		if ( ! self::user_has_access() ) {
			wp_die( esc_html__( 'A valid EMCP Tools Pro license is required to download the skills bundle.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'The ZipArchive PHP extension is required to build the skill bundle. Please contact your host to enable it.', 'emcp-tools' ), '', array( 'response' => 500 ) );
		}

		$source = self::source_path();
		$tmp    = wp_tempnam( self::DOWNLOAD_FILENAME );
		if ( ! $tmp ) {
			wp_die( esc_html__( 'Could not create a temporary file for the skill bundle.', 'emcp-tools' ), '', array( 'response' => 500 ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			wp_die( esc_html__( 'Could not open the temporary zip archive for writing.', 'emcp-tools' ), '', array( 'response' => 500 ) );
		}

		// Top-level folder name inside the zip is `emcp-skills/` so users get
		// a sensible directory when they extract it on their machine.
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		$base_in_zip = 'emcp-skills/';
		foreach ( $iterator as $file_info ) {
			$abs_path  = $file_info->getPathname();
			$rel_path  = ltrim( substr( $abs_path, strlen( $source ) ), DIRECTORY_SEPARATOR . '/' );
			$zip_path  = $base_in_zip . str_replace( DIRECTORY_SEPARATOR, '/', $rel_path );
			if ( $file_info->isDir() ) {
				$zip->addEmptyDir( $zip_path );
			} else {
				$zip->addFile( $abs_path, $zip_path );
			}
		}
		$zip->close();

		// Stream the file.
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . self::DOWNLOAD_FILENAME . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		header( 'X-Content-Type-Options: nosniff' );

		// flush any output buffers WP started so the binary doesn't get
		// corrupted by stray whitespace from autoload.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		readfile( $tmp );
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		exit;
	}
}
