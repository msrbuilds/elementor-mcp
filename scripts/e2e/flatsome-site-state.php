<?php
/** Local E2E control-plane setup only. Landing page content must go through MCP. */
if (!defined('WP_CLI') || !WP_CLI || wp_parse_url(home_url(),PHP_URL_HOST)!=='elementor-mcp.test') { throw new RuntimeException('Wrong E2E site.'); }
$file=$args[1] ?? ''; if (!$file) { throw new RuntimeException('Baseline path required.'); }
require_once EMCP_TOOLS_DIR.'includes/admin/class-admin.php';
$keys=array('emcp_tools_page_builder','emcp_tools_disabled_tools','emcp_tools_defaults_applied');
if (($args[0] ?? '')==='setup') {
    if (file_exists($file)) { throw new RuntimeException('Baseline already exists; do not overwrite it.'); }
    $state=array('site'=>home_url(),'options'=>array(),'headers'=>array(),'global_id'=>EMCP_Tools_Flatsome_Native::global_id());
    foreach($keys as $key) { $state['options'][$key]=get_option($key,null); }
    foreach(get_block_templates(array(),'wp_template_part') as $part) { if ($part->area==='header' || str_contains($part->slug,'header')) { $state['headers'][$part->id]=hash('sha256',$part->content); } }
    file_put_contents($file,wp_json_encode($state,JSON_PRETTY_PRINT));
    update_option('emcp_tools_page_builder','flatsome');
    // Freeze migrations before opening wp-admin so it cannot re-disable the
    // explicitly enabled test writes. Restore the original marker afterward.
    update_option('emcp_tools_defaults_applied',(string)max((int)get_option('emcp_tools_defaults_applied',0),EMCP_Tools_Admin::DEFAULTS_VERSION));
    update_option('emcp_tools_disabled_tools',array_values(array_filter((array)$state['options']['emcp_tools_disabled_tools'],static fn($tool)=>!str_starts_with($tool,'emcp-tools/flatsome-'))));
    echo wp_json_encode(array('site'=>home_url(),'global_id'=>$state['global_id'],'protected_headers'=>array_keys($state['headers'])));
} elseif (($args[0] ?? '')==='resume') {
    $state=json_decode(file_get_contents($file),true);
    if ($state['site']!==home_url()) { throw new RuntimeException('Wrong baseline.'); }
    update_option('emcp_tools_page_builder','flatsome');
    update_option('emcp_tools_defaults_applied',(string)max((int)get_option('emcp_tools_defaults_applied',0),EMCP_Tools_Admin::DEFAULTS_VERSION));
    update_option('emcp_tools_disabled_tools',array_values(array_filter((array)get_option('emcp_tools_disabled_tools'),static fn($tool)=>!str_starts_with($tool,'emcp-tools/flatsome-'))));
    echo 'E2E controls enabled; original baseline preserved.';
} elseif (($args[0] ?? '')==='restore') {
    $state=json_decode(file_get_contents($file),true);
    if ($state['site']!==home_url()) { throw new RuntimeException('Wrong baseline.'); }
    foreach($state['headers'] as $key=>$hash) { $part=get_block_template($key,'wp_template_part'); if (!$part || hash('sha256',$part->content)!==$hash) { throw new RuntimeException('Protected header changed.'); } }
    if (EMCP_Tools_Flatsome_Native::global_id()!==$state['global_id']) { throw new RuntimeException('Global style absence not restored.'); }
    foreach($state['options'] as $key=>$value) { $value===null?delete_option($key):update_option($key,$value); }
    foreach($state['options'] as $key=>$value) { if(get_option($key,null)!==$value) { throw new RuntimeException('Option restoration failed: '.$key); } }
    echo 'RESTORED: original builder, toggles, global-style identity and protected header verified.';
}
