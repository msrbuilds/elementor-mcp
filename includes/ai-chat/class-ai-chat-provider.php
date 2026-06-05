<?php
/**
 * AI Chat provider store — multi-provider API key management (per-user,
 * encrypted) and per-provider fetched-and-cached model lists.
 *
 * Providers (Anthropic, OpenAI, OpenRouter, Gemini) are defined in
 * EMCP_Tools_AI_Providers. Keys are stored as one encrypted-per-value map in
 * user meta; model lists are cached per provider in a single option. Pro-gated.
 *
 * @package EMCP_Tools
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multi-provider key + model store for the in-plugin AI Chat.
 *
 * @since 2.2.0
 */
class EMCP_Tools_AI_Chat_Provider {

	const KEYS_META    = 'emcp_tools_ai_keys';            // user meta: { provider => encrypted_key }
	const CHOSEN_META  = 'emcp_tools_ai_defaults';        // user meta: { provider => chosen model_id }
	const MODELS_OPT   = 'emcp_tools_ai_models';          // option:    { provider => { fetched_at, models } }
	const REFRESH_HOOK = 'emcp_tools_refresh_ai_models';

	/**
	 * Whether the current site/user can use AI Chat (Pro). Filterable.
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	public static function has_access(): bool {
		$access = function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code();
		/** @since 2.2.0 */
		return (bool) apply_filters( 'emcp_tools_ai_chat_has_access', $access );
	}

	// ── keys (per provider, per user) ─────────────────────────────────────────

	private static function uid( ?int $user_id = null ): int {
		return $user_id ?? get_current_user_id();
	}

	/** @return array<string,string> provider => encrypted */
	private static function key_map( ?int $user_id ): array {
		$map = get_user_meta( self::uid( $user_id ), self::KEYS_META, true );
		return is_array( $map ) ? $map : array();
	}

	/**
	 * @since 2.2.0
	 * @param string   $provider Provider id.
	 * @param int|null $user_id  User.
	 * @return bool
	 */
	public static function has_key( string $provider, ?int $user_id = null ): bool {
		return '' !== self::get_key( $provider, $user_id );
	}

	/**
	 * @since 2.2.0
	 * @param string   $provider  Provider id.
	 * @param string   $plaintext Key.
	 * @param int|null $user_id   User.
	 * @return bool
	 */
	public static function save_key( string $provider, string $plaintext, ?int $user_id = null ): bool {
		if ( ! EMCP_Tools_AI_Providers::exists( $provider ) ) {
			return false;
		}
		$enc = EMCP_Tools_Key_Crypto::encrypt( trim( $plaintext ) );
		if ( '' === $enc ) {
			return false;
		}
		$map              = self::key_map( $user_id );
		$map[ $provider ] = $enc;
		update_user_meta( self::uid( $user_id ), self::KEYS_META, $map );
		return true;
	}

	/**
	 * @since 2.2.0
	 * @param string   $provider Provider id.
	 * @param int|null $user_id  User.
	 * @return string Decrypted key or ''.
	 */
	public static function get_key( string $provider, ?int $user_id = null ): string {
		$map = self::key_map( $user_id );
		return isset( $map[ $provider ] ) ? EMCP_Tools_Key_Crypto::decrypt( $map[ $provider ] ) : '';
	}

	/**
	 * @since 2.2.0
	 * @param string   $provider Provider id.
	 * @param int|null $user_id  User.
	 */
	public static function delete_key( string $provider, ?int $user_id = null ): void {
		$map = self::key_map( $user_id );
		if ( isset( $map[ $provider ] ) ) {
			unset( $map[ $provider ] );
			update_user_meta( self::uid( $user_id ), self::KEYS_META, $map );
		}
	}

	/**
	 * @since 2.2.0
	 * @param string   $provider Provider id.
	 * @param int|null $user_id  User.
	 * @return string
	 */
	public static function masked_key( string $provider, ?int $user_id = null ): string {
		$key = self::get_key( $provider, $user_id );
		if ( '' === $key ) {
			return '';
		}
		$len = strlen( $key );
		return $len <= 8 ? str_repeat( '•', $len ) : substr( $key, 0, 5 ) . '…' . substr( $key, -4 );
	}

	/**
	 * Per-provider connection status for the current/given user.
	 *
	 * @since 2.2.0
	 * @param int|null $user_id User.
	 * @return array<string,array> provider => { connected, masked }
	 */
	public static function connections( ?int $user_id = null ): array {
		$out = array();
		foreach ( EMCP_Tools_AI_Providers::ids() as $id ) {
			$has         = self::has_key( $id, $user_id );
			$out[ $id ]  = array(
				'connected' => $has,
				'masked'    => $has ? self::masked_key( $id, $user_id ) : '',
			);
		}
		return $out;
	}

	/** @since 2.2.0 @return bool Whether the user has any provider connected. */
	public static function has_any_key( ?int $user_id = null ): bool {
		foreach ( EMCP_Tools_AI_Providers::ids() as $id ) {
			if ( self::has_key( $id, $user_id ) ) {
				return true;
			}
		}
		return false;
	}

	// ── models (per provider, cached) ─────────────────────────────────────────

	/**
	 * Fetches + normalizes the model list for a provider. Returns
	 * `[ { id, display_name, created_at } ]` or WP_Error.
	 *
	 * @since 2.2.0
	 * @param string $provider Provider id.
	 * @param string $key      API key (plaintext).
	 * @return array|\WP_Error
	 */
	public static function fetch_models( string $provider, string $key ) {
		$cfg = EMCP_Tools_AI_Providers::get( $provider );
		if ( null === $cfg ) {
			return new \WP_Error( 'unknown_provider', __( 'Unknown provider.', 'emcp-tools' ) );
		}
		$key = trim( $key );
		if ( '' === $key ) {
			return new \WP_Error( 'no_key', __( 'No API key provided.', 'emcp-tools' ) );
		}

		$headers = $cfg['headers'];
		if ( 'bearer' === $cfg['auth'] ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		} else {
			$headers['x-api-key'] = $key;
		}

		$response = wp_remote_get( $cfg['models_url'], array( 'timeout' => 20, 'headers' => $headers ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error( 'invalid_key', __( 'The provider rejected this API key.', 'emcp-tools' ), array( 'status' => 401 ) );
		}
		if ( 200 !== $code || ! is_array( $body ) || empty( $body['data'] ) ) {
			$msg = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : __( 'Could not fetch models from the provider.', 'emcp-tools' );
			return new \WP_Error( 'fetch_failed', $msg, array( 'status' => $code ?: 502 ) );
		}

		$filter = isset( $cfg['model_filter'] ) ? (string) $cfg['model_filter'] : '';
		$models = array();
		foreach ( $body['data'] as $m ) {
			if ( empty( $m['id'] ) ) {
				continue;
			}
			$id = preg_replace( '#^models/#', '', (string) $m['id'] ); // Gemini ids come as "models/…".
			if ( '' !== $filter && ! preg_match( $filter, $id ) ) {
				continue;
			}
			$display = isset( $m['display_name'] ) ? $m['display_name'] : ( isset( $m['name'] ) ? $m['name'] : $id );
			$created = isset( $m['created_at'] ) ? (string) $m['created_at'] : ( isset( $m['created'] ) ? (string) $m['created'] : '' );

			// Free flag — OpenRouter exposes per-token pricing; "0" prompt+completion
			// (or a ":free" id) means a free model. Surfaced first in the picker.
			$free = false;
			if ( isset( $m['pricing'] ) && is_array( $m['pricing'] ) ) {
				$free = 0.0 === (float) ( $m['pricing']['prompt'] ?? 1 ) && 0.0 === (float) ( $m['pricing']['completion'] ?? 1 );
			}
			if ( false !== strpos( $id, ':free' ) ) {
				$free = true;
			}

			$models[] = array( 'id' => $id, 'display_name' => (string) $display, 'created_at' => $created, 'free' => $free );
		}

		if ( empty( $models ) ) {
			return new \WP_Error( 'no_models', __( 'The provider returned no usable chat models.', 'emcp-tools' ) );
		}
		return $models;
	}

	/** @return array<string,array> provider => { fetched_at, models } */
	private static function models_store(): array {
		$s = get_option( self::MODELS_OPT, array() );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * @since 2.2.0
	 * @param string $provider Provider id.
	 * @param array  $models   Normalized model list.
	 * @param int    $now      Timestamp.
	 */
	public static function store_models( string $provider, array $models, int $now ): void {
		$store              = self::models_store();
		$store[ $provider ] = array( 'fetched_at' => $now, 'models' => array_values( $models ) );
		update_option( self::MODELS_OPT, $store, false );
	}

	/**
	 * @since 2.2.0
	 * @param string $provider Provider id.
	 * @return array
	 */
	public static function get_models( string $provider ): array {
		$store = self::models_store();
		return ( isset( $store[ $provider ]['models'] ) && is_array( $store[ $provider ]['models'] ) ) ? $store[ $provider ]['models'] : array();
	}

	/**
	 * All cached model lists keyed by provider (only providers with cached lists).
	 *
	 * @since 2.2.0
	 * @return array<string,array>
	 */
	public static function all_models(): array {
		$out = array();
		foreach ( self::models_store() as $provider => $entry ) {
			if ( isset( $entry['models'] ) && is_array( $entry['models'] ) ) {
				$out[ $provider ] = $entry['models'];
			}
		}
		return $out;
	}

	/**
	 * Best default model id for a provider: first model matching the provider's
	 * default regex, else the first model, else ''.
	 *
	 * @since 2.2.0
	 * @param string $provider Provider id.
	 * @return string
	 */
	public static function default_model( string $provider, ?int $user_id = null ): string {
		$models = self::get_models( $provider );
		if ( empty( $models ) ) {
			return '';
		}
		$ids = array();
		foreach ( $models as $m ) {
			$ids[] = $m['id'];
		}

		// A user-chosen default wins, if it's still a real model.
		$chosen = self::get_chosen_default( $provider, $user_id );
		if ( '' !== $chosen && in_array( $chosen, $ids, true ) ) {
			return $chosen;
		}

		$cfg = EMCP_Tools_AI_Providers::get( $provider );
		$rx  = ( $cfg && ! empty( $cfg['default_rx'] ) ) ? '/' . $cfg['default_rx'] . '/i' : '';
		if ( '' !== $rx ) {
			foreach ( $models as $m ) {
				if ( preg_match( $rx, $m['id'] ) ) {
					return (string) $m['id'];
				}
			}
		}
		return (string) $models[0]['id'];
	}

	/**
	 * The user's chosen default model id for a provider, or '' if none set.
	 *
	 * @since 2.2.0
	 * @param string   $provider Provider id.
	 * @param int|null $user_id  User.
	 * @return string
	 */
	public static function get_chosen_default( string $provider, ?int $user_id = null ): string {
		$map = get_user_meta( self::uid( $user_id ), self::CHOSEN_META, true );
		return ( is_array( $map ) && isset( $map[ $provider ] ) ) ? (string) $map[ $provider ] : '';
	}

	/**
	 * Sets (or clears, with '') the user's chosen default model for a provider.
	 *
	 * @since 2.2.0
	 * @param string   $provider Provider id.
	 * @param string   $model    Model id ('' to clear).
	 * @param int|null $user_id  User.
	 */
	public static function set_chosen_default( string $provider, string $model, ?int $user_id = null ): void {
		$map = get_user_meta( self::uid( $user_id ), self::CHOSEN_META, true );
		if ( ! is_array( $map ) ) {
			$map = array();
		}
		if ( '' === $model ) {
			unset( $map[ $provider ] );
		} else {
			$map[ $provider ] = $model;
		}
		update_user_meta( self::uid( $user_id ), self::CHOSEN_META, $map );
	}

	// ── refresh ──────────────────────────────────────────────────────────────

	/**
	 * Re-fetch + cache a provider's models using the (given/any) user's key.
	 *
	 * @since 2.2.0
	 * @param string   $provider Provider id.
	 * @param int|null $user_id  User.
	 * @param int|null $now      Timestamp.
	 * @return bool|\WP_Error True on refresh, false if skipped, WP_Error on failure.
	 */
	public static function refresh_models( string $provider, ?int $user_id = null, ?int $now = null ) {
		if ( ! self::has_access() ) {
			return false;
		}
		$key = self::get_key( $provider, $user_id );
		if ( '' === $key ) {
			return false;
		}
		$models = self::fetch_models( $provider, $key );
		if ( is_wp_error( $models ) ) {
			return $models;
		}
		self::store_models( $provider, $models, $now ?? time() );
		return true;
	}

	/** @since 2.2.0 */
	public static function maybe_schedule_refresh(): void {
		if ( ! wp_next_scheduled( self::REFRESH_HOOK ) ) {
			wp_schedule_event( time() + WEEK_IN_SECONDS, 'weekly', self::REFRESH_HOOK );
		}
	}

	/**
	 * WP-Cron: refresh every provider that some user has a key for (the model
	 * cache is global, so any valid key works).
	 *
	 * @since 2.2.0
	 */
	public static function cron_refresh(): void {
		if ( ! self::has_access() ) {
			return;
		}
		$users = get_users(
			array(
				'meta_key' => self::KEYS_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'number'   => 50,
				'fields'   => 'ID',
			)
		);
		$done = array();
		foreach ( $users as $uid ) {
			foreach ( EMCP_Tools_AI_Providers::ids() as $provider ) {
				if ( in_array( $provider, $done, true ) ) {
					continue;
				}
				if ( self::has_key( $provider, (int) $uid ) ) {
					$ok = self::refresh_models( $provider, (int) $uid );
					if ( true === $ok ) {
						$done[] = $provider;
					}
				}
			}
		}
	}
}
