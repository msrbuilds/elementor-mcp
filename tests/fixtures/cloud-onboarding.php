<?php
define( 'ABSPATH', __DIR__ );
$options = array(); $transients = array(); $calls = array(); $connected = false;
$allowed = true; $multi = false; $user = 7; $base = 'https://site.test/sub'; $conflict = false;
$status = array( 'version' => 1, 'workspace_id' => 'workspace-1', 'cloud_bound' => true, 'site_uuid' => 'site-1', 'origin_url' => 'https://site.test/sub',
	'gateway_allowed' => true, 'gateway_uploaded' => false, 'health' => array( 'status' => 'available' ) );
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][$key] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][$key] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][$key] ); }
function current_user_can( $cap ) { return $GLOBALS['allowed']; }
function get_current_user_id() { return $GLOBALS['user']; }
function is_multisite() { return $GLOBALS['multi']; }
function wp_parse_url( $url, $part ) { return parse_url( $url, $part ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function get_bloginfo( $key ) { return 'Site'; }
function home_url() { return $GLOBALS['base']; }
function admin_url( $path ) { return 'https://site.test/sub/wp-admin/' . $path; }
function __( $text, $domain ) { return $text; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function sanitize_text_field( $v ) { return $v; }
function wp_unslash( $v ) { return $v; }
function wp_safe_redirect( $url ) { throw new RuntimeException( $url ); }
class WP_Error { public function __construct( ...$args ) {} }
class EMCP_Tools_Cloud {
	const OPTION_SITE_UUID = 'uuid'; const SCOPES = 'openid email cloud offline_access';
	public static function installation_base() { return $GLOBALS['base']; }
	public static function base_url() { return 'https://cloud.test'; }
	public static function identity_conflict() { return $GLOBALS['conflict']; }
	public static function is_connected() { return $GLOBALS['connected']; }
	public static function site_uuid() { if ( ! isset( $GLOBALS['options']['uuid'] ) ) { $GLOBALS['options']['uuid'] = 'site-1'; } return $GLOBALS['options']['uuid']; }
}
class EMCP_Tools_Gateway_Credential {
	const OPTION_FLAG = 'gateway';
	public static function provision( $user ) { $GLOBALS['calls'][] = 'provision'; $GLOBALS['status']['gateway_uploaded'] = true; return true; }
}
class EMCP_Tools_Cloud_Client {
	public static function get( $path ) { $GLOBALS['calls'][] = $path; return $GLOBALS['status']; }
}
class EMCP_Tools_Cloud_Http {
	public static function request( ...$args ) { $GLOBALS['calls'][] = 'capability'; return array( 'code' => 200, 'json' => array( 'version' => 1, 'expected_workspace' => true ) ); }
	public static function post_json( ...$args ) { $GLOBALS['calls'][] = 'register'; return array( 'code' => 201, 'json' => array( 'client_id' => 'client-1', 'emcp_site_registration_proof' => 'proof' ) ); }
}
class FakeDb {
	public $prefix = 'wp_'; public $busy = false;
	public function prepare( $sql, ...$args ) { return $sql; }
	public function get_var( $sql ) { return $this->busy ? '0' : '1'; }
}
$wpdb = new FakeDb();
require dirname( __DIR__, 2 ) . '/includes/oauth/class-oauth-util.php';
require dirname( __DIR__, 2 ) . '/includes/cloud/class-cloud-connect.php';
require dirname( __DIR__, 2 ) . '/includes/cloud/class-cloud-onboarding.php';
function check( $ok, $why ) { if ( ! $ok ) { throw new RuntimeException( $why ); } }
function run_onboard( $phase, $gateway = false ) { return EMCP_Tools_Cloud_Onboarding::run( $phase, 'workspace-1', $gateway ); }
$r = run_onboard( 'preflight' );
check( 'ready' === $r['state'] && empty( $options ) && empty( $calls ), 'Preflight has no writes or network' );
$allowed = false; check( 'administrator_required' === run_onboard( 'prepare' )['reason'], 'Admin required' ); $allowed = true;
$multi = true; check( 'multisite_not_supported' === run_onboard( 'prepare' )['reason'], 'Multisite refused' ); $multi = false;
$conflict = true; check( 'site_identity_conflict' === run_onboard( 'prepare' )['reason'], 'Clone refused' ); $conflict = false;
$wpdb->busy = true; check( 'onboarding_busy' === run_onboard( 'prepare' )['reason'], 'Concurrent command refused' ); $wpdb->busy = false;
check( empty( $calls ), 'Guards precede registration' );
$r = run_onboard( 'prepare' ); $p = $transients[EMCP_Tools_Cloud_Connect::PENDING_TRANSIENT];
check( 'awaiting_approval' === $r['state'], 'Preparation requires approval' );
check( false === $p['gateway'] && 7 === $p['onboarding_user'], 'Callback cannot auto-provision' );
check( ! str_contains( json_encode( $r ), $p['verifier'] ), 'Never output PKCE secret' );
parse_str( parse_url( $r['authorization_url'], PHP_URL_QUERY ), $query );
$state = json_decode( EMCP_Tools_OAuth_Util::base64url_decode( $query['state'] ), true );
check( 'workspace-1' === $state['expected_workspace'], 'Pin workspace in approval' );
check( $r['authorization_url'] === run_onboard( 'prepare' )['authorization_url'], 'Resume same approval' );
check( 1 === count( array_filter( $calls, fn( $v ) => 'register' === $v ) ), 'No duplicate registration' );
$user = 8; check( 'another_approval_pending' === run_onboard( 'prepare' )['reason'], 'No other admin takeover' );
try { EMCP_Tools_Cloud_Connect::handle_callback(); } catch ( RuntimeException $e ) { check( str_contains( $e->getMessage(), 'cloud_error=state' ), 'Reject callback from other admin' ); }
check( $p === get_transient( EMCP_Tools_Cloud_Connect::PENDING_TRANSIENT ), 'Other admin cannot consume pending request' ); $user = 7;
$_GET = array( 'code' => 'wrong-code', 'state' => EMCP_Tools_OAuth_Util::base64url_encode( json_encode( array( 'csrf' => 'wrong-state' ) ) ) );
try { EMCP_Tools_Cloud_Connect::handle_callback(); } catch ( RuntimeException $e ) { check( str_contains( $e->getMessage(), 'cloud_error=state' ), 'Reject wrong state' ); }
check( $p === get_transient( EMCP_Tools_Cloud_Connect::PENDING_TRANSIENT ), 'Invalid callback preserves approval' );
$transients[EMCP_Tools_Cloud_Connect::PENDING_TRANSIENT]['onboarding_expires'] = time() - 1;
try { EMCP_Tools_Cloud_Connect::handle_callback(); } catch ( RuntimeException $e ) { check( str_contains( $e->getMessage(), 'cloud_error=state' ), 'Reject expired callback' ); }
$transients = array();
check( 'awaiting_approval' === run_onboard( 'resume' )['state'], 'No implicit connect on resume' );
$connected = true; $calls = array();
$status['origin_url'] = 'https://another-site.test'; check( 'site_binding_mismatch' === run_onboard( 'resume', true )['reason'], 'Legacy copied identity cannot pass as the source' ); $status['origin_url'] = 'https://site.test/sub';
check( 'connected' === run_onboard( 'prepare' )['state'] && ! in_array( 'register', $calls, true ), 'Preserve existing binding' );
$status['workspace_id'] = 'wrong'; check( 'workspace_mismatch' === run_onboard( 'resume', true )['reason'], 'Wrong workspace refused before provisioning' );
check( ! in_array( 'provision', $calls, true ), 'No writes on workspace mismatch' ); $status['workspace_id'] = 'workspace-1';
check( 'gateway_consent_required' === run_onboard( 'resume' )['reason'], 'Explicit Gateway consent' );
$status['gateway_allowed'] = false; check( 'gateway_requires_paid' === run_onboard( 'resume', true )['reason'], 'Fresh paid entitlement needed' ); $status['gateway_allowed'] = true;
check( 'complete' === run_onboard( 'resume', true )['state'], 'Upload and probe succeed' );
check( 'complete' === run_onboard( 'resume', true )['state'], 'Repeat is safe' );
check( 1 === count( array_filter( $calls, fn( $v ) => 'provision' === $v ) ), 'No repeated Gateway credential issuance' );
$status['health']['status'] = 'unavailable'; check( 'retry' === run_onboard( 'resume', true )['state'], 'Failed health is not completion' );
$status = new WP_Error(); check( 'cloud_status_unavailable' === run_onboard( 'resume', true )['reason'], 'Network failure preserves state' );
echo "PASS\n";
