<?php
/**
 * Connection Token Manager.
 *
 * Plugin-managed Bearer tokens for MCP authentication with
 * per-connection history, labeling, and real revoke support.
 *
 * Token format: emcp_ + 60 random hex chars = 65 char token.
 * Storage: only sha256(token) is persisted; raw token shown once at creation.
 *
 * @package Elementor_MCP
 * @since   2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor_MCP_Connection_Tokens {

	/** @var string Option key for connections array. */
	const OPTION_KEY = 'elementor_mcp_connections';

	/** @var string Token prefix — ensures we only intercept our tokens. */
	const TOKEN_PREFIX = 'emcp_';

	/**
	 * Boot: register the authentication filter.
	 */
	public function init(): void {
		add_filter( 'determine_current_user', array( $this, 'authenticate_bearer' ), 20 );
	}

	// =========================================================================
	//  Token CRUD
	// =========================================================================

	/**
	 * Generate a new connection token.
	 *
	 * @param int    $user_id WP user ID the token authenticates as.
	 * @param string $label   Human-readable label (e.g. "Claude Desktop").
	 *
	 * @return array{id: string, raw_token: string, connection: array} Token data.
	 */
	public function generate( int $user_id, string $label ): array {
		$raw_token  = self::TOKEN_PREFIX . bin2hex( random_bytes( 30 ) ); // 65 chars.
		$token_hash = hash( 'sha256', $raw_token );
		$token_hint = '••••••••' . substr( $raw_token, -4 );
		$conn_id    = 'conn_' . bin2hex( random_bytes( 8 ) );

		$user = get_userdata( $user_id );

		$connection = array(
			'id'          => $conn_id,
			'label'       => $label,
			'token_hash'  => $token_hash,
			'token_hint'  => $token_hint,
			'user_id'     => $user_id,
			'created_by'  => $user ? $user->user_login : 'unknown',
			'created_at'  => gmdate( 'c' ),
			'last_used_at' => null,
			'usage_count' => 0,
			'status'      => 'active',
			'revoked_at'  => null,
			'revoked_by'  => null,
		);

		$connections = $this->get_all();
		$connections[ $conn_id ] = $connection;
		update_option( self::OPTION_KEY, $connections, false );

		return array(
			'id'         => $conn_id,
			'raw_token'  => $raw_token,
			'connection' => $connection,
		);
	}

	/**
	 * Validate an incoming raw Bearer token.
	 *
	 * @param string $raw_token The raw token from the Authorization header.
	 *
	 * @return int|false WP user ID if valid and active, false otherwise.
	 */
	public function validate( string $raw_token ) {
		if ( 0 !== strpos( $raw_token, self::TOKEN_PREFIX ) ) {
			return false;
		}

		$token_hash  = hash( 'sha256', $raw_token );
		$connections = $this->get_all();

		foreach ( $connections as $id => &$conn ) {
			if ( $conn['token_hash'] === $token_hash ) {
				if ( 'active' !== $conn['status'] ) {
					return false; // Revoked or invalid → deny.
				}

				// Update usage stats.
				$conn['last_used_at'] = gmdate( 'c' );
				$conn['usage_count']  = ( $conn['usage_count'] ?? 0 ) + 1;
				update_option( self::OPTION_KEY, $connections, false );

				return (int) $conn['user_id'];
			}
		}
		unset( $conn );

		return false;
	}

	/**
	 * Revoke a connection token.
	 *
	 * @param string $conn_id    Connection ID to revoke.
	 * @param int    $revoked_by WP user ID performing the revocation.
	 *
	 * @return bool True if revoked, false if not found.
	 */
	public function revoke( string $conn_id, int $revoked_by = 0 ): bool {
		$connections = $this->get_all();

		if ( ! isset( $connections[ $conn_id ] ) ) {
			return false;
		}

		$connections[ $conn_id ]['status']     = 'revoked';
		$connections[ $conn_id ]['revoked_at'] = gmdate( 'c' );

		if ( $revoked_by ) {
			$user = get_userdata( $revoked_by );
			$connections[ $conn_id ]['revoked_by'] = $user ? $user->user_login : 'user_' . $revoked_by;
		}

		update_option( self::OPTION_KEY, $connections, false );

		return true;
	}

	/**
	 * Permanently delete a connection record.
	 *
	 * @param string $conn_id Connection ID.
	 *
	 * @return bool True if deleted.
	 */
	public function delete( string $conn_id ): bool {
		$connections = $this->get_all();

		if ( ! isset( $connections[ $conn_id ] ) ) {
			return false;
		}

		unset( $connections[ $conn_id ] );
		update_option( self::OPTION_KEY, $connections, false );

		return true;
	}

	/**
	 * Get all connection records.
	 *
	 * @return array<string, array> Connections keyed by ID.
	 */
	public function get_all(): array {
		$connections = get_option( self::OPTION_KEY, array() );
		return is_array( $connections ) ? $connections : array();
	}

	/**
	 * Get a single connection record.
	 *
	 * @param string $conn_id Connection ID.
	 *
	 * @return array|null Connection data or null.
	 */
	public function get( string $conn_id ): ?array {
		$connections = $this->get_all();
		return $connections[ $conn_id ] ?? null;
	}

	// =========================================================================
	//  WordPress Auth Filter
	// =========================================================================

	/**
	 * Authenticate incoming requests using our Bearer tokens.
	 *
	 * Hooked to `determine_current_user` at priority 20.
	 * Only processes tokens with our `emcp_` prefix.
	 *
	 * @param int|false $user_id Current user ID or false.
	 *
	 * @return int|false Authenticated user ID or passthrough.
	 */
	public function authenticate_bearer( $user_id ) {
		// If already authenticated, pass through.
		if ( $user_id ) {
			return $user_id;
		}

		$raw_token = $this->get_bearer_token();

		if ( ! $raw_token || 0 !== strpos( $raw_token, self::TOKEN_PREFIX ) ) {
			return $user_id; // Not our token, pass through.
		}

		$validated_user = $this->validate( $raw_token );

		return $validated_user ? $validated_user : $user_id;
	}

	/**
	 * Extract Bearer token from Authorization header.
	 *
	 * @return string|null Raw token or null.
	 */
	private function get_bearer_token(): ?string {
		$headers = null;

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
		}

		// Normalize header keys.
		if ( $headers ) {
			foreach ( $headers as $key => $value ) {
				if ( strtolower( $key ) === 'authorization' ) {
					$headers['Authorization'] = $value;
					break;
				}
			}
		}

		$auth = $headers['Authorization'] ?? ( $_SERVER['HTTP_AUTHORIZATION'] ?? '' );

		if ( empty( $auth ) && isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
		}

		if ( empty( $auth ) ) {
			return null;
		}

		if ( 0 === stripos( $auth, 'Bearer ' ) ) {
			return trim( substr( $auth, 7 ) );
		}

		return null;
	}
}
