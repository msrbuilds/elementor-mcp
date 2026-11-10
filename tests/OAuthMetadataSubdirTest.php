<?php
/**
 * Public unit test for EMCP_Tools_OAuth_Metadata::strip_home_path() /
 * path_matches() — the fix for OAuth discovery on subdirectory installs.
 *
 * A subdirectory install (WordPress at /gpt-build/) receives the RFC 9728
 * protected-resource request as `/gpt-build/.well-known/oauth-protected-resource`,
 * but the well-known paths are site-root-relative. Without stripping the home
 * path, the advertised metadata URL 404s and OAuth discovery (ChatGPT, Claude,
 * any RFC 9728 client) dead-ends. This pins the path handling.
 *
 * @package EMCP_Tools
 */

require_once dirname( __DIR__ ) . '/includes/class-site-context.php';
require_once dirname( __DIR__ ) . '/includes/oauth/class-oauth-server.php';
require_once dirname( __DIR__ ) . '/includes/oauth/class-oauth-metadata.php';
require_once dirname( __DIR__ ) . '/includes/oauth/class-oauth-bearer.php';
require_once dirname( __DIR__ ) . '/includes/oauth/class-oauth-authorize.php';

class OAuthMetadataSubdirTest extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		emcp_test_reset();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['emcp_test']['home_url'] );
	}

	/** Root install (home path empty): paths pass through unchanged. */
	public function test_root_install_is_a_noop() {
		$GLOBALS['emcp_test']['home_url'] = 'https://example.test';
		$this->assertSame(
			'/.well-known/oauth-protected-resource',
			EMCP_Tools_OAuth_Metadata::strip_home_path( '/.well-known/oauth-protected-resource' )
		);
	}

	/** Subdirectory install: the home-path prefix is stripped so the match works. */
	public function test_subdirectory_prefix_is_stripped() {
		$GLOBALS['emcp_test']['home_url'] = 'https://demo.emcptools.com/gpt-build';
		$this->assertSame(
			'/.well-known/oauth-protected-resource',
			EMCP_Tools_OAuth_Metadata::strip_home_path( '/gpt-build/.well-known/oauth-protected-resource' )
		);
	}

	/** The RFC 9728 resource-scoped variant is stripped + still matches. */
	public function test_subdirectory_resource_scoped_variant() {
		$GLOBALS['emcp_test']['home_url'] = 'https://demo.emcptools.com/gpt-build';
		$stripped = EMCP_Tools_OAuth_Metadata::strip_home_path(
			'/gpt-build/.well-known/oauth-protected-resource/gpt-build/wp-json/mcp/emcp-tools-server'
		);
		$this->assertSame( '/.well-known/oauth-protected-resource/gpt-build/wp-json/mcp/emcp-tools-server', $stripped );
		$this->assertTrue(
			EMCP_Tools_OAuth_Metadata::path_matches( $stripped, EMCP_Tools_OAuth_Metadata::PATH_PROTECTED_RESOURCE )
		);
	}

	/** A non-well-known path under the subdir is left alone (won't match). */
	public function test_unrelated_subdir_path_unchanged() {
		$GLOBALS['emcp_test']['home_url'] = 'https://demo.emcptools.com/gpt-build';
		$this->assertSame(
			'/gpt-build-other/page',
			EMCP_Tools_OAuth_Metadata::strip_home_path( '/gpt-build-other/page' )
		);
	}

	/**
	 * path_matches: exact + OUR resource-scoped path true; a sibling well-known
	 * path and another server's resource suffix false. A second OAuth-capable
	 * MCP plugin with a path-based issuer has its clients request
	 * `/.well-known/oauth-authorization-server/<its issuer path>`; answering
	 * that with EMCP's document fails the client's issuer check before consent.
	 */
	public function test_path_matches_semantics() {
		$base = '/.well-known/oauth-protected-resource';
		$this->assertTrue( EMCP_Tools_OAuth_Metadata::path_matches( $base, $base ) );
		$this->assertTrue( EMCP_Tools_OAuth_Metadata::path_matches( $base . '/wp-json/mcp/emcp-tools-server', $base ) );
		$this->assertTrue( EMCP_Tools_OAuth_Metadata::path_matches( $base . '/wp-json/mcp/emcp-tools-server/', $base ), 'trailing slash tolerated' );
		$this->assertFalse( EMCP_Tools_OAuth_Metadata::path_matches( $base . '-other', $base ) );
		$this->assertFalse( EMCP_Tools_OAuth_Metadata::path_matches( $base . '/wp-json/mcp/x', $base ), 'another server resource is not ours' );
		$this->assertFalse( EMCP_Tools_OAuth_Metadata::path_matches( '/.well-known/oauth-authorization-server/wp-json/tropk-mcp/v1/', '/.well-known/oauth-authorization-server' ), 'a path-based issuer of another plugin must fall through' );
	}

	/** Subdirectory install: the resource-scoped path carries the subdir twice (once stripped as home path) and must still be ours. */
	public function test_path_matches_own_resource_on_a_subdirectory_install() {
		$GLOBALS['emcp_test']['home_url'] = 'https://demo.emcptools.com/gpt-build';
		$base = '/.well-known/oauth-protected-resource';
		$this->assertContains( '/gpt-build/wp-json/mcp/emcp-tools-server', EMCP_Tools_OAuth_Metadata::own_resource_paths() );
		$this->assertContains( '/wp-json/mcp/emcp-tools-server', EMCP_Tools_OAuth_Metadata::own_resource_paths() );
		$stripped = EMCP_Tools_OAuth_Metadata::normalize_request_path( '/gpt-build' . $base . '/gpt-build/wp-json/mcp/emcp-tools-server' );
		$this->assertTrue( EMCP_Tools_OAuth_Metadata::path_matches( $stripped, $base ) );
		$this->assertFalse( EMCP_Tools_OAuth_Metadata::path_matches( $base . '/gpt-build/wp-json/tropk-mcp/v1', $base ) );
	}

	/** Resource comparison normalizes only URI components that are insensitive. */
	public function test_resource_uri_normalization() {
		$this->assertSame(
			'https://example.test/wp-json/mcp/emcp-tools-server',
			EMCP_Tools_OAuth_Metadata::normalize_resource_uri( 'HTTPS://EXAMPLE.TEST/wp-json/mcp/emcp-tools-server/' )
		);
		$this->assertSame( '', EMCP_Tools_OAuth_Metadata::normalize_resource_uri( '/wp-json/mcp/emcp-tools-server' ) );
		$this->assertSame( '', EMCP_Tools_OAuth_Metadata::normalize_resource_uri( 'https://example.test/resource#fragment' ) );
		$this->assertSame( '', EMCP_Tools_OAuth_Metadata::normalize_resource_uri( 'https://user@example.test/resource' ) );
	}

	/** OAuth codes and tokens must not be accepted for a different audience. */
	public function test_resource_match_is_bound_to_the_canonical_mcp_endpoint() {
		$resource = EMCP_Tools_OAuth_Metadata::resource();
		$this->assertTrue( EMCP_Tools_OAuth_Metadata::resource_matches( $resource . '/' ) );
		$this->assertFalse( EMCP_Tools_OAuth_Metadata::resource_matches( 'https://attacker.example/wp-json/mcp/emcp-tools-server' ) );
		$this->assertFalse( EMCP_Tools_OAuth_Metadata::resource_matches( $resource . '?audience=other' ) );
	}

	/** The challenge must never advertise the shared bare metadata path (#130). */
	public function test_challenge_advertises_emcp_specific_metadata_url() {
		$metadata_url = EMCP_Tools_OAuth_Metadata::protected_resource_url();
		$challenge    = EMCP_Tools_OAuth_Bearer::challenge_value();

		$this->assertStringContainsString( 'resource_metadata="' . $metadata_url . '"', $challenge );
		$this->assertStringNotContainsString( 'resource_metadata="' . EMCP_Tools_OAuth_Metadata::issuer() . EMCP_Tools_OAuth_Metadata::PATH_PROTECTED_RESOURCE . '"', $challenge );
		$this->assertStringContainsString( 'scope="mcp"', $challenge );
	}

	/** A valid-looking document for another plugin must fail diagnostics (#130). */
	public function test_metadata_identifier_rejects_another_plugins_document() {
		$expected = EMCP_Tools_OAuth_Metadata::resource();
		$this->assertTrue( EMCP_Tools_OAuth_Metadata::identifier_matches( $expected, $expected ) );
		$this->assertFalse(
			EMCP_Tools_OAuth_Metadata::identifier_matches(
				EMCP_Tools_OAuth_Metadata::issuer() . '/wp-json/mcp/another-plugin',
				$expected
			)
		);
	}

	/**
	 * The issuer is EMCP's own REST namespace (ticket 156, and the Tropk and
	 * SiteGround cases before it): a root issuer sends clients to the shared bare
	 * /.well-known/oauth-authorization-server, which another OAuth plugin can own.
	 */
	public function test_issuer_is_the_oauth_rest_namespace() {
		$GLOBALS['emcp_test']['home_url'] = 'https://galar.test';
		$this->assertSame( 'https://galar.test/wp-json/emcp-tools/oauth/v1', EMCP_Tools_OAuth_Metadata::issuer() );
		$this->assertSame( 'https://galar.test/.well-known/oauth-authorization-server/wp-json/emcp-tools/oauth/v1', EMCP_Tools_OAuth_Metadata::issuer_metadata_url() );
		$doc = EMCP_Tools_OAuth_Metadata::protected_resource_document();
		$this->assertSame( array( 'https://galar.test/wp-json/emcp-tools/oauth/v1' ), $doc['authorization_servers'] );
		$this->assertSame( EMCP_Tools_OAuth_Metadata::issuer(), EMCP_Tools_OAuth_Metadata::authorization_server_document()['issuer'] );
	}

	/** Plain permalinks give ?rest_route=, which an RFC 8414 issuer may not carry: keep the site-root issuer there. */
	public function test_plain_permalinks_keep_the_origin_issuer() {
		$GLOBALS['emcp_test']['home_url']      = 'https://plain.test';
		$GLOBALS['emcp_test']['rest_url_base'] = 'https://plain.test/?rest_route=';
		try {
			$this->assertSame( 'https://plain.test', EMCP_Tools_OAuth_Metadata::issuer() );
			$this->assertSame( array(), EMCP_Tools_OAuth_Metadata::own_issuer_paths() );
			$this->assertFalse( EMCP_Tools_OAuth_Metadata::issuer_path_matches( '/.well-known/openid-configuration', '/.well-known/openid-configuration' ) );
		} finally {
			unset( $GLOBALS['emcp_test']['rest_url_base'] );
		}
	}

	/** Path-inserted discovery for EMCP's issuer is answered; the bare OIDC root and other issuers are not. */
	public function test_issuer_path_discovery_matching() {
		$GLOBALS['emcp_test']['home_url'] = 'https://galar.test';
		$as   = '/.well-known/oauth-authorization-server';
		$oidc = '/.well-known/openid-configuration';
		$this->assertTrue( EMCP_Tools_OAuth_Metadata::path_matches( $as . '/wp-json/emcp-tools/oauth/v1', $as ) );
		$this->assertTrue( EMCP_Tools_OAuth_Metadata::path_matches( $as . '/wp-json/emcp-tools/oauth/v1/', $as ), 'trailing slash tolerated' );
		$this->assertTrue( EMCP_Tools_OAuth_Metadata::path_matches( $as, $as ), 'the bare root is still answered when EMCP owns it' );
		$this->assertTrue( EMCP_Tools_OAuth_Metadata::issuer_path_matches( $oidc . '/wp-json/emcp-tools/oauth/v1', $oidc ) );
		$this->assertFalse( EMCP_Tools_OAuth_Metadata::issuer_path_matches( $oidc, $oidc ), 'the site OIDC root belongs to its identity provider' );
		$this->assertFalse( EMCP_Tools_OAuth_Metadata::issuer_path_matches( $oidc . '/wp-json/tropk-mcp/v1', $oidc ) );
		$this->assertFalse( EMCP_Tools_OAuth_Metadata::path_matches( $as . '/wp-json/emcp-tools/oauth/v2', $as ) );
	}

	/** Subdirectory install: the issuer path keeps the subdirectory, and the home-stripped form is accepted too. */
	public function test_issuer_paths_on_a_subdirectory_install() {
		$GLOBALS['emcp_test']['home_url'] = 'https://demo.emcptools.com/gpt-build';
		$this->assertSame( 'https://demo.emcptools.com/gpt-build/wp-json/emcp-tools/oauth/v1', EMCP_Tools_OAuth_Metadata::issuer() );
		$this->assertContains( '/gpt-build/wp-json/emcp-tools/oauth/v1', EMCP_Tools_OAuth_Metadata::own_issuer_paths() );
		$this->assertSame( 'https://demo.emcptools.com/.well-known/oauth-authorization-server/gpt-build/wp-json/emcp-tools/oauth/v1', EMCP_Tools_OAuth_Metadata::issuer_metadata_url() );
	}
}
