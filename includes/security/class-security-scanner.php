<?php
/**
 * Security & Malware Scanner orchestrator.
 *
 * resolve_checks(), summarize(), group_by_category() are pure (unit-tested).
 * scan() wires the four audits together (verified live). Read-only.
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
class EMCP_Tools_Security_Scanner {

	const CRITICAL_WEIGHT   = 20;
	const WARNING_WEIGHT    = 5;
	const CATEGORY_CRIT_CAP = 60;
	const TOP_RECS          = 8;

	const ALL_CHECKS = array( 'malware', 'integrity', 'hardening', 'software' );

	/** @var EMCP_Tools_Security_Malware_Audit|null Built on first use. */
	private $malware;
	/** @var EMCP_Tools_Security_Integrity_Audit|null Built on first use. */
	private $integrity;
	/** @var EMCP_Tools_Security_Hardening_Audit|null Built on first use. */
	private $hardening;
	/** @var EMCP_Tools_Security_Software_Audit|null Built on first use. */
	private $software;

	/**
	 * Audits are built lazily, on the first scan that needs them.
	 *
	 * Registering the scan-security tool must not instantiate the audit engine.
	 * Ability registration runs on every admin page load and every REST request,
	 * so eagerly constructing the audits here meant a single unavailable audit
	 * class turned tool registration into a site-wide fatal (issue #100, where a
	 * host malware scanner had quarantined the malware-audit file). Deferring
	 * construction keeps that failure inside the one tool that needs the class.
	 *
	 * Passing instances still works and is what the tests inject.
	 */
	public function __construct(
		?EMCP_Tools_Security_Malware_Audit $malware = null,
		?EMCP_Tools_Security_Integrity_Audit $integrity = null,
		?EMCP_Tools_Security_Hardening_Audit $hardening = null,
		?EMCP_Tools_Security_Software_Audit $software = null
	) {
		$this->malware   = $malware;
		$this->integrity = $integrity;
		$this->hardening = $hardening;
		$this->software  = $software;
	}

	private function malware(): EMCP_Tools_Security_Malware_Audit {
		if ( null === $this->malware ) {
			$this->malware = new EMCP_Tools_Security_Malware_Audit();
		}
		return $this->malware;
	}

	private function integrity(): EMCP_Tools_Security_Integrity_Audit {
		if ( null === $this->integrity ) {
			$this->integrity = new EMCP_Tools_Security_Integrity_Audit();
		}
		return $this->integrity;
	}

	private function hardening(): EMCP_Tools_Security_Hardening_Audit {
		if ( null === $this->hardening ) {
			$this->hardening = new EMCP_Tools_Security_Hardening_Audit();
		}
		return $this->hardening;
	}

	private function software(): EMCP_Tools_Security_Software_Audit {
		if ( null === $this->software ) {
			$this->software = new EMCP_Tools_Security_Software_Audit();
		}
		return $this->software;
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

		$max_files   = $this->clamp( (int) ( $input['max_files'] ?? EMCP_Tools_Security_Malware_Audit::MAX_FILES ), 1, EMCP_Tools_Security_Malware_Audit::MAX_FILES_CEILING );
		$max_seconds = $this->clamp( (int) ( $input['max_seconds'] ?? EMCP_Tools_Security_Malware_Audit::TIME_BUDGET ), 1, EMCP_Tools_Security_Malware_Audit::TIME_BUDGET_CEILING );

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
			$m = $this->malware()->run( $deep, $max_files, $max_seconds );
			$findings = array_merge( $findings, $m['findings'] );
			$scan_meta['files_scanned']      = (int) $m['stats']['files_scanned'];
			$scan_meta['files_skipped_size'] = (int) $m['stats']['files_skipped_size'];
			$scan_meta['truncated']          = (bool) $m['stats']['truncated'];
			$scan_meta['truncated_reason']   = $m['stats']['truncated_reason'];
		}
		if ( in_array( 'integrity', $checks, true ) ) {
			$i = $this->integrity()->run();
			$findings = array_merge( $findings, $i['findings'] );
			$scan_meta['integrity_api'] = $i['api'];
		}
		if ( in_array( 'hardening', $checks, true ) ) {
			$h = $this->hardening()->run();
			$findings = array_merge( $findings, $h['findings'] );
			$scan_meta['headers_fetch'] = $h['headers_fetch'];
		}
		if ( in_array( 'software', $checks, true ) ) {
			$findings = array_merge( $findings, $this->software()->run() );
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
