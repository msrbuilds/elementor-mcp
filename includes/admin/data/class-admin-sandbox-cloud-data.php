<?php
/**
 * Sandbox Cloud actions for the admin REST: save, save all, refresh, the
 * cross-site library and Marketplace updates (spec 8.11 list pattern, 8.18).
 * The Cloud token never leaves the server (spec 11).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sandbox Cloud actions.
 */
final class EMCP_Tools_Admin_Sandbox_Cloud_Data {

	const LIB_TTL       = 300;
	const LIB_ERROR_TTL = 60;
	const REFRESH_MAX    = 50;
	const REFRESH_BUDGET = 15;
	const LIB_KEY       = 'emcp_tools_sb_library_';

	private static function connected(): bool {
		return class_exists( 'EMCP_Tools_Cloud' ) && EMCP_Tools_Cloud::is_connected() && EMCP_Tools_Sandbox_Cloud_State::ensure() && class_exists( 'EMCP_Tools_Cloud_Sync' );
	}

	private static function not_connected(): WP_Error {
		return new WP_Error( 'not_connected', __( 'Connect this site to EMCP Cloud first.', 'emcp-tools' ), array( 'status' => 409 ) );
	}

	/**
	 * A Cloud failure as a 502.
	 *
	 * @param WP_Error $e Cloud error.
	 */
	private static function upstream( WP_Error $e ): WP_Error {
		return new WP_Error( $e->get_error_code(), $e->get_error_message(), array( 'status' => 502 ) );
	}

	/**
	 * Access to the list, then a Cloud connection.
	 *
	 * @param string $type  Type.
	 * @param bool   $write A change.
	 * @return true|WP_Error
	 */
	private static function gate( string $type, bool $write = false ) {
		$ok = EMCP_Tools_Admin_Sandbox_Data::can( $type, $write );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return self::connected() ? true : self::not_connected();
	}

	/**
	 * The list for $query with extra keys.
	 *
	 * @param string $type  Type.
	 * @param array  $query List query.
	 * @param array  $extra Extra keys.
	 */
	private static function list_with( string $type, array $query, array $extra ): array {
		$list = ( new EMCP_Tools_Admin_Sandbox_Data() )->list( $type, $query );
		return array_merge( is_wp_error( $list ) ? array() : $list, $extra );
	}

	/**
	 * Save or update one item in Cloud.
	 *
	 * @param string $type  Type.
	 * @param int    $id    Id.
	 * @param array  $query List query.
	 * @return array|WP_Error
	 */
	public function save( string $type, int $id, array $query ) {
		$ok = self::gate( $type );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$kind = EMCP_Tools_Admin_Sandbox_Data::kind( $type );
		$res  = EMCP_Tools_Cloud_Sync::backup( $kind, $id );
		if ( is_wp_error( $res ) ) {
			return self::upstream( $res );
		}
		EMCP_Tools_Sandbox_Cloud_State::after_push( $kind, $id );
		delete_transient( self::LIB_KEY . $kind );
		return self::list_with( $type, $query, array( 'message' => __( 'Saved to Cloud.', 'emcp-tools' ) ) );
	}

	/**
	 * Save every changed item of a type (up-to-date items are skipped).
	 *
	 * @param string $type  Type.
	 * @param array  $query List query.
	 * @return array|WP_Error
	 */
	public function bulk( string $type, array $query ) {
		$ok = self::gate( $type );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$kind = EMCP_Tools_Admin_Sandbox_Data::kind( $type );
		$res  = EMCP_Tools_Cloud_Sync::bulk_backup( array( $kind ) );
		if ( is_wp_error( $res ) ) {
			return self::upstream( $res );
		}
		foreach ( (array) ( $res['items'] ?? array() ) as $item ) {
			if ( ! empty( $item['ok'] ) && empty( $item['skipped'] ) && ! empty( $item['id'] ) ) {
				EMCP_Tools_Sandbox_Cloud_State::after_push( $kind, (int) $item['id'] );
			}
		}
		delete_transient( self::LIB_KEY . $kind );
		$pushed  = (int) ( $res['pushed'] ?? 0 );
		$skipped = (int) ( $res['skipped'] ?? 0 );
		$failed  = (int) ( $res['failed'] ?? 0 );
		if ( 0 === $pushed && 0 === $failed && $skipped > 0 ) {
			$message = __( 'Everything is already up to date in Cloud.', 'emcp-tools' );
		} else {
			/* translators: %d: number of items saved to Cloud. */
			$message = sprintf( _n( 'Saved %d item to Cloud.', 'Saved %d items to Cloud.', $pushed, 'emcp-tools' ), $pushed );
			if ( $failed > 0 ) {
				/* translators: %d: number of items that failed. */
				$message .= ' ' . sprintf( _n( '%d failed.', '%d failed.', $failed, 'emcp-tools' ), $failed );
			}
		}
		return self::list_with(
			$type,
			$query,
			array(
				'message' => $message,
				'pushed'  => $pushed,
				'skipped' => $skipped,
				'failed'  => $failed,
			)
		);
	}

	/**
	 * Re-verify pushed items (at most REFRESH_MAX per call, within
	 * REFRESH_BUDGET seconds). The first Cloud failure ends the pass as a 502,
	 * so an outage is reported as one instead of "refreshed".
	 *
	 * @param string $type  Type.
	 * @param array  $query List query.
	 * @return array|WP_Error
	 */
	public function refresh( string $type, array $query ) {
		$ok = self::gate( $type );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$kind = EMCP_Tools_Admin_Sandbox_Data::kind( $type );
		$done = 0;
		$ids  = get_posts(
			array(
				'post_type'      => EMCP_Tools_Admin_Sandbox_Data::POST_TYPES[ $kind ],
				'post_status'    => 'any',
				'posts_per_page' => EMCP_Tools_Admin_Sandbox_Data::MAX_ROWS,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$start = microtime( true );
		$more  = false;
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( ! get_post_meta( $id, '_emcp_cloud_pushed', true ) || null === EMCP_Tools_Admin_Sandbox_Data::row( $kind, $id, false ) ) {
				continue;
			}
			if ( $done >= self::REFRESH_MAX || microtime( true ) - $start > self::REFRESH_BUDGET ) {
				$more = true;
				break;
			}
			$err = EMCP_Tools_Sandbox_Cloud_State::verify_backup( $kind, $id );
			if ( $err instanceof WP_Error ) {
				return self::upstream( $err );
			}
			EMCP_Tools_Sandbox_Cloud_State::refresh_marketplace( $kind, $id );
			++$done;
		}
		$message = $more
			? __( 'Cloud status refreshed for part of the list. Refresh again for the rest.', 'emcp-tools' )
			: __( 'Cloud status refreshed.', 'emcp-tools' );
		return self::list_with( $type, $query, array( 'message' => $message ) );
	}

	/**
	 * The workspace's Cloud artifacts of a kind across every connected site.
	 *
	 * @param string $kind    Kind.
	 * @param bool   $refresh Skip the cache.
	 * @return array|WP_Error
	 */
	public function library( string $kind, bool $refresh = false ) {
		$type = EMCP_Tools_Admin_Sandbox_Data::type_of( $kind );
		$ok   = EMCP_Tools_Admin_Sandbox_Data::can( $type );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		if ( ! self::connected() ) {
			return array(
				'connected' => false,
				'count'     => 0,
				'artifacts' => array(),
				'site'      => '',
			);
		}
		$key = self::LIB_KEY . $kind;
		if ( ! $refresh ) {
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$site = EMCP_Tools_Cloud::site_uuid();
		$res  = EMCP_Tools_Cloud_Sync::list_remote( $kind );
		if ( is_wp_error( $res ) ) {
			$out = array(
				'connected' => true,
				'count'     => null,
				'artifacts' => array(),
				'site'      => $site,
				'error'     => $res->get_error_message(),
			);
			set_transient( $key, $out, self::LIB_ERROR_TTL );
			return $out;
		}
		$arts = array();
		foreach ( (array) ( $res['artifacts'] ?? array() ) as $a ) {
			$arts[] = array(
				'uuid'       => (string) ( $a['artifact_uuid'] ?? '' ),
				'title'      => (string) ( $a['title'] ?? '' ),
				'version'    => (int) ( $a['version'] ?? 1 ),
				'origin'     => (string) ( $a['origin_site_uuid'] ?? '' ),
				'originName' => (string) ( $a['origin_site_name'] ?? '' ),
				'originUrl'  => (string) ( $a['origin_site_url'] ?? '' ),
				'updated'    => (string) ( $a['updated_at'] ?? '' ),
			);
		}
		$out = array(
			'connected' => true,
			'count'     => count( $arts ),
			'artifacts' => $arts,
			'site'      => $site,
		);
		set_transient( $key, $out, self::LIB_TTL );
		return $out;
	}

	/**
	 * Pull one Cloud artifact in as a new inactive draft.
	 *
	 * @param string $kind Kind.
	 * @param string $uuid Artifact uuid.
	 * @return array|WP_Error
	 */
	public function library_import( string $kind, string $uuid ) {
		$type = EMCP_Tools_Admin_Sandbox_Data::type_of( $kind );
		$ok   = self::gate( $type, true );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$res = EMCP_Tools_Cloud_Sync::pull( $uuid, $kind );
		if ( is_wp_error( $res ) ) {
			return self::upstream( $res );
		}
		EMCP_Tools_Admin_Sandbox_Data::flush_nav();
		$id = (int) ( $res['id'] ?? 0 );
		return array(
			'id'        => $id,
			'type'      => $type,
			'message'   => __( 'Imported as a new inactive draft.', 'emcp-tools' ),
			'reviewUrl' => EMCP_Tools_Admin_Sandbox_Data::view_url( $type, $id ),
		);
	}

	/**
	 * Publish an update to an item's Marketplace listing (was ajax push_update).
	 *
	 * @param string $kind      Kind.
	 * @param int    $id        Id.
	 * @param string $changelog What changed.
	 * @return array|WP_Error
	 */
	public function marketplace_update( string $kind, int $id, string $changelog ) {
		$type = EMCP_Tools_Admin_Sandbox_Data::type_of( $kind );
		$ok   = self::gate( $type );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		if ( '' === (string) get_post_meta( $id, '_emcp_marketplace_slug', true ) ) {
			return new WP_Error( 'emcp_sandbox_no_listing', __( 'This item has no Marketplace listing.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$res = EMCP_Tools_Cloud_Sync::push_update( $kind, $id, $changelog );
		if ( is_wp_error( $res ) ) {
			return self::upstream( $res );
		}
		EMCP_Tools_Sandbox_Cloud_State::store_checksum( $kind, $id );
		EMCP_Tools_Sandbox_Cloud_State::refresh_marketplace( $kind, $id );
		return array(
			'row'     => EMCP_Tools_Admin_Sandbox_Data::row( $kind, $id, true ),
			'message' => __( 'Update sent for review.', 'emcp-tools' ),
		);
	}
}
