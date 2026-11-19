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
	public static function is_safe_remote_url( string $url, ?callable $resolver = null ): bool {
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

		// Reject any private/reserved/link-local address core misses. This used
		// to be a single gethostbyname() plus filter_var() flags, which reads
		// only the FIRST A record and never an AAAA one, so a host publishing
		// one public and one internal address slipped through. host_is_blocked()
		// checks every record against the same CIDR list validate() uses.
		return ! self::host_is_blocked( (string) $parsed['host'], $resolver );
	}

	/**
	 * Does this host resolve to an address we refuse to talk to?
	 *
	 * The address half of validate(), without its scheme, port and credential
	 * rules, so a caller with a looser accept set can still get the full CIDR
	 * check. Sideloading a media URL is that caller: it allows port 8080, which
	 * validate() does not.
	 *
	 * Fails closed. A host that resolves to nothing cannot be shown to be
	 * public, so it is refused rather than passed through.
	 *
	 * @since 3.14.1
	 * @param string        $host     Hostname or IP literal, brackets optional.
	 * @param callable|null $resolver Optional `fn(string $host): string[]`.
	 * @return bool
	 */
	public static function host_is_blocked( string $host, ?callable $resolver = null ): bool {
		$host = self::normalize_host( $host );
		if ( '' === $host ) {
			return true;
		}

		// An IP literal needs no DNS.
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return self::ip_is_blocked( $host );
		}

		$resolver = $resolver ?? array( __CLASS__, 'resolve_host' );
		$ips      = (array) call_user_func( $resolver, $host );
		if ( empty( $ips ) ) {
			return true;
		}

		foreach ( $ips as $ip ) {
			if ( self::ip_is_blocked( (string) $ip ) ) {
				return true;
			}
		}

		return false;
	}

	/** Redirect hops a download may follow before giving up. */
	const MAX_DOWNLOAD_REDIRECTS = 3;

	/**
	 * Download a remote URL to a temp file, checking every redirect hop.
	 *
	 * Redirects are followed by hand rather than by WP_Http, because handing
	 * WP_Http `reject_unsafe_urls` only gets the hops core's
	 * wp_http_validate_url() blesses, and that does NOT reject the link-local
	 * 169.254.0.0/16 range the cloud-metadata endpoint lives in. So a URL that
	 * passed the strong check could redirect to 169.254.169.254 and have the
	 * response written into the Media Library. Now the strong check runs on the
	 * first URL and on every hop, and `reject_unsafe_urls` stays on underneath
	 * as a second opinion.
	 *
	 * Residual risk, stated plainly: the host is validated by name and then
	 * connected to by name, so a record that changes between the two answers
	 * (DNS rebinding) is not covered here. Closing that needs the connection
	 * pinned to the address we checked, which the AI Chat fetcher does with
	 * validate_pinned() and CURLOPT_RESOLVE.
	 *
	 * @since 1.9.1
	 *
	 * @param string $url     The URL to download.
	 * @param int    $timeout Timeout in seconds.
	 * @param array|null $headers Filled with the final response's headers (lowercase keys) on success.
	 * @return string|\WP_Error Temp file path, or WP_Error on unsafe URL / failure.
	 */
	public static function safe_download( string $url, int $timeout = 30, ?array &$headers = null ) {
		if ( ! self::is_safe_remote_url( $url ) ) {
			return new \WP_Error(
				'unsafe_url',
				__( 'The URL is not allowed. It must be a public http or https address that resolves.', 'emcp-tools' )
			);
		}

		$tmp = wp_tempnam( $url );
		if ( ! $tmp ) {
			return new \WP_Error( 'download_failed', __( 'Could not create a temporary file for the download.', 'emcp-tools' ) );
		}

		$current = $url;
		$hops    = 0;

		while ( true ) {
			$response = wp_remote_get(
				$current,
				array(
					'timeout'            => $timeout,
					'redirection'        => 0,    // Followed below, so each hop is re-checked.
					'reject_unsafe_urls' => true, // Second opinion, weaker than ours.
					'stream'             => true,
					'filename'           => $tmp,
				)
			);

			if ( is_wp_error( $response ) ) {
				self::discard( $tmp );
				return $response;
			}

			$status = (int) wp_remote_retrieve_response_code( $response );

			if ( $status >= 300 && $status < 400 ) {
				++$hops;
				if ( $hops > self::MAX_DOWNLOAD_REDIRECTS ) {
					self::discard( $tmp );
					return new \WP_Error( 'too_many_redirects', __( 'The URL redirected too many times.', 'emcp-tools' ) );
				}

				$next = self::redirect_target( (string) wp_remote_retrieve_header( $response, 'location' ), $current );
				if ( '' === $next || ! self::is_safe_remote_url( $next ) ) {
					self::discard( $tmp );
					return new \WP_Error(
						'unsafe_url',
						__( 'The URL redirected somewhere that is not allowed (redirects must stay on public http or https addresses).', 'emcp-tools' )
					);
				}

				$current = $next;
				continue;
			}

			if ( $status < 200 || $status >= 300 ) {
				self::discard( $tmp );
				return new \WP_Error(
					'download_failed',
					sprintf(
						/* translators: %d: HTTP status code. */
						__( 'The server returned HTTP %d.', 'emcp-tools' ),
						$status
					)
				);
			}

			$headers = array();
			foreach ( wp_remote_retrieve_headers( $response ) as $key => $value ) {
				$headers[ strtolower( (string) $key ) ] = is_array( $value ) ? (string) end( $value ) : (string) $value;
			}
			return $tmp;
		}
	}

	/**
	 * Absolute URL for a Location header, or '' when it cannot be resolved.
	 *
	 * A relative Location needs core's resolver; without it we refuse rather
	 * than guess, since guessing wrong here means fetching the wrong host.
	 *
	 * @since 3.14.1
	 * @param string $location Raw Location header.
	 * @param string $base     URL the redirect came from.
	 * @return string
	 */
	private static function redirect_target( string $location, string $base ): string {
		$location = trim( $location );
		if ( '' === $location ) {
			return '';
		}
		$parts = wp_parse_url( $location );
		if ( is_array( $parts ) && ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
			return $location;
		}

		return class_exists( 'WP_Http' ) ? (string) \WP_Http::make_absolute_url( $location, $base ) : '';
	}

	/**
	 * Delete a partial download. Streaming writes the body as it arrives, so a
	 * refused hop can already have bytes on disk.
	 *
	 * @since 3.14.1
	 * @param string $tmp Temp file path.
	 * @return void
	 */
	private static function discard( string $tmp ): void {
		if ( $tmp && file_exists( $tmp ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
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
	 * Callers that actually fetch the URL should use validate_pinned() instead,
	 * which returns the validated address so the connection can be pinned to it
	 * (CURLOPT_RESOLVE). Validating by name alone leaves a TOCTOU window: the
	 * name can resolve to a public address here and an internal one at connect
	 * time (DNS rebinding).
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
	 * Validate a URL AND return the public address the request must be pinned to.
	 *
	 * Closing the rebinding window needs the connection to go to an address we
	 * already validated, not to whatever DNS answers at connect time. Every
	 * returned address must be public (a mixed public/private answer set is
	 * rejected outright), and the caller pins the chosen one while keeping the
	 * original hostname for the Host header and TLS verification.
	 *
	 * @since 3.12.4
	 * @param string        $url      Absolute http(s) URL.
	 * @param callable|null $resolver Optional `fn(string $host): string[]`.
	 * @return array{url:string,host:string,ip:string,port:int}|\WP_Error
	 */
	public static function validate_pinned( string $url, ?callable $resolver = null ) {
		$checked = self::validate( $url, $resolver );
		if ( is_wp_error( $checked ) ) {
			return $checked;
		}
		$parts  = wp_parse_url( $checked );
		$host   = self::normalize_host( (string) ( $parts['host'] ?? '' ) );
		$scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'http' === $scheme ? 80 : 443 );

		// An IP literal is already the address we validated.
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return array( 'url' => $checked, 'host' => $host, 'ip' => $host, 'port' => $port );
		}

		$resolver = $resolver ?? array( __CLASS__, 'resolve_host' );
		$ips      = (array) call_user_func( $resolver, $host );
		if ( empty( $ips ) ) {
			return new \WP_Error( 'blocked_host', __( 'That host could not be resolved.', 'emcp-tools' ) );
		}
		// Re-check here too: this is the answer set we are about to pin to, and it
		// may differ from the one validate() saw a moment ago.
		foreach ( $ips as $ip ) {
			if ( self::ip_is_blocked( (string) $ip ) ) {
				return new \WP_Error( 'blocked_host', __( 'That host resolves to a private, loopback, or link-local address and cannot be fetched.', 'emcp-tools' ) );
			}
		}
		return array( 'url' => $checked, 'host' => $host, 'ip' => (string) reset( $ips ), 'port' => $port );
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
