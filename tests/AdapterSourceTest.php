<?php
/**
 * Which plugin's MCP Adapter copy is loaded (spec D3 of the Amelia integration): another plugin
 * (Amelia bundles 0.5.0 behind its own autoloader and loads McpAdapter before EMCP) can answer
 * first, and the Connection screen and Dashboard say so.
 *
 * @package EMCP_Tools\Tests
 */

use PHPUnit\Framework\TestCase;

require_once EMCP_TOOLS_DIR . 'includes/class-mcp-adapter-bootstrap.php';
require_once EMCP_TOOLS_DIR . 'includes/attention/interface-attention-check.php';
require_once EMCP_TOOLS_DIR . 'includes/attention/class-attention-checks.php';

final class AdapterSourceTest extends TestCase {

	const PLUGINS = 'F:/site/wp-content/plugins';

	public function test_our_bundled_copy_is_ours(): void {
		$s = EMCP_Tools_Adapter_Bootstrap::source_from_file( self::PLUGINS . '/elementor-mcp/vendor/wordpress/mcp-adapter/includes/Core/McpAdapter.php', self::PLUGINS, self::PLUGINS . '/elementor-mcp/vendor/wordpress/mcp-adapter/includes/', '0.6.1' );
		$this->assertSame( array( 'file' => 'elementor-mcp/vendor/wordpress/mcp-adapter/includes/Core/McpAdapter.php', 'version' => '0.6.1', 'ours' => true, 'plugin' => 'elementor-mcp' ), $s );
	}

	public function test_another_plugin_copy_names_the_plugin_and_version(): void {
		$s = EMCP_Tools_Adapter_Bootstrap::source_from_file( 'F:\\site\\wp-content\\plugins\\ameliabooking\\vendor\\wordpress\\mcp-adapter\\includes\\Core\\McpAdapter.php', self::PLUGINS, self::PLUGINS . '/elementor-mcp/vendor/wordpress/mcp-adapter/includes/', '0.5.0' );
		$this->assertSame( array( false, 'ameliabooking', '0.5.0' ), array( $s['ours'], $s['plugin'], $s['version'] ) );
		$this->assertSame( '', EMCP_Tools_Adapter_Bootstrap::source_from_file( '', self::PLUGINS, '', '' )['plugin'] );
	}

	public function test_the_bundled_version_is_read_from_the_bundled_file(): void {
		$this->assertSame( '0.6.1', EMCP_Tools_Adapter_Bootstrap::bundled_version() );
	}

	public function test_the_attention_item_shows_only_for_another_plugin_copy(): void {
		$ours = new EMCP_Tools_Adapter_Attention( array( 'file' => 'x', 'version' => '0.6.1', 'ours' => true, 'plugin' => 'elementor-mcp' ) );
		$this->assertFalse( $ours->applies() );
		$none = new EMCP_Tools_Adapter_Attention( array( 'file' => '', 'version' => '', 'ours' => false, 'plugin' => '' ) );
		$this->assertFalse( $none->applies() );
		$a = new EMCP_Tools_Adapter_Attention( array( 'file' => 'ameliabooking/vendor/x.php', 'version' => '0.5.0', 'ours' => false, 'plugin' => 'ameliabooking' ) );
		$this->assertTrue( $a->applies() );
		$this->assertSame( 'mcp-adapter-foreign', $a->id() );
		$this->assertSame( 'ameliabooking@0.5.0', $a->state() );
		$b = new EMCP_Tools_Adapter_Attention( array( 'file' => 'ameliabooking/vendor/x.php', 'version' => '0.5.1', 'ours' => false, 'plugin' => 'ameliabooking' ) );
		$this->assertNotSame( $a->state(), $b->state() );
		$item = $a->item();
		$this->assertStringContainsString( 'ameliabooking', $item['body'] );
		$this->assertStringContainsString( '0.5.0', $item['body'] );
		$this->assertStringNotContainsString( "\u{2014}", $item['title'] . $item['body'] . $item['action_label'] );
		$this->assertSame( 'warning', $item['severity'] );
	}
}
