<?php
/**
 * Changelog screen data (spec 8.23). The whole file is over the 150 KB boot
 * budget, so the boot carries the release index plus the newest release;
 * REST serves one release or a search.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_Changelog_Data {

	const SEARCH_MAX = 50;

	/** @var array|null Parsed once per request. */
	private static $releases = null;

	/** Every release, newest first. */
	private static function releases(): array {
		if ( null === self::$releases ) {
			$file = EMCP_TOOLS_DIR . 'CHANGELOG.md';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
			$raw            = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
			self::$releases = EMCP_Tools_Changelog_Parser::parse( $raw );
		}
		return self::$releases;
	}

	/** The release list for the rail. */
	public function index(): array {
		$out = array();
		foreach ( self::releases() as $r ) {
			$out[] = array(
				'version'    => $r['version'],
				'unreleased' => $r['unreleased'],
				'counts'     => $r['counts'],
			);
		}
		return $out;
	}

	/**
	 * One release.
	 *
	 * @param string $version Version.
	 */
	public function release( string $version ): ?array {
		foreach ( self::releases() as $r ) {
			if ( $r['version'] === $version ) {
				return $r;
			}
		}
		return null;
	}

	/**
	 * Items matching a query, grouped by release. `#145` matches an issue
	 * number; anything else a case-insensitive substring of the title and text.
	 *
	 * @param string $q Query.
	 */
	public function search( string $q ): array {
		$q = trim( $q );
		if ( strlen( $q ) < 2 ) {
			return array();
		}
		$issue = preg_match( '/^#(\d+)$/', $q, $m ) ? (int) $m[1] : 0;
		$out   = array();
		$left  = self::SEARCH_MAX;
		foreach ( self::releases() as $r ) {
			$hits = array();
			foreach ( $r['items'] as $item ) {
				if ( $issue ? in_array( $issue, array_column( $item['issues'], 'number' ), true ) : self::matches( $item, $q ) ) {
					$hits[] = $item;
					if ( --$left <= 0 ) {
						break;
					}
				}
			}
			if ( $hits ) {
				$out[] = array(
					'version' => $r['version'],
					'items'   => $hits,
				);
			}
			if ( $left <= 0 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Whether an item's title or text contains a query (case-insensitive,
	 * against the decoded text, so "Elementor's" matches).
	 *
	 * @param array  $item   Parsed item.
	 * @param string $needle Query.
	 */
	public static function matches( array $item, string $needle ): bool {
		$lower = static function ( string $s ): string {
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
		};
		$hay = $item['title'] . ' ' . html_entity_decode( wp_strip_all_tags( $item['html'] ), ENT_QUOTES, 'UTF-8' );
		return false !== strpos( $lower( $hay ), $lower( trim( $needle ) ) );
	}

	/** The boot payload. */
	public function payload(): array {
		$releases = self::releases();
		return array(
			'index'   => $this->index(),
			'latest'  => $releases[0] ?? null,
			'current' => EMCP_TOOLS_VERSION,
		);
	}
}
