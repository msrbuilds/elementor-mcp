<?php
/**
 * EMCP Themer PHP Template Renderer — runs one compiled template to a string.
 *
 * Manifest-only, hash-verified include (never a directory scan): the file must live
 * inside the sandbox and match its recorded sha256 before it is loaded. Defining the
 * wrapped function runs no user code; the function is then called inside output
 * buffering + try/catch + a shutdown fatal-recovery handler that decompiles a
 * template which hard-fatals, so a bad template can't repeatedly break the site.
 *
 * The main query is left intact, so the loop + template tags resolve to the viewed
 * post/archive — the same guarantee the builder-content renderer gives.
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
class EMCP_Tools_Themer_PHP_Renderer {

	/** @var int|null Template currently including/executing, for fatal attribution. */
	private static $active = null;

	/** @var bool */
	private static $shutdown_armed = false;

	/** @var array<int,string> func name per loaded template id. */
	private static $funcs = array();

	/**
	 * Render a template to a markup string. Returns '' on any failure so the caller
	 * can fall back to builder content.
	 *
	 * @param int $id Template id.
	 * @return string
	 */
	public static function render( int $id ): string {
		if ( ! class_exists( 'EMCP_Tools_Themer_PHP_Store' ) ) {
			return '';
		}
		$entry = self::manifest_entry( $id );
		if ( null === $entry ) {
			return '';
		}

		$func = self::load( $id, $entry );
		if ( '' === $func || ! function_exists( $func ) ) {
			return '';
		}

		self::arm_shutdown();
		self::$active = $id;
		ob_start();
		$ret = null;
		try {
			$ret = $func();
		} catch ( \Throwable $e ) {
			ob_end_clean();
			self::$active = null;
			EMCP_Tools_Themer_PHP_Store::mark_error( $id, $e->getMessage() );
			return '';
		}
		$out          = (string) ob_get_clean();
		self::$active = null;

		if ( is_string( $ret ) || is_numeric( $ret ) ) {
			$out .= (string) $ret;
		}
		return $out;
	}

	/**
	 * Find the manifest entry for a template id.
	 *
	 * @param int $id Template id.
	 * @return array|null
	 */
	private static function manifest_entry( int $id ) {
		foreach ( EMCP_Tools_Themer_PHP_Store::read_manifest() as $entry ) {
			if ( isset( $entry['post_id'] ) && (int) $entry['post_id'] === $id ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Hash-verify + include the wrapped function (defines it; runs no user code).
	 *
	 * @param int   $id    Template id.
	 * @param array $entry Manifest entry.
	 * @return string Function name, or '' on any guard failure.
	 */
	private static function load( int $id, array $entry ): string {
		if ( isset( self::$funcs[ $id ] ) && function_exists( self::$funcs[ $id ] ) ) {
			return self::$funcs[ $id ];
		}
		$func = isset( $entry['func'] ) ? (string) $entry['func'] : '';
		$rel  = isset( $entry['php_path'] ) ? (string) $entry['php_path'] : '';
		$hash = isset( $entry['hash'] ) ? (string) $entry['hash'] : '';
		if ( '' === $func || '' === $rel || '' === $hash ) {
			return '';
		}

		$sandbox = EMCP_Tools_PHP_Snippet_Store::sandbox_dir() . '/';
		$path    = $sandbox . $rel;
		// Path must stay inside the sandbox (defends a poisoned manifest).
		if ( 0 !== strpos( wp_normalize_path( $path ), wp_normalize_path( $sandbox ) ) || ! is_file( $path ) ) {
			return '';
		}
		// Tamper guard: contents must match the recorded hash.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( hash( 'sha256', (string) file_get_contents( $path ) ) !== $hash ) {
			return '';
		}

		self::arm_shutdown();
		self::$active = $id;
		try {
			include_once $path;
		} catch ( \Throwable $e ) {
			self::$active = null;
			EMCP_Tools_Themer_PHP_Store::mark_error( $id, $e->getMessage() );
			return '';
		}
		self::$active = null;

		if ( ! function_exists( $func ) ) {
			return '';
		}
		self::$funcs[ $id ] = $func;
		return $func;
	}

	private static function arm_shutdown(): void {
		if ( self::$shutdown_armed ) {
			return;
		}
		self::$shutdown_armed = true;
		register_shutdown_function( array( __CLASS__, 'on_shutdown' ) );
	}

	/**
	 * Shutdown callback: decompile a template that hard-fataled mid-render.
	 */
	public static function on_shutdown(): void {
		if ( null === self::$active ) {
			return;
		}
		$err   = error_get_last();
		$fatal = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );
		if ( is_array( $err ) && in_array( $err['type'], $fatal, true ) && class_exists( 'EMCP_Tools_Themer_PHP_Store' ) ) {
			EMCP_Tools_Themer_PHP_Store::mark_error(
				self::$active,
				isset( $err['message'] ) ? (string) $err['message'] : 'Fatal error while rendering PHP template.'
			);
		}
	}
}
