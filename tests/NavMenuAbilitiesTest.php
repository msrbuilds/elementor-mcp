<?php
/**
 * Unit tests for EMCP_Tools_Nav_Menu_Abilities.
 *
 * Self-contained: adds the nav-menu WordPress stubs the shared harness
 * (tests/bootstrap.php) does not define, guarded so they never clash, and an
 * in-memory menu model in $GLOBALS['emcp_nav']. Run:
 *
 *     vendor/bin/phpunit -c tests/phpunit.xml
 *
 * @package EMCP_Tools
 */

use PHPUnit\Framework\TestCase;

require_once EMCP_TOOLS_DIR . 'includes/abilities/class-nav-menu-abilities.php';

// ---------------------------------------------------------------------------
// Nav-menu WordPress stubs (only those the shared harness lacks).
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term {
		public $term_id  = 0;
		public $name     = '';
		public $slug     = '';
		public $count    = 0;
		public $taxonomy = 'nav_menu';
		public function __construct( array $props = array() ) {
			foreach ( $props as $k => $v ) {
				$this->$k = $v;
			}
		}
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class, $fallback = '' ) {
		$class = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
		return '' === $class ? $fallback : $class;
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $title ) ), '-' );
	}
}

if ( ! function_exists( 'wp_get_nav_menus' ) ) {
	function wp_get_nav_menus( $args = array() ) {
		return array_values( $GLOBALS['emcp_nav']['menus'] );
	}
}

if ( ! function_exists( 'wp_get_nav_menu_object' ) ) {
	function wp_get_nav_menu_object( $ref ) {
		if ( $ref instanceof WP_Term ) {
			return $ref;
		}
		foreach ( $GLOBALS['emcp_nav']['menus'] as $menu ) {
			if ( ( is_numeric( $ref ) && (int) $ref === (int) $menu->term_id ) || $ref === $menu->slug || $ref === $menu->name ) {
				return $menu;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'wp_create_nav_menu' ) ) {
	function wp_create_nav_menu( $name ) {
		$id                                = $GLOBALS['emcp_nav']['next_menu']++;
		$GLOBALS['emcp_nav']['menus'][ $id ] = new WP_Term( array( 'term_id' => $id, 'name' => $name, 'slug' => sanitize_title( $name ), 'count' => 0 ) );
		return $id;
	}
}

if ( ! function_exists( 'wp_update_nav_menu_object' ) ) {
	function wp_update_nav_menu_object( $id, $data ) {
		if ( ! isset( $GLOBALS['emcp_nav']['menus'][ $id ] ) ) {
			return new WP_Error( 'menu_missing', 'no menu' );
		}
		if ( isset( $data['menu-name'] ) ) {
			$GLOBALS['emcp_nav']['menus'][ $id ]->name = $data['menu-name'];
			$GLOBALS['emcp_nav']['menus'][ $id ]->slug = sanitize_title( $data['menu-name'] );
		}
		return $id;
	}
}

if ( ! function_exists( 'wp_delete_nav_menu' ) ) {
	function wp_delete_nav_menu( $menu ) {
		$obj = wp_get_nav_menu_object( $menu );
		if ( ! $obj ) {
			return false;
		}
		unset( $GLOBALS['emcp_nav']['menus'][ $obj->term_id ] );
		foreach ( $GLOBALS['emcp_nav']['item_menu'] as $iid => $mid ) {
			if ( (int) $mid === (int) $obj->term_id ) {
				unset( $GLOBALS['emcp_nav']['items'][ $iid ], $GLOBALS['emcp_nav']['item_menu'][ $iid ] );
			}
		}
		return true;
	}
}

if ( ! function_exists( 'wp_setup_nav_menu_item' ) ) {
	function wp_setup_nav_menu_item( $post ) {
		$id      = is_object( $post ) ? (int) $post->ID : (int) $post;
		$fields  = isset( $GLOBALS['emcp_nav']['items'][ $id ] ) ? $GLOBALS['emcp_nav']['items'][ $id ] : array();
		$default = array(
			'ID'               => $id,
			'title'            => '',
			'post_title'       => '',
			'url'              => '',
			'type'             => 'custom',
			'object'           => '',
			'object_id'        => 0,
			'target'           => '',
			'classes'          => array(),
			'xfn'              => '',
			'description'      => '',
			'menu_order'       => 0,
			'menu_item_parent' => 0,
			'attr_title'       => '',
		);
		return (object) array_merge( $default, $fields );
	}
}

if ( ! function_exists( 'wp_get_nav_menu_items' ) ) {
	function wp_get_nav_menu_items( $menu, $args = array() ) {
		$obj = wp_get_nav_menu_object( $menu );
		if ( ! $obj ) {
			return false;
		}
		$items = array();
		foreach ( $GLOBALS['emcp_nav']['items'] as $iid => $fields ) {
			if ( isset( $GLOBALS['emcp_nav']['item_menu'][ $iid ] ) && (int) $GLOBALS['emcp_nav']['item_menu'][ $iid ] === (int) $obj->term_id ) {
				$items[] = wp_setup_nav_menu_item( (object) array( 'ID' => $iid ) );
			}
		}
		usort(
			$items,
			function ( $a, $b ) {
				return $a->menu_order - $b->menu_order;
			}
		);
		return $items;
	}
}

if ( ! function_exists( 'wp_update_nav_menu_item' ) ) {
	function wp_update_nav_menu_item( $menu_id, $item_id, $data = array() ) {
		if ( ! isset( $GLOBALS['emcp_nav']['menus'][ $menu_id ] ) ) {
			return new WP_Error( 'menu_missing', 'no menu' );
		}
		$item_id = (int) $item_id;
		if ( 0 === $item_id ) {
			$item_id = $GLOBALS['emcp_nav']['next_item']++;
		}
		// Core resets every omitted menu-item-* key to its default — the data-loss
		// this feature guards against — so the stub must mirror that (NOT preserve
		// existing values), or the field-preservation tests would be circular.
		$get   = function ( $key, $default ) use ( $data ) {
			return array_key_exists( $key, $data ) ? $data[ $key ] : $default;
		};
		$title = (string) $get( 'menu-item-title', '' );
		$GLOBALS['emcp_nav']['items'][ $item_id ] = array(
			'ID'               => $item_id,
			'title'            => $title,
			'post_title'       => $title,
			'url'              => (string) $get( 'menu-item-url', '' ),
			'type'             => (string) $get( 'menu-item-type', 'custom' ),
			'object'           => (string) $get( 'menu-item-object', '' ),
			'object_id'        => (int) $get( 'menu-item-object-id', 0 ),
			'target'           => (string) $get( 'menu-item-target', '' ),
			'classes'          => array_values( array_filter( explode( ' ', (string) $get( 'menu-item-classes', '' ) ) ) ),
			'xfn'              => (string) $get( 'menu-item-xfn', '' ),
			'description'      => (string) $get( 'menu-item-description', '' ),
			'menu_order'       => (int) $get( 'menu-item-position', 0 ),
			'menu_item_parent' => (int) $get( 'menu-item-parent-id', 0 ),
			'attr_title'       => (string) $get( 'menu-item-attr-title', '' ),
		);
		$GLOBALS['emcp_nav']['item_menu'][ $item_id ] = (int) $menu_id;
		$GLOBALS['emcp_test']['posts'][ $item_id ]    = new WP_Post( array( 'ID' => $item_id, 'post_type' => 'nav_menu_item' ) );
		return $item_id;
	}
}

if ( ! function_exists( 'is_nav_menu_item' ) ) {
	function is_nav_menu_item( $id ) {
		return isset( $GLOBALS['emcp_nav']['items'][ (int) $id ] );
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $id, $force = false ) {
		$id      = (int) $id;
		$existed = isset( $GLOBALS['emcp_nav']['items'][ $id ] );
		unset( $GLOBALS['emcp_nav']['items'][ $id ], $GLOBALS['emcp_nav']['item_menu'][ $id ], $GLOBALS['emcp_test']['posts'][ $id ] );
		return $existed ? new WP_Post( array( 'ID' => $id ) ) : false;
	}
}

if ( ! function_exists( 'wp_get_object_terms' ) ) {
	function wp_get_object_terms( $id, $taxonomy ) {
		$mid = isset( $GLOBALS['emcp_nav']['item_menu'][ (int) $id ] ) ? (int) $GLOBALS['emcp_nav']['item_menu'][ (int) $id ] : 0;
		if ( ! $mid || ! isset( $GLOBALS['emcp_nav']['menus'][ $mid ] ) ) {
			return array();
		}
		return array( $GLOBALS['emcp_nav']['menus'][ $mid ] );
	}
}

if ( ! function_exists( 'get_registered_nav_menus' ) ) {
	function get_registered_nav_menus() {
		return $GLOBALS['emcp_nav']['registered'];
	}
}

if ( ! function_exists( 'get_nav_menu_locations' ) ) {
	function get_nav_menu_locations() {
		return isset( $GLOBALS['emcp_nav']['theme_mods']['nav_menu_locations'] ) ? $GLOBALS['emcp_nav']['theme_mods']['nav_menu_locations'] : array();
	}
}

if ( ! function_exists( 'set_theme_mod' ) ) {
	function set_theme_mod( $name, $value ) {
		$GLOBALS['emcp_nav']['theme_mods'][ $name ] = $value;
	}
}

if ( ! function_exists( 'get_term' ) ) {
	function get_term( $term_id, $taxonomy = '' ) {
		$key = $taxonomy . ':' . (int) $term_id;
		return isset( $GLOBALS['emcp_nav']['terms'][ $key ] ) ? $GLOBALS['emcp_nav']['terms'][ $key ] : null;
	}
}

if ( ! function_exists( 'wp_nav_menu' ) ) {
	function wp_nav_menu( $args = array() ) {
		if ( isset( $args['theme_location'] ) ) {
			$locations = get_nav_menu_locations();
			$ref       = isset( $locations[ $args['theme_location'] ] ) ? $locations[ $args['theme_location'] ] : 0;
		} else {
			$ref = isset( $args['menu'] ) ? $args['menu'] : 0;
		}
		$obj  = wp_get_nav_menu_object( $ref );
		$html = '<ul>';
		if ( $obj ) {
			foreach ( (array) wp_get_nav_menu_items( $obj->term_id ) as $item ) {
				$html .= '<li>' . $item->title . '</li>';
			}
		}
		$html .= '</ul>';
		if ( isset( $args['echo'] ) && false === $args['echo'] ) {
			return $html;
		}
		echo $html; // phpcs:ignore
		return $html;
	}
}

// ---------------------------------------------------------------------------

class NavMenuAbilitiesTest extends TestCase {

	/** @var EMCP_Tools_Nav_Menu_Abilities */
	private $abilities;

	protected function setUp(): void {
		emcp_test_reset();
		$GLOBALS['emcp_nav'] = array(
			'menus'      => array(),
			'items'      => array(),
			'item_menu'  => array(),
			'registered' => array(),
			'theme_mods' => array(),
			'terms'      => array(),
			'next_menu'  => 1,
			'next_item'  => 1000,
		);
		$GLOBALS['emcp_test']['existing_types'] = array( 'page', 'post' );
		$GLOBALS['emcp_test']['existing_taxes'] = array( 'category', 'post_tag' );
		$this->abilities                        = new EMCP_Tools_Nav_Menu_Abilities();
	}

	private function write( $operation, $arguments = array() ) {
		return $this->abilities->run_menu_write( array( 'operation' => $operation, 'arguments' => $arguments ) );
	}

	private function read( $operation, $arguments = array() ) {
		return $this->abilities->run_menu_read( array( 'operation' => $operation, 'arguments' => $arguments ) );
	}

	private function make_menu( $name = 'Main' ) {
		$res = $this->write( 'create-menu', array( 'name' => $name ) );
		return (int) $res['menu_id'];
	}

	public function test_read_catalog_lists_operations(): void {
		$catalog = $this->abilities->run_menu_read( array() );
		$this->assertSame( 'read', $catalog['mode'] );
		$names = array_column( $catalog['operations'], 'operation' );
		$this->assertContains( 'list-menus', $names );
		$this->assertContains( 'get-menu', $names );
		$this->assertContains( 'render', $names );
		$this->assertNotContains( 'create-menu', $names );
	}

	public function test_write_catalog_lists_operations(): void {
		$catalog = $this->abilities->run_menu_write( array() );
		$names   = array_column( $catalog['operations'], 'operation' );
		$this->assertContains( 'create-menu', $names );
		$this->assertContains( 'add-item', $names );
		$this->assertContains( 'reorder-items', $names );
	}

	public function test_unknown_operation_returns_error(): void {
		$this->assertTrue( is_wp_error( $this->read( 'nope' ) ) );
		$this->assertTrue( is_wp_error( $this->write( 'nope' ) ) );
		// Wrong-mode routing: a write op via the read dispatcher.
		$this->assertTrue( is_wp_error( $this->read( 'create-menu' ) ) );
	}

	public function test_create_and_list_menus(): void {
		$id   = $this->make_menu( 'Primary' );
		$this->assertGreaterThan( 0, $id );
		$list = $this->read( 'list-menus' );
		$this->assertCount( 1, $list['menus'] );
		$this->assertSame( 'Primary', $list['menus'][0]['name'] );
	}

	public function test_add_items_build_nested_tree(): void {
		$menu   = $this->make_menu();
		$parent = $this->write( 'add-item', array( 'menu' => $menu, 'type' => 'custom', 'title' => 'Services', 'url' => 'https://x.test/services' ) );
		$this->assertArrayHasKey( 'item_id', $parent );
		$child  = $this->write( 'add-item', array( 'menu' => $menu, 'type' => 'custom', 'title' => 'SEO', 'url' => 'https://x.test/seo', 'parent' => $parent['item_id'] ) );
		$this->assertArrayHasKey( 'item_id', $child );

		$tree = $this->read( 'get-menu', array( 'menu' => $menu ) );
		$this->assertCount( 1, $tree['items'] );
		$this->assertSame( 'Services', $tree['items'][0]['title'] );
		$this->assertCount( 1, $tree['items'][0]['children'] );
		$this->assertSame( 'SEO', $tree['items'][0]['children'][0]['title'] );
	}

	public function test_add_page_item_validates_object(): void {
		$menu = $this->make_menu();
		$GLOBALS['emcp_test']['posts'][10] = new WP_Post( array( 'ID' => 10, 'post_title' => 'About', 'post_type' => 'page' ) );

		$ok  = $this->write( 'add-item', array( 'menu' => $menu, 'type' => 'page', 'object_id' => 10 ) );
		$this->assertArrayHasKey( 'item_id', $ok );

		$bad = $this->write( 'add-item', array( 'menu' => $menu, 'type' => 'page', 'object_id' => 999 ) );
		$this->assertTrue( is_wp_error( $bad ) );
	}

	public function test_update_item_preserves_unspecified_fields(): void {
		$menu = $this->make_menu();
		$item = $this->write( 'add-item', array( 'menu' => $menu, 'type' => 'custom', 'title' => 'Old', 'url' => 'https://x.test/keep', 'classes' => array( 'a', 'b' ) ) );
		$id   = $item['item_id'];

		$this->write( 'update-item', array( 'item' => $id, 'title' => 'New' ) );

		$tree = $this->read( 'get-menu', array( 'menu' => $menu ) );
		$node = $tree['items'][0];
		$this->assertSame( 'New', $node['title'] );
		$this->assertSame( 'https://x.test/keep', $node['url'] );
		$this->assertSame( array( 'a', 'b' ), $node['classes'] );
	}

	public function test_reorder_items_changes_order_and_preserves_titles(): void {
		$menu = $this->make_menu();
		$a    = $this->write( 'add-item', array( 'menu' => $menu, 'type' => 'custom', 'title' => 'A', 'url' => 'https://x.test/a', 'position' => 1 ) );
		$b    = $this->write( 'add-item', array( 'menu' => $menu, 'type' => 'custom', 'title' => 'B', 'url' => 'https://x.test/b', 'position' => 2 ) );

		$res = $this->write( 'reorder-items', array( 'menu' => $menu, 'items' => array(
			array( 'id' => $b['item_id'], 'position' => 1 ),
			array( 'id' => $a['item_id'], 'position' => 2 ),
		) ) );
		$this->assertSame( 2, $res['updated'] );

		$tree = $this->read( 'get-menu', array( 'menu' => $menu ) );
		$this->assertSame( 'B', $tree['items'][0]['title'] );
		$this->assertSame( 'A', $tree['items'][1]['title'] );
	}

	public function test_assign_and_unassign_location(): void {
		$menu                                  = $this->make_menu();
		$GLOBALS['emcp_nav']['registered']     = array( 'primary' => 'Primary Menu' );

		$ok = $this->write( 'assign-location', array( 'menu' => $menu, 'location' => 'primary' ) );
		$this->assertSame( $menu, $ok['menu_id'] );

		$locations = $this->read( 'list-locations' );
		$this->assertSame( 'primary', $locations['locations'][0]['location'] );
		$this->assertSame( $menu, $locations['locations'][0]['menu_id'] );

		$bad = $this->write( 'assign-location', array( 'menu' => $menu, 'location' => 'nope' ) );
		$this->assertTrue( is_wp_error( $bad ) );

		$this->write( 'unassign-location', array( 'location' => 'primary' ) );
		$after = $this->read( 'list-locations' );
		$this->assertNull( $after['locations'][0]['menu_id'] );
	}

	public function test_delete_item_and_menu(): void {
		$menu = $this->make_menu();
		$item = $this->write( 'add-item', array( 'menu' => $menu, 'type' => 'custom', 'title' => 'X', 'url' => 'https://x.test/x' ) );

		$this->assertTrue( $this->write( 'delete-item', array( 'item' => $item['item_id'] ) )['deleted'] );
		$tree = $this->read( 'get-menu', array( 'menu' => $menu ) );
		$this->assertCount( 0, $tree['items'] );

		$this->assertTrue( $this->write( 'delete-menu', array( 'menu' => $menu ) )['deleted'] );
		$this->assertCount( 0, $this->read( 'list-menus' )['menus'] );
	}

	public function test_render_returns_html(): void {
		$menu = $this->make_menu();
		$this->write( 'add-item', array( 'menu' => $menu, 'type' => 'custom', 'title' => 'Home', 'url' => 'https://x.test/' ) );

		$out = $this->read( 'render', array( 'menu' => $menu ) );
		$this->assertArrayHasKey( 'html', $out );
		$this->assertStringContainsString( 'Home', $out['html'] );

		$this->assertTrue( is_wp_error( $this->read( 'render', array() ) ) );
	}

	public function test_permission_follows_capability(): void {
		$GLOBALS['emcp_test']['caps'] = array( 'edit_theme_options' );
		$this->assertTrue( $this->abilities->check_permission() );
		$GLOBALS['emcp_test']['caps'] = array( 'edit_posts' );
		$this->assertFalse( $this->abilities->check_permission() );
	}

	public function test_add_item_rejects_parent_from_another_menu(): void {
		$menu_a = $this->make_menu( 'A' );
		$menu_b = $this->make_menu( 'B' );
		$in_a   = $this->write( 'add-item', array( 'menu' => $menu_a, 'type' => 'custom', 'title' => 'A1', 'url' => 'https://x.test/a1' ) );

		$bad = $this->write( 'add-item', array( 'menu' => $menu_b, 'type' => 'custom', 'title' => 'B1', 'url' => 'https://x.test/b1', 'parent' => $in_a['item_id'] ) );
		$this->assertTrue( is_wp_error( $bad ) );
	}
}
