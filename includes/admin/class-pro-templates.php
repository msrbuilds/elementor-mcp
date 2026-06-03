<?php
/**
 * Fetches and caches the premium templates library from emcp.msrbuilds.com.
 *
 * Mirror of class-pro-prompts.php for templates. Same auth flow, different
 * endpoint and bundle shape. Each template's `data` field is an Elementor
 * element tree that can be imported into a new or existing page.
 *
 * @package EMCP_Tools
 * @since   1.7.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Premium templates service.
 *
 * @since 1.7.1
 */
class EMCP_Tools_Pro_Templates {

	/**
	 * Transient key for the cached bundle.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'emcp_tools_pro_templates_bundle';

	/**
	 * Transient TTL in seconds. 24 hours.
	 *
	 * @var int
	 */
	const CACHE_TTL = 86400;

	/**
	 * Default endpoint. Filterable via `emcp_tools_pro_templates_endpoint`.
	 *
	 * @var string
	 */
	const DEFAULT_ENDPOINT = 'https://emcp.msrbuilds.com/api/emcp/templates.json';

	/**
	 * Whether the current site can access premium templates.
	 *
	 * @since 1.7.1
	 *
	 * @return bool
	 */
	public static function user_has_access(): bool {
		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return false;
		}
		return emcp_tools_fs()->can_use_premium_code();
	}

	/**
	 * Get the templates bundle. Returns cached copy when available.
	 *
	 * Bundle shape:
	 *   [
	 *     'fetched_at' => int,
	 *     'categories' => [
	 *       [
	 *         'slug'      => 'hero-sections',
	 *         'label'     => 'Hero Sections',
	 *         'templates' => [
	 *           [
	 *             'slug'          => 'bakery-hero',
	 *             'title'         => 'Bakery Hero',
	 *             'description'   => '...',
	 *             'thumbnail_url' => 'https://...' (optional),
	 *             'data'          => [ ... Elementor elements ... ],
	 *           ],
	 *         ],
	 *       ],
	 *     ],
	 *   ]
	 *
	 * @since 1.7.1
	 *
	 * @param bool $force_refresh Bypass the local cache.
	 * @return array|WP_Error
	 */
	public static function get_bundle( bool $force_refresh = false ) {
		if ( ! self::user_has_access() ) {
			return new WP_Error( 'no_license', __( 'A valid EMCP Tools Pro license is required to access premium templates.', 'emcp-tools' ) );
		}

		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		return self::fetch_remote_bundle();
	}

	/**
	 * Find a specific template by its category and slug. Returns the template
	 * entry (with the `data` array) or null if not found.
	 *
	 * @since 1.7.1
	 *
	 * @param string $category_slug
	 * @param string $template_slug
	 * @return array|null
	 */
	public static function find_template( string $category_slug, string $template_slug ): ?array {
		$bundle = self::get_bundle();
		if ( is_wp_error( $bundle ) || ! is_array( $bundle ) ) {
			return null;
		}
		foreach ( $bundle['categories'] ?? array() as $category ) {
			if ( ( $category['slug'] ?? '' ) !== $category_slug ) {
				continue;
			}
			foreach ( $category['templates'] ?? array() as $template ) {
				if ( ( $template['slug'] ?? '' ) === $template_slug ) {
					return $template;
				}
			}
		}
		return null;
	}

	/**
	 * Hit the remote endpoint, validate the response, store it in cache.
	 *
	 * @since 1.7.1
	 *
	 * @return array|WP_Error
	 */
	private static function fetch_remote_bundle() {
		$license_key = self::get_license_key();
		$license_id  = self::get_license_id();
		if ( '' === $license_key || '' === $license_id ) {
			return new WP_Error( 'no_license_key', __( 'No active EMCP Tools Pro license was found on this site.', 'emcp-tools' ) );
		}

		$endpoint = apply_filters( 'emcp_tools_pro_templates_endpoint', self::DEFAULT_ENDPOINT );

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 12,
				'headers' => array(
					'Accept'                => 'application/json',
					'Authorization'         => 'Bearer ' . $license_key,
					'X-EMCP-License-Id'     => $license_id,
					'X-EMCP-Site'           => home_url(),
					'X-EMCP-Plugin-Version' => EMCP_TOOLS_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 403 === $code ) {
			return new WP_Error(
				'forbidden',
				__( 'Premium Templates are unavailable on this site. Make sure your EMCP Tools Pro license is active and this site is on its activated-sites list. Contact support if the issue persists.', 'emcp-tools' )
			);
		}

		if ( 429 === $code ) {
			return new WP_Error(
				'rate_limited',
				__( 'Premium Templates endpoint is rate-limiting this site. Try again in a few minutes.', 'emcp-tools' )
			);
		}

		if ( 200 !== $code ) {
			return new WP_Error(
				'remote_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Templates endpoint returned HTTP %d. Please try again later or contact support.', 'emcp-tools' ),
					$code
				)
			);
		}

		$body   = wp_remote_retrieve_body( $response );
		$bundle = json_decode( $body, true );

		if ( ! is_array( $bundle ) || ! isset( $bundle['categories'] ) || ! is_array( $bundle['categories'] ) ) {
			return new WP_Error( 'invalid_payload', __( 'Templates endpoint returned an unexpected payload.', 'emcp-tools' ) );
		}

		$bundle['fetched_at'] = time();
		set_transient( self::CACHE_KEY, $bundle, self::CACHE_TTL );

		return $bundle;
	}

	/**
	 * Returns the active license key from Freemius, or empty string.
	 *
	 * @since 1.7.1
	 *
	 * @return string
	 */
	private static function get_license_key(): string {
		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return '';
		}
		$license = emcp_tools_fs()->_get_license();
		if ( ! $license || empty( $license->secret_key ) ) {
			return '';
		}
		return (string) $license->secret_key;
	}

	/**
	 * Returns the active license's numeric Freemius ID, or empty string.
	 *
	 * @since 1.7.1
	 *
	 * @return string
	 */
	private static function get_license_id(): string {
		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return '';
		}
		$license = emcp_tools_fs()->_get_license();
		if ( ! $license || empty( $license->id ) ) {
			return '';
		}
		return (string) $license->id;
	}

	/**
	 * Clear the cached bundle.
	 *
	 * @since 1.7.1
	 */
	public static function flush_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Apply a template to a target page (existing or new).
	 *
	 * Creates a new Elementor-enabled page if $target_post_id is 0, otherwise
	 * replaces the target page's Elementor data with the template's data.
	 *
	 * @since 1.7.1
	 *
	 * @param string $category_slug
	 * @param string $template_slug
	 * @param int    $target_post_id  0 to create a new page; otherwise an existing post ID.
	 * @return array|WP_Error { post_id, edit_url, view_url, action: 'created'|'replaced' }
	 */
	public static function apply_template( string $category_slug, string $template_slug, int $target_post_id ) {
		$template = self::find_template( $category_slug, $template_slug );
		if ( ! $template || empty( $template['data'] ) || ! is_array( $template['data'] ) ) {
			return new WP_Error( 'template_not_found', __( 'Template not found in the cached bundle. Try clicking Sync Library first.', 'emcp-tools' ) );
		}

		$elementor_data = $template['data'];

		if ( $target_post_id > 0 ) {
			$post = get_post( $target_post_id );
			if ( ! $post ) {
				return new WP_Error( 'post_not_found', __( 'The target page no longer exists.', 'emcp-tools' ) );
			}
			if ( ! current_user_can( 'edit_post', $target_post_id ) ) {
				return new WP_Error( 'forbidden', __( 'You do not have permission to edit that page.', 'emcp-tools' ) );
			}
			$post_id = $target_post_id;
			$action  = 'replaced';
		} else {
			if ( ! current_user_can( 'edit_pages' ) ) {
				return new WP_Error( 'forbidden', __( 'You do not have permission to create pages.', 'emcp-tools' ) );
			}
			$post_id = wp_insert_post(
				array(
					'post_title'  => isset( $template['title'] ) ? (string) $template['title'] : __( 'Untitled Template', 'emcp-tools' ),
					'post_type'   => 'page',
					'post_status' => 'draft',
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}
			$action = 'created';
		}

		// Persist the Elementor element tree. wp_slash() so backslashes inside
		// the template content (rare but possible) don't get stripped by
		// update_post_meta().
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );

		// Page-level settings (background, padding, hide_title, etc.) when the
		// template ships them — common in Elementor's native exports.
		if ( ! empty( $template['page_settings'] ) && is_array( $template['page_settings'] ) ) {
			update_post_meta( $post_id, '_elementor_page_settings', $template['page_settings'] );
		}

		// Trigger Elementor's CSS regeneration if available.
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		$edit_url = admin_url( 'post.php?post=' . $post_id . '&action=elementor' );
		$view_url = get_permalink( $post_id );

		return array(
			'post_id'  => $post_id,
			'edit_url' => $edit_url,
			'view_url' => $view_url ? $view_url : '',
			'action'   => $action,
		);
	}

	/**
	 * Import a template into Elementor's Saved Templates library.
	 *
	 * Creates an `elementor_library` CPT post — the same post type Elementor
	 * itself uses for templates saved via the editor's "Save as Template"
	 * action. After import, the template is visible at
	 * Elementor → Templates → Saved Templates and insertable from the
	 * editor's "Add Template" picker on any page.
	 *
	 * @since 1.7.1
	 *
	 * @param string $category_slug
	 * @param string $template_slug
	 * @return array|WP_Error { template_id, edit_url, library_url }
	 */
	public static function import_to_library( string $category_slug, string $template_slug ) {
		$template = self::find_template( $category_slug, $template_slug );
		if ( ! $template || empty( $template['data'] ) || ! is_array( $template['data'] ) ) {
			return new WP_Error( 'template_not_found', __( 'Template not found in the cached bundle. Try clicking Sync Library first.', 'emcp-tools' ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to import templates.', 'emcp-tools' ) );
		}

		if ( ! post_type_exists( 'elementor_library' ) ) {
			return new WP_Error( 'no_library', __( 'Elementor template library post type is not available. Make sure Elementor is active.', 'emcp-tools' ) );
		}

		$title = isset( $template['title'] ) ? (string) $template['title'] : __( 'Untitled Template', 'emcp-tools' );

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => 'elementor_library',
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Tell Elementor this is a page-type template (full page layout).
		// Other valid types: 'section', 'widget'. Theme-builder types
		// require Elementor Pro.
		wp_set_object_terms( $post_id, 'page', 'elementor_library_type' );

		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $template['data'] ) ) );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_template_type', 'page' );

		if ( ! empty( $template['page_settings'] ) && is_array( $template['page_settings'] ) ) {
			update_post_meta( $post_id, '_elementor_page_settings', $template['page_settings'] );
		}

		// Trigger Elementor's CSS regeneration if available.
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		return array(
			'template_id' => $post_id,
			'edit_url'    => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
			'library_url' => admin_url( 'edit.php?post_type=elementor_library' ),
		);
	}
}
