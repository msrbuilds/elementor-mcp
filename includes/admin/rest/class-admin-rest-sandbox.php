<?php
/**
 * Sandbox screens REST (spec 8.11 to 8.14). Cookie-only, so an agent holding an
 * application password or token can never switch its own work on (spec 5.5).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sandbox admin REST.
 */
final class EMCP_Tools_Admin_REST_Sandbox extends EMCP_Tools_Admin_REST_Controller {

	const TYPE = '(?P<type>widgets|blocks|snippets)';

	public function register_routes(): void {
		$this->route(
			'sandbox',
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'overview' ),
			)
		);
		$this->route(
			'sandbox/' . self::TYPE,
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'list' ),
				'args'     => array(
					'status' => array(
						'type'    => 'string',
						'default' => 'all',
					),
					'search' => array(
						'type'    => 'string',
						'default' => '',
					),
					'page'   => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			)
		);
		$this->route(
			'sandbox/' . self::TYPE . '/(?P<id>\d+)',
			array(
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'detail' ),
				),
			)
		);
	}

	/** Screens for the Sandbox tab and its ?view= children (spec 8.11). */
	public static function register_screens(): void {
		$list_boot = static function ( string $type ): callable {
			return static function () use ( $type ): array {
				$res = ( new EMCP_Tools_Admin_Sandbox_Data() )->list( $type, self::boot_query() );
				return is_wp_error( $res ) ? array( 'error' => $res->get_error_message() ) : $res;
			};
		};
		EMCP_Tools_Admin_Screens::register(
			'sandbox',
			array(
				'script' => 'screen-sandbox',
				'tabs'   => array( 'widgets' ),
				'boot'   => static function (): array {
					return ( new EMCP_Tools_Admin_Sandbox_Data() )->overview();
				},
			)
		);
		EMCP_Tools_Admin_Screens::register(
			'snippets',
			array(
				'script' => 'screen-snippets',
				'tabs'   => array( 'widgets:snippets' ),
				'boot'   => $list_boot( 'snippets' ),
			)
		);
		EMCP_Tools_Admin_Screens::register(
			'sandbox-widgets',
			array(
				'script' => 'screen-sandbox-widgets',
				'root'   => 'pro',
				'tabs'   => array( 'widgets:widgets' ),
				'boot'   => $list_boot( 'widgets' ),
			)
		);
		EMCP_Tools_Admin_Screens::register(
			'sandbox-blocks',
			array(
				'script' => 'screen-sandbox-blocks',
				'root'   => 'pro',
				'tabs'   => array( 'widgets:blocks' ),
				'boot'   => $list_boot( 'blocks' ),
			)
		);
	}

	/** List query from the admin URL, for the boot payload. */
	private static function boot_query(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view state.
		return array(
			'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all',
			'search' => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
			'page'   => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
		);
		// phpcs:enable
	}

	/**
	 * The list query a request carries (query string on GET, JSON body on writes).
	 *
	 * @param WP_REST_Request $request Request.
	 */
	protected static function query( $request ): array {
		return array(
			'status' => sanitize_key( (string) ( $request->get_param( 'status' ) ?? 'all' ) ),
			'search' => sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) ),
			'page'   => max( 1, (int) $request->get_param( 'page' ) ),
		);
	}

	/**
	 * A data-layer result as a REST response.
	 *
	 * @param array|WP_Error $result Result.
	 */
	protected static function respond( $result ) {
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}

	public function overview( $request ) {
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Data() )->overview() );
	}

	public function list( $request ) {
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Data() )->list( self::route_param( $request, 'type' ), self::query( $request ) ) );
	}

	public function detail( $request ) {
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Data() )->detail( self::route_param( $request, 'type' ), (int) self::route_param( $request, 'id' ) ) );
	}
}
