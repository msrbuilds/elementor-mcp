<?php
/**
 * Option lists both builders offer for a loop query.
 *
 * One source, so an Elementor control and a block inspector cannot drift.
 * Everything is capped and memoised per request: a site with 40k terms must
 * not turn a widget panel into a timeout.
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
class EMCP_Tools_Themer_Loop_Options {

	const MAX_TERMS   = 500;
	const MAX_AUTHORS = 200;

	/** @var array<string,mixed> */
	private static $cache = array();

	/**
	 * Published Loop Items.
	 *
	 * @return array<int,string> id => title.
	 */
	public static function loop_templates(): array {
		if ( isset( self::$cache['templates'] ) ) {
			return self::$cache['templates'];
		}
		$out = array();
		$q   = new WP_Query(
			array(
				'post_type'      => EMCP_Tools_Themer_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => EMCP_Tools_Themer_Index::META_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'loop', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( (array) $q->posts as $id ) {
			$id = (int) ( is_object( $id ) ? ( $id->ID ?? 0 ) : $id );
			if ( $id <= 0 ) {
				continue;
			}
			$title = (string) get_the_title( $id );
			/* translators: %d: template id */
			$out[ $id ] = '' !== trim( $title ) ? $title : sprintf( __( 'Loop Item %d', 'emcp-tools' ), $id );
		}
		self::$cache['templates'] = $out;
		return $out;
	}

	/**
	 * Post types the loop query accepts.
	 *
	 * @return array<string,string> slug => label.
	 */
	public static function post_types(): array {
		if ( isset( self::$cache['types'] ) ) {
			return self::$cache['types'];
		}
		$out = array();
		foreach ( EMCP_Tools_Themer_Loop_Query::allowed_post_types() as $slug ) {
			$obj          = get_post_type_object( $slug );
			$out[ $slug ] = $obj && isset( $obj->label ) ? (string) $obj->label : $slug;
		}
		self::$cache['types'] = $out;
		return $out;
	}

	/**
	 * Public taxonomies with their terms, capped.
	 *
	 * Term keys are the `taxonomy:term_id` refs the loop query accepts. A
	 * taxonomy whose name that format cannot express is skipped, so every
	 * option offered is one the query keeps.
	 *
	 * @return array<string,array{label:string,terms:array<string,string>}>
	 */
	public static function taxonomy_terms(): array {
		if ( isset( self::$cache['terms'] ) ) {
			return self::$cache['terms'];
		}
		$out       = array();
		$remaining = self::MAX_TERMS;
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			if ( $remaining <= 0 ) {
				break;
			}
			$name = is_object( $tax ) ? (string) ( $tax->name ?? '' ) : '';
			if ( ! preg_match( '/^[a-z0-9_-]+$/', $name ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $name,
					'hide_empty' => false,
					'number'     => $remaining,
					'orderby'    => 'name',
				)
			);
			if ( is_wp_error( $terms ) || ! is_array( $terms ) || ! $terms ) {
				continue;
			}
			$rows = array();
			foreach ( $terms as $t ) {
				if ( $remaining <= 0 ) {
					break;
				}
				$term_id = (int) ( is_object( $t ) ? ( $t->term_id ?? 0 ) : 0 );
				if ( $term_id <= 0 ) {
					continue;
				}
				$rows[ $name . ':' . $term_id ] = (string) ( $t->name ?? '' );
				--$remaining;
			}
			if ( $rows ) {
				$out[ $name ] = array(
					'label' => (string) ( $tax->label ?? $name ),
					'terms' => $rows,
				);
			}
		}
		self::$cache['terms'] = $out;
		return $out;
	}

	/**
	 * Every term as one flat option list, labelled with its taxonomy.
	 *
	 * @return array<string,string>
	 */
	public static function flat_terms(): array {
		$out = array();
		foreach ( self::taxonomy_terms() as $group ) {
			foreach ( $group['terms'] as $key => $name ) {
				$out[ $key ] = $group['label'] . ': ' . $name;
			}
		}
		return $out;
	}

	/**
	 * Users who can write posts.
	 *
	 * `who => authors` is deprecated since WordPress 5.9; the capability
	 * filter is its replacement.
	 *
	 * @return array<int,string> user id => display name.
	 */
	public static function authors(): array {
		if ( isset( self::$cache['authors'] ) ) {
			return self::$cache['authors'];
		}
		$out   = array();
		$users = get_users(
			array(
				'capability' => array( 'edit_posts' ),
				'number'     => self::MAX_AUTHORS,
				'fields'     => array( 'ID', 'display_name' ),
				'orderby'    => 'display_name',
			)
		);
		foreach ( (array) $users as $u ) {
			$id = (int) ( is_object( $u ) ? ( $u->ID ?? 0 ) : 0 );
			if ( $id > 0 ) {
				$out[ $id ] = (string) ( $u->display_name ?? ( '#' . $id ) );
			}
		}
		self::$cache['authors'] = $out;
		return $out;
	}

	/**
	 * Orderings the loop query accepts.
	 *
	 * @param bool $with_products Include the WooCommerce orderings.
	 * @return array<string,string>
	 */
	public static function orderby( bool $with_products ): array {
		$out = array(
			'date'           => __( 'Date', 'emcp-tools' ),
			'modified'       => __( 'Last modified', 'emcp-tools' ),
			'title'          => __( 'Title', 'emcp-tools' ),
			'menu_order'     => __( 'Menu order', 'emcp-tools' ),
			'comment_count'  => __( 'Comment count', 'emcp-tools' ),
			'rand'           => __( 'Random', 'emcp-tools' ),
			'meta_value'     => __( 'Custom field (text)', 'emcp-tools' ),
			'meta_value_num' => __( 'Custom field (number)', 'emcp-tools' ),
		);
		if ( $with_products ) {
			$out['price']      = __( 'Price', 'emcp-tools' );
			$out['popularity'] = __( 'Popularity (sales)', 'emcp-tools' );
			$out['rating']     = __( 'Average rating', 'emcp-tools' );
		}
		return $out;
	}

	/**
	 * Date ranges the loop query accepts.
	 *
	 * @return array<string,string>
	 */
	public static function date_ranges(): array {
		return array(
			'all'          => __( 'All time', 'emcp-tools' ),
			'past_day'     => __( 'Past day', 'emcp-tools' ),
			'past_week'    => __( 'Past week', 'emcp-tools' ),
			'past_month'   => __( 'Past month', 'emcp-tools' ),
			'past_quarter' => __( 'Past quarter', 'emcp-tools' ),
			'past_year'    => __( 'Past year', 'emcp-tools' ),
			'custom'       => __( 'Custom range', 'emcp-tools' ),
		);
	}

	/** Test seam. */
	public static function reset_for_tests(): void {
		self::$cache = array();
	}
}
