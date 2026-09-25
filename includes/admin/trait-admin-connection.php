<?php
/**
 * Connection clients, credentials, diagnostics, and bundle downloads.
 *
 * Internal implementation of EMCP_Tools_Admin; loaded by class-admin.php.
 * Methods retain the admin class scope for existing callbacks and callers.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connection clients, credentials, diagnostics, and bundle downloads.
 */
trait EMCP_Tools_Admin_Connection_Trait {

	/**
	 * Revoke every token issued to an OAuth client (disconnects it).
	 */
	public function handle_revoke_oauth_client(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified just below against the per-client action.
		$client_id = isset( $_GET['client'] ) ? sanitize_text_field( wp_unslash( $_GET['client'] ) ) : '';
		check_admin_referer( self::ACTION_REVOKE_OAUTH . '_' . $client_id );

		if ( '' !== $client_id && class_exists( 'EMCP_Tools_Gateway_Credential' ) ) {
			// Run before revoke_client() below so the gateway teardown observes the
			// still-live token count. (Identity itself survives revoke_client(), which
			// only deletes token rows, not the client registration.)
			EMCP_Tools_Gateway_Credential::handle_client_revoked( $client_id );
		}

		if ( '' !== $client_id && class_exists( 'EMCP_Tools_OAuth_Store' ) ) {
			EMCP_Tools_OAuth_Store::revoke_client( $client_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-connection&oauth_revoked=1#emcp-conn-manage-apps' ) );
		exit;
	}

	/**
	 * Delete an OAuth client registration and every token issued to it.
	 *
	 * The recovery path for a registration an app can no longer use: it asks to
	 * come back at a different callback than the one it registered, so every
	 * authorization attempt is refused and nothing on the client side clears it.
	 * Removing the row here means the next connection attempt registers afresh.
	 *
	 * @since 3.15.0
	 */
	public function handle_delete_oauth_client(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified just below against the per-client action.
		$client_id = isset( $_GET['client'] ) ? sanitize_text_field( wp_unslash( $_GET['client'] ) ) : '';
		check_admin_referer( self::ACTION_DELETE_OAUTH_CLIENT . '_' . $client_id );

		if ( '' !== $client_id && class_exists( 'EMCP_Tools_Gateway_Credential' ) ) {
			// Same ordering as the revoke path: the teardown wants to see the
			// token count before the tokens go.
			EMCP_Tools_Gateway_Credential::handle_client_revoked( $client_id );
		}

		$removed = ( '' !== $client_id && class_exists( 'EMCP_Tools_OAuth_Store' ) )
			? EMCP_Tools_OAuth_Store::delete_client( $client_id )
			: false;

		wp_safe_redirect(
			admin_url(
				'admin.php?page=' . self::PAGE_SLUG . '-connection&oauth_removed=' . ( $removed ? '1' : '0' ) . '#emcp-conn-manage-apps'
			)
		);
		exit;
	}

	/**
	 * AJAX: create a fresh Application Password for a chosen administrator.
	 *
	 * Returns the chunked plaintext password once so the Connection tab can drop
	 * it straight into the generated client configs — no profile visit needed.
	 *
	 * @since 1.8.3
	 */
	public function ajax_create_app_password(): void {
		check_ajax_referer( 'emcp_tools_create_app_password', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$result = $this->create_app_password_for( $user_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), (int) ( $result->get_error_data()['status'] ?? 400 ) );
		}
		unset( $result['uuid'] );
		wp_send_json_success( $result );
	}

	/**
	 * Create an application password for an administrator (shared by the
	 * legacy AJAX handler and admin REST). Returns the password once.
	 *
	 * @param int $user_id Administrator to create it for.
	 * @return array{username:string, password:string, name:string, uuid:string}|WP_Error
	 */
	public function create_app_password_for( int $user_id ) {
		if ( ! $user_id ) {
			return new WP_Error( 'emcp_app_password', __( 'No user selected.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'emcp_app_password', __( 'That user no longer exists.', 'emcp-tools' ), array( 'status' => 404 ) );
		}

		// Only administrators, and only those the current user is allowed to edit.
		if ( ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'emcp_app_password', __( 'Application passwords can only be generated for administrator accounts here.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return new WP_Error( 'emcp_app_password', __( 'You cannot manage application passwords for this user.', 'emcp-tools' ), array( 'status' => 403 ) );
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return new WP_Error( 'emcp_app_password', __( 'Application Passwords are not supported on this WordPress version.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		// Respect WordPress core and site-policy availability filters. A security
		// plugin may disable application passwords globally or for this user even
		// when the core class exists.
		if ( function_exists( 'wp_is_application_passwords_available' ) && ! wp_is_application_passwords_available() ) {
			return new WP_Error( 'emcp_app_password', __( 'Application Passwords are disabled for this site. Check HTTPS and any security-plugin policy, or use OAuth.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		if ( function_exists( 'wp_is_application_passwords_available_for_user' ) && ! wp_is_application_passwords_available_for_user( $user ) ) {
			return new WP_Error( 'emcp_app_password', __( 'Application Passwords are disabled for this user by site policy.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		// Compatibility fallback for WordPress versions without the availability
		// helper (the plugin normally requires a newer core release).
		if ( ! function_exists( 'wp_is_application_passwords_available' ) && ! is_ssl() && 'local' !== wp_get_environment_type() ) {
			return new WP_Error( 'emcp_app_password', __( 'Application Passwords require HTTPS.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		$app_name = sprintf(
			/* translators: %s: current date and time */
			__( 'EMCP Tools (MCP), %s', 'emcp-tools' ),
			gmdate( 'Y-m-d H:i' )
		);

		$created = \WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => $app_name ) );

		if ( is_wp_error( $created ) ) {
			return new WP_Error( 'emcp_app_password', $created->get_error_message(), array( 'status' => 400 ) );
		}

		$raw_password = isset( $created[0] ) ? $created[0] : '';
		if ( '' === $raw_password ) {
			return new WP_Error( 'emcp_app_password', __( 'Could not create an application password.', 'emcp-tools' ), array( 'status' => 500 ) );
		}

		return array(
			'username' => $user->user_login,
			'password' => \WP_Application_Passwords::chunk_password( $raw_password ),
			'name'     => $app_name,
			'uuid'     => (string) ( $created[1]['uuid'] ?? '' ),
		);
	}

	/**
	 * The user's application passwords, without secrets, newest first.
	 *
	 * @param int $user_id User.
	 * @return array<int, array{uuid:string, name:string, created:int, last_used:int|null}>
	 */
	public function list_app_passwords( int $user_id ): array {
		if ( ! class_exists( 'WP_Application_Passwords' ) || ! $user_id ) {
			return array();
		}
		$out = array();
		foreach ( (array) \WP_Application_Passwords::get_user_application_passwords( $user_id ) as $item ) {
			$out[] = array(
				'uuid'      => (string) ( $item['uuid'] ?? '' ),
				'name'      => (string) ( $item['name'] ?? '' ),
				'created'   => (int) ( $item['created'] ?? 0 ),
				'last_used' => isset( $item['last_used'] ) ? (int) $item['last_used'] : null,
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $b['created'] <=> $a['created'];
			}
		);
		return $out;
	}

	/**
	 * AJAX: test Application Password credentials against the real MCP endpoint.
	 *
	 * Unlike the old `/wp/v2/users/me` probe, this exercises the complete MCP
	 * session lifecycle and therefore catches transport, routing, session, and
	 * tool-registration failures as well as a stripped Authorization header.
	 *
	 * @since 3.15.0
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'emcp_tools_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to run this test.', 'emcp-tools' ) ), 403 );
		}

		$username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
		$password = isset( $_POST['password'] ) ? trim( (string) wp_unslash( $_POST['password'] ) ) : '';
		if ( '' === $username || '' === $password ) {
			wp_send_json_error( array( 'message' => __( 'Enter a username and Application Password first.', 'emcp-tools' ) ), 400 );
		}

		$result = $this->run_mcp_handshake( $username, $password );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'stage'   => $result->get_error_code(),
				),
				400
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Run initialize → notifications/initialized → tools/list via public HTTP.
	 *
	 * @param string $username WordPress login.
	 * @param string $password Application Password.
	 * @return array|WP_Error
	 */
	public function run_mcp_handshake( string $username, string $password ) {
		$endpoint = class_exists( 'EMCP_Tools_Site_Context' ) ? EMCP_Tools_Site_Context::mcp_endpoint() : rest_url( 'mcp/emcp-tools-server' );
		$auth     = 'Basic ' . base64_encode( $username . ':' . $password );
		$session  = '';

		$initialize = $this->mcp_diagnostic_request(
			$endpoint,
			$auth,
			'',
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'initialize',
				'params'  => array(
					'protocolVersion' => '2025-11-25',
					'capabilities'    => (object) array(),
					'clientInfo'      => array(
						'name'    => 'EMCP Tools connection test',
						'version' => defined( 'EMCP_TOOLS_VERSION' ) ? EMCP_TOOLS_VERSION : 'unknown',
					),
				),
			),
			'initialize'
		);
		if ( is_wp_error( $initialize ) ) {
			return $initialize;
		}

		$session = (string) wp_remote_retrieve_header( $initialize['response'], 'mcp-session-id' );
		if ( '' === $session ) {
			return new WP_Error( 'initialize', __( 'Initialize succeeded but the server did not return an MCP session ID.', 'emcp-tools' ) );
		}
		$protocol_version = isset( $initialize['json']['result']['protocolVersion'] ) ? sanitize_text_field( (string) $initialize['json']['result']['protocolVersion'] ) : '';
		if ( '' === $protocol_version ) {
			return new WP_Error( 'initialize', __( 'Initialize returned an invalid protocol version.', 'emcp-tools' ) );
		}

		try {
			$initialized = $this->mcp_diagnostic_request(
				$endpoint,
				$auth,
				$session,
				array(
					'jsonrpc' => '2.0',
					'method'  => 'notifications/initialized',
				),
				'initialized',
				true,
				$protocol_version
			);
			if ( is_wp_error( $initialized ) ) {
				return $initialized;
			}

			$tools = $this->mcp_diagnostic_request(
				$endpoint,
				$auth,
				$session,
				array(
					'jsonrpc' => '2.0',
					'id'      => 2,
					'method'  => 'tools/list',
					'params'  => (object) array(),
				),
				'tools_list',
				false,
				$protocol_version
			);
			if ( is_wp_error( $tools ) ) {
				return $tools;
			}
			if ( ! isset( $tools['json']['result']['tools'] ) || ! is_array( $tools['json']['result']['tools'] ) ) {
				return new WP_Error( 'tools_list', __( 'tools/list returned an invalid MCP response.', 'emcp-tools' ) );
			}

			return array(
				'message'    => __( 'Full MCP handshake succeeded.', 'emcp-tools' ),
				'tool_count' => count( $tools['json']['result']['tools'] ),
			);
		} finally {
			// Best-effort cleanup; never replace the useful diagnostic result with a
			// session-delete failure.
			wp_safe_remote_request(
				$endpoint,
				array(
					'method'  => 'DELETE',
					'timeout' => 10,
					'headers' => array(
						'Authorization'        => $auth,
						'Mcp-Protocol-Version' => $protocol_version,
						'Mcp-Session-Id'       => $session,
					),
				)
			);
		}
	}

	/**
	 * Send one JSON-RPC request used by the connection diagnostic.
	 *
	 * @param string $endpoint           Public MCP endpoint.
	 * @param string $authorization      Basic authorization header.
	 * @param string $session            MCP session ID, or empty for initialize.
	 * @param array  $payload            JSON-RPC payload.
	 * @param string $stage              Stable diagnostic stage.
	 * @param bool   $notification       Whether a 202 empty response is valid.
	 * @param string $protocol_version   Negotiated MCP protocol version.
	 * @return array|WP_Error
	 */
	private function mcp_diagnostic_request( string $endpoint, string $authorization, string $session, array $payload, string $stage, bool $notification = false, string $protocol_version = '' ) {
		$headers = array(
			'Accept'        => 'application/json, text/event-stream',
			'Authorization' => $authorization,
			'Content-Type'  => 'application/json',
		);
		if ( '' !== $session ) {
			$headers['Mcp-Session-Id'] = $session;
		}
		if ( '' !== $protocol_version ) {
			$headers['Mcp-Protocol-Version'] = $protocol_version;
		}

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => $headers,
				'body'        => wp_json_encode( $payload ),
			)
		);
		if ( is_wp_error( $response ) ) {
			/* translators: 1: MCP handshake stage, 2: Request error message. */
			return new WP_Error( $stage, sprintf( __( '%1$s request failed: %2$s', 'emcp-tools' ), $this->diagnostic_stage_label( $stage ), $response->get_error_message() ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $notification && in_array( $status, array( 200, 202 ), true ) ) {
			return array( 'response' => $response, 'json' => array() );
		}
		if ( 200 !== $status ) {
			return new WP_Error(
				$stage,
				sprintf(
					/* translators: 1: MCP handshake stage, 2: HTTP status code. */
					__( '%1$s failed with HTTP %2$d. Check the endpoint, CDN/WAF rules, and whether the Authorization header reaches WordPress.', 'emcp-tools' ),
					$this->diagnostic_stage_label( $stage ),
					$status
				)
			);
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			/* translators: %s: MCP handshake stage. */
			return new WP_Error( $stage, sprintf( __( '%s returned a non-JSON response.', 'emcp-tools' ), $this->diagnostic_stage_label( $stage ) ) );
		}
		if ( isset( $decoded['error'] ) ) {
			$error_message = isset( $decoded['error']['message'] ) ? sanitize_text_field( (string) $decoded['error']['message'] ) : __( 'Unknown JSON-RPC error.', 'emcp-tools' );
			/* translators: 1: MCP handshake stage, 2: JSON-RPC error message. */
			return new WP_Error( $stage, sprintf( __( '%1$s returned an MCP error: %2$s', 'emcp-tools' ), $this->diagnostic_stage_label( $stage ), $error_message ) );
		}

		return array( 'response' => $response, 'json' => $decoded );
	}

	/** Human-readable label for a stable diagnostic stage. */
	private function diagnostic_stage_label( string $stage ): string {
		$labels = array(
			'initialize'  => 'initialize',
			'initialized' => 'notifications/initialized',
			'tools_list'  => 'tools/list',
		);
		return isset( $labels[ $stage ] ) ? $labels[ $stage ] : $stage;
	}

	/**
	 * AJAX: check both standards-based well-known URLs and their REST aliases.
	 *
	 * @since 3.15.0
	 */
	public function ajax_test_oauth_discovery(): void {
		check_ajax_referer( 'emcp_tools_test_oauth_discovery', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to run this test.', 'emcp-tools' ) ), 403 );
		}
		$report = $this->oauth_discovery_report();
		if ( is_wp_error( $report ) ) {
			wp_send_json_error( array( 'message' => $report->get_error_message() ), 400 );
		}
		if ( $report['ok'] ) {
			wp_send_json_success( array( 'message' => $report['message'], 'checks' => $report['checks'] ) );
		}
		wp_send_json_error( array( 'message' => $report['message'], 'checks' => $report['checks'] ), 400 );
	}

	/**
	 * Probe both standards-based well-known URLs and their REST aliases (shared
	 * by the legacy AJAX handler and admin REST).
	 *
	 * @return array{ok:bool, message:string, checks:array}|WP_Error
	 */
	public function oauth_discovery_report() {
		if ( ! class_exists( 'EMCP_Tools_OAuth_Metadata' ) || ! class_exists( 'EMCP_Tools_OAuth_Server' ) || ! EMCP_Tools_OAuth_Server::is_enabled() ) {
			return new WP_Error( 'emcp_oauth_off', __( 'Enable OAuth before testing discovery.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		$base   = class_exists( 'EMCP_Tools_Site_Context' ) ? EMCP_Tools_Site_Context::public_base_url() : rtrim( (string) home_url(), '/' );
		$checks = array(
			'well_known_protected_resource' => $this->probe_oauth_document( $base . EMCP_Tools_OAuth_Metadata::PATH_PROTECTED_RESOURCE, 'resource', EMCP_Tools_OAuth_Metadata::resource() ),
			'well_known_authorization_server' => $this->probe_oauth_document( $base . EMCP_Tools_OAuth_Metadata::PATH_AUTH_SERVER, 'issuer', EMCP_Tools_OAuth_Metadata::issuer() ),
			'rest_protected_resource'       => $this->probe_oauth_document( EMCP_Tools_OAuth_Metadata::protected_resource_url(), 'resource', EMCP_Tools_OAuth_Metadata::resource() ),
			'rest_authorization_server'     => $this->probe_oauth_document( EMCP_Tools_OAuth_Metadata::authorization_server_url(), 'issuer', EMCP_Tools_OAuth_Metadata::issuer() ),
		);

		$root_ok = $checks['well_known_protected_resource']['ok'] && $checks['well_known_authorization_server']['ok'];
		$rest_ok = $checks['rest_protected_resource']['ok'] && $checks['rest_authorization_server']['ok'];
		if ( $root_ok && $rest_ok ) {
			return array( 'ok' => true, 'message' => __( 'OAuth discovery is publicly reachable through both standard well-known URLs and REST aliases.', 'emcp-tools' ), 'checks' => $checks );
		}
		if ( ! $root_ok && $rest_ok ) {
			return array( 'ok' => false, 'message' => __( 'EMCP OAuth routes work, but the public .well-known URLs do not return EMCP metadata. A CDN/host may be intercepting them, or another plugin may own the shared paths. Review the failed values, then bypass or rewrite those routes before reconnecting the client.', 'emcp-tools' ), 'checks' => $checks );
		}

		return array( 'ok' => false, 'message' => __( 'OAuth discovery is not reachable. Review the failed checks and confirm the REST API, HTTPS, permalink routing, and CDN/WAF rules.', 'emcp-tools' ), 'checks' => $checks );
	}

	/** Probe one public OAuth metadata document without following redirects. */
	private function probe_oauth_document( string $url, string $required_key, string $expected_value ): array {
		$response = wp_safe_remote_get( $url, array( 'timeout' => 12, 'redirection' => 0 ) );
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'status' => 0, 'message' => $response->get_error_message() );
		}
		$status  = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$actual  = is_array( $decoded ) && isset( $decoded[ $required_key ] ) && is_string( $decoded[ $required_key ] ) ? $decoded[ $required_key ] : '';
		$matches = '' !== $actual && EMCP_Tools_OAuth_Metadata::identifier_matches( $actual, $expected_value );
		$valid   = 200 === $status && $matches;
		$message = __( 'Expected OAuth metadata JSON was not returned.', 'emcp-tools' );
		if ( $valid ) {
			$message = __( 'OK', 'emcp-tools' );
		} elseif ( 200 === $status && '' !== $actual && ! $matches ) {
			$message = sprintf(
				/* translators: 1: metadata identifier returned, 2: EMCP identifier expected. */
				__( 'Metadata identifies "%1$s", but EMCP expected "%2$s". Another plugin, CDN, or host may own this URL.', 'emcp-tools' ),
				sanitize_text_field( $actual ),
				sanitize_text_field( $expected_value )
			);
		}
		return array(
			'ok'       => $valid,
			'status'   => $status,
			'actual'   => $actual,
			'expected' => $expected_value,
			'message'  => $message,
		);
	}

	/**
	 * admin-post.php callback: build + stream a Claude Desktop .mcpb bundle
	 * with the chosen admin's credentials baked in. POST body: user_id,
	 * app_password, _emcp_nonce. Halts execution at the end.
	 *
	 * @since 3.0.0
	 */
	public function handle_download_mcpb(): void {
		if (
			! isset( $_POST['_emcp_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_emcp_nonce'] ) ), self::NONCE_DOWNLOAD_MCPB )
		) {
			wp_die( esc_html__( 'Invalid request.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$user    = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user || ! current_user_can( 'edit_user', $user_id ) || ! user_can( $user_id, 'manage_options' ) ) {
			wp_die( esc_html__( 'Pick a valid administrator account.', 'emcp-tools' ), '', array( 'response' => 400 ) );
		}

		// The app password was generated on the page (Step 1) and POSTed back —
		// same-origin, nonce-gated, the admin's own credential.
		$app_password = isset( $_POST['app_password'] ) ? sanitize_text_field( wp_unslash( $_POST['app_password'] ) ) : '';
		if ( '' === $app_password ) {
			wp_die( esc_html__( 'Generate an Application Password first, then download the bundle.', 'emcp-tools' ), '', array( 'response' => 400 ) );
		}

		// Bake the reachable public base (rest_url-derived / admin-overridable),
		// NOT home_url() — on a staging host whose Site Address is pinned to a
		// not-yet-live domain, home_url() would ship a bundle that can't connect.
		$emcp_base = class_exists( 'EMCP_Tools_Site_Context' ) ? EMCP_Tools_Site_Context::public_base_url() : home_url();
		$manifest  = EMCP_Tools_Mcpb_Builder::build_manifest( $emcp_base, $user->user_login, $app_password );
		$tmp      = EMCP_Tools_Mcpb_Builder::build_zip( $manifest );
		if ( is_wp_error( $tmp ) ) {
			wp_die( esc_html( $tmp->get_error_message() ), '', array( 'response' => 500 ) );
		}

		// Safety net: the temp file holds a live Application Password. Guarantee
		// it is removed even if streaming aborts (fatal, memory limit, etc.) —
		// the explicit unlink after readfile() handles the normal fast path.
		register_shutdown_function(
			static function () use ( $tmp ) {
				if ( file_exists( $tmp ) ) {
					@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
			}
		);

		$host     = (string) wp_parse_url( $emcp_base, PHP_URL_HOST );
		$filename = 'emcp-tools-' . sanitize_file_name( $host ?: 'site' ) . '.mcpb';

		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		header( 'X-Content-Type-Options: nosniff' );
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		readfile( $tmp );
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		exit;
	}

	/**
	 * Connection-tab client registry: the single source of truth for the
	 * client cards grid + per-client reveal. `methods` declares WHICH options
	 * a client supports; the actual JSON/CLI/prompt strings are assembled
	 * client-side in admin.js from the generated credentials.
	 *
	 * `cli` is a printf-style template with these tokens, substituted in JS:
	 *   %ENDPOINT% (REST MCP url), %B64% (base64 user:app-password).
	 *
	 * @since 3.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function connection_clients(): array {
		$claude_cli = 'claude mcp add --transport http %NAME% "%ENDPOINT%" --header "Authorization: Basic %B64%"';
		$codex_cli  = 'codex mcp add %NAME% --transport http --url "%ENDPOINT%" --header "Authorization=Basic %B64%"';

		// OAuth-mode setup per client — the browser sign-in supplies auth, so no
		// password. Shapes: 'cmd' (terminal command), 'connector' (custom-connector
		// UI), 'config' (a config-file snippet). %NAME%/%ENDPOINT% are filled in JS.
		$oauth_claude_code = array(
			'type' => 'cmd',
			'cmd'  => 'claude mcp add %NAME% --transport http %ENDPOINT%',
		);
		$oauth_claude_desktop = array(
			'type' => 'connector',
			'app'  => __( 'Claude Desktop', 'emcp-tools' ),
		);
		$oauth_claude_ai = array(
			'type'     => 'connector',
			'app'      => 'claude.ai',
			'deeplink' => 'claude-ai',
			'note'     => __( 'Works in the browser and in Claude Desktop.', 'emcp-tools' ),
		);
		$oauth_cursor = array(
			'type'     => 'config',
			'lang'     => 'json',
			'paths'    => array(
				array( 'path' => '~/.cursor/mcp.json', 'label' => __( 'Global', 'emcp-tools' ) ),
				array( 'path' => '.cursor/mcp.json', 'label' => __( 'Project', 'emcp-tools' ) ),
			),
			'template' => "{\n    \"mcpServers\": {\n        \"%NAME%\": {\n            \"url\": \"%ENDPOINT%\"\n        }\n    }\n}",
			'deeplink' => 'cursor',
		);
		// The ChatGPT App signs in through its own MCP UI (Add server → Streamable
		// HTTP → Authenticate), not a config file — config.toml has no OAuth path.
		$oauth_codex = array(
			'type'  => 'steps',
			'steps' => array(
				array(
					'title' => __( 'a. Open the MCP settings', 'emcp-tools' ),
					'desc'  => __( 'In the ChatGPT app, go to File → Settings → Plugins, switch to the MCP tab, and click “Add server”.', 'emcp-tools' ),
				),
				array(
					'title' => __( 'b. Choose Streamable HTTP', 'emcp-tools' ),
					'desc'  => __( 'Set Type to “Streamable HTTP”, then enter a name and this server URL:', 'emcp-tools' ),
				),
				array( 'title' => __( 'Name', 'emcp-tools' ), 'copy' => '%NAME%' ),
				array( 'title' => __( 'URL', 'emcp-tools' ), 'copy' => '%ENDPOINT%' ),
				array(
					'title' => __( 'c. Save, then Authenticate', 'emcp-tools' ),
					'desc'  => __( 'Click Save. An “Authenticate” button appears on the server row, click it, then “Approve” on the consent screen that opens. Your site is now connected and you can start chatting.', 'emcp-tools' ),
				),
			),
		);
		$oauth_antigravity = array(
			'type'     => 'config',
			'lang'     => 'json',
			'paths'    => array(
				array( 'path' => '~/.gemini/antigravity/mcp_config.json', 'label' => __( 'macOS / Linux', 'emcp-tools' ) ),
				array( 'path' => '%USERPROFILE%\\.gemini\\antigravity\\mcp_config.json', 'label' => __( 'Windows', 'emcp-tools' ) ),
			),
			'template' => "{\n    \"mcpServers\": {\n        \"%NAME%\": {\n            \"command\": \"npx\",\n            \"args\": [\n                \"-y\",\n                \"mcp-remote\",\n                \"%ENDPOINT%\"\n            ]\n        }\n    }\n}",
		);
		$oauth_mcp_remote = array(
			'type' => 'cmd',
			'cmd'  => 'npx -y mcp-remote %ENDPOINT%',
		);
		// OpenClaw CLI — `openclaw mcp set <name> '<json>'` writes straight to
		// ~/.openclaw/openclaw.json (mcp.servers). Basic-auth via the headers map.
		$openclaw_cli   = 'openclaw mcp set %NAME% \'{"url":"%ENDPOINT%","transport":"streamable-http","headers":{"Authorization":"Basic %B64%"}}\'';
		// OpenClaw OAuth: same shape with auth:oauth; `openclaw mcp login` runs the flow.
		$oauth_openclaw = array(
			'type'      => 'config',
			'lang'      => 'json',
			'paths'     => array( array( 'path' => '~/.openclaw/openclaw.json', 'label' => '' ) ),
			// The "mcp" property (not a whole object) — openclaw.json usually has
			// other keys already; add this, or drop the server under an existing
			// mcp.servers.
			'template'  => "\"mcp\": {\n    \"servers\": {\n        \"%NAME%\": {\n            \"url\": \"%ENDPOINT%\",\n            \"transport\": \"streamable-http\",\n            \"auth\": \"oauth\"\n        }\n    }\n}",
			'merge_msg' => __( 'openclaw.json usually already has other settings. Add this "mcp" block, or if you already have one, add the server inside its "servers".', 'emcp-tools' ),
			'note'      => __( 'After saving, run  openclaw mcp login %NAME%  to authorize through your browser.', 'emcp-tools' ),
		);
		// Hermes uses ~/.hermes/config.yaml (mcp_servers). OAuth mode is url-only —
		// the server initiates the browser sign-in on first connect.
		$oauth_hermes = array(
			'type'     => 'config',
			'lang'     => 'yaml',
			'paths'    => array( array( 'path' => '~/.hermes/config.yaml', 'label' => '' ) ),
			'template' => "mcp_servers:\n  %NAME%:\n    url: \"%ENDPOINT%\"",
		);

		// Codex's "Connect to a custom MCP" UI form — a field-by-field mapping so
		// users know which Connection value goes where. %ENDPOINT%/%B64% are filled
		// with the live endpoint + Basic-auth token in JS (escaped). HTML tags are
		// kept outside the translation calls so they are not escaped.
		$codex_guide = '<p class="description">'
			. esc_html__( 'Prefer the ChatGPT App\'s UI? Choose “Connect to a custom MCP” → “Streamable HTTP”, then fill the form like this:', 'emcp-tools' )
			. '</p>'
			. '<table class="emcp-conn-guide"><tbody>'
			. '<tr><th>' . esc_html__( 'Name', 'emcp-tools' ) . '</th><td><code>%NAME%</code></td></tr>'
			. '<tr><th>' . esc_html__( 'Transport', 'emcp-tools' ) . '</th><td>' . esc_html__( 'Streamable HTTP', 'emcp-tools' ) . '</td></tr>'
			. '<tr><th>' . esc_html__( 'URL', 'emcp-tools' ) . '</th><td><code>%ENDPOINT%</code></td></tr>'
			. '<tr><th>' . esc_html__( 'Bearer token env var', 'emcp-tools' ) . '</th><td>' . esc_html__( 'Leave blank, EMCP uses a WordPress Application Password (HTTP Basic), not a bearer token.', 'emcp-tools' ) . '</td></tr>'
			. '<tr><th>' . esc_html__( 'Headers', 'emcp-tools' ) . '</th><td>' . esc_html__( 'Key', 'emcp-tools' ) . ' <code>Authorization</code> &middot; ' . esc_html__( 'Value', 'emcp-tools' ) . ' <code>Basic %B64%</code></td></tr>'
			. '</tbody></table>'
			. '<p class="description">' . esc_html__( 'Then Save. The config blocks below do the same thing, “direct HTTP” for the URL + header approach, or the “Node proxy / npx” config if the HTTP transport gives you handshake trouble.', 'emcp-tools' ) . '</p>';

		return array(
			array(
				'id'      => 'claude-desktop',
				'label'   => __( 'Claude Desktop', 'emcp-tools' ),
				'icon'    => 'desktop',
				'image'   => 'claude.png',
				'methods' => array( 'bundle' => true, 'cli' => null, 'ai_prompt' => true, 'json' => array( 'npx', 'http' ) ),
				'oauth'   => $oauth_claude_desktop,
			),
			array(
				'id'      => 'claude-ai',
				'label'   => __( 'Claude.ai', 'emcp-tools' ),
				'icon'    => 'admin-site-alt3',
				'image'   => 'claude.png',
				'methods' => array( 'bundle' => false, 'cli' => null, 'ai_prompt' => true, 'json' => array( 'remote' ) ),
				'oauth'   => $oauth_claude_ai,
			),
			array(
				'id'      => 'claude-code',
				'label'   => __( 'Claude Code', 'emcp-tools' ),
				'icon'    => 'editor-code',
				'image'   => 'claude.png',
				'methods' => array( 'bundle' => false, 'cli' => $claude_cli, 'ai_prompt' => false, 'json' => array( 'npx', 'http' ) ),
				'oauth'   => $oauth_claude_code,
			),
			array(
				'id'      => 'cursor',
				'label'   => __( 'Cursor', 'emcp-tools' ),
				'icon'    => 'editor-code',
				'image'   => 'cursor.png',
				'methods' => array( 'bundle' => false, 'cli' => null, 'ai_prompt' => true, 'json' => array( 'http' ) ),
				'oauth'   => $oauth_cursor,
			),
			array(
				'id'          => 'codex',
				'label'       => __( 'ChatGPT App', 'emcp-tools' ),
				'icon'        => 'editor-code',
				'image'       => 'gpt.png',
				'guide_title' => __( 'Using the ChatGPT App “Custom MCP” form', 'emcp-tools' ),
				'guide'       => $codex_guide,
				'methods'     => array( 'bundle' => false, 'cli' => $codex_cli, 'ai_prompt' => false, 'json' => array( 'toml', 'toml-stdio' ) ),
				'oauth'       => $oauth_codex,
			),
			array(
				'id'      => 'antigravity',
				'label'   => __( 'Antigravity', 'emcp-tools' ),
				'icon'    => 'editor-code',
				'image'   => 'antigravity.png',
				'methods' => array( 'bundle' => false, 'cli' => null, 'ai_prompt' => false, 'json' => array( 'http' ) ),
				'oauth'   => $oauth_antigravity,
			),
			array(
				'id'      => 'openclaw',
				'label'   => __( 'OpenClaw', 'emcp-tools' ),
				'icon'    => 'editor-code',
				'methods' => array( 'bundle' => false, 'cli' => $openclaw_cli, 'ai_prompt' => false, 'json' => array( 'openclaw-http', 'openclaw-npx' ) ),
				'oauth'   => $oauth_openclaw,
			),
			array(
				'id'      => 'hermes',
				'label'   => __( 'Hermes', 'emcp-tools' ),
				'icon'    => 'editor-code',
				'methods' => array( 'bundle' => false, 'cli' => null, 'ai_prompt' => false, 'json' => array( 'hermes-http', 'hermes-npx' ) ),
				'oauth'   => $oauth_hermes,
			),
			array(
				'id'      => 'mcp-remote',
				'label'   => __( 'npx mcp-remote', 'emcp-tools' ),
				'icon'    => 'admin-links',
				'methods' => array( 'bundle' => false, 'cli' => null, 'ai_prompt' => false, 'json' => array( 'remote' ) ),
				'oauth'   => $oauth_mcp_remote,
			),
		);
	}
}
