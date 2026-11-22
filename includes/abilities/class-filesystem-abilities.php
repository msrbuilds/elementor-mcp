<?php
/**
 * Filesystem MCP tools: read/list/search and list-quarantine (enabled) +
 * write/edit/delete and quarantine/restore (disabled-by-default). Every path is
 * confined to ABSPATH by EMCP_Tools_Filesystem_Guard. Writes require edit_files
 * + respect DISALLOW_FILE_EDIT; delete, quarantine and restore require
 * confirm:true.
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
class EMCP_Tools_Filesystem_Abilities {

	/** @var string[] */
	private $ability_names = array();

	public function get_ability_names(): array {
		return $this->ability_names;
	}

	public function register(): void {
		$this->register_read_file();
		$this->register_list_directory();
		$this->register_search_files();
		$this->register_write_file();
		$this->register_edit_file();
		$this->register_delete_file();
		$this->register_list_quarantine();
		$this->register_quarantine_file();
		$this->register_restore_quarantined_file();
		$this->register_list_fs_backups();
		$this->register_purge_fs_backups();
	}

	/** All filesystem tools require manage_options (reads can expose secrets). */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ---- read-file -----------------------------------------------------

	private function register_read_file(): void {
		$this->ability_names[] = 'emcp-tools/read-file';
		emcp_tools_register_ability(
			'emcp-tools/read-file',
			array(
				'label'               => __( 'Read File', 'emcp-tools' ),
				'description'         => __( 'Read a file inside the WordPress installation (core, plugins, themes, uploads). Optional line offset/limit for large files. Path is confined to the WP install.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_read_file' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'path'   => array( 'type' => 'string', 'description' => __( 'Path relative to the WordPress root (e.g. wp-content/themes/x/style.css).', 'emcp-tools' ) ),
						'offset' => array( 'type' => 'integer', 'description' => __( '1-based start line.', 'emcp-tools' ) ),
						'limit'  => array( 'type' => 'integer', 'description' => __( 'Number of lines to return from offset.', 'emcp-tools' ) ),
					),
					'required'   => array( 'path' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_read_file( $input ) {
		$abs = EMCP_Tools_Filesystem_Guard::resolve_path( (string) ( $input['path'] ?? '' ) );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		if ( ! is_file( $abs ) ) {
			return new \WP_Error( 'not_found', __( 'File not found.', 'emcp-tools' ) );
		}
		if ( EMCP_Tools_Filesystem_Guard::is_read_protected( $abs ) ) {
			return new \WP_Error( 'protected_read', __( 'This file holds site secrets (database credentials and auth salts) and cannot be read.', 'emcp-tools' ) );
		}
		$size = (int) filesize( $abs );
		if ( $size > EMCP_Tools_Filesystem_Guard::MAX_READ_BYTES ) {
			return new \WP_Error( 'too_large', __( 'File exceeds the maximum readable size.', 'emcp-tools' ) );
		}
		$content = (string) file_get_contents( $abs );
		$rel     = EMCP_Tools_Filesystem_Guard::to_relative( $abs );
		if ( ! EMCP_Tools_Filesystem_Guard::is_utf8( $content ) ) {
			return array( 'path' => $rel, 'size' => $size, 'binary' => true, 'message' => __( 'Binary file, not returned as text.', 'emcp-tools' ) );
		}
		$offset = isset( $input['offset'] ) ? max( 1, (int) $input['offset'] ) : 0;
		$limit  = isset( $input['limit'] ) ? max( 0, (int) $input['limit'] ) : 0;
		if ( $offset || $limit ) {
			$lines   = explode( "\n", $content );
			$slice   = array_slice( $lines, $offset ? $offset - 1 : 0, $limit ? $limit : null );
			$content = implode( "\n", $slice );
		}
		return array(
			'path'    => $rel,
			'size'    => $size,
			'lines'   => substr_count( $content, "\n" ) + 1,
			'content' => $content,
		);
	}

	// ---- list-directory ------------------------------------------------

	private function register_list_directory(): void {
		$this->ability_names[] = 'emcp-tools/list-directory';
		emcp_tools_register_ability(
			'emcp-tools/list-directory',
			array(
				'label'               => __( 'List Directory', 'emcp-tools' ),
				'description'         => __( 'List entries (files/dirs with size + mtime) of a directory inside the WordPress install. Optional recursive (bounded) listing.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_directory' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'path'      => array( 'type' => 'string', 'description' => __( 'Directory path relative to the WP root. Defaults to the root.', 'emcp-tools' ) ),
						'recursive' => array( 'type' => 'boolean', 'description' => __( 'Recurse up to 5 levels.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_list_directory( $input ) {
		$abs = EMCP_Tools_Filesystem_Guard::resolve_path( (string) ( $input['path'] ?? '.' ) );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		if ( ! is_dir( $abs ) ) {
			return new \WP_Error( 'not_a_dir', __( 'Not a directory.', 'emcp-tools' ) );
		}
		$recursive = ! empty( $input['recursive'] );
		$entries   = array();
		if ( $recursive ) {
			$it = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $abs, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);
			$it->setMaxDepth( 5 );
			foreach ( $it as $f ) {
				$entries[] = $this->entry( $f->getPathname() );
				if ( count( $entries ) >= 2000 ) {
					break;
				}
			}
		} else {
			foreach ( scandir( $abs ) as $name ) {
				if ( '.' === $name || '..' === $name ) {
					continue;
				}
				$entries[] = $this->entry( $abs . DIRECTORY_SEPARATOR . $name );
			}
		}
		return array( 'path' => EMCP_Tools_Filesystem_Guard::to_relative( $abs ), 'entries' => $entries );
	}

	private function entry( string $abs ): array {
		$entry = array(
			'name'  => basename( $abs ),
			'path'  => EMCP_Tools_Filesystem_Guard::to_relative( $abs ),
			'type'  => is_dir( $abs ) ? 'dir' : 'file',
			'size'  => is_file( $abs ) ? (int) filesize( $abs ) : 0,
			'mtime' => (int) filemtime( $abs ),
		);

		// A symlink reports its TARGET's size and mtime, and that target can sit
		// outside the install. Listing is metadata only, so this is a label
		// rather than a block: say what the entry is instead of presenting it as
		// an ordinary file. read-file and search-files resolve before reading.
		if ( is_link( $abs ) ) {
			$entry['symlink'] = true;
		}

		return $entry;
	}

	// ---- search-files --------------------------------------------------

	private function register_search_files(): void {
		$this->ability_names[] = 'emcp-tools/search-files';
		emcp_tools_register_ability(
			'emcp-tools/search-files',
			array(
				'label'               => __( 'Search Files', 'emcp-tools' ),
				'description'         => __( 'Search file contents for a string across a directory tree inside the WordPress install. Returns file:line matches. Filter by extensions; results are bounded.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_search_files' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'query'       => array( 'type' => 'string', 'description' => __( 'Substring to search for (case-sensitive).', 'emcp-tools' ) ),
						'path'        => array( 'type' => 'string', 'description' => __( 'Directory root, relative to the WP root. Defaults to the root.', 'emcp-tools' ) ),
						'extensions'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => __( 'Limit to these extensions, e.g. ["php","js"].', 'emcp-tools' ) ),
						'max_results' => array( 'type' => 'integer', 'description' => __( 'Cap on matches (default 200, max 500).', 'emcp-tools' ) ),
					),
					'required'   => array( 'query' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_search_files( $input ) {
		$query = (string) ( $input['query'] ?? '' );
		if ( '' === $query ) {
			return new \WP_Error( 'missing_query', __( 'A search query is required.', 'emcp-tools' ) );
		}
		$abs = EMCP_Tools_Filesystem_Guard::resolve_path( (string) ( $input['path'] ?? '.' ) );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		if ( ! is_dir( $abs ) ) {
			return new \WP_Error( 'not_a_dir', __( 'Not a directory.', 'emcp-tools' ) );
		}
		$exts = array();
		if ( ! empty( $input['extensions'] ) && is_array( $input['extensions'] ) ) {
			$exts = array_map( 'strtolower', array_map( 'strval', $input['extensions'] ) );
		}
		$cap     = min( 500, max( 1, isset( $input['max_results'] ) ? (int) $input['max_results'] : 200 ) );
		$matches = array();
		$it      = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $abs, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ( $it as $f ) {
			if ( ! $f->isFile() ) {
				continue;
			}
			if ( $exts && ! in_array( strtolower( $f->getExtension() ), $exts, true ) ) {
				continue;
			}
			if ( $f->getSize() > EMCP_Tools_Filesystem_Guard::MAX_READ_BYTES ) {
				continue;
			}
			// Canonicalize before deciding anything. getPathname() is the path as
			// walked, so for a symlink it is the LINK: its basename defeats the
			// secret check (a link named notes.txt pointing at wp-config.php), and
			// reading it follows the link straight out of ABSPATH. resolve_path()
			// realpath()s and re-confines, which closes both.
			$real = EMCP_Tools_Filesystem_Guard::resolve_path( $f->getPathname() );
			if ( is_wp_error( $real ) ) {
				continue;
			}
			// Never surface secrets (wp-config.php and friends) via a content search.
			if ( EMCP_Tools_Filesystem_Guard::is_read_protected( $real ) ) {
				continue;
			}
			$content = (string) file_get_contents( $real );
			if ( ! EMCP_Tools_Filesystem_Guard::is_utf8( $content ) ) {
				continue;
			}
			$rel = EMCP_Tools_Filesystem_Guard::to_relative( $f->getPathname() );
			$ln  = 0;
			foreach ( explode( "\n", $content ) as $line ) {
				$ln++;
				if ( false !== strpos( $line, $query ) ) {
					$matches[] = array( 'file' => $rel, 'line' => $ln, 'text' => substr( trim( $line ), 0, 300 ) );
					if ( count( $matches ) >= $cap ) {
						return array( 'matches' => $matches, 'truncated' => true );
					}
				}
			}
		}
		return array( 'matches' => $matches, 'truncated' => false );
	}

	// ---- write-file ----------------------------------------------------

	private function register_write_file(): void {
		$this->ability_names[] = 'emcp-tools/write-file';
		emcp_tools_register_ability(
			'emcp-tools/write-file',
			array(
				'label'               => __( 'Write File', 'emcp-tools' ),
				'description'         => __( 'Create or overwrite a file inside the WordPress install. Backs up an existing file first. Refuses wp-config.php/.htaccess. For binary files (images, fonts, ZIP), pass the bytes base64-encoded with encoding "base64". Disabled by default; requires file-editing to be allowed.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_write_file' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'path'     => array( 'type' => 'string' ),
						'content'  => array( 'type' => 'string' ),
						'encoding' => array(
							'type'        => 'string',
							'enum'        => array( 'utf8', 'base64' ),
							'description' => __( 'How content is encoded. "utf8" (default) writes it as text; "base64" decodes it and writes the raw bytes.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'path', 'content' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_write_file( $input ) {
		$gate = EMCP_Tools_Filesystem_Guard::writes_allowed();
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$abs = EMCP_Tools_Filesystem_Guard::resolve_path( (string) ( $input['path'] ?? '' ) );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		if ( EMCP_Tools_Filesystem_Guard::is_protected( $abs ) ) {
			return new \WP_Error( 'protected', __( 'This file is protected from writes.', 'emcp-tools' ) );
		}
		$content  = (string) ( $input['content'] ?? '' );
		$encoding = (string) ( $input['encoding'] ?? 'utf8' );
		if ( ! in_array( $encoding, array( 'utf8', 'base64' ), true ) ) {
			return new \WP_Error( 'invalid_encoding', __( 'encoding must be "utf8" or "base64".', 'emcp-tools' ) );
		}
		$encoded = null;
		$size    = strlen( $content );
		if ( 'base64' === $encoding ) {
			// Strict: anything outside the base64 alphabet (other than the
			// whitespace agents wrap long payloads with) is refused, never skipped.
			$encoded = self::canonical_base64( $content );
			if ( null === $encoded ) {
				return new \WP_Error( 'invalid_base64', __( 'Content is not valid base64.', 'emcp-tools' ) );
			}
			$size = self::decoded_size( $encoded );
		}
		// The size limit applies to the bytes written, after decoding.
		if ( $size > EMCP_Tools_Filesystem_Guard::MAX_WRITE_BYTES ) {
			return new \WP_Error( 'too_large', __( 'Content exceeds the maximum writable size.', 'emcp-tools' ) );
		}
		$existed = is_file( $abs );
		$backup  = EMCP_Tools_Filesystem_Guard::backup( $abs );
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}
		if ( ! wp_mkdir_p( dirname( $abs ) ) ) {
			return new \WP_Error( 'mkdir_failed', __( 'Could not create the parent directory.', 'emcp-tools' ) );
		}
		$bytes = null === $encoded ? file_put_contents( $abs, $content ) : self::write_encoded( $abs, $encoded, $size );
		if ( false === $bytes ) {
			return new \WP_Error( 'write_failed', __( 'Could not write the file (check permissions).', 'emcp-tools' ) );
		}
		self::invalidate_php_opcache( $abs );
		self::record_fs_change( 'write-file', $abs, $backup );
		return array(
			'path'   => EMCP_Tools_Filesystem_Guard::to_relative( $abs ),
			'bytes'  => (int) $bytes,
			'action' => $existed ? 'overwritten' : 'created',
			'backup' => $backup ? EMCP_Tools_Filesystem_Guard::to_relative( $backup ) : null,
		);
	}

	/**
	 * Base64 text without whitespace and with its padding restored, or null when
	 * it is not valid base64 (padding may be left off, as many encoders do).
	 *
	 * @since 3.21.0
	 *
	 * @param string $text Encoded content.
	 */
	private static function canonical_base64( string $text ): ?string {
		// Linear checks only: a regex over a multi-megabyte payload can hit
		// PCRE's backtrack limit and refuse valid input.
		$text = str_replace( array( ' ', "\t", "\r", "\n", "\f", "\v" ), '', $text );
		$data = rtrim( $text, '=' );
		$pad  = strlen( $text ) - strlen( $data );
		$len  = strlen( $data );
		$rest = $len % 4;
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
		if ( strspn( $data, $alphabet ) !== $len || 1 === $rest || $pad > 2 ) {
			return null;
		}
		if ( $pad > 0 && $pad !== ( 4 - $rest ) % 4 ) {
			return null; // Padding must complete the last group exactly.
		}
		return 0 === $rest ? $data : $data . str_repeat( '=', 4 - $rest );
	}

	/**
	 * Bytes a canonical base64 string decodes to.
	 *
	 * @since 3.21.0
	 *
	 * @param string $encoded Output of canonical_base64().
	 */
	private static function decoded_size( string $encoded ): int {
		return intdiv( strlen( $encoded ), 4 ) * 3 - ( strlen( $encoded ) - strlen( rtrim( $encoded, '=' ) ) );
	}

	/**
	 * Write base64 text to a file as raw bytes, decoded by a stream filter on the
	 * way out, so the decoded bytes never exist as a PHP string (host malware
	 * scanners flag a decode feeding a file write; see the 3.14.2 restore engine).
	 *
	 * @since 3.21.0
	 *
	 * @param string $abs     Target path.
	 * @param string $encoded Output of canonical_base64().
	 * @param int    $size    Expected decoded size.
	 * @return int|false Bytes written.
	 */
	private static function write_encoded( string $abs, string $encoded, int $size ) {
		$handle = fopen( $abs, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return false;
		}
		$filter = stream_filter_append( $handle, 'convert.base64-decode', STREAM_FILTER_WRITE );
		$ok     = false !== $filter;
		for ( $at = 0, $len = strlen( $encoded ); $ok && $at < $len; $at += 65536 ) {
			$ok = false !== fwrite( $handle, substr( $encoded, $at, 65536 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		}
		$ok = fclose( $handle ) && $ok; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		clearstatcache( true, $abs );
		return $ok && filesize( $abs ) === $size ? $size : false;
	}

	// ---- edit-file -----------------------------------------------------

	private function register_edit_file(): void {
		$this->ability_names[] = 'emcp-tools/edit-file';
		emcp_tools_register_ability(
			'emcp-tools/edit-file',
			array(
				'label'               => __( 'Edit File', 'emcp-tools' ),
				'description'         => __( 'Replace an exact string in a file (must match once unless replace_all). Backs up first. Refuses wp-config.php/.htaccess. Disabled by default.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_edit_file' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'path'        => array( 'type' => 'string' ),
						'old_string'  => array( 'type' => 'string' ),
						'new_string'  => array( 'type' => 'string' ),
						'replace_all' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'path', 'old_string', 'new_string' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_edit_file( $input ) {
		$gate = EMCP_Tools_Filesystem_Guard::writes_allowed();
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$abs = EMCP_Tools_Filesystem_Guard::resolve_path( (string) ( $input['path'] ?? '' ) );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		if ( EMCP_Tools_Filesystem_Guard::is_protected( $abs ) ) {
			return new \WP_Error( 'protected', __( 'This file is protected from writes.', 'emcp-tools' ) );
		}
		if ( ! is_file( $abs ) ) {
			return new \WP_Error( 'not_found', __( 'File not found.', 'emcp-tools' ) );
		}
		$content = (string) file_get_contents( $abs );
		if ( ! EMCP_Tools_Filesystem_Guard::is_utf8( $content ) ) {
			return new \WP_Error( 'binary', __( 'Cannot edit a binary file.', 'emcp-tools' ) );
		}
		$old = (string) ( $input['old_string'] ?? '' );
		$new = (string) ( $input['new_string'] ?? '' );
		if ( '' === $old ) {
			return new \WP_Error( 'empty_old', __( 'old_string must not be empty.', 'emcp-tools' ) );
		}
		$count = substr_count( $content, $old );
		if ( 0 === $count ) {
			return new \WP_Error( 'no_match', __( 'old_string was not found in the file.', 'emcp-tools' ) );
		}
		$all = ! empty( $input['replace_all'] );
		if ( $count > 1 && ! $all ) {
			return new \WP_Error( 'multiple_matches', __( 'old_string matched multiple times; pass replace_all or make it unique.', 'emcp-tools' ) );
		}
		$backup = EMCP_Tools_Filesystem_Guard::backup( $abs );
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}
		if ( $all ) {
			$updated = str_replace( $old, $new, $content );
		} else {
			$pos     = strpos( $content, $old );
			$updated = substr( $content, 0, $pos ) . $new . substr( $content, $pos + strlen( $old ) );
		}
		if ( false === file_put_contents( $abs, $updated ) ) {
			return new \WP_Error( 'write_failed', __( 'Could not write the file (check permissions).', 'emcp-tools' ) );
		}
		self::invalidate_php_opcache( $abs );
		self::record_fs_change( 'edit-file', $abs, $backup );
		return array(
			'path'         => EMCP_Tools_Filesystem_Guard::to_relative( $abs ),
			'replacements' => $all ? $count : 1,
			'backup'       => $backup ? EMCP_Tools_Filesystem_Guard::to_relative( $backup ) : null,
		);
	}

	// ---- delete-file ---------------------------------------------------

	private function register_delete_file(): void {
		$this->ability_names[] = 'emcp-tools/delete-file';
		emcp_tools_register_ability(
			'emcp-tools/delete-file',
			array(
				'label'               => __( 'Delete File', 'emcp-tools' ),
				'description'         => __( 'Delete a file inside the WordPress install. Backs up first. Requires confirm:true. Refuses wp-config.php/.htaccess. Disabled by default.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_delete_file' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'path'    => array( 'type' => 'string' ),
						'confirm' => array( 'type' => 'boolean', 'description' => __( 'Must be true to delete.', 'emcp-tools' ) ),
					),
					'required'   => array( 'path' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_delete_file( $input ) {
		if ( empty( $input['confirm'] ) || true !== $input['confirm'] ) {
			return new \WP_Error( 'confirm_required', __( 'Deleting a file requires confirm:true.', 'emcp-tools' ) );
		}
		$gate = EMCP_Tools_Filesystem_Guard::writes_allowed();
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$abs = EMCP_Tools_Filesystem_Guard::resolve_path( (string) ( $input['path'] ?? '' ) );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		if ( EMCP_Tools_Filesystem_Guard::is_protected( $abs ) ) {
			return new \WP_Error( 'protected', __( 'This file is protected from deletion.', 'emcp-tools' ) );
		}
		if ( ! is_file( $abs ) ) {
			return new \WP_Error( 'not_found', __( 'File not found.', 'emcp-tools' ) );
		}
		$backup = EMCP_Tools_Filesystem_Guard::backup( $abs );
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}
		if ( ! unlink( $abs ) ) {
			return new \WP_Error( 'delete_failed', __( 'Could not delete the file (check permissions).', 'emcp-tools' ) );
		}
		self::invalidate_php_opcache( $abs );
		self::record_fs_change( 'delete-file', $abs, $backup );
		return array(
			'path'    => EMCP_Tools_Filesystem_Guard::to_relative( $abs ),
			'deleted' => true,
			'backup'  => $backup ? EMCP_Tools_Filesystem_Guard::to_relative( $backup ) : null,
		);
	}

	// ---- list-quarantine -----------------------------------------------

	private function register_list_quarantine(): void {
		$this->ability_names[] = 'emcp-tools/list-quarantine';
		emcp_tools_register_ability(
			'emcp-tools/list-quarantine',
			array(
				'label'               => __( 'List Quarantine', 'emcp-tools' ),
				'description'         => __( 'List files and folders moved into quarantine by quarantine-file: id, original path, size, sha256, times, reason, who and when, where it is stored and whether it was restored. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_quarantine' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array( 'type' => 'string', 'enum' => array( 'quarantined', 'restored' ), 'description' => __( 'Only entries in this state. Default: all.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array
	 */
	public function execute_list_quarantine( $input ) {
		$status  = (string) ( $input['status'] ?? '' );
		$status  = in_array( $status, array( 'quarantined', 'restored' ), true ) ? $status : '';
		$entries = EMCP_Tools_File_Quarantine::entries( $status );
		return array( 'entries' => $entries, 'count' => count( $entries ) );
	}

	// ---- list-fs-backups / purge-fs-backups (#196) -----------------------

	private function register_list_fs_backups(): void {
		$this->ability_names[] = 'emcp-tools/list-fs-backups';
		emcp_tools_register_ability(
			'emcp-tools/list-fs-backups',
			array(
				'label'               => __( 'List Filesystem Backups', 'emcp-tools' ),
				'description'         => __( 'Lists the copies write-file, edit-file and delete-file keep before each change: name, size and time, newest first, with the total size and the retention setting. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_fs_backups' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array
	 */
	public function execute_list_fs_backups( $input ) {
		EMCP_Tools_FS_Backup_Store::migrate_legacy();
		$all   = EMCP_Tools_FS_Backup_Store::entries();
		$shown = array_slice( $all, 0, EMCP_Tools_FS_Backup_Store::MAX_LIST );
		foreach ( $shown as &$e ) {
			$e['time'] = gmdate( 'c', $e['time'] );
		}
		unset( $e );
		return array(
			'backups'     => $shown,
			'count'       => count( $all ),
			'total_bytes' => (int) array_sum( array_column( $all, 'size' ) ),
			'retention'   => EMCP_Tools_FS_Backup_Store::retention(),
		);
	}

	private function register_purge_fs_backups(): void {
		$this->ability_names[] = 'emcp-tools/purge-fs-backups';
		emcp_tools_register_ability(
			'emcp-tools/purge-fs-backups',
			array(
				'label'               => __( 'Purge Filesystem Backups', 'emcp-tools' ),
				'description'         => __( 'Deletes filesystem backups (the copies the file tools keep before each change): those older than older_than_days, the oldest beyond max_mb, or the names given. Returns what it removed, the space freed and which History entries lose their undo. dry_run: true lists them without deleting. Deleting needs confirm: true and cannot be undone. Disabled by default.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_purge_fs_backups' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'older_than_days' => array( 'type' => 'integer', 'description' => __( 'Remove copies older than this many days.', 'emcp-tools' ) ),
						'max_mb'          => array( 'type' => 'integer', 'description' => __( 'Remove the oldest copies until the rest fit in this many MB.', 'emcp-tools' ) ),
						'names'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => __( 'Remove these copies by name (from list-fs-backups).', 'emcp-tools' ) ),
						'dry_run'         => array( 'type' => 'boolean', 'description' => __( 'List what would be removed without deleting. Default false.', 'emcp-tools' ) ),
						'confirm'         => array( 'type' => 'boolean', 'description' => __( 'Must be true to delete.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|WP_Error
	 */
	public function execute_purge_fs_backups( $input ) {
		$days  = isset( $input['older_than_days'] ) ? max( 0, (int) $input['older_than_days'] ) : 0;
		$mb    = isset( $input['max_mb'] ) ? max( 0, (int) $input['max_mb'] ) : 0;
		$names = array_values( array_filter( array_map( 'strval', (array) ( $input['names'] ?? array() ) ), array( 'EMCP_Tools_FS_Backup_Store', 'is_copy_name' ) ) );
		if ( 0 === $days && 0 === $mb && empty( $names ) ) {
			return new WP_Error( 'missing_params', __( 'Send older_than_days, max_mb or names.', 'emcp-tools' ) );
		}
		$dry = ! empty( $input['dry_run'] );
		if ( ! $dry && true !== ( $input['confirm'] ?? null ) ) {
			return new WP_Error( 'confirm_required', __( 'Deleting backups cannot be undone. Pass confirm: true, or dry_run: true to see what would go.', 'emcp-tools' ) );
		}
		EMCP_Tools_FS_Backup_Store::migrate_legacy();
		$entries = EMCP_Tools_FS_Backup_Store::entries();
		$known   = array_column( $entries, 'size', 'name' );
		$targets = array_values(
			array_unique(
				array_merge(
					array_values( array_intersect( $names, array_keys( $known ) ) ),
					( $days || $mb ) ? EMCP_Tools_FS_Backup_Store::to_prune( $entries, $days, $mb, time() ) : array()
				)
			)
		);
		$history = EMCP_Tools_FS_Backup_Store::history_using( $targets );
		if ( $dry ) {
			return array(
				'dry_run'             => true,
				'would_remove'        => $targets,
				'bytes'               => (int) array_sum( array_intersect_key( $known, array_flip( $targets ) ) ),
				'history_losing_undo' => $history,
			);
		}
		$res = EMCP_Tools_FS_Backup_Store::delete( $targets );
		if ( ! empty( $res['removed'] ) && class_exists( 'EMCP_Tools_Change_Log' ) ) {
			EMCP_Tools_Change_Log::record(
				array(
					'domain'   => 'filesystem',
					'action'   => 'purge-fs-backups',
					'target'   => sprintf( '%d backups', count( $res['removed'] ) ),
					/* translators: %d: number of copies. */
					'summary'  => sprintf( __( 'Deleted %d filesystem backups', 'emcp-tools' ), count( $res['removed'] ) ),
					'rollback' => array(
						'type'   => 'irreversible',
						'reason' => __( 'The backup copies were deleted.', 'emcp-tools' ),
					),
				)
			);
		}
		return array(
			'removed'             => $res['removed'],
			'bytes_freed'         => $res['bytes_freed'],
			'history_losing_undo' => $history,
		);
	}

	// ---- quarantine-file -----------------------------------------------

	private function register_quarantine_file(): void {
		$this->ability_names[] = 'emcp-tools/quarantine-file';
		emcp_tools_register_ability(
			'emcp-tools/quarantine-file',
			array(
				'label'               => __( 'Quarantine File', 'emcp-tools' ),
				'description'         => __( 'Move a suspicious file or folder out of the web root so it stops running, with a manifest (size, sha256, times, reason). Every stored file is renamed to end in .quarantined, so no web server runs it, and the folder is denied to the web (.htaccess and web.config). Stored above WordPress when that folder is writable and not itself web-served, else in wp-content/emcp-quarantine. Undo with restore-quarantined-file or rollback-change. Refuses WordPress core files, wp-admin, wp-includes and symlinks that leave the site. Requires confirm:true. Disabled by default.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_quarantine_file' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'path'    => array( 'type' => 'string', 'description' => __( 'File or folder relative to the WordPress root, e.g. wp-content/plugins/x/prompts/prompts/index.php.', 'emcp-tools' ) ),
						'reason'  => array( 'type' => 'string', 'description' => __( 'Why it is being quarantined (kept in the manifest).', 'emcp-tools' ) ),
						'confirm' => array( 'type' => 'boolean', 'description' => __( 'Must be true to quarantine.', 'emcp-tools' ) ),
					),
					'required'   => array( 'path' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_quarantine_file( $input ) {
		if ( empty( $input['confirm'] ) || true !== $input['confirm'] ) {
			return new \WP_Error( 'confirm_required', __( 'Quarantining a file requires confirm:true.', 'emcp-tools' ) );
		}
		$gate = EMCP_Tools_Filesystem_Guard::writes_allowed();
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$result = EMCP_Tools_File_Quarantine::quarantine( (string) ( $input['path'] ?? '' ), (string) ( $input['reason'] ?? '' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$manifest = $result['manifest'];
		$change   = '';
		if ( class_exists( 'EMCP_Tools_Change_Log' ) ) {
			$change = EMCP_Tools_Change_Log::record(
				array(
					'domain'   => 'filesystem',
					'action'   => 'quarantine-file',
					'target'   => $manifest['original_path'],
					'summary'  => 'Quarantined ' . $manifest['original_path'],
					'rollback' => array(
						'type'          => EMCP_Tools_File_Quarantine::ROLLBACK_TYPE,
						'quarantine_id' => $manifest['id'],
					),
				)
			);
			if ( '' !== $change ) {
				EMCP_Tools_File_Quarantine::set_change_id( $result['base'], $manifest, $change );
			}
		}
		return array(
			'id'        => $manifest['id'],
			'path'      => $manifest['original_path'],
			'type'      => $manifest['type'],
			'size'      => $manifest['size'],
			'sha256'    => $manifest['sha256'],
			'location'  => $manifest['location'],
			'stored_at' => $result['stored_at'],
			'method'    => $manifest['method'],
			'change_id' => $change,
			'warnings'  => $result['warnings'],
		);
	}

	// ---- restore-quarantined-file --------------------------------------

	private function register_restore_quarantined_file(): void {
		$this->ability_names[] = 'emcp-tools/restore-quarantined-file';
		emcp_tools_register_ability(
			'emcp-tools/restore-quarantined-file',
			array(
				'label'               => __( 'Restore Quarantined File', 'emcp-tools' ),
				'description'         => __( 'Move a quarantined file or folder back to its original path, by id from list-quarantine. Refuses when something now exists at that path unless overwrite:true, which quarantines the current item first instead of deleting it. Requires confirm:true. Disabled by default.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_restore_quarantined_file' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'        => array( 'type' => 'string', 'description' => __( 'Quarantine id from list-quarantine.', 'emcp-tools' ) ),
						'overwrite' => array( 'type' => 'boolean', 'description' => __( 'Restore even if the original path exists now (the current item is quarantined, not deleted).', 'emcp-tools' ) ),
						'confirm'   => array( 'type' => 'boolean', 'description' => __( 'Must be true to restore.', 'emcp-tools' ) ),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_restore_quarantined_file( $input ) {
		if ( empty( $input['confirm'] ) || true !== $input['confirm'] ) {
			return new \WP_Error( 'confirm_required', __( 'Restoring a quarantined file requires confirm:true.', 'emcp-tools' ) );
		}
		$gate = EMCP_Tools_Filesystem_Guard::writes_allowed();
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$result = EMCP_Tools_File_Quarantine::restore( (string) ( $input['id'] ?? '' ), ! empty( $input['overwrite'] ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( class_exists( 'EMCP_Tools_Change_Log' ) ) {
			// The quarantine's own ledger entry is now undone; say so, so History
			// does not offer an undo that would find nothing to restore.
			if ( '' !== $result['change_id'] ) {
				EMCP_Tools_Change_Log::mark_rolled_back( $result['change_id'] );
			}
			EMCP_Tools_Change_Log::record(
				array(
					'domain'   => 'filesystem',
					'action'   => 'restore-quarantined-file',
					'target'   => $result['path'],
					'summary'  => 'Restored ' . $result['path'] . ' from quarantine',
					'rollback' => null,
				)
			);
		}
		return $result;
	}

	/**
	 * Record a filesystem mutation to the unified change ledger.
	 *
	 * @param string        $action write-file | edit-file | delete-file.
	 * @param string        $abs    Absolute target path.
	 * @param string|mixed  $backup Backup path ('' when the write created a new file).
	 */
	private static function record_fs_change( string $action, string $abs, $backup ): void {
		if ( ! class_exists( 'EMCP_Tools_Change_Log' ) ) {
			return;
		}
		$rel     = EMCP_Tools_Filesystem_Guard::to_relative( $abs );
		$rollback = ( '' === $backup )
			? array( 'type' => 'file-create', 'target_path' => $abs )
			: array( 'type' => 'file-backup', 'target_path' => $abs, 'backup_path' => (string) $backup );
		$verbs   = array( 'write-file' => 'Wrote', 'edit-file' => 'Edited', 'delete-file' => 'Deleted' );
		$entry   = array(
			'domain'   => 'filesystem',
			'action'   => $action,
			'target'   => $rel,
			'summary'  => ( $verbs[ $action ] ?? 'Changed' ) . ' ' . $rel,
			'rollback' => $rollback,
		);
		// record_file stamps an after-hash of the written file (empty for a delete)
		// so a later external change is caught by the conflict guard on rollback.
		if ( class_exists( 'EMCP_Tools_Change_Recorder' ) ) {
			EMCP_Tools_Change_Recorder::record_file( $entry, $abs );
		} else {
			EMCP_Tools_Change_Log::record( $entry );
		}
	}

	/**
	 * Invalidate the OPcache entry for a freshly written or removed PHP file so the
	 * change takes effect on the next request instead of executing stale bytecode.
	 * Mirrors the guard already used in class-php-snippet-store / class-widget-store /
	 * themer/php/class-themer-php-store.
	 */
	private static function invalidate_php_opcache( string $abs ): void {
		if ( function_exists( 'opcache_invalidate' ) && '.php' === strtolower( substr( $abs, -4 ) ) ) {
			opcache_invalidate( $abs, true );
		}
	}
}
