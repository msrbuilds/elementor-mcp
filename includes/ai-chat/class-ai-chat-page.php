<?php
/**
 * AI Chat admin page — asset enqueue + localized config for the chat client.
 *
 * The submenu itself is registered by EMCP_Tools_Admin (so it sits in the EMCP
 * Tools tab list); this class only loads ai-chat.js/css and hands the browser
 * everything it needs (REST URL + nonce, key status, the fetched model list,
 * the destructive-tool list, pricing, i18n) on the AI Chat screen only.
 *
 * @package EMCP_Tools
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues + configures the AI Chat client.
 *
 * @since 2.2.0
 */
class EMCP_Tools_AI_Chat_Page {

	const PAGE = 'emcp-tools-ai-chat';

	/**
	 * @since 2.2.0
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Whether the current admin request is the AI Chat screen.
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	private function is_chat_screen(): bool {
		return isset( $_GET['page'] ) && self::PAGE === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * @since 2.2.0
	 */
	public function enqueue(): void {
		if ( ! $this->is_chat_screen() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$ver = ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( EMCP_TOOLS_DIR . 'assets/js/ai-chat.js' ) )
			? (string) filemtime( EMCP_TOOLS_DIR . 'assets/js/ai-chat.js' )
			: EMCP_TOOLS_VERSION;

		wp_enqueue_style( 'emcp-tools-ai-chat', EMCP_TOOLS_URL . 'assets/css/ai-chat.css', array(), $ver );
		wp_enqueue_script( 'emcp-tools-ai-chat', EMCP_TOOLS_URL . 'assets/js/ai-chat.js', array(), $ver, true );

		// Strip the elementor-mcp/ prefix so the JS gate matches the Anthropic
		// tool names (which can't contain "/").
		$destructive = array_map(
			static function ( $n ) {
				return str_replace( 'elementor-mcp/', '', $n );
			},
			EMCP_Tools_AI_Chat_Controller::DESTRUCTIVE_TOOLS
		);

		$emcp_ai_models   = array();
		$emcp_ai_defaults = array();
		$emcp_ai_chosen   = array();
		foreach ( EMCP_Tools_AI_Providers::ids() as $emcp_ai_pid ) {
			$emcp_ai_models[ $emcp_ai_pid ]   = EMCP_Tools_AI_Chat_Provider::get_models( $emcp_ai_pid );
			$emcp_ai_defaults[ $emcp_ai_pid ] = EMCP_Tools_AI_Chat_Provider::default_model( $emcp_ai_pid );
			$emcp_ai_chosen[ $emcp_ai_pid ]   = EMCP_Tools_AI_Chat_Provider::get_chosen_default( $emcp_ai_pid );
		}
		$emcp_ai_convos = class_exists( 'EMCP_Tools_AI_Chat_Store' ) ? EMCP_Tools_AI_Chat_Store::list_conversations() : array();

		wp_localize_script(
			'emcp-tools-ai-chat',
			'emcpAiChat',
			array(
				'restBase'        => esc_url_raw( rest_url( EMCP_Tools_AI_Chat_Controller::NAMESPACE ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'hasAccess'       => EMCP_Tools_AI_Chat_Provider::has_access(),
				'providers'       => EMCP_Tools_AI_Providers::for_client(),
				'connections'     => EMCP_Tools_AI_Chat_Provider::connections(),
				'models'          => $emcp_ai_models,
				'defaults'        => $emcp_ai_defaults,
				'chosen'          => $emcp_ai_chosen,
				'conversations'   => $emcp_ai_convos,
				'hasAnyKey'       => EMCP_Tools_AI_Chat_Provider::has_any_key(),
				'destructive'     => array_values( $destructive ),
				'maxIterations'   => 50,
				'defaultBudget'   => 5,
				'upgradeUrl'      => function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : '',
				'siteUrl'         => site_url(),
				'wpVersion'       => get_bloginfo( 'version' ),
				'elementorVer'    => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
				'elementorPro'    => defined( 'ELEMENTOR_PRO_VERSION' ),
				'userName'        => wp_get_current_user()->display_name,
				// Rough list pricing per million tokens (USD): [input, output]. Keys
				// are matched as substrings of the model id; first match wins, so
				// order specific → generic. Best-effort — used only for the cost
				// estimate footer. (Anthropic cache write ≈ 1.25× in, read ≈ 0.1×.)
				'pricing'         => array(
					'opus'      => array( 15.0, 75.0 ),
					'sonnet'    => array( 3.0, 15.0 ),
					'haiku'     => array( 0.8, 4.0 ),
					'gpt-4o-mini' => array( 0.15, 0.6 ),
					'gpt-4o'    => array( 2.5, 10.0 ),
					'o3-mini'   => array( 1.1, 4.4 ),
					'o3'        => array( 2.0, 8.0 ),
					'o1'        => array( 15.0, 60.0 ),
					'gpt-4'     => array( 30.0, 60.0 ),
					'gpt-3.5'   => array( 0.5, 1.5 ),
					'flash'     => array( 0.3, 2.5 ),
					'gemini'    => array( 1.25, 10.0 ),
				),
				'i18n'            => array(
					'thinking'      => __( 'Thinking…', 'emcp-tools' ),
					'calling'       => __( 'Calling', 'emcp-tools' ),
					'approve'       => __( 'Approve', 'emcp-tools' ),
					'reject'        => __( 'Reject', 'emcp-tools' ),
					'rejected'      => __( 'You rejected this action.', 'emcp-tools' ),
					'send'          => __( 'Send', 'emcp-tools' ),
					'stop'          => __( 'Stop', 'emcp-tools' ),
					'validating'    => __( 'Validating…', 'emcp-tools' ),
					'saved'         => __( 'Key saved.', 'emcp-tools' ),
					'refreshing'    => __( 'Refreshing…', 'emcp-tools' ),
					'thisTurn'      => __( 'This turn', 'emcp-tools' ),
					'convTotal'     => __( 'Conversation total', 'emcp-tools' ),
					'tokens'        => __( 'tokens', 'emcp-tools' ),
					'loopStopped'   => __( 'Stopped — exceeded 50 tool calls in one turn. The AI may be looping; refine your request.', 'emcp-tools' ),
					'budgetHit'     => __( 'Budget cap reached for this conversation. Raise it in Settings or start a new chat.', 'emcp-tools' ),
					'apiError'      => __( 'Provider API error', 'emcp-tools' ),
					'newChat'       => __( 'New chat', 'emcp-tools' ),
					'connect'       => __( 'Connect', 'emcp-tools' ),
					'disconnect'    => __( 'Disconnect', 'emcp-tools' ),
					'connected'     => __( 'Connected', 'emcp-tools' ),
					'notConnected'  => __( 'Not connected', 'emcp-tools' ),
					'getApiKey'     => __( 'Get an API key →', 'emcp-tools' ),
					'noModelsYet'   => __( 'Connect a provider to choose a model.', 'emcp-tools' ),
					'providerLabel' => __( 'Provider', 'emcp-tools' ),
					'modelLabel'    => __( 'Model', 'emcp-tools' ),
					'searchModels'  => __( 'Search models…', 'emcp-tools' ),
					'noMatches'     => __( 'No matches', 'emcp-tools' ),
					'selectModel'   => __( 'Select model…', 'emcp-tools' ),
					'freeGroup'     => __( 'Free', 'emcp-tools' ),
					'defaultModel'  => __( 'Default model', 'emcp-tools' ),
				),
			)
		);
	}
}
