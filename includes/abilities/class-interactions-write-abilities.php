<?php
/**
 * Elementor v4 Interactions (per-element animations) CRUD MCP abilities — Elementor 4.0+.
 *
 * Four write/read tools that let an agent author Elementor 4's Interactions: the
 * per-element scroll / hover / click animations attached to an atomic element. An
 * interaction pairs a TRIGGER (when it fires — e.g. load, scrollIn) with an
 * ANIMATION preset (a fade/slide/scale in/out, with timing + easing). Companion
 * to the Global Classes and Variables write tools — where those author the shared
 * design system, Interactions decorate a specific element on a specific page.
 *
 * Storage: each element in `_elementor_data` carries a TOP-LEVEL `interactions`
 * field (sibling to id / elType / settings / elements), stored as a JSON-encoded
 * STRING of `{ "version": 1, "items": [ <interaction-item>, … ] }`. Each item is a
 * nested atomic `$$type` tree (interaction-item → animation-preset-props →
 * timing-config / config-v2). Because the field is top-level — NOT inside
 * `settings` — we walk the page tree and mutate `$element['interactions']`
 * directly, then save the page (update_element_settings only touches `settings`
 * and is the wrong writer here).
 *
 * IDs round-trip temp → canonical: ADD writes a `temp-<hex>` interaction_id; on a
 * real document save Elementor's `elementor/document/save/data` filter runs
 * `Parser::assign_interaction_ids()`, replacing every `temp-` id with a canonical
 * `{post_id}-{element_id}-{hash}` id. So ADD saves through the document, then
 * RE-READS the page to surface the canonical id. If the save falls back to a raw
 * meta write (no document save fires the filter), the temp id persists —
 * acceptable, and reported as-is.
 *
 * Registers only when BOTH the `e_interactions` experiment AND the Atomic Widgets
 * experiment are active (class/feature existence alone would let writes land while
 * the runtime feature is off). Writes are gated on `manage_options`; the read tool
 * (list) is gated on `edit_posts`. Pro triggers / effects / easing are gated on
 * Elementor Pro (`Utils::has_pro()`), permissive only when unresolvable.
 *
 * @package Elementor_MCP
 * @since   1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes Elementor v4 Interactions (per-element animations) CRUD over MCP.
 *
 * @since 1.16.0
 */
class Elementor_MCP_Interactions_Write_Abilities {

	/**
	 * Triggers available on the Free tier.
	 */
	const TRIGGERS_FREE = array( 'load', 'scrollIn' );

	/**
	 * Triggers that require Elementor Pro.
	 */
	// NB: `scrollOn` (while-scrolling / scroll-progress) is intentionally omitted.
	// It requires a scroll start/end range in the Pro config (start/end/relativeTo)
	// that build_item() does not write, so a scrollOn interaction created here would
	// save "successfully" but never behave correctly. Reject it until range support
	// is added. The other pro triggers are event-based (no range).
	const TRIGGERS_PRO = array( 'scrollOut', 'hover', 'click' );

	/**
	 * Animation effects available on the Free tier.
	 */
	const EFFECTS_FREE = array( 'fade', 'slide', 'scale' );

	/**
	 * Animation effects that require Elementor Pro.
	 */
	// NB: `custom` is intentionally omitted. A custom effect needs a
	// `custom_effect`/keyframes payload that build_item() does not write, so a
	// custom interaction created here would save without its animation data and
	// never render. Reject it until keyframe support is added.
	const EFFECTS_PRO = array();

	/**
	 * Animation types (in|out) — available on every tier.
	 */
	const TYPES = array( 'in', 'out' );

	/**
	 * Allowed animation directions ('' = none / centered).
	 */
	const DIRECTIONS = array( '', 'left', 'right', 'top', 'bottom', 'top-left', 'top-right', 'bottom-left', 'bottom-right' );

	/**
	 * Easing curves available on the Free tier.
	 */
	const EASING_FREE = array( 'easeIn' );

	/**
	 * Easing curves that require Elementor Pro.
	 */
	const EASING_PRO = array( 'easeOut', 'easeInOut', 'backIn', 'backInOut', 'backOut', 'linear' );

	/**
	 * Default animation duration in milliseconds.
	 */
	const DEFAULT_DURATION_MS = 600;

	/**
	 * Default animation delay in milliseconds.
	 */
	const DEFAULT_DELAY_MS = 0;

	/**
	 * Elementor's per-element interaction cap. Elementor's Interactions Validation
	 * rejects an element with MORE than this many interactions on save.
	 */
	const MAX_INTERACTIONS = 5;

	/**
	 * The data access layer (page read/find/save).
	 *
	 * @var Elementor_MCP_Data
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param Elementor_MCP_Data $data The data access layer.
	 */
	public function __construct( Elementor_MCP_Data $data ) {
		$this->data = $data;
	}

	/**
	 * Whether the Interactions module is available to write through.
	 *
	 * Gate: BOTH the `e_interactions` experiment AND Atomic Widgets support must
	 * be active. The Interactions module returns early (registers nothing, and the
	 * runtime never reads the `interactions` field) unless both experiments are on,
	 * so writing without them would silently no-op — the same trap
	 * Elementor_MCP_Atomic_Props::is_atomic_supported() guards against for atomic
	 * elements. Permissive only when the experiments API can't be reached.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		if ( ! self::e_interactions_active() ) {
			return false;
		}
		return class_exists( 'Elementor_MCP_Atomic_Props' )
			? \Elementor_MCP_Atomic_Props::is_atomic_supported()
			: true;
	}

	/**
	 * Whether Elementor's `e_interactions` experiment is active. Permissive when
	 * the experiments API is unreachable; strict (false) when it is present and
	 * reports the feature off.
	 *
	 * @return bool
	 */
	private static function e_interactions_active(): bool {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return false;
		}
		$elementor = \Elementor\Plugin::$instance ?? null;
		if ( ! is_object( $elementor ) || ! isset( $elementor->experiments ) || ! is_object( $elementor->experiments )
			|| ! method_exists( $elementor->experiments, 'is_feature_active' ) ) {
			return true;
		}
		try {
			return (bool) $elementor->experiments->is_feature_active( 'e_interactions' );
		} catch ( \Throwable $e ) {
			return true;
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
			'elementor-mcp/list-interactions',
			'elementor-mcp/add-interaction',
			'elementor-mcp/edit-interaction',
			'elementor-mcp/delete-interaction',
		);
	}

	/**
	 * Permission check for Interactions WRITES (add/edit/delete). Requires
	 * `manage_options` + the per-post `edit_post` capability.
	 *
	 * @param array $input Input parameters.
	 * @return true|\WP_Error
	 */
	public function check_write_permission( $input = array() ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to manage interactions.', 'elementor-mcp' ) );
		}
		return $this->check_post_cap( $input );
	}

	/**
	 * Permission check for Interactions READS (list). Requires `edit_posts` AND
	 * the per-post `edit_post` capability for the requested page.
	 *
	 * @param array $input Input parameters.
	 * @return true|\WP_Error
	 */
	public function check_read_permission( $input = array() ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to read interactions.', 'elementor-mcp' ) );
		}
		return $this->check_post_cap( $input );
	}

	/**
	 * Enforces the per-post `edit_post` capability for the requested post_id, so a
	 * user who can edit *some* posts can't read/write interactions on a page they
	 * cannot edit (e.g. a contributor querying another author's private page).
	 *
	 * @param array $input Input parameters.
	 * @return true|\WP_Error
	 */
	private function check_post_cap( $input ) {
		$post_id = is_array( $input ) ? (int) ( $input['post_id'] ?? 0 ) : 0;
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to access this post.', 'elementor-mcp' ) );
		}
		return true;
	}

	/**
	 * Registers the Interactions abilities.
	 */
	public function register(): void {
		if ( ! self::is_available() ) {
			return;
		}

		$this->register_list();
		$this->register_add();
		$this->register_edit();
		$this->register_delete();
	}

	// =========================================================================
	// Registration
	// =========================================================================

	private function register_list(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/list-interactions',
			array(
				'label'               => __( 'List Interactions', 'elementor-mcp' ),
				'description'         => __( 'Lists the Elementor 4.0+ Interactions (per-element scroll/hover/click animations) attached to an atomic element on a page. Returns each interaction in ergonomic shape { interaction_id, trigger, effect, type, direction, duration_ms, delay_ms, easing }. Requires edit_posts.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_list' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array( 'type' => 'integer', 'description' => __( 'The post/page ID containing the element.', 'elementor-mcp' ) ),
						'element_id' => array( 'type' => 'string', 'description' => __( 'The target atomic element ID.', 'elementor-mcp' ) ),
					),
					'required'   => array( 'post_id', 'element_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'count'        => array( 'type' => 'integer' ),
						'interactions' => array( 'type' => 'array' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_add(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/add-interaction',
			array(
				'label'               => __( 'Add Interaction', 'elementor-mcp' ),
				'description'         => __( 'Adds an Elementor 4.0+ Interaction (per-element animation) to an atomic element. Pass post_id + element_id and the ergonomic animation fields: trigger (load|scrollIn; pro: scrollOut|hover|click), effect (fade|slide|scale), type (in|out), direction (\'\'|left|right|top|bottom|top-left|top-right|bottom-left|bottom-right), duration_ms, delay_ms, easing (easeIn; pro: easeOut|easeInOut|backIn|backInOut|backOut|linear). Saves the page and returns the new interaction\'s (canonical) id. Requires manage_options.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_add' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'     => array( 'type' => 'integer', 'description' => __( 'The post/page ID containing the element.', 'elementor-mcp' ) ),
						'element_id'  => array( 'type' => 'string', 'description' => __( 'The target atomic element ID.', 'elementor-mcp' ) ),
						'trigger'     => array( 'type' => 'string', 'description' => __( 'When the animation fires. Free: load, scrollIn. Pro: scrollOut, hover, click. Default load.', 'elementor-mcp' ) ),
						'effect'      => array( 'type' => 'string', 'description' => __( 'Animation effect: fade, slide, scale. Default fade.', 'elementor-mcp' ) ),
						'type'        => array( 'type' => 'string', 'enum' => self::TYPES, 'description' => __( 'in | out. Default in.', 'elementor-mcp' ) ),
						'direction'   => array( 'type' => 'string', 'description' => __( 'Direction: \'\' | left | right | top | bottom | top-left | top-right | bottom-left | bottom-right. Default \'\'.', 'elementor-mcp' ) ),
						'duration_ms' => array( 'type' => 'integer', 'description' => __( 'Animation duration in milliseconds. Default 600.', 'elementor-mcp' ) ),
						'delay_ms'    => array( 'type' => 'integer', 'description' => __( 'Animation delay in milliseconds. Default 0.', 'elementor-mcp' ) ),
						'easing'      => array( 'type' => 'string', 'description' => __( 'Easing curve. Free: easeIn. Pro: easeOut, easeInOut, backIn, backInOut, backOut, linear. Default easeIn.', 'elementor-mcp' ) ),
					),
					'required'   => array( 'post_id', 'element_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'interaction_id' => array( 'type' => 'string' ),
						'added'          => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_edit(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/edit-interaction',
			array(
				'label'               => __( 'Edit Interaction', 'elementor-mcp' ),
				'description'         => __( 'Edits an existing Elementor 4.0+ Interaction in place, addressed by its interaction_id, preserving that id (and any fields you do not pass). Pass post_id + element_id + interaction_id plus any of: trigger, effect, type, direction, duration_ms, delay_ms, easing. Returns not_found if the id is not on the element. Requires manage_options.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_edit' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'        => array( 'type' => 'integer', 'description' => __( 'The post/page ID containing the element.', 'elementor-mcp' ) ),
						'element_id'     => array( 'type' => 'string', 'description' => __( 'The target atomic element ID.', 'elementor-mcp' ) ),
						'interaction_id' => array( 'type' => 'string', 'description' => __( 'The interaction id to edit (as returned by list/add).', 'elementor-mcp' ) ),
						'trigger'        => array( 'type' => 'string', 'description' => __( 'Optional new trigger.', 'elementor-mcp' ) ),
						'effect'         => array( 'type' => 'string', 'description' => __( 'Optional new effect.', 'elementor-mcp' ) ),
						'type'           => array( 'type' => 'string', 'enum' => self::TYPES, 'description' => __( 'Optional new type (in|out).', 'elementor-mcp' ) ),
						'direction'      => array( 'type' => 'string', 'description' => __( 'Optional new direction.', 'elementor-mcp' ) ),
						'duration_ms'    => array( 'type' => 'integer', 'description' => __( 'Optional new duration (ms).', 'elementor-mcp' ) ),
						'delay_ms'       => array( 'type' => 'integer', 'description' => __( 'Optional new delay (ms).', 'elementor-mcp' ) ),
						'easing'         => array( 'type' => 'string', 'description' => __( 'Optional new easing.', 'elementor-mcp' ) ),
					),
					'required'   => array( 'post_id', 'element_id', 'interaction_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'interaction_id' => array( 'type' => 'string' ),
						'updated'        => array( 'type' => 'boolean' ),
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
			'elementor-mcp/delete-interaction',
			array(
				'label'               => __( 'Delete Interaction', 'elementor-mcp' ),
				'description'         => __( 'Removes an Elementor 4.0+ Interaction from an atomic element, addressed by its interaction_id, and saves the page. Returns not_found if the id is not on the element. Requires manage_options.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_delete' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'        => array( 'type' => 'integer', 'description' => __( 'The post/page ID containing the element.', 'elementor-mcp' ) ),
						'element_id'     => array( 'type' => 'string', 'description' => __( 'The target atomic element ID.', 'elementor-mcp' ) ),
						'interaction_id' => array( 'type' => 'string', 'description' => __( 'The interaction id to delete.', 'elementor-mcp' ) ),
					),
					'required'   => array( 'post_id', 'element_id', 'interaction_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'interaction_id' => array( 'type' => 'string' ),
						'deleted'        => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	// =========================================================================
	// Execute — list
	// =========================================================================

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public function execute_list( $input ) {
		if ( ! self::is_available() ) {
			return $this->unavailable();
		}

		$ctx = $this->resolve_element( $input );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}
		list( , , $element ) = $ctx;

		$items = $this->decode_items( $element['interactions'] ?? '' );

		$out = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$out[] = $this->public_shape( $item );
			}
		}

		return array(
			'count'        => count( $out ),
			'interactions' => $out,
		);
	}

	// =========================================================================
	// Execute — add
	// =========================================================================

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public function execute_add( $input ) {
		if ( ! self::is_available() ) {
			return $this->unavailable();
		}

		$post_id    = absint( $input['post_id'] ?? 0 );
		$element_id = isset( $input['element_id'] ) ? sanitize_text_field( $input['element_id'] ) : '';
		if ( ! $post_id || '' === $element_id ) {
			return new \WP_Error( 'missing_input', __( 'post_id and element_id are required.', 'elementor-mcp' ) );
		}

		// Resolve ergonomic fields (defaults) and validate them (+ Pro gating).
		$fields = array(
			'trigger'     => isset( $input['trigger'] ) ? sanitize_text_field( $input['trigger'] ) : 'load',
			'effect'      => isset( $input['effect'] ) ? sanitize_text_field( $input['effect'] ) : 'fade',
			'type'        => isset( $input['type'] ) ? sanitize_text_field( $input['type'] ) : 'in',
			'direction'   => isset( $input['direction'] ) ? sanitize_text_field( $input['direction'] ) : '',
			'duration_ms' => array_key_exists( 'duration_ms', $input ) ? (int) $input['duration_ms'] : self::DEFAULT_DURATION_MS,
			'delay_ms'    => array_key_exists( 'delay_ms', $input ) ? (int) $input['delay_ms'] : self::DEFAULT_DELAY_MS,
			'easing'      => isset( $input['easing'] ) ? sanitize_text_field( $input['easing'] ) : 'easeIn',
		);
		$check = $this->validate_fields( $fields );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$ctx = $this->resolve_element( array( 'post_id' => $post_id, 'element_id' => $element_id ) );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}
		list( $page, , $element ) = $ctx;

		$items = $this->decode_items( $element['interactions'] ?? '' );

		// If existing items still carry `temp-` ids (from an earlier raw-meta-write
		// fallback), a native save would canonicalize BOTH those and the new item —
		// making the post-add id diff ambiguous. Canonicalize the existing ones
		// first (save unchanged, re-read) so only the newly-added temp id is new.
		if ( $this->has_temp_id( $items ) ) {
			$pre = $this->write_items( $page, $post_id, $element_id, $items );
			if ( is_wp_error( $pre ) ) {
				return $pre;
			}
			$page = $this->data->get_page_data( $post_id );
			if ( is_array( $page ) ) {
				$element = $this->data->find_element_by_id( $page, $element_id );
				$items   = is_array( $element ) ? $this->decode_items( $element['interactions'] ?? '' ) : $items;
			}
		}

		// Elementor caps each element at MAX_INTERACTIONS; a native save of a 6th
		// throws, and the raw-meta fallback would persist an invalid list Elementor
		// later rejects/sanitizes. Refuse up front.
		if ( count( $items ) >= self::MAX_INTERACTIONS ) {
			return new \WP_Error(
				'interaction_limit_reached',
				sprintf(
					/* translators: %d: the per-element interaction limit */
					__( 'This element already has the maximum of %d interactions. Delete one before adding another.', 'elementor-mcp' ),
					self::MAX_INTERACTIONS
				)
			);
		}

		$temp_id = 'temp-' . bin2hex( random_bytes( 8 ) );
		$items[] = $this->build_item( $temp_id, $fields );

		$save = $this->write_items( $page, $post_id, $element_id, $items );
		if ( is_wp_error( $save ) ) {
			return $save;
		}

		// Re-read so the canonical id assigned by Parser::assign_interaction_ids()
		// (fired on the document save) surfaces. If the save fell back to a raw
		// meta write, the temp id persists — return whatever id is on the element.
		$new_id = $this->resolve_new_id( $post_id, $element_id, $temp_id, $items );

		return array(
			'interaction_id' => $new_id,
			'added'          => true,
		);
	}

	// =========================================================================
	// Execute — edit
	// =========================================================================

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public function execute_edit( $input ) {
		if ( ! self::is_available() ) {
			return $this->unavailable();
		}

		$post_id        = absint( $input['post_id'] ?? 0 );
		$element_id     = isset( $input['element_id'] ) ? sanitize_text_field( $input['element_id'] ) : '';
		$interaction_id = isset( $input['interaction_id'] ) ? sanitize_text_field( $input['interaction_id'] ) : '';
		if ( ! $post_id || '' === $element_id || '' === $interaction_id ) {
			return new \WP_Error( 'missing_input', __( 'post_id, element_id and interaction_id are required.', 'elementor-mcp' ) );
		}

		// Collect only the provided ergonomic fields.
		$fields = array();
		foreach ( array( 'trigger', 'effect', 'type', 'direction', 'easing' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$fields[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}
		foreach ( array( 'duration_ms', 'delay_ms' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$fields[ $key ] = (int) $input[ $key ];
			}
		}
		if ( empty( $fields ) ) {
			return new \WP_Error( 'nothing_to_update', __( 'Provide at least one of: trigger, effect, type, direction, duration_ms, delay_ms, easing.', 'elementor-mcp' ) );
		}

		$check = $this->validate_fields( $fields );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$ctx = $this->resolve_element( array( 'post_id' => $post_id, 'element_id' => $element_id ) );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}
		list( $page, , $element ) = $ctx;

		$items = $this->decode_items( $element['interactions'] ?? '' );
		$index = $this->find_item_index( $items, $interaction_id );
		if ( null === $index ) {
			return $this->not_found( $interaction_id );
		}

		// Patch only the provided fields in the existing tree, preserving the rest
		// (and the id + any Pro-only nodes we don't model, e.g. breakpoints).
		$items[ $index ] = $this->patch_item( (array) $items[ $index ], $fields );

		$save = $this->write_items( $page, $post_id, $element_id, $items );
		if ( is_wp_error( $save ) ) {
			return $save;
		}

		// If the edited interaction still carried a `temp-` id (from an earlier
		// raw-meta add), a native save just canonicalized it. Re-read the item at
		// the same position and surface its current id so callers can address it.
		$current_id = $interaction_id;
		if ( 0 === strpos( $interaction_id, 'temp-' ) ) {
			$current_id = $this->id_at_index( $post_id, $element_id, $index, $interaction_id );
		}

		return array(
			'interaction_id' => $current_id,
			'updated'        => true,
		);
	}

	/**
	 * Re-reads the element and returns the interaction id at $index (the position
	 * we just wrote to), falling back to $default. Used to surface a canonical id
	 * after a `temp-` id was rewritten on save.
	 *
	 * @param int    $post_id    The post id.
	 * @param string $element_id The element id.
	 * @param int    $index      The item index that was written.
	 * @param string $default    Fallback id.
	 * @return string
	 */
	private function id_at_index( int $post_id, string $element_id, int $index, string $default ): string {
		$page = $this->data->get_page_data( $post_id );
		if ( ! is_array( $page ) ) {
			return $default;
		}
		$element = $this->data->find_element_by_id( $page, $element_id );
		if ( ! is_array( $element ) ) {
			return $default;
		}
		$items = $this->decode_items( $element['interactions'] ?? '' );
		if ( isset( $items[ $index ] ) && is_array( $items[ $index ] ) ) {
			$id = $this->item_id( $items[ $index ] );
			if ( '' !== $id ) {
				return $id;
			}
		}
		return $default;
	}

	// =========================================================================
	// Execute — delete
	// =========================================================================

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public function execute_delete( $input ) {
		if ( ! self::is_available() ) {
			return $this->unavailable();
		}

		$post_id        = absint( $input['post_id'] ?? 0 );
		$element_id     = isset( $input['element_id'] ) ? sanitize_text_field( $input['element_id'] ) : '';
		$interaction_id = isset( $input['interaction_id'] ) ? sanitize_text_field( $input['interaction_id'] ) : '';
		if ( ! $post_id || '' === $element_id || '' === $interaction_id ) {
			return new \WP_Error( 'missing_input', __( 'post_id, element_id and interaction_id are required.', 'elementor-mcp' ) );
		}

		$ctx = $this->resolve_element( array( 'post_id' => $post_id, 'element_id' => $element_id ) );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}
		list( $page, , $element ) = $ctx;

		$items = $this->decode_items( $element['interactions'] ?? '' );
		$index = $this->find_item_index( $items, $interaction_id );
		if ( null === $index ) {
			return $this->not_found( $interaction_id );
		}

		unset( $items[ $index ] );
		$items = array_values( $items );

		$save = $this->write_items( $page, $post_id, $element_id, $items );
		if ( is_wp_error( $save ) ) {
			return $save;
		}

		return array(
			'interaction_id' => $interaction_id,
			'deleted'        => true,
		);
	}

	// =========================================================================
	// Element resolution + tree write
	// =========================================================================

	/**
	 * Resolves the page tree + the target atomic element from post_id/element_id.
	 *
	 * @param array $input Must carry post_id + element_id.
	 * @return array{0: array, 1: string, 2: array}|\WP_Error [ $page, $element_id, $element ] or WP_Error.
	 */
	private function resolve_element( array $input ) {
		$post_id    = absint( $input['post_id'] ?? 0 );
		$element_id = isset( $input['element_id'] ) ? sanitize_text_field( $input['element_id'] ) : '';
		if ( ! $post_id || '' === $element_id ) {
			return new \WP_Error( 'missing_input', __( 'post_id and element_id are required.', 'elementor-mcp' ) );
		}

		$page = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		if ( ! is_array( $page ) ) {
			return new \WP_Error( 'no_data', __( 'This page has no Elementor data.', 'elementor-mcp' ) );
		}

		$element = $this->data->find_element_by_id( $page, $element_id );
		if ( null === $element ) {
			return new \WP_Error(
				'element_not_found',
				sprintf( /* translators: %s: element id */ __( 'Element "%s" was not found on this page.', 'elementor-mcp' ), $element_id )
			);
		}

		// Interactions only attach to atomic elements — reject anything else with a
		// schema snapshot so the agent can self-correct (mirrors apply-global-class).
		if ( ! $this->is_atomic_element( $element ) ) {
			$type   = $this->element_type( $element );
			$schema = $this->compact_schema( $element );
			$json   = wp_json_encode( $schema );
			return new \WP_Error(
				'not_atomic',
				sprintf(
					/* translators: 1: element/widget type, 2: compact schema JSON */
					__( 'Element "%1$s" is not an atomic element, so Interactions cannot be attached to it. Its settings schema is: %2$s', 'elementor-mcp' ),
					$type,
					false === $json ? '{}' : $json
				),
				$schema
			);
		}

		return array( $page, $element_id, $element );
	}

	/**
	 * Writes the interaction items back onto the element's TOP-LEVEL `interactions`
	 * field (NOT settings) and saves the page.
	 *
	 * @param array  $page       The page tree.
	 * @param int    $post_id    The post ID.
	 * @param string $element_id The element ID.
	 * @param array  $items      The full interaction-item list.
	 * @return true|\WP_Error
	 */
	private function write_items( array $page, int $post_id, string $element_id, array $items ) {
		$encoded = $this->encode_items( $items );

		$found = $this->set_element_interactions( $page, $element_id, $encoded );
		if ( ! $found ) {
			return new \WP_Error(
				'element_not_found',
				sprintf( /* translators: %s: element id */ __( 'Element "%s" was not found on this page.', 'elementor-mcp' ), $element_id )
			);
		}

		$save = $this->data->save_page_data( $post_id, $page );
		if ( is_wp_error( $save ) ) {
			return $save;
		}

		// Refresh Elementor's interactions postmeta cache from the SAVED page —
		// re-read, not the pre-save `$page`. On the native document-save path the
		// save/data filter has already sanitized the data + canonicalized temp ids
		// and after_save rebuilt the cache; re-reading and rebuilding from that is
		// idempotent. On the raw `_elementor_data` fallback (which skips after_save),
		// the re-read is the just-written data, so the cache the frontend reads
		// (`elementor-interactions-cache`) stays in sync. Refreshing from the
		// pre-save `$page` would instead clobber the sanitized cache with stale
		// (temp-id) data.
		$reread = $this->data->get_page_data( $post_id );
		$this->refresh_interactions_cache( $post_id, is_array( $reread ) ? $reread : $page );

		return true;
	}

	/**
	 * Rebuilds Elementor's interactions postmeta cache from the current page
	 * elements, so add/edit/delete take effect on the frontend even when the save
	 * fell back to a raw meta write (which skips `document/after_save`). Guarded +
	 * non-fatal.
	 *
	 * @param int   $post_id The post id.
	 * @param array $page    The page element tree (as saved).
	 */
	private function refresh_interactions_cache( int $post_id, array $page ): void {
		$cls = '\\Elementor\\Modules\\Interactions\\Cache\\Interactions_Postmeta';
		if ( ! class_exists( $cls ) ) {
			return;
		}
		try {
			$postmeta = new $cls();
			if ( method_exists( $postmeta, 'process_content' ) ) {
				// parse_from() expects a document-shaped payload keyed by `elements`.
				$postmeta->process_content( $post_id, array( 'elements' => $page ) );
			}
		} catch ( \Throwable $e ) {
			// Non-fatal — the document save may already have refreshed the cache.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Elementor MCP] interactions cache refresh failed: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Recursively finds the element by id in the tree (by reference) and sets its
	 * top-level `interactions` field to $encoded.
	 *
	 * @param array  $data       The element tree (by reference).
	 * @param string $element_id The element ID to update.
	 * @param string $encoded    The JSON-encoded interactions string.
	 * @return bool True if the element was found and updated.
	 */
	private function set_element_interactions( array &$data, string $element_id, string $encoded ): bool {
		foreach ( $data as &$element ) {
			if ( isset( $element['id'] ) && $element['id'] === $element_id ) {
				$element['interactions'] = $encoded;
				return true;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				if ( $this->set_element_interactions( $element['elements'], $element_id, $encoded ) ) {
					return true;
				}
			}
		}
		unset( $element );
		return false;
	}

	/**
	 * After a save, re-reads the page to surface the canonical interaction id that
	 * Elementor assigned to the just-added item (temp → canonical). Falls back to
	 * the temp id when the id is unchanged (raw-meta-write path — no document save
	 * fired the id-assignment filter).
	 *
	 * @param int    $post_id       The post ID.
	 * @param string $element_id    The element ID.
	 * @param string $temp_id       The temp id we wrote.
	 * @param array  $written_items The items we saved (fallback source).
	 * @return string The surfaced interaction id.
	 */
	private function resolve_new_id( int $post_id, string $element_id, string $temp_id, array $written_items ): string {
		$before = array();
		foreach ( $written_items as $item ) {
			$id = is_array( $item ) ? $this->item_id( $item ) : '';
			if ( '' !== $id && $id !== $temp_id ) {
				$before[ $id ] = true;
			}
		}

		$page = $this->data->get_page_data( $post_id );
		if ( is_array( $page ) ) {
			$element = $this->data->find_element_by_id( $page, $element_id );
			if ( is_array( $element ) ) {
				$items = $this->decode_items( $element['interactions'] ?? '' );
				$new   = array();
				foreach ( $items as $item ) {
					$id = is_array( $item ) ? $this->item_id( $item ) : '';
					if ( '' !== $id && ! isset( $before[ $id ] ) ) {
						$new[] = $id;
					}
				}
				// Exactly one id that wasn't present before the add → the canonical id.
				if ( 1 === count( $new ) ) {
					return $new[0];
				}
				// Temp id still present (raw-meta-write fallback) → return it.
				if ( in_array( $temp_id, $new, true ) || in_array( $temp_id, array_map( array( $this, 'item_id' ), array_filter( $items, 'is_array' ) ), true ) ) {
					return $temp_id;
				}
			}
		}

		return $temp_id;
	}

	// =========================================================================
	// Item build / patch / find
	// =========================================================================

	/**
	 * Builds a full interaction-item `$$type` tree from ergonomic fields.
	 *
	 * @param string $interaction_id The interaction id (temp-… on add).
	 * @param array  $fields         Ergonomic fields (all present, defaulted).
	 * @return array
	 */
	private function build_item( string $interaction_id, array $fields ): array {
		return array(
			'$$type' => 'interaction-item',
			'value'  => array(
				'interaction_id' => $this->str( $interaction_id ),
				'trigger'        => $this->str( (string) $fields['trigger'] ),
				'animation'      => array(
					'$$type' => 'animation-preset-props',
					'value'  => array(
						'effect'        => $this->str( (string) $fields['effect'] ),
						'type'          => $this->str( (string) $fields['type'] ),
						'direction'     => $this->str( (string) $fields['direction'] ),
						'timing_config' => array(
							'$$type' => 'timing-config',
							'value'  => array(
								'duration' => $this->size_ms( (int) $fields['duration_ms'] ),
								'delay'    => $this->size_ms( (int) $fields['delay_ms'] ),
							),
						),
						'config'        => array(
							'$$type' => 'config-v2',
							'value'  => array(
								'easing' => $this->str( (string) $fields['easing'] ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Patches only the provided ergonomic fields into an existing interaction-item
	 * tree, preserving the rest (and the interaction_id). Ensures the nested
	 * animation / timing / config nodes exist before writing into them.
	 *
	 * @param array $item   The existing interaction-item tree.
	 * @param array $fields Ergonomic fields to change.
	 * @return array
	 */
	private function patch_item( array $item, array $fields ): array {
		// Capture the id BEFORE normalizing — a "bare" item can carry the id at the
		// top level (no `value` wrapper), and moving to the wrapped shape below must
		// not drop it, or the item becomes unaddressable.
		$existing_id = $this->item_id( $item );

		if ( ! isset( $item['value'] ) || ! is_array( $item['value'] ) ) {
			$item['value'] = array();
		}
		if ( ! isset( $item['$$type'] ) ) {
			$item['$$type'] = 'interaction-item';
		}
		if ( ! isset( $item['value']['interaction_id'] ) && '' !== $existing_id ) {
			$item['value']['interaction_id'] = $this->str( $existing_id );
		}

		if ( array_key_exists( 'trigger', $fields ) ) {
			$item['value']['trigger'] = $this->str( (string) $fields['trigger'] );
		}

		$anim_fields = array( 'effect', 'type', 'direction', 'duration_ms', 'delay_ms', 'easing' );
		if ( array_intersect( $anim_fields, array_keys( $fields ) ) ) {
			if ( ! isset( $item['value']['animation'] ) || ! is_array( $item['value']['animation'] )
				|| ! isset( $item['value']['animation']['value'] ) || ! is_array( $item['value']['animation']['value'] ) ) {
				$item['value']['animation'] = array( '$$type' => 'animation-preset-props', 'value' => array() );
			} else {
				$item['value']['animation']['$$type'] = 'animation-preset-props';
			}
			$anim = &$item['value']['animation']['value'];

			if ( array_key_exists( 'effect', $fields ) ) {
				$anim['effect'] = $this->str( (string) $fields['effect'] );
			}
			if ( array_key_exists( 'type', $fields ) ) {
				$anim['type'] = $this->str( (string) $fields['type'] );
			}
			if ( array_key_exists( 'direction', $fields ) ) {
				$anim['direction'] = $this->str( (string) $fields['direction'] );
			}

			if ( array_key_exists( 'duration_ms', $fields ) || array_key_exists( 'delay_ms', $fields ) ) {
				if ( ! isset( $anim['timing_config'] ) || ! is_array( $anim['timing_config'] )
					|| ! isset( $anim['timing_config']['value'] ) || ! is_array( $anim['timing_config']['value'] ) ) {
					$anim['timing_config'] = array( '$$type' => 'timing-config', 'value' => array() );
				} else {
					$anim['timing_config']['$$type'] = 'timing-config';
				}
				if ( array_key_exists( 'duration_ms', $fields ) ) {
					$anim['timing_config']['value']['duration'] = $this->size_ms( (int) $fields['duration_ms'] );
				}
				if ( array_key_exists( 'delay_ms', $fields ) ) {
					$anim['timing_config']['value']['delay'] = $this->size_ms( (int) $fields['delay_ms'] );
				}
			}

			if ( array_key_exists( 'easing', $fields ) ) {
				if ( ! isset( $anim['config'] ) || ! is_array( $anim['config'] )
					|| ! isset( $anim['config']['value'] ) || ! is_array( $anim['config']['value'] ) ) {
					$anim['config'] = array( '$$type' => 'config-v2', 'value' => array() );
				} else {
					$anim['config']['$$type'] = 'config-v2';
				}
				$anim['config']['value']['easing'] = $this->str( (string) $fields['easing'] );
			}

			unset( $anim );
		}

		return $item;
	}

	/**
	 * Finds the list index of the interaction-item carrying $interaction_id.
	 *
	 * @param array  $items          The item list.
	 * @param string $interaction_id The id to match.
	 * @return int|null The index, or null if not present.
	 */
	/**
	 * Whether any item in the list still carries a `temp-` interaction id.
	 *
	 * @param array $items The item list.
	 * @return bool
	 */
	private function has_temp_id( array $items ): bool {
		foreach ( $items as $item ) {
			if ( is_array( $item ) && 0 === strpos( $this->item_id( $item ), 'temp-' ) ) {
				return true;
			}
		}
		return false;
	}

	private function find_item_index( array $items, string $interaction_id ): ?int {
		foreach ( $items as $i => $item ) {
			if ( is_array( $item ) && $this->item_id( $item ) === $interaction_id ) {
				return (int) $i;
			}
		}
		return null;
	}

	/**
	 * Reads the interaction_id off an interaction-item tree.
	 *
	 * @param array $item The item.
	 * @return string
	 */
	public function item_id( array $item ): string {
		$v = isset( $item['value'] ) && is_array( $item['value'] ) ? $item['value'] : $item;
		return isset( $v['interaction_id'] ) ? $this->scalar( $v['interaction_id'] ) : '';
	}

	// =========================================================================
	// Public shape (unwrap)
	// =========================================================================

	/**
	 * Unwraps an interaction-item `$$type` tree back to the ergonomic shape.
	 *
	 * @param array $item The interaction-item tree.
	 * @return array
	 */
	public function public_shape( array $item ): array {
		$v    = isset( $item['value'] ) && is_array( $item['value'] ) ? $item['value'] : $item;
		$anim = isset( $v['animation'] ) && is_array( $v['animation'] ) ? $v['animation'] : array();
		$av   = isset( $anim['value'] ) && is_array( $anim['value'] ) ? $anim['value'] : $anim;

		$timing = isset( $av['timing_config'] ) && is_array( $av['timing_config'] ) ? $av['timing_config'] : array();
		$tv     = isset( $timing['value'] ) && is_array( $timing['value'] ) ? $timing['value'] : $timing;

		$config = isset( $av['config'] ) && is_array( $av['config'] ) ? $av['config'] : array();
		$cv     = isset( $config['value'] ) && is_array( $config['value'] ) ? $config['value'] : $config;

		return array(
			'interaction_id' => isset( $v['interaction_id'] ) ? $this->scalar( $v['interaction_id'] ) : '',
			'trigger'        => isset( $v['trigger'] ) ? $this->scalar( $v['trigger'] ) : '',
			'effect'         => isset( $av['effect'] ) ? $this->scalar( $av['effect'] ) : '',
			'type'           => isset( $av['type'] ) ? $this->scalar( $av['type'] ) : '',
			'direction'      => isset( $av['direction'] ) ? $this->scalar( $av['direction'] ) : '',
			'duration_ms'    => isset( $tv['duration'] ) ? $this->size_num( $tv['duration'] ) : self::DEFAULT_DURATION_MS,
			'delay_ms'       => isset( $tv['delay'] ) ? $this->size_num( $tv['delay'] ) : self::DEFAULT_DELAY_MS,
			'easing'         => isset( $cv['easing'] ) ? $this->scalar( $cv['easing'] ) : '',
		);
	}

	/**
	 * Reads a scalar out of a (possibly `$$type`-wrapped) node.
	 *
	 * @param mixed $node The node.
	 * @return string
	 */
	private function scalar( $node ): string {
		if ( is_array( $node ) ) {
			if ( array_key_exists( 'value', $node ) ) {
				$val = $node['value'];
				return is_scalar( $val ) ? (string) $val : '';
			}
			return '';
		}
		return is_scalar( $node ) ? (string) $node : '';
	}

	/**
	 * Reads the numeric size out of a `size` prop node (its inner value.size).
	 *
	 * @param mixed $node The size prop node.
	 * @return int
	 */
	private function size_num( $node ): int {
		if ( is_array( $node ) ) {
			$val = isset( $node['value'] ) && is_array( $node['value'] ) ? $node['value'] : $node;
			if ( isset( $val['size'] ) && is_numeric( $val['size'] ) ) {
				return (int) $val['size'];
			}
			if ( isset( $node['value'] ) && is_numeric( $node['value'] ) ) {
				return (int) $node['value'];
			}
		}
		return is_numeric( $node ) ? (int) $node : 0;
	}

	// =========================================================================
	// Decode / encode the interactions field
	// =========================================================================

	/**
	 * Decodes the element's `interactions` field to a plain list of interaction
	 * items. Tolerates: a JSON string, a raw array, the `{version,items}` wrapper,
	 * an `{items:{$$type:'array',value:[…]}}` wrapper, and a bare list of items.
	 *
	 * @param mixed $raw The stored interactions field.
	 * @return array The list of interaction-item trees.
	 */
	private function decode_items( $raw ): array {
		$decoded = $raw;
		if ( is_string( $raw ) ) {
			if ( '' === trim( $raw ) ) {
				return array();
			}
			$decoded = json_decode( $raw, true );
		}
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		// { version, items } wrapper (the canonical stored shape).
		if ( array_key_exists( 'items', $decoded ) ) {
			$items = $decoded['items'];
			// { items: { $$type:'array', value:[…] } } wrapper.
			if ( is_array( $items ) && isset( $items['$$type'] ) && 'array' === $items['$$type']
				&& isset( $items['value'] ) && is_array( $items['value'] ) ) {
				return array_values( $items['value'] );
			}
			return is_array( $items ) ? array_values( $items ) : array();
		}

		// A bare list of items (no wrapper).
		if ( $this->is_list( $decoded ) ) {
			return array_values( $decoded );
		}

		return array();
	}

	/**
	 * Encodes an item list into the canonical `{ version:1, items:[…] }` JSON
	 * string Elementor stores in the top-level `interactions` field.
	 *
	 * @param array $items The item list.
	 * @return string
	 */
	private function encode_items( array $items ): string {
		$payload = array(
			'version' => 1,
			'items'   => array_values( $items ),
		);
		$json = wp_json_encode( $payload );
		return false === $json ? '{"version":1,"items":[]}' : $json;
	}

	/**
	 * Whether an array is a zero-indexed list.
	 *
	 * @param array $arr The array.
	 * @return bool
	 */
	private function is_list( array $arr ): bool {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $arr );
		}
		$i = 0;
		foreach ( $arr as $k => $unused ) {
			if ( $k !== $i ) {
				return false;
			}
			++$i;
		}
		return true;
	}

	// =========================================================================
	// Atomic prop primitives
	// =========================================================================

	/**
	 * Wraps a string into the atomic `string` prop.
	 *
	 * @param string $value The value.
	 * @return array
	 */
	private function str( string $value ): array {
		if ( class_exists( 'Elementor_MCP_Atomic_Props' ) ) {
			return \Elementor_MCP_Atomic_Props::string( $value );
		}
		return array( '$$type' => 'string', 'value' => $value );
	}

	/**
	 * Wraps a millisecond value into the atomic `size` prop (unit ms).
	 *
	 * @param int $ms The milliseconds.
	 * @return array
	 */
	private function size_ms( int $ms ): array {
		if ( class_exists( 'Elementor_MCP_Atomic_Props' ) ) {
			return \Elementor_MCP_Atomic_Props::size( $ms, 'ms' );
		}
		return array( '$$type' => 'size', 'value' => array( 'size' => $ms, 'unit' => 'ms' ) );
	}

	// =========================================================================
	// Validation + Pro gating
	// =========================================================================

	/**
	 * Validates the ergonomic fields against the enums and gates Pro-only options.
	 *
	 * @param array $fields The fields to validate (only present keys are checked).
	 * @return true|\WP_Error
	 */
	private function validate_fields( array $fields ) {
		if ( array_key_exists( 'trigger', $fields ) ) {
			$err = $this->validate_enum( (string) $fields['trigger'], self::TRIGGERS_FREE, self::TRIGGERS_PRO, 'trigger' );
			if ( is_wp_error( $err ) ) {
				return $err;
			}
		}
		if ( array_key_exists( 'effect', $fields ) ) {
			$err = $this->validate_enum( (string) $fields['effect'], self::EFFECTS_FREE, self::EFFECTS_PRO, 'effect' );
			if ( is_wp_error( $err ) ) {
				return $err;
			}
		}
		if ( array_key_exists( 'easing', $fields ) ) {
			$err = $this->validate_enum( (string) $fields['easing'], self::EASING_FREE, self::EASING_PRO, 'easing' );
			if ( is_wp_error( $err ) ) {
				return $err;
			}
		}
		if ( array_key_exists( 'type', $fields ) && ! in_array( (string) $fields['type'], self::TYPES, true ) ) {
			return new \WP_Error(
				'invalid_type',
				sprintf( /* translators: %s: the given type */ __( 'type "%s" is not valid. Use one of: in, out.', 'elementor-mcp' ), (string) $fields['type'] )
			);
		}
		if ( array_key_exists( 'direction', $fields ) && ! in_array( (string) $fields['direction'], self::DIRECTIONS, true ) ) {
			return new \WP_Error(
				'invalid_direction',
				sprintf( /* translators: %s: the given direction */ __( 'direction "%s" is not valid. Use \'\' | left | right | top | bottom | top-left | top-right | bottom-left | bottom-right.', 'elementor-mcp' ), (string) $fields['direction'] )
			);
		}
		if ( array_key_exists( 'duration_ms', $fields ) && (int) $fields['duration_ms'] < 0 ) {
			return new \WP_Error( 'invalid_duration', __( 'duration_ms must be a non-negative integer.', 'elementor-mcp' ) );
		}
		if ( array_key_exists( 'delay_ms', $fields ) && (int) $fields['delay_ms'] < 0 ) {
			return new \WP_Error( 'invalid_delay', __( 'delay_ms must be a non-negative integer.', 'elementor-mcp' ) );
		}
		return true;
	}

	/**
	 * Validates one enum value: rejects unknown values, and rejects Pro-only values
	 * on a non-Pro site with a `requires_pro` error.
	 *
	 * @param string   $value The value.
	 * @param string[] $free  Free-tier allowed values.
	 * @param string[] $pro   Pro-tier allowed values.
	 * @param string   $field The field name (for messages).
	 * @return true|\WP_Error
	 */
	private function validate_enum( string $value, array $free, array $pro, string $field ) {
		if ( in_array( $value, $free, true ) ) {
			return true;
		}
		if ( in_array( $value, $pro, true ) ) {
			if ( $this->pro_active() ) {
				return true;
			}
			return new \WP_Error(
				'requires_pro',
				sprintf(
					/* translators: 1: field name, 2: the pro value */
					__( 'The %1$s "%2$s" requires Elementor Pro. Use a Free-tier value, or activate Elementor Pro.', 'elementor-mcp' ),
					$field,
					$value
				)
			);
		}
		return new \WP_Error(
			'invalid_' . $field,
			sprintf(
				/* translators: 1: field name, 2: the value, 3: allowed free values */
				__( '%1$s "%2$s" is not valid. Free-tier values: %3$s.', 'elementor-mcp' ),
				$field,
				$value,
				implode( ', ', $free )
			)
		);
	}

	/**
	 * Whether Elementor Pro is active. Permissive only when has_pro() can't be
	 * resolved (mirrors the Variables write class).
	 *
	 * @return bool
	 */
	private function pro_active(): bool {
		if ( class_exists( '\\Elementor\\Utils' ) && method_exists( '\\Elementor\\Utils', 'has_pro' ) ) {
			try {
				return (bool) \Elementor\Utils::has_pro();
			} catch ( \Throwable $e ) {
				return true;
			}
		}
		return true;
	}

	// =========================================================================
	// Element inspection
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
	 * Whether an element is atomic (interactions attach only to atomic `e-`
	 * elements). Atomic elements are `e-` prefixed and/or carry a `styles` map or a
	 * `classes` control — mirrors apply-global-class's atomic gate.
	 *
	 * @param array $element Element array.
	 * @return bool
	 */
	private function is_atomic_element( array $element ): bool {
		$type = $this->element_type( $element );
		if ( 0 === strpos( $type, 'e-' ) ) {
			return true;
		}
		if ( isset( $element['styles'] ) && is_array( $element['styles'] ) ) {
			return true;
		}
		if ( isset( $element['settings'] ) && is_array( $element['settings'] )
			&& array_key_exists( 'classes', $element['settings'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * A compact settings schema snapshot embedded in the not-atomic error.
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
		);
	}

	// =========================================================================
	// Errors
	// =========================================================================

	/**
	 * The standard unavailable WP_Error.
	 *
	 * @return \WP_Error
	 */
	private function unavailable(): \WP_Error {
		return new \WP_Error( 'unavailable', __( 'Elementor Interactions are not available — Elementor 4.0+ with the Interactions and Atomic Widgets experiments is required.', 'elementor-mcp' ) );
	}

	/**
	 * The standard not-found WP_Error for an interaction id.
	 *
	 * @param string $interaction_id The id.
	 * @return \WP_Error
	 */
	private function not_found( string $interaction_id ): \WP_Error {
		return new \WP_Error(
			'not_found',
			sprintf( /* translators: %s: interaction id */ __( 'Interaction "%s" was not found on this element.', 'elementor-mcp' ), $interaction_id )
		);
	}
}
