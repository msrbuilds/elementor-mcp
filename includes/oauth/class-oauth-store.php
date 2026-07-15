<?php
/**
 * OAuth persistence — registered clients and issued tokens live in two custom
 * tables; short-lived authorization codes live in transients. Tokens and codes
 * are stored SHA-256-hashed (never in the clear).
 *
 * @package EMCP_Tools
 * @since   3.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DB access layer for the OAuth server.
 *
 * @since 3.4.1
 */
class EMCP_Tools_OAuth_Store {

	const DB_VERSION        = 1;
	const DB_VERSION_OPTION = 'emcp_tools_oauth_db_version';
	const CODE_TTL          = 60;               // seconds
	const CODE_PREFIX       = 'emcp_oauth_code_';

	/**
	 * Registered-clients table name.
	 *
	 * @return string
	 */
	public static function clients_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'emcp_oauth_clients';
	}

	/**
	 * Issued-tokens table name.
	 *
	 * @return string
	 */
	public static function tokens_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'emcp_oauth_tokens';
	}

	/**
	 * Create/upgrade the OAuth tables when the stored version is behind.
	 */
	public static function maybe_install(): void {
		$installed = (int) get_option( self::DB_VERSION_OPTION, 0 );
		if ( $installed >= self::DB_VERSION ) {
			return;
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			$upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( is_readable( $upgrade ) ) {
				require_once $upgrade;
			}
		}
		if ( function_exists( 'dbDelta' ) ) {
			global $wpdb;
			$charset = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
			$clients = self::clients_table();
			$tokens  = self::tokens_table();

			dbDelta(
				"CREATE TABLE {$clients} (
					client_id VARCHAR(64) NOT NULL,
					client_name VARCHAR(191) NOT NULL,
					redirect_uris TEXT NOT NULL,
					created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
					created_at INT NOT NULL,
					PRIMARY KEY (client_id)
				) {$charset};"
			);
			dbDelta(
				"CREATE TABLE {$tokens} (
					id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					token_hash CHAR(64) NOT NULL,
					token_type VARCHAR(10) NOT NULL,
					client_id VARCHAR(64) NOT NULL,
					user_id BIGINT UNSIGNED NOT NULL,
					scopes VARCHAR(191) NOT NULL DEFAULT '',
					expires_at INT NOT NULL,
					refresh_of BIGINT UNSIGNED NULL DEFAULT NULL,
					created_at INT NOT NULL,
					PRIMARY KEY (id),
					UNIQUE KEY token_hash (token_hash),
					KEY client_id (client_id),
					KEY user_id (user_id),
					KEY expires_at (expires_at)
				) {$charset};"
			);
		}
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	// ---------------------------------------------------------------------
	// Clients
	// ---------------------------------------------------------------------

	/**
	 * Register a new public client (Dynamic Client Registration).
	 *
	 * @param string   $name          Human-readable client name.
	 * @param string[] $redirect_uris Registered redirect URIs.
	 * @param int      $user_id       Authorizing user id (audit).
	 * @return array{client_id:string,client_name:string,redirect_uris:string[]}
	 */
	public static function create_client( string $name, array $redirect_uris, int $user_id = 0 ): array {
		global $wpdb;
		$client_id = EMCP_Tools_OAuth_Util::generate_client_id();
		$uris      = array_values( array_unique( array_filter( array_map( 'strval', $redirect_uris ) ) ) );

		$wpdb->insert(
			self::clients_table(),
			array(
				'client_id'     => $client_id,
				'client_name'   => mb_substr( $name, 0, 191 ),
				'redirect_uris' => wp_json_encode( $uris ),
				'created_by'    => $user_id,
				'created_at'    => time(),
			),
			array( '%s', '%s', '%s', '%d', '%d' )
		);

		return array(
			'client_id'     => $client_id,
			'client_name'   => $name,
			'redirect_uris' => $uris,
		);
	}

	/**
	 * Fetch a client by id.
	 *
	 * @param string $client_id Client id.
	 * @return array{client_id:string,client_name:string,redirect_uris:string[]}|null
	 */
	public static function get_client( string $client_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::clients_table() . ' WHERE client_id = %s', $client_id ),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$uris = json_decode( (string) $row['redirect_uris'], true );
		return array(
			'client_id'     => (string) $row['client_id'],
			'client_name'   => (string) $row['client_name'],
			'redirect_uris' => is_array( $uris ) ? $uris : array(),
		);
	}

	// ---------------------------------------------------------------------
	// Authorization codes (transient-backed, single-use, short TTL)
	// ---------------------------------------------------------------------

	/**
	 * Store a single-use authorization code and return the raw code.
	 *
	 * @param array $payload { client_id, user_id, redirect_uri, code_challenge, scopes }.
	 * @return string The raw authorization code to hand to the client.
	 */
	public static function issue_code( array $payload ): string {
		$code = EMCP_Tools_OAuth_Util::generate_token();
		set_transient( self::CODE_PREFIX . EMCP_Tools_OAuth_Util::hash_token( $code ), $payload, self::CODE_TTL );
		return $code;
	}

	/**
	 * Consume (fetch + delete) an authorization code. Returns null if unknown or
	 * already used/expired.
	 *
	 * @param string $code Raw authorization code.
	 * @return array|null
	 */
	public static function consume_code( string $code ) {
		$key     = self::CODE_PREFIX . EMCP_Tools_OAuth_Util::hash_token( $code );
		$payload = get_transient( $key );
		if ( false === $payload || ! is_array( $payload ) ) {
			return null;
		}
		delete_transient( $key );
		return $payload;
	}

	// ---------------------------------------------------------------------
	// Tokens
	// ---------------------------------------------------------------------

	/**
	 * Issue and persist a token (stored hashed). Returns the raw token.
	 *
	 * @param string   $type       'access' | 'refresh'.
	 * @param string   $client_id  Client id.
	 * @param int      $user_id    User the token acts as.
	 * @param string   $scopes     Space-separated scopes.
	 * @param int      $ttl        Lifetime in seconds.
	 * @param int|null $refresh_of Token id this access token is bound to (rotation).
	 * @return array{token:string,id:int} Raw token + row id.
	 */
	public static function issue_token( string $type, string $client_id, int $user_id, string $scopes, int $ttl, ?int $refresh_of = null ): array {
		global $wpdb;
		$token = EMCP_Tools_OAuth_Util::generate_token();
		$wpdb->insert(
			self::tokens_table(),
			array(
				'token_hash' => EMCP_Tools_OAuth_Util::hash_token( $token ),
				'token_type' => $type,
				'client_id'  => $client_id,
				'user_id'    => $user_id,
				'scopes'     => $scopes,
				'expires_at' => time() + $ttl,
				'refresh_of' => $refresh_of,
				'created_at' => time(),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d' )
		);
		return array( 'token' => $token, 'id' => (int) $wpdb->insert_id );
	}

	/**
	 * Look up an unexpired token row by raw token + type.
	 *
	 * @param string $token Raw token.
	 * @param string $type  'access' | 'refresh'.
	 * @return array|null Row (assoc) or null.
	 */
	public static function find_token( string $token, string $type ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::tokens_table() . ' WHERE token_hash = %s AND token_type = %s AND expires_at > %d',
				EMCP_Tools_OAuth_Util::hash_token( $token ),
				$type,
				time()
			),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * Delete a token row and any access token bound to it (refresh rotation).
	 *
	 * @param int $id Token row id.
	 */
	public static function revoke_token( int $id ): void {
		global $wpdb;
		$wpdb->delete( self::tokens_table(), array( 'id' => $id ), array( '%d' ) );
		$wpdb->delete( self::tokens_table(), array( 'refresh_of' => $id ), array( '%d' ) );
	}

	/**
	 * Revoke every token issued to a client. Returns rows removed.
	 *
	 * @param string $client_id Client id.
	 * @return int
	 */
	public static function revoke_client( string $client_id ): int {
		global $wpdb;
		return (int) $wpdb->delete( self::tokens_table(), array( 'client_id' => $client_id ), array( '%s' ) );
	}

	/**
	 * List registered clients that have at least one live token, with usage
	 * detail for the admin "Authorized clients" table.
	 *
	 * @return array<int,array{client_id:string,client_name:string,user_id:int,created_at:int,active_tokens:int}>
	 */
	public static function list_authorized_clients(): array {
		global $wpdb;
		$clients = self::clients_table();
		$tokens  = self::tokens_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.client_id, c.client_name, c.created_at,
					COUNT( t.id ) AS active_tokens,
					MAX( t.user_id ) AS user_id
				FROM {$clients} c
				INNER JOIN {$tokens} t
					ON t.client_id = c.client_id AND t.expires_at > %d
				GROUP BY c.client_id, c.client_name, c.created_at
				ORDER BY c.created_at DESC",
				time()
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( $r ) {
				return array(
					'client_id'     => (string) $r['client_id'],
					'client_name'   => (string) $r['client_name'],
					'user_id'       => (int) $r['user_id'],
					'created_at'    => (int) $r['created_at'],
					'active_tokens' => (int) $r['active_tokens'],
				);
			},
			$rows
		);
	}

	/**
	 * Delete expired tokens (housekeeping).
	 */
	public static function gc(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::tokens_table() . ' WHERE expires_at < %d', time() ) );
	}
}
