<?php
/**
 * Uniform finding builder for the Performance Analyzer.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the canonical finding array shared by every audit.
 *
 * @since 3.0.0
 */
class EMCP_Tools_Performance_Finding {

	/**
	 * @param string     $id
	 * @param string     $category       server|database|config|page|assets
	 * @param string     $label
	 * @param string     $status         pass|warning|critical|info
	 * @param mixed      $value
	 * @param string     $message
	 * @param string     $recommendation Non-empty when status is not "pass".
	 * @return array
	 */
	public static function make( string $id, string $category, string $label, string $status, $value, string $message, string $recommendation = '' ): array {
		return array(
			'id'             => $id,
			'category'       => $category,
			'label'          => $label,
			'status'         => $status,
			'value'          => $value,
			'message'        => $message,
			'recommendation' => $recommendation,
		);
	}
}
