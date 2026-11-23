<?php
/**
 * clear-cache: purge page, asset and host caches after a change (#201).
 *
 * @package EMCP_Tools
 * @since   3.22.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the clear-cache ability.
 */
class EMCP_Tools_Cache_Abilities {

	/** @var string[] */
	private $ability_names = array();

	/** @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/** Register the group. */
	public function register(): void {
		$this->ability_names[] = 'emcp-tools/clear-cache';
		emcp_tools_register_ability(
			'emcp-tools/clear-cache',
			array(
				'label'               => __( 'Clear Cache', 'emcp-tools' ),
				'description'         => __( 'Clears the caches this site uses so visitors see a change at once: Elementor CSS files, WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, WP Fastest Cache, Autoptimize, Breeze, SiteGround Speed Optimizer, WP-Optimize, Cache Enabler and Kinsta. Clears every layer it finds, or only the ones named in layers. Each layer reports purged, queued (purged on the next request), skipped or failed. Set object_cache: true to also flush the object cache. Content is not changed.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'layers'       => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Only these layer ids, such as elementor, wp-rocket, litespeed, kinsta. Default: every layer found.', 'emcp-tools' ),
						),
						'object_cache' => array(
							'type'        => 'boolean',
							'description' => __( 'Also flush the object cache (Redis, Memcached). Default false.', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/** @return bool */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param array $input Tool input.
	 * @return array
	 */
	public function execute( $input ): array {
		$only = array_values( array_filter( array_map( 'sanitize_key', (array) ( $input['layers'] ?? array() ) ) ) );
		$out  = EMCP_Tools_Cache_Clearer::clear( $only, ! empty( $input['object_cache'] ) );
		if ( empty( $out['detected'] ) && empty( $out['results'] ) ) {
			$out['note'] = __( 'No cache layer this tool knows is active on this site.', 'emcp-tools' );
		}
		return $out;
	}

	/**
	 * Clear caches after a write when its input asked for it (clear_cache: true).
	 *
	 * @param array $input  Tool input.
	 * @param mixed $result Tool result.
	 * @return mixed Result, with a cache report added to an array result.
	 */
	public static function after_write( array $input, $result ) {
		if ( empty( $input['clear_cache'] ) || ! is_array( $result ) || ! class_exists( 'EMCP_Tools_Cache_Clearer' ) ) {
			return $result;
		}
		// Same rule as clear-cache itself: an author editing their own page's CSS
		// must not be able to purge every cache on the site (3.22.1 review).
		if ( ! current_user_can( 'manage_options' ) ) {
			$result['cache'] = array( 'skipped' => __( 'Clearing caches needs the manage_options capability.', 'emcp-tools' ) );
			return $result;
		}
		$result['cache'] = EMCP_Tools_Cache_Clearer::clear();
		return $result;
	}

	/**
	 * The clear_cache input property the code tools share.
	 *
	 * @return array
	 */
	public static function flag_schema(): array {
		return array(
			'type'        => 'boolean',
			'description' => __( 'Clear the site caches after saving (see clear-cache), so visitors see the change at once. Default false.', 'emcp-tools' ),
		);
	}
}
