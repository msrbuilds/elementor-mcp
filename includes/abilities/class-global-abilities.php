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
	 * The four `system_colors` slot ids. Writing them into `custom_colors`
	 * shadows the system variable instead of changing it (#145).
	 */
	const SYSTEM_COLOR_IDS = array( 'primary', 'secondary', 'text', 'accent' );

	/**
	 * The four `system_typography` slot ids. Writing them into
	 * `custom_typography` adds a second global with the same id instead of
	 * changing the system one (#186).
	 */
	const SYSTEM_TYPOGRAPHY_IDS = array( 'primary', 'secondary', 'text', 'accent' );

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
				'description'         => __( 'Adds or updates entries in the Elementor kit CUSTOM color palette (settings.custom_colors), matched by _id. Provide an array of color objects with _id, title, and color (hex). The four system slots (primary, secondary, text, accent) live in system_colors and are refused here; use replace-system-colors (Pro) or the Elementor Site Settings UI for those.', 'emcp-tools' ),
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
										'description' => __( 'Unique custom color ID (e.g. "brand"). primary, secondary, text and accent are reserved system slots and are refused.', 'emcp-tools' ),
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
						'target'  => array( 'type' => 'string' ),
						'written' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'updated' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'added'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
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
	 * Reads the kit settings as stored now.
	 *
	 * The document's get_settings() is read once per document object and never
	 * refreshed, while update_settings() merges into the stored meta. A long-lived
	 * MCP process (WP-CLI stdio) that called these tools twice therefore rebuilt
	 * the second list from the first call's stale copy and undid it. Read the
	 * meta instead, which update_post_meta() keeps current.
	 *
	 * @param object $kit Active Elementor kit document.
	 * @return array
	 */
	private function stored_kit_settings( $kit ): array {
		$settings = method_exists( $kit, 'get_meta' )
			? $kit->get_meta( '_elementor_page_settings' )
			: $kit->get_settings();
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Maps each `_id` in a stored globals list to its first position.
	 *
	 * @param mixed $items Stored list.
	 * @return array<string,int>
	 */
	private static function id_positions( $items ): array {
		$map = array();
		foreach ( is_array( $items ) ? $items : array() as $index => $item ) {
			if ( is_array( $item ) && isset( $item['_id'] ) && is_scalar( $item['_id'] ) && ! isset( $map[ (string) $item['_id'] ] ) ) {
				$map[ (string) $item['_id'] ] = $index;
			}
		}
		return $map;
	}

	/**
	 * Snapshot the kit's stored settings meta so a global change can be rolled back.
	 *
	 * @param object $kit Active Elementor kit document.
	 * @return array { id, before }.
	 */
	private function snapshot_kit_settings( $kit ): array {
		$kit_id = (int) $kit->get_id();
		return array( 'id' => $kit_id, 'before' => get_post_meta( $kit_id, '_elementor_page_settings', true ) );
	}

	/**
	 * Record a kit-settings change to the change ledger (meta-before-image).
	 *
	 * @param array  $snap    Snapshot from snapshot_kit_settings().
	 * @param string $summary Human summary.
	 */
	private function record_kit_change( array $snap, string $summary ): void {
		if ( class_exists( 'EMCP_Tools_Change_Recorder' ) && (int) $snap['id'] > 0 ) {
			EMCP_Tools_Change_Recorder::record_meta(
				'post',
				(int) $snap['id'],
				array( '_elementor_page_settings' => $snap['before'] ),
				$summary,
				'Elementor kit #' . (int) $snap['id'],
				'globals',
				'update-globals'
			);
		}
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

		// The four system slots live in `system_colors`; upserting one of their
		// ids into `custom_colors` creates a shadow entry that collides with the
		// system CSS variable and never changes the real color (#145). Refuse the
		// whole call rather than write the rest and report success.
		$reserved = array();
		foreach ( $colors as $color ) {
			$id = sanitize_text_field( is_array( $color ) ? ( $color['_id'] ?? '' ) : '' );
			if ( in_array( $id, self::SYSTEM_COLOR_IDS, true ) ) {
				$reserved[] = $id;
			}
		}
		if ( $reserved ) {
			return new \WP_Error(
				'reserved_color_id',
				sprintf(
					/* translators: %s: comma-separated list of reserved ids */
					__( 'These ids are Elementor system color slots and cannot be written as custom colors: %s. Use replace-system-colors (Pro) or Elementor Site Settings to change system colors.', 'emcp-tools' ),
					implode( ', ', $reserved )
				),
				array( 'reserved' => array_values( array_unique( $reserved ) ) )
			);
		}

		// Merge colors: update existing by _id, add new ones.
		$kit_settings    = $this->stored_kit_settings( $kit );
		// Keep the stored keys: update_settings() merges list entries by key.
		$existing_colors = is_array( $kit_settings['custom_colors'] ?? null ) ? $kit_settings['custom_colors'] : array();
		$existing_map    = self::id_positions( $existing_colors );

		$written = array();
		$updated = array();
		$added   = array();
		foreach ( $colors as $color ) {
			$color_id = sanitize_text_field( $color['_id'] ?? '' );
			if ( empty( $color_id ) ) {
				continue;
			}
			$written[] = $color_id;

			$color_entry = array(
				'_id'   => $color_id,
				'title' => sanitize_text_field( $color['title'] ?? '' ),
				'color' => sanitize_hex_color( $color['color'] ?? '' ),
			);

			if ( isset( $existing_map[ $color_id ] ) ) {
				$existing_colors[ $existing_map[ $color_id ] ] = $color_entry;
				if ( ! in_array( $color_id, $added, true ) ) {
					$updated[] = $color_id;
				}
			} else {
				// Remember the new position so a repeated id in the same call
				// updates this entry instead of adding a second one.
				$existing_colors[]         = $color_entry;
				$existing_map[ $color_id ] = array_key_last( $existing_colors );
				$added[]                   = $color_id;
			}
		}

		$emcp_kit_snap = $this->snapshot_kit_settings( $kit );
		$kit->update_settings( array( 'custom_colors' => $existing_colors ) );
		$this->record_kit_change( $emcp_kit_snap, 'Updated global colors' );

		return array(
			'success' => true,
			'target'  => 'custom_colors',
			'written' => array_values( array_unique( $written ) ),
			'updated' => array_values( array_unique( $updated ) ),
			'added'   => array_values( array_unique( $added ) ),
		);
	}

	// -------------------------------------------------------------------------
	// update-global-typography
	// -------------------------------------------------------------------------

	private function register_update_global_typography(): void {
		$responsive_size_schema = array(
			'type' => 'object',
		);

		emcp_tools_register_ability(
			'emcp-tools/update-global-typography',
			array(
				'label'               => __( 'Update Global Typography', 'emcp-tools' ),
				'description'         => __( 'Adds or updates entries in the Elementor kit CUSTOM typography list (settings.custom_typography), matched by _id: an existing _id is updated in place (only the fields you pass change), an unknown _id is added. The four system slots (primary, secondary, text, accent, and any other id in system_typography) are refused here; use replace-system-typography (Pro) or the Elementor Site Settings UI for those. Returns the ids updated and the ids added.', 'emcp-tools' ),
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
										'description' => __( 'Unique custom typography ID (e.g. "body-copy"). primary, secondary, text and accent are reserved system slots and are refused.', 'emcp-tools' ),
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
										...$responsive_size_schema,
										'description' => __( 'Font size with size and unit.', 'emcp-tools' ),
									),
									'typography_font_size_tablet' => array(
										...$responsive_size_schema,
										'description' => __( 'Tablet font size with size and unit.', 'emcp-tools' ),
									),
									'typography_font_size_mobile' => array(
										...$responsive_size_schema,
										'description' => __( 'Mobile font size with size and unit.', 'emcp-tools' ),
									),
									'typography_font_weight'   => array(
										'type'        => 'string',
										'description' => __( 'Font weight (100-900, normal, bold).', 'emcp-tools' ),
									),
									'typography_line_height'   => array(
										...$responsive_size_schema,
										'description' => __( 'Line height with size and unit.', 'emcp-tools' ),
									),
									'typography_line_height_tablet' => array(
										...$responsive_size_schema,
										'description' => __( 'Tablet line height with size and unit.', 'emcp-tools' ),
									),
									'typography_line_height_mobile' => array(
										...$responsive_size_schema,
										'description' => __( 'Mobile line height with size and unit.', 'emcp-tools' ),
									),
									'typography_letter_spacing' => array(
										...$responsive_size_schema,
										'description' => __( 'Letter spacing with size and unit.', 'emcp-tools' ),
									),
									'typography_letter_spacing_tablet' => array(
										...$responsive_size_schema,
										'description' => __( 'Tablet letter spacing with size and unit.', 'emcp-tools' ),
									),
									'typography_letter_spacing_mobile' => array(
										...$responsive_size_schema,
										'description' => __( 'Mobile letter spacing with size and unit.', 'emcp-tools' ),
									),
									'typography_word_spacing' => array(
										...$responsive_size_schema,
										'description' => __( 'Word spacing with size and unit.', 'emcp-tools' ),
									),
									'typography_word_spacing_tablet' => array(
										...$responsive_size_schema,
										'description' => __( 'Tablet word spacing with size and unit.', 'emcp-tools' ),
									),
									'typography_word_spacing_mobile' => array(
										...$responsive_size_schema,
										'description' => __( 'Mobile word spacing with size and unit.', 'emcp-tools' ),
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
						'target'  => array( 'type' => 'string' ),
						'written' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'updated' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'added'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
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

		$kit_settings = $this->stored_kit_settings( $kit );

		// System globals live in `system_typography`; upserting one of their ids
		// into `custom_typography` added a second global with the same id and
		// left the real one unchanged while reporting success (#186). Refuse the
		// whole call, as update-global-colors does for system colors (#145).
		$system_ids = array_merge(
			self::SYSTEM_TYPOGRAPHY_IDS,
			array_map( 'strval', array_keys( self::id_positions( $kit_settings['system_typography'] ?? array() ) ) )
		);
		$reserved   = array();
		foreach ( $typography as $typo ) {
			$id = sanitize_text_field( is_array( $typo ) ? ( $typo['_id'] ?? '' ) : '' );
			if ( '' !== $id && in_array( $id, $system_ids, true ) ) {
				$reserved[] = $id;
			}
		}
		if ( $reserved ) {
			return new \WP_Error(
				'reserved_typography_id',
				sprintf(
					/* translators: %s: comma-separated list of reserved ids */
					__( 'These ids are Elementor system typography slots and cannot be written as custom typography: %s. Use replace-system-typography (Pro) or Elementor Site Settings to change system typography.', 'emcp-tools' ),
					implode( ', ', array_unique( $reserved ) )
				),
				array( 'reserved' => array_values( array_unique( $reserved ) ) )
			);
		}

		// Keep the stored keys: Kit::update_settings() merges with
		// array_replace_recursive(), which matches list entries by key, so a
		// reindexed list would merge entries into the wrong stored ones.
		$existing_typo = is_array( $kit_settings['custom_typography'] ?? null ) ? $kit_settings['custom_typography'] : array();
		$existing_map  = self::id_positions( $existing_typo );
		$updated       = array();
		$added         = array();

		$allowed_keys = array(
			'_id', 'title', 'typography_typography',
			'typography_font_family', 'typography_font_size',
			'typography_font_size_tablet', 'typography_font_size_mobile',
			'typography_font_weight', 'typography_text_transform',
			'typography_font_style', 'typography_text_decoration',
			'typography_line_height', 'typography_line_height_tablet', 'typography_line_height_mobile',
			'typography_letter_spacing', 'typography_letter_spacing_tablet', 'typography_letter_spacing_mobile',
			'typography_word_spacing', 'typography_word_spacing_tablet', 'typography_word_spacing_mobile',
		);

		foreach ( $typography as $typo ) {
			if ( ! is_array( $typo ) ) {
				continue;
			}
			$typo_id = sanitize_text_field( $typo['_id'] ?? '' );
			if ( empty( $typo_id ) ) {
				continue;
			}

			// Build a sanitized entry with only allowed keys.
			$typo_entry = array();
			foreach ( $allowed_keys as $key ) {
				if ( isset( $typo[ $key ] ) ) {
					$typo_entry[ $key ] = $typo[ $key ];
				}
			}

			// Ensure typography_typography is set to 'custom' to activate overrides.
			$typo_entry['typography_typography'] = 'custom';
			$typo_entry['_id']                   = $typo_id;

			if ( isset( $existing_map[ $typo_id ] ) ) {
				$existing_typo[ $existing_map[ $typo_id ] ] = array_merge(
					$existing_typo[ $existing_map[ $typo_id ] ],
					$typo_entry
				);
				if ( ! in_array( $typo_id, $added, true ) ) {
					$updated[] = $typo_id;
				}
			} else {
				// Store the sanitized id and remember the new position so a
				// repeated id in the same call updates this entry.
				$existing_typo[]          = $typo_entry;
				$existing_map[ $typo_id ] = array_key_last( $existing_typo );
				$added[]                  = $typo_id;
			}
		}

		$emcp_kit_snap = $this->snapshot_kit_settings( $kit );
		$kit->update_settings( array( 'custom_typography' => $existing_typo ) );
		$this->record_kit_change( $emcp_kit_snap, 'Updated global typography' );

		return array(
			'success' => true,
			'target'  => 'custom_typography',
			'updated' => array_values( array_unique( $updated ) ),
			'added'   => array_values( array_unique( $added ) ),
		);
	}
}
