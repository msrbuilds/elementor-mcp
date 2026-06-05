<?php
/**
 * Authenticated symmetric encryption for stored secrets (AI Chat Anthropic key,
 * and reused by the future BYO-key AI Copy/Image tools).
 *
 * Uses AES-256-GCM (authenticated — the tag makes tampering detectable) with a
 * key derived from the site's AUTH_KEY salt. Ciphertext layout (base64): 12-byte
 * IV ‖ 16-byte tag ‖ ciphertext.
 *
 * @package EMCP_Tools
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AES-256-GCM encrypt/decrypt for at-rest secrets.
 *
 * @since 2.2.0
 */
class EMCP_Tools_Key_Crypto {

	const CIPHER       = 'aes-256-gcm';
	const IV_LEN       = 12;
	const TAG_LEN      = 16;
	const FALLBACK_OPT = 'emcp_tools_key_fallback';

	/**
	 * Encrypts a plaintext secret. Returns base64( iv ‖ tag ‖ ciphertext ),
	 * or '' on failure.
	 *
	 * @since 2.2.0
	 *
	 * @param string $plaintext The secret to encrypt.
	 * @return string
	 */
	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext || ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}
		$key = self::derive_key();
		$iv  = random_bytes( self::IV_LEN );
		$tag = '';
		$ct  = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $ct ) {
			return '';
		}
		return base64_encode( $iv . $tag . $ct );
	}

	/**
	 * Decrypts a value produced by encrypt(). Returns '' on any failure
	 * (including a tampered tag).
	 *
	 * @since 2.2.0
	 *
	 * @param string $ciphertext The base64 blob from encrypt().
	 * @return string
	 */
	public static function decrypt( string $ciphertext ): string {
		if ( '' === $ciphertext || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw = base64_decode( $ciphertext, true );
		if ( false === $raw || strlen( $raw ) <= ( self::IV_LEN + self::TAG_LEN ) ) {
			return '';
		}
		$iv  = substr( $raw, 0, self::IV_LEN );
		$tag = substr( $raw, self::IV_LEN, self::TAG_LEN );
		$ct  = substr( $raw, self::IV_LEN + self::TAG_LEN );
		$pt  = openssl_decrypt( $ct, self::CIPHER, $key = self::derive_key(), OPENSSL_RAW_DATA, $iv, $tag );
		return false === $pt ? '' : $pt;
	}

	/**
	 * Derives a 32-byte key from AUTH_KEY (or a stored random fallback on the
	 * rare install without salts).
	 *
	 * @since 2.2.0
	 *
	 * @return string Raw 32-byte key.
	 */
	private static function derive_key(): string {
		$secret = defined( 'AUTH_KEY' ) && '' !== AUTH_KEY ? AUTH_KEY : '';
		if ( '' === $secret ) {
			$secret = get_option( self::FALLBACK_OPT );
			if ( ! $secret ) {
				$secret = bin2hex( random_bytes( 32 ) );
				update_option( self::FALLBACK_OPT, $secret, false );
			}
		}
		return hash( 'sha256', 'emcp-ai-chat:' . $secret, true );
	}
}
