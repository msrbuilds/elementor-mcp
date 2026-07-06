<?php
/**
 * EMCP Themer PHP Template Store — source of truth + sandbox.
 *
 * A private emcp_theme_php CPT holds each template's raw PHP, region type, and
 * validation report. Modeled on EMCP_Tools_PHP_Snippet_Store but region-oriented:
 * the executable file is compiled ONLY while a Themer post references the template
 * (see class-themer-php.php + the metabox). A draft has no runnable file on disk.
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
class EMCP_Tools_Themer_PHP_Store {

	const POST_TYPE       = 'emcp_theme_php';
	const META_CODE       = '_emcp_theme_php_code';
	const META_TYPE       = '_emcp_theme_php_type';
	const META_VALIDATION = '_emcp_theme_php_validation';
	const META_HASH       = '_emcp_theme_php_hash';
	const META_ERROR      = '_emcp_theme_php_error';

	/** Region types a template may target. `any` = attachable to any slot type. */
	const TYPES = array( 'header', 'footer', 'single', 'archive', 'any' );

	/** Reuses the shared snippet sandbox base; own subdir. */
	const SUBDIR = 'theme-php';

	// -------------------------------------------------------------------------
	// CPT + permissions
	// -------------------------------------------------------------------------

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
				'labels'              => array( 'name' => __( 'EMCP Theme PHP Templates', 'emcp-tools' ) ),
			)
		);
	}

	public static function can_edit(): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
	}

	public static function can_read(): bool {
		return current_user_can( 'manage_options' );
	}

	private static function sanitize_type( $type ): string {
		$type = is_string( $type ) ? $type : '';
		return in_array( $type, self::TYPES, true ) ? $type : 'any';
	}

	// -------------------------------------------------------------------------
	// Sandbox paths
	// -------------------------------------------------------------------------

	public static function dir(): string {
		return EMCP_Tools_PHP_Snippet_Store::sandbox_dir() . '/' . self::SUBDIR;
	}

	public static function relative_php_path( int $id ): string {
		return self::SUBDIR . '/' . $id . '.php';
	}

	public static function php_path( int $id ): string {
		return EMCP_Tools_PHP_Snippet_Store::sandbox_dir() . '/' . self::relative_php_path( $id );
	}

	public static function manifest_path(): string {
		return EMCP_Tools_PHP_Snippet_Store::sandbox_dir() . '/theme-php-manifest.json';
	}

	public static function func_name( int $id ): string {
		return 'emcp_theme_php_' . $id;
	}

	// -------------------------------------------------------------------------
	// CRUD
	// -------------------------------------------------------------------------

	/**
	 * Create a DRAFT template. Validates; refuses parse errors + CRITICAL findings.
	 *
	 * @param array $args { title, code, type }.
	 * @return array|WP_Error Summary+code on success.
	 */
	public static function create_draft( array $args ) {
		if ( ! self::can_edit() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to create PHP templates.', 'emcp-tools' ) );
		}
		$code       = isset( $args['code'] ) ? (string) $args['code'] : '';
		$validation = EMCP_Tools_PHP_Snippet_Validator::validate( $code );
		if ( ! $validation['valid'] ) {
			return new WP_Error( 'invalid_php', sprintf( /* translators: %s: parse error */ __( 'The template is not valid PHP: %s', 'emcp-tools' ), $validation['parse_error'] ), array( 'validation' => $validation ) );
		}
		if ( ! $validation['safe'] ) {
			return new WP_Error( 'unsafe_php', __( 'The template was blocked by the security validator (critical finding).', 'emcp-tools' ), array( 'validation' => $validation ) );
		}

		$title = isset( $args['title'] ) && '' !== trim( (string) $args['title'] )
			? sanitize_text_field( (string) $args['title'] )
			: __( 'PHP Template', 'emcp-tools' );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $title,
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( (int) $post_id, self::META_CODE, wp_slash( $code ) );
		update_post_meta( (int) $post_id, self::META_TYPE, self::sanitize_type( $args['type'] ?? 'any' ) );
		update_post_meta( (int) $post_id, self::META_VALIDATION, wp_slash( (string) wp_json_encode( $validation ) ) );

		return self::get( (int) $post_id );
	}

	/**
	 * Update a template's title/code/type; re-validates. If currently compiled,
	 * recompiles from the new code.
	 *
	 * @param int   $id   Template id.
	 * @param array $args Partial { title, code, type }.
	 * @return array|WP_Error
	 */
	public static function update( int $id, array $args ) {
		if ( ! self::can_edit() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to update PHP templates.', 'emcp-tools' ) );
		}
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Template not found.', 'emcp-tools' ) );
		}

		$code       = array_key_exists( 'code', $args ) ? (string) $args['code'] : (string) get_post_meta( $id, self::META_CODE, true );
		$validation = EMCP_Tools_PHP_Snippet_Validator::validate( $code );
		if ( ! $validation['valid'] ) {
			return new WP_Error( 'invalid_php', sprintf( /* translators: %s: parse error */ __( 'The template is not valid PHP: %s', 'emcp-tools' ), $validation['parse_error'] ), array( 'validation' => $validation ) );
		}
		if ( ! $validation['safe'] ) {
			return new WP_Error( 'unsafe_php', __( 'The template was blocked by the security validator (critical finding).', 'emcp-tools' ), array( 'validation' => $validation ) );
		}

		if ( isset( $args['title'] ) && '' !== trim( (string) $args['title'] ) ) {
			wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( (string) $args['title'] ) ) );
		}
		if ( array_key_exists( 'code', $args ) ) {
			update_post_meta( $id, self::META_CODE, wp_slash( $code ) );
		}
		if ( array_key_exists( 'type', $args ) ) {
			update_post_meta( $id, self::META_TYPE, self::sanitize_type( $args['type'] ) );
		}
		update_post_meta( $id, self::META_VALIDATION, wp_slash( (string) wp_json_encode( $validation ) ) );
		delete_post_meta( $id, self::META_ERROR );

		// Recompile in place if this template is currently compiled.
		if ( '' !== (string) get_post_meta( $id, self::META_HASH, true ) ) {
			self::ensure_compiled( $id );
		}

		return self::get( $id );
	}

	// -------------------------------------------------------------------------
	// Read
	// -------------------------------------------------------------------------

	/**
	 * Full record (summary + code + validation).
	 *
	 * @param int $id Template id.
	 * @return array|WP_Error
	 */
	public static function get( int $id ) {
		$summary = self::summary( $id );
		if ( is_wp_error( $summary ) ) {
			return $summary;
		}
		$summary['code']       = (string) get_post_meta( $id, self::META_CODE, true );
		$summary['validation'] = self::get_validation( $id );
		return $summary;
	}

	/**
	 * Compact summary (no code body).
	 *
	 * @param int $id Template id.
	 * @return array|WP_Error
	 */
	public static function summary( int $id ) {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Template not found.', 'emcp-tools' ) );
		}
		$compiled = '' !== (string) get_post_meta( $id, self::META_HASH, true );
		return array(
			'template_id' => (int) $id,
			'title'       => (string) $post->post_title,
			'type'        => (string) ( get_post_meta( $id, self::META_TYPE, true ) ?: 'any' ),
			'status'      => 'draft',
			'compiled'    => $compiled,
			'last_error'  => (string) get_post_meta( $id, self::META_ERROR, true ),
			'updated'     => (string) $post->post_modified,
		);
	}

	public static function get_validation( int $id ): array {
		$raw = get_post_meta( $id, self::META_VALIDATION, true );
		if ( empty( $raw ) ) {
			return array( 'valid' => true, 'safe' => true, 'parse_error' => '', 'findings' => array() );
		}
		$data = json_decode( (string) $raw, true );
		return is_array( $data ) ? $data : array( 'valid' => true, 'safe' => true, 'parse_error' => '', 'findings' => array() );
	}

	/**
	 * List templates, optionally filtered to a type (plus `any`).
	 *
	 * @param string $type '' | header | footer | single | archive | any.
	 * @return array<int,array>
	 */
	public static function list_templates( string $type = '' ): array {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'draft',
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $query->posts as $post ) {
			$summary = self::summary( (int) $post->ID );
			if ( is_wp_error( $summary ) ) {
				continue;
			}
			if ( '' !== $type && $summary['type'] !== $type && 'any' !== $summary['type'] ) {
				continue;
			}
			$out[] = $summary;
		}
		return $out;
	}

	/**
	 * Delete a template: decompile + remove the CPT post.
	 *
	 * @param int $id Template id.
	 * @return array|WP_Error
	 */
	public static function delete( int $id ) {
		if ( ! self::can_edit() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to delete PHP templates.', 'emcp-tools' ) );
		}
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Template not found.', 'emcp-tools' ) );
		}
		self::decompile( $id );
		wp_delete_post( $id, true );
		return array( 'success' => true, 'template_id' => $id );
	}

	// -------------------------------------------------------------------------
	// Compile / manifest (added in Task 3)
	// -------------------------------------------------------------------------
}
