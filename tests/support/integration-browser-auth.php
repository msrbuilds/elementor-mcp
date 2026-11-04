<?php
/** Short-lived, revocable local browser session. Never prints cookies. */
$local_ols = 'http://localhost:18880' === home_url() && 'local' === wp_get_environment_type();
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ( ! $local_ols && ! in_array( home_url(), array( 'https://msrplugins.test', 'https://emcp-lite.test', 'https://elementor-mcp.test' ), true ) ) ) { throw new RuntimeException( 'Local test sites only.' ); }
$mode = $args[0] ?? '';
$path = $args[1] ?? '';
if ( ! $path || ! is_dir( dirname( $path ) ) ) { throw new RuntimeException( 'Provide a private temporary cookie-file path.' ); }
if ( 'cleanup' === $mode ) {
	$data = json_decode( file_get_contents( $path ), true );
	if ( ( $data['host'] ?? '' ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) { throw new RuntimeException( 'Wrong site cookie file.' ); }
	WP_Session_Tokens::get_instance( $data['user_id'] )->destroy( $data['token'] );
	unlink( $path );
	WP_CLI::success( 'Temporary browser session revoked and cookie file removed.' );
	return;
}
if ( 'setup' !== $mode || file_exists( $path ) ) { throw new RuntimeException( 'Use setup with a new file or cleanup with its existing file.' ); }
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
$user = $admins[0];
$expires = time() + 1800;
$token = WP_Session_Tokens::get_instance( $user->ID )->create( $expires );
$data = array( 'host' => wp_parse_url( home_url(), PHP_URL_HOST ), 'secure' => ! $local_ols, 'user_id' => $user->ID, 'token' => $token,
	'auth' => array( $local_ols ? AUTH_COOKIE : SECURE_AUTH_COOKIE, wp_generate_auth_cookie( $user->ID, $expires, $local_ols ? 'auth' : 'secure_auth', $token ) ),
	'logged' => array( LOGGED_IN_COOKIE, wp_generate_auth_cookie( $user->ID, $expires, 'logged_in', $token ) ) );
if ( false === file_put_contents( $path, wp_json_encode( $data ) ) ) { WP_Session_Tokens::get_instance( $user->ID )->destroy( $token ); throw new RuntimeException( 'Cannot save temporary cookies.' ); }
WP_CLI::success( 'Temporary browser session written privately.' );
