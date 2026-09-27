<?php
/**
 * History screen REST (spec 8.19). Cookie-only through the base controller.
 * Every write returns the fresh payload for the filters and page count it
 * carried, so the screen reconciles from the server.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * History REST controller.
 */
final class EMCP_Tools_Admin_REST_History extends EMCP_Tools_Admin_REST_Controller {

	const ID = '(?P<id>[a-f0-9]{6,32})';

	public function register_routes(): void {
		// Literal routes first: the first matching route wins.
		$this->route( 'history/sessions/undo', array( 'methods' => 'POST', 'callback' => array( $this, 'undo_session' ) ) );
		$this->route( 'history/sessions/rows', array( 'methods' => 'GET', 'callback' => array( $this, 'session_rows' ) ) );
		$this->route( 'history/retention', array( 'methods' => 'POST', 'callback' => array( $this, 'retention' ) ) );
		$this->route( 'history/banners/(?P<banner>unrecorded|stray)/dismiss', array( 'methods' => 'POST', 'callback' => array( $this, 'dismiss_banner' ) ) );
		$this->route(
			'history',
			array(
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get' ),
				),
				array(
					'methods'  => 'DELETE',
					'callback' => array( $this, 'clear' ),
				),
			)
		);
		$this->route( 'history/' . self::ID . '/undo', array( 'methods' => 'POST', 'callback' => array( $this, 'undo' ) ) );
		$this->route( 'history/' . self::ID . '/diff', array( 'methods' => 'GET', 'callback' => array( $this, 'diff' ) ) );
		$this->route( 'history/' . self::ID, array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete' ) ) );
	}

	public static function register_screen(): void {
		EMCP_Tools_Admin_Screens::register(
			'history',
			array(
				'script' => 'screen-history',
				'tabs'   => array( 'history' ),
				'boot'   => static function (): array {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filters, sanitized by filters().
					$get = wp_unslash( $_GET );
					return ( new EMCP_Tools_Admin_History_Data() )->payload( is_array( $get ) ? $get : array() );
				},
			)
		);
	}

	/**
	 * Filter and paging arguments a request carried.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private static function view( $request ): array {
		$out = array();
		foreach ( array( 'search', 'kind', 'client', 'range', 'from', 'to', 'before', 'limit' ) as $k ) {
			$v = $request->get_param( $k );
			if ( null !== $v ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @param mixed           $result  What the write did.
	 */
	private static function done( $request, $result ): WP_REST_Response {
		$view = self::view( $request );
		unset( $view['before'] ); // A write reloads from the newest session.
		return new WP_REST_Response(
			array(
				'result'  => $result,
				'history' => ( new EMCP_Tools_Admin_History_Data() )->payload( $view ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	private static function confirmed( $request ): ?WP_Error {
		if ( true !== rest_sanitize_boolean( $request->get_param( 'confirm' ) ) ) {
			return new WP_Error( 'confirm_required', __( 'Confirm this action to continue.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		return null;
	}

	/** A ledger error with an HTTP status: conflicts are 409, a missing change 404. */
	private static function status( WP_Error $e ): WP_Error {
		$data = $e->get_error_data();
		if ( is_array( $data ) && ! empty( $data['status'] ) ) {
			return $e;
		}
		$code = $e->get_error_code();
		$map  = array(
			'conflict'  => 409,
			'not_found' => 404,
		);
		return new WP_Error( $code, $e->get_error_message(), array( 'status' => $map[ $code ] ?? 400 ) );
	}

	public function get( $request ) {
		return new WP_REST_Response( ( new EMCP_Tools_Admin_History_Data() )->payload( self::view( $request ) ) );
	}

	public function undo( $request ) {
		$result = EMCP_Tools_Change_Log::rollback( self::route_param( $request, 'id' ), true === rest_sanitize_boolean( $request->get_param( 'force' ) ) );
		return is_wp_error( $result ) ? self::status( $result ) : self::done( $request, $result );
	}

	/**
	 * A session key as sessions() made it, or null. Validated by shape, not
	 * sanitized: sanitize_text_field() strips the %xx octets a window key's
	 * encoded client name carries.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private static function session_key( $request ): ?string {
		$key = wp_unslash( (string) $request->get_param( 'session' ) );
		$ok  = preg_match( '/^(s:[^\x00-\x1f<>"]{1,100}|w:\d+-\d+:\d+:[A-Za-z0-9%._~!*\'()-]{0,300}|c:[a-f0-9]{6,32})$/', $key );
		return 1 === $ok ? $key : null;
	}

	private static function invalid_session(): WP_Error {
		return new WP_Error( 'invalid_session', __( 'That session key is not valid.', 'emcp-tools' ), array( 'status' => 400 ) );
	}

	public function session_rows( $request ) {
		$key = self::session_key( $request );
		if ( null === $key ) {
			return self::invalid_session();
		}
		return new WP_REST_Response( ( new EMCP_Tools_Admin_History_Data() )->rows( $key, (int) $request->get_param( 'before' ), self::view( $request ) ) );
	}

	public function undo_session( $request ) {
		$confirm = self::confirmed( $request );
		if ( $confirm ) {
			return $confirm;
		}
		$key = self::session_key( $request );
		if ( null === $key ) {
			return self::invalid_session();
		}
		$result = EMCP_Tools_Change_Log::rollback_session( $key, true === rest_sanitize_boolean( $request->get_param( 'force' ) ) );
		if ( is_wp_error( $result ) ) {
			return self::status( $result );
		}
		$stopped = null !== $result['stopped_at'] ? EMCP_Tools_Change_Log::get( (string) $result['stopped_at'] ) : null;
		return self::done(
			$request,
			array(
				'undone'       => $result['undone'],
				'stoppedAt'    => $result['stopped_at'],
				'stoppedTitle' => $stopped ? ( '' !== $stopped['summary'] ? $stopped['summary'] : $stopped['action'] ) : '',
				'reason'       => (string) $result['reason'],
				'remaining'    => (int) $result['remaining'],
				'total'        => count( $result['undone'] ) + (int) $result['remaining'],
			)
		);
	}

	public function diff( $request ) {
		return new WP_REST_Response( EMCP_Tools_Change_Diff::for_entry( self::route_param( $request, 'id' ) ) );
	}

	public function delete( $request ) {
		$confirm = self::confirmed( $request );
		if ( $confirm ) {
			return $confirm;
		}
		$id = self::route_param( $request, 'id' );
		if ( null === EMCP_Tools_Change_Log::get( $id ) ) {
			return new WP_Error( 'not_found', __( 'That change no longer exists.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		if ( ! EMCP_Tools_Change_Log::delete( $id ) ) {
			$error = EMCP_Tools_Change_Log::last_error();
			return $error ? $error : new WP_Error( 'delete_failed', __( 'The change could not be deleted.', 'emcp-tools' ), array( 'status' => 500 ) );
		}
		return self::done( $request, array( 'deleted' => true ) );
	}

	public function clear( $request ) {
		$confirm = self::confirmed( $request );
		if ( $confirm ) {
			return $confirm;
		}
		$count = EMCP_Tools_Change_Log::clear();
		$error = EMCP_Tools_Change_Log::last_error();
		if ( $error ) {
			return $error;
		}
		return self::done( $request, array( 'cleared' => $count ) );
	}

	public function retention( $request ) {
		$days = (int) $request->get_param( 'days' );
		if ( ! in_array( $days, EMCP_Tools_Change_Retention::ALLOWED, true ) ) {
			return new WP_Error( 'invalid_retention', __( 'Choose 30, 90, 180 or 365 days.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		update_option( EMCP_Tools_Change_Names::retention(), $days, false );
		return self::done( $request, array( 'days' => $days ) );
	}

	public function dismiss_banner( $request ) {
		$banner = self::route_param( $request, 'banner' );
		if ( 'stray' === $banner ) {
			EMCP_Tools_Change_Notices::dismiss_stray();
		} elseif ( 'unrecorded' === $banner ) {
			EMCP_Tools_Change_Log::store()->storage()->delete_meta( EMCP_Tools_Change_Names::unrecorded() );
		} else {
			return new WP_Error( 'not_found', __( 'Unknown banner.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		return self::done( $request, array( 'dismissed' => $banner ) );
	}
}
