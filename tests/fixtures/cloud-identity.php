<?php
define( 'ABSPATH', __DIR__ );
$options = array();
$base = 'https://source.test/site';
$local_revoked = false;
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); }
function home_url() { return $GLOBALS['base']; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_generate_uuid4() { return bin2hex( random_bytes( 16 ) ); }
function delete_transient( $key ) { unset( $GLOBALS['options'][ $key ] ); }
function apply_filters( $hook, $value ) { return $value; }
function current_user_can( $cap ) { return $GLOBALS['allowed'] ?? true; }
function check_admin_referer( $action ) { if ( ! ( $GLOBALS['nonce_valid'] ?? true ) ) { throw new RuntimeException( 'Invalid nonce' ); } }
function esc_html__( $text, $domain ) { return $text; }
function wp_die( $message, $title = '', $args = array() ) { throw new RuntimeException( $message ); }
class EMCP_Tools_OAuth_Store {
	public static function find_client_by_registration( $name, $uris ) { return array( 'client_id' => 'copied-gateway' ); }
	public static function revoke_client( $id ) { $GLOBALS['local_revoked'] = 'copied-gateway' === $id; }
	public static function acquire_client_registration_lock( $name, $uris ) { return true; }
	public static function release_client_registration_lock( $name, $uris ) {}
	public static function acquire_client_token_lock( $id ) { return true; }
	public static function release_client_token_lock( $id ) {}
	public static function revoke_client_locked( $id ) { $GLOBALS['local_revoked'] = 'copied-gateway' === $id; return 1; }
}
class EMCP_Tools_Cloud_Client {
	public static function delete_gateway_credential() { throw new RuntimeException( 'Must not revoke the source remotely' ); }
}
require dirname( __DIR__, 2 ) . '/includes/class-secret.php';
require dirname( __DIR__, 2 ) . '/includes/cloud/class-cloud.php';
require dirname( __DIR__, 2 ) . '/includes/cloud/class-cloud-connect.php';
require dirname( __DIR__, 2 ) . '/includes/cloud/class-gateway-credential.php';
function check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
$uuid = EMCP_Tools_Cloud::site_uuid();
EMCP_Tools_Cloud::save_connection( array( 'access_token' => 'source-token', 'refresh_token' => 'source-refresh' ) );
check( ! EMCP_Tools_Cloud::identity_conflict(), 'Source must remain connected' );
check( EMCP_Tools_Cloud::is_connected(), 'Source credentials must be readable' );
$source_options = $options;
foreach ( array( 'permission', 'nonce', 'method', 'confirmation' ) as $case ) {
	$GLOBALS['allowed'] = 'permission' !== $case;
	$GLOBALS['nonce_valid'] = 'nonce' !== $case;
	$_SERVER['REQUEST_METHOD'] = 'method' === $case ? 'GET' : 'POST';
	$_POST['confirm_separate'] = 'confirmation' === $case ? '0' : '1';
	$rejected = false;
	try { EMCP_Tools_Cloud_Connect::handle_separate(); } catch ( RuntimeException $error ) { $rejected = true; }
	check( $rejected, 'Invalid separation must be rejected: ' . $case );
	check( $options === $source_options, 'Rejected separation must not change identity' );
}
$base = 'https://source.test/clone';
check( EMCP_Tools_Cloud::identity_conflict(), 'A different subdirectory must be detected' );
check( array() === EMCP_Tools_Cloud::get_connection(), 'Clone must not expose copied credentials' );
check( ! EMCP_Tools_Cloud_Connect::refresh(), 'Clone must not refresh copied credentials' );
EMCP_Tools_Cloud::separate_identity();
check( $local_revoked, 'Copied local Gateway tokens must be revoked' );
check( $uuid !== EMCP_Tools_Cloud::site_uuid(), 'Clone needs a new identity' );
check( ! EMCP_Tools_Cloud::identity_conflict(), 'New identity must belong to clone' );
check( ! EMCP_Tools_Cloud::is_connected(), 'Clone must reconnect explicitly' );
$options = $source_options;
$base = 'https://source.test/site/';
check( EMCP_Tools_Cloud::is_connected(), 'Source copy must remain connected' );
check( $uuid === EMCP_Tools_Cloud::site_uuid(), 'Source identity must survive' );
echo "PASS\n";
