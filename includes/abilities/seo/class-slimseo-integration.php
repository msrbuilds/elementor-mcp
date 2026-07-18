<?php
/**
 * Slim SEO integration (free) — two dispatcher tools (slimseo-read /
 * slimseo-write) over Slim SEO's `slim_seo` post/term meta + option.
 *
 * Slim SEO stores per-post and per-term SEO in a single `slim_seo` meta array
 * (keys: title, description, canonical, noindex, nofollow, facebook_image,
 * twitter_image); site settings live in the `slim_seo` option. Verified live.
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.5.0
 */
class EMCP_Tools_SlimSEO_Integration extends EMCP_Tools_SEO_Integration {

	const META_KEY = 'slim_seo';

	/** @return string */
	public function id(): string {
		return 'slimseo';
	}

	/** @return string */
	public function label(): string {
		return 'Slim SEO';
	}

	/** @return bool */
	public function is_active(): bool {
		return defined( 'SLIM_SEO_VER' );
	}

	/** @return array<string,array> */
	protected function operations(): array {
		$edit_posts = static function (): bool {
			return current_user_can( 'edit_posts' );
		};
		$manage     = static function (): bool {
			return current_user_can( 'manage_options' );
		};

		return array(
			'get-post-seo'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_post_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Get a post\'s Slim SEO metadata by { post_id } (title, description, canonical, noindex, nofollow, og_image, twitter_image).',
			),
			'get-term-seo'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_term_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Get a term\'s Slim SEO metadata by { term_id }.',
			),
			'get-settings'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_settings' ),
				'perm' => $manage,
				'desc' => 'Get Slim SEO site settings (the slim_seo option).',
			),
			'update-post-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_post_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update a post\'s Slim SEO metadata: { post_id, title?, description?, canonical?, noindex?, nofollow?, og_image?, twitter_image? }. Only provided fields change.',
			),
			'update-term-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_term_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update a term\'s Slim SEO metadata: { term_id, title?, description?, ... }.',
			),
		);
	}

	/**
	 * Unified field => Slim SEO meta-array key.
	 *
	 * @return array<string,string>
	 */
	private function map(): array {
		return array(
			'title'         => 'title',
			'description'   => 'description',
			'canonical'     => 'canonical',
			'noindex'       => 'noindex',
			'nofollow'      => 'nofollow',
			'og_image'      => 'facebook_image',
			'twitter_image' => 'twitter_image',
		);
	}

	/**
	 * Shape a stored slim_seo array into the unified read view.
	 *
	 * @param array $data Stored meta.
	 * @return array<string,mixed>
	 */
	private function read_view( array $data ): array {
		$out = array();
		foreach ( $this->map() as $field => $key ) {
			$val = $data[ $key ] ?? '';
			if ( in_array( $field, array( 'noindex', 'nofollow' ), true ) ) {
				$out[ $field ] = ! empty( $val );
			} else {
				$out[ $field ] = is_scalar( $val ) ? (string) $val : $val;
			}
		}
		return $out;
	}

	/**
	 * Merge unified input fields into a stored slim_seo array.
	 *
	 * @param array $current Existing meta.
	 * @param array $args    Operation arguments.
	 * @return array
	 */
	private function apply( array $current, array $args ): array {
		foreach ( $this->map() as $field => $key ) {
			if ( ! array_key_exists( $field, $args ) ) {
				continue;
			}
			if ( in_array( $field, array( 'noindex', 'nofollow' ), true ) ) {
				$current[ $key ] = ! empty( $args[ $field ] ) ? true : false;
			} else {
				$current[ $key ] = is_scalar( $args[ $field ] ) ? (string) $args[ $field ] : $args[ $field ];
			}
		}
		return $current;
	}

	/**
	 * @param array $args { post_id }.
	 * @return array|WP_Error
	 */
	public function op_get_post_seo( array $args ) {
		$id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return $this->missing_or_not_found( 'post_id', $id, 'post' );
		}
		$data = get_post_meta( $id, self::META_KEY, true );
		return array( 'post_id' => $id, 'seo' => $this->read_view( is_array( $data ) ? $data : array() ) );
	}

	/**
	 * @param array $args { term_id }.
	 * @return array|WP_Error
	 */
	public function op_get_term_seo( array $args ) {
		$id = isset( $args['term_id'] ) ? absint( $args['term_id'] ) : 0;
		if ( ! $id || ! get_term( $id ) ) {
			return $this->missing_or_not_found( 'term_id', $id, 'term' );
		}
		$data = get_term_meta( $id, self::META_KEY, true );
		return array( 'term_id' => $id, 'seo' => $this->read_view( is_array( $data ) ? $data : array() ) );
	}

	/**
	 * @param array $args Unused.
	 * @return array
	 */
	public function op_get_settings( array $args ): array {
		$opt = get_option( self::META_KEY, array() );
		return array( 'settings' => is_array( $opt ) ? $opt : array() );
	}

	/**
	 * @param array $args { post_id, ...fields }.
	 * @return array|WP_Error
	 */
	public function op_update_post_seo( array $args ) {
		$id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return $this->missing_or_not_found( 'post_id', $id, 'post' );
		}
		$current = get_post_meta( $id, self::META_KEY, true );
		$merged  = $this->apply( is_array( $current ) ? $current : array(), $args );
		update_post_meta( $id, self::META_KEY, $merged );
		return array( 'updated' => true, 'post_id' => $id, 'seo' => $this->read_view( $merged ) );
	}

	/**
	 * @param array $args { term_id, ...fields }.
	 * @return array|WP_Error
	 */
	public function op_update_term_seo( array $args ) {
		$id = isset( $args['term_id'] ) ? absint( $args['term_id'] ) : 0;
		if ( ! $id || ! get_term( $id ) ) {
			return $this->missing_or_not_found( 'term_id', $id, 'term' );
		}
		$current = get_term_meta( $id, self::META_KEY, true );
		$merged  = $this->apply( is_array( $current ) ? $current : array(), $args );
		update_term_meta( $id, self::META_KEY, $merged );
		return array( 'updated' => true, 'term_id' => $id, 'seo' => $this->read_view( $merged ) );
	}

	/**
	 * @param string $field Argument name.
	 * @param int    $id    Id.
	 * @param string $what  Object type.
	 * @return WP_Error
	 */
	private function missing_or_not_found( string $field, int $id, string $what ): WP_Error {
		if ( ! $id ) {
			return new WP_Error(
				'missing_argument',
				sprintf(
					/* translators: %s: argument name */
					__( 'Missing required argument: %s.', 'emcp-tools' ),
					$field
				),
				array( 'status' => 400 )
			);
		}
		return new WP_Error(
			'not_found',
			sprintf(
				/* translators: 1: object type, 2: id */
				__( 'No %1$s with id %2$d.', 'emcp-tools' ),
				$what,
				$id
			),
			array( 'status' => 404 )
		);
	}
}
