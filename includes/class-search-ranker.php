<?php
/**
 * Pure lexical ranker for content search (field-weighted TF-IDF).
 *
 * No storage, no WordPress dependency — tokenize + rank a passed set of docs.
 * The embedding-backed rerank is a future upgrade layered on top of this.
 *
 * @package EMCP_Tools
 * @since   3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ranks documents against a query.
 *
 * @since 3.3.0
 */
class EMCP_Tools_Search_Ranker {

	const TITLE_BOOST = 3.0;

	/**
	 * Common English stopwords (small, curated).
	 *
	 * @var string[]
	 */
	private static $stopwords = array(
		'the', 'a', 'an', 'and', 'or', 'but', 'of', 'to', 'in', 'on', 'at', 'for',
		'with', 'is', 'are', 'was', 'were', 'be', 'by', 'as', 'it', 'this', 'that',
		'from', 'into', 'your', 'you', 'we', 'our', 'us', 'i',
	);

	/**
	 * Normalize text into a list of terms.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function tokenize( string $text ): array {
		$text  = strtolower( $text );
		$parts = preg_split( '/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$out   = array();
		foreach ( (array) $parts as $p ) {
			if ( strlen( $p ) >= 2 && ! in_array( $p, self::$stopwords, true ) ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/**
	 * Rank docs against a query. Each doc: { object_type, object_id, title, content, meta? }.
	 *
	 * @param array  $docs  Documents.
	 * @param string $query Query string.
	 * @param int    $limit Max results.
	 * @return array<int,array{object_type:string,object_id:string,title:string,score:float,snippet:string,meta:mixed}>
	 */
	public static function rank( array $docs, string $query, int $limit = 20 ): array {
		$q = self::tokenize( $query );
		if ( empty( $q ) || empty( $docs ) ) {
			return array();
		}
		$q = array_values( array_unique( $q ) );

		// Pre-tokenize docs + document frequency per query term.
		$prepared = array();
		$df       = array_fill_keys( $q, 0 );
		foreach ( $docs as $doc ) {
			$title_tokens = self::tokenize( (string) ( $doc['title'] ?? '' ) );
			$body_tokens  = self::tokenize( (string) ( $doc['content'] ?? '' ) );
			$all          = array_merge( $title_tokens, $body_tokens );
			$prepared[]   = array(
				'doc'    => $doc,
				'title'  => array_count_values( $title_tokens ),
				'body'   => array_count_values( $body_tokens ),
				'hasany' => $all,
			);
			foreach ( $q as $term ) {
				if ( in_array( $term, $all, true ) ) {
					++$df[ $term ];
				}
			}
		}

		$n       = count( $docs );
		$scored  = array();
		foreach ( $prepared as $p ) {
			$score = 0.0;
			foreach ( $q as $term ) {
				$tf = ( $p['body'][ $term ] ?? 0 ) + self::TITLE_BOOST * ( $p['title'][ $term ] ?? 0 );
				if ( $tf <= 0 ) {
					continue;
				}
				$dfi = max( 1, (int) ( $df[ $term ] ?? 0 ) );
				$idf = log( 1.0 + ( ( $n - $dfi + 0.5 ) / ( $dfi + 0.5 ) ) );
				$score += ( $tf / ( $tf + 1.0 ) ) * $idf;
			}
			if ( $score > 0 ) {
				$doc      = $p['doc'];
				$scored[] = array(
					'object_type' => (string) ( $doc['object_type'] ?? '' ),
					'object_id'   => (string) ( $doc['object_id'] ?? '' ),
					'title'       => (string) ( $doc['title'] ?? '' ),
					'score'       => round( $score, 4 ),
					'snippet'     => self::snippet( (string) ( $doc['content'] ?? '' ), $q ),
					'meta'        => $doc['meta'] ?? null,
				);
			}
		}

		usort( $scored, static function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		return array_slice( $scored, 0, max( 1, $limit ) );
	}

	/**
	 * A short snippet around the first matching term.
	 *
	 * @param string   $content Body text.
	 * @param string[] $terms   Query terms.
	 * @return string
	 */
	private static function snippet( string $content, array $terms ): string {
		$plain = trim( (string) preg_replace( '/\s+/', ' ', $content ) );
		if ( '' === $plain ) {
			return '';
		}
		$lower = strtolower( $plain );
		$pos   = false;
		foreach ( $terms as $t ) {
			$p = strpos( $lower, $t );
			if ( false !== $p && ( false === $pos || $p < $pos ) ) {
				$pos = $p;
			}
		}
		$start = ( false === $pos ) ? 0 : max( 0, $pos - 30 );
		$out   = substr( $plain, $start, 120 );
		return ( $start > 0 ? '…' : '' ) . trim( $out ) . ( strlen( $plain ) > $start + 120 ? '…' : '' );
	}
}
