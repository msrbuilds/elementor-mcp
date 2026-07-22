<?php
/**
 * JSON Schema compatibility layer for the tool input/output schemas.
 *
 * Different MCP clients accept different JSON Schema dialects, so every ability
 * schema is normalized here before it reaches the Abilities API:
 *   - sanitize(): strips empty enum values and keeps empty `properties` as `{}`
 *     (Gemini / Antigravity reject those).
 *   - strictify(): optional, opt-in OpenAI strict function-calling form — every
 *     property required, optionals nullable, additionalProperties:false (CrewAI
 *     and other OpenAI-compatible stacks require it; it would break Gemini, so
 *     it's gated behind the `emcp_tools_strict_schemas` option/filter).
 *
 * `register_ability()` is the single entry point all ability classes use to
 * register a tool; the global `emcp_tools_register_ability()` shim (defined at
 * the bottom of this file) forwards to it so call sites stay terse.
 *
 * @package EMCP_Tools
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema normalization + ability registration.
 *
 * @since 2.1.0
 */
class EMCP_Tools_Schema_Compat {

	const STRICT_OPTION = 'emcp_tools_strict_schemas';

	/**
	 * Registers an ability with normalized (and optionally strict) schemas.
	 *
	 * @since 2.1.0 (extracted from emcp_tools_register_ability, since 1.4.3)
	 *
	 * @param string $name The ability name.
	 * @param array  $args The ability arguments.
	 * @return mixed The result of wp_register_ability().
	 */
	public static function register_ability( string $name, array $args ) {
		$strict = self::use_strict_schemas();

		if ( isset( $args['input_schema'] ) && is_array( $args['input_schema'] ) ) {
			$args['input_schema'] = self::sanitize( $args['input_schema'] );
			if ( $strict ) {
				$args['input_schema'] = self::strictify( $args['input_schema'] );
			}
		}
		if ( isset( $args['output_schema'] ) && is_array( $args['output_schema'] ) ) {
			$args['output_schema'] = self::sanitize( $args['output_schema'] );
		}

		if ( isset( $args['execute_callback'] ) && is_callable( $args['execute_callback'] ) ) {
			$args['execute_callback'] = self::wrap_execute_callback( $args['execute_callback'] );
		}

		return wp_register_ability( $name, $args );
	}

	/**
	 * Wraps a tool's execute callback so its return value is always a shape the
	 * MCP `structuredContent` field accepts.
	 *
	 * The MCP schema types `structuredContent` as `{ [key: string]: unknown }`,
	 * a JSON object. The adapter passes a tool's return value straight through
	 * to that field, so a tool returning a JSON *list* produces a response that
	 * strict clients reject with a dictionary-validation error.
	 *
	 * This bites any tool that forwards another API's payload verbatim. The
	 * WooCommerce dispatcher returns `WP_REST_Response::get_data()` as-is, and
	 * plenty of `wc/v3` routes answer with a top-level array (product lists,
	 * order lists, `reports/products/totals`, and so on).
	 *
	 * Fixing it here rather than in the vendored adapter means it survives
	 * `composer update`, and it covers every ability rather than the one route
	 * someone happened to hit. Reported against 3.6.0 with `woo-read`,
	 * `report-products-totals`.
	 *
	 * @since 3.6.1
	 *
	 * @param callable $callback The ability's execute callback.
	 * @return callable
	 */
	protected static function wrap_execute_callback( callable $callback ): callable {
		return static function () use ( $callback ) {
			return self::normalize_result( $callback( ...func_get_args() ) );
		};
	}

	/**
	 * Coerces a tool result into a JSON object, leaving anything already
	 * object-shaped untouched.
	 *
	 * Associative arrays and objects pass through unchanged, which is what the
	 * overwhelming majority of abilities return. Lists, scalars and null are
	 * wrapped in a `data` key. `WP_Error` is returned untouched so the adapter's
	 * error handling still sees it.
	 *
	 * Note that PHP cannot distinguish an empty list from an empty map, so
	 * `array()` is wrapped too. That is deliberate: unwrapped it serialises to
	 * `[]`, which is exactly the invalid shape this guards against.
	 *
	 * @since 3.6.1
	 *
	 * @param mixed $result The raw ability result.
	 * @return mixed
	 */
	public static function normalize_result( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Already a JSON object: a string-keyed array, or any object that is not
		// an error. Leave the shape the ability intended.
		if ( is_array( $result ) && ! array_is_list( $result ) ) {
			return $result;
		}
		if ( is_object( $result ) ) {
			return $result;
		}

		return array( 'data' => $result );
	}

	/**
	 * Whether to emit OpenAI-strict-compatible tool schemas. OFF by default;
	 * opt-in via the Connection-tab toggle or the `emcp_tools_strict_schemas`
	 * filter. (GitHub #42)
	 *
	 * @since 2.1.0
	 *
	 * @return bool
	 */
	public static function use_strict_schemas(): bool {
		$enabled = '1' === (string) get_option( self::STRICT_OPTION, '0' );
		return (bool) apply_filters( self::STRICT_OPTION, $enabled );
	}

	/**
	 * Recursively removes empty strings from enum arrays and keeps empty
	 * `properties` as a JSON object. (Gemini / Antigravity compatibility, #21)
	 *
	 * @since 2.1.0 (extracted from emcp_tools_sanitize_schema, since 1.4.3)
	 *
	 * @param array $schema A JSON Schema array.
	 * @return array
	 */
	public static function sanitize( array $schema ): array {
		if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) ) {
			$schema['enum'] = array_values(
				array_filter(
					$schema['enum'],
					static function ( $value ) {
						return '' !== $value;
					}
				)
			);
			if ( empty( $schema['enum'] ) ) {
				unset( $schema['enum'] );
			}
		}

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			if ( empty( $schema['properties'] ) ) {
				$schema['properties'] = new \stdClass();
			} else {
				foreach ( $schema['properties'] as $key => $prop ) {
					if ( is_array( $prop ) ) {
						$schema['properties'][ $key ] = self::sanitize( $prop );
					}
				}
			}
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$schema['items'] = self::sanitize( $schema['items'] );
		}

		foreach ( array( 'allOf', 'oneOf', 'anyOf' ) as $keyword ) {
			if ( isset( $schema[ $keyword ] ) && is_array( $schema[ $keyword ] ) ) {
				foreach ( $schema[ $keyword ] as $i => $sub ) {
					if ( is_array( $sub ) ) {
						$schema[ $keyword ][ $i ] = self::sanitize( $sub );
					}
				}
			}
		}

		return $schema;
	}

	/**
	 * Rewrites a JSON Schema to satisfy OpenAI strict function-calling: every
	 * property required, originally-optional properties nullable, and objects
	 * with declared properties get additionalProperties:false. Free-form objects
	 * (no declared properties) are left untouched. (GitHub #42)
	 *
	 * @since 2.1.0 (extracted from emcp_tools_strictify_schema)
	 *
	 * @param array $schema A JSON Schema array.
	 * @return array
	 */
	public static function strictify( array $schema ): array {
		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) && ! empty( $schema['properties'] ) ) {
			$required = ( isset( $schema['required'] ) && is_array( $schema['required'] ) ) ? $schema['required'] : array();
			$keys     = array();

			foreach ( $schema['properties'] as $key => $prop ) {
				$keys[] = $key;
				if ( ! is_array( $prop ) ) {
					continue;
				}
				if ( ! in_array( $key, $required, true ) ) {
					$prop = self::make_nullable( $prop );
				}
				$schema['properties'][ $key ] = self::strictify( $prop );
			}

			$schema['required']             = array_values( $keys );
			$schema['additionalProperties'] = false;
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$schema['items'] = self::strictify( $schema['items'] );
		}

		foreach ( array( 'allOf', 'oneOf', 'anyOf' ) as $keyword ) {
			if ( isset( $schema[ $keyword ] ) && is_array( $schema[ $keyword ] ) ) {
				foreach ( $schema[ $keyword ] as $i => $sub ) {
					if ( is_array( $sub ) ) {
						$schema[ $keyword ][ $i ] = self::strictify( $sub );
					}
				}
			}
		}

		return $schema;
	}

	/**
	 * Makes a single property schema nullable (so strict mode can list it in
	 * `required` while still allowing it to be omitted as null).
	 *
	 * @since 2.1.0 (extracted from emcp_tools_make_schema_nullable)
	 *
	 * @param array $prop A property schema.
	 * @return array
	 */
	public static function make_nullable( array $prop ): array {
		if ( isset( $prop['type'] ) ) {
			if ( is_string( $prop['type'] ) && 'null' !== $prop['type'] ) {
				$prop['type'] = array( $prop['type'], 'null' );
			} elseif ( is_array( $prop['type'] ) && ! in_array( 'null', $prop['type'], true ) ) {
				$prop['type'][] = 'null';
			}
		}
		if ( isset( $prop['enum'] ) && is_array( $prop['enum'] ) && ! in_array( null, $prop['enum'], true ) ) {
			$prop['enum'][] = null;
		}
		return $prop;
	}
}

if ( ! function_exists( 'emcp_tools_register_ability' ) ) {
	/**
	 * Back-compat global shim: the public ability-registration entry point used
	 * by every ability class. Forwards to EMCP_Tools_Schema_Compat::register_ability().
	 *
	 * @since 1.4.3
	 *
	 * @param string $name The ability name.
	 * @param array  $args The ability arguments.
	 * @return mixed
	 */
	function emcp_tools_register_ability( string $name, array $args ) {
		return EMCP_Tools_Schema_Compat::register_ability( $name, $args );
	}
}
