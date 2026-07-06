<?php
/**
 * Themer selector matcher registry (extension seam).
 *
 * Maps a selector key (text before the first ":" in a rule's `object`, or the
 * whole string) to a specificity score and a matcher callable. Free selectors are
 * preloaded; the `emcp_themer_matchers` filter lets the Pro overlay add granular
 * selectors (per-ID/per-term/per-author/exclude/date) without any Pro code in the
 * free tree. Matchers are pure: callback( array $rule, array $ctx ): bool.
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
class EMCP_Tools_Themer_Matcher_Registry {

	/** @var array<string,array{specificity:int,callback:callable}> */
	private $matchers = array();

	/**
	 * Build a registry with the free matchers + any registered via the filter.
	 *
	 * @return self
	 */
	public static function fresh(): self {
		$self           = new self();
		$self->matchers = $self->free_matchers();
		/**
		 * Filters the Themer selector matchers.
		 *
		 * @param array<string,array{specificity:int,callback:callable}> $matchers Selector key => spec + matcher.
		 */
		$filtered = apply_filters( 'emcp_themer_matchers', $self->matchers );
		if ( is_array( $filtered ) ) {
			$self->matchers = $filtered;
		}
		return $self;
	}

	/**
	 * The free (broad) selectors.
	 *
	 * @return array<string,array{specificity:int,callback:callable}>
	 */
	private function free_matchers(): array {
		return array(
			'entire-site'       => array(
				'specificity' => 0,
				'callback'    => static function ( array $rule, array $ctx ): bool {
					return true;
				},
			),
			'all-singular'      => array(
				'specificity' => 10,
				'callback'    => static function ( array $rule, array $ctx ): bool {
					return ! empty( $ctx['is_singular'] );
				},
			),
			'all-archives'      => array(
				'specificity' => 10,
				'callback'    => static function ( array $rule, array $ctx ): bool {
					return ! empty( $ctx['is_archive'] ) || ! empty( $ctx['is_post_type_archive'] )
						|| ! empty( $ctx['is_author'] ) || ! empty( $ctx['is_date'] ) || ! empty( $ctx['is_home'] );
				},
			),
			'front-page'        => array(
				'specificity' => 20,
				'callback'    => static function ( array $rule, array $ctx ): bool {
					return ! empty( $ctx['is_front_page'] );
				},
			),
			'post-type'         => array(
				'specificity' => 20,
				'callback'    => static function ( array $rule, array $ctx ): bool {
					return ! empty( $ctx['is_singular'] ) && ( $ctx['post_type'] ?? '' ) === self::param( $rule );
				},
			),
			'post-type-archive' => array(
				'specificity' => 20,
				'callback'    => static function ( array $rule, array $ctx ): bool {
					return ! empty( $ctx['is_post_type_archive'] ) && ( $ctx['queried_post_type'] ?? '' ) === self::param( $rule );
				},
			),
			'tax-archive'       => array(
				'specificity' => 20,
				'callback'    => static function ( array $rule, array $ctx ): bool {
					return ( $ctx['queried_taxonomy'] ?? '' ) === self::param( $rule );
				},
			),
		);
	}

	/**
	 * The selector key of a rule (before the first ":", else whole string).
	 *
	 * @param array $rule Rule with an `object` string.
	 * @return string
	 */
	private static function key( array $rule ): string {
		$object = (string) ( $rule['object'] ?? '' );
		$pos    = strpos( $object, ':' );
		return false === $pos ? $object : substr( $object, 0, $pos );
	}

	/**
	 * The first parameter of a parameterized selector (`post-type:page` -> `page`;
	 * `tax:category:5` -> `category`).
	 *
	 * @param array $rule Rule with an `object` string.
	 * @return string
	 */
	public static function param( array $rule ): string {
		$parts = explode( ':', (string) ( $rule['object'] ?? '' ) );
		return $parts[1] ?? '';
	}

	/**
	 * The second parameter (`tax:category:5` -> `5`).
	 *
	 * @param array $rule Rule with an `object` string.
	 * @return string
	 */
	public static function param2( array $rule ): string {
		$parts = explode( ':', (string) ( $rule['object'] ?? '' ) );
		return $parts[2] ?? '';
	}

	/**
	 * Whether a rule matches a request context.
	 *
	 * @param array $rule Rule.
	 * @param array $ctx  Request context.
	 * @return bool
	 */
	public function matches( array $rule, array $ctx ): bool {
		$key = self::key( $rule );
		if ( ! isset( $this->matchers[ $key ] ) ) {
			return false;
		}
		return (bool) call_user_func( $this->matchers[ $key ]['callback'], $rule, $ctx );
	}

	/**
	 * The specificity score of a rule's selector (0 when unknown).
	 *
	 * @param array $rule Rule.
	 * @return int
	 */
	public function specificity( array $rule ): int {
		$key = self::key( $rule );
		return isset( $this->matchers[ $key ] ) ? (int) $this->matchers[ $key ]['specificity'] : 0;
	}
}
