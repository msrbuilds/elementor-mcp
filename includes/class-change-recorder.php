<?php
/**
 * Recorder façade for the change ledger.
 *
 * Every write site records through here rather than calling
 * EMCP_Tools_Change_Log::record() directly. The recorder (1) offloads large
 * before-images to the durable EMCP_Tools_Change_Blobs store so the ledger row
 * stays light, and (2) stamps an `after_hash` of the just-written state so
 * rollback can detect that the target changed underneath it (conflict guard).
 *
 * @package EMCP_Tools
 * @since   3.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change recorder.
 *
 * @since 3.10.0
 */
class EMCP_Tools_Change_Recorder {

	/** Before-images larger than this (bytes, JSON-encoded) go to the blob store. */
	const BLOB_THRESHOLD = 4096;

	/**
	 * Record an Elementor page edit.
	 *
	 * @param int    $post_id     Page id.
	 * @param array  $before_tree Prior _elementor_data tree.
	 * @param string $summary     Human summary.
	 * @param string $target      Human target label.
	 * @return string Ledger entry id ('' when suppressed).
	 */
	public static function record_elementor( int $post_id, array $before_tree, string $summary, string $target = '' ): string {
		if ( EMCP_Tools_Change_Log::$suppress ) {
			return '';
		}
		$rb               = self::attach_before( array( 'type' => 'elementor-data', 'post_id' => $post_id ), array( 'before' => $before_tree ) );
		$rb['after_hash'] = self::hash_elementor( $post_id );
		return EMCP_Tools_Change_Log::record( array(
			'domain'   => 'elementor',
			'action'   => 'page-edit',
			'target'   => $target,
			'summary'  => $summary,
			'rollback' => $rb,
		) );
	}

	/**
	 * Record a database write. The caller supplies the full ledger entry (with a
	 * `db-before-image` rollback ref); the recorder offloads a large `before_rows`
	 * array to the blob store.
	 *
	 * @param array $entry Ledger entry.
	 * @return string Ledger entry id.
	 */
	public static function record_db( array $entry ): string {
		if ( EMCP_Tools_Change_Log::$suppress ) {
			return '';
		}
		$rb = ( isset( $entry['rollback'] ) && is_array( $entry['rollback'] ) ) ? $entry['rollback'] : array();
		if ( isset( $rb['before_rows'] ) && is_array( $rb['before_rows'] ) ) {
			$heavy = array( 'before_rows' => $rb['before_rows'] );
			unset( $rb['before_rows'] );
			$rb = self::attach_before( $rb, $heavy );
		}
		$entry['rollback'] = $rb;
		return EMCP_Tools_Change_Log::record( $entry );
	}

	/**
	 * Record a filesystem write. Stamps an after-hash of the written file so a
	 * later external change is detected on rollback.
	 *
	 * @param array  $entry       Ledger entry (with a file-* rollback ref).
	 * @param string $written_abs Absolute path of the file just written.
	 * @return string Ledger entry id.
	 */
	public static function record_file( array $entry, string $written_abs ): string {
		if ( EMCP_Tools_Change_Log::$suppress ) {
			return '';
		}
		if ( isset( $entry['rollback'] ) && is_array( $entry['rollback'] ) ) {
			$entry['rollback']['after_hash'] = self::hash_file( $written_abs );
		}
		return EMCP_Tools_Change_Log::record( $entry );
	}

	/**
	 * Record a partial post edit (content/media/Gutenberg). `$before` is
	 * { fields:{col:val}, meta:{key:val|__DELETE__}, terms:{tax:[ids]} } — only
	 * the parts the write can change.
	 *
	 * @param int    $post_id Post id.
	 * @param array  $before  Before-image (fields/meta/terms).
	 * @param string $summary Human summary.
	 * @param string $target  Human target label.
	 * @param string $domain  Ledger domain (default 'content').
	 * @param string $action  Ledger action (default 'update-post').
	 * @return string
	 */
	public static function record_post_fields( int $post_id, array $before, string $summary, string $target = '', string $domain = 'content', string $action = 'update-post' ): string {
		if ( EMCP_Tools_Change_Log::$suppress ) {
			return '';
		}
		$rb               = self::attach_before( array( 'type' => 'post-fields', 'post_id' => $post_id ), array( 'before' => $before ) );
		$rb['after_scope'] = array(
			'fields' => array_keys( (array) ( $before['fields'] ?? array() ) ),
			'meta'   => array_values( array_unique( array_merge(
				array_keys( (array) ( $before['meta'] ?? array() ) ),
				array_keys( (array) ( $before['meta_rows'] ?? array() ) )
			) ) ),
			'terms'  => array_keys( (array) ( $before['terms'] ?? array() ) ),
		);
		$rb['after_hash'] = self::hash_post_scope( $post_id, $rb['after_scope'] );
		return EMCP_Tools_Change_Log::record( array(
			'domain'   => $domain,
			'action'   => $action,
			'target'   => $target,
			'summary'  => $summary,
			'rollback' => $rb,
		) );
	}

	/**
	 * Record a post creation (undo = delete the created post).
	 *
	 * @param int    $post_id Post id.
	 * @param string $summary Human summary.
	 * @param string $target  Human target label.
	 * @return string
	 */
	public static function record_post_create( int $post_id, string $summary, string $target = '' ): string {
		if ( EMCP_Tools_Change_Log::$suppress ) {
			return '';
		}
		return EMCP_Tools_Change_Log::record( array(
			'domain'   => 'content',
			'action'   => 'create-post',
			'target'   => $target,
			'summary'  => $summary,
			'rollback' => array( 'type' => 'post-create', 'post_id' => $post_id ),
		) );
	}

	/** Record a complete page/upload creation, guarded against subsequent edits. */
	public static function record_resource_create( int $post_id, string $domain, string $action ): string {
		if ( EMCP_Tools_Change_Log::$suppress ) {
			return '';
		}
		return EMCP_Tools_Change_Log::record( array(
			'domain' => $domain,
			'action' => $action,
			'target' => get_the_title( $post_id ) . ' (#' . $post_id . ')',
			'summary' => sprintf( 'Created %s #%d', $domain === 'media' ? 'attachment' : 'Elementor page', $post_id ),
			'rollback' => array(
				'type' => 'post-create',
				'post_id' => $post_id,
				'creation_guard' => true,
				'created_files' => 'attachment' === ( get_post( $post_id )->post_type ?? '' ) ? self::attachment_files( $post_id ) : array(),
				'after_hash' => self::hash_created_resource( $post_id ),
			),
		) );
	}

	/** Core-owned attachment files, including scaled originals and editor backups. */
	public static function attachment_files( int $post_id ): array {
		$main = (string) get_attached_file( $post_id );
		if ( '' === $main ) {
			return array();
		}
		$paths = array( $main );
		$dir = dirname( $main );
		$metadata = (array) wp_get_attachment_metadata( $post_id );
		foreach ( array_merge( (array) ( $metadata['sizes'] ?? array() ), (array) get_post_meta( $post_id, '_wp_attachment_backup_sizes', true ) ) as $size ) {
			if ( ! empty( $size['file'] ) ) {
				$paths[] = $dir . '/' . $size['file'];
			}
		}
		foreach ( array( 'original_image', 'source_image', 'animated_video', 'animated_video_poster', 'thumb' ) as $key ) {
			if ( ! empty( $metadata[ $key ] ) && is_string( $metadata[ $key ] ) ) {
				$paths[] = $dir . '/' . $metadata[ $key ];
			}
		}
		$paths = array_values( array_unique( $paths ) );
		sort( $paths );
		return $paths;
	}

	/** Hash current core fields, all persisted metadata/terms and attachment bytes. */
	public static function hash_created_resource( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$fields = (array) $post;
		// Edit timestamps and generated caches do not represent content changes.
		unset( $fields['post_modified'], $fields['post_modified_gmt'], $fields['filter'] );
		// WordPress advances the local date of an undated draft on every save,
		// including undo of an unrelated field. A real assigned date has GMT.
		if ( '0000-00-00 00:00:00' === ( $fields['post_date_gmt'] ?? '' ) && in_array( $fields['post_status'] ?? '', array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			unset( $fields['post_date'] );
		}
		$meta = (array) get_post_meta( $post_id );
		foreach ( array( '_edit_lock', '_edit_last', '_elementor_css', '_elementor_element_cache', '_elementor_page_assets' ) as $key ) {
			unset( $meta[ $key ] );
		}
		// Elementor records its data migrations per page when the page renders; viewing a page is not a change.
		foreach ( array_keys( $meta ) as $key ) {
			if ( 0 === strpos( (string) $key, '_elementor_migrations_state' ) ) {
				unset( $meta[ $key ] );
			}
		}
		$terms = array();
		foreach ( get_object_taxonomies( (string) ( $post->post_type ?? 'post' ) ) as $tax ) {
			$ids = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $ids ) ) {
				return '';
			}
			$ids = array_map( 'intval', $ids );
			sort( $ids );
			$terms[ $tax ] = $ids;
		}
		$files = array();
		if ( 'attachment' === ( $post->post_type ?? '' ) ) {
			foreach ( self::attachment_files( $post_id ) as $path ) {
				$digest = is_file( $path ) ? hash_file( 'sha256', $path ) : false;
				if ( false === $digest ) {
					return '';
				}
				$files[ $path ] = $digest;
			}
			if ( empty( $files ) ) {
				return '';
			}
		}
		ksort( $fields );
		ksort( $meta );
		ksort( $terms );
		$json = wp_json_encode( array( $fields, $meta, $terms, $files ) );
		return false === $json ? '' : hash( 'sha256', $json );
	}

	/**
	 * Record a post deletion. A trash is undone by untrashing; a force-delete is
	 * undone by re-inserting from the snapshot (post + meta + terms).
	 *
	 * @param int    $post_id  Post id.
	 * @param array  $snapshot Full snapshot (from snapshot_post()); only used when $forced.
	 * @param bool   $forced   True = permanent delete; false = trashed.
	 * @param string $summary  Human summary.
	 * @param string $target   Human target label.
	 * @return string
	 */
	public static function record_post_delete( int $post_id, array $snapshot, bool $forced, string $summary, string $target = '' ): string {
		if ( EMCP_Tools_Change_Log::$suppress ) {
			return '';
		}
		if ( $forced ) {
			$rb = self::attach_before( array( 'type' => 'post-restore', 'mode' => 'reinsert' ), array( 'snapshot' => $snapshot ) );
			// wp_delete_post() also accepts attachments, but a generic post
			// snapshot has no backup of the files WordPress deletes with them.
			if ( 'attachment' === ( $snapshot['post']['post_type'] ?? '' ) ) {
				$rb['partial'] = true;
			}
		} else {
			$rb = array( 'type' => 'post-restore', 'mode' => 'untrash', 'post_id' => $post_id );
		}
		return EMCP_Tools_Change_Log::record( array(
			'domain'   => 'content',
			'action'   => 'delete-post',
			'target'   => $target,
			'summary'  => $summary,
			'rollback' => $rb,
		) );
	}

	/**
	 * Record a settings write (one or more options). `$before_map` maps each
	 * changed option name to its prior value, or the marker '__ABSENT__' when the
	 * option did not exist.
	 *
	 * @param array  $before_map { option => prior value | '__ABSENT__' }.
	 * @param string $summary    Human summary.
	 * @param string $target     Human target label.
	 * @param string $domain     Ledger domain (default 'settings').
	 * @param string $action     Ledger action (default 'update-settings').
	 * @return string
	 */
	public static function record_options( array $before_map, string $summary, string $target = '', string $domain = 'settings', string $action = 'update-settings' ): string {
		if ( EMCP_Tools_Change_Log::$suppress || empty( $before_map ) ) {
			return '';
		}
		$rb = self::attach_before( array( 'type' => 'option' ), array( 'values' => $before_map ) );
		$rb['option_keys'] = array_keys( $before_map );
		$rb['after_hash']  = self::hash_options( array_keys( $before_map ) );
		return EMCP_Tools_Change_Log::record( array(
			'domain'   => $domain,
			'action'   => $action,
			'target'   => $target,
			'summary'  => $summary,
			'rollback' => $rb,
		) );
	}

	/**
	 * Record a redirect write. `$action` is 'create'|'update'|'delete'; `$before`
	 * is the rollback before-image: { id } for create (undo = delete the row) or
	 * { row: <full prior row> } for update/delete (undo = restore / re-insert).
	 *
	 * @param string $action  create|update|delete.
	 * @param array  $before  Before-image.
	 * @param string $summary Human summary.
	 * @param string $target  Human target label.
	 * @return string
	 */
	public static function record_redirect( string $action, array $before, string $summary, string $target = '' ): string {
		if ( EMCP_Tools_Change_Log::$suppress ) {
			return '';
		}
		return EMCP_Tools_Change_Log::record( array(
			'domain'   => 'redirect',
			'action'   => $action,
			'target'   => $target,
			'summary'  => $summary,
			'rollback' => array( 'type' => 'redirect-row', 'action' => $action, 'before' => $before ),
		) );
	}

	/**
	 * Record a post/term meta write (globals, ACF). `$before_map` maps each meta
	 * key to its prior value ('' / array() means it was unset → deleted on undo).
	 *
	 * @param string $object     'post' | 'term'.
	 * @param int    $id         Object id.
	 * @param array  $before_map { meta_key => prior value }.
	 * @param string $summary    Human summary.
	 * @param string $target     Human target label.
	 * @param string $domain     Ledger domain.
	 * @param string $action     Ledger action.
	 * @return string
	 */
	public static function record_meta( string $object, int $id, array $before_map, string $summary, string $target = '', string $domain = 'content', string $action = 'update' ): string {
		if ( EMCP_Tools_Change_Log::$suppress || empty( $before_map ) ) {
			return '';
		}
		$keys              = array_keys( $before_map );
		$rb                = self::attach_before( array( 'type' => 'meta-before-image', 'object' => $object, 'id' => $id, 'meta_keys' => $keys ), array( 'before' => $before_map ) );
		$rb['after_hash']  = self::hash_meta( $object, $id, $keys );
		return EMCP_Tools_Change_Log::record( array(
			'domain'   => $domain,
			'action'   => $action,
			'target'   => $target,
			'summary'  => $summary,
			'rollback' => $rb,
		) );
	}

	/**
	 * Hash of a set of options' current values.
	 *
	 * @param string[] $names Option names.
	 * @return string
	 */
	public static function hash_options( array $names ): string {
		$snap = array();
		sort( $names );
		foreach ( $names as $n ) {
			$snap[ (string) $n ] = maybe_serialize( get_option( (string) $n, null ) );
		}
		return sha1( (string) wp_json_encode( $snap ) );
	}

	/**
	 * Record a user creation (undo = delete the created user).
	 *
	 * @param int    $user_id User id.
	 * @param string $summary Human summary.
	 * @param string $target  Human target label.
	 * @return string
	 */
	public static function record_user_create( int $user_id, string $summary, string $target = '' ): string {
		if ( EMCP_Tools_Change_Log::$suppress ) {
			return '';
		}
		return EMCP_Tools_Change_Log::record( array(
			'domain'   => 'users',
			'action'   => 'create-user',
			'target'   => $target,
			'summary'  => $summary,
			'rollback' => array( 'type' => 'user-create', 'user_id' => $user_id ),
		) );
	}

	/**
	 * Record a user profile update. `$before` maps wp_update_user field keys
	 * (user_email, first_name, …) to their prior values.
	 *
	 * @param int    $user_id User id.
	 * @param array  $before  Prior field values.
	 * @param string $summary Human summary.
	 * @param string $target  Human target label.
	 * @return string
	 */
	public static function record_user_fields( int $user_id, array $before, string $summary, string $target = '' ): string {
		if ( EMCP_Tools_Change_Log::$suppress || empty( $before ) ) {
			return '';
		}
		$rb = self::attach_before( array( 'type' => 'user-fields', 'user_id' => $user_id ), array( 'before' => $before ) );
		$rb['field_keys'] = array_keys( $before );
		$rb['after_hash'] = self::hash_user_fields( $user_id, $rb['field_keys'] );
		return EMCP_Tools_Change_Log::record( array(
			'domain'   => 'users',
			'action'   => 'update-user',
			'target'   => $target,
			'summary'  => $summary,
			'rollback' => $rb,
		) );
	}

	/**
	 * Record an ACF field write. `$before` maps field KEY to its prior raw value;
	 * `$acf_target` is the post id or options-page selector the fields live on.
	 *
	 * @param int|string $acf_target Post id or options-page selector.
	 * @param array      $before     { field_key => prior raw value }.
	 * @param string     $summary    Human summary.
	 * @param string     $target     Human target label.
	 * @return string
	 */
	public static function record_acf_fields( $acf_target, array $before, string $summary, string $target = '' ): string {
		if ( EMCP_Tools_Change_Log::$suppress || empty( $before ) ) {
			return '';
		}
		$rb = self::attach_before( array( 'type' => 'acf-fields', 'acf_target' => $acf_target ), array( 'before' => $before ) );
		$rb['field_keys'] = array_keys( $before );
		$rb['after_hash'] = self::hash_acf_fields( $acf_target, $rb['field_keys'] );
		return EMCP_Tools_Change_Log::record( array(
			'domain'   => 'acf',
			'action'   => 'update-fields',
			'target'   => $target,
			'summary'  => $summary,
			'rollback' => $rb,
		) );
	}

	/**
	 * Snapshot an attachment for a reversible delete: post row + all meta + a
	 * trashed copy of every file (main + sub-sizes), so it can be re-created.
	 * MUST be called BEFORE wp_delete_attachment (which removes the files).
	 *
	 * @param int $att_id Attachment id.
	 * @return array { post, meta, files:[{orig,trashed}] } ('' post => empty).
	 */
	public static function snapshot_attachment( int $att_id ): array {
		$obj = get_post( $att_id );
		if ( ! $obj ) {
			return array();
		}
		return array(
			'post'  => (array) $obj,
			'meta'  => (array) get_post_meta( $att_id ),
			'files' => self::trash_attachment_files( $att_id ),
		);
	}

	/**
	 * Record a media deletion from a snapshot captured by snapshot_attachment().
	 *
	 * @param array  $snapshot Snapshot (post + meta + trashed files).
	 * @param int    $att_id   Attachment id.
	 * @param string $summary  Human summary.
	 * @param string $target   Human target label.
	 * @return string
	 */
	public static function record_attachment_delete( array $snapshot, int $att_id, string $summary, string $target = '' ): string {
		if ( EMCP_Tools_Change_Log::$suppress || empty( $snapshot ) ) {
			return '';
		}
		$rb = self::attach_before( array( 'type' => 'attachment-delete', 'att_id' => $att_id ), array( 'snapshot' => $snapshot ) );
		return EMCP_Tools_Change_Log::record( array(
			'domain'   => 'media',
			'action'   => 'delete-media',
			'target'   => $target,
			'summary'  => $summary,
			'rollback' => $rb,
		) );
	}

	/**
	 * Copy an attachment's files (main + sub-sizes) into a managed trash dir under
	 * uploads, so a delete is reversible. Returns [{ orig, trashed }].
	 *
	 * @param int $att_id Attachment id.
	 * @return array
	 */
	private static function trash_attachment_files( int $att_id ): array {
		$out = array();
		if ( ! function_exists( 'get_attached_file' ) ) {
			return $out;
		}
		$main = (string) get_attached_file( $att_id );
		if ( '' === $main ) {
			return $out;
		}
		$dir   = dirname( $main );
		$paths = array( $main );
		$amd   = function_exists( 'wp_get_attachment_metadata' ) ? wp_get_attachment_metadata( $att_id ) : array();
		if ( is_array( $amd ) && ! empty( $amd['sizes'] ) && is_array( $amd['sizes'] ) ) {
			foreach ( $amd['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$paths[] = $dir . '/' . $size['file'];
				}
			}
		}
		$up    = function_exists( 'wp_get_upload_dir' ) ? wp_get_upload_dir() : array( 'basedir' => sys_get_temp_dir() );
		$trash = rtrim( (string) ( $up['basedir'] ?? sys_get_temp_dir() ), '/\\' ) . '/emcp-originals/trash/' . $att_id;
		if ( ! is_dir( $trash ) && function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( $trash );
		}
		foreach ( array_unique( $paths ) as $orig ) {
			if ( is_file( $orig ) && is_dir( $trash ) ) {
				$dest = $trash . '/' . basename( $orig );
				if ( @copy( $orig, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$out[] = array( 'orig' => $orig, 'trashed' => $dest );
				}
			}
		}
		return $out;
	}

	/**
	 * Full snapshot of a post for reversible force-delete: post row + all meta +
	 * its terms across every taxonomy for the type.
	 *
	 * @param int $post_id Post id.
	 * @return array
	 */
	public static function snapshot_post( int $post_id ): array {
		$obj = get_post( $post_id );
		if ( ! $obj ) {
			return array();
		}
		$post  = (array) $obj;
		$terms = array();
		foreach ( get_object_taxonomies( (string) ( $post['post_type'] ?? 'post' ) ) as $tax ) {
			$ids = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $ids ) && ! empty( $ids ) ) {
				$terms[ $tax ] = array_map( 'intval', $ids );
			}
		}
		return array(
			'post'  => $post,
			'meta'  => (array) get_post_meta( $post_id ),
			'terms' => $terms,
		);
	}

	// ---------------------------------------------------------------------
	// Before-image offload
	// ---------------------------------------------------------------------

	/**
	 * Attach a heavy before-image to a rollback ref — inline when small, or in
	 * the blob store (as `blob_id`) when it exceeds BLOB_THRESHOLD.
	 *
	 * @param array $rb    Rollback ref (routing fields).
	 * @param array $heavy The bulk before-image, e.g. { before: [...] }.
	 * @return array
	 */
	public static function attach_before( array $rb, array $heavy ): array {
		$json = (string) wp_json_encode( $heavy );
		if ( strlen( $json ) > self::BLOB_THRESHOLD && class_exists( 'EMCP_Tools_Change_Blobs' ) ) {
			$blob = EMCP_Tools_Change_Blobs::put( $heavy );
			if ( '' !== $blob ) {
				$rb['blob_id'] = $blob;
				return $rb;
			}
		}
		return array_merge( $rb, $heavy );
	}

	/**
	 * The rollback ref with its offloaded before-image merged back in (the inverse of
	 * attach_before()). A ref without a blob, or whose blob is gone, is returned unchanged.
	 *
	 * @param array $rb Rollback ref.
	 * @return array
	 */
	public static function resolve_before( array $rb ): array {
		if ( empty( $rb['blob_id'] ) || ! class_exists( 'EMCP_Tools_Change_Blobs' ) ) {
			return $rb;
		}
		$heavy = EMCP_Tools_Change_Blobs::get( (string) $rb['blob_id'] );
		return is_array( $heavy ) ? array_merge( $rb, $heavy ) : $rb;
	}

	// ---------------------------------------------------------------------
	// State hashes (conflict guard)
	// ---------------------------------------------------------------------

	/**
	 * Hash of a page's current Elementor data.
	 *
	 * @param int $post_id Page id.
	 * @return string
	 */
	public static function hash_elementor( int $post_id ): string {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		return sha1( is_string( $raw ) ? $raw : (string) wp_json_encode( $raw ) );
	}

	/**
	 * Hash of a post's current core fields (title/content/status/excerpt/name/
	 * parent/menu_order) — the conflict indicator for post-fields rollbacks.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function hash_post( int $post_id ): string {
		$p = get_post( $post_id );
		if ( ! $p ) {
			return '';
		}
		return sha1( (string) wp_json_encode( array(
			(string) ( $p->post_title ?? '' ),
			(string) ( $p->post_content ?? '' ),
			(string) ( $p->post_status ?? '' ),
			(string) ( $p->post_excerpt ?? '' ),
			(string) ( $p->post_name ?? '' ),
			(int) ( $p->post_parent ?? 0 ),
			(int) ( $p->menu_order ?? 0 ),
		) ) );
	}

	/**
	 * Hash exactly the state a post-fields inverse will replace. Meta uses all
	 * rows so absence, empty values and duplicate rows have distinct hashes.
	 * An empty hash signals an unreadable resource, never an absent conflict.
	 *
	 * @param int   $post_id Post id.
	 * @param array $scope   Lists of field, meta and taxonomy names.
	 * @return string
	 */
	public static function hash_post_scope( int $post_id, array $scope ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$state = array( 'fields' => array(), 'meta' => array(), 'terms' => array() );
		foreach ( (array) ( $scope['fields'] ?? array() ) as $key ) {
			$state['fields'][ $key ] = isset( $post->$key ) ? (string) $post->$key : null;
		}
		foreach ( (array) ( $scope['meta'] ?? array() ) as $key ) {
			$state['meta'][ $key ] = get_post_meta( $post_id, (string) $key, false );
		}
		foreach ( (array) ( $scope['terms'] ?? array() ) as $tax ) {
			$ids = wp_get_object_terms( $post_id, (string) $tax, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $ids ) ) {
				return '';
			}
			$ids = array_map( 'intval', (array) $ids );
			sort( $ids, SORT_NUMERIC );
			$state['terms'][ $tax ] = $ids;
		}
		foreach ( $state as &$part ) {
			ksort( $part );
		}
		unset( $part );
		$json = wp_json_encode( $state );
		return false === $json ? '' : hash( 'sha256', $json );
	}

	/** Hash the profile fields replaced by a user update. */
	public static function hash_user_fields( int $user_id, array $keys ): string {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}
		sort( $keys );
		$state = array();
		foreach ( $keys as $key ) {
			$state[ $key ] = (string) ( $user->$key ?? '' );
		}
		$json = wp_json_encode( $state );
		return false === $json ? '' : hash( 'sha256', $json );
	}

	/** Hash raw ACF values, using the same field keys as the saved snapshot. */
	public static function hash_acf_fields( $target, array $keys ): string {
		if ( ! function_exists( 'get_field' ) ) {
			return '';
		}
		sort( $keys );
		$state = array();
		foreach ( $keys as $key ) {
			$state[ $key ] = get_field( (string) $key, $target, false );
		}
		$json = wp_json_encode( $state );
		return false === $json ? '' : hash( 'sha256', $json );
	}

	/**
	 * Hash of a file's current bytes ('' when absent).
	 *
	 * @param string $abs Absolute path.
	 * @return string
	 */
	public static function hash_file( string $abs ): string {
		return ( '' !== $abs && is_file( $abs ) ) ? (string) sha1_file( $abs ) : '';
	}

	/**
	 * Hash of an option's current value.
	 *
	 * @param string $name Option name.
	 * @return string
	 */
	public static function hash_option( string $name ): string {
		return sha1( maybe_serialize( get_option( $name, null ) ) );
	}

	/**
	 * Hash of a subset of an object's current meta values.
	 *
	 * @param string   $object 'post' | 'term'.
	 * @param int      $id     Object id.
	 * @param string[] $keys   Meta keys to include.
	 * @return string
	 */
	public static function hash_meta( string $object, int $id, array $keys ): string {
		$snap = array();
		sort( $keys );
		foreach ( $keys as $k ) {
			$k          = (string) $k;
			$snap[ $k ] = 'term' === $object ? get_term_meta( $id, $k, true ) : get_post_meta( $id, $k, true );
		}
		return sha1( (string) wp_json_encode( $snap ) );
	}
}
