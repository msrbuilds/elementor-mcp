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

	const DB_VERSION        = 4; // v4: bind OAuth tokens to their MCP resource/audience.
	const DB_VERSION_OPTION = 'emcp_tools_oauth_db_version';
	// A freshly-registered client legitimately has no tokens until the user
	// finishes authorizing, so orphan-client pruning only touches rows older
	// than this grace window — AND only rows that never completed an
	// authorization (authorized_at = 0). A client that was authorized once is
	// never auto-purged, however long its tokens have been gone.
	const ORPHAN_CLIENT_GRACE = DAY_IN_SECONDS;
	// Throttle for gc_throttled(): the shortest gap between sweeps when gc is
	// driven from a hot path (bearer validation on every MCP request).
	const GC_THROTTLE_OPTION   = 'emcp_tools_oauth_gc_throttle';
	const GC_THROTTLE_INTERVAL = 900; // 15 min
	// Authorization-code lifetime. Kept short per the OAuth spec (RFC 6749
	// §4.1.2 recommends a maximum of ~10 min), but generous enough for CLI MCP
	// clients that print the URL and require a manual copy-paste of the code
	// (e.g. OpenClaw), where 60s was easy to miss. The code stays single-use and
	// PKCE-bound; only the window widened.
	const CODE_TTL          = 300;              // seconds (5 minutes)
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
		self::install_tables();
		if ( $installed < 3 ) {
			self::backfill_authorized_clients();
		}
		if ( $installed < 4 ) {
			self::backfill_token_resources();
		}
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * (Re)create the OAuth tables via dbDelta — idempotent. Also called to
	 * self-heal a drifted or missing schema when a token insert fails.
	 */
	public static function install_tables(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			$upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( is_readable( $upgrade ) ) {
				require_once $upgrade;
			}
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			return;
		}
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
					created_at BIGINT NOT NULL,
					authorized_at BIGINT NOT NULL DEFAULT 0,
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
					resource TEXT NOT NULL,
					expires_at BIGINT NOT NULL,
					refresh_of BIGINT UNSIGNED NULL DEFAULT NULL,
					created_at BIGINT NOT NULL,
					PRIMARY KEY (id),
					UNIQUE KEY token_hash (token_hash),
					KEY client_id (client_id),
					KEY user_id (user_id),
					KEY expires_at (expires_at),
					KEY refresh_of (refresh_of)
				) {$charset};"
			);
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
		$uris = array_values( array_unique( array_filter( array_map( 'strval', $redirect_uris ) ) ) );

		// Reuse an existing public client with the same name + redirect URIs
		// instead of minting a fresh row on every connect. MCP clients (Claude,
		// Codex) re-run Dynamic Client Registration each time they connect; without
		// this the clients table grows unbounded (one dead row per connect). These
		// are public PKCE clients (no secret), and tokens are bound to the
		// authorizing user, so sharing a client_id across connects is safe.
		$existing = self::find_client_by_registration( $name, $uris );
		if ( $existing ) {
			return $existing;
		}

		$client_id = EMCP_Tools_OAuth_Util::generate_client_id();

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
	 * Find an existing client whose name + normalized redirect URIs match a
	 * registration request, so repeat DCR from the same MCP client reuses it.
	 *
	 * @param string   $name Client name.
	 * @param string[] $uris Normalized (unique, filtered) redirect URIs.
	 * @return array{client_id:string,client_name:string,redirect_uris:string[]}|null
	 */
	public static function find_client_by_registration( string $name, array $uris ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT client_id FROM ' . self::clients_table() . ' WHERE client_name = %s AND redirect_uris = %s LIMIT 1',
				mb_substr( $name, 0, 191 ),
				(string) wp_json_encode( $uris )
			),
			ARRAY_A
		);
		return $row ? self::get_client( (string) $row['client_id'] ) : null;
	}

	/** Seconds a fallback lease lives if its process dies without releasing it. */
	const LOCK_LEASE_TTL = 60;

	/** @var array<string, array{depth:int, mode:string, owner:string}> Locks this process holds. */
	private static $held = array();

	/** @var EMCP_Tools_Lease|null Lease used where the database has no named locks (tests inject one). */
	private static $lease = null;

	/**
	 * Use this lease for the fallback lock; null restores the options-table lease.
	 *
	 * @param EMCP_Tools_Lease|null $lease Lease.
	 */
	public static function use_lease( ?EMCP_Tools_Lease $lease ): void {
		self::$lease = $lease;
		self::$held  = array();
	}

	/** The registration identity lock name. */
	private static function registration_lock_key( string $name, array $uris ): string {
		global $wpdb;
		$normalized = array_values( array_unique( array_filter( array_map( 'strval', $uris ) ) ) );
		return 'emcp_oauth_reg_' . md5( $wpdb->prefix . '|' . mb_substr( $name, 0, 191 ) . '|' . wp_json_encode( $normalized ) );
	}

	/**
	 * Take a lock, waiting up to $timeout seconds. Re-entrant within this process.
	 *
	 * MySQL and MariaDB answer GET_LOCK with 1 (held) or 0 (busy, so the caller
	 * waits or answers busy). A database without named locks (some managed and
	 * clustered setups) answers NULL or an error; there the lock is EMCP's lease,
	 * an atomic options-table row, so OAuth keeps working instead of answering 503.
	 *
	 * @param string $key     Lock name.
	 * @param int    $timeout Seconds to wait.
	 * @return bool
	 */
	private static function lock( string $key, int $timeout ): bool {
		global $wpdb;
		if ( isset( self::$held[ $key ] ) ) {
			++self::$held[ $key ]['depth'];
			return true;
		}
		// A database without named locks answers this with an error: keep it off the page.
		$quiet  = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
		$answer = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $key, max( 0, $timeout ) ) );
		if ( null !== $quiet ) {
			$wpdb->suppress_errors( $quiet );
		}
		if ( '1' === (string) $answer ) {
			self::$held[ $key ] = array( 'depth' => 1, 'mode' => 'named', 'owner' => '' );
			return true;
		}
		if ( '0' === (string) $answer ) {
			return false;
		}
		$lease = self::$lease;
		if ( null === $lease ) {
			if ( ! class_exists( 'EMCP_Tools_Lease' ) ) {
				return false;
			}
			$lease = new EMCP_Tools_Lease();
		}
		$owner    = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) );
		$name     = 'oauth_' . md5( $key );
		$deadline = microtime( true ) + max( 0, $timeout );
		while ( true ) {
			if ( $lease->acquire( $name, $owner, self::LOCK_LEASE_TTL ) ) {
				self::$held[ $key ] = array( 'depth' => 1, 'mode' => 'lease', 'owner' => $owner );
				return true;
			}
			if ( microtime( true ) >= $deadline ) {
				return false;
			}
			usleep( 100000 );
		}
	}

	/**
	 * Release a lock taken with lock(); the outermost release frees it.
	 *
	 * @param string $key Lock name.
	 */
	private static function unlock( string $key ): void {
		global $wpdb;
		if ( ! isset( self::$held[ $key ] ) ) {
			return;
		}
		if ( --self::$held[ $key ]['depth'] > 0 ) {
			return;
		}
		$held = self::$held[ $key ];
		unset( self::$held[ $key ] );
		if ( 'named' === $held['mode'] ) {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
			return;
		}
		$lease = self::$lease ?? ( class_exists( 'EMCP_Tools_Lease' ) ? new EMCP_Tools_Lease() : null );
		if ( $lease ) {
			$lease->release( 'oauth_' . md5( $key ), $held['owner'] );
		}
	}

	/**
	 * Acquire a lock for a normalized public-client registration identity.
	 *
	 * This closes the check-then-insert race in stable client provisioning
	 * without changing the public DCR table contract.
	 *
	 * @param string   $name    Client name.
	 * @param string[] $uris    Redirect URIs.
	 * @param int      $timeout Seconds to wait.
	 * @return bool
	 */
	public static function acquire_client_registration_lock( string $name, array $uris, int $timeout = 5 ): bool {
		global $wpdb;
		if ( '' === $name || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return false;
		}
		return self::lock( self::registration_lock_key( $name, $uris ), $timeout );
	}

	/**
	 * Release a public-client registration identity lock.
	 *
	 * @param string   $name Client name.
	 * @param string[] $uris Redirect URIs.
	 */
	public static function release_client_registration_lock( string $name, array $uris ): void {
		global $wpdb;
		if ( '' === $name || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return;
		}
		self::unlock( self::registration_lock_key( $name, $uris ) );
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
	 * @param string   $resource   MCP resource/audience.
	 * @return array{token:string,id:int} Raw token + row id.
	 */
	public static function issue_token( string $type, string $client_id, int $user_id, string $scopes, int $ttl, ?int $refresh_of = null, string $resource = '' ): array {
		global $wpdb;
		$token   = EMCP_Tools_OAuth_Util::generate_token();
		$resource = '' !== $resource ? $resource : EMCP_Tools_OAuth_Metadata::resource();
		$data    = array(
			'token_hash' => EMCP_Tools_OAuth_Util::hash_token( $token ),
			'token_type' => $type,
			'client_id'  => $client_id,
			'user_id'    => $user_id,
			'scopes'     => $scopes,
			'resource'   => $resource,
			'expires_at' => time() + $ttl,
			'refresh_of' => $refresh_of,
			'created_at' => time(),
		);
		$formats = array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d' );

		$ok = $wpdb->insert( self::tokens_table(), $data, $formats );
		if ( false === $ok ) {
			// The insert failed — most often a drifted/missing tokens table after an
			// upgrade. Recreate the tables (dbDelta is idempotent) and retry once.
			self::install_tables();
			$ok = $wpdb->insert( self::tokens_table(), $data, $formats );
		}
		if ( false === $ok || ! $wpdb->insert_id ) {
			// Never hand back a token we didn't store — the caller turns this into an
			// OAuth error instead of a 200 with an unusable token. Log the DB error so
			// the real cause is visible.
			if ( function_exists( 'error_log' ) ) {
				error_log( '[EMCP Tools] OAuth token was not persisted: ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return array( 'token' => '', 'id' => 0 );
		}
		// A token was issued for this client, so it completed an authorization:
		// stamp it so gc() never prunes the registration when the tokens later
		// lapse (that deletion is what produced the permanent "Invalid client").
		self::mark_client_authorized( $client_id );
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
	 * Delete a token row and any access token bound to it. Used for explicit
	 * revocation (RFC 7009 /revoke) and client teardown — there we DO want the
	 * bound access token gone immediately.
	 *
	 * @param int $id Token row id.
	 */
	public static function revoke_token( int $id ): void {
		global $wpdb;
		$wpdb->delete( self::tokens_table(), array( 'id' => $id ), array( '%d' ) );
		$wpdb->delete( self::tokens_table(), array( 'refresh_of' => $id ), array( '%d' ) );
	}

	/**
	 * Rotate a refresh token out WITHOUT touching its bound access token.
	 *
	 * On refresh the old refresh token must become unusable (rotation), but the
	 * old ACCESS token is a short-lived bearer credential that in-flight MCP
	 * requests may still be carrying. Cascade-deleting it (as revoke_token does)
	 * 401s those requests the instant the client refreshes — which surfaced as
	 * connections dropping mid-chat. Letting the access token live out its own
	 * TTL is the standard OAuth behaviour (RFC 6749 §1.5: refreshing does not
	 * invalidate previously issued access tokens).
	 *
	 * When a positive $grace is given, the rotated refresh token is not deleted
	 * but SOFT-EXPIRED to `now + grace` instead — it stays usable for that short
	 * window so a lost-response retry (the client's refresh succeeded server-side
	 * but the response was dropped, so it re-presents the same refresh token)
	 * re-rotates and gets a fresh pair rather than a 401 mid-chat. We only ever
	 * shorten the lifetime (grace << remaining refresh TTL), then it expires on
	 * its own and gc() prunes it. $grace = 0 keeps the immediate-delete behaviour.
	 *
	 * @param int $id    Refresh token row id.
	 * @param int $grace Grace window in seconds (0 = delete immediately).
	 */
	public static function rotate_out_refresh( int $id, int $grace = 0 ): void {
		global $wpdb;
		if ( $grace <= 0 ) {
			$wpdb->delete( self::tokens_table(), array( 'id' => $id ), array( '%d' ) );
			return;
		}
		$until = time() + $grace;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::tokens_table() . ' SET expires_at = %d WHERE id = %d AND expires_at > %d',
				$until,
				$id,
				$until
			)
		);
	}

	/**
	 * Revoke every token issued to a client. Returns rows removed.
	 *
	 * @param string $client_id Client id.
	 * @return int|false Rows removed, or false on lock/database failure.
	 */
	public static function revoke_client( string $client_id ) {
		if ( '' === $client_id || ! self::acquire_client_token_lock( $client_id ) ) {
			return false;
		}

		try {
			return self::revoke_client_locked( $client_id );
		} finally {
			self::release_client_token_lock( $client_id );
		}
	}

	/**
	 * Delete all tokens for a client while its mutation lock is held.
	 *
	 * @param string $client_id Client id.
	 * @return int|false
	 */
	public static function revoke_client_locked( string $client_id ) {
		global $wpdb;
		return $wpdb->delete( self::tokens_table(), array( 'client_id' => $client_id ), array( '%s' ) );
	}

	/**
	 * Acquire the per-client token mutation lock.
	 *
	 * @param string $client_id Client id.
	 * @param int    $timeout   Seconds to wait.
	 * @return bool
	 */
	public static function acquire_client_token_lock( string $client_id, int $timeout = 5 ): bool {
		global $wpdb;
		if ( '' === $client_id || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return false;
		}
		return self::lock( 'emcp_oauth_' . md5( $wpdb->prefix . '|' . $client_id ), $timeout );
	}

	/**
	 * Release the per-client token mutation lock.
	 *
	 * @param string $client_id Client id.
	 */
	public static function release_client_token_lock( string $client_id ): void {
		global $wpdb;
		if ( '' === $client_id || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return;
		}
		self::unlock( 'emcp_oauth_' . md5( $wpdb->prefix . '|' . $client_id ) );
	}

	/**
	 * Revoke every token for a client except one newly-issued refresh token.
	 *
	 * @param string $client_id Client id.
	 * @param int    $keep_id   Token row to preserve.
	 * @return bool Whether the delete completed.
	 */
	public static function revoke_client_tokens_except( string $client_id, int $keep_id ): bool {
		global $wpdb;
		if ( '' === $client_id || $keep_id <= 0 ) {
			return false;
		}
		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::tokens_table() . ' WHERE client_id = %s AND id <> %d',
				$client_id,
				$keep_id
			)
		);
		return false !== $result;
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
	 * Every registration, live token or not, for the admin Connected apps table.
	 *
	 * list_authorized_clients() INNER JOINs live tokens, so it answers "who is
	 * connected". This answers "what has registered", which is the question you
	 * have when a connection is failing: a client that registered and never
	 * finished signing in, or one whose tokens have lapsed, is invisible to the
	 * other list and is exactly the row someone needs to see.
	 *
	 * Connected rows sort first, then newest, so the useful ones stay on page 1
	 * however many dead registrations sit behind them. client_id breaks the
	 * remaining ties: registrations made in the same second sort equal, and an
	 * order that is not total can hand the same row to two pages and hide
	 * another, which is a thing MySQL is allowed to do under LIMIT/OFFSET.
	 *
	 * @since 3.15.0
	 *
	 * @param int $limit  Rows to return.
	 * @param int $offset Rows to skip.
	 * @return array<int,array{client_id:string,client_name:string,redirect_uris:string[],created_at:int,authorized_at:int,user_id:int,active_tokens:int}>
	 */
	public static function list_clients( int $limit = 20, int $offset = 0 ): array {
		global $wpdb;
		$clients = self::clients_table();
		$tokens  = self::tokens_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.client_id, c.client_name, c.redirect_uris, c.created_at, c.authorized_at,
					COUNT( t.id ) AS active_tokens,
					MAX( t.user_id ) AS user_id
				FROM {$clients} c
				LEFT JOIN {$tokens} t
					ON t.client_id = c.client_id AND t.expires_at > %d
				GROUP BY c.client_id, c.client_name, c.redirect_uris, c.created_at, c.authorized_at
				ORDER BY active_tokens DESC, c.created_at DESC, c.client_id DESC
				LIMIT %d OFFSET %d",
				time(),
				max( 1, $limit ),
				max( 0, $offset )
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( $r ) {
				$uris = json_decode( (string) ( $r['redirect_uris'] ?? '' ), true );
				return array(
					'client_id'     => (string) $r['client_id'],
					'client_name'   => (string) $r['client_name'],
					'redirect_uris' => is_array( $uris ) ? $uris : array(),
					'created_at'    => (int) $r['created_at'],
					'authorized_at' => (int) $r['authorized_at'],
					'user_id'       => (int) $r['user_id'],
					'active_tokens' => (int) $r['active_tokens'],
				);
			},
			$rows
		);
	}

	/**
	 * How many registrations exist, for the pager.
	 *
	 * @since 3.15.0
	 * @return int
	 */
	public static function count_clients(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::clients_table() );
	}

	/**
	 * What state a row from list_clients() is in.
	 *
	 * Three cases, and telling them apart is the whole reason the table exists:
	 * a client holding a live token is connected; one that authorized in the
	 * past but holds none has simply signed out and can sign back in; one that
	 * never authorized at all is a registration the app made and abandoned,
	 * which is what a failing connection leaves behind.
	 *
	 * @since 3.15.0
	 *
	 * @param array $row Row from list_clients().
	 * @return string 'connected'|'signed_out'|'registered'
	 */
	public static function client_state( array $row ): string {
		if ( (int) ( $row['active_tokens'] ?? 0 ) > 0 ) {
			return 'connected';
		}
		return (int) ( $row['authorized_at'] ?? 0 ) > 0 ? 'signed_out' : 'registered';
	}

	/**
	 * Delete a registration outright, tokens included.
	 *
	 * revoke_client() deletes tokens and leaves the registration, which is right
	 * for "sign this app out": the client_id keeps working and the app can sign
	 * in again. This is the other thing, for a registration that can never be
	 * used again, and it has to take the tokens with it. Bearer validation reads
	 * the token table alone, so a token outliving its client row would keep
	 * authenticating against a client that no longer exists.
	 *
	 * @since 3.15.0
	 *
	 * @param string $client_id Client id.
	 * @return bool Whether a registration row was removed.
	 */
	public static function delete_client( string $client_id ): bool {
		if ( '' === $client_id || ! self::acquire_client_token_lock( $client_id ) ) {
			return false;
		}
		try {
			return self::delete_client_locked( $client_id );
		} finally {
			self::release_client_token_lock( $client_id );
		}
	}

	/**
	 * Delete a registration and its tokens while its mutation lock is held.
	 *
	 * @param string $client_id Client id.
	 * @return bool Whether the registration row was removed.
	 */
	public static function delete_client_locked( string $client_id ): bool {
		global $wpdb;
		if ( false === self::revoke_client_locked( $client_id ) ) {
			return false;
		}
		return (bool) $wpdb->delete( self::clients_table(), array( 'client_id' => $client_id ), array( '%s' ) );
	}

	/**
	 * Housekeeping: delete expired tokens, then delete orphan client rows (no
	 * tokens and older than the grace window). Runs on the daily cron and
	 * opportunistically; idempotent.
	 */
	public static function gc(): void {
		global $wpdb;
		$now     = time();
		$clients = self::clients_table();
		$tokens  = self::tokens_table();

		// 1) Expired access/refresh tokens.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$tokens} WHERE expires_at < %d", $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// 2) Orphan clients — repeat DCR (or an abandoned registration retry)
		// leaves token-less rows; drop those older than the grace window.
		//
		// CRITICAL: only rows that NEVER completed an authorization
		// (authorized_at = 0). "Has no tokens right now" is NOT the same as
		// "abandoned": a real, connected client whose tokens simply lapsed (a
		// refresh token expiring after 30 days idle, or any event that cleared
		// its tokens) also has zero tokens. Deleting its registration turned a
		// recoverable "please sign in again" into a permanent "Invalid client"
		// — the MCP client still has the client_id cached, so it re-opened the
		// authorize page on a loop and could never reconnect. An authorized
		// client is now kept forever; only the admin (Connected apps) or the
		// gateway teardown removes it.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE c FROM {$clients} c
				 LEFT JOIN {$tokens} t ON t.client_id = c.client_id
				 WHERE t.id IS NULL AND c.authorized_at = 0 AND c.created_at < %d",
				$now - self::ORPHAN_CLIENT_GRACE
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Stamp a client as having completed an authorization, so gc() never prunes
	 * it. Idempotent + cheap: only writes the first time (authorized_at = 0).
	 *
	 * @param string $client_id Client id.
	 */
	/**
	 * Upgrade backfill (DB v3): every client that currently holds a token has
	 * demonstrably completed an authorization, so stamp it before the next gc()
	 * runs. Without this, an existing connection whose tokens lapse right after
	 * the upgrade would still be purged once. Clients with no tokens keep
	 * authorized_at = 0 and stay prunable, which is the intended behaviour for
	 * genuinely abandoned DCR registrations.
	 */
	public static function backfill_authorized_clients(): void {
		global $wpdb;
		$clients = self::clients_table();
		$tokens  = self::tokens_table();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$clients} c
				 SET c.authorized_at = %d
				 WHERE c.authorized_at = 0
				   AND EXISTS ( SELECT 1 FROM {$tokens} t WHERE t.client_id = c.client_id )",
				time()
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Upgrade backfill (DB v4): this authorization server has historically
	 * exposed one MCP resource, so every existing token can be safely bound to
	 * that canonical audience without disconnecting established clients.
	 */
	public static function backfill_token_resources(): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::tokens_table() . ' SET resource = %s WHERE resource = %s OR resource IS NULL',
				EMCP_Tools_OAuth_Metadata::resource(),
				''
			)
		);
	}

	public static function mark_client_authorized( string $client_id ): void {
		if ( '' === $client_id ) {
			return;
		}
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::clients_table() . ' SET authorized_at = %d WHERE client_id = %s AND authorized_at = 0',
				time(),
				$client_id
			)
		);
	}

	/**
	 * Run gc() at most once per interval (transient-guarded), so it can be called
	 * cheaply from a hot path — bearer validation on every MCP request — without a
	 * DELETE per request. This is the "clean at validation time" path: any site
	 * actively serving MCP traffic sweeps expired tokens/orphans within the
	 * interval, independent of WP-Cron reliability. Returns whether it swept.
	 *
	 * @param int $interval Minimum seconds between sweeps.
	 * @return bool
	 */
	public static function gc_throttled( int $interval = self::GC_THROTTLE_INTERVAL ): bool {
		if ( get_transient( self::GC_THROTTLE_OPTION ) ) {
			return false;
		}
		set_transient( self::GC_THROTTLE_OPTION, 1, max( 60, $interval ) );
		self::gc();
		return true;
	}

	/**
	 * Drops the OAuth tables and clears the version/throttle options. Called
	 * from the plugin's uninstall handler, registered clients and issued
	 * tokens are live credentials and must not survive uninstall.
	 *
	 * @since 3.19.1
	 */
	public static function uninstall_cleanup(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::clients_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::tokens_table() );
		delete_option( self::DB_VERSION_OPTION );
		delete_transient( self::GC_THROTTLE_OPTION );
	}
}
