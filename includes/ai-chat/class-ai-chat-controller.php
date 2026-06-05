<?php
/**
 * AI Chat REST controller — `emcp-tools/v1` routes for the in-plugin chat:
 * per-provider API-key management, fetched model lists, single-ability
 * execution, and the Anthropic-format tool list.
 *
 * All routes are `manage_options` + Pro gated. `/execute-ability` re-checks the
 * destructive-tool approval gate server-side and only runs tools in the same
 * active set the MCP server exposes.
 *
 * @package EMCP_Tools
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller for the AI Chat feature.
 *
 * @since 2.2.0
 */
class EMCP_Tools_AI_Chat_Controller {

	const NAMESPACE = 'emcp-tools/v1';

	/**
	 * Tools requiring explicit per-call approval (kept in sync with the JS gate;
	 * re-checked here). Names are the full `elementor-mcp/…` ability names.
	 * (AI_CHAT_PLAN.md §3.5)
	 *
	 * @var string[]
	 */
	const DESTRUCTIVE_TOOLS = array(
		'elementor-mcp/create-custom-widget',
		'elementor-mcp/update-custom-widget',
		'elementor-mcp/add-custom-js',
		'elementor-mcp/add-code-snippet',
		'elementor-mcp/add-custom-css',
		'elementor-mcp/delete-page-content',
		'elementor-mcp/remove-element',
		'elementor-mcp/delete-custom-widget',
		'elementor-mcp/delete-php-snippet',
		'elementor-mcp/update-global-colors',
		'elementor-mcp/update-global-typography',
		'elementor-mcp/apply-brand-kit',
		'elementor-mcp/replace-system-colors',
		'elementor-mcp/replace-system-typography',
	);

	/**
	 * @since 2.2.0
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * @since 2.2.0
	 * @return bool
	 */
	public static function permission(): bool {
		return current_user_can( 'manage_options' ) && EMCP_Tools_AI_Chat_Provider::has_access();
	}

	/**
	 * @since 2.2.0
	 */
	public function register_routes(): void {
		$perm = array( __CLASS__, 'permission' );

		register_rest_route(
			self::NAMESPACE,
			'/api-key',
			array(
				array(
					'methods'             => 'POST',
					'permission_callback' => $perm,
					'callback'            => array( $this, 'save_api_key' ),
					'args'                => array(
						'provider' => array( 'type' => 'string', 'required' => true ),
						'key'      => array( 'type' => 'string', 'required' => true ),
					),
				),
				array(
					'methods'             => 'DELETE',
					'permission_callback' => $perm,
					'callback'            => array( $this, 'delete_api_key' ),
					'args'                => array(
						'provider' => array( 'type' => 'string', 'required' => true ),
					),
				),
			)
		);

		register_rest_route( self::NAMESPACE, '/api-key/status', array(
			'methods'             => 'GET',
			'permission_callback' => $perm,
			'callback'            => array( $this, 'api_key_status' ),
		) );

		register_rest_route( self::NAMESPACE, '/api-key/reveal', array(
			'methods'             => 'GET',
			'permission_callback' => $perm,
			'callback'            => array( $this, 'reveal_api_key' ),
			'args'                => array( 'provider' => array( 'type' => 'string', 'required' => true ) ),
		) );

		register_rest_route( self::NAMESPACE, '/models/refresh', array(
			'methods'             => 'POST',
			'permission_callback' => $perm,
			'callback'            => array( $this, 'refresh_models' ),
			'args'                => array( 'provider' => array( 'type' => 'string', 'required' => true ) ),
		) );

		register_rest_route( self::NAMESPACE, '/abilities', array(
			'methods'             => 'GET',
			'permission_callback' => $perm,
			'callback'            => array( $this, 'get_abilities' ),
		) );

		register_rest_route( self::NAMESPACE, '/execute-ability', array(
			'methods'             => 'POST',
			'permission_callback' => $perm,
			'callback'            => array( $this, 'execute_ability' ),
			'args'                => array(
				'ability'  => array( 'type' => 'string', 'required' => true ),
				'args'     => array( 'type' => 'object', 'default' => array() ),
				'approved' => array( 'type' => 'boolean', 'default' => false ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/default-model', array(
			'methods'             => 'POST',
			'permission_callback' => $perm,
			'callback'            => array( $this, 'set_default_model' ),
			'args'                => array(
				'provider' => array( 'type' => 'string', 'required' => true ),
				'model'    => array( 'type' => 'string', 'default' => '' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/conversations', array(
			array( 'methods' => 'GET', 'permission_callback' => $perm, 'callback' => array( $this, 'list_conversations' ) ),
			array( 'methods' => 'POST', 'permission_callback' => $perm, 'callback' => array( $this, 'save_conversation' ) ),
		) );
		register_rest_route( self::NAMESPACE, '/conversations/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'permission_callback' => $perm, 'callback' => array( $this, 'get_conversation' ) ),
			array( 'methods' => 'DELETE', 'permission_callback' => $perm, 'callback' => array( $this, 'delete_conversation' ) ),
		) );
	}

	// ── default model + conversations ─────────────────────────────────────────

	/**
	 * @since 2.2.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_default_model( \WP_REST_Request $request ) {
		$provider = sanitize_key( (string) $request->get_param( 'provider' ) );
		if ( ! EMCP_Tools_AI_Providers::exists( $provider ) ) {
			return new \WP_Error( 'unknown_provider', __( 'Unknown provider.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		EMCP_Tools_AI_Chat_Provider::set_chosen_default( $provider, sanitize_text_field( (string) $request->get_param( 'model' ) ) );
		return rest_ensure_response( $this->state_payload() );
	}

	/** @since 2.2.0 @return \WP_REST_Response */
	public function list_conversations() {
		return rest_ensure_response( array( 'conversations' => EMCP_Tools_AI_Chat_Store::list_conversations() ) );
	}

	/**
	 * @since 2.2.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_conversation( \WP_REST_Request $request ) {
		$c = EMCP_Tools_AI_Chat_Store::get( (int) $request['id'] );
		if ( null === $c ) {
			return new \WP_Error( 'not_found', __( 'Conversation not found.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $c );
	}

	/**
	 * @since 2.2.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_conversation( \WP_REST_Request $request ) {
		$id  = $request->get_param( 'id' ) ? (int) $request->get_param( 'id' ) : null;
		$res = EMCP_Tools_AI_Chat_Store::save(
			$id,
			(string) $request->get_param( 'title' ),
			sanitize_key( (string) $request->get_param( 'provider' ) ),
			sanitize_text_field( (string) $request->get_param( 'model' ) ),
			(array) $request->get_param( 'messages' )
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return rest_ensure_response( array( 'id' => $res, 'conversations' => EMCP_Tools_AI_Chat_Store::list_conversations() ) );
	}

	/**
	 * @since 2.2.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_conversation( \WP_REST_Request $request ) {
		$ok = EMCP_Tools_AI_Chat_Store::delete( (int) $request['id'] );
		return rest_ensure_response( array( 'deleted' => $ok, 'conversations' => EMCP_Tools_AI_Chat_Store::list_conversations() ) );
	}

	// ── keys ────────────────────────────────────────────────────────────────

	/**
	 * Validates the key against the provider's model endpoint, stores it, and
	 * caches the returned models.
	 *
	 * @since 2.2.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_api_key( \WP_REST_Request $request ) {
		$provider = sanitize_key( (string) $request->get_param( 'provider' ) );
		$key      = (string) $request->get_param( 'key' );

		if ( ! EMCP_Tools_AI_Providers::exists( $provider ) ) {
			return new \WP_Error( 'unknown_provider', __( 'Unknown provider.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		if ( '' === trim( $key ) ) {
			return new \WP_Error( 'missing_key', __( 'API key is required.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		$models = EMCP_Tools_AI_Chat_Provider::fetch_models( $provider, $key );
		if ( is_wp_error( $models ) ) {
			return $models;
		}
		if ( ! EMCP_Tools_AI_Chat_Provider::save_key( $provider, $key ) ) {
			return new \WP_Error( 'store_failed', __( 'Could not securely store the key.', 'emcp-tools' ), array( 'status' => 500 ) );
		}
		EMCP_Tools_AI_Chat_Provider::store_models( $provider, $models, time() );
		EMCP_Tools_AI_Chat_Provider::maybe_schedule_refresh();

		return rest_ensure_response( $this->state_payload() );
	}

	/**
	 * @since 2.2.0
	 * @return \WP_REST_Response
	 */
	public function api_key_status() {
		return rest_ensure_response( $this->state_payload() );
	}

	/**
	 * @since 2.2.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reveal_api_key( \WP_REST_Request $request ) {
		$provider = sanitize_key( (string) $request->get_param( 'provider' ) );
		$key      = EMCP_Tools_AI_Chat_Provider::get_key( $provider );
		if ( '' === $key ) {
			return new \WP_Error( 'no_key', __( 'No API key stored for this provider.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'provider' => $provider, 'key' => $key ) );
	}

	/**
	 * @since 2.2.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_api_key( \WP_REST_Request $request ) {
		$provider = sanitize_key( (string) $request->get_param( 'provider' ) );
		EMCP_Tools_AI_Chat_Provider::delete_key( $provider );
		return rest_ensure_response( $this->state_payload() );
	}

	// ── models ──────────────────────────────────────────────────────────────

	/**
	 * @since 2.2.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function refresh_models( \WP_REST_Request $request ) {
		$provider = sanitize_key( (string) $request->get_param( 'provider' ) );
		if ( ! EMCP_Tools_AI_Providers::exists( $provider ) ) {
			return new \WP_Error( 'unknown_provider', __( 'Unknown provider.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$result = EMCP_Tools_AI_Chat_Provider::refresh_models( $provider );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new \WP_Error( 'no_key', __( 'Connect this provider first.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( $this->state_payload() );
	}

	/**
	 * The full client state: per-provider connection status, cached models, and
	 * default model ids. Returned by every key/model mutation so the UI stays in
	 * sync from one response.
	 *
	 * @since 2.2.0
	 * @return array
	 */
	private function state_payload(): array {
		$models   = array();
		$defaults = array();
		$chosen   = array();
		foreach ( EMCP_Tools_AI_Providers::ids() as $id ) {
			$models[ $id ]   = EMCP_Tools_AI_Chat_Provider::get_models( $id );
			$defaults[ $id ] = EMCP_Tools_AI_Chat_Provider::default_model( $id );
			$chosen[ $id ]   = EMCP_Tools_AI_Chat_Provider::get_chosen_default( $id );
		}
		return array(
			'connections' => EMCP_Tools_AI_Chat_Provider::connections(),
			'models'      => $models,
			'defaults'    => $defaults,
			'chosen'      => $chosen,
		);
	}

	// ── tools ───────────────────────────────────────────────────────────────

	/**
	 * Active tools in Anthropic function-schema format (the OpenAI adapter maps
	 * these to its own shape client-side). `/` is stripped from names.
	 *
	 * @since 2.2.0
	 * @return \WP_REST_Response
	 */
	public function get_abilities() {
		$tools = array();
		foreach ( self::active_ability_names() as $name ) {
			$ability = wp_get_ability( $name );
			if ( ! $ability ) {
				continue;
			}
			$schema  = $ability->get_input_schema();
			$tools[] = array(
				'name'         => str_replace( 'elementor-mcp/', '', $name ),
				'description'  => method_exists( $ability, 'get_description' ) ? (string) $ability->get_description() : '',
				'input_schema' => empty( $schema ) ? array( 'type' => 'object', 'properties' => new \stdClass() ) : $schema,
			);
		}
		return rest_ensure_response( array( 'tools' => $tools ) );
	}

	/**
	 * Runs a single ability. Enforces the active-tool set + the destructive gate.
	 *
	 * @since 2.2.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function execute_ability( \WP_REST_Request $request ) {
		$ability_name = (string) $request->get_param( 'ability' );
		$args         = (array) $request->get_param( 'args' );

		if ( false === strpos( $ability_name, '/' ) ) {
			$ability_name = 'elementor-mcp/' . $ability_name;
		}

		$ability = wp_get_ability( $ability_name );
		if ( ! $ability ) {
			return new \WP_Error( 'unknown_ability', "Unknown ability: $ability_name", array( 'status' => 404 ) );
		}
		if ( ! in_array( $ability_name, self::active_ability_names(), true ) ) {
			return new \WP_Error( 'tool_unavailable', __( 'This tool is disabled or unavailable in EMCP Tools → Tools.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		if ( in_array( $ability_name, self::DESTRUCTIVE_TOOLS, true ) && true !== $request->get_param( 'approved' ) ) {
			return new \WP_Error( 'approval_required', __( 'This tool requires explicit user approval.', 'emcp-tools' ), array( 'status' => 403 ) );
		}

		try {
			$result = $ability->execute( $args );
			if ( is_wp_error( $result ) ) {
				return new \WP_Error( 'ability_error', $result->get_error_message(), array( 'status' => 422 ) );
			}
			return rest_ensure_response( array( 'success' => true, 'result' => $result ) );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'ability_failed',
				$e->getMessage(),
				array( 'status' => 500, 'trace' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? $e->getTraceAsString() : null )
			);
		}
	}

	/**
	 * @since 2.2.0
	 * @return string[]
	 */
	private static function active_ability_names(): array {
		if ( class_exists( 'EMCP_Tools_Plugin' ) ) {
			return EMCP_Tools_Plugin::instance()->get_active_ability_names();
		}
		return array();
	}
}
