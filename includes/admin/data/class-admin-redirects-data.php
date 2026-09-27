<?php
/**
 * Redirects screen data (spec 8.21): the paged redirect list, the suggestions
 * queue and the form-to-store mapping.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirects data.
 */
final class EMCP_Tools_Admin_Redirects_Data {

	const PER_PAGE = 20;
	const OPTION   = 'emcp_tools_redirect_suggestions';

	/**
	 * Stable key of a suggestion.
	 *
	 * @param string $old_path Suggested source path.
	 * @return string 12 hex characters.
	 */
	public static function suggestion_key( string $old_path ): string {
		return substr( sha1( $old_path ), 0, 12 );
	}

	/**
	 * The suggestions queue, newest first. A renamed post that is still
	 * published is offered as the target.
	 *
	 * @return array[]
	 */
	public static function suggestions(): array {
		$queue = get_option( self::OPTION, array() );
		$out   = array();
		foreach ( array_reverse( is_array( $queue ) ? $queue : array() ) as $s ) {
			$old = is_array( $s ) ? (string) ( $s['old_path'] ?? '' ) : '';
			if ( '' === $old ) {
				continue;
			}
			$target = null;
			$pid    = (int) ( $s['source_post_id'] ?? 0 );
			$post   = $pid && 'slug-changed' === ( $s['reason'] ?? '' ) ? get_post( $pid ) : null;
			if ( is_object( $post ) && 'publish' === ( $post->post_status ?? '' ) ) {
				$target = array(
					'postId' => $pid,
					'title'  => (string) $post->post_title,
					'url'    => (string) get_permalink( $pid ),
				);
			}
			$out[] = array(
				'key'             => self::suggestion_key( $old ),
				'oldPath'         => $old,
				'reason'          => (string) ( $s['reason'] ?? '' ),
				'suggestedTarget' => $target,
				'at'              => (string) ( $s['suggested_at'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Remove a suggestion by key.
	 *
	 * @param string $key Suggestion key.
	 * @return bool Whether one was removed.
	 */
	public static function remove_suggestion( string $key ): bool {
		$queue = get_option( self::OPTION, array() );
		$queue = is_array( $queue ) ? $queue : array();
		$kept  = array_values(
			array_filter(
				$queue,
				static function ( $s ) use ( $key ) {
					return ! ( is_array( $s ) && self::suggestion_key( (string) ( $s['old_path'] ?? '' ) ) === $key );
				}
			)
		);
		if ( count( $kept ) === count( $queue ) ) {
			return false;
		}
		update_option( self::OPTION, $kept, false );
		return true;
	}

	/**
	 * One redirect as the screen shows it.
	 *
	 * @param array $r Stored row.
	 * @return array
	 */
	public static function row( array $r ): array {
		$query = (string) ( $r['source_query'] ?? '' );
		$pid   = (int) ( $r['target_post_id'] ?? 0 );
		return array(
			'id'           => (int) $r['id'],
			'source'       => (string) $r['source_path'] . ( '' !== $query ? '?' . $query : '' ),
			'sourcePath'   => (string) $r['source_path'],
			'sourceQuery'  => $query,
			'ignoreQuery'  => 1 === (int) ( $r['ignore_query'] ?? 1 ),
			'target'       => (string) ( $r['target'] ?? '' ),
			'targetPostId' => $pid,
			'targetTitle'  => $pid ? (string) get_the_title( $pid ) : '',
			'targetUrl'    => EMCP_Tools_Redirect_Store::resolve_target( $r ),
			'code'         => (int) ( $r['status_code'] ?? 301 ),
			'hits'         => (int) ( $r['hits'] ?? 0 ),
			'lastHit'      => $r['last_hit'] ?? null,
			'enabled'      => ! empty( $r['enabled'] ),
			'created'      => (string) ( $r['created_at'] ?? '' ),
		);
	}

	/**
	 * Map a form body to store data. The source keeps its %xx octets
	 * (sanitize_text_field() would strip them and change the query).
	 *
	 * @param array $b { source, target, targetPostId, code, ignoreQuery, enabled }.
	 * @return array
	 */
	public static function input( array $b ): array {
		$out = array();
		if ( isset( $b['source'] ) ) {
			$out['source'] = trim( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', wp_strip_all_tags( (string) $b['source'] ) ) );
		}
		if ( isset( $b['code'] ) ) {
			$out['status_code'] = 302 === (int) $b['code'] ? 302 : 301;
		}
		if ( array_key_exists( 'ignoreQuery', $b ) ) {
			$out['ignore_query'] = rest_sanitize_boolean( $b['ignoreQuery'] );
		}
		if ( array_key_exists( 'enabled', $b ) ) {
			$out['enabled'] = rest_sanitize_boolean( $b['enabled'] );
		}
		if ( ! empty( $b['targetPostId'] ) ) {
			$out['target_post_id'] = absint( $b['targetPostId'] );
		} elseif ( isset( $b['target'] ) ) {
			$out['target'] = esc_url_raw( trim( (string) $b['target'] ) );
		}
		return $out;
	}

	/**
	 * The list payload for a search and page.
	 *
	 * @param array $args { search, page }.
	 * @return array
	 */
	public function payload( array $args = array() ): array {
		EMCP_Tools_Redirect_Store::repair_keys();
		$search = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		$total  = EMCP_Tools_Redirect_Store::count( array( 'search' => $search ) );
		$pages  = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page   = min( $pages, max( 1, (int) ( $args['page'] ?? 1 ) ) );
		$rows   = EMCP_Tools_Redirect_Store::all(
			array(
				'search' => $search,
				'limit'  => self::PER_PAGE,
				'offset' => ( $page - 1 ) * self::PER_PAGE,
			)
		);
		return array(
			'redirects'   => array_map( array( __CLASS__, 'row' ), $rows ),
			'total'       => $total,
			'page'        => $page,
			'pages'       => $pages,
			'search'      => $search,
			'suggestions' => self::suggestions(),
		);
	}
}
