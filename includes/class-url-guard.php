<?php
/**
 * SSRF-safe remote URL helper.
 *
 * Validates and downloads remote URLs while blocking requests that resolve to
 * private, reserved, or loopback addresses — preventing Server-Side Request
 * Forgery via the image/SVG sideload tools.
 *
 * @package EMCP_Tools
 * @since   1.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class EMCP_Tools_Url_Guard
 */
class EMCP_Tools_Url_Guard {

	/**
	 * Whether a URL is a safe http(s) target that does not resolve to a
	 * private, reserved, or loopback address.
	 *
	 * @since 1.9.1
	 *
	 * @param string $url The URL to validate.
	 * @return bool True if the URL is a public http(s) address.
	 */
	public static function is_safe_remote_url( string $url ): bool {
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return false;
		}

		if ( ! in_array( strtolower( $parsed['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}

		// wp_http_validate_url() rejects most private RFC1918 ranges, loopback,
		// and non-80/443/8080 ports — but NOT the link-local 169.254.0.0/16
		// range (which includes the cloud-metadata endpoint 169.254.169.254),
		// and it does not cover IPv6 internal addresses.
		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}

		// Resolve the host and reject any private/reserved/link-local IP that
		// core misses. filter_var()'s NO_PRIV_RANGE | NO_RES_RANGE flags cover
		// RFC1918, loopback, 0.0.0.0/8, 169.254.0.0/16, and the IPv6
		// equivalents (::1, fe80::/10, fc00::/7).
		$host = strtolower( trim( $parsed['host'], '[]' ) );
		$ip   = filter_var( $host, FILTER_VALIDATE_IP ) ? $host : gethostbyname( $host );

		// gethostbyname() returns the host unchanged when resolution fails.
		if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Downloads a remote URL to a temp file with SSRF protection.
	 *
	 * Validates the initial URL and forces WP_Http to re-validate every
	 * redirect hop against private/reserved hosts ( download_url() does not
	 * set reject_unsafe_urls on its own ).
	 *
	 * @since 1.9.1
	 *
	 * @param string $url     The URL to download.
	 * @param int    $timeout Timeout in seconds.
	 * @return string|\WP_Error Temp file path, or WP_Error on unsafe URL / failure.
	 */
	public static function safe_download( string $url, int $timeout = 30 ) {
		if ( ! self::is_safe_remote_url( $url ) ) {
			return new \WP_Error(
				'unsafe_url',
				__( 'The URL is not allowed (must be a public http or https address).', 'emcp-tools' )
			);
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$harden = static function ( $args ) {
			// reject_unsafe_urls makes WP_Http re-validate every redirect hop
			// against private/reserved ranges; capping redirection limits the
			// window for redirect-based SSRF.
			$args['reject_unsafe_urls'] = true;
			$args['redirection']        = min( 2, (int) ( $args['redirection'] ?? 5 ) );
			return $args;
		};
		add_filter( 'http_request_args', $harden );

		$tmp_file = download_url( $url, $timeout );

		remove_filter( 'http_request_args', $harden );

		return $tmp_file;
	}
}
