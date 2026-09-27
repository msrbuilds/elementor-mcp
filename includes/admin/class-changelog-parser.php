<?php
/**
 * Parses the bundled CHANGELOG.md for the Changelog screen (spec 8.23).
 * Every text is escaped before the few markdown tokens become tags, links
 * keep only http(s) URLs, and the result goes through wp_kses (inline
 * allowlist) then wp_kses_post: SafeHtml only ever receives this output.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Changelog_Parser {

	const ISSUES = 'https://github.com/msrbuilds/elementor-mcp/issues/';

	const ALLOWED = array(
		'strong' => array(),
		'em'     => array(),
		'code'   => array(),
		'ul'     => array(),
		'li'     => array(),
		'a'      => array(
			'href'   => array(),
			'target' => array(),
			'rel'    => array(),
		),
	);

	/**
	 * Releases, newest first.
	 *
	 * @param string $markdown CHANGELOG.md contents.
	 * @return array<int, array{version: string, unreleased: bool, summary: string, items: array, counts: array}>
	 */
	public static function parse( string $markdown ): array {
		$releases = array();
		$current  = null;
		$section  = '';
		foreach ( explode( "\n", str_replace( "\r\n", "\n", $markdown ) ) as $line ) {
			$line = rtrim( $line );
			if ( preg_match( '/^##\s+\[([^\]]+)\](.*)$/', $line, $m ) ) {
				if ( null !== $current ) {
					$releases[] = $current;
				}
				$current = array(
					'version'    => trim( $m[1] ),
					'unreleased' => false !== stripos( $m[2], 'unreleased' ),
					'notes'      => array(),
					'raw'        => array(),
				);
				$section = '';
				continue;
			}
			if ( null === $current ) {
				continue;
			}
			if ( preg_match( '/^###\s+(\w+)/', $line, $m ) ) {
				$section = self::tag_of( $m[1] );
				continue;
			}
			if ( preg_match( '/^>\s?(.*)/', $line, $m ) ) {
				if ( '' !== trim( $m[1] ) ) {
					$current['notes'][] = trim( $m[1] );
				}
				continue;
			}
			if ( preg_match( '/^(?:\t|\s{2,})[-*]\s+(.+)/', $line, $m ) ) {
				$last = count( $current['raw'] ) - 1;
				if ( $last >= 0 ) {
					$current['raw'][ $last ]['children'][] = $m[1];
				}
				continue;
			}
			if ( preg_match( '/^[-*]\s+(.+)/', $line, $m ) ) {
				$current['raw'][] = array(
					'text'     => $m[1],
					'children' => array(),
					'section'  => $section,
				);
			}
		}
		if ( null !== $current ) {
			$releases[] = $current;
		}
		return array_map( array( __CLASS__, 'release' ), $releases );
	}

	/**
	 * @param array $r Raw release.
	 */
	private static function release( array $r ): array {
		$items  = array_map( array( __CLASS__, 'item' ), $r['raw'] );
		$counts = array(
			'all'   => count( $items ),
			'new'   => 0,
			'fixed' => 0,
		);
		foreach ( $items as $i ) {
			if ( isset( $counts[ $i['type'] ] ) ) {
				++$counts[ $i['type'] ];
			}
		}
		return array(
			'version'    => $r['version'],
			'unreleased' => $r['unreleased'],
			'summary'    => self::clean( self::inline( implode( ' ', $r['notes'] ) ) ),
			'items'      => $items,
			'counts'     => $counts,
		);
	}

	/**
	 * @param array $raw { text, children }.
	 */
	private static function item( array $raw ): array {
		$text = $raw['text'];
		$tag  = (string) ( $raw['section'] ?? '' );
		if ( preg_match( '/^(Fixed|New|Added|Improved|Changed|Removed|Security|Deprecated|Maintenance|Note):\s*/i', $text, $m ) ) {
			$tag  = self::tag_of( $m[1] );
			$text = substr( $text, strlen( $m[0] ) );
		}
		$title = '';
		if ( preg_match( '/^\*\*(.+?)\*\*\s*[.:]?\s*/', $text, $m ) ) {
			$title = rtrim( trim( str_replace( '`', '', $m[1] ) ), '.:' );
			$text  = substr( $text, strlen( $m[0] ) );
		}

		$issues = array();
		$links  = array();
		// Issue links become tokens so a parenthetical holding only them can go.
		$text = preg_replace_callback(
			'/\[#(\d+)\]\(([^)\s]+)\)/',
			static function ( $m ) use ( &$issues, &$links ) {
				$n = (int) $m[1];
				if ( self::is_http( $m[2] ) ) {
					$issues[ $n ] = array(
						'number' => $n,
						'url'    => $m[2],
					);
				}
				$links[ $n ] = $m[0];
				return '{{#' . $n . '}}';
			},
			$text
		);
		$text = preg_replace( '/\s*\(\s*(?:reported in\s+)?(?:\{\{#\d+\}\}\s*(?:,|and)?\s*)+\)/i', '', $text );
		$text = preg_replace_callback(
			'/\{\{#(\d+)\}\}/',
			static function ( $m ) use ( $links ) {
				return $links[ (int) $m[1] ] ?? '';
			},
			$text
		);
		$text = preg_replace( '/^\s*[.,:;]\s*/', '', $text );
		if ( preg_match_all( '/#(\d+)/', $title, $mm ) ) {
			foreach ( $mm[1] as $n ) {
				$n = (int) $n;
				if ( ! isset( $issues[ $n ] ) ) {
					$issues[ $n ] = array(
						'number' => $n,
						'url'    => self::ISSUES . $n,
					);
				}
			}
		}

		$html = self::inline( $text );
		if ( $raw['children'] ) {
			$html .= '<ul>';
			foreach ( $raw['children'] as $child ) {
				$html .= '<li>' . self::inline( $child ) . '</li>';
			}
			$html .= '</ul>';
		}
		$type = 'other';
		if ( in_array( $tag, array( 'NEW', 'IMPROVED' ), true ) ) {
			$type = 'new';
		} elseif ( 'FIXED' === $tag ) {
			$type = 'fixed';
		}
		return array(
			'type'   => $type,
			'tag'    => $tag,
			'title'  => $title,
			'html'   => self::clean( $html ),
			'issues' => array_values( $issues ),
		);
	}

	/**
	 * An item or section keyword as its badge: Added reads as NEW.
	 *
	 * @param string $word Keyword.
	 */
	private static function tag_of( string $word ): string {
		$tag = strtoupper( $word );
		return 'ADDED' === $tag ? 'NEW' : $tag;
	}

	/**
	 * @param string $url URL.
	 */
	private static function is_http( string $url ): bool {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true );
	}

	/**
	 * One line of inline markdown (bold, italic, code, links) to HTML. The
	 * whole string is escaped first.
	 *
	 * @param string $text Markdown.
	 */
	private static function inline( string $text ): string {
		$html = esc_html( $text );
		$html = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $html );
		$html = preg_replace_callback(
			'/\[([^\]]+)\]\(([^)\s]+)\)/',
			static function ( $m ) {
				$url = html_entity_decode( $m[2], ENT_QUOTES );
				if ( ! self::is_http( $url ) ) {
					return $m[1];
				}
				return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
			},
			$html
		);
		$html = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html );
		return (string) preg_replace( '/(?<![\w*])\*([^*<>]+)\*(?![\w*])/', '<em>$1</em>', $html );
	}

	/**
	 * @param string $html HTML built by inline().
	 */
	private static function clean( string $html ): string {
		return wp_kses_post( wp_kses( $html, self::ALLOWED ) );
	}
}
