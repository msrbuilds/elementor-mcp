<?php
/**
 * AI Chat provider registry — the LLM backends the chat can talk to.
 *
 * Four providers, two wire formats: Anthropic has its own Messages API; OpenAI,
 * OpenRouter, and Google Gemini all speak the OpenAI chat-completions format
 * (Gemini via its `/openai/` compatibility endpoint), so they share one adapter.
 *
 * @package EMCP_Tools
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static registry of supported providers + their endpoints/auth/format.
 *
 * @since 2.2.0
 */
class EMCP_Tools_AI_Providers {

	/**
	 * All providers, keyed by id. `format` selects the JS adapter; `auth` selects
	 * the header style ('x-api-key' | 'bearer'); `model_filter` (optional) is a
	 * PCRE applied to model ids to hide non-chat models.
	 *
	 * @since 2.2.0
	 * @return array<string,array>
	 */
	public static function all(): array {
		return array(
			'anthropic'  => array(
				'label'        => 'Anthropic (Claude)',
				'format'       => 'anthropic',
				'chat_url'     => 'https://api.anthropic.com/v1/messages',
				'models_url'   => 'https://api.anthropic.com/v1/models?limit=1000',
				'auth'         => 'x-api-key',
				'headers'      => array(
					'anthropic-version'                         => '2023-06-01',
					'anthropic-dangerous-direct-browser-access' => 'true',
				),
				'key_hint'     => 'sk-ant-...',
				'console_url'  => 'https://console.anthropic.com/settings/keys',
				'default_rx'   => 'sonnet',
				'model_filter' => '',
			),
			'openai'     => array(
				'label'        => 'OpenAI',
				'format'       => 'openai',
				'chat_url'     => 'https://api.openai.com/v1/chat/completions',
				'models_url'   => 'https://api.openai.com/v1/models',
				'auth'         => 'bearer',
				'headers'      => array(),
				'key_hint'     => 'sk-...',
				'console_url'  => 'https://platform.openai.com/api-keys',
				'default_rx'   => 'gpt-4o',
				'model_filter' => '/^(gpt-|o[1-9]|chatgpt)/i',
			),
			'openrouter' => array(
				'label'        => 'OpenRouter',
				'format'       => 'openai',
				'chat_url'     => 'https://openrouter.ai/api/v1/chat/completions',
				'models_url'   => 'https://openrouter.ai/api/v1/models',
				'auth'         => 'bearer',
				'headers'      => array(
					'HTTP-Referer' => 'https://emcp.msrbuilds.com',
					'X-Title'      => 'EMCP Tools',
				),
				'key_hint'     => 'sk-or-...',
				'console_url'  => 'https://openrouter.ai/keys',
				'default_rx'   => 'claude.*sonnet',
				'model_filter' => '',
			),
			'gemini'     => array(
				'label'        => 'Google Gemini',
				'format'       => 'openai',
				'chat_url'     => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
				'models_url'   => 'https://generativelanguage.googleapis.com/v1beta/openai/models',
				'auth'         => 'bearer',
				'headers'      => array(),
				'key_hint'     => 'AIza...',
				'console_url'  => 'https://aistudio.google.com/apikey',
				'default_rx'   => 'gemini-[0-9].*(flash|pro)',
				'model_filter' => '/gemini/i',
			),
		);
	}

	/**
	 * @since 2.2.0
	 * @param string $id Provider id.
	 * @return array|null
	 */
	public static function get( string $id ): ?array {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * @since 2.2.0
	 * @param string $id Provider id.
	 * @return bool
	 */
	public static function exists( string $id ): bool {
		return null !== self::get( $id );
	}

	/**
	 * @since 2.2.0
	 * @return string[]
	 */
	public static function ids(): array {
		return array_keys( self::all() );
	}

	/**
	 * Config safe to expose to the browser (no secrets — there are none here).
	 * Strips nothing today, but kept as the single client-facing shape.
	 *
	 * @since 2.2.0
	 * @return array<string,array>
	 */
	public static function for_client(): array {
		$out = array();
		foreach ( self::all() as $id => $p ) {
			$out[ $id ] = array(
				'label'       => $p['label'],
				'format'      => $p['format'],
				'chatUrl'     => $p['chat_url'],
				'auth'        => $p['auth'],
				'headers'     => $p['headers'],
				'keyHint'     => $p['key_hint'],
				'consoleUrl'  => $p['console_url'],
			);
		}
		return $out;
	}
}
