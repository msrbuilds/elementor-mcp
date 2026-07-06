<?php
/**
 * Builds the cascading condition-builder schema the metabox UI consumes.
 *
 * The schema is a type-aware options tree: relations (include / [pro] exclude) and
 * groups (Entire site / Archives / Singular) each with sub-types, some of which
 * carry an "object" descriptor enabling a [pro] specific-object search (a page, a
 * term, an author). Free = broad leaves only; the `emcp_themer_condition_schema`
 * filter lets the Pro overlay add the Exclude relation, object search, and the
 * granular Author/Date/In-term nodes — so no Pro UI config lives in the free tree.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.1.0
 */
class EMCP_Tools_Themer_Condition_Schema {

	/**
	 * Whether a template type uses the condition builder at all.
	 *
	 * @param string $type Template type.
	 * @return bool
	 */
	public static function type_uses_builder( string $type ): bool {
		return in_array( $type, array( 'header', 'footer', 'single', 'archive' ), true );
	}

	/**
	 * The full schema for a template type.
	 *
	 * @param string $type Template type (header/footer/single/archive/…).
	 * @return array{relations:array,groups:array}
	 */
	public static function for_type( string $type ): array {
		$groups = array();

		// Header/Footer can target anything; Single → singular; Archive → archives.
		$want_general  = in_array( $type, array( 'header', 'footer' ), true );
		$want_singular = in_array( $type, array( 'header', 'footer', 'single' ), true );
		$want_archive  = in_array( $type, array( 'header', 'footer', 'archive' ), true );

		if ( $want_general ) {
			$groups[] = array(
				'value' => 'general',
				'label' => __( 'Entire site', 'emcp-tools' ),
				'subs'  => array(
					array( 'value' => 'entire-site', 'label' => __( 'Entire site', 'emcp-tools' ), 'selector' => 'entire-site' ),
				),
			);
		}

		if ( $want_archive ) {
			$groups[] = array(
				'value' => 'archive',
				'label' => __( 'Archives', 'emcp-tools' ),
				'subs'  => self::archive_subs(),
			);
		}

		if ( $want_singular ) {
			$groups[] = array(
				'value' => 'singular',
				'label' => __( 'Singular', 'emcp-tools' ),
				'subs'  => self::singular_subs(),
			);
		}

		$schema = array(
			'relations' => array(
				array( 'value' => 'include', 'label' => __( 'Include', 'emcp-tools' ) ),
			),
			'groups'    => $groups,
		);

		/**
		 * Filters the Themer condition schema. Pro adds the Exclude relation, object
		 * search on nodes, and granular Author/Date/In-term nodes.
		 *
		 * @param array  $schema The base (free) schema.
		 * @param string $type   Template type.
		 */
		$filtered = apply_filters( 'emcp_themer_condition_schema', $schema, $type );
		return is_array( $filtered ) ? $filtered : $schema;
	}

	/**
	 * Free archive sub-types: all archives, each post-type archive, each taxonomy
	 * (all terms). Pro augments these with specific-term search.
	 *
	 * @return array
	 */
	private static function archive_subs(): array {
		$subs = array(
			array( 'value' => 'all-archives', 'label' => __( 'All archives', 'emcp-tools' ), 'selector' => 'all-archives' ),
		);

		foreach ( get_post_types( array( 'public' => true, 'has_archive' => true ), 'objects' ) as $pt ) {
			$subs[] = array(
				'value'    => 'post-type-archive:' . $pt->name,
				/* translators: %s: post type label */
				'label'    => sprintf( __( '%s archive', 'emcp-tools' ), $pt->label ),
				'selector' => 'post-type-archive:' . $pt->name,
			);
		}

		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tx ) {
			$subs[] = array(
				'value'    => 'tax-archive:' . $tx->name,
				'label'    => $tx->label,
				'selector' => 'tax-archive:' . $tx->name,
				// Pro attaches an "object" descriptor here for specific-term search.
				'taxonomy' => $tx->name,
			);
		}

		return $subs;
	}

	/**
	 * Free singular sub-types: all singular, front page, each public post type.
	 * Pro augments post types with specific-post search + adds in-term/author nodes.
	 *
	 * @return array
	 */
	private static function singular_subs(): array {
		$subs = array(
			array( 'value' => 'all-singular', 'label' => __( 'All singular', 'emcp-tools' ), 'selector' => 'all-singular' ),
			array( 'value' => 'front-page', 'label' => __( 'Front page', 'emcp-tools' ), 'selector' => 'front-page' ),
		);

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( 'attachment' === $pt->name ) {
				continue; // media isn't a meaningful template target.
			}
			$subs[] = array(
				'value'     => 'post-type:' . $pt->name,
				'label'     => $pt->label,
				'selector'  => 'post-type:' . $pt->name,
				// Pro attaches an "object" descriptor here for specific-post search.
				'post_type' => $pt->name,
			);
		}

		return $subs;
	}
}
