<?php
/**
 * Compact tool mode reports which tools only read.
 *
 * The hosted Gateway lets read-only team members use a compact site only when
 * list-tools and get-tool-schema say which tools are read-only. The field is
 * always present, so the Gateway can tell this plugin from an older one.
 *
 * @package EMCP_Tools
 */

use PHPUnit\Framework\TestCase;

require_once EMCP_TOOLS_DIR . 'includes/abilities/class-dispatcher-abilities.php';
// list-tools adds the discovery context whenever EMCP_Tools_Site_Context is
// loaded, which other public tests do; that summary needs its sections class.
require_once EMCP_TOOLS_DIR . 'includes/class-context-sections.php';

/**
 * A minimal ability double: description, category, schema and meta.
 */
class Emcp_Readonly_Flag_Ability {
	private $meta;
	public function __construct( array $meta ) {
		$this->meta = $meta;
	}
	public function get_description() {
		return 'Stub tool.';
	}
	public function get_category() {
		return 'emcp-tools';
	}
	public function get_input_schema() {
		return array( 'type' => 'object' );
	}
	public function get_meta() {
		return $this->meta;
	}
}

/**
 * Dispatcher with its two seams pointed at in-memory abilities.
 */
class Emcp_Readonly_Flag_Dispatcher extends EMCP_Tools_Dispatcher_Abilities {
	public $abilities = array();
	protected function active_names(): array {
		return array_keys( $this->abilities );
	}
	protected function resolve_ability( string $name ) {
		return $this->abilities[ $name ] ?? null;
	}
}

/**
 * Runs apart from the suite: another test loads EMCP_Tools_Site_Context, whose
 * discovery summary needs WordPress functions this test does not stub.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DispatcherReadonlyFlagTest extends TestCase {

	private function dispatcher(): Emcp_Readonly_Flag_Dispatcher {
		$d            = new Emcp_Readonly_Flag_Dispatcher();
		$d->abilities = array(
			'emcp-tools/reader' => new Emcp_Readonly_Flag_Ability(
				array( 'annotations' => array( 'readonly' => true, 'destructive' => false ) )
			),
			'emcp-tools/writer' => new Emcp_Readonly_Flag_Ability(
				array( 'annotations' => array( 'destructive' => false ) )
			),
		);
		return $d;
	}

	public function test_list_tools_reports_readonly_on_every_entry(): void {
		$result = $this->dispatcher()->execute_list_tools( array() );
		$by     = array_column( $result['tools'], null, 'name' );

		$this->assertArrayHasKey( 'readonly', $by['emcp-tools/reader'] );
		$this->assertArrayHasKey( 'readonly', $by['emcp-tools/writer'] );
		$this->assertTrue( $by['emcp-tools/reader']['readonly'] );
		$this->assertFalse( $by['emcp-tools/writer']['readonly'] );
	}

	public function test_get_tool_schema_reports_readonly_on_every_entry(): void {
		$result = $this->dispatcher()->execute_get_tool_schema(
			array( 'names' => array( 'emcp-tools/reader', 'emcp-tools/writer' ) )
		);

		$this->assertArrayHasKey( 'readonly', $result['schemas']['emcp-tools/reader'] );
		$this->assertArrayHasKey( 'readonly', $result['schemas']['emcp-tools/writer'] );
		$this->assertTrue( $result['schemas']['emcp-tools/reader']['readonly'] );
		$this->assertFalse( $result['schemas']['emcp-tools/writer']['readonly'] );
	}

	public function test_only_a_strict_true_counts_as_readonly(): void {
		$d            = new Emcp_Readonly_Flag_Dispatcher();
		$d->abilities = array(
			'emcp-tools/truthy' => new Emcp_Readonly_Flag_Ability( array( 'annotations' => array( 'readonly' => 1 ) ) ),
			'emcp-tools/bare'   => new Emcp_Readonly_Flag_Ability( array() ),
		);
		$by = array_column( $d->execute_list_tools( array() )['tools'], null, 'name' );

		$this->assertFalse( $by['emcp-tools/truthy']['readonly'] );
		$this->assertFalse( $by['emcp-tools/bare']['readonly'] );
	}
}
