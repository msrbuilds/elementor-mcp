<?php
/**
 * Image Optimization module — auto-compress uploads + generate/serve WebP.
 *
 * When active it hooks `wp_generate_attachment_metadata` (compress sub-sizes +
 * WebP siblings) and registers the WebP URL rewriter (so the frontend and the
 * MCP media tools serve the optimized file). A resumable bulk optimizer handles
 * the existing library from the Modules admin card. Free tier; opt-in.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Optimization module.
 *
 * @since 3.1.0
 */
class EMCP_Tools_Image_Optimization_Module extends EMCP_Tools_Module {

	const ID     = 'image-optimization';
	const PREFIX = 'emcp_tools_module_image_optimization_';

	public function id(): string {
		return self::ID;
	}

	/**
	 * Whether the module is active (static helper for the ability registrar, which
	 * runs on wp_abilities_api_init — before the module boots on init:5).
	 *
	 * @return bool
	 */
	public static function module_is_active(): bool {
		$active = (array) get_option( EMCP_Tools_Module::OPTION_ACTIVE, array() );
		return in_array( self::ID, $active, true );
	}

	public function title(): string {
		return __( 'Image Optimization', 'emcp-tools' );
	}

	public function description(): string {
		return __( 'Automatically compress uploaded images and generate/serve WebP. Images added to pages via MCP or AI Chat use the optimized version.', 'emcp-tools' );
	}

	public function tier(): string {
		return 'free';
	}

	public function default_active(): bool {
		return false;
	}

	/** WebP generation needs an image editor that supports WebP output. */
	public function is_available(): bool {
		return ( new EMCP_Tools_Webp_Generator( 82 ) )->is_available();
	}

	/**
	 * The five option keys + their sanitizers for the Modules settings group.
	 *
	 * @return array<string,array>
	 */
	public function settings_fields(): array {
		$bool = static function ( $v ) {
			return '1' === (string) $v ? '1' : '0';
		};
		$int = static function ( $v ) {
			return (string) max( 0, (int) $v );
		};
		return array(
			self::PREFIX . 'compress'       => array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => $bool,
			),
			self::PREFIX . 'webp'           => array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => $bool,
			),
			self::PREFIX . 'webp_serve'     => array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => $bool,
			),
			self::PREFIX . 'quality'        => array(
				'type'              => 'string',
				'default'           => '60',
				'sanitize_callback' => $int,
			),
			self::PREFIX . 'max_dimension'  => array(
				'type'              => 'string',
				'default'           => '0',
				'sanitize_callback' => $int,
			),
			self::PREFIX . 'keep_originals' => array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => $bool,
			),
		);
	}

	/**
	 * Resolve the current settings into a typed array for the optimizer.
	 *
	 * @return array{compress:bool,webp:bool,webp_serve:bool,quality:int,max_dimension:int,keep_originals:bool}
	 */
	public function current_settings(): array {
		return array(
			'compress'       => '1' === (string) get_option( self::PREFIX . 'compress', '1' ),
			'webp'           => '1' === (string) get_option( self::PREFIX . 'webp', '1' ),
			'webp_serve'     => '1' === (string) get_option( self::PREFIX . 'webp_serve', '1' ),
			'quality'        => EMCP_Tools_Image_Optimizer::clamp_quality( (int) get_option( self::PREFIX . 'quality', 60 ) ),
			'max_dimension'  => max( 0, (int) get_option( self::PREFIX . 'max_dimension', 0 ) ),
			'keep_originals' => '1' === (string) get_option( self::PREFIX . 'keep_originals', '1' ),
		);
	}

	/** Wire the module's runtime hooks. Called only when active + available. */
	public function register(): void {
		$settings = $this->current_settings();

		if ( $settings['compress'] || $settings['webp'] ) {
			$optimizer = new EMCP_Tools_Image_Optimizer( $settings );
			add_filter(
				'wp_generate_attachment_metadata',
				array( $optimizer, 'on_generate_metadata' ),
				20,
				2
			);
		}
		if ( $settings['webp'] ) {
			// REST/CLI (MCP media tools) always resolve to WebP; the frontend
			// rewrite is gated by the separate "serve on frontend" toggle.
			( new EMCP_Tools_Webp_Rewriter( $settings['webp_serve'] ) )->register();
		}
		if ( is_admin() ) {
			( new EMCP_Tools_Bulk_Optimizer( $settings ) )->register();
		}
	}

	/** Render the card knobs. Delegates to the shared view partial. */
	public function render_settings(): void {
		$settings = $this->current_settings();
		include EMCP_TOOLS_DIR . 'includes/modules/image-optimization/settings-fields.php';
	}
}
