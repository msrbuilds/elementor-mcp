<?php
/**
 * Active-compromise audit.
 *
 * Looks for signs that a site is (or was) actively hacked: hidden "twin"
 * folders holding backdoors, back-dated PHP files, search-engine cloaking,
 * publicly downloadable backups, suspicious must-use plugins and orphaned
 * scheduled tasks.
 *
 * Every evaluate_*() is pure (unit-tested) and takes plain arrays. run()
 * gathers the live data: ONE bounded walk of the install (shared by the
 * twin-folder and back-dated-file checks), two same-host GETs for the
 * cloaking check, and up to MAX_BACKUP_HEADS same-host HEAD requests for the
 * backup check. Read-only: nothing is written or deleted.
 *
 * @package EMCP_Tools
 * @since   3.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.21.0
 */
class EMCP_Tools_Security_Compromise_Audit {

	const MAX_DIRS           = 60000;
	const MAX_FILES          = 300000;  // Directory entries visited (stat only, never read).
	const TIME_BUDGET        = 20;      // seconds.
	const MAX_LISTED         = 20;      // Offending items listed in a finding value.
	const MAX_TWIN_FILES     = 50;      // File names kept per twin folder.
	const TIMESTOMP_DAYS     = 30;      // ctime window for "recently dropped".
	const TIMESTOMP_GAP_DAYS = 7;       // mtime older than ctime by more than this.
	const FETCH_TIMEOUT      = 10;
	const HEAD_TIMEOUT       = 8;
	const MAX_BACKUP_HEADS   = 10;
	const MAX_BODY_BYTES     = 2097152; // Cap each cloaking response body.

	const BOT_UA     = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
	const VISITOR_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

	const PHP_EXT_RE = '/\.(php|phtml|php5|php6|php7|php8|inc)$/i';

	/** Folder names the walk never enters: dependency and version-control trees, which are huge and never served as site code. */
	const SKIPPED_DIRS = array( 'node_modules', '.git', '.svn', '.hg' );

	/** Path segments where legitimate libraries nest a same-named folder. */
	const LIBRARY_SEGMENTS = array( 'vendor', 'vendor_prefixed', 'node_modules', 'lib' );

	/** Regular-plugin slugs attackers borrow as must-use plugin file names. */
	const IMITATED_SLUGS = array(
		'contact-form-7', 'wordfence', 'elementor', 'elementor-pro', 'woocommerce',
		'wp-rocket', 'wordpress-seo', 'yoast', 'yoast-seo', 'akismet', 'jetpack',
		'litespeed-cache', 'wp-super-cache', 'w3-total-cache', 'wp-fastest-cache',
		'all-in-one-seo-pack', 'wpforms', 'wpforms-lite', 'updraftplus',
		'really-simple-ssl', 'classic-editor', 'hello-dolly',
	);

	/** Backup folders, relative to WP_CONTENT_DIR. Trailing * = prefix match. */
	const BACKUP_DIRS = array(
		'ai1wm-backups', 'updraft', 'backups', 'backup', 'backupbuddy_backups',
		'wpvivid_backup', 'backups-dup-lite', 'uploads/backwpup*', 'uploads/backup*',
		'emcp-backups*', 'emcp-fs-backups*',
	);

	const BACKUP_EXT_RE      = '/\.(wpress|sql|sql\.gz|gz|zip|tar|tar\.gz|tgz|bak|tmp)$/i';
	const ROOT_BACKUP_EXT_RE = '/\.(wpress|sql|sql\.gz|gz|zip|tar|tar\.gz|tgz|bak)$/i';

	const SUSPICIOUS_CRON_RE = '/inject|shell|backdoor|cache_index|self_?heal|(^|_)heal(_|$)|guard|w2s|(^|_)fm_|wp_cache_[a-z]{3,}|(?=[0-9a-f]*[0-9])[0-9a-f]{8,}/i';

	// ---- Pure evaluators ---------------------------------------------------

	/**
	 * Pure: hidden twin folders (a directory named exactly like its parent).
	 *
	 * @param array       $twins            Each: array( 'path' => ABSPATH-relative dir, 'files' => string[] file names in it ).
	 * @param string|null $truncated_reason Why the walk stopped early, or null when it covered everything.
	 * @return array Finding
	 */
	public function evaluate_twin_folders( array $twins, ?string $truncated_reason = null ): array {
		$critical = array();
		$warning  = array();

		foreach ( $twins as $twin ) {
			$path  = trim( str_replace( '\\', '/', (string) ( $twin['path'] ?? '' ) ), '/' );
			$files = array_map( 'strval', (array) ( $twin['files'] ?? array() ) );
			$name  = strtolower( basename( $path ) );

			$has_htaccess = false;
			$odd_php      = false;
			foreach ( $files as $file ) {
				$lower = strtolower( $file );
				if ( '.htaccess' === $lower ) {
					$has_htaccess = true;
				} elseif ( preg_match( self::PHP_EXT_RE, $lower ) && pathinfo( $lower, PATHINFO_FILENAME ) !== $name ) {
					$odd_php = true;
				}
			}

			if ( $has_htaccess ) {
				$critical[] = $path;
			} elseif ( $odd_php && ! $this->in_library_path( $path ) ) {
				$warning[] = $path;
			}
		}

		if ( empty( $critical ) && empty( $warning ) ) {
			if ( null !== $truncated_reason ) {
				return EMCP_Tools_Security_Finding::make( 'compromise_twin_folder', 'compromise', 'Hidden twin folders', 'info', array( 'count' => 0, 'paths' => array(), 'truncated_reason' => $truncated_reason ), self::partial_message( 'folders named like their parent folder' ), self::PARTIAL_RECOMMENDATION );
			}
			return EMCP_Tools_Security_Finding::make( 'compromise_twin_folder', 'compromise', 'Hidden twin folders', 'pass', array( 'count' => 0, 'paths' => array() ), 'No suspicious folders named like their parent folder were found.' );
		}

		$flagged = array_merge( $critical, $warning );
		$value   = array(
			'count'         => count( $flagged ),
			'with_htaccess' => count( $critical ),
			'paths'         => array_slice( $flagged, 0, self::MAX_LISTED ),
		);
		if ( null !== $truncated_reason ) {
			$value['truncated_reason'] = $truncated_reason;
		}

		if ( ! empty( $critical ) ) {
			return EMCP_Tools_Security_Finding::make(
				'compromise_twin_folder', 'compromise', 'Hidden twin folders', 'critical', $value,
				sprintf( '%d folder(s) named exactly like their parent folder contain their own .htaccess file, the layout attackers use to hide backdoors (e.g. plugins/foo/prompts/prompts/).', count( $critical ) ),
				'Inspect each listed folder: a twin folder with its own .htaccess plus index.php or random-named PHP/decoy files is a backdoor drop. Delete it, then compare the parent plugin/theme against a fresh copy and rotate all passwords and keys.'
			);
		}
		return EMCP_Tools_Security_Finding::make(
			'compromise_twin_folder', 'compromise', 'Hidden twin folders', 'warning', $value,
			sprintf( '%d folder(s) named exactly like their parent folder contain PHP files that do not match the folder name.', count( $warning ) ),
			'Check the listed folders against a fresh copy of the plugin/theme they sit in; delete any PHP file the original does not ship.'
		);
	}

	/**
	 * Pure: which PHP files look back-dated? ctime (inode change time) cannot be
	 * set with touch(), so a recent ctime with a much older mtime means the
	 * modification time was faked or preserved on copy. (On Windows ctime is the
	 * creation time, which gives the same signal for copied files.)
	 *
	 * @param array $files Each: array( 'path', 'mtime', 'ctime' ).
	 * @param int   $now   Unix seconds.
	 * @return array The flagged subset, same shape.
	 */
	public function find_timestomped( array $files, int $now ): array {
		$cutoff  = $now - self::TIMESTOMP_DAYS * 86400;
		$gap     = self::TIMESTOMP_GAP_DAYS * 86400;
		$flagged = array();
		foreach ( $files as $file ) {
			$ctime = (int) ( $file['ctime'] ?? 0 );
			$mtime = (int) ( $file['mtime'] ?? 0 );
			if ( $ctime >= $cutoff && ( $ctime - $mtime ) > $gap ) {
				$flagged[] = $file;
			}
		}
		return $flagged;
	}

	/**
	 * Pure: back-dated PHP files.
	 *
	 * @param array    $files              Each: array( 'path', 'mtime', 'ctime' ), ABSPATH-relative paths.
	 * @param int      $now                Unix seconds.
	 * @param string[]    $sensitive_prefixes Relative dirs where a hit is critical (WP root is always sensitive).
	 * @param string|null $truncated_reason   Why the walk stopped early, or null when it covered everything.
	 * @return array Finding
	 */
	public function evaluate_timestomp( array $files, int $now, array $sensitive_prefixes = array( 'wp-content/mu-plugins', 'wp-includes' ), ?string $truncated_reason = null ): array {
		$flagged = $this->find_timestomped( $files, $now );
		if ( empty( $flagged ) && null !== $truncated_reason ) {
			return EMCP_Tools_Security_Finding::make( 'compromise_timestomp', 'compromise', 'Back-dated files', 'info', array( 'count' => 0, 'files' => array(), 'truncated_reason' => $truncated_reason ), self::partial_message( 'back-dated PHP files' ), self::PARTIAL_RECOMMENDATION );
		}
		if ( empty( $flagged ) ) {
			return EMCP_Tools_Security_Finding::make( 'compromise_timestomp', 'compromise', 'Back-dated files', 'pass', array( 'count' => 0, 'files' => array() ), sprintf( 'No PHP file changed in the last %d days carries a much older modification date.', self::TIMESTOMP_DAYS ) );
		}

		$sensitive = 0;
		$listed    = array();
		foreach ( $flagged as $file ) {
			$path = ltrim( str_replace( '\\', '/', (string) $file['path'] ), '/' );
			if ( $this->is_sensitive_path( $path, $sensitive_prefixes ) ) {
				$sensitive++;
			}
			if ( count( $listed ) < self::MAX_LISTED ) {
				$listed[] = array(
					'path'  => $path,
					'mtime' => gmdate( 'Y-m-d H:i', (int) $file['mtime'] ),
					'ctime' => gmdate( 'Y-m-d H:i', (int) $file['ctime'] ),
				);
			}
		}

		$value  = array( 'count' => count( $flagged ), 'in_core_or_mu' => $sensitive, 'files' => $listed );
		if ( null !== $truncated_reason ) {
			$value['truncated_reason'] = $truncated_reason;
		}
		$how    = 'Compare each file against a fresh copy of WordPress core or the plugin/theme it belongs to. Restoring a backup, cp -p, rsync -a or an FTP client that preserves timestamps can also cause this, so only files that differ from the original matter.';
		$status = $sensitive > 0 ? 'critical' : 'warning';
		$msg    = $sensitive > 0
			? sprintf( '%d PHP file(s) in the WordPress root, wp-includes or mu-plugins were written in the last %d days but carry a modification date more than %d days older. Attackers back-date dropped files to hide them.', $sensitive, self::TIMESTOMP_DAYS, self::TIMESTOMP_GAP_DAYS )
			: sprintf( '%d PHP file(s) were written in the last %d days but carry a modification date more than %d days older.', count( $flagged ), self::TIMESTOMP_DAYS, self::TIMESTOMP_GAP_DAYS );

		return EMCP_Tools_Security_Finding::make( 'compromise_timestomp', 'compromise', 'Back-dated files', $status, $value, $msg, $how . ' Delete anything that is not part of the original.' );
	}

	/**
	 * Pure: normalize a <title> for comparison.
	 *
	 * @param string $title
	 * @return string
	 */
	public function normalize_title( string $title ): string {
		$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title = strip_tags( $title );
		$clean = preg_replace( '/\s+/u', ' ', $title );
		$title = trim( null === $clean ? (string) preg_replace( '/\s+/', ' ', $title ) : $clean ); // Non-UTF-8 page: byte-wise fallback.
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
	}

	/**
	 * Pure: search-engine cloaking (Googlebot sees a different page).
	 *
	 * @param array $visitor array( 'ok', 'status', 'title', 'length', 'error' ) for the browser fetch.
	 * @param array $bot     Same shape for the Googlebot fetch.
	 * @return array Finding
	 */
	public function evaluate_cloaking( array $visitor, array $bot ): array {
		if ( empty( $visitor['ok'] ) || empty( $bot['ok'] ) ) {
			$errors = array_filter(
				array(
					'visitor' => empty( $visitor['ok'] ) ? (string) ( $visitor['error'] ?? 'request failed' ) : null,
					'bot'     => empty( $bot['ok'] ) ? (string) ( $bot['error'] ?? 'request failed' ) : null,
				)
			);
			return EMCP_Tools_Security_Finding::make( 'compromise_cloaking', 'compromise', 'Search-engine cloaking', 'info', $errors, 'The homepage could not be fetched both as a visitor and as Googlebot, so cloaking could not be verified.', 'Re-run the scan; if loopback requests are blocked, compare the homepage with Google Search Console URL Inspection (live test).' );
		}

		$v_status = (int) ( $visitor['status'] ?? 0 );
		$b_status = (int) ( $bot['status'] ?? 0 );
		$v_title  = $this->normalize_title( (string) ( $visitor['title'] ?? '' ) );
		$b_title  = $this->normalize_title( (string) ( $bot['title'] ?? '' ) );
		$v_len    = max( 1, (int) ( $visitor['length'] ?? 0 ) );
		$b_len    = max( 1, (int) ( $bot['length'] ?? 0 ) );
		$ratio    = $b_len / $v_len;
		$value    = array(
			'visitor' => array( 'status' => $v_status, 'title' => $v_title, 'length' => (int) ( $visitor['length'] ?? 0 ) ),
			'bot'     => array( 'status' => $b_status, 'title' => $b_title, 'length' => (int) ( $bot['length'] ?? 0 ) ),
		);

		$v_ok = 2 === intdiv( $v_status, 100 );
		$b_ok = 2 === intdiv( $b_status, 100 );

		// Firewalls (Cloudflare, Wordfence, ...) block fake Googlebots that do not
		// come from Google's IP ranges. That is a refusal, not cloaking.
		if ( $v_ok && in_array( $b_status, array( 403, 429, 503 ), true ) ) {
			return EMCP_Tools_Security_Finding::make( 'compromise_cloaking', 'compromise', 'Search-engine cloaking', 'warning', $value, sprintf( 'The Googlebot request was refused (HTTP %d) while visitors get HTTP %d, most likely a firewall rule against fake Googlebots, so cloaking could not be verified.', $b_status, $v_status ), 'Confirm with Google Search Console URL Inspection (live test) that Google sees your real homepage title and content.' );
		}

		$reasons = array();
		if ( $v_title !== $b_title ) {
			$reasons[] = 'title differs';
		}
		if ( $v_ok !== $b_ok ) {
			$reasons[] = 'status differs';
		}
		if ( $ratio < 0.5 || $ratio > 2 ) {
			$reasons[] = 'page size differs';
		}

		if ( empty( $reasons ) ) {
			return EMCP_Tools_Security_Finding::make( 'compromise_cloaking', 'compromise', 'Search-engine cloaking', 'pass', $value, 'Googlebot and visitors receive the same homepage.' );
		}
		$value['reasons'] = $reasons;
		return EMCP_Tools_Security_Finding::make(
			'compromise_cloaking', 'compromise', 'Search-engine cloaking', 'critical', $value,
			sprintf( 'Googlebot is served a different homepage than visitors (%s): the signature of SEO-spam cloaking malware.', implode( ', ', $reasons ) ),
			'Compare the root index.php, .htaccess, wp-config.php and every mu-plugin against fresh copies; look for code that runs before WordPress loads and checks the user agent. Then request re-indexing in Google Search Console.'
		);
	}

	/**
	 * Pure: backup archives reachable over the web.
	 *
	 * A file that was not checked (status null) counts as blocked when every
	 * checked file in the same directory was blocked, because the deny rule that
	 * protects a backup folder covers the whole folder. It stays unknown when no
	 * file in its directory was checked, or when a check there failed.
	 *
	 * @param array $candidates Each: array( 'path', 'size', 'status' ); status is the HEAD response code,
	 *                          0 when the request failed, null when the file was not checked.
	 * @return array Finding
	 */
	public function evaluate_public_backups( array $candidates ): array {
		if ( empty( $candidates ) ) {
			return EMCP_Tools_Security_Finding::make( 'compromise_public_backup', 'compromise', 'Publicly downloadable backups', 'pass', array( 'count' => 0, 'files' => array() ), 'No backup archives were found in the usual backup folders or the site root.' );
		}

		$blocked_codes = array( 401, 403, 404, 410 );

		// Per directory: true when at least one file was checked and every checked file was blocked.
		$dir_blocked = array();
		foreach ( $candidates as $c ) {
			if ( null === ( $c['status'] ?? null ) ) {
				continue;
			}
			$dir                 = $this->candidate_dir( (string) ( $c['path'] ?? '' ) );
			$dir_blocked[ $dir ] = ( $dir_blocked[ $dir ] ?? true ) && in_array( (int) $c['status'], $blocked_codes, true );
		}

		$public   = 0;
		$unknown  = 0;
		$inferred = 0;
		$listed   = array();
		foreach ( $candidates as $c ) {
			$raw    = $c['status'] ?? null;
			$status = (int) $raw;
			if ( 200 === $status || 206 === $status ) {
				$public++;
			} elseif ( null === $raw && ! empty( $dir_blocked[ $this->candidate_dir( (string) ( $c['path'] ?? '' ) ) ] ) ) {
				$inferred++;
			} elseif ( ! in_array( $status, $blocked_codes, true ) ) {
				$unknown++;
			}
			if ( count( $listed ) < self::MAX_LISTED ) {
				$listed[] = array( 'path' => (string) ( $c['path'] ?? '' ), 'size' => (int) ( $c['size'] ?? 0 ), 'status' => $status ?: null );
			}
		}
		$value = array( 'count' => count( $candidates ), 'public' => $public, 'inferred_blocked' => $inferred, 'files' => $listed );

		if ( $public > 0 ) {
			return EMCP_Tools_Security_Finding::make(
				'compromise_public_backup', 'compromise', 'Publicly downloadable backups', 'critical', $value,
				sprintf( '%d backup file(s) can be downloaded by anyone (HTTP 200). A site backup can expose a full copy of the database and the wp-config.php secrets.', $public ),
				'Move backups off the web root (or to remote storage) and delete the public copies now; block the backup folder with a deny rule. Assume the database leaked: rotate admin passwords, database password and the wp-config.php salts.'
			);
		}
		if ( $unknown > 0 ) {
			return EMCP_Tools_Security_Finding::make(
				'compromise_public_backup', 'compromise', 'Publicly downloadable backups', 'warning', $value,
				sprintf( '%d backup file(s) sit inside the web root and their public reachability could not be confirmed.', $unknown ),
				'Open each listed file URL in a private browser window; if it downloads, move the backups off the web root and block the folder.'
			);
		}
		return EMCP_Tools_Security_Finding::make( 'compromise_public_backup', 'compromise', 'Publicly downloadable backups', 'pass', $value, $inferred > 0
			? sprintf( 'Backup files exist but are not downloadable over the web (401/403/404). %d unchecked file(s) sit in folders where every checked file was blocked.', $inferred )
			: 'Backup files exist but are not downloadable over the web (401/403/404).' );
	}

	/**
	 * Pure: must-use plugins (always loaded, never shown on the Plugins screen).
	 *
	 * @param array    $files         Each: array( 'name', 'size', 'mtime' ) for top-level mu-plugins files.
	 * @param string[] $active_slugs  Active regular-plugin folder slugs.
	 * @param string[] $backdated     File names (basename) flagged as back-dated.
	 * @return array Finding
	 */
	public function evaluate_mu_plugins( array $files, array $active_slugs = array(), array $backdated = array() ): array {
		if ( empty( $files ) ) {
			return EMCP_Tools_Security_Finding::make( 'compromise_mu_plugins', 'compromise', 'Must-use plugins', 'pass', array( 'count' => 0, 'files' => array() ), 'No must-use plugins are installed.' );
		}

		$active    = array_map( 'strtolower', $active_slugs );
		$backdated = array_map( 'strtolower', $backdated );
		$suspect   = array();
		$listed    = array();

		foreach ( $files as $file ) {
			$name = (string) ( $file['name'] ?? '' );
			$slug = strtolower( (string) preg_replace( '/\.php$/i', '', $name ) );

			$reason = null;
			if ( in_array( $slug, self::IMITATED_SLUGS, true ) ) {
				$reason = 'named like a regular plugin';
			} elseif ( '-cache' === substr( $slug, -6 ) && ! in_array( $slug, $active, true ) ) {
				$reason = 'named like a caching plugin that is not active';
			} elseif ( in_array( strtolower( $name ), $backdated, true ) ) {
				$reason = 'back-dated';
			}
			if ( null !== $reason ) {
				$suspect[] = array( 'name' => $name, 'reason' => $reason );
			}
			if ( count( $listed ) < self::MAX_LISTED ) {
				$listed[] = array(
					'name'  => $name,
					'size'  => (int) ( $file['size'] ?? 0 ),
					'mtime' => gmdate( 'Y-m-d H:i', (int) ( $file['mtime'] ?? 0 ) ),
				);
			}
		}

		$value = array( 'count' => count( $files ), 'files' => $listed );
		if ( ! empty( $suspect ) ) {
			$value['suspicious'] = array_slice( $suspect, 0, self::MAX_LISTED );
			return EMCP_Tools_Security_Finding::make(
				'compromise_mu_plugins', 'compromise', 'Must-use plugins', 'warning', $value,
				sprintf( '%d must-use plugin(s) look suspicious (named like a regular plugin, or back-dated). Real plugins never install themselves under their own name in mu-plugins, and mu-plugins never appear on the Plugins screen.', count( $suspect ) ),
				'Open each suspicious file in wp-content/mu-plugins. If you or your host did not put it there, delete it and check the rest of the site for the same code.'
			);
		}
		return EMCP_Tools_Security_Finding::make( 'compromise_mu_plugins', 'compromise', 'Must-use plugins', 'info', $value, sprintf( '%d must-use plugin(s) installed; none looks suspicious. Confirm you recognise each one, because they run on every request and are hidden from the Plugins screen.', count( $files ) ) );
	}

	/**
	 * Pure: scheduled tasks with no callback attached.
	 *
	 * @param array $hooks Each: array( 'hook', 'next' ); next is Unix seconds of the next run.
	 * @return array Finding
	 */
	public function evaluate_orphan_cron( array $hooks ): array {
		if ( empty( $hooks ) ) {
			return EMCP_Tools_Security_Finding::make( 'compromise_orphan_cron', 'compromise', 'Orphaned scheduled tasks', 'pass', array( 'count' => 0, 'hooks' => array() ), 'Every scheduled task has a callback attached.' );
		}

		$suspect = array();
		$listed  = array();
		foreach ( $hooks as $h ) {
			$hook = (string) ( $h['hook'] ?? '' );
			$row  = array( 'hook' => $hook, 'next' => gmdate( 'Y-m-d H:i', (int) ( $h['next'] ?? 0 ) ) );
			if ( preg_match( self::SUSPICIOUS_CRON_RE, $hook ) ) {
				$suspect[] = $row;
			} elseif ( count( $listed ) < self::MAX_LISTED ) {
				$listed[] = $row;
			}
		}

		if ( ! empty( $suspect ) ) {
			$value = array( 'count' => count( $hooks ), 'suspicious' => array_slice( $suspect, 0, self::MAX_LISTED ), 'hooks' => $listed );
			return EMCP_Tools_Security_Finding::make(
				'compromise_orphan_cron', 'compromise', 'Orphaned scheduled tasks', 'warning', $value,
				sprintf( '%d scheduled task(s) with no callback have names typical of re-infection jobs (e.g. fm_cache_index_inject_cron).', count( $suspect ) ),
				'Find the code that registers each listed hook (search the whole install, including mu-plugins and the database), remove it, then unschedule the hook (wp cron event delete <hook>).'
			);
		}
		$value = array( 'count' => count( $hooks ), 'hooks' => $listed );
		return EMCP_Tools_Security_Finding::make( 'compromise_orphan_cron', 'compromise', 'Orphaned scheduled tasks', 'info', $value, sprintf( '%d scheduled task(s) have no callback at scan time. Usually these are leftovers from removed plugins, or plugins that only attach callbacks during cron runs.', count( $hooks ) ) );
	}

	// ---- Live gather -------------------------------------------------------

	/**
	 * Live gather + evaluate.
	 *
	 * @return array { findings: Finding[], stats: array }
	 */
	public function run( int $max_seconds = self::TIME_BUDGET ): array {
		$now      = time();
		$deadline = microtime( true ) + max( 1, $max_seconds );
		$walk     = $this->walk_install( $now, $deadline );

		$mu_dir    = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : rtrim( WP_CONTENT_DIR, '/\\' ) . '/mu-plugins';
		$mu_real   = $this->realpath_slashed( $mu_dir );
		$sensitive = array_values( array_unique( array( 'wp-content/mu-plugins', 'wp-includes', $this->rel_path( '' !== $mu_real ? $mu_real : $mu_dir ) ) ) );
		$backdated = array();
		foreach ( $this->find_timestomped( $walk['php'], $now ) as $file ) {
			$backdated[] = basename( (string) $file['path'] );
		}

		$visitor = $this->fetch_page( self::VISITOR_UA, $deadline );
		$bot     = $this->fetch_page( self::BOT_UA, $deadline );

		$backups = $this->gather_backups( $deadline );

		$findings = array(
			$this->evaluate_twin_folders( $walk['twins'], $walk['truncated_reason'] ),
			$this->evaluate_timestomp( $walk['php'], $now, $sensitive, $walk['truncated_reason'] ),
			$this->evaluate_cloaking( $visitor, $bot ),
			$this->evaluate_public_backups( $backups['candidates'] ),
			$this->evaluate_mu_plugins( $this->gather_mu_plugins(), $this->active_plugin_slugs(), $backdated ),
			$this->evaluate_orphan_cron( $this->gather_orphan_cron() ),
		);

		return array(
			'findings' => $findings,
			'stats'    => array(
				'folders_walked'   => $walk['dirs'],
				'files_seen'       => $walk['files'],
				'truncated'        => null !== $walk['truncated_reason'],
				'truncated_reason' => $walk['truncated_reason'],
				'cloaking_fetch'   => array(
					'ok'    => ! empty( $visitor['ok'] ) && ! empty( $bot['ok'] ),
					'error' => ! empty( $visitor['error'] ) ? $visitor['error'] : ( $bot['error'] ?? null ),
				),
				'backups_checked'  => $backups['checked'],
			),
		);
	}

	/**
	 * One bounded walk of the install. Collects twin folders (with their file
	 * names) and the stat of recently changed PHP files. Never reads file
	 * contents, never follows symlinks, skips unreadable folders silently.
	 * Uploads is walked last so a huge media library cannot starve the code dirs.
	 *
	 * @param int   $now
	 * @param float $deadline microtime() by which the whole audit must finish.
	 * @return array{ twins: array, php: array, dirs: int, files: int, truncated_reason: ?string }
	 */
	private function walk_install( int $now, float $deadline ): array {
		$abspath = $this->realpath_slashed( ABSPATH );
		$content = $this->realpath_slashed( WP_CONTENT_DIR );
		$uploads = '' !== $content ? $content . '/uploads' : '';

		$roots = array();
		if ( '' !== $abspath ) {
			$roots[] = $abspath;
		}
		if ( '' !== $content && ! $this->is_within( $content, $abspath ) ) {
			$roots[] = $content; // Content dir lives outside ABSPATH (e.g. Bedrock).
		}
		if ( '' !== $uploads && is_dir( $uploads ) ) {
			$roots[] = $uploads;
		}
		$skip = array_filter( array( '' !== $content ? $content . '/cache' : '', $uploads ) );

		$cutoff = $now - self::TIMESTOMP_DAYS * 86400;
		// The walk keeps a share of the budget back for the page fetches and backup checks.
		$walk_until = min( microtime( true ) + self::TIME_BUDGET, $deadline - max( 0.0, ( $deadline - microtime( true ) ) * 0.25 ) );
		$out     = array( 'twins' => array(), 'php' => array(), 'dirs' => 0, 'files' => 0, 'truncated_reason' => null );

		foreach ( $roots as $root ) {
			$stack = array( $root );
			while ( ! empty( $stack ) ) {
				$reason = $this->walk_stop_reason( $out['dirs'], $out['files'], $walk_until );
				if ( null !== $reason ) {
					$out['truncated_reason'] = $reason;
					return $out;
				}
				$dir = array_pop( $stack );
				if ( ! is_readable( $dir ) ) {
					continue;
				}
				$entries = @scandir( $dir );
				if ( false === $entries ) {
					continue;
				}
				$out['dirs']++;
				$names = array();
				foreach ( $entries as $name ) {
					if ( '.' === $name || '..' === $name ) {
						continue;
					}
					$full = $dir . '/' . $name;
					if ( is_link( $full ) ) {
						continue;
					}
					if ( is_dir( $full ) ) {
						if ( ! in_array( $full, $skip, true ) && ! self::is_skipped_dir( $name ) ) {
							$stack[] = $full;
						}
						continue;
					}
					$out['files']++;
					if ( count( $names ) < self::MAX_TWIN_FILES ) {
						$names[] = $name;
					}
					if ( preg_match( self::PHP_EXT_RE, $name ) ) {
						$st = @stat( $full );
						if ( is_array( $st ) && (int) $st['ctime'] >= $cutoff ) {
							$out['php'][] = array( 'path' => $this->rel_path( $full ), 'mtime' => (int) $st['mtime'], 'ctime' => (int) $st['ctime'] );
						}
					}
				}
				if ( $dir !== $root && strtolower( basename( $dir ) ) === strtolower( basename( dirname( $dir ) ) ) ) {
					$out['twins'][] = array( 'path' => $this->rel_path( $dir ), 'files' => $names );
				}
			}
		}
		return $out;
	}

	private function walk_stop_reason( int $dirs, int $files, float $until ): ?string {
		if ( $dirs >= self::MAX_DIRS ) {
			return 'max_dirs';
		}
		if ( $files >= self::MAX_FILES ) {
			return 'max_files';
		}
		if ( microtime( true ) >= $until ) {
			return 'time_budget';
		}
		return null;
	}

	/** @return array{ ok: bool, status: int, title: string, length: int, error: ?string } */
	private function fetch_page( string $user_agent, float $deadline ): array {
		$timeout = self::timeout_for( (int) floor( $deadline - microtime( true ) ), self::FETCH_TIMEOUT );
		if ( $timeout <= 0 ) {
			return array( 'ok' => false, 'status' => 0, 'title' => '', 'length' => 0, 'error' => 'skipped: time budget used up' );
		}
		$url = add_query_arg( 'emcp_scan', wp_rand( 100000, 999999 ), home_url( '/' ) );
		$res = wp_remote_get(
			$url,
			array(
				'timeout'             => $timeout,
				'redirection'         => 2,
				'user-agent'          => $user_agent,
				'limit_response_size' => self::MAX_BODY_BYTES,
				'headers'             => array( 'Cache-Control' => 'no-cache' ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'status' => 0, 'title' => '', 'length' => 0, 'error' => $res->get_error_message() );
		}
		$body  = (string) wp_remote_retrieve_body( $res );
		$title = preg_match( '/<title[^>]*>(.*?)<\/title>/is', $body, $m ) ? $m[1] : '';
		return array(
			'ok'     => true,
			'status' => (int) wp_remote_retrieve_response_code( $res ),
			'title'  => $title,
			'length' => strlen( $body ),
			'error'  => null,
		);
	}

	/** @return array{ candidates: array, checked: int } */
	private function gather_backups( float $deadline ): array {
		$content = $this->realpath_slashed( WP_CONTENT_DIR );
		$files   = array();

		if ( '' !== $content ) {
			foreach ( self::BACKUP_DIRS as $pattern ) {
				$dirs = array();
				if ( '*' === substr( $pattern, -1 ) ) {
					$parent = $content . '/' . dirname( $pattern );
					$prefix = strtolower( rtrim( basename( $pattern ), '*' ) );
					$entries = ( is_dir( $parent ) && is_readable( $parent ) ) ? @scandir( $parent ) : false;
					if ( false !== $entries ) {
						foreach ( $entries as $name ) {
							if ( '.' !== $name && '..' !== $name && 0 === strpos( strtolower( (string) $name ), $prefix ) && is_dir( $parent . '/' . $name ) ) {
								$dirs[] = $parent . '/' . $name;
							}
						}
					}
				} else {
					$dirs[] = $content . '/' . $pattern;
				}
				foreach ( $dirs as $dir ) {
					$files = array_merge( $files, $this->list_files( $dir, self::BACKUP_EXT_RE, content_url( substr( $dir, strlen( $content ) ) ) ) );
				}
			}
		}
		$abspath = $this->realpath_slashed( ABSPATH );
		if ( '' !== $abspath ) {
			$files = array_merge( $files, $this->list_files( $abspath, self::ROOT_BACKUP_EXT_RE, site_url( '/' ) ) );
		}

		// Check the biggest files first: those are the full-site archives.
		usort(
			$files,
			function ( $a, $b ) {
				return $b['size'] <=> $a['size'];
			}
		);

		$to_check = array_flip( array_slice( $this->head_order( $files ), 0, self::MAX_BACKUP_HEADS ) );

		$candidates = array();
		$checked    = 0;
		foreach ( $files as $i => $file ) {
			$status  = null; // Not checked.
			$timeout = isset( $to_check[ $i ] ) ? self::timeout_for( (int) floor( $deadline - microtime( true ) ), self::HEAD_TIMEOUT ) : 0;
			if ( $timeout > 0 ) {
				$res    = wp_remote_head( $file['url'], array( 'timeout' => $timeout, 'redirection' => 0 ) );
				$status = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
				$checked++;
			}
			$candidates[] = array( 'path' => $file['path'], 'size' => $file['size'], 'status' => $status );
		}
		return array( 'candidates' => $candidates, 'checked' => $checked );
	}

	/**
	 * Pure: the order in which to HEAD-check backup files. One file per
	 * directory comes first (round-robin), so a capped number of requests
	 * covers every backup folder before a second file of the same folder.
	 * Within a directory the input order (biggest first) is kept.
	 *
	 * @param array $files Each has a 'path'.
	 * @return int[] Indexes into $files.
	 */
	public function head_order( array $files ): array {
		$by_dir = array();
		foreach ( $files as $i => $file ) {
			$by_dir[ $this->candidate_dir( (string) ( $file['path'] ?? '' ) ) ][] = $i;
		}
		$order = array();
		while ( ! empty( $by_dir ) ) {
			foreach ( $by_dir as $dir => $indexes ) {
				$order[] = array_shift( $by_dir[ $dir ] );
				if ( empty( $by_dir[ $dir ] ) ) {
					unset( $by_dir[ $dir ] );
				}
			}
		}
		return $order;
	}

	private function candidate_dir( string $path ): string {
		$path = str_replace( '\\', '/', $path );
		$pos  = strrpos( $path, '/' );
		return false === $pos ? '' : substr( $path, 0, $pos );
	}

	/**
	 * Top-level files in $dir matching $ext_re.
	 *
	 * @return array Each: array( 'path', 'size', 'url' ).
	 */
	private function list_files( string $dir, string $ext_re, string $base_url ): array {
		if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) {
			return array();
		}
		$entries = @scandir( $dir );
		if ( false === $entries ) {
			return array();
		}
		$out = array();
		foreach ( $entries as $name ) {
			$full = $dir . '/' . $name;
			if ( '.' === $name || '..' === $name || ! preg_match( $ext_re, $name ) || ! is_file( $full ) ) {
				continue;
			}
			$st    = @stat( $full );
			$out[] = array(
				'path' => $this->rel_path( $full ),
				'size' => is_array( $st ) ? (int) $st['size'] : 0,
				'url'  => rtrim( $base_url, '/' ) . '/' . rawurlencode( $name ),
			);
		}
		return $out;
	}

	/** @return array Each: array( 'name', 'size', 'mtime' ). */
	private function gather_mu_plugins(): array {
		$dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : rtrim( WP_CONTENT_DIR, '/\\' ) . '/mu-plugins';
		$dir = rtrim( str_replace( '\\', '/', $dir ), '/' );
		if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) {
			return array();
		}
		$entries = @scandir( $dir );
		if ( false === $entries ) {
			return array();
		}
		$out = array();
		foreach ( $entries as $name ) {
			$full = $dir . '/' . $name;
			if ( '.' === $name || '..' === $name || ! is_file( $full ) ) {
				continue;
			}
			$st    = @stat( $full );
			$out[] = array(
				'name'  => $name,
				'size'  => is_array( $st ) ? (int) $st['size'] : 0,
				'mtime' => is_array( $st ) ? (int) $st['mtime'] : 0,
			);
		}
		return $out;
	}

	/** @return string[] */
	private function active_plugin_slugs(): array {
		$plugins = (array) get_option( 'active_plugins', array() );
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$plugins = array_merge( $plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$slugs = array();
		foreach ( $plugins as $plugin ) {
			$slugs[] = strtolower( (string) preg_replace( '/\.php$/i', '', strtok( (string) $plugin, '/' ) ) );
		}
		return array_values( array_unique( $slugs ) );
	}

	/** @return array Each: array( 'hook', 'next' ). */
	private function gather_orphan_cron(): array {
		if ( ! function_exists( '_get_cron_array' ) ) {
			return array();
		}
		$next = array();
		foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
			foreach ( (array) $hooks as $hook => $events ) {
				$hook = (string) $hook;
				if ( ! isset( $next[ $hook ] ) || (int) $timestamp < $next[ $hook ] ) {
					$next[ $hook ] = (int) $timestamp;
				}
			}
		}
		$orphans = array();
		foreach ( $next as $hook => $ts ) {
			if ( ! has_action( $hook ) ) {
				$orphans[] = array( 'hook' => $hook, 'next' => $ts );
			}
		}
		return $orphans;
	}

	// ---- Helpers -----------------------------------------------------------

	/** Shown when the walk stopped before covering the whole install. */
	const PARTIAL_RECOMMENDATION = 'Run scan-security again with checks: ["compromise"] and a larger max_seconds (up to 120) to cover the whole install.';

	/**
	 * Pure: the message for a check the walk only partly covered.
	 *
	 * @param string $what What the check looks for.
	 */
	private static function partial_message( string $what ): string {
		return sprintf( 'The install was only partly checked for %s: the folder walk stopped at its limit before covering every folder. None were found in the folders it reached.', $what );
	}

	/**
	 * Pure: whether the walk skips a folder by name.
	 *
	 * @param string $name Folder name.
	 */
	public static function is_skipped_dir( string $name ): bool {
		return in_array( strtolower( $name ), self::SKIPPED_DIRS, true );
	}

	/**
	 * Pure: the timeout for a network request given the seconds left, or 0 to skip it.
	 *
	 * @param int $remaining Whole seconds left in the audit's budget.
	 * @param int $max       The request's usual timeout.
	 */
	public static function timeout_for( int $remaining, int $max ): int {
		$left = $remaining - 1;
		return $left < 1 ? 0 : min( $max, $left );
	}

	private function in_library_path( string $path ): bool {
		$segments = explode( '/', strtolower( $path ) );
		return (bool) array_intersect( $segments, self::LIBRARY_SEGMENTS );
	}

	private function is_sensitive_path( string $path, array $prefixes ): bool {
		if ( false === strpos( $path, '/' ) ) {
			return true; // A file in the WordPress root.
		}
		foreach ( $prefixes as $p ) {
			$p = trim( str_replace( '\\', '/', (string) $p ), '/' );
			if ( '' !== $p && 0 === strpos( $path, $p . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/** ABSPATH-relative path; content-dir files outside ABSPATH map to wp-content/... */
	private function rel_path( string $path ): string {
		$path    = rtrim( str_replace( '\\', '/', $path ), '/' );
		$abspath = $this->realpath_slashed( ABSPATH );
		if ( '' !== $abspath && $this->is_within( $path, $abspath ) ) {
			return ltrim( substr( $path, strlen( $abspath ) ), '/' );
		}
		$content = $this->realpath_slashed( WP_CONTENT_DIR );
		if ( '' !== $content && $this->is_within( $path, $content ) ) {
			return 'wp-content' . substr( $path, strlen( $content ) );
		}
		return $path;
	}

	private function is_within( string $path, string $base ): bool {
		return '' !== $base && ( $path === $base || 0 === strpos( $path, $base . '/' ) );
	}

	private function realpath_slashed( string $path ): string {
		static $cache = array();
		if ( ! isset( $cache[ $path ] ) ) {
			$real           = realpath( $path );
			$cache[ $path ] = false === $real ? '' : rtrim( str_replace( '\\', '/', $real ), '/' );
		}
		return $cache[ $path ];
	}
}
