<?php
/**
 * Filtering, counts and paging for the Sandbox lists (spec 8.11 list pattern).
 * Pure: rows in, one page out.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sandbox list builder.
 */
final class EMCP_Tools_Admin_Sandbox_List {

	const PER_PAGE = 20;
	const STATUSES = array( 'all', 'active', 'inactive', 'review' );

	/**
	 * One page of rows plus the counts the filter shows.
	 *
	 * @param array $rows Mapped rows (id, title, ident, active, updatedTs, review{level}).
	 * @param array $args { status?, search?, page? }.
	 */
	public static function build( array $rows, array $args ): array {
		$status = (string) ( $args['status'] ?? 'all' );
		$status = in_array( $status, self::STATUSES, true ) ? $status : 'all';
		$search = trim( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( array $r ) use ( $search ): bool {
						return false !== mb_stripos( $r['title'] . ' ' . $r['ident'], $search );
					}
				)
			);
		}
		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return array( $b['updatedTs'], $b['id'] ) <=> array( $a['updatedTs'], $a['id'] );
			}
		);
		$counts = array( 'all' => count( $rows ), 'active' => 0, 'inactive' => 0, 'review' => 0 );
		foreach ( $rows as $r ) {
			++$counts[ $r['active'] ? 'active' : 'inactive' ];
			if ( self::needs_reading( $r ) ) {
				++$counts['review'];
			}
		}
		$shown = array_values(
			array_filter(
				$rows,
				static function ( array $r ) use ( $status ): bool {
					if ( 'active' === $status ) {
						return $r['active'];
					}
					if ( 'inactive' === $status ) {
						return ! $r['active'];
					}
					return 'review' === $status ? self::needs_reading( $r ) : true;
				}
			)
		);
		$total = count( $shown );
		$pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page  = min( max( 1, (int) ( $args['page'] ?? 1 ) ), $pages );
		return array(
			'items'   => array_slice( $shown, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE ),
			'counts'  => $counts,
			'total'   => $total,
			'page'    => $page,
			'pages'   => $pages,
			'perPage' => self::PER_PAGE,
			'query'   => array( 'status' => $status, 'search' => $search, 'page' => $page ),
		);
	}

	/**
	 * "Needs reading": the validator found something a human should read first.
	 *
	 * @param array $row Row.
	 */
	public static function needs_reading( array $row ): bool {
		return in_array( $row['review']['level'] ?? 'none', array( 'warning', 'critical' ), true );
	}
}
