<?php
// Isolated transport harness: no WordPress bootstrap/test doubles from other suites.
define( 'ABSPATH', __DIR__ ); define( 'EMCP_TOOLS_DIR', dirname( __DIR__, 2 ) . '/' ); define( 'WP_CLI', true );
set_error_handler( static function( $severity, $message ) { throw new RuntimeException( $message ); } );
class WP_Error {
	public function __construct( public $code, public $message, public $data = array() ) {}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class TestDenied extends RuntimeException { public function __construct( public $status ) { parent::__construct( 'denied' ); } }
$accounts = array( 1 => true, 2 => true, 3 => false ); $actor = 1; $nonce = true; $hooks = array(); $writes = array();
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value ) { return json_encode( $value, JSON_THROW_ON_ERROR ); }
function get_userdata( $id ) { global $accounts; return array_key_exists( $id, $accounts ) ? (object) array( 'ID' => $id ) : false; }
function user_can( $id, $cap ) { global $accounts; return !empty( $accounts[$id] ) && 'manage_options' === $cap; }
function get_current_user_id() { global $actor; return $actor; }
function current_user_can( $cap ) {
	global $actor;
	if ( 'manage_emcp_tools' === $cap ) {
		$caps = EMCP_Tools_Management_Access::map_capability( array(), $cap, $actor, array() );
		return !in_array( 'do_not_allow', $caps, true ) && user_can( $actor, 'manage_options' );
	}
	return user_can( $actor, $cap );
}
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return stripslashes( $value ); }
function wp_die( $text, $title = '', $args = array() ) { throw new TestDenied( $args['response'] ?? 500 ); }
function check_admin_referer() { global $nonce; if (!$nonce) { throw new TestDenied( 403 ); } }
function apply_filters( $name, $value ) { return array_merge( $value, array( 'emcp_tools_management_policy' ) ); }
function update_option( $key, $value ) { global $writes; $writes[$key] = $value; }
function get_option( $key, $default = false ) { return $key === 'emcp_tools_management_policy' ? 'secret-policy' : $default; }
require EMCP_TOOLS_DIR . 'includes/class-lease.php';
require EMCP_TOOLS_DIR . 'includes/class-management-policy.php';
require EMCP_TOOLS_DIR . 'includes/class-management-access.php';
require EMCP_TOOLS_DIR . 'includes/admin/class-management-access-admin.php';
require EMCP_TOOLS_DIR . 'includes/admin/rest/class-admin-rest-controller.php';
class MemoryPolicyStore implements EMCP_Tools_Lease_Store {
	public ?string $raw = null; public bool $fail = false; public bool $fault = false;
	public function get( string $key ): ?string { if ($this->fault) { throw new RuntimeException('private database credentials'); } return $this->raw; }
	public function insert( string $key, string $value ): bool { if ($this->fail || $this->raw !== null) { return false; } $this->raw=$value; return true; }
	public function swap( string $key, string $old, string $new ): bool { if ($this->fail || $this->raw !== $old) { return false; } $this->raw=$new; return true; }
	public function remove( string $key, string $old ): bool { return false; }
}
$store = new MemoryPolicyStore(); $seam = new ReflectionProperty( EMCP_Tools_Management_Policy::class, 'store' ); $seam->setValue(null, $store);
function check( $condition ) { if (!$condition) { throw new RuntimeException('Assertion failed at ' . (debug_backtrace()[0]['line'] ?? 0)); } }
function code( $result, $expected ) { check(is_wp_error($result) && $result->code === $expected); }
function save( $mode='allowlist', $users=array(1), $version=0, $actor=1, $recovery=false ) { return EMCP_Tools_Management_Policy::save($mode,$users,$version,$actor,$recovery); }
function denied( callable $fn, $status=403 ) { try { $fn(); } catch (TestDenied $error) { check($error->status===$status); return; } throw new RuntimeException('Expected denied'); }
function request( $route ) { return new class($route) { public function __construct(private $route){} public function get_route(){return $this->route;} }; }
switch ($argv[1]) {
	case 'default':
		check(EMCP_Tools_Management_Access::can_manage()); check(current_user_can('manage_emcp_tools'));
		$actor=2;check(EMCP_Tools_Management_Access::can_manage());$actor=3;check(!EMCP_Tools_Management_Access::can_manage());
		check(EMCP_Tools_Management_Access::map_capability(array('edit_posts'),'edit_posts',3,array())===array('edit_posts')); break;
	case 'membership':
		check(save()['version']===1);$actor=2;check(!EMCP_Tools_Management_Access::can_manage());check(!current_user_can('manage_emcp_tools'));
		check(current_user_can('manage_options')); // Execution capabilities were not replaced.
		$actor=1;$accounts[1]=false;check(!EMCP_Tools_Management_Access::can_manage());unset($accounts[1]);check(!EMCP_Tools_Management_Access::can_manage()); break;
	case 'validation':
		foreach(array('','0','1,','1e1','-1','01','1, x','99999999999999999999999999') as $ids){code(EMCP_Tools_Management_Policy::parse_users($ids),'emcp_management_invalid_user');}
		check(EMCP_Tools_Management_Policy::parse_users('1, 2')===array(1,2));
		code(save('allowlist',array(2)),'emcp_management_lockout');code(save('allowlist',array(1,3)),'emcp_management_invalid_user');
		code(save('allowlist',array(1,999)),'emcp_management_invalid_user');code(save('all_admins',array(1)),'emcp_management_lockout');
		code(save('bad'),'emcp_management_invalid_policy');code(save('allowlist',array('1')),'emcp_management_invalid_user');
		code(save('allowlist',array(1),0,3),'emcp_management_forbidden');check($store->raw===null);break;
	case 'concurrency':
		check(save('allowlist',array(2,1,1))['users']===array(1,2));
		code(save('all_admins',array(),0),'emcp_management_policy_changed');check(save('allowlist',array(1),1)['version']===2);
		code(save('all_admins',array(),2,2),'emcp_management_forbidden');$original=$store->raw;$store->fail=true;
		code(save('all_admins',array(),2),'emcp_management_policy_changed');check($store->raw===$original); break;
	case 'malformed':
		foreach(array('', '{}','null','{"version":1,"mode":"allowlist","users":[]}','{"version":1,"mode":"all_admins","users":[1]}','{"version":1,"mode":"allowlist","users":[1,1]}','{"version":1,"mode":"allowlist","users":["1"]}','{"version":0,"mode":"all_admins","users":[]}') as $raw){$store->raw=$raw;check(!EMCP_Tools_Management_Access::can_manage());code(save(),'emcp_management_policy_unavailable');}break;
	case 'storage':
		$store->fault=true;check(!EMCP_Tools_Management_Access::can_manage());$error=save();code($error,'emcp_management_policy_unavailable');check(!str_contains($error->message,'credentials'));
		// A database failure that returns null must not be treated as an absent policy.
		$wpdb=new class {public $options='wp_options';public $last_error='private database credentials';public function prepare($q,...$args){return $q;}public function get_var($q){return null;}};
		$seam->setValue(null,null);check(!EMCP_Tools_Management_Access::can_manage());break;
	case 'admin-requests':
		save();$actor=2;
		foreach(array(array('page'=>'emcp-tools'),array('page'=>'emcp-tools-connection'),array('page'=>'emcp-themer-php'),array('action'=>'emcp_tools_cloud_gateway_reissue'),array('action'=>'emcp_tools_migrate_start_backup'),array('action'=>'emcp_themer_object_search'),array('action'=>'emcp_backup_chunk'),array('action'=>'emcp_restore_chunk')) as $params){$_GET=$params;$_POST=array();$_REQUEST=$params;denied(fn()=>EMCP_Tools_Management_Access::guard_admin_request());}
		$_GET=array('action'=>'unrelated');$_POST=array('action'=>'unrelated');$_REQUEST=array('action'=>'emcp_tools_settings_pull');denied(fn()=>EMCP_Tools_Management_Access::guard_admin_request());
		$_GET=array('action'=>'emcp_tools_settings_push');$_POST=array('action'=>'emcp_tools_migrate_restore_chunk_token');$_REQUEST=$_POST;denied(fn()=>EMCP_Tools_Management_Access::guard_admin_request());
		$_GET=$_POST=$_REQUEST=array('action'=>'emcp_tools_migrate_restore_chunk_token');EMCP_Tools_Management_Access::guard_admin_request();
		$_GET=$_POST=$_REQUEST=array('page'=>'edit');EMCP_Tools_Management_Access::guard_admin_request();break;
	case 'rest':
		save();$actor=2;
		foreach(array('/emcp-tools/v1/admin','/emcp-tools/v1/admin/tools','/EMCP-TOOLS/v1/ADMIN/connection/advanced') as $route){code(EMCP_Tools_Management_Access::guard_admin_rest(true,null,request($route)),'emcp_management_forbidden');}
		foreach(array('/emcp-tools/v1/mcp','/emcp-tools/v1/oauth/token','/other/v1/admin','/emcp-tools/v1/admin-other') as $route){check(EMCP_Tools_Management_Access::guard_admin_rest('kept',null,request($route))==='kept');}
		$GLOBALS['wp_rest_auth_cookie']=true;code(EMCP_Tools_Admin_REST_Controller::check(request('/'),'manage_options'),'emcp_management_forbidden');
		$actor=1;check(EMCP_Tools_Admin_REST_Controller::check(request('/'),'manage_options')===true);$GLOBALS['wp_rest_auth_cookie']='malformed';code(EMCP_Tools_Admin_REST_Controller::check(request('/'),'manage_options'),'emcp_admin_cookie_required');break;
	case 'recovery':
		save();code(save('all_admins',array(),1,2),'emcp_management_forbidden');check(save('all_admins',array(),1,2,true)['version']===2);
		$store->raw='corrupt';check(save('all_admins',array(),0,2,true)['version']===1);code(save('all_admins',array(),1,3,true),'emcp_management_forbidden');break;
	case 'http-recovery':
		save();define('REST_REQUEST',true);code(save('all_admins',array(),1,2,true),'emcp_management_forbidden');break;
	case 'form':
		$_POST=array('mode'=>'allowlist','version'=>'0','users'=>'1','confirm'=>'1');$nonce=false;denied(fn()=>EMCP_Tools_Management_Access_Admin::save());check($store->raw===null);
		$nonce=true;$_POST['confirm']='';denied(fn()=>EMCP_Tools_Management_Access_Admin::save(),400);check($store->raw===null);
		save();$_POST['confirm']='1';denied(fn()=>EMCP_Tools_Management_Access_Admin::save(),409);$actor=2;denied(fn()=>EMCP_Tools_Management_Access_Admin::save());break;
	case 'sync':
		require EMCP_TOOLS_DIR.'includes/cloud/class-settings-sync.php';
		check(!in_array('emcp_tools_management_policy',EMCP_Tools_Settings_Sync::sync_keys(),true));check(!isset(EMCP_Tools_Settings_Sync::collect()['emcp_tools_management_policy']));
		check(EMCP_Tools_Settings_Sync::apply(array('emcp_tools_management_policy'=>'overwrite'))===0);check(!$writes);break;
	default:throw new RuntimeException('Unknown scenario');
}
echo "PASS\n";
