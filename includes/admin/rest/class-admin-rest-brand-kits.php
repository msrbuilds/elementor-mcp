<?php
/**
 * Brand Kits screen REST (spec 8.17).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_REST_Brand_Kits extends EMCP_Tools_Admin_REST_Controller {

	public function register_routes(): void {
		$this->route(
			'brand-kits',
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'get' ),
				'args'     => array(
					'search'   => array( 'type' => 'string', 'default' => '' ),
					'category' => array( 'type' => 'string', 'default' => '' ),
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
				),
			)
		);
		$this->route( 'brand-kits/(?P<category>[a-z0-9-]+)/(?P<slug>[a-z0-9-]+)/apply', array( 'methods' => 'POST', 'callback' => array( $this, 'apply' ), 'args' => array( 'backup' => array( 'type' => 'boolean', 'default' => true ) ) ) );
		$this->route( 'brand-kits/restore', array( 'methods' => 'POST', 'callback' => array( $this, 'restore' ), 'args' => array( 'full_clobber' => array( 'type' => 'boolean', 'default' => false ) ) ) );
		$this->route( 'brand-kits/sync', array( 'methods' => 'POST', 'callback' => array( $this, 'sync' ) ) );
	}

	public static function register_screen(): void {
		EMCP_Tools_Admin_Screens::register(
			'brandkits',
			array(
				'script' => 'screen-brand-kits',
				'tabs'   => array( 'brand-kits' ),
				'boot'   => static function (): array {
					return ( new EMCP_Tools_Admin_Brand_Kits_Data() )->payload();
				},
			)
		);
	}

	private static function query( $request ): array {
		return array(
			'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'category' => sanitize_key( (string) $request->get_param( 'category' ) ),
			'page'     => max( 1, (int) $request->get_param( 'page' ) ),
		);
	}

	public function get( $request ) {
		return new WP_REST_Response( ( new EMCP_Tools_Admin_Brand_Kits_Data() )->payload( self::query( $request ) ) );
	}

	public function apply( $request ) {
		$data   = new EMCP_Tools_Admin_Brand_Kits_Data();
		$result = $data->apply( sanitize_key( self::route_param( $request, 'category' ) ), sanitize_key( self::route_param( $request, 'slug' ) ), rest_sanitize_boolean( $request->get_param( 'backup' ) ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$view = class_exists( 'EMCP_Tools_Pro_Ajax' ) ? EMCP_Tools_Pro_Ajax::recent_elementor_page_url() : home_url( '/' );
		return new WP_REST_Response( array_merge( $data->payload(), array( 'applied' => $result, 'viewUrl' => $view ) ) );
	}

	public function restore( $request ) {
		$data   = new EMCP_Tools_Admin_Brand_Kits_Data();
		$result = $data->restore( rest_sanitize_boolean( $request->get_param( 'full_clobber' ) ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array_merge( $data->payload(), $result ) );
	}

	public function sync( $request ) {
		if ( ! class_exists( 'EMCP_Tools_Pro_Brand_Kits' ) || ! EMCP_Tools_Pro_Brand_Kits::user_has_access() ) {
			return new WP_Error( 'emcp_no_license', __( 'Syncing the brand kit library needs an active EMCP Pro licence.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		$bundle = EMCP_Tools_Pro_Brand_Kits::get_bundle( true );
		if ( is_wp_error( $bundle ) ) {
			return new WP_Error( 'emcp_sync_failed', $bundle->get_error_message(), array( 'status' => 400 ) );
		}
		delete_transient( 'emcp_tools_nav_counts' );
		if ( class_exists( 'EMCP_Tools_Attention' ) ) {
			EMCP_Tools_Attention::flush();
		}
		$payload = ( new EMCP_Tools_Admin_Brand_Kits_Data() )->payload();
		/* translators: %d: kit count. */
		$payload['message'] = sprintf( __( 'Synced %d brand kits.', 'emcp-tools' ), $payload['total'] );
		return new WP_REST_Response( $payload );
	}
}
