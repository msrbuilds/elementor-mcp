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
				),
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'create' ),
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
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'edit' ),
				),
				array(
					'methods'  => 'DELETE',
					'callback' => array( $this, 'delete' ),
				),
			)
		);
		$post = array(
			'sandbox/' . self::TYPE . '/(?P<id>\d+)/status'           => 'status',
			'sandbox/import'                                          => 'import',
			'sandbox/' . self::TYPE . '/(?P<id>\d+)/cloud'            => 'cloud_save',
			'sandbox/' . self::TYPE . '/cloud/bulk'                   => 'cloud_bulk',
			'sandbox/' . self::TYPE . '/cloud/refresh'                => 'cloud_refresh',
			'sandbox/cloud/library/(?P<uuid>[A-Za-z0-9-]+)/import'    => 'library_import',
		);
		foreach ( $post as $path => $callback ) {
			$this->route(
				$path,
				array(
					'methods'  => 'POST',
					'callback' => array( $this, $callback ),
				)
			);
		}
		$get = array(
			'sandbox/' . self::TYPE . '/(?P<id>\d+)/export' => 'export',
			'sandbox/blocks/(?P<id>\d+)/preview'            => 'preview',
			'sandbox/cloud/library'                         => 'library',
		);
		foreach ( $get as $path => $callback ) {
			$this->route(
				$path,
				array(
					'methods'  => 'GET',
					'callback' => array( $this, $callback ),
				)
			);
		}
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
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Data() )->detail( self::type( $request ), self::id( $request ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	private static function type( $request ): string {
		return self::route_param( $request, 'type' );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	private static function id( $request ): int {
		return (int) self::route_param( $request, 'id' );
	}

	/**
	 * Snippet fields present in the request.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private static function snippet_args( $request ): array {
		$out = array();
		foreach ( array( 'title', 'code', 'context', 'hook', 'priority' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * Only snippets are written by hand here; widgets and blocks come from specs.
	 *
	 * @param string $type Type.
	 * @return true|WP_Error
	 */
	private static function snippets_only( string $type ) {
		return 'snippets' === $type ? true : new WP_Error( 'rest_no_route', __( 'Only PHP snippets are written here.', 'emcp-tools' ), array( 'status' => 404 ) );
	}

	public function create( $request ) {
		$only = self::snippets_only( self::type( $request ) );
		return is_wp_error( $only ) ? $only : self::respond( ( new EMCP_Tools_Admin_Sandbox_Data() )->save_snippet( 0, self::snippet_args( $request ), self::query( $request ) ) );
	}

	public function edit( $request ) {
		$only = self::snippets_only( self::type( $request ) );
		return is_wp_error( $only ) ? $only : self::respond( ( new EMCP_Tools_Admin_Sandbox_Data() )->save_snippet( self::id( $request ), self::snippet_args( $request ), self::query( $request ) ) );
	}

	public function delete( $request ) {
		if ( ! rest_sanitize_boolean( $request->get_param( 'confirm' ) ) ) {
			return new WP_Error( 'emcp_confirm_required', __( 'Confirm the deletion first.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Data() )->delete( self::type( $request ), self::id( $request ), self::query( $request ) ) );
	}

	public function status( $request ) {
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Data() )->set_status( self::type( $request ), self::id( $request ), rest_sanitize_boolean( $request->get_param( 'active' ) ), self::query( $request ) ) );
	}

	public function export( $request ) {
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Data() )->export_bundle( self::type( $request ), self::id( $request ) ) );
	}

	public function preview( $request ) {
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Data() )->block_preview( self::id( $request ) ) );
	}

	/**
	 * Multipart upload, checked like the legacy admin_post handler.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function import( $request ) {
		$files = method_exists( $request, 'get_file_params' ) ? (array) $request->get_file_params() : array();
		$file  = isset( $files['bundle'] ) && is_array( $files['bundle'] ) ? $files['bundle'] : array();
		$bad   = static function ( string $message ): WP_Error {
			return new WP_Error( 'emcp_sandbox_bad_bundle', $message, array( 'status' => 400 ) );
		};
		if ( empty( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? -1 ) ) {
			return $bad( __( 'No bundle file was uploaded, or the upload failed.', 'emcp-tools' ) );
		}
		if ( (int) ( $file['size'] ?? 0 ) <= 0 || (int) $file['size'] > 2 * MB_IN_BYTES ) {
			return $bad( __( 'The bundle file is empty or larger than 2 MB.', 'emcp-tools' ) );
		}
		if ( '.json' !== strtolower( substr( sanitize_file_name( (string) ( $file['name'] ?? '' ) ), -5 ) ) ) {
			return $bad( __( 'The bundle must be a .json file.', 'emcp-tools' ) );
		}
		$tmp = (string) ( $file['tmp_name'] ?? '' );
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return $bad( __( 'The upload could not be read.', 'emcp-tools' ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a checked PHP upload.
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Data() )->import_json( (string) file_get_contents( $tmp ) ) );
	}

	public function cloud_save( $request ) {
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Cloud_Data() )->save( self::type( $request ), self::id( $request ), self::query( $request ) ) );
	}

	public function cloud_bulk( $request ) {
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Cloud_Data() )->bulk( self::type( $request ), self::query( $request ) ) );
	}

	public function cloud_refresh( $request ) {
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Cloud_Data() )->refresh( self::type( $request ), self::query( $request ) ) );
	}

	public function library( $request ) {
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Cloud_Data() )->library( sanitize_key( (string) $request->get_param( 'kind' ) ), rest_sanitize_boolean( $request->get_param( 'refresh' ) ) ) );
	}

	public function library_import( $request ) {
		return self::respond( ( new EMCP_Tools_Admin_Sandbox_Cloud_Data() )->library_import( sanitize_key( (string) $request->get_param( 'kind' ) ), self::route_param( $request, 'uuid' ) ) );
	}
}
