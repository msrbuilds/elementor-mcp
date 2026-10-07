<?php
/** Local WordPress only: wp --user=1 eval-file tests/support/verify-cloud-config-deployment.php */
if(!defined('WP_CLI')||!WP_CLI)exit;
if(!in_array(parse_url(home_url(),PHP_URL_HOST),array('msrplugins.test','localhost'),true))WP_CLI::error('Local fixture only');
require_once WP_PLUGIN_DIR.'/elementor-mcp/includes/cloud/class-config-deployment.php';
$key='emcp_tools_strict_schemas';$other='emcp_tools_site_context_enabled';$sentinel=new stdClass();
$old=get_option($key,$sentinel);$old_other=get_option($other,$sentinel);$ids=array();$trigger=null;
$check=function($ok,$message){if(!$ok)throw new RuntimeException($message);};
$snapshot=function(){ $r=EMCP_Tools_Settings_Sync::managed_snapshot();if(is_wp_error($r))throw new RuntimeException($r->get_error_code());return (array)$r['settings'];};
$call=function($args){return EMCP_Tools_Config_Deployment::execute($args);};
try {
 update_option($key,0);$before=$snapshot();$uuid=get_option(EMCP_Tools_Cloud::OPTION_SITE_UUID);
 $id=wp_generate_uuid4();$ids[]=$id;
 $apply=array('action'=>'apply','operation_id'=>$id,'site_uuid'=>$uuid,'expected'=>EMCP_Tools_Config_Deployment::fingerprint($before),'settings'=>array($key=>true),'confirm'=>true);
 $ability=wp_get_ability('emcp-tools/cloud-config-deploy');$check((bool)$ability,'deployment ability missing');
 $r=$ability->execute($apply);$check(!is_wp_error($r)&&$r['status']==='applied','registered apply failed: '.(is_wp_error($r)?$r->get_error_code():''));
 $check(get_option($key)==='1','write not visible');$check($call($apply)===$r,'idempotent retry differs');
 // A fresh process boots with strict schemas enabled by the preceding apply.
 $strict_status=array('action'=>'status','operation_id'=>$id,'site_uuid'=>$uuid,'expected'=>null,'settings'=>null,'confirm'=>false);
 $strict_code='$r=wp_get_ability("emcp-tools/cloud-config-deploy")->execute(json_decode(base64_decode("'.base64_encode(wp_json_encode($strict_status)).'"),true)); if(is_wp_error($r)) throw new Exception($r->get_error_message()); echo "STRICT_RECEIPT=".$r["status"];';
 $strict_process=proc_open(array(PHP_BINARY,'C:/wp-cli/wp-cli.phar','--path='.ABSPATH,'--user=1','eval',$strict_code),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$strict_pipes);
 fclose($strict_pipes[0]);$strict_out=stream_get_contents($strict_pipes[1]);$strict_err=stream_get_contents($strict_pipes[2]);fclose($strict_pipes[1]);fclose($strict_pipes[2]);
 $check(proc_close($strict_process)===0&&str_contains($strict_out,'STRICT_RECEIPT=applied'),'strict registered recovery failed: '.$strict_err);
 $different=$apply;$different['settings'][$key]=false;$conflict=$call($different);$check(is_wp_error($conflict)&&$conflict->get_error_code()==='idempotency_conflict','id reuse accepted');
 $stale=$apply;$stale['operation_id']=wp_generate_uuid4();$ids[]=$stale['operation_id'];$conflict=$call($stale);$check(is_wp_error($conflict)&&$conflict->get_error_code()==='configuration_changed','stale fingerprint accepted');
 $rollback=array('action'=>'rollback','operation_id'=>$id,'site_uuid'=>$uuid,'confirm'=>true);
 update_option($key,0);$conflict=$call($rollback);$check(is_wp_error($conflict)&&$conflict->get_error_code()==='rollback_conflict','later edit overwritten');
 update_option($key,1);update_option($other,1);
 $r=$call($rollback);$check(!is_wp_error($r)&&$r['status']==='rolled_back','rollback failed');
 $check(get_option($key)==='0'&&get_option($other)==='1','rollback lost unrelated edit');
 $check($call($rollback)===$r&&$call($apply)===$r,'retry after rollback reapplied');
 // Absence is restored, rather than persisting a default value.
 delete_option($key);$before=$snapshot();$apply['operation_id']=wp_generate_uuid4();$ids[]=$apply['operation_id'];$apply['expected']=EMCP_Tools_Config_Deployment::fingerprint($before);
 $check(!is_wp_error($call($apply)),'missing-key apply failed');$rollback['operation_id']=$apply['operation_id'];$check(!is_wp_error($call($rollback)),'missing-key rollback failed');$check(get_option($key,$sentinel)===$sentinel,'missing key not restored');
 // Force a failure after both setting statements, at journal insertion. The entire transaction must disappear.
 global $wpdb;$failure_id=wp_generate_uuid4();$ids[]=$failure_id;$trigger='emcp_config_qa_'.str_replace('-','',$failure_id);
 $journal='emcp_config_operation_'.$failure_id;
 $created=$wpdb->query("CREATE TRIGGER `$trigger` BEFORE INSERT ON `{$wpdb->options}` FOR EACH ROW BEGIN IF NEW.option_name='$journal' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='intentional configuration QA failure'; END IF; END");
 $check($created!==false,'could not install scoped failure fixture');
 $before=$snapshot();$failure=array('action'=>'apply','operation_id'=>$failure_id,'site_uuid'=>$uuid,'expected'=>EMCP_Tools_Config_Deployment::fingerprint($before),'settings'=>array($key=>true,$other=>false),'confirm'=>true);
 $failed=$call($failure);$check(is_wp_error($failed)&&$failed->get_error_code()==='storage_error','injected failure was not reported');
 $check($snapshot()===$before&&get_option($journal,$sentinel)===$sentinel,'partial write survived rollback');
 $wpdb->query("DROP TRIGGER `$trigger`");$trigger=null;
 // Independent PHP/DB connections compete with the same expected snapshot; at most one may apply.
 update_option($key,0);$before=$snapshot();$processes=array();$pipes_list=array();
 for($i=0;$i<2;$i++){
  $race=$apply;$race['operation_id']=wp_generate_uuid4();$ids[]=$race['operation_id'];$race['expected']=EMCP_Tools_Config_Deployment::fingerprint($before);
  $code='require_once '.var_export(WP_PLUGIN_DIR.'/elementor-mcp/includes/cloud/class-config-deployment.php',true).'; $r=EMCP_Tools_Config_Deployment::execute(json_decode(base64_decode("'.base64_encode(wp_json_encode($race)).'"),true)); echo "QA_RESULT=".wp_json_encode(is_wp_error($r)?$r->get_error_code():$r["status"])."\\n";';
  $processes[]=proc_open(array(PHP_BINARY,'C:/wp-cli/wp-cli.phar','--path='.ABSPATH,'--user=1','eval',$code),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
  fclose($pipes[0]);$pipes_list[]=$pipes;
 }
 $outcomes=array();foreach($processes as $i=>$process){$out=stream_get_contents($pipes_list[$i][1]);$err=stream_get_contents($pipes_list[$i][2]);fclose($pipes_list[$i][1]);fclose($pipes_list[$i][2]);$exit=proc_close($process);$check($exit===0&&preg_match('/QA_RESULT=("[a-z_]+")/',$out,$match),'race child failed');$outcomes[]=json_decode($match[1],true);}
 $check(count(array_filter($outcomes,function($s){return $s==='applied';}))===1&&!array_diff($outcomes,array('applied','busy','configuration_changed')),'competing writes were not serialized');
 wp_set_current_user(0);$denied=$call($apply);wp_set_current_user(1);$check(is_wp_error($denied)&&$denied->get_error_code()==='forbidden','guest accepted');
 WP_CLI::success('Atomic apply, durable retries, stale CAS, rollback conflict, unrelated edits, absent-key restore, injected mid-write failure, independent-process contention and guest rejection passed.');
} finally {
 wp_set_current_user(1);
 if($trigger){global $wpdb;$wpdb->query("DROP TRIGGER `$trigger`");}
 if($old===$sentinel)delete_option($key);else update_option($key,$old);
 if($old_other===$sentinel)delete_option($other);else update_option($other,$old_other);
 foreach($ids as $id)delete_option('emcp_config_operation_'.$id);
}
