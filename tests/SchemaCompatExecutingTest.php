<?php
/**
 * EMCP_Tools_Schema_Compat::executing(): true only while an EMCP tool callback runs, and back to
 * false after any outcome. Core's WP_Ability::execute() skips wp_after_execute_ability on an error
 * result, a failed output check or an exception, so guards that must know "an EMCP tool is running"
 * read this counter, kept in EMCP's own execute wrapper with a finally.
 *
 * @package EMCP_Tools\Tests
 */

namespace EMCP_Tools\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-schema-compat.php';

/** Exposes the protected wrapper for the test. */
final class Executing_Compat extends \EMCP_Tools_Schema_Compat {
	public static function wrap( callable $cb, bool $readonly = true ): callable {
		return self::wrap_execute_callback( $cb, 'emcp-tools/test', $readonly );
	}
}

final class SchemaCompatExecutingTest extends TestCase {

	public function test_true_inside_the_callback_and_false_after_success(): void {
		$seen = null;
		$fn   = Executing_Compat::wrap(
			static function () use ( &$seen ) {
				$seen = \EMCP_Tools_Schema_Compat::executing();
				return array( 'ok' => true );
			}
		);
		$this->assertFalse( \EMCP_Tools_Schema_Compat::executing() );
		$fn( array() );
		$this->assertTrue( $seen );
		$this->assertFalse( \EMCP_Tools_Schema_Compat::executing() );
	}

	public function test_false_after_an_error_result(): void {
		$fn = Executing_Compat::wrap(
			static function () {
				return new \WP_Error( 'nope', 'nope' );
			}
		);
		$this->assertTrue( is_wp_error( $fn( array() ) ) );
		$this->assertFalse( \EMCP_Tools_Schema_Compat::executing() );
	}

	public function test_false_after_an_exception(): void {
		$fn = Executing_Compat::wrap(
			static function () {
				throw new \RuntimeException( 'boom' );
			}
		);
		try {
			$fn( array() );
			$this->fail( 'expected the exception' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'boom', $e->getMessage() );
		}
		$this->assertFalse( \EMCP_Tools_Schema_Compat::executing() );
	}

	public function test_nested_calls_count(): void {
		$inner = Executing_Compat::wrap(
			static function () {
				return array( 'inner' => \EMCP_Tools_Schema_Compat::executing() );
			}
		);
		$after_inner = null;
		$outer       = Executing_Compat::wrap(
			static function () use ( $inner, &$after_inner ) {
				$inner( array() );
				$after_inner = \EMCP_Tools_Schema_Compat::executing();
				return array( 'ok' => true );
			}
		);
		$outer( array() );
		$this->assertTrue( $after_inner, 'still executing in the outer call after the inner one ended' );
		$this->assertFalse( \EMCP_Tools_Schema_Compat::executing() );
	}
}
