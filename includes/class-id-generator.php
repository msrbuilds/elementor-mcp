<?php
/**
 * Unique element ID generation.
 *
 * Generates 7-character hex IDs matching Elementor's internal format.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates unique element IDs for Elementor elements.
 *
 * @since 1.0.0
 */
class Elementor_MCP_Id_Generator {

	/**
	 * Generates a 7-character random hex string.
	 *
	 * Matches Elementor's internal element ID format.
	 *
	 * @since 1.0.0
	 *
	 * @return string 7-character hex ID.
	 */
	public static function generate(): string {
		return substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	}
}
