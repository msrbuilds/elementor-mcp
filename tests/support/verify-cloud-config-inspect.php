<?php
// Run through wp eval-file with --user=1 on a local test installation only.
if (!defined('WP_CLI') || !WP_CLI) { exit; }
global $wpdb;
$before = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'emcp_tools_%' ORDER BY option_name", ARRAY_A);
$snapshot = (new EMCP_Tools_Cloud_Abilities())->execute_config_inspect(array());
if (is_wp_error($snapshot)) {
 foreach (array('emcp_tools_dispatcher_mode','emcp_tools_strict_schemas','emcp_tools_content_mirror_enabled','emcp_tools_site_context_enabled','emcp_tools_module_themer_force_render','emcp_tools_memory_require_approval','emcp_tools_memory_auto_summarize','emcp_tools_disabled_tools','emcp_tools_active_modules') as $key) {
  $value=get_option($key,null);
  WP_CLI::log($key.': '.gettype($value).(is_string($value)?' length='.strlen($value):'').(is_array($value)?' count='.count($value).' indexed='.(array_values($value)===$value?'yes':'no'):''));
 }
 WP_CLI::error($snapshot->get_error_code());
}
$after = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'emcp_tools_%' ORDER BY option_name", ARRAY_A);
if ($before !== $after) { WP_CLI::error('Settings changed during read'); }
$ability = wp_get_ability('emcp-tools/cloud-config-inspect');
if (!$ability) { WP_CLI::error('Managed reader not registered on this test installation'); }
$registered = $ability->execute(array());
if (is_wp_error($registered)) { WP_CLI::error($registered->get_error_code()); }
if (wp_json_encode($registered) !== wp_json_encode($snapshot)) { WP_CLI::error('Registered ability did not return the same snapshot'); }
$admin = get_current_user_id();wp_set_current_user(0);
$denied = (new EMCP_Tools_Cloud_Abilities())->execute_config_inspect(array());
wp_set_current_user($admin);
if (!is_wp_error($denied) || $denied->get_error_code() !== 'forbidden') { WP_CLI::error('Permission check failed'); }
WP_CLI::success('Read-only snapshot verified on WordPress; schema '.$snapshot['schema_version'].'; '.count((array)$snapshot['settings']).' settings; guest rejected.');
