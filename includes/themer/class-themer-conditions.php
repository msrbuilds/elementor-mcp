<?php
/**
 * Pure evaluation of a Themer template's condition set.
 *
 * A condition set = { include: Rule[], exclude: Rule[] }. A template matches a
 * request when at least one include rule matches AND no exclude rule matches; the
 * returned value is the highest specificity among the matched include rules (used
 * by the resolver to pick the most specific winner), or null when it does not apply.
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
class EMCP_Tools_Themer_Conditions {

	/**
	 * Evaluate a condition set against a request context.
	 *
	 * @param array                              $conditions { include: Rule[], exclude: Rule[] }.
	 * @param array                              $ctx        Request context.
	 * @param EMCP_Tools_Themer_Matcher_Registry $registry   Matcher registry.
	 * @return int|null Highest matched-include specificity, or null if it does not apply.
	 */
	public static function evaluate( array $conditions, array $ctx, EMCP_Tools_Themer_Matcher_Registry $registry ): ?int {
		$include = isset( $conditions['include'] ) && is_array( $conditions['include'] ) ? $conditions['include'] : array();
		$exclude = isset( $conditions['exclude'] ) && is_array( $conditions['exclude'] ) ? $conditions['exclude'] : array();

		$best = -1;
		foreach ( $include as $rule ) {
			if ( is_array( $rule ) && $registry->matches( $rule, $ctx ) ) {
				$best = max( $best, $registry->specificity( $rule ) );
			}
		}
		if ( $best < 0 ) {
			return null;
		}

		foreach ( $exclude as $rule ) {
			if ( is_array( $rule ) && $registry->matches( $rule, $ctx ) ) {
				return null;
			}
		}

		return $best;
	}
}
