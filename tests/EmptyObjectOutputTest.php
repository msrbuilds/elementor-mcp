<?php
/**
 * Empty maps in tool results must reach the wire as JSON objects (#184).
 *
 * PHP has one empty array for `[]` and `{}`. get-element-settings returned a widget saved with
 * `"settings":[]` as `"settings":[]`, and Claude Code rejected the result: "data/settings must be
 * object". WordPress's own output check passes it because rest_is_object() accepts any array, so
 * the execute wrapper now turns an empty array into an object wherever the output schema says object.
 *
 * @package EMCP_Tools\Tests
 */

namespace EMCP_Tools\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-schema-compat.php';

/** Exposes the protected wrapper for the test. */
final class Empty_Object_Compat extends \EMCP_Tools_Schema_Compat {
	public static function wrap( callable $cb, ?array $schema ): callable {
		return self::wrap_execute_callback( $cb, 'emcp-tools/test', true, $schema );
	}
}

final class EmptyObjectOutputTest extends TestCase {

	/** The output schema get-element-settings declares. */
	private const ELEMENT_SETTINGS_SCHEMA = array(
		'type'       => 'object',
		'properties' => array(
			'element_id' => array( 'type' => 'string' ),
			'elType'     => array( 'type' => 'string' ),
			'widgetType' => array( 'type' => 'string' ),
			'settings'   => array( 'type' => 'object' ),
		),
	);

	private static function wire( $result ): string {
		return (string) json_encode( $result ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}

	public function test_empty_element_settings_are_sent_as_an_object(): void {
		$fn = Empty_Object_Compat::wrap(
			static function () {
				return array(
					'element_id' => 'bcdae14',
					'elType'     => 'widget',
					'widgetType' => 'post-info',
					'settings'   => array(),
				);
			},
			self::ELEMENT_SETTINGS_SCHEMA
		);

		$this->assertSame(
			'{"element_id":"bcdae14","elType":"widget","widgetType":"post-info","settings":{}}',
			self::wire( $fn( array() ) )
		);
	}

	public function test_filled_settings_and_other_values_are_unchanged(): void {
		$fn = Empty_Object_Compat::wrap(
			static function () {
				return array(
					'element_id' => 'a',
					'settings'   => array( 'header_size' => 'h2', 'gap' => array() ),
				);
			},
			self::ELEMENT_SETTINGS_SCHEMA
		);

		// Undeclared nested keys are left alone: only declared object positions change.
		$this->assertSame( '{"element_id":"a","settings":{"header_size":"h2","gap":[]}}', self::wire( $fn( array() ) ) );
	}

	public function test_lists_items_and_maps_follow_the_schema(): void {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'tags'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'matches' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array( 'settings_preview' => array( 'type' => 'object' ) ),
					),
				),
				'either'  => array( 'type' => array( 'object', 'array' ) ),
				'maybe'   => array( 'type' => array( 'object', 'null' ) ),
				'byKey'   => array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'object' ),
				),
			),
		);
		$fn     = Empty_Object_Compat::wrap(
			static function () {
				return array(
					'tags'    => array(),
					'matches' => array( array( 'settings_preview' => array() ), array() ),
					'either'  => array(),
					'maybe'   => array(),
					'byKey'   => array( 'one' => array() ),
				);
			},
			$schema
		);

		$this->assertSame(
			'{"tags":[],"matches":[{"settings_preview":{}},{}],"either":[],"maybe":{},"byKey":{"one":{}}}',
			self::wire( $fn( array() ) )
		);
	}

	public function test_no_schema_and_errors_pass_through(): void {
		$plain = Empty_Object_Compat::wrap(
			static function () {
				return array( 'settings' => array() );
			},
			null
		);
		$this->assertSame( '{"settings":[]}', self::wire( $plain( array() ) ) );

		$error = Empty_Object_Compat::wrap(
			static function () {
				return new \WP_Error( 'nope', 'nope' );
			},
			self::ELEMENT_SETTINGS_SCHEMA
		);
		$this->assertTrue( is_wp_error( $error( array() ) ) );
	}

	#[RunInSeparateProcess]
	public function test_register_ability_hands_the_output_schema_to_the_wrapper(): void {
		require_once __DIR__ . '/fixtures/register-ability-capture.php';

		\EMCP_Tools_Schema_Compat::register_ability(
			'emcp-tools/get-element-settings',
			array(
				'execute_callback' => static function () {
					return array( 'element_id' => 'x', 'settings' => array() );
				},
				'output_schema'    => self::ELEMENT_SETTINGS_SCHEMA,
				'meta'             => array( 'annotations' => array( 'readonly' => true ) ),
			)
		);

		$callback = $GLOBALS['emcp_test_registered']['emcp-tools/get-element-settings']['execute_callback'];
		$this->assertSame( '{"element_id":"x","settings":{}}', self::wire( $callback( array() ) ) );
	}
}
