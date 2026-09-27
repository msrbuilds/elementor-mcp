<?php
/**
 * Tools screen REST (spec 8.3).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_REST_Tools extends EMCP_Tools_Admin_REST_Controller {

	public function register_routes(): void {
		$this->route(
			'tools',
			array(
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get' ),
				),
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'save' ),
					'args'     => array(
						'enable'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'default' => array() ),
						'disable'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'default' => array() ),
						'dispatcher_mode' => array( 'type' => 'boolean' ),
						'themer_php'      => array( 'type' => 'boolean' ),
					),
				),
			)
		);
	}

	/** Register the React screen for the Tools tab. */
	public static function register_screen( EMCP_Tools_Admin $admin ): void {
		EMCP_Tools_Admin_Screens::register(
			'tools',
			array(
				'script' => 'screen-tools',
				'tabs'   => array( 'tools' ),
				'boot'   => static function () use ( $admin ): array {
					return ( new EMCP_Tools_Admin_Tools_Data( $admin ) )->payload();
				},
			)
		);
	}

	private function data(): EMCP_Tools_Admin_Tools_Data {
		EMCP_Tools_Bootstrap::require_admin_classes();
		return new EMCP_Tools_Admin_Tools_Data( new EMCP_Tools_Admin() );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public function get( $request ) {
		return new WP_REST_Response( $this->data()->payload() );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public function save( $request ) {
		$data   = $this->data();
		$admin  = new EMCP_Tools_Admin();
		$slugs  = static function ( $list ): array {
			return array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', array_filter( (array) $list, 'is_scalar' ) ) ) ) );
		};
		$result = EMCP_Tools_Admin_Tools_Data::apply(
			(array) get_option( EMCP_Tools_Admin::OPTION_DISABLED_TOOLS, array() ),
			$slugs( $request->get_param( 'enable' ) ),
			$slugs( $request->get_param( 'disable' ) ),
			$admin->get_all_tool_slugs(),
			$data->enableable_slugs()
		);
		update_option( EMCP_Tools_Admin::OPTION_DISABLED_TOOLS, $result['disabled'] );

		if ( null !== $request->get_param( 'dispatcher_mode' ) ) {
			update_option( 'emcp_tools_dispatcher_mode', rest_sanitize_boolean( $request->get_param( 'dispatcher_mode' ) ) ? '1' : '0' );
		}
		if ( null !== $request->get_param( 'themer_php' ) && class_exists( 'EMCP_Tools_Themer_Module' ) && EMCP_Tools_Themer_Module::is_enabled() ) {
			update_option( 'emcp_tools_themer_php_enabled', rest_sanitize_boolean( $request->get_param( 'themer_php' ) ) ? '1' : '0' );
		}
		delete_transient( 'emcp_tools_nav_counts' );
		if ( class_exists( 'EMCP_Tools_Attention' ) ) {
			EMCP_Tools_Attention::flush();
		}

		return new WP_REST_Response( array_merge( $data->payload(), array( 'ignored' => $result['ignored'] ) ) );
	}
}
