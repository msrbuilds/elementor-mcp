<?php
/**
 * The MCP adapter observability handler (spec 9.2). The adapter's
 * RequestRouter emits `mcp.request` for every routed JSON-RPC method, on every
 * exit path, for HTTP and WP-CLI stdio alike; each becomes one MCP log row and
 * one activity count. The adapter instantiates this class by name.
 *
 * Declared only when the adapter interface exists: another plugin's older
 * adapter copy may win the Jetpack Autoloader, and then the REST fallback
 * (EMCP_Tools_MCP_Log_Recorder) records every HTTP request instead.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( '\WP\MCP\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface' ) ) {
	return;
}

/**
 * Writes one MCP log row per routed request.
 */
final class EMCP_Tools_MCP_Observability implements \WP\MCP\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface {

	/**
	 * Adapter callback.
	 *
	 * @param string     $event       Event name.
	 * @param array      $tags        Tags.
	 * @param float|null $duration_ms Duration.
	 */
	public function record_event( string $event, array $tags = array(), ?float $duration_ms = null ): void {
		if ( 'mcp.request' !== $event ) {
			return;
		}
		$row = self::row_from_tags( $tags, $duration_ms );
		EMCP_Tools_MCP_Request_Log::record( $row );
		EMCP_Tools_Activity_Stats::record( 'tools/call' === $row['method'] ? $row['tool'] : '', 'error' === $row['status'] );
		EMCP_Tools_Request_Context::mark_logged();
	}

	/**
	 * A log row from the router's tags.
	 *
	 * @param array      $tags        Tags.
	 * @param float|null $duration_ms Duration.
	 */
	public static function row_from_tags( array $tags, ?float $duration_ms ): array {
		$method = (string) ( $tags['method'] ?? '' );
		// The stdio bridge routes notifications (no id) and the router answers
		// "method not found"; the client expects no reply, so it is a success.
		$is_notification = 0 === strpos( $method, 'notifications/' ) && null === ( $tags['request_id'] ?? null );
		$status          = $is_notification ? 'success' : (string) ( $tags['status'] ?? 'error' );
		$reason          = (string) ( $tags['failure_reason'] ?? ( $tags['error_type'] ?? '' ) );
		$ctx             = EMCP_Tools_Request_Context::for_event( $tags );
		$request_id      = $tags['request_id'] ?? '';

		return array(
			'method'         => $method,
			'tool'           => (string) ( $tags['tool_name'] ?? '' ),
			'status'         => $status,
			'ms'             => (float) ( $duration_ms ?? 0 ),
			'req_id'         => is_scalar( $request_id ) ? (string) $request_id : '',
			'client'         => $ctx['client'],
			'session'        => $ctx['session'],
			'credential'     => $ctx['credential'],
			'setup'          => $ctx['setup'],
			'stage'          => '',
			'failure_reason' => 'error' === $status ? $reason : '',
			'ledger'         => EMCP_Tools_Request_Context::take_ledger(),
		);
	}
}
