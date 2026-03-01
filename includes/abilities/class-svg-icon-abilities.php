<?php
/**
 * SVG icon MCP abilities for Elementor.
 *
 * Registers tools for uploading SVG icons and using them with Elementor
 * icon and icon-box widgets.
 *
 * @package Elementor_MCP
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the SVG icon abilities.
 *
 * @since 1.2.0
 */
class Elementor_MCP_Svg_Icon_Abilities {

	/**
	 * @var Elementor_MCP_Data
	 */
	private $data;

	/**
	 * @var Elementor_MCP_Element_Factory
	 */
	private $factory;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param Elementor_MCP_Data            $data    The data access layer.
	 * @param Elementor_MCP_Element_Factory $factory The element factory.
	 */
	public function __construct( Elementor_MCP_Data $data, Elementor_MCP_Element_Factory $factory ) {
		$this->data    = $data;
		$this->factory = $factory;
	}

	/**
	 * Returns the ability names registered by this class.
	 *
	 * @since 1.2.0
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'elementor-mcp/upload-svg-icon',
		);
	}

	/**
	 * Registers all SVG icon abilities.
	 *
	 * @since 1.2.0
	 */
	public function register(): void {
		$this->register_upload_svg_icon();
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Permission check for uploading SVG files.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	public function check_upload_permission(): bool {
		return current_user_can( 'upload_files' );
	}

	// -------------------------------------------------------------------------
	// upload-svg-icon
	// -------------------------------------------------------------------------

	/**
	 * Registers the upload-svg-icon ability.
	 *
	 * @since 1.2.0
	 */
	private function register_upload_svg_icon(): void {
		wp_register_ability(
			'elementor-mcp/upload-svg-icon',
			array(
				'label'               => __( 'Upload SVG Icon', 'elementor-mcp' ),
				'description'         => __( 'Uploads an SVG icon to the WordPress Media Library and returns an Elementor icon object ready to use with any widget that accepts icons (icon, icon-box, button, etc.). Accepts either an external SVG URL or raw SVG markup. The returned icon object has the format: { "value": { "id": 123, "url": "..." }, "library": "svg" }. Use this value for the selected_icon setting in icon/icon-box widgets, or the selected_icon setting in button widgets.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_upload_svg_icon' ),
				'permission_callback' => array( $this, 'check_upload_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'svg_url'     => array(
							'type'        => 'string',
							'description' => __( 'External URL to an SVG file to download and import. Use this OR svg_content, not both.', 'elementor-mcp' ),
						),
						'svg_content' => array(
							'type'        => 'string',
							'description' => __( 'Raw SVG markup string (e.g. "<svg viewBox=\'0 0 24 24\'><path d=\'M12 ...\'/></svg>"). Use this OR svg_url, not both.', 'elementor-mcp' ),
						),
						'title'       => array(
							'type'        => 'string',
							'description' => __( 'Title for the SVG in the Media Library. Falls back to filename or "Custom SVG Icon".', 'elementor-mcp' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'attachment_id' => array( 'type' => 'integer' ),
						'url'           => array( 'type' => 'string' ),
						'icon_object'   => array(
							'type'        => 'object',
							'description' => __( 'Ready-to-use Elementor icon object. Pass this directly as the selected_icon setting.', 'elementor-mcp' ),
							'properties'  => array(
								'value'   => array(
									'type'       => 'object',
									'properties' => array(
										'id'  => array( 'type' => 'integer' ),
										'url' => array( 'type' => 'string' ),
									),
								),
								'library' => array( 'type' => 'string' ),
							),
						),
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
	 * Executes the upload-svg-icon ability.
	 *
	 * Handles two input modes:
	 * 1. svg_url: Downloads an external SVG file and imports it.
	 * 2. svg_content: Takes raw SVG markup, writes to temp file, and imports it.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_upload_svg_icon( $input ) {
		$svg_url     = esc_url_raw( $input['svg_url'] ?? '' );
		$svg_content = $input['svg_content'] ?? '';
		$title       = sanitize_text_field( $input['title'] ?? '' );

		if ( empty( $svg_url ) && empty( $svg_content ) ) {
			return new \WP_Error(
				'missing_input',
				__( 'Either svg_url or svg_content is required.', 'elementor-mcp' )
			);
		}

		if ( ! empty( $svg_url ) && ! empty( $svg_content ) ) {
			return new \WP_Error(
				'conflicting_input',
				__( 'Provide either svg_url or svg_content, not both.', 'elementor-mcp' )
			);
		}

		// Load required WordPress media functions.
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Enable SVG uploads temporarily via filter.
		$allow_svg = function ( $mimes ) {
			$mimes['svg'] = 'image/svg+xml';
			return $mimes;
		};
		add_filter( 'upload_mimes', $allow_svg );

		// Also bypass real MIME check for SVG (WordPress misidentifies SVG MIME).
		$fix_svg_mime = function ( $data, $file, $filename ) {
			if ( ! empty( $data['ext'] ) && 'svg' === $data['ext'] ) {
				$data['type'] = 'image/svg+xml';
			}
			if ( empty( $data['ext'] ) && ! empty( $filename ) ) {
				$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
				if ( 'svg' === $ext ) {
					$data['ext']             = 'svg';
					$data['type']            = 'image/svg+xml';
					$data['proper_filename'] = $filename;
				}
			}
			return $data;
		};
		add_filter( 'wp_check_filetype_and_ext', $fix_svg_mime, 10, 3 );

		if ( ! empty( $svg_url ) ) {
			$result = $this->upload_from_url( $svg_url, $title );
		} else {
			$result = $this->upload_from_content( $svg_content, $title );
		}

		// Remove our temporary filters.
		remove_filter( 'upload_mimes', $allow_svg );
		remove_filter( 'wp_check_filetype_and_ext', $fix_svg_mime, 10 );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$attachment_id = $result['attachment_id'];
		$local_url     = $result['url'];

		// Set the title.
		if ( ! empty( $title ) ) {
			wp_update_post( array(
				'ID'         => $attachment_id,
				'post_title' => $title,
			) );
		}

		// Build the Elementor icon object.
		$icon_object = array(
			'value'   => array(
				'id'  => $attachment_id,
				'url' => $local_url,
			),
			'library' => 'svg',
		);

		return array(
			'attachment_id' => $attachment_id,
			'url'           => $local_url,
			'icon_object'   => $icon_object,
		);
	}

	/**
	 * Uploads an SVG file from an external URL.
	 *
	 * @since 1.2.0
	 *
	 * @param string $url   The external SVG URL.
	 * @param string $title Optional title for filename fallback.
	 * @return array|\WP_Error Array with attachment_id and url on success.
	 */
	private function upload_from_url( string $url, string $title ): array {
		$tmp_file = download_url( $url, 30 );

		if ( is_wp_error( $tmp_file ) ) {
			return new \WP_Error(
				'download_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to download SVG: %s', 'elementor-mcp' ),
					$tmp_file->get_error_message()
				)
			);
		}

		// Validate the downloaded file contains SVG content.
		$validation = $this->validate_svg_file( $tmp_file );
		if ( is_wp_error( $validation ) ) {
			wp_delete_file( $tmp_file );
			return $validation;
		}

		// Determine filename.
		$url_path = wp_parse_url( $url, PHP_URL_PATH );
		$filename = $url_path ? basename( $url_path ) : 'icon.svg';

		if ( ! preg_match( '/\.svg$/i', $filename ) ) {
			$filename = ( ! empty( $title ) ? sanitize_title( $title ) : 'icon' ) . '.svg';
		}

		return $this->do_sideload( $tmp_file, $filename );
	}

	/**
	 * Uploads an SVG from raw markup content.
	 *
	 * @since 1.2.0
	 *
	 * @param string $content Raw SVG markup.
	 * @param string $title   Optional title for filename.
	 * @return array|\WP_Error Array with attachment_id and url on success.
	 */
	private function upload_from_content( string $content, string $title ): array {
		// Basic validation: must contain <svg tag.
		if ( stripos( $content, '<svg' ) === false ) {
			return new \WP_Error(
				'invalid_svg',
				__( 'The svg_content does not contain valid SVG markup. Must include an <svg> element.', 'elementor-mcp' )
			);
		}

		// Sanitize the SVG content.
		$sanitized = $this->sanitize_svg_content( $content );
		if ( is_wp_error( $sanitized ) ) {
			return $sanitized;
		}

		// Write to a temp file.
		$tmp_file = wp_tempnam( 'svg_icon_' );
		if ( ! $tmp_file ) {
			return new \WP_Error(
				'temp_file_failed',
				__( 'Could not create temporary file for SVG upload.', 'elementor-mcp' )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $tmp_file, $sanitized );

		$filename = ( ! empty( $title ) ? sanitize_title( $title ) : 'custom-icon' ) . '.svg';

		return $this->do_sideload( $tmp_file, $filename );
	}

	/**
	 * Performs the actual sideload into the WordPress Media Library.
	 *
	 * @since 1.2.0
	 *
	 * @param string $tmp_file Path to the temporary file.
	 * @param string $filename The desired filename.
	 * @return array|\WP_Error Array with attachment_id and url on success.
	 */
	private function do_sideload( string $tmp_file, string $filename ): array {
		$file_array = array(
			'name'     => sanitize_file_name( $filename ),
			'tmp_name' => $tmp_file,
		);

		$attachment_id = media_handle_sideload( $file_array, 0 );

		if ( is_wp_error( $attachment_id ) ) {
			if ( file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
			}

			return new \WP_Error(
				'sideload_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to sideload SVG: %s', 'elementor-mcp' ),
					$attachment_id->get_error_message()
				)
			);
		}

		$local_url = wp_get_attachment_url( $attachment_id );

		return array(
			'attachment_id' => $attachment_id,
			'url'           => $local_url ? $local_url : '',
		);
	}

	/**
	 * Validates that a file contains SVG content.
	 *
	 * @since 1.2.0
	 *
	 * @param string $file_path Path to the file to validate.
	 * @return true|\WP_Error True if valid, WP_Error if not.
	 */
	private function validate_svg_file( string $file_path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $file_path );

		if ( empty( $content ) ) {
			return new \WP_Error(
				'empty_svg',
				__( 'The downloaded file is empty.', 'elementor-mcp' )
			);
		}

		if ( stripos( $content, '<svg' ) === false ) {
			return new \WP_Error(
				'not_svg',
				__( 'The downloaded file does not contain SVG markup.', 'elementor-mcp' )
			);
		}

		// Check for potentially dangerous content.
		if ( preg_match( '/<script/i', $content ) ) {
			return new \WP_Error(
				'svg_has_script',
				__( 'SVG contains script elements and was rejected for security.', 'elementor-mcp' )
			);
		}

		// Let Elementor's sanitizer handle the rest if available.
		if ( class_exists( '\Elementor\Utils' ) && method_exists( '\Elementor\Utils', 'get_svg_sanitizer' ) ) {
			$sanitizer = \Elementor\Utils::get_svg_sanitizer();
			if ( $sanitizer && method_exists( $sanitizer, 'sanitize' ) ) {
				$sanitized = $sanitizer->sanitize( $content );
				if ( empty( $sanitized ) ) {
					return new \WP_Error(
						'svg_sanitization_failed',
						__( 'SVG failed Elementor security sanitization.', 'elementor-mcp' )
					);
				}
				// Write back the sanitized content.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $file_path, $sanitized );
			}
		}

		return true;
	}

	/**
	 * Sanitizes raw SVG content.
	 *
	 * Uses Elementor's SVG sanitizer if available, otherwise performs
	 * basic security checks.
	 *
	 * @since 1.2.0
	 *
	 * @param string $content Raw SVG markup.
	 * @return string|\WP_Error Sanitized SVG content or WP_Error.
	 */
	private function sanitize_svg_content( string $content ) {
		// Strip PHP tags.
		$content = preg_replace( '/<\?(=|php)(.+?)\?>/i', '', $content );

		// Remove script tags.
		if ( preg_match( '/<script/i', $content ) ) {
			return new \WP_Error(
				'svg_has_script',
				__( 'SVG contains script elements and was rejected for security.', 'elementor-mcp' )
			);
		}

		// Remove event handlers (on*="...").
		$content = preg_replace( '/\s+on\w+\s*=\s*(["\']).*?\1/i', '', $content );

		// Remove javascript: URLs.
		$content = preg_replace( '/javascript\s*:/i', '', $content );

		// Use Elementor's sanitizer if available.
		if ( class_exists( '\Elementor\Utils' ) && method_exists( '\Elementor\Utils', 'get_svg_sanitizer' ) ) {
			$sanitizer = \Elementor\Utils::get_svg_sanitizer();
			if ( $sanitizer && method_exists( $sanitizer, 'sanitize' ) ) {
				$sanitized = $sanitizer->sanitize( $content );
				if ( empty( $sanitized ) ) {
					return new \WP_Error(
						'svg_sanitization_failed',
						__( 'SVG failed Elementor security sanitization. Ensure it contains valid SVG markup.', 'elementor-mcp' )
					);
				}
				return $sanitized;
			}
		}

		return $content;
	}
}
