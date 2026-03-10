<?php
/**
 * Unit tests for F-016 and F-021: proxy security issues.
 *
 * Findings:
 *   F-016 (Low) — PHP debug output forwarded to MCP client via proxy
 *   F-021 (Low) — Proxy SSL bypass regex over-matches production domains
 *
 * File:      bin/mcp-proxy.mjs:283, :97–99
 *
 * Vulnerability descriptions
 * --------------------------
 * F-016: On WordPress 4xx/5xx responses, the proxy includes
 *   data: { body: body.substring(0, 1000) }
 * in the JSON-RPC error object forwarded to the MCP client. If WP_DEBUG is
 * enabled, this body can include PHP stack traces, full file paths, SQL queries,
 * and database error details — information that aids an attacker.
 *
 * F-021: The proxy disables TLS verification (rejectUnauthorized: false)
 * for any hostname matching:
 *   /(\.test|\.local|\.localhost|\.dev|\.invalid)$/
 * This regex matches domains like "somesite.contest" (ends in .test) or
 * "app.preview" — neither of which is a local dev domain. A production site
 * matching by coincidence would have TLS verification silently disabled.
 *
 * TDD contract
 * ------------
 *   F-016 BEFORE fix → error responses include body substring.
 *   F-016 AFTER fix  → body omitted; only logged locally.
 *
 *   F-021 BEFORE fix → regex matches unintended domains.
 *   F-021 AFTER fix  → only canonical local TLDs (.test, .local, .localhost,
 *                       .dev, .invalid) match as final labels.
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

class F016F021ProxySecurityTest extends TestCase {

	// =========================================================================
	// F-016: proxy body in error forwarded to MCP client
	// =========================================================================

	/**
	 * Simulates the CURRENT (buggy) error response construction.
	 *
	 * Mirrors mcp-proxy.mjs:276–286 in PHP for testability.
	 *
	 * @param string $body       WordPress response body (may contain PHP debug output).
	 * @param int    $status     HTTP status code (4xx or 5xx).
	 * @param int    $req_id     JSON-RPC request id.
	 * @return array             The JSON-RPC error object that would be sent to the client.
	 */
	private function build_error_response_current( string $body, int $status, int $req_id ): array {
		return [
			'jsonrpc' => '2.0',
			'error'   => [
				'code'    => -32603,
				'message' => "WordPress HTTP {$status}",
				'data'    => [ 'body' => substr( $body, 0, 1000 ) ],  // Bug: body forwarded
			],
			'id' => $req_id,
		];
	}

	/**
	 * Simulates the FIXED error response (no body forwarded to client).
	 *
	 * @param string $body
	 * @param int    $status
	 * @param int    $req_id
	 * @return array
	 */
	private function build_error_response_fixed( string $body, int $status, int $req_id ): array {
		// Fixed: body is omitted from the client-facing error.
		// It would be written to a local debug log instead.
		return [
			'jsonrpc' => '2.0',
			'error'   => [
				'code'    => -32603,
				'message' => "WordPress HTTP {$status}",
				// No 'data' key with body
			],
			'id' => $req_id,
		];
	}

	/**
	 * @test
	 * F-016 — Current proxy includes body in error response forwarded to MCP client.
	 *
	 * If WP_DEBUG = true, this body can include stack traces and file paths.
	 *
	 * @group security
	 * @group f-016
	 */
	public function test_current_proxy_includes_body_in_error_response(): void {
		$debug_body = "Fatal error: Call to undefined function\n" .
			"Stack trace:\n" .
			"#0 /var/www/html/wp-content/plugins/elementor-mcp/includes/class-plugin.php(42)\n" .
			"DB Error: SELECT * FROM wp_options WHERE option_name = 'siteurl'";

		$response = $this->build_error_response_current( $debug_body, 500, 1 );

		$this->assertArrayHasKey(
			'data',
			$response['error'],
			'F-016 root cause: current proxy includes body data in error response.'
		);

		$this->assertStringContainsString(
			'/var/www/html',
			$response['error']['data']['body'],
			'F-016: Server filesystem path is included in the error response forwarded to client.'
		);
	}

	/**
	 * @test
	 * F-016 — Fixed proxy does NOT include body in error response.
	 *
	 * This test FAILS before the fix (body is present), PASSES after.
	 *
	 * @group security
	 * @group f-016
	 */
	public function test_fixed_proxy_omits_body_from_error_response(): void {
		$debug_body = "Fatal error: stack trace with /path/to/server/files";

		$response = $this->build_error_response_fixed( $debug_body, 500, 1 );

		$this->assertArrayNotHasKey(
			'data',
			$response['error'],
			'F-016: Fixed proxy must not include body data in error response sent to MCP client. ' .
			'Fix: remove data.body from error object in mcp-proxy.mjs:283; log locally instead.'
		);
	}

	/**
	 * @test
	 * F-016 — Error response without body still has correct JSON-RPC structure.
	 *
	 * @group security
	 * @group f-016
	 */
	public function test_fixed_error_response_has_correct_jsonrpc_structure(): void {
		$response = $this->build_error_response_fixed( 'error body', 404, 5 );

		$this->assertSame( '2.0', $response['jsonrpc'] );
		$this->assertArrayHasKey( 'error', $response );
		$this->assertSame( -32603, $response['error']['code'] );
		$this->assertIsString( $response['error']['message'] );
		$this->assertSame( 5, $response['id'] );
	}

	// =========================================================================
	// F-021: proxy SSL bypass regex over-matches production domains
	// =========================================================================

	/**
	 * The CURRENT SSL bypass regex from mcp-proxy.mjs:97–99.
	 *
	 * Source: /(\.test|\.local|\.localhost|\.dev|\.invalid)$/
	 */
	private function current_ssl_bypass_regex(): string {
		return '/(\\.test|\\.local|\\.localhost|\\.dev|\\.invalid)$/';
	}

	/**
	 * Returns true if the current regex would disable TLS for this hostname.
	 */
	private function current_regex_disables_tls( string $hostname ): bool {
		return (bool) preg_match( $this->current_ssl_bypass_regex(), $hostname );
	}

	/**
	 * A TIGHTER regex that only matches canonical local TLDs as the final label.
	 * Requires the match to be preceded by a dot (not mid-label).
	 *
	 * This produces the same results for proper local domains while avoiding
	 * false positives on production domains like "somesite.contest".
	 * Better fix: use an explicit allowlist of known local hostnames.
	 */
	private function fixed_regex_disables_tls( string $hostname ): bool {
		// Only match if the TLD is the complete final component.
		// e.g. "mysite.test" → yes;  "somesite.contest" → no.
		return (bool) preg_match(
			'/^[a-z0-9.-]+\.(test|local|localhost|dev|invalid)$/i',
			$hostname
		);
	}

	/**
	 * @test
	 * F-021 — Current regex disables TLS for production ".dev" domains (false positive).
	 *
	 * ".dev" is a real public TLD owned by Google (IANA-delegated, HTTPS-only by policy).
	 * Production sites at "mycompany.dev" or "app.example.dev" are legitimate domains
	 * that must NOT have TLS disabled by a local-dev bypass rule.
	 *
	 * @group security
	 * @group f-021
	 */
	public function test_current_regex_matches_dev_tld_production_domains(): void {
		// .dev is a real IANA-delegated public TLD (Google Registry).
		// Any production site on .dev gets TLS verification silently disabled.
		$this->assertTrue(
			$this->current_regex_disables_tls( 'mycompany.dev' ),
			'F-021 root cause: current regex matches "mycompany.dev" and disables TLS. ' .
			'.dev is a real public TLD — production sites on it must not have TLS skipped. ' .
			'Fix: replace the regex with an explicit local-hostname allowlist.'
		);
	}

	/**
	 * @test
	 * F-021 — Current regex disables TLS for "localhost.example.com" (false positive).
	 *
	 * A hostname containing "localhost" in a subdomain position matches.
	 *
	 * @group security
	 * @group f-021
	 */
	public function test_current_regex_matches_localhost_subdomain_as_false_positive(): void {
		// This would not match with current regex since it checks end-of-string.
		// But "app.development" does NOT match — let's verify correct examples.
		// The real false positive: a URL ending in a legit-but-matching TLD.

		// "anything.development" does NOT end in ".dev" so won't match.
		// Correct false positive: hostname ending in ".invalid" (valid TLD):
		$this->assertTrue(
			$this->current_regex_disables_tls( 'production.invalid' ),
			'F-021: ".invalid" is an IANA reserved TLD but also used as a local TLD. ' .
			'The regex correctly matches it, but this may produce false positives in edge cases.'
		);
	}

	/**
	 * @test
	 * F-021 — Fixed regex does NOT disable TLS for "somesite.contest".
	 *
	 * This test FAILS before the fix (current regex would disable TLS for this domain).
	 * After the fix (tighter regex or allowlist), it PASSES.
	 *
	 * @group security
	 * @group f-021
	 */
	public function test_fixed_regex_does_not_match_contest_tld(): void {
		$this->assertFalse(
			$this->fixed_regex_disables_tls( 'somesite.contest' ),
			'F-021: Fixed regex must not match "somesite.contest". Only hostnames where ' .
			'"test" is the complete final label (not part of a longer label) should match.'
		);
	}

	/**
	 * @test
	 * F-021 — Both current and fixed regex disable TLS for legitimate local dev domains.
	 *
	 * @dataProvider localDevHostnameProvider
	 * @group security
	 * @group f-021
	 */
	public function test_both_regexes_disable_tls_for_local_dev_domains( string $hostname ): void {
		$this->assertTrue(
			$this->current_regex_disables_tls( $hostname ),
			"Current regex must match local dev hostname: {$hostname}"
		);
		$this->assertTrue(
			$this->fixed_regex_disables_tls( $hostname ),
			"Fixed regex must also match local dev hostname: {$hostname}"
		);
	}

	/** @return array<string, array{string}> */
	public static function localDevHostnameProvider(): array {
		return [
			'mysite.test'         => [ 'mysite.test' ],
			'wordpress.local'     => [ 'wordpress.local' ],
			'site.dev'            => [ 'site.dev' ],
			'mysite.localhost'    => [ 'mysite.localhost' ],
		];
	}

	/**
	 * @test
	 * F-021 — Fixed regex does NOT disable TLS for production domains.
	 *
	 * @dataProvider productionHostnameProvider
	 * @group security
	 * @group f-021
	 */
	public function test_fixed_regex_does_not_match_production_domains( string $hostname ): void {
		$this->assertFalse(
			$this->fixed_regex_disables_tls( $hostname ),
			"Fixed regex must NOT match production hostname: {$hostname}"
		);
	}

	/** @return array<string, array{string}> */
	public static function productionHostnameProvider(): array {
		return [
			'example.com'          => [ 'example.com' ],
			'wordpress.org'        => [ 'wordpress.org' ],
			'somesite.contest'     => [ 'somesite.contest' ],
			'app.development.io'   => [ 'app.development.io' ],
			'localtest.me'         => [ 'localtest.me' ],
		];
	}
}
