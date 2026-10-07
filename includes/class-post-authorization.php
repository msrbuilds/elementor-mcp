<?php
/**
 * Post-type-aware authorization helpers for MCP writes.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves primitive capabilities from the requested post type.
 */
class EMCP_Tools_Post_Authorization {

	/** Statuses that require the post type's publish capability. */
	const PUBLISHED_STATUSES = array( 'publish', 'future', 'private' );

	/** Elementor document types that can affect sitewide visitor output. */
	const SITEWIDE_ELEMENTOR_TYPES = array( 'header', 'footer', 'single', 'single-post', 'single-page', 'archive', 'search-results', 'error-404', 'popup', 'loop-item' );

	/** Register central post-object capability protection. */
	public static function register(): void {
		add_filter( 'map_meta_cap', array( __CLASS__, 'protect_sitewide_elementor_template' ), 10, 4 );
	}

	/**
	 * Require theme-management authority for every edit/delete path targeting a
	 * globally rendered Elementor template. WordPress applies this to MCP tools,
	 * native admin requests, REST writes, revisions and plugin integrations.
	 *
	 * @param string[] $caps    Primitive capabilities mapped by WordPress.
	 * @param string   $cap     Requested meta capability.
	 * @param int      $user_id User id.
	 * @param mixed[]  $args    Meta-capability arguments.
	 * @return string[]
	 */
	public static function protect_sitewide_elementor_template( array $caps, string $cap, int $user_id, array $args ): array {
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'edit_page', 'delete_page' ), true ) ) {
			return $caps;
		}

		$post_id = absint( $args[0] ?? 0 );
		$parent  = $post_id ? wp_is_post_revision( $post_id ) : false;
		if ( $parent ) {
			$post_id = (int) $parent;
		}
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || 'elementor_library' !== $post->post_type || ! self::is_sitewide_elementor_template( $post_id ) ) {
			return $caps;
		}

		$caps[] = 'edit_theme_options';
		return array_values( array_unique( $caps ) );
	}

	/**
	 * Whether an Elementor library row can affect sitewide visitor output.
	 *
	 * Conditions and popup settings remain protection signals even if a damaged
	 * or migrated row has lost its normal document type marker.
	 *
	 * @param int $post_id Elementor library post id.
	 * @return bool
	 */
	public static function is_sitewide_elementor_template( int $post_id ): bool {
		$type = sanitize_key( (string) get_post_meta( $post_id, '_elementor_template_type', true ) );
		if ( in_array( $type, self::SITEWIDE_ELEMENTOR_TYPES, true ) ) {
			return true;
		}

		return ! empty( get_post_meta( $post_id, '_elementor_conditions', true ) )
			|| ! empty( get_post_meta( $post_id, '_elementor_popup_triggers', true ) )
			|| ! empty( get_post_meta( $post_id, '_elementor_popup_timing', true ) );
	}

	/**
	 * Authorize creation for a concrete post type and requested status.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $status    Requested post status.
	 * @return true|WP_Error
	 */
	public static function authorize_create( string $post_type, string $status = 'draft' ) {
		$object = get_post_type_object( $post_type );
		if ( ! $object || empty( $object->cap ) ) {
			return new WP_Error( 'invalid_post_type', __( 'That post type is not registered.', 'emcp-tools' ) );
		}

		$create_cap = (string) ( $object->cap->create_posts ?? $object->cap->edit_posts ?? '' );
		if ( '' === $create_cap || ! current_user_can( $create_cap ) ) {
			return new WP_Error( 'cannot_create', __( 'You do not have permission to create that post type.', 'emcp-tools' ) );
		}

		return self::authorize_status( $post_type, $status );
	}

	/**
	 * Authorize a requested status through the post type's publish capability.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $status    Requested post status.
	 * @return true|WP_Error
	 */
	public static function authorize_status( string $post_type, string $status ) {
		if ( ! in_array( $status, self::PUBLISHED_STATUSES, true ) ) {
			return true;
		}

		$object      = get_post_type_object( $post_type );
		$publish_cap = $object && ! empty( $object->cap ) ? (string) ( $object->cap->publish_posts ?? '' ) : '';
		if ( '' === $publish_cap || ! current_user_can( $publish_cap ) ) {
			return new WP_Error( 'cannot_publish', __( 'You do not have permission to publish that post type.', 'emcp-tools' ) );
		}
		return true;
	}

	/**
	 * Authorize assigning a post to another user.
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $author_id Target author id.
	 * @return true|WP_Error
	 */
	public static function authorize_author( string $post_type, int $author_id ) {
		if ( $author_id <= 0 || $author_id === get_current_user_id() ) {
			return true;
		}

		$object = get_post_type_object( $post_type );
		$cap    = $object && ! empty( $object->cap ) ? (string) ( $object->cap->edit_others_posts ?? '' ) : '';
		if ( '' === $cap || ! current_user_can( $cap ) ) {
			return new WP_Error( 'cannot_set_author', __( 'You cannot assign another author for that post type.', 'emcp-tools' ) );
		}
		return true;
	}
}
