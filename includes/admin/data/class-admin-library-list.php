<?php
/**
 * A categorised library (prompts, brand kits) as one filtered, counted page
 * for the admin screens (spec 8.8, 8.17). Pure: no WordPress calls.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_Library_List {

	/**
	 * @param array[]  $categories [ { slug, label, $items_key: [ item ] } ].
	 * @param string   $items_key  'prompts' or 'kits'.
	 * @param callable $haystack   item => searchable text.
	 * @param array    $query      { search?, category?, page? }.
	 * @param int      $per_page   Page size.
	 */
	public static function build( array $categories, string $items_key, callable $haystack, array $query, int $per_page = 12 ): array {
		$search   = strtolower( trim( (string) ( $query['search'] ?? '' ) ) );
		$category = (string) ( $query['category'] ?? '' );
		$counts   = array();
		$matched  = array();
		foreach ( $categories as $cat ) {
			$slug  = (string) ( $cat['slug'] ?? '' );
			$label = (string) ( $cat['label'] ?? $slug );
			$items = is_array( $cat[ $items_key ] ?? null ) ? $cat[ $items_key ] : array();
			if ( '' === $slug || ! $items ) {
				continue;
			}
			$counts[] = array( 'slug' => $slug, 'label' => $label, 'count' => count( $items ) );
			if ( '' !== $category && $category !== $slug ) {
				continue;
			}
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				if ( '' !== $search && false === strpos( strtolower( (string) $haystack( $item ) ), $search ) ) {
					continue;
				}
				$matched[] = array_merge( $item, array( 'category' => $slug, 'categoryLabel' => $label ) );
			}
		}
		$total = count( $matched );
		$pages = max( 1, (int) ceil( $total / max( 1, $per_page ) ) );
		$page  = min( $pages, max( 1, (int) ( $query['page'] ?? 1 ) ) );
		return array(
			'items'      => array_slice( $matched, ( $page - 1 ) * $per_page, $per_page ),
			'total'      => $total,
			'page'       => $page,
			'pages'      => $pages,
			'categories' => $counts,
		);
	}
}
