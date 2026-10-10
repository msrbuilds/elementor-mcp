<?php
/** Loaded at the start of wp-config.php during an explicitly approved update window. */
if ( defined( 'EMCP_UPDATE_INTERNAL' ) && EMCP_UPDATE_INTERNAL ) { return; }
require_once __DIR__ . '/runtime.php';
try {
	$emcp_update_state = EMCP_Update_Runtime::read( __DIR__ . '/state.php' );
	if ( in_array( $emcp_update_state['phase'], EMCP_Update_Runtime::TERMINAL, true ) ) { return; }
	$emcp_update_auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
	$emcp_update_path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	$emcp_update_paths = array_map( static fn( $path ) => $emcp_update_state['base_path'] . $path, $emcp_update_state['paths'] );
	$emcp_update_probe = $_SERVER['HTTP_X_EMCP_UPDATE_PROBE'] ?? '';
	if ( 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && in_array( $emcp_update_path, $emcp_update_paths, true ) && preg_match( '/^[a-f0-9-]{36}$/D', $emcp_update_probe ) && preg_match( '/^Bearer ([a-f0-9]{64})$/D', $emcp_update_auth, $emcp_update_match ) && hash_equals( $emcp_update_state['token_hash'], hash( 'sha256', $emcp_update_match[1] ) ) ) {
		if ( ! defined( 'DISABLE_WP_CRON' ) ) { define( 'DISABLE_WP_CRON', true ); }
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		ob_start();
		register_shutdown_function( static function () use ( $emcp_update_probe ) {
			$error = error_get_last();
			if ( ! $error || ! in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
				header( 'X-EMCP-Update-Probe-Result: ' . $emcp_update_probe );
				header( 'Cache-Control: no-store' );
			}
		} );
		return;
	}
} catch ( Throwable $error ) { /* An unreadable recovery journal must not reopen writes. */ }
http_response_code( 503 );
header( 'Retry-After: 120' );
header( 'Cache-Control: no-store' );
header( 'Content-Type: text/html; charset=utf-8' );
echo '<!doctype html><html lang="en"><title>Maintenance</title><h1>Scheduled maintenance</h1><p>This website is installing verified updates. Please try again shortly.</p></html>';
exit;
