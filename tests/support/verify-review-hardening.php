<?php
if ( 'https://msrplugins.test' !== untrailingslashit( home_url() ) ) { throw new RuntimeException( 'Wrong site' ); }
foreach ( array( 'loop/class-themer-loop-assets.php', 'loop/class-themer-loop-rest.php', 'blocks/class-themer-blocks.php' ) as $review_file ) { require_once EMCP_TOOLS_DIR . 'includes/themer/' . $review_file; }
$checks = 0;
$check = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };
wp_enqueue_style( 'emcpli-review-style', plugins_url( 'assets/css/oauth-consent.css', EMCP_TOOLS_DIR . 'emcp-tools.php' ), array(), '1' );
wp_add_inline_style( 'emcpli-review-style', '.emcpli-review{color:red}' );
wp_enqueue_script( 'emcpli-review-script', plugins_url( 'assets/js/elementor-notice.js', EMCP_TOOLS_DIR . 'emcp-tools.php' ), array(), '1', true );
wp_add_inline_script( 'emcpli-review-script', 'window.emcpliReview = true;', 'before' );
$before_styles = wp_styles()->done;
$before_scripts = wp_scripts()->done;
$result = EMCP_Tools_Themer_Loop_REST::collect_assets( static function () { return '<p>Card</p>'; } );
$assets = array_column( $result['assets'], null, 'handle' );
$check( str_contains( $assets['emcpli-review-style']['external'], '<link' ), 'Missing core stylesheet tag' );
$check( str_contains( $assets['emcpli-review-script']['external'], '<script' ), 'Missing core script tag' );
$check( ! str_contains( $assets['emcpli-review-style']['external'], 'color:red' ), 'Duplicated inline style' );
$check( ! str_contains( $assets['emcpli-review-script']['external'], 'emcpliReview' ), 'Duplicated config' );
$check( count( $assets['emcpli-review-script']['config'] ) === 1, 'Missing script config' );
$check( $before_styles === wp_styles()->done && $before_scripts === wp_scripts()->done, 'REST consumed page queues' );
EMCP_Tools_Themer_Loop_Assets::enqueue_dynamic_css( 912345, 912346, '.emcpli-card{color:blue}' );
ob_start(); EMCP_Tools_Themer_Loop_Assets::print_dynamic_styles(); $css = ob_get_clean();
$check( str_contains( $css, '.emcpli-card{color:blue}' ), 'Late CSS not printed' );
ob_start(); EMCP_Tools_Themer_Loop_Assets::print_dynamic_styles(); $again = ob_get_clean();
$check( ! str_contains( $again, '.emcpli-card' ), 'Late CSS printed twice' );
$consent = EMCP_Tools_OAuth_Authorize::render_consent( array( 'client_name' => '<script>bad</script>' ) );
$check( str_contains( $consent, 'oauth-consent.css' ) && ! str_contains( $consent, '<style>' ), 'Consent stylesheet missing' );
$check( ! str_contains( $consent, '<script>bad</script>' ), 'Consent client label not escaped' );
echo "PASS OAuth consent asset and label checks\n";

$server_before = $_SERVER;
$trusted_proxy = static function () { return array( '127.0.0.1' ); };
add_filter( 'emcp_tools_trusted_proxies', $trusted_proxy );
try {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    unset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.8, 127.0.0.1';
    $check( '192.0.2.8' === EMCP_Tools_OAuth_Clients::client_ip(), 'Valid proxy IP rejected' );
    $_SERVER['HTTP_X_FORWARDED_FOR'] = "192.0.2.8\r\n";
    $check( '127.0.0.1' === EMCP_Tools_OAuth_Clients::client_ip(), 'Malformed IP normalized into trusted value' );
    $_SERVER['REMOTE_ADDR'] = array( '127.0.0.1' );
    $check( 'unknown' === EMCP_Tools_OAuth_Clients::client_ip(), 'Array server input accepted' );
} finally {
    $_SERVER = $server_before;
    remove_filter( 'emcp_tools_trusted_proxies', $trusted_proxy );
}
global $wpdb;
EMCP_Tools_OAuth_Store::count_clients();
$check( '' === $wpdb->last_error, 'Client count SQL failed' );
EMCP_Tools_OAuth_Store::list_authorized_clients();
$check( '' === $wpdb->last_error, 'Client join SQL failed' );
$check( null === EMCP_Tools_OAuth_Store::get_client( 'review-smoke-nonexistent' ), 'Unexpected test client' );
$check( '' === $wpdb->last_error, 'Client lookup SQL failed' );
echo "PASS $checks native WordPress review checks\n";

require_once EMCP_TOOLS_DIR . 'pro/includes/themer/class-themer-pro-conditions.php';
$admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) );
foreach ( $admins as $admin ) {
    if ( ! EMCP_Tools_Themer_CPT::excluded( (int) $admin ) ) { wp_set_current_user( $admin ); break; }
}
$check( current_user_can( 'edit_pages' ), 'No permitted local administrator for AJAX check' );
if ( ! defined( 'DOING_AJAX' ) ) { define( 'DOING_AJAX', true ); }
$die = static function () { return static function () { throw new RuntimeException( 'review-json-complete' ); }; };
add_filter( 'wp_die_ajax_handler', $die, 9999 );
$before_post = $_POST; $before_request = $_REQUEST;
$deny_reads = static function ( $caps, $cap ) { return 'read_post' === $cap ? array( 'do_not_allow' ) : $caps; };
try {
    $_REQUEST['nonce'] = wp_create_nonce( 'emcp_themer_object_search' );
    foreach ( array( array( 'object' => array( 'invalid' ), 'q' => array( 'invalid' ) ), array( 'object' => wp_slash( '{"kind":"post"}' ), 'q' => '' ) ) as $payload ) {
        $_POST = $payload;
        add_filter( 'map_meta_cap', $deny_reads, 9999, 2 );
        ob_start();
        try { EMCP_Tools_Themer_Pro_Conditions::ajax_object_search(); }
        catch ( RuntimeException $e ) { if ( 'review-json-complete' !== $e->getMessage() ) { throw $e; } }
        finally { $json = ob_get_clean(); remove_filter( 'map_meta_cap', $deny_reads, 9999 ); }
        $body = json_decode( $json, true );
        $check( true === ( $body['success'] ?? null ) && array() === $body['data']['items'], 'Malformed input or unreadable posts leaked from Pro search' );
    }
} finally {
    $_POST = $before_post; $_REQUEST = $before_request;
    remove_filter( 'wp_die_ajax_handler', $die, 9999 );
}
echo "PASS $checks total native Free/Pro checks\n";
