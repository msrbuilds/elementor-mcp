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

	/* ---------------------------------------------------------------------
	 * Strict validation (added 3.2.0 for the AI Chat `web_fetch` tool).
	 *
	 * is_safe_remote_url() above leans on wp_http_validate_url() plus a single
	 * gethostbyname() lookup. That is adequate for sideloading a media URL, but
	 * it inspects only the FIRST A record and never an AAAA record, so a host
	 * publishing one public and one internal address slips through. It also
	 * permits port 8080 and URLs carrying credentials.
	 *
	 * validate() below is the stricter gate used when a URL's *contents* are
	 * fed back to a language model. Its resolver is injectable so the whole
	 * decision table is unit-testable with no network.
	 * ------------------------------------------------------------------- */

	/** Ports an ordinary web page is served on. Anything else is a service probe. */
	const ALLOWED_PORTS = array( 80, 443 );

	/**
	 * Blocked IPv4 CIDRs: unspecified, private, loopback, link-local (incl. the
	 * cloud-metadata address 169.254.169.254), CGNAT, multicast, reserved.
	 *
	 * @var array<int,array{0:string,1:int}>
	 */
	const BLOCKED_V4 = array(
		array( '0.0.0.0', 8 ),
		array( '10.0.0.0', 8 ),
		array( '100.64.0.0', 10 ),
		array( '127.0.0.0', 8 ),
		array( '169.254.0.0', 16 ),
		array( '172.16.0.0', 12 ),
		array( '192.168.0.0', 16 ),
		array( '224.0.0.0', 4 ),
		array( '240.0.0.0', 4 ),
	);

	/**
	 * Blocked IPv6 CIDRs: unspecified, loopback, unique-local, link-local.
	 *
	 * @var array<int,array{0:string,1:int}>
	 */
	const BLOCKED_V6 = array(
		array( '::', 128 ),
		array( '::1', 128 ),
		array( 'fc00::', 7 ),
		array( 'fe80::', 10 ),
	);

	/**
	 * Strictly validate a URL before its contents are fetched for a model.
	 *
	 * KNOWN LIMIT: WordPress's HTTP API connects by hostname, so a TOCTOU
	 * window remains between this check and the TCP connect (DNS rebinding).
	 * Re-validating every redirect hop and using a short timeout narrow it;
	 * closing it entirely needs CURLOPT_RESOLVE pinning.
	 *
	 * @since 3.2.0
	 * @param string        $url      Absolute http(s) URL.
	 * @param callable|null $resolver Optional `fn(string $host): string[]` returning IPs.
	 * @return string|\WP_Error The URL when safe, WP_Error otherwise.
	 */
	public static function validate( string $url, ?callable $resolver = null ) {
		$url   = trim( $url );
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return new \WP_Error( 'blocked_scheme', __( 'Only absolute http:// or https:// URLs can be fetched.', 'emcp-tools' ) );
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		if ( 'http' !== $scheme && 'https' !== $scheme ) {
			return new \WP_Error( 'blocked_scheme', __( 'Only absolute http:// or https:// URLs can be fetched.', 'emcp-tools' ) );
		}

		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return new \WP_Error( 'blocked_credentials', __( 'URLs containing credentials cannot be fetched.', 'emcp-tools' ) );
		}

		if ( isset( $parts['port'] ) && ! in_array( (int) $parts['port'], self::ALLOWED_PORTS, true ) ) {
			return new \WP_Error( 'blocked_port', __( 'Only ports 80 and 443 can be fetched.', 'emcp-tools' ) );
		}

		$host = self::normalize_host( (string) $parts['host'] );

		// An IP literal needs no DNS — check it directly.
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return self::ip_is_blocked( $host )
				? new \WP_Error( 'blocked_host', __( 'That address is on a private, loopback, or link-local network and cannot be fetched.', 'emcp-tools' ) )
				: $url;
		}

		$resolver = $resolver ?? array( __CLASS__, 'resolve_host' );
		$ips      = (array) call_user_func( $resolver, $host );

		if ( empty( $ips ) ) {
			return new \WP_Error( 'blocked_host', __( 'That host could not be resolved.', 'emcp-tools' ) );
		}

		// EVERY resolved address must be public: a host publishing one public
		// and one internal record must not slip through.
		foreach ( $ips as $ip ) {
			if ( self::ip_is_blocked( (string) $ip ) ) {
				return new \WP_Error( 'blocked_host', __( 'That host resolves to a private, loopback, or link-local address and cannot be fetched.', 'emcp-tools' ) );
			}
		}

		return $url;
	}

	/**
	 * Whether an IP address is outside the publicly routable internet. Fails
	 * closed: anything unparseable is treated as blocked.
	 *
	 * @since 3.2.0
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function ip_is_blocked( string $ip ): bool {
		$ip = self::normalize_host( $ip );

		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return self::in_any_cidr( $ip, self::BLOCKED_V4 );
		}

		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			// An IPv4-mapped address (::ffff:127.0.0.1) is an IPv4 address.
			$packed = @inet_pton( $ip );
			if ( false !== $packed && 16 === strlen( $packed ) && "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff" === substr( $packed, 0, 12 ) ) {
				return self::in_any_cidr( inet_ntop( substr( $packed, 12 ) ), self::BLOCKED_V4 );
			}
			return self::in_any_cidr( $ip, self::BLOCKED_V6 );
		}

		return true;
	}

	/**
	 * Resolve a host to all of its A + AAAA records.
	 *
	 * @since 3.2.0
	 * @param string $host Hostname.
	 * @return string[]
	 */
	public static function resolve_host( string $host ): array {
		$ips = array();

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS failure is handled by the empty check.
		$records = @dns_get_record( $host, DNS_A );
		if ( is_array( $records ) ) {
			foreach ( $records as $r ) {
				if ( ! empty( $r['ip'] ) ) {
					$ips[] = (string) $r['ip'];
				}
			}
		}

		if ( defined( 'DNS_AAAA' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS failure is handled by the empty check.
			$records6 = @dns_get_record( $host, DNS_AAAA );
			if ( is_array( $records6 ) ) {
				foreach ( $records6 as $r ) {
					if ( ! empty( $r['ipv6'] ) ) {
						$ips[] = (string) $r['ipv6'];
					}
				}
			}
		}

		// dns_get_record() can fail where gethostbynamel() succeeds.
		if ( empty( $ips ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS failure is handled by the caller.
			$fallback = @gethostbynamel( $host );
			if ( is_array( $fallback ) ) {
				$ips = $fallback;
			}
		}

		return $ips;
	}

	/**
	 * Strip the brackets wp_parse_url() keeps around an IPv6 host.
	 *
	 * @param string $host Host or IP.
	 * @return string
	 */
	private static function normalize_host( string $host ): string {
		$host = trim( $host );
		if ( '' !== $host && '[' === $host[0] && ']' === substr( $host, -1 ) ) {
			$host = substr( $host, 1, -1 );
		}
		return $host;
	}

	/**
	 * Whether an IP falls inside any of the given CIDRs.
	 *
	 * @param string                           $ip    IP address.
	 * @param array<int,array{0:string,1:int}> $cidrs Subnet list.
	 * @return bool
	 */
	private static function in_any_cidr( string $ip, array $cidrs ): bool {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Failure means "not an IP", handled below.
		$packed = @inet_pton( $ip );
		if ( false === $packed ) {
			return true; // Fail closed.
		}
		foreach ( $cidrs as $cidr ) {
			if ( self::in_cidr( $packed, $cidr[0], $cidr[1] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Binary prefix comparison, so IPv4 and IPv6 share one implementation.
	 *
	 * @param string $packed_ip Packed IP (inet_pton).
	 * @param string $subnet    Subnet base address.
	 * @param int    $bits      Prefix length.
	 * @return bool
	 */
	private static function in_cidr( string $packed_ip, string $subnet, int $bits ): bool {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Constant subnets, cannot fail.
		$packed_subnet = @inet_pton( $subnet );
		if ( false === $packed_subnet || strlen( $packed_ip ) !== strlen( $packed_subnet ) ) {
			return false;
		}

		$whole_bytes = intdiv( $bits, 8 );
		$rest_bits   = $bits % 8;

		if ( $whole_bytes > 0 && 0 !== substr_compare( $packed_ip, substr( $packed_subnet, 0, $whole_bytes ), 0, $whole_bytes ) ) {
			return false;
		}

		if ( 0 === $rest_bits ) {
			return true;
		}

		$mask = ~( ( 1 << ( 8 - $rest_bits ) ) - 1 ) & 0xFF;
		return ( ord( $packed_ip[ $whole_bytes ] ) & $mask ) === ( ord( $packed_subnet[ $whole_bytes ] ) & $mask );
	}
}
