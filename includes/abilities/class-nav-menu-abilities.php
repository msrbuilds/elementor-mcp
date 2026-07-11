<?php
/**
 * Nav-menu MCP abilities: menu-read / menu-write dispatchers.
 *
 * Exposes WordPress nav-menu management (menus, items, theme locations, and
 * HTML rendering) as two dispatcher tools, mirroring the ACF abilities pattern.
 *
 * @package EMCP_Tools
 * @since   3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and executes the nav-menu MCP abilities.
 *
 * @since 3.3.0
 */
class EMCP_Tools_Nav_Menu_Abilities {

	/**
	 * All registered ability names.
	 *
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * Registered ability names, for the registrar to merge.
	 *
	 * @since 3.3.0
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/**
	 * Registers the read and write dispatchers.
	 *
	 * @since 3.3.0
	 */
	public function register(): void {
		$this->register_read_dispatcher();
		$this->register_write_dispatcher();
	}

	/**
	 * The operation catalog: name => { mode, run, desc }.
	 *
	 * @since 3.3.0
	 * @return array
	 */
	private function operations(): array {
		return array(
			'list-menus'        => array( 'mode' => 'read',  'run' => 'op_list_menus',       'desc' => __( 'List all nav menus with id, name, slug, item count, and assigned theme locations. arguments: {}.', 'emcp-tools' ) ),
			'get-menu'          => array( 'mode' => 'read',  'run' => 'op_get_menu',         'desc' => __( 'Get one menu and its nested item tree. arguments: { menu: id|slug|name }.', 'emcp-tools' ) ),
			'list-locations'    => array( 'mode' => 'read',  'run' => 'op_list_locations',   'desc' => __( 'List the theme\'s registered menu locations and which menu (if any) is assigned. arguments: {}.', 'emcp-tools' ) ),
			'render'            => array( 'mode' => 'read',  'run' => 'op_render',           'desc' => __( 'Render a menu to HTML via wp_nav_menu, for embedding in a custom header. arguments: { menu | location, depth?, container?, container_class?, menu_class?, menu_id? }.', 'emcp-tools' ) ),
			'create-menu'       => array( 'mode' => 'write', 'run' => 'op_create_menu',      'desc' => __( 'Create a nav menu. arguments: { name }.', 'emcp-tools' ) ),
			'rename-menu'       => array( 'mode' => 'write', 'run' => 'op_rename_menu',       'desc' => __( 'Rename a menu. arguments: { menu, name }.', 'emcp-tools' ) ),
			'delete-menu'       => array( 'mode' => 'write', 'run' => 'op_delete_menu',       'desc' => __( 'Delete a menu. arguments: { menu }.', 'emcp-tools' ) ),
			'assign-location'   => array( 'mode' => 'write', 'run' => 'op_assign_location',   'desc' => __( 'Assign a menu to a registered theme location. arguments: { menu, location }.', 'emcp-tools' ) ),
			'unassign-location' => array( 'mode' => 'write', 'run' => 'op_unassign_location', 'desc' => __( 'Clear a theme location assignment. arguments: { location }.', 'emcp-tools' ) ),
			'add-item'          => array( 'mode' => 'write', 'run' => 'op_add_item',          'desc' => __( 'Add a menu item. arguments: { menu, type: custom|page|post|<cpt>|category|taxonomy, object_id?, object?, url?, title?, parent?, position?, target?, classes?, description?, xfn? }.', 'emcp-tools' ) ),
			'update-item'       => array( 'mode' => 'write', 'run' => 'op_update_item',       'desc' => __( 'Update a menu item; unspecified fields are preserved. arguments: { item, title?, url?, parent?, position?, target?, classes?, description?, xfn? }.', 'emcp-tools' ) ),
			'delete-item'       => array( 'mode' => 'write', 'run' => 'op_delete_item',       'desc' => __( 'Delete a menu item. arguments: { item }.', 'emcp-tools' ) ),
			'reorder-items'     => array( 'mode' => 'write', 'run' => 'op_reorder_items',     'desc' => __( 'Reorder / re-parent items in one call; other fields preserved. arguments: { menu, items: [ { id, parent?, position } ] }.', 'emcp-tools' ) ),
		);
	}

	/**
	 * Registers the read dispatcher.
	 *
	 * @since 3.3.0
	 */
	private function register_read_dispatcher(): void {
		$this->ability_names[] = 'emcp-tools/menu-read';
		emcp_tools_register_ability(
			'emcp-tools/menu-read',
			array(
				'label'               => __( 'Menu Read', 'emcp-tools' ),
				'description'         => __( 'Read WordPress nav menus: list menus, get a menu\'s nested item tree, list theme locations, and render a menu to HTML. Call with no "operation" to list the available read operations and their arguments, then call again with { operation, arguments }.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'run_menu_read' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operation' => array( 'type' => 'string', 'description' => __( 'The read operation to run. Omit to list operations. One of: list-menus, get-menu, list-locations, render.', 'emcp-tools' ) ),
						'arguments' => array( 'type' => 'object', 'description' => __( 'Arguments for the chosen operation (see the catalog returned when operation is omitted).', 'emcp-tools' ) ),
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
	 * Registers the write dispatcher.
	 *
	 * @since 3.3.0
	 */
	private function register_write_dispatcher(): void {
		$this->ability_names[] = 'emcp-tools/menu-write';
		emcp_tools_register_ability(
			'emcp-tools/menu-write',
			array(
				'label'               => __( 'Menu Write', 'emcp-tools' ),
				'description'         => __( 'Manage WordPress nav menus: create/rename/delete menus, assign theme locations, and add/update/delete/reorder items. Call with no "operation" to list the available write operations and their arguments, then call again with { operation, arguments }.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'run_menu_write' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operation' => array( 'type' => 'string', 'description' => __( 'The write operation to run. Omit to list operations. One of: create-menu, rename-menu, delete-menu, assign-location, unassign-location, add-item, update-item, delete-item, reorder-items.', 'emcp-tools' ) ),
						'arguments' => array( 'type' => 'object', 'description' => __( 'Arguments for the chosen operation (see the catalog returned when operation is omitted).', 'emcp-tools' ) ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Execute callback for the read dispatcher.
	 *
	 * @since 3.3.0
	 * @param mixed $input Dispatcher input.
	 * @return mixed
	 */
	public function run_menu_read( $input ) {
		return $this->dispatch( 'read', $input );
	}

	/**
	 * Execute callback for the write dispatcher.
	 *
	 * @since 3.3.0
	 * @param mixed $input Dispatcher input.
	 * @return mixed
	 */
	public function run_menu_write( $input ) {
		return $this->dispatch( 'write', $input );
	}

	/**
	 * Both dispatchers require the WordPress menu-management capability.
	 *
	 * @since 3.3.0
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Routes an operation to its executor. An empty operation returns the catalog.
	 *
	 * @since 3.3.0
	 * @param string $mode  'read' or 'write'.
	 * @param mixed  $input Dispatcher input ({ operation, arguments }).
	 * @return mixed
	 */
	private function dispatch( string $mode, $input ) {
		$operation = isset( $input['operation'] ) ? str_replace( '_', '-', sanitize_key( (string) $input['operation'] ) ) : '';
		if ( '' === $operation ) {
			return $this->operations_catalog( $mode );
		}

		$ops = $this->operations();
		if ( ! isset( $ops[ $operation ] ) || $ops[ $operation ]['mode'] !== $mode ) {
			return new \WP_Error(
				'unknown_operation',
				sprintf(
					/* translators: 1: mode (read/write), 2: operation name */
					__( 'Unknown menu %1$s operation "%2$s". Call menu-%1$s with no operation to list the available operations.', 'emcp-tools' ),
					$mode,
					$operation
				)
			);
		}

		$args = ( isset( $input['arguments'] ) && is_array( $input['arguments'] ) ) ? $input['arguments'] : array();
		$run  = $ops[ $operation ]['run'];
		return $this->$run( $args );
	}

	/**
	 * Builds the discovery catalog for a mode.
	 *
	 * @since 3.3.0
	 * @param string $mode 'read' or 'write'.
	 * @return array
	 */
	private function operations_catalog( string $mode ): array {
		$list = array();
		foreach ( $this->operations() as $name => $op ) {
			if ( $op['mode'] !== $mode ) {
				continue;
			}
			$list[] = array(
				'operation'   => $name,
				'description' => $op['desc'],
			);
		}
		return array(
			'mode'       => $mode,
			'operations' => $list,
			'usage'      => sprintf(
				/* translators: %s: mode (read/write) */
				__( 'Call menu-%s again with { "operation": "<name>", "arguments": { ... } }.', 'emcp-tools' ),
				$mode
			),
		);
	}

	// -------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------

	/**
	 * Resolves a menu reference (id, slug, or name) to a menu object.
	 *
	 * @since 3.3.0
	 * @param mixed $ref Menu id, slug, or name.
	 * @return \WP_Term|\WP_Error
	 */
	private function resolve_menu( $ref ) {
		if ( is_numeric( $ref ) ) {
			$ref = (int) $ref;
		}
		$menu = wp_get_nav_menu_object( $ref );
		if ( ! $menu ) {
			return new \WP_Error( 'menu_not_found', __( 'No nav menu matches the given id, slug, or name.', 'emcp-tools' ) );
		}
		return $menu;
	}

	/**
	 * Theme-location slugs a menu is assigned to.
	 *
	 * @since 3.3.0
	 * @param int        $menu_id   Menu term id.
	 * @param array|null $locations Optional pre-fetched location map.
	 * @return string[]
	 */
	private function locations_for_menu( $menu_id, $locations = null ) {
		if ( null === $locations ) {
			$locations = get_nav_menu_locations();
		}
		$slugs = array();
		foreach ( (array) $locations as $slug => $id ) {
			if ( (int) $id === (int) $menu_id ) {
				$slugs[] = (string) $slug;
			}
		}
		return $slugs;
	}

	/**
	 * Resolves the menu id that owns a menu item.
	 *
	 * @since 3.3.0
	 * @param int $item_id Menu item (post) id.
	 * @return int|\WP_Error
	 */
	private function menu_id_for_item( $item_id ) {
		$terms = wp_get_object_terms( (int) $item_id, 'nav_menu' );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		if ( empty( $terms ) ) {
			return new \WP_Error( 'item_menu_not_found', __( 'The menu item is not attached to a menu.', 'emcp-tools' ) );
		}
		return (int) $terms[0]->term_id;
	}

	/**
	 * Validates a parent item id against a menu.
	 *
	 * @since 3.3.0
	 * @param mixed $parent_id Proposed parent item id (0 = top level).
	 * @param int   $menu_id   Menu the item belongs to.
	 * @return int|\WP_Error The sanitized parent id, or WP_Error if invalid.
	 */
	private function validate_parent( $parent_id, $menu_id ) {
		$parent_id = absint( $parent_id );
		if ( 0 === $parent_id ) {
			return 0;
		}
		if ( ! is_nav_menu_item( $parent_id ) ) {
			return new \WP_Error( 'invalid_parent', __( 'The "parent" is not a menu item.', 'emcp-tools' ) );
		}
		$owner = $this->menu_id_for_item( $parent_id );
		if ( is_wp_error( $owner ) || (int) $owner !== (int) $menu_id ) {
			return new \WP_Error( 'invalid_parent', __( 'The "parent" must be an item in the same menu.', 'emcp-tools' ) );
		}
		return $parent_id;
	}

	/**
	 * Sanitizes a space-separated class list.
	 *
	 * @since 3.3.0
	 * @param string $value Raw class string.
	 * @return string
	 */
	private function sanitize_class_list( $value ) {
		$parts = preg_split( '/\s+/', trim( (string) $value ) );
		$clean = array();
		foreach ( (array) $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			$class = sanitize_html_class( $part );
			if ( '' !== $class ) {
				$clean[] = $class;
			}
		}
		return implode( ' ', $clean );
	}

	/**
	 * Builds a nested item tree from a flat item list.
	 *
	 * @since 3.3.0
	 * @param array $items Flat list from wp_get_nav_menu_items().
	 * @return array
	 */
	private function build_item_tree( $items ) {
		$by_parent = array();
		foreach ( (array) $items as $item ) {
			$parent = (int) $item->menu_item_parent;
			if ( ! isset( $by_parent[ $parent ] ) ) {
				$by_parent[ $parent ] = array();
			}
			$by_parent[ $parent ][] = $item;
		}
		return $this->build_item_branch( $by_parent, 0 );
	}

	/**
	 * Recursively builds one branch of the item tree.
	 *
	 * @since 3.3.0
	 * @param array $by_parent Items grouped by parent id.
	 * @param int   $parent_id Parent item id (0 for top level).
	 * @return array
	 */
	private function build_item_branch( $by_parent, $parent_id ) {
		$branch = array();
		if ( empty( $by_parent[ $parent_id ] ) ) {
			return $branch;
		}
		foreach ( $by_parent[ $parent_id ] as $item ) {
			$branch[] = array(
				'id'          => (int) $item->ID,
				'title'       => (string) $item->title,
				'type'        => (string) $item->type,
				'object'      => (string) $item->object,
				'object_id'   => (int) $item->object_id,
				'url'         => (string) $item->url,
				'target'      => (string) $item->target,
				'classes'     => array_values( array_filter( (array) $item->classes ) ),
				'description' => (string) $item->description,
				'xfn'         => (string) $item->xfn,
				'parent'      => (int) $item->menu_item_parent,
				'position'    => (int) $item->menu_order,
				'children'    => $this->build_item_branch( $by_parent, (int) $item->ID ),
			);
		}
		return $branch;
	}

	/**
	 * Resolves item type/object/object_id for add-item.
	 *
	 * @since 3.3.0
	 * @param array $args Operation arguments.
	 * @return array|\WP_Error
	 */
	private function resolve_item_type( array $args ) {
		$type = isset( $args['type'] ) ? sanitize_key( (string) $args['type'] ) : 'custom';

		if ( 'custom' === $type ) {
			return array( 'type' => 'custom', 'object' => 'custom', 'object_id' => 0 );
		}

		$object_id = isset( $args['object_id'] ) ? absint( $args['object_id'] ) : 0;

		if ( in_array( $type, array( 'category', 'tag', 'post_tag', 'taxonomy', 'term' ), true ) ) {
			$taxonomy = $type;
			if ( 'tag' === $type ) {
				$taxonomy = 'post_tag';
			}
			if ( 'taxonomy' === $type || 'term' === $type ) {
				$taxonomy = isset( $args['object'] ) ? sanitize_key( (string) $args['object'] ) : '';
			}
			if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				return new \WP_Error( 'invalid_taxonomy', __( 'A valid "object" taxonomy is required for taxonomy items.', 'emcp-tools' ) );
			}
			if ( ! $object_id || ! get_term( $object_id, $taxonomy ) instanceof \WP_Term ) {
				return new \WP_Error( 'invalid_object', __( 'A valid "object_id" (existing term) is required.', 'emcp-tools' ) );
			}
			return array( 'type' => 'taxonomy', 'object' => $taxonomy, 'object_id' => $object_id );
		}

		$post_type = $type;
		if ( 'post_type' === $type ) {
			$post_type = isset( $args['object'] ) ? sanitize_key( (string) $args['object'] ) : '';
		}
		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return new \WP_Error( 'invalid_type', __( 'Unknown item "type". Use custom, page, post, a CPT slug, category, or taxonomy.', 'emcp-tools' ) );
		}
		$post = $object_id ? get_post( $object_id ) : null;
		if ( ! ( $post instanceof \WP_Post ) ) {
			return new \WP_Error( 'invalid_object', __( 'A valid "object_id" (existing post/page) is required.', 'emcp-tools' ) );
		}
		if ( (string) $post->post_type !== $post_type ) {
			return new \WP_Error( 'type_mismatch', __( 'The "object_id" does not match the requested "type".', 'emcp-tools' ) );
		}
		return array( 'type' => 'post_type', 'object' => $post_type, 'object_id' => (int) $object_id );
	}

	/**
	 * Collects optional item fields shared by add-item.
	 *
	 * @since 3.3.0
	 * @param array $args Operation arguments.
	 * @return array
	 */
	private function optional_item_fields( array $args ) {
		$out = array();
		if ( isset( $args['parent'] ) ) {
			$out['menu-item-parent-id'] = absint( $args['parent'] );
		}
		if ( isset( $args['position'] ) ) {
			$out['menu-item-position'] = (int) $args['position'];
		}
		if ( isset( $args['target'] ) ) {
			$out['menu-item-target'] = ( '_blank' === (string) $args['target'] ) ? '_blank' : '';
		}
		if ( isset( $args['classes'] ) ) {
			$raw                      = is_array( $args['classes'] ) ? implode( ' ', $args['classes'] ) : (string) $args['classes'];
			$out['menu-item-classes'] = $this->sanitize_class_list( $raw );
		}
		if ( isset( $args['description'] ) ) {
			$out['menu-item-description'] = sanitize_text_field( (string) $args['description'] );
		}
		if ( isset( $args['xfn'] ) ) {
			$out['menu-item-xfn'] = sanitize_text_field( (string) $args['xfn'] );
		}
		return $out;
	}

	// -------------------------------------------------------------------
	// Read operations
	// -------------------------------------------------------------------

	/**
	 * @since 3.3.0
	 * @param array $args Unused.
	 * @return array|\WP_Error
	 */
	private function op_list_menus( array $args ) {
		$menus = wp_get_nav_menus();
		if ( is_wp_error( $menus ) ) {
			return $menus;
		}
		$locations = get_nav_menu_locations();
		$rows      = array();
		foreach ( $menus as $menu ) {
			$rows[] = array(
				'id'        => (int) $menu->term_id,
				'name'      => (string) $menu->name,
				'slug'      => (string) $menu->slug,
				'count'     => (int) $menu->count,
				'locations' => $this->locations_for_menu( (int) $menu->term_id, $locations ),
			);
		}
		return array( 'menus' => $rows );
	}

	/**
	 * @since 3.3.0
	 * @param array $args { menu }.
	 * @return array|\WP_Error
	 */
	private function op_get_menu( array $args ) {
		if ( ! isset( $args['menu'] ) || '' === $args['menu'] ) {
			return new \WP_Error( 'missing_menu', __( 'The "menu" argument (id, slug, or name) is required.', 'emcp-tools' ) );
		}
		$menu = $this->resolve_menu( $args['menu'] );
		if ( is_wp_error( $menu ) ) {
			return $menu;
		}
		$items = wp_get_nav_menu_items( $menu->term_id );
		if ( false === $items ) {
			$items = array();
		}
		return array(
			'id'        => (int) $menu->term_id,
			'name'      => (string) $menu->name,
			'slug'      => (string) $menu->slug,
			'locations' => $this->locations_for_menu( (int) $menu->term_id ),
			'items'     => $this->build_item_tree( $items ),
		);
	}

	/**
	 * @since 3.3.0
	 * @param array $args Unused.
	 * @return array
	 */
	private function op_list_locations( array $args ) {
		$registered = get_registered_nav_menus();
		$assigned   = get_nav_menu_locations();
		$rows       = array();
		foreach ( (array) $registered as $slug => $label ) {
			$menu_id   = isset( $assigned[ $slug ] ) ? (int) $assigned[ $slug ] : 0;
			$menu_name = null;
			if ( $menu_id ) {
				$obj = wp_get_nav_menu_object( $menu_id );
				if ( $obj ) {
					$menu_name = (string) $obj->name;
				}
			}
			$rows[] = array(
				'location'    => (string) $slug,
				'description' => (string) $label,
				'menu_id'     => $menu_id ? $menu_id : null,
				'menu_name'   => $menu_name,
			);
		}
		return array( 'locations' => $rows );
	}

	/**
	 * @since 3.3.0
	 * @param array $args { menu | location, depth?, container?, container_class?, menu_class?, menu_id? }.
	 * @return array|\WP_Error
	 */
	private function op_render( array $args ) {
		$wp_args = array(
			'echo'        => false,
			'fallback_cb' => false,
		);
		if ( isset( $args['menu'] ) && '' !== $args['menu'] ) {
			$menu = $this->resolve_menu( $args['menu'] );
			if ( is_wp_error( $menu ) ) {
				return $menu;
			}
			$wp_args['menu'] = (int) $menu->term_id;
		} elseif ( isset( $args['location'] ) && '' !== $args['location'] ) {
			$wp_args['theme_location'] = sanitize_key( (string) $args['location'] );
		} else {
			return new \WP_Error( 'missing_target', __( 'Provide either "menu" (id/slug/name) or "location".', 'emcp-tools' ) );
		}
		if ( isset( $args['depth'] ) ) {
			$wp_args['depth'] = (int) $args['depth'];
		}
		if ( array_key_exists( 'container', $args ) ) {
			$container            = strtolower( trim( (string) $args['container'] ) );
			$wp_args['container'] = ( '' === $container || 'false' === $container || '0' === $container || 'none' === $container ) ? false : sanitize_key( $container );
		}
		if ( isset( $args['container_class'] ) ) {
			$wp_args['container_class'] = $this->sanitize_class_list( $args['container_class'] );
		}
		if ( isset( $args['menu_class'] ) ) {
			$wp_args['menu_class'] = $this->sanitize_class_list( $args['menu_class'] );
		}
		if ( isset( $args['menu_id'] ) ) {
			$wp_args['menu_id'] = sanitize_html_class( (string) $args['menu_id'] );
		}
		$html = wp_nav_menu( $wp_args );
		if ( ! is_string( $html ) ) {
			$html = '';
		}
		return array( 'html' => $html );
	}

	// -------------------------------------------------------------------
	// Write operations
	// -------------------------------------------------------------------

	/**
	 * @since 3.3.0
	 * @param array $args { name }.
	 * @return array|\WP_Error
	 */
	private function op_create_menu( array $args ) {
		$name = isset( $args['name'] ) ? sanitize_text_field( (string) $args['name'] ) : '';
		if ( '' === $name ) {
			return new \WP_Error( 'missing_name', __( 'The "name" argument is required.', 'emcp-tools' ) );
		}
		$menu_id = wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) {
			return $menu_id;
		}
		$menu = wp_get_nav_menu_object( (int) $menu_id );
		return array(
			'menu_id' => (int) $menu_id,
			'name'    => $menu ? (string) $menu->name : $name,
			'slug'    => $menu ? (string) $menu->slug : '',
		);
	}

	/**
	 * @since 3.3.0
	 * @param array $args { menu, name }.
	 * @return array|\WP_Error
	 */
	private function op_rename_menu( array $args ) {
		if ( ! isset( $args['menu'] ) || '' === $args['menu'] ) {
			return new \WP_Error( 'missing_menu', __( 'The "menu" argument is required.', 'emcp-tools' ) );
		}
		$menu = $this->resolve_menu( $args['menu'] );
		if ( is_wp_error( $menu ) ) {
			return $menu;
		}
		$name = isset( $args['name'] ) ? sanitize_text_field( (string) $args['name'] ) : '';
		if ( '' === $name ) {
			return new \WP_Error( 'missing_name', __( 'The "name" argument is required.', 'emcp-tools' ) );
		}
		$result = wp_update_nav_menu_object( (int) $menu->term_id, array( 'menu-name' => $name ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$updated = wp_get_nav_menu_object( (int) $menu->term_id );
		return array(
			'menu_id' => (int) $menu->term_id,
			'name'    => $updated ? (string) $updated->name : $name,
			'slug'    => $updated ? (string) $updated->slug : (string) $menu->slug,
		);
	}

	/**
	 * @since 3.3.0
	 * @param array $args { menu }.
	 * @return array|\WP_Error
	 */
	private function op_delete_menu( array $args ) {
		if ( ! isset( $args['menu'] ) || '' === $args['menu'] ) {
			return new \WP_Error( 'missing_menu', __( 'The "menu" argument is required.', 'emcp-tools' ) );
		}
		$menu = $this->resolve_menu( $args['menu'] );
		if ( is_wp_error( $menu ) ) {
			return $menu;
		}
		$menu_id = (int) $menu->term_id;
		$result  = wp_delete_nav_menu( $menu_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new \WP_Error( 'delete_failed', __( 'The menu could not be deleted.', 'emcp-tools' ) );
		}
		return array( 'deleted' => true, 'menu_id' => $menu_id );
	}

	/**
	 * @since 3.3.0
	 * @param array $args { menu, location }.
	 * @return array|\WP_Error
	 */
	private function op_assign_location( array $args ) {
		if ( ! isset( $args['menu'] ) || '' === $args['menu'] ) {
			return new \WP_Error( 'missing_menu', __( 'The "menu" argument is required.', 'emcp-tools' ) );
		}
		$menu = $this->resolve_menu( $args['menu'] );
		if ( is_wp_error( $menu ) ) {
			return $menu;
		}
		$location = isset( $args['location'] ) ? sanitize_key( (string) $args['location'] ) : '';
		if ( '' === $location ) {
			return new \WP_Error( 'missing_location', __( 'The "location" argument is required.', 'emcp-tools' ) );
		}
		$registered = get_registered_nav_menus();
		if ( ! isset( $registered[ $location ] ) ) {
			return new \WP_Error(
				'unregistered_location',
				sprintf(
					/* translators: %s: location slug */
					__( 'The theme does not register a "%s" menu location. Call list-locations for valid slugs.', 'emcp-tools' ),
					$location
				)
			);
		}
		$locations              = get_nav_menu_locations();
		$locations[ $location ] = (int) $menu->term_id;
		set_theme_mod( 'nav_menu_locations', $locations );
		return array( 'location' => $location, 'menu_id' => (int) $menu->term_id );
	}

	/**
	 * @since 3.3.0
	 * @param array $args { location }.
	 * @return array|\WP_Error
	 */
	private function op_unassign_location( array $args ) {
		$location = isset( $args['location'] ) ? sanitize_key( (string) $args['location'] ) : '';
		if ( '' === $location ) {
			return new \WP_Error( 'missing_location', __( 'The "location" argument is required.', 'emcp-tools' ) );
		}
		$locations = get_nav_menu_locations();
		if ( isset( $locations[ $location ] ) ) {
			unset( $locations[ $location ] );
			set_theme_mod( 'nav_menu_locations', $locations );
		}
		return array( 'location' => $location, 'menu_id' => null );
	}

	/**
	 * @since 3.3.0
	 * @param array $args { menu, type, ... }.
	 * @return array|\WP_Error
	 */
	private function op_add_item( array $args ) {
		if ( ! isset( $args['menu'] ) || '' === $args['menu'] ) {
			return new \WP_Error( 'missing_menu', __( 'The "menu" argument is required.', 'emcp-tools' ) );
		}
		$menu = $this->resolve_menu( $args['menu'] );
		if ( is_wp_error( $menu ) ) {
			return $menu;
		}
		$resolved = $this->resolve_item_type( $args );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		if ( isset( $args['parent'] ) ) {
			$parent = $this->validate_parent( $args['parent'], (int) $menu->term_id );
			if ( is_wp_error( $parent ) ) {
				return $parent;
			}
		}
		$title = isset( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : '';
		$data  = array(
			'menu-item-type'      => $resolved['type'],
			'menu-item-object'    => $resolved['object'],
			'menu-item-object-id' => (int) $resolved['object_id'],
			'menu-item-status'    => 'publish',
		);
		if ( '' !== $title ) {
			$data['menu-item-title'] = $title;
		}
		if ( 'custom' === $resolved['type'] ) {
			$url = isset( $args['url'] ) ? esc_url_raw( (string) $args['url'] ) : '';
			if ( '' === $url ) {
				return new \WP_Error( 'missing_url', __( 'Custom-link items require a "url".', 'emcp-tools' ) );
			}
			if ( '' === $title ) {
				return new \WP_Error( 'missing_title', __( 'Custom-link items require a "title".', 'emcp-tools' ) );
			}
			$data['menu-item-url'] = $url;
		}
		$data    = array_merge( $data, $this->optional_item_fields( $args ) );
		$item_id = wp_update_nav_menu_item( (int) $menu->term_id, 0, $data );
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}
		return array( 'item_id' => (int) $item_id );
	}

	/**
	 * @since 3.3.0
	 * @param array $args { item, ... }.
	 * @return array|\WP_Error
	 */
	private function op_update_item( array $args ) {
		$item_id = isset( $args['item'] ) ? absint( $args['item'] ) : 0;
		if ( ! $item_id || ! is_nav_menu_item( $item_id ) ) {
			return new \WP_Error( 'item_not_found', __( 'A valid "item" (menu item id) is required.', 'emcp-tools' ) );
		}
		$menu_id = $this->menu_id_for_item( $item_id );
		if ( is_wp_error( $menu_id ) ) {
			return $menu_id;
		}
		if ( isset( $args['parent'] ) ) {
			$parent = $this->validate_parent( $args['parent'], (int) $menu_id );
			if ( is_wp_error( $parent ) ) {
				return $parent;
			}
		}
		$existing = wp_setup_nav_menu_item( get_post( $item_id ) );
		$data     = $this->merge_existing_item( $existing, $item_id, $args );
		$result   = wp_update_nav_menu_item( (int) $menu_id, $item_id, $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'item_id' => $item_id );
	}

	/**
	 * Merges change args onto an existing item's full field set (no data loss).
	 *
	 * @since 3.3.0
	 * @param object $existing wp_setup_nav_menu_item() object.
	 * @param int    $item_id  Menu item id.
	 * @param array  $args     Change arguments.
	 * @return array
	 */
	private function merge_existing_item( $existing, $item_id, array $args ) {
		return array(
			'menu-item-db-id'      => (int) $item_id,
			'menu-item-object-id'  => (int) $existing->object_id,
			'menu-item-object'     => (string) $existing->object,
			'menu-item-type'       => (string) $existing->type,
			'menu-item-status'     => 'publish',
			'menu-item-attr-title' => (string) $existing->attr_title,
			'menu-item-parent-id'  => isset( $args['parent'] ) ? absint( $args['parent'] ) : (int) $existing->menu_item_parent,
			'menu-item-position'   => isset( $args['position'] ) ? (int) $args['position'] : (int) $existing->menu_order,
			'menu-item-title'      => isset( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : (string) $existing->post_title,
			'menu-item-url'        => isset( $args['url'] ) ? esc_url_raw( (string) $args['url'] ) : (string) $existing->url,
			'menu-item-description' => isset( $args['description'] ) ? sanitize_text_field( (string) $args['description'] ) : (string) $existing->description,
			'menu-item-target'     => isset( $args['target'] ) ? ( '_blank' === (string) $args['target'] ? '_blank' : '' ) : (string) $existing->target,
			'menu-item-classes'    => isset( $args['classes'] ) ? $this->sanitize_class_list( is_array( $args['classes'] ) ? implode( ' ', $args['classes'] ) : (string) $args['classes'] ) : implode( ' ', (array) $existing->classes ),
			'menu-item-xfn'        => isset( $args['xfn'] ) ? sanitize_text_field( (string) $args['xfn'] ) : (string) $existing->xfn,
		);
	}

	/**
	 * @since 3.3.0
	 * @param array $args { item }.
	 * @return array|\WP_Error
	 */
	private function op_delete_item( array $args ) {
		$item_id = isset( $args['item'] ) ? absint( $args['item'] ) : 0;
		if ( ! $item_id || ! is_nav_menu_item( $item_id ) ) {
			return new \WP_Error( 'item_not_found', __( 'A valid "item" (menu item id) is required.', 'emcp-tools' ) );
		}
		$deleted = wp_delete_post( $item_id, true );
		if ( ! $deleted ) {
			return new \WP_Error( 'delete_failed', __( 'The menu item could not be deleted.', 'emcp-tools' ) );
		}
		return array( 'deleted' => true, 'item_id' => $item_id );
	}

	/**
	 * @since 3.3.0
	 * @param array $args { menu, items: [ { id, parent?, position } ] }.
	 * @return array|\WP_Error
	 */
	private function op_reorder_items( array $args ) {
		if ( ! isset( $args['menu'] ) || '' === $args['menu'] ) {
			return new \WP_Error( 'missing_menu', __( 'The "menu" argument is required.', 'emcp-tools' ) );
		}
		$menu = $this->resolve_menu( $args['menu'] );
		if ( is_wp_error( $menu ) ) {
			return $menu;
		}
		if ( ! isset( $args['items'] ) || ! is_array( $args['items'] ) || empty( $args['items'] ) ) {
			return new \WP_Error( 'missing_items', __( 'The "items" argument must be a non-empty array of { id, parent?, position }.', 'emcp-tools' ) );
		}
		$menu_id = (int) $menu->term_id;
		$updated = 0;
		foreach ( $args['items'] as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
				continue;
			}
			$item_id = absint( $entry['id'] );
			if ( ! is_nav_menu_item( $item_id ) ) {
				continue;
			}
			$owner = $this->menu_id_for_item( $item_id );
			if ( is_wp_error( $owner ) || (int) $owner !== $menu_id ) {
				continue;
			}
			if ( array_key_exists( 'parent', $entry ) && is_wp_error( $this->validate_parent( $entry['parent'], $menu_id ) ) ) {
				continue;
			}
			$existing = wp_setup_nav_menu_item( get_post( $item_id ) );
			$change   = array();
			if ( array_key_exists( 'parent', $entry ) ) {
				$change['parent'] = absint( $entry['parent'] );
			}
			if ( isset( $entry['position'] ) ) {
				$change['position'] = (int) $entry['position'];
			}
			$data = $this->merge_existing_item( $existing, $item_id, $change );
			$res  = wp_update_nav_menu_item( $menu_id, $item_id, $data );
			if ( ! is_wp_error( $res ) ) {
				++$updated;
			}
		}
		return array( 'menu_id' => $menu_id, 'updated' => $updated );
	}
}
