<?php
/**
 * Connection screen REST (spec 8.2, 9.5).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_REST_Connection extends EMCP_Tools_Admin_REST_Controller {

	const CLIENT_ID = '(?P<id>[A-Za-z0-9_.-]+)';

	public function register_routes(): void {
		$this->route( 'connection', array( 'methods' => 'GET', 'callback' => array( $this, 'get' ) ) );
		$this->route(
			'connection/advanced',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'save_advanced' ),
				'args'     => array(
					'server_enabled'  => array( 'type' => 'boolean' ),
					'oauth_enabled'   => array( 'type' => 'boolean' ),
					'strict_schemas'  => array( 'type' => 'boolean' ),
					'public_base_url' => array( 'type' => 'string' ),
				),
			)
		);
		$this->route( 'connection/services', array( 'methods' => 'POST', 'callback' => array( $this, 'save_services' ), 'args' => array( 'values' => array( 'type' => 'object', 'default' => array() ) ) ) );
		$this->route( 'connection/app-passwords', array( 'methods' => 'GET', 'callback' => array( $this, 'app_passwords' ), 'args' => array( 'user_id' => array( 'type' => 'integer', 'default' => 0 ) ) ) );
		$this->route(
			'connection/app-password',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'create_app_password' ),
				'args'     => array(
					'user_id' => array( 'type' => 'integer', 'required' => true ),
					'setup'   => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);
		$this->route(
			'connection/test',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'test' ),
				'args'     => array(
					'username' => array( 'type' => 'string', 'required' => true ),
					'password' => array( 'type' => 'string', 'required' => true ),
				),
			)
		);
		$this->route( 'connection/oauth-discovery', array( 'methods' => 'POST', 'callback' => array( $this, 'oauth_discovery' ) ) );
		$this->route(
			'connection/setup',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'open_setup' ),
				'args'     => array(
					'client'  => array( 'type' => 'string', 'required' => true ),
					'method'  => array( 'type' => 'string', 'required' => true ),
					'expect'  => array( 'type' => 'string', 'default' => '' ),
					'user_id' => array( 'type' => 'integer', 'default' => 0 ),
				),
			)
		);
		$this->route( 'connection/first-call', array( 'methods' => 'GET', 'callback' => array( $this, 'first_call' ), 'args' => array( 'setup' => array( 'type' => 'string', 'required' => true ) ) ) );
		$this->route( 'connection/oauth-clients/' . self::CLIENT_ID . '/revoke', array( 'methods' => 'POST', 'callback' => array( $this, 'revoke_app' ) ) );
		$this->route( 'connection/oauth-clients/' . self::CLIENT_ID, array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete_app' ) ) );
	}

	public static function register_screen( EMCP_Tools_Admin $admin ): void {
		EMCP_Tools_Admin_Screens::register(
			'connection',
			array(
				'script' => 'screen-connection',
				'tabs'   => array( 'connection' ),
				'boot'   => static function () use ( $admin ): array {
					return ( new EMCP_Tools_Admin_Connection_Data( $admin ) )->payload();
				},
			)
		);
	}

	private function admin(): EMCP_Tools_Admin {
		return new EMCP_Tools_Admin();
	}

	private function data(): EMCP_Tools_Admin_Connection_Data {
		return new EMCP_Tools_Admin_Connection_Data( $this->admin() );
	}

	public function get( $request ) {
		return new WP_REST_Response( $this->data()->payload() );
	}

	public function save_advanced( $request ) {
		$data   = $this->data();
		$in     = array();
		foreach ( array( 'server_enabled', 'oauth_enabled', 'strict_schemas', 'public_base_url' ) as $key ) {
			$in[ $key ] = $request->get_param( $key );
		}
		$result = $data->apply_advanced( $in );
		delete_transient( 'emcp_tools_nav_counts' );
		return new WP_REST_Response(
			array(
				'advanced' => $data->advanced(),
				'status'   => $data->status(),
				'oauth'    => $data->oauth(),
				'ignored'  => $result['ignored'],
			)
		);
	}

	public function save_services( $request ) {
		$data   = $this->data();
		$result = $data->apply_services( (array) $request->get_param( 'values' ) );
		return new WP_REST_Response( array( 'services' => $data->services(), 'ignored' => $result['ignored'] ) );
	}

	public function app_passwords( $request ) {
		$user_id = (int) $request->get_param( 'user_id' );
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You cannot manage application passwords for this user.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		return new WP_REST_Response( array( 'passwords' => $this->admin()->list_app_passwords( $user_id ) ) );
	}

	public function create_app_password( $request ) {
		$result = $this->admin()->create_app_password_for( (int) $request->get_param( 'user_id' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$record = EMCP_Tools_Connection_Setup::get( get_current_user_id() );
		$setup  = (string) $request->get_param( 'setup' );
		if ( '' !== $setup && null !== $record && hash_equals( (string) $record['setup_id'], $setup ) && 'app' === $record['method'] && '' !== $result['uuid'] ) {
			EMCP_Tools_Connection_Setup::set_expect( get_current_user_id(), 'app:' . $result['uuid'] );
		}
		return new WP_REST_Response( $result );
	}

	public function test( $request ) {
		$result = $this->admin()->run_mcp_handshake( sanitize_text_field( (string) $request->get_param( 'username' ) ), trim( (string) $request->get_param( 'password' ) ) );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message(), 'stage' => $result->get_error_code() ) );
		}
		return new WP_REST_Response( array_merge( array( 'ok' => true ), $result ) );
	}

	public function oauth_discovery( $request ) {
		$report = $this->admin()->oauth_discovery_report();
		return is_wp_error( $report ) ? $report : new WP_REST_Response( $report );
	}

	public function open_setup( $request ) {
		$user_id = get_current_user_id();
		$client  = sanitize_key( (string) $request->get_param( 'client' ) );
		$method  = sanitize_key( (string) $request->get_param( 'method' ) );
		$expect  = sanitize_text_field( (string) $request->get_param( 'expect' ) );
		$valid   = $this->data()->validate_setup( $user_id, $client, $method, $expect, (int) $request->get_param( 'user_id' ) );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$record = EMCP_Tools_Connection_Setup::open( $user_id, $client, $method, $expect );
		return new WP_REST_Response(
			array(
				'setup_id' => $record['setup_id'],
				'token'    => $record['token'],
				'since'    => $record['since'],
				'expires'  => $record['expires'],
			)
		);
	}

	public function first_call( $request ) {
		$user_id = get_current_user_id();
		$record  = EMCP_Tools_Connection_Setup::renew( $user_id );
		if ( null === $record || ! hash_equals( (string) $record['setup_id'], (string) $request->get_param( 'setup' ) ) ) {
			return new WP_Error( 'emcp_setup_gone', __( 'This setup has expired. Start again from step 1.', 'emcp-tools' ), array( 'status' => 410 ) );
		}
		$result = EMCP_Tools_Connection_First_Call::check( $record, EMCP_Tools_MCP_Request_Log::all() );
		if ( ! empty( $result['matched'] ) ) {
			EMCP_Tools_Connection_Setup::close( $user_id );
		}
		return new WP_REST_Response( $result );
	}

	public function revoke_app( $request ) {
		$id = (string) $request->get_param( 'id' );
		if ( class_exists( 'EMCP_Tools_Gateway_Credential' ) ) {
			EMCP_Tools_Gateway_Credential::handle_client_revoked( $id );
		}
		EMCP_Tools_OAuth_Store::revoke_client( $id );
		return new WP_REST_Response( array( 'apps' => $this->data()->apps() ) );
	}

	public function delete_app( $request ) {
		$id = (string) $request->get_param( 'id' );
		if ( class_exists( 'EMCP_Tools_Gateway_Credential' ) ) {
			EMCP_Tools_Gateway_Credential::handle_client_revoked( $id );
		}
		EMCP_Tools_OAuth_Store::delete_client( $id );
		return new WP_REST_Response( array( 'apps' => $this->data()->apps() ) );
	}
}
