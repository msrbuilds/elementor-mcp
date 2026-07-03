<?php
/**
 * Symmetric encryption for at-rest secrets (e.g. third-party API keys).
 *
 * Keys are derived per-site from the WordPress AUTH_KEY / SECURE_AUTH_KEY salts,
 * so a database dump alone never discloses a stored secret. Values are tagged
 * with a version prefix so plaintext (legacy / constant-provided) values are
 * transparently passed through on read.
 *
 * Prefers libsodium (bundled in PHP 7.2+), falling back to OpenSSL AES-256-GCM.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encrypt / decrypt short secrets at rest.
 *
 * @since 3.1.0
 */
class EMCP_Tools_Secret {

	/** Token prefix marking an EMCP-encrypted value. */
	const PREFIX = 'emcps1:';

	/**
	 * The 32-byte site-specific encryption key.
	 *
	 * @return string Raw 32-byte key.
	 */
	private static function key(): string {
		$material = ( defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : '' )
			. ( defined( 'SECURE_AUTH_KEY' ) ? (string) SECURE_AUTH_KEY : '' )
			. 'emcp-tools-secret-v1';

		if ( function_exists( 'sodium_crypto_generichash' ) ) {
			return sodium_crypto_generichash( $material, '', 32 );
		}
		return hash( 'sha256', $material, true );
	}

	/**
	 * Encrypt a plaintext secret. Empty input returns '' (nothing to store).
	 *
	 * @param string $plain Plaintext secret.
	 * @return string Opaque token (or plaintext if no crypto backend exists).
	 */
	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		$key = self::key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plain, $nonce, $key );
			return self::PREFIX . 's:' . base64_encode( $nonce . $cipher );
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( false !== $cipher ) {
				return self::PREFIX . 'o:' . base64_encode( $iv . $tag . $cipher );
			}
		}

		// No crypto backend available — store as-is rather than lose the key.
		return $plain;
	}

	/**
	 * Decrypt a token produced by encrypt(). A non-token (plaintext) value is
	 * returned unchanged so legacy/constant values keep working.
	 *
	 * @param string $token Stored value.
	 * @return string Plaintext, or '' when a real token fails to decrypt.
	 */
	public static function decrypt( string $token ): string {
		if ( ! self::is_encrypted( $token ) ) {
			return $token;
		}
		$body = substr( $token, strlen( self::PREFIX ) );
		$algo = substr( $body, 0, 2 );
		$data = base64_decode( substr( $body, 2 ), true );
		if ( false === $data || '' === $data ) {
			return '';
		}
		$key = self::key();

		if ( 's:' === $algo && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$nb = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
			if ( strlen( $data ) <= $nb ) {
				return '';
			}
			$plain = sodium_crypto_secretbox_open( substr( $data, $nb ), substr( $data, 0, $nb ), $key );
			return is_string( $plain ) ? $plain : '';
		}

		if ( 'o:' === $algo && function_exists( 'openssl_decrypt' ) ) {
			if ( strlen( $data ) <= 28 ) {
				return '';
			}
			$plain = openssl_decrypt( substr( $data, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $data, 0, 12 ), substr( $data, 12, 16 ) );
			return is_string( $plain ) ? $plain : '';
		}

		return '';
	}

	/**
	 * @param string $value Stored value.
	 * @return bool Whether the value is an EMCP-encrypted token.
	 */
	public static function is_encrypted( string $value ): bool {
		return 0 === strncmp( $value, self::PREFIX, strlen( self::PREFIX ) );
	}

	/**
	 * Decrypt when the value is a token; otherwise return it unchanged.
	 *
	 * @param string $value Stored value (encrypted token or plaintext).
	 * @return string Plaintext.
	 */
	public static function decrypt_if_needed( string $value ): string {
		return self::is_encrypted( $value ) ? self::decrypt( $value ) : $value;
	}
}
