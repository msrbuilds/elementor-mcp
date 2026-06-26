<?php
/**
 * @group performance
 * @package EMCP_Tools\Tests\Performance
 */
namespace EMCP_Tools\Tests\Performance;

use PHPUnit\Framework\TestCase;

class PageAuditTest extends TestCase {

	private \EMCP_Tools_Performance_Page_Audit $audit;

	protected function setUp(): void {
		$this->audit = new \EMCP_Tools_Performance_Page_Audit();
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
}
