<?php
define( 'ABSPATH', __DIR__ );
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {}
class EMCP_Tools_Cloud {
	public static $connection = array();
	public static $bound = true;
	public static function get_connection() { return self::$connection; }
	public static function save_connection( $value ) { self::$connection = $value; }
	public static function base_url() { return 'https://cloud.test'; }
	public static function identity_bound() { return self::$bound; }
	public static function installation_base() { return 'https://site.test'; }
}
require dirname( __DIR__, 2 ) . '/includes/cloud/class-cloud-http.php';
require dirname( __DIR__, 2 ) . '/includes/cloud/class-cloud-connect.php';
function check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

$calls = 0;
EMCP_Tools_Cloud_Http::set_transport( function () use ( &$calls ) {
	++$calls;
	return array( 'code' => 200, 'json' => array( 'access_token' => 'new', 'refresh_token' => 'rotated', 'expires_in' => 3600 ) );
} );
function bundle( array $extra = array() ) {
	return array_merge( array( 'client_id' => 'client', 'refresh_token' => 'original', 'access_token' => 'expired', 'access_expires_at' => 1, 'installation_base' => 'https://site.test' ), $extra );
}
$day = 86400;

// An idle site that has not renewed for over a week renews, and records when.
EMCP_Tools_Cloud::$connection = bundle( array( 'refreshed_at' => time() - 8 * $day ) );
check( EMCP_Tools_Cloud_Connect::keepalive(), 'A week-old connection must renew' );
check( 1 === $calls && 'rotated' === EMCP_Tools_Cloud::$connection['refresh_token'], 'Renewal must rotate the stored token' );
check( EMCP_Tools_Cloud::$connection['refreshed_at'] >= time() - 5, 'Renewal must record the time' );

// A connection saved before this release has no time: it is due.
$calls = 0;
EMCP_Tools_Cloud::$connection = bundle();
check( EMCP_Tools_Cloud_Connect::keepalive() && 1 === $calls, 'A connection with no renewal time must renew' );

// A recent renewal rotates nothing: every rotation risks losing the response.
$calls = 0;
EMCP_Tools_Cloud::$connection = bundle( array( 'refreshed_at' => time() - 6 * $day ) );
check( ! EMCP_Tools_Cloud_Connect::keepalive() && 0 === $calls, 'A recent renewal must not rotate again' );

// A rejected connection stays as it is until a person reconnects.
EMCP_Tools_Cloud::$connection = bundle( array( 'unhealthy' => true ) );
check( ! EMCP_Tools_Cloud_Connect::keepalive() && 0 === $calls, 'An unhealthy connection must not be retried' );

// Disconnected, or a copied site whose get_connection() answers nothing.
EMCP_Tools_Cloud::$connection = array();
check( ! EMCP_Tools_Cloud_Connect::keepalive() && 0 === $calls, 'No connection means no request' );

// No identity marker: this could be a clone of a pre-3.19 connection, and a
// clone that renews by itself would burn the original site's token family.
EMCP_Tools_Cloud::$bound = false;
EMCP_Tools_Cloud::$connection = bundle();
check( ! EMCP_Tools_Cloud_Connect::keepalive() && 0 === $calls, 'No identity marker means no unattended renewal' );
EMCP_Tools_Cloud::$bound = true;

// A staging copy: search-replace rewrote the plain marker, not the encrypted bundle.
$calls = 0;
EMCP_Tools_Cloud::$connection = bundle( array( 'installation_base' => 'https://production.test' ) );
check( ! EMCP_Tools_Cloud_Connect::keepalive() && 0 === $calls, 'A bundle from another address must not renew' );
EMCP_Tools_Cloud::$connection = array_diff_key( bundle(), array( 'installation_base' => 1 ) );
check( ! EMCP_Tools_Cloud_Connect::keepalive() && 0 === $calls, 'A bundle with no address must not renew' );

// A temporary failure keeps the connection and the old time, so the next day retries.
$old     = time() - 8 * $day;
$paused  = array();
EMCP_Tools_Cloud_Connect::$pause = function ( $seconds ) use ( &$paused ) {
	$paused[] = $seconds;
	$c = EMCP_Tools_Cloud::get_connection();
	$c['refresh_retry_at'] = 0;
	EMCP_Tools_Cloud::save_connection( $c );
};
EMCP_Tools_Cloud::$connection = bundle( array( 'refreshed_at' => $old ) );
EMCP_Tools_Cloud_Http::set_transport( function () { return array( 'code' => 503, 'json' => array() ); } );
check( ! EMCP_Tools_Cloud_Connect::keepalive(), 'A temporary failure is not a renewal' );
check( $old === EMCP_Tools_Cloud::$connection['refreshed_at'] && empty( EMCP_Tools_Cloud::$connection['unhealthy'] ), 'A temporary failure must change nothing but the cooldown' );
check( 1 === count( $paused ) && $paused[0] <= 62, 'A failed tick retries once inside the replay window' );

// A lost response: the retry in the same tick gets the successor token.
$attempts = 0;
EMCP_Tools_Cloud_Http::set_transport( function () use ( &$attempts ) {
	return 1 === ++$attempts ? array( 'code' => 504, 'json' => array() ) : array( 'code' => 200, 'json' => array( 'access_token' => 'new', 'refresh_token' => 'successor', 'expires_in' => 3600 ) );
} );
EMCP_Tools_Cloud::$connection = bundle( array( 'refreshed_at' => $old ) );
check( EMCP_Tools_Cloud_Connect::keepalive() && 2 === $attempts, 'The retry must renew after a lost response' );
check( 'successor' === EMCP_Tools_Cloud::$connection['refresh_token'], 'The retry must store the successor token' );

// A firewall page never reached Cloud: the connection stays healthy.
foreach ( array( array( 'code' => 403, 'json' => array() ), array( 'code' => 200, 'json' => array() ) ) as $page ) {
	EMCP_Tools_Cloud::$connection = bundle( array( 'refreshed_at' => $old ) );
	EMCP_Tools_Cloud_Http::set_transport( function () use ( $page ) { return $page; } );
	check( ! EMCP_Tools_Cloud_Connect::keepalive(), 'A blocked request is not a renewal' );
	check( empty( EMCP_Tools_Cloud::$connection['unhealthy'] ), 'A blocked request must not mark the connection unhealthy' );
}

// A real OAuth rejection still does, and it is not retried.
$paused = array();
EMCP_Tools_Cloud::$connection = bundle( array( 'refreshed_at' => $old ) );
EMCP_Tools_Cloud_Http::set_transport( function () { return array( 'code' => 400, 'json' => array( 'error' => 'invalid_grant' ) ); } );
check( ! EMCP_Tools_Cloud_Connect::keepalive() && ! empty( EMCP_Tools_Cloud::$connection['unhealthy'] ) && ! $paused, 'An OAuth rejection needs a person' );
echo "PASS\n";
