<?php
/**
 * Live check for the path-based OAuth issuer (ticket 156). Walks discovery the way
 * an MCP client does while a temporary mu-plugin answers the site-root
 * /.well-known/oauth-authorization-server for another "plugin", then registers a
 * client, exchanges a PKCE code and calls MCP with the Bearer token.
 *
 *   wp eval-file tests/support/verify-oauth-issuer-discovery.php [prefix]
 *
 * `prefix` makes the impostor claim every path under that well-known prefix, not
 * just the root. Local sites only; removes its mu-plugin, client and tokens.
 *
 * @package EMCP_Tools
 */

require_once __DIR__ . '/local-mcp-harness.php';

$mode    = in_array( 'prefix', (array) ( $args ?? array() ), true ) ? 'prefix' : 'exact';
$mu_dir  = WP_CONTENT_DIR . '/mu-plugins';
$made_mu = ! is_dir( $mu_dir );
$mu_file = $mu_dir . '/emcp-test-oauth-impostor.php';
$checks  = 0;
$check   = static function ( $ok, string $label ) use ( &$checks ) {
	if ( ! $ok ) {
		throw new RuntimeException( $label );
	}
	++$checks;
	WP_CLI::log( 'PASS ' . $label );
};
$get     = static function ( string $url, string $method = 'GET', array $extra = array() ) {
	$r = wp_remote_request( $url, array_merge( array( 'method' => $method, 'sslverify' => false, 'redirection' => 0, 'timeout' => 45 ), $extra ) );
	if ( is_wp_error( $r ) ) {
		throw new RuntimeException( $url . ': ' . $r->get_error_message() );
	}
	return $r;
};
$json    = static fn( $r ) => json_decode( wp_remote_retrieve_body( $r ), true );

if ( $made_mu ) {
	wp_mkdir_p( $mu_dir );
}
$match = 'prefix' === $mode ? "0 === strpos( \$path, '/.well-known/oauth-authorization-server' )" : "'/.well-known/oauth-authorization-server' === \$path";
file_put_contents(
	$mu_file,
	"<?php\n// Temporary test impostor: another OAuth plugin owning the shared well-known path.\nadd_action( 'muplugins_loaded', static function () {\n\t\$path = rtrim( (string) parse_url( \$_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );\n\tif ( {$match} ) {\n\t\theader( 'Content-Type: application/json' );\n\t\techo json_encode( array( 'issuer' => 'https://impostor.invalid', 'authorization_endpoint' => 'https://impostor.invalid/authorize', 'token_endpoint' => 'https://impostor.invalid/token' ) );\n\t\texit;\n\t}\n} );\n"
);

$mcp    = new EMCP_Local_Mcp_Test( array() );
$client = '';
try {
	$root = $json( $get( home_url( '/.well-known/oauth-authorization-server' ) ) );
	$check( 'https://impostor.invalid' === ( $root['issuer'] ?? '' ), 'the impostor owns the site-root authorization-server path' );

	// 1. An unauthenticated MCP call answers 401 with EMCP's own metadata URL.
	$r         = $get( rest_url( 'mcp/emcp-tools-server' ), 'POST', array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' ) );
	$challenge = (string) wp_remote_retrieve_header( $r, 'www-authenticate' );
	$check( 401 === wp_remote_retrieve_response_code( $r ) && preg_match( '/resource_metadata="([^"]+)"/', $challenge, $m ), '401 challenge names a resource_metadata URL' );

	// 2. Protected resource metadata names the path issuer.
	$prm    = $json( $get( $m[1] ) );
	$issuer = (string) ( $prm['authorization_servers'][0] ?? '' );
	$check( EMCP_Tools_OAuth_Metadata::issuer() === $issuer && str_ends_with( $issuer, '/emcp-tools/oauth/v1' ), 'protected resource metadata advertises the REST issuer' );

	// 3. Every discovery URL a client derives from that issuer returns EMCP's document.
	$parts  = wp_parse_url( $issuer );
	$origin = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	$urls   = array(
		'RFC 8414 path insertion'    => $origin . '/.well-known/oauth-authorization-server' . $parts['path'],
		'OIDC path insertion'        => $origin . '/.well-known/openid-configuration' . $parts['path'],
		'OIDC path appending'        => $issuer . '/.well-known/openid-configuration',
		'authorization-server appended' => $issuer . '/.well-known/oauth-authorization-server',
	);
	$doc = null;
	foreach ( $urls as $label => $url ) {
		$r    = $get( $url );
		$body = $json( $r );
		$ours = 200 === wp_remote_retrieve_response_code( $r ) && $issuer === ( $body['issuer'] ?? '' );
		if ( 'prefix' === $mode && 'RFC 8414 path insertion' === $label ) {
			$check( ! $ours, 'prefix impostor also takes the path-inserted URL (clients fall back below)' );
			continue;
		}
		$check( $ours, $label . ' returns EMCP\'s document' );
		$doc = $doc ?? $body;
	}

	// 4. Register a client, exchange a PKCE code at the discovered token endpoint, call MCP.
	$r      = $get( $doc['registration_endpoint'], 'POST', array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'client_name' => 'EMCP issuer check ' . wp_generate_uuid4(), 'redirect_uris' => array( 'http://127.0.0.1/emcp-issuer-check' ) ) ) ) );
	$reg    = $json( $r );
	$client = (string) ( $reg['client_id'] ?? '' );
	$check( '' !== $client, 'dynamic client registration at the discovered endpoint' );
	$verifier = bin2hex( random_bytes( 32 ) );
	$code     = EMCP_Tools_OAuth_Store::issue_code( array( 'client_id' => $client, 'user_id' => get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID, 'redirect_uri' => 'http://127.0.0.1/emcp-issuer-check', 'code_challenge' => rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ), 'code_challenge_method' => 'S256', 'scopes' => EMCP_Tools_OAuth_Server::SCOPE, 'resource' => EMCP_Tools_OAuth_Metadata::resource() ) );
	$token    = $json( $get( $doc['token_endpoint'], 'POST', array( 'body' => array( 'grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $client, 'redirect_uri' => 'http://127.0.0.1/emcp-issuer-check', 'code_verifier' => $verifier, 'resource' => EMCP_Tools_OAuth_Metadata::resource() ) ) ) );
	$check( ! empty( $token['access_token'] ), 'PKCE code exchange at the discovered token endpoint' );
	$r = $get( rest_url( 'mcp/emcp-tools-server' ), 'POST', array( 'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'Authorization' => 'Bearer ' . $token['access_token'] ), 'body' => '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"emcp-issuer-check","version":"1"}}}' ) );
	$check( 200 === wp_remote_retrieve_response_code( $r ) && '' !== (string) wp_remote_retrieve_header( $r, 'mcp-session-id' ), 'MCP initialize with the Bearer token' );
	WP_CLI::log( wp_json_encode( array( 'result' => 'PASS', 'mode' => $mode, 'checks' => $checks, 'issuer' => $issuer ) ) );
} finally {
	if ( '' !== $client ) {
		EMCP_Tools_OAuth_Store::delete_client( $client );
	}
	$mcp->close();
	wp_delete_file( $mu_file );
	if ( $made_mu && is_dir( $mu_dir ) && 2 === count( (array) scandir( $mu_dir ) ) ) {
		rmdir( $mu_dir );
	}
}
