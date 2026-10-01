<?php
/** Isolated lazy-registry fixture for the real plugin orchestrator. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'EMCP_TOOLS_VERSION', 'test' );
$scenario = $argv[1];
$lookups = 0;
$initialized = false;
$names = 'empty' === $scenario ? array() : array( 'emcp-tools/list-pages' );
function get_option( $key, $default = false ) {
	global $scenario;
	if ( 'emcp_tools_server_enabled' === $key && 'disabled' === $scenario ) {
		return '0';
	}
	if ( 'emcp_tools_dispatcher_mode' === $key && 'compact' === $scenario ) {
		return '1';
	}
	return $default;
}
function __( $text, $domain = '' ) { return $text; }
function wp_get_ability( $name ) {
	global $lookups, $initialized, $plugin, $names;
	++$lookups;
	if ( ! $initialized ) {
		$initialized = true;
		$property = new ReflectionProperty( EMCP_Tools_Plugin::class, 'ability_names' );
		$property->setValue( $plugin, $names );
	}
	return null;
}
class EMCP_Tools_Site_Context {
	public static function server_instructions() { return 'Fixture'; }
}
class EMCP_Tools_MCP_Log_Recorder {
	public static function observability_class() { return null; }
}
class EMCP_Tools_Dispatcher_Abilities {
	const NAMES = array( 'emcp-tools/list-tools', 'emcp-tools/get-tool-schema', 'emcp-tools/call-tool' );
}
require dirname( __DIR__, 2 ) . '/includes/class-plugin.php';
$plugin = ( new ReflectionClass( EMCP_Tools_Plugin::class ) )->newInstanceWithoutConstructor();
if ( 'preloaded' === $scenario ) {
	$initialized = true;
	( new ReflectionProperty( EMCP_Tools_Plugin::class, 'ability_names' ) )->setValue( $plugin, $names );
}
$adapter = new class {
	public array $servers = array();
	public function create_server( ...$args ) { $this->servers[] = array( 'id' => $args[0], 'tools' => $args[9] ); }
};
$plugin->register_mcp_server( $adapter );
echo json_encode( array( 'lookups' => $lookups, 'initialized' => $initialized, 'servers' => $adapter->servers ) );
