<?php
/**
 * Themer condition index — the front-end fast path.
 *
 * A single autoloaded option maps template type => normalized rows
 * [{ id, include, exclude, priority }] so the resolver runs zero DB queries per
 * request. build() is pure (records -> grouped index); rebuild() is the WP glue
 * that queries the CPT and writes the option (hooked on save/delete).
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
class EMCP_Tools_Themer_Index {

	const OPTION = 'emcp_tools_themer_index';

	/** Post type storing templates. */
	const POST_TYPE = 'emcp_theme_template';

	/** Meta keys. */
	const META_TYPE       = '_emcp_themer_type';
	const META_CONDITIONS = '_emcp_themer_conditions';

	/**
	 * Build the grouped index from flat records.
	 *
	 * @param array<int,array{id?:int,type?:string,conditions?:array}> $records Records.
	 * @return array<string,array<int,array{id:int,include:array,exclude:array,priority:int}>>
	 */
	public static function build( array $records ): array {
		$index = array();
		foreach ( $records as $rec ) {
			if ( empty( $rec['id'] ) || empty( $rec['type'] ) ) {
				continue;
			}
			$cond                             = is_array( $rec['conditions'] ?? null ) ? $rec['conditions'] : array();
			$index[ (string) $rec['type'] ][] = array(
				'id'       => (int) $rec['id'],
				'include'  => is_array( $cond['include'] ?? null ) ? array_values( $cond['include'] ) : array(),
				'exclude'  => is_array( $cond['exclude'] ?? null ) ? array_values( $cond['exclude'] ) : array(),
				'priority' => (int) ( $cond['priority'] ?? 0 ),
			);
		}
		return $index;
	}

	/**
	 * Read the stored index (empty array when unset).
	 *
	 * @return array
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Rebuild the index option from the CPT. Hooked on save/delete.
	 *
	 * @return array The freshly built index.
	 */
	public static function rebuild(): array {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 500,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);

		$records = array();
		foreach ( $query->posts as $id ) {
			$id   = (int) $id;
			$type = (string) get_post_meta( $id, self::META_TYPE, true );
			if ( '' === $type ) {
				continue;
			}
			$raw       = get_post_meta( $id, self::META_CONDITIONS, true );
			$cond      = is_array( $raw ) ? $raw : ( is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array() );
			$records[] = array( 'id' => $id, 'type' => $type, 'conditions' => is_array( $cond ) ? $cond : array() );
		}

		$index = self::build( $records );
		update_option( self::OPTION, $index, true );
		return $index;
	}
}
