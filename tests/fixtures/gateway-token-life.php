<?php
// Drives the real token endpoint and the real Gateway credential class over an
// in-memory token table: only storage and the OAuth metadata are stand-ins.
define( 'ABSPATH', __DIR__ );
function apply_filters( $hook, $value ) { return $value; }
class WP_Error {
	private $code;
	public function __construct( $code = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_REST_Response {
	public $data;
	public $status;
	public function __construct( $data, $status = 200 ) { $this->data = $data; $this->status = $status; }
}
class EMCP_Tools_Cloud { public static function base_url() { return 'https://cloud.test'; } }
class EMCP_Tools_OAuth_Metadata {
	public static function resource() { return 'https://site.test/mcp'; }
	public static function resource_matches( $resource ) { return true; }
	public static function normalize_resource_uri( $resource ) { return $resource; }
}
class EMCP_Tools_OAuth_Store {
	public static $tokens = array();
	public static $gateway_client = 'gateway-client';
	public static $lock_free = true;
	public static $next = 1;
	public static function find_client_by_registration( $name, $uris ) {
		return '' === self::$gateway_client ? null : array( 'client_id' => self::$gateway_client );
	}
	public static function get_client( $client_id ) { return array( 'client_id' => $client_id ); }
	public static function acquire_client_token_lock( $client_id, $timeout = 5 ) { return self::$lock_free; }
	public static function release_client_token_lock( $client_id ) {}
	public static function issue_token( $type, $client_id, $user_id, $scopes, $ttl, $refresh_of = null, $resource = '' ) {
		$id = self::$next++;
		$token = 'token-' . $id;
		self::$tokens[ $token ] = array( 'id' => $id, 'token_type' => $type, 'client_id' => $client_id, 'user_id' => $user_id, 'scopes' => $scopes, 'resource' => $resource, 'expires_at' => time() + $ttl );
		return array( 'token' => $token, 'id' => $id );
	}
	public static function find_token( $token, $type ) {
		$row = self::$tokens[ $token ] ?? null;
		return ( $row && $type === $row['token_type'] && $row['expires_at'] > time() ) ? $row : null;
	}
	public static function rotate_out_refresh( $id, $grace = 0 ) {
		foreach ( self::$tokens as $key => $row ) {
			if ( $id === $row['id'] ) { self::$tokens[ $key ]['expires_at'] = min( $row['expires_at'], time() + $grace ); }
		}
	}
	public static function extend_refresh_tokens( $client_id, $scope, $expires_at, $floor ) {
		foreach ( self::$tokens as $key => $row ) {
			if ( 'refresh' === $row['token_type'] && $client_id === $row['client_id'] && $scope === $row['scopes'] && $row['expires_at'] > $floor ) {
				self::$tokens[ $key ]['expires_at'] = $expires_at;
			}
		}
		return true;
	}
}
require dirname( __DIR__, 2 ) . '/includes/cloud/class-gateway-credential.php';
require dirname( __DIR__, 2 ) . '/includes/oauth/class-oauth-token.php';
function check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
class Fake_Request {
	private $params;
	public function __construct( $params ) { $this->params = $params; }
	public function get_body_params() { return $this->params; }
}
function rotate( $token, $client ) {
	return EMCP_Tools_OAuth_Token::handle_token( new Fake_Request( array( 'grant_type' => 'refresh_token', 'refresh_token' => $token, 'client_id' => $client ) ) );
}
$year = 31536000;

// Every rotation of a Gateway token keeps the long life it was issued with.
$first = EMCP_Tools_OAuth_Store::issue_token( 'refresh', 'gateway-client', 1, 'gateway', EMCP_Tools_Gateway_Credential::REFRESH_TTL );
$res   = rotate( $first['token'], 'gateway-client' );
check( 200 === $res->status, 'Gateway rotation must succeed' );
$second = $res->data['refresh_token'];
check( EMCP_Tools_OAuth_Store::$tokens[ $second ]['expires_at'] > time() + 9 * $year, 'A rotated Gateway token must keep its long life' );
$third = rotate( $second, 'gateway-client' )->data['refresh_token'];
check( EMCP_Tools_OAuth_Store::$tokens[ $third ]['expires_at'] > time() + 9 * $year, 'A second rotation must keep it too' );
check( 'gateway' === EMCP_Tools_OAuth_Store::$tokens[ $third ]['scopes'], 'Rotation keeps the gateway scope' );

// The access token stays short.
$access = rotate( $third, 'gateway-client' )->data['access_token'];
check( EMCP_Tools_OAuth_Store::$tokens[ $access ]['expires_at'] < time() + 86400, 'The access token must stay short-lived' );

// Other clients keep 30 days, even one holding a scope named gateway.
foreach ( array( array( 'claude', 'mcp' ), array( 'claude', 'gateway' ), array( 'gateway-client', 'mcp' ) ) as $case ) {
	$token = EMCP_Tools_OAuth_Store::issue_token( 'refresh', $case[0], 1, $case[1], EMCP_Tools_OAuth_Token::REFRESH_TTL );
	$next  = rotate( $token['token'], $case[0] )->data['refresh_token'];
	check( EMCP_Tools_OAuth_Store::$tokens[ $next ]['expires_at'] <= time() + EMCP_Tools_OAuth_Token::REFRESH_TTL, 'Only the Gateway client with the gateway scope gets the long life: ' . implode( '/', $case ) );
}

// Tokens rotated before this fix are extended once, except retired ones.
EMCP_Tools_OAuth_Store::$tokens = array();
$live    = EMCP_Tools_OAuth_Store::issue_token( 'refresh', 'gateway-client', 1, 'gateway', 86400 );
$retired = EMCP_Tools_OAuth_Store::issue_token( 'refresh', 'gateway-client', 1, 'gateway', 60 );
$other   = EMCP_Tools_OAuth_Store::issue_token( 'refresh', 'claude', 1, 'gateway', 86400 );
check( true === EMCP_Tools_Gateway_Credential::extend_existing_tokens(), 'Extension must report success' );
check( EMCP_Tools_OAuth_Store::$tokens[ $live['token'] ]['expires_at'] > time() + 9 * $year, 'A live Gateway token must be extended' );
check( EMCP_Tools_OAuth_Store::$tokens[ $retired['token'] ]['expires_at'] <= time() + 60, 'A token in its rotation grace must stay retired' );
check( EMCP_Tools_OAuth_Store::$tokens[ $other['token'] ]['expires_at'] <= time() + 86400, 'Another client must be left alone' );

// A busy lock is retried later; no Gateway client means nothing to do.
EMCP_Tools_OAuth_Store::$lock_free = false;
check( false === EMCP_Tools_Gateway_Credential::extend_existing_tokens(), 'A busy lock must report not done' );
EMCP_Tools_OAuth_Store::$lock_free = true;
EMCP_Tools_OAuth_Store::$gateway_client = '';
check( true === EMCP_Tools_Gateway_Credential::extend_existing_tokens(), 'No Gateway client means done' );
echo "PASS\n";
