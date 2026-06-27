<?php
/**
 * @group filesystem
 * @package EMCP_Tools\Tests\Filesystem
 */
namespace EMCP_Tools\Tests\Filesystem;

use PHPUnit\Framework\TestCase;

class FilesystemGuardTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		// A real temp fixture tree to act as the "WordPress install root".
		$this->root = sys_get_temp_dir() . '/emcp-fs-' . uniqid();
		mkdir( $this->root . '/wp-content/themes/x', 0777, true );
		file_put_contents( $this->root . '/wp-content/themes/x/style.css', "a\nb\nc\n" );
		file_put_contents( $this->root . '/wp-config.php', "<?php // secrets" );
		// A sibling file OUTSIDE the root (in its parent dir).
		file_put_contents( dirname( $this->root ) . '/emcp-outside.txt', 'nope' );
	}

	protected function tearDown(): void {
		@unlink( dirname( $this->root ) . '/emcp-outside.txt' );
		// best-effort recursive cleanup
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $this->root, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $f ) { $f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); }
		@rmdir( $this->root );
	}

	/** @test */
	public function resolves_an_in_tree_path(): void {
		$out = \EMCP_Tools_Filesystem_Guard::resolve_path( 'wp-content/themes/x/style.css', $this->root );
		$this->assertSame( realpath( $this->root . '/wp-content/themes/x/style.css' ), $out );
	}

	/** @test */
	public function rejects_parent_traversal(): void {
		$out = \EMCP_Tools_Filesystem_Guard::resolve_path( 'wp-content/../../emcp-outside.txt', $this->root );
		$this->assertInstanceOf( \WP_Error::class, $out );
		$this->assertSame( 'outside_root', $out->get_error_code() );
	}

	/** @test */
	public function rejects_absolute_path_outside_root(): void {
		$out = \EMCP_Tools_Filesystem_Guard::resolve_path( dirname( $this->root ) . '/emcp-outside.txt', $this->root );
		$this->assertInstanceOf( \WP_Error::class, $out );
		$this->assertSame( 'outside_root', $out->get_error_code() );
	}

	/** @test */
	public function rejects_null_byte(): void {
		$out = \EMCP_Tools_Filesystem_Guard::resolve_path( "wp-config.php\0.txt", $this->root );
		$this->assertInstanceOf( \WP_Error::class, $out );
		$this->assertSame( 'invalid_path', $out->get_error_code() );
	}

	/** @test */
	public function rejects_empty_path(): void {
		$out = \EMCP_Tools_Filesystem_Guard::resolve_path( '', $this->root );
		$this->assertInstanceOf( \WP_Error::class, $out );
	}

	/** @test */
	public function resolves_a_new_file_when_parent_exists(): void {
		$out = \EMCP_Tools_Filesystem_Guard::resolve_path( 'wp-content/themes/x/new.txt', $this->root );
		$this->assertSame( realpath( $this->root . '/wp-content/themes/x' ) . DIRECTORY_SEPARATOR . 'new.txt', $out );
	}

	/** @test */
	public function rejects_symlink_escaping_the_root(): void {
		$link = $this->root . '/escape';
		if ( ! @symlink( dirname( $this->root ), $link ) ) {
			$this->markTestSkipped( 'symlink() unavailable in this environment' );
		}
		$out = \EMCP_Tools_Filesystem_Guard::resolve_path( 'escape/emcp-outside.txt', $this->root );
		$this->assertInstanceOf( \WP_Error::class, $out );
		$this->assertSame( 'outside_root', $out->get_error_code() );
	}

	/** @test */
	public function is_protected_flags_config_and_htaccess(): void {
		$this->assertTrue( \EMCP_Tools_Filesystem_Guard::is_protected( '/srv/site/wp-config.php' ) );
		$this->assertTrue( \EMCP_Tools_Filesystem_Guard::is_protected( '/srv/site/.htaccess' ) );
		$this->assertFalse( \EMCP_Tools_Filesystem_Guard::is_protected( '/srv/site/wp-content/themes/x/style.css' ) );
	}

	/** @test */
	public function backup_name_is_timestamped_and_sanitized(): void {
		$name = \EMCP_Tools_Filesystem_Guard::backup_name( 'wp-content/themes/x/style.css', '20260627-120000' );
		$this->assertSame( '20260627-120000-wp-content-themes-x-style.css', $name );
	}

	/** @test */
	public function is_utf8_detects_text_vs_binary(): void {
		$this->assertTrue( \EMCP_Tools_Filesystem_Guard::is_utf8( "plain text\nok" ) );
		$this->assertFalse( \EMCP_Tools_Filesystem_Guard::is_utf8( "\xff\xfe\x00\x01binary" ) );
	}

	/** @test */
	public function check_writes_gates_on_caps_and_disallow(): void {
		$this->assertTrue( \EMCP_Tools_Filesystem_Guard::check_writes( true, false ) );
		$this->assertInstanceOf( \WP_Error::class, \EMCP_Tools_Filesystem_Guard::check_writes( true, true ) );
		$this->assertInstanceOf( \WP_Error::class, \EMCP_Tools_Filesystem_Guard::check_writes( false, false ) );
	}
}
