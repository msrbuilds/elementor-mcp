<?php
/**
 * Theme adapters for standalone header/footer injection.
 *
 * On supported themes we suppress the theme's own header/footer and print ours at
 * the theme's hook, preserving the theme's content area. adapter_for() maps a
 * template (parent) slug to an adapter key; the render controller wires the hooks.
 * Unsupported themes fall back to the documented emcp_themer_location() tag or the
 * optional full-page-takeover toggle (handled by the render controller).
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
class EMCP_Tools_Themer_Theme_Adapters {

	/**
	 * Supported theme (template) slug => { header, footer } hook names.
	 *
	 * @return array<string,array{header:string,footer:string}>
	 */
	public static function map(): array {
		/**
		 * Filters the supported theme adapters.
		 *
		 * @param array $map Theme slug => hook config.
		 */
		return apply_filters(
			'emcp_themer_theme_adapters',
			array(
				'astra'           => array( 'header' => 'astra_header', 'footer' => 'astra_footer' ),
				'generatepress'   => array( 'header' => 'generate_header', 'footer' => 'generate_footer' ),
				'kadence'         => array( 'header' => 'kadence_header', 'footer' => 'kadence_footer' ),
				'oceanwp'         => array( 'header' => 'ocean_header', 'footer' => 'ocean_footer' ),
				'blocksy'         => array( 'header' => 'blocksy:header', 'footer' => 'blocksy:footer' ),
				'neve'            => array( 'header' => 'neve_after_header_wrapper_hook', 'footer' => 'neve_before_footer_hook' ),
				'hello-elementor' => array( 'header' => 'hello_elementor_header', 'footer' => 'hello_elementor_footer' ),
			)
		);
	}

	/**
	 * Adapter key for a template (parent) slug, or null when unsupported.
	 *
	 * @param string $template_slug The active theme's template (parent) slug.
	 * @return string|null
	 */
	public static function adapter_for( string $template_slug ): ?string {
		$map = self::map();
		return isset( $map[ $template_slug ] ) ? $template_slug : null;
	}

	/**
	 * Whether a template slug is supported.
	 *
	 * @param string $template_slug Template slug.
	 * @return bool
	 */
	public static function is_supported( string $template_slug ): bool {
		return null !== self::adapter_for( $template_slug );
	}

	/**
	 * The active theme's adapter key (uses the parent/template slug), or null.
	 *
	 * @return string|null
	 */
	public static function current(): ?string {
		return self::adapter_for( (string) get_template() );
	}
}
