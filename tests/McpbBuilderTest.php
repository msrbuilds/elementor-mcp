<?php
/**
 * Tests for EMCP_Tools_Mcpb_Builder::build_manifest().
 *
 * Focus: the manifest `name` must be unique per site so installing a second
 * site's bundle in Claude Desktop does not overwrite the first (issue #86).
 *
 * Self-contained: requires the builder class and stubs wp_parse_url() (which
 * the public bootstrap doesn't define), so it doesn't touch the shared harness.
 *
 * @package EMCP_Tools
 */

use PHPUnit\Framework\TestCase;

require_once EMCP_TOOLS_DIR . 'includes/admin/class-mcpb-builder.php';

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

final class McpbBuilderTest extends TestCase {

	private function manifest( string $site_url ): array {
		return EMCP_Tools_Mcpb_Builder::build_manifest( $site_url, 'admin', 'app pass word' );
	}

	public function test_name_is_derived_from_host(): void {
		$m = $this->manifest( 'https://example.com' );
		$this->assertSame( 'emcp-tools-example-com', $m['name'] );
	}

	public function test_two_sites_get_distinct_names(): void {
		$a = $this->manifest( 'https://alpha.example.com' );
		$b = $this->manifest( 'https://beta.example.org' );

		// The actual regression from #86: different sites must not collide.
		$this->assertNotSame( $a['name'], $b['name'] );
		$this->assertSame( 'emcp-tools-alpha-example-com', $a['name'] );
		$this->assertSame( 'emcp-tools-beta-example-org', $b['name'] );
	}

	public function test_subdomain_and_multi_part_tld_are_slugged(): void {
		$m = $this->manifest( 'https://staging.example.co.uk' );
		$this->assertSame( 'emcp-tools-staging-example-co-uk', $m['name'] );
	}

	public function test_host_is_lowercased(): void {
		$m = $this->manifest( 'https://Example.COM' );
		$this->assertSame( 'emcp-tools-example-com', $m['name'] );
	}

	public function test_ip_host_ignores_port(): void {
		$m = $this->manifest( 'http://192.168.1.10:8080' );
		$this->assertSame( 'emcp-tools-192-168-1-10', $m['name'] );
	}

	public function test_falls_back_to_base_name_when_host_absent(): void {
		// Defensive: a hostless value yields the original stable name.
		$m = $this->manifest( '' );
		$this->assertSame( 'emcp-tools', $m['name'] );
	}

	public function test_display_name_remains_per_host(): void {
		$m = $this->manifest( 'https://example.com' );
		$this->assertSame( 'EMCP Tools — example.com', $m['display_name'] );
	}

	public function test_credentials_are_embedded_in_env(): void {
		$env = $this->manifest( 'https://example.com' )['server']['mcp_config']['env'];
		$this->assertSame( 'https://example.com', $env['WP_URL'] );
		$this->assertSame( 'admin', $env['WP_USERNAME'] );
		$this->assertSame( 'app pass word', $env['WP_APP_PASSWORD'] );
	}
}
