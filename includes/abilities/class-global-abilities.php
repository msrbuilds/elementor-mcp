<?php
/**
 * Global settings MCP abilities for Elementor.
 *
 * Registers 2 tools for updating global colors and typography
 * in the Elementor kit (site-wide settings).
 *
 * @package EMCP_Tools
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the global settings abilities.
 *
 * @since 1.0.0
 */
class EMCP_Tools_Global_Abilities {

	/**
	 * @var EMCP_Tools_Data
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param EMCP_Tools_Data $data The data access layer.
	 */
	public function __construct( EMCP_Tools_Data $data ) {
		$this->data = $data;
	}

	/**
	 * Returns the ability names registered by this class.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'emcp-tools/update-global-colors',
			'emcp-tools/update-global-typography',
		);
	}

	/**
	 * Registers all global abilities.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		$this->register_update_global_colors();
		$this->register_update_global_typography();
	}

	/**
	 * Permission check for global settings (requires manage_options).
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function check_manage_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	// -------------------------------------------------------------------------
	// update-global-colors
	// -------------------------------------------------------------------------

	private function register_update_global_colors(): void {
		emcp_tools_register_ability(
			'emcp-tools/update-global-colors',
			array(
				'label'               => __( 'Update Global Colors', 'emcp-tools' ),
				'description'         => __( 'Updates the site-wide color palette in the Elementor kit. Provide an array of color objects with id, title, and color (hex).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_global_colors' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'colors' => array(
							'type'        => 'array',
							'description' => __( 'Array of color definitions.', 'emcp-tools' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'_id'   => array(
										'type'        => 'string',
										'description' => __( 'Unique color ID (e.g. "primary").', 'emcp-tools' ),
									),
									'title' => array(
										'type'        => 'string',
										'description' => __( 'Human-readable title.', 'emcp-tools' ),
									),
									'color' => array(
										'type'        => 'string',
										'description' => __( 'Color value in hex format (e.g. "#FF5733").', 'emcp-tools' ),
									),
								),
								'required' => array( '_id', 'title', 'color' ),
							),
						),
					),
					'required'   => array( 'colors' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
					),
				),
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

	/**
	 * Executes the update-global-colors ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_update_global_colors( $input ) {
		$colors = $input['colors'] ?? array();

		if ( empty( $colors ) || ! is_array( $colors ) ) {
			return new \WP_Error( 'missing_colors', __( 'The colors parameter is required and must be an array.', 'emcp-tools' ) );
		}

		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();

		if ( ! $kit || ! $kit->get_id() ) {
			return new \WP_Error( 'kit_not_found', __( 'Active Elementor kit not found.', 'emcp-tools' ) );
		}

		// Merge the incoming colors into the kit's four SYSTEM slots (the ones
		// widgets actually inherit via `globals/colors?id=<slot>`), keyed by _id.
		// `custom_colors` is a separate, additive palette that no widget uses by
		// default — writing there does not rebrand anything.
		$kit_settings    = $kit->get_settings();
		$existing_colors = $kit_settings['system_colors'] ?? array();
		$slot_map        = array();

		foreach ( $existing_colors as $slot ) {
			if ( isset( $slot['_id'] ) ) {
				$slot_map[ $slot['_id'] ] = array(
					'title' => $slot['title'] ?? '',
					'color' => $slot['color'] ?? '',
				);
			}
		}

		foreach ( $colors as $color ) {
			$color_id = sanitize_text_field( $color['_id'] ?? '' );
			if ( empty( $color_id ) || ! in_array( $color_id, EMCP_Tools_System_Kit_Writer::SYSTEM_SLOTS, true ) ) {
				continue;
			}

			$slot_map[ $color_id ] = array(
				'title' => sanitize_text_field( $color['title'] ?? ( $slot_map[ $color_id ]['title'] ?? '' ) ),
				'color' => sanitize_hex_color( $color['color'] ?? '' ) ?: ( $slot_map[ $color_id ]['color'] ?? '' ),
			);
		}

		$result = EMCP_Tools_System_Kit_Writer::replace_system_colors( $slot_map );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->maybe_apply_theme_style( $kit );

		return array( 'success' => true );
	}

	// -------------------------------------------------------------------------
	// update-global-typography
	// -------------------------------------------------------------------------

	private function register_update_global_typography(): void {
		emcp_tools_register_ability(
			'emcp-tools/update-global-typography',
			array(
				'label'               => __( 'Update Global Typography', 'emcp-tools' ),
				'description'         => __( 'Updates the site-wide typography settings in the Elementor kit.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_global_typography' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'typography' => array(
							'type'        => 'array',
							'description' => __( 'Array of typography definitions.', 'emcp-tools' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'_id'                      => array(
										'type'        => 'string',
										'description' => __( 'Unique typography ID (e.g. "primary").', 'emcp-tools' ),
									),
									'title'                    => array(
										'type'        => 'string',
										'description' => __( 'Human-readable title.', 'emcp-tools' ),
									),
									'typography_font_family'   => array(
										'type'        => 'string',
										'description' => __( 'Font family name.', 'emcp-tools' ),
									),
									'typography_font_size'     => array(
										'type'        => 'object',
										'description' => __( 'Font size with size and unit.', 'emcp-tools' ),
									),
									'typography_font_weight'   => array(
										'type'        => 'string',
										'description' => __( 'Font weight (100-900, normal, bold).', 'emcp-tools' ),
									),
									'typography_line_height'   => array(
										'type'        => 'object',
										'description' => __( 'Line height with size and unit.', 'emcp-tools' ),
									),
									'typography_letter_spacing' => array(
										'type'        => 'object',
										'description' => __( 'Letter spacing with size and unit.', 'emcp-tools' ),
									),
								),
								'required' => array( '_id', 'title' ),
							),
						),
					),
					'required'   => array( 'typography' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
					),
				),
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

	/**
	 * Executes the update-global-typography ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_update_global_typography( $input ) {
		$typography = $input['typography'] ?? array();

		if ( empty( $typography ) || ! is_array( $typography ) ) {
			return new \WP_Error( 'missing_typography', __( 'The typography parameter is required and must be an array.', 'emcp-tools' ) );
		}

		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();

		if ( ! $kit || ! $kit->get_id() ) {
			return new \WP_Error( 'kit_not_found', __( 'Active Elementor kit not found.', 'emcp-tools' ) );
		}

		// Merge the incoming typography into the kit's four SYSTEM slots, keyed
		// by _id, the same way execute_update_global_colors merges into
		// system_colors — see the comment there for why `custom_typography`
		// alone does not rebrand anything.
		//
		// EMCP_Tools_System_Kit_Writer::replace_system_typography() expects each
		// slot's fields WITHOUT the `typography_` prefix (title, font_family,
		// font_weight, ...); this tool's public input/output uses the prefixed
		// shape (typography_font_family, ...) to match Elementor's own kit
		// settings shape. Translate both ways here.
		$kit_settings  = $kit->get_settings();
		$existing_typo = $kit_settings['system_typography'] ?? array();
		$slot_map      = array();

		foreach ( $existing_typo as $slot ) {
			$slot_id = $slot['_id'] ?? '';
			if ( '' === $slot_id ) {
				continue;
			}
			$slot_map[ $slot_id ] = $this->unprefix_typography_entry( $slot );
		}

		foreach ( $typography as $typo ) {
			$typo_id = sanitize_text_field( $typo['_id'] ?? '' );
			if ( empty( $typo_id ) || ! in_array( $typo_id, EMCP_Tools_System_Kit_Writer::SYSTEM_SLOTS, true ) ) {
				continue;
			}

			$incoming = $this->unprefix_typography_entry( $typo );
			$slot_map[ $typo_id ] = array_merge( $slot_map[ $typo_id ] ?? array(), $incoming );
		}

		$result = EMCP_Tools_System_Kit_Writer::replace_system_typography( $slot_map );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->maybe_apply_theme_style( $kit );

		return array( 'success' => true );
	}

	/**
	 * Strips the `typography_` prefix from a system-typography entry's field
	 * names, keeping only the keys `EMCP_Tools_System_Kit_Writer` understands
	 * (title, plus its `TYPO_FIELDS` list).
	 *
	 * @since 1.9.1
	 *
	 * @param array $entry Prefixed entry, e.g. as stored in `system_typography`
	 *                     or as received from this tool's `typography` input.
	 * @return array Unprefixed entry, e.g. `[ 'title' => ..., 'font_family' => ... ]`.
	 */
	private function unprefix_typography_entry( array $entry ): array {
		$unprefixed = array();

		if ( isset( $entry['title'] ) ) {
			$unprefixed['title'] = $entry['title'];
		}

		foreach ( array_keys( EMCP_Tools_System_Kit_Writer::TYPO_FIELDS ) as $field ) {
			$prefixed_key = 'typography_' . $field;
			if ( isset( $entry[ $prefixed_key ] ) ) {
				$unprefixed[ $field ] = $entry[ $prefixed_key ];
			}
		}

		return $unprefixed;
	}

	/**
	 * If both system_colors and system_typography now have all four slots
	 * filled, apply them to the kit's Theme Style defaults (body/h1-h6/links)
	 * so the rebrand is actually visible on the site — not just stored as
	 * inert globals. No-ops (and never errors the caller) while the palette
	 * is still incomplete, e.g. after a tool call that only touched colors.
	 *
	 * @since 1.9.1
	 *
	 * @param \Elementor\Core\Kits\Documents\Kit $kit The active kit document.
	 */
	private function maybe_apply_theme_style( $kit ): void {
		$settings   = $kit->get_settings();
		$colors     = array();
		$typography = array();

		foreach ( $settings['system_colors'] ?? array() as $slot ) {
			if ( isset( $slot['_id'] ) ) {
				$colors[ $slot['_id'] ] = $slot;
			}
		}
		foreach ( $settings['system_typography'] ?? array() as $slot ) {
			if ( isset( $slot['_id'] ) ) {
				$typography[ $slot['_id'] ] = $this->unprefix_typography_entry( $slot );
			}
		}

		foreach ( EMCP_Tools_System_Kit_Writer::SYSTEM_SLOTS as $slot_id ) {
			if ( empty( $colors[ $slot_id ]['color'] ) || empty( $typography[ $slot_id ]['font_family'] ) ) {
				return;
			}
		}

		// Best-effort: a failure here (e.g. a slot missing its font/color)
		// leaves system_colors/system_typography already persisted intact.
		EMCP_Tools_System_Kit_Writer::apply_theme_style( $colors, $typography );
	}
}
