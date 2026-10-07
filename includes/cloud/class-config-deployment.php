<?php
/** Atomic managed-option deployment. Uses a dedicated connection; never nests a caller transaction. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class EMCP_Tools_Config_Deployment {
 const BOOLS = array('emcp_tools_dispatcher_mode','emcp_tools_strict_schemas','emcp_tools_content_mirror_enabled','emcp_tools_site_context_enabled','emcp_tools_module_themer_force_render','emcp_tools_memory_require_approval','emcp_tools_memory_auto_summarize');
 const LISTS = array('emcp_tools_disabled_tools','emcp_tools_active_modules');
 public static function normalize( $values, $stored = false ) {
  if ( ! is_array($values) && ! is_object($values) ) { throw new RuntimeException('invalid_settings'); }
  $out=array();
  foreach ((array)$values as $key=>$value) {
   if(in_array($key,self::BOOLS,true)) {
    if($stored && in_array($value,array(true,false,0,1,'','0','1'),true)) $value=in_array($value,array(true,1,'1'),true);
    if(!is_bool($value)) throw new RuntimeException('invalid_settings');
   } elseif(in_array($key,self::LISTS,true)) {
    if(!is_array($value)||array_values($value)!==$value||count($value)>5000) throw new RuntimeException('invalid_settings');
    foreach($value as $item) if(!is_string($item)||strlen($item)>120||!preg_match('~^[a-z][a-z0-9_-]*(/[a-z][a-z0-9_-]*)?$~D',$item)) throw new RuntimeException('invalid_settings');
    $value=array_values(array_unique($value));sort($value,SORT_STRING);
   } else throw new RuntimeException('invalid_settings');
   $out[$key]=$value;
  }
  ksort($out,SORT_STRING); return $out;
 }
 public static function fingerprint($settings) { return hash('sha256',wp_json_encode((object)self::normalize($settings,true),JSON_UNESCAPED_SLASHES)); }
 public static function execute($input) {
  if(!current_user_can('manage_options')) return new WP_Error('forbidden','Administrator access required.');
  if(EMCP_Tools_Cloud::identity_conflict()) return new WP_Error('site_identity_conflict','Separate this installation first.');
  $a=(array)$input;$action=$a['action']??'';
  // Strict schemas require nullable placeholders even for recovery commands.
  if(in_array($action,array('rollback','status'),true)) foreach(array('expected','settings') as $key) if(array_key_exists($key,$a)&&$a[$key]===null) unset($a[$key]);
  $allowed=$action==='apply'?array('action','operation_id','site_uuid','expected','settings','confirm'):array('action','operation_id','site_uuid','confirm');
  if(array_diff(array_keys($a),$allowed)||!in_array($action,array('apply','rollback','status'),true)||!is_string($a['operation_id']??null)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$a['operation_id'])||!is_string($a['site_uuid']??null)||$a['site_uuid']===''||$a['site_uuid']!==get_option(EMCP_Tools_Cloud::OPTION_SITE_UUID,'')) return new WP_Error('invalid_input','Invalid deployment request.');
  if($action!=='status'&&($a['confirm']??false)!==true) return new WP_Error('confirmation_required','Explicit confirmation required.');
  $connection=null;$locked=false;$transaction=false;
  global $wpdb; $table=$wpdb->options;
  try {
   $desired=$action==='apply'?self::normalize($a['settings']??null):array();
   if($action==='apply'&&(!$desired||!is_string($a['expected']??null)||!preg_match('/^[a-f0-9]{64}$/D',$a['expected']))) throw new RuntimeException('invalid_input');
   // Keep the recovery channel available. This surface cannot turn off Cloud or its own tools.
   if(isset($desired['emcp_tools_active_modules'])&&!in_array('cloud',$desired['emcp_tools_active_modules'],true)) throw new RuntimeException('recovery_channel_required');
   if(array_intersect($desired['emcp_tools_disabled_tools']??array(),array('emcp-tools/cloud-config-inspect','emcp-tools/cloud-config-deploy','emcp-tools/call-tool'))) throw new RuntimeException('recovery_channel_required');
   $connection=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$connection->suppress_errors(true);
   $engine=$connection->get_var($connection->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table));
   if(strtoupper((string)$engine)!=='INNODB') throw new RuntimeException('transactional_storage_required');
   $lock='emcp_config_'.substr(hash('sha256',DB_NAME.$table),0,40);
   $locked=(string)$connection->get_var($connection->prepare('SELECT GET_LOCK(%s,0)',$lock))==='1';
   if(!$locked) throw new RuntimeException('busy');
   self::query($connection,'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');self::query($connection,'START TRANSACTION');$transaction=true;
   // Lock the actual option rows and missing-key ranges, so ordinary admin writes cannot race the CAS.
   $keys=array_merge(self::BOOLS,self::LISTS,array(EMCP_Tools_Cloud::OPTION_SITE_UUID,'emcp_tools_cloud_identity_base','home'));
   $placeholders=implode(',',array_fill(0,count($keys),'%s'));
   $rows=$connection->get_results($connection->prepare("SELECT option_name,option_value FROM `$table` WHERE option_name IN ($placeholders) ORDER BY option_name FOR UPDATE",...$keys),ARRAY_A);
   if($connection->last_error) throw new RuntimeException('storage_error');
   $raw=array_column($rows,'option_value','option_name');
   if(($raw[EMCP_Tools_Cloud::OPTION_SITE_UUID]??'')!==$a['site_uuid']||(!empty($raw['emcp_tools_cloud_identity_base'])&&untrailingslashit($raw['emcp_tools_cloud_identity_base'])!==untrailingslashit($raw['home']??''))) throw new RuntimeException('site_changed');
   $current=array();foreach(array_merge(self::BOOLS,self::LISTS) as $key) if(array_key_exists($key,$raw)) $current[$key]=maybe_unserialize($raw[$key]);
   $current=self::normalize($current,true);
   $journal='emcp_config_operation_'.$a['operation_id'];
   $prior=$connection->get_var($connection->prepare("SELECT option_value FROM `$table` WHERE option_name=%s FOR UPDATE",$journal));
   $record=$prior===null?null:json_decode($prior,true,512,JSON_THROW_ON_ERROR);
   $signature=hash('sha256',wp_json_encode(array($a['site_uuid'],$a['expected']??'',(object)$desired),JSON_UNESCAPED_SLASHES));
   if($record&&$record['site_uuid']!==$a['site_uuid']) throw new RuntimeException('site_changed');
   if($action==='status') { self::query($connection,'ROLLBACK');$transaction=false;return $record?self::receipt($record):array('status'=>'not_found','operation_id'=>$a['operation_id'],'site_uuid'=>$a['site_uuid']); }
   if($action==='apply'&&$record) {
    if(!hash_equals($record['signature'],$signature)) throw new RuntimeException('idempotency_conflict');
    self::query($connection,'ROLLBACK');$transaction=false;return self::receipt($record);
   }
   if($action==='rollback') {
    if(!$record) throw new RuntimeException('not_found');
    if($record['status']==='rolled_back') {self::query($connection,'ROLLBACK');$transaction=false;return self::receipt($record);}
    foreach($record['written'] as $key=>$value) if(!array_key_exists($key,$raw)||$raw[$key]!==$value) throw new RuntimeException('rollback_conflict');
    foreach($record['before'] as $key=>$value) self::write($connection,$table,$key,$value);
    $record['status']='rolled_back';
   } else {
    if(!hash_equals($a['expected'],self::fingerprint($current))) throw new RuntimeException('configuration_changed');
    $count=$connection->get_var("SELECT COUNT(*) FROM `$table` WHERE option_name LIKE 'emcp\\_config\\_operation\\_%'");
    if($connection->last_error||$count>=1000) throw new RuntimeException('journal_capacity');
    $before=array();$written=array();
    foreach($desired as $key=>$value) {
     if(array_key_exists($key,$current)&&$current[$key]===$value) continue;
     $before[$key]=$raw[$key]??null;
     $written[$key]=is_array($value)?serialize($value):($value?'1':'0');
     self::write($connection,$table,$key,$written[$key]);
    }
    $after=array_replace($current,$desired);
    $record=array('status'=>'applied','operation_id'=>$a['operation_id'],'site_uuid'=>$a['site_uuid'],'signature'=>$signature,'before'=>$before,'written'=>$written,'before_fingerprint'=>self::fingerprint($current),'after_fingerprint'=>self::fingerprint($after));
   }
   self::write($connection,$table,$journal,wp_json_encode($record));
   self::query($connection,'COMMIT');$transaction=false;
   return self::receipt($record);
  } catch(Throwable $e) {
   $codes=array('invalid_input','invalid_settings','recovery_channel_required','transactional_storage_required','busy','storage_error','site_changed','idempotency_conflict','not_found','rollback_conflict','configuration_changed','journal_capacity');
   return new WP_Error(in_array($e->getMessage(),$codes,true)?$e->getMessage():'deployment_unknown','Deployment did not return a confirmed result. Read operation status before retrying.');
  } finally {
   if($connection) {
    if($transaction) $connection->query('ROLLBACK');
    if($locked) $connection->get_var($connection->prepare('SELECT RELEASE_LOCK(%s)',$lock));
    $connection->close();
   }
   // These are internal EMCP preferences, committed together without third-party option hooks.
   // Invalidate even on an uncertain COMMIT response; the durable receipt resolves its outcome.
   foreach(array_merge(self::BOOLS,self::LISTS) as $key) wp_cache_delete($key,'options');
   wp_cache_delete('alloptions','options');wp_cache_delete('notoptions','options');
  }
 }
 private static function receipt($record) { return array_intersect_key($record,array_flip(array('status','operation_id','site_uuid','before_fingerprint','after_fingerprint'))); }
 private static function query($db,$sql) { if($db->query($sql)===false) throw new RuntimeException('storage_error'); }
 private static function write($db,$table,$key,$value) {
  if($value===null) self::query($db,$db->prepare("DELETE FROM `$table` WHERE option_name=%s",$key));
  else self::query($db,$db->prepare("INSERT INTO `$table` (option_name,option_value,autoload) VALUES (%s,%s,'off') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)",$key,$value));
 }
}
