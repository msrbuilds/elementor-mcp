# WordPress Content Tools Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 8 general-WordPress content MCP tools (post/page/CPT CRUD + taxonomy + meta + featured image) in a new `EMCP_Tools_Content_Abilities` class, exposed on the existing `emcp-tools-server`.

**Architecture:** One self-contained ability group built on WP core functions (`wp_insert_post`, `wp_update_post`, `wp_delete_post`, `WP_Query`, `wp_set_object_terms`, `set_post_thumbnail`, `update_post_meta`). Mirrors the existing ability-class pattern. Enabled by default, capability-gated. Never touches `_elementor_data`.

**Tech Stack:** WordPress plugin (PHP 8.2+), WP Abilities API, bundled MCP Adapter, PHPUnit 10 with the project's function-stub harness.

**Spec:** [docs/superpowers/specs/2026-06-25-wp-content-tools-design.md](../specs/2026-06-25-wp-content-tools-design.md) — the full tool contracts. This plan implements it; when a tool's exact input/output schema isn't reproduced here, use the spec's contract verbatim.

---

## Background for the implementer (zero assumed context)

- **`php` is NOT on PATH.** Run tests with:
  ```
  /f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist
  ```
  from the plugin root `f:/laragon/www/msrplugins/wp-content/plugins/elementor-mcp`. Baseline before this work: **468 tests, 0 failures**.
- Branch: `feature/wp-content-tools` (already created). Commit footer:
  `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`
- **WordPress plugin conventions:** tab indentation, WP `array()` long syntax, every file starts with `if ( ! defined( 'ABSPATH' ) ) { exit; }`, classes `EMCP_Tools_*`, all MCP abilities registered via the global `emcp_tools_register_ability( $name, $args )` shim (never call `wp_register_ability()` directly), category `'emcp-tools'`, text domain `'emcp-tools'`, MCP namespace prefix `emcp-tools/`.
- **Pattern to copy:** [includes/abilities/class-media-library-abilities.php](../../../includes/abilities/class-media-library-abilities.php) is the cleanest single-tool example (constructor, `get_ability_names()`, `register()`, `check_read_permission()`, a `register_*` with full input/output schema + `meta` annotations, an `execute_*`, a private formatter). Follow its structure exactly.
- **Test harness:** base class [tests/unit/class-ability-test-case.php](../../../tests/unit/class-ability-test-case.php) gives `allow_caps(...)`, `deny_all_caps()`, `assertWPError($r,$code)`, `assertNotWPError($r)`, `assertResultHasKey($r,$key)`. Capabilities are controlled by `$GLOBALS['_caps']` (null = all allowed; array = only those allowed). WP functions are **stubbed** in [tests/bootstrap.php](../../../tests/bootstrap.php) — existing stubs include `current_user_can` (honors `$GLOBALS['_caps']`), `wp_insert_post` (returns incrementing id ≥101), `wp_update_post` (returns `$args['ID']`), `wp_delete_post` (records to `$GLOBALS['_wp_deleted_posts']`), `get_post` (returns null), `get_post_meta`, `update_post_meta`, `sanitize_text_field`, `absint`, `is_wp_error`, `apply_filters`, `get_permalink`, `admin_url`. **Tests in this plan add more stubs** (see Task 1 Step 1) — add them guarded by `if ( ! function_exists(...) )` next to the others in `tests/bootstrap.php`.
- **Registration flow:** an ability group is instantiated + `register()`ed + its names merged in [includes/abilities/class-ability-registrar.php](../../../includes/abilities/class-ability-registrar.php) `register_all()`, and the class file is `require_once`d in [includes/class-bootstrap.php](../../../includes/class-bootstrap.php) `load_classes()`.
- **Live MCP check (Task 8)** uses WP-CLI:
  ```
  /c/wp-cli/wp-cli.phar  (run via the PHP binary above)
  /f/.../php.exe /c/wp-cli/wp-cli.phar mcp-adapter serve --server=emcp-tools-server --user=admin --path=f:/laragon/www/msrplugins
  ```

---

## File Structure

**New:**
- `includes/abilities/class-content-abilities.php` — `EMCP_Tools_Content_Abilities`: the 8 tools + private helpers (`format_post()`, `apply_write_extras()` for terms/meta/featured-image, `is_writable_post_type()`, `reject_protected_meta()`).
- `tests/unit/capabilities/ContentCapabilityTest.php` — permission-callback gating per tool.
- `tests/unit/content/ContentToolsTest.php` — execute-path behavior + input validation.

**Modified:**
- `tests/bootstrap.php` — add WP function stubs the content tools need.
- `includes/abilities/class-ability-registrar.php` — register the group (unconditional).
- `includes/class-bootstrap.php` — `require_once` the class.
- `includes/class-plugin.php` — append the 3 `core/*` read abilities to the server tools array; add content slugs to `get_essential_tool_slugs()`.
- `includes/admin/class-admin.php` — new "WordPress Content" category in `get_tool_catalog()`.
- `CLAUDE.md`, `README.md`, `readme.txt`, `CHANGELOG.md` — document the family.

---

## Conventions for all 8 tools

Each tool follows this shape (copy from media-library):
```php
private function register_<tool>(): void {
	emcp_tools_register_ability(
		'emcp-tools/<tool>',
		array(
			'label'               => __( '<Label>', 'emcp-tools' ),
			'description'         => __( '<desc — see spec>', 'emcp-tools' ),
			'category'            => 'emcp-tools',
			'execute_callback'    => array( $this, 'execute_<tool>' ),
			'permission_callback' => array( $this, '<cap check>' ),
			'input_schema'        => array( 'type' => 'object', 'properties' => array( /* spec */ ), 'required' => array( /* spec */ ) ),
			'output_schema'       => array( 'type' => 'object', 'properties' => array( /* spec */ ) ),
			'meta'                => array(
				'annotations'  => array( 'readonly' => <bool>, 'destructive' => <bool>, 'idempotent' => <bool> ),
				'show_in_rest' => true,
			),
		)
	);
}
```
Annotations: discovery + get + list → `readonly:true, destructive:false, idempotent:true`. create → all false. update/set-post-terms → `readonly:false, destructive:false, idempotent:false`. delete → `readonly:false, destructive:true, idempotent:false`.

Permission callbacks (methods on the class):
- `check_read_permission()` → `current_user_can('edit_posts')` (discovery, get, list).
- `check_create_permission()` → `current_user_can('edit_posts')` (finer publish/author checks happen in execute).
- `check_edit_permission( $input )` → `current_user_can('edit_posts')` and, if `post_id` present, `current_user_can('edit_post', $post_id)`.
- `check_delete_permission( $input )` → `current_user_can('delete_posts')` and, if `post_id` present, `current_user_can('delete_post', $post_id)`.

---

## Task 1: Scaffold class + discovery tools (`list-post-types`, `list-taxonomies`)

**Files:**
- Create: `includes/abilities/class-content-abilities.php`
- Create: `tests/unit/content/ContentToolsTest.php`
- Modify: `tests/bootstrap.php`
- Modify: `includes/class-bootstrap.php`, `includes/abilities/class-ability-registrar.php`

- [ ] **Step 1: Add the WP stubs this whole plan needs to `tests/bootstrap.php`**

Add these next to the existing stubs (each guarded by `if ( ! function_exists(...) )`). They give controllable behavior via `$GLOBALS`:

```php
	// --- Content-tool stubs (added for WordPress Content tools) ---

	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $key ): string {
			return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
		}
	}

	if ( ! function_exists( 'sanitize_title' ) ) {
		function sanitize_title( $title ): string {
			return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', (string) $title ), '-' ) );
		}
	}

	if ( ! function_exists( 'wp_trash_post' ) ) {
		function wp_trash_post( int $post_id ) {
			$GLOBALS['_wp_trashed_posts'][] = $post_id;
			return (object) array( 'ID' => $post_id );
		}
	}

	if ( ! function_exists( 'wp_set_object_terms' ) ) {
		function wp_set_object_terms( int $object_id, $terms, string $taxonomy, bool $append = false ) {
			$GLOBALS['_wp_term_calls'][] = compact( 'object_id', 'terms', 'taxonomy', 'append' );
			return is_array( $terms ) ? array_map( 'absint', array_values( array_filter( $terms, 'is_numeric' ) ) ) : array();
		}
	}

	if ( ! function_exists( 'set_post_thumbnail' ) ) {
		function set_post_thumbnail( $post, int $thumbnail_id ): bool {
			$GLOBALS['_wp_thumbnail_calls'][] = array( 'post' => is_object( $post ) ? $post->ID : $post, 'thumb' => $thumbnail_id );
			return true;
		}
	}

	if ( ! function_exists( 'delete_post_thumbnail' ) ) {
		function delete_post_thumbnail( $post ): bool {
			$GLOBALS['_wp_thumbnail_calls'][] = array( 'post' => is_object( $post ) ? $post->ID : $post, 'thumb' => 0 );
			return true;
		}
	}

	if ( ! function_exists( 'is_protected_meta' ) ) {
		function is_protected_meta( string $meta_key, string $meta_type = '' ): bool {
			return '_' === substr( $meta_key, 0, 1 );
		}
	}

	if ( ! function_exists( 'get_userdata' ) ) {
		function get_userdata( int $user_id ) {
			// $GLOBALS['_wp_users'][$id] = (object){ID, display_name} or false for "missing".
			if ( isset( $GLOBALS['_wp_users'] ) && array_key_exists( $user_id, $GLOBALS['_wp_users'] ) ) {
				return $GLOBALS['_wp_users'][ $user_id ];
			}
			return (object) array( 'ID' => $user_id, 'display_name' => 'User ' . $user_id );
		}
	}

	if ( ! function_exists( 'get_current_user_id' ) ) {
		function get_current_user_id(): int {
			return $GLOBALS['_wp_current_user_id'] ?? 1;
		}
	}

	if ( ! function_exists( 'post_type_exists' ) ) {
		function post_type_exists( string $post_type ): bool {
			$known = $GLOBALS['_wp_post_types'] ?? array( 'post', 'page' );
			return in_array( $post_type, array_keys( (array) $known ), true ) || in_array( $post_type, (array) $known, true );
		}
	}

	if ( ! function_exists( 'get_post_types' ) ) {
		function get_post_types( array $args = array(), string $output = 'names', string $operator = 'and' ): array {
			// $GLOBALS['_wp_post_type_objects'] = [ name => (object){name,label,hierarchical,public,...} ]
			$objs = $GLOBALS['_wp_post_type_objects'] ?? array();
			if ( 'objects' === $output ) {
				return $objs;
			}
			return array_keys( $objs );
		}
	}

	if ( ! function_exists( 'get_taxonomies' ) ) {
		function get_taxonomies( array $args = array(), string $output = 'names' ): array {
			$objs = $GLOBALS['_wp_taxonomy_objects'] ?? array();
			return 'objects' === $output ? $objs : array_keys( $objs );
		}
	}

	if ( ! function_exists( 'get_object_taxonomies' ) ) {
		function get_object_taxonomies( $object, string $output = 'names' ): array {
			return array_keys( $GLOBALS['_wp_taxonomy_objects'] ?? array() );
		}
	}

	if ( ! function_exists( 'get_terms' ) ) {
		function get_terms( $args = array() ): array {
			return $GLOBALS['_wp_terms'] ?? array();
		}
	}

	if ( ! function_exists( 'get_the_terms' ) ) {
		function get_the_terms( $post, string $taxonomy ) {
			return $GLOBALS['_wp_post_terms'][ $taxonomy ] ?? array();
		}
	}

	if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
		function get_post_thumbnail_id( $post = null ) {
			return $GLOBALS['_wp_post_thumbnail_id'] ?? 0;
		}
	}

	if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
		function wp_get_attachment_image_url( int $id, $size = 'thumbnail' ) {
			return 'http://example.com/img-' . $id . '.jpg';
		}
	}
```

Also extend `Ability_Test_Case::setUp()` recording resets — but do NOT edit the base class; instead each content test resets its own globals in `setUp()` (see Task tests). (Rationale: keep base-class changes out of this feature.)

- [ ] **Step 2: Write the failing test** `tests/unit/content/ContentToolsTest.php`

```php
<?php
/**
 * Execute-path + validation tests for the WordPress Content tools.
 * @group content
 * @package EMCP_Tools\Tests\Content
 */
namespace EMCP_Tools\Tests\Content;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class ContentToolsTest extends Ability_Test_Case {
	private \EMCP_Tools_Content_Abilities $ability;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_trashed_posts']     = array();
		$GLOBALS['_wp_term_calls']        = array();
		$GLOBALS['_wp_thumbnail_calls']   = array();
		$GLOBALS['_wp_current_user_id']   = 1;
		$GLOBALS['_wp_post_type_objects'] = array(
			'post' => (object) array( 'name' => 'post', 'label' => 'Posts', 'hierarchical' => false, 'public' => true, '_builtin' => true ),
			'page' => (object) array( 'name' => 'page', 'label' => 'Pages', 'hierarchical' => true, 'public' => true, '_builtin' => true ),
		);
		$GLOBALS['_wp_taxonomy_objects'] = array(
			'category' => (object) array( 'name' => 'category', 'label' => 'Categories', 'hierarchical' => true, 'object_type' => array( 'post' ) ),
			'post_tag' => (object) array( 'name' => 'post_tag', 'label' => 'Tags', 'hierarchical' => false, 'object_type' => array( 'post' ) ),
		);
		$this->ability = new \EMCP_Tools_Content_Abilities();
		$this->ability->register();
	}

	/** @test */
	public function test_registers_all_eight_tools(): void {
		$names = $this->ability->get_ability_names();
		foreach ( array(
			'emcp-tools/list-post-types', 'emcp-tools/list-taxonomies',
			'emcp-tools/create-post', 'emcp-tools/get-post', 'emcp-tools/update-post',
			'emcp-tools/list-posts', 'emcp-tools/delete-post', 'emcp-tools/set-post-terms',
		) as $n ) {
			$this->assertContains( $n, $names, "missing tool: $n" );
		}
	}

	/** @test */
	public function test_list_post_types_returns_public_types(): void {
		$out = $this->ability->execute_list_post_types( array() );
		$this->assertResultHasKey( $out, 'post_types' );
		$names = array_column( $out['post_types'], 'name' );
		$this->assertContains( 'post', $names );
		$this->assertContains( 'page', $names );
	}

	/** @test */
	public function test_list_taxonomies_returns_category(): void {
		$out = $this->ability->execute_list_taxonomies( array() );
		$this->assertResultHasKey( $out, 'taxonomies' );
		$this->assertContains( 'category', array_column( $out['taxonomies'], 'name' ) );
	}
}
```

- [ ] **Step 3: Run it — verify failure**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --filter ContentToolsTest --configuration phpunit.xml.dist`
Expected: FATAL — `Class "EMCP_Tools_Content_Abilities" not found`. (If instead you get "function not found" for a stub, you missed one in Step 1 — add it.)

- [ ] **Step 4: Create the class with the two discovery tools**

Create `includes/abilities/class-content-abilities.php`:

```php
<?php
/**
 * General WordPress Content MCP abilities.
 *
 * Eight tools for managing posts, pages, and any custom post type via MCP —
 * the plugin's first step beyond Elementor. Built on WP core functions, gated
 * by WordPress capabilities, and deliberately Elementor-agnostic: these tools
 * operate on post_content (classic HTML or block markup) and never touch
 * `_elementor_data`. To edit an Elementor-built page, use the Elementor tools.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the WordPress content abilities.
 *
 * @since 3.1.0
 */
class EMCP_Tools_Content_Abilities {

	/**
	 * @since 3.1.0
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'emcp-tools/list-post-types',
			'emcp-tools/list-taxonomies',
			'emcp-tools/create-post',
			'emcp-tools/get-post',
			'emcp-tools/update-post',
			'emcp-tools/list-posts',
			'emcp-tools/delete-post',
			'emcp-tools/set-post-terms',
		);
	}

	/**
	 * @since 3.1.0
	 */
	public function register(): void {
		$this->register_list_post_types();
		$this->register_list_taxonomies();
		$this->register_create_post();
		$this->register_get_post();
		$this->register_update_post();
		$this->register_list_posts();
		$this->register_delete_post();
		$this->register_set_post_terms();
	}

	// ---------------------------------------------------------------------
	// Permission callbacks
	// ---------------------------------------------------------------------

	public function check_read_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function check_create_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function check_edit_permission( $input = null ): bool {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		$post_id = absint( $input['post_id'] ?? 0 );
		return ! $post_id || current_user_can( 'edit_post', $post_id );
	}

	public function check_delete_permission( $input = null ): bool {
		if ( ! current_user_can( 'delete_posts' ) ) {
			return false;
		}
		$post_id = absint( $input['post_id'] ?? 0 );
		return ! $post_id || current_user_can( 'delete_post', $post_id );
	}

	// ---------------------------------------------------------------------
	// list-post-types
	// ---------------------------------------------------------------------

	private function register_list_post_types(): void {
		emcp_tools_register_ability(
			'emcp-tools/list-post-types',
			array(
				'label'               => __( 'List Post Types', 'emcp-tools' ),
				'description'         => __( 'Lists registered WordPress post types (posts, pages, and any custom post type) so you can target the right one with create-post / list-posts. Returns name, label, whether it is hierarchical, and its taxonomies.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_post_types' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'public_only' => array( 'type' => 'boolean', 'description' => __( 'Only public, non-internal types. Default: true.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_types' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
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
	 * @param array $input
	 * @return array
	 */
	public function execute_list_post_types( $input ): array {
		$public_only = ! isset( $input['public_only'] ) || (bool) $input['public_only'];
		$args        = $public_only ? array( 'public' => true ) : array();
		$objects     = get_post_types( $args, 'objects' );

		$internal = array( 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' );
		$rows     = array();
		foreach ( $objects as $name => $obj ) {
			if ( $public_only && in_array( $name, $internal, true ) ) {
				continue;
			}
			$rows[] = array(
				'name'         => (string) $name,
				'label'        => (string) ( $obj->label ?? $name ),
				'hierarchical' => (bool) ( $obj->hierarchical ?? false ),
				'public'       => (bool) ( $obj->public ?? false ),
				'supports'     => function_exists( 'get_all_post_type_supports' ) ? array_keys( get_all_post_type_supports( $name ) ) : array(),
				'taxonomies'   => function_exists( 'get_object_taxonomies' ) ? array_values( get_object_taxonomies( $name ) ) : array(),
			);
		}
		return array( 'post_types' => $rows );
	}

	// ---------------------------------------------------------------------
	// list-taxonomies
	// ---------------------------------------------------------------------

	private function register_list_taxonomies(): void {
		emcp_tools_register_ability(
			'emcp-tools/list-taxonomies',
			array(
				'label'               => __( 'List Taxonomies', 'emcp-tools' ),
				'description'         => __( 'Lists registered taxonomies (categories, tags, custom taxonomies) and optionally their terms, so you can categorize content with set-post-terms or the create-post "terms" param.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_taxonomies' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type'     => array( 'type' => 'string', 'description' => __( 'Only taxonomies attached to this post type.', 'emcp-tools' ) ),
						'include_terms' => array( 'type' => 'boolean', 'description' => __( 'Embed each taxonomy\'s terms (capped). Default: false.', 'emcp-tools' ) ),
						'terms_limit'   => array( 'type' => 'integer', 'description' => __( 'Max terms per taxonomy when include_terms. Default: 100.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'taxonomies' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
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
	 * @param array $input
	 * @return array
	 */
	public function execute_list_taxonomies( $input ): array {
		$post_type     = sanitize_key( $input['post_type'] ?? '' );
		$include_terms = ! empty( $input['include_terms'] );
		$limit         = max( 1, min( 500, absint( $input['terms_limit'] ?? 100 ) ) );

		$objects = $post_type
			? get_taxonomies( array( 'object_type' => array( $post_type ) ), 'objects' )
			: get_taxonomies( array(), 'objects' );

		$rows = array();
		foreach ( $objects as $name => $obj ) {
			$row = array(
				'name'         => (string) $name,
				'label'        => (string) ( $obj->label ?? $name ),
				'hierarchical' => (bool) ( $obj->hierarchical ?? false ),
				'object_types' => array_values( (array) ( $obj->object_type ?? array() ) ),
			);
			if ( $include_terms ) {
				$terms     = get_terms( array( 'taxonomy' => $name, 'hide_empty' => false, 'number' => $limit ) );
				$row['terms'] = array();
				foreach ( (array) $terms as $t ) {
					if ( is_object( $t ) ) {
						$row['terms'][] = array(
							'term_id' => (int) $t->term_id,
							'name'    => (string) $t->name,
							'slug'    => (string) $t->slug,
							'parent'  => (int) ( $t->parent ?? 0 ),
							'count'   => (int) ( $t->count ?? 0 ),
						);
					}
				}
			}
			$rows[] = $row;
		}
		return array( 'taxonomies' => $rows );
	}
}
```

- [ ] **Step 5: Register the group**

In `includes/class-bootstrap.php` `load_classes()`, after the media-library require (`require_once EMCP_TOOLS_DIR . 'includes/abilities/class-media-library-abilities.php';`) add:
```php
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-content-abilities.php';
```

In `includes/abilities/class-ability-registrar.php` `register_all()`, after the media-library block (the `$media_library = new EMCP_Tools_Media_Library_Abilities(...)` block) add:
```php
		// WordPress Content abilities (posts/pages/CPT CRUD + taxonomy + meta).
		// Unconditional — pure WordPress, always available.
		$content = new EMCP_Tools_Content_Abilities();
		$content->register();
		$this->ability_names = array_merge( $this->ability_names, $content->get_ability_names() );
```

- [ ] **Step 6: Run tests — verify pass**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --filter ContentToolsTest --configuration phpunit.xml.dist`
Expected: PASS (3 tests). Then full suite — expected still green (was 468; now 471):
`/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist`

- [ ] **Step 7: Commit**

```bash
git add includes/abilities/class-content-abilities.php tests/unit/content/ContentToolsTest.php tests/bootstrap.php includes/class-bootstrap.php includes/abilities/class-ability-registrar.php
git commit -m "feat(content): scaffold content ability group + discovery tools (Task 1)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: `create-post` + `get-post` + shared write helpers

**Files:** Modify `includes/abilities/class-content-abilities.php`, `tests/unit/content/ContentToolsTest.php`.

- [ ] **Step 1: Write failing tests** — append to `ContentToolsTest`:

```php
	/** @test */
	public function test_create_post_returns_id_and_permalink(): void {
		$out = $this->ability->execute_create_post( array(
			'post_type' => 'post', 'title' => 'Hello', 'content' => '<p>Hi</p>', 'status' => 'draft',
		) );
		$this->assertNotWPError( $out );
		$this->assertResultHasKey( $out, 'post_id' );
		$this->assertArrayHasKey( 'permalink', $out );
		$this->assertGreaterThan( 100, $out['post_id'] );
	}

	/** @test */
	public function test_create_post_rejects_internal_type(): void {
		$out = $this->ability->execute_create_post( array( 'post_type' => 'revision', 'title' => 'x' ) );
		$this->assertWPError( $out, 'invalid_post_type' );
	}

	/** @test */
	public function test_create_post_rejects_unknown_type(): void {
		$out = $this->ability->execute_create_post( array( 'post_type' => 'no_such_type', 'title' => 'x' ) );
		$this->assertWPError( $out, 'invalid_post_type' );
	}

	/** @test */
	public function test_create_post_rejects_protected_meta(): void {
		$out = $this->ability->execute_create_post( array(
			'post_type' => 'post', 'title' => 'x',
			'meta' => array( '_elementor_data' => '[]' ),
		) );
		$this->assertWPError( $out, 'protected_meta' );
	}

	/** @test */
	public function test_create_post_applies_terms_and_meta(): void {
		$out = $this->ability->execute_create_post( array(
			'post_type' => 'post', 'title' => 'x',
			'terms' => array( 'category' => array( 5 ) ),
			'meta'  => array( 'my_field' => 'v' ),
		) );
		$this->assertNotWPError( $out );
		$this->assertNotEmpty( $GLOBALS['_wp_term_calls'], 'wp_set_object_terms should have been called' );
	}
```

- [ ] **Step 2: Run — verify fail** (`execute_create_post` undefined).
Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --filter ContentToolsTest --configuration phpunit.xml.dist`

- [ ] **Step 3: Implement `create-post`, `get-post`, and shared helpers**

Add to the class. The shared helpers first:

```php
	/** Internal/non-writable post types (never targets for create/update/delete). */
	private function internal_post_types(): array {
		return array( 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'attachment' );
	}

	/** Whether a post type may be written to. */
	private function is_writable_post_type( string $post_type ): bool {
		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return false;
		}
		return ! in_array( $post_type, $this->internal_post_types(), true );
	}

	/**
	 * Validate a meta map against the protected-meta guard.
	 * @return true|\WP_Error
	 */
	private function reject_protected_meta( array $meta ) {
		$allowed = (array) apply_filters( 'emcp_tools_content_allowed_protected_meta', array() );
		foreach ( array_keys( $meta ) as $key ) {
			$key = (string) $key;
			if ( in_array( $key, $allowed, true ) ) {
				continue;
			}
			if ( '_' === substr( $key, 0, 1 ) || is_protected_meta( $key, 'post' ) ) {
				return new \WP_Error( 'protected_meta', sprintf( /* translators: %s: meta key */ __( 'Refusing to write protected meta key "%s". Use the featured_image param for thumbnails; Elementor data is never writable here.', 'emcp-tools' ), $key ) );
			}
		}
		return true;
	}

	/**
	 * Apply terms / meta / featured image to a post after create/update.
	 * Collects non-fatal problems into $warnings (by reference).
	 *
	 * @param int   $post_id
	 * @param array $input
	 * @param array $warnings
	 * @param bool  $append_terms
	 */
	private function apply_write_extras( int $post_id, array $input, array &$warnings, bool $append_terms = false ): void {
		// Terms.
		if ( isset( $input['terms'] ) && is_array( $input['terms'] ) ) {
			foreach ( $input['terms'] as $taxonomy => $terms ) {
				$taxonomy = sanitize_key( $taxonomy );
				$res      = wp_set_object_terms( $post_id, array_values( (array) $terms ), $taxonomy, $append_terms );
				if ( is_wp_error( $res ) ) {
					$warnings[] = sprintf( 'terms[%s]: %s', $taxonomy, $res->get_error_message() );
				}
			}
		}
		// Meta (already guarded by reject_protected_meta before calling this).
		if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
			foreach ( $input['meta'] as $key => $value ) {
				update_post_meta( $post_id, sanitize_key( $key ), $value );
			}
		}
		// Featured image: { id } or { url } ; null clears.
		if ( array_key_exists( 'featured_image', $input ) ) {
			$fi = $input['featured_image'];
			if ( null === $fi ) {
				delete_post_thumbnail( $post_id );
			} elseif ( is_array( $fi ) && ! empty( $fi['id'] ) ) {
				set_post_thumbnail( $post_id, absint( $fi['id'] ) );
			} elseif ( is_array( $fi ) && ! empty( $fi['url'] ) ) {
				if ( ! current_user_can( 'upload_files' ) ) {
					$warnings[] = 'featured_image: upload_files capability required to sideload a URL.';
				} else {
					$att = media_sideload_image( esc_url_raw( (string) $fi['url'] ), $post_id, '', 'id' );
					if ( is_wp_error( $att ) ) {
						$warnings[] = 'featured_image: ' . $att->get_error_message();
					} else {
						set_post_thumbnail( $post_id, (int) $att );
					}
				}
			}
		}
	}

	/** Allowed post statuses for create/update. */
	private function valid_statuses(): array {
		return array( 'draft', 'publish', 'pending', 'private', 'future' );
	}
```

Now `create-post` registration + executor (input/output schema per spec):

```php
	private function register_create_post(): void {
		emcp_tools_register_ability(
			'emcp-tools/create-post',
			array(
				'label'               => __( 'Create Post', 'emcp-tools' ),
				'description'         => __( 'Creates a post, page, or any custom post type. Sets title, content (classic HTML or Gutenberg block markup), excerpt, status, slug, author, date, parent/menu_order, plus optional taxonomy terms, custom-field meta, and a featured image — in one call. This writes post_content; to build an Elementor page use the Elementor tools instead.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_create_post' ),
				'permission_callback' => array( $this, 'check_create_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type'      => array( 'type' => 'string', 'description' => __( 'Target type (post, page, or a CPT from list-post-types). Default: post.', 'emcp-tools' ) ),
						'title'          => array( 'type' => 'string', 'description' => __( 'Post title.', 'emcp-tools' ) ),
						'content'        => array( 'type' => 'string', 'description' => __( 'post_content — classic HTML or Gutenberg block markup, stored verbatim.', 'emcp-tools' ) ),
						'excerpt'        => array( 'type' => 'string' ),
						'status'         => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'pending', 'private', 'future' ), 'description' => __( 'Default: draft.', 'emcp-tools' ) ),
						'slug'           => array( 'type' => 'string' ),
						'author'         => array( 'type' => 'integer', 'description' => __( 'User ID. Default: current user.', 'emcp-tools' ) ),
						'date'           => array( 'type' => 'string', 'description' => __( 'Y-m-d H:i:s. Required for status=future.', 'emcp-tools' ) ),
						'parent'         => array( 'type' => 'integer', 'description' => __( 'Parent ID (hierarchical types).', 'emcp-tools' ) ),
						'menu_order'     => array( 'type' => 'integer' ),
						'comment_status' => array( 'type' => 'string', 'enum' => array( 'open', 'closed' ) ),
						'terms'          => array( 'type' => 'object', 'description' => __( 'Map of taxonomy → array of term IDs or names. Names are created if missing.', 'emcp-tools' ) ),
						'meta'           => array( 'type' => 'object', 'description' => __( 'Custom fields. Protected/underscore-prefixed keys are rejected.', 'emcp-tools' ) ),
						'featured_image' => array( 'type' => 'object', 'description' => __( 'Featured image: { id } (attachment) or { url } (sideloaded). null clears.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'   => array( 'type' => 'integer' ),
						'status'    => array( 'type' => 'string' ),
						'permalink' => array( 'type' => 'string' ),
						'edit_link' => array( 'type' => 'string' ),
						'warnings'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
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
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_create_post( $input ) {
		$post_type = sanitize_key( $input['post_type'] ?? 'post' );
		if ( '' === $post_type ) {
			$post_type = 'post';
		}
		if ( ! $this->is_writable_post_type( $post_type ) ) {
			return new \WP_Error( 'invalid_post_type', sprintf( /* translators: %s: type */ __( '"%s" is not a writable post type.', 'emcp-tools' ), $post_type ) );
		}

		$status = sanitize_key( $input['status'] ?? 'draft' );
		if ( ! in_array( $status, $this->valid_statuses(), true ) ) {
			return new \WP_Error( 'invalid_status', __( 'Invalid status.', 'emcp-tools' ) );
		}
		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			return new \WP_Error( 'cannot_publish', __( 'You do not have permission to publish.', 'emcp-tools' ) );
		}

		// Protected-meta guard BEFORE any write.
		if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
			$guard = $this->reject_protected_meta( $input['meta'] );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
		}

		$author = absint( $input['author'] ?? 0 );
		if ( $author && (int) $author !== get_current_user_id() && ! current_user_can( 'edit_others_posts' ) ) {
			return new \WP_Error( 'cannot_set_author', __( 'You cannot assign another author.', 'emcp-tools' ) );
		}

		$postarr = array(
			'post_type'    => $post_type,
			'post_status'  => $status,
			'post_title'   => sanitize_text_field( $input['title'] ?? '' ),
			'post_content' => (string) ( $input['content'] ?? '' ),  // verbatim; wp_insert_post slashes.
			'post_excerpt' => (string) ( $input['excerpt'] ?? '' ),
		);
		if ( ! empty( $input['slug'] ) )           { $postarr['post_name'] = sanitize_title( $input['slug'] ); }
		if ( $author )                              { $postarr['post_author'] = $author; }
		if ( ! empty( $input['date'] ) )            { $postarr['post_date'] = sanitize_text_field( $input['date'] ); }
		if ( isset( $input['parent'] ) )            { $postarr['post_parent'] = absint( $input['parent'] ); }
		if ( isset( $input['menu_order'] ) )        { $postarr['menu_order'] = (int) $input['menu_order']; }
		if ( ! empty( $input['comment_status'] ) )  { $postarr['comment_status'] = ( 'open' === $input['comment_status'] ) ? 'open' : 'closed'; }

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$post_id = (int) $post_id;

		$warnings = array();
		$this->apply_write_extras( $post_id, $input, $warnings, false );

		$result = array(
			'post_id'   => $post_id,
			'status'    => $status,
			'permalink' => (string) get_permalink( $post_id ),
			'edit_link' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
		);
		if ( $warnings ) {
			$result['warnings'] = $warnings;
		}
		return $result;
	}
```

Now `get-post`:

```php
	private function register_get_post(): void {
		emcp_tools_register_ability(
			'emcp-tools/get-post',
			array(
				'label'               => __( 'Get Post', 'emcp-tools' ),
				'description'         => __( 'Returns a single post/page/CPT: title, content, status, author, dates, terms, non-protected meta, and featured image. The is_elementor flag tells you whether the page is built with Elementor (edit those with the Elementor tools, not update-post).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_post' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'post_id' => array( 'type' => 'integer', 'description' => __( 'The post ID.', 'emcp-tools' ) ) ),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'post_id' => array( 'type' => 'integer' ), 'post_type' => array( 'type' => 'string' ),
					'title' => array( 'type' => 'string' ), 'slug' => array( 'type' => 'string' ),
					'status' => array( 'type' => 'string' ), 'content' => array( 'type' => 'string' ),
					'excerpt' => array( 'type' => 'string' ), 'date' => array( 'type' => 'string' ),
					'modified' => array( 'type' => 'string' ), 'parent' => array( 'type' => 'integer' ),
					'menu_order' => array( 'type' => 'integer' ), 'permalink' => array( 'type' => 'string' ),
					'edit_link' => array( 'type' => 'string' ), 'author' => array( 'type' => 'object' ),
					'terms' => array( 'type' => 'object' ), 'meta' => array( 'type' => 'object' ),
					'featured_image' => array( 'type' => array( 'object', 'null' ) ),
					'is_elementor' => array( 'type' => 'boolean' ),
				) ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_get_post( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'post_id is required.', 'emcp-tools' ) );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'emcp-tools' ) );
		}
		return $this->format_post( $post );
	}

	/**
	 * Full serialization of a post for get-post.
	 *
	 * @param object $post WP_Post.
	 * @return array
	 */
	private function format_post( $post ): array {
		$post_id = (int) $post->ID;

		// Terms across the post type's taxonomies.
		$terms = array();
		foreach ( (array) get_object_taxonomies( $post->post_type ) as $tax ) {
			$tobjs = get_the_terms( $post, $tax );
			if ( is_array( $tobjs ) ) {
				$terms[ $tax ] = array();
				foreach ( $tobjs as $t ) {
					$terms[ $tax ][] = array( 'term_id' => (int) $t->term_id, 'name' => (string) $t->name, 'slug' => (string) $t->slug );
				}
			}
		}

		// Non-protected meta.
		$meta_raw = get_post_meta( $post_id );
		$meta     = array();
		if ( is_array( $meta_raw ) ) {
			foreach ( $meta_raw as $key => $vals ) {
				if ( '_' === substr( (string) $key, 0, 1 ) || is_protected_meta( (string) $key, 'post' ) ) {
					continue;
				}
				$meta[ $key ] = is_array( $vals ) && 1 === count( $vals ) ? maybe_unserialize( $vals[0] ) : array_map( 'maybe_unserialize', (array) $vals );
			}
		}

		// Featured image.
		$thumb_id = (int) get_post_thumbnail_id( $post );
		$featured = $thumb_id ? array(
			'id'  => $thumb_id,
			'url' => (string) wp_get_attachment_image_url( $thumb_id, 'full' ),
			'alt' => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
		) : null;

		$author_id = (int) ( $post->post_author ?? 0 );
		$author_obj = $author_id ? get_userdata( $author_id ) : null;

		return array(
			'post_id'        => $post_id,
			'post_type'      => (string) $post->post_type,
			'title'          => (string) $post->post_title,
			'slug'           => (string) $post->post_name,
			'status'         => (string) $post->post_status,
			'content'        => (string) $post->post_content,
			'excerpt'        => (string) $post->post_excerpt,
			'date'           => (string) $post->post_date,
			'modified'       => (string) ( $post->post_modified ?? '' ),
			'parent'         => (int) ( $post->post_parent ?? 0 ),
			'menu_order'     => (int) ( $post->menu_order ?? 0 ),
			'comment_status' => (string) ( $post->comment_status ?? '' ),
			'permalink'      => (string) get_permalink( $post_id ),
			'edit_link'      => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			'author'         => array( 'id' => $author_id, 'name' => $author_obj ? (string) $author_obj->display_name : '' ),
			'terms'          => $terms,
			'meta'           => $meta,
			'featured_image' => $featured,
			'is_elementor'   => 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ),
		);
	}
```

Add a `maybe_unserialize` and `esc_url_raw` stub to `tests/bootstrap.php` if missing:
```php
	if ( ! function_exists( 'maybe_unserialize' ) ) {
		function maybe_unserialize( $value ) { return is_string( $value ) ? ( @unserialize( $value ) !== false || 'b:0;' === $value ? unserialize( $value ) : $value ) : $value; }
	}
	if ( ! function_exists( 'esc_url_raw' ) ) {
		function esc_url_raw( string $url, array $protocols = array() ): string { return $url; }
	}
```
And make the `get_post` stub return a controllable post for get-post tests — REPLACE the existing `get_post` stub body with:
```php
	function get_post( $post = null, string $output = 'OBJECT', string $filter = 'raw' ) {
		if ( is_object( $post ) ) { return $post; }
		$id = absint( $post );
		return $GLOBALS['_wp_posts'][ $id ] ?? null;
	}
```
(If another test relied on `get_post` returning null, the default registry miss still returns null — safe.)

- [ ] **Step 4: Add get-post test** to `ContentToolsTest`:
```php
	/** @test */
	public function test_get_post_returns_shape_and_is_elementor_flag(): void {
		$GLOBALS['_wp_posts'][555] = (object) array(
			'ID' => 555, 'post_type' => 'page', 'post_title' => 'P', 'post_name' => 'p',
			'post_status' => 'publish', 'post_content' => '<p>c</p>', 'post_excerpt' => '',
			'post_date' => '2026-01-01 00:00:00', 'post_modified' => '2026-01-02 00:00:00',
			'post_parent' => 0, 'menu_order' => 0, 'comment_status' => 'open', 'post_author' => 1,
		);
		$out = $this->ability->execute_get_post( array( 'post_id' => 555 ) );
		$this->assertNotWPError( $out );
		$this->assertSame( 555, $out['post_id'] );
		$this->assertSame( 'page', $out['post_type'] );
		$this->assertArrayHasKey( 'is_elementor', $out );
		$this->assertFalse( $out['is_elementor'] );
	}

	/** @test */
	public function test_get_post_not_found(): void {
		$out = $this->ability->execute_get_post( array( 'post_id' => 99999 ) );
		$this->assertWPError( $out, 'post_not_found' );
	}
```

- [ ] **Step 5: Run tests** — `--filter ContentToolsTest` then full suite. Expected: all green.

- [ ] **Step 6: Commit**
```bash
git add includes/abilities/class-content-abilities.php tests/unit/content/ContentToolsTest.php tests/bootstrap.php
git commit -m "feat(content): create-post + get-post + shared write helpers (Task 2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: `update-post` + `delete-post`

**Files:** Modify the class + test.

- [ ] **Step 1: Failing tests** — append:
```php
	/** @test */
	public function test_update_post_merges_and_clears_featured_image(): void {
		$GLOBALS['_wp_posts'][600] = (object) array( 'ID' => 600, 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => 1, 'post_title' => 'old' );
		$out = $this->ability->execute_update_post( array( 'post_id' => 600, 'title' => 'new', 'featured_image' => null ) );
		$this->assertNotWPError( $out );
		$this->assertSame( 600, $out['post_id'] );
		$cleared = array_filter( $GLOBALS['_wp_thumbnail_calls'], fn( $c ) => 0 === $c['thumb'] );
		$this->assertNotEmpty( $cleared, 'featured_image:null should clear the thumbnail' );
	}

	/** @test */
	public function test_update_post_rejects_protected_meta(): void {
		$GLOBALS['_wp_posts'][601] = (object) array( 'ID' => 601, 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => 1 );
		$out = $this->ability->execute_update_post( array( 'post_id' => 601, 'meta' => array( '_edit_lock' => '1' ) ) );
		$this->assertWPError( $out, 'protected_meta' );
	}

	/** @test */
	public function test_delete_post_trashes_by_default(): void {
		$GLOBALS['_wp_posts'][700] = (object) array( 'ID' => 700, 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1 );
		$out = $this->ability->execute_delete_post( array( 'post_id' => 700 ) );
		$this->assertNotWPError( $out );
		$this->assertSame( 'trashed', $out['deleted'] );
		$this->assertContains( 700, $GLOBALS['_wp_trashed_posts'] );
	}

	/** @test */
	public function test_delete_post_force(): void {
		$GLOBALS['_wp_posts'][701] = (object) array( 'ID' => 701, 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1 );
		$out = $this->ability->execute_delete_post( array( 'post_id' => 701, 'force' => true ) );
		$this->assertSame( 'deleted', $out['deleted'] );
		$this->assertContains( 701, $GLOBALS['_wp_deleted_posts'] );
	}
```

- [ ] **Step 2: Run — verify fail.**

- [ ] **Step 3: Implement `update-post` + `delete-post`**

`update-post` (input schema = create-post's properties + required `post_id`, plus `terms_mode`):
```php
	private function register_update_post(): void {
		emcp_tools_register_ability(
			'emcp-tools/update-post',
			array(
				'label'               => __( 'Update Post', 'emcp-tools' ),
				'description'         => __( 'Partial update of a post/page/CPT. Only the fields you pass change. terms_mode controls replace/append; meta upserts the given keys; featured_image:null clears it. Does not touch Elementor data — use the Elementor tools for builder pages.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_post' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'        => array( 'type' => 'integer', 'description' => __( 'The post ID.', 'emcp-tools' ) ),
						'title'          => array( 'type' => 'string' ),
						'content'        => array( 'type' => 'string', 'description' => __( 'post_content — classic HTML or block markup.', 'emcp-tools' ) ),
						'excerpt'        => array( 'type' => 'string' ),
						'status'         => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'pending', 'private', 'future' ) ),
						'slug'           => array( 'type' => 'string' ),
						'author'         => array( 'type' => 'integer' ),
						'date'           => array( 'type' => 'string' ),
						'parent'         => array( 'type' => 'integer' ),
						'menu_order'     => array( 'type' => 'integer' ),
						'comment_status' => array( 'type' => 'string', 'enum' => array( 'open', 'closed' ) ),
						'terms'          => array( 'type' => 'object' ),
						'terms_mode'     => array( 'type' => 'string', 'enum' => array( 'replace', 'append' ), 'description' => __( 'Default: replace.', 'emcp-tools' ) ),
						'meta'           => array( 'type' => 'object' ),
						'featured_image' => array( 'type' => array( 'object', 'null' ) ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'post_id' => array( 'type' => 'integer' ), 'status' => array( 'type' => 'string' ),
					'permalink' => array( 'type' => 'string' ),
					'warnings' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				) ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_update_post( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'post_id is required.', 'emcp-tools' ) );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'emcp-tools' ) );
		}
		if ( ! $this->is_writable_post_type( (string) $post->post_type ) ) {
			return new \WP_Error( 'invalid_post_type', __( 'That post type is not writable here.', 'emcp-tools' ) );
		}

		if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
			$guard = $this->reject_protected_meta( $input['meta'] );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
		}

		$postarr = array( 'ID' => $post_id );
		if ( array_key_exists( 'title', $input ) )          { $postarr['post_title'] = sanitize_text_field( (string) $input['title'] ); }
		if ( array_key_exists( 'content', $input ) )        { $postarr['post_content'] = (string) $input['content']; }
		if ( array_key_exists( 'excerpt', $input ) )        { $postarr['post_excerpt'] = (string) $input['excerpt']; }
		if ( ! empty( $input['slug'] ) )                    { $postarr['post_name'] = sanitize_title( $input['slug'] ); }
		if ( isset( $input['parent'] ) )                    { $postarr['post_parent'] = absint( $input['parent'] ); }
		if ( isset( $input['menu_order'] ) )                { $postarr['menu_order'] = (int) $input['menu_order']; }
		if ( ! empty( $input['date'] ) )                    { $postarr['post_date'] = sanitize_text_field( $input['date'] ); }
		if ( ! empty( $input['comment_status'] ) )          { $postarr['comment_status'] = ( 'open' === $input['comment_status'] ) ? 'open' : 'closed'; }

		if ( ! empty( $input['status'] ) ) {
			$status = sanitize_key( $input['status'] );
			if ( ! in_array( $status, $this->valid_statuses(), true ) ) {
				return new \WP_Error( 'invalid_status', __( 'Invalid status.', 'emcp-tools' ) );
			}
			if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
				return new \WP_Error( 'cannot_publish', __( 'You do not have permission to publish.', 'emcp-tools' ) );
			}
			$postarr['post_status'] = $status;
		}
		if ( isset( $input['author'] ) ) {
			$author = absint( $input['author'] );
			if ( (int) $author !== get_current_user_id() && ! current_user_can( 'edit_others_posts' ) ) {
				return new \WP_Error( 'cannot_set_author', __( 'You cannot assign another author.', 'emcp-tools' ) );
			}
			$postarr['post_author'] = $author;
		}

		$res = wp_update_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$warnings   = array();
		$append     = isset( $input['terms_mode'] ) && 'append' === $input['terms_mode'];
		$this->apply_write_extras( $post_id, $input, $warnings, $append );

		$result = array(
			'post_id'   => $post_id,
			'status'    => (string) ( $postarr['post_status'] ?? $post->post_status ),
			'permalink' => (string) get_permalink( $post_id ),
		);
		if ( $warnings ) {
			$result['warnings'] = $warnings;
		}
		return $result;
	}
```

`delete-post`:
```php
	private function register_delete_post(): void {
		emcp_tools_register_ability(
			'emcp-tools/delete-post',
			array(
				'label'               => __( 'Delete Post', 'emcp-tools' ),
				'description'         => __( 'Deletes a post/page/CPT. By default it is moved to Trash (recoverable); pass force:true to permanently delete. Destructive.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_delete_post' ),
				'permission_callback' => array( $this, 'check_delete_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => __( 'The post ID.', 'emcp-tools' ) ),
						'force'   => array( 'type' => 'boolean', 'description' => __( 'Permanently delete instead of trashing. Default: false.', 'emcp-tools' ) ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'success' => array( 'type' => 'boolean' ), 'post_id' => array( 'type' => 'integer' ),
					'deleted' => array( 'type' => 'string' ),
				) ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_delete_post( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'post_id is required.', 'emcp-tools' ) );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'emcp-tools' ) );
		}
		$force = ! empty( $input['force'] );
		if ( $force ) {
			$res = wp_delete_post( $post_id, true );
			return array( 'success' => (bool) $res, 'post_id' => $post_id, 'deleted' => 'deleted' );
		}
		$res = wp_trash_post( $post_id );
		return array( 'success' => (bool) $res, 'post_id' => $post_id, 'deleted' => 'trashed' );
	}
```

- [ ] **Step 4: Run tests** — filtered then full suite, all green.

- [ ] **Step 5: Commit**
```bash
git add includes/abilities/class-content-abilities.php tests/unit/content/ContentToolsTest.php
git commit -m "feat(content): update-post + delete-post (Task 3)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: `list-posts` + `set-post-terms`

**Files:** Modify the class + test. Needs a controllable `WP_Query` stub.

- [ ] **Step 1: Add a `WP_Query` stub to `tests/bootstrap.php`** (guarded). It reads a global result set:
```php
	if ( ! class_exists( 'WP_Query' ) ) {
		class WP_Query {
			public $posts = array();
			public $found_posts = 0;
			public $max_num_pages = 0;
			public function __construct( $args = array() ) {
				$set = $GLOBALS['_wp_query_result'] ?? array();
				$this->posts        = $set['posts'] ?? array();
				$this->found_posts  = $set['found'] ?? count( $this->posts );
				$per                = max( 1, (int) ( $args['posts_per_page'] ?? 20 ) );
				$this->max_num_pages = (int) ceil( $this->found_posts / $per );
				$GLOBALS['_wp_query_args'] = $args;
			}
		}
	}
```
(If `WP_Query` is already defined elsewhere in the bootstrap, extend that one instead to honor `$GLOBALS['_wp_query_result']` — grep first: `grep -n "class WP_Query" tests/bootstrap.php`.)

- [ ] **Step 2: Failing tests** — append:
```php
	/** @test */
	public function test_list_posts_compact_shape_and_paging(): void {
		$GLOBALS['_wp_query_result'] = array(
			'posts' => array(
				(object) array( 'ID' => 1, 'post_type' => 'post', 'post_title' => 'A', 'post_name' => 'a', 'post_status' => 'publish', 'post_date' => '2026-01-01 00:00:00', 'post_modified' => '2026-01-01 00:00:00', 'post_author' => 1 ),
				(object) array( 'ID' => 2, 'post_type' => 'post', 'post_title' => 'B', 'post_name' => 'b', 'post_status' => 'draft', 'post_date' => '2026-01-02 00:00:00', 'post_modified' => '2026-01-02 00:00:00', 'post_author' => 1 ),
			),
			'found' => 2,
		);
		$out = $this->ability->execute_list_posts( array( 'per_page' => 20 ) );
		$this->assertResultHasKey( $out, 'posts' );
		$this->assertSame( 2, $out['total'] );
		$this->assertCount( 2, $out['posts'] );
		$this->assertArrayHasKey( 'is_elementor', $out['posts'][0] );
		$this->assertArrayNotHasKey( 'content', $out['posts'][0], 'list rows must be compact (no content body)' );
	}

	/** @test */
	public function test_set_post_terms_replace(): void {
		$GLOBALS['_wp_posts'][800] = (object) array( 'ID' => 800, 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1 );
		$out = $this->ability->execute_set_post_terms( array( 'post_id' => 800, 'taxonomy' => 'category', 'terms' => array( 3, 4 ), 'mode' => 'replace' ) );
		$this->assertNotWPError( $out );
		$this->assertSame( 'category', $out['taxonomy'] );
		$call = end( $GLOBALS['_wp_term_calls'] );
		$this->assertFalse( $call['append'], 'replace mode → append=false' );
	}

	/** @test */
	public function test_set_post_terms_append(): void {
		$GLOBALS['_wp_posts'][801] = (object) array( 'ID' => 801, 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1 );
		$this->ability->execute_set_post_terms( array( 'post_id' => 801, 'taxonomy' => 'post_tag', 'terms' => array( 9 ), 'mode' => 'append' ) );
		$call = end( $GLOBALS['_wp_term_calls'] );
		$this->assertTrue( $call['append'], 'append mode → append=true' );
	}
```

- [ ] **Step 3: Run — verify fail.**

- [ ] **Step 4: Implement `list-posts` + `set-post-terms`**

`list-posts`:
```php
	private function register_list_posts(): void {
		emcp_tools_register_ability(
			'emcp-tools/list-posts',
			array(
				'label'               => __( 'List Posts', 'emcp-tools' ),
				'description'         => __( 'Lists/searches posts, pages, or any CPT. Filter by type, status, search text, taxonomy term, author, or parent; paginated. Returns compact rows (no content body) — call get-post for the full content. The is_elementor flag flags builder pages.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_posts' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => array( 'string', 'array' ), 'description' => __( 'Type(s) to query. Default: post.', 'emcp-tools' ) ),
						'status'    => array( 'type' => array( 'string', 'array' ), 'description' => __( 'Status(es). Default: any.', 'emcp-tools' ) ),
						'search'    => array( 'type' => 'string' ),
						'taxonomy'  => array( 'type' => 'object', 'description' => __( 'Map of taxonomy → array of term IDs or slugs (AND).', 'emcp-tools' ) ),
						'author'    => array( 'type' => 'integer' ),
						'parent'    => array( 'type' => 'integer' ),
						'per_page'  => array( 'type' => 'integer', 'description' => __( '1-100. Default: 20.', 'emcp-tools' ) ),
						'page'      => array( 'type' => 'integer', 'description' => __( 'Default: 1.', 'emcp-tools' ) ),
						'orderby'   => array( 'type' => 'string', 'enum' => array( 'date', 'modified', 'title', 'menu_order', 'ID' ) ),
						'order'     => array( 'type' => 'string', 'enum' => array( 'ASC', 'DESC' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'posts' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					'total' => array( 'type' => 'integer' ), 'pages' => array( 'type' => 'integer' ),
					'page' => array( 'type' => 'integer' ),
				) ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array
	 */
	public function execute_list_posts( $input ): array {
		$per_page = max( 1, min( 100, absint( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, absint( $input['page'] ?? 1 ) );
		$orderby  = in_array( $input['orderby'] ?? '', array( 'date', 'modified', 'title', 'menu_order', 'ID' ), true ) ? $input['orderby'] : 'date';
		$order    = ( isset( $input['order'] ) && 'ASC' === strtoupper( (string) $input['order'] ) ) ? 'ASC' : 'DESC';

		$args = array(
			'post_type'      => $input['post_type'] ?? 'post',
			'post_status'    => $input['status'] ?? 'any',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $orderby,
			'order'          => $order,
		);
		if ( ! empty( $input['search'] ) )  { $args['s'] = sanitize_text_field( $input['search'] ); }
		if ( ! empty( $input['author'] ) )  { $args['author'] = absint( $input['author'] ); }
		if ( isset( $input['parent'] ) )    { $args['post_parent'] = absint( $input['parent'] ); }
		if ( ! empty( $input['taxonomy'] ) && is_array( $input['taxonomy'] ) ) {
			$tax_query = array( 'relation' => 'AND' );
			foreach ( $input['taxonomy'] as $tax => $terms ) {
				$terms = array_values( (array) $terms );
				$field = ( ! empty( $terms ) && is_numeric( $terms[0] ) ) ? 'term_id' : 'slug';
				$tax_query[] = array( 'taxonomy' => sanitize_key( $tax ), 'field' => $field, 'terms' => $terms );
			}
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$query = new \WP_Query( $args );
		$rows  = array();
		foreach ( $query->posts as $p ) {
			$rows[] = array(
				'post_id'      => (int) $p->ID,
				'post_type'    => (string) $p->post_type,
				'title'        => (string) $p->post_title,
				'slug'         => (string) $p->post_name,
				'status'       => (string) $p->post_status,
				'date'         => (string) $p->post_date,
				'modified'     => (string) ( $p->post_modified ?? '' ),
				'author_id'    => (int) ( $p->post_author ?? 0 ),
				'permalink'    => (string) get_permalink( (int) $p->ID ),
				'is_elementor' => 'builder' === get_post_meta( (int) $p->ID, '_elementor_edit_mode', true ),
			);
		}
		return array(
			'posts' => $rows,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
			'page'  => $page,
		);
	}
```

`set-post-terms`:
```php
	private function register_set_post_terms(): void {
		emcp_tools_register_ability(
			'emcp-tools/set-post-terms',
			array(
				'label'               => __( 'Set Post Terms', 'emcp-tools' ),
				'description'         => __( 'Assigns taxonomy terms (categories, tags, custom) to a post. mode controls replace (default), append, or remove. Terms may be IDs or names; missing names are created when create_missing is true and you can manage that taxonomy.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_set_post_terms' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'        => array( 'type' => 'integer', 'description' => __( 'The post ID.', 'emcp-tools' ) ),
						'taxonomy'       => array( 'type' => 'string', 'description' => __( 'Taxonomy name (e.g. category, post_tag).', 'emcp-tools' ) ),
						'terms'          => array( 'type' => 'array', 'items' => array( 'type' => array( 'integer', 'string' ) ), 'description' => __( 'Term IDs or names.', 'emcp-tools' ) ),
						'mode'           => array( 'type' => 'string', 'enum' => array( 'replace', 'append', 'remove' ), 'description' => __( 'Default: replace.', 'emcp-tools' ) ),
						'create_missing' => array( 'type' => 'boolean', 'description' => __( 'Create term names that do not exist. Default: true.', 'emcp-tools' ) ),
					),
					'required'   => array( 'post_id', 'taxonomy', 'terms' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'post_id' => array( 'type' => 'integer' ), 'taxonomy' => array( 'type' => 'string' ),
					'terms' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					'created' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				) ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_set_post_terms( $input ) {
		$post_id  = absint( $input['post_id'] ?? 0 );
		$taxonomy = sanitize_key( $input['taxonomy'] ?? '' );
		if ( ! $post_id || '' === $taxonomy ) {
			return new \WP_Error( 'missing_params', __( 'post_id and taxonomy are required.', 'emcp-tools' ) );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'emcp-tools' ) );
		}
		$mode  = in_array( $input['mode'] ?? 'replace', array( 'replace', 'append', 'remove' ), true ) ? $input['mode'] : 'replace';
		$terms = array_values( (array) ( $input['terms'] ?? array() ) );

		if ( 'remove' === $mode ) {
			// wp_remove_object_terms removes; emulate via the helper for the stub.
			$res = function_exists( 'wp_remove_object_terms' ) ? wp_remove_object_terms( $post_id, $terms, $taxonomy ) : true;
		} else {
			$res = wp_set_object_terms( $post_id, $terms, $taxonomy, 'append' === $mode );
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		// Report current terms after the change.
		$current = array();
		$tobjs   = get_the_terms( $post, $taxonomy );
		if ( is_array( $tobjs ) ) {
			foreach ( $tobjs as $t ) {
				$current[] = array( 'term_id' => (int) $t->term_id, 'name' => (string) $t->name, 'slug' => (string) $t->slug );
			}
		}
		return array(
			'post_id'  => $post_id,
			'taxonomy' => $taxonomy,
			'terms'    => $current,
			'created'  => array(),
		);
	}
```
Add a `wp_remove_object_terms` stub to `tests/bootstrap.php` (guarded): records like `wp_set_object_terms` and returns `true`.

> Note on `create_missing`: `wp_set_object_terms` already creates missing **names** in a non-hierarchical taxonomy, and accepts existing IDs. For hierarchical taxonomies a name that doesn't exist is created too. The `created` array is best-effort; leaving it empty is acceptable for v1 (the spec lists it as informational). Do not over-build term-creation tracking.

- [ ] **Step 5: Run tests** — filtered then full suite, all green.

- [ ] **Step 6: Commit**
```bash
git add includes/abilities/class-content-abilities.php tests/unit/content/ContentToolsTest.php tests/bootstrap.php
git commit -m "feat(content): list-posts + set-post-terms (Task 4)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Capability tests

**Files:** Create `tests/unit/capabilities/ContentCapabilityTest.php`.

- [ ] **Step 1: Write the test** (4-space indentation to match the other capability tests):
```php
<?php
/**
 * Capability gating for the WordPress Content tools.
 * @group capabilities
 * @group content
 * @package EMCP_Tools\Tests\Capabilities
 */
namespace EMCP_Tools\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class ContentCapabilityTest extends Ability_Test_Case {
    private \EMCP_Tools_Content_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new \EMCP_Tools_Content_Abilities();
        $this->ability->register();
    }

    /** @test */
    public function test_read_denied_without_edit_posts(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_read_permission());
    }

    /** @test */
    public function test_read_allowed_with_edit_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertTrue($this->ability->check_read_permission());
    }

    /** @test */
    public function test_edit_denied_on_unowned_post(): void {
        // edit_posts yes, but edit_post (per-post) no.
        $this->allow_caps('edit_posts');
        $this->assertFalse($this->ability->check_edit_permission(['post_id' => 42]));
    }

    /** @test */
    public function test_edit_allowed_with_both_caps(): void {
        $this->allow_caps('edit_posts', 'edit_post');
        $this->assertTrue($this->ability->check_edit_permission(['post_id' => 42]));
    }

    /** @test */
    public function test_delete_denied_without_delete_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertFalse($this->ability->check_delete_permission(['post_id' => 42]));
    }

    /** @test */
    public function test_delete_allowed_with_both_caps(): void {
        $this->allow_caps('delete_posts', 'delete_post');
        $this->assertTrue($this->ability->check_delete_permission(['post_id' => 42]));
    }
}
```
> The `current_user_can` stub treats `edit_post`/`delete_post` (the per-post meta caps) as plain strings in `$GLOBALS['_caps']`, so granting `'edit_post'` makes the ownership check pass. That matches how the existing WidgetCapabilityTest exercises ownership.

- [ ] **Step 2: Run** — `--filter ContentCapabilityTest` then full suite. Green.

- [ ] **Step 3: Commit**
```bash
git add tests/unit/capabilities/ContentCapabilityTest.php
git commit -m "test(content): capability gating per tool (Task 5)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Wiring — core abilities on the server, essentials, admin category

**Files:** Modify `includes/class-plugin.php`, `includes/admin/class-admin.php`.

- [ ] **Step 1: Surface the 3 core read-only abilities on our server**

In `includes/class-plugin.php` `register_mcp_server()`, the tools array passed to `create_server()` is `$this->ability_names`. Append the core read abilities right before the `create_server` call (only if they exist in the registry, so we never pass an unregistered name):
```php
		// Also expose WordPress core's read-only context abilities (site/user/
		// environment info) on our server — registered by core, free to surface.
		$tools = $this->ability_names;
		foreach ( array( 'core/get-site-info', 'core/get-user-info', 'core/get-environment-info' ) as $core_ability ) {
			if ( function_exists( 'wp_get_ability' ) && wp_get_ability( $core_ability ) && ! in_array( $core_ability, $tools, true ) ) {
				$tools[] = $core_ability;
			}
		}
```
Then change the `$this->ability_names` argument in the `create_server(...)` call to `$tools`. (Find the line `$this->ability_names,                                     // tools` and replace with `$tools,                                                   // tools`.)

- [ ] **Step 2: Add content tools to low-tools essentials**

In `includes/class-plugin.php` `get_essential_tool_slugs()`, add a block (before the closing `);`):
```php
			// WordPress content (8) — general post/page/CPT management.
			'emcp-tools/list-post-types',
			'emcp-tools/list-taxonomies',
			'emcp-tools/create-post',
			'emcp-tools/get-post',
			'emcp-tools/update-post',
			'emcp-tools/list-posts',
			'emcp-tools/delete-post',
			'emcp-tools/set-post-terms',
```

- [ ] **Step 3: Add the admin "WordPress Content" category**

In `includes/admin/class-admin.php` `get_tool_catalog()`, add a new category (place it after the `query` category for logical grouping):
```php
			'wp_content'       => array(
				'label' => __( 'WordPress Content', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-post-types' => array(
						'label'       => __( 'List Post Types', 'emcp-tools' ),
						'description' => __( 'Lists registered post types (posts, pages, CPTs).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/list-taxonomies' => array(
						'label'       => __( 'List Taxonomies', 'emcp-tools' ),
						'description' => __( 'Lists taxonomies and optionally their terms.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/create-post'     => array(
						'label'       => __( 'Create Post', 'emcp-tools' ),
						'description' => __( 'Creates a post/page/CPT with content, terms, meta, featured image.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/get-post'        => array(
						'label'       => __( 'Get Post', 'emcp-tools' ),
						'description' => __( 'Returns a post\'s content, terms, meta, and featured image.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/update-post'     => array(
						'label'       => __( 'Update Post', 'emcp-tools' ),
						'description' => __( 'Partial update of a post/page/CPT.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/list-posts'      => array(
						'label'       => __( 'List Posts', 'emcp-tools' ),
						'description' => __( 'Lists/searches posts, pages, or any CPT (compact).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/delete-post'     => array(
						'label'       => __( 'Delete Post', 'emcp-tools' ),
						'description' => __( 'Trashes (or force-deletes) a post.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/set-post-terms'  => array(
						'label'       => __( 'Set Post Terms', 'emcp-tools' ),
						'description' => __( 'Assigns category/tag/custom terms to a post.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
```

- [ ] **Step 4: Run the full suite** — green. The `F019F020AdminTest` drift guard cross-checks catalog slugs against the registry under WP_DEBUG; the 8 content slugs are all registered, so it passes.

- [ ] **Step 5: Commit**
```bash
git add includes/class-plugin.php includes/admin/class-admin.php
git commit -m "feat(content): surface core read abilities, essentials, admin category (Task 6)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Docs + CHANGELOG + version

**Files:** `CLAUDE.md`, `README.md`, `readme.txt`, `CHANGELOG.md`, `emcp-tools.php`.

- [ ] **Step 1: Version → 3.1.0** in `emcp-tools.php` (header `Version:` and `EMCP_TOOLS_VERSION`). This is a feature release (new tools, no breaking change), so minor bump from 3.0.0.

- [ ] **Step 2: CLAUDE.md** — add a "WordPress Content (beyond Elementor)" subsection to the tools list documenting the 8 tools + the 3 surfaced `core/*` abilities; update the tool-count note (free Elementor now ~44 + 8 content + 3 core ≈ 55; recompute and mark "to be confirmed against live tools/list in Task 8"); add `class-content-abilities.php` to the directory structure; note this is the first "beyond Elementor" domain.

- [ ] **Step 3: README.md + readme.txt** — add a "WordPress Content" feature bullet (create/manage posts, pages, CPTs, taxonomies, custom fields, featured images via MCP); bump the count ranges; note the broadened scope.

- [ ] **Step 4: CHANGELOG.md** — prepend:
```markdown
## [3.1.0]

- Added: **WordPress Content tools — the first step beyond Elementor.** Eight new MCP tools to manage general WordPress content from an AI agent: `list-post-types`, `list-taxonomies`, `create-post`, `get-post`, `update-post`, `list-posts`, `delete-post`, and `set-post-terms`. Create and edit posts, pages, and any custom post type — title, content (classic HTML or block markup), status, slug, author, taxonomy terms, custom fields, and featured image — without touching Elementor data. Capability-gated and enabled by default; `delete-post` trashes by default (pass `force` to permanently delete).
- Added: WordPress core's read-only context abilities (`core/get-site-info`, `core/get-user-info`, `core/get-environment-info`) are now surfaced on the EMCP server too.
```

- [ ] **Step 5: Run full suite** (docs shouldn't affect it) — green. Commit:
```bash
git add emcp-tools.php CLAUDE.md README.md readme.txt CHANGELOG.md
git commit -m "docs: v3.1.0 WordPress Content tools (Task 7)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: End-to-end verification (PHP + live MCP + browser)

**Files:** none (verification). This task fulfills the user's request for thorough testing.

- [ ] **Step 1: Full PHP suite, both configs**
```
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit
```
Expected: 0 failures both. Record counts.

- [ ] **Step 2: Live MCP `tools/list` on the server** — confirm the 8 content tools + 3 core tools appear, all named `emcp-tools-*` / `core-*`. Pipe initialize + tools/list into:
```
/f/.../php.exe /c/wp-cli/wp-cli.phar mcp-adapter serve --server=emcp-tools-server --user=admin --path=f:/laragon/www/msrplugins
```
Write output to a Windows-absolute path (bash `/tmp` ≠ PHP `/tmp`). Confirm: `emcp-tools-create-post`, `emcp-tools-get-post`, …, and `core-get-site-info` present.

- [ ] **Step 3: Live round-trip via MCP `tools/call`** (the real proof):
  1. `emcp-tools-create-post` → `{post_type:"post", title:"EMCP content test", content:"<p>hello</p>", status:"draft"}` → capture `post_id`.
  2. `emcp-tools-set-post-terms` → assign a category.
  3. `emcp-tools-get-post` → confirm title/content/terms, and `is_elementor:false`.
  4. `emcp-tools-update-post` → set `status:"publish"`.
  5. `emcp-tools-list-posts` → `{search:"EMCP content test"}` → confirm it appears.
  6. `emcp-tools-delete-post` → trash it (then optionally `force:true` to clean up).
  Verify via `wp post get <id> --field=post_status` between steps. Confirm `_elementor_data` is absent on the post (`wp post meta get <id> _elementor_data` → empty).

- [ ] **Step 4: Browser check (admin UI)** — load `wp-admin` → **EMCP Tools → Tools** tab; confirm the new **"WordPress Content"** category renders with all 8 toggles, correct badges (read-only on discovery/get/list, destructive on delete), all enabled by default. Then **EMCP Tools → Connection** tab loads without error (the server route/config still generates). Use the Playwright MCP or a manual screenshot. Confirm no PHP notices in the page or debug.log.

- [ ] **Step 5: Reconcile docs counts** — if Step 2's live count differs from the Task-7 estimate, correct CLAUDE.md/README/readme.txt and commit:
```bash
git add CLAUDE.md README.md readme.txt
git commit -m "docs: reconcile content tool counts with live tools/list (Task 8)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 6: Clean up** any temp page created in Step 3 (`wp post delete <id> --force`) and temp files.

---

## Self-review notes (plan author)

- **Spec coverage:** all 8 tools (Tasks 1–4), guardrails — capability matrix (Task 5 + permission_callbacks), protected-meta allowlist (`reject_protected_meta`, Task 2), internal-post-type exclusion (`is_writable_post_type`, Task 2), Elementor coexistence (`is_elementor` in get/list, never writes `_elementor_data`), featured-image sideload (`apply_write_extras`), status-transition gating (Tasks 2–3) — all covered. Discovery (Task 1). Core-ability surfacing + essentials + admin category (Task 6). Docs (Task 7). PHP + live MCP + browser verification (Task 8) — covers the user's "thorough testing with php and in browser + actual mcp tool calling."
- **Type/name consistency:** method names (`execute_*`, `check_*_permission`, `apply_write_extras`, `is_writable_post_type`, `reject_protected_meta`, `format_post`, `valid_statuses`, `internal_post_types`) are used identically across tasks. Tool slugs match the spec's 8.
- **Out of scope (per spec):** settings/plugins/themes/users, comments/menus, block parsing, bulk ops — none added.
- **Known pragmatic call flagged in-task:** `set-post-terms` `created[]` is best-effort/empty for v1 (spec lists it informational) — not over-built.
