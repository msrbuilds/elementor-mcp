<?php
/**
 * @group security-scanner
 * @package Elementor_MCP\Tests\SecurityScanner
 */
namespace Elementor_MCP\Tests\SecurityScanner;

use PHPUnit\Framework\TestCase;

class HardeningAuditTest extends TestCase {

	private function audit(): \Elementor_MCP_Security_Hardening_Audit {
		return new \Elementor_MCP_Security_Hardening_Audit();
	}

	/** @test */
	public function file_edit_disabled_passes_enabled_warns(): void {
		$this->assertSame( 'pass', $this->audit()->evaluate_file_edit( true )['status'] );
		$this->assertSame( 'warning', $this->audit()->evaluate_file_edit( false )['status'] );
	}

	/** @test */
	public function debug_display_on_in_production_warns(): void {
		$this->assertSame( 'warning', $this->audit()->evaluate_debug_display( true, 'production' )['status'] );
		$this->assertSame( 'info', $this->audit()->evaluate_debug_display( true, 'local' )['status'] );
		$this->assertSame( 'pass', $this->audit()->evaluate_debug_display( false, 'production' )['status'] );
	}

	/** @test */
	public function admin_username_present_warns(): void {
		$this->assertSame( 'warning', $this->audit()->evaluate_admin_user( true )['status'] );
		$this->assertSame( 'pass', $this->audit()->evaluate_admin_user( false )['status'] );
	}

	/** @test */
	public function xmlrpc_enabled_warns(): void {
		$this->assertSame( 'warning', $this->audit()->evaluate_xmlrpc( true )['status'] );
		$this->assertSame( 'pass', $this->audit()->evaluate_xmlrpc( false )['status'] );
	}

	/** @test */
	public function version_disclosure_via_readme_warns(): void {
		$this->assertSame( 'warning', $this->audit()->evaluate_version_disclosure( true, false )['status'] );
		$this->assertSame( 'warning', $this->audit()->evaluate_version_disclosure( false, true )['status'] );
		$this->assertSame( 'pass', $this->audit()->evaluate_version_disclosure( false, false )['status'] );
	}

	/** @test */
	public function non_https_home_warns(): void {
		$this->assertSame( 'warning', $this->audit()->evaluate_https( 'http://example.com' )['status'] );
		$this->assertSame( 'pass', $this->audit()->evaluate_https( 'https://example.com' )['status'] );
	}

	/** @test */
	public function security_headers_missing_warns(): void {
		$present = array(
			'x-frame-options'           => 'SAMEORIGIN',
			'x-content-type-options'    => 'nosniff',
			'strict-transport-security' => 'max-age=31536000',
			'content-security-policy'   => "default-src 'self'",
		);
		$this->assertSame( 'pass', $this->audit()->evaluate_security_headers( $present )['status'] );
		$this->assertSame( 'warning', $this->audit()->evaluate_security_headers( array() )['status'] );
	}

	/**
	 * S3: when the loopback fetch itself fails, no headers are received. The audit
	 * must NOT read that as "every security header is missing" and penalize the
	 * score — it emits a single non-scoring info finding instead. Only when the
	 * fetch succeeds are the (possibly missing) headers scored.
	 *
	 * @test
	 */
	public function failed_loopback_fetch_does_not_penalize_header_scoring(): void {
		$a = $this->audit();

		// Fetch failed (ok=false, empty headers) → info, not warning.
		$unavailable = $a->evaluate_headers_finding( array( 'ok' => false, 'headers' => array() ) );
		$this->assertSame( 'info', $unavailable['status'] );
		$this->assertSame( 'harden_security_headers', $unavailable['id'] );
		$this->assertSame( 'info', $a->evaluate_security_headers_unavailable()['status'] );

		// Fetch succeeded but headers genuinely missing → still warning (scored).
		$missing = $a->evaluate_headers_finding( array( 'ok' => true, 'headers' => array() ) );
		$this->assertSame( 'warning', $missing['status'] );

		// Fetch succeeded with all headers present → pass.
		$present = array(
			'x-frame-options'           => 'SAMEORIGIN',
			'x-content-type-options'    => 'nosniff',
			'strict-transport-security' => 'max-age=31536000',
			'content-security-policy'   => "default-src 'self'",
		);
		$this->assertSame( 'pass', $a->evaluate_headers_finding( array( 'ok' => true, 'headers' => $present ) )['status'] );
	}
}
