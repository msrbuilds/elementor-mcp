<?php
/**
 * Page Builders screen REST (spec 8.5).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_REST_Builders extends EMCP_Tools_Admin_REST_Controller {

	public function register_routes(): void {
		$this->route(
			'builders',
			array(
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get' ),
				),
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'save' ),
					'args'     => array(
						'builder'       => array( 'type' => 'string' ),
						'packs_enable'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'default' => array() ),
						'packs_disable' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'default' => array() ),
					),
				),
			)
		);
	}

	public static function register_screen(): void {
		EMCP_Tools_Admin_Screens::register(
			'builders',
			array(
				'script' => 'screen-builders',
				'tabs'   => array( 'page-builders' ),
				'boot'   => static function (): array {
					return ( new EMCP_Tools_Admin_Builders_Data() )->payload();
				},
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public function get( $request ) {
		return new WP_REST_Response( ( new EMCP_Tools_Admin_Builders_Data() )->payload() );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save( $request ) {
		$builder = $request->get_param( 'builder' );
		if ( null !== $builder ) {
			$builder = sanitize_key( (string) $builder );
			if ( '' !== $builder && ( ! isset( EMCP_Tools_Page_Builders::catalog()[ $builder ] ) || ! EMCP_Tools_Page_Builders::available( $builder ) ) ) {
				return new WP_Error( 'builder_unavailable', __( 'That builder is not installed and active on this site.', 'emcp-tools' ), array( 'status' => 400 ) );
			}
			update_option( EMCP_Tools_Page_Builders::OPTION, $builder );
		}

		$ids       = static function ( $list ): array {
			return array_values( array_map( 'sanitize_key', array_map( 'strval', array_filter( (array) $list, 'is_scalar' ) ) ) );
		};
		$known     = array_keys( EMCP_Tools_Page_Builders::block_packs() );
		$available = array_values( array_filter( $known, array( 'EMCP_Tools_Page_Builders', 'available' ) ) );
		$current   = (array) get_option( EMCP_Tools_Page_Builders::BLOCK_PACK_OPTION, $known );
		$packs     = EMCP_Tools_Admin_Builders_Data::apply_packs( $current, $ids( $request->get_param( 'packs_enable' ) ), $ids( $request->get_param( 'packs_disable' ) ), $known, $available );
		update_option( EMCP_Tools_Page_Builders::BLOCK_PACK_OPTION, $packs );
		delete_transient( 'emcp_tools_nav_counts' );

		return new WP_REST_Response( ( new EMCP_Tools_Admin_Builders_Data() )->payload() );
	}
}
