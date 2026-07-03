<?php
/**
 * Performance Analyzer orchestrator.
 *
 * Resolves the target page, runs the server audit and (optionally) the page
 * audit, and scores the merged findings. Read-only.
 *
 * Ported from upstream msrbuilds/elementor-mcp (v3.0.0), adapted to this fork's
 * class/helper naming (the upstream rename to emcp-tools is not adopted).
 *
 * @package Elementor_MCP
 * @since   1.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 1.11.0
 */
class Elementor_MCP_Performance_Analyzer {

	const CRITICAL_WEIGHT = 15;
	const WARNING_WEIGHT  = 4;
	const TOP_RECS        = 8;

	/** @var Elementor_MCP_Performance_Server_Audit */
	private $server;

	/** @var Elementor_MCP_Performance_Page_Audit */
	private $page;

	public function __construct( ?Elementor_MCP_Performance_Server_Audit $server = null, ?Elementor_MCP_Performance_Page_Audit $page = null ) {
		$this->server = $server ?: new Elementor_MCP_Performance_Server_Audit();
		$this->page   = $page ?: new Elementor_MCP_Performance_Page_Audit();
	}

	/**
	 * @param array $input { url?, post_id?, include_page_fetch?, deep_assets? }
	 * @return array|\WP_Error
	 */
	public function analyze( array $input ) {
		$target = $this->resolve_target( $input );
		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$findings   = $this->server->run();
		$page_fetch = array( 'ok' => false, 'status_code' => 0, 'response_ms' => 0, 'total_bytes' => 0, 'error' => 'not_requested' );

		$do_fetch = ! isset( $input['include_page_fetch'] ) || (bool) $input['include_page_fetch'];
		if ( $do_fetch ) {
			$fetched   = $this->page->fetch( $target['resolved_url'] );
			$result    = $this->page->analyze( $fetched, ! empty( $input['deep_assets'] ) );
			$findings  = array_merge( $findings, $result['findings'] );
			$page_fetch = $result['page_fetch'];
		}

		$summary  = $this->summarize( $findings );
		$sections = $this->group_by_category( $findings );

		return array(
			'target'              => $target,
			'summary'             => array(
				'score'  => $summary['score'],
				'grade'  => $summary['grade'],
				'counts' => $summary['counts'],
			),
			'sections'            => $sections,
			'page_fetch'          => $page_fetch,
			'top_recommendations' => $summary['top_recommendations'],
		);
	}

	/**
	 * Resolve url|post_id|frontpage to a same-host absolute URL.
	 *
	 * @param array $input
	 * @return array|\WP_Error { resolved_url, post_id, is_front_page }
	 */
	private function resolve_target( array $input ) {
		$site_url = home_url();

		if ( ! empty( $input['url'] ) ) {
			$url = esc_url_raw( (string) $input['url'] );
			if ( '' === $url || ! $this->same_origin( $url, $site_url ) ) {
				return new \WP_Error( 'invalid_target', __( 'The url must be a page on this site.', 'elementor-mcp' ) );
			}
			return array( 'resolved_url' => $url, 'post_id' => null, 'is_front_page' => false );
		}

		if ( ! empty( $input['post_id'] ) ) {
			$post_id = (int) $input['post_id'];
			$link    = get_permalink( $post_id );
			if ( ! $link ) {
				return new \WP_Error( 'invalid_target', __( 'No published page found for that post_id.', 'elementor-mcp' ) );
			}
			return array( 'resolved_url' => $link, 'post_id' => $post_id, 'is_front_page' => false );
		}

		return array( 'resolved_url' => home_url( '/' ), 'post_id' => null, 'is_front_page' => true );
	}

	/**
	 * Pure: does $url share the FULL origin (scheme + host + port) of $site_url?
	 *
	 * Comparing only the host is an SSRF hole: http://example.com:8080/ (a
	 * different port) or an http:// downgrade would pass the host-only check when
	 * the site is https://example.com, letting the "this-site-only" loopback fetch
	 * a different service. Default ports (80/443) are normalized so
	 * https://example.com and https://example.com:443 match.
	 *
	 * @param string $url
	 * @param string $site_url
	 * @return bool
	 */
	public function same_origin( string $url, string $site_url ): bool {
		$a = $this->origin_parts( $url );
		$b = $this->origin_parts( $site_url );
		return null !== $a && null !== $b && $a === $b;
	}

	/**
	 * Pure: normalized { scheme, host, port } for $url, or null when it has no host.
	 *
	 * @param string $url
	 * @return array{scheme:string,host:string,port:int}|null
	 */
	private function origin_parts( string $url ): ?array {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return null;
		}
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$port   = wp_parse_url( $url, PHP_URL_PORT );
		return array(
			'scheme' => $scheme,
			'host'   => $host,
			'port'   => $this->normalize_port( $scheme, $port ),
		);
	}

	/**
	 * Pure: resolve the effective port, defaulting 443 for https and 80 otherwise.
	 *
	 * @param string   $scheme
	 * @param int|null $port
	 * @return int
	 */
	private function normalize_port( string $scheme, $port ): int {
		if ( null !== $port && '' !== $port ) {
			return (int) $port;
		}
		return 'https' === $scheme ? 443 : 80;
	}

	/**
	 * Pure: counts, score, grade, ranked recommendations.
	 *
	 * @param array $findings Finding[]
	 * @return array { counts, score, grade, top_recommendations }
	 */
	public function summarize( array $findings ): array {
		$counts = array( 'critical' => 0, 'warning' => 0, 'pass' => 0, 'info' => 0 );
		foreach ( $findings as $f ) {
			$status = (string) ( $f['status'] ?? 'info' );
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ]++;
			}
		}

		$score = 100 - ( $counts['critical'] * self::CRITICAL_WEIGHT ) - ( $counts['warning'] * self::WARNING_WEIGHT );
		$score = max( 0, min( 100, $score ) );

		if ( $score >= 90 )      { $grade = 'A'; }
		elseif ( $score >= 80 )  { $grade = 'B'; }
		elseif ( $score >= 70 )  { $grade = 'C'; }
		elseif ( $score >= 60 )  { $grade = 'D'; }
		else                     { $grade = 'F'; }

		return array(
			'counts'              => $counts,
			'score'               => $score,
			'grade'               => $grade,
			'top_recommendations' => $this->rank_recommendations( $findings ),
		);
	}

	/**
	 * @param array $findings Finding[]
	 * @return string[]
	 */
	private function rank_recommendations( array $findings ): array {
		$crit = array();
		$warn = array();
		foreach ( $findings as $f ) {
			$rec = trim( (string) ( $f['recommendation'] ?? '' ) );
			if ( '' === $rec ) {
				continue;
			}
			$line = sprintf( '[%s] %s', (string) ( $f['label'] ?? '' ), $rec );
			if ( 'critical' === ( $f['status'] ?? '' ) ) {
				$crit[] = $line;
			} elseif ( 'warning' === ( $f['status'] ?? '' ) ) {
				$warn[] = $line;
			}
		}
		return array_slice( array_merge( $crit, $warn ), 0, self::TOP_RECS );
	}

	/**
	 * @param array $findings Finding[]
	 * @return array category => Finding[]
	 */
	private function group_by_category( array $findings ): array {
		$sections = array( 'server' => array(), 'database' => array(), 'config' => array(), 'page' => array(), 'assets' => array() );
		foreach ( $findings as $f ) {
			$cat = (string) ( $f['category'] ?? 'server' );
			if ( ! isset( $sections[ $cat ] ) ) {
				$sections[ $cat ] = array();
			}
			$sections[ $cat ][] = $f;
		}
		return $sections;
	}
}
