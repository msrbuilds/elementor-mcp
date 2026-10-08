<?php
/** Isolated outbound boundary regression: never performs network requests. */
define( 'ABSPATH', __DIR__ );
define( 'EMCP_TOOLS_UNSPLASH_ACCESS_KEY', 'dummy-regression-key' );
define( 'EMCP_TOOLS_VERSION', 'test' );
class WP_Error { public function __construct( public $code, public $message ) {} }
function __( $s, $domain ) { return $s; }
function esc_url_raw( $s ) { return $s; }
function wp_parse_url( $s ) { return parse_url( $s ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function wp_remote_retrieve_body( $v ) { return $v['body']; }
function wp_remote_retrieve_response_code( $v ) { return $v['response']['code']; }
function sanitize_text_field( $s ) { return $s; }
function sanitize_key( $s ) { return strtolower( $s ); }
function absint( $v ) { return abs( (int) $v ); }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function get_bloginfo( $key ) { return 'test'; }
$GLOBALS['calls'] = array();
function wp_remote_get( $url, $args ) {
    $GLOBALS['calls'][] = array( $url, $args );
    return array( 'response' => array( 'code' => 200 ), 'body' => '{"url":"https://images.unsplash.com/test.jpg","results":[],"total":0,"total_pages":0}' );
}
require ( $argv[1] ?? dirname( __DIR__, 2 ) ) . '/includes/class-unsplash-client.php';
function verify( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); } }
$client = new EMCP_Tools_Unsplash_Client();
foreach ( array(
    'https://api.unsplash.com.attacker.example/photos/x/download',
    'https://api.unsplash.com@attacker.example/photos/x/download',
    'https://attacker.example/api.unsplash.com/photos/x/download',
    'http://api.unsplash.com/photos/x/download',
    'https://api.unsplash.com:444/photos/x/download',
    'https://user:password@api.unsplash.com/photos/x/download',
    '//api.unsplash.com/photos/x/download',
    'https://api.unsplash.com./photos/x/download',
    'https://api.unsplash.com\\@attacker.example/photos/x/download',
) as $url ) {
    verify( is_wp_error( $client::resolve_download( $url ) ), 'Rejected untrusted origin: ' . $url );
    $client->trigger_download( $url );
    verify( 0 === count( $GLOBALS['calls'] ), 'No credentialed HTTP for untrusted origins' );
}
foreach ( array( 'https://api.unsplash.com/photos/x/download', 'https://API.UNSPLASH.COM:443/photos/x/download' ) as $url ) {
    verify( 'https://images.unsplash.com/test.jpg' === $client::resolve_download( $url ), 'Valid resolution works' );
    $client->trigger_download( $url );
}
verify( ! is_wp_error( $client->search_images( array( 'q' => 'tree' ) ) ), 'Search still works' );
verify( 5 === count( $GLOBALS['calls'] ), 'Expected resolution, tracking and search calls' );
foreach ( $GLOBALS['calls'] as $call ) {
    verify( 0 === ( $call[1]['redirection'] ?? null ), 'Credentialed requests never follow redirects' );
    verify( 'Client-ID dummy-regression-key' === $call[1]['headers']['Authorization'], 'Provider key retained on trusted requests' );
}
echo "PASS\n";
