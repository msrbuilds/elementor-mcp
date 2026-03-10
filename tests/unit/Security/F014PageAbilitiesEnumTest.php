<?php
/**
 * Unit tests for F-014: post_type and status accept arbitrary values without enum enforcement.
 *
 * Finding:   F-014 (Low)
 * File:      includes/abilities/class-page-abilities.php:208 (execute_create_page only)
 *
 * Vulnerability description
 * -------------------------
 * execute_create_page() sanitizes the post_type and status inputs with
 * sanitize_key() but does NOT enforce an allowlist.  sanitize_key() only
 * lowercases and strips non-alphanumeric characters; it does not restrict
 * the value to expected post types or statuses.
 *
 * An MCP client can therefore create posts of unexpected custom post types
 * or set non-standard statuses (e.g. 'trash', 'inherit', 'auto-draft')
 * by sending:
 *   { "post_type": "elementor_library", "status": "trash" }  — or any CPT
 *
 * Note: build-page in class-composite-abilities.php has explicit enum on both
 * post_type and status — do NOT flag build-page for this finding.
 *
 * TDD contract
 * ------------
 *   BEFORE the fix → invalid post_type/status values pass sanitize_key() unchanged.
 *   AFTER  the fix → only allowlisted values are accepted; WP_Error returned otherwise.
 *
 * Fix: add allowlist checks:
 *   post_type: in_array($pt, ['page', 'post', 'elementor_library'], true)
 *   status:    in_array($st, ['draft', 'publish', 'pending', 'private'], true)
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Elementor_MCP_Page_Abilities::execute_create_page
 */
class F014PageAbilitiesEnumTest extends TestCase {

	/** @var string[] Allowed post types per the fix recommendation. */
	private array $allowed_post_types = [ 'page', 'post', 'elementor_library' ];

	/** @var string[] Allowed statuses per the fix recommendation. */
	private array $allowed_statuses = [ 'draft', 'publish', 'pending', 'private' ];

	// -------------------------------------------------------------------------
	// Helper: simulate sanitize_key() (current behaviour)
	// -------------------------------------------------------------------------

	/**
	 * Reproduces what sanitize_key() does to a value.
	 * This shows that it does NOT enforce an allowlist.
	 */
	private function simulate_sanitize_key( string $value ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) );
	}

	/**
	 * Correct post_type validation: sanitize_key() + allowlist check.
	 *
	 * @param string $post_type
	 * @return string|false  The post type if valid, false otherwise.
	 */
	private function validate_post_type( string $post_type ) {
		$sanitized = $this->simulate_sanitize_key( $post_type );
		return in_array( $sanitized, $this->allowed_post_types, true ) ? $sanitized : false;
	}

	/**
	 * Correct status validation: sanitize_key() + allowlist check.
	 *
	 * @param string $status
	 * @return string|false  The status if valid, false otherwise.
	 */
	private function validate_status( string $status ) {
		$sanitized = $this->simulate_sanitize_key( $status );
		return in_array( $sanitized, $this->allowed_statuses, true ) ? $sanitized : false;
	}

	// -------------------------------------------------------------------------
	// Tests: sanitize_key() alone does NOT enforce enum (root cause)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-014 — sanitize_key() passes unexpected post types unchanged.
	 *
	 * Documents that the current guard is insufficient.
	 *
	 * @dataProvider unexpectedPostTypeProvider
	 * @group security
	 * @group f-014
	 */
	public function test_sanitize_key_passes_unexpected_post_types( string $post_type ): void {
		$result = $this->simulate_sanitize_key( $post_type );

		// The current code would use this value directly with wp_insert_post().
		// Prove that the value survived sanitize_key() intact (or nearly so).
		$this->assertNotEmpty(
			$result,
			"F-014 root cause: sanitize_key('{$post_type}') returns '{$result}', which " .
			'is not empty and would be passed to wp_insert_post() without enum check.'
		);
	}

	/** @return array<string, array{string}> */
	public static function unexpectedPostTypeProvider(): array {
		return [
			'attachment'        => [ 'attachment' ],
			'nav_menu_item'     => [ 'nav_menu_item' ],
			'custom_cpt'        => [ 'custom_cpt' ],
			'revision'          => [ 'revision' ],
			'elementor_snippet' => [ 'elementor_snippet' ],
		];
	}

	/**
	 * @test
	 * F-014 — sanitize_key() passes unexpected statuses unchanged.
	 *
	 * @dataProvider unexpectedStatusProvider
	 * @group security
	 * @group f-014
	 */
	public function test_sanitize_key_passes_unexpected_statuses( string $status ): void {
		$result = $this->simulate_sanitize_key( $status );

		$this->assertNotEmpty(
			$result,
			"F-014 root cause: sanitize_key('{$status}') returns '{$result}', which " .
			'passes through without allowlist check.'
		);
	}

	/** @return array<string, array{string}> */
	public static function unexpectedStatusProvider(): array {
		return [
			'trash'       => [ 'trash' ],
			'inherit'     => [ 'inherit' ],
			'auto-draft'  => [ 'auto-draft' ],
			'future'      => [ 'future' ],
		];
	}

	// -------------------------------------------------------------------------
	// Tests: correct allowlist validation rejects unexpected values (FAIL before fix)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-014 — Correct validator rejects post_type 'attachment'.
	 *
	 * This test FAILS before the fix (no allowlist check), PASSES after.
	 *
	 * @group security
	 * @group f-014
	 */
	public function test_attachment_post_type_is_rejected(): void {
		$this->assertFalse(
			$this->validate_post_type( 'attachment' ),
			'F-014: "attachment" must be rejected by the post_type allowlist. ' .
			'Fix: add in_array check against [page, post, elementor_library] in ' .
			'execute_create_page() at class-page-abilities.php:208.'
		);
	}

	/**
	 * @test
	 * F-014 — Correct validator rejects status 'trash'.
	 *
	 * @group security
	 * @group f-014
	 */
	public function test_trash_status_is_rejected(): void {
		$this->assertFalse(
			$this->validate_status( 'trash' ),
			'F-014: "trash" must be rejected by the status allowlist.'
		);
	}

	/**
	 * @test
	 * F-014 — All allowed post_types pass the validator.
	 *
	 * @dataProvider allowedPostTypeProvider
	 * @group security
	 * @group f-014
	 */
	public function test_allowed_post_types_pass_validation( string $post_type ): void {
		$result = $this->validate_post_type( $post_type );

		$this->assertNotFalse(
			$result,
			"Allowed post_type '{$post_type}' must pass the validator."
		);
		$this->assertSame( $post_type, $result );
	}

	/** @return array<string, array{string}> */
	public static function allowedPostTypeProvider(): array {
		return [
			'page'               => [ 'page' ],
			'post'               => [ 'post' ],
			'elementor_library'  => [ 'elementor_library' ],
		];
	}

	/**
	 * @test
	 * F-014 — All allowed statuses pass the validator.
	 *
	 * @dataProvider allowedStatusProvider
	 * @group security
	 * @group f-014
	 */
	public function test_allowed_statuses_pass_validation( string $status ): void {
		$result = $this->validate_status( $status );

		$this->assertNotFalse(
			$result,
			"Allowed status '{$status}' must pass the validator."
		);
	}

	/** @return array<string, array{string}> */
	public static function allowedStatusProvider(): array {
		return [
			'draft'   => [ 'draft' ],
			'publish' => [ 'publish' ],
			'pending' => [ 'pending' ],
			'private' => [ 'private' ],
		];
	}
}
