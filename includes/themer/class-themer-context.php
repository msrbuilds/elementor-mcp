<?php
/**
 * Build the normalized request context the Themer matchers consume.
 *
 * from_query() reads the current WordPress main-query conditionals + queried
 * object into a plain array; from_parts() is the pure normalizer (defaults every
 * key) so matchers/tests never worry about missing keys.
 *
 * @package EMCP_Tools
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.2.0
 */
class EMCP_Tools_Themer_Context {

	/**
	 * Normalize a partial context into the full shape (pure).
	 *
	 * @param array $parts Partial context.
	 * @return array
	 */
	public static function from_parts( array $parts ): array {
		$defaults = array(
			'is_singular'          => false,
			'is_archive'           => false,
			'is_search'            => false,
			'is_404'               => false,
			'is_front_page'        => false,
			'is_home'              => false,
			'is_post_type_archive' => false,
			'is_author'            => false,
			'is_date'              => false,
			'post_id'              => 0,
			'post_type'            => '',
			'author_id'            => 0,
			'queried_post_type'    => '',
			'queried_taxonomy'     => '',
			'queried_term_id'      => 0,
			'term_ids'             => array(),
		);
		$ctx = array_merge( $defaults, array_intersect_key( $parts, $defaults ) );

		// Cast scalars.
		foreach ( array( 'post_id', 'author_id', 'queried_term_id' ) as $int_key ) {
			$ctx[ $int_key ] = (int) $ctx[ $int_key ];
		}
		foreach ( array( 'is_singular', 'is_archive', 'is_search', 'is_404', 'is_front_page', 'is_home', 'is_post_type_archive', 'is_author', 'is_date' ) as $bool_key ) {
			$ctx[ $bool_key ] = (bool) $ctx[ $bool_key ];
		}
		$ctx['term_ids'] = is_array( $ctx['term_ids'] ) ? $ctx['term_ids'] : array();

		return $ctx;
	}

	/**
	 * Snapshot the current main query into a context array (WP glue).
	 *
	 * @return array
	 */
	public static function from_query(): array {
		$parts = array(
			'is_singular'          => is_singular(),
			'is_archive'           => is_archive(),
			'is_search'            => is_search(),
			'is_404'               => is_404(),
			'is_front_page'        => is_front_page(),
			'is_home'              => is_home(),
			'is_post_type_archive' => is_post_type_archive(),
			'is_author'            => is_author(),
			'is_date'              => is_date(),
		);

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$parts['post_id']   = $post->ID;
				$parts['post_type'] = $post->post_type;
				$parts['author_id'] = (int) $post->post_author;
				$parts['term_ids']  = self::collect_terms( $post );
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$parts['queried_taxonomy'] = $term->taxonomy;
				$parts['queried_term_id']  = (int) $term->term_id;
			}
		} elseif ( is_post_type_archive() ) {
			$parts['queried_post_type'] = (string) get_query_var( 'post_type' );
		} elseif ( is_author() ) {
			$parts['author_id'] = (int) get_query_var( 'author' );
		}

		return self::from_parts( $parts );
	}

	/**
	 * All term ids of a post, keyed by taxonomy.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string,int[]>
	 */
	private static function collect_terms( WP_Post $post ): array {
		$out = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
			$ids = wp_get_object_terms( $post->ID, $tax, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $ids ) && $ids ) {
				$out[ $tax ] = array_map( 'intval', $ids );
			}
		}
		return $out;
	}
}
