<?php
/**
 * Front-end page performance audit via a loopback HTTP fetch.
 *
 * fetch() performs the HTTP request; analyze() is pure and parses a fetched
 * struct into findings. No JS execution — this is HTML/header analysis, not
 * Core Web Vitals.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.0.0
 */
class EMCP_Tools_Performance_Page_Audit {

	const FETCH_TIMEOUT     = 10;
	const MAX_HTML_BYTES    = 2097152; // 2 MB cap for parsing.
	const RESPONSE_WARN_MS  = 800;
	const HTML_WARN_BYTES   = 512000;  // 500 KB of HTML.
	const RENDER_BLOCK_WARN = 5;
	const ASSET_SAMPLE_CAP  = 20;

	/**
	 * Perform the loopback fetch and normalize the response.
	 *
	 * @param string $url     Same-host URL (validated by the Analyzer).
	 * @param int    $timeout Seconds.
	 * @return array { ok, status_code, response_ms, total_bytes, headers, body, error, host }
	 */
	public function fetch( string $url, int $timeout = self::FETCH_TIMEOUT ): array {
		$host  = (string) wp_parse_url( $url, PHP_URL_HOST );
		$start = microtime( true );
		$res   = wp_remote_get(
			$url,
			array(
				'timeout'     => $timeout,
				'redirection' => 3,
				'user-agent'  => 'EMCP-Performance-Analyzer/' . ( defined( 'EMCP_TOOLS_VERSION' ) ? EMCP_TOOLS_VERSION : '0' ),
			)
		);
		$elapsed = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $res ) ) {
			return array(
				'ok' => false, 'status_code' => 0, 'response_ms' => $elapsed, 'total_bytes' => 0,
				'headers' => array(), 'body' => '', 'error' => $res->get_error_message(), 'host' => $host,
			);
		}

		$body    = (string) wp_remote_retrieve_body( $res );
		$bytes   = strlen( $body );
		$headers = $this->normalize_headers( wp_remote_retrieve_headers( $res ) );
		if ( strlen( $body ) > self::MAX_HTML_BYTES ) {
			$body = substr( $body, 0, self::MAX_HTML_BYTES );
		}

		return array(
			'ok'          => true,
			'status_code' => (int) wp_remote_retrieve_response_code( $res ),
			'response_ms' => $elapsed,
			'total_bytes' => $bytes,
			'headers'     => $headers,
			'body'        => $body,
			'error'       => null,
			'host'        => $host,
		);
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
				EMCP_Tools_Performance_Finding::make( 'page_fetch', 'page', 'Page fetch', 'warning', false, sprintf( 'Could not fetch the page: %s', (string) ( $fetched['error'] ?? 'unknown error' ) ), 'The loopback request failed (often a local firewall, DNS, or self-SSL issue). Server and database checks are still reported.' ),
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

		// HTTP status.
		$findings[] = ( 200 === $status )
			? EMCP_Tools_Performance_Finding::make( 'http_status', 'page', 'HTTP status', 'pass', $status, 'Page returned HTTP 200.' )
			: EMCP_Tools_Performance_Finding::make( 'http_status', 'page', 'HTTP status', 'warning', $status, sprintf( 'Page returned HTTP %d.', $status ), 'A non-200 status means the analyzed URL is redirecting or erroring; verify the target.' );

		// Response time.
		$findings[] = ( $ms > self::RESPONSE_WARN_MS )
			? EMCP_Tools_Performance_Finding::make( 'response_time', 'page', 'Server response time', 'warning', $ms, sprintf( 'Full HTML response took %d ms.', $ms ), 'Add page caching (a cache plugin or server cache) so HTML is served without a full PHP/DB render.' )
			: EMCP_Tools_Performance_Finding::make( 'response_time', 'page', 'Server response time', 'pass', $ms, sprintf( 'Full HTML response took %d ms.', $ms ) );

		// HTML weight.
		$findings[] = ( $bytes > self::HTML_WARN_BYTES )
			? EMCP_Tools_Performance_Finding::make( 'html_size', 'page', 'HTML size', 'warning', $bytes, sprintf( 'The HTML document is %d KB.', (int) round( $bytes / 1024 ) ), 'Large HTML often means inlined data or huge page builders; trim markup and avoid inlining big payloads.' )
			: EMCP_Tools_Performance_Finding::make( 'html_size', 'page', 'HTML size', 'pass', $bytes, sprintf( 'The HTML document is %d KB.', (int) round( $bytes / 1024 ) ) );

		// Compression.
		$encoding   = strtolower( (string) ( $headers['content-encoding'] ?? '' ) );
		$findings[] = ( false !== strpos( $encoding, 'gzip' ) || false !== strpos( $encoding, 'br' ) )
			? EMCP_Tools_Performance_Finding::make( 'compression', 'page', 'Compression', 'pass', $encoding, sprintf( 'Response is compressed (%s).', $encoding ) )
			: EMCP_Tools_Performance_Finding::make( 'compression', 'page', 'Compression', 'warning', $encoding ?: 'none', 'Response is not gzip/brotli compressed.', 'Enable gzip or brotli at the server (or via a cache plugin) to cut transfer size.' );

		// Cache headers.
		$has_cache  = ! empty( $headers['cache-control'] ) || ! empty( $headers['expires'] ) || ! empty( $headers['x-cache'] );
		$findings[] = $has_cache
			? EMCP_Tools_Performance_Finding::make( 'cache_headers', 'page', 'Cache headers', 'pass', true, 'The page sends caching headers.' )
			: EMCP_Tools_Performance_Finding::make( 'cache_headers', 'page', 'Cache headers', 'warning', false, 'No Cache-Control / Expires headers on the HTML.', 'A page cache that emits Cache-Control lets browsers and CDNs reuse the response.' );

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
			? EMCP_Tools_Performance_Finding::make( 'render_blocking', 'assets', 'Render-blocking resources', 'warning', $render_blocking, sprintf( '%d render-blocking resources in <head> (%d CSS, %d sync JS).', $render_blocking, $head_css, $sync_head ), 'Defer non-critical JS (async/defer) and combine or inline critical CSS to unblock first paint.' )
			: EMCP_Tools_Performance_Finding::make( 'render_blocking', 'assets', 'Render-blocking resources', 'info', $render_blocking, sprintf( '%d render-blocking resources in <head> (%d CSS, %d sync JS).', $render_blocking, $head_css, $sync_head ) );

		$findings[] = EMCP_Tools_Performance_Finding::make( 'asset_counts', 'assets', 'Asset counts', 'info', array( 'css' => $css_total, 'js' => $js_total, 'images' => $img_total ), sprintf( '%d CSS, %d JS, %d images referenced.', $css_total, $js_total, $img_total ) );

		$findings[] = ( $not_lazy > 0 )
			? EMCP_Tools_Performance_Finding::make( 'image_lazy_loading', 'assets', 'Image lazy-loading', 'info', $not_lazy, sprintf( '%d of %d images lack loading="lazy".', $not_lazy, $img_total ), 'Add loading="lazy" to below-the-fold images to defer offscreen downloads.' )
			: EMCP_Tools_Performance_Finding::make( 'image_lazy_loading', 'assets', 'Image lazy-loading', 'pass', 0, 'All images use lazy-loading (or there are none).' );

		$tp         = array_keys( $third_parties );
		$findings[] = ( count( $tp ) > 0 )
			? EMCP_Tools_Performance_Finding::make( 'third_party', 'assets', 'Third-party domains', 'info', $tp, sprintf( '%d third-party domain(s) referenced.', count( $tp ) ), 'Each extra domain adds DNS + connection cost; self-host fonts/scripts where practical.' )
			: EMCP_Tools_Performance_Finding::make( 'third_party', 'assets', 'Third-party domains', 'pass', array(), 'No third-party asset domains referenced.' );

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
