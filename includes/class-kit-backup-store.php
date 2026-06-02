<?php
/**
 * Kit Backup Store — pre-apply snapshots of the active Elementor kit's globals.
 *
 * Backups are stored in a private `emcp_kit_backup` custom post type, NOT the
 * Media Library: `application/json` is not an allowed upload MIME in WordPress
 * core, and filtering `upload_mimes` to permit it would open a site-wide upload
 * hole. A CPT is self-owned, never web-addressable, listable via WP_Query, and
 * carries no third-party surface. See docs/BRAND_KITS_PLAN.md §§ 5.4, 5.5.
 *
 * @package Elementor_MCP
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and retrieves brand-kit backups.
 *
 * @since 1.8.0
 */
class Elementor_MCP_Kit_Backup_Store {

	/**
	 * Custom post type name.
	 *
	 * @var string
	 */
	const POST_TYPE = 'emcp_kit_backup';

	/**
	 * Meta key holding the JSON-encoded snapshot blob.
	 *
	 * @var string
	 */
	const META_SNAPSHOT = '_emcp_kit_snapshot';

	/**
	 * Register the CPT. Hooked on `init`.
	 *
	 * @since 1.8.0
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'author' ),
				'labels'              => array(
					'name' => __( 'EMCP Brand Kit Backups', 'elementor-mcp' ),
				),
			)
		);
	}

	/**
	 * Whether the current user may manage brand backups. As of 1.9.0 backup +
	 * restore ships with the free brand-kit apply feature, so this is a
	 * capability gate (`manage_options`), not a license gate.
	 *
	 * @since 1.8.0
	 *
	 * @return bool
	 */
	public static function user_has_access(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Capture the current kit globals and store them as a new backup post.
	 *
	 * @since 1.8.0
	 *
	 * @param string $label A human label for the backup (e.g. the kit being applied).
	 * @return int|WP_Error The new backup post ID, or WP_Error.
	 */
	public static function create( string $label = '' ) {
		if ( ! self::user_has_access() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to create brand kit backups.', 'elementor-mcp' ) );
		}

		if ( ! class_exists( 'Elementor_MCP_System_Kit_Writer' ) ) {
			return new WP_Error( 'no_writer', __( 'The kit writer service is unavailable.', 'elementor-mcp' ) );
		}

		$snapshot = Elementor_MCP_System_Kit_Writer::snapshot();
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}

		// Collision-proof title: full date + time, so multiple applies in one
		// day never overwrite each other.
		$stamp = current_time( 'Y-m-d H:i:s' );
		$title = '' !== $label
			/* translators: 1: brand kit label, 2: date/time */
			? sprintf( __( 'Before "%1$s" — %2$s', 'elementor-mcp' ), $label, $stamp )
			/* translators: %s: date/time */
			: sprintf( __( 'Backup — %s', 'elementor-mcp' ), $stamp );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::META_SNAPSHOT, wp_slash( wp_json_encode( $snapshot ) ) );

		return $post_id;
	}

	/**
	 * List stored backups, newest first.
	 *
	 * @since 1.8.0
	 *
	 * @param int $limit Max number of backups to return.
	 * @return array<int, array{id: int, title: string, created: string}>
	 */
	public static function list_backups( int $limit = 25 ): array {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, $limit ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$out = array();
		foreach ( $query->posts as $post ) {
			$out[] = array(
				'id'      => (int) $post->ID,
				'title'   => (string) $post->post_title,
				'created' => (string) $post->post_date,
			);
		}

		return $out;
	}

	/**
	 * Read a backup's snapshot blob.
	 *
	 * @since 1.8.0
	 *
	 * @param int $backup_id The backup post ID.
	 * @return array|WP_Error The decoded snapshot, or WP_Error.
	 */
	public static function get_snapshot( int $backup_id ) {
		$post = get_post( $backup_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Backup not found.', 'elementor-mcp' ) );
		}

		$raw = get_post_meta( $backup_id, self::META_SNAPSHOT, true );
		if ( empty( $raw ) ) {
			return new WP_Error( 'empty_backup', __( 'That backup is empty or corrupted.', 'elementor-mcp' ) );
		}

		$snapshot = json_decode( $raw, true );
		if ( ! is_array( $snapshot ) ) {
			return new WP_Error( 'invalid_backup', __( 'That backup could not be decoded.', 'elementor-mcp' ) );
		}

		return $snapshot;
	}

	/**
	 * Restore a backup onto the active kit.
	 *
	 * @since 1.8.0
	 *
	 * @param int  $backup_id    The backup post ID.
	 * @param bool $full_clobber Whether to fully clobber custom colors/typography.
	 * @return array|WP_Error
	 */
	public static function restore( int $backup_id, bool $full_clobber = false ) {
		if ( ! self::user_has_access() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to restore brand kit backups.', 'elementor-mcp' ) );
		}

		$snapshot = self::get_snapshot( $backup_id );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}

		if ( ! class_exists( 'Elementor_MCP_System_Kit_Writer' ) ) {
			return new WP_Error( 'no_writer', __( 'The kit writer service is unavailable.', 'elementor-mcp' ) );
		}

		return Elementor_MCP_System_Kit_Writer::restore_snapshot( $snapshot, $full_clobber );
	}
}
