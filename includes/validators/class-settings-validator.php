<?php
/**
 * Validates widget settings against Elementor control schemas.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates widget settings against their control definitions.
 *
 * @since 1.0.0
 */
class Elementor_MCP_Settings_Validator {

	/**
	 * The schema generator instance.
	 *
	 * @var Elementor_MCP_Schema_Generator
	 */
	private $schema_generator;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Elementor_MCP_Schema_Generator $schema_generator The schema generator.
	 */
	public function __construct( Elementor_MCP_Schema_Generator $schema_generator ) {
		$this->schema_generator = $schema_generator;
	}

	/**
	 * Validates settings for a widget type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $widget_type The widget type name.
	 * @param array  $settings    The settings to validate.
	 * @return true|\WP_Error True if valid, WP_Error on failure.
	 */
	public function validate( string $widget_type, array $settings ) {
		$schema = $this->schema_generator->generate( $widget_type );

		if ( is_wp_error( $schema ) ) {
			return $schema;
		}

		// Basic validation: check that settings keys exist in the schema.
		$valid_keys = array_keys( $schema['properties'] ?? array() );

		foreach ( array_keys( $settings ) as $key ) {
			if ( ! in_array( $key, $valid_keys, true ) ) {
				return new \WP_Error(
					'invalid_setting',
					sprintf(
						/* translators: 1: setting key, 2: widget type */
						__( 'Setting "%1$s" is not a valid control for widget type "%2$s".', 'elementor-mcp' ),
						$key,
						$widget_type
					)
				);
			}
		}

		return true;
	}
}
