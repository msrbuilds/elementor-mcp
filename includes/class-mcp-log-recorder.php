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

	const ROUTE = '/mcp/emcp-tools-server';

	/**
	 * Start of the current MCP HTTP request.
	 *
	 * @var float|null
	 */
	private static $start = null;

	/**
	 * Raw body of the current MCP HTTP request.
	 *
	 * @var string
	 */
	private static $body = '';

	/**
	 * rest_pre_dispatch: start a per-request record for the MCP route.
	 *
	 * @param mixed $result  Pass-through.
	 * @param mixed $server  Unused.
	 * @param mixed $request WP_REST_Request.
	 * @return mixed
	 */
	public static function pre_dispatch( $result, $server, $request ) {
		if ( self::is_mcp( $request ) ) {
			EMCP_Tools_Request_Context::reset();
			self::$start = microtime( true );
			self::$body  = (string) $request->get_body();
		}
		return $result;
	}

	/**
	 * rest_post_dispatch: write a row only when the router logged nothing for
	 * this request (rejected before routing, a notification, or an adapter
	 * without the observability interface).
	 *
	 * @param mixed $response WP_REST_Response or WP_Error.
	 * @param mixed $server   Unused.
	 * @param mixed $request  WP_REST_Request.
	 * @return mixed
	 */
	public static function post_dispatch( $response, $server, $request ) {
		if ( ! self::is_mcp( $request ) || null === self::$start ) {
			return $response;
		}
		if ( ! EMCP_Tools_Request_Context::was_logged() ) {
			$row  = self::classify( $response, self::$body );
			$cred = EMCP_Tools_Request_Context::http_credential( EMCP_Tools_Request_Context::http_auth() );
			$sid  = method_exists( $request, 'get_header' ) ? (string) $request->get_header( 'mcp-session-id' ) : '';
			EMCP_Tools_MCP_Request_Log::record(
				array_merge(
					$row,
					array(
						'ms'         => ( microtime( true ) - self::$start ) * 1000,
						'client'     => $cred['client'],
						'credential' => $cred['credential'],
						'session'    => $sid,
						'ledger'     => EMCP_Tools_Request_Context::take_ledger(),
					)
				)
			);
			EMCP_Tools_Activity_Stats::record( 'tools/call' === $row['method'] ? $row['tool'] : '', 'error' === $row['status'] );
		}
		self::$start = null;
		self::$body  = '';
		return $response;
	}

	/**
	 * Outcome of an HTTP request from its actual response (round 3, item 5).
	 *
	 * @param mixed  $response WP_REST_Response or WP_Error.
	 * @param string $body     Raw request body.
	 * @return array{method:string, tool:string, status:string, stage:string, failure_reason:string}
	 */
	public static function classify( $response, string $body ): array {
		$decoded = json_decode( $body, true );
		$method  = 'invalid';
		$tool    = '';
		if ( is_array( $decoded ) && array_is_list( $decoded ) && ! empty( $decoded ) ) {
			$method = 'batch';
		} elseif ( is_array( $decoded ) && isset( $decoded['method'] ) && is_string( $decoded['method'] ) ) {
			$method = $decoded['method'];
			if ( 'tools/call' === $method && is_string( $decoded['params']['name'] ?? null ) ) {
				$tool = $decoded['params']['name'];
			}
		}

		$error  = false;
		$reason = '';
		if ( is_wp_error( $response ) ) {
			$error  = true;
			$reason = $response->get_error_message();
		} else {
			$code = is_object( $response ) && method_exists( $response, 'get_status' ) ? (int) $response->get_status() : 200;
			$data = is_object( $response ) && method_exists( $response, 'get_data' ) ? $response->get_data() : null;
			// The adapter casts `result` (and content items) to objects before
			// the response is serialized; read them as plain arrays.
			if ( is_array( $data ) || is_object( $data ) ) {
				$data = json_decode( (string) wp_json_encode( $data ), true );
			}
			if ( $code >= 400 ) {
				$error  = true;
				$reason = self::error_text( $data );
				if ( '' === $reason ) {
					$reason = 'HTTP ' . $code;
				}
			} elseif ( is_array( $data ) ) {
				$items = array_is_list( $data ) ? $data : array( $data );
				foreach ( $items as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					if ( isset( $item['error'] ) || true === ( $item['result']['isError'] ?? null ) ) {
						$error  = true;
						$reason = self::error_text( $item );
						break;
					}
				}
			}
		}

		return array(
			'method'         => $method,
			'tool'           => $tool,
			'status'         => $error ? 'error' : 'success',
			'stage'          => $error ? 'transport' : '',
			'failure_reason' => $error ? $reason : '',
		);
	}

	/**
	 * The message of a JSON-RPC error, a tool error result, or a REST error.
	 *
	 * @param mixed $data Response data.
	 */
	private static function error_text( $data ): string {
		if ( ! is_array( $data ) ) {
			return '';
		}
		if ( isset( $data['error']['message'] ) && is_string( $data['error']['message'] ) ) {
			return $data['error']['message'];
		}
		if ( isset( $data['result']['content'][0]['text'] ) && is_string( $data['result']['content'][0]['text'] ) ) {
			return $data['result']['content'][0]['text'];
		}
		return isset( $data['message'] ) && is_string( $data['message'] ) ? $data['message'] : '';
	}

	/**
	 * Whether a REST request targets the EMCP MCP server route.
	 *
	 * @param mixed $request Request.
	 */
	private static function is_mcp( $request ): bool {
		return is_object( $request ) && method_exists( $request, 'get_route' ) && 0 === strpos( (string) $request->get_route(), self::ROUTE );
	}

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
