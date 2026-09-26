<?php
/**
 * Unified change ledger + rollback dispatcher (AI-safe transactions).
 *
 * A single recorder every write site calls, storing lightweight rollback-capable
 * entries in one capped option, plus a rollback dispatcher that undoes an entry
 * via the right mechanism (re-save prior Elementor data, restore/delete a file
 * backup, or inverse a $wpdb write from a before-image).
 *
 * @package EMCP_Tools
 * @since   3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The storage layer is loaded here, not by the bootstrap, because standalone
// fixtures load the ledger on its own.
require_once __DIR__ . '/changes/class-change-names.php';
require_once __DIR__ . '/changes/interface-change-storage.php';
require_once __DIR__ . '/changes/class-change-codec.php';
require_once __DIR__ . '/changes/class-change-memory-filter.php';
require_once __DIR__ . '/changes/class-change-wpdb-storage.php';
require_once __DIR__ . '/changes/class-change-store.php';

/**
 * The change ledger.
 *
 * @since 3.3.0
 */
class EMCP_Tools_Change_Log {

	const OPTION    = 'emcp_tools_changelog';
	const MAX_COUNT = 500;     // Option store only: the table has retention instead.
	const MAX_BYTES = 2097152; // Option store only.

	/**
	 * When true, record() is a no-op. Set during rollback so the rollback's own
	 * write (e.g. re-saving Elementor data) does not create a spurious entry.
	 *
	 * @var bool
	 */
	public static $suppress = false;

	/** @var EMCP_Tools_Change_Store|null */
	private static $store = null;

	/** @var WP_Error|null Why the last delete() or clear() did nothing. */
	private static $last_error = null;

	/**
	 * Use a storage (tests and the live acceptance); null returns to $wpdb.
	 *
	 * @param EMCP_Tools_Change_Storage|null $s     Storage.
	 * @param EMCP_Tools_Lease|null          $lease Lease for the migration guard.
	 */
	public static function use_storage( ?EMCP_Tools_Change_Storage $s, ?EMCP_Tools_Lease $lease = null ): void {
		self::$store = $s ? new EMCP_Tools_Change_Store( $s, $lease ) : null;
	}

	/** The store (spec 9.1 writer protocol). */
	public static function store(): EMCP_Tools_Change_Store {
		if ( null === self::$store ) {
			self::$store = new EMCP_Tools_Change_Store( new EMCP_Tools_Change_WPDB_Storage() );
		}
		return self::$store;
	}

	/** Why the last delete() or clear() did nothing (409 history_busy / history_upgrading), or null. */
	public static function last_error(): ?WP_Error {
		return self::$last_error;
	}

	/**
	 * Append an entry. Returns its id, or '' when suppressed or persistence fails.
	 *
	 * @param array $entry { domain, action, target?, summary?, rollback?, client?, session? }.
	 * @return string
	 */
	public static function record( array $entry ): string {
		if ( self::$suppress ) {
			return '';
		}
		$id    = self::uid();
		$ctx   = class_exists( 'EMCP_Tools_Request_Context' ) ? EMCP_Tools_Request_Context::current() : array(
			'client'  => '',
			'session' => '',
		);
		$entry = array_merge(
			array(
				'target'   => '',
				'summary'  => '',
				'rollback' => null,
				'client'   => $ctx['client'],
				'session'  => $ctx['session'],
			),
			$entry,
			array(
				'id'             => $id,
				'ts'             => time(),
				'ts_us'          => (int) round( microtime( true ) * 1000000 ),
				'user_id'        => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
				'user_login'     => self::current_login(),
				'rolled_back'    => false,
				'rolled_back_at' => null,
			)
		);
		$dropped = array();
		if ( '' === self::store()->add( $entry, $dropped ) ) {
			self::not_recorded( $entry );
			return '';
		}
		self::forget_blobs( $dropped );
		return $id;
	}

	/**
	 * The change happened but its undo record did not persist (spec 9.1 step 2):
	 * its blob is deleted, the MCP log row says so, the History banner counts it.
	 *
	 * @param array $entry The entry that could not be stored.
	 */
	private static function not_recorded( array $entry ): void {
		self::forget_blobs( array( $entry ) );
		if ( class_exists( 'EMCP_Tools_Request_Context' ) ) {
			EMCP_Tools_Request_Context::flag_ledger_not_recorded();
		}
		$s = self::store()->storage();
		$u = $s->get_meta( EMCP_Tools_Change_Names::unrecorded() );
		$s->set_meta(
			EMCP_Tools_Change_Names::unrecorded(),
			array(
				'count'       => ( is_array( $u ) ? (int) ( $u['count'] ?? 0 ) : 0 ) + 1,
				'last_ts'     => time(),
				'last_target' => (string) ( $entry['target'] ?? '' ),
				'last_tool'   => (string) ( $entry['action'] ?? '' ),
			)
		);
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'EMCP Tools: a change to %s (%s) was made but could not be added to History.', (string) ( $entry['target'] ?? '' ), (string) ( $entry['action'] ?? '' ) ) );
		}
	}

	/**
	 * The newest 500 entries, oldest first. Internal and kept for backward
	 * compatibility; History and list-changes use query().
	 *
	 * @return array[]
	 */
	public static function all(): array {
		return array_reverse( self::store()->select( array( 'limit' => self::MAX_COUNT ) ) );
	}

	/** Number of entries. */
	public static function count(): int {
		return self::store()->count();
	}

	/**
	 * A page of entries, newest first.
	 *
	 * @param array $args domain, client, session, user_id, rolled_back, search, since, until,
	 *                    cursor (the seq of the last row seen), limit (default 50, max 200).
	 * @return array{items: array, next_cursor: ?int}
	 */
	public static function query( array $args ): array {
		$limit = max( 1, min( 200, (int) ( $args['limit'] ?? 50 ) ) );
		$sel   = array_intersect_key( $args, array_flip( array( 'domain', 'client', 'session', 'user_id', 'rolled_back', 'search', 'since', 'until' ) ) );
		if ( ! empty( $args['cursor'] ) ) {
			$sel['before_seq'] = (int) $args['cursor'];
		}
		$rows = self::store()->select(
			$sel + array(
				'order' => 'desc',
				'limit' => $limit + 1,
			)
		);
		$more = count( $rows ) > $limit;
		$rows = array_slice( $rows, 0, $limit );
		return array(
			'items'       => $rows,
			'next_cursor' => ( $more && $rows ) ? (int) $rows[ count( $rows ) - 1 ]['seq'] : null,
		);
	}

	/**
	 * Fetch an entry by id.
	 *
	 * @param string $id Entry id.
	 * @return array|null
	 */
	public static function get( string $id ): ?array {
		return '' === $id ? null : self::store()->find( $id );
	}

	/**
	 * Flag an entry as rolled back.
	 *
	 * @param string $id Entry id.
	 */
	public static function mark_rolled_back( string $id ): bool {
		$r = self::store()->update(
			$id,
			array(
				'rolled_back'    => true,
				'rolled_back_at' => time(),
			)
		);
		return true === $r;
	}

	/**
	 * Delete a single entry from the ledger.
	 *
	 * Note: an entry carries its own before-image, so deleting a reversible
	 * entry permanently forfeits the ability to roll it back. The admin UI
	 * warns about this before calling.
	 *
	 * @since 3.4.2
	 * @param string $id Entry id.
	 * @return bool True when an entry was removed; see last_error() otherwise.
	 */
	public static function delete( string $id ): bool {
		self::$last_error = null;
		if ( '' === $id ) {
			return false;
		}
		$removed = self::store()->remove( $id );
		if ( is_wp_error( $removed ) ) {
			self::$last_error = $removed;
			return false;
		}
		if ( ! is_array( $removed ) ) {
			return false;
		}
		self::forget_blobs( array( $removed ) );
		return true;
	}

	/**
	 * Wipe the whole ledger.
	 *
	 * @since 3.4.2
	 * @return int Number of entries removed; see last_error() when 0.
	 */
	public static function clear(): int {
		self::$last_error = null;
		$rows             = self::store()->remove_all();
		if ( is_wp_error( $rows ) ) {
			self::$last_error = $rows;
			return 0;
		}
		self::forget_blobs( $rows );
		return count( $rows );
	}

	/**
	 * Delete the out-of-band before-image blobs for a set of dropped/removed
	 * ledger rows, so evicted entries don't orphan their snapshots.
	 *
	 * @param array $rows Ledger rows being removed.
	 */
	public static function forget_blobs( array $rows ): void {
		if ( ! class_exists( 'EMCP_Tools_Change_Blobs' ) ) {
			return;
		}
		foreach ( $rows as $r ) {
			$bid = ( isset( $r['rollback']['blob_id'] ) ) ? (string) $r['rollback']['blob_id'] : '';
			if ( '' !== $bid ) {
				EMCP_Tools_Change_Blobs::delete( $bid );
			}
		}
	}

	/**
	 * Undo an entry by id, dispatching on its rollback type. Marks the entry
	 * rolled_back and records a compensating entry.
	 *
	 * @param string $id    Entry id.
	 * @param bool   $force  Roll back even if the target changed since (skips the conflict guard).
	 * @return array|WP_Error
	 */
	public static function rollback( string $id, bool $force = false ) {
		$guard = self::store()->guard_destructive();
		if ( $guard ) {
			return $guard;
		}
		// In the option store the whole undo holds the lock, so the conflict
		// check, the restore and the ledger writes see one consistent list.
		return self::store()->with_lock(
			static function () use ( $id, $force ) {
				return self::rollback_now( $id, $force );
			}
		);
	}

	/**
	 * rollback() body, run inside the store's lock.
	 *
	 * @param string $id    Entry id.
	 * @param bool   $force Skip the conflict guard.
	 * @return array|WP_Error
	 */
	private static function rollback_now( string $id, bool $force ) {
		$entry = self::get( $id );
		if ( null === $entry ) {
			return new WP_Error( 'not_found', __( 'Change not found.', 'emcp-tools' ) );
		}
		if ( ! empty( $entry['rolled_back'] ) ) {
			return new WP_Error( 'already_rolled_back', __( 'This change has already been rolled back.', 'emcp-tools' ) );
		}
		$rb = ( isset( $entry['rollback'] ) && is_array( $entry['rollback'] ) ) ? $entry['rollback'] : null;
		if ( null === $rb ) {
			return new WP_Error( 'not_reversible', __( 'This change is not reversible.', 'emcp-tools' ) );
		}
		$blocker = self::rollback_blocker( $entry );
		if ( is_wp_error( $blocker ) ) {
			return $blocker;
		}

		// Conflict guard: refuse if the target changed after we recorded it,
		// unless the caller forces it. Undoing then would clobber newer edits.
		if ( ! $force ) {
			$conflict = self::detect_conflict( $rb );
			if ( is_wp_error( $conflict ) ) {
				return $conflict;
			}
		}

		$was_suppressed = self::$suppress;
		self::$suppress = true;
		try {
			$result = self::apply_rollback( $rb );
		} catch ( \Throwable $e ) {
			$result = new WP_Error( 'rollback_failed', $e->getMessage() );
		} finally {
			self::$suppress = $was_suppressed;
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( true !== $result ) {
			return new WP_Error( 'rollback_failed', __( 'The rollback handler did not confirm successful restoration.', 'emcp-tools' ) );
		}

		if ( ! self::mark_rolled_back( $id ) ) {
			return new WP_Error( 'rollback_state_failed', __( 'The target was restored, but History could not save completion. Inspect the target before retrying.', 'emcp-tools' ) );
		}
		$comp = self::record( array(
			'domain'   => $entry['domain'] ?? '',
			'action'   => 'rollback',
			'target'   => $entry['target'] ?? '',
			'summary'  => 'Rolled back: ' . ( $entry['summary'] ?? $id ),
			'rollback' => null,
		) );
		if ( '' === $comp && ! $was_suppressed ) {
			return new WP_Error( 'rollback_audit_failed', __( 'The target was restored and marked rolled back, but the rollback activity entry could not be saved.', 'emcp-tools' ) );
		}
		$out = array(
			'rolled_back'  => $id,
			'compensating' => $comp,
		);
		return $out;
	}

	/**
	 * Structural undo eligibility, shared by the API and History UI. Force may
	 * override a state conflict, never an unsafe or incomplete inverse.
	 *
	 * @param array $entry Ledger entry.
	 * @return true|WP_Error
	 */
	public static function rollback_blocker( array $entry ) {
		if ( ! empty( $entry['rolled_back'] ) ) {
			return new WP_Error( 'already_rolled_back', __( 'This change has already been rolled back.', 'emcp-tools' ) );
		}
		$rb = $entry['rollback'] ?? null;
		if ( ! is_array( $rb ) || empty( $rb['type'] ) ) {
			return new WP_Error( 'not_reversible', __( 'This change is not reversible.', 'emcp-tools' ) );
		}
		if ( 'db-before-image' === $rb['type'] ) {
			return new WP_Error( 'legacy_unverified', __( 'This database snapshot does not contain verified row identities and after-state. Automatic rollback is unavailable; the snapshot is retained for inspection.', 'emcp-tools' ) );
		}
		if ( ! empty( $rb['partial'] ) ) {
			return new WP_Error( 'snapshot_incomplete', __( 'This snapshot is incomplete and cannot fully restore the change.', 'emcp-tools' ) );
		}
		if ( 'post-restore' === $rb['type'] && 'attachment' === ( $rb['snapshot']['post']['post_type'] ?? '' ) ) {
			return new WP_Error( 'snapshot_incomplete', __( 'This generic post snapshot has no attachment file backups. Automatic rollback is unavailable.', 'emcp-tools' ) );
		}
		if ( ( ! empty( $rb['creation_guard'] ) || isset( $rb['after_scope'] ) || in_array( $rb['type'], array( 'user-fields', 'acf-fields' ), true ) ) && empty( $rb['after_hash'] ) ) {
			return new WP_Error( 'legacy_unverified', __( 'This snapshot has no verifiable after-state. Automatic rollback is unavailable.', 'emcp-tools' ) );
		}
		if ( 'post-fields' === $rb['type'] && ! isset( $rb['after_scope'] ) && ! empty( $rb['after_hash'] ) ) {
			// Old post hashes omitted metadata, terms and some core fields. They
			// cannot prove that a newer edit is safe to overwrite.
			return new WP_Error( 'legacy_unverified', __( 'This older post snapshot does not have a complete conflict guard. Automatic rollback is unavailable.', 'emcp-tools' ) );
		}
		return true;
	}

	/**
	 * Detect whether the target changed since the recorded write, by comparing
	 * the stored `after_hash` against the target's current state hash. Returns a
	 * `conflict` WP_Error on mismatch or unreadable current state. Structural
	 * legacy restrictions are checked separately by rollback_blocker().
	 *
	 * @param array $rb Rollback ref.
	 * @return true|WP_Error
	 */
	private static function detect_conflict( array $rb ) {
		$expected = isset( $rb['after_hash'] ) ? (string) $rb['after_hash'] : '';
		if ( '' === $expected || ! class_exists( 'EMCP_Tools_Change_Recorder' ) ) {
			return true;
		}
		$current = self::current_hash( $rb );
		if ( '' === $current ) {
			return new WP_Error( 'conflict', __( 'The current target state could not be verified. The target may have been removed or become unreadable.', 'emcp-tools' ) );
		}
		if ( ! hash_equals( $expected, $current ) ) {
			return new WP_Error( 'conflict', __( 'This target has changed since the recorded change. Roll back anyway with force to overwrite the newer state.', 'emcp-tools' ) );
		}
		return true;
	}

	/**
	 * The target's current state hash, matching how the recorder stamped it.
	 *
	 * @param array $rb Rollback ref.
	 * @return string '' when not hashable for this type.
	 */
	private static function current_hash( array $rb ): string {
		switch ( $rb['type'] ?? '' ) {
			case 'elementor-data':
				return EMCP_Tools_Change_Recorder::hash_elementor( (int) ( $rb['post_id'] ?? 0 ) );
			case 'file-backup':
			case 'file-create':
				return EMCP_Tools_Change_Recorder::hash_file( (string) ( $rb['target_path'] ?? '' ) );
			case 'option':
				if ( isset( $rb['option_keys'] ) && is_array( $rb['option_keys'] ) ) {
					return EMCP_Tools_Change_Recorder::hash_options( $rb['option_keys'] );
				}
				return EMCP_Tools_Change_Recorder::hash_option( (string) ( $rb['option'] ?? '' ) );
			case 'post-fields':
				if ( isset( $rb['after_scope'] ) && is_array( $rb['after_scope'] ) ) {
					return EMCP_Tools_Change_Recorder::hash_post_scope( (int) ( $rb['post_id'] ?? 0 ), $rb['after_scope'] );
				}
				return EMCP_Tools_Change_Recorder::hash_post( (int) ( $rb['post_id'] ?? 0 ) );
			case 'post-create':
				return EMCP_Tools_Change_Recorder::hash_created_resource( (int) ( $rb['post_id'] ?? 0 ) );
			case 'meta-before-image':
				return EMCP_Tools_Change_Recorder::hash_meta( (string) ( $rb['object'] ?? 'post' ), (int) ( $rb['id'] ?? 0 ), (array) ( $rb['meta_keys'] ?? array() ) );
			case 'user-fields':
				return EMCP_Tools_Change_Recorder::hash_user_fields( (int) ( $rb['user_id'] ?? 0 ), (array) ( $rb['field_keys'] ?? array() ) );
			case 'acf-fields':
				return EMCP_Tools_Change_Recorder::hash_acf_fields( $rb['acf_target'] ?? 0, (array) ( $rb['field_keys'] ?? array() ) );
			default:
				return '';
		}
	}

	/**
	 * Dispatch a rollback by type.
	 *
	 * @param array $rb Rollback ref.
	 * @return true|WP_Error
	 */
	private static function apply_rollback( array $rb ) {
		// Resolve an out-of-band before-image (large snapshots live in the blob
		// store; the row carries only a blob_id pointer).
		if ( ! empty( $rb['blob_id'] ) ) {
			if ( ! class_exists( 'EMCP_Tools_Change_Blobs' ) ) {
				return new WP_Error( 'blob_missing', __( 'The snapshot store is unavailable.', 'emcp-tools' ) );
			}
			$heavy = EMCP_Tools_Change_Blobs::get( (string) $rb['blob_id'] );
			if ( ! is_array( $heavy ) ) {
				return new WP_Error( 'blob_missing', __( 'The saved snapshot for this change is no longer available.', 'emcp-tools' ) );
			}
			$rb = array_merge( $rb, $heavy );
		}
		switch ( $rb['type'] ?? '' ) {
			case 'elementor-data':
				return self::rollback_elementor( $rb );
			case 'file-backup':
				return self::rollback_file_restore( $rb );
			case 'file-create':
				return self::rollback_file_delete( $rb );
			case 'db-before-image':
				return self::rollback_db( $rb );
			case 'meta-before-image':
				return self::rollback_meta( $rb );
			case 'post-fields':
				return self::rollback_post_fields( $rb );
			case 'post-create':
				return self::rollback_post_create( $rb );
			case 'post-restore':
				return self::rollback_post_restore( $rb );
			case 'option':
				return self::rollback_option( $rb );
			case 'attachment-delete':
				return self::rollback_attachment_delete( $rb );
			case 'user-create':
				return self::rollback_user_create( $rb );
			case 'user-fields':
				return self::rollback_user_fields( $rb );
			case 'acf-fields':
				return self::rollback_acf_fields( $rb );
			case 'redirect-row':
				if ( class_exists( 'EMCP_Tools_Redirect_Store' ) && EMCP_Tools_Redirect_Store::rollback( $rb ) ) {
					return true;
				}
				return new WP_Error( 'rollback_failed', __( 'Could not reverse the redirect change.', 'emcp-tools' ) );
			default:
				return new WP_Error( 'unknown_rollback', __( 'Unknown rollback type.', 'emcp-tools' ) );
		}
	}

	/**
	 * Restore a page's prior Elementor data.
	 *
	 * @param array $rb Rollback ref.
	 * @return true|WP_Error
	 */
	private static function rollback_elementor( array $rb ) {
		$post_id = (int) ( $rb['post_id'] ?? 0 );
		$before  = ( isset( $rb['before'] ) && is_array( $rb['before'] ) ) ? $rb['before'] : array();
		if ( $post_id <= 0 || ! class_exists( 'EMCP_Tools_Data' ) ) {
			return new WP_Error( 'rollback_failed', __( 'Cannot restore this page.', 'emcp-tools' ) );
		}
		$data = new EMCP_Tools_Data();
		$res  = $data->save_page_data( $post_id, $before );
		return is_wp_error( $res ) ? $res : ( true === $res ? true : new WP_Error( 'rollback_failed', __( 'Could not restore the Elementor page.', 'emcp-tools' ) ) );
	}

	/**
	 * Restore a file from its backup (ABSPATH-confined).
	 *
	 * @param array $rb Rollback ref.
	 * @return true|WP_Error
	 */
	private static function rollback_file_restore( array $rb ) {
		$target = (string) ( $rb['target_path'] ?? '' );
		$backup = (string) ( $rb['backup_path'] ?? '' );
		if ( '' === $target || '' === $backup || ! is_file( $backup ) ) {
			return new WP_Error( 'rollback_failed', __( 'Backup is unavailable.', 'emcp-tools' ) );
		}
		$safe = self::guard_target( $target );
		if ( is_wp_error( $safe ) ) {
			return $safe;
		}
		return copy( $backup, $safe ) ? true : new WP_Error( 'rollback_failed', __( 'Could not restore the file.', 'emcp-tools' ) );
	}

	/**
	 * Delete a file that a recorded write created (ABSPATH-confined).
	 *
	 * @param array $rb Rollback ref.
	 * @return true|WP_Error
	 */
	private static function rollback_file_delete( array $rb ) {
		$target = (string) ( $rb['target_path'] ?? '' );
		if ( '' === $target || ! is_file( $target ) ) {
			return true; // Already gone, nothing to undo.
		}
		$safe = self::guard_target( $target );
		if ( is_wp_error( $safe ) ) {
			return $safe;
		}
		return @unlink( $safe ) ? true : new WP_Error( 'rollback_failed', __( 'Could not delete the created file.', 'emcp-tools' ) );
	}

	/**
	 * Inverse a database write from its before-image.
	 *
	 * @param array $rb Rollback ref.
	 * @return true|WP_Error
	 */
	private static function rollback_db( array $rb ) {
		return new WP_Error( 'legacy_unverified', __( 'Database rollback requires a snapshot with verified row identities and after-state.', 'emcp-tools' ) );
	}

	/**
	 * Re-create a deleted attachment from its snapshot — re-insert the post,
	 * restore all meta, and copy the trashed files back to their original paths.
	 *
	 * @param array $rb Rollback ref: { snapshot:{ post, meta, files } }.
	 * @return true|WP_Error
	 */
	private static function rollback_attachment_delete( array $rb ) {
		$snap = ( isset( $rb['snapshot'] ) && is_array( $rb['snapshot'] ) ) ? $rb['snapshot'] : array();
		$post = ( isset( $snap['post'] ) && is_array( $snap['post'] ) ) ? $snap['post'] : array();
		if ( empty( $post ) ) {
			return new WP_Error( 'rollback_failed', __( 'No attachment snapshot to restore.', 'emcp-tools' ) );
		}
		$old_id = (int) ( $post['ID'] ?? 0 );
		if ( $old_id <= 0 || get_post( $old_id ) ) {
			return new WP_Error( 'restore_identity_conflict', __( 'The original attachment ID is unavailable. Nothing was restored.', 'emcp-tools' ) );
		}
		// Validate every required backup before creating a post or copying files.
		foreach ( (array) ( $snap['files'] ?? array() ) as $file ) {
			$orig = (string) ( $file['orig'] ?? '' );
			$backup = (string) ( $file['trashed'] ?? '' );
			if ( '' === $orig || '' === $backup || ! is_readable( $backup ) || ! is_file( $backup ) ) {
				return new WP_Error( 'backup_missing', __( 'A required attachment backup is unavailable. Nothing was restored.', 'emcp-tools' ) );
			}
			if ( file_exists( $orig ) ) {
				return new WP_Error( 'restore_identity_conflict', __( 'An attachment destination is already occupied. Nothing was restored.', 'emcp-tools' ) );
			}
			$safe = self::guard_target( $orig );
			if ( is_wp_error( $safe ) ) {
				return $safe;
			}
		}
		unset( $post['ID'] );
		if ( $old_id > 0 && ! get_post( $old_id ) ) {
			$post['import_id'] = $old_id;
		}
		$new_id = wp_insert_post( wp_slash( $post ), true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		$new_id = (int) $new_id;
		if ( $old_id !== $new_id ) {
			return new WP_Error( 'rollback_failed', __( 'The original attachment ID could not be restored. Inspect the partially restored attachment before retrying.', 'emcp-tools' ) );
		}
		foreach ( (array) ( $snap['meta'] ?? array() ) as $key => $values ) {
			foreach ( (array) $values as $value ) {
				if ( false === add_post_meta( $new_id, (string) $key, wp_slash( maybe_unserialize( $value ) ) ) ) {
					return new WP_Error( 'rollback_failed', __( 'The attachment was only partially restored: metadata could not be written.', 'emcp-tools' ) );
				}
			}
		}
		foreach ( (array) ( $snap['files'] ?? array() ) as $file ) {
			$orig    = (string) ( $file['orig'] ?? '' );
			$trashed = (string) ( $file['trashed'] ?? '' );
			if ( '' !== $orig && is_file( $trashed ) ) {
				$parent = dirname( $orig );
				if ( ! is_dir( $parent ) && function_exists( 'wp_mkdir_p' ) ) {
					wp_mkdir_p( $parent );
				}
				$copied = @copy( $trashed, $orig ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- failure returned below.
				$source_hash = $copied ? @hash_file( 'sha256', $trashed ) : false;
				$target_hash = $copied ? @hash_file( 'sha256', $orig ) : false;
				if ( false === $source_hash || false === $target_hash || ! hash_equals( $source_hash, $target_hash ) ) {
					return new WP_Error( 'rollback_failed', __( 'The attachment was only partially restored: a file could not be restored and verified.', 'emcp-tools' ) );
				}
			} else {
				return new WP_Error( 'backup_missing', __( 'An attachment backup disappeared during restoration.', 'emcp-tools' ) );
			}
		}
		return true;
	}

	/**
	 * Undo a user creation by deleting the user. Refuses if the user has since
	 * gained administrator-level capabilities (safety).
	 *
	 * @param array $rb Rollback ref: { user_id }.
	 * @return true|WP_Error
	 */
	private static function rollback_user_create( array $rb ) {
		$user_id = (int) ( $rb['user_id'] ?? 0 );
		if ( $user_id <= 0 ) {
			return new WP_Error( 'rollback_failed', __( 'Missing user id.', 'emcp-tools' ) );
		}
		if ( function_exists( 'get_userdata' ) && ! get_userdata( $user_id ) ) {
			return true; // Already gone.
		}
		if ( function_exists( 'user_can' ) && user_can( $user_id, 'manage_options' ) ) {
			return new WP_Error( 'rollback_refused', __( 'This user now has administrator capabilities and will not be deleted by a rollback.', 'emcp-tools' ) );
		}
		if ( ! function_exists( 'wp_delete_user' ) ) {
			return new WP_Error( 'rollback_failed', __( 'User deletion is unavailable.', 'emcp-tools' ) );
		}
		$result = wp_delete_user( $user_id );
		return $result && ! get_userdata( $user_id ) ? true : new WP_Error( 'rollback_failed', __( 'Could not delete the created user.', 'emcp-tools' ) );
	}

	/**
	 * Restore a user's prior profile fields.
	 *
	 * @param array $rb Rollback ref: { user_id, before:{ field => value } }.
	 * @return true|WP_Error
	 */
	private static function rollback_user_fields( array $rb ) {
		$user_id = (int) ( $rb['user_id'] ?? 0 );
		$before  = ( isset( $rb['before'] ) && is_array( $rb['before'] ) ) ? $rb['before'] : array();
		if ( $user_id <= 0 || empty( $before ) || ! function_exists( 'wp_update_user' ) ) {
			return new WP_Error( 'rollback_failed', __( 'Cannot restore this user.', 'emcp-tools' ) );
		}
		$res = wp_update_user( array_merge( array( 'ID' => $user_id ), $before ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$user = get_userdata( $user_id );
		foreach ( $before as $key => $value ) {
			if ( ! $res || ! $user || (string) ( $user->$key ?? '' ) !== (string) $value ) {
				return new WP_Error( 'rollback_failed', __( 'Could not restore the user profile.', 'emcp-tools' ) );
			}
		}
		return true;
	}

	/**
	 * Restore ACF field values by re-writing the prior raw values through ACF
	 * (by field key), which correctly reverses simple and complex fields alike.
	 *
	 * @param array $rb Rollback ref: { acf_target, before:{ field_key => value } }.
	 * @return true|WP_Error
	 */
	private static function rollback_acf_fields( array $rb ) {
		if ( ! function_exists( 'update_field' ) ) {
			return new WP_Error( 'rollback_failed', __( 'ACF is not available to restore these fields.', 'emcp-tools' ) );
		}
		$target = $rb['acf_target'] ?? 0;
		$before = ( isset( $rb['before'] ) && is_array( $rb['before'] ) ) ? $rb['before'] : array();
		if ( empty( $before ) ) {
			return new WP_Error( 'rollback_failed', __( 'No ACF values to restore.', 'emcp-tools' ) );
		}
		foreach ( $before as $key => $value ) {
			update_field( (string) $key, $value, $target );
			if ( ! function_exists( 'get_field' ) || maybe_serialize( get_field( (string) $key, $target, false ) ) !== maybe_serialize( $value ) ) {
				return new WP_Error( 'rollback_failed', __( 'Could not restore an ACF field value.', 'emcp-tools' ) );
			}
		}
		return true;
	}

	/**
	 * Restore option values from a before-image. `values` maps each option to its
	 * prior value, or the marker '__ABSENT__' when it did not exist (deleted on undo).
	 *
	 * @param array $rb Rollback ref: { values:{ option => prior|'__ABSENT__' } } or { option, before }.
	 * @return true|WP_Error
	 */
	private static function rollback_option( array $rb ) {
		$values = ( isset( $rb['values'] ) && is_array( $rb['values'] ) ) ? $rb['values'] : array();
		if ( empty( $values ) ) {
			return new WP_Error( 'rollback_failed', __( 'No option values to restore.', 'emcp-tools' ) );
		}
		foreach ( $values as $name => $value ) {
			$name = (string) $name;
			if ( '__ABSENT__' === $value ) {
				delete_option( $name );
				$absent = new \stdClass();
				if ( $absent !== get_option( $name, $absent ) ) {
					return new WP_Error( 'rollback_failed', __( 'Could not remove the created option.', 'emcp-tools' ) );
				}
			} else {
				update_option( $name, $value );
				if ( maybe_serialize( get_option( $name ) ) !== maybe_serialize( $value ) ) {
					return new WP_Error( 'rollback_failed', __( 'Could not restore the option value.', 'emcp-tools' ) );
				}
			}
		}
		return true;
	}

	/**
	 * Restore post/term meta from a before-image.
	 *
	 * `before` maps meta_key => prior value (as returned by get_*_meta(single);
	 * an empty string/array means the key was unset, so it is deleted on undo).
	 *
	 * @param array $rb Rollback ref: { object:'post'|'term', id:int, before:array }.
	 * @return true|WP_Error
	 */
	private static function rollback_meta( array $rb ) {
		$object = 'term' === ( $rb['object'] ?? '' ) ? 'term' : 'post';
		$id     = (int) ( $rb['id'] ?? 0 );
		$before = ( isset( $rb['before'] ) && is_array( $rb['before'] ) ) ? $rb['before'] : array();
		if ( $id <= 0 ) {
			return new WP_Error( 'rollback_failed', __( 'Missing object id.', 'emcp-tools' ) );
		}
		foreach ( $before as $key => $value ) {
			$key   = (string) $key;
			$empty = '' === $value || array() === $value || null === $value;
			if ( 'term' === $object ) {
				$empty ? delete_term_meta( $id, $key ) : update_term_meta( $id, $key, wp_slash( $value ) );
				$actual = get_term_meta( $id, $key, ! $empty );
			} else {
				$empty ? delete_post_meta( $id, $key ) : update_post_meta( $id, $key, wp_slash( $value ) );
				$actual = get_post_meta( $id, $key, ! $empty );
			}
			if ( $empty ? array() !== $actual : maybe_serialize( $actual ) !== maybe_serialize( $value ) ) {
				return new WP_Error( 'rollback_failed', __( 'Could not restore the metadata value.', 'emcp-tools' ) );
			}
		}
		return true;
	}

	/**
	 * Restore a post's prior fields, meta, and terms (content/media/Gutenberg).
	 *
	 * @param array $rb Rollback ref: { post_id, before:{ fields, meta, terms } }.
	 * @return true|WP_Error
	 */
	private static function rollback_post_fields( array $rb ) {
		$post_id = (int) ( $rb['post_id'] ?? 0 );
		$before  = ( isset( $rb['before'] ) && is_array( $rb['before'] ) ) ? $rb['before'] : array();
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new WP_Error( 'rollback_failed', __( 'The post no longer exists.', 'emcp-tools' ) );
		}
		$fields = ( isset( $before['fields'] ) && is_array( $before['fields'] ) ) ? $before['fields'] : array();
		if ( ! empty( $fields ) ) {
			$fields['ID'] = $post_id;
			$result = wp_update_post( wp_slash( $fields ), true );
			if ( is_wp_error( $result ) || ! $result ) {
				return is_wp_error( $result ) ? $result : new WP_Error( 'rollback_failed', __( 'Could not restore the post fields.', 'emcp-tools' ) );
			}
			$post = get_post( $post_id );
			foreach ( $fields as $key => $value ) {
				if ( ! $post || (string) ( $post->$key ?? '' ) !== (string) $value ) {
					return new WP_Error( 'rollback_failed', __( 'The restored post fields did not match the snapshot.', 'emcp-tools' ) );
				}
			}
		}
		foreach ( (array) ( $before['meta'] ?? array() ) as $key => $value ) {
			$key = (string) $key;
			if ( '__DELETE__' === $value ) {
				delete_post_meta( $post_id, $key );
				if ( array() !== get_post_meta( $post_id, $key, false ) ) {
					return new WP_Error( 'rollback_failed', __( 'Could not remove the created metadata.', 'emcp-tools' ) );
				}
			} else {
				update_post_meta( $post_id, $key, wp_slash( $value ) );
				if ( maybe_serialize( get_post_meta( $post_id, $key, true ) ) !== maybe_serialize( $value ) ) {
					return new WP_Error( 'rollback_failed', __( 'Could not restore the metadata.', 'emcp-tools' ) );
				}
			}
		}
		foreach ( (array) ( $before['meta_rows'] ?? array() ) as $key => $values ) {
			if ( ! is_array( $values ) ) {
				return new WP_Error( 'snapshot_invalid', __( 'The metadata snapshot is invalid.', 'emcp-tools' ) );
			}
			if ( get_post_meta( $post_id, (string) $key, false ) !== $values ) {
				delete_post_meta( $post_id, (string) $key );
				if ( array() !== get_post_meta( $post_id, (string) $key, false ) ) {
					return new WP_Error( 'rollback_failed', __( 'Could not clear the metadata before restoring it.', 'emcp-tools' ) );
				}
				foreach ( $values as $value ) {
					if ( false === add_post_meta( $post_id, (string) $key, wp_slash( $value ) ) ) {
						return new WP_Error( 'rollback_failed', __( 'Could not restore a metadata row.', 'emcp-tools' ) );
					}
				}
			}
			if ( get_post_meta( $post_id, (string) $key, false ) !== $values ) {
				return new WP_Error( 'rollback_failed', __( 'The restored metadata did not match the snapshot.', 'emcp-tools' ) );
			}
			if ( '_elementor_page_settings' === $key ) {
				delete_post_meta( $post_id, '_elementor_css' );
				delete_post_meta( $post_id, '_elementor_element_cache' );
				if ( class_exists( '\\Elementor\\Core\\Files\\CSS\\Post' ) ) {
					$css = new \Elementor\Core\Files\CSS\Post( $post_id );
					$css->delete();
				}
			}
		}
		foreach ( (array) ( $before['terms'] ?? array() ) as $tax => $ids ) {
			$ids = array_map( 'intval', (array) $ids );
			$result = wp_set_object_terms( $post_id, $ids, (string) $tax, false );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$actual = wp_get_object_terms( $post_id, (string) $tax, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $actual ) ) {
				return $actual;
			}
			$actual = array_map( 'intval', (array) $actual );
			sort( $actual );
			sort( $ids );
			if ( $actual !== $ids ) {
				return new WP_Error( 'rollback_failed', __( 'Could not restore the taxonomy terms.', 'emcp-tools' ) );
			}
		}
		return true;
	}

	/**
	 * Undo a post creation by deleting the created post.
	 *
	 * @param array $rb Rollback ref: { post_id }.
	 * @return true|WP_Error
	 */
	private static function rollback_post_create( array $rb ) {
		$post_id = (int) ( $rb['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'rollback_failed', __( 'Missing post id.', 'emcp-tools' ) );
		}
		if ( ! get_post( $post_id ) ) {
			foreach ( (array) ( $rb['created_files'] ?? array() ) as $path ) {
				clearstatcache( true, $path );
				if ( file_exists( $path ) ) {
					return new WP_Error( 'rollback_failed', __( 'The attachment is gone but an uploaded file remains. Manual file cleanup is required.', 'emcp-tools' ) );
				}
			}
			return true; // Already gone.
		}
		$post = get_post( $post_id );
		$files = ! empty( $rb['creation_guard'] ) && 'attachment' === ( $post->post_type ?? '' )
			? EMCP_Tools_Change_Recorder::attachment_files( $post_id ) : array();
		$files = array_unique( array_merge( $files, (array) ( $rb['created_files'] ?? array() ) ) );
		// WP's path_join() does not recognize Windows drive paths with forward
		// slashes. Give core a native path for this attachment so its own guarded
		// deletion removes the sub-sizes too; retain all WordPress delete hooks.
		$native_path = static function ( $file, $attachment_id ) use ( $post_id ) {
			return (int) $attachment_id === $post_id ? ( realpath( $file ) ?: $file ) : $file;
		};
		$normalize = '\\' === DIRECTORY_SEPARATOR && ! empty( $files ) && function_exists( 'add_filter' );
		if ( $normalize ) {
			add_filter( 'get_attached_file', $native_path, PHP_INT_MAX, 2 );
		}
		try {
			$result = wp_delete_post( $post_id, true );
		} finally {
			if ( $normalize ) {
				remove_filter( 'get_attached_file', $native_path, PHP_INT_MAX );
			}
		}
		if ( ! $result || get_post( $post_id ) ) {
			return new WP_Error( 'rollback_failed', __( 'Could not delete the created post.', 'emcp-tools' ) );
		}
		foreach ( $files as $path ) {
			clearstatcache( true, $path );
			if ( file_exists( $path ) ) {
				return new WP_Error( 'rollback_failed', __( 'The attachment was removed, but an uploaded file could not be deleted. Inspect the files before retrying.', 'emcp-tools' ) );
			}
		}
		return true;
	}

	/**
	 * Undo a post deletion — untrash a trashed post, or re-insert a force-deleted
	 * one from its snapshot (post + meta + terms), preserving the id when free.
	 *
	 * @param array $rb Rollback ref: { mode:'untrash'|'reinsert', post_id?, snapshot? }.
	 * @return true|WP_Error
	 */
	private static function rollback_post_restore( array $rb ) {
		if ( 'untrash' === ( $rb['mode'] ?? '' ) ) {
			$post_id = (int) ( $rb['post_id'] ?? 0 );
			if ( $post_id <= 0 ) {
				return new WP_Error( 'rollback_failed', __( 'Missing post id.', 'emcp-tools' ) );
			}
			$result = wp_untrash_post( $post_id );
			$post = get_post( $post_id );
			return $result && $post && 'trash' !== $post->post_status ? true : new WP_Error( 'rollback_failed', __( 'Could not restore the trashed post.', 'emcp-tools' ) );
		}
		$snap = ( isset( $rb['snapshot'] ) && is_array( $rb['snapshot'] ) ) ? $rb['snapshot'] : array();
		$post = ( isset( $snap['post'] ) && is_array( $snap['post'] ) ) ? $snap['post'] : array();
		if ( empty( $post ) ) {
			return new WP_Error( 'rollback_failed', __( 'No snapshot to restore.', 'emcp-tools' ) );
		}
		// Also guard legacy snapshots loaded from blobs: restoring just the
		// attachment row and metadata would leave a broken media-library item.
		if ( 'attachment' === ( $post['post_type'] ?? '' ) ) {
			return new WP_Error( 'snapshot_incomplete', __( 'This generic post snapshot has no attachment file backups. Automatic rollback is unavailable.', 'emcp-tools' ) );
		}
		$old_id = (int) ( $post['ID'] ?? 0 );
		if ( $old_id <= 0 || get_post( $old_id ) ) {
			return new WP_Error( 'restore_identity_conflict', __( 'The original post ID is unavailable. Nothing was restored.', 'emcp-tools' ) );
		}
		unset( $post['ID'] );
		if ( $old_id > 0 && ! get_post( $old_id ) ) {
			$post['import_id'] = $old_id; // Reuse the original id when it is free.
		}
		$new_id = wp_insert_post( wp_slash( $post ), true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		$new_id = (int) $new_id;
		if ( $old_id !== $new_id ) {
			return new WP_Error( 'rollback_failed', __( 'The original post ID could not be restored. Inspect the partially restored post before retrying.', 'emcp-tools' ) );
		}
		foreach ( (array) ( $snap['meta'] ?? array() ) as $key => $values ) {
			foreach ( (array) $values as $value ) {
				if ( false === add_post_meta( $new_id, (string) $key, wp_slash( maybe_unserialize( $value ) ) ) ) {
					return new WP_Error( 'rollback_failed', __( 'The post was only partially restored: metadata could not be written.', 'emcp-tools' ) );
				}
			}
		}
		foreach ( (array) ( $snap['terms'] ?? array() ) as $tax => $ids ) {
			$result = wp_set_object_terms( $new_id, array_map( 'intval', (array) $ids ), (string) $tax, false );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	/**
	 * Confine a filesystem target to ABSPATH via the shared guard.
	 *
	 * @param string $target Absolute path.
	 * @return string|WP_Error Canonical path or error.
	 */
	private static function guard_target( string $target ) {
		if ( class_exists( 'EMCP_Tools_Filesystem_Guard' ) ) {
			return EMCP_Tools_Filesystem_Guard::resolve_path( $target );
		}
		return $target;
	}

	/**
	 * A short unique id.
	 *
	 * @return string
	 */
	private static function uid(): string {
		return substr( md5( uniqid( '', true ) ), 0, 12 );
	}

	/**
	 * Current user login (best-effort).
	 *
	 * @return string
	 */
	private static function current_login(): string {
		if ( function_exists( 'wp_get_current_user' ) ) {
			$u = wp_get_current_user();
			if ( $u && isset( $u->user_login ) ) {
				return (string) $u->user_login;
			}
		}
		return '';
	}
}
