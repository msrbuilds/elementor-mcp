<?php
define( 'ABSPATH', __DIR__ );
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {}
class EMCP_Tools_Cloud {
	public static $connection = array();
	public static function get_connection() { return self::$connection; }
	public static function save_connection( $value ) { self::$connection = $value; }
	public static function base_url() { return 'https://cloud.test'; }
}
require dirname( __DIR__, 2 ) . '/includes/cloud/class-cloud-http.php';
require dirname( __DIR__, 2 ) . '/includes/cloud/class-cloud-connect.php';
function check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function seed() { EMCP_Tools_Cloud::$connection = array( 'client_id' => 'client', 'refresh_token' => 'original', 'access_token' => 'expired', 'access_expires_at' => 1 ); }

foreach ( array( 408, 429, 500, 502, 503, 504, 0 ) as $status ) {
	seed();
	$calls = 0;
	EMCP_Tools_Cloud_Http::set_transport( function () use ( $status, &$calls ) {
		++$calls;
		return $status ? array( 'code' => $status, 'json' => array(), 'retry_after' => '45' ) : new WP_Error();
	} );
	check( ! EMCP_Tools_Cloud_Connect::refresh(), 'Temporary failure must defer' );
	$c = EMCP_Tools_Cloud::get_connection();
	check( ! isset( $c['unhealthy'] ) && 'original' === $c['refresh_token'], 'Temporary failure must preserve connection' );
	check( $c['refresh_retry_at'] > time(), 'Cooldown must persist' );
	for ( $i = 0; $i < 100; ++$i ) { check( ! EMCP_Tools_Cloud_Connect::refresh(), 'Cooldown must defer repeats' ); }
	check( 1 === $calls, 'Only one HTTP request during cooldown' );
	$c['refresh_retry_at'] = time() - 1;
	EMCP_Tools_Cloud::save_connection( $c );
	EMCP_Tools_Cloud_Http::set_transport( function ( $url, $args ) {
		check( 'original' === $args['body']['refresh_token'], 'Retry must use original credential' );
		return array( 'code' => 200, 'json' => array( 'access_token' => 'new', 'refresh_token' => 'rotated', 'expires_in' => 3600 ) );
	} );
	check( EMCP_Tools_Cloud_Connect::refresh(), 'Retry should recover' );
	check( ! isset( EMCP_Tools_Cloud::$connection['refresh_retry_at'] ), 'Recovery must clear cooldown' );
}
foreach ( array( 'garbage', '0', '-1', 'tomorrow', '', '9999999999', gmdate( 'D, d M Y H:i:s \G\M\T', time() + 120 ) ) as $retry ) {
	seed();
	EMCP_Tools_Cloud_Http::set_transport( function () use ( $retry ) { return array( 'code' => 429, 'json' => array(), 'retry_after' => $retry ); } );
	EMCP_Tools_Cloud_Connect::refresh();
	$delay = EMCP_Tools_Cloud::$connection['refresh_retry_at'] - time();
	check( $delay > 0 && $delay <= 3600, 'Retry-After must be bounded' );
	if ( in_array( $retry, array( 'garbage', '0', '-1', 'tomorrow', '' ), true ) ) {
		check( $delay >= 29 && $delay <= 30, 'Invalid headers must use the fallback' );
	}
}
seed();
EMCP_Tools_Cloud_Http::set_transport( function () { return array( 'code' => 400, 'json' => array( 'error' => 'invalid_grant' ) ); } );
check( ! EMCP_Tools_Cloud_Connect::refresh() && EMCP_Tools_Cloud::$connection['unhealthy'], 'Invalid grant still requires reconnect' );
seed();
EMCP_Tools_Cloud_Http::set_transport( function () {
	EMCP_Tools_Cloud::save_connection( array( 'client_id' => 'replacement', 'refresh_token' => 'replacement', 'access_token' => 'new', 'access_expires_at' => time() + 3600 ) );
	return array( 'code' => 429, 'json' => array() );
} );
EMCP_Tools_Cloud_Connect::refresh();
check( ! isset( EMCP_Tools_Cloud::$connection['refresh_retry_at'] ), 'Old failure must not poison reconnect' );
echo "PASS\n";
