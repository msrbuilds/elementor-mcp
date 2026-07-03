<?php
/**
 * Core file integrity audit against official wordpress.org checksums.
 *
 * diff() is pure (unit-tested). run() fetches checksums + hashes core files
 * (verified live) and degrades gracefully when the checksum API is unreachable.
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
class Elementor_MCP_Security_Integrity_Audit {

	/**
	 * Pure: compare a checksum manifest against actual file hashes.
	 *
	 * @param array<string,string> $checksums core-relative path => expected md5.
	 * @param callable             $hasher    fn(string $relpath): ?string actual md5 (null = missing).
	 * @return array Finding[]
	 */
	public function diff( array $checksums, callable $hasher ): array {
		$findings = array();
		foreach ( $checksums as $path => $expected ) {
			$actual = $hasher( (string) $path );
			if ( null === $actual ) {
				$findings[] = Elementor_MCP_Security_Finding::make(
					'integrity_missing', 'integrity', 'Missing core file', 'warning', (string) $path,
					sprintf( 'Core file %s is missing.', $path ),
					'Reinstall WordPress core (Dashboard → Updates → Re-install) to restore missing files.'
				);
				continue;
			}
			if ( ! hash_equals( strtolower( (string) $expected ), strtolower( (string) $actual ) ) ) {
				$findings[] = Elementor_MCP_Security_Finding::make(
					'integrity_modified', 'integrity', 'Modified core file', 'critical', (string) $path,
					sprintf( 'Core file %s does not match the official checksum.', $path ),
					'A modified core file is a strong infection signal. Re-install WordPress core and investigate how it changed.'
				);
			}
		}
		return $findings;
	}

	/**
	 * Live: fetch checksums + hash core files. Excludes wp-content (not part of
	 * core checksums).
	 *
	 * @return array { findings: Finding[], api: array{ ok: bool, error: ?string } }
	 */
	public function run(): array {
		if ( ! function_exists( 'get_core_checksums' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		global $wp_version;
		$locale    = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		$checksums = function_exists( 'get_core_checksums' ) ? get_core_checksums( $wp_version, $locale ) : false;

		if ( empty( $checksums ) || ! is_array( $checksums ) ) {
			return array(
				'findings' => array(
					Elementor_MCP_Security_Finding::make(
						'integrity_unavailable', 'integrity', 'Core checksums', 'info', false,
						'Could not retrieve official core checksums (offline or wordpress.org unreachable).',
						'Run this scan on an internet-connected environment to verify core file integrity.'
					),
				),
				'api'      => array( 'ok' => false, 'error' => 'checksums_unavailable' ),
			);
		}

		$hasher = static function ( string $relpath ): ?string {
			$full = ABSPATH . $relpath;
			if ( 0 === strpos( $relpath, 'wp-content/' ) ) {
				return null; // Excluded — treat as present to suppress findings.
			}
			if ( ! is_file( $full ) ) {
				return null;
			}
			$md5 = @md5_file( $full );
			return false === $md5 ? null : $md5;
		};

		// Skip wp-content entries entirely (excluded from integrity).
		$filtered = array();
		foreach ( $checksums as $path => $md5 ) {
			if ( 0 === strpos( (string) $path, 'wp-content/' ) ) {
				continue;
			}
			$filtered[ $path ] = $md5;
		}

		return array(
			'findings' => $this->diff( $filtered, $hasher ),
			'api'      => array( 'ok' => true, 'error' => null ),
		);
	}
}
