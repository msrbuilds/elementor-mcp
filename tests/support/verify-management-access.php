<?php
/** Run with host WP-CLI --user=1 on the owned local msrplugins.test fixture only. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'msrplugins.test' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) { exit(1); }
global $wpdb;
$actor = get_current_user_id();
if ( ! current_user_can( 'manage_options' ) ) { WP_CLI::error( 'Use a current administrator.' ); }
$before = $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", EMCP_Tools_Management_Policy::OPTION ), ARRAY_A );
$users = array();
$passwords = array();
$assertions = 0;
$check = static function( $ok, $label ) use ( &$assertions ) { ++$assertions; if (!$ok) { throw new RuntimeException($label); } };
try {
	$existing = EMCP_Tools_Management_Policy::read();
	$check( !is_wp_error($existing) && EMCP_Tools_Management_Access::can_manage(), 'The fixture must begin with valid manager access.' );
	foreach ( array('manager','denied') as $suffix ) {
		$password=wp_generate_password(40,true);
		$id = wp_insert_user(array('user_login'=>'emcp_policy_qa_'.$suffix.'_'.wp_generate_password(8,false),'user_pass'=>$password,'role'=>'administrator'));
		$check(!is_wp_error($id),'Temporary administrator creation');$users[]=$id;
		$passwords[$id]=$password;
	}
	[$manager,$denied] = $users;
	$policy=EMCP_Tools_Management_Policy::save('allowlist',array($actor,$manager),$existing['version'],$actor);
	$check(!is_wp_error($policy),'Atomic policy creation');
	wp_set_current_user($denied);
	$check(current_user_can('manage_options') && !current_user_can('manage_emcp_tools') && !EMCP_Tools_Management_Access::can_manage(),'Denied manager keeps independent execution capabilities');
	$check( in_array('do_not_allow',EMCP_Tools_Management_Access::map_capability(array(),'manage_emcp_tools',$denied,array()),true),'Super-admin denial is represented explicitly');
	$check(is_wp_error(EMCP_Tools_Management_Policy::save('all_admins',array(),$policy['version'],$denied)),'Denied actor cannot edit policy');
	$check(is_wp_error(apply_filters('rest_pre_dispatch',null,rest_get_server(),new WP_REST_Request('POST','/EMCP-TOOLS/v1/ADMIN/tools'))),'Actual registered REST gate protects case-insensitive routes');
	$check(null===apply_filters('rest_pre_dispatch',null,rest_get_server(),new WP_REST_Request('POST','/emcp-tools/v1/mcp')),'Public MCP execution gate unchanged');
	$GLOBALS['wp_rest_auth_cookie']=true;
	EMCP_Tools_Bootstrap::require_admin_classes();
	$check(is_wp_error(EMCP_Tools_Admin_REST_Controller::check(new WP_REST_Request(),'manage_options')),'Admin REST permission callback refuses denied actor');
	// Exercise actual Apache/PHP requests with short-lived cookies kept only in memory.
	$session_cookies=array();
	$http = static function($id,$path,$body=null) use(&$session_cookies,$passwords,$check) {
		if(!isset($session_cookies[$id])) {
			$login=wp_remote_post('http://msrplugins.test/wp-login.php',array('timeout'=>25,'redirection'=>0,'cookies'=>array(new WP_Http_Cookie(array('name'=>'wordpress_test_cookie','value'=>'WP Cookie check'))),'body'=>array('log'=>get_userdata($id)->user_login,'pwd'=>$passwords[$id],'redirect_to'=>'http://msrplugins.test/wp-admin/','testcookie'=>'1')));
			$check(!is_wp_error($login) && 302===wp_remote_retrieve_response_code($login),'Temporary HTTP session login');
			$session_cookies[$id]=wp_remote_retrieve_cookies($login);
		}
		$cookies=$session_cookies[$id];
		if(is_array($body) && isset($body['_wpnonce'])) {
			$prior_cookie=$_COOKIE[LOGGED_IN_COOKIE]??null;
			foreach($cookies as $cookie){if(str_starts_with($cookie->name,'wordpress_logged_in_')){$_COOKIE[LOGGED_IN_COOKIE]=$cookie->value;}}
			$body['_wpnonce']=wp_create_nonce(str_starts_with($path,'/wp-json/')?'wp_rest':($body['action']??''));
			if(null===$prior_cookie){unset($_COOKIE[LOGGED_IN_COOKIE]);}else{$_COOKIE[LOGGED_IN_COOKIE]=$prior_cookie;}
		}
		return wp_remote_request('http://msrplugins.test'.$path,array('method'=>null===$body?'GET':'POST','timeout'=>25,'redirection'=>0,'cookies'=>$cookies,'body'=>$body));
	};
	foreach (array('/wp-admin/admin.php?page=emcp-tools','/wp-admin/admin.php?page=emcp-tools-management','/wp-admin/admin.php?page=emcp-tools-connection') as $path) {
		$response=$http($denied,$path);
		// Core can refuse a missing menu before admin_init runs; either refusal is valid.
		$check(!is_wp_error($response) && 403===wp_remote_retrieve_response_code($response),'HTTP direct management URL is blocked');
	}
	$response=$http($denied,'/wp-admin/admin-post.php',array('action'=>'emcp_tools_management_policy_save','_wpnonce'=>wp_create_nonce('emcp_tools_management_policy_save'),'mode'=>'all_admins','version'=>(string)$policy['version'],'confirm'=>'1'));
	$check(!is_wp_error($response) && 403===wp_remote_retrieve_response_code($response) && str_contains(wp_remote_retrieve_body($response),'cannot manage EMCP Tools'),'HTTP policy change is blocked before the form handler');
	$response=$http($denied,'/wp-admin/admin-ajax.php?action=unrelated',array('action'=>'emcp_tools_migrate_job_progress'));
	$check(!is_wp_error($response) && 403===wp_remote_retrieve_response_code($response) && str_contains(wp_remote_retrieve_body($response),'cannot manage EMCP Tools'),'HTTP AJAX with conflicting actions is blocked');
	$response=$http($denied,'/wp-json/emcp-tools/v1/admin/connection',array('_wpnonce'=>wp_create_nonce('wp_rest')));
	$check(!is_wp_error($response) && 403===wp_remote_retrieve_response_code($response) && str_contains(wp_remote_retrieve_body($response),'emcp_management_forbidden'),'HTTP admin REST is blocked by policy with session-bound nonce');
	wp_set_current_user($manager);
	$check(current_user_can('manage_emcp_tools'),'Named manager permitted by real WordPress capability mapping');
	$response=$http($manager,'/wp-admin/admin.php?page=emcp-tools-management');
	$check(!is_wp_error($response) && 200===wp_remote_retrieve_response_code($response) && str_contains(wp_remote_retrieve_body($response),'emcp-management-users'),'HTTP named manager can load the policy form');
	$response=$http($manager,'/wp-admin/admin-post.php',array('action'=>'emcp_tools_management_policy_save','_wpnonce'=>'session-bound','mode'=>'allowlist','users'=>$actor.','.$manager,'version'=>(string)$policy['version'],'confirm'=>'1'));
	$next_policy=EMCP_Tools_Management_Policy::read();
	$check(!is_wp_error($response) && 302===wp_remote_retrieve_response_code($response) && !is_wp_error($next_policy) && $next_policy['version']===$policy['version']+1,'HTTP named manager can save with actual session nonce');
	$policy=$next_policy;
	$check(is_wp_error(EMCP_Tools_Management_Policy::save('allowlist',array($actor),$policy['version'],$manager)),'Self-lockout prevented');
	$user=new WP_User($manager);$user->set_role('subscriber');wp_set_current_user(0);wp_set_current_user($manager);
	$check(!current_user_can('manage_emcp_tools'),'Demotion removes manager access');
	wp_set_current_user($actor);
	$check(!in_array(EMCP_Tools_Management_Policy::OPTION,EMCP_Tools_Settings_Sync::sync_keys(),true),'Legacy sync excludes policy');
	$check(EMCP_Tools_Settings_Sync::apply(array(EMCP_Tools_Management_Policy::OPTION=>'tampered'))===0,'Incoming settings cannot overwrite policy');
	$stale=EMCP_Tools_Management_Policy::save('all_admins',array(),$existing['version'],$actor);
	$check(is_wp_error($stale) && 'emcp_management_policy_changed'===$stale->get_error_code(),'Stale submissions rejected');
	wp_set_current_user($denied);
	$cli_options=array('launch'=>true,'exit_error'=>false,'return'=>'all','command_args'=>array('--path=F:/laragon/www/msrplugins','--skip-themes','--skip-plugins=elementor,elementor-pro'));
	$rejected=WP_CLI::runcommand('emcp management-access recover --user='.$denied,$cli_options);
	$check(0!==$rejected->return_code && $policy===EMCP_Tools_Management_Policy::read(),'Actual host command requires explicit consent');
	$command=WP_CLI::runcommand('emcp management-access recover --confirm --user='.$denied,$cli_options);
	if(0!==$command->return_code){preg_match('/^Error:.*$/m',$command->stderr,$diagnostic);WP_CLI::log('Host command diagnostic: '.($diagnostic[0]??'exit '.$command->return_code));}
	$check(0===$command->return_code,'Actual host recovery command succeeds');
	$recovered=EMCP_Tools_Management_Policy::read();
	$check(!is_wp_error($recovered) && current_user_can('manage_emcp_tools'),'Host recovery by current administrator');
	wp_set_current_user($actor);
	$locked=EMCP_Tools_Management_Policy::save('allowlist',array($actor,$denied),$recovered['version'],$actor);
	$check(!is_wp_error($locked),'Policy can be restricted after recovery');
	wp_set_current_user($denied);$check(current_user_can('manage_emcp_tools'),'Selected account before deletion');
	wp_set_current_user($actor);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($denied);
	wp_set_current_user(0);wp_set_current_user($denied);
	$check(!current_user_can('manage_emcp_tools'),'Deleted manager cannot regain access from stale policy membership');
	WP_CLI::success('Local WordPress management policy verified: '.$assertions.' checks.');
} finally {
	wp_set_current_user($actor);
	// Restore exact policy bytes and its autoload setting, including original absence.
	$wpdb->delete($wpdb->options,array('option_name'=>EMCP_Tools_Management_Policy::OPTION),array('%s'));
	if ($before) {$wpdb->insert($wpdb->options,array('option_name'=>EMCP_Tools_Management_Policy::OPTION,'option_value'=>$before['option_value'],'autoload'=>$before['autoload']),array('%s','%s','%s'));}
	wp_cache_delete(EMCP_Tools_Management_Policy::OPTION,'options');wp_cache_delete('notoptions','options');wp_cache_delete('alloptions','options');
	require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as $id){if(get_userdata($id)){wp_delete_user($id);}}
	$after=$wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s",EMCP_Tools_Management_Policy::OPTION),ARRAY_A);
	if ($before!==$after) { WP_CLI::error('Original policy restoration failed.'); }
	WP_CLI::log('Original policy restored exactly; temporary accounts removed.');
}
