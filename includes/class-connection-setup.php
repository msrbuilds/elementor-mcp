<?php
/**
 * The Connection screen's setup record (spec 9.5): one per user, 30 minutes,
 * bound to the client and sign-in method being set up. Part 1b owns the
 * record and its tags; Part 2 builds the wizard and the first-call check.
 *
 * The one-time token is what a stdio config carries as EMCP_SETUP. Only its
 * sha256 is stored in the lookup index, so the index never holds the secret.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-user Connection setup record.
 */
final class EMCP_Tools_Connection_Setup {

	const META  = 'emcp_tools_connection_setup';
	const INDEX = 'emcp_tools_connection_setup_tokens';
	const TTL   = 1800;

	/**
	 * Open (or replace) the user's setup record.
	 *
	 * @param int      $user_id User.
	 * @param string   $client  Client key chosen in step 1.
	 * @param string   $method  Sign-in method chosen in step 2 (app, oauth, cli).
	 * @param string   $expect  Expected credential tag when known (app:{uuid}).
	 * @param int|null $now     Timestamp (tests).
	 */
	public static function open( int $user_id, string $client, string $method, string $expect = '', ?int $now = null ): array {
		$now = $now ?? time();
		self::close( $user_id );
		$record = array(
			'setup_id' => 'set_' . bin2hex( random_bytes( 8 ) ),
			'client'   => sanitize_key( $client ),
			'method'   => sanitize_key( $method ),
			'expect'   => substr( $expect, 0, 100 ),
			'token'    => bin2hex( random_bytes( 16 ) ),
			'since'    => $now,
			'expires'  => $now + self::TTL,
			'tags'     => array(),
		);
		update_user_meta( $user_id, self::META, $record );

		$index = self::index();
		foreach ( $index as $hash => $entry ) {
			if ( (int) ( $entry['expires'] ?? 0 ) < $now ) {
				unset( $index[ $hash ] );
			}
		}
		$index[ self::hash( $record['token'] ) ] = array(
			'user_id' => $user_id,
			'expires' => $record['expires'],
		);
		update_option( self::INDEX, $index, false );
		return $record;
	}

	/**
	 * The user's open record, or null.
	 *
	 * @param int      $user_id User.
	 * @param int|null $now     Timestamp (tests).
	 */
	public static function get( int $user_id, ?int $now = null ): ?array {
		// A long-running stdio process may have cached this user's meta before
		// the setup was opened in the browser.
		wp_cache_delete( $user_id, 'user_meta' );
		$record = get_user_meta( $user_id, self::META, true );
		if ( ! is_array( $record ) || empty( $record['setup_id'] ) ) {
			return null;
		}
		return (int) ( $record['expires'] ?? 0 ) >= ( $now ?? time() ) ? $record : null;
	}

	/**
	 * Extend an open record by TTL (the Connection screen renews while it polls).
	 *
	 * @param int      $user_id User.
	 * @param int|null $now     Timestamp (tests).
	 */
	public static function renew( int $user_id, ?int $now = null ): ?array {
		$now    = $now ?? time();
		$record = self::get( $user_id, $now );
		if ( null === $record ) {
			return null;
		}
		// Only rewrite once half the TTL is gone: every write replaces the whole
		// record, and a poll that rewrote it on each tick could drop an OAuth
		// consent tag written between its read and its write.
		if ( (int) $record['expires'] - $now > self::TTL / 2 ) {
			return $record;
		}
		$record['expires'] = $now + self::TTL;
		update_user_meta( $user_id, self::META, $record );
		$index = self::index();
		$hash  = self::hash( $record['token'] );
		if ( isset( $index[ $hash ] ) ) {
			$index[ $hash ]['expires'] = $record['expires'];
			update_option( self::INDEX, $index, false );
		}
		return $record;
	}

	/**
	 * Set what the open record waits for (app:{uuid} once the password exists,
	 * oauth:{client_id} when reconnecting an app). The token does not change.
	 *
	 * @param int    $user_id User.
	 * @param string $expect  Expected credential tag.
	 */
	public static function set_expect( int $user_id, string $expect ): ?array {
		$record = self::get( $user_id );
		if ( null === $record ) {
			return null;
		}
		$record['expect'] = substr( $expect, 0, 100 );
		update_user_meta( $user_id, self::META, $record );
		return $record;
	}

	/**
	 * Close the user's record; its token stops working.
	 *
	 * @param int $user_id User.
	 */
	public static function close( int $user_id ): void {
		$record = get_user_meta( $user_id, self::META, true );
		if ( is_array( $record ) && ! empty( $record['token'] ) ) {
			$index = self::index();
			unset( $index[ self::hash( (string) $record['token'] ) ] );
			update_option( self::INDEX, $index, false );
		}
		delete_user_meta( $user_id, self::META );
	}

	/**
	 * The open record a token belongs to.
	 *
	 * @param string   $token Token from EMCP_SETUP.
	 * @param int|null $now   Timestamp (tests).
	 * @return array{user_id:int, record:array}|null
	 */
	public static function find_by_token( string $token, ?int $now = null ): ?array {
		if ( '' === $token ) {
			return null;
		}
		$entry = self::index()[ self::hash( $token ) ] ?? null;
		if ( ! is_array( $entry ) ) {
			return null;
		}
		$user_id = (int) $entry['user_id'];
		$record  = self::get( $user_id, $now );
		if ( null === $record || ! hash_equals( (string) $record['token'], $token ) ) {
			return null;
		}
		return array(
			'user_id' => $user_id,
			'record'  => $record,
		);
	}

	/**
	 * Tag the user's open record (oauth_client, cli_session).
	 *
	 * @param int    $user_id User.
	 * @param string $key     Tag.
	 * @param string $value   Value.
	 */
	public static function tag( int $user_id, string $key, string $value ): void {
		$record = self::get( $user_id );
		if ( null === $record ) {
			return;
		}
		$record['tags'][ sanitize_key( $key ) ] = substr( $value, 0, 100 );
		update_user_meta( $user_id, self::META, $record );
	}

	/**
	 * Called when the user approves an OAuth consent: an app authorized while
	 * the user's setup is open belongs to that setup.
	 *
	 * @param int    $user_id   Approving user.
	 * @param string $client_id EMCP OAuth client id.
	 */
	public static function tag_oauth_consent( int $user_id, string $client_id ): void {
		self::tag( $user_id, 'oauth_client', $client_id );
	}

	/**
	 * Token hash => owner.
	 *
	 * @return array<string, array{user_id:int, expires:int}>
	 */
	private static function index(): array {
		wp_cache_delete( self::INDEX, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		$index = get_option( self::INDEX, array() );
		return is_array( $index ) ? $index : array();
	}

	/**
	 * Hash a token for the index.
	 *
	 * @param string $token Token.
	 */
	private static function hash( string $token ): string {
		return hash( 'sha256', $token );
	}
}
