<?php
/**
 * MCP Log REST (spec 8.22). Cookie-only through the base controller.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MCP Log REST controller.
 */
final class EMCP_Tools_Admin_REST_Log extends EMCP_Tools_Admin_REST_Controller {

	const EXPORT_ROUTE = '/emcp-tools/v1/admin/log/export.csv';

	/**
	 * Register the routes and the raw CSV printer.
	 */
	public function register_routes(): void {
		$this->route( 'log/export\.csv', array( 'methods' => 'GET', 'callback' => array( $this, 'export' ) ) );
		$this->route(
			'log',
			array(
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get' ),
				),
				array(
					'methods'  => 'DELETE',
					'callback' => array( $this, 'delete' ),
				),
			)
		);
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_csv' ), 10, 3 );
	}

	/**
	 * Register the MCP Log screen.
	 */
	public static function register_screen(): void {
		EMCP_Tools_Admin_Screens::register(
			'log',
			array(
				'script' => 'screen-log',
				'tabs'   => array( 'mcp-log' ),
				'boot'   => static function (): array {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filters, sanitized by payload().
					$get = wp_unslash( $_GET );
					$get = is_array( $get ) ? $get : array();
					return ( new EMCP_Tools_Admin_Log_Data() )->payload(
						array(
							'status' => $get['status'] ?? 'all',
							'search' => $get['search'] ?? '',
							'page'   => $get['paged'] ?? 1,
						)
					);
				},
			)
		);
	}

	/**
	 * The filters a request carried.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private static function args( $request ): array {
		return array(
			'status' => (string) $request->get_param( 'status' ),
			'search' => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'page'   => (int) $request->get_param( 'page' ),
		);
	}

	/**
	 * GET admin/log.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get( $request ) {
		return new WP_REST_Response( ( new EMCP_Tools_Admin_Log_Data() )->payload( self::args( $request ) ) );
	}

	/**
	 * DELETE admin/log (needs confirm: true).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete( $request ) {
		if ( true !== rest_sanitize_boolean( $request->get_param( 'confirm' ) ) ) {
			return new WP_Error( 'confirm_required', __( 'Confirm this action to continue.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		EMCP_Tools_MCP_Request_Log::clear();
		return new WP_REST_Response( ( new EMCP_Tools_Admin_Log_Data() )->payload( array() ) );
	}

	/**
	 * GET admin/log/export.csv: the CSV for the filters in view.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function export( $request ) {
		$res = new WP_REST_Response( EMCP_Tools_MCP_Request_Log::export_csv( self::args( $request ) ) );
		$res->header( 'Content-Type', 'text/csv; charset=utf-8' );
		$res->header( 'Content-Disposition', 'attachment; filename="emcp-mcp-log-' . gmdate( 'Ymd-His' ) . '.csv"' );
		$res->header( 'Cache-Control', 'no-store' );
		$res->header( 'X-Content-Type-Options', 'nosniff' );
		return $res;
	}

	/**
	 * Print the export route's CSV raw instead of JSON-encoding it.
	 *
	 * @param bool             $served  Whether a handler already served it.
	 * @param WP_REST_Response $result  Response.
	 * @param WP_REST_Request  $request Request.
	 * @return bool
	 */
	public static function serve_csv( $served, $result, $request ) {
		if ( $served || ! $request || self::EXPORT_ROUTE !== $request->get_route() || ! $result instanceof WP_REST_Response || ! is_string( $result->get_data() ) ) {
			return $served;
		}
		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV body, cells neutralised in export_csv().
		return true;
	}
}
