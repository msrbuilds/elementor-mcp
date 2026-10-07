<?php
/**
 * Base for the admin screens' REST endpoints (spec 5.5).
 *
 * Admin endpoints are for the logged-in browser only: an agent holding an
 * application password or an OAuth token must not reach them, so tool
 * toggles, module switches and sandbox activation stay human-only.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cookie-only REST controller base.
 */
abstract class EMCP_Tools_Admin_REST_Controller {

	const REST_NAMESPACE = 'emcp-tools/v1';

	/**
	 * Capability required by default.
	 *
	 * @var string
	 */
	protected $capability = 'manage_options';

	/**
	 * Register this controller's routes.
	 */
	abstract public function register_routes(): void;

	/**
	 * Hook route registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Permission callback for every route of this controller.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function can_access( $request ) {
		$allowed = self::check( $request, $this->capability );
		// REST requests are not is_admin(), so the bootstrap never loaded the admin
		// classes the screen payloads use (module settings URLs name the admin page).
		if ( true === $allowed && class_exists( 'EMCP_Tools_Bootstrap' ) ) {
			EMCP_Tools_Bootstrap::require_admin_classes();
		}
		return $allowed;
	}

	/**
	 * Cookie-only check, in the spec's order.
	 *
	 * @param WP_REST_Request $request    Request.
	 * @param string          $capability Capability.
	 * @return true|WP_Error
	 */
	public static function check( $request, string $capability ) {
		// Core sets this to true for a logged-in cookie and to 'malformed' on
		// every cookieless request, so a truthiness check would pass everyone.
		if ( true !== ( $GLOBALS['wp_rest_auth_cookie'] ?? null ) ) {
			return new WP_Error( 'emcp_admin_cookie_required', __( 'This endpoint is only available from the WordPress admin.', 'emcp-tools' ), array( 'status' => 401 ) );
		}
		if ( function_exists( 'rest_get_authenticated_app_password' ) && null !== rest_get_authenticated_app_password() ) {
			return new WP_Error( 'emcp_admin_no_app_password', __( 'Application passwords cannot use admin endpoints.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		if ( class_exists( 'EMCP_Tools_OAuth_Bearer' ) && '' !== EMCP_Tools_OAuth_Bearer::bearer_token( $request ) ) {
			return new WP_Error( 'emcp_admin_no_bearer', __( 'Access tokens cannot use admin endpoints.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		if ( ! current_user_can( $capability ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not allowed to do that.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		if ( ! current_user_can( EMCP_Tools_Management_Access::CAPABILITY ) ) {
			return new WP_Error( 'emcp_management_forbidden', __( 'Your account cannot manage EMCP Tools on this site.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Register /emcp-tools/v1/admin/{path} with this controller's permission check.
	 *
	 * @param string $path Route path under admin/.
	 * @param array  $args register_rest_route() arguments.
	 */
	/**
	 * A parameter from the route pattern. WP_REST_Request::get_param() reads the
	 * query string before URL params, so `?category=` would override a route's
	 * {category}; route-addressed items read their ids here instead.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $key     Route parameter.
	 */
	protected static function route_param( $request, string $key ): string {
		$url = method_exists( $request, 'get_url_params' ) ? (array) $request->get_url_params() : array();
		return (string) ( $url[ $key ] ?? $request->get_param( $key ) ?? '' );
	}

	protected function route( string $path, array $args ): void {
		$check = array( 'permission_callback' => array( $this, 'can_access' ) );
		// A list of endpoints (GET plus POST) needs the check on each endpoint:
		// register_rest_route() ignores a top-level permission_callback there.
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			foreach ( $args as $i => $endpoint ) {
				if ( is_int( $i ) && is_array( $endpoint ) ) {
					$args[ $i ] = array_merge( $check, $endpoint );
				}
			}
		} else {
			$args = array_merge( $check, $args );
		}
		register_rest_route( self::REST_NAMESPACE, '/admin/' . ltrim( $path, '/' ), $args );
	}
}
