<?php
/**
 * Pure slot resolution: pick the single winning template id per slot.
 *
 * Given the condition index (type => rows), a request context, a matcher registry,
 * and a priority ranker, resolve() returns { header, body, footer } — the id that
 * wins each slot, or null. The body slot's eligible type is derived from the
 * context (singular->single, archive->archive, search, 404). Winner ranking:
 * highest matched specificity, then highest ranker() value (priority), then newest
 * (largest) id.
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
class EMCP_Tools_Themer_Resolver {

	/**
	 * The body-slot template type for a request context, or null.
	 *
	 * @param array $ctx Request context.
	 * @return string|null single|archive|search|404
	 */
	public static function body_type( array $ctx ): ?string {
		if ( ! empty( $ctx['is_404'] ) ) {
			return '404';
		}
		if ( ! empty( $ctx['is_search'] ) ) {
			return 'search';
		}
		if ( ! empty( $ctx['is_singular'] ) ) {
			return 'single';
		}
		if ( ! empty( $ctx['is_archive'] ) || ! empty( $ctx['is_post_type_archive'] )
			|| ! empty( $ctx['is_author'] ) || ! empty( $ctx['is_date'] ) || ! empty( $ctx['is_home'] ) ) {
			return 'archive';
		}
		return null;
	}

	/**
	 * Resolve all three slots.
	 *
	 * @param array                              $index    type => rows[{id, include, exclude, priority}].
	 * @param array                              $ctx      Request context.
	 * @param EMCP_Tools_Themer_Matcher_Registry $registry Matcher registry.
	 * @param callable                           $ranker   fn(array $row): int priority.
	 * @return array{header:?int, body:?int, footer:?int}
	 */
	public static function resolve( array $index, array $ctx, EMCP_Tools_Themer_Matcher_Registry $registry, callable $ranker ): array {
		$body_type = self::body_type( $ctx );

		return array(
			'header' => self::winner( $index['header'] ?? array(), $ctx, $registry, $ranker ),
			'body'   => $body_type ? self::winner( $index[ $body_type ] ?? array(), $ctx, $registry, $ranker ) : null,
			'footer' => self::winner( $index['footer'] ?? array(), $ctx, $registry, $ranker ),
		);
	}

	/**
	 * The winning id among candidate rows, or null.
	 *
	 * @param array                              $rows     Candidate rows.
	 * @param array                              $ctx      Request context.
	 * @param EMCP_Tools_Themer_Matcher_Registry $registry Matcher registry.
	 * @param callable                           $ranker   Priority ranker.
	 * @return int|null
	 */
	private static function winner( array $rows, array $ctx, EMCP_Tools_Themer_Matcher_Registry $registry, callable $ranker ): ?int {
		$best      = null;
		$best_spec = -1;
		$best_prio = PHP_INT_MIN;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['id'] ) ) {
				continue;
			}
			$spec = EMCP_Tools_Themer_Conditions::evaluate(
				array(
					'include' => $row['include'] ?? array(),
					'exclude' => $row['exclude'] ?? array(),
				),
				$ctx,
				$registry
			);
			if ( null === $spec ) {
				continue;
			}
			$prio = (int) call_user_func( $ranker, $row );
			$id   = (int) $row['id'];

			if ( $spec > $best_spec
				|| ( $spec === $best_spec && $prio > $best_prio )
				|| ( $spec === $best_spec && $prio === $best_prio && ( null === $best || $id > $best ) ) ) {
				$best      = $id;
				$best_spec = $spec;
				$best_prio = $prio;
			}
		}

		return $best;
	}
}
