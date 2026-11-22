<?php
/**
 * Visibility integration (free): two dispatcher tools (visibility-read /
 * visibility-write) over Visibility's per-post `_native_aeo_pack_*` meta keys.
 *
 * Unlike Slim SEO, Visibility does not store its SEO fields as a single
 * serialized meta array: each field (title, description, canonical, robots
 * flags, Open Graph title/description/image, Schema.org type override) is its
 * own post meta key, already registered with `show_in_rest`. Term-level SEO
 * (category/tag) is out of scope for this first pass; see the plugin's
 * Native_AEO_Pack_Term_Meta for the equivalent keys if that's added later.
 *
 * @package EMCP_Tools
 * @since   3.17.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.17.1
 */
class EMCP_Tools_Visibility_Integration extends EMCP_Tools_SEO_Integration {

	const META_TITLE            = '_native_aeo_pack_title';
	const META_DESCRIPTION      = '_native_aeo_pack_description';
	const META_CANONICAL        = '_native_aeo_pack_canonical';
	const META_OG_TITLE         = '_native_aeo_pack_og_title';
	const META_OG_DESCRIPTION   = '_native_aeo_pack_og_description';
	const META_OG_IMAGE         = '_native_aeo_pack_og_image';
	const META_NOINDEX          = '_native_aeo_pack_noindex';
	const META_NOFOLLOW         = '_native_aeo_pack_nofollow';
	const META_NOIMAGEINDEX     = '_native_aeo_pack_noimageindex';
	const META_NOSNIPPET        = '_native_aeo_pack_nosnippet';
	const META_SITEMAP_EXCLUDED = '_native_aeo_pack_sitemap_excluded';
	const META_SCHEMA_TYPE      = '_native_aeo_pack_schema_type';

	/** @return string */
	public function id(): string {
		return 'visibility';
	}

	/** @return string */
	public function label(): string {
		return 'Visibility';
	}

	/** @return bool */
	public function is_active(): bool {
		return defined( 'NATIVE_AEO_PACK_VERSION' );
	}

	/** @return array<string,array> */
	protected function operations(): array {
		$edit_posts = static function (): bool {
			return current_user_can( 'edit_posts' );
		};

		return array(
			'get-post-seo'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_post_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Get a post\'s Visibility SEO metadata by { post_id } (title, description, canonical, noindex, nofollow, noimageindex, nosnippet, og_title, og_description, og_image, schema_type, sitemap_excluded).',
			),
			'update-post-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_post_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update a post\'s Visibility SEO metadata: { post_id, title?, description?, canonical?, noindex?, nofollow?, noimageindex?, nosnippet?, og_title?, og_description?, og_image?, schema_type?, sitemap_excluded? }. Only provided fields change.',
			),
		);
	}

	/**
	 * Unified field => Visibility meta key + value shape. `flag` fields are
	 * stored as the string '1' or '' (Visibility's own convention: it clears
	 * the value rather than deleting the row), `int` is an attachment id,
	 * everything else is free text.
	 *
	 * @return array<string,array{key:string,type:string}>
	 */
	private function map(): array {
		return array(
			'title'            => array(
				'key'  => self::META_TITLE,
				'type' => 'string',
			),
			'description'      => array(
				'key'  => self::META_DESCRIPTION,
				'type' => 'string',
			),
			'canonical'        => array(
				'key'  => self::META_CANONICAL,
				'type' => 'string',
			),
			'og_title'         => array(
				'key'  => self::META_OG_TITLE,
				'type' => 'string',
			),
			'og_description'   => array(
				'key'  => self::META_OG_DESCRIPTION,
				'type' => 'string',
			),
			'og_image'         => array(
				'key'  => self::META_OG_IMAGE,
				'type' => 'int',
			),
			'schema_type'      => array(
				'key'  => self::META_SCHEMA_TYPE,
				'type' => 'schema_type',
			),
			'noindex'          => array(
				'key'  => self::META_NOINDEX,
				'type' => 'flag',
			),
			'nofollow'         => array(
				'key'  => self::META_NOFOLLOW,
				'type' => 'flag',
			),
			'noimageindex'     => array(
				'key'  => self::META_NOIMAGEINDEX,
				'type' => 'flag',
			),
			'nosnippet'        => array(
				'key'  => self::META_NOSNIPPET,
				'type' => 'flag',
			),
			'sitemap_excluded' => array(
				'key'  => self::META_SITEMAP_EXCLUDED,
				'type' => 'flag',
			),
		);
	}

	/** Visibility's meta keys are individual, not a single array: list them all for the ledger. */
	protected function recordable_meta_keys( string $object ): array {
		if ( 'post' !== $object ) {
			return array();
		}
		return wp_list_pluck( $this->map(), 'key' );
	}

	/**
	 * Valid Schema.org @type overrides, straight from Visibility's own
	 * allowlist (the same one its REST meta sanitizer enforces).
	 *
	 * @return string[]
	 */
	private function schema_type_choices(): array {
		if ( class_exists( 'Native_AEO_Pack_Settings' ) && method_exists( 'Native_AEO_Pack_Settings', 'schema_type_choices' ) ) {
			return Native_AEO_Pack_Settings::schema_type_choices();
		}
		return array( '' );
	}

	/**
	 * Read the unified view for a post.
	 *
	 * @param int $id Post id.
	 * @return array<string,mixed>
	 */
	private function read_view( int $id ): array {
		$out = array();
		foreach ( $this->map() as $field => $spec ) {
			$val = get_post_meta( $id, $spec['key'], true );
			if ( 'flag' === $spec['type'] ) {
				$out[ $field ] = ! empty( $val );
			} elseif ( 'int' === $spec['type'] ) {
				$out[ $field ] = absint( $val );
			} else {
				$out[ $field ] = is_scalar( $val ) ? (string) $val : '';
			}
		}
		return $out;
	}

	/**
	 * Validate the provided fields before anything is written. The only field
	 * with a closed value set is schema_type: one of Visibility's own choices,
	 * or '' to clear the override back to the site default (Visibility's REST
	 * sanitizer stores '' for anything it does not recognise).
	 *
	 * @param array $args Operation arguments.
	 * @return true|WP_Error
	 */
	private function validate( array $args ) {
		if ( ! array_key_exists( 'schema_type', $args ) ) {
			return true;
		}
		$value   = is_scalar( $args['schema_type'] ) ? (string) $args['schema_type'] : null;
		$allowed = $this->schema_type_choices();
		if ( '' === $value || ( null !== $value && in_array( $value, $allowed, true ) ) ) {
			return true;
		}
		return new WP_Error(
			'invalid_schema_type',
			sprintf(
				/* translators: %s: comma-separated list of allowed values */
				__( 'schema_type must be one of: %s (or an empty string to use the site default).', 'emcp-tools' ),
				implode( ', ', $allowed )
			),
			array(
				'status'  => 400,
				'allowed' => $allowed,
			)
		);
	}

	/**
	 * Write the provided fields for a post. Only keys present in $args change.
	 * Call validate() first; this never writes a partial update.
	 *
	 * @param int   $id   Post id.
	 * @param array $args Operation arguments.
	 */
	private function apply( int $id, array $args ): void {
		foreach ( $this->map() as $field => $spec ) {
			if ( ! array_key_exists( $field, $args ) ) {
				continue;
			}
			if ( 'flag' === $spec['type'] ) {
				update_post_meta( $id, $spec['key'], ! empty( $args[ $field ] ) ? '1' : '' );
			} elseif ( 'int' === $spec['type'] ) {
				update_post_meta( $id, $spec['key'], absint( $args[ $field ] ) );
			} else {
				update_post_meta( $id, $spec['key'], wp_slash( is_scalar( $args[ $field ] ) ? (string) $args[ $field ] : '' ) );
			}
		}
	}

	/**
	 * @param array $args { post_id }.
	 * @return array|WP_Error
	 */
	public function op_get_post_seo( array $args ) {
		$id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return $this->missing_or_not_found( 'post_id', $id );
		}
		return array(
			'post_id' => $id,
			'seo'     => $this->read_view( $id ),
		);
	}

	/**
	 * @param array $args { post_id, ...fields }.
	 * @return array|WP_Error
	 */
	public function op_update_post_seo( array $args ) {
		$id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return $this->missing_or_not_found( 'post_id', $id );
		}
		$valid = $this->validate( $args );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$this->apply( $id, $args );
		return array(
			'updated' => true,
			'post_id' => $id,
			'seo'     => $this->read_view( $id ),
		);
	}

	/**
	 * @param string $field Argument name.
	 * @param int    $id    Id.
	 * @return WP_Error
	 */
	private function missing_or_not_found( string $field, int $id ): WP_Error {
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
				/* translators: %d: post id */
				__( 'No post with id %d.', 'emcp-tools' ),
				$id
			),
			array( 'status' => 404 )
		);
	}
}
