<?php
/**
 * Front-end page performance audit via a loopback HTTP fetch.
 *
 * fetch() performs the HTTP request; analyze() is pure and parses a fetched
 * struct into findings. No JS execution — this is HTML/header analysis, not
 * Core Web Vitals.
 *
 * Ported from upstream msrbuilds/elementor-mcp (v3.0.0), adapted to this fork's
 * class/helper naming (the upstream rename to emcp-tools is not adopted). The
 * same-host-across-redirects SSRF hardening (upstream 0c53be2) is preserved.
 *
 * @package Elementor_MCP
 * @since   1.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 1.11.0
 */
class Elementor_MCP_Performance_Page_Audit {

	const FETCH_TIMEOUT     = 10;
	const MAX_HTML_BYTES    = 2097152; // 2 MB cap for parsing.
	const MAX_REDIRECTS     = 3;
	const RESPONSE_WARN_MS  = 800;
	const HTML_WARN_BYTES   = 512000;  // 500 KB of HTML.
	const RENDER_BLOCK_WARN = 5;
	const ASSET_SAMPLE_CAP  = 20;

	/**
	 * Perform the loopback fetch and normalize the response.
	 *
	 * Redirects are followed manually (never by wp_remote_get) so that every hop
	 * is re-checked against the origin host — an open redirect to another host is
	 * refused instead of fetched (SSRF guard).
	 *
	 * @param string $url     Same-host URL (validated by the Analyzer).
	 * @param int    $timeout Seconds.
	 * @return array { ok, status_code, response_ms, total_bytes, headers, body, error, host }
	 */
	public function fetch( string $url, int $timeout = self::FETCH_TIMEOUT ): array {
		$origin_host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$origin_url  = $url;
		$current     = $url;
		$start       = microtime( true );

		for ( $hop = 0; $hop <= self::MAX_REDIRECTS; $hop++ ) {
			$res = wp_remote_get( $current, $this->request_args( $timeout ) );

			if ( is_wp_error( $res ) ) {
				$elapsed = (int) round( ( microtime( true ) - $start ) * 1000 );
				return array(
					'ok' => false, 'status_code' => 0, 'response_ms' => $elapsed, 'total_bytes' => 0,
					'headers' => array(), 'body' => '', 'error' => $res->get_error_message(), 'host' => $origin_host,
				);
			}

			$status   = (int) wp_remote_retrieve_response_code( $res );
			$location = (string) wp_remote_retrieve_header( $res, 'location' );

			if ( $status >= 300 && $status < 400 && '' !== $location ) {
				$next = $this->safe_redirect_target( $location, $current, $origin_url );
				if ( '' === $next ) {
					$elapsed = (int) round( ( microtime( true ) - $start ) * 1000 );
					return array(
						'ok' => false, 'status_code' => $status, 'response_ms' => $elapsed, 'total_bytes' => 0,
						'headers' => array(), 'body' => '', 'error' => 'offsite_redirect', 'host' => $origin_host,
					);
				}
				$current = $next;
				continue;
			}

			// Terminal response.
			$elapsed = (int) round( ( microtime( true ) - $start ) * 1000 );
			$body    = (string) wp_remote_retrieve_body( $res );
			$bytes   = strlen( $body );
			$headers = $this->normalize_headers( wp_remote_retrieve_headers( $res ) );
			if ( strlen( $body ) > self::MAX_HTML_BYTES ) {
				$body = substr( $body, 0, self::MAX_HTML_BYTES );
			}

			return array(
				'ok'          => true,
				'status_code' => $status,
				'response_ms' => $elapsed,
				'total_bytes' => $bytes,
				'headers'     => $headers,
				'body'        => $body,
				'error'       => null,
				'host'        => $origin_host,
			);
		}

		$elapsed = (int) round( ( microtime( true ) - $start ) * 1000 );
		return array(
			'ok' => false, 'status_code' => 0, 'response_ms' => $elapsed, 'total_bytes' => 0,
			'headers' => array(), 'body' => '', 'error' => 'too_many_redirects', 'host' => $origin_host,
		);
	}

	/**
	 * Build the wp_remote_get() args for a loopback hop.
	 *
	 * The Range header bounds the download at the request level: a cooperating
	 * same-host server returns only the first MAX_HTML_BYTES instead of a huge body
	 * (e.g. a giant page or a same-host upload), avoiding memory exhaustion. Servers
	 * that ignore Range still return the full body, which the MAX_HTML_BYTES
	 * substring cap in fetch() truncates before parsing (the backstop).
	 *
	 * @param int $timeout Seconds.
	 * @return array
	 */
	public function request_args( int $timeout ): array {
		return array(
			'timeout'     => $timeout,
			'redirection' => 0,
			'user-agent'  => 'Elementor-MCP-Performance-Analyzer/' . ( defined( 'ELEMENTOR_MCP_VERSION' ) ? ELEMENTOR_MCP_VERSION : '0' ),
			'headers'     => array(
				'Range' => 'bytes=0-' . ( self::MAX_HTML_BYTES - 1 ),
			),
		);
	}

	/**
	 * Resolve a (possibly relative) Location against the current URL, then test
	 * whether it leaves the origin. Returns the absolute redirect URL when it stays
	 * on the SAME ORIGIN (scheme + host + port) as $origin_url, or '' when it is
	 * off-origin / unresolvable.
	 *
	 * Comparing the full origin (not just the host) is an SSRF guard: an open
	 * redirect to http://origin-host:8080/ or an http:// downgrade of an https site
	 * must be refused, not followed.
	 *
	 * @param string $location    The raw Location header value.
	 * @param string $current_url The URL that produced the redirect.
	 * @param string $origin_url  The origin URL the loopback must stay on.
	 * @return string
	 */
	public function safe_redirect_target( string $location, string $current_url, string $origin_url ): string {
		$location = trim( $location );
		if ( '' === $location ) {
			return '';
		}
		// Resolve relative Location against the current URL's scheme+host+port.
		if ( false === strpos( $location, '://' ) ) {
			$scheme = (string) wp_parse_url( $current_url, PHP_URL_SCHEME );
			$host   = (string) wp_parse_url( $current_url, PHP_URL_HOST );
			if ( '' === $host ) {
				return '';
			}
			$port      = wp_parse_url( $current_url, PHP_URL_PORT );
			$authority = ( null !== $port && '' !== $port ) ? $host . ':' . (int) $port : $host;
			$prefix    = $scheme ? $scheme . '://' . $authority : '//' . $authority;
			$location  = ( '/' === substr( $location, 0, 1 ) ) ? $prefix . $location : $prefix . '/' . ltrim( $location, '/' );
		}
		if ( ! $this->same_origin( $location, $origin_url ) ) {
			return '';
		}
		return $location;
	}

	/**
	 * Pure: does $url share the FULL origin (scheme + host + port) of $origin_url?
	 * Default ports (80/443) are normalized. Mirrors the Analyzer's same-origin
	 * SSRF guard so redirect hops cannot leave the site's origin.
	 *
	 * @param string $url
	 * @param string $origin_url
	 * @return bool
	 */
	public function same_origin( string $url, string $origin_url ): bool {
		$a = $this->origin_parts( $url );
		$b = $this->origin_parts( $origin_url );
		return null !== $a && null !== $b && $a === $b;
	}

	/**
	 * Pure: normalized { scheme, host, port } for $url, or null when it has no host.
	 *
	 * @param string $url
	 * @return array{scheme:string,host:string,port:int}|null
	 */
	private function origin_parts( string $url ): ?array {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return null;
		}
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$port   = wp_parse_url( $url, PHP_URL_PORT );
		$port   = ( null !== $port && '' !== $port ) ? (int) $port : ( 'https' === $scheme ? 443 : 80 );
		return array( 'scheme' => $scheme, 'host' => $host, 'port' => $port );
	}

	/**
	 * Parse a fetched struct into findings + a page_fetch meta block. Pure.
	 *
	 * @param array $fetched     Output of fetch().
	 * @param bool  $deep_assets Reserved for bounded asset-size sampling.
	 * @return array { findings: Finding[], page_fetch: array }
	 */
	public function analyze( array $fetched, bool $deep_assets ): array {
		$page_fetch = array(
			'ok'          => ! empty( $fetched['ok'] ),
			'status_code' => (int) ( $fetched['status_code'] ?? 0 ),
			'response_ms' => (int) ( $fetched['response_ms'] ?? 0 ),
			'total_bytes' => (int) ( $fetched['total_bytes'] ?? 0 ),
			'error'       => $fetched['error'] ?? null,
		);

		if ( empty( $fetched['ok'] ) ) {
			$findings = array(
				Elementor_MCP_Performance_Finding::make( 'page_fetch', 'page', 'Page fetch', 'warning', false, sprintf( 'Could not fetch the page: %s', (string) ( $fetched['error'] ?? 'unknown error' ) ), 'The loopback request failed (often a local firewall, DNS, or self-SSL issue). Server and database checks are still reported.' ),
			);
			return array( 'findings' => $findings, 'page_fetch' => $page_fetch );
		}

		$headers  = (array) ( $fetched['headers'] ?? array() );
		$body     = (string) ( $fetched['body'] ?? '' );
		$bytes    = (int) ( $fetched['total_bytes'] ?? 0 );
		$ms       = (int) ( $fetched['response_ms'] ?? 0 );
		$status   = (int) ( $fetched['status_code'] ?? 0 );
		$host     = (string) ( $fetched['host'] ?? '' );
		$findings = array();

		// HTTP status. 206 is a healthy response to our ranged loopback request
		// (the fetch sends a Range header), so treat it as a pass like 200.
		$findings[] = ( 200 === $status || 206 === $status )
			? Elementor_MCP_Performance_Finding::make( 'http_status', 'page', 'HTTP status', 'pass', $status, sprintf( 'Page returned HTTP %d.', $status ) )
			: Elementor_MCP_Performance_Finding::make( 'http_status', 'page', 'HTTP status', 'warning', $status, sprintf( 'Page returned HTTP %d.', $status ), 'A non-200 status means the analyzed URL is redirecting or erroring; verify the target.' );

		// Response time.
		$findings[] = ( $ms > self::RESPONSE_WARN_MS )
			? Elementor_MCP_Performance_Finding::make( 'response_time', 'page', 'Server response time', 'warning', $ms, sprintf( 'Full HTML response took %d ms.', $ms ), 'Add page caching (a cache plugin or server cache) so HTML is served without a full PHP/DB render.' )
			: Elementor_MCP_Performance_Finding::make( 'response_time', 'page', 'Server response time', 'pass', $ms, sprintf( 'Full HTML response took %d ms.', $ms ) );

		// HTML weight.
		$findings[] = ( $bytes > self::HTML_WARN_BYTES )
			? Elementor_MCP_Performance_Finding::make( 'html_size', 'page', 'HTML size', 'warning', $bytes, sprintf( 'The HTML document is %d KB.', (int) round( $bytes / 1024 ) ), 'Large HTML often means inlined data or huge page builders; trim markup and avoid inlining big payloads.' )
			: Elementor_MCP_Performance_Finding::make( 'html_size', 'page', 'HTML size', 'pass', $bytes, sprintf( 'The HTML document is %d KB.', (int) round( $bytes / 1024 ) ) );

		// Compression.
		$encoding   = strtolower( (string) ( $headers['content-encoding'] ?? '' ) );
		$findings[] = ( false !== strpos( $encoding, 'gzip' ) || false !== strpos( $encoding, 'br' ) )
			? Elementor_MCP_Performance_Finding::make( 'compression', 'page', 'Compression', 'pass', $encoding, sprintf( 'Response is compressed (%s).', $encoding ) )
			: Elementor_MCP_Performance_Finding::make( 'compression', 'page', 'Compression', 'warning', $encoding ?: 'none', 'Response is not gzip/brotli compressed.', 'Enable gzip or brotli at the server (or via a cache plugin) to cut transfer size.' );

		// Cache headers.
		$has_cache  = ! empty( $headers['cache-control'] ) || ! empty( $headers['expires'] ) || ! empty( $headers['x-cache'] );
		$findings[] = $has_cache
			? Elementor_MCP_Performance_Finding::make( 'cache_headers', 'page', 'Cache headers', 'pass', true, 'The page sends caching headers.' )
			: Elementor_MCP_Performance_Finding::make( 'cache_headers', 'page', 'Cache headers', 'warning', false, 'No Cache-Control / Expires headers on the HTML.', 'A page cache that emits Cache-Control lets browsers and CDNs reuse the response.' );

		// Asset / DOM analysis.
		$dom = $this->parse_dom( $body );
		if ( null !== $dom ) {
			$findings = array_merge( $findings, $this->asset_findings( $dom, $host ) );
		}

		return array( 'findings' => $findings, 'page_fetch' => $page_fetch );
	}

	// ---- DOM helpers --------------------------------------------------

	private function parse_dom( string $html ): ?\DOMDocument {
		if ( '' === trim( $html ) ) {
			return null;
		}
		$prev = libxml_use_internal_errors( true );
		$dom  = new \DOMDocument();
		$dom->loadHTML( $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		return $dom;
	}

	private function asset_findings( \DOMDocument $dom, string $host ): array {
		$links   = $dom->getElementsByTagName( 'link' );
		$scripts = $dom->getElementsByTagName( 'script' );
		$images  = $dom->getElementsByTagName( 'img' );

		$head_css      = 0;
		$css_total     = 0;
		$third_parties = array();

		foreach ( $links as $link ) {
			$rel = strtolower( (string) $link->getAttribute( 'rel' ) );
			if ( 'stylesheet' !== $rel ) {
				continue;
			}
			$css_total++;
			if ( $this->in_head( $link ) ) {
				$head_css++;
			}
			$this->track_third_party( (string) $link->getAttribute( 'href' ), $host, $third_parties );
		}

		$js_total  = 0;
		$sync_head = 0;
		foreach ( $scripts as $script ) {
			$src = (string) $script->getAttribute( 'src' );
			if ( '' === $src ) {
				continue; // inline.
			}
			$js_total++;
			$this->track_third_party( $src, $host, $third_parties );
			$is_async = $script->hasAttribute( 'async' ) || $script->hasAttribute( 'defer' );
			if ( ! $is_async && $this->in_head( $script ) ) {
				$sync_head++;
			}
		}

		$img_total = 0;
		$not_lazy  = 0;
		foreach ( $images as $img ) {
			$img_total++;
			if ( 'lazy' !== strtolower( (string) $img->getAttribute( 'loading' ) ) ) {
				$not_lazy++;
			}
		}

		$render_blocking = $head_css + $sync_head;

		$findings   = array();
		$findings[] = ( $render_blocking > self::RENDER_BLOCK_WARN )
			? Elementor_MCP_Performance_Finding::make( 'render_blocking', 'assets', 'Render-blocking resources', 'warning', $render_blocking, sprintf( '%d render-blocking resources in <head> (%d CSS, %d sync JS).', $render_blocking, $head_css, $sync_head ), 'Defer non-critical JS (async/defer) and combine or inline critical CSS to unblock first paint.' )
			: Elementor_MCP_Performance_Finding::make( 'render_blocking', 'assets', 'Render-blocking resources', 'info', $render_blocking, sprintf( '%d render-blocking resources in <head> (%d CSS, %d sync JS).', $render_blocking, $head_css, $sync_head ) );

		$findings[] = Elementor_MCP_Performance_Finding::make( 'asset_counts', 'assets', 'Asset counts', 'info', array( 'css' => $css_total, 'js' => $js_total, 'images' => $img_total ), sprintf( '%d CSS, %d JS, %d images referenced.', $css_total, $js_total, $img_total ) );

		$findings[] = ( $not_lazy > 0 )
			? Elementor_MCP_Performance_Finding::make( 'image_lazy_loading', 'assets', 'Image lazy-loading', 'info', $not_lazy, sprintf( '%d of %d images lack loading="lazy".', $not_lazy, $img_total ), 'Add loading="lazy" to below-the-fold images to defer offscreen downloads.' )
			: Elementor_MCP_Performance_Finding::make( 'image_lazy_loading', 'assets', 'Image lazy-loading', 'pass', 0, 'All images use lazy-loading (or there are none).' );

		$tp         = array_keys( $third_parties );
		$findings[] = ( count( $tp ) > 0 )
			? Elementor_MCP_Performance_Finding::make( 'third_party', 'assets', 'Third-party domains', 'info', $tp, sprintf( '%d third-party domain(s) referenced.', count( $tp ) ), 'Each extra domain adds DNS + connection cost; self-host fonts/scripts where practical.' )
			: Elementor_MCP_Performance_Finding::make( 'third_party', 'assets', 'Third-party domains', 'pass', array(), 'No third-party asset domains referenced.' );

		return $findings;
	}

	private function in_head( \DOMNode $node ): bool {
		for ( $p = $node->parentNode; null !== $p; $p = $p->parentNode ) {
			if ( 'head' === strtolower( $p->nodeName ) ) {
				return true;
			}
		}
		return false;
	}

	private function track_third_party( string $url, string $host, array &$acc ): void {
		$u = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( '' !== $u && $u !== $host ) {
			$acc[ $u ] = true;
		}
	}

	private function normalize_headers( $headers ): array {
		$out = array();
		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$headers = $headers->getAll();
		}
		if ( ! is_array( $headers ) ) {
			return $out;
		}
		foreach ( $headers as $k => $v ) {
			$out[ strtolower( (string) $k ) ] = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
		}
		return $out;
	}
}
