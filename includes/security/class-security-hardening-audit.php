<?php
/**
 * Configuration / hardening audit.
 *
 * Every evaluate_*() is pure (unit-tested). run() gathers live config values
 * and performs ONE loopback GET to the site host to read security headers and
 * the generator meta tag. Read-only.
 *
 * Ported from upstream msrbuilds/elementor-mcp (v3.0.0), adapted to this fork's
 * class/helper naming (the upstream rename to emcp-tools is not adopted).
 *
 * @package Elementor_MCP
 * @since   1.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 1.12.0
 */
class Elementor_MCP_Security_Hardening_Audit {

	const FETCH_TIMEOUT = 8;

	public function evaluate_file_edit( bool $disallowed ): array {
		return $disallowed
			? Elementor_MCP_Security_Finding::make( 'harden_file_edit', 'hardening', 'File editor', 'pass', true, 'The built-in theme/plugin file editor is disabled.' )
			: Elementor_MCP_Security_Finding::make( 'harden_file_edit', 'hardening', 'File editor', 'warning', false, 'The theme/plugin file editor is enabled.', 'Add define( "DISALLOW_FILE_EDIT", true ); to wp-config.php so a compromised admin account cannot edit PHP from the dashboard.' );
	}

	public function evaluate_debug_display( bool $on, string $environment ): array {
		if ( ! $on ) {
			return Elementor_MCP_Security_Finding::make( 'harden_debug_display', 'hardening', 'Debug output', 'pass', false, 'WP_DEBUG_DISPLAY is off.' );
		}
		if ( 'production' === $environment ) {
			return Elementor_MCP_Security_Finding::make( 'harden_debug_display', 'hardening', 'Debug output', 'warning', true, 'Debug output is shown to visitors in production.', 'Set WP_DEBUG_DISPLAY to false in production; on-screen errors leak paths and internals.' );
		}
		return Elementor_MCP_Security_Finding::make( 'harden_debug_display', 'hardening', 'Debug output', 'info', true, sprintf( 'Debug output is on (environment: %s).', $environment ) );
	}

	public function evaluate_admin_user( bool $exists ): array {
		return $exists
			? Elementor_MCP_Security_Finding::make( 'harden_admin_user', 'hardening', 'Default admin username', 'warning', true, 'A user named "admin" exists.', 'Create a new administrator with a unique username and remove or demote the "admin" account; it is the #1 brute-force target.' )
			: Elementor_MCP_Security_Finding::make( 'harden_admin_user', 'hardening', 'Default admin username', 'pass', false, 'No user named "admin".' );
	}

	public function evaluate_xmlrpc( bool $enabled ): array {
		return $enabled
			? Elementor_MCP_Security_Finding::make( 'harden_xmlrpc', 'hardening', 'XML-RPC', 'warning', true, 'XML-RPC is enabled.', 'If you do not use the Jetpack/app XML-RPC API, disable it (a security plugin or server rule) to remove a brute-force and pingback amplification vector.' )
			: Elementor_MCP_Security_Finding::make( 'harden_xmlrpc', 'hardening', 'XML-RPC', 'pass', false, 'XML-RPC is disabled.' );
	}

	public function evaluate_version_disclosure( bool $readme_present, bool $generator_meta ): array {
		if ( $readme_present || $generator_meta ) {
			return Elementor_MCP_Security_Finding::make( 'harden_version_disclosure', 'hardening', 'Version disclosure', 'warning', array( 'readme' => $readme_present, 'generator' => $generator_meta ), 'The WordPress version is discoverable (readme.html and/or the generator meta tag).', 'Delete readme.html after upgrades and remove the generator meta tag so attackers cannot fingerprint your version.' );
		}
		return Elementor_MCP_Security_Finding::make( 'harden_version_disclosure', 'hardening', 'Version disclosure', 'pass', false, 'No obvious WordPress version disclosure detected.' );
	}

	public function evaluate_https( string $home_url ): array {
		$scheme = strtolower( (string) wp_parse_url( $home_url, PHP_URL_SCHEME ) );
		return 'https' === $scheme
			? Elementor_MCP_Security_Finding::make( 'harden_https', 'hardening', 'HTTPS', 'pass', $home_url, 'The site URL uses HTTPS.' )
			: Elementor_MCP_Security_Finding::make( 'harden_https', 'hardening', 'HTTPS', 'warning', $home_url, 'The site is not served over HTTPS.', 'Install a TLS certificate and move Site Address to https:// — plain HTTP exposes logins and cookies.' );
	}

	/**
	 * @param array<string,string> $headers Lower-cased response header map.
	 */
	public function evaluate_security_headers( array $headers ): array {
		$wanted  = array( 'x-frame-options', 'x-content-type-options', 'strict-transport-security', 'content-security-policy' );
		$missing = array();
		foreach ( $wanted as $h ) {
			if ( empty( $headers[ $h ] ) ) {
				$missing[] = $h;
			}
		}
		if ( empty( $missing ) ) {
			return Elementor_MCP_Security_Finding::make( 'harden_security_headers', 'hardening', 'Security headers', 'pass', array(), 'All checked security headers are present.' );
		}
		return Elementor_MCP_Security_Finding::make( 'harden_security_headers', 'hardening', 'Security headers', 'warning', $missing, sprintf( 'Missing security headers: %s.', implode( ', ', $missing ) ), 'Add the missing headers (X-Frame-Options, X-Content-Type-Options, Strict-Transport-Security, Content-Security-Policy) at the server or via a security plugin.' );
	}

	/**
	 * Non-scoring finding for when the loopback fetch itself failed, so no headers
	 * were received. Without this, an empty header map from a failed fetch would be
	 * read as "every security header is missing" and wrongly penalize the score.
	 */
	public function evaluate_security_headers_unavailable(): array {
		return Elementor_MCP_Security_Finding::make( 'harden_security_headers', 'hardening', 'Security headers', 'info', array(), 'Could not fetch a loopback response, so security headers were not audited (the request failed — often a blocked loopback, HTTP basic auth, or local DNS).', 'Re-run where the site can reach itself over HTTP so X-Frame-Options, X-Content-Type-Options, Strict-Transport-Security, and Content-Security-Policy can be checked.' );
	}

	/**
	 * Choose the header finding from a fetch result: score the headers only when the
	 * loopback succeeded; otherwise emit the non-scoring "unavailable" info finding.
	 *
	 * @param array $fetch { ok: bool, headers: array<string,string> }
	 * @return array
	 */
	public function evaluate_headers_finding( array $fetch ): array {
		if ( empty( $fetch['ok'] ) ) {
			return $this->evaluate_security_headers_unavailable();
		}
		return $this->evaluate_security_headers( (array) ( $fetch['headers'] ?? array() ) );
	}

	/**
	 * Live gather + one loopback GET.
	 *
	 * @return array { findings: Finding[], headers_fetch: array{ ok: bool, error: ?string } }
	 */
	public function run(): array {
		$findings = array();

		$disallow_edit = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
		$findings[]    = $this->evaluate_file_edit( $disallow_edit );

		$debug_display = defined( 'WP_DEBUG_DISPLAY' ) ? (bool) WP_DEBUG_DISPLAY : ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		$env           = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$findings[]    = $this->evaluate_debug_display( $debug_display, $env );

		$admin_exists = function_exists( 'username_exists' ) && username_exists( 'admin' );
		$findings[]   = $this->evaluate_admin_user( (bool) $admin_exists );

		$xmlrpc     = (bool) apply_filters( 'xmlrpc_enabled', true ) && file_exists( ABSPATH . 'xmlrpc.php' );
		$findings[] = $this->evaluate_xmlrpc( $xmlrpc );

		$findings[] = $this->evaluate_https( home_url() );

		// One loopback GET for headers + generator meta.
		$fetch  = $this->fetch_home();
		$findings[] = $this->evaluate_headers_finding( $fetch );
		$findings[] = $this->evaluate_version_disclosure( file_exists( ABSPATH . 'readme.html' ), $fetch['generator'] );

		return array(
			'findings'      => $findings,
			'headers_fetch' => array( 'ok' => $fetch['ok'], 'error' => $fetch['error'] ),
		);
	}

	/** @return array{ ok: bool, headers: array<string,string>, generator: bool, error: ?string } */
	private function fetch_home(): array {
		$res = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'     => self::FETCH_TIMEOUT,
				'redirection' => 2,
				'user-agent'  => 'Elementor-MCP-Security-Scanner/' . ( defined( 'ELEMENTOR_MCP_VERSION' ) ? ELEMENTOR_MCP_VERSION : '0' ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'headers' => array(), 'generator' => false, 'error' => $res->get_error_message() );
		}
		$headers = $this->normalize_headers( wp_remote_retrieve_headers( $res ) );
		$body    = (string) wp_remote_retrieve_body( $res );
		$gen     = (bool) preg_match( '/<meta[^>]+name=["\']generator["\'][^>]+wordpress/i', $body );
		return array( 'ok' => true, 'headers' => $headers, 'generator' => $gen, 'error' => null );
	}

	private function normalize_headers( $headers ): array {
		$out = array();
		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$headers = $headers->getAll();
		}
		if ( ! is_array( $headers ) ) {
			return $out;
		}
		foreach ( $headers as $k => $v ) {
			$out[ strtolower( (string) $k ) ] = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
		}
		return $out;
	}
}
