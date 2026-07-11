<?php
/**
 * Content mirror — export Elementor page/template content to git-trackable JSON
 * files under uploads/, so an external VCS (the user's git/CI) can version and
 * diff page designs. The plugin never runs git itself.
 *
 * Complements AI-safe transactions: transactions are an in-DB recent-change
 * ledger + rollback; the mirror is durable, diffable, file-based history.
 *
 * @package EMCP_Tools
 * @since   3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes/reads/restores the content mirror.
 *
 * @since 3.3.0
 */
class EMCP_Tools_Content_Mirror {

	const OPTION_ENABLED = 'emcp_tools_content_mirror_enabled';
	const MIRROR_DIR     = 'emcp-content-mirror';

	/**
	 * Whether the on-save auto-export is enabled.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return '1' === (string) get_option( self::OPTION_ENABLED, '' );
	}

	/**
	 * Wire the on-save / on-delete hooks.
	 */
	public static function init(): void {
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 40, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete_post' ), 10, 1 );
	}

	/**
	 * The mirror base directory (absolute).
	 *
	 * @return string
	 */
	public static function dir(): string {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . self::MIRROR_DIR;
	}

	/**
	 * Pure: build the export payload for a post.
	 *
	 * @param array $post     { id, type, slug, title }.
	 * @param array $elements Elementor elements array.
	 * @return array
	 */
	public static function build_export( array $post, array $elements ): array {
		return array(
			'id'             => (int) ( $post['id'] ?? 0 ),
			'type'           => (string) ( $post['type'] ?? '' ),
			'slug'           => (string) ( $post['slug'] ?? '' ),
			'title'          => (string) ( $post['title'] ?? '' ),
			'elementor_data' => $elements,
			'exported_at'    => time(),
		);
	}

	/**
	 * Pure: a deterministic mirror filename.
	 *
	 * @param string $type Object type.
	 * @param int    $id   Post ID.
	 * @param string $slug Human slug (sanitized).
	 * @return string
	 */
	public static function file_name( string $type, int $id, string $slug ): string {
		$slug = strtolower( (string) preg_replace( '/[^A-Za-z0-9]+/', '-', $slug ) );
		$slug = trim( $slug, '-' );
		$base = $type . '-' . $id . ( '' !== $slug ? '-' . $slug : '' );
		return $base . '.json';
	}

	/**
	 * Export one post to the mirror.
	 *
	 * @param int $post_id Post ID.
	 * @return string|WP_Error Absolute file path, or error.
	 */
	public static function export_post( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'not_found', __( 'Post not found.', 'emcp-tools' ) );
		}
		$type = ( 'elementor_library' === $post->post_type ) ? 'template' : 'page';

		$elements = array();
		if ( class_exists( 'EMCP_Tools_Data' ) ) {
			try {
				$data = new EMCP_Tools_Data();
				$e    = $data->get_page_data( $post_id );
				if ( is_array( $e ) ) {
					$elements = $e;
				}
			} catch ( \Throwable $ex ) {
				$elements = array();
			}
		}

		$payload = self::build_export(
			array(
				'id'    => $post_id,
				'type'  => $type,
				'slug'  => $post->post_name,
				'title' => get_the_title( $post_id ),
			),
			$elements
		);

		$dir = self::dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'mkdir_failed', __( 'Could not create the mirror directory.', 'emcp-tools' ) );
		}
		if ( ! is_file( $dir . '/.gitignore' ) ) {
			// Do NOT ignore json — this dir is meant to be committed. Keep a README hint.
			@file_put_contents( $dir . '/README.txt', "EMCP content mirror — commit the .json files to version your page designs.\n" ); // phpcs:ignore
		}

		$path  = $dir . '/' . self::file_name( $type, $post_id, (string) $post->post_name );
		$bytes = file_put_contents( $path, wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		if ( false === $bytes ) {
			return new WP_Error( 'write_failed', __( 'Could not write the mirror file.', 'emcp-tools' ) );
		}
		return $path;
	}

	/**
	 * Restore a post's Elementor content from its mirror file.
	 *
	 * @param int $post_id Post ID.
	 * @return true|WP_Error
	 */
	public static function restore_post( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'not_found', __( 'Post not found.', 'emcp-tools' ) );
		}
		$type = ( 'elementor_library' === $post->post_type ) ? 'template' : 'page';
		$path = self::dir() . '/' . self::file_name( $type, $post_id, (string) $post->post_name );
		if ( ! is_file( $path ) ) {
			return new WP_Error( 'no_export', __( 'No mirror file exists for this post.', 'emcp-tools' ) );
		}
		$decoded = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['elementor_data'] ) || ! is_array( $decoded['elementor_data'] ) ) {
			return new WP_Error( 'bad_export', __( 'The mirror file is invalid.', 'emcp-tools' ) );
		}
		if ( ! class_exists( 'EMCP_Tools_Data' ) ) {
			return new WP_Error( 'no_data_layer', __( 'The Elementor data layer is unavailable.', 'emcp-tools' ) );
		}
		$data = new EMCP_Tools_Data();
		$res  = $data->save_page_data( $post_id, $decoded['elementor_data'] );
		return is_wp_error( $res ) ? $res : true;
	}

	/**
	 * Export all Elementor pages + templates.
	 *
	 * @return array{pages:int,templates:int}
	 */
	public static function export_all(): array {
		$counts = array( 'pages' => 0, 'templates' => 0 );

		$pages = new WP_Query( array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_elementor_edit_mode', 'value' => 'builder' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		foreach ( (array) $pages->posts as $id ) {
			if ( ! is_wp_error( self::export_post( (int) $id ) ) ) {
				++$counts['pages'];
			}
		}

		$templates = new WP_Query( array(
			'post_type'      => 'elementor_library',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
		foreach ( (array) $templates->posts as $id ) {
			if ( ! is_wp_error( self::export_post( (int) $id ) ) ) {
				++$counts['templates'];
			}
		}

		return $counts;
	}

	/**
	 * save_post handler: auto-export when enabled.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post.
	 */
	public static function on_save_post( $post_id, $post = null ): void {
		if ( ! self::enabled() ) {
			return;
		}
		$post_id = (int) $post_id;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
			return;
		}
		$ptype = $post && isset( $post->post_type ) ? $post->post_type : ( function_exists( 'get_post_type' ) ? get_post_type( $post_id ) : '' );
		if ( 'elementor_library' === $ptype ) {
			self::export_post( $post_id );
		} elseif ( in_array( $ptype, array( 'page', 'post' ), true ) && 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			self::export_post( $post_id );
		}
	}

	/**
	 * before_delete_post handler: remove the mirror file.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_delete_post( $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$type = ( 'elementor_library' === $post->post_type ) ? 'template' : 'page';
		$path = self::dir() . '/' . self::file_name( $type, (int) $post_id, (string) $post->post_name );
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
