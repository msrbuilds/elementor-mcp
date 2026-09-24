<?php
/**
 * Lucide icons for the server-rendered admin frame.
 *
 * The inner markup comes from includes/admin/icons.php, generated at build time
 * from the same icon list the React components use.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icon lookup.
 */
final class EMCP_Tools_Admin_Icons {

	/**
	 * Name to inner-SVG map, loaded once.
	 *
	 * @var array<string,string>|null
	 */
	private static $map = null;

	/**
	 * A decorative inline SVG for an icon, or '' for an unknown name.
	 * The markup is a trusted build artifact (see admin-build/generate-php-icons.mjs).
	 *
	 * @param string $name Kebab-case icon name.
	 * @param int    $size Pixel size, clamped to 8..64.
	 * @return string
	 */
	public static function svg( string $name, int $size = 16 ): string {
		$inner = self::map()[ $name ] ?? '';
		if ( '' === $inner ) {
			return '';
		}
		$size = max( 8, min( 64, $size ) );
		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="eui-icon">%2$s</svg>',
			$size,
			$inner
		);
	}

	/**
	 * All available icon names.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		return array_keys( self::map() );
	}

	/**
	 * Load the generated map.
	 *
	 * @return array<string,string>
	 */
	private static function map(): array {
		if ( null === self::$map ) {
			$file      = EMCP_TOOLS_DIR . 'includes/admin/icons.php';
			$data      = is_readable( $file ) ? include $file : array();
			self::$map = is_array( $data ) ? $data : array();
		}
		return self::$map;
	}
}
