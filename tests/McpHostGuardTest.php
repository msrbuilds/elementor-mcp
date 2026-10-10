<?php
/**
 * Host-mismatch guard (Bug report Issue 2).
 *
 * @package EMCP_Tools
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-mcp-host-guard.php';

class McpHostGuardTest extends TestCase {

	public function test_exact_match(): void {
		$this->assertTrue(
			EMCP_Tools_MCP_Host_Guard::host_matches( 'devis.debord-toiture.com', 'devis.debord-toiture.com' )
		);
	}

	public function test_www_and_case_and_port_tolerant(): void {
		$this->assertTrue(
			EMCP_Tools_MCP_Host_Guard::host_matches( 'www.Example.com:443', 'example.com' )
		);
	}

	public function test_different_domain_is_mismatch(): void {
		$this->assertFalse(
			EMCP_Tools_MCP_Host_Guard::host_matches( 'paleturquoise-sardine-346722.hostingersite.com', 'devis.debord-toiture.com' )
		);
	}

	public function test_empty_request_host_is_not_a_mismatch(): void {
		// A missing Host header must never brick the endpoint.
		$this->assertTrue( EMCP_Tools_MCP_Host_Guard::host_matches( '', 'example.com' ) );
	}

	public function test_is_mcp_route(): void {
		$this->assertTrue( EMCP_Tools_MCP_Host_Guard::is_mcp_route( '/mcp/emcp-tools-server' ) );
		$this->assertTrue( EMCP_Tools_MCP_Host_Guard::is_mcp_route( '/mcp/emcp-tools-server/messages' ) );
		$this->assertFalse( EMCP_Tools_MCP_Host_Guard::is_mcp_route( '/wp/v2/posts' ) );
		$this->assertFalse( EMCP_Tools_MCP_Host_Guard::is_mcp_route( '/mcp/other-server' ) );
	}
	public function test_malformed_host_is_rejected_without_normalizing_it_into_a_match(): void {
		foreach ( array( "example.com\r\n", 'example.com/path', 'example.com@evil.test', ' example.com' ) as $host ) {
			$this->assertFalse( EMCP_Tools_MCP_Host_Guard::host_matches( $host, 'example.com' ) );
		}
		$this->assertTrue( EMCP_Tools_MCP_Host_Guard::host_matches( '[::1]:443', '[::1]' ) );
	}
}
