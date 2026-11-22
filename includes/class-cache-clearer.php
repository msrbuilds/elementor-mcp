<?php
/**
 * Clears whatever page, asset and host caches a site has (#201).
 *
 * Every layer is detected from the API its plugin or host exposes and reports
 * its own result: purged (the call returned), queued (the purge happens later,
 * such as LiteSpeed, which purges through a response header), skipped or
 * failed. Nothing is reported as purged without a call that did it.
 *
 * APIs were read from each plugin's source (W3 Total Cache, WP Super Cache,
 * WP Fastest Cache, Autoptimize, Breeze, SiteGround Speed Optimizer,
 * WP-Optimize, Cache Enabler, LiteSpeed Cache). WP Rocket uses its public
 * functions. Kinsta has no documented PHP API: the MU plugin's own purge is
 * called when present, else the same internal request it sends; this layer
 * is not verified on a live Kinsta site.
 *
 * @package EMCP_Tools
 * @since   3.22.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Detects and clears cache layers.
 */
final class EMCP_Tools_Cache_Clearer {

	const PURGED  = 'purged';
	const QUEUED  = 'queued';
	const SKIPPED = 'skipped';
	const FAILED  = 'failed';

	/**
	 * The known layers: id => array{label, detect, purge}. purge returns a status
	 * or array{status, detail}. Filterable through emcp_tools_cache_layers.
	 *
	 * @return array<string, array>
	 */
	public static function layers(): array {
		$layers = array(
			'elementor'        => array(
				'label'  => 'Elementor CSS',
				'detect' => static fn() => class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ),
				'purge'  => static function () {
					\Elementor\Plugin::$instance->files_manager->clear_cache();
					return self::PURGED;
				},
			),
			'wp-rocket'        => array(
				'label'  => 'WP Rocket',
				'detect' => static fn() => function_exists( 'rocket_clean_domain' ),
				'purge'  => static function () {
					rocket_clean_domain();
					if ( function_exists( 'rocket_clean_minify' ) ) {
						rocket_clean_minify();
					}
					return self::PURGED;
				},
			),
			'litespeed'        => array(
				'label'  => 'LiteSpeed Cache',
				'detect' => static fn() => defined( 'LSCWP_V' ) || has_action( 'litespeed_purge_all' ),
				'purge'  => static function () {
					do_action( 'litespeed_purge_all', 'EMCP Tools' );
					return array( 'status' => self::QUEUED, 'detail' => 'LiteSpeed purges through a header on this response; pages update on their next request.' );
				},
			),
			'w3-total-cache'   => array(
				'label'  => 'W3 Total Cache',
				'detect' => static fn() => function_exists( 'w3tc_flush_all' ),
				'purge'  => static function () {
					w3tc_flush_all();
					return self::PURGED;
				},
			),
			'wp-super-cache'   => array(
				'label'  => 'WP Super Cache',
				'detect' => static fn() => function_exists( 'wp_cache_clear_cache' ),
				'purge'  => static function () {
					wp_cache_clear_cache();
					return self::PURGED;
				},
			),
			'wp-fastest-cache' => array(
				'label'  => 'WP Fastest Cache',
				'detect' => static fn() => function_exists( 'wpfc_clear_all_cache' ),
				'purge'  => static function () {
					wpfc_clear_all_cache( true );
					return self::PURGED;
				},
			),
			'autoptimize'      => array(
				'label'  => 'Autoptimize',
				'detect' => static fn() => class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ),
				'purge'  => static function () {
					return \autoptimizeCache::clearall() ? self::PURGED : self::FAILED;
				},
			),
			'breeze'           => array(
				'label'  => 'Breeze',
				'detect' => static fn() => (bool) has_action( 'breeze_clear_all_cache' ),
				'purge'  => static function () {
					do_action( 'breeze_clear_all_cache' );
					return self::PURGED;
				},
			),
			'siteground'       => array(
				'label'  => 'SiteGround Speed Optimizer',
				'detect' => static fn() => function_exists( 'sg_cachepress_purge_everything' ),
				'purge'  => static function () {
					sg_cachepress_purge_everything();
					return self::PURGED;
				},
			),
			'wp-optimize'      => array(
				'label'  => 'WP-Optimize',
				'detect' => static fn() => function_exists( 'WP_Optimize' ) && method_exists( WP_Optimize(), 'get_page_cache' ),
				'purge'  => static function () {
					$cache = WP_Optimize()->get_page_cache();
					return $cache && method_exists( $cache, 'purge' ) && $cache->purge() ? self::PURGED : self::FAILED;
				},
			),
			'cache-enabler'    => array(
				'label'  => 'Cache Enabler',
				'detect' => static fn() => (bool) has_action( 'cache_enabler_clear_complete_cache' ),
				'purge'  => static function () {
					do_action( 'cache_enabler_clear_complete_cache' );
					return self::PURGED;
				},
			),
			'kinsta'           => array(
				'label'  => 'Kinsta',
				'detect' => static fn() => isset( $GLOBALS['kinsta_cache'] ) || class_exists( 'Kinsta\Cache_Purge' ),
				'purge'  => array( __CLASS__, 'purge_kinsta' ),
			),
		);
		/**
		 * Filters the cache layers clear-cache knows.
		 *
		 * @param array $layers id => array{label, detect, purge}.
		 */
		return (array) apply_filters( 'emcp_tools_cache_layers', $layers );
	}

	/**
	 * Kinsta: the MU plugin's own purge when reachable, else the internal
	 * request that purge sends.
	 *
	 * @return array{status: string, detail: string}
	 */
	public static function purge_kinsta(): array {
		$kmp = $GLOBALS['kinsta_cache'] ?? null;
		foreach ( array( 'kinsta_cache_purge', 'cache_purge' ) as $prop ) {
			if ( is_object( $kmp ) && isset( $kmp->$prop ) && is_callable( array( $kmp->$prop, 'purge_complete_caches' ) ) ) {
				$kmp->$prop->purge_complete_caches( true );
				return array( 'status' => self::PURGED, 'detail' => 'Kinsta MU plugin purge (not verified on a live Kinsta site).' );
			}
		}
		// The MU plugin's purge requests this endpoint on the server itself.
		$res = wp_remote_get( 'https://localhost/kinsta-clear-cache-all', array( 'sslverify' => false, 'timeout' => 5 ) );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return array( 'status' => self::FAILED, 'detail' => 'The Kinsta cache endpoint did not answer. Use Clear All Caches in the admin bar or MyKinsta.' );
		}
		return array( 'status' => self::QUEUED, 'detail' => 'Kinsta accepted the purge request (not verified on a live Kinsta site).' );
	}

	/**
	 * Clear every detected layer, or only the ids in $only.
	 *
	 * @param string[] $only         Layer ids to clear; empty for all detected.
	 * @param bool     $object_cache Also flush the object cache.
	 * @return array{results: array, purged: int, detected: string[]}
	 */
	public static function clear( array $only = array(), bool $object_cache = false ): array {
		$results  = array();
		$detected = array();
		foreach ( self::layers() as $id => $layer ) {
			$present = self::call_bool( $layer['detect'] ?? null );
			if ( $present ) {
				$detected[] = (string) $id;
			}
			if ( ! empty( $only ) && ! in_array( $id, $only, true ) ) {
				continue;
			}
			$row = array( 'layer' => (string) $id, 'label' => (string) ( $layer['label'] ?? $id ) );
			if ( ! $present ) {
				if ( ! empty( $only ) ) {
					$results[] = $row + array( 'status' => self::SKIPPED, 'detail' => 'Not active on this site.' );
				}
				continue;
			}
			$results[] = $row + self::run( $layer['purge'] ?? null );
		}
		if ( $object_cache ) {
			$row       = array( 'layer' => 'object-cache', 'label' => 'Object cache' );
			$results[] = $row + ( function_exists( 'wp_cache_flush' ) && wp_cache_flush() ? array( 'status' => self::PURGED, 'detail' => '' ) : array( 'status' => self::FAILED, 'detail' => 'wp_cache_flush() failed.' ) );
		}
		return array(
			'results'  => $results,
			'purged'   => count( array_filter( $results, static fn( $r ) => in_array( $r['status'], array( self::PURGED, self::QUEUED ), true ) ) ),
			'detected' => $detected,
		);
	}

	/**
	 * Run one purge, turning anything it throws into a failed result.
	 *
	 * @param mixed $purge Callable.
	 * @return array{status: string, detail: string}
	 */
	private static function run( $purge ): array {
		if ( ! is_callable( $purge ) ) {
			return array( 'status' => self::FAILED, 'detail' => 'No purge method.' );
		}
		try {
			$out = call_user_func( $purge );
		} catch ( \Throwable $e ) {
			return array( 'status' => self::FAILED, 'detail' => substr( $e->getMessage(), 0, 300 ) );
		}
		if ( is_array( $out ) ) {
			return array( 'status' => (string) ( $out['status'] ?? self::FAILED ), 'detail' => (string) ( $out['detail'] ?? '' ) );
		}
		return array( 'status' => in_array( $out, array( self::PURGED, self::QUEUED, self::FAILED ), true ) ? $out : self::PURGED, 'detail' => '' );
	}

	/**
	 * @param mixed $fn Callable.
	 */
	private static function call_bool( $fn ): bool {
		if ( ! is_callable( $fn ) ) {
			return false;
		}
		try {
			return (bool) call_user_func( $fn );
		} catch ( \Throwable $e ) {
			return false;
		}
	}
}
