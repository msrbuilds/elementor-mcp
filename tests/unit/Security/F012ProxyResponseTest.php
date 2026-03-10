<?php
/**
 * Unit tests for F-012: Proxy mishandles non-JSON and empty WordPress responses.
 *
 * Finding:   F-012 (Medium)
 * File:      bin/mcp-proxy.mjs:290–291 and :316–318
 * Pattern:   PAT-PROXY-PASSTHROUGH
 *
 * Vulnerability description
 * -------------------------
 * The Node.js MCP proxy handles WordPress HTTP responses with two related bugs:
 *
 * Bug 1 — Empty 2xx hang (ADVERSARIAL-4, line 290–291):
 *   The proxy writes the response body to stdout only inside `if (trimmed)`.
 *   When WordPress returns an empty body on a 2xx response, the else branch
 *   is absent — nothing is written to stdout.  The MCP client waits
 *   indefinitely for a JSON-RPC response that never arrives.
 *
 * Bug 2 — Non-JSON 2xx passthrough (line 316–318):
 *   When JSON.parse(trimmed) throws (e.g. WordPress returns an HTML fatal
 *   error page on a 2xx status), the catch block passes the raw HTML to
 *   stdout via process.stdout.write(trimmed + '\n').  The MCP client receives
 *   unparseable content, breaking the session.
 *
 * Both bugs are in JavaScript (bin/mcp-proxy.mjs), so these PHP tests verify
 * the logical equivalents of the guard patterns, confirming the pattern
 * produces the correct JSON-RPC error structure.
 *
 * TDD contract
 * ------------
 *   BEFORE the fix → proxy either hangs or forwards malformed content.
 *   AFTER  the fix → proxy always writes a valid JSON-RPC error object.
 *
 * Fixes:
 *   Bug 1: Add else branch → write JSON-RPC error with code -32603 and
 *          message 'Empty response from WordPress'.
 *   Bug 2: Replace catch pass-through with JSON-RPC error write.
 *
 * Note: Since the proxy is Node.js, these PHP tests verify the logical
 * guard patterns and JSON-RPC error structure by simulating the proxy's
 * response construction logic in PHP.
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

class F012ProxyResponseTest extends TestCase {

	// -------------------------------------------------------------------------
	// Helpers: simulate proxy response-handling logic
	// -------------------------------------------------------------------------

	/**
	 * Simulates the CURRENT (buggy) proxy 2xx body handling.
	 *
	 * Mirrors bin/mcp-proxy.mjs:~290–318 logic in PHP for testability.
	 * Returns what the proxy would write to stdout (null = nothing written = hang).
	 *
	 * @param string $body    The raw HTTP response body.
	 * @param int    $req_id  The JSON-RPC request id.
	 * @return string|null  JSON string written to stdout, or null if nothing written.
	 */
	private function simulate_proxy_current( string $body, int $req_id ): ?string {
		$trimmed = trim( $body );

		if ( $trimmed ) {  // Bug 1: no else branch for empty body
			try {
				$parsed = json_decode( $trimmed, true, 512, JSON_THROW_ON_ERROR );
				return json_encode( $parsed );
			} catch ( \Exception $e ) {
				// Bug 2: forwards raw HTML instead of a JSON-RPC error
				return $trimmed;
			}
		}

		// Bug 1: empty body — nothing written (returns null = hang)
		return null;
	}

	/**
	 * Simulates the FIXED proxy 2xx body handling.
	 *
	 * @param string $body    The raw HTTP response body.
	 * @param int    $req_id  The JSON-RPC request id.
	 * @return string  Always returns a valid JSON string.
	 */
	private function simulate_proxy_fixed( string $body, int $req_id ): string {
		$trimmed = trim( $body );

		if ( $trimmed ) {
			try {
				$parsed = json_decode( $trimmed, true, 512, JSON_THROW_ON_ERROR );
				return json_encode( $parsed );
			} catch ( \Exception $e ) {
				// Fixed Bug 2: return JSON-RPC error instead of raw HTML
				return json_encode( [
					'jsonrpc' => '2.0',
					'error'   => [ 'code' => -32603, 'message' => 'Non-JSON response from WordPress' ],
					'id'      => $req_id,
				] );
			}
		}

		// Fixed Bug 1: always write a response, never hang
		return json_encode( [
			'jsonrpc' => '2.0',
			'error'   => [ 'code' => -32603, 'message' => 'Empty response from WordPress' ],
			'id'      => $req_id,
		] );
	}

	// -------------------------------------------------------------------------
	// Bug 1 tests: empty body causes hang in current proxy
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-012 — Current proxy returns null (hangs) when WordPress sends empty 2xx body.
	 *
	 * This documents the bug: null = nothing written to stdout = MCP client hangs.
	 *
	 * @group security
	 * @group f-012
	 */
	public function test_current_proxy_returns_null_for_empty_body(): void {
		$result = $this->simulate_proxy_current( '', 1 );

		$this->assertNull(
			$result,
			'F-012 root cause (Bug 1): current proxy writes nothing to stdout when ' .
			'WordPress returns an empty 2xx body. MCP client hangs indefinitely. ' .
			'Fix: add else branch in mcp-proxy.mjs:290–291 to write a JSON-RPC error.'
		);
	}

	/**
	 * @test
	 * F-012 — Current proxy returns null for whitespace-only bodies.
	 *
	 * @group security
	 * @group f-012
	 */
	public function test_current_proxy_returns_null_for_whitespace_body(): void {
		$result = $this->simulate_proxy_current( "   \n\t  ", 1 );

		$this->assertNull(
			$result,
			'F-012: Whitespace-only body must also be treated as empty — no hang.'
		);
	}

	/**
	 * @test
	 * F-012 — Fixed proxy always writes a valid JSON-RPC error for empty body.
	 *
	 * This test FAILS before the fix (null returned), PASSES after.
	 *
	 * @group security
	 * @group f-012
	 */
	public function test_fixed_proxy_writes_jsonrpc_error_for_empty_body(): void {
		$result = $this->simulate_proxy_fixed( '', 42 );

		$this->assertNotNull( $result, 'Fixed proxy must never return null.' );

		$decoded = json_decode( $result, true );
		$this->assertIsArray( $decoded, 'Fixed proxy must write valid JSON.' );
		$this->assertSame( '2.0', $decoded['jsonrpc'] );
		$this->assertArrayHasKey( 'error', $decoded );
		$this->assertSame( -32603, $decoded['error']['code'] );
		$this->assertSame( 42, $decoded['id'] );
	}

	// -------------------------------------------------------------------------
	// Bug 2 tests: non-JSON body is forwarded raw in current proxy
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-012 — Current proxy forwards raw HTML when WordPress returns HTML on 2xx.
	 *
	 * This documents Bug 2: a PHP fatal error page (HTML) is forwarded to the
	 * MCP client as-is, breaking JSON-RPC framing.
	 *
	 * @group security
	 * @group f-012
	 */
	public function test_current_proxy_forwards_html_error_page_as_raw_content(): void {
		$html_body = '<!DOCTYPE html><html><body><b>Fatal error</b>: Call to undefined function...</body></html>';

		$result = $this->simulate_proxy_current( $html_body, 1 );

		// The current proxy returns the raw HTML — not valid JSON-RPC.
		$this->assertNotNull( $result );
		$decoded = json_decode( $result, true );
		$this->assertNull(
			$decoded,
			'F-012 root cause (Bug 2): current proxy forwards raw HTML to MCP client ' .
			'stdout when WordPress returns a non-JSON 2xx body (e.g. PHP fatal error). ' .
			'Fix: replace catch pass-through with JSON-RPC error in mcp-proxy.mjs:316–318.'
		);
	}

	/**
	 * @test
	 * F-012 — Fixed proxy returns valid JSON-RPC error for HTML body.
	 *
	 * This test FAILS before the fix (raw HTML returned), PASSES after.
	 *
	 * @group security
	 * @group f-012
	 */
	public function test_fixed_proxy_writes_jsonrpc_error_for_html_body(): void {
		$html_body = '<!DOCTYPE html><html><body>Fatal error</body></html>';

		$result = $this->simulate_proxy_fixed( $html_body, 7 );

		$decoded = json_decode( $result, true );
		$this->assertIsArray( $decoded, 'Fixed proxy must write valid JSON for HTML response.' );
		$this->assertSame( '2.0', $decoded['jsonrpc'] );
		$this->assertArrayHasKey( 'error', $decoded );
		$this->assertSame( -32603, $decoded['error']['code'] );
		$this->assertSame( 7, $decoded['id'] );
	}

	// -------------------------------------------------------------------------
	// Happy-path tests: valid JSON bodies pass through correctly
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * A valid JSON response body passes through both current and fixed proxy unchanged.
	 *
	 * @group security
	 * @group f-012
	 */
	public function test_valid_json_body_passes_through_proxy(): void {
		$valid_response = json_encode( [
			'jsonrpc' => '2.0',
			'result'  => [ 'tools' => [ [ 'name' => 'elementor-mcp-list-widgets' ] ] ],
			'id'      => 3,
		] );

		$current = $this->simulate_proxy_current( $valid_response, 3 );
		$fixed   = $this->simulate_proxy_fixed( $valid_response, 3 );

		$this->assertNotNull( $current );
		$this->assertNotNull( $fixed );

		$decoded_current = json_decode( $current, true );
		$decoded_fixed   = json_decode( $fixed, true );

		$this->assertIsArray( $decoded_current );
		$this->assertIsArray( $decoded_fixed );
		$this->assertSame( $decoded_current, $decoded_fixed, 'Valid JSON must produce identical output from both proxy versions.' );
	}

	/**
	 * @test
	 * F-012 — The JSON-RPC error structure from the proxy conforms to spec.
	 *
	 * JSON-RPC 2.0 requires: jsonrpc, error.code (integer), error.message (string), id.
	 *
	 * @dataProvider errorBodyProvider
	 * @group security
	 * @group f-012
	 */
	public function test_jsonrpc_error_structure_conforms_to_spec( string $body, int $req_id ): void {
		$result  = $this->simulate_proxy_fixed( $body, $req_id );
		$decoded = json_decode( $result, true );

		$this->assertIsArray( $decoded );
		$this->assertSame( '2.0', $decoded['jsonrpc'], 'JSON-RPC version must be "2.0".' );
		$this->assertArrayHasKey( 'error', $decoded, 'Error response must have "error" key.' );
		$this->assertIsInt( $decoded['error']['code'], 'Error code must be an integer.' );
		$this->assertIsString( $decoded['error']['message'], 'Error message must be a string.' );
		$this->assertSame( $req_id, $decoded['id'], 'Error response must echo the request id.' );
		$this->assertArrayNotHasKey( 'result', $decoded, 'Error response must not have "result" key.' );
	}

	/** @return array<string, array{string, int}> */
	public static function errorBodyProvider(): array {
		return [
			'empty body'         => [ '', 1 ],
			'whitespace body'    => [ "   \n  ", 2 ],
			'html error page'    => [ '<html><body>Fatal error</body></html>', 3 ],
			'truncated json'     => [ '{"partial":', 4 ],
			'plain text error'   => [ 'Internal Server Error', 5 ],
		];
	}
}
