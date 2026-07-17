<?php
/**
 * Stock image MCP abilities for Elementor.
 *
 * Registers 3 tools for searching, sideloading, and adding stock images
 * from the configured stock provider (Unsplash, Pexels, or Pixabay).
 *
 * @package EMCP_Tools
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the stock image abilities.
 *
 * @since 1.1.0
 */
class EMCP_Tools_Stock_Image_Abilities {

	/**
	 * @var EMCP_Tools_Data
	 */
	private $data;

	/**
	 * @var EMCP_Tools_Element_Factory
	 */
	private $factory;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param EMCP_Tools_Data            $data    The data access layer.
	 * @param EMCP_Tools_Element_Factory $factory The element factory.
	 */
	public function __construct( EMCP_Tools_Data $data, EMCP_Tools_Element_Factory $factory ) {
		$this->data    = $data;
		$this->factory = $factory;
	}

	/**
	 * Returns the ability names registered by this class.
	 *
	 * @since 1.1.0
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'emcp-tools/search-images',
			'emcp-tools/sideload-image',
			'emcp-tools/add-stock-image',
		);
	}

	/**
	 * Registers all stock image abilities.
	 *
	 * @since 1.1.0
	 */
	public function register(): void {
		$this->register_provider_tools();
		$this->register_add_stock_image();
	}

	/**
	 * The provider tools — search-images + sideload-image. These are pure WP core
	 * (a stock-provider search + a Media Library sideload) with no Elementor
	 * dependency, so they register regardless of whether Elementor is active.
	 *
	 * @since 3.4.0
	 */
	public function register_provider_tools(): void {
		$this->register_search_images();
		$this->register_sideload_image();
	}

	/**
	 * Registers add-stock-image (search + sideload + add an image widget to the
	 * page). This one needs a page builder, so the registrar calls it only when
	 * Elementor is active.
	 *
	 * @since 3.4.0
	 */
	public function register_widget_tool(): void {
		$this->register_add_stock_image();
	}

	/**
	 * Ability names for the always-on provider tools.
	 *
	 * @since 3.4.0
	 *
	 * @return string[]
	 */
	public function provider_tool_names(): array {
		return array( 'emcp-tools/search-images', 'emcp-tools/sideload-image' );
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Permission check for read-only search.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function check_read_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission check for uploading/sideloading images.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function check_upload_permission(): bool {
		return current_user_can( 'upload_files' );
	}

	/**
	 * Permission check for combined search + sideload + add to page.
	 *
	 * @since 1.1.0
	 *
	 * @param array|null $input The input data.
	 * @return bool
	 */
	public function check_combined_permission( $input = null ): bool {
		if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'upload_files' ) ) {
			return false;
		}

		$post_id = absint( $input['post_id'] ?? 0 );
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// search-images
	// -------------------------------------------------------------------------

	private function register_search_images(): void {
		emcp_tools_register_ability(
			'emcp-tools/search-images',
			array(
				'label'               => __( 'Search Images', 'emcp-tools' ),
				'description'         => __( 'Searches a stock-photo provider (Unsplash, Pexels, or Pixabay) for high-quality images. Returns image URLs, thumbnails, dimensions, and attribution. Use the returned URLs with sideload-image or add-stock-image. Requires a free API key for at least one provider (set on EMCP Tools → Connection).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_search_images' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'query'        => array(
							'type'        => 'string',
							'description' => __( 'Search keywords (e.g. "mountain landscape", "modern office").', 'emcp-tools' ),
						),
						'provider'     => array(
							'type'        => 'string',
							'enum'        => array( 'unsplash', 'pexels', 'pixabay' ),
							'description' => __( 'Which stock provider to search. Omit to use the first one you have connected.', 'emcp-tools' ),
						),
						'page'         => array(
							'type'        => 'integer',
							'description' => __( 'Page number. Default: 1.', 'emcp-tools' ),
						),
						'page_size'    => array(
							'type'        => 'integer',
							'description' => __( 'Results per page. Default: 5.', 'emcp-tools' ),
						),
						'aspect_ratio' => array(
							'type'        => 'string',
							'enum'        => array( 'tall', 'wide', 'square' ),
							'description' => __( 'Filter by orientation. Use "wide" (landscape) for hero banners, cards, and most page layouts. Use "tall" (portrait) for sidebar images. Use "square" for avatars and thumbnails.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'query' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'provider'     => array( 'type' => 'string' ),
						'result_count' => array( 'type' => 'integer' ),
						'page'         => array( 'type' => 'integer' ),
						'page_count'   => array( 'type' => 'integer' ),
						'results'      => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'                  => array( 'type' => 'string' ),
									'title'               => array( 'type' => 'string' ),
									'url'                 => array( 'type' => 'string' ),
									'thumbnail'           => array( 'type' => 'string' ),
									'width'               => array( 'type' => 'integer' ),
									'height'              => array( 'type' => 'integer' ),
									'creator'             => array( 'type' => 'string' ),
									'creator_url'         => array( 'type' => 'string' ),
									'license'             => array( 'type' => 'string' ),
									'license_url'         => array( 'type' => 'string' ),
									'attribution'         => array( 'type' => 'string' ),
									'source'              => array( 'type' => 'string' ),
									'foreign_landing_url' => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the search-images ability.
	 *
	 * @since 1.1.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_search_images( $input ) {
		$query = sanitize_text_field( $input['query'] ?? '' );
		if ( empty( $query ) ) {
			return new \WP_Error( 'missing_query', __( 'The query parameter is required.', 'emcp-tools' ) );
		}

		$resolved = EMCP_Tools_Stock_Image_Providers::resolve( sanitize_key( $input['provider'] ?? '' ) );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		list( $provider_id, $client ) = $resolved;

		$result = $this->run_search( $client, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['provider'] = $provider_id;
		return $result;
	}

	/**
	 * Run a search against a resolved provider client and map to the tool's stable
	 * output shape. `download_location` is kept on each result (not in the output
	 * schema) so add-stock-image can fire the provider's download trigger.
	 *
	 * @since 3.1.0
	 * @param object $client Provider client (search_images()).
	 * @param array  $input  Tool input.
	 * @return array|\WP_Error
	 */
	private function run_search( $client, $input ) {
		$params = array( 'q' => sanitize_text_field( $input['query'] ?? '' ) );
		foreach ( array( 'page', 'page_size', 'aspect_ratio' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$params[ $key ] = $input[ $key ];
			}
		}

		$response = $client->search_images( $params );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$results = array();
		if ( ! empty( $response['results'] ) && is_array( $response['results'] ) ) {
			foreach ( $response['results'] as $image ) {
				$results[] = array(
					'id'                  => $image['id'] ?? '',
					'title'               => $image['title'] ?? '',
					'url'                 => $image['url'] ?? '',
					'thumbnail'           => $image['thumbnail'] ?? '',
					'width'               => intval( $image['width'] ?? 0 ),
					'height'              => intval( $image['height'] ?? 0 ),
					'creator'             => $image['creator'] ?? '',
					'creator_url'         => $image['creator_url'] ?? '',
					'license'             => $image['license'] ?? '',
					'license_url'         => $image['license_url'] ?? '',
					'attribution'         => $image['attribution'] ?? '',
					'source'              => $image['source'] ?? '',
					'foreign_landing_url' => $image['foreign_landing_url'] ?? '',
					'download_location'   => $image['download_location'] ?? '',
				);
			}
		}

		$page = isset( $input['page'] ) ? max( absint( $input['page'] ), 1 ) : 1;

		return array(
			'result_count' => intval( $response['total'] ?? count( $results ) ),
			'page'         => $page,
			'page_count'   => intval( $response['total_pages'] ?? 0 ),
			'results'      => $results,
		);
	}

	// -------------------------------------------------------------------------
	// sideload-image
	// -------------------------------------------------------------------------

	private function register_sideload_image(): void {
		emcp_tools_register_ability(
			'emcp-tools/sideload-image',
			array(
				'label'               => __( 'Sideload Image', 'emcp-tools' ),
				'description'         => __( 'Downloads an external image URL into the WordPress Media Library and returns the local attachment ID and URL. Use this after search-images to import a chosen image.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_sideload_image' ),
				'permission_callback' => array( $this, 'check_upload_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'url'         => array(
							'type'        => 'string',
							'description' => __( 'The external image URL to download.', 'emcp-tools' ),
						),
						'title'       => array(
							'type'        => 'string',
							'description' => __( 'Attachment title. Falls back to the filename.', 'emcp-tools' ),
						),
						'alt_text'    => array(
							'type'        => 'string',
							'description' => __( 'Alt text for the image.', 'emcp-tools' ),
						),
						'caption'     => array(
							'type'        => 'string',
							'description' => __( 'Image caption.', 'emcp-tools' ),
						),
						'attribution' => array(
							'type'        => 'string',
							'description' => __( 'Attribution text for Creative Commons images. Stored as the attachment caption/excerpt.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'url' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'attachment_id' => array( 'type' => 'integer' ),
						'url'           => array( 'type' => 'string' ),
						'title'         => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the sideload-image ability.
	 *
	 * Downloads a remote image into the WordPress Media Library.
	 *
	 * @since 1.1.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_sideload_image( $input ) {
		$url = esc_url_raw( $input['url'] ?? '' );

		if ( empty( $url ) ) {
			return new \WP_Error( 'missing_url', __( 'The url parameter is required.', 'emcp-tools' ) );
		}

		// Load required WordPress media functions.
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Download the file to a temp location (SSRF-guarded: blocks private/
		// reserved/loopback hosts and re-validates each redirect hop).
		$tmp_file = EMCP_Tools_Url_Guard::safe_download( $url, 30 );

		if ( is_wp_error( $tmp_file ) ) {
			return new \WP_Error(
				'download_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to download image: %s', 'emcp-tools' ),
					$tmp_file->get_error_message()
				)
			);
		}

		// Determine filename from URL.
		$url_path = wp_parse_url( $url, PHP_URL_PATH );
		$filename = $url_path ? basename( $url_path ) : 'image.jpg';

		// Ensure it has an extension.
		if ( ! preg_match( '/\.\w+$/', $filename ) ) {
			$filename .= '.jpg';
		}

		$file_array = array(
			'name'     => sanitize_file_name( $filename ),
			'tmp_name' => $tmp_file,
		);

		// Sideload into the media library.
		$attachment_id = media_handle_sideload( $file_array, 0 );

		if ( is_wp_error( $attachment_id ) ) {
			// Clean up temp file on failure.
			if ( file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
			}

			return new \WP_Error(
				'sideload_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to sideload image: %s', 'emcp-tools' ),
					$attachment_id->get_error_message()
				)
			);
		}

		// Set title if provided.
		$title = sanitize_text_field( $input['title'] ?? '' );
		if ( ! empty( $title ) ) {
			wp_update_post( array(
				'ID'         => $attachment_id,
				'post_title' => $title,
			) );
		} else {
			$title = get_the_title( $attachment_id );
		}

		// Set alt text if provided.
		$alt_text = sanitize_text_field( $input['alt_text'] ?? '' );
		if ( ! empty( $alt_text ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}

		// Set caption or attribution as post excerpt.
		$caption     = sanitize_text_field( $input['caption'] ?? '' );
		$attribution = sanitize_text_field( $input['attribution'] ?? '' );
		$excerpt     = ! empty( $caption ) ? $caption : $attribution;

		if ( ! empty( $excerpt ) ) {
			wp_update_post( array(
				'ID'           => $attachment_id,
				'post_excerpt' => $excerpt,
			) );
		}

		$local_url = wp_get_attachment_url( $attachment_id );

		return array(
			'attachment_id' => $attachment_id,
			'url'           => $local_url ? $local_url : '',
			'title'         => $title,
		);
	}

	// -------------------------------------------------------------------------
	// add-stock-image
	// -------------------------------------------------------------------------

	private function register_add_stock_image(): void {
		emcp_tools_register_ability(
			'emcp-tools/add-stock-image',
			array(
				'label'               => __( 'Add Stock Image', 'emcp-tools' ),
				'description'         => __( 'Searches a stock provider (Unsplash, Pexels, or Pixabay) for a photo, downloads it to the Media Library, and adds it as an image widget to the page — all in one step. Defaults to landscape (wide) images for consistent layouts. Combines search-images + sideload-image + add-free-widget. Requires a free API key for at least one provider (EMCP Tools → Connection).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_stock_image' ),
				'permission_callback' => array( $this, 'check_combined_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
						),
						'parent_id'  => array(
							'type'        => 'string',
							'description' => __( 'Parent container element ID.', 'emcp-tools' ),
						),
						'query'      => array(
							'type'        => 'string',
							'description' => __( 'Image search keywords (e.g. "hero banner technology", "team photo office").', 'emcp-tools' ),
						),
						'provider'   => array(
							'type'        => 'string',
							'enum'        => array( 'unsplash', 'pexels', 'pixabay' ),
							'description' => __( 'Which stock provider to use. Omit to use the first one you have connected.', 'emcp-tools' ),
						),
						'index'      => array(
							'type'        => 'integer',
							'description' => __( 'Which search result to use (0 = best match). Default: 0.', 'emcp-tools' ),
						),
						'position'   => array(
							'type'        => 'integer',
							'description' => __( 'Insert position within parent. -1 = append (default).', 'emcp-tools' ),
						),
						'image_size' => array(
							'type'        => 'string',
							'enum'        => array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ),
							'description' => __( 'Image size preset. Default: full.', 'emcp-tools' ),
						),
						'align'      => array(
							'type'        => 'string',
							'enum'        => array( 'left', 'center', 'right' ),
							'description' => __( 'Image alignment.', 'emcp-tools' ),
						),
						'caption'    => array(
							'type'        => 'string',
							'description' => __( 'Caption override. Defaults to the Unsplash photographer attribution.', 'emcp-tools' ),
						),
						'aspect_ratio' => array(
							'type'        => 'string',
							'enum'        => array( 'wide', 'tall', 'square', 'any' ),
							'description' => __( 'Image aspect ratio filter. Default: wide (landscape). Use "wide" for hero banners and card images, "tall" for sidebar/portrait, "square" for thumbnails, "any" for no filter.', 'emcp-tools' ),
						),
						'alt_text'   => array(
							'type'        => 'string',
							'description' => __( 'Alt text override. Defaults to the image title.', 'emcp-tools' ),
						),
						'link_to'    => array(
							'type'        => 'string',
							'enum'        => array( 'none', 'file', 'custom' ),
							'description' => __( 'Link behavior. Default: none.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id', 'parent_id', 'query' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'attachment_id' => array( 'type' => 'integer' ),
						'image_url'     => array( 'type' => 'string' ),
						'element_id'    => array( 'type' => 'string' ),
						'original_url'  => array( 'type' => 'string' ),
						'attribution'   => array( 'type' => 'string' ),
						'provider'      => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the add-stock-image ability.
	 *
	 * Chains: search → pick result → sideload → add image widget to page.
	 *
	 * @since 1.1.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_add_stock_image( $input ) {
		$post_id   = absint( $input['post_id'] ?? 0 );
		$parent_id = sanitize_text_field( $input['parent_id'] ?? '' );
		$query     = sanitize_text_field( $input['query'] ?? '' );
		$index     = absint( $input['index'] ?? 0 );
		$position  = intval( $input['position'] ?? -1 );

		if ( ! $post_id || empty( $parent_id ) || empty( $query ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id, parent_id, and query are required.', 'emcp-tools' ) );
		}

		// Resolve the stock provider (requested one, else first connected).
		$resolved = EMCP_Tools_Stock_Image_Providers::resolve( sanitize_key( $input['provider'] ?? '' ) );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		list( $provider_id, $client ) = $resolved;

		// Default to wide/landscape images for better layout compatibility.
		$aspect_ratio = sanitize_key( $input['aspect_ratio'] ?? 'wide' );

		$search_params = array(
			'query'     => $query,
			'page_size' => min( $index + 3, 20 ), // Fetch a few extra for safety.
		);

		// Apply aspect ratio filter (wide = landscape, best for most layouts).
		if ( ! empty( $aspect_ratio ) && 'any' !== $aspect_ratio ) {
			$search_params['aspect_ratio'] = $aspect_ratio;
		}

		// Step 1: Search for images with the resolved provider.
		$search_result = $this->run_search( $client, $search_params );

		if ( is_wp_error( $search_result ) ) {
			return $search_result;
		}

		if ( empty( $search_result['results'] ) ) {
			return new \WP_Error(
				'no_results',
				sprintf(
					/* translators: %s: search query */
					__( 'No images found for "%s". Try different keywords.', 'emcp-tools' ),
					$query
				)
			);
		}

		if ( ! isset( $search_result['results'][ $index ] ) ) {
			return new \WP_Error(
				'invalid_index',
				sprintf(
					/* translators: %1$d: requested index, %2$d: available count */
					__( 'Requested image index %1$d but only %2$d results were returned.', 'emcp-tools' ),
					$index,
					count( $search_result['results'] )
				)
			);
		}

		$image = $search_result['results'][ $index ];

		// Unsplash API guideline: fire the download-tracking endpoint when a photo is
		// selected for use (no-op on providers that don't have one). Best-effort.
		if ( ! empty( $image['download_location'] ) ) {
			$client->trigger_download( (string) $image['download_location'] );
		}

		// Step 2: Sideload the image into the Media Library.
		$alt_text    = sanitize_text_field( $input['alt_text'] ?? '' );
		$caption     = sanitize_text_field( $input['caption'] ?? '' );
		$attribution = $image['attribution'] ?? '';

		$sideload_result = $this->execute_sideload_image( array(
			'url'         => $image['url'],
			'title'       => $image['title'] ?? $query,
			'alt_text'    => ! empty( $alt_text ) ? $alt_text : ( $image['title'] ?? $query ),
			'caption'     => $caption,
			'attribution' => $attribution,
		) );

		if ( is_wp_error( $sideload_result ) ) {
			return $sideload_result;
		}

		// Step 3: Add image widget to the page.
		$widget_settings = array(
			'image'      => array(
				'url' => $sideload_result['url'],
				'id'  => $sideload_result['attachment_id'],
			),
			'image_size' => sanitize_key( $input['image_size'] ?? 'full' ),
		);

		if ( ! empty( $input['align'] ) ) {
			$widget_settings['align'] = sanitize_text_field( $input['align'] );
		}

		if ( ! empty( $caption ) ) {
			$widget_settings['caption_source'] = 'custom';
			$widget_settings['caption']        = $caption;
		} elseif ( ! empty( $attribution ) ) {
			$widget_settings['caption_source'] = 'custom';
			$widget_settings['caption']        = $attribution;
		}

		$link_to = sanitize_key( $input['link_to'] ?? 'none' );
		if ( 'file' === $link_to ) {
			$widget_settings['link_to'] = 'file';
		} elseif ( 'custom' === $link_to && ! empty( $image['foreign_landing_url'] ) ) {
			$widget_settings['link_to'] = 'custom';
			$widget_settings['link']    = array( 'url' => $image['foreign_landing_url'] );
		} else {
			$widget_settings['link_to'] = 'none';
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$widget = $this->factory->create_widget( 'image', $widget_settings );

		$inserted = $this->data->insert_element( $page_data, $parent_id, $widget, $position );

		if ( ! $inserted ) {
			return new \WP_Error( 'parent_not_found', __( 'Parent container not found.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'attachment_id' => $sideload_result['attachment_id'],
			'image_url'     => $sideload_result['url'],
			'element_id'    => $widget['id'],
			'original_url'  => $image['url'] ?? '',
			'attribution'   => $attribution,
			'provider'      => $provider_id,
		);
	}
}
