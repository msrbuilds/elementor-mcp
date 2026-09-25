<?php
/**
 * MCP log wiring: which observability handler the server gets, and the REST
 * fallback recorder for HTTP requests the router never sees (spec 9.2).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Observability handler choice and REST fallback recorder.
 */
final class EMCP_Tools_MCP_Log_Recorder {

	const OBSERVABILITY_INTERFACE = '\WP\MCP\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface';

	/**
	 * The observability handler class for create_server(), or null (the adapter
	 * then uses its null handler and the REST fallback records every request).
	 *
	 * @param bool|null $interface_exists Override for tests.
	 */
	public static function observability_class( ?bool $interface_exists = null ): ?string {
		$exists = $interface_exists ?? interface_exists( self::OBSERVABILITY_INTERFACE );
		if ( ! $exists || ! apply_filters( 'emcp_tools_mcp_observability', true ) ) {
			return null;
		}
		require_once EMCP_TOOLS_DIR . 'includes/class-mcp-observability.php';
		return class_exists( 'EMCP_Tools_MCP_Observability', false ) ? 'EMCP_Tools_MCP_Observability' : null;
	}
}
