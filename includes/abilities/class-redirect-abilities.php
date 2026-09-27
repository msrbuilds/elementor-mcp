<?php
/**
 * Redirect Manager MCP abilities.
 *
 * CRUD over the {prefix}emcp_redirects store plus a read-only broken-internal-
 * link scan. Reads (list-redirects/find-broken-links) are enabled by default;
 * the three writes ship disabled-by-default. All require manage_options. Every
 * write routes through EMCP_Tools_Change_Recorder so it is reversible in the
 * History tab.
 *
 * @package EMCP_Tools
 * @since   3.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the Redirect Manager abilities.
 *
 * @since 3.11.0
 */
class EMCP_Tools_Redirect_Abilities {

	/**
	 * The suggestion queue option + cap.
	 */
	const SUGGESTIONS_OPTION = 'emcp_tools_redirect_suggestions';
	const SUGGESTIONS_CAP    = 50;

	/**
	 * Names of the abilities actually registered by register().
	 *
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * Returns the names of all abilities registered by this group.
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/**
	 * Registers this group's MCP abilities.
	 */
	public function register(): void {
		$this->register_list_redirects();
		$this->register_create_redirect();
		$this->register_update_redirect();
		$this->register_delete_redirect();
		$this->register_find_broken_links();
	}

	/**
	 * Management permission — admin only.
	 *
	 * @return bool
	 */
	public function check_manage_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ---------------------------------------------------------------------
	// list-redirects
	// ---------------------------------------------------------------------

	private function register_list_redirects(): void {
		$this->ability_names[] = 'emcp-tools/list-redirects';
		emcp_tools_register_ability(
			'emcp-tools/list-redirects',
			array(
				'label'               => __( 'List Redirects', 'emcp-tools' ),
				'description'         => __( 'Lists the site\'s managed 301/302 redirects (source path → target, status code, enabled, hit count). Filter by enabled or a search string; paginated. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_redirects' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'enabled'  => array( 'type' => 'boolean', 'description' => __( 'Filter by enabled state. Omit for all.', 'emcp-tools' ) ),
						'search'   => array( 'type' => 'string', 'description' => __( 'Match source or target.', 'emcp-tools' ) ),
						'per_page' => array( 'type' => 'integer', 'description' => __( '1-500. Default 100.', 'emcp-tools' ) ),
						'page'     => array( 'type' => 'integer', 'description' => __( 'Default 1.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'redirects' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					'total'     => array( 'type' => 'integer' ),
				) ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input Tool input.
	 * @return array|WP_Error
	 */
	public function execute_list_redirects( $input ) {
		if ( ! class_exists( 'EMCP_Tools_Redirect_Store' ) ) {
			return new \WP_Error( 'unavailable', __( 'Redirect store unavailable.', 'emcp-tools' ) );
		}
		$per_page = max( 1, min( 500, absint( $input['per_page'] ?? 100 ) ) );
		$page     = max( 1, absint( $input['page'] ?? 1 ) );
		$filters  = array( 'limit' => $per_page, 'offset' => ( $page - 1 ) * $per_page );
		if ( array_key_exists( 'enabled', $input ) ) {
			$filters['enabled'] = (bool) $input['enabled'];
		}
		if ( ! empty( $input['search'] ) ) {
			$filters['search'] = sanitize_text_field( (string) $input['search'] );
		}
		return array(
			'redirects' => EMCP_Tools_Redirect_Store::all( $filters ),
			'total'     => EMCP_Tools_Redirect_Store::count( $filters ),
		);
	}

	// ---------------------------------------------------------------------
	// create-redirect
	// ---------------------------------------------------------------------

	private function register_create_redirect(): void {
		$this->ability_names[] = 'emcp-tools/create-redirect';
		emcp_tools_register_ability(
			'emcp-tools/create-redirect',
			array(
				'label'               => __( 'Create Redirect', 'emcp-tools' ),
				'description'         => __( 'Creates a 301/302 redirect from a source path to a target URL or post. Use this after deleting or renaming a page so the old URL still resolves. Warns (but allows) when the source shadows a live published page.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_create_redirect' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'source'         => array( 'type' => 'string', 'description' => __( 'The old path to redirect from, e.g. /old-page.', 'emcp-tools' ) ),
						'target'         => array( 'type' => 'string', 'description' => __( 'Destination URL (absolute or site-relative). Provide this OR target_post_id.', 'emcp-tools' ) ),
						'target_post_id' => array( 'type' => 'integer', 'description' => __( 'Destination post ID (its permalink is resolved live). Provide this OR target.', 'emcp-tools' ) ),
						'status_code'    => array( 'type' => 'integer', 'enum' => array( 301, 302 ), 'description' => __( 'Default 301 (permanent).', 'emcp-tools' ) ),
						'ignore_query'   => array( 'type' => 'boolean', 'description' => __( 'Match regardless of query string. Default true. False matches only the query string given in source (for example /page?ref=ad).', 'emcp-tools' ) ),
					),
					'required'   => array( 'source' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'redirect' => array( 'type' => 'object' ), 'warning' => array( 'type' => 'string' ),
				) ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input Tool input.
	 * @return array|WP_Error
	 */
	public function execute_create_redirect( $input ) {
		if ( ! class_exists( 'EMCP_Tools_Redirect_Store' ) ) {
			return new \WP_Error( 'unavailable', __( 'Redirect store unavailable.', 'emcp-tools' ) );
		}
		$row = EMCP_Tools_Redirect_Store::create( array(
			'source'         => (string) ( $input['source'] ?? '' ),
			'target'         => isset( $input['target'] ) ? (string) $input['target'] : null,
			'target_post_id' => isset( $input['target_post_id'] ) ? absint( $input['target_post_id'] ) : null,
			'status_code'    => (int) ( $input['status_code'] ?? 301 ),
			'ignore_query'   => array_key_exists( 'ignore_query', $input ) ? (bool) $input['ignore_query'] : true,
		) );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( class_exists( 'EMCP_Tools_Change_Recorder' ) ) {
			EMCP_Tools_Change_Recorder::record_redirect(
				'create',
				array( 'id' => (int) $row['id'] ),
				sprintf( 'Created redirect %s', $row['source_path'] ),
				(string) $row['source_path']
			);
		}
		$result = array( 'redirect' => $row );
		$warn   = EMCP_Tools_Redirect_Store::shadow_warning( (string) $row['source_path'] );
		if ( '' !== $warn ) {
			$result['warning'] = $warn;
		}
		return $result;
	}

	// ---------------------------------------------------------------------
	// update-redirect
	// ---------------------------------------------------------------------

	private function register_update_redirect(): void {
		$this->ability_names[] = 'emcp-tools/update-redirect';
		emcp_tools_register_ability(
			'emcp-tools/update-redirect',
			array(
				'label'               => __( 'Update Redirect', 'emcp-tools' ),
				'description'         => __( 'Updates an existing redirect by id. Only supplied fields change (source, target/target_post_id, status_code, ignore_query, enabled).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_redirect' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'             => array( 'type' => 'integer', 'description' => __( 'Redirect id.', 'emcp-tools' ) ),
						'source'         => array( 'type' => 'string' ),
						'target'         => array( 'type' => 'string' ),
						'target_post_id' => array( 'type' => 'integer' ),
						'status_code'    => array( 'type' => 'integer', 'enum' => array( 301, 302 ) ),
						'ignore_query'   => array( 'type' => 'boolean' ),
						'enabled'        => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'redirect' => array( 'type' => 'object' ) ) ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input Tool input.
	 * @return array|WP_Error
	 */
	public function execute_update_redirect( $input ) {
		if ( ! class_exists( 'EMCP_Tools_Redirect_Store' ) ) {
			return new \WP_Error( 'unavailable', __( 'Redirect store unavailable.', 'emcp-tools' ) );
		}
		$id    = absint( $input['id'] ?? 0 );
		$prior = EMCP_Tools_Redirect_Store::get( $id );
		if ( ! $prior ) {
			return new \WP_Error( 'not_found', __( 'Redirect not found.', 'emcp-tools' ) );
		}
		$patch = array();
		foreach ( array( 'source', 'target', 'target_post_id', 'status_code', 'ignore_query', 'enabled' ) as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$patch[ $k ] = $input[ $k ];
			}
		}
		$row = EMCP_Tools_Redirect_Store::update( $id, $patch );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( class_exists( 'EMCP_Tools_Change_Recorder' ) ) {
			EMCP_Tools_Change_Recorder::record_redirect(
				'update',
				array( 'row' => $prior ),
				sprintf( 'Updated redirect %s', $row['source_path'] ),
				(string) $row['source_path']
			);
		}
		return array( 'redirect' => $row );
	}

	// ---------------------------------------------------------------------
	// delete-redirect
	// ---------------------------------------------------------------------

	private function register_delete_redirect(): void {
		$this->ability_names[] = 'emcp-tools/delete-redirect';
		emcp_tools_register_ability(
			'emcp-tools/delete-redirect',
			array(
				'label'               => __( 'Delete Redirect', 'emcp-tools' ),
				'description'         => __( 'Deletes a redirect by id. Reversible from the History tab. Destructive.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_delete_redirect' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'description' => __( 'Redirect id.', 'emcp-tools' ) ) ),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'success' => array( 'type' => 'boolean' ), 'id' => array( 'type' => 'integer' ),
				) ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input Tool input.
	 * @return array|WP_Error
	 */
	public function execute_delete_redirect( $input ) {
		if ( ! class_exists( 'EMCP_Tools_Redirect_Store' ) ) {
			return new \WP_Error( 'unavailable', __( 'Redirect store unavailable.', 'emcp-tools' ) );
		}
		$id    = absint( $input['id'] ?? 0 );
		$prior = EMCP_Tools_Redirect_Store::get( $id );
		if ( ! $prior ) {
			return new \WP_Error( 'not_found', __( 'Redirect not found.', 'emcp-tools' ) );
		}
		$ok = EMCP_Tools_Redirect_Store::delete( $id );
		if ( $ok && class_exists( 'EMCP_Tools_Change_Recorder' ) ) {
			EMCP_Tools_Change_Recorder::record_redirect(
				'delete',
				array( 'row' => $prior ),
				sprintf( 'Deleted redirect %s', $prior['source_path'] ),
				(string) $prior['source_path']
			);
		}
		return array( 'success' => (bool) $ok, 'id' => $id );
	}

	// ---------------------------------------------------------------------
	// find-broken-links
	// ---------------------------------------------------------------------

	private function register_find_broken_links(): void {
		$this->ability_names[] = 'emcp-tools/find-broken-links';
		emcp_tools_register_ability(
			'emcp-tools/find-broken-links',
			array(
				'label'               => __( 'Find Broken Links', 'emcp-tools' ),
				'description'         => __( 'Scans published content for internal links that point at trashed/missing pages (dead), or at a path that already has a redirect (should link straight to the target). Read-only — proposes fixes, changes nothing. Bounded by max_posts/max_seconds.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_find_broken_links' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'max_posts'   => array( 'type' => 'integer', 'description' => __( '1-2000. Default 200.', 'emcp-tools' ) ),
						'max_seconds' => array( 'type' => 'integer', 'description' => __( '1-60. Default 10.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'scanned'  => array( 'type' => 'integer' ),
					'findings' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					'partial'  => array( 'type' => 'boolean' ),
				) ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input Tool input.
	 * @return array|WP_Error
	 */
	public function execute_find_broken_links( $input ) {
		return $this->scan_broken_links( is_array( $input ) ? $input : array() );
	}

	/**
	 * Walk published content and report internal links that are dead or already
	 * redirected. Bounded by max_posts + max_seconds (partial flag when a cap
	 * trips). Read-only.
	 *
	 * @param array $opts { max_posts:int, max_seconds:int }.
	 * @return array { scanned, findings, partial }.
	 */
	public function scan_broken_links( array $opts ): array {
		$max_posts   = max( 1, min( 2000, (int) ( $opts['max_posts'] ?? 200 ) ) );
		$max_seconds = max( 1, min( 60, (int) ( $opts['max_seconds'] ?? 10 ) ) );
		$start       = microtime( true );

		$sources = array();
		if ( class_exists( 'EMCP_Tools_Redirect_Store' ) ) {
			foreach ( EMCP_Tools_Redirect_Store::all( array( 'enabled' => true, 'limit' => 500 ) ) as $r ) {
				$sources[] = (string) $r['source_path'];
			}
		}

		$types = array_values( get_post_types( array( 'public' => true ), 'names' ) );
		$query = new WP_Query( array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => $max_posts,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		) );

		$findings = array();
		$scanned  = 0;
		$partial  = false;
		foreach ( (array) $query->posts as $pid ) {
			if ( microtime( true ) - $start > $max_seconds ) {
				$partial = true;
				break;
			}
			++$scanned;
			$content = (string) get_post_field( 'post_content', (int) $pid );
			foreach ( $this->extract_hrefs( $content ) as $href ) {
				$c = $this->classify_href( $href, $sources );
				if ( in_array( $c['kind'], array( 'dead', 'redirected' ), true ) ) {
					$findings[] = array(
						'post_id'    => (int) $pid,
						'post_title' => get_the_title( (int) $pid ),
						'href'       => $href,
						'kind'       => $c['kind'],
						'suggestion' => $c['suggestion'] ?? '',
					);
				}
			}
		}
		return array( 'scanned' => $scanned, 'findings' => $findings, 'partial' => $partial );
	}

	/**
	 * Extract href values from HTML/block content.
	 *
	 * @param string $content Post content.
	 * @return string[]
	 */
	private function extract_hrefs( string $content ): array {
		if ( ! preg_match_all( '#href=["\']([^"\']+)["\']#i', $content, $m ) ) {
			return array();
		}
		return array_values( array_unique( $m[1] ) );
	}

	/**
	 * Classify a single href. `$redirect_sources` is the set of enabled,
	 * normalized redirect source paths. Returns { kind, suggestion? } where kind
	 * is external | ok | dead | redirected.
	 *
	 * @param string   $href            Raw href.
	 * @param string[] $redirect_sources Enabled normalized source paths.
	 * @return array
	 */
	public function classify_href( string $href, array $redirect_sources ): array {
		$path = $this->internal_path( $href );
		if ( '' === $path ) {
			return array( 'kind' => 'external' );
		}
		if ( in_array( $path, $redirect_sources, true ) ) {
			return array( 'kind' => 'redirected', 'suggestion' => __( 'This path already has a redirect — link straight to the target instead.', 'emcp-tools' ) );
		}
		$pid = function_exists( 'url_to_postid' ) ? (int) url_to_postid( home_url( $path ) ) : 0;
		if ( ! $pid ) {
			return array( 'kind' => 'dead', 'suggestion' => __( 'Points at a URL with no published content — fix the link or add a redirect.', 'emcp-tools' ) );
		}
		$status = function_exists( 'get_post_status' ) ? get_post_status( $pid ) : 'publish';
		if ( ! in_array( $status, array( 'publish', 'inherit' ), true ) ) {
			return array( 'kind' => 'dead', 'suggestion' => __( 'Points at trashed/unpublished content — fix the link or add a redirect.', 'emcp-tools' ) );
		}
		return array( 'kind' => 'ok' );
	}

	/**
	 * Return the normalized internal path of an href, or '' when it is external,
	 * an anchor, or a non-http scheme.
	 *
	 * @param string $href Raw href.
	 * @return string
	 */
	private function internal_path( string $href ): string {
		$href = trim( $href );
		if ( '' === $href || 0 === strpos( $href, '#' ) || 0 === strpos( $href, 'mailto:' ) || 0 === strpos( $href, 'tel:' ) ) {
			return '';
		}
		$parts = wp_parse_url( $href );
		$home  = wp_parse_url( home_url( '/' ) );
		if ( ! empty( $parts['host'] ) ) {
			if ( empty( $home['host'] ) || strtolower( $parts['host'] ) !== strtolower( (string) $home['host'] ) ) {
				return ''; // External host.
			}
		}
		if ( ! class_exists( 'EMCP_Tools_Redirect_Store' ) ) {
			return '';
		}
		return EMCP_Tools_Redirect_Store::normalize_path( $href );
	}

	// ---------------------------------------------------------------------
	// Suggestion queue (used by the content abilities on delete/rename)
	// ---------------------------------------------------------------------

	/**
	 * Push a suggested redirect onto the capped queue. De-dupes by old_path
	 * (newest wins). Never throws — a suggestion must not fail a delete/update.
	 *
	 * @param string $old_path       Normalized dead path.
	 * @param string $reason         'post-deleted' | 'slug-changed'.
	 * @param int    $source_post_id The post that produced this.
	 */
	public static function push_suggestion( string $old_path, string $reason, int $source_post_id ): void {
		try {
			$old_path = class_exists( 'EMCP_Tools_Redirect_Store' ) ? EMCP_Tools_Redirect_Store::normalize_path( $old_path ) : $old_path;
			if ( '' === $old_path || '/' === $old_path ) {
				return;
			}
			$queue = get_option( self::SUGGESTIONS_OPTION, array() );
			if ( ! is_array( $queue ) ) {
				$queue = array();
			}
			// De-dupe: drop any existing entry for this path.
			$queue = array_values( array_filter( $queue, static function ( $s ) use ( $old_path ) {
				return ! ( is_array( $s ) && ( $s['old_path'] ?? '' ) === $old_path );
			} ) );
			$queue[] = array(
				'old_path'       => $old_path,
				'reason'         => $reason,
				'source_post_id' => $source_post_id,
				'suggested_at'   => function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ),
			);
			if ( count( $queue ) > self::SUGGESTIONS_CAP ) {
				$queue = array_slice( $queue, -self::SUGGESTIONS_CAP );
			}
			update_option( self::SUGGESTIONS_OPTION, $queue, false );
		} catch ( \Throwable $e ) {
			// Never let a suggestion break the underlying write.
			return;
		}
	}
}
