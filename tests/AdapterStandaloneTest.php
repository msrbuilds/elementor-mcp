<?php
/**
 * The MCP Adapter is a standalone wordpress.org plugin since 0.7 (slug mcp-adapter). When it is
 * active and at least as new as the copy EMCP bundles, EMCP serves the WP\MCP namespace from that
 * plugin instead of forcing its own copy, so plugins that need 0.7.x keep working and the
 * adapter's "another version is already loaded" notice does not fire.
 *
 * @package EMCP_Tools\Tests
 */

use PHPUnit\Framework\TestCase;

require_once EMCP_TOOLS_DIR . 'includes/class-mcp-adapter-bootstrap.php';

final class AdapterStandaloneTest extends TestCase {

	/** @var string */
	private $plugins;

	protected function setUp(): void {
		$plugins = sys_get_temp_dir() . '/emcp-adapter-' . bin2hex( random_bytes( 4 ) );
		mkdir( $plugins, 0777, true );
		// sys_get_temp_dir() can be an 8.3 short path on Windows; included files report the long one.
		$this->plugins = str_replace( '\\', '/', (string) realpath( $plugins ) );
	}

	protected function tearDown(): void {
		EMCP_Tools_Adapter_Bootstrap::use_adapter_dir( null );
		$this->remove( $this->plugins );
	}

	private function remove( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
		}
		rmdir( $dir );
	}

	/** A plugin folder shaped like the wordpress.org MCP Adapter. */
	private function adapter( string $folder, string $version, bool $vendor = true ): string {
		$root = $this->plugins . '/' . $folder;
		mkdir( $root . '/includes/Core', 0777, true );
		file_put_contents( $root . '/mcp-adapter.php', "<?php\n// Plugin Name: MCP Adapter\n" );
		file_put_contents( $root . '/includes/Core/McpAdapter.php', "<?php\nnamespace WP\\MCP\\Core;\nclass McpAdapterFixture {\n\tpublic const VERSION = '" . $version . "';\n}\n" );
		if ( $vendor ) {
			mkdir( $root . '/vendor', 0777, true );
			file_put_contents( $root . '/vendor/autoload_packages.php', "<?php\n" );
		}
		return $root . '/includes/';
	}

	public function test_finds_the_active_standalone_plugin_and_its_version(): void {
		$dir = $this->adapter( 'mcp-adapter', '0.7.1' );
		$found = EMCP_Tools_Adapter_Bootstrap::find_standalone( array( 'akismet/akismet.php', 'mcp-adapter/mcp-adapter.php' ), $this->plugins );
		$this->assertSame( array( 'plugin' => 'mcp-adapter', 'dir' => $dir, 'version' => '0.7.1' ), $found );
	}

	public function test_a_renamed_folder_from_a_zip_upload_is_found_too(): void {
		$dir = $this->adapter( 'mcp-adapter-0.7.1', '0.7.1' );
		$this->assertSame( $dir, EMCP_Tools_Adapter_Bootstrap::find_standalone( array( 'mcp-adapter-0.7.1/mcp-adapter.php' ), $this->plugins )['dir'] ?? '' );
	}

	public function test_inactive_incomplete_or_unrelated_plugins_are_not_the_standalone(): void {
		$this->adapter( 'mcp-adapter', '0.7.1' );
		$this->assertSame( array(), EMCP_Tools_Adapter_Bootstrap::find_standalone( array( 'akismet/akismet.php' ), $this->plugins ), 'installed but not active' );
		$this->assertSame( array(), EMCP_Tools_Adapter_Bootstrap::find_standalone( array( 'mcp-adapter.php' ), $this->plugins ), 'a single-file plugin is not the adapter' );
		$this->adapter( 'source-checkout', '0.7.1', false );
		$this->assertSame( array(), EMCP_Tools_Adapter_Bootstrap::find_standalone( array( 'source-checkout/mcp-adapter.php' ), $this->plugins ), 'a git checkout without composer install cannot load its schema package' );
		mkdir( $this->plugins . '/hollow', 0777, true );
		file_put_contents( $this->plugins . '/hollow/mcp-adapter.php', "<?php\n" );
		$this->assertSame( array(), EMCP_Tools_Adapter_Bootstrap::find_standalone( array( 'hollow/mcp-adapter.php' ), $this->plugins ), 'no adapter classes' );
		$this->assertSame( array(), EMCP_Tools_Adapter_Bootstrap::find_standalone( array( '../mcp-adapter/mcp-adapter.php', 'x/../mcp-adapter/mcp-adapter.php' ), $this->plugins ), 'no path tricks' );
	}

	public function test_the_version_is_read_from_a_class_root(): void {
		$this->assertSame( '0.7.1', EMCP_Tools_Adapter_Bootstrap::version_of( $this->adapter( 'mcp-adapter', '0.7.1' ) ) );
		$this->assertSame( '', EMCP_Tools_Adapter_Bootstrap::version_of( $this->plugins . '/missing/includes/' ) );
	}

	public function test_the_resolver_serves_the_namespace_from_the_official_plugin_only(): void {
		$dir = $this->adapter( 'mcp-adapter', '0.7.1' );
		mkdir( $dir . 'EmcpProbe', 0777, true );
		file_put_contents( $dir . 'EmcpProbe/FromStandalone.php', "<?php\nnamespace WP\\MCP\\EmcpProbe;\nclass FromStandalone {}\n" );
		file_put_contents( $dir . 'EmcpProbe/WithoutPlugin.php', "<?php\nnamespace WP\\MCP\\EmcpProbe;\nclass WithoutPlugin {}\n" );
		EMCP_Tools_Adapter_Bootstrap::use_adapter_dir( $dir );
		$this->assertSame( $dir, EMCP_Tools_Adapter_Bootstrap::adapter_dir() );
		EMCP_Tools_Adapter_Bootstrap::autoload_adapter( 'WP\\MCP\\EmcpProbe\\FromStandalone' );
		$this->assertTrue( class_exists( 'WP\\MCP\\EmcpProbe\\FromStandalone', false ) );
		$file = str_replace( '\\', '/', (string) ( new ReflectionClass( 'WP\\MCP\\EmcpProbe\\FromStandalone' ) )->getFileName() );
		$this->assertStringStartsWith( $dir, $file );
		// The adapter's own copy check compares McpAdapter::DIR with DIRECTORY_SEPARATOR.
		$this->assertStringStartsWith( dirname( $dir ) . '/', $file );

		EMCP_Tools_Adapter_Bootstrap::use_adapter_dir( '' );
		EMCP_Tools_Adapter_Bootstrap::autoload_adapter( 'WP\\MCP\\EmcpProbe\\WithoutPlugin' );
		$this->assertFalse( class_exists( 'WP\\MCP\\EmcpProbe\\WithoutPlugin', false ), 'nothing is served without the official plugin' );
		EMCP_Tools_Adapter_Bootstrap::autoload_adapter( 'Other\\Vendor\\Thing' );
		$this->assertFalse( class_exists( 'Other\\Vendor\\Thing', false ) );
	}

	public function test_the_bundled_fallback_is_0_7_2(): void {
		$this->assertSame( '0.7.2', EMCP_Tools_Adapter_Bootstrap::bundled_version() );
		$this->assertSame( EMCP_Tools_Adapter_Bootstrap::BUNDLED_VERSION, EMCP_Tools_Adapter_Bootstrap::bundled_version() );
		$this->assertFileExists( EMCP_TOOLS_DIR . 'vendor/wordpress/php-mcp-schema/src/Record.php', 'its schema package ships too' );
	}

	public function test_the_official_plugin_qualifies_from_the_minimum_version(): void {
		$this->assertTrue( EMCP_Tools_Adapter_Bootstrap::qualifies( '0.7.1' ) );
		$this->assertTrue( EMCP_Tools_Adapter_Bootstrap::qualifies( EMCP_Tools_Adapter_Bootstrap::MIN_VERSION ) );
		$this->assertFalse( EMCP_Tools_Adapter_Bootstrap::qualifies( '0.5.0' ) );
		$this->assertFalse( EMCP_Tools_Adapter_Bootstrap::qualifies( '' ) );
	}

	public function test_without_the_official_plugin_the_bundled_copy_serves(): void {
		EMCP_Tools_Adapter_Bootstrap::use_adapter_dir( null );
		$this->assertSame( EMCP_Tools_Adapter_Bootstrap::bundled_dir(), EMCP_Tools_Adapter_Bootstrap::adapter_dir(), 'no official plugin is active in the test site' );
		$this->assertFalse( EMCP_Tools_Adapter_Bootstrap::uses_official() );
	}

	public function test_banner_states(): void {
		require_once EMCP_TOOLS_DIR . 'includes/class-adapter-installer.php';
		$this->assertNull( EMCP_Tools_Adapter_Installer::banner( array( 'state' => 'ok', 'version' => '0.7.1', 'plugin' => '' ), true ) );

		// The built-in copy is a permanent fallback: no banner while it serves.
		$this->assertNull( EMCP_Tools_Adapter_Installer::banner( array( 'state' => 'missing', 'version' => '0.7.1', 'plugin' => '' ), true ) );
		$this->assertNull( EMCP_Tools_Adapter_Installer::banner( array( 'state' => 'inactive', 'version' => '0.7.1', 'plugin' => '' ), true ) );

		$other = EMCP_Tools_Adapter_Installer::banner( array( 'state' => 'missing', 'version' => '0.6.1', 'plugin' => 'elementor' ), true );
		$this->assertSame( array( 'info', 'Install and activate MCP Adapter', true ), array( $other['type'], $other['button'], $other['dismissible'] ) );
		$this->assertStringContainsString( 'elementor', $other['message'] );
		$this->assertStringNotContainsString( 'future version', $other['message'] );

		$old = EMCP_Tools_Adapter_Installer::banner( array( 'state' => 'old', 'version' => '0.5.0', 'plugin' => '' ), true );
		$this->assertSame( array( 'warning', 'Update MCP Adapter', true ), array( $old['type'], $old['button'], $old['dismissible'] ) );
		$this->assertStringContainsString( '0.5.0', $old['message'] );
		$this->assertStringContainsString( EMCP_Tools_Adapter_Bootstrap::MIN_VERSION, $old['message'] );

		$none = EMCP_Tools_Adapter_Installer::banner( array( 'state' => 'missing', 'version' => '', 'plugin' => '' ), false );
		$this->assertSame( array( 'error', false ), array( $none['type'], $none['dismissible'] ), 'no adapter at all: the MCP server is off' );

		foreach ( array( $other, $old, $none ) as $b ) {
			$this->assertStringNotContainsString( "\u{2014}", $b['message'] . $b['button'] );
		}
	}

	public function test_connection_offer_stays_available_as_an_option(): void {
		require_once EMCP_TOOLS_DIR . 'includes/class-adapter-installer.php';
		$this->assertNull( EMCP_Tools_Adapter_Installer::offer( array( 'state' => 'ok', 'version' => '0.7.1', 'plugin' => '' ), true ), 'the official plugin serves' );
		$this->assertSame(
			array( 'label' => 'Install and activate MCP Adapter', 'optional' => true ),
			EMCP_Tools_Adapter_Installer::offer( array( 'state' => 'missing', 'version' => '0.7.1', 'plugin' => '' ), true ),
			'the built-in copy serves: an option, not a request'
		);
		$this->assertSame( array( 'label' => 'Activate MCP Adapter', 'optional' => true ), EMCP_Tools_Adapter_Installer::offer( array( 'state' => 'inactive', 'version' => '0.7.1', 'plugin' => '' ), true ) );
		$this->assertFalse( EMCP_Tools_Adapter_Installer::offer( array( 'state' => 'old', 'version' => '0.5.0', 'plugin' => '' ), true )['optional'] );
		$this->assertFalse( EMCP_Tools_Adapter_Installer::offer( array( 'state' => 'missing', 'version' => '', 'plugin' => '' ), false )['optional'] );
		$this->assertFalse( EMCP_Tools_Adapter_Installer::offer( array( 'state' => 'missing', 'version' => '0.6.1', 'plugin' => 'elementor' ), true )['optional'] );
	}

	public function test_network_wide_activation_needs_network_plugin_rights(): void {
		require_once EMCP_TOOLS_DIR . 'includes/class-adapter-installer.php';
		$caps = $GLOBALS['emcp_test']['caps'];
		try {
			EMCP_Tools_Adapter_Installer::use_network_wide( true );
			// A site admin of a network where EMCP is network-activated.
			$GLOBALS['emcp_test']['caps'] = array( 'activate_plugins' );
			$this->assertFalse( EMCP_Tools_Adapter_Installer::can( 'inactive' ) );
			$GLOBALS['emcp_test']['caps'] = array( 'activate_plugins', 'manage_network_plugins' );
			$this->assertTrue( EMCP_Tools_Adapter_Installer::can( 'inactive' ) );
			// EMCP active on this site only: the site's own rights are enough.
			EMCP_Tools_Adapter_Installer::use_network_wide( false );
			$GLOBALS['emcp_test']['caps'] = array( 'activate_plugins' );
			$this->assertTrue( EMCP_Tools_Adapter_Installer::can( 'inactive' ) );
		} finally {
			$GLOBALS['emcp_test']['caps'] = $caps;
			EMCP_Tools_Adapter_Installer::use_network_wide( null );
		}
	}

	public function test_a_dismissal_lasts_until_the_state_changes(): void {
		require_once EMCP_TOOLS_DIR . 'includes/class-adapter-installer.php';
		$a = EMCP_Tools_Adapter_Installer::signature( array( 'state' => 'missing', 'plugin' => '' ) );
		$this->assertSame( $a, EMCP_Tools_Adapter_Installer::signature( array( 'state' => 'missing', 'plugin' => '', 'version' => '0.7.1' ) ) );
		$this->assertNotSame( $a, EMCP_Tools_Adapter_Installer::signature( array( 'state' => 'inactive', 'plugin' => '' ) ) );
		$this->assertNotSame( $a, EMCP_Tools_Adapter_Installer::signature( array( 'state' => 'missing', 'plugin' => 'elementor' ) ) );
	}

	public function test_the_adapter_is_protected_from_mcp_deactivate_and_delete(): void {
		require_once EMCP_TOOLS_DIR . 'includes/class-package-guard.php';
		$this->assertTrue( EMCP_Tools_Package_Guard::is_protected_plugin( 'mcp-adapter/mcp-adapter.php' ) );
	}
}
