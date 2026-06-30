<?php
/**
 * MCPB bundle manifest builder.
 * @group admin
 * @package EMCP_Tools\Tests\Admin
 */
namespace EMCP_Tools\Tests\Admin;

use PHPUnit\Framework\TestCase;

class McpbBuilderTest extends TestCase {

	private function manifest(): array {
		return \EMCP_Tools_Mcpb_Builder::build_manifest(
			'https://example.com',
			'admin',
			'abcd efgh ijkl mnop qrst uvwx'
		);
	}

	/** @test */
	public function manifest_names_the_server_emcp_tools(): void {
		$m = $this->manifest();
		$this->assertSame( 'emcp-tools', $m['name'] );
		$this->assertArrayHasKey( 'manifest_version', $m );
		$this->assertArrayHasKey( 'version', $m );
	}

	/** @test */
	public function manifest_uses_current_schema_with_entry_point(): void {
		$m = $this->manifest();
		$this->assertSame( '0.3', $m['manifest_version'] );
		$this->assertSame( 'node', $m['server']['type'] );
		$this->assertSame( 'server/index.js', $m['server']['entry_point'] );
		$this->assertArrayHasKey( 'name', $m['author'] );
	}

	/** @test */
	public function manifest_runs_the_bundled_proxy_via_node(): void {
		$cfg = $this->manifest()['server']['mcp_config'];
		// mcp_config now runs the bundled entry-point via node (no npx needed).
		$this->assertSame( 'node', $cfg['command'] );
		// args MUST use ${__dirname} so Node resolves the entry-point against the
		// extracted bundle dir, not Claude Desktop's CWD.
		$this->assertContains( '${__dirname}/server/index.js', $cfg['args'] );
	}

	/** @test */
	public function manifest_bakes_in_the_credentials(): void {
		$env = $this->manifest()['server']['mcp_config']['env'];
		$this->assertSame( 'https://example.com', $env['WP_URL'] );
		$this->assertSame( 'admin', $env['WP_USERNAME'] );
		$this->assertSame( 'abcd efgh ijkl mnop qrst uvwx', $env['WP_APP_PASSWORD'] );
		$this->assertSame( '2024-11-05', $env['MCP_PROTOCOL_VERSION'] );
	}

	/** @test */
	public function display_name_includes_the_site_host(): void {
		$this->assertStringContainsString( 'example.com', (string) $this->manifest()['display_name'] );
	}

	/** @test */
	public function build_server_js_converts_esm_to_cjs_and_embeds_credentials(): void {
		$fake_esm = "import { createInterface } from 'node:readline';\n"
			. "import { request as httpRequest } from 'node:http';\n"
			. "import { request as httpsRequest } from 'node:https';\n"
			. "import { appendFileSync } from 'node:fs';\n"
			. "const MCP_REST_PATH = '/mcp/emcp-tools-server';\n";

		$out = \EMCP_Tools_Mcpb_Builder::build_server_js(
			$fake_esm,
			array( 'WP_URL' => 'https://example.com', 'WP_USERNAME' => 'admin' )
		);

		$this->assertStringContainsString( "require('readline')", $out );
		$this->assertStringContainsString( "require('http')", $out );
		$this->assertStringContainsString( "require('https')", $out );
		$this->assertStringContainsString( "require('fs')", $out );
		$this->assertStringNotContainsString( 'import {', $out );
		$this->assertStringContainsString( 'process.env["WP_URL"]', $out );
		$this->assertStringContainsString( 'emcp-tools-server', $out );
		$this->assertStringStartsWith( "'use strict';", $out );
	}
}
