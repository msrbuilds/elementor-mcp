<?php
/**
 * Captures wp_register_ability() calls for EmptyObjectOutputTest (#184). Loaded only in a
 * separate test process, so no other test sees the stub.
 *
 * @package EMCP_Tools\Tests
 */

if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( $name, $args ) {
		$GLOBALS['emcp_test_registered'][ $name ] = $args;
		return true;
	}
}
