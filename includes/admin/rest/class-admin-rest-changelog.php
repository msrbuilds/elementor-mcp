<?php
/**
 * Changelog REST (spec 8.23). Cookie-only through the base controller.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_REST_Changelog extends EMCP_Tools_Admin_REST_Controller {

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		$this->route( 'changelog', array( 'methods' => 'GET', 'callback' => array( $this, 'search' ) ) );
		$this->route( 'changelog/(?P<version>[0-9A-Za-z.\-]{1,20})', array( 'methods' => 'GET', 'callback' => array( $this, 'release' ) ) );
	}

	/**
	 * Register the Changelog screen.
	 */
	public static function register_screen(): void {
		EMCP_Tools_Admin_Screens::register(
			'changelog',
			array(
				'script' => 'screen-changelog',
				'tabs'   => array( 'changelog' ),
				'boot'   => static function (): array {
					return ( new EMCP_Tools_Admin_Changelog_Data() )->payload();
				},
			)
		);
	}

	/**
	 * GET admin/changelog/{version}.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function release( $request ) {
		$r = ( new EMCP_Tools_Admin_Changelog_Data() )->release( self::route_param( $request, 'version' ) );
		if ( null === $r ) {
			return new WP_Error( 'not_found', __( 'That release is not in the changelog.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $r );
	}

	/**
	 * GET admin/changelog?search=.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function search( $request ) {
		$q = substr( sanitize_text_field( (string) $request->get_param( 'search' ) ), 0, 100 );
		return rest_ensure_response( array( 'results' => ( new EMCP_Tools_Admin_Changelog_Data() )->search( $q ) ) );
	}
}
