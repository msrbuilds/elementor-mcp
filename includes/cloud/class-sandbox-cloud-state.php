<?php
/**
 * Cloud and Marketplace state of a Sandbox artifact (block, widget, snippet),
 * shared by the legacy Sandbox views and the admin REST (spec 8.11).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloud state of one Sandbox artifact.
 */
final class EMCP_Tools_Sandbox_Cloud_State {

	const KINDS = array( 'widget', 'block', 'snippet' );

	/**
	 * The artifact resolver ships in the deferred MCP surface, which a plain
	 * REST request never loads; EMCP_Tools_Cloud_Sync needs it too.
	 */
	public static function ensure(): bool {
		if ( ! class_exists( 'EMCP_Tools_Sandbox_Cloud_Abilities' ) && class_exists( 'EMCP_Tools_Bootstrap' ) ) {
			EMCP_Tools_Bootstrap::load_mcp_surface();
		}
		return class_exists( 'EMCP_Tools_Sandbox_Cloud_Abilities' );
	}

	/**
	 * The portable-bundle presenter for a kind, or null (block without Pro).
	 *
	 * @param string $kind widget | block | snippet.
	 */
	public static function artifact( string $kind ): ?EMCP_Tools_Sandbox_Artifact {
		return self::ensure() ? ( new EMCP_Tools_Sandbox_Cloud_Abilities() )->resolve_artifact( $kind ) : null;
	}

	/**
	 * Current content checksum ('' when unresolvable).
	 *
	 * @param string $kind Kind.
	 * @param int    $id   Artifact id.
	 */
	public static function checksum( string $kind, int $id ): string {
		$art = self::artifact( $kind );
		return $art ? (string) $art->checksum( $id ) : '';
	}

	/**
	 * Record the current checksum as the last-pushed one.
	 *
	 * @param string $kind Kind.
	 * @param int    $id   Artifact id.
	 */
	public static function store_checksum( string $kind, int $id ): void {
		$sum = self::checksum( $kind, $id );
		if ( '' !== $sum ) {
			update_post_meta( $id, '_emcp_cloud_checksum', $sum );
		}
	}

	/**
	 * Record a successful push: the flag, the pushed checksum, fresh Marketplace state.
	 *
	 * @param string $kind Kind.
	 * @param int    $id   Artifact id.
	 */
	public static function after_push( string $kind, int $id ): void {
		update_post_meta( $id, '_emcp_cloud_pushed', time() );
		self::store_checksum( $kind, $id );
		self::refresh_marketplace( $kind, $id );
	}

	/**
	 * Local content differs from the last push. With no recorded baseline (pushed
	 * before checksums existed) an update is allowed rather than hidden forever.
	 *
	 * @param string $kind Kind.
	 * @param int    $id   Artifact id.
	 */
	public static function changed( string $kind, int $id ): bool {
		if ( ! get_post_meta( $id, '_emcp_cloud_pushed', true ) ) {
			return false;
		}
		$pushed = (string) get_post_meta( $id, '_emcp_cloud_checksum', true );
		return '' === $pushed || self::checksum( $kind, $id ) !== $pushed;
	}

	/**
	 * Cloud column: none (never pushed), synced (same hash as the last push), changed.
	 *
	 * @param string $kind Kind.
	 * @param int    $id   Artifact id.
	 */
	public static function column( string $kind, int $id ): string {
		if ( ! get_post_meta( $id, '_emcp_cloud_pushed', true ) ) {
			return 'none';
		}
		if ( ! self::ensure() || ! class_exists( 'EMCP_Tools_Cloud_Sync' ) ) {
			return 'changed';
		}
		return EMCP_Tools_Cloud_Sync::is_up_to_date( $kind, $id ) ? 'synced' : 'changed';
	}

	/**
	 * Fetch Marketplace state and cache it locally. Best effort; null on any error.
	 *
	 * @param string $kind Kind.
	 * @param int    $id   Artifact id.
	 */
	public static function refresh_marketplace( string $kind, int $id ): ?array {
		if ( ! self::ensure() || ! class_exists( 'EMCP_Tools_Cloud_Sync' ) ) {
			return null;
		}
		$state = EMCP_Tools_Cloud_Sync::marketplace_state( $kind, $id );
		if ( is_wp_error( $state ) || ! is_array( $state ) ) {
			return null;
		}
		$slug = isset( $state['slug'] ) ? (string) $state['slug'] : '';
		if ( '' !== $slug ) {
			update_post_meta( $id, '_emcp_marketplace_slug', $slug );
			update_post_meta( $id, '_emcp_marketplace_status', (string) ( $state['status'] ?? '' ) );
			update_post_meta( $id, '_emcp_marketplace_pending', ! empty( $state['hasPendingUpdate'] ) ? 1 : 0 );
		} else {
			delete_post_meta( $id, '_emcp_marketplace_slug' );
			delete_post_meta( $id, '_emcp_marketplace_status' );
			delete_post_meta( $id, '_emcp_marketplace_pending' );
		}
		return $state;
	}

	/**
	 * Clear the pushed flags when the Cloud copy is definitively gone. Only a
	 * 404/410 resets the state, so a network blip never drops a real save.
	 *
	 * @param string $kind Kind.
	 * @param int    $id   Artifact id.
	 */
	public static function verify_backup( string $kind, int $id ): void {
		if ( ! get_post_meta( $id, '_emcp_cloud_pushed', true ) || ! class_exists( 'EMCP_Tools_Cloud_Client' ) ) {
			return;
		}
		$art  = self::artifact( $kind );
		$uuid = $art ? (string) $art->uuid( $id ) : '';
		if ( '' === $uuid ) {
			return;
		}
		$res = EMCP_Tools_Cloud_Client::get( '/api/cloud/v1/artifacts/' . rawurlencode( $uuid ) );
		if ( is_wp_error( $res ) && in_array( $res->get_error_code(), array( 'cloud_http_404', 'cloud_http_410' ), true ) ) {
			delete_post_meta( $id, '_emcp_cloud_pushed' );
			delete_post_meta( $id, '_emcp_cloud_checksum' );
		}
	}

	/**
	 * Cached Marketplace state for the list screens.
	 *
	 * @param string $kind Kind.
	 * @param int    $id   Artifact id.
	 * @return array{slug:string,status:string,published:bool,pending:bool,viewUrl:string,publishUrl:string}
	 */
	public static function marketplace( string $kind, int $id ): array {
		$slug   = (string) get_post_meta( $id, '_emcp_marketplace_slug', true );
		$status = (string) get_post_meta( $id, '_emcp_marketplace_status', true );
		$sync   = class_exists( 'EMCP_Tools_Cloud_Sync' );
		return array(
			'slug'       => $slug,
			'status'     => $status,
			'published'  => 'published' === $status,
			'pending'    => (bool) get_post_meta( $id, '_emcp_marketplace_pending', true ),
			'viewUrl'    => ( '' !== $slug && $sync ) ? EMCP_Tools_Cloud_Sync::marketplace_view_url( $slug ) : '',
			'publishUrl' => $sync ? EMCP_Tools_Cloud_Sync::publish_url( $kind, $id ) : '',
		);
	}

	/**
	 * The legacy sandbox-cloud.js payload.
	 *
	 * @param string $kind Kind.
	 * @param int    $id   Artifact id.
	 */
	public static function payload( string $kind, int $id ): array {
		$m = self::marketplace( $kind, $id );
		return array(
			'kind'               => $kind,
			'id'                 => $id,
			'pushed'             => (bool) get_post_meta( $id, '_emcp_cloud_pushed', true ),
			'changed'            => self::changed( $kind, $id ),
			'slug'               => $m['slug'],
			'status'             => $m['status'],
			'published'          => $m['published'],
			'has_pending_update' => $m['pending'],
			'publish_url'        => $m['publishUrl'],
			'view_url'           => $m['viewUrl'],
		);
	}
}
