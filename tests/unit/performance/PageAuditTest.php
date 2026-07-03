<?php
/**
 * @group performance
 * @package Elementor_MCP\Tests\Performance
 */
namespace Elementor_MCP\Tests\Performance;

use PHPUnit\Framework\TestCase;

class PageAuditTest extends TestCase {

	private \Elementor_MCP_Performance_Page_Audit $audit;

	protected function setUp(): void {
		$this->audit = new \Elementor_MCP_Performance_Page_Audit();
	}

	private function fetched( string $body, array $headers = array(), int $status = 200 ): array {
		return array(
			'ok'          => true,
			'status_code' => $status,
			'response_ms' => 120,
			'total_bytes' => strlen( $body ),
			'headers'     => $headers,
			'body'        => $body,
			'error'       => null,
			'host'        => 'example.com',
		);
	}

	private function status_of( array $result, string $id ): string {
		foreach ( $result['findings'] as $f ) {
			if ( $f['id'] === $id ) {
				return $f['status'];
			}
		}
		return 'MISSING';
	}

	/** @test */
	public function failed_fetch_degrades_gracefully(): void {
		$result = $this->audit->analyze( array(
			'ok' => false, 'status_code' => 0, 'response_ms' => 0, 'total_bytes' => 0,
			'headers' => array(), 'body' => '', 'error' => 'timeout', 'host' => 'example.com',
		), false );
		$this->assertFalse( $result['page_fetch']['ok'] );
		$this->assertSame( 'timeout', $result['page_fetch']['error'] );
		$this->assertSame( 'warning', $this->status_of( $result, 'page_fetch' ) );
	}

	/** @test */
	public function compression_detected_from_headers(): void {
		$pass = $this->audit->analyze( $this->fetched( '<html></html>', array( 'content-encoding' => 'gzip' ) ), false );
		$this->assertSame( 'pass', $this->status_of( $pass, 'compression' ) );
		$warn = $this->audit->analyze( $this->fetched( '<html></html>', array() ), false );
		$this->assertSame( 'warning', $this->status_of( $warn, 'compression' ) );
	}

	/** @test */
	public function cache_headers_detected(): void {
		$pass = $this->audit->analyze( $this->fetched( '<html></html>', array( 'cache-control' => 'max-age=600' ) ), false );
		$this->assertSame( 'pass', $this->status_of( $pass, 'cache_headers' ) );
		$warn = $this->audit->analyze( $this->fetched( '<html></html>', array() ), false );
		$this->assertSame( 'warning', $this->status_of( $warn, 'cache_headers' ) );
	}

	/** @test */
	public function render_blocking_counts_head_css_and_sync_js(): void {
		$body = '<html><head>'
			. '<link rel="stylesheet" href="/a.css">'
			. '<link rel="stylesheet" href="/b.css">'
			. '<script src="/sync.js"></script>'
			. '<script src="/async.js" defer></script>'
			. '</head><body></body></html>';
		$result = $this->audit->analyze( $this->fetched( $body ), false );
		$rb = null;
		foreach ( $result['findings'] as $f ) {
			if ( 'render_blocking' === $f['id'] ) { $rb = $f; }
		}
		$this->assertNotNull( $rb );
		// 2 head stylesheets + 1 sync script = 3; the deferred script does not count.
		$this->assertSame( 3, $rb['value'] );
	}

	/** @test */
	public function lazy_loading_flags_non_lazy_images(): void {
		$body = '<html><body><img src="/1.jpg"><img src="/2.jpg" loading="lazy"><img src="/3.jpg"></body></html>';
		$result = $this->audit->analyze( $this->fetched( $body ), false );
		$lazy = null;
		foreach ( $result['findings'] as $f ) {
			if ( 'image_lazy_loading' === $f['id'] ) { $lazy = $f; }
		}
		$this->assertNotNull( $lazy );
		$this->assertSame( 2, $lazy['value'] ); // two images without loading="lazy"
	}

	/** @test */
	public function non_200_status_is_flagged_warning(): void {
		$result = $this->audit->analyze( $this->fetched( '<html></html>', array(), 404 ), false );
		$this->assertSame( 'warning', $this->status_of( $result, 'http_status' ) );
	}

	/** @test */
	public function ranged_206_status_is_treated_as_pass(): void {
		// The loopback fetch sends a Range header, so a range-honoring server
		// returns 206 Partial Content for a healthy page — must not be a warning.
		$result = $this->audit->analyze( $this->fetched( '<html></html>', array(), 206 ), false );
		$this->assertSame( 'pass', $this->status_of( $result, 'http_status' ) );
	}

	/** @test */
	public function safe_redirect_target_allows_same_origin_absolute(): void {
		$next = $this->audit->safe_redirect_target( 'https://example.com/landing/', 'https://example.com/', 'https://example.com/' );
		$this->assertSame( 'https://example.com/landing/', $next );
	}

	/** @test */
	public function safe_redirect_target_rejects_offsite_absolute(): void {
		$next = $this->audit->safe_redirect_target( 'https://evil.test/x', 'https://example.com/', 'https://example.com/' );
		$this->assertSame( '', $next );
	}

	/** @test */
	public function safe_redirect_target_resolves_relative_to_same_origin(): void {
		$next = $this->audit->safe_redirect_target( '/landing/', 'https://example.com/start', 'https://example.com/' );
		$this->assertSame( 'https://example.com/landing/', $next );
	}

	/**
	 * A3 (SSRF mirror): a redirect that keeps the host but changes the port or
	 * downgrades the scheme leaves the site's origin and MUST be refused — the
	 * old host-only check would have followed it.
	 *
	 * @test
	 */
	public function safe_redirect_target_rejects_port_and_scheme_change_on_same_host(): void {
		$origin = 'https://example.com/';
		$this->assertSame( '', $this->audit->safe_redirect_target( 'http://example.com:8080/x', 'https://example.com/', $origin ) );
		$this->assertSame( '', $this->audit->safe_redirect_target( 'https://example.com:8080/x', 'https://example.com/', $origin ) );
		$this->assertSame( '', $this->audit->safe_redirect_target( 'http://example.com/x', 'https://example.com/', $origin ) );
		// Explicit default port still matches the origin.
		$this->assertSame( 'https://example.com:443/x', $this->audit->safe_redirect_target( 'https://example.com:443/x', 'https://example.com/', $origin ) );
	}

	/**
	 * A2 (memory): the loopback request must bound the download at the request
	 * level with a Range header so a cooperating same-host server returns only the
	 * first chunk instead of a huge body.
	 *
	 * @test
	 */
	public function request_args_set_a_range_header_bounding_the_download(): void {
		$args = $this->audit->request_args( 10 );
		$this->assertArrayHasKey( 'headers', $args );
		$this->assertArrayHasKey( 'Range', $args['headers'] );
		$this->assertSame( 'bytes=0-2097151', $args['headers']['Range'] ); // MAX_HTML_BYTES - 1
		$this->assertSame( 0, $args['redirection'] ); // redirects still followed manually
		$this->assertSame( 10, $args['timeout'] );
	}
}
