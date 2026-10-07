<?php
define( 'ABSPATH', __DIR__ );
define( 'EMCP_TOOLS_VERSION', '3.19.0' );
$options = array(); $allowed = true; $conflict = false; $reads = array();
class WP_Error { public function __construct( public $code, public $message ) {} }
class EMCP_Tools_Cloud {
 const OPTION_SITE_UUID = 'site_uuid';
 public static function identity_conflict() { global $conflict; return $conflict; }
}
function current_user_can( $cap ) { global $allowed; return $allowed && 'manage_options' === $cap; }
function get_option( $key, $default = false ) { global $options, $reads; $reads[] = $key; return $options[$key] ?? $default; }
function update_option() { throw new Exception('Read-only tool wrote an option'); }
function apply_filters() { throw new Exception('Fixed reader used a filterable allowlist'); }
require __DIR__ . '/../../includes/cloud/class-settings-sync.php';
require __DIR__ . '/../../includes/abilities/class-cloud-abilities.php';
function check( $value ) { if (!$value) throw new Exception('Assertion failed'); }
$tool = new EMCP_Tools_Cloud_Abilities();
$options = array('site_uuid'=>'s','emcp_tools_strict_schemas'=>'0','emcp_tools_disabled_tools'=>array('z','a','z'),'token'=>'secret','emcp_tools_site_context'=>'secret');
$snapshot = $tool->execute_config_inspect(array());
check( $snapshot['site_uuid'] === 's' );
check( (array)$snapshot['settings'] === array('emcp_tools_disabled_tools'=>array('a','z'),'emcp_tools_strict_schemas'=>false) );
check( !in_array('token',$reads,true) && !in_array('emcp_tools_site_context',$reads,true) );
$options['emcp_tools_strict_schemas']='';check($tool->execute_config_inspect(array())['settings']->emcp_tools_strict_schemas===false);
check( $tool->execute_config_inspect(array('apply'=>true))->code === 'invalid_input' );
$allowed=false;check($tool->execute_config_inspect(array())->code==='forbidden');
$allowed=true;$conflict=true;check($tool->execute_config_inspect(array())->code==='site_identity_conflict');$conflict=false;
foreach(array('yes',array(),2) as $invalid){$options['emcp_tools_strict_schemas']=$invalid;check($tool->execute_config_inspect(array())->code==='invalid_managed_setting');}
unset($options['emcp_tools_strict_schemas']);
foreach(array(array('../secret'),array('a'=>'module'),array_fill(0,5001,'tool')) as $invalid){$options['emcp_tools_disabled_tools']=$invalid;check($tool->execute_config_inspect(array())->code==='invalid_managed_setting');}
$options=array('site_uuid'=>'s');check(json_encode($tool->execute_config_inspect(array())['settings'])==='{}');
echo "PASS\n";
