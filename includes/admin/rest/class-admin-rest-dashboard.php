<?php
/**
 * Dashboard REST (spec 8.1). Cookie-only through the base controller.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard REST controller: the range refetch and attention dismissal.
 */
final class EMCP_Tools_Admin_REST_Dashboard extends EMCP_Tools_Admin_REST_Controller {

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		$this->route( 'dashboard', array( 'methods' => 'GET', 'callback' => array( $this, 'get' ) ) );
		$this->route( 'dashboard/attention/(?P<id>[a-z0-9-]{1,40})/dismiss', array( 'methods' => 'POST', 'callback' => array( $this, 'dismiss' ) ) );
	}

	/**
	 * Register the Dashboard screen.
	 *
	 * @param EMCP_Tools_Admin $admin Admin instance.
	 */
	public static function register_screen( EMCP_Tools_Admin $admin ): void {
		EMCP_Tools_Admin_Screens::register(
			'dashboard',
			array(
				'script' => 'screen-dashboard',
				'tabs'   => array( 'dashboard' ),
				'boot'   => static function () use ( $admin ): array {
					return ( new EMCP_Tools_Admin_Dashboard_Data( $admin ) )->payload();
				},
			)
		);
	}

	/** The data class, with the admin loaded (REST is not is_admin()). */
	private static function data(): EMCP_Tools_Admin_Dashboard_Data {
		if ( ! class_exists( 'EMCP_Tools_Admin' ) && class_exists( 'EMCP_Tools_Bootstrap' ) ) {
			EMCP_Tools_Bootstrap::require_admin_classes();
		}
		return new EMCP_Tools_Admin_Dashboard_Data( class_exists( 'EMCP_Tools_Admin' ) ? new EMCP_Tools_Admin() : null );
	}

	/**
	 * GET admin/dashboard?range=7|14|30.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function get( $request ) {
		$data = self::data();
		return rest_ensure_response(
			array(
				'activity'  => $data->activity( EMCP_Tools_Admin_Dashboard_Data::range_of( $request->get_param( 'range' ) ) ),
				'recent'    => $data->recent(),
				'attention' => EMCP_Tools_Admin_Dashboard_Data::attention(),
			)
		);
	}

	/**
	 * POST admin/dashboard/attention/{id}/dismiss.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function dismiss( $request ) {
		$id = sanitize_key( self::route_param( $request, 'id' ) );
		if ( '' === $id || ! EMCP_Tools_Attention::dismiss( get_current_user_id(), $id ) ) {
			return new WP_Error( 'not_found', __( 'That item is no longer shown.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'attention' => EMCP_Tools_Admin_Dashboard_Data::attention() ) );
	}
}
