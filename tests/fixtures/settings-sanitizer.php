<?php
/** Settings sanitizer trust-boundary regression, isolated from WordPress globals. */
define('ABSPATH',__DIR__);
function sanitize_text_field($s){return trim(strip_tags($s));}
function wp_unslash($s){return $s;}
function wp_verify_nonce($n,$a){return $n==='valid' && $a==='emcp_tools_settings-options';}
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function update_option($k,$v){$GLOBALS['options'][$k]=$k==='disabled'?$GLOBALS['admin']->sanitize_disabled_tools($v):$v;}
class EMCP_Tools_Management_Access {public static function can_manage(){return $GLOBALS['manager']??false;}}
require dirname(__DIR__,2).'/includes/admin/trait-admin-settings.php';
class EMCP_Tools_Settings_Test {
 use EMCP_Tools_Admin_Settings_Trait;
 const SETTINGS_GROUP='emcp_tools_settings';
 const OPTION_DISABLED_TOOLS='disabled';
 const OPTION_DEFAULTS_APPLIED='defaults';
 const DEFAULTS_VERSION=1;
 public function get_all_tool_slugs(){return array('read','write','hidden');}
 public function get_available_tool_slugs(){return array('read','write');}
 public function default_disabled_changes($v){return array('strip'=>array(),'add'=>array('write'));}
}
function verify($ok,$why){if(!$ok){fwrite(STDERR,$why."\n");exit(1);}}
$a=new EMCP_Tools_Settings_Test();$GLOBALS['admin']=$a;
foreach(array(
 array(false,null,'admin-ajax.php'),
 array(true,'invalid','options.php'),
 array(false,'valid','options.php'),
 array(true,'valid','admin-ajax.php'),
 array(true,array('valid'),'options.php'),
) as [$manager,$nonce,$page]) {
 $GLOBALS['manager']=$manager;$GLOBALS['pagenow']=$page;$GLOBALS['options']=array();
 $_POST=array('option_page'=>$a::SETTINGS_GROUP,'disabled'=>array('read','write'));
 if(null!==$nonce)$_POST['_wpnonce']=$nonce;
 $a->maybe_apply_default_disabled_tools();
 verify(get_option('disabled')===array('write'),'Forged POST must not influence pending defaults migration');
}
$GLOBALS['manager']=true;$GLOBALS['pagenow']='options.php';$GLOBALS['options']=array('disabled'=>array('hidden'));
$_POST=array('option_page'=>$a::SETTINGS_GROUP,'_wpnonce'=>'valid','disabled'=>array('read'));
$expected=array('write','hidden');
verify($a->sanitize_disabled_tools(array())===$expected,'Valid manager settings form must invert visible tools and preserve hidden preferences');
verify($a->sanitize_disabled_tools($expected)===$expected,'Double sanitization remains idempotent');
$_POST=array();
verify($a->sanitize_disabled_tools(array('write','unknown'))===array('write'),'Programmatic values remain sanitized');
echo "PASS\n";
