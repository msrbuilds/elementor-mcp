<?php
/**
 * EMCP Cloud sync: push/pull sandbox artifact bundles + config via the Cloud API.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Cloud_Sync {
	/**
	 * @return EMCP_Tools_Sandbox_Cloud_Abilities
	 */
	private static function abilities(): EMCP_Tools_Sandbox_Cloud_Abilities {
		return new EMCP_Tools_Sandbox_Cloud_Abilities();
	}

	private static function not_connected(): \WP_Error {
		return new \WP_Error( 'not_connected', __( 'This site is not connected to EMCP Cloud.', 'emcp-tools' ) );
	}

	/**
	 * Workspace plan + usage.
	 *
	 * @return array|\WP_Error
	 */
	public static function status() {
		return EMCP_Tools_Cloud_Client::get( '/api/cloud/v1/me' );
	}

	/**
	 * List the account's cloud artifacts (optionally by kind).
	 *
	 * @param string $kind Optional kind filter.
	 * @return array|\WP_Error
	 */
	public static function list_remote( string $kind = '' ) {
		$path = '/api/cloud/v1/artifacts' . ( '' !== $kind ? '?kind=' . rawurlencode( $kind ) : '' );
		return EMCP_Tools_Cloud_Client::get( $path );
	}

	/**
	 * Back up a local sandbox artifact to the cloud.
	 *
	 * @param string $kind Artifact kind (block/widget/snippet).
	 * @param int    $id   Local artifact id.
	 * @return array|\WP_Error
	 */
	public static function backup( string $kind, int $id ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return self::not_connected();
		}
		$art = self::abilities()->resolve_artifact( $kind );
		if ( ! $art ) {
			return new \WP_Error( 'unknown_kind', __( 'Unknown artifact kind.', 'emcp-tools' ) );
		}
		$bundle = $art->to_bundle( $id );
		if ( is_wp_error( $bundle ) ) {
			return $bundle;
		}
		return EMCP_Tools_Cloud_Client::put(
			'/api/cloud/v1/artifacts',
			array(
				'artifact_uuid'    => (string) ( $bundle['uuid'] ?? '' ),
				'kind'             => (string) ( $bundle['kind'] ?? $kind ),
				'title'            => (string) ( $bundle['meta']['title'] ?? '' ),
				'origin_site_uuid' => EMCP_Tools_Cloud::site_uuid(),
				'bundle'           => (string) wp_json_encode( $bundle ),
				'checksum'         => (string) ( $bundle['checksum'] ?? '' ),
			)
		);
	}

	/**
	 * The sandbox CPT post type for each artifact kind.
	 *
	 * @return array<string,string>
	 */
	private static function kind_post_types(): array {
		return array(
			'snippet' => class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) ? EMCP_Tools_PHP_Snippet_Store::POST_TYPE : 'emcp_php_snippet',
			'widget'  => class_exists( 'EMCP_Tools_Widget_Store' ) ? EMCP_Tools_Widget_Store::POST_TYPE : 'emcp_widget',
			'block'   => class_exists( 'EMCP_Tools_Block_Store' ) ? EMCP_Tools_Block_Store::POST_TYPE : 'emcp_block',
		);
	}

	/**
	 * Back up every local sandbox artifact (optionally only the given kinds) to
	 * the cloud in one call — the bulk counterpart to backup(). Reuses the
	 * per-artifact backup() so each push keeps its checksum/validation.
	 *
	 * Artifacts whose content has not changed since their last push are skipped
	 * (the cloud PUT is an upsert keyed by uuid, so re-pushing was harmless but
	 * re-uploaded the whole library on every click); pass $force to push them
	 * anyway.
	 *
	 * @param string[] $kinds Kinds to sync (block/widget/snippet); empty = all.
	 * @param bool     $force Push even artifacts that are already up to date.
	 * @return array|\WP_Error { pushed, skipped, failed, items:[{kind,id,ok,skipped?,error?}] }.
	 */
	public static function bulk_backup( array $kinds = array(), bool $force = false ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return self::not_connected();
		}
		$map     = self::kind_post_types();
		$kinds   = empty( $kinds ) ? array_keys( $map ) : array_values( array_intersect( $kinds, array_keys( $map ) ) );
		$results = array( 'pushed' => 0, 'skipped' => 0, 'failed' => 0, 'items' => array() );

		foreach ( $kinds as $kind ) {
			// Skip a kind whose store isn't available (e.g. block on a free build).
			if ( ! self::abilities()->resolve_artifact( $kind ) ) {
				continue;
			}
			$ids = get_posts(
				array(
					'post_type'      => $map[ $kind ],
					'post_status'    => array( 'publish', 'draft', 'pending' ),
					'posts_per_page' => 500,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			foreach ( (array) $ids as $id ) {
				if ( ! $force && self::is_up_to_date( $kind, (int) $id ) ) {
					$results['skipped']++;
					$results['items'][] = array( 'kind' => $kind, 'id' => (int) $id, 'ok' => true, 'skipped' => true );
					continue;
				}
				$res = self::backup( $kind, (int) $id );
				if ( is_wp_error( $res ) ) {
					$results['failed']++;
					$results['items'][] = array( 'kind' => $kind, 'id' => (int) $id, 'ok' => false, 'error' => $res->get_error_message() );
				} else {
					$results['pushed']++;
					$results['items'][] = array( 'kind' => $kind, 'id' => (int) $id, 'ok' => true );
				}
			}
		}
		return $results;
	}

	/**
	 * Whether a local artifact's content is unchanged since its last cloud push:
	 * it was pushed before AND the checksum recorded at that push equals the
	 * current one. A missing baseline (pushed before checksum tracking existed)
	 * counts as changed, since the content cannot be proven unchanged; the next
	 * push records a fresh baseline.
	 *
	 * @param string $kind Artifact kind.
	 * @param int    $id   Local artifact id.
	 * @return bool
	 */
	public static function is_up_to_date( string $kind, int $id ): bool {
		if ( ! get_post_meta( $id, '_emcp_cloud_pushed', true ) ) {
			return false;
		}
		$recorded = (string) get_post_meta( $id, '_emcp_cloud_checksum', true );
		if ( '' === $recorded ) {
			return false;
		}
		$art = self::abilities()->resolve_artifact( $kind );
		if ( ! $art ) {
			return false;
		}
		$current = (string) $art->checksum( $id );
		return '' !== $current && hash_equals( $recorded, $current );
	}

	/**
	 * Pull a cloud artifact into this site (imports as a new local draft).
	 *
	 * @param string $artifact_uuid Cloud artifact uuid.
	 * @param string $kind          Artifact kind (falls back to the bundle's kind).
	 * @return array|\WP_Error
	 */
	public static function pull( string $artifact_uuid, string $kind = '' ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return self::not_connected();
		}
		$res = EMCP_Tools_Cloud_Client::get( '/api/cloud/v1/artifacts/' . rawurlencode( $artifact_uuid ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$bundle = json_decode( (string) ( $res['bundle'] ?? '' ), true );
		if ( ! is_array( $bundle ) ) {
			return new \WP_Error( 'bad_bundle', __( 'The cloud artifact could not be read.', 'emcp-tools' ) );
		}
		$art = self::abilities()->resolve_artifact( '' !== $kind ? $kind : (string) ( $bundle['kind'] ?? '' ) );
		if ( ! $art ) {
			return new \WP_Error( 'unknown_kind', __( 'Unknown artifact kind.', 'emcp-tools' ) );
		}
		$new_id = $art->apply_bundle( $bundle );
		return is_wp_error( $new_id ) ? $new_id : array( 'id' => (int) $new_id );
	}

	/**
	 * Push a config blob (settings/brand_kit/tool_toggles) to the cloud.
	 *
	 * Settings are account-scoped so another connected site can pull them. Other
	 * config types retain their existing site-scoped behavior.
	 *
	 * @param string $type Config type.
	 * @param array  $data Config data.
	 * @return array|\WP_Error
	 */
	public static function push_config( string $type, array $data ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return self::not_connected();
		}
		$scope     = ( 'settings' === $type ) ? 'account' : 'site';
		$site_uuid = ( 'account' === $scope ) ? '' : EMCP_Tools_Cloud::site_uuid();
		return EMCP_Tools_Cloud_Client::put(
			'/api/cloud/v1/config/' . rawurlencode( $type ),
			array( 'scope' => $scope, 'site_uuid' => $site_uuid, 'data' => (string) wp_json_encode( $data ) )
		);
	}

	/**
	 * Pull a config blob from the cloud.
	 *
	 * @param string $type Config type.
	 * @return array|\WP_Error
	 */
	public static function pull_config( string $type ) {
		$scope     = ( 'settings' === $type ) ? 'account' : 'site';
		$site_uuid = ( 'account' === $scope ) ? '' : EMCP_Tools_Cloud::site_uuid();
		return EMCP_Tools_Cloud_Client::get(
			'/api/cloud/v1/config/' . rawurlencode( $type )
			. '?scope=' . rawurlencode( $scope )
			. '&site_uuid=' . rawurlencode( $site_uuid )
		);
	}

	/**
	 * Browse published marketplace listings (public; no connection required, but
	 * we still route through the client for a consistent base URL).
	 *
	 * Accepts either a bare category string (legacy) or an options array:
	 * `q`, `kind`, `category`, `access`, `sort`, `page`, `per_page`. When `page`
	 * or `per_page` is present the endpoint returns one page plus
	 * `{ page, per_page, total, pages }` meta; the response always carries a
	 * `facets` block ({ kinds, categories }).
	 *
	 * @param array|string $args Options array, or a bare category string (legacy).
	 * @return array|\WP_Error
	 */
	public static function marketplace_list( $args = array() ) {
		if ( is_string( $args ) ) {
			$args = ( '' !== $args ) ? array( 'category' => $args ) : array();
		}
		$query = array();
		foreach ( array( 'q', 'kind', 'category', 'access', 'sort', 'page', 'per_page', 'view' ) as $key ) {
			if ( isset( $args[ $key ] ) && '' !== (string) $args[ $key ] ) {
				$query[ $key ] = $args[ $key ];
			}
		}
		$path = '/api/cloud/v1/marketplace';
		if ( ! empty( $query ) ) {
			$path .= '?' . http_build_query( $query );
		}
		return EMCP_Tools_Cloud_Client::get( $path );
	}

	/**
	 * Website submit-page URL for publishing a backed-up artifact to the
	 * marketplace. The artifact must already be pushed to the cloud (its
	 * stable uuid is what the submit page looks up). '' if unresolvable.
	 *
	 * @param string $kind block|widget|snippet.
	 * @param int    $id   Local artifact id.
	 * @return string
	 */
	public static function publish_url( string $kind, int $id ): string {
		$art = self::abilities()->resolve_artifact( $kind );
		if ( ! $art ) {
			return '';
		}
		$uuid = (string) $art->uuid( $id );
		if ( '' === $uuid ) {
			return '';
		}
		return trailingslashit( EMCP_Tools_Cloud::base_url() ) . 'account/marketplace/submit?artifact=' . rawurlencode( $uuid );
	}

	/**
	 * Publish one of your cloud artifacts as a marketplace listing (pending
	 * moderation).
	 *
	 * @param string $artifact_uuid Cloud artifact uuid.
	 * @param string $title         Listing title.
	 * @param string $summary       Short description.
	 * @param string $category      Category.
	 * @return array|\WP_Error
	 */
	public static function marketplace_publish( string $artifact_uuid, string $title, string $summary = '', string $category = '' ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return self::not_connected();
		}
		return EMCP_Tools_Cloud_Client::put(
			'/api/cloud/v1/marketplace',
			array( 'artifact_uuid' => $artifact_uuid, 'title' => $title, 'summary' => $summary, 'category' => $category )
		);
	}

	/**
	 * Install a marketplace listing into this site (imports as a new draft).
	 *
	 * @param string $slug Listing slug.
	 * @return array|\WP_Error
	 */
	public static function marketplace_install( string $slug ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return self::not_connected();
		}
		$res = EMCP_Tools_Cloud_Client::request(
			'POST',
			'/api/cloud/v1/marketplace/' . rawurlencode( $slug ) . '/install',
			array( 'site_uuid' => EMCP_Tools_Cloud::site_uuid() )
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$bundle = json_decode( (string) ( $res['bundle'] ?? '' ), true );
		if ( ! is_array( $bundle ) ) {
			return new \WP_Error( 'bad_bundle', __( 'The listing bundle could not be read.', 'emcp-tools' ) );
		}
		$art = self::abilities()->resolve_artifact( (string) ( $res['kind'] ?? $bundle['kind'] ?? '' ) );
		if ( ! $art ) {
			return new \WP_Error( 'unknown_kind', __( 'Unknown artifact kind.', 'emcp-tools' ) );
		}
		$new_id = $art->apply_bundle( $bundle );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		// Remembered locally so the Marketplace screen can say "Installed",
		// whether the install came from the screen or the MCP tool.
		if ( class_exists( 'EMCP_Tools_Marketplace_Installs' ) ) {
			EMCP_Tools_Marketplace_Installs::record(
				$slug,
				(string) ( $res['kind'] ?? $bundle['kind'] ?? '' ),
				(int) $new_id,
				(string) ( $bundle['meta']['title'] ?? $slug )
			);
		}
		return array( 'id' => (int) $new_id );
	}

	/**
	 * Marketplace state for a local artifact (published? pending update? updatable?).
	 * Drives the Sandbox buttons.
	 *
	 * @param string $kind block|widget|snippet.
	 * @param int    $id   Local artifact id.
	 * @return array|\WP_Error
	 */
	public static function marketplace_state( string $kind, int $id ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return self::not_connected();
		}
		$art = self::abilities()->resolve_artifact( $kind );
		if ( ! $art ) {
			return new \WP_Error( 'unknown_kind', __( 'Unknown artifact kind.', 'emcp-tools' ) );
		}
		$uuid = (string) $art->uuid( $id );
		if ( '' === $uuid ) {
			return new \WP_Error( 'no_uuid', __( 'Artifact has no cloud id yet.', 'emcp-tools' ) );
		}
		return EMCP_Tools_Cloud_Client::get( '/api/cloud/v1/marketplace/state?artifact_uuid=' . rawurlencode( $uuid ) );
	}

	/**
	 * Push an update to an already-published marketplace listing: re-push the
	 * bundle (which creates a new cloud version) and flag the listing as a
	 * pending update for re-review.
	 *
	 * @param string $kind      block|widget|snippet.
	 * @param int    $id        Local artifact id.
	 * @param string $changelog Optional author notes.
	 * @return array|\WP_Error
	 */
	public static function push_update( string $kind, int $id, string $changelog = '' ) {
		$backup = self::backup( $kind, $id );
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}
		$art = self::abilities()->resolve_artifact( $kind );
		if ( ! $art ) {
			return new \WP_Error( 'unknown_kind', __( 'Unknown artifact kind.', 'emcp-tools' ) );
		}
		return EMCP_Tools_Cloud_Client::request(
			'POST',
			'/api/cloud/v1/marketplace/update',
			array( 'artifact_uuid' => (string) $art->uuid( $id ), 'changelog' => $changelog )
		);
	}

	/**
	 * Public marketplace URL for a published listing.
	 *
	 * @param string $slug Listing slug.
	 * @return string
	 */
	public static function marketplace_view_url( string $slug ): string {
		return trailingslashit( EMCP_Tools_Cloud::base_url() ) . 'marketplace/' . rawurlencode( $slug );
	}
}
