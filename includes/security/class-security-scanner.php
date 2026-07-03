<?php
/**
 * Security & Malware Scanner orchestrator.
 *
 * resolve_checks(), summarize(), group_by_category() are pure (unit-tested).
 * scan() wires the four audits together (verified live). Read-only.
 *
 * Ported from upstream msrbuilds/elementor-mcp (v3.0.0), adapted to this fork's
 * class/helper naming (the upstream rename to emcp-tools is not adopted).
 *
 * @package Elementor_MCP
 * @since   1.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 1.12.0
 */
class Elementor_MCP_Security_Scanner {

	const CRITICAL_WEIGHT   = 20;
	const WARNING_WEIGHT    = 5;
	const CATEGORY_CRIT_CAP = 60;
	const TOP_RECS          = 8;

	const ALL_CHECKS = array( 'malware', 'integrity', 'hardening', 'software' );

	/** @var Elementor_MCP_Security_Malware_Audit */
	private $malware;
	/** @var Elementor_MCP_Security_Integrity_Audit */
	private $integrity;
	/** @var Elementor_MCP_Security_Hardening_Audit */
	private $hardening;
	/** @var Elementor_MCP_Security_Software_Audit */
	private $software;

	public function __construct(
		?Elementor_MCP_Security_Malware_Audit $malware = null,
		?Elementor_MCP_Security_Integrity_Audit $integrity = null,
		?Elementor_MCP_Security_Hardening_Audit $hardening = null,
		?Elementor_MCP_Security_Software_Audit $software = null
	) {
		$this->malware   = $malware ?: new Elementor_MCP_Security_Malware_Audit();
		$this->integrity = $integrity ?: new Elementor_MCP_Security_Integrity_Audit();
		$this->hardening = $hardening ?: new Elementor_MCP_Security_Hardening_Audit();
		$this->software  = $software ?: new Elementor_MCP_Security_Software_Audit();
	}

	/**
	 * Pure: normalize the requested checks to a canonical-ordered valid subset.
	 *
	 * @param array|null $requested
	 * @return string[]
	 */
	public function resolve_checks( ?array $requested ): array {
		if ( empty( $requested ) ) {
			return self::ALL_CHECKS;
		}
		$valid = array();
		foreach ( self::ALL_CHECKS as $check ) {
			if ( in_array( $check, $requested, true ) ) {
				$valid[] = $check;
			}
		}
		return empty( $valid ) ? self::ALL_CHECKS : $valid;
	}

	/**
	 * Live: run the requested audits and assemble the report.
	 *
	 * @param array $input { checks?, deep?, max_files?, max_seconds? }
	 * @return array
	 */
	public function scan( array $input ): array {
		$checks = $this->resolve_checks( isset( $input['checks'] ) && is_array( $input['checks'] ) ? $input['checks'] : null );
		$deep   = ! empty( $input['deep'] );

		$max_files   = $this->clamp( (int) ( $input['max_files'] ?? Elementor_MCP_Security_Malware_Audit::MAX_FILES ), 1, Elementor_MCP_Security_Malware_Audit::MAX_FILES_CEILING );
		$max_seconds = $this->clamp( (int) ( $input['max_seconds'] ?? Elementor_MCP_Security_Malware_Audit::TIME_BUDGET ), 1, Elementor_MCP_Security_Malware_Audit::TIME_BUDGET_CEILING );

		$started   = microtime( true );
		$findings  = array();
		$scan_meta = array(
			'files_scanned'      => 0,
			'files_skipped_size' => 0,
			'truncated'          => false,
			'truncated_reason'   => null,
			'deep'               => $deep,
			'checks_run'         => $checks,
			'integrity_api'      => array( 'ok' => false, 'error' => 'not_run' ),
			'headers_fetch'      => array( 'ok' => false, 'error' => 'not_run' ),
			'elapsed_ms'         => 0,
		);

		if ( in_array( 'malware', $checks, true ) ) {
			$m = $this->malware->run( $deep, $max_files, $max_seconds );
			$findings = array_merge( $findings, $m['findings'] );
			$scan_meta['files_scanned']      = (int) $m['stats']['files_scanned'];
			$scan_meta['files_skipped_size'] = (int) $m['stats']['files_skipped_size'];
			$scan_meta['truncated']          = (bool) $m['stats']['truncated'];
			$scan_meta['truncated_reason']   = $m['stats']['truncated_reason'];
		}
		if ( in_array( 'integrity', $checks, true ) ) {
			$i = $this->integrity->run();
			$findings = array_merge( $findings, $i['findings'] );
			$scan_meta['integrity_api'] = $i['api'];
		}
		if ( in_array( 'hardening', $checks, true ) ) {
			$h = $this->hardening->run();
			$findings = array_merge( $findings, $h['findings'] );
			$scan_meta['headers_fetch'] = $h['headers_fetch'];
		}
		if ( in_array( 'software', $checks, true ) ) {
			$findings = array_merge( $findings, $this->software->run() );
		}

		$summary               = $this->summarize( $findings );
		$scan_meta['elapsed_ms'] = (int) round( ( microtime( true ) - $started ) * 1000 );

		return array(
			'summary'             => array(
				'score'  => $summary['score'],
				'grade'  => $summary['grade'],
				'counts' => $summary['counts'],
			),
			'sections'            => $this->group_by_category( $findings ),
			'scan_meta'           => $scan_meta,
			'top_recommendations' => $summary['top_recommendations'],
		);
	}

	/**
	 * Pure: counts, score (per-category critical cap), grade, ranked recs.
	 *
	 * @param array $findings Finding[]
	 * @return array { counts, score, grade, top_recommendations }
	 */
	public function summarize( array $findings ): array {
		$counts        = array( 'critical' => 0, 'warning' => 0, 'pass' => 0, 'info' => 0 );
		$cat_crit_pen  = array();
		$warn_penalty  = 0;

		foreach ( $findings as $f ) {
			$status = (string) ( $f['status'] ?? 'info' );
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ]++;
			}
			if ( 'critical' === $status ) {
				$cat = (string) ( $f['category'] ?? 'malware' );
				$cat_crit_pen[ $cat ] = min( self::CATEGORY_CRIT_CAP, ( $cat_crit_pen[ $cat ] ?? 0 ) + self::CRITICAL_WEIGHT );
			} elseif ( 'warning' === $status ) {
				$warn_penalty += self::WARNING_WEIGHT;
			}
		}

		$score = 100 - array_sum( $cat_crit_pen ) - $warn_penalty;
		$score = max( 0, min( 100, $score ) );

		if ( $score >= 90 )     { $grade = 'A'; }
		elseif ( $score >= 80 ) { $grade = 'B'; }
		elseif ( $score >= 70 ) { $grade = 'C'; }
		elseif ( $score >= 60 ) { $grade = 'D'; }
		else                    { $grade = 'F'; }

		return array(
			'counts'              => $counts,
			'score'               => $score,
			'grade'               => $grade,
			'top_recommendations' => $this->rank_recommendations( $findings ),
		);
	}

	/**
	 * @param array $findings Finding[]
	 * @return array category => Finding[]
	 */
	public function group_by_category( array $findings ): array {
		$sections = array( 'malware' => array(), 'integrity' => array(), 'hardening' => array(), 'software' => array() );
		foreach ( $findings as $f ) {
			$cat = (string) ( $f['category'] ?? 'malware' );
			if ( ! isset( $sections[ $cat ] ) ) {
				$sections[ $cat ] = array();
			}
			$sections[ $cat ][] = $f;
		}
		return $sections;
	}

	// ---- helpers ------------------------------------------------------

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

	private function clamp( int $value, int $min, int $max ): int {
		return max( $min, min( $max, $value ) );
	}
}
