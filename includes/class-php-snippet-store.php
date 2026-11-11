<?php
/**
 * PHP Snippet Store — source-of-truth + sandbox for admin/AI PHP snippets.
 *
 * The canonical record is a private `emcp_php_snippet` CPT whose meta holds the
 * raw code, run context, validation report, and a sha256 integrity hash. An
 * executable file is written to the sandbox ONLY while a snippet is active
 * (`wp-content/emcp-sandbox/snippets/{id}.php`, already `.htaccess`-blocked); a
 * draft has no runnable artifact on disk at all. Post status is the activation
 * flag: `publish` = active, `draft` = inactive.
 *
 * Activation is the human approval gate: create/update only ever produce DRAFTS
 * (the MCP tools cannot reach set_status), and set_status('active') re-runs the
 * validator and refuses anything with a critical finding.
 *
 * @package EMCP_Tools
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores, validates, compiles, and tracks PHP snippets.
 *
 * @since 2.1.0
 */
class EMCP_Tools_PHP_Snippet_Store {

	const POST_TYPE       = 'emcp_php_snippet';
	const META_CODE       = '_emcp_snippet_code';
	const META_CONTEXT    = '_emcp_snippet_context';
	const META_HOOK       = '_emcp_snippet_hook';
	const META_PRIORITY   = '_emcp_snippet_priority';
	const META_VALIDATION = '_emcp_snippet_validation';
	const META_HASH       = '_emcp_snippet_hash';
	const META_ERROR      = '_emcp_snippet_error';

	/** Allowed run contexts. */
	const CONTEXTS = array( 'shortcode', 'hook', 'both' );

	// -------------------------------------------------------------------------
	// CPT + permissions
	// -------------------------------------------------------------------------

	/**
	 * Registers the CPT. Hooked on `init`.
	 *
	 * @since 2.1.0
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'author' ),
				'labels'              => array( 'name' => __( 'EMCP PHP Snippets', 'emcp-tools' ) ),
			)
		);
	}

	/**
	 * May the current user create/edit/activate snippets? Running arbitrary PHP
	 * is gated to the same capability set that already lets a user edit plugin
	 * code: manage_options AND unfiltered_html (capability-based, not Pro-gated).
	 *
	 * @since 2.1.0
	 *
	 * @return bool
	 */
	public static function can_edit(): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
	}

	/**
	 * May the current user read/list snippets?
	 *
	 * @since 2.1.0
	 *
	 * @return bool
	 */
	public static function can_read(): bool {
		return current_user_can( 'manage_options' );
	}

	// -------------------------------------------------------------------------
	// Sandbox paths
	// -------------------------------------------------------------------------

	public static function sandbox_dir(): string {
		return EMCP_Tools_Sandbox_Paths::base_dir();
	}

	public static function snippets_dir(): string {
		return self::sandbox_dir() . '/snippets';
	}

	public static function manifest_path(): string {
		return self::sandbox_dir() . '/snippets-manifest.json';
	}

	public static function relative_php_path( int $post_id ): string {
		return 'snippets/' . $post_id . '.php';
	}

	public static function php_path( int $post_id ): string {
		return self::sandbox_dir() . '/' . self::relative_php_path( $post_id );
	}

	public static function func_name( int $post_id ): string {
		return 'emcp_php_snippet_' . $post_id;
	}

	/**
	 * Ensures the sandbox tree exists with web-access guards.
	 *
	 * @since 2.1.0
	 *
	 * @return true|WP_Error
	 */
	public static function ensure_sandbox() {
		$dir = self::snippets_dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'sandbox_unwritable', __( 'Could not create the snippet sandbox directory under wp-content. Check filesystem permissions.', 'emcp-tools' ) );
		}
		EMCP_Tools_Sandbox_Paths::harden();
		EMCP_Tools_Sandbox_Paths::guard_subdir( $dir );
		return true;
	}

	private static function write_file( string $path, string $contents ): bool {
		wp_mkdir_p( dirname( $path ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$ok = false !== file_put_contents( $path, $contents );
		if ( $ok && function_exists( 'opcache_invalidate' ) && '.php' === substr( $path, -4 ) ) {
			opcache_invalidate( $path, true );
		}
		return $ok;
	}

	private static function delete_file( string $path ): void {
		if ( file_exists( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $path );
			if ( function_exists( 'opcache_invalidate' ) ) {
				opcache_invalidate( $path, true );
			}
		}
	}

	// -------------------------------------------------------------------------
	// CRUD
	// -------------------------------------------------------------------------

	/**
	 * Creates a snippet as a DRAFT (never active). Validates first and refuses
	 * code that fails to parse or trips a critical security finding.
	 *
	 * @since 2.1.0
	 *
	 * @param array $args { title, code, context, hook, priority }.
	 * @return array|WP_Error Summary on success.
	 */
	public static function create_draft( array $args ) {
		if ( ! self::can_edit() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to create PHP snippets (requires manage_options and unfiltered_html).', 'emcp-tools' ) );
		}

		$code       = isset( $args['code'] ) ? (string) $args['code'] : '';
		$validation = EMCP_Tools_PHP_Snippet_Validator::validate( $code );
		if ( ! $validation['valid'] ) {
			return new WP_Error( 'invalid_php', sprintf( /* translators: %s: parse error */ __( 'The snippet is not valid PHP: %s', 'emcp-tools' ), $validation['parse_error'] ), array( 'validation' => $validation ) );
		}
		if ( ! $validation['safe'] ) {
			return new WP_Error( 'unsafe_php', __( 'The snippet was blocked by the security validator (critical finding). See the validation report.', 'emcp-tools' ), array( 'validation' => $validation ) );
		}

		$title = isset( $args['title'] ) && '' !== trim( (string) $args['title'] )
			? sanitize_text_field( (string) $args['title'] )
			: __( 'PHP Snippet', 'emcp-tools' );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $title,
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::persist_meta( (int) $post_id, $args, $validation );

		return self::get( (int) $post_id );
	}

	/**
	 * Updates a snippet's code/config. Re-validates; if the snippet is currently
	 * active it is re-compiled, or demoted to draft if it now trips a critical.
	 *
	 * @since 2.1.0
	 *
	 * @param int   $post_id Snippet ID.
	 * @param array $args    Partial { title, code, context, hook, priority }.
	 * @return array|WP_Error
	 */
	public static function update( int $post_id, array $args ) {
		if ( ! self::can_edit() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to update PHP snippets.', 'emcp-tools' ) );
		}
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Snippet not found.', 'emcp-tools' ) );
		}

		// Merge code: new code if given, else the stored code.
		self::refresh_meta( $post_id );
		$code       = array_key_exists( 'code', $args ) ? (string) $args['code'] : (string) get_post_meta( $post_id, self::META_CODE, true );
		$validation = EMCP_Tools_PHP_Snippet_Validator::validate( $code );
		if ( ! $validation['valid'] ) {
			return new WP_Error( 'invalid_php', sprintf( /* translators: %s: parse error */ __( 'The snippet is not valid PHP: %s', 'emcp-tools' ), $validation['parse_error'] ), array( 'validation' => $validation ) );
		}
		if ( ! $validation['safe'] ) {
			return new WP_Error( 'unsafe_php', __( 'The snippet was blocked by the security validator (critical finding). See the validation report.', 'emcp-tools' ), array( 'validation' => $validation ) );
		}

		if ( isset( $args['title'] ) && '' !== trim( (string) $args['title'] ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_title' => sanitize_text_field( (string) $args['title'] ) ) );
		}

		$args['code'] = $code;
		self::persist_meta( $post_id, $args, $validation );
		delete_post_meta( $post_id, self::META_ERROR );

		// If active, re-write the executable file from the new code.
		if ( 'publish' === get_post_status( $post_id ) ) {
			$written = self::write_executable( $post_id, $code );
			if ( is_wp_error( $written ) ) {
				wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
				update_post_meta( $post_id, self::META_ERROR, sanitize_text_field( $written->get_error_message() ) );
			}
			self::rebuild_manifest();
		}

		return self::get( $post_id );
	}

	/**
	 * Persists the snippet meta (code is stored slashed; context/hook/priority
	 * sanitized). Shared by create/update.
	 *
	 * @param int   $post_id    Snippet ID.
	 * @param array $args       Input.
	 * @param array $validation Validation report.
	 */
	private static function persist_meta( int $post_id, array $args, array $validation ): void {
		self::refresh_meta( $post_id );
		if ( array_key_exists( 'code', $args ) ) {
			update_post_meta( $post_id, self::META_CODE, wp_slash( (string) $args['code'] ) );
		}
		if ( array_key_exists( 'context', $args ) ) {
			$ctx = in_array( $args['context'], self::CONTEXTS, true ) ? (string) $args['context'] : 'shortcode';
			update_post_meta( $post_id, self::META_CONTEXT, $ctx );
		} elseif ( '' === (string) get_post_meta( $post_id, self::META_CONTEXT, true ) ) {
			update_post_meta( $post_id, self::META_CONTEXT, 'shortcode' );
		}
		if ( array_key_exists( 'hook', $args ) ) {
			update_post_meta( $post_id, self::META_HOOK, self::sanitize_hook( (string) $args['hook'] ) );
		}
		if ( array_key_exists( 'priority', $args ) ) {
			update_post_meta( $post_id, self::META_PRIORITY, (int) $args['priority'] );
		}
		update_post_meta( $post_id, self::META_VALIDATION, wp_slash( (string) wp_json_encode( $validation ) ) );
		self::refresh_meta( $post_id );
	}

	/**
	 * Drops the snippet's cached meta and loads it again from the database.
	 * Some persistent object caches kept serving the meta read before a write,
	 * so a create answered empty fields and a code-less update found no stored
	 * code (#182).
	 *
	 * @since 3.21.4
	 *
	 * @param int $post_id Snippet ID.
	 */
	private static function refresh_meta( int $post_id ): void {
		wp_cache_delete( $post_id, 'post_meta' );
		if ( function_exists( 'update_meta_cache' ) ) {
			update_meta_cache( 'post', array( $post_id ) );
		}
	}

	private static function sanitize_hook( string $hook ): string {
		$hook = trim( $hook );
		// WP hook tags are conservative identifiers; allow word chars, slashes, dashes.
		$hook = preg_replace( '/[^A-Za-z0-9_\/\-]/', '', $hook );
		return is_string( $hook ) ? $hook : '';
	}

	/**
	 * Wraps the snippet code in a uniquely-named function and writes it to the
	 * sandbox, recording the integrity hash. Only called for ACTIVE snippets.
	 *
	 * @param int    $post_id Snippet ID.
	 * @param string $code    Raw snippet code.
	 * @return true|WP_Error
	 */
	private static function write_executable( int $post_id, string $code ) {
		$ensured = self::ensure_sandbox();
		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}
		$body = EMCP_Tools_PHP_Snippet_Validator::strip_tags( $code );
		$func = self::func_name( $post_id );
		$php  = "<?php\n"
			. "// EMCP PHP Snippet {$post_id}, generated from the snippet stored in the database.\n"
			. "// Do not edit: edits are ignored and fail the integrity check.\n"
			. "if ( ! function_exists( '{$func}' ) ) {\n"
			. "\tfunction {$func}() {\n"
			. $body . "\n"
			. "\t}\n"
			. "}\n";

		// Final guard: the wrapped file must itself parse.
		try {
			token_get_all( $php, TOKEN_PARSE );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'compile_failed', $e->getMessage() );
		}

		$path = self::php_path( $post_id );
		if ( ! self::write_file( $path, $php ) ) {
			return new WP_Error( 'write_failed', __( 'Could not write the snippet file to the sandbox.', 'emcp-tools' ) );
		}
		update_post_meta( $post_id, self::META_HASH, hash( 'sha256', $php ) );
		return true;
	}

	/**
	 * Activates or deactivates a snippet. Activation re-validates and writes the
	 * executable file; deactivation removes it. This is the human approval gate —
	 * never exposed to the MCP tools.
	 *
	 * @since 2.1.0
	 *
	 * @param int    $post_id Snippet ID.
	 * @param string $status  'active' | 'draft'.
	 * @return array|WP_Error
	 */
	public static function set_status( int $post_id, string $status ) {
		if ( ! self::can_edit() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to change snippet status.', 'emcp-tools' ) );
		}
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Snippet not found.', 'emcp-tools' ) );
		}

		if ( 'active' === $status ) {
			$code       = (string) get_post_meta( $post_id, self::META_CODE, true );
			$validation = EMCP_Tools_PHP_Snippet_Validator::validate( $code );
			if ( ! $validation['valid'] || ! $validation['safe'] ) {
				update_post_meta( $post_id, self::META_VALIDATION, wp_slash( (string) wp_json_encode( $validation ) ) );
				return new WP_Error( 'activation_blocked', __( 'Cannot activate, the snippet fails validation. Fix the flagged code and try again.', 'emcp-tools' ), array( 'validation' => $validation ) );
			}
			$written = self::write_executable( $post_id, $code );
			if ( is_wp_error( $written ) ) {
				return $written;
			}
			delete_post_meta( $post_id, self::META_ERROR );
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		} else {
			self::delete_file( self::php_path( $post_id ) );
			delete_post_meta( $post_id, self::META_HASH );
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
		}

		self::rebuild_manifest();
		return self::get( $post_id );
	}

	/**
	 * Deletes a snippet: CPT post, sandbox file, manifest entry.
	 *
	 * @since 2.1.0
	 *
	 * @param int $post_id Snippet ID.
	 * @return array|WP_Error
	 */
	public static function delete( int $post_id ) {
		if ( ! self::can_edit() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to delete PHP snippets.', 'emcp-tools' ) );
		}
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Snippet not found.', 'emcp-tools' ) );
		}
		self::delete_file( self::php_path( $post_id ) );
		wp_delete_post( $post_id, true );
		self::rebuild_manifest();
		return array( 'success' => true, 'snippet_id' => $post_id );
	}

	/**
	 * Loader shutdown callback target: flag a snippet that crashed on load and
	 * deactivate it so the site recovers next request.
	 *
	 * @since 2.1.0
	 *
	 * @param int    $post_id Snippet ID.
	 * @param string $error   Captured error.
	 */
	public static function mark_error( int $post_id, string $error ): void {
		update_post_meta( $post_id, self::META_ERROR, sanitize_text_field( $error ) );
		self::delete_file( self::php_path( $post_id ) );
		delete_post_meta( $post_id, self::META_HASH );
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
		self::rebuild_manifest();
	}

	// -------------------------------------------------------------------------
	// Read
	// -------------------------------------------------------------------------

	/**
	 * Returns a snippet's full record (summary + code + validation report).
	 *
	 * @since 2.1.0
	 *
	 * @param int $post_id Snippet ID.
	 * @return array|WP_Error
	 */
	public static function get( int $post_id ) {
		$summary = self::summary( $post_id );
		if ( is_wp_error( $summary ) ) {
			return $summary;
		}
		$summary['code']       = (string) get_post_meta( $post_id, self::META_CODE, true );
		$summary['validation'] = self::get_validation( $post_id );
		return $summary;
	}

	/**
	 * Returns a snippet summary (no code body).
	 *
	 * @since 2.1.0
	 *
	 * @param int $post_id Snippet ID.
	 * @return array|WP_Error
	 */
	public static function summary( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Snippet not found.', 'emcp-tools' ) );
		}
		$context = (string) get_post_meta( $post_id, self::META_CONTEXT, true );
		return array(
			'snippet_id' => (int) $post_id,
			'title'      => (string) $post->post_title,
			'status'     => ( 'publish' === $post->post_status ) ? 'active' : 'draft',
			'context'    => '' !== $context ? $context : 'shortcode',
			'hook'       => (string) get_post_meta( $post_id, self::META_HOOK, true ),
			'priority'   => (int) get_post_meta( $post_id, self::META_PRIORITY, true ),
			'shortcode'  => '[emcp_snippet id="' . (int) $post_id . '"]',
			'last_error' => (string) get_post_meta( $post_id, self::META_ERROR, true ),
			'updated'    => (string) $post->post_modified,
		);
	}

	/**
	 * Returns the stored validation report for a snippet.
	 *
	 * @param int $post_id Snippet ID.
	 * @return array
	 */
	public static function get_validation( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_VALIDATION, true );
		if ( empty( $raw ) ) {
			return array( 'valid' => true, 'safe' => true, 'parse_error' => '', 'findings' => array() );
		}
		$data = json_decode( (string) $raw, true );
		return is_array( $data ) ? $data : array( 'valid' => true, 'safe' => true, 'parse_error' => '', 'findings' => array() );
	}

	/**
	 * Lists snippets.
	 *
	 * @since 2.1.0
	 *
	 * @param string $status 'active' | 'draft' | 'any'.
	 * @return array<int,array>
	 */
	public static function list_snippets( string $status = 'any' ): array {
		$post_status = 'any';
		if ( 'active' === $status ) {
			$post_status = 'publish';
		} elseif ( 'draft' === $status ) {
			$post_status = 'draft';
		}
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => $post_status,
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $query->posts as $post ) {
			$summary = self::summary( (int) $post->ID );
			if ( ! is_wp_error( $summary ) ) {
				$out[] = $summary;
			}
		}
		return $out;
	}

	/**
	 * One page of snippets, plus the totals needed to draw a pager.
	 *
	 * list_snippets() stays as it is for the MCP tools, which want the whole
	 * list in a single call. The admin table renders each row's full source,
	 * so it takes a page at a time instead.
	 *
	 * @since 3.15.0
	 *
	 * @param string $status   'active', 'draft', or 'any'.
	 * @param int    $page     1-based page number.
	 * @param int    $per_page Rows per page.
	 * @return array{items:array[],total:int,page:int,pages:int,per_page:int}
	 */
	public static function list_snippets_page( string $status = 'any', int $page = 1, int $per_page = EMCP_Tools_Sandbox_List_Query::PER_PAGE ): array {
		$result = EMCP_Tools_Sandbox_List_Query::page( self::POST_TYPE, $status, $page, $per_page );

		$items = array();
		foreach ( $result['ids'] as $emcp_id ) {
			$summary = self::summary( $emcp_id );
			if ( ! is_wp_error( $summary ) ) {
				$items[] = $summary;
			}
		}

		return array(
			'items'    => $items,
			'total'    => $result['total'],
			'page'     => $result['page'],
			'pages'    => $result['pages'],
			'per_page' => $result['per_page'],
		);
	}

	// -------------------------------------------------------------------------
	// Manifest
	// -------------------------------------------------------------------------

	/**
	 * Rebuilds the active-snippets manifest.
	 *
	 * @since 2.1.0
	 */
	public static function rebuild_manifest(): void {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'no_found_rows'  => true,
			)
		);
		$entries = array();
		foreach ( $query->posts as $post ) {
			$post_id   = (int) $post->ID;
			$context   = (string) get_post_meta( $post_id, self::META_CONTEXT, true );
			$entries[] = array(
				'post_id'   => $post_id,
				'func'      => self::func_name( $post_id ),
				'php_path'  => self::relative_php_path( $post_id ),
				'hash'      => (string) get_post_meta( $post_id, self::META_HASH, true ),
				'context'   => '' !== $context ? $context : 'shortcode',
				'hook'      => (string) get_post_meta( $post_id, self::META_HOOK, true ),
				'priority'  => (int) get_post_meta( $post_id, self::META_PRIORITY, true ),
			);
		}
		self::ensure_sandbox();
		self::write_file( self::manifest_path(), (string) wp_json_encode( $entries ) );
	}

	/**
	 * Reads the active-snippets manifest.
	 *
	 * @since 2.1.0
	 *
	 * @return array<int,array>
	 */
	public static function read_manifest(): array {
		$path = self::manifest_path();
		if ( ! file_exists( $path ) ) {
			return array();
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $data ) ? $data : array();
	}

	// -------------------------------------------------------------------------
	// Uninstall
	// -------------------------------------------------------------------------

	/**
	 * Deletes all snippets + their sandbox files. Called on uninstall.
	 *
	 * @since 2.1.0
	 */
	public static function uninstall_cleanup(): void {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $query->posts as $id ) {
			self::delete_file( self::php_path( (int) $id ) );
			wp_delete_post( (int) $id, true );
		}
		self::delete_file( self::manifest_path() );
	}
}
