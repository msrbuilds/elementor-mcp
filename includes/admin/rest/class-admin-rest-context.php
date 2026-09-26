<?php
/**
 * Context screen REST (spec 8.7).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_REST_Context extends EMCP_Tools_Admin_REST_Controller {

	public function register_routes(): void {
		$this->route(
			'context',
			array(
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get' ),
				),
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'save' ),
				),
			)
		);
		$this->route( 'context/refresh', array( 'methods' => 'POST', 'callback' => array( $this, 'refresh' ) ) );
		// POST, not GET: the preview carries an unsaved draft. It writes nothing.
		$this->route( 'context/preview', array( 'methods' => 'POST', 'callback' => array( $this, 'preview' ) ) );
	}

	public static function register_screen(): void {
		EMCP_Tools_Admin_Screens::register(
			'context',
			array(
				'script' => 'screen-context',
				'tabs'   => array( 'context' ),
				'boot'   => static function (): array {
					return ( new EMCP_Tools_Admin_Context_Data() )->payload();
				},
			)
		);
	}

	private static function body( $request ): array {
		$out = array();
		foreach ( array( 'profile', 'sections', 'instructions', 'enabled' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	public function get( $request ) {
		return new WP_REST_Response( ( new EMCP_Tools_Admin_Context_Data() )->payload() );
	}

	public function save( $request ) {
		return new WP_REST_Response( ( new EMCP_Tools_Admin_Context_Data() )->save( self::body( $request ) ) );
	}

	public function refresh( $request ) {
		EMCP_Tools_Context_Sections::flush();
		return new WP_REST_Response( ( new EMCP_Tools_Admin_Context_Data() )->payload( true ) );
	}

	public function preview( $request ) {
		return new WP_REST_Response( ( new EMCP_Tools_Admin_Context_Data() )->preview( self::body( $request ) ) );
	}
}
