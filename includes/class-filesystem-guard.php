<?php
/**
 * Filesystem safety guard: confines every path to ABSPATH, protects critical
 * files, backs up before destructive writes, audits, and caps sizes.
 *
 * This is the security boundary for the filesystem tools. resolve_path() is the
 * one chokepoint that makes "inside the WordPress install only" true.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.0.0
 */
class EMCP_Tools_Filesystem_Guard {

	const MAX_READ_BYTES  = 5242880; // 5 MB
	const MAX_WRITE_BYTES = 5242880; // 5 MB
	const AUDIT_OPTION    = 'emcp_tools_fs_audit_log';
	const AUDIT_MAX       = 200;
	const BACKUP_DIR      = 'emcp-fs-backups';

	/**
	 * Confine a path to $root (defaults to ABSPATH). Returns the canonical
	 * absolute path, or WP_Error when the path is invalid or escapes the root.
	 *
	 * @param string      $path
	 * @param string|null $root Defaults to ABSPATH; a parameter only for tests.
	 * @return string|\WP_Error
	 */
	public static function resolve_path( string $path, ?string $root = null ) {
		$root = ( null === $root ) ? ABSPATH : $root;

		if ( '' === $path || false !== strpos( $path, "\0" ) ) {
			return new \WP_Error( 'invalid_path', __( 'Invalid file path.', 'emcp-tools' ) );
		}

		$is_abs = ( '/' === $path[0] ) || ( '\\' === $path[0] ) || 1 === preg_match( '#^[A-Za-z]:[\\\\/]#', $path );
		$candidate = $is_abs ? $path : rtrim( $root, '/\\' ) . DIRECTORY_SEPARATOR . ltrim( $path, '/\\' );

		$real = realpath( $candidate );
		if ( false === $real ) {
			$parent = realpath( dirname( $candidate ) );
			if ( false === $parent ) {
				return new \WP_Error( 'parent_missing', __( 'The target directory does not exist.', 'emcp-tools' ) );
			}
			$real = $parent . DIRECTORY_SEPARATOR . basename( $candidate );
		}

		$root_real = realpath( $root );
		if ( false === $root_real ) {
			return new \WP_Error( 'bad_root', __( 'Filesystem root is unavailable.', 'emcp-tools' ) );
		}

		$real_n = rtrim( $real, '/\\' );
		$root_n = rtrim( $root_real, '/\\' );
		if ( $real_n !== $root_n && 0 !== strpos( $real_n, $root_n . DIRECTORY_SEPARATOR ) ) {
			return new \WP_Error( 'outside_root', __( 'Path is outside the WordPress installation.', 'emcp-tools' ) );
		}
		return $real;
	}

	/**
	 * Whether a path is write/delete-protected (wp-config.php / .htaccess).
	 *
	 * @param string $abs
	 * @return bool
	 */
	public static function is_protected( string $abs ): bool {
		$base      = strtolower( basename( $abs ) );
		$protected = array( 'wp-config.php', '.htaccess' );
		/** Filter the write/delete-protected basenames. */
		$protected = (array) apply_filters( 'emcp_tools_fs_protected_paths', $protected, $abs );
		return in_array( $base, array_map( 'strtolower', $protected ), true );
	}

	/**
	 * Pure: a sanitized, timestamped backup filename for a relative path.
	 *
	 * @param string $rel       Path relative to ABSPATH.
	 * @param string $timestamp e.g. gmdate('Ymd-His').
	 * @return string
	 */
	public static function backup_name( string $rel, string $timestamp ): string {
		$flat = str_replace( array( '/', '\\' ), '-', ltrim( $rel, '/\\' ) );
		$flat = preg_replace( '/[^A-Za-z0-9._-]/', '-', $flat );
		return $timestamp . '-' . $flat;
	}

	/**
	 * Pure: is $content valid UTF-8 text (vs binary)?
	 *
	 * @param string $content
	 * @return bool
	 */
	public static function is_utf8( string $content ): bool {
		if ( '' === $content ) {
			return true;
		}
		if ( false !== strpos( $content, "\0" ) ) {
			return false;
		}
		return (bool) preg_match( '//u', $content );
	}

	/**
	 * Pure: gate write/edit/delete on the edit_files capability + DISALLOW_FILE_EDIT.
	 *
	 * @param bool $can_edit_files
	 * @param bool $disallow_file_edit
	 * @return true|\WP_Error
	 */
	public static function check_writes( bool $can_edit_files, bool $disallow_file_edit ) {
		if ( $disallow_file_edit ) {
			return new \WP_Error( 'file_edit_disabled', __( 'File editing is disabled on this site (DISALLOW_FILE_EDIT).', 'emcp-tools' ) );
		}
		if ( ! $can_edit_files ) {
			return new \WP_Error( 'file_edit_disabled', __( 'You do not have permission to edit files.', 'emcp-tools' ) );
		}
		return true;
	}

	/**
	 * Live wrapper: gate writes from the current request context.
	 *
	 * @return true|\WP_Error
	 */
	public static function writes_allowed() {
		return self::check_writes(
			current_user_can( 'edit_files' ),
			defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT
		);
	}

	/**
	 * Path relative to ABSPATH (for display/log).
	 *
	 * @param string $abs
	 * @return string
	 */
	public static function to_relative( string $abs ): string {
		$root = rtrim( (string) realpath( ABSPATH ), '/\\' );
		$abs_n = $abs;
		if ( '' !== $root && 0 === strpos( $abs_n, $root ) ) {
			$abs_n = ltrim( substr( $abs_n, strlen( $root ) ), '/\\' );
		}
		return str_replace( '\\', '/', $abs_n );
	}

	/**
	 * Copy a file to a timestamped backup under uploads/emcp-fs-backups/.
	 * Returns the backup absolute path, '' if the source doesn't exist yet
	 * (a create, not an overwrite), or WP_Error on failure.
	 *
	 * @param string $abs
	 * @return string|\WP_Error
	 */
	public static function backup( string $abs ) {
		if ( ! is_file( $abs ) ) {
			return '';
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new \WP_Error( 'no_uploads', __( 'Uploads directory is unavailable for backups.', 'emcp-tools' ) );
		}
		$dir = trailingslashit( $uploads['basedir'] ) . self::BACKUP_DIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'backup_dir', __( 'Could not create the backup directory.', 'emcp-tools' ) );
		}
		// Block direct web access to backups.
		if ( ! is_file( $dir . '/.htaccess' ) ) {
			@file_put_contents( $dir . '/.htaccess', "Require all denied\n" ); // phpcs:ignore
			@file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore
		}
		$name = self::backup_name( self::to_relative( $abs ), gmdate( 'Ymd-His' ) );
		$dest = $dir . '/' . $name;
		if ( ! copy( $abs, $dest ) ) {
			return new \WP_Error( 'backup_failed', __( 'Could not back up the file before writing.', 'emcp-tools' ) );
		}
		return $dest;
	}

	/**
	 * Append a write/delete to the capped audit log option.
	 *
	 * @param string $op
	 * @param string $abs
	 * @return void
	 */
	public static function log( string $op, string $abs ): void {
		$log = get_option( self::AUDIT_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = array(
			'op'   => $op,
			'path' => self::to_relative( $abs ),
			'user' => get_current_user_id(),
			'time' => time(),
		);
		if ( count( $log ) > self::AUDIT_MAX ) {
			$log = array_slice( $log, -self::AUDIT_MAX );
		}
		update_option( self::AUDIT_OPTION, $log, false );
	}
}
