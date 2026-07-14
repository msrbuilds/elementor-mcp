<?php
/**
 * SVG sanitizer — thin wrapper over the bundled enshrined/svg-sanitize library.
 *
 * SVG is XML and can carry script, event handlers, external references and XXE
 * payloads. Allowing raw SVG uploads without sanitizing is a stored-XSS vector.
 * This class runs every uploaded SVG through enshrined/svg-sanitize (the same
 * library Safe SVG uses) and is **fail-closed**: if the library is unavailable
 * or the markup can't be cleaned, sanitization fails and the upload is rejected.
 *
 * @package EMCP_Tools
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes SVG markup before it reaches the Media Library.
 *
 * @since 3.4.0
 */
class EMCP_Tools_SVG_Sanitizer {

	/**
	 * Ensure the enshrined/svg-sanitize library is autoloadable.
	 *
	 * The library ships in vendor/ and is autoloaded through the Jetpack
	 * autoloader (same mechanism as the bundled MCP adapter). We boot that
	 * autoloader on demand so the class is available even if nothing else
	 * has required it yet this request.
	 *
	 * @return bool True when the Sanitizer class is available.
	 */
	public static function library_available(): bool {
		if ( class_exists( '\\enshrined\\svgSanitize\\Sanitizer' ) ) {
			return true;
		}
		// Preferred: the Jetpack autoloader (same mechanism as the bundled MCP adapter).
		if ( class_exists( 'EMCP_Tools_Adapter_Bootstrap' ) && method_exists( 'EMCP_Tools_Adapter_Bootstrap', 'ensure' ) ) {
			EMCP_Tools_Adapter_Bootstrap::ensure();
		} elseif ( defined( 'EMCP_TOOLS_DIR' ) && is_readable( EMCP_TOOLS_DIR . 'vendor/autoload_packages.php' ) ) {
			require_once EMCP_TOOLS_DIR . 'vendor/autoload_packages.php';
		}
		if ( class_exists( '\\enshrined\\svgSanitize\\Sanitizer' ) ) {
			return true;
		}
		// Fallback: load the bundled copy directly via a scoped PSR-4 autoloader,
		// so SVG sanitizing works even if the Jetpack classmap wasn't regenerated.
		self::register_fallback_autoloader();
		return class_exists( '\\enshrined\\svgSanitize\\Sanitizer' );
	}

	/** Register a one-time PSR-4 autoloader for the bundled enshrined/svg-sanitize copy. */
	private static function register_fallback_autoloader(): void {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;
		if ( ! defined( 'EMCP_TOOLS_DIR' ) ) {
			return;
		}
		$base = EMCP_TOOLS_DIR . 'vendor/enshrined/svg-sanitize/src/';
		if ( ! is_dir( $base ) ) {
			return;
		}
		spl_autoload_register(
			static function ( string $class ) use ( $base ): void {
				$prefix = 'enshrined\\svgSanitize\\';
				if ( 0 !== strpos( $class, $prefix ) ) {
					return;
				}
				$file = $base . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
				if ( is_file( $file ) ) {
					require_once $file;
				}
			}
		);
	}

	/**
	 * Sanitize SVG markup.
	 *
	 * @param string $svg Raw SVG markup.
	 * @return string|false Clean markup, or false when it can't be sanitized
	 *                      (unavailable library, empty, or malformed input).
	 */
	public function sanitize( string $svg ) {
		if ( '' === trim( $svg ) ) {
			return false;
		}
		if ( ! self::library_available() ) {
			return false;
		}

		$sanitizer = new \enshrined\svgSanitize\Sanitizer();
		$sanitizer->minify( false );
		// Strip external references (remote xlink:href, use@href) — SSRF/XSS hardening.
		$sanitizer->removeRemoteReferences( true );

		$clean = $sanitizer->sanitize( $svg );

		if ( false === $clean || '' === trim( (string) $clean ) ) {
			return false;
		}
		return $clean;
	}

	/**
	 * Sanitize an SVG file in place (read → clean → overwrite).
	 *
	 * @param string $path Absolute path to a .svg file.
	 * @return bool True on success, false if the file is unreadable/unsafe.
	 */
	public function sanitize_file( string $path ): bool {
		if ( ! is_readable( $path ) ) {
			return false;
		}
		$dirty = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $dirty ) {
			return false;
		}
		$clean = $this->sanitize( $dirty );
		if ( false === $clean ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return false !== file_put_contents( $path, $clean );
	}
}
