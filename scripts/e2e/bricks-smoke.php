<?php
/** Run explicitly on the local Bricks test site: php scripts/e2e/bricks-smoke.php. */
if ( PHP_SAPI !== 'cli' ) { exit; }
$_SERVER['REQUEST_SCHEME'] = 'https';
$_SERVER['HTTP_HOST'] = 'elementor-mcp.test';
if ( ! defined( 'ABSPATH' ) ) { require dirname( __DIR__, 6 ) . '/elementor-mcp/wp-load.php'; }
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'elementor-mcp.test', 'localhost' ), true ) ) { exit( "Local test site required.\n" ); }
EMCP_Tools_Bootstrap::load_mcp_surface();
if ( ! EMCP_Tools_Bricks_Integration::supported() ) { exit( "Supported Bricks theme required.\n" ); }
$administrators = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $administrators[0]->ID );
\Bricks\Capabilities::$capabilities_set = false;
\Bricks\Capabilities::set_user_capabilities();
\Bricks\Capabilities::set_administrator_capabilities();
$saved = array();
foreach ( array( 'emcp_tools_page_builder', 'emcp_tools_disabled_tools' ) as $key ) { $saved[ $key ] = get_option( $key, null ); }
$ids = array();
$old_css_dir = \Bricks\Assets::$css_dir;
// Keep generated test files in the writable workspace; exercise the real save hook.
$test_css_dir = __DIR__ . '/.bricks-css-' . getmypid();
wp_mkdir_p( $test_css_dir );
\Bricks\Assets::$css_dir = $test_css_dir;
function bricks_check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } echo "PASS $message\n"; }
function bricks_run( $slug, $args = array() ) {
	$ability = wp_get_ability( 'emcp-tools/bricks-' . $slug );
	if ( ! $ability ) { throw new RuntimeException( 'Missing ability: ' . $slug ); }
	$result = $ability->execute( $args );
	if ( is_wp_error( $result ) ) { throw new RuntimeException( $slug . ': ' . $result->get_error_code() . ': ' . $result->get_error_message() ); }
	return $result;
}
try {
	update_option( 'emcp_tools_page_builder', 'bricks' );
	$disabled = get_option( 'emcp_tools_disabled_tools', array() );
	update_option( 'emcp_tools_disabled_tools', array_values( array_filter( $disabled, static function( $slug ) { return strpos( $slug, 'emcp-tools/bricks-' ) !== 0; } ) ) );
	$context = bricks_run( 'get-context' );
	bricks_check( $context['version'] === BRICKS_VERSION && EMCP_Tools_Bricks_Integration::version_supported( $context['version'] ), 'installed Bricks version' );
	bricks_check( ! wp_get_ability( 'emcp-tools/get-page-structure' ), 'Elementor excluded' );
	bricks_check( (bool) wp_get_ability( 'emcp-tools/list-blocks' ), 'Gutenberg coexists' );
	$schema = bricks_run( 'get-element-schema', array( 'name' => 'heading' ) );
	bricks_check( isset( $schema['controls']['text'], $schema['controls']['_typography'] ), 'native heading controls' );
	$catalog = bricks_run( 'list-elements' );
	bricks_check( count( $catalog['elements'] ) > 50, 'runtime element catalog' );
	$state = bricks_run( 'create-page', array( 'title' => 'EMCP Bricks Integration Smoke Test' ) );
	$id = $state['post_id']; $ids[] = $id;
	bricks_check( get_post_status( $id ) === 'draft', 'creation is draft only' );
	$elements = array(
		array( 'id' => 'sec001', 'name' => 'section', 'parent' => 0, 'children' => array( 'con001' ), 'settings' => array( '_padding' => array( 'top' => '48px', 'bottom' => '48px' ) ) ),
		array( 'id' => 'con001', 'name' => 'container', 'parent' => 'sec001', 'children' => array( 'hdg001' ), 'settings' => array() ),
		array( 'id' => 'hdg001', 'name' => 'heading', 'parent' => 'con001', 'children' => array(), 'settings' => array( 'text' => 'Bricks smoke test', 'tag' => 'h1', '_typography' => array( 'font-size' => '48px' ), '_typography:mobile_portrait' => array( 'font-size' => '28px' ) ) ),
	);
	$state = bricks_run( 'set-page-elements', array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'elements' => $elements ) );
	bricks_check( $state['elements'] === $elements, 'flat tree round trip including responsive styles' );
	bricks_check( ! empty( $state['revision_id'] ), 'native revision created' );
	$stale = $state['content_hash'];
	$state = bricks_run( 'add-element', array( 'post_id' => $id, 'expected_hash' => $stale, 'element' => array( 'id' => 'txt001', 'name' => 'text-basic', 'parent' => 'con001', 'settings' => array( 'text' => 'Path C:\\demo and "quotes"' ) ) ) );
	bricks_check( count( $state['elements'] ) === 4, 'insert with slash preservation' );
	$denied = wp_get_ability( 'emcp-tools/bricks-remove-element' )->execute( array( 'post_id' => $id, 'expected_hash' => $stale, 'element_id' => 'txt001' ) );
	bricks_check( is_wp_error( $denied ) && 'stale_page' === $denied->get_error_code(), 'stale edit refused' );
	$state = bricks_run( 'update-element', array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 'hdg001', 'settings' => array( 'text' => 'Bricks verified' ) ) );
	bricks_check( get_metadata( 'post', $state['revision_id'], BRICKS_DB_PAGE_CONTENT, true )[2]['settings']['text'] === 'Bricks smoke test', 'revision preserves previous tree' );
	$state = bricks_run( 'move-element', array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 'txt001', 'parent' => 'con001', 'position' => 0 ) );
	bricks_check( $state['elements'][1]['children'] === array( 'txt001', 'hdg001' ), 'sibling reorder' );
	$html = \Bricks\Frontend::render_data( $state['elements'] );
	bricks_check( strpos( $html, 'Bricks verified' ) !== false && strpos( $html, '<h1' ) !== false, 'native frontend rendering' );
	$state = bricks_run( 'remove-element', array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 'txt001' ) );
	bricks_check( count( $state['elements'] ) === 3, 'remove element' );
	foreach ( array(
		array( '_padding:tablet' => array( 'top' => '20px' ) ),
		array( '_background' => '#fff' ),
		array( '_typography' => array( 'fontSize' => '40px' ) ),
		array( '_cssGlobalClasses' => array( 'missing-class' ) ),
		array( 'text' => '{echo:phpinfo}' ),
		array( 'inventedSetting' => 'test' ),
	) as $settings ) {
		$denied = wp_get_ability( 'emcp-tools/bricks-update-element' )->execute( array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 'hdg001', 'settings' => $settings ) );
		bricks_check( is_wp_error( $denied ), 'invalid settings rejected: ' . array_key_first( $settings ) );
		bricks_check( bricks_run( 'get-page', array( 'post_id' => $id ) )['content_hash'] === $state['content_hash'], 'rejection leaves content unchanged' );
	}
	add_option( 'emcp_bricks_lock_' . $id, time(), '', false );
	$denied = wp_get_ability( 'emcp-tools/bricks-remove-element' )->execute( array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 'hdg001' ) );
	delete_option( 'emcp_bricks_lock_' . $id );
	bricks_check( is_wp_error( $denied ) && 'page_locked' === $denied->get_error_code(), 'competing writer denied' );
	// Only this request uses external CSS; no site setting is changed.
	\Bricks\Database::$global_settings['cssLoading'] = 'file';
	// Switching mode after theme bootstrap needs the same save hook that Bricks
	// installs at startup when the persisted CSS mode is External Files.
	if ( ! class_exists( '\Bricks\Assets_Files', false ) ) {
		require_once BRICKS_PATH . 'includes/assets/files.php';
		new \Bricks\Assets_Files();
	}
	$state = bricks_run( 'update-element', array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 'hdg001', 'settings' => array( '_typography' => array( 'font-size' => '51px' ) ) ) );
	$css_file = \Bricks\Assets::$css_dir . '/post-' . $id . '.min.css';
	bricks_check( is_file( $css_file ) && strpos( file_get_contents( $css_file ), '51px' ) !== false, 'native save regenerates external CSS' );
	bricks_check( get_post( $id )->post_content === '', 'Gutenberg content preserved' );
	foreach ( array( 'header', 'footer' ) as $area ) {
		$template_id = wp_insert_post( array( 'post_type' => 'bricks_template', 'post_status' => 'draft', 'post_title' => 'EMCP Bricks Test ' . $area ), true );
		$ids[] = $template_id;
		update_post_meta( $template_id, BRICKS_DB_TEMPLATE_TYPE, $area );
		$template = bricks_run( 'get-page', array( 'post_id' => $template_id ) );
		$template = bricks_run( 'set-page-elements', array( 'post_id' => $template_id, 'expected_hash' => $template['content_hash'], 'elements' => $elements ) );
		bricks_check( $template['area'] === $area && get_post_meta( $template_id, \Bricks\Database::get_bricks_data_key( $area ), true ) === $elements, $area . ' template writes correct area' );
	}
	wp_set_current_user( 0 );
	$denied = wp_get_ability( 'emcp-tools/bricks-get-page' )->execute( array( 'post_id' => $id ) );
	bricks_check( is_wp_error( $denied ), 'anonymous access denied' );
	wp_set_current_user( $administrators[0]->ID );
	$deny_post = static function( $caps, $cap, $user, $args ) use ( $id ) { return 'edit_post' === $cap && (int) ( $args[0] ?? 0 ) === $id ? array( 'do_not_allow' ) : $caps; };
	add_filter( 'map_meta_cap', $deny_post, 10, 4 );
	$denied = wp_get_ability( 'emcp-tools/bricks-get-page' )->execute( array( 'post_id' => $id ) );
	remove_filter( 'map_meta_cap', $deny_post, 10 );
	bricks_check( is_wp_error( $denied ), 'per-post permission denial enforced' );
	update_option( 'emcp_tools_disabled_tools', array( 'emcp-tools/bricks-update-element' ) );
	$denied = wp_get_ability( 'emcp-tools/bricks-update-element' )->execute( array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 'hdg001', 'settings' => array( 'text' => 'Must fail' ) ) );
	bricks_check( is_wp_error( $denied ), 'retained ability respects per-tool switch' );
	update_option( 'emcp_tools_disabled_tools', array() );
	bricks_run( 'get-design-system' );
	bricks_run( 'list-templates' );
	$callback = wp_get_ability( 'emcp-tools/bricks-get-page' );
	update_option( 'emcp_tools_page_builder', '' );
	$denied = $callback->execute( array( 'post_id' => $id ) );
	bricks_check( is_wp_error( $denied ), 'retained ability denied after integration disabled' );
} finally {
	foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
	\Bricks\Assets::$css_dir = $old_css_dir;
	if ( is_dir( $test_css_dir ) ) { rmdir( $test_css_dir ); }
	foreach ( $saved as $key => $value ) { if ( null === $value ) { delete_option( $key ); } else { update_option( $key, $value ); } }
}
