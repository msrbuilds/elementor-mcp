<?php
/**
 * Marketplace screen data (spec 8.18). The Cloud API filters by search,
 * category, access and sort; type counts, the verified filter and paging are
 * done here over one cached full set, because the API has neither counts nor
 * a verified filter.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_Marketplace_Data {

	const PER_PAGE  = 24;
	const CACHE_TTL = 300;
	const ERROR_TTL = 60;
	const TYPES     = array( 'block', 'widget', 'snippet', 'template' );

	private static function connected(): bool {
		return class_exists( 'EMCP_Tools_Cloud' ) && EMCP_Tools_Cloud::is_connected();
	}

	public static function kind_labels(): array {
		return array(
			'block'    => __( 'Block', 'emcp-tools' ),
			'widget'   => __( 'Widget', 'emcp-tools' ),
			'snippet'  => __( 'Snippet', 'emcp-tools' ),
			'template' => __( 'Template', 'emcp-tools' ),
		);
	}

	private static function kind( string $kind ): string {
		return 'php_snippet' === $kind ? 'snippet' : $kind;
	}

	/** Boot data: no remote request (spec 5.2). */
	public function boot(): array {
		return array(
			'connected'  => self::connected(),
			'connectUrl' => admin_url( 'admin.php?page=emcp-tools-connection&section=cloud' ),
			'sandboxUrl' => admin_url( 'admin.php?page=emcp-tools-widgets' ),
			'kinds'      => self::kind_labels(),
		);
	}

	/**
	 * The full filtered set from the Cloud, cached per filter combination.
	 *
	 * @param array $remote Cloud query (q, category, access, sort).
	 * @return array{rows: array, categories: array, error: string}
	 */
	private function fetch( array $remote ): array {
		$key    = 'emcp_tools_mk_' . md5( (string) wp_json_encode( $remote ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$res = EMCP_Tools_Cloud_Sync::marketplace_list( $remote );
		if ( is_wp_error( $res ) ) {
			$out = array( 'rows' => array(), 'categories' => array(), 'error' => $res->get_error_message() );
			set_transient( $key, $out, self::ERROR_TTL );
			return $out;
		}
		$out = array(
			'rows'       => is_array( $res['listings'] ?? null ) ? $res['listings'] : array(),
			'categories' => array_values( array_map( 'strval', (array) ( $res['facets']['categories'] ?? array() ) ) ),
			'error'      => '',
		);
		set_transient( $key, $out, self::CACHE_TTL );
		return $out;
	}

	private static function item( array $l, array $labels ): array {
		$kind   = self::kind( (string) ( $l['kind'] ?? '' ) );
		$author = is_array( $l['author'] ?? null ) ? $l['author'] : null;
		$slug   = (string) ( $l['slug'] ?? '' );
		$shots  = is_array( $l['screenshots'] ?? null ) ? array_values( $l['screenshots'] ) : array();
		return array(
			'slug'         => $slug,
			'kind'         => $kind,
			'kindLabel'    => (string) ( $labels[ $kind ] ?? $kind ),
			'title'        => (string) ( $l['title'] ?? $slug ),
			'summary'      => (string) ( $l['summary'] ?? '' ),
			'category'     => (string) ( $l['category'] ?? '' ),
			'access'       => 'pro' === ( $l['access'] ?? '' ) ? 'pro' : 'community',
			'thumbnail'    => (string) ( $shots[0] ?? '' ),
			'previewUrl'   => (string) ( $l['preview_url'] ?? '' ),
			'installCount' => (int) ( $l['install_count'] ?? 0 ),
			'author'       => $author ? array(
				'name'       => (string) ( $author['name'] ?? '' ),
				'avatar'     => (string) ( $author['avatar'] ?? '' ),
				'verified'   => ! empty( $author['verified'] ),
				'profileUrl' => (string) ( $author['profile_url'] ?? '' ),
			) : null,
			'installed'    => EMCP_Tools_Marketplace_Installs::installed( $slug ),
		);
	}

	public function payload( array $query ): array {
		$empty = array(
			'connected'  => self::connected(),
			'items'      => array(),
			'total'      => 0,
			'page'       => 1,
			'pages'      => 1,
			'counts'     => array_merge( array( 'all' => 0 ), array_fill_keys( self::TYPES, 0 ) ),
			'categories' => array(),
			'error'      => '',
		);
		if ( ! $empty['connected'] ) {
			return $empty;
		}
		$remote = array_filter(
			array(
				'q'        => (string) ( $query['search'] ?? '' ),
				'category' => (string) ( $query['category'] ?? '' ),
				'access'   => in_array( $query['access'] ?? '', array( 'community', 'pro' ), true ) ? $query['access'] : '',
				'sort'     => 'popular' === ( $query['sort'] ?? '' ) ? 'popular' : 'newest',
			),
			'strlen'
		);
		$set    = $this->fetch( $remote );
		$labels = self::kind_labels();
		$rows   = array_map(
			static function ( $l ) use ( $labels ): array {
				return self::item( is_array( $l ) ? $l : array(), $labels );
			},
			$set['rows']
		);
		if ( ! empty( $query['verified'] ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( array $r ): bool {
						return ! empty( $r['author']['verified'] );
					}
				)
			);
		}
		$counts = $empty['counts'];
		foreach ( $rows as $r ) {
			++$counts['all'];
			if ( isset( $counts[ $r['kind'] ] ) ) {
				++$counts[ $r['kind'] ];
			}
		}
		$type = (string) ( $query['type'] ?? '' );
		if ( in_array( $type, self::TYPES, true ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( array $r ) use ( $type ): bool {
						return $type === $r['kind'];
					}
				)
			);
		}
		$total = count( $rows );
		$pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page  = min( $pages, max( 1, (int) ( $query['page'] ?? 1 ) ) );
		return array_merge(
			$empty,
			array(
				'items'      => array_slice( $rows, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE ),
				'total'      => $total,
				'page'       => $page,
				'pages'      => $pages,
				'counts'     => $counts,
				'categories' => $set['categories'],
				'error'      => $set['error'],
			)
		);
	}
}
