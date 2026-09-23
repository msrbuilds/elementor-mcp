<?php
/**
 * Option lists both builders offer for a loop query.
 *
 * One source, so an Elementor control and a block inspector cannot drift.
 * Everything is capped and memoised per request: a site with 40k terms must
 * not turn a widget panel into a timeout. Labels are plain text; the
 * consumer escapes them.
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

	/** Terms each taxonomy is offered at least, while the total cap allows. */
	const MIN_TERMS_PER_TAXONOMY = 50;

	/** @var array<string,mixed> */
	private static $cache = array();

	/** @var bool */
	private static $booted = false;

	/**
	 * Drop the memo whenever something it lists changes. Idempotent.
	 *
	 * deleted_post covers a Loop Item deleted outright (skipping the trash),
	 * which fires no save_post.
	 */
	public static function init(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		$hooks        = array( 'save_post_' . EMCP_Tools_Themer_CPT::POST_TYPE, 'deleted_post', 'created_term', 'edited_term', 'delete_term', 'profile_update', 'user_register' );
		foreach ( $hooks as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush' ) );
		}
	}

	/** Forget every memoised list. */
	public static function flush(): void {
		self::$cache = array();
	}

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
			$title = self::plain( get_the_title( $id ) );
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
			$out[ $slug ] = $obj && isset( $obj->label ) ? self::plain( $obj->label ) : $slug;
		}
		self::$cache['types'] = $out;
		return $out;
	}

	/**
	 * Public taxonomies with their terms, capped at MAX_TERMS in total.
	 *
	 * Each taxonomy first gets a fair share (at least MIN_TERMS_PER_TAXONOMY
	 * while the cap allows that for every taxonomy, else an equal split) so
	 * one huge taxonomy cannot starve the rest; the budget a small
	 * taxonomy leaves unused then goes to the ones that filled their share.
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
		$taxes = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			$name = is_object( $tax ) ? (string) ( $tax->name ?? '' ) : '';
			if ( preg_match( '/^[a-z0-9_-]+$/', $name ) ) {
				$taxes[ $name ] = self::plain( $tax->label ?? $name );
			}
		}
		$out = array();
		if ( $taxes ) {
			// The floor only applies while every taxonomy can have it: with
			// more than MAX_TERMS / MIN_TERMS_PER_TAXONOMY taxonomies, a floor
			// would spend the whole budget on the first few and starve the rest.
			$count = count( $taxes );
			$share = max( 1, (int) floor( self::MAX_TERMS / $count ) );
			if ( $count * self::MIN_TERMS_PER_TAXONOMY <= self::MAX_TERMS ) {
				$share = max( self::MIN_TERMS_PER_TAXONOMY, $share );
			}
			$remaining = self::MAX_TERMS;
			$rows      = array();
			$full      = array();

			// Pass 1: a fair share each.
			foreach ( $taxes as $name => $label ) {
				if ( $remaining <= 0 ) {
					break;
				}
				$want          = min( $share, $remaining );
				$rows[ $name ] = self::fetch_terms( $name, $want, 0 );
				$remaining    -= count( $rows[ $name ] );
				if ( count( $rows[ $name ] ) >= $want ) {
					$full[] = $name; // Filled its share, so it may have more.
				}
			}

			// Pass 2: the unused budget goes to taxonomies that filled theirs.
			foreach ( $full as $name ) {
				if ( $remaining <= 0 ) {
					break;
				}
				$more          = self::fetch_terms( $name, $remaining, count( $rows[ $name ] ) );
				$rows[ $name ] = $rows[ $name ] + $more;
				$remaining    -= count( $more );
			}

			foreach ( $taxes as $name => $label ) {
				if ( empty( $rows[ $name ] ) ) {
					continue;
				}
				$terms = array();
				foreach ( $rows[ $name ] as $term_id => $term_name ) {
					$terms[ $name . ':' . $term_id ] = $term_name;
				}
				$out[ $name ] = array(
					'label' => $label,
					'terms' => $terms,
				);
			}
		}
		self::$cache['terms'] = $out;
		return $out;
	}

	/**
	 * One page of a taxonomy's terms.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $number   How many at most.
	 * @param int    $offset   How many to skip.
	 * @return array<int,string> term id => plain name, at most $number entries.
	 */
	private static function fetch_terms( string $taxonomy, int $number, int $offset ): array {
		if ( $number <= 0 ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'               => $taxonomy,
				'hide_empty'             => false,
				'number'                 => $number,
				'offset'                 => $offset,
				'orderby'                => 'name',
				'update_term_meta_cache' => false,
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $t ) {
			if ( count( $out ) >= $number ) {
				break;
			}
			$term_id = (int) ( is_object( $t ) ? ( $t->term_id ?? 0 ) : 0 );
			if ( $term_id > 0 ) {
				$out[ $term_id ] = self::plain( $t->name ?? '' );
			}
		}
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
				$out[ $id ] = self::plain( $u->display_name ?? ( '#' . $id ) );
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

	/**
	 * Public taxonomies the loop query can relate by (names the term ref
	 * format can express).
	 *
	 * @return array<string,string> name => plain label.
	 */
	public static function taxonomies(): array {
		if ( isset( self::$cache['taxonomies'] ) ) {
			return self::$cache['taxonomies'];
		}
		$out = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			$name = is_object( $tax ) ? (string) ( $tax->name ?? '' ) : '';
			if ( preg_match( '/^[a-z0-9_-]+$/', $name ) ) {
				$out[ $name ] = self::plain( $tax->label ?? $name );
			}
		}
		self::$cache['taxonomies'] = $out;
		return $out;
	}

	/**
	 * Label for a saved Loop Item id missing from loop_templates().
	 *
	 * @param int|string $id Template id.
	 * @return string Plain text.
	 */
	public static function template_label( $id ): string {
		$title = self::plain( get_the_title( (int) $id ) );
		/* translators: %d: Loop Item id */
		return '' !== trim( $title ) ? $title : sprintf( __( 'Loop Item #%d', 'emcp-tools' ), (int) $id );
	}

	/**
	 * Label for a saved `taxonomy:term_id` ref missing from flat_terms().
	 *
	 * @param string $ref Term ref.
	 * @return string Plain text.
	 */
	public static function term_label( $ref ): string {
		$parts = explode( ':', (string) $ref, 2 );
		$term  = 2 === count( $parts ) && function_exists( 'get_term' ) ? get_term( (int) $parts[1], $parts[0] ) : null;
		if ( is_object( $term ) && isset( $term->name ) && ! is_wp_error( $term ) ) {
			return $parts[0] . ': ' . self::plain( $term->name );
		}
		return (string) $ref;
	}

	/**
	 * Label for a saved author id missing from authors().
	 *
	 * @param int|string $id User id.
	 * @return string Plain text.
	 */
	public static function author_label( $id ): string {
		$user = function_exists( 'get_userdata' ) ? get_userdata( (int) $id ) : false;
		/* translators: %d: user id */
		return $user ? self::plain( $user->display_name ) : sprintf( __( 'User #%d', 'emcp-tools' ), (int) $id );
	}

	/**
	 * Plain text for an option label (the consumer escapes it).
	 *
	 * @param mixed $raw Label.
	 * @return string
	 */
	private static function plain( $raw ): string {
		return wp_specialchars_decode( wp_strip_all_tags( (string) $raw ), ENT_QUOTES );
	}

	/** Test seam. */
	public static function reset_for_tests(): void {
		self::$cache  = array();
		self::$booted = false;
	}
}
