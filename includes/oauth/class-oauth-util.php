<?php
/**
 * OAuth helpers — pure, dependency-free primitives for the EMCP OAuth 2.1
 * authorization server (token generation, hashing, PKCE verification,
 * base64url). Kept side-effect-free so they can be unit-tested without a
 * database or WordPress.
 *
 * @package EMCP_Tools
 * @since   3.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless OAuth primitives.
 *
 * @since 3.4.1
 */
class EMCP_Tools_OAuth_Util {

	/**
	 * base64url-encode raw bytes (RFC 4648 §5, no padding).
	 *
	 * @param string $bin Raw bytes.
	 * @return string
	 */
	public static function base64url_encode( string $bin ): string {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	/**
	 * Decode a base64url string back to raw bytes.
	 *
	 * @param string $str base64url string.
	 * @return string
	 */
	public static function base64url_decode( string $str ): string {
		$pad = strlen( $str ) % 4;
		if ( $pad ) {
			$str .= str_repeat( '=', 4 - $pad );
		}
		return (string) base64_decode( strtr( $str, '-_', '+/' ), true );
	}

	/**
	 * Generate an opaque token: 32 random bytes → 43-char base64url string.
	 *
	 * @return string
	 */
	public static function generate_token(): string {
		return self::base64url_encode( random_bytes( 32 ) );
	}

	/**
	 * Generate a public client id (`emcp_` + 24 hex chars).
	 *
	 * @return string
	 */
	public static function generate_client_id(): string {
		return 'emcp_' . bin2hex( random_bytes( 12 ) );
	}

	/**
	 * Hash a token/code for at-rest storage. Tokens are high-entropy, so a plain
	 * SHA-256 (no salt) is appropriate and keeps lookups a single indexed query.
	 *
	 * @param string $token Raw token or code.
	 * @return string 64-char hex digest.
	 */
	public static function hash_token( string $token ): string {
		return hash( 'sha256', $token );
	}

	/**
	 * Verify a PKCE code_verifier against a stored code_challenge.
	 *
	 * Only S256 is accepted (`plain` is refused for public clients). The verifier
	 * must be 43-128 chars (RFC 7636 §4.1). Comparison is constant-time.
	 *
	 * @param string $verifier  The client's code_verifier.
	 * @param string $challenge The stored code_challenge.
	 * @param string $method    Challenge method; must be 'S256'.
	 * @return bool
	 */
	public static function verify_pkce( string $verifier, string $challenge, string $method = 'S256' ): bool {
		if ( 'S256' !== $method ) {
			return false;
		}
		$len = strlen( $verifier );
		if ( '' === $challenge || $len < 43 || $len > 128 ) {
			return false;
		}
		$computed = self::base64url_encode( hash( 'sha256', $verifier, true ) );
		return hash_equals( $challenge, $computed );
	}

	/**
	 * Constant-time equality for opaque secrets (codes, tokens).
	 *
	 * @param string $known Expected value.
	 * @param string $given Provided value.
	 * @return bool
	 */
	public static function secure_equals( string $known, string $given ): bool {
		return hash_equals( $known, $given );
	}

	/**
	 * Whether two redirect URIs match. Exact string match, with the native-app
	 * loopback exception (RFC 8252 §7.3): for http://127.0.0.1 / http://[::1] /
	 * http://localhost the port may differ, since native clients bind an
	 * ephemeral local port.
	 *
	 * @param string $registered A registered redirect URI.
	 * @param string $given      The redirect URI presented in the request.
	 * @return bool
	 */
	public static function redirect_uri_matches( string $registered, string $given ): bool {
		if ( hash_equals( $registered, $given ) ) {
			return true;
		}

		$r = parse_url( $registered );
		$g = parse_url( $given );
		if ( ! is_array( $r ) || ! is_array( $g ) ) {
			return false;
		}

		$loopback = array( '127.0.0.1', '::1', 'localhost' );
		$r_host   = isset( $r['host'] ) ? strtolower( trim( $r['host'], '[]' ) ) : '';
		$g_host   = isset( $g['host'] ) ? strtolower( trim( $g['host'], '[]' ) ) : '';

		if (
			'http' === ( $r['scheme'] ?? '' ) && 'http' === ( $g['scheme'] ?? '' )
			&& in_array( $r_host, $loopback, true ) && $r_host === $g_host
			&& ( $r['path'] ?? '/' ) === ( $g['path'] ?? '/' )
		) {
			// Same loopback host + path; port is allowed to differ.
			return true;
		}

		return false;
	}
}
