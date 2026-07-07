<?php
/**
 * Image Optimization module — resize-media MCP ability.
 *
 * Registered only when the Image Optimization module is active. Lets an agent
 * resize an existing Media Library attachment in place (during a build, or any
 * library image), reusing the module's backup + compress + WebP machinery.
 *
 * @package EMCP_Tools
 * @since   3.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.1.1
 */
class EMCP_Tools_Image_Resize_Abilities {

	/** @var string[] */
	private $ability_names = array();

	/** @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	public function register(): void {
		$this->ability_names[] = 'emcp-tools/resize-media';
		emcp_tools_register_ability(
			'emcp-tools/resize-media',
			array(
				'label'               => __( 'Resize Media', 'emcp-tools' ),
				'description'         => __( 'Resize an existing Media Library image in place (the attachment ID and its URLs stay the same). Scales to fit the given width and/or height, preserving aspect ratio; pass crop:true to hard-crop to exactly width×height. The original is backed up (reversible), all sub-sizes + WebP are regenerated, and — if the module\'s max-dimension cap is smaller than your target — the cap applies. JPEG/PNG/WebP/GIF.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_resize' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'attachment_id' => array( 'type' => 'integer', 'description' => __( 'Media Library attachment ID.', 'emcp-tools' ) ),
						'width'         => array( 'type' => 'integer', 'description' => __( 'Target width in px. Omit to scale by height only.', 'emcp-tools' ) ),
						'height'        => array( 'type' => 'integer', 'description' => __( 'Target height in px. Omit to scale by width only.', 'emcp-tools' ) ),
						'crop'          => array( 'type' => 'boolean', 'description' => __( 'Hard-crop to exactly width×height (requires both). Default false = scale to fit.', 'emcp-tools' ) ),
					),
					'required'   => array( 'attachment_id' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/** @param array|null $input @return bool */
	public function check_permission( $input = null ): bool {
		$id = is_array( $input ) ? absint( $input['attachment_id'] ?? 0 ) : 0;
		if ( $id ) {
			return current_user_can( 'edit_post', $id );
		}
		return current_user_can( 'upload_files' );
	}

	/** @param array $input @return array */
	public function execute_resize( $input ): array {
		$id     = absint( $input['attachment_id'] ?? 0 );
		$width  = isset( $input['width'] ) ? (int) $input['width'] : null;
		$height = isset( $input['height'] ) ? (int) $input['height'] : null;
		$crop   = ! empty( $input['crop'] );

		$result = EMCP_Tools_Image_Resizer::resize( $id, $width, $height, $crop );
		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}
		return is_array( $result ) ? $result : array( 'error' => __( 'Unexpected result.', 'emcp-tools' ) );
	}
}
