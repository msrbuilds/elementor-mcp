<?php
/**
 * Global Classes (Class Manager) WRITE abilities — Elementor 4.0+.
 *
 * The read counterpart (`list-global-classes`) resolves the opaque `g-` class IDs
 * back to names + CSS. These tools let an agent AUTHOR the design system: create a
 * new `g-` class with styles, update its label/styles, or delete it (GitHub #108).
 *
 * Writes go through Elementor's own repository — read the full class set with
 * `Global_Classes_Repository::make()->all()`, mutate the id→item map, then
 * `put($items, $order)` (Elementor computes the add/modify/delete diff and handles
 * relations + usage cleanup). Each class stores one or more variants
 * `{ meta:{breakpoint,state}, props:{ css-prop: $$type } }`; props are the atomic
 * typed props our atomic tools already build (`EMCP_Tools_Atomic_Styles` /
 * `EMCP_Tools_Atomic_Props`), so callers pass friendly flat `styles` (background,
 * color, padding, margin, border-radius, flex…) plus a raw `props` escape hatch.
 *
 * Registers only when Elementor's Global Classes repository is present (4.0+).
 * Writes gated on Elementor's own `elementor_global_classes_update_class`
 * capability (administrator), falling back to `manage_options`. All three tools
 * ship disabled-by-default; `delete-global-class` also requires `confirm:true`.
 *
 * @package EMCP_Tools
 * @since   3.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create / update / delete Elementor Global Classes over MCP.
 */
class EMCP_Tools_Global_Classes_Write_Abilities {

	const REPOSITORY  = '\\Elementor\\Modules\\GlobalClasses\\Global_Classes_Repository';
	const UPDATE_CAP  = 'elementor_global_classes_update_class';

	/** Elementor v4 breakpoint ids a variant may target. */
	const BREAKPOINTS = array( 'desktop', 'widescreen', 'laptop', 'tablet_extra', 'tablet', 'mobile_extra', 'mobile' );

	/**
	 * @return bool Whether the Global Classes repository is available (4.0+).
	 */
	public static function is_available(): bool {
		return class_exists( 'EMCP_Tools_Global_Classes_Abilities' )
			? EMCP_Tools_Global_Classes_Abilities::is_available()
			: class_exists( self::REPOSITORY );
	}

	/**
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return self::is_available()
			? array( 'emcp-tools/create-global-class', 'emcp-tools/update-global-class', 'emcp-tools/delete-global-class', 'emcp-tools/reorder-global-classes' )
			: array();
	}

	/**
	 * Register the three write abilities.
	 */
	public function register(): void {
		if ( ! self::is_available() ) {
			return;
		}

		$styles_schema = array(
			'type'        => 'object',
			'description' => __( 'Friendly flat styles, mapped to Elementor atomic props: background_color, color, width, min_height, border_radius, padding|padding_top|padding_right|padding_bottom|padding_left, margin(+ per-side), direction, justify, align, wrap, gap, row_gap, column_gap (each size accepts a <key>_unit). Anything not covered goes in "props".', 'emcp-tools' ),
		);
		$props_schema  = array(
			'type'        => 'object',
			'description' => __( 'Raw escape hatch: CSS-property => $$type-wrapped value (e.g. {"border-radius":{"$$type":"size","value":{"size":8,"unit":"px"}}}). Merged over the built styles.', 'emcp-tools' ),
		);
		$bp_schema     = array(
			'type'        => 'string',
			'enum'        => self::BREAKPOINTS,
			'description' => __( 'Breakpoint this variant targets (default desktop). Call update per breakpoint to build responsive styles.', 'emcp-tools' ),
		);
		$state_schema  = array(
			'type'        => 'string',
			'description' => __( 'Optional state for this variant, e.g. hover, focus, active. Omit for the normal state.', 'emcp-tools' ),
		);

		emcp_tools_register_ability(
			'emcp-tools/create-global-class',
			array(
				'label'               => __( 'Create Global Class', 'emcp-tools' ),
				'description'         => __( 'Create an Elementor v4 Global Class (Class Manager) with a label and styles. Returns the new g- id to apply to elements. Pass friendly "styles" and/or raw "props"; optional breakpoint/state.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_create_global_class' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'label'      => array( 'type' => 'string', 'description' => __( 'Human-readable class name, e.g. "card-base".', 'emcp-tools' ) ),
						'styles'     => $styles_schema,
						'props'      => $props_schema,
						'breakpoint' => $bp_schema,
						'state'      => $state_schema,
					),
					'required'   => array( 'label' ),
				),
				'meta'                => array( 'annotations' => array( 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/update-global-class',
			array(
				'label'               => __( 'Update Global Class', 'emcp-tools' ),
				'description'         => __( 'Update a Global Class by g- id: change its label and/or merge styles/props into the variant for a breakpoint+state (replace_variant:true replaces that variant instead of merging).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_global_class' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'              => array( 'type' => 'string', 'description' => __( 'The g- class id to update.', 'emcp-tools' ) ),
						'label'           => array( 'type' => 'string', 'description' => __( 'New label (optional).', 'emcp-tools' ) ),
						'styles'          => $styles_schema,
						'props'           => $props_schema,
						'breakpoint'      => $bp_schema,
						'state'           => $state_schema,
						'replace_variant' => array( 'type' => 'boolean', 'description' => __( 'Replace the target variant\'s props instead of merging into them.', 'emcp-tools' ) ),
					),
					'required'   => array( 'id' ),
				),
				'meta'                => array( 'annotations' => array( 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/delete-global-class',
			array(
				'label'               => __( 'Delete Global Class', 'emcp-tools' ),
				'description'         => __( 'Delete a Global Class by g- id. Destructive — also removes the class from every element that uses it. Requires confirm:true.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_delete_global_class' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'string', 'description' => __( 'The g- class id to delete.', 'emcp-tools' ) ),
						'confirm' => array( 'type' => 'boolean', 'description' => __( 'Must be true to delete.', 'emcp-tools' ) ),
					),
					'required'   => array( 'id' ),
				),
				'meta'                => array( 'annotations' => array( 'destructive' => true, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/reorder-global-classes',
			array(
				'label'               => __( 'Reorder Global Classes', 'emcp-tools' ),
				'description'         => __( 'Set the order of the Elementor v4 Global Classes. The Class Manager order IS the CSS source order, so it decides which class wins when two apply (later overrides earlier at equal specificity). Pass { order: [g-id, ...] }; any existing classes you omit are appended after, keeping their current relative order.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_reorder_global_classes' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'order' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'The desired top-to-bottom order of g- class ids. Classes omitted here are appended after, in their current order.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'order' ),
				),
				'meta'                => array( 'annotations' => array( 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @return bool
	 */
	public function check_write_permission(): bool {
		return current_user_can( self::UPDATE_CAP ) || current_user_can( 'manage_options' );
	}

	/**
	 * @param array $input { label, styles?, props?, breakpoint?, state? }.
	 * @return array|\WP_Error
	 */
	public function execute_create_global_class( $input ) {
		if ( ! self::is_available() ) {
			return $this->unavailable();
		}
		$label = sanitize_text_field( (string) ( $input['label'] ?? '' ) );
		if ( '' === $label ) {
			return new \WP_Error( 'missing_label', __( 'A "label" is required.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$state = $this->read_state();
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		list( $items, $order ) = $state;

		$variant_props = $this->build_variant_props( $input );
		if ( is_wp_error( $variant_props ) ) {
			return $variant_props;
		}

		$id = $this->mint_id( $items );
		$items[ $id ] = array(
			'id'       => $id,
			'type'     => 'class',
			'label'    => $label,
			'variants' => array(
				array(
					'meta'  => $this->variant_meta( $input ),
					'props' => $variant_props,
				),
			),
		);
		$order[] = $id;

		$saved = $this->write_state( $items, $order );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return array( 'id' => $id, 'label' => $label );
	}

	/**
	 * @param array $input { id, label?, styles?, props?, breakpoint?, state?, replace_variant? }.
	 * @return array|\WP_Error
	 */
	public function execute_update_global_class( $input ) {
		if ( ! self::is_available() ) {
			return $this->unavailable();
		}
		$id = sanitize_text_field( (string) ( $input['id'] ?? '' ) );
		if ( '' === $id ) {
			return new \WP_Error( 'missing_id', __( 'A class "id" is required.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$state = $this->read_state();
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		list( $items, $order ) = $state;
		if ( ! isset( $items[ $id ] ) ) {
			return new \WP_Error( 'not_found', sprintf( __( 'Global class not found: %s', 'emcp-tools' ), $id ), array( 'status' => 404 ) );
		}

		$item = (array) $items[ $id ];
		if ( isset( $input['label'] ) ) {
			$item['label'] = sanitize_text_field( (string) $input['label'] );
		}

		$has_styles = ( isset( $input['styles'] ) && is_array( $input['styles'] ) ) || ( isset( $input['props'] ) && is_array( $input['props'] ) );
		if ( $has_styles ) {
			$meta      = $this->variant_meta( $input );
			$new_props = $this->build_variant_props( $input );
			if ( is_wp_error( $new_props ) ) {
				return $new_props;
			}
			$variants = isset( $item['variants'] ) ? (array) $item['variants'] : array();
			$idx      = $this->find_variant_index( $variants, $meta );
			$replace  = ! empty( $input['replace_variant'] );
			if ( null === $idx ) {
				$variants[] = array( 'meta' => $meta, 'props' => $new_props );
			} elseif ( $replace ) {
				$variants[ $idx ]['props'] = $new_props;
			} else {
				$existing = isset( $variants[ $idx ]['props'] ) ? (array) $variants[ $idx ]['props'] : array();
				$variants[ $idx ]['props'] = array_merge( $existing, $new_props );
			}
			$item['variants'] = array_values( $variants );
		}

		$items[ $id ] = $item;
		$saved        = $this->write_state( $items, $order );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return array( 'id' => $id, 'label' => (string) ( $item['label'] ?? '' ), 'variants' => count( (array) ( $item['variants'] ?? array() ) ) );
	}

	/**
	 * @param array $input { id, confirm }.
	 * @return array|\WP_Error
	 */
	public function execute_delete_global_class( $input ) {
		if ( ! self::is_available() ) {
			return $this->unavailable();
		}
		$id = sanitize_text_field( (string) ( $input['id'] ?? '' ) );
		if ( '' === $id ) {
			return new \WP_Error( 'missing_id', __( 'A class "id" is required.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error( 'confirm_required', __( 'Deleting a global class also removes it from every element using it. Pass confirm:true to proceed.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$state = $this->read_state();
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		list( $items, $order ) = $state;
		if ( ! isset( $items[ $id ] ) ) {
			return new \WP_Error( 'not_found', sprintf( __( 'Global class not found: %s', 'emcp-tools' ), $id ), array( 'status' => 404 ) );
		}
		unset( $items[ $id ] );
		$order = array_values( array_filter( $order, static function ( $oid ) use ( $id ) {
			return $oid !== $id;
		} ) );

		$saved = $this->write_state( $items, $order );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return array( 'deleted' => $id );
	}

	/**
	 * @param array $input { order: string[] }.
	 * @return array|\WP_Error
	 */
	public function execute_reorder_global_classes( $input ) {
		if ( ! self::is_available() ) {
			return $this->unavailable();
		}
		$requested = ( isset( $input['order'] ) && is_array( $input['order'] ) )
			? array_values( array_map( 'sanitize_text_field', array_map( 'strval', $input['order'] ) ) )
			: array();
		if ( empty( $requested ) ) {
			return new \WP_Error( 'missing_order', __( 'Provide an "order" array of g- class ids.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		$state = $this->read_state();
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		list( $items, $current_order ) = $state;

		// The set of existing classes is the UNION of the current order and the
		// items map — either source may be momentarily incomplete, and reorder must
		// never drop a class from the Class Manager. `known_order` keeps a stable
		// baseline order (current order first, then any items not yet in it).
		$known_order = array();
		$known       = array();
		foreach ( array_merge( $current_order, array_keys( $items ) ) as $id ) {
			$id = (string) $id;
			if ( '' !== $id && ! isset( $known[ $id ] ) ) {
				$known[ $id ]  = true;
				$known_order[] = $id;
			}
		}

		$unknown = array();
		foreach ( $requested as $id ) {
			if ( ! isset( $known[ $id ] ) ) {
				$unknown[] = $id;
			}
		}
		if ( ! empty( $unknown ) ) {
			return new \WP_Error(
				'unknown_class',
				sprintf( __( 'Unknown global class id(s): %s', 'emcp-tools' ), implode( ', ', $unknown ) ),
				array( 'status' => 404 )
			);
		}

		// Final order = the requested ids (deduped), then every other known class
		// the caller omitted — appended in the baseline order so none drop out.
		$seen  = array();
		$final = array();
		foreach ( array_merge( $requested, $known_order ) as $id ) {
			if ( isset( $known[ $id ] ) && ! isset( $seen[ $id ] ) ) {
				$seen[ $id ] = true;
				$final[]     = $id;
			}
		}

		$saved = $this->write_order( $final );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return array( 'order' => $final );
	}

	// ── helpers ────────────────────────────────────────────────────────────────

	/**
	 * Persist a new class order (order only — no class content change). Uses
	 * Elementor's dedicated order API, which mirrors to preview itself.
	 *
	 * @param array $order Ordered id list.
	 * @return true|\WP_Error
	 */
	private function write_order( array $order ) {
		try {
			$repo = $this->repo();
			if ( method_exists( $repo, 'update_order_and_labels' ) ) {
				$repo->update_order_and_labels( $order, array() );
				return true;
			}
			// Fallback for older Elementor: rewrite via put (also mirrored to preview).
			$state = $this->read_state();
			if ( is_wp_error( $state ) ) {
				return $state;
			}
			list( $items ) = $state;
			$this->repo()->put( $items, $order );
			if ( method_exists( $repo, 'set_preview' ) ) {
				$this->repo()->set_preview( true )->put( $items, $order );
			}
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'reorder_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
		return true;
	}

	/**
	 * @return \WP_Error
	 */
	private function unavailable(): \WP_Error {
		return new \WP_Error( 'unavailable', __( 'Global Classes are not available; Elementor 4.0+ is required.', 'emcp-tools' ), array( 'status' => 501 ) );
	}

	/**
	 * A fresh repository instance.
	 *
	 * @return object
	 */
	private function repo() {
		$repo = self::REPOSITORY;
		return $repo::make();
	}

	/**
	 * Read the current class set as [ id=>item map, order[] ].
	 *
	 * @return array|\WP_Error [ items, order ].
	 */
	private function read_state() {
		try {
			$repo  = $this->repo();
			$all   = $repo->all();
			$items = method_exists( $all, 'get_items' ) ? $all->get_items() : $all;
			if ( is_object( $items ) && method_exists( $items, 'all' ) ) {
				$items = $items->all();
			}
			$items = (array) $items;
			// Normalize each item to an array.
			foreach ( $items as $k => $v ) {
				$items[ $k ] = (array) $v;
			}
			$order = (array) $repo->get_order();
			if ( empty( $order ) ) {
				$order = array_keys( $items );
			}
			return array( $items, array_values( $order ) );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'read_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Persist the class set to the frontend context, and best-effort to preview so
	 * the editor reflects the change.
	 *
	 * @param array $items id=>item map.
	 * @param array $order Ordered id list.
	 * @return true|\WP_Error
	 */
	private function write_state( array $items, array $order ) {
		try {
			$this->repo()->put( $items, $order );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'write_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
		try {
			$preview = $this->repo();
			if ( method_exists( $preview, 'set_preview' ) ) {
				$preview->set_preview( true )->put( $items, $order );
			}
		} catch ( \Throwable $e ) {
			// Preview mirror is best-effort; the frontend write is authoritative.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[EMCP Tools] global-class preview mirror failed: ' . $e->getMessage() );
			}
		}
		return true;
	}

	/**
	 * Longhand / CSS names Elementor stores under a different schema key.
	 *
	 * @var array<string, string>
	 */
	const PROP_ALIASES = array(
		'padding-top'          => 'padding',
		'padding-right'        => 'padding',
		'padding-bottom'       => 'padding',
		'padding-left'         => 'padding',
		'padding-block-start'  => 'padding',
		'padding-block-end'    => 'padding',
		'padding-inline-start' => 'padding',
		'padding-inline-end'   => 'padding',
		'margin-top'           => 'margin',
		'margin-right'         => 'margin',
		'margin-bottom'        => 'margin',
		'margin-left'          => 'margin',
		'margin-block-start'   => 'margin',
		'margin-block-end'     => 'margin',
		'margin-inline-start'  => 'margin',
		'margin-inline-end'    => 'margin',
		'border-top-width'     => 'border-width',
		'border-right-width'   => 'border-width',
		'border-bottom-width'  => 'border-width',
		'border-left-width'    => 'border-width',
		'border-top-style'     => 'border-style',
		'border-right-style'   => 'border-style',
		'border-bottom-style'  => 'border-style',
		'border-left-style'    => 'border-style',
		'border-top-color'     => 'border-color',
		'border-right-color'   => 'border-color',
		'border-bottom-color'  => 'border-color',
		'border-left-color'    => 'border-color',
		'flex-grow'            => 'flex',
		'flex-shrink'          => 'flex',
		'flex-basis'           => 'flex',
		'background-color'     => 'background',
	);

	/**
	 * Build a variant's props from friendly `styles` + raw `props`.
	 *
	 * @param array $input Tool input.
	 * @return array|\WP_Error CSS prop => $$type map.
	 */
	private function build_variant_props( array $input ) {
		$styles = ( isset( $input['styles'] ) && is_array( $input['styles'] ) ) ? $input['styles'] : array();
		$props  = array();
		if ( ! empty( $styles ) && class_exists( 'EMCP_Tools_Atomic_Styles' ) ) {
			$common = EMCP_Tools_Atomic_Styles::build_common_props( $styles );
			if ( is_wp_error( $common ) ) {
				return $common;
			}
			$flex = EMCP_Tools_Atomic_Styles::build_flex_props( $styles );
			if ( is_wp_error( $flex ) ) {
				return $flex;
			}
			$props = array_merge( $common, $flex );
		}
		if ( isset( $input['props'] ) && is_array( $input['props'] ) ) {
			$rejected = $this->rejected_style_props( $input['props'] );
			if ( ! empty( $rejected ) ) {
				return new \WP_Error(
					'unsupported_style_props',
					sprintf(
						/* translators: %s: property names and hints */
						__( 'These style properties are not in Elementor\'s Global Class schema and would be silently discarded: %s', 'emcp-tools' ),
						implode( '; ', $rejected )
					),
					array( 'status' => 400, 'rejected' => array_keys( $rejected ) )
				);
			}
			// Raw escape hatch wins over built styles for the same key.
			$props = array_merge( $props, $input['props'] );
		}

		return $props;
	}

	/**
	 * Keys Elementor will keep when compiling Global Class CSS.
	 *
	 * @return string[]
	 */
	public static function allowed_style_prop_keys(): array {
		if ( class_exists( '\\Elementor\\Modules\\AtomicWidgets\\Styles\\Style_Schema' ) ) {
			$schema = \Elementor\Modules\AtomicWidgets\Styles\Style_Schema::get();
			if ( is_array( $schema ) && ! empty( $schema ) ) {
				return array_keys( $schema );
			}
		}

		return array(
			'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
			'overflow', 'aspect-ratio', 'object-fit', 'object-position',
			'position', 'inset-block-start', 'inset-inline-end', 'inset-block-end', 'inset-inline-start',
			'z-index', 'scroll-margin-top',
			'font-family', 'font-weight', 'font-size', 'color', 'letter-spacing', 'word-spacing',
			'column-count', 'column-gap', 'line-height', 'text-align', 'font-style',
			'text-decoration', 'text-transform', 'direction', 'stroke', 'all', 'cursor',
			'padding', 'margin',
			'border-radius', 'border-width', 'border-color', 'border-style',
			'outline-width', 'outline-color', 'outline-style', 'outline-offset',
			'background',
			'mix-blend-mode', 'box-shadow', 'opacity', 'filter', 'backdrop-filter', 'transform', 'transition',
			'display', 'flex-direction', 'gap', 'flex-wrap', 'flex',
			'grid-template-columns', 'grid-template-rows', 'grid-auto-flow', 'grid-auto-rows',
			'grid-auto-columns', 'grid-column', 'grid-row',
			'justify-content', 'justify-items', 'align-items', 'align-self', 'align-content',
		);
	}

	/**
	 * Human-readable reasons for props Elementor would drop.
	 *
	 * @param array $props Built variant props.
	 * @return array<string, string> rejected key => hint
	 */
	public function rejected_style_props( array $props ): array {
		$allowed  = array_fill_keys( self::allowed_style_prop_keys(), true );
		$rejected = array();

		foreach ( array_keys( $props ) as $key ) {
			$key = (string) $key;
			if ( isset( $allowed[ $key ] ) ) {
				continue;
			}
			if ( isset( self::PROP_ALIASES[ $key ] ) ) {
				$rejected[ $key ] = sprintf( '%s (use "%s")', $key, self::PROP_ALIASES[ $key ] );
				continue;
			}
			$rejected[ $key ] = sprintf( '%s (not in the atomic style schema)', $key );
		}

		return $rejected;
	}

	/**
	 * The variant meta from input (validated breakpoint, optional state).
	 *
	 * @param array $input Tool input.
	 * @return array { breakpoint, state }.
	 */
	private function variant_meta( array $input ): array {
		$bp = isset( $input['breakpoint'] ) ? sanitize_key( (string) $input['breakpoint'] ) : 'desktop';
		if ( ! in_array( $bp, self::BREAKPOINTS, true ) ) {
			$bp = 'desktop';
		}
		$state = null;
		if ( isset( $input['state'] ) && '' !== trim( (string) $input['state'] ) ) {
			$state = preg_replace( '/[^a-z-]/', '', strtolower( (string) $input['state'] ) );
			$state = '' !== $state ? $state : null;
		}
		return array( 'breakpoint' => $bp, 'state' => $state );
	}

	/**
	 * Index of the variant matching a meta, or null.
	 *
	 * @param array $variants Variants.
	 * @param array $meta     { breakpoint, state }.
	 * @return int|null
	 */
	private function find_variant_index( array $variants, array $meta ): ?int {
		foreach ( array_values( $variants ) as $i => $v ) {
			$vm = (array) ( ( (array) $v )['meta'] ?? array() );
			$bp = $vm['breakpoint'] ?? 'desktop';
			$st = $vm['state'] ?? null;
			if ( $bp === $meta['breakpoint'] && $st === $meta['state'] ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Mint a unique g- class id not colliding with existing items.
	 *
	 * @param array $items Existing items.
	 * @return string
	 */
	private function mint_id( array $items ): string {
		do {
			$id = 'g-' . substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
		} while ( isset( $items[ $id ] ) );
		return $id;
	}
}
