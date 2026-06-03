<?php
/**
 * Regression tests — atomic (V4) detection.
 *
 * Two separate facts about Elementor's atomic/V4 rollout drive this:
 *
 *  1. `ELEMENTOR_VERSION` can still read 3.x while atomic is active, so a
 *     version_compare gate misses real atomic sites.
 *  2. Atomic tooling is only useful when atomic **writes persist**, and that
 *     happens only once the atomic element *types* (`e-flexbox`/`e-div-block`)
 *     are registered server-side — which is gated by the `e_atomic_elements`
 *     experiment, NOT by the `e_opt_in_v4_page` page-editor opt-in. A site can
 *     have `e_opt_in_v4_page` on while `e_atomic_elements` is off; there the
 *     atomic tools would register but `Document::save()` silently drops the
 *     unknown elements (write "succeeds", `_elementor_data` stays empty).
 *
 * So is_atomic_supported() keys on element-type registration (or the experiment
 * that produces it), and deliberately ignores the page-opt-in experiment. These
 * tests lock that in. Verified live on Elementor 3.31.5.
 *
 * @group regression
 * @group atomic
 * @package EMCP_Tools\Tests\Regression
 */

namespace EMCP_Tools\Tests\Regression;

use PHPUnit\Framework\TestCase;

class AtomicDetectionRegressionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_active_experiments']       = [];
		$GLOBALS['_registered_element_types'] = [];
	}

	protected function tearDown(): void {
		$GLOBALS['_active_experiments']       = [];
		$GLOBALS['_registered_element_types'] = [];
		parent::tearDown();
	}

	/**
	 * @test
	 * Classic site: no atomic experiment, no atomic element types → not atomic.
	 */
	public function classic_site_is_not_atomic(): void {
		$this->assertFalse( \EMCP_Tools_Atomic_Props::is_atomic_supported() );
	}

	/**
	 * @test
	 * Authoritative signal: the atomic element types are registered → atomic,
	 * even with no experiment flag readable.
	 *
	 * @dataProvider registered_type_provider
	 */
	public function registered_atomic_element_type_marks_atomic_supported( string $slug ): void {
		$GLOBALS['_registered_element_types'] = [ $slug ];
		$this->assertTrue( \EMCP_Tools_Atomic_Props::is_atomic_supported(), "Registered type '$slug' should enable atomic." );
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function registered_type_provider(): array {
		return [
			'e-flexbox'   => [ 'e-flexbox' ],
			'e-div-block' => [ 'e-div-block' ],
		];
	}

	/**
	 * @test
	 * The experiments that actually register atomic element types → atomic.
	 *
	 * @dataProvider atomic_experiment_provider
	 */
	public function atomic_element_experiment_marks_atomic_supported( string $slug ): void {
		$GLOBALS['_active_experiments'] = [ $slug ];
		$this->assertTrue( \EMCP_Tools_Atomic_Props::is_atomic_supported(), "Experiment '$slug' should enable atomic." );
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function atomic_experiment_provider(): array {
		return [
			'e_atomic_elements' => [ 'e_atomic_elements' ],
			'atomic_widgets'    => [ 'atomic_widgets' ],
		];
	}

	/**
	 * @test
	 *
	 * THE KEY REGRESSION: the V4 *page* opt-in alone, with no atomic element
	 * types registered, must NOT report atomic — otherwise the tools register
	 * but writes silently no-op.
	 */
	public function v4_page_optin_alone_is_not_atomic(): void {
		$GLOBALS['_active_experiments'] = [ 'e_opt_in_v4_page' ]; // page editor opt-in only
		$this->assertFalse(
			\EMCP_Tools_Atomic_Props::is_atomic_supported(),
			'e_opt_in_v4_page without registered atomic element types must not enable atomic tools.'
		);
	}

	/**
	 * @test
	 * An unrelated active experiment must not be mistaken for atomic.
	 */
	public function unrelated_experiment_is_not_atomic(): void {
		$GLOBALS['_active_experiments'] = [ 'some_other_feature' ];
		$this->assertFalse( \EMCP_Tools_Atomic_Props::is_atomic_supported() );
	}
}
