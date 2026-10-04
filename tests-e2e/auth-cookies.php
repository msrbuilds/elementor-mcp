<?php
/**
 * Write short-lived admin auth cookies for the Playwright smoke, so a local
 * run needs no password:
 *
 *   wp eval-file tests-e2e/auth-cookies.php > tests-e2e/.auth/cookies.json
 *   wp eval-file tests-e2e/auth-cookies.php student1 > tests-e2e/.auth/student.json
 *   EMCP_E2E_COOKIES=tests-e2e/.auth/cookies.json npm run test:e2e
 *
 * Local development only; the cookies expire after three hours.
 *
 * @package EMCP_Tools
 */

if ( ! empty( $args[0] ) ) {
	// A named user (any role), for checks that must run as a student or an instructor.
	$emcp_user = get_user_by( 'login', (string) $args[0] );
	if ( ! $emcp_user ) {
		WP_CLI::error( 'No such user.' );
	}
} else {
	$emcp_admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
		)
	);
	if ( ! $emcp_admins ) {
		WP_CLI::error( 'No administrator found.' );
	}
	$emcp_user = $emcp_admins[0];
}
$emcp_expires = time() + 3 * HOUR_IN_SECONDS;
$emcp_https   = 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );
echo wp_json_encode(
	array(
		'host'   => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
		'secure' => $emcp_https,
		'auth'   => array(
			$emcp_https ? SECURE_AUTH_COOKIE : AUTH_COOKIE,
			wp_generate_auth_cookie( $emcp_user->ID, $emcp_expires, $emcp_https ? 'secure_auth' : 'auth' ),
		),
		'logged' => array( LOGGED_IN_COOKIE, wp_generate_auth_cookie( $emcp_user->ID, $emcp_expires, 'logged_in' ) ),
	)
);
