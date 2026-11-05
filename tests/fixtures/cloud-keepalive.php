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
	return array_merge( array( 'client_id' => 'client', 'refresh_token' => 'original', 'access_token' => 'expired', 'access_expires_at' => 1 ), $extra );
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

// A temporary failure keeps the connection and the old time, so the next day retries.
$old = time() - 8 * $day;
EMCP_Tools_Cloud::$connection = bundle( array( 'refreshed_at' => $old ) );
EMCP_Tools_Cloud_Http::set_transport( function () { return array( 'code' => 503, 'json' => array() ); } );
check( ! EMCP_Tools_Cloud_Connect::keepalive(), 'A temporary failure is not a renewal' );
check( $old === EMCP_Tools_Cloud::$connection['refreshed_at'] && empty( EMCP_Tools_Cloud::$connection['unhealthy'] ), 'A temporary failure must change nothing but the cooldown' );
echo "PASS\n";
