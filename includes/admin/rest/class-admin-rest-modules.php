<?php
/**
 * Modules screen REST (spec 8.4).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_REST_Modules extends EMCP_Tools_Admin_REST_Controller {

	public function register_routes(): void {
		$this->route(
			'modules',
			array(
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get' ),
				),
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'save' ),
					'args'     => array(
						'activate'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'default' => array() ),
						'deactivate' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'default' => array() ),
					),
				),
			)
		);
		$this->route(
			'modules/(?P<id>[a-z0-9-]+)/settings',
			array(
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_settings' ),
				),
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'save_settings' ),
					'args'     => array( 'values' => array( 'type' => 'object', 'default' => array() ) ),
				),
			)
		);
		$this->route(
			'modules/image-optimization/bulk',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'bulk' ),
				'args'     => array( 'batch' => array( 'type' => 'integer', 'default' => 10 ) ),
			)
		);
		$this->route(
			'modules/image-optimization/restore',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'restore' ),
			)
		);
	}

	public static function register_screen(): void {
		EMCP_Tools_Admin_Screens::register(
			'modules',
			array(
				'script' => 'screen-modules',
				'tabs'   => array( 'modules' ),
				'boot'   => static function (): array {
					return ( new EMCP_Tools_Admin_Modules_Data() )->payload();
				},
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public function get( $request ) {
		return new WP_REST_Response( ( new EMCP_Tools_Admin_Modules_Data() )->payload() );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public function save( $request ) {
		$ids       = static function ( $list ): array {
			return array_values( array_map( 'sanitize_key', array_map( 'strval', array_filter( (array) $list, 'is_scalar' ) ) ) );
		};
		$modules   = EMCP_Tools_Modules_Registry::instance()->all();
		$known     = array_map( static function ( $m ) { return $m->id(); }, $modules );
		$available = array_map( static function ( $m ) { return $m->id(); }, array_filter( $modules, static function ( $m ) { return $m->is_available(); } ) );
		$result    = EMCP_Tools_Admin_Modules_Data::apply(
			(array) get_option( EMCP_Tools_Module::OPTION_ACTIVE, array() ),
			$ids( $request->get_param( 'activate' ) ),
			$ids( $request->get_param( 'deactivate' ) ),
			array_values( $known ),
			array_values( $available )
		);
		update_option( EMCP_Tools_Module::OPTION_ACTIVE, $result['active'] );
		delete_transient( 'emcp_tools_nav_counts' );
		if ( class_exists( 'EMCP_Tools_Attention' ) ) {
			EMCP_Tools_Attention::flush();
		}
		return new WP_REST_Response( array_merge( ( new EMCP_Tools_Admin_Modules_Data() )->payload(), array( 'ignored' => $result['ignored'] ) ) );
	}

	/**
	 * The module a settings request names, or an error.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return EMCP_Tools_Module|WP_Error
	 */
	private function module( $request ) {
		$module = EMCP_Tools_Modules_Registry::instance()->get( sanitize_key( (string) $request->get_param( 'id' ) ) );
		if ( ! $module || array() === $module->settings_schema() ) {
			return new WP_Error( 'unknown_module', __( 'That module has no settings here.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		if ( ! $module->is_active() ) {
			return new WP_Error( 'module_inactive', __( 'Turn the module on and save before changing its settings.', 'emcp-tools' ), array( 'status' => 409 ) );
		}
		return $module;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public function get_settings( $request ) {
		$module = $this->module( $request );
		return is_wp_error( $module ) ? $module : new WP_REST_Response( ( new EMCP_Tools_Admin_Modules_Data() )->settings( $module ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public function save_settings( $request ) {
		$module = $this->module( $request );
		if ( is_wp_error( $module ) ) {
			return $module;
		}
		$data   = new EMCP_Tools_Admin_Modules_Data();
		$result = $data->save_settings( $module, (array) $request->get_param( 'values' ) );
		return new WP_REST_Response( array_merge( $data->settings( $module ), array( 'ignored' => $result['ignored'] ) ) );
	}

	/** The active Image Optimization module's optimizer, or an error. */
	private function optimizer() {
		$module = EMCP_Tools_Modules_Registry::instance()->get( 'image-optimization' );
		if ( ! $module instanceof EMCP_Tools_Image_Optimization_Module || ! $module->is_active() || ! $module->is_available() ) {
			return new WP_Error( 'module_inactive', __( 'Turn Image Optimization on first.', 'emcp-tools' ), array( 'status' => 409 ) );
		}
		return new EMCP_Tools_Bulk_Optimizer( $module->current_settings() );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public function bulk( $request ) {
		$optimizer = $this->optimizer();
		return is_wp_error( $optimizer ) ? $optimizer : new WP_REST_Response( $optimizer->run_batch( (int) $request->get_param( 'batch' ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public function restore( $request ) {
		$optimizer = $this->optimizer();
		return is_wp_error( $optimizer ) ? $optimizer : new WP_REST_Response( $optimizer->restore() );
	}
}
