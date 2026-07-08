<?php
/**
 * Filesystem MCP tools: read/list/search (enabled) + write/edit/delete
 * (disabled-by-default). Every path is confined to ABSPATH by
 * EMCP_Tools_Filesystem_Guard. Writes require edit_files + respect
 * DISALLOW_FILE_EDIT; delete requires confirm:true.
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
		$size = (int) filesize( $abs );
		if ( $size > EMCP_Tools_Filesystem_Guard::MAX_READ_BYTES ) {
			return new \WP_Error( 'too_large', __( 'File exceeds the maximum readable size.', 'emcp-tools' ) );
		}
		$content = (string) file_get_contents( $abs );
		$rel     = EMCP_Tools_Filesystem_Guard::to_relative( $abs );
		if ( ! EMCP_Tools_Filesystem_Guard::is_utf8( $content ) ) {
			return array( 'path' => $rel, 'size' => $size, 'binary' => true, 'message' => __( 'Binary file — not returned as text.', 'emcp-tools' ) );
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
		return array(
			'name'  => basename( $abs ),
			'path'  => EMCP_Tools_Filesystem_Guard::to_relative( $abs ),
			'type'  => is_dir( $abs ) ? 'dir' : 'file',
			'size'  => is_file( $abs ) ? (int) filesize( $abs ) : 0,
			'mtime' => (int) filemtime( $abs ),
		);
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
			$content = (string) file_get_contents( $f->getPathname() );
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
				'description'         => __( 'Create or overwrite a file inside the WordPress install. Backs up an existing file first. Refuses wp-config.php/.htaccess. Disabled by default; requires file-editing to be allowed.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_write_file' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'path'    => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
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
		$content = (string) ( $input['content'] ?? '' );
		if ( strlen( $content ) > EMCP_Tools_Filesystem_Guard::MAX_WRITE_BYTES ) {
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
		$bytes = file_put_contents( $abs, $content );
		if ( false === $bytes ) {
			return new \WP_Error( 'write_failed', __( 'Could not write the file (check permissions).', 'emcp-tools' ) );
		}
		self::invalidate_php_opcache( $abs );
		EMCP_Tools_Filesystem_Guard::log( 'write', $abs );
		return array(
			'path'   => EMCP_Tools_Filesystem_Guard::to_relative( $abs ),
			'bytes'  => (int) $bytes,
			'action' => $existed ? 'overwritten' : 'created',
			'backup' => $backup ? EMCP_Tools_Filesystem_Guard::to_relative( $backup ) : null,
		);
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
		EMCP_Tools_Filesystem_Guard::log( 'edit', $abs );
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
		EMCP_Tools_Filesystem_Guard::log( 'delete', $abs );
		return array(
			'path'    => EMCP_Tools_Filesystem_Guard::to_relative( $abs ),
			'deleted' => true,
			'backup'  => $backup ? EMCP_Tools_Filesystem_Guard::to_relative( $backup ) : null,
		);
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
