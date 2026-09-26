<?php
/**
 * Marketplace screen REST (spec 8.18). The Cloud request stays server-side:
 * no Cloud token reaches the browser (spec 11).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_REST_Marketplace extends EMCP_Tools_Admin_REST_Controller {

	public function register_routes(): void {
		$this->route(
			'marketplace',
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'get' ),
				'args'     => array(
					'search'   => array( 'type' => 'string', 'default' => '' ),
					'type'     => array( 'type' => 'string', 'default' => '' ),
					'category' => array( 'type' => 'string', 'default' => '' ),
					'access'   => array( 'type' => 'string', 'default' => '' ),
					'sort'     => array( 'type' => 'string', 'default' => 'newest' ),
					'verified' => array( 'type' => 'boolean', 'default' => false ),
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
				),
			)
		);
		$this->route( 'marketplace/installs', array( 'methods' => 'GET', 'callback' => array( $this, 'installs' ) ) );
		$this->route( 'marketplace/(?P<slug>[a-z0-9-]+)/install', array( 'methods' => 'POST', 'callback' => array( $this, 'install' ) ) );
	}

	public static function register_screen(): void {
		EMCP_Tools_Admin_Screens::register(
			'marketplace',
			array(
				'script' => 'screen-marketplace',
				'tabs'   => array( 'marketplace' ),
				'boot'   => static function (): array {
					return ( new EMCP_Tools_Admin_Marketplace_Data() )->boot();
				},
			)
		);
	}

	public function get( $request ) {
		return new WP_REST_Response(
			( new EMCP_Tools_Admin_Marketplace_Data() )->payload(
				array(
					'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
					'type'     => sanitize_key( (string) $request->get_param( 'type' ) ),
					'category' => sanitize_text_field( (string) $request->get_param( 'category' ) ),
					'access'   => sanitize_key( (string) $request->get_param( 'access' ) ),
					'sort'     => sanitize_key( (string) $request->get_param( 'sort' ) ),
					'verified' => rest_sanitize_boolean( $request->get_param( 'verified' ) ),
					'page'     => max( 1, (int) $request->get_param( 'page' ) ),
				)
			)
		);
	}

	public function installs( $request ) {
		return new WP_REST_Response( array( 'installs' => EMCP_Tools_Marketplace_Installs::all() ) );
	}

	public function install( $request ) {
		$slug = sanitize_title( self::route_param( $request, 'slug' ) );
		$res  = EMCP_Tools_Cloud_Sync::marketplace_install( $slug );
		if ( is_wp_error( $res ) ) {
			$data   = $res->get_error_data();
			$status = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
			if ( 402 === $status || 'pro_required' === $res->get_error_message() ) {
				return new WP_Error( 'emcp_pro_required', __( 'That item needs a paid EMCP Cloud plan. Upgrade your Cloud plan to install Pro items.', 'emcp-tools' ), array( 'status' => 402 ) );
			}
			return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 400 ) );
		}
		return new WP_REST_Response(
			array(
				'installed' => EMCP_Tools_Marketplace_Installs::installed( $slug ),
				'message'   => __( 'Installed as a draft. Review it in the Sandbox before you activate it.', 'emcp-tools' ),
			)
		);
	}
}
