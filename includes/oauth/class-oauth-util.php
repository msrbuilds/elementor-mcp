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
	 * Generate a PKCE code_verifier (RFC 7636). 32 random bytes -> 43-char
	 * base64url, within the 43..128 length bound.
	 *
	 * @return string
	 */
	public static function generate_code_verifier(): string {
		return self::generate_token();
	}

	/**
	 * Compute the S256 code_challenge for a verifier. Byte-identical to the
	 * computation in verify_pkce(), so client + server agree.
	 *
	 * @param string $verifier The code_verifier.
	 * @return string base64url( sha256( verifier ) ).
	 */
	public static function code_challenge_s256( string $verifier ): string {
		return self::base64url_encode( hash( 'sha256', $verifier, true ) );
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

		$r = wp_parse_url( $registered );
		$g = wp_parse_url( $given );
		if ( ! is_array( $r ) || ! is_array( $g ) ) {
			return false;
		}

		$r_host = isset( $r['host'] ) ? strtolower( trim( $r['host'], '[]' ) ) : '';
		$g_host = isset( $g['host'] ) ? strtolower( trim( $g['host'], '[]' ) ) : '';

		// Loopback redirects get a relaxed comparison; everything else, notably
		// every https callback, stays an exact string match. Only a loopback URI
		// is safe to relax, because it can only ever deliver the code to a
		// listener on the user's OWN machine.
		if (
			'http' !== ( $r['scheme'] ?? '' ) || 'http' !== ( $g['scheme'] ?? '' )
			|| ! self::is_loopback_host( $r_host ) || ! self::is_loopback_host( $g_host )
		) {
			return false;
		}

		// `localhost`, `127.0.0.1` and `::1` all name the same machine. Clients
		// routinely register one form and authorize with another (or the OS
		// resolves one to the other), and refusing that mismatch was rejecting
		// legitimate connections from CLI clients with nothing gained.
		//
		// The port is likewise ignored: RFC 8252 section 7.3 requires it, because
		// a native client binds an ephemeral port that it cannot know at
		// registration time.
		$r_path = self::normalize_loopback_path( $r['path'] ?? '/' );
		$g_path = self::normalize_loopback_path( $g['path'] ?? '/' );

		return $r_path === $g_path;
	}

	/**
	 * Is $host one of the names that mean "this machine"?
	 *
	 * @since 3.13.0
	 * @param string $host Lowercased host, brackets already stripped.
	 * @return bool
	 */
	public static function is_loopback_host( string $host ): bool {
		return in_array( $host, array( '127.0.0.1', '::1', 'localhost' ), true );
	}

	/**
	 * Normalize a loopback callback path so a trailing slash is not a mismatch.
	 *
	 * `/callback` and `/callback/` reach the same local listener, and clients are
	 * inconsistent about which they send.
	 *
	 * @since 3.13.0
	 * @param string $path Path component.
	 * @return string
	 */
	private static function normalize_loopback_path( string $path ): string {
		$path = '' === $path ? '/' : $path;
		$trimmed = rtrim( $path, '/' );
		return '' === $trimmed ? '/' : $trimmed;
	}
}
