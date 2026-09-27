<?php
/**
 * Redirects screen REST (spec 8.21). Cookie-only through the base controller;
 * every write records through EMCP_Tools_Change_Recorder::record_redirect(),
 * so History can undo it.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirects REST controller.
 */
final class EMCP_Tools_Admin_REST_Redirects extends EMCP_Tools_Admin_REST_Controller {

	/**
	 * Register the routes (literal routes first: the first match wins).
	 */
	public function register_routes(): void {
		$this->route( 'redirects/targets', array( 'methods' => 'GET', 'callback' => array( $this, 'targets' ) ) );
		$this->route( 'redirects/suggestions/(?P<key>[a-f0-9]{12})/dismiss', array( 'methods' => 'POST', 'callback' => array( $this, 'dismiss' ) ) );
		$this->route( 'redirects/suggestions/(?P<key>[a-f0-9]{12})/accept', array( 'methods' => 'POST', 'callback' => array( $this, 'accept' ) ) );
		$this->route(
			'redirects',
			array(
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get' ),
				),
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'create' ),
				),
			)
		);
		$this->route(
			'redirects/(?P<id>\d+)',
			array(
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'update' ),
				),
				array(
					'methods'  => 'DELETE',
					'callback' => array( $this, 'delete' ),
				),
			)
		);
	}

	/**
	 * Register the Redirects screen.
	 */
	public static function register_screen(): void {
		EMCP_Tools_Admin_Screens::register(
			'redirects',
			array(
				'script' => 'screen-redirects',
				'tabs'   => array( 'redirects' ),
				'boot'   => static function (): array {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state, sanitized by payload().
					$get = wp_unslash( $_GET );
					$get = is_array( $get ) ? $get : array();
					return ( new EMCP_Tools_Admin_Redirects_Data() )->payload(
						array(
							'search' => $get['search'] ?? '',
							'page'   => $get['paged'] ?? 1,
						)
					);
				},
			)
		);
	}

	/**
	 * The list for the search and page a request carried.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private static function list_for( $request ): array {
		return ( new EMCP_Tools_Admin_Redirects_Data() )->payload(
			array(
				'search' => (string) $request->get_param( 'search' ),
				'page'   => (int) $request->get_param( 'page' ),
			)
		);
	}

	/**
	 * The form fields a request carried.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private static function body( $request ): array {
		$out = array();
		foreach ( array( 'source', 'target', 'targetPostId', 'code', 'ignoreQuery', 'enabled' ) as $k ) {
			$v = $request->get_param( $k );
			if ( null !== $v ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	/**
	 * A store error as a REST error.
	 *
	 * @param WP_Error $e Store error.
	 * @return WP_Error
	 */
	private static function error( WP_Error $e ): WP_Error {
		$status = 'not_found' === $e->get_error_code() ? 404 : 400;
		return new WP_Error( $e->get_error_code(), $e->get_error_message(), array( 'status' => $status ) );
	}

	/**
	 * A write response: the result plus the fresh list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param array           $result  Result.
	 * @return WP_REST_Response
	 */
	private static function done( $request, array $result ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'result' => $result,
				'list'   => self::list_for( $request ),
			)
		);
	}

	/**
	 * GET admin/redirects.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get( $request ) {
		return new WP_REST_Response( self::list_for( $request ) );
	}

	/**
	 * Create a rule and record it; shared with accepting a suggestion.
	 *
	 * @param array $data Store data.
	 * @return array|WP_Error
	 */
	private static function create_row( array $data ) {
		$row = EMCP_Tools_Redirect_Store::create( $data );
		if ( is_wp_error( $row ) ) {
			return self::error( $row );
		}
		if ( ! $row ) {
			return new WP_Error( 'save_failed', __( 'The redirect could not be saved.', 'emcp-tools' ), array( 'status' => 500 ) );
		}
		EMCP_Tools_Change_Recorder::record_redirect( 'create', array( 'id' => (int) $row['id'] ), sprintf( 'Created redirect %s', $row['source_path'] ), (string) $row['source_path'] );
		return $row;
	}

	/**
	 * POST admin/redirects.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( $request ) {
		$row = self::create_row( EMCP_Tools_Admin_Redirects_Data::input( self::body( $request ) ) );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		return self::done(
			$request,
			array(
				'row'     => EMCP_Tools_Admin_Redirects_Data::row( $row ),
				'warning' => EMCP_Tools_Redirect_Store::shadow_warning( (string) $row['source_path'] ),
			)
		);
	}

	/**
	 * POST admin/redirects/{id}: edit or toggle.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update( $request ) {
		$id    = (int) self::route_param( $request, 'id' );
		$prior = EMCP_Tools_Redirect_Store::get( $id );
		if ( ! $prior ) {
			return new WP_Error( 'not_found', __( 'Redirect not found.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		$data = EMCP_Tools_Admin_Redirects_Data::input( self::body( $request ) );
		$row  = EMCP_Tools_Redirect_Store::update( $id, $data );
		if ( is_wp_error( $row ) ) {
			return self::error( $row );
		}
		$verb = array( 'enabled' ) === array_keys( $data ) ? 'Toggled' : 'Updated';
		EMCP_Tools_Change_Recorder::record_redirect( 'update', array( 'row' => $prior ), sprintf( '%s redirect %s', $verb, $row['source_path'] ), (string) $row['source_path'] );
		return self::done(
			$request,
			array(
				'row'     => EMCP_Tools_Admin_Redirects_Data::row( $row ),
				'warning' => '',
			)
		);
	}

	/**
	 * DELETE admin/redirects/{id} (needs confirm: true).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete( $request ) {
		if ( true !== rest_sanitize_boolean( $request->get_param( 'confirm' ) ) ) {
			return new WP_Error( 'confirm_required', __( 'Confirm this action to continue.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$id    = (int) self::route_param( $request, 'id' );
		$prior = EMCP_Tools_Redirect_Store::get( $id );
		if ( ! $prior || ! EMCP_Tools_Redirect_Store::delete( $id ) ) {
			return new WP_Error( 'not_found', __( 'Redirect not found.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		EMCP_Tools_Change_Recorder::record_redirect( 'delete', array( 'row' => $prior ), sprintf( 'Deleted redirect %s', $prior['source_path'] ), (string) $prior['source_path'] );
		return self::done( $request, array( 'deleted' => true ) );
	}

	/**
	 * POST admin/redirects/suggestions/{key}/dismiss.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function dismiss( $request ) {
		if ( ! EMCP_Tools_Admin_Redirects_Data::remove_suggestion( (string) self::route_param( $request, 'key' ) ) ) {
			return new WP_Error( 'not_found', __( 'That suggestion is gone.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		return self::done( $request, array( 'dismissed' => true ) );
	}

	/**
	 * POST admin/redirects/suggestions/{key}/accept: create the rule from the
	 * suggestion's old path, then drop the suggestion.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function accept( $request ) {
		$key = (string) self::route_param( $request, 'key' );
		$hit = null;
		foreach ( EMCP_Tools_Admin_Redirects_Data::suggestions() as $s ) {
			if ( $s['key'] === $key ) {
				$hit = $s;
				break;
			}
		}
		if ( ! $hit ) {
			return new WP_Error( 'not_found', __( 'That suggestion is gone.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		$body           = self::body( $request );
		$body['source'] = $hit['oldPath'];
		$row            = self::create_row( EMCP_Tools_Admin_Redirects_Data::input( $body ) );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		EMCP_Tools_Admin_Redirects_Data::remove_suggestion( $key );
		return self::done(
			$request,
			array(
				'row'     => EMCP_Tools_Admin_Redirects_Data::row( $row ),
				'warning' => EMCP_Tools_Redirect_Store::shadow_warning( (string) $row['source_path'] ),
			)
		);
	}

	/**
	 * GET admin/redirects/targets?search: published posts for the To field.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function targets( $request ) {
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$posts  = get_posts(
			array(
				'post_type'        => array_values( get_post_types( array( 'public' => true ) ) ),
				'post_status'      => 'publish',
				's'                => $search,
				'posts_per_page'   => 10,
				'orderby'          => '' === $search ? 'modified' : 'relevance',
				'suppress_filters' => false,
			)
		);
		$items  = array();
		foreach ( $posts as $p ) {
			$items[] = array(
				'id'    => (int) $p->ID,
				'title' => '' !== (string) $p->post_title ? (string) $p->post_title : sprintf( '#%d', (int) $p->ID ),
				'type'  => (string) $p->post_type,
				'url'   => (string) get_permalink( $p ),
			);
		}
		return new WP_REST_Response( array( 'items' => $items ) );
	}
}
