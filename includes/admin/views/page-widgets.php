<?php
/**
 * Sandbox tab — thin view=-routed dispatcher.
 *
 * The Sandbox parent page (?page=emcp-tools-widgets) shows a 3-card overview
 * (Blocks | Widgets | PHP Snippets); each pillar's full management UI lives
 * in its own view file under includes/admin/views/sandbox/ and is reached
 * via ?page=emcp-tools-widgets&view=blocks|widgets|snippets. Those views are
 * intentionally hidden from the wp-admin menu (no submenu entries), routed
 * only through this file.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_view = EMCP_Tools_Admin::sandbox_view();
$emcp_map  = array(
	'overview' => 'sandbox/overview.php',
	'blocks'   => 'sandbox/blocks.php',
	'widgets'  => 'sandbox/widgets.php',
	'snippets' => 'sandbox/snippets.php',
);
$emcp_file = EMCP_TOOLS_DIR . 'includes/admin/views/' . ( $emcp_map[ $emcp_view ] ?? $emcp_map['overview'] );

// Export as plugin (Pro module): the view ships in the Pro overlay and only
// resolves while the module is switched on; otherwise fall back to the overview.
if ( 'export' === $emcp_view ) {
	$emcp_file = '';
	if ( class_exists( 'EMCP_Tools_Plugin_Export_Module' ) && EMCP_Tools_Plugin_Export_Module::is_enabled() && class_exists( 'EMCP_Tools_Pro_Loader' ) ) {
		$emcp_file = EMCP_Tools_Pro_Loader::path( 'includes/admin/views/sandbox/export.php' );
	}
	if ( '' === $emcp_file ) {
		$emcp_file = EMCP_TOOLS_DIR . 'includes/admin/views/' . $emcp_map['overview'];
	}
}

if ( file_exists( $emcp_file ) ) {
	include $emcp_file;
} else {
	// Graceful fallback (e.g. blocks.php not yet shipped) — show the overview.
	include EMCP_TOOLS_DIR . 'includes/admin/views/' . $emcp_map['overview'];
}
