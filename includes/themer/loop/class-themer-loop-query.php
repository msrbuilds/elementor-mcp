<?php
/**
 * Loop query builder: flat settings in, WP_Query args out.
 *
 * Free-tree, so it cannot lean on the Pro Widget Builder query engine. Every
 * query it builds is forced to published, readable, non-password content of
 * public post types, and capped, whatever the settings say. Offsets are
 * computed here because WordPress ignores `paged` whenever `offset` is set.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.18.0
 */
class EMCP_Tools_Themer_Loop_Query {

	const SOURCES          = array( 'posts', 'current', 'related', 'manual', 'products' );
	const MAX_PER_PAGE     = 100;
	const DEFAULT_PER_PAGE = 6;
	const MAX_OFFSET       = 1000;
	const ORDERBY          = array( 'date', 'modified', 'title', 'menu_order', 'rand', 'comment_count', 'meta_value', 'meta_value_num' );
	const PRODUCT_ORDERBY  = array( 'price', 'popularity', 'rating' );
	const DATE_RANGES      = array( 'all', 'past_day', 'past_week', 'past_month', 'past_quarter', 'past_year', 'custom' );

	/**
	 * Main-query vars a snapshot keeps. Constraints expressed anywhere else
	 * (SQL filters gated on is_main_query()) cannot be captured; replay_matches()
	 * is the heuristic that catches the common cases.
	 */
	const SNAPSHOT_KEYS = array(
		'post_type', 'posts_per_page', 'orderby', 'order', 'meta_key', 'meta_value', 'meta_value_num',
		'meta_query', 'tax_query', 'date_query', 'cat', 'category_name', 'category__in', 'category__not_in',
		'tag', 'tag_id', 'tag__in', 'tag__not_in', 'taxonomy', 'term', 'author', 'author_name', 'author__in',
		'author__not_in', 'year', 'monthnum', 'day', 'w', 'm', 's', 'post__in', 'post__not_in', 'post_parent',
		'ignore_sticky_posts', 'offset',
	);

	/** @return bool */
	public static function woocommerce_active(): bool {
		if ( isset( $GLOBALS['_emcp_woo_active'] ) ) {
			return (bool) $GLOBALS['_emcp_woo_active']; // test seam
		}
		return class_exists( 'WooCommerce' ) && post_type_exists( 'product' );
	}

	/**
	 * Public post types a loop may query (attachments and private types excluded).
	 *
	 * @return string[]
	 */
	public static function allowed_post_types(): array {
		$types = get_post_types( array( 'public' => true ), 'names' );
		$types = is_array( $types ) ? array_values( array_map( 'strval', $types ) ) : array();
		return array_values( array_diff( $types, array( 'attachment' ) ) );
	}

	/**
	 * Normalize settings; unknown or invalid values fall back to defaults.
	 *
	 * @param array $q Raw settings.
	 * @return array
	 */
	public static function sanitize( array $q ): array {
		$source = isset( $q['source'] ) ? (string) $q['source'] : 'posts';
		if ( ! in_array( $source, self::SOURCES, true ) ) {
			$source = 'posts';
		}
		if ( 'products' === $source && ! self::woocommerce_active() ) {
			$source = 'posts';
		}

		$allowed = self::allowed_post_types();
		$types   = array();
		foreach ( (array) ( $q['post_types'] ?? array( 'post' ) ) as $t ) {
			$t = sanitize_key( (string) $t );
			if ( in_array( $t, $allowed, true ) ) {
				$types[] = $t;
			}
		}
		if ( ! $types ) {
			$types = array( 'post' );
		}

		$orderby = isset( $q['orderby'] ) ? sanitize_key( (string) $q['orderby'] ) : 'date';
		$valid_o = 'products' === $source ? array_merge( self::ORDERBY, self::PRODUCT_ORDERBY ) : self::ORDERBY;
		if ( ! in_array( $orderby, $valid_o, true ) ) {
			$orderby = 'date';
		}

		$date = isset( $q['date'] ) ? sanitize_key( (string) $q['date'] ) : 'all';
		if ( ! in_array( $date, self::DATE_RANGES, true ) ) {
			$date = 'all';
		}

		$per = isset( $q['per_page'] ) ? (int) $q['per_page'] : self::DEFAULT_PER_PAGE;

		return array(
			'source'            => $source,
			'post_types'        => array_values( array_unique( $types ) ),
			'per_page'          => max( 1, min( self::MAX_PER_PAGE, $per > 0 ? $per : self::DEFAULT_PER_PAGE ) ),
			'offset'            => max( 0, min( self::MAX_OFFSET, (int) ( $q['offset'] ?? 0 ) ) ),
			'orderby'           => $orderby,
			'order'             => ( isset( $q['order'] ) && 'asc' === strtolower( (string) $q['order'] ) ) ? 'ASC' : 'DESC',
			'meta_key'          => isset( $q['meta_key'] ) ? sanitize_key( (string) $q['meta_key'] ) : '',
			'terms'             => self::term_refs( $q['terms'] ?? array() ),
			'exclude_terms'     => self::term_refs( $q['exclude_terms'] ?? array() ),
			'authors'           => self::int_list( $q['authors'] ?? array() ),
			'include_ids'       => self::int_list( $q['include_ids'] ?? array() ),
			'exclude_ids'       => self::int_list( $q['exclude_ids'] ?? array() ),
			'exclude_current'   => isset( $q['exclude_current'] ) ? EMCP_Tools_Themer_Element_Base::truthy( $q['exclude_current'] ) : ( 'related' === $source ),
			'ignore_sticky'     => isset( $q['ignore_sticky'] ) ? EMCP_Tools_Themer_Element_Base::truthy( $q['ignore_sticky'] ) : true,
			'date'              => $date,
			'after'             => self::ymd( (string) ( $q['after'] ?? '' ) ),
			'before'            => self::ymd( (string) ( $q['before'] ?? '' ) ),
			'related_taxonomy'  => isset( $q['related_taxonomy'] ) && '' !== $q['related_taxonomy'] ? sanitize_key( (string) $q['related_taxonomy'] ) : 'category',
			'hide_out_of_stock' => ! empty( $q['hide_out_of_stock'] ) && EMCP_Tools_Themer_Element_Base::truthy( $q['hide_out_of_stock'] ),
			'on_sale_only'      => ! empty( $q['on_sale_only'] ) && EMCP_Tools_Themer_Element_Base::truthy( $q['on_sale_only'] ),
			'featured_only'     => ! empty( $q['featured_only'] ) && EMCP_Tools_Themer_Element_Base::truthy( $q['featured_only'] ),
			'current_snapshot'  => self::allowlist_snapshot( is_array( $q['current_snapshot'] ?? null ) ? $q['current_snapshot'] : array() ),
		);
	}

	/**
	 * Build WP_Query args for a page.
	 *
	 * @param array $q    Settings (raw or sanitized).
	 * @param array $ctx  Context: current_post_id.
	 * @param int   $page 1-based page.
	 * @return array
	 */
	public static function build_args( array $q, array $ctx = array(), int $page = 1 ): array {
		$q       = self::sanitize( $q );
		$page    = max( 1, $page );
		$current = (int) ( $ctx['current_post_id'] ?? 0 );

		$forced = array(
			'post_status'   => 'publish',
			'has_password'  => false,
			'perm'          => 'readable',
			'no_found_rows' => false,
		);

		if ( 'current' === $q['source'] ) {
			$args = array_merge( $q['current_snapshot'], $forced );
			if ( empty( $args['post_type'] ) ) {
				$args['post_type'] = 'post';
			}
			if ( empty( $args['posts_per_page'] ) || (int) $args['posts_per_page'] < 1 ) {
				$args['posts_per_page'] = (int) get_option( 'posts_per_page', 10 );
			}
			if ( isset( $args['offset'] ) ) {
				// A main query with an offset paginates by hand, like everything else here.
				$args['offset'] = (int) $args['offset'] + ( $page - 1 ) * (int) $args['posts_per_page'];
			} else {
				$args['paged'] = $page;
			}
			return $args;
		}

		$args = array_merge(
			$forced,
			array(
				'ignore_sticky_posts' => $q['ignore_sticky'],
				'posts_per_page'      => $q['per_page'],
				'offset'              => $q['offset'] + ( $page - 1 ) * $q['per_page'],
				'orderby'             => $q['orderby'],
				'order'               => $q['order'],
			)
		);

		if ( 'manual' === $q['source'] ) {
			$args['post_type'] = 'any';
			$args['post__in']  = $q['include_ids'] ? $q['include_ids'] : array( 0 );
			$args['orderby']   = 'post__in';
			unset( $args['order'] );
			return $args;
		}

		$args['post_type'] = 'products' === $q['source'] ? 'product' : $q['post_types'];

		$tax_query = array();
		foreach ( self::group_terms( $q['terms'] ) as $taxonomy => $ids ) {
			$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $ids, 'operator' => 'IN' );
		}
		foreach ( self::group_terms( $q['exclude_terms'] ) as $taxonomy => $ids ) {
			$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $ids, 'operator' => 'NOT IN' );
		}

		$not_in = $q['exclude_ids'];

		if ( 'related' === $q['source'] ) {
			$terms = array();
			if ( $current > 0 ) {
				$found = wp_get_object_terms( $current, $q['related_taxonomy'], array( 'fields' => 'ids' ) );
				$terms = is_array( $found ) ? array_values( array_map( 'intval', $found ) ) : array();
			}
			if ( ! $terms ) {
				$args['post__in'] = array( 0 );
				return $args;
			}
			$tax_query[] = array( 'taxonomy' => $q['related_taxonomy'], 'field' => 'term_id', 'terms' => $terms, 'operator' => 'IN' );
			$not_in[]    = $current;
		} elseif ( $q['exclude_current'] && $current > 0 ) {
			$not_in[] = $current;
		}

		if ( 'products' === $q['source'] ) {
			$hidden = array( 'exclude-from-catalog' );
			if ( $q['hide_out_of_stock'] ) {
				$hidden[] = 'outofstock';
			}
			$tax_query[] = array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => $hidden, 'operator' => 'NOT IN' );
			if ( $q['featured_only'] ) {
				$tax_query[] = array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => array( 'featured' ), 'operator' => 'IN' );
			}
			if ( $q['on_sale_only'] && function_exists( 'wc_get_product_ids_on_sale' ) ) {
				$sale             = array_map( 'intval', (array) wc_get_product_ids_on_sale() );
				$args['post__in'] = $sale ? $sale : array( 0 );
			}
			$product_meta = array( 'price' => '_price', 'popularity' => 'total_sales', 'rating' => '_wc_average_rating' );
			if ( isset( $product_meta[ $q['orderby'] ] ) ) {
				$args['meta_key'] = $product_meta[ $q['orderby'] ];
				$args['orderby']  = 'meta_value_num';
			}
		}

		if ( in_array( $args['orderby'], array( 'meta_value', 'meta_value_num' ), true ) && empty( $args['meta_key'] ) ) {
			if ( '' !== $q['meta_key'] ) {
				$args['meta_key'] = $q['meta_key'];
			} else {
				$args['orderby'] = 'date';
			}
		}

		if ( $tax_query ) {
			$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		if ( $q['authors'] ) {
			$args['author__in'] = $q['authors'];
		}
		if ( $q['include_ids'] && ! isset( $args['post__in'] ) ) {
			$args['post__in'] = $q['include_ids'];
		}
		if ( $not_in ) {
			$args['post__not_in'] = array_values( array_unique( $not_in ) );
		}

		$date_query = self::date_query( $q );
		if ( $date_query ) {
			$args['date_query'] = array( $date_query );
		}

		return $args;
	}

	/**
	 * Run a page and describe the result.
	 *
	 * @param array $q    Settings.
	 * @param array $ctx  Context.
	 * @param int   $page 1-based page.
	 * @return array{posts:array,found:int,max_pages:int,page:int,query:WP_Query}
	 */
	public static function run( array $q, array $ctx = array(), int $page = 1 ): array {
		$page  = max( 1, $page );
		$clean = self::sanitize( $q );
		$args  = self::build_args( $clean, $ctx, $page );
		$wpq   = new WP_Query( $args );
		$per   = max( 1, (int) ( $args['posts_per_page'] ?? $clean['per_page'] ) );
		$base  = 'current' === $clean['source'] ? (int) ( $clean['current_snapshot']['offset'] ?? 0 ) : $clean['offset'];
		$found = (int) $wpq->found_posts;
		return array(
			'posts'     => (array) $wpq->posts,
			'found'     => $found,
			'max_pages' => self::max_pages( $found, $base, $per ),
			'page'      => $page,
			'query'     => $wpq,
		);
	}

	/**
	 * Pages available after a base offset. WordPress's own max_num_pages ignores
	 * the offset, so a grid that skips 3 posts would otherwise show a blank page.
	 *
	 * @param int $found       Total matching posts.
	 * @param int $base_offset Posts skipped before page 1.
	 * @param int $per_page    Posts per page.
	 * @return int
	 */
	public static function max_pages( int $found, int $base_offset, int $per_page ): int {
		$per = max( 1, $per_page );
		if ( $found <= $base_offset ) {
			return 0;
		}
		return (int) ceil( ( $found - $base_offset ) / $per );
	}

	/**
	 * Snapshot the resolved main query (after pre_get_posts) for replay.
	 *
	 * @param WP_Query|null $wp_query The main query.
	 * @return array
	 */
	public static function snapshot_main_query( $wp_query ): array {
		if ( ! is_object( $wp_query ) || ! isset( $wp_query->query_vars ) ) {
			return array();
		}
		return self::allowlist_snapshot( (array) $wp_query->query_vars );
	}

	/**
	 * Does replaying the snapshot reproduce the live page? A heuristic: it
	 * compares the ids and page count of the page the visitor is on.
	 *
	 * @param array         $q    Settings with source current.
	 * @param array         $ctx  Context.
	 * @param WP_Query|null $main The live main query.
	 * @return bool
	 */
	public static function replay_matches( array $q, array $ctx, $main ): bool {
		if ( ! is_object( $main ) ) {
			return false;
		}
		$page   = max( 1, (int) ( method_exists( $main, 'get' ) ? $main->get( 'paged' ) : 1 ) );
		$replay = self::run( $q, $ctx, $page );
		$live   = array_map( 'intval', wp_list_pluck( (array) $main->posts, 'ID' ) );
		$ours   = array_map( 'intval', wp_list_pluck( $replay['posts'], 'ID' ) );
		if ( $live !== $ours ) {
			return false;
		}
		return (int) $main->max_num_pages === (int) $replay['query']->max_num_pages;
	}

	// ---- helpers -----------------------------------------------------------

	/** @param mixed $raw @return string[] "taxonomy:id" refs */
	private static function term_refs( $raw ): array {
		$out = array();
		foreach ( (array) $raw as $ref ) {
			$ref = (string) $ref;
			if ( preg_match( '/^([a-z0-9_-]+):(\d+)$/', $ref, $m ) && (int) $m[2] > 0 ) {
				$out[] = $m[1] . ':' . (int) $m[2];
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** @param string[] $refs @return array<string,int[]> */
	private static function group_terms( array $refs ): array {
		$out = array();
		foreach ( $refs as $ref ) {
			list( $tax, $id ) = explode( ':', $ref, 2 );
			$out[ $tax ][]    = (int) $id;
		}
		return $out;
	}

	/** @param mixed $raw @return int[] */
	private static function int_list( $raw ): array {
		$items = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$out   = array();
		foreach ( $items as $v ) {
			$v = (int) trim( (string) $v );
			if ( $v > 0 ) {
				$out[] = $v;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** @param string $v @return string Y-m-d or '' */
	private static function ymd( string $v ): string {
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
	}

	/** @param array $q Sanitized settings. @return array One date_query clause or empty. */
	private static function date_query( array $q ): array {
		$relative = array(
			'past_day'     => '1 day ago',
			'past_week'    => '1 week ago',
			'past_month'   => '1 month ago',
			'past_quarter' => '3 months ago',
			'past_year'    => '1 year ago',
		);
		if ( isset( $relative[ $q['date'] ] ) ) {
			return array( 'after' => $relative[ $q['date'] ], 'inclusive' => true );
		}
		if ( 'custom' === $q['date'] ) {
			$clause = array();
			if ( '' !== $q['after'] ) {
				$clause['after'] = $q['after'];
			}
			if ( '' !== $q['before'] ) {
				$clause['before'] = $q['before'];
			}
			if ( $clause ) {
				$clause['inclusive'] = true;
				return $clause;
			}
		}
		return array();
	}

	/** @param array $vars @return array Allowlisted, non-empty vars only. */
	private static function allowlist_snapshot( array $vars ): array {
		$out = array();
		foreach ( self::SNAPSHOT_KEYS as $key ) {
			if ( ! isset( $vars[ $key ] ) || empty( $vars[ $key ] ) ) {
				continue;
			}
			$out[ $key ] = $vars[ $key ];
		}
		return $out;
	}
}
