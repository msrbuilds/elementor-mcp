<?php
define('ABSPATH',__DIR__);
$GLOBALS['wpdb']=(object)array('options'=>'wp_options');
class WP_Error {private $code;public function __construct($c,$m){$this->code=$c;}public function get_error_code(){return $this->code;}}
class EMCP_Tools_Cloud {const OPTION_SITE_UUID='uuid';public static function identity_conflict(){return false;}}
function current_user_can($c){return $GLOBALS['admin']??true;}
function get_option($k,$default=null){return $k==='uuid'?'test-site':$default;}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function wp_cache_delete($k,$g){}
require __DIR__.'/../../includes/cloud/class-config-deployment.php';
$assert=function($v){if(!$v)throw new RuntimeException('assertion failed');};
$valid=array('action'=>'apply','operation_id'=>'11111111-1111-4111-8111-111111111111','site_uuid'=>'test-site','confirm'=>true,'expected'=>str_repeat('a',64),'settings'=>array('emcp_tools_strict_schemas'=>true));
foreach(array(array('confirm'=>false),array('site_uuid'=>'other'),array('operation_id'=>array()),array('expected'=>array()),array('settings'=>array('secret'=>'x')),array('settings'=>array('emcp_tools_strict_schemas'=>'1')),array('settings'=>array('emcp_tools_active_modules'=>array('core'))),array('settings'=>array('emcp_tools_disabled_tools'=>array('emcp-tools/cloud-config-deploy')))) as $change){$r=EMCP_Tools_Config_Deployment::execute(array_replace($valid,$change));$assert($r instanceof WP_Error&&$r->get_error_code()!=='deployment_unknown');}
$GLOBALS['admin']=false;$assert(EMCP_Tools_Config_Deployment::execute($valid)->get_error_code()==='forbidden');
$a=EMCP_Tools_Config_Deployment::normalize(array('emcp_tools_disabled_tools'=>array('b','a','b'),'emcp_tools_strict_schemas'=>''),true);
$assert($a===array('emcp_tools_disabled_tools'=>array('a','b'),'emcp_tools_strict_schemas'=>false));
$assert(EMCP_Tools_Config_Deployment::fingerprint(array())===hash('sha256','{}'));
echo "PASS\n";
