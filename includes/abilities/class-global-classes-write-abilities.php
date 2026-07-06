<?php
/**
 * Global Classes (Class Manager) WRITE MCP abilities — Elementor 4.0+.
 *
 * Companion to the read-only Elementor_MCP_Global_Classes_Abilities. Where the
 * read tool resolves opaque `g-` IDs back to names + CSS, these four write tools
 * let an agent author the design system itself: create / update / delete Global
 * Classes and apply them to atomic elements on a page.
 *
 * Registers only when Elementor's Global Classes repository is present
 * (Elementor 4.0+). Writes are gated on `manage_options` — mutating the shared
 * design system is a site-wide operation, not per-post.
 *
 * Context: writes go through the repository's default (frontend/published)
 * context, deliberately NOT the editor preview context, respecting the publish
 * boundary (a preview write would clobber a user's unpublished in-editor Global
 * Class draft). Each mutation uses the touched-item API `apply_changes( $touched,
 * $changes, $order )` (see apply_change()) so that only the class actually being
 * created/updated/deleted has its preview override reconciled — the bulk `put()`
 * would instead mark every existing id as "modified" and clear ALL preview
 * overrides, discarding unrelated in-editor drafts. We also do NOT manually
 * mirror labels/order into the `_preview` meta. Consequence: changes are live on
 * the published frontend immediately and appear in the editor on next open; an
 * already-open editor may need a refresh (standard for any external edit).
 *
 * @package Elementor_MCP
 * @since   1.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes Elementor Global Classes write operations over MCP.
 *
 * @since 1.14.0
 */
class Elementor_MCP_Global_Classes_Write_Abilities {

	/**
	 * Elementor's global-classes repository class.
	 */
	const REPOSITORY = '\\Elementor\\Modules\\GlobalClasses\\Global_Classes_Repository';

	/**
	 * Elementor's atomic style schema (present on atomic-capable installs).
	 */
	const STYLE_SCHEMA = '\\Elementor\\Modules\\AtomicWidgets\\Styles\\Style_Schema';

	/**
	 * The data access layer.
	 *
	 * @var Elementor_MCP_Data
	 */
	private $data;

	/**
	 * The ability names registered by this class.
	 *
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * Constructor.
	 *
	 * @param Elementor_MCP_Data $data The data access layer.
	 */
	public function __construct( Elementor_MCP_Data $data ) {
		$this->data = $data;
	}

	/**
	 * Whether Elementor exposes a Global Classes repository we can WRITE through.
	 *
	 * The repository's write API changed shape over Elementor's v4 line:
	 *   get/delete/patch (2024-11) → put( string $id, array $value ) per-class
	 *   (2024-11 … 2025-01) → put( array $items, array $order ) bulk (2025-02+)
	 *   → apply_changes( $touched, $changes, $order ) added (2026-05+).
	 * We only support the two write paths apply_change() actually uses — the
	 * touched-item `apply_changes()` (preferred) and the bulk `put(array, …)`
	 * fallback — so on ancient per-class-`put()` / get-delete-patch builds the
	 * write tools simply don't register rather than failing at call time.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		if ( ! class_exists( self::REPOSITORY ) ) {
			return false;
		}
		if ( method_exists( self::REPOSITORY, 'apply_changes' ) ) {
			return true;
		}
		return self::has_bulk_put();
	}

	/**
	 * Whether the repository exposes the bulk `put( array $items, array $order )`
	 * signature (vs the historical per-class `put( string $id, array $value )`),
	 * checked via reflection on the first parameter's type.
	 *
	 * @return bool
	 */
	private static function has_bulk_put(): bool {
		if ( ! method_exists( self::REPOSITORY, 'put' ) ) {
			return false;
		}
		try {
			$params = ( new \ReflectionMethod( self::REPOSITORY, 'put' ) )->getParameters();
			if ( empty( $params ) ) {
				return false;
			}
			$type = $params[0]->getType();
			return $type instanceof \ReflectionNamedType && 'array' === $type->getName();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Returns the ability names registered by this class.
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			'elementor-mcp/create-global-class',
			'elementor-mcp/update-global-class',
			'elementor-mcp/delete-global-class',
			'elementor-mcp/apply-global-class',
		);
	}

	/**
	 * Permission check for Global Classes writes.
	 *
	 * Mutating the shared design system is a site-wide operation, gated on
	 * `manage_options`.
	 *
	 * @return bool
	 */
	public function check_write_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Registers the Global Classes write abilities.
	 */
	public function register(): void {
		if ( ! self::is_available() ) {
			return;
		}

		$this->register_create();
		$this->register_update();
		$this->register_delete();
		$this->register_apply();
	}

	// =========================================================================
	// Registration
	// =========================================================================

	/**
	 * JSON-Schema fragment describing a responsive/state variant.
	 *
	 * @return array
	 */
	private static function variant_schema(): array {
		return array(
			'type'        => 'array',
			'description' => __( 'Optional responsive/state variants. Each item is { breakpoint, state, styles } where styles is a CSS-prop->value map for that variant.', 'elementor-mcp' ),
			'items'       => array(
				'type'       => 'object',
				'properties' => array(
					'breakpoint' => array( 'type' => 'string', 'description' => __( 'Breakpoint (e.g. tablet, mobile). Null/omitted = base desktop.', 'elementor-mcp' ) ),
					'state'      => array( 'type' => 'string', 'description' => __( 'CSS state (e.g. hover, focus, active). Null/omitted = normal.', 'elementor-mcp' ) ),
					'styles'     => array( 'type' => 'object', 'description' => __( 'CSS-prop->value map for this variant.', 'elementor-mcp' ) ),
				),
			),
		);
	}

	private function register_create(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/create-global-class',
			array(
				'label'               => __( 'Create Global Class', 'elementor-mcp' ),
				'description'         => __( 'Creates a new Elementor 4.0+ Global Class (Class Manager entry) that can be reused across pages. Pass a human-readable label and a styles map of CSS properties to ergonomic values (e.g. {"color":"#111","padding":24}); values are wrapped into Elementor\'s atomic prop format automatically. Optionally pass variants for responsive breakpoints / states. Returns the minted g- id. Requires manage_options.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_create' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'label'    => array( 'type' => 'string', 'description' => __( 'Human-readable class name (e.g. "card-base").', 'elementor-mcp' ) ),
						'styles'   => array( 'type' => 'object', 'description' => __( 'Base (desktop) variant: CSS-prop->value map (e.g. {"color":"#111","padding":24}).', 'elementor-mcp' ) ),
						'variants' => self::variant_schema(),
					),
					'required'   => array( 'label', 'styles' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'string' ),
						'label'   => array( 'type' => 'string' ),
						'created' => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/update-global-class',
			array(
				'label'               => __( 'Update Global Class', 'elementor-mcp' ),
				'description'         => __( 'Updates an existing Elementor 4.0+ Global Class in place, preserving its g- id so element bindings survive. Pass class_id plus at least one of: label (rename), styles (replaces ONLY the base/desktop variant, keeping other variants), variants (replaces matching breakpoint/state variants). Requires manage_options.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_update' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'class_id' => array( 'type' => 'string', 'description' => __( 'The g- id of the class to update.', 'elementor-mcp' ) ),
						'label'    => array( 'type' => 'string', 'description' => __( 'Optional new label.', 'elementor-mcp' ) ),
						'styles'   => array( 'type' => 'object', 'description' => __( 'Optional replacement base/desktop styles (CSS-prop->value map).', 'elementor-mcp' ) ),
						'variants' => self::variant_schema(),
					),
					'required'   => array( 'class_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'string' ),
						'updated' => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_delete(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/delete-global-class',
			array(
				'label'               => __( 'Delete Global Class', 'elementor-mcp' ),
				'description'         => __( 'Deletes an Elementor 4.0+ Global Class by its g- id. Elementor ignores dangling class references left on elements (no cascade / no re-write of pages), so existing elements that referenced it simply lose that styling. Requires manage_options.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_delete' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'class_id' => array( 'type' => 'string', 'description' => __( 'The g- id of the class to delete.', 'elementor-mcp' ) ),
					),
					'required'   => array( 'class_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'string' ),
						'deleted' => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_apply(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/apply-global-class',
			array(
				'label'               => __( 'Apply Global Class', 'elementor-mcp' ),
				'description'         => __( 'Applies an existing Global Class (by g- id) to an atomic element on a page, appending it to the element\'s settings.classes. The element must be an atomic element that has a classes control; non-atomic widgets are rejected with their schema. Idempotent — re-applying an already-present class is a no-op. Requires manage_options.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_apply' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'class_id'   => array( 'type' => 'string', 'description' => __( 'The g- id of the class to apply.', 'elementor-mcp' ) ),
						'post_id'    => array( 'type' => 'integer', 'description' => __( 'The post/page ID containing the element.', 'elementor-mcp' ) ),
						'element_id' => array( 'type' => 'string', 'description' => __( 'The target element ID.', 'elementor-mcp' ) ),
					),
					'required'   => array( 'class_id', 'post_id', 'element_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'applied'         => array( 'type' => 'boolean' ),
						'already_present' => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	// =========================================================================
	// Execute — create / update / delete
	// =========================================================================

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public function execute_create( $input ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'unavailable', __( 'Global Classes are not available — Elementor 4.0+ is required.', 'elementor-mcp' ) );
		}

		$label = isset( $input['label'] ) ? sanitize_text_field( $input['label'] ) : '';
		if ( '' === $label ) {
			return new \WP_Error( 'missing_label', __( 'label is required.', 'elementor-mcp' ) );
		}
		if ( ! isset( $input['styles'] ) || ! is_array( $input['styles'] ) ) {
			return new \WP_Error( 'missing_styles', __( 'styles must be a CSS-prop->value object.', 'elementor-mcp' ) );
		}

		$state = $this->read_state();
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		list( $items, $order ) = $state;

		// Elementor V4 caps the Global Classes count (100 as of current; 50 in
		// alpha). The cap is enforced at Elementor's REST/save layer, not in the
		// repository's put(), so a direct write can push past it and then wedge the
		// editor's own Class Manager saves until a class is removed. Refuse up front.
		$limit = $this->class_limit();
		if ( count( $items ) >= $limit ) {
			return new \WP_Error(
				'class_limit_reached',
				sprintf(
					/* translators: %d: the Global Classes limit */
					__( 'Elementor\'s Global Classes limit (%d) has been reached. Delete an unused class before creating a new one.', 'elementor-mcp' ),
					$limit
				),
				array( 'limit' => $limit, 'count' => count( $items ) )
			);
		}

		// Build the base variant props (validated) plus any extra variants.
		$base_props = $this->wrap_and_validate( (array) $input['styles'] );
		if ( is_wp_error( $base_props ) ) {
			return $base_props;
		}

		$variants   = array( $this->build_variant( null, null, $base_props ) );
		$extra      = $this->build_extra_variants( $input['variants'] ?? array() );
		if ( is_wp_error( $extra ) ) {
			return $extra;
		}
		$variants = array_merge( $variants, $extra );

		$id = $this->mint_id( $items );

		$items[ $id ] = array(
			'id'       => $id,
			'type'     => 'class',
			'label'    => $label,
			'variants' => $variants,
		);
		$order[]      = $id;

		$put = $this->apply_change(
			array( $id => $items[ $id ] ),
			array( 'added' => array( $id ), 'modified' => array(), 'deleted' => array(), 'order' => true ),
			$order,
			$items
		);
		if ( is_wp_error( $put ) ) {
			return $put;
		}

		$this->clear_cache();

		return array(
			'id'      => $id,
			'label'   => $label,
			'created' => true,
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public function execute_update( $input ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'unavailable', __( 'Global Classes are not available — Elementor 4.0+ is required.', 'elementor-mcp' ) );
		}

		$class_id = isset( $input['class_id'] ) ? sanitize_text_field( $input['class_id'] ) : '';
		if ( '' === $class_id ) {
			return new \WP_Error( 'missing_class_id', __( 'class_id is required.', 'elementor-mcp' ) );
		}
		$has_label    = isset( $input['label'] );
		$has_styles   = isset( $input['styles'] ) && is_array( $input['styles'] );
		$has_variants = isset( $input['variants'] ) && is_array( $input['variants'] ) && ! empty( $input['variants'] );
		if ( ! $has_label && ! $has_styles && ! $has_variants ) {
			return new \WP_Error( 'nothing_to_update', __( 'Provide at least one of: label, styles, variants.', 'elementor-mcp' ) );
		}

		$state = $this->read_state();
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		list( $items, $order ) = $state;

		if ( ! isset( $items[ $class_id ] ) ) {
			return new \WP_Error(
				'class_not_found',
				sprintf( /* translators: %s: class id */ __( 'Global Class "%s" was not found.', 'elementor-mcp' ), $class_id )
			);
		}

		$entry             = (array) $items[ $class_id ];
		$entry['id']       = $class_id; // Preserve the id — bindings survive.
		$entry['type']     = 'class';
		$entry['variants'] = isset( $entry['variants'] ) && is_array( $entry['variants'] ) ? array_values( $entry['variants'] ) : array();

		if ( $has_label ) {
			$entry['label'] = sanitize_text_field( $input['label'] );
		}

		if ( $has_styles ) {
			$base_props = $this->wrap_and_validate( (array) $input['styles'] );
			if ( is_wp_error( $base_props ) ) {
				return $base_props;
			}
			$entry['variants'] = $this->replace_variant( $entry['variants'], null, null, $base_props );
		}

		if ( $has_variants ) {
			foreach ( $input['variants'] as $variant ) {
				$variant = (array) $variant;
				$bp      = $this->norm_breakpoint( $variant['breakpoint'] ?? null );
				$st      = $this->norm_state( $variant['state'] ?? null );
				$props   = $this->wrap_and_validate( (array) ( $variant['styles'] ?? array() ) );
				if ( is_wp_error( $props ) ) {
					return $props;
				}
				$entry['variants'] = $this->replace_variant( $entry['variants'], $bp, $st, $props );
			}
		}

		$items[ $class_id ] = $entry;

		$put = $this->apply_change(
			array( $class_id => $entry ),
			array( 'added' => array(), 'modified' => array( $class_id ), 'deleted' => array(), 'order' => false ),
			$order,
			$items
		);
		if ( is_wp_error( $put ) ) {
			return $put;
		}

		$this->clear_cache();

		return array(
			'id'      => $class_id,
			'updated' => true,
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public function execute_delete( $input ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'unavailable', __( 'Global Classes are not available — Elementor 4.0+ is required.', 'elementor-mcp' ) );
		}

		$class_id = isset( $input['class_id'] ) ? sanitize_text_field( $input['class_id'] ) : '';
		if ( '' === $class_id ) {
			return new \WP_Error( 'missing_class_id', __( 'class_id is required.', 'elementor-mcp' ) );
		}

		$state = $this->read_state();
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		list( $items, $order ) = $state;

		if ( ! isset( $items[ $class_id ] ) ) {
			return new \WP_Error(
				'class_not_found',
				sprintf( /* translators: %s: class id */ __( 'Global Class "%s" was not found.', 'elementor-mcp' ), $class_id )
			);
		}

		unset( $items[ $class_id ] );
		$order = array_values(
			array_filter(
				$order,
				static function ( $id ) use ( $class_id ) {
					return $id !== $class_id;
				}
			)
		);

		$put = $this->apply_change(
			array(),
			array( 'added' => array(), 'modified' => array(), 'deleted' => array( $class_id ), 'order' => true ),
			$order,
			$items
		);
		if ( is_wp_error( $put ) ) {
			return $put;
		}

		$this->clear_cache();

		return array(
			'id'      => $class_id,
			'deleted' => true,
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public function execute_apply( $input ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'unavailable', __( 'Global Classes are not available — Elementor 4.0+ is required.', 'elementor-mcp' ) );
		}

		$class_id   = isset( $input['class_id'] ) ? sanitize_text_field( $input['class_id'] ) : '';
		$post_id    = absint( $input['post_id'] ?? 0 );
		$element_id = isset( $input['element_id'] ) ? sanitize_text_field( $input['element_id'] ) : '';
		if ( '' === $class_id || ! $post_id || '' === $element_id ) {
			return new \WP_Error( 'missing_input', __( 'class_id, post_id and element_id are all required.', 'elementor-mcp' ) );
		}

		// The class must exist in the repository before we bind it.
		$state = $this->read_state();
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		list( $items ) = $state;
		if ( ! isset( $items[ $class_id ] ) ) {
			return new \WP_Error(
				'class_not_found',
				sprintf( /* translators: %s: class id */ __( 'Global Class "%s" was not found.', 'elementor-mcp' ), $class_id )
			);
		}

		$page_data = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$element = $this->data->find_element_by_id( $page_data, $element_id );
		if ( null === $element ) {
			return new \WP_Error(
				'element_not_found',
				sprintf( /* translators: %s: element id */ __( 'Element "%s" was not found on this page.', 'elementor-mcp' ), $element_id )
			);
		}

		// Hard-reject non-atomic elements (no `classes` control). Embed the widget
		// type + compact schema so the agent can self-correct (schema-in-error).
		if ( ! $this->element_supports_classes( $element ) ) {
			$type   = $this->element_type( $element );
			$schema = $this->compact_schema( $element );
			$json   = wp_json_encode( $schema );
			return new \WP_Error(
				'not_atomic',
				sprintf(
					/* translators: 1: element/widget type, 2: compact schema JSON */
					__( 'Element "%1$s" is not an atomic element and has no classes control, so a Global Class cannot be applied to it. Its settings schema is: %2$s', 'elementor-mcp' ),
					$type,
					false === $json ? '{}' : $json
				),
				$schema
			);
		}

		// Read the current classes value, dedupe, append.
		$existing = array();
		if ( isset( $element['settings']['classes']['value'] ) && is_array( $element['settings']['classes']['value'] ) ) {
			$existing = array_values( array_map( 'strval', $element['settings']['classes']['value'] ) );
		}

		if ( in_array( $class_id, $existing, true ) ) {
			return array(
				'applied'         => true,
				'already_present' => true,
			);
		}

		$existing[] = $class_id;

		$settings = array(
			'classes' => Elementor_MCP_Atomic_Props::classes( $existing ),
		);

		$updated = $this->data->update_element_settings( $page_data, $element_id, $settings );
		if ( ! $updated ) {
			return new \WP_Error(
				'element_not_found',
				sprintf( /* translators: %s: element id */ __( 'Element "%s" was not found on this page.', 'elementor-mcp' ), $element_id )
			);
		}

		$save = $this->data->save_page_data( $post_id, $page_data );
		if ( is_wp_error( $save ) ) {
			return $save;
		}

		return array(
			'applied'         => true,
			'already_present' => false,
		);
	}

	// =========================================================================
	// Repository read / write helpers
	// =========================================================================

	/**
	 * Reads the current repository state (items map + order list) defensively.
	 *
	 * Mirrors the read class's normalization so it tolerates stub/shape variance
	 * (method_exists / Collection ->all() / (array) casts).
	 *
	 * @return array{0: array, 1: array}|\WP_Error [ $items, $order ] or WP_Error.
	 */
	private function read_state() {
		$repo = self::REPOSITORY;
		try {
			$ctx = $repo::make()->all();

			// NB: is_object() before method_exists() — on PHP 8 method_exists()
			// TypeErrors on an array arg, which would otherwise kill the array-shape
			// fallback (and be caught as read_failed).
			$items = ( is_object( $ctx ) && method_exists( $ctx, 'get_items' ) )
				? $ctx->get_items()
				: ( is_array( $ctx ) && isset( $ctx['items'] ) ? $ctx['items'] : $ctx );
			if ( is_object( $items ) && method_exists( $items, 'all' ) ) {
				$items = $items->all();
			}
			$items = (array) $items;

			$order = array();
			if ( is_object( $ctx ) && method_exists( $ctx, 'get_order' ) ) {
				$order = $ctx->get_order();
			} elseif ( is_array( $ctx ) && isset( $ctx['order'] ) ) {
				$order = $ctx['order'];
			}
			if ( is_object( $order ) && method_exists( $order, 'all' ) ) {
				$order = $order->all();
			}
			$order = array_values( (array) $order );

			// If order is empty but we have items, derive a stable order.
			if ( empty( $order ) && ! empty( $items ) ) {
				$order = array_map( 'strval', array_keys( $items ) );
			}
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'read_failed', $e->getMessage() );
		}

		return array( $items, $order );
	}

	/**
	 * Writes a single Global Classes mutation, preserving OTHER classes' preview
	 * drafts.
	 *
	 * Prefers Elementor's touched-item API `apply_changes( $touched, $changes,
	 * $order )` when the build exposes it: it clears the preview overrides only
	 * for the ids named in $changes (added/modified/deleted). The bulk
	 * `put( $items, $order )` cannot be used for this — it derives
	 * `modified = array_intersect( new_ids, current_ids )` (i.e. EVERY existing id
	 * in the passed map, not a value-diff) and then `bulk_clear_preview_meta()` +
	 * `clear_preview_labels_for_ids()` over all of them, discarding a user's
	 * unpublished in-editor drafts for unrelated classes. We fall back to put()
	 * only on older builds without apply_changes() (correctness over the
	 * draft-preservation nicety there).
	 *
	 * @param array $touched Map id=>item for added/updated classes ([] for delete).
	 * @param array $changes { added:[], modified:[], deleted:[], order:bool }.
	 * @param array $order   Full new class-id order.
	 * @param array $items   Full items map (fallback put()).
	 * @return true|\WP_Error
	 */
	private function apply_change( array $touched, array $changes, array $order, array $items ) {
		$repo = self::REPOSITORY;
		try {
			$r = $repo::make();
			if ( method_exists( $r, 'apply_changes' ) ) {
				$r->apply_changes( $touched, $changes, array_values( $order ) );
			} else {
				$r->put( $items, array_values( $order ) );
			}
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'write_failed', $e->getMessage() );
		}
		return true;
	}

	/**
	 * Mints a fresh `g-<7hex>` id (Elementor editor format), regenerating on
	 * collision with an existing id.
	 *
	 * @param array $items Existing items map.
	 * @return string
	 */
	/**
	 * The Global Classes count cap Elementor enforces at its save layer. Defaults
	 * to Elementor's current 100 (was 50 in alpha), read from Elementor's own
	 * constant when one is exposed, and filterable for forward-compat.
	 *
	 * @return int
	 */
	private function class_limit(): int {
		$limit = 100;
		// Prefer Elementor's own constant if a build exposes one.
		foreach ( array( self::REPOSITORY . '::MAX_ITEMS', '\\Elementor\\Modules\\GlobalClasses\\Global_Classes_REST::MAX_ITEMS' ) as $const ) {
			if ( defined( $const ) ) {
				$val = (int) constant( $const );
				if ( $val > 0 ) {
					$limit = $val;
					break;
				}
			}
		}
		/**
		 * Filters the Global Classes count cap enforced before create.
		 *
		 * @since 1.14.0
		 * @param int $limit The maximum number of global classes.
		 */
		return (int) apply_filters( 'elementor_mcp_global_classes_limit', $limit );
	}

	private function mint_id( array $items ): string {
		do {
			$id = 'g-' . substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
		} while ( isset( $items[ $id ] ) );
		return $id;
	}

	// =========================================================================
	// Variant builders
	// =========================================================================

	/**
	 * Builds a single variant structure (see class-atomic-styles.php).
	 *
	 * @param string|null $breakpoint Breakpoint or null for base.
	 * @param string|null $state      State or null for normal.
	 * @param array       $props      Wrapped $$type props.
	 * @return array
	 */
	private function build_variant( ?string $breakpoint, ?string $state, array $props ): array {
		return array(
			'meta'       => array(
				// Elementor's atomic style parser expects a string breakpoint; the
				// base (responsive-default) variant is stored as 'desktop' — matching
				// the editor's own variant builder and this fork's create_local_class
				// (class-atomic-styles.php). A null base can fail to round-trip
				// through the Class Manager/REST parser. Reads normalize either way
				// (norm_breakpoint / the read class both fold 'desktop' <-> base).
				'breakpoint' => null === $breakpoint ? 'desktop' : $breakpoint,
				'state'      => $state,
			),
			'props'      => $props,
			'custom_css' => null,
		);
	}

	/**
	 * Builds the extra (responsive/state) variants from input.
	 *
	 * @param mixed $variants Raw variants input.
	 * @return array|\WP_Error
	 */
	private function build_extra_variants( $variants ) {
		$out = array();
		if ( ! is_array( $variants ) ) {
			return $out;
		}
		foreach ( $variants as $variant ) {
			$variant = (array) $variant;
			$bp      = $this->norm_breakpoint( $variant['breakpoint'] ?? null );
			$st      = $this->norm_state( $variant['state'] ?? null );
			$props   = $this->wrap_and_validate( (array) ( $variant['styles'] ?? array() ) );
			if ( is_wp_error( $props ) ) {
				return $props;
			}
			$out[] = $this->build_variant( $bp, $st, $props );
		}
		return $out;
	}

	/**
	 * Replaces (or appends) the variant matching a breakpoint/state, preserving
	 * all other variants.
	 *
	 * The base variant is the one with breakpoint in {null,'desktop'} AND state
	 * in {null,''}.
	 *
	 * @param array       $variants   Existing variants.
	 * @param string|null $breakpoint Target breakpoint.
	 * @param string|null $state      Target state.
	 * @param array       $props      New wrapped props.
	 * @return array
	 */
	private function replace_variant( array $variants, ?string $breakpoint, ?string $state, array $props ): array {
		$replaced = false;
		foreach ( $variants as $i => $variant ) {
			$variant = (array) $variant;
			$meta    = (array) ( $variant['meta'] ?? array() );
			$bp      = $this->norm_breakpoint( $meta['breakpoint'] ?? null );
			$st      = $this->norm_state( $meta['state'] ?? null );
			if ( $bp === $breakpoint && $st === $state ) {
				$variants[ $i ] = $this->build_variant( $breakpoint, $state, $props );
				$replaced       = true;
				break;
			}
		}
		if ( ! $replaced ) {
			$variants[] = $this->build_variant( $breakpoint, $state, $props );
		}
		return array_values( $variants );
	}

	/**
	 * Normalizes a breakpoint: 'desktop'/''/'null' -> null (base variant).
	 *
	 * @param mixed $bp Raw breakpoint.
	 * @return string|null
	 */
	private function norm_breakpoint( $bp ): ?string {
		if ( null === $bp ) {
			return null;
		}
		$bp = sanitize_text_field( (string) $bp );
		if ( '' === $bp || 'desktop' === $bp || 'null' === $bp ) {
			return null;
		}
		return $bp;
	}

	/**
	 * Normalizes a state: ''/'null'/'normal' -> null.
	 *
	 * @param mixed $state Raw state.
	 * @return string|null
	 */
	private function norm_state( $state ): ?string {
		if ( null === $state ) {
			return null;
		}
		$state = sanitize_text_field( (string) $state );
		if ( '' === $state || 'null' === $state || 'normal' === $state ) {
			return null;
		}
		return $state;
	}

	// =========================================================================
	// Props wrapping + validation
	// =========================================================================

	/**
	 * CSS property names whose ergonomic value is a color.
	 */
	// NB: no 'background-color' — Elementor v4's atomic schema represents
	// backgrounds through the structured `background` prop, not a top-level
	// `background-color`, so it would be rejected (schema present) or silently
	// ignored by the renderer (schema absent).
	const COLOR_PROPS = array(
		'color', 'border-color', 'border-top-color',
		'border-right-color', 'border-bottom-color', 'border-left-color',
		'fill', 'stroke', 'outline-color', 'text-decoration-color',
	);

	// CSS property names whose ergonomic value is a size (number + unit).
	// NB: no 'gap'/'row-gap'/'column-gap' — on Elementor v4 atomic, flex gap is
	// the structured `layout-direction` prop { row, column } (each a Size), and a
	// flat `gap` Size is dropped by the renderer (see class-atomic-styles.php gap
	// handling). Wrapping it as a plain size here would save but not render, so
	// it's deliberately excluded from the ergonomic map; structured gap support
	// is a follow-up. A `gap` passed anyway falls through to a string $$type and
	// is rejected by Style_Schema (honest failure) rather than silently dropped.
	const SIZE_PROPS = array(
		'padding', 'margin', 'width', 'height', 'min-width', 'max-width',
		'min-height', 'max-height', 'font-size', 'line-height', 'letter-spacing',
		'word-spacing', 'border-radius', 'border-width',
		'top', 'right', 'bottom', 'left', 'flex-basis',
		'text-indent', 'outline-width',
	);

	/**
	 * CSS property names whose ergonomic value is a unitless number — the atomic
	 * `number` prop type. Wrapping these as string (the fallback) would be
	 * rejected by Style_Schema / persisted with the wrong type.
	 */
	// NB: no 'opacity' — its atomic prop type (Size/percent vs plain number) is
	// version-dependent and not confidently determinable here, so it's left out
	// rather than mis-typed; passed anyway it falls through to string and the
	// schema surfaces an honest mismatch.
	const NUMBER_PROPS = array(
		'z-index', 'order', 'flex-grow', 'flex-shrink',
		'column-count', 'flex-order',
	);

	/**
	 * Wraps an ergonomic CSS-prop->value map into atomic $$type props, then
	 * (when Elementor's Style_Schema is present) validates the wrapped props
	 * against it — rejecting unknown properties / type mismatches with a
	 * schema-embedding WP_Error so the agent can self-correct without a round
	 * trip. When Style_Schema is absent, best-effort wrap without hard validation.
	 *
	 * @param array $styles CSS-prop->value map.
	 * @return array|\WP_Error Wrapped props, or WP_Error on validation failure.
	 */
	private function wrap_and_validate( array $styles ) {
		$wrapped = array();
		foreach ( $styles as $prop => $value ) {
			$prop             = (string) $prop;
			$wrapped[ $prop ] = $this->wrap_value( $prop, $value );
		}

		$validation = $this->validate_against_schema( $wrapped );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return $wrapped;
	}

	/**
	 * Wraps a single ergonomic value into its atomic $$type form.
	 *
	 * Reuses the Elementor_MCP_Atomic_Props primitives (size/string) and the
	 * inline color shape used across the atomic style builder.
	 *
	 * @param string $prop  CSS property name.
	 * @param mixed  $value Ergonomic value (scalar) or an already-wrapped array.
	 * @return array
	 */
	private function wrap_value( string $prop, $value ) {
		// Already wrapped ({ $$type, value }) — pass through untouched.
		if ( is_array( $value ) && isset( $value['$$type'] ) ) {
			return $value;
		}

		if ( in_array( $prop, self::COLOR_PROPS, true ) ) {
			return array( '$$type' => 'color', 'value' => (string) $value );
		}

		if ( in_array( $prop, self::SIZE_PROPS, true ) ) {
			return $this->wrap_size( $value );
		}

		if ( in_array( $prop, self::NUMBER_PROPS, true ) ) {
			// Unitless number ($$type:number). Keep ints as ints; a numeric string
			// like "3" becomes 3. Non-numeric input falls through to string so the
			// schema surfaces an honest mismatch rather than a silent 0.
			if ( is_int( $value ) || is_float( $value ) ) {
				return Elementor_MCP_Atomic_Props::number( $value );
			}
			if ( is_string( $value ) && is_numeric( trim( $value ) ) ) {
				$num = trim( $value );
				return Elementor_MCP_Atomic_Props::number( false === strpos( $num, '.' ) ? (int) $num : (float) $num );
			}
		}

		// Fallback: plain string prop (font-weight, text-align, display, ...).
		return Elementor_MCP_Atomic_Props::string( (string) $value );
	}

	/**
	 * Wraps a size value, parsing a trailing unit from strings like "24px".
	 *
	 * @param mixed $value Numeric or "<number><unit>" string.
	 * @return array
	 */
	private function wrap_size( $value ): array {
		if ( is_int( $value ) || is_float( $value ) ) {
			return Elementor_MCP_Atomic_Props::size( (float) $value, 'px' );
		}
		$str = trim( (string) $value );
		if ( preg_match( '/^(-?[0-9]*\.?[0-9]+)\s*([a-z%]*)$/i', $str, $m ) ) {
			$unit = '' !== $m[2] ? strtolower( $m[2] ) : 'px';
			return Elementor_MCP_Atomic_Props::size( (float) $m[1], $unit );
		}
		// Non-numeric (e.g. "auto") — represent as a string value.
		return Elementor_MCP_Atomic_Props::string( $str );
	}

	/**
	 * Validates wrapped props against Elementor's atomic Style_Schema when it's
	 * available, rejecting unknown properties (and, where confidently
	 * determinable, type mismatches) with a schema-embedding WP_Error.
	 *
	 * @param array $wrapped Wrapped $$type props.
	 * @return true|\WP_Error
	 */
	private function validate_against_schema( array $wrapped ) {
		$schema_class = self::STYLE_SCHEMA;
		if ( ! class_exists( $schema_class ) || ! method_exists( $schema_class, 'get' ) ) {
			return true; // Best-effort: no hard validation without the schema.
		}

		try {
			$schema = $schema_class::get();
		} catch ( \Throwable $e ) {
			return true; // If the schema can't be read, don't block the write.
		}
		if ( ! is_array( $schema ) || empty( $schema ) ) {
			return true;
		}

		$allowed  = array_keys( $schema );
		$unknown  = array();
		$mismatch = array();

		foreach ( $wrapped as $prop => $value ) {
			if ( ! in_array( $prop, $allowed, true ) ) {
				$unknown[] = $prop;
				continue;
			}
			// Best-effort scalar type check. Only compares when the expected type
			// key is confidently a simple scalar; union/object types are skipped
			// to avoid false rejections.
			$expected = $this->schema_type_key( $schema[ $prop ] );
			$actual   = is_array( $value ) && isset( $value['$$type'] ) ? (string) $value['$$type'] : gettype( $value );
			if ( null !== $expected && in_array( $expected, array( 'color', 'string', 'number', 'size' ), true ) && $expected !== $actual ) {
				$mismatch[ $prop ] = array( 'expected' => $expected, 'got' => $actual );
			}
		}

		if ( empty( $unknown ) && empty( $mismatch ) ) {
			return true;
		}

		$detail = array(
			'rejected_props'   => array_values( $unknown ),
			'type_mismatches'  => $mismatch,
			'allowed_props'    => $allowed,
		);

		return new \WP_Error(
			'invalid_styles',
			sprintf(
				/* translators: %s: JSON describing the rejected props + the allowed schema */
				__( 'One or more style properties are not valid for Elementor atomic Global Classes. Fix these and retry. %s', 'elementor-mcp' ),
				wp_json_encode( $detail )
			),
			$detail
		);
	}

	/**
	 * Attempts to read a schema prop type's simple type key.
	 *
	 * @param mixed $prop_type Schema entry (a Prop_Type object, typically).
	 * @return string|null The type key, or null if not confidently determinable.
	 */
	private function schema_type_key( $prop_type ): ?string {
		if ( is_object( $prop_type ) ) {
			foreach ( array( 'get_key', 'get_type' ) as $method ) {
				if ( method_exists( $prop_type, $method ) ) {
					try {
						$key = $prop_type->$method();
						return is_string( $key ) ? $key : null;
					} catch ( \Throwable $e ) {
						return null;
					}
				}
			}
		}
		return null;
	}

	// =========================================================================
	// Element inspection (apply)
	// =========================================================================

	/**
	 * The element/widget type string for messages.
	 *
	 * @param array $element Element array.
	 * @return string
	 */
	private function element_type( array $element ): string {
		if ( ! empty( $element['widgetType'] ) ) {
			return (string) $element['widgetType'];
		}
		if ( ! empty( $element['elType'] ) ) {
			return (string) $element['elType'];
		}
		return 'unknown';
	}

	/**
	 * Whether an element is atomic and exposes a `classes` control.
	 *
	 * Atomic elements/widgets use `e-` prefixed types and carry a `styles` map;
	 * their settings support a `classes` prop. Legacy (non-atomic) widgets do
	 * not, so a Global Class cannot be bound to them.
	 *
	 * @param array $element Element array.
	 * @return bool
	 */
	private function element_supports_classes( array $element ): bool {
		$type = $this->element_type( $element );

		// Already carries a classes control in its settings → supported.
		if ( isset( $element['settings'] ) && is_array( $element['settings'] )
			&& array_key_exists( 'classes', $element['settings'] ) ) {
			return true;
		}

		// Atomic elements are `e-` prefixed (e-heading, e-flexbox, e-div-block…)
		// and/or carry a `styles` map. Either signals atomic (classes-capable).
		if ( 0 === strpos( $type, 'e-' ) ) {
			return true;
		}
		if ( isset( $element['styles'] ) && is_array( $element['styles'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * A compact settings schema snapshot embedded in the not-atomic error, so
	 * the agent sees which controls the element actually exposes.
	 *
	 * @param array $element Element array.
	 * @return array
	 */
	private function compact_schema( array $element ): array {
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$keys     = array_map( 'strval', array_keys( $settings ) );
		return array(
			'type'         => $this->element_type( $element ),
			'is_atomic'    => false,
			'setting_keys' => array_slice( $keys, 0, 40 ),
			'has_classes'  => in_array( 'classes', $keys, true ),
		);
	}

	// =========================================================================
	// Cache
	// =========================================================================

	/**
	 * Clears Elementor's file cache so regenerated CSS picks up the change.
	 * Guarded for unit stubs (the stub Plugin has no files_manager).
	 */
	private function clear_cache(): void {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return;
		}
		$elementor = \Elementor\Plugin::$instance ?? null;
		if ( ! is_object( $elementor ) || ! isset( $elementor->files_manager ) || ! is_object( $elementor->files_manager ) ) {
			return;
		}
		if ( method_exists( $elementor->files_manager, 'clear_cache' ) ) {
			try {
				$elementor->files_manager->clear_cache();
			} catch ( \Throwable $e ) {
				// Non-fatal — the write already succeeded.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( '[Elementor MCP] global-classes: clear_cache failed: ' . $e->getMessage() );
				}
			}
		}
	}
}
