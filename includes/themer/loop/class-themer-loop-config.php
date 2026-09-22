<?php
/**
 * The signed replay config a loop element prints and the REST route replays.
 *
 * The signature authenticates the configuration (what the page already
 * rendered), never the visitor. The route only accepts a config this site
 * signed, so it cannot be used as a general query API.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.18.0
 */
class EMCP_Tools_Themer_Loop_Config {

	const VERSION = 1;

	/**
	 * @param array $config Config.
	 * @return string base64 JSON.
	 */
	public static function encode( array $config ): string {
		$config['v'] = self::VERSION;
		ksort( $config );
		return base64_encode( (string) wp_json_encode( $config ) );
	}

	/**
	 * @param string $encoded Output of encode().
	 * @return string hex HMAC.
	 */
	public static function sign( string $encoded ): string {
		return hash_hmac( 'sha256', $encoded, wp_salt( 'nonce' ) );
	}

	/**
	 * Verify then decode.
	 *
	 * @param string $encoded Config.
	 * @param string $sig     Signature.
	 * @return array|null
	 */
	public static function decode( string $encoded, string $sig ): ?array {
		if ( '' === $encoded || '' === $sig || ! hash_equals( self::sign( $encoded ), $sig ) ) {
			return null;
		}
		$json = base64_decode( $encoded, true );
		if ( false === $json ) {
			return null;
		}
		$config = json_decode( $json, true );
		if ( ! is_array( $config ) || (int) ( $config['v'] ?? 0 ) !== self::VERSION ) {
			return null;
		}
		return $config;
	}

	/**
	 * Both attribute values for a config.
	 *
	 * @param array $config Config.
	 * @return array{config:string,sig:string}
	 */
	public static function attributes( array $config ): array {
		$enc = self::encode( $config );
		return array( 'config' => $enc, 'sig' => self::sign( $enc ) );
	}
}
