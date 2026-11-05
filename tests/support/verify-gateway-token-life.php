<?php
/**
 * Live check of Gateway token life on real WordPress and MySQL.
 *
 * Run with this checkout's plugin copy skipped, so these files are the ones loaded:
 *   wp eval-file tests/support/verify-gateway-token-life.php --skip-plugins=elementor-mcp --path=...
 *
 * Creates one throwaway "EMCP Gateway" client under a private base URL and
 * removes it, with its tokens, at the end.
 *
 * @package EMCP_Tools
 */

$root = dirname( __DIR__, 2 ) . '/';
define( 'EMCP_TOOLS_DIR', $root );
foreach ( array( 'includes/oauth/class-oauth-util.php', 'includes/oauth/class-oauth-metadata.php', 'includes/oauth/class-oauth-store.php', 'includes/oauth/class-oauth-server.php', 'includes/oauth/class-oauth-token.php', 'includes/cloud/class-cloud.php', 'includes/cloud/class-gateway-credential.php' ) as $file ) {
	require_once $root . $file;
}

$base = 'https://emcp-smoke-' . wp_generate_password( 12, false ) . '.invalid';
add_filter( 'emcp_tools_cloud_base_url', static function () use ( $base ) { return $base; } );
EMCP_Tools_OAuth_Store::maybe_install();

$passed = 0;
$failed = 0;
$check  = static function ( $ok, string $name ) use ( &$passed, &$failed ) {
	$ok ? ++$passed : ++$failed;
	WP_CLI::log( ( $ok ? 'ok   ' : 'FAIL ' ) . $name );
};

global $wpdb;
$table = EMCP_Tools_OAuth_Store::tokens_table();
$life  = static function ( string $token ) use ( $wpdb, $table ): int {
	$hash = EMCP_Tools_OAuth_Util::hash_token( $token );
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT expires_at FROM %i WHERE token_hash = %s', $table, $hash ) ) - time();
};
$rotate = static function ( string $token, string $client ) {
	$request = new WP_REST_Request( 'POST', '/token' );
	$request->set_body_params( array( 'grant_type' => 'refresh_token', 'refresh_token' => $token, 'client_id' => $client ) );
	return EMCP_Tools_OAuth_Token::handle_token( $request );
};

$client = EMCP_Tools_Gateway_Credential::ensure_client();
$check( '' !== $client, 'throwaway Gateway client created' );
$other  = '';
try {
	$first = EMCP_Tools_OAuth_Store::issue_token( 'refresh', $client, 1, EMCP_Tools_Gateway_Credential::SCOPE, EMCP_Tools_Gateway_Credential::REFRESH_TTL );
	$res   = $rotate( $first['token'], $client );
	$check( 200 === $res->get_status(), 'real token endpoint rotates the Gateway token' );
	$data   = $res->get_data();
	$second = (string) ( $data['refresh_token'] ?? '' );
	$check( $life( $second ) > 9 * YEAR_IN_SECONDS, 'rotated token keeps its long life' );
	$check( $life( $first['token'] ) <= EMCP_Tools_OAuth_Token::refresh_grace(), 'old token sits in its rotation grace' );

	// A token left at 30 days by an older version gets its life back.
	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET expires_at = %d WHERE token_hash = %s', $table, time() + 86400, EMCP_Tools_OAuth_Util::hash_token( $second ) ) );
	$check( true === EMCP_Tools_Gateway_Credential::extend_existing_tokens(), 'extension reports success' );
	$check( $life( $second ) > 9 * YEAR_IN_SECONDS, 'short live token extended' );
	$check( $life( $first['token'] ) <= EMCP_Tools_OAuth_Token::refresh_grace(), 'token in grace stays retired' );

	$other = 'emcp_smoke_' . wp_generate_password( 12, false );
	$row   = EMCP_Tools_OAuth_Store::issue_token( 'refresh', $other, 1, 'mcp', EMCP_Tools_OAuth_Token::REFRESH_TTL );
	$check( $life( $row['token'] ) <= EMCP_Tools_OAuth_Token::REFRESH_TTL, 'ordinary client stays on 30 days' );
	$check( '' === $wpdb->last_error, 'no database error' );
} finally {
	EMCP_Tools_OAuth_Store::delete_client( $client );
	if ( '' !== $other ) {
		$wpdb->delete( $table, array( 'client_id' => $other ), array( '%s' ) );
	}
	$left = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE client_id IN (%s, %s)', $table, $client, $other ) );
	$check( 0 === $left, 'throwaway rows removed' );
}

$failed ? WP_CLI::error( "$failed failed, $passed passed" ) : WP_CLI::success( "$passed checks passed" );
