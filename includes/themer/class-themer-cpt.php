<?php
/**
 * Themer template CPT + quota gate.
 *
 * Registers `emcp_theme_template` (editable by any builder), enables Elementor on
 * it, and enforces the free 1-per-type quota. The cap is a seam: the Pro overlay
 * raises `emcp_themer_quota` to PHP_INT_MAX. register() runs on `init`.
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
class EMCP_Tools_Themer_CPT {

	const POST_TYPE = 'emcp_theme_template';

	/** Valid template types. */
	const TYPES = array( 'header', 'footer', 'single', 'archive', 'search', '404' );

	/**
	 * Register the CPT + Elementor support. Hooked to `init`.
	 */
	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor', 'author', 'custom-fields' ),
				'labels'              => array(
					'name'          => __( 'EMCP Theme Templates', 'emcp-tools' ),
					'singular_name' => __( 'Theme Template', 'emcp-tools' ),
				),
			)
		);

		// Let Elementor offer "Edit with Elementor" on our CPT.
		add_filter(
			'elementor/cpt_support/get_public_post_types',
			static function ( $types ) {
				$types[] = self::POST_TYPE;
				return array_unique( (array) $types );
			}
		);
		add_filter(
			'elementor/utils/get_public_post_types',
			static function ( $types ) {
				if ( is_array( $types ) && ! isset( $types[ self::POST_TYPE ] ) ) {
					$types[ self::POST_TYPE ] = __( 'Theme Template', 'emcp-tools' );
				}
				return $types;
			}
		);
	}

	/**
	 * The per-type cap (free 1; Pro raises via the filter).
	 *
	 * @param string $type Template type.
	 * @return int
	 */
	public static function quota( string $type ): int {
		/**
		 * Filters the max number of templates allowed per type.
		 *
		 * @param int    $cap  Default 1 (free).
		 * @param string $type Template type.
		 */
		return (int) apply_filters( 'emcp_themer_quota', 1, $type );
	}

	/**
	 * Whether another template of $type may be created given the current count.
	 *
	 * @param string $type           Template type.
	 * @param int    $existing_count Existing count of that type.
	 * @return bool
	 */
	public static function can_create( string $type, int $existing_count ): bool {
		return $existing_count < self::quota( $type );
	}

	/**
	 * Count existing templates of a type (live).
	 *
	 * @param string $type Template type.
	 * @return int
	 */
	public static function count_of_type( string $type ): int {
		$q = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => EMCP_Tools_Themer_Index::META_TYPE,
				'meta_value'     => $type,
			)
		);
		return (int) $q->found_posts;
	}
}
