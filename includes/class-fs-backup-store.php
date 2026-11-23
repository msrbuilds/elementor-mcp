<?php
/**
 * Where the filesystem tools keep the copies they take before a write (#196).
 *
 * Until 3.22.1 the copies went to uploads/emcp-fs-backups/ with their original
 * names, protected only by an .htaccess file. nginx, Caddy and FrankenPHP
 * ignore .htaccess, so a copy of a PHP file could be downloaded, or run, and
 * nothing ever removed them.
 *
 * Now they go to wp-content/emcp-fs-backups-{20 hex}: the name comes from the
 * site's auth salt, so it cannot be guessed; deny files for Apache and IIS and
 * an empty index sit inside; and every copy ends in .bak, so no server runs it
 * as PHP. A marker file lets a folder be found again after a salt change.
 * Copies older than the retention setting (30 days by default) or beyond an
 * optional size cap are removed daily.
 *
 * History stores each copy's absolute path. resolve() finds a copy that moved
 * here from the old folder by its file name, so existing undo and diff keep
 * working.
 *
 * @package EMCP_Tools
 * @since   3.22.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * The filesystem backup folder, its copies and their retention.
 */
final class EMCP_Tools_FS_Backup_Store {

	const PREFIX     = 'emcp-fs-backups-';
	const LEGACY_DIR = 'emcp-fs-backups';
	const MARKER     = '.emcp-fs-backups';
	const SUFFIX     = '.bak';
	const OPTION     = 'emcp_tools_fs_backup_retention';
	const CRON       = 'emcp_tools_fs_backup_prune';
	const DEFAULTS   = array( 'days' => 30, 'max_mb' => 0 );
	const MAX_LIST   = 2000;

	/** @var string|null Folder override (tests). */
	private static $dir_override = null;

	/**
	 * Use a folder instead of the real one (tests), null to stop.
	 *
	 * @param string|null $dir Folder.
	 */
	public static function use_dir( ?string $dir ): void {
		self::$dir_override = $dir;
	}

	/** Daily retention sweep and the move from the old folder. */
	public static function init(): void {
		add_action( self::CRON, array( __CLASS__, 'daily' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_schedule' ) );
	}

	/** Schedule the daily sweep once, from wp-admin. */
	public static function maybe_schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/** The daily job: move old copies over, then apply retention. */
	public static function daily(): void {
		self::migrate_legacy();
		$r = self::retention();
		self::prune( (int) $r['days'], (int) $r['max_mb'] );
	}

	// ---- Location ------------------------------------------------------

	/**
	 * Pure: the folder name for a salt.
	 *
	 * @param string $salt Auth salt.
	 */
	public static function folder_name( string $salt ): string {
		return self::PREFIX . substr( hash_hmac( 'sha256', 'emcp-fs-backups', $salt ), 0, 20 );
	}

	/**
	 * The folder, created and protected on first use. '' when it cannot be made.
	 */
	public static function dir(): string {
		if ( null !== self::$dir_override ) {
			return self::protect( self::$dir_override ) ? self::$dir_override : '';
		}
		$content = rtrim( wp_normalize_path( WP_CONTENT_DIR ), '/' );
		$dir     = $content . '/' . self::folder_name( (string) wp_salt( 'auth' ) );
		if ( ! is_dir( $dir ) ) {
			// After a salt change, adopt the folder that carries our marker.
			foreach ( (array) glob( $content . '/' . self::PREFIX . '*', GLOB_ONLYDIR ) as $found ) {
				if ( is_file( $found . '/' . self::MARKER ) ) {
					$dir = wp_normalize_path( $found );
					break;
				}
			}
		}
		return self::protect( $dir ) ? $dir : '';
	}

	/**
	 * Create the folder and its deny files (every call, so a removed file comes back).
	 *
	 * @param string $dir Folder.
	 */
	private static function protect( string $dir ): bool {
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		if ( ! class_exists( 'EMCP_Tools_File_Quarantine' ) && defined( 'EMCP_TOOLS_DIR' ) ) {
			require_once EMCP_TOOLS_DIR . 'includes/class-file-quarantine.php'; // Its deny rules.
		}
		$files = array(
			self::MARKER  => "EMCP Tools filesystem backups.\n",
			'.htaccess'   => EMCP_Tools_File_Quarantine::htaccess_rules(),
			'web.config'  => EMCP_Tools_File_Quarantine::web_config_rules(),
			'index.php'   => "<?php\n// Silence is golden.\n",
			'index.html'  => '',
		);
		foreach ( $files as $name => $body ) {
			if ( ! is_file( $dir . '/' . $name ) ) {
				@file_put_contents( $dir . '/' . $name, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			}
		}
		return is_dir( $dir );
	}

	/** The old folder, '' when uploads are unavailable. */
	public static function legacy_dir(): string {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return '';
		}
		$up = wp_upload_dir( null, false );
		return empty( $up['basedir'] ) ? '' : rtrim( wp_normalize_path( (string) $up['basedir'] ), '/' ) . '/' . self::LEGACY_DIR;
	}

	// ---- Copies --------------------------------------------------------

	/**
	 * Copy a file before a write. '' when there is nothing to copy (a create).
	 *
	 * @param string $abs Absolute path of the file about to change.
	 * @return string|WP_Error Path of the copy.
	 */
	public static function store( string $abs ) {
		if ( ! is_file( $abs ) ) {
			return '';
		}
		$dir = self::dir();
		if ( '' === $dir ) {
			return new WP_Error( 'backup_dir', __( 'Could not create the filesystem backup folder.', 'emcp-tools' ) );
		}
		$base = EMCP_Tools_Filesystem_Guard::backup_name( EMCP_Tools_Filesystem_Guard::to_relative( $abs ), gmdate( 'Ymd-His' ) );
		$dest = $dir . '/' . $base . self::SUFFIX;
		for ( $n = 2; file_exists( $dest ); $n++ ) {
			$dest = $dir . '/' . $base . '-' . $n . self::SUFFIX;
		}
		if ( ! @copy( $abs, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return new WP_Error( 'backup_failed', __( 'Could not back up the file before writing.', 'emcp-tools' ) );
		}
		return $dest;
	}

	/**
	 * The copy a History entry points at, wherever it is now.
	 *
	 * @param string $path Absolute path History stored.
	 * @return string '' when the copy is gone.
	 */
	public static function resolve( string $path ): string {
		if ( '' === $path ) {
			return '';
		}
		if ( is_file( $path ) ) {
			return $path;
		}
		$name = basename( wp_normalize_path( $path ) );
		if ( ! self::is_copy_name( $name ) ) {
			return '';
		}
		$dir = self::dir();
		foreach ( array( $name, $name . self::SUFFIX ) as $candidate ) {
			if ( '' !== $dir && is_file( $dir . '/' . $candidate ) ) {
				return $dir . '/' . $candidate;
			}
		}
		return '';
	}

	/**
	 * Pure: whether a name looks like one of our copies (timestamp prefix).
	 *
	 * @param string $name File name.
	 */
	public static function is_copy_name( string $name ): bool {
		return 1 === preg_match( '/^\d{8}-\d{6}-[A-Za-z0-9._-]+$/', $name );
	}

	/**
	 * Move copies from uploads/emcp-fs-backups into the protected folder.
	 *
	 * @return int Files moved.
	 */
	public static function migrate_legacy(): int {
		$old = self::legacy_dir();
		if ( '' === $old || ! is_dir( $old ) ) {
			return 0;
		}
		$dir = self::dir();
		if ( '' === $dir ) {
			return 0;
		}
		$moved = 0;
		foreach ( (array) scandir( $old ) as $name ) {
			if ( ! is_string( $name ) || ! self::is_copy_name( $name ) || ! is_file( $old . '/' . $name ) ) {
				continue;
			}
			$dest = $dir . '/' . $name . self::SUFFIX;
			if ( file_exists( $dest ) || @rename( $old . '/' . $name, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( file_exists( $old . '/' . $name ) && file_exists( $dest ) ) {
					@unlink( $old . '/' . $name ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
				++$moved;
			}
		}
		// Remove the old folder once only our guard files are left.
		$left = array_diff( (array) scandir( $old ), array( '.', '..', '.htaccess', 'index.html', 'index.php' ) );
		if ( empty( $left ) ) {
			foreach ( array( '.htaccess', 'index.html', 'index.php' ) as $guard ) {
				@unlink( $old . '/' . $guard ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			@rmdir( $old ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		return $moved;
	}

	/**
	 * The copies, newest first.
	 *
	 * @return array<int, array{name: string, size: int, time: int}>
	 */
	public static function entries(): array {
		$dir = self::dir();
		if ( '' === $dir ) {
			return array();
		}
		$out = array();
		foreach ( (array) scandir( $dir ) as $name ) {
			if ( ! is_string( $name ) || ! self::is_copy_name( $name ) || ! is_file( $dir . '/' . $name ) ) {
				continue;
			}
			$out[] = array(
				'name' => $name,
				'size' => (int) filesize( $dir . '/' . $name ),
				'time' => self::time_of( $name, (int) filemtime( $dir . '/' . $name ) ),
			);
		}
		usort( $out, static fn( $a, $b ) => $b['time'] <=> $a['time'] ?: strcmp( $b['name'], $a['name'] ) );
		return $out;
	}

	/**
	 * Pure: a copy's time from its name, else the fallback.
	 *
	 * @param string $name     File name.
	 * @param int    $fallback Unix time.
	 */
	public static function time_of( string $name, int $fallback ): int {
		if ( 1 === preg_match( '/^(\d{8})-(\d{6})-/', $name, $m ) ) {
			$t = strtotime( $m[1] . 'T' . $m[2] . 'Z' );
			if ( false !== $t ) {
				return (int) $t;
			}
		}
		return $fallback;
	}

	/**
	 * Pure: which copies a retention rule removes. Oldest go first.
	 *
	 * @param array $entries From entries(), any order.
	 * @param int   $days    Keep this many days, 0 = no age limit.
	 * @param int   $max_mb  Keep at most this many MB, 0 = no size limit.
	 * @param int   $now     Unix time.
	 * @return string[] Names.
	 */
	public static function to_prune( array $entries, int $days, int $max_mb, int $now ): array {
		usort( $entries, static fn( $a, $b ) => $a['time'] <=> $b['time'] );
		$drop = array();
		$kept = array();
		foreach ( $entries as $e ) {
			if ( $days > 0 && $e['time'] < $now - $days * DAY_IN_SECONDS ) {
				$drop[] = $e['name'];
			} else {
				$kept[] = $e;
			}
		}
		if ( $max_mb > 0 ) {
			$total = array_sum( array_column( $kept, 'size' ) );
			foreach ( $kept as $e ) {
				if ( $total <= $max_mb * MB_IN_BYTES ) {
					break;
				}
				$drop[]  = $e['name'];
				$total  -= $e['size'];
			}
		}
		return $drop;
	}

	/**
	 * Delete copies by name.
	 *
	 * @param string[] $names Names from entries().
	 * @return array{removed: string[], bytes_freed: int}
	 */
	public static function delete( array $names ): array {
		$dir     = self::dir();
		$removed = array();
		$bytes   = 0;
		foreach ( array_unique( $names ) as $name ) {
			$name = (string) $name;
			if ( '' === $dir || ! self::is_copy_name( $name ) || ! is_file( $dir . '/' . $name ) ) {
				continue;
			}
			$size = (int) filesize( $dir . '/' . $name );
			if ( @unlink( $dir . '/' . $name ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$removed[] = $name;
				$bytes    += $size;
			}
		}
		return array( 'removed' => $removed, 'bytes_freed' => $bytes );
	}

	/**
	 * Apply a retention rule now.
	 *
	 * @param int $days   Days.
	 * @param int $max_mb MB.
	 * @return array{removed: string[], bytes_freed: int}
	 */
	public static function prune( int $days, int $max_mb ): array {
		$drop = self::to_prune( self::entries(), $days, $max_mb, time() );
		// Automatic retention never removes a copy an undo still needs; those go
		// when History's own retention drops the entry, or by an explicit purge.
		$used = array_flip( array_column( self::history_using( $drop ), 'file' ) );
		$drop = array_values(
			array_filter(
				$drop,
				static function ( $n ) use ( $used ) {
					$bare = str_ends_with( $n, self::SUFFIX ) ? substr( $n, 0, -strlen( self::SUFFIX ) ) : $n;
					return ! isset( $used[ $n ] ) && ! isset( $used[ $bare ] );
				}
			)
		);
		return self::delete( $drop );
	}

	/** The saved retention setting. */
	public static function retention(): array {
		$r = get_option( self::OPTION, array() );
		$r = is_array( $r ) ? $r : array();
		return array(
			'days'   => max( 0, min( 3650, (int) ( $r['days'] ?? self::DEFAULTS['days'] ) ) ),
			'max_mb' => max( 0, min( 1048576, (int) ( $r['max_mb'] ?? self::DEFAULTS['max_mb'] ) ) ),
		);
	}

	/**
	 * Save the retention setting.
	 *
	 * @param int $days   Days, 0 = keep regardless of age.
	 * @param int $max_mb MB, 0 = no cap.
	 */
	public static function save_retention( int $days, int $max_mb ): array {
		update_option( self::OPTION, array( 'days' => max( 0, min( 3650, $days ) ), 'max_mb' => max( 0, min( 1048576, $max_mb ) ) ), false );
		return self::retention();
	}

	/**
	 * History entries whose undo needs these copies.
	 *
	 * @param string[] $names Copy names.
	 * @return array<int, array{id: string, summary: string, file: string}>
	 */
	public static function history_using( array $names ): array {
		if ( empty( $names ) || ! class_exists( 'EMCP_Tools_Change_Log' ) ) {
			return array();
		}
		$want = array();
		foreach ( $names as $n ) {
			$n          = (string) $n;
			$want[ $n ] = true;
			if ( str_ends_with( $n, self::SUFFIX ) ) {
				$want[ substr( $n, 0, -strlen( self::SUFFIX ) ) ] = true;
			}
		}
		$out    = array();
		$cursor = null;
		for ( $page = 0; $page < 20; $page++ ) {
			$q = EMCP_Tools_Change_Log::query( array( 'domain' => 'filesystem', 'limit' => 200, 'cursor' => $cursor ) );
			foreach ( (array) ( $q['items'] ?? array() ) as $e ) {
				$rb = (array) ( $e['rollback'] ?? array() );
				$bp = basename( wp_normalize_path( (string) ( $rb['backup_path'] ?? '' ) ) );
				if ( '' !== $bp && isset( $want[ $bp ] ) && empty( $e['rolled_back'] ) ) {
					$out[] = array( 'id' => (string) $e['id'], 'summary' => (string) ( $e['summary'] ?? '' ), 'file' => $bp );
				}
			}
			$cursor = $q['next_cursor'] ?? null;
			if ( null === $cursor ) {
				break;
			}
		}
		return $out;
	}
}
