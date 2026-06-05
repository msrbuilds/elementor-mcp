<?php
/**
 * Global Classes (Class Manager) MCP ability — Elementor 4.0+.
 *
 * Elementor's Class Manager assigns human-readable names (e.g. "card-base") to
 * reusable style classes, but stores and applies them by opaque `g-` IDs (e.g.
 * `g-037bb9c`). When an AI reads an element it sees only those IDs and can't
 * tell what they mean. This read-only tool resolves the IDs back to their names
 * and the CSS properties they define, so an agent can understand and debug a
 * design-system-driven page. (GitHub #55)
 *
 * Registers only when Elementor's Global Classes repository is present
 * (Elementor 4.0+). Read-only, gated on `edit_posts`.
 *
 * @package EMCP_Tools
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes Elementor Global Classes (Class Manager) over MCP.
 *
 * @since 2.1.0
 */
class EMCP_Tools_Global_Classes_Abilities {

	/**
	 * Elementor's global-classes repository class.
	 */
	const REPOSITORY = '\\Elementor\\Modules\\GlobalClasses\\Global_Classes_Repository';

	/**
	 * Whether Elementor exposes the Global Classes repository on this site.
	 *
	 * @since 2.1.0
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return class_exists( self::REPOSITORY );
	}

	/**
	 * @since 2.1.0
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return self::is_available() ? array( 'elementor-mcp/list-global-classes' ) : array();
	}

	/**
	 * @since 2.1.0
	 */
	public function register(): void {
		if ( ! self::is_available() ) {
			return;
		}
		emcp_tools_register_ability(
			'elementor-mcp/list-global-classes',
			array(
				'label'               => __( 'List Global Classes', 'emcp-tools' ),
				'description'         => __( 'Resolves Elementor Class Manager (Global Classes) entries. Maps the opaque "g-" class IDs that appear on elements back to their human-readable names and the CSS properties they define, per breakpoint/state. Use it to understand what styling a g- class applies. Pass class_ids to resolve specific IDs, or omit to list them all. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_global_classes' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'class_ids' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Optional list of g- class IDs to resolve (e.g. ["g-037bb9c"]). Omit to return every global class.', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'count'   => array( 'type' => 'integer' ),
						'classes' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'    => array( 'type' => 'string' ),
									'label' => array( 'type' => 'string' ),
									'css'   => array( 'type' => 'object' ),
								),
							),
						),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @since 2.1.0
	 * @return bool
	 */
	public function check_read_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Executes list-global-classes.
	 *
	 * @since 2.1.0
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public function execute_list_global_classes( $input ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'unavailable', __( 'Global Classes are not available — Elementor 4.0+ is required.', 'emcp-tools' ) );
		}

		$filter = array();
		if ( isset( $input['class_ids'] ) && is_array( $input['class_ids'] ) ) {
			$filter = array_map( 'sanitize_text_field', $input['class_ids'] );
		}

		$repo = self::REPOSITORY;
		try {
			$all   = $repo::make()->all();
			$items = method_exists( $all, 'get_items' ) ? $all->get_items() : $all;
			// Elementor wraps items in a Collection with ->all(); normalize to array.
			if ( is_object( $items ) && method_exists( $items, 'all' ) ) {
				$items = $items->all();
			}
			$items = (array) $items;
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'read_failed', $e->getMessage() );
		}

		$classes = array();
		foreach ( $items as $key => $item ) {
			// Resolve each class defensively: one malformed entry must not abort
			// the whole enumeration. Before this guard, a single unexpected class
			// structure made the no-args (resolve-all) call fail entirely while
			// explicit class_ids — which skip the bad entry — still worked (#57).
			try {
				$item = (array) $item;
				$id   = isset( $item['id'] ) ? (string) $item['id'] : (string) $key;

				if ( ! empty( $filter ) && ! in_array( $id, $filter, true ) ) {
					continue;
				}

				$classes[] = array(
					'id'    => $id,
					'label' => isset( $item['label'] ) ? (string) $item['label'] : '',
					'css'   => $this->flatten_variants( $item['variants'] ?? array() ),
				);
			} catch ( \Throwable $e ) {
				$id = is_string( $key ) || is_int( $key ) ? (string) $key : '';
				if ( ! empty( $filter ) && ! in_array( $id, $filter, true ) ) {
					continue;
				}
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( '[EMCP Tools] list-global-classes: could not fully resolve class "' . $id . '": ' . $e->getMessage() );
				}
				// Still surface the class id so enumeration/discovery is complete.
				$classes[] = array(
					'id'    => $id,
					'label' => '',
					'css'   => array(),
					'error' => 'could not resolve styles for this class',
				);
			}
		}

		return array(
			'count'   => count( $classes ),
			'classes' => $classes,
		);
	}

	/**
	 * Flattens a class's style variants into a readable map keyed by
	 * breakpoint (and state), with each variant's $$type-wrapped CSS props
	 * unwrapped to plain values.
	 *
	 * @since 2.1.0
	 *
	 * @param array $variants The class variants.
	 * @return array
	 */
	private function flatten_variants( array $variants ): array {
		$out = array();
		foreach ( $variants as $variant ) {
			$variant = (array) $variant;
			$meta    = (array) ( $variant['meta'] ?? array() );
			$bp      = isset( $meta['breakpoint'] ) && '' !== $meta['breakpoint'] ? (string) $meta['breakpoint'] : 'desktop';
			$state   = isset( $meta['state'] ) && '' !== $meta['state'] && null !== $meta['state'] ? (string) $meta['state'] : '';
			$key     = '' !== $state ? $bp . ':' . $state : $bp;

			$props = (array) ( $variant['props'] ?? array() );
			$flat  = array();
			foreach ( $props as $prop_name => $prop_value ) {
				// Per-prop guard: an unexpected single prop value must not lose the
				// rest of the class's resolved CSS.
				try {
					$flat[ (string) $prop_name ] = class_exists( 'EMCP_Tools_Atomic_Props' )
						? EMCP_Tools_Atomic_Props::unwrap( $prop_value )
						: $prop_value;
				} catch ( \Throwable $e ) {
					$flat[ (string) $prop_name ] = $prop_value;
				}
			}
			$out[ $key ] = $flat;
		}
		return $out;
	}
}
