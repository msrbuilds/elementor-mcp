<?php
/**
 * Connection-tab client registry.
 * @group admin
 * @package EMCP_Tools\Tests\Admin
 */
namespace EMCP_Tools\Tests\Admin;

use PHPUnit\Framework\TestCase;

class ConnectionClientsTest extends TestCase {

	/** @test */
	public function lists_the_seven_clients_in_order(): void {
		$ids = array_map(
			static function ( $c ) { return $c['id']; },
			\EMCP_Tools_Admin::connection_clients()
		);
		$this->assertSame(
			array( 'claude-desktop', 'claude-code', 'cursor', 'codex', 'windsurf', 'antigravity', 'mcp-remote' ),
			$ids
		);
	}

	/** @test */
	public function each_client_has_label_icon_and_methods(): void {
		foreach ( \EMCP_Tools_Admin::connection_clients() as $c ) {
			$this->assertArrayHasKey( 'label', $c );
			$this->assertNotSame( '', (string) $c['label'] );
			$this->assertArrayHasKey( 'icon', $c );
			$this->assertArrayHasKey( 'methods', $c );
			foreach ( array( 'bundle', 'cli', 'ai_prompt', 'json' ) as $k ) {
				$this->assertArrayHasKey( $k, $c['methods'], "client {$c['id']} missing methods.{$k}" );
			}
		}
	}

	/** @test */
	public function only_claude_desktop_offers_the_bundle(): void {
		$by_id = array();
		foreach ( \EMCP_Tools_Admin::connection_clients() as $c ) {
			$by_id[ $c['id'] ] = $c;
		}
		$this->assertTrue( $by_id['claude-desktop']['methods']['bundle'] );
		$this->assertFalse( $by_id['claude-code']['methods']['bundle'] );
		$this->assertFalse( $by_id['cursor']['methods']['bundle'] );
	}

	/** @test */
	public function cli_clients_carry_a_command_template(): void {
		$by_id = array();
		foreach ( \EMCP_Tools_Admin::connection_clients() as $c ) {
			$by_id[ $c['id'] ] = $c;
		}
		// Claude Code + Codex expose a CLI add command; others do not.
		$this->assertIsString( $by_id['claude-code']['methods']['cli'] );
		$this->assertStringContainsString( 'claude mcp add', $by_id['claude-code']['methods']['cli'] );
		$this->assertIsString( $by_id['codex']['methods']['cli'] );
		$this->assertNull( $by_id['windsurf']['methods']['cli'] );
		$this->assertNull( $by_id['mcp-remote']['methods']['cli'] );
	}
}
