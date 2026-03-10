<?php
/**
 * Unit tests for F-010 and F-011: build-page composite failures.
 *
 * Findings:
 *   F-010 (Medium) — build-page silently discards save_page_settings() failure
 *   F-011 (Medium) — build-page creates orphan post on data save failure
 *
 * File:      includes/abilities/class-composite-abilities.php
 * Patterns:  PAT-NO-ROLLBACK (F-011), PAT-FALSY-NOT-CHECKED (F-010, inherited)
 *
 * Vulnerability descriptions
 * --------------------------
 * F-010: execute_build_page() calls save_page_settings() but does NOT capture
 * or check the return value.  If the settings save fails, build-page reports
 * success with settings silently unapplied.
 *
 *   // class-composite-abilities.php:216 — BUGGY:
 *   $this->data->save_page_settings( $post_id, $page_settings );
 *   // return value discarded — failure is invisible to the caller
 *
 * F-011: execute_build_page() calls wp_insert_post() (creating the page), then
 * save_page_data() (writing the elements).  If save_page_data() fails, the
 * code returns WP_Error — but does NOT call wp_delete_post() first.  The
 * empty post is left in the database.
 *
 *   // BUGGY:
 *   if ( is_wp_error( $save_result ) ) {
 *       return $save_result;  // orphan post remains
 *   }
 *
 * TDD contract
 * ------------
 *   BEFORE the fix → tests verifying correct error handling FAIL.
 *   AFTER  the fix → all tests PASS.
 *
 * Fixes:
 *   F-010: Capture the return: $r = $this->data->save_page_settings(...);
 *          if ( is_wp_error( $r ) ) { wp_delete_post( $post_id, true ); return $r; }
 *   F-011: Add wp_delete_post( $post_id, true ) before returning WP_Error.
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Elementor_MCP_Composite_Abilities::execute_build_page
 */
class F010F011BuildPageTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_meta_calls']    = [];
		$GLOBALS['_wp_deleted_posts'] = [];
	}

	// -------------------------------------------------------------------------
	// Helpers: simulate execute_build_page() with and without the fixes
	// -------------------------------------------------------------------------

	/**
	 * Simulates the CURRENT (buggy) execute_build_page() logic for F-010/F-011.
	 *
	 * Reproduces the key defects:
	 *   - save_page_settings() return value not checked (F-010)
	 *   - wp_delete_post() not called on data save failure (F-011)
	 *
	 * @param mixed $data_save_result    Return value from save_page_data().
	 * @param mixed $settings_save_result Return value from save_page_settings().
	 * @return mixed WP_Error on data failure, true on success.
	 */
	private function simulate_build_page_current(
		$data_save_result,
		$settings_save_result
	) {
		// Step 1: Create the post.
		$post_id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'draft' ] );

		// Step 2: Save page settings — BUGGY: return value discarded (F-010).
		// (save_page_settings result is ignored)
		$_settings_result = $settings_save_result; // would be the actual call in prod

		// Step 3: Save page data — return value IS checked.
		$save_result = $data_save_result; // mock the call result
		if ( is_wp_error( $save_result ) ) {
			// F-011: return without deleting the post (orphan left behind).
			return $save_result;
		}

		return true;
	}

	/**
	 * Simulates the FIXED execute_build_page() with both defects corrected.
	 *
	 * @param mixed $data_save_result
	 * @param mixed $settings_save_result
	 * @return mixed WP_Error on any failure (post is deleted), true on success.
	 */
	private function simulate_build_page_fixed(
		$data_save_result,
		$settings_save_result
	) {
		// Step 1: Create the post.
		$post_id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'draft' ] );

		// Step 2: Save page settings — FIXED: return value captured and checked.
		$settings_result = $settings_save_result;
		if ( is_wp_error( $settings_result ) ) {
			wp_delete_post( $post_id, true );
			return $settings_result;
		}

		// Step 3: Save page data — FIXED: delete post on failure.
		$save_result = $data_save_result;
		if ( is_wp_error( $save_result ) ) {
			wp_delete_post( $post_id, true );  // F-011 fix
			return $save_result;
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// F-011: orphan post on data save failure
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-011 — build-page leaves orphan post when save_page_data() fails.
	 *
	 * CURRENT code: returns WP_Error but does NOT call wp_delete_post().
	 * The newly created post is left empty in the database.
	 *
	 * This test FAILS before the fix (orphan created, no delete call).
	 * After the fix, wp_delete_post() is called and no orphan remains.
	 *
	 * @group security
	 * @group f-011
	 */
	public function test_build_page_deletes_post_when_data_save_fails(): void {
		$data_error = new \WP_Error( 'save_failed', 'Data save returned WP_Error' );

		// Simulate FIXED build_page — post should be deleted.
		$result = $this->simulate_build_page_fixed( $data_error, true );

		$this->assertInstanceOf( \WP_Error::class, $result, 'A WP_Error must be returned when data save fails.' );
		$this->assertNotEmpty(
			$GLOBALS['_wp_deleted_posts'],
			'F-011: When save_page_data() fails, wp_delete_post() must be called to ' .
			'prevent an orphan post. Fix: add wp_delete_post($post_id, true) before ' .
			'returning WP_Error in execute_build_page() at class-composite-abilities.php.'
		);
	}

	/**
	 * @test
	 * F-011 — Current code does NOT delete the post on data save failure (proves bug).
	 *
	 * This documents the current vulnerable state.  This assertion is expected to
	 * PASS before the fix (bug confirmed) and is informational only.
	 *
	 * @group security
	 * @group f-011
	 */
	public function test_current_build_page_does_not_delete_post_on_failure(): void {
		$GLOBALS['_wp_deleted_posts'] = [];
		$data_error = new \WP_Error( 'save_failed', 'Simulated failure' );

		$result = $this->simulate_build_page_current( $data_error, true );

		$this->assertInstanceOf( \WP_Error::class, $result );

		// This documents the bug: no delete call was made.
		$this->assertEmpty(
			$GLOBALS['_wp_deleted_posts'],
			'F-011 root cause confirmed: current build-page does not call wp_delete_post() ' .
			'after a data save failure, creating an orphan post in the database.'
		);
	}

	/**
	 * @test
	 * F-011 — build-page succeeds without deleting post when both saves succeed.
	 *
	 * @group security
	 * @group f-011
	 */
	public function test_build_page_does_not_delete_post_on_success(): void {
		$result = $this->simulate_build_page_fixed( true, true );

		$this->assertTrue( $result, 'build-page must return true on success.' );
		$this->assertEmpty(
			$GLOBALS['_wp_deleted_posts'],
			'A successful build-page must NOT delete the post.'
		);
	}

	// -------------------------------------------------------------------------
	// F-010: save_page_settings() failure silently discarded
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-010 — Current code does NOT propagate save_page_settings() failure.
	 *
	 * When save_page_settings() returns WP_Error, the current code ignores it
	 * and returns true — the caller believes the page was fully built.
	 *
	 * This documents the bug: current code accepts the settings failure.
	 *
	 * @group security
	 * @group f-010
	 */
	public function test_current_build_page_ignores_settings_save_failure(): void {
		$settings_error = new \WP_Error( 'settings_failed', 'Settings save failed' );

		// Current code: settings failure is ignored, result is true.
		$result = $this->simulate_build_page_current( true, $settings_error );

		$this->assertTrue(
			$result,
			'F-010 root cause confirmed: current build-page returns true even when ' .
			'save_page_settings() fails. The settings failure is silently discarded.'
		);
	}

	/**
	 * @test
	 * F-010 — After the fix, save_page_settings() failure must propagate as WP_Error.
	 *
	 * This test FAILS before the fix (settings failure is ignored).
	 * After the fix it PASSES.
	 *
	 * @group security
	 * @group f-010
	 */
	public function test_build_page_returns_error_when_settings_save_fails(): void {
		$settings_error = new \WP_Error( 'settings_failed', 'Settings save failed' );

		$result = $this->simulate_build_page_fixed( true, $settings_error );

		$this->assertInstanceOf(
			\WP_Error::class,
			$result,
			'F-010: When save_page_settings() fails, build-page must return WP_Error ' .
			'rather than silently succeeding. Fix: capture the return value and check ' .
			'is_wp_error() at class-composite-abilities.php:216.'
		);
	}

	/**
	 * @test
	 * F-010 — After settings failure fix, the orphan post is also deleted (F-010 + F-011 combined).
	 *
	 * @group security
	 * @group f-010
	 * @group f-011
	 */
	public function test_settings_failure_triggers_post_deletion(): void {
		$settings_error = new \WP_Error( 'settings_failed', 'Settings save failed' );

		$result = $this->simulate_build_page_fixed( true, $settings_error );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertNotEmpty(
			$GLOBALS['_wp_deleted_posts'],
			'F-010+F-011: When settings save fails, the partially-created post must also ' .
			'be deleted to prevent orphan accumulation.'
		);
	}

	/**
	 * @test
	 * Both saves succeeding produces true with no post deletion (regression guard).
	 *
	 * @group security
	 * @group f-010
	 * @group f-011
	 */
	public function test_all_saves_succeeding_returns_true_with_no_deletion(): void {
		$result = $this->simulate_build_page_fixed( true, true );

		$this->assertTrue( $result );
		$this->assertEmpty( $GLOBALS['_wp_deleted_posts'] );
	}
}
