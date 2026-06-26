<?php
/**
 * Performance Analyzer orchestrator.
 *
 * Resolves the target page, runs the server audit and (optionally) the page
 * audit, and scores the merged findings. Read-only.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.0.0
 */
class EMCP_Tools_Performance_Analyzer {

	const CRITICAL_WEIGHT = 15;
	const WARNING_WEIGHT  = 4;
	const TOP_RECS        = 8;

	/** @var EMCP_Tools_Performance_Server_Audit */
	private $server;

	/** @var EMCP_Tools_Performance_Page_Audit */
	private $page;

	public function __construct( ?EMCP_Tools_Performance_Server_Audit $server = null, ?EMCP_Tools_Performance_Page_Audit $page = null ) {
		$this->server = $server ?: new EMCP_Tools_Performance_Server_Audit();
		$this->page   = $page ?: new EMCP_Tools_Performance_Page_Audit();
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
		$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! empty( $input['url'] ) ) {
			$url = esc_url_raw( (string) $input['url'] );
			if ( '' === $url || ! $this->validate_same_host( $url, $site_host ) ) {
				return new \WP_Error( 'invalid_target', __( 'The url must be a page on this site.', 'emcp-tools' ) );
			}
			return array( 'resolved_url' => $url, 'post_id' => null, 'is_front_page' => false );
		}

		if ( ! empty( $input['post_id'] ) ) {
			$post_id = (int) $input['post_id'];
			$link    = get_permalink( $post_id );
			if ( ! $link ) {
				return new \WP_Error( 'invalid_target', __( 'No published page found for that post_id.', 'emcp-tools' ) );
			}
			return array( 'resolved_url' => $link, 'post_id' => $post_id, 'is_front_page' => false );
		}

		return array( 'resolved_url' => home_url( '/' ), 'post_id' => null, 'is_front_page' => true );
	}

	/**
	 * Pure: is $url on $site_host?
	 *
	 * @param string $url
	 * @param string $site_host
	 * @return bool
	 */
	public function validate_same_host( string $url, string $site_host ): bool {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		return '' !== $host && strtolower( $host ) === strtolower( $site_host );
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
