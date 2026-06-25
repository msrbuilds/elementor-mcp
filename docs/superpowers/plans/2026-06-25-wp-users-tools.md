# WordPress Users Tools Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 4 MCP tools (`list-users`, `get-user`, `create-user`, `update-user`) for safe user management — reads enabled, the two writes disabled-by-default — with a strict privilege guard (no admin-grade role creation, admins untouchable, no role/password changes, passwords never returned).

**Architecture:** A new `EMCP_Tools_User_Abilities` group class (accumulator pattern) built on WP core user functions, with a privilege-guard helper (`role_has_admin_caps`/`user_has_admin_caps`) keyed off a protected-capability set. Wired into bootstrap/registrar/essentials/admin; `create-user`/`update-user` seeded disabled-by-default (DEFAULTS_VERSION 7→8).

**Tech Stack:** PHP 8.2, WordPress core (`WP_User_Query`, `get_userdata`, `wp_insert_user`, `wp_update_user`, `wp_generate_password`, `wp_send_new_user_notifications`, `get_role`, `user_can`, `count_user_posts`), the `emcp_tools_register_ability()` shim, PHPUnit function-stub harness.

**Spec:** `docs/superpowers/specs/2026-06-25-wp-users-tools-design.md`

---

## File Structure

| File | Responsibility |
|---|---|
| `includes/abilities/class-user-abilities.php` | NEW — `EMCP_Tools_User_Abilities`: 4 tools + privilege-guard helpers + permission callbacks. |
| `tests/bootstrap.php` | MODIFY — user-function stubs + `WP_User_Query` stub; adjust `get_userdata` to return `false` for unknown IDs. |
| `tests/unit/users/UserToolsTest.php` | NEW — execute-path tests. |
| `tests/unit/capabilities/UserCapabilityTest.php` | NEW — per-tool capability gating. |
| `includes/class-bootstrap.php` | MODIFY — `require_once` the class. |
| `includes/abilities/class-ability-registrar.php` | MODIFY — register the group. |
| `includes/class-plugin.php` | MODIFY — essentials += `list-users`, `get-user`. |
| `includes/admin/class-admin.php` | MODIFY — "Users" category; `DEFAULTS_VERSION` 7→8; `user_write_tool_slugs()`; v8 seed. |
| `phpunit.xml` | MODIFY — add a `Users` testsuite. |
| `CHANGELOG.md`, `README.md`, `readme.txt`, `CLAUDE.md` | MODIFY — fold into the single v3.0.0 entry. |

**Conventions:** accumulator pattern (`private $ability_names = array();` appended at the top of each `register_*`), `emcp_tools_register_ability()`, `'category' => 'emcp-tools'`, text domain `'emcp-tools'`, tabs, `\WP_Error` (code+message), meta annotations + `show_in_rest`.

---

## Task 1: Test harness — user-function stubs

**Files:** Modify `tests/bootstrap.php`

- [ ] **Step 1: Check for pre-existing stubs**

Run: `grep -n "function wp_insert_user\|function wp_update_user\|class WP_User_Query\|function get_role\|function user_can\|function wp_generate_password\|function count_user_posts\|function wp_send_new_user_notifications\|function sanitize_user\|function is_email" tests/bootstrap.php`
Expected: no matches (only `get_userdata` and `current_user_can` already exist).

- [ ] **Step 2: Adjust the `get_userdata` stub to return false for unknown IDs**

Replace the existing `get_userdata` block:
```php
	if ( ! function_exists( 'get_userdata' ) ) {
		function get_userdata( int $user_id ) {
			if ( isset( $GLOBALS['_wp_users'] ) && array_key_exists( $user_id, $GLOBALS['_wp_users'] ) ) {
				return $GLOBALS['_wp_users'][ $user_id ];
			}
			return (object) array( 'ID' => $user_id, 'display_name' => 'User ' . $user_id );
		}
	}
```
with:
```php
	if ( ! function_exists( 'get_userdata' ) ) {
		function get_userdata( int $user_id ) {
			if ( isset( $GLOBALS['_wp_users'] ) && array_key_exists( $user_id, $GLOBALS['_wp_users'] ) ) {
				return $GLOBALS['_wp_users'][ $user_id ];
			}
			return false;
		}
	}
```

- [ ] **Step 3: Add the user-function + WP_User_Query stubs**

In the global `namespace { … }` block, add the functions near `get_userdata`:
```php
	if ( ! function_exists( 'get_user_by' ) ) {
		function get_user_by( $field, $value ) {
			if ( 'id' === $field || 'ID' === $field ) {
				return get_userdata( (int) $value );
			}
			foreach ( $GLOBALS['_wp_users'] ?? array() as $u ) {
				if ( ( 'email' === $field && ( $u->user_email ?? '' ) === $value ) || ( 'login' === $field && ( $u->user_login ?? '' ) === $value ) ) {
					return $u;
				}
			}
			return false;
		}
	}
	if ( ! function_exists( 'wp_insert_user' ) ) {
		function wp_insert_user( $userdata ) {
			if ( ! empty( $GLOBALS['_wp_insert_user_error'] ) ) {
				return new \WP_Error( 'insert_failed', $GLOBALS['_wp_insert_user_error'] );
			}
			$GLOBALS['_wp_inserted_users'][] = (array) $userdata;
			return $GLOBALS['_wp_next_user_id'] ?? 501;
		}
	}
	if ( ! function_exists( 'wp_update_user' ) ) {
		function wp_update_user( $userdata ) {
			if ( ! empty( $GLOBALS['_wp_update_user_error'] ) ) {
				return new \WP_Error( 'update_failed', $GLOBALS['_wp_update_user_error'] );
			}
			$arr = (array) $userdata;
			$GLOBALS['_wp_updated_users'][] = $arr;
			return (int) ( $arr['ID'] ?? 0 );
		}
	}
	if ( ! function_exists( 'wp_generate_password' ) ) {
		function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
			return 'GENERATED-PASSWORD-' . $length;
		}
	}
	if ( ! function_exists( 'wp_send_new_user_notifications' ) ) {
		function wp_send_new_user_notifications( $user_id, $notify = 'both' ) {
			$GLOBALS['_wp_new_user_notifications'][] = array( 'id' => (int) $user_id, 'notify' => $notify );
		}
	}
	if ( ! function_exists( 'get_role' ) ) {
		function get_role( $role ) {
			$map = $GLOBALS['_wp_roles'] ?? array(
				'subscriber'    => array(),
				'contributor'   => array( 'edit_posts' => true ),
				'author'        => array( 'publish_posts' => true ),
				'editor'        => array( 'edit_others_posts' => true ),
				'administrator' => array( 'manage_options' => true, 'edit_users' => true, 'promote_users' => true, 'delete_users' => true ),
			);
			if ( ! array_key_exists( $role, $map ) ) {
				return null;
			}
			return (object) array( 'name' => $role, 'capabilities' => $map[ $role ] );
		}
	}
	if ( ! function_exists( 'user_can' ) ) {
		function user_can( $user, $cap ) {
			$id   = is_object( $user ) ? (int) ( $user->ID ?? 0 ) : (int) $user;
			$caps = $GLOBALS['_wp_user_caps'][ $id ] ?? array();
			return in_array( $cap, $caps, true );
		}
	}
	if ( ! function_exists( 'count_user_posts' ) ) {
		function count_user_posts( $user_id, $post_type = 'post', $public_only = false ) {
			return (int) ( $GLOBALS['_wp_user_post_counts'][ (int) $user_id ] ?? 0 );
		}
	}
	if ( ! function_exists( 'sanitize_user' ) ) {
		function sanitize_user( $username, $strict = false ) {
			return preg_replace( '/[^a-zA-Z0-9._\-@ ]/', '', (string) $username );
		}
	}
	if ( ! function_exists( 'is_email' ) ) {
		function is_email( $email ) {
			return ( is_string( $email ) && false !== strpos( $email, '@' ) ) ? $email : false;
		}
	}
	if ( ! function_exists( 'sanitize_email' ) ) {
		function sanitize_email( $email ) {
			return trim( (string) $email );
		}
	}
```

And add the `WP_User_Query` class in the global namespace block (alongside `WP_Query`/`WP_Error`):
```php
	if ( ! class_exists( 'WP_User_Query' ) ) {
		class WP_User_Query {
			public $results; public $total;
			public function __construct( $args = array() ) {
				$this->results = $GLOBALS['_wp_user_query_result'] ?? array();
				$this->total   = $GLOBALS['_wp_user_query_total'] ?? count( $this->results );
			}
			public function get_results() { return $this->results; }
			public function get_total() { return (int) $this->total; }
		}
	}
```

> If `sanitize_textarea_field` is needed by `update-user` and isn't stubbed, add a trivial stub `function sanitize_textarea_field( $s ) { return is_string( $s ) ? trim( $s ) : ''; }` (it was removed in an earlier domain's cleanup — re-add if `grep -n "function sanitize_textarea_field" tests/bootstrap.php` shows none).

- [ ] **Step 4: Verify the harness still loads (no regression)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist 2>&1 | tail -3`
Expected: `OK (570 tests, …)` — unchanged. (The `get_userdata`→false change is safe: content `format_post` and media `get-media` both handle a falsy author via `$author_obj ? … : ''`.) If any test regresses, report it.

- [ ] **Step 5: Commit**

```bash
git add tests/bootstrap.php
git commit -m "test: user-function + WP_User_Query stubs for the Users tools"
```

---

## Task 2: list-users + get-user (reads)

**Files:** Create `includes/abilities/class-user-abilities.php`; Create `tests/unit/users/UserToolsTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/unit/users/UserToolsTest.php`:

```php
<?php
/**
 * Execute-path tests for the WordPress Users tools.
 * @group users
 * @package EMCP_Tools\Tests\Users
 */
namespace EMCP_Tools\Tests\Users;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class UserToolsTest extends Ability_Test_Case {
	private \EMCP_Tools_User_Abilities $ability;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_users'] = array(
			9 => (object) array( 'ID' => 9, 'user_login' => 'jane', 'display_name' => 'Jane Doe', 'user_email' => 'jane@example.com', 'roles' => array( 'author' ), 'first_name' => 'Jane', 'last_name' => 'Doe', 'nickname' => 'jane', 'user_url' => 'https://jane.example', 'description' => 'Writer', 'user_registered' => '2026-01-01 00:00:00' ),
			1 => (object) array( 'ID' => 1, 'user_login' => 'admin', 'display_name' => 'Admin', 'user_email' => 'admin@example.com', 'roles' => array( 'administrator' ), 'user_registered' => '2025-01-01 00:00:00' ),
		);
		$GLOBALS['_wp_user_caps'] = array( 1 => array( 'manage_options', 'edit_users', 'promote_users', 'delete_users' ), 9 => array( 'edit_posts', 'publish_posts' ) );
		$GLOBALS['_wp_user_query_result'] = array( $GLOBALS['_wp_users'][9], $GLOBALS['_wp_users'][1] );
		$GLOBALS['_wp_user_query_total']  = 2;
		$GLOBALS['_wp_inserted_users']    = array();
		$GLOBALS['_wp_updated_users']     = array();
		$GLOBALS['_wp_new_user_notifications'] = array();
		$this->ability = new \EMCP_Tools_User_Abilities();
		$this->ability->register();
	}

	/** @test */
	public function test_registers_four_tools(): void {
		$names = $this->ability->get_ability_names();
		foreach ( array( 'list-users', 'get-user', 'create-user', 'update-user' ) as $slug ) {
			$this->assertContains( 'emcp-tools/' . $slug, $names );
		}
		$this->assertCount( 4, $names );
	}

	/** @test */
	public function test_list_users_rows(): void {
		$out = $this->ability->execute_list_users( array() );
		$this->assertResultHasKey( $out, 'users' );
		$rows = array();
		foreach ( $out['users'] as $r ) { $rows[ $r['id'] ] = $r; }
		$this->assertSame( 'jane', $rows[9]['username'] );
		$this->assertSame( 'jane@example.com', $rows[9]['email'] );
		$this->assertContains( 'author', $rows[9]['roles'] );
		$this->assertSame( 2, $out['total'] );
	}

	/** @test */
	public function test_list_users_never_leaks_secrets(): void {
		$out  = $this->ability->execute_list_users( array() );
		$json = json_encode( $out );
		$this->assertStringNotContainsStringIgnoringCase( 'user_pass', $json );
		$this->assertStringNotContainsStringIgnoringCase( 'password', $json );
	}

	/** @test */
	public function test_get_user_detail_and_is_admin_flag(): void {
		$jane = $this->ability->execute_get_user( array( 'id' => 9 ) );
		$this->assertNotWPError( $jane );
		$this->assertSame( 'Jane Doe', $jane['display_name'] );
		$this->assertFalse( $jane['is_admin'] );
		$admin = $this->ability->execute_get_user( array( 'id' => 1 ) );
		$this->assertTrue( $admin['is_admin'] );
	}

	/** @test */
	public function test_get_user_not_found(): void {
		$this->assertWPError( $this->ability->execute_get_user( array( 'id' => 999 ) ), 'user_not_found' );
	}

	/** @test */
	public function test_get_user_requires_id(): void {
		$this->assertWPError( $this->ability->execute_get_user( array() ), 'missing_params' );
	}
}
```

- [ ] **Step 2: Run — expect FAIL (class not found)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/users/UserToolsTest.php`

- [ ] **Step 3: Create the class with the accumulator, guard helpers, permissions, and the two read tools**

Create `includes/abilities/class-user-abilities.php`:

```php
<?php
/**
 * WordPress Users management MCP abilities.
 *
 * Four tools — list-users, get-user (read), create-user, update-user (write) —
 * for SAFE user management. The security boundary is the design: no delete tool,
 * no role changes, passwords are auto-generated and emailed (never returned), and
 * a strict privilege guard means agents can only create non-admin accounts and
 * can never edit any user that holds admin-level capabilities.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the WordPress user abilities.
 *
 * @since 3.0.0
 */
class EMCP_Tools_User_Abilities {

	/** @since 3.0.0 @var string[] */
	private $ability_names = array();

	/** @since 3.0.0 @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/** @since 3.0.0 */
	public function register(): void {
		$this->register_list_users();
		$this->register_get_user();
		$this->register_create_user();
		$this->register_update_user();
	}

	// -------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------

	public function can_list(): bool { return current_user_can( 'list_users' ); }
	public function can_create(): bool { return current_user_can( 'create_users' ); }
	public function can_edit(): bool { return current_user_can( 'edit_users' ); }

	// -------------------------------------------------------------------
	// Privilege guard
	// -------------------------------------------------------------------

	/**
	 * Capabilities that mark a role or user as admin-grade (protected).
	 *
	 * @since 3.0.0
	 * @return string[]
	 */
	private static function protected_caps(): array {
		return array( 'manage_options', 'promote_users', 'edit_users', 'delete_users', 'manage_network' );
	}

	/**
	 * Whether a role slug carries any admin-level capability.
	 *
	 * @since 3.0.0
	 * @param string $role_slug
	 * @return bool
	 */
	private function role_has_admin_caps( string $role_slug ): bool {
		$role = function_exists( 'get_role' ) ? get_role( $role_slug ) : null;
		if ( ! $role || empty( $role->capabilities ) || ! is_array( $role->capabilities ) ) {
			return false;
		}
		foreach ( self::protected_caps() as $cap ) {
			if ( ! empty( $role->capabilities[ $cap ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a user holds any admin-level capability (untouchable via MCP).
	 *
	 * @since 3.0.0
	 * @param int $user_id
	 * @return bool
	 */
	private function user_has_admin_caps( int $user_id ): bool {
		foreach ( self::protected_caps() as $cap ) {
			if ( user_can( $user_id, $cap ) ) {
				return true;
			}
		}
		return false;
	}

	// -------------------------------------------------------------------
	// list-users
	// -------------------------------------------------------------------

	private function register_list_users(): void {
		$this->ability_names[] = 'emcp-tools/list-users';
		emcp_tools_register_ability(
			'emcp-tools/list-users',
			array(
				'label'               => __( 'List Users', 'emcp-tools' ),
				'description'         => __( 'Lists WordPress users (admin-only). Filter by role or search text; paginated. Returns id, username, display name, email, roles, registration date, and post count. Never returns passwords or auth data.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_users' ),
				'permission_callback' => array( $this, 'can_list' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'role'     => array( 'type' => 'string', 'description' => __( 'Filter by role slug (e.g. author).', 'emcp-tools' ) ),
						'search'   => array( 'type' => 'string', 'description' => __( 'Search login/email/display name.', 'emcp-tools' ) ),
						'per_page' => array( 'type' => 'integer', 'description' => __( '1-100. Default: 20.', 'emcp-tools' ) ),
						'page'     => array( 'type' => 'integer', 'description' => __( 'Default: 1.', 'emcp-tools' ) ),
						'orderby'  => array( 'type' => 'string', 'enum' => array( 'registered', 'display_name', 'ID' ) ),
						'order'    => array( 'type' => 'string', 'enum' => array( 'ASC', 'DESC' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'users' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					'total' => array( 'type' => 'integer' ), 'pages' => array( 'type' => 'integer' ), 'page' => array( 'type' => 'integer' ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array
	 */
	public function execute_list_users( $input ): array {
		$per_page = max( 1, min( 100, absint( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, absint( $input['page'] ?? 1 ) );
		$orderby  = in_array( $input['orderby'] ?? '', array( 'registered', 'display_name', 'ID' ), true ) ? $input['orderby'] : 'registered';
		$order    = ( isset( $input['order'] ) && 'ASC' === strtoupper( (string) $input['order'] ) ) ? 'ASC' : 'DESC';

		$args = array(
			'number'  => $per_page,
			'paged'   => $page,
			'orderby' => $orderby,
			'order'   => $order,
		);
		if ( ! empty( $input['role'] ) ) {
			$args['role'] = sanitize_key( $input['role'] );
		}
		if ( ! empty( $input['search'] ) ) {
			$args['search']         = '*' . sanitize_text_field( $input['search'] ) . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$query = new \WP_User_Query( $args );
		$rows  = array();
		foreach ( (array) $query->get_results() as $u ) {
			$rows[] = $this->format_user_row( $u );
		}
		$total = (int) $query->get_total();
		return array(
			'users' => $rows,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
			'page'  => $page,
		);
	}

	/**
	 * Compact public row for list-users.
	 *
	 * @param object $u WP_User-like.
	 * @return array
	 */
	private function format_user_row( $u ): array {
		return array(
			'id'           => (int) ( $u->ID ?? 0 ),
			'username'     => (string) ( $u->user_login ?? '' ),
			'display_name' => (string) ( $u->display_name ?? '' ),
			'email'        => (string) ( $u->user_email ?? '' ),
			'roles'        => array_values( (array) ( $u->roles ?? array() ) ),
			'registered'   => (string) ( $u->user_registered ?? '' ),
			'post_count'   => function_exists( 'count_user_posts' ) ? (int) count_user_posts( (int) ( $u->ID ?? 0 ) ) : 0,
		);
	}

	// -------------------------------------------------------------------
	// get-user
	// -------------------------------------------------------------------

	private function register_get_user(): void {
		$this->ability_names[] = 'emcp-tools/get-user';
		emcp_tools_register_ability(
			'emcp-tools/get-user',
			array(
				'label'               => __( 'Get User', 'emcp-tools' ),
				'description'         => __( 'Returns one user\'s detail: username, email, display name, first/last name, URL, description, roles, registration date, post count, and an is_admin flag (true users are off-limits to update-user). Never returns passwords or auth data.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_user' ),
				'permission_callback' => array( $this, 'can_list' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'description' => __( 'User ID.', 'emcp-tools' ) ) ),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'id' => array( 'type' => 'integer' ), 'username' => array( 'type' => 'string' ),
					'email' => array( 'type' => 'string' ), 'display_name' => array( 'type' => 'string' ),
					'first_name' => array( 'type' => 'string' ), 'last_name' => array( 'type' => 'string' ),
					'nickname' => array( 'type' => 'string' ), 'url' => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ), 'roles' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'registered' => array( 'type' => 'string' ), 'post_count' => array( 'type' => 'integer' ),
					'is_admin' => array( 'type' => 'boolean' ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_get_user( $input ) {
		$id = absint( $input['id'] ?? 0 );
		if ( ! $id ) {
			return new \WP_Error( 'missing_params', __( 'A user "id" is required.', 'emcp-tools' ) );
		}
		$u = get_userdata( $id );
		if ( ! $u ) {
			return new \WP_Error( 'user_not_found', __( 'User not found.', 'emcp-tools' ) );
		}
		return array(
			'id'           => (int) $u->ID,
			'username'     => (string) ( $u->user_login ?? '' ),
			'email'        => (string) ( $u->user_email ?? '' ),
			'display_name' => (string) ( $u->display_name ?? '' ),
			'first_name'   => (string) ( $u->first_name ?? '' ),
			'last_name'    => (string) ( $u->last_name ?? '' ),
			'nickname'     => (string) ( $u->nickname ?? '' ),
			'url'          => (string) ( $u->user_url ?? '' ),
			'description'  => (string) ( $u->description ?? '' ),
			'roles'        => array_values( (array) ( $u->roles ?? array() ) ),
			'registered'   => (string) ( $u->user_registered ?? '' ),
			'post_count'   => function_exists( 'count_user_posts' ) ? (int) count_user_posts( $id ) : 0,
			'is_admin'     => $this->user_has_admin_caps( $id ),
		);
	}
}
```

- [ ] **Step 4: Run — expect PASS (read tests) with the registration trick**

`register_create_user()` / `register_update_user()` are referenced by `register()` but not yet implemented → fatal. Add EMPTY stubs `private function register_create_user(): void {}` and `private function register_update_user(): void {}` now, and TEMPORARILY reduce `test_registers_four_tools` to assert only `list-users` + `get-user` are present (drop the count-4). Tasks 3 & 4 restore.

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/users/UserToolsTest.php`
Expected: PASS.

- [ ] **Step 5: Autoloader entry + commit**

Add `'EMCP_Tools_User_Abilities' => 'includes/abilities/class-user-abilities.php'` to the `tests/bootstrap.php` autoloader map (mirror the existing entries).

```bash
git add includes/abilities/class-user-abilities.php tests/unit/users/UserToolsTest.php tests/bootstrap.php
git commit -m "feat(users): list-users + get-user reads with is_admin flag"
```

---

## Task 3: create-user (privilege guard, password, email)

**Files:** Modify `includes/abilities/class-user-abilities.php`; append to `tests/unit/users/UserToolsTest.php`

- [ ] **Step 1: Append failing tests**

```php
	/** @test */
	public function test_create_user_makes_subscriber_and_emails_no_password_returned(): void {
		$GLOBALS['_wp_next_user_id'] = 555;
		$out = $this->ability->execute_create_user( array( 'username' => 'newbie', 'email' => 'newbie@example.com' ) );
		$this->assertNotWPError( $out );
		$this->assertSame( 555, $out['id'] );
		$this->assertSame( 'subscriber', $out['role'] );
		$this->assertArrayNotHasKey( 'password', $out );
		$this->assertArrayNotHasKey( 'generated_password', $out );
		$this->assertStringNotContainsStringIgnoringCase( 'GENERATED-PASSWORD', json_encode( $out ) );
		// notification sent.
		$this->assertNotEmpty( $GLOBALS['_wp_new_user_notifications'] );
		// a password was generated and passed to wp_insert_user.
		$this->assertArrayHasKey( 'user_pass', $GLOBALS['_wp_inserted_users'][0] );
	}

	/** @test */
	public function test_create_user_rejects_admin_role(): void {
		$out = $this->ability->execute_create_user( array( 'username' => 'evil', 'email' => 'evil@example.com', 'role' => 'administrator' ) );
		$this->assertWPError( $out, 'forbidden_role' );
		$this->assertSame( array(), $GLOBALS['_wp_inserted_users'] );
	}

	/** @test */
	public function test_create_user_rejects_unknown_role(): void {
		$this->assertWPError( $this->ability->execute_create_user( array( 'username' => 'x', 'email' => 'x@example.com', 'role' => 'wizard' ) ), 'forbidden_role' );
	}

	/** @test */
	public function test_create_user_requires_username_and_email(): void {
		$this->assertWPError( $this->ability->execute_create_user( array( 'email' => 'a@b.com' ) ), 'missing_params' );
		$this->assertWPError( $this->ability->execute_create_user( array( 'username' => 'a' ) ), 'missing_params' );
	}

	/** @test */
	public function test_create_user_surfaces_insert_error(): void {
		$GLOBALS['_wp_insert_user_error'] = 'username exists';
		$this->assertWPError( $this->ability->execute_create_user( array( 'username' => 'dup', 'email' => 'dup@example.com' ) ), 'insert_failed' );
	}
```

- [ ] **Step 2: Run — expect FAIL (undefined execute_create_user)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/users/UserToolsTest.php`

- [ ] **Step 3: Replace the empty `register_create_user()` stub with the real registration + execute**

```php
	// -------------------------------------------------------------------
	// create-user
	// -------------------------------------------------------------------

	private function register_create_user(): void {
		$this->ability_names[] = 'emcp-tools/create-user';
		emcp_tools_register_ability(
			'emcp-tools/create-user',
			array(
				'label'               => __( 'Create User', 'emcp-tools' ),
				'description'         => __( 'Creates a new non-admin WordPress user. A strong password is generated automatically and the user is emailed a set-password link — the password is never returned. The role defaults to subscriber and cannot be an administrator or any admin-grade role.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_create_user' ),
				'permission_callback' => array( $this, 'can_create' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'username'     => array( 'type' => 'string', 'description' => __( 'The login username.', 'emcp-tools' ) ),
						'email'        => array( 'type' => 'string', 'description' => __( 'Email address (must be unique).', 'emcp-tools' ) ),
						'role'         => array( 'type' => 'string', 'description' => __( 'Role slug (non-admin). Default: subscriber.', 'emcp-tools' ) ),
						'first_name'   => array( 'type' => 'string' ),
						'last_name'    => array( 'type' => 'string' ),
						'display_name' => array( 'type' => 'string' ),
						'url'          => array( 'type' => 'string' ),
						'description'  => array( 'type' => 'string' ),
					),
					'required'   => array( 'username', 'email' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'id' => array( 'type' => 'integer' ), 'username' => array( 'type' => 'string' ),
					'email' => array( 'type' => 'string' ), 'role' => array( 'type' => 'string' ),
					'edit_link' => array( 'type' => 'string' ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_create_user( $input ) {
		$username = sanitize_user( (string) ( $input['username'] ?? '' ), true );
		$email    = sanitize_email( (string) ( $input['email'] ?? '' ) );
		if ( '' === $username || '' === $email ) {
			return new \WP_Error( 'missing_params', __( 'Both "username" and "email" are required.', 'emcp-tools' ) );
		}
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'That email address is not valid.', 'emcp-tools' ) );
		}

		$role = sanitize_key( $input['role'] ?? '' );
		if ( '' === $role ) {
			$role = 'subscriber';
		}
		if ( ! get_role( $role ) ) {
			return new \WP_Error( 'forbidden_role', sprintf( /* translators: %s: role */ __( 'Unknown role "%s".', 'emcp-tools' ), $role ) );
		}
		if ( $this->role_has_admin_caps( $role ) ) {
			return new \WP_Error( 'forbidden_role', __( 'Refusing to create a user with an admin-level role via MCP.', 'emcp-tools' ) );
		}

		$userdata = array(
			'user_login' => $username,
			'user_email' => $email,
			'user_pass'  => wp_generate_password( 24, true, true ),
			'role'       => $role,
		);
		foreach ( array( 'first_name' => 'first_name', 'last_name' => 'last_name', 'display_name' => 'display_name', 'description' => 'description' ) as $in => $key ) {
			if ( array_key_exists( $in, $input ) ) {
				$userdata[ $key ] = sanitize_text_field( (string) $input[ $in ] );
			}
		}
		if ( array_key_exists( 'url', $input ) ) {
			$userdata['user_url'] = esc_url_raw( (string) $input['url'] );
		}

		$user_id = wp_insert_user( $userdata );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		$user_id = (int) $user_id;

		// Email the new user a set-password link. The password is NEVER returned.
		if ( function_exists( 'wp_send_new_user_notifications' ) ) {
			wp_send_new_user_notifications( $user_id, 'user' );
		}

		return array(
			'id'        => $user_id,
			'username'  => $username,
			'email'     => $email,
			'role'      => $role,
			'edit_link' => admin_url( 'user-edit.php?user_id=' . $user_id ),
		);
	}
```

- [ ] **Step 4: Run — expect PASS**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/users/UserToolsTest.php`

- [ ] **Step 5: Commit**

```bash
git add includes/abilities/class-user-abilities.php tests/unit/users/UserToolsTest.php
git commit -m "feat(users): create-user — non-admin only, auto-password + email, never returned"
```

---

## Task 4: update-user (admin-protection)

**Files:** Modify `includes/abilities/class-user-abilities.php`; append to `tests/unit/users/UserToolsTest.php`

- [ ] **Step 1: Restore the count assertion + append failing tests**

Restore `test_registers_four_tools` to its full form (4-slug loop + `assertCount( 4, $names )`). Append:

```php
	/** @test */
	public function test_update_user_edits_non_admin_profile(): void {
		$out = $this->ability->execute_update_user( array( 'id' => 9, 'display_name' => 'Jane Q. Doe', 'description' => 'Senior writer' ) );
		$this->assertNotWPError( $out );
		$this->assertContains( 'display_name', $out['updated'] );
		$this->assertContains( 'description', $out['updated'] );
		$arr = $GLOBALS['_wp_updated_users'][0];
		$this->assertSame( 9, (int) $arr['ID'] );
		$this->assertSame( 'Jane Q. Doe', $arr['display_name'] );
	}

	/** @test */
	public function test_update_user_refuses_admin_target(): void {
		$out = $this->ability->execute_update_user( array( 'id' => 1, 'description' => 'hacked' ) );
		$this->assertWPError( $out, 'protected_user' );
		$this->assertSame( array(), $GLOBALS['_wp_updated_users'] );
	}

	/** @test */
	public function test_update_user_ignores_role_and_password(): void {
		$out = $this->ability->execute_update_user( array( 'id' => 9, 'role' => 'administrator', 'password' => 'x', 'user_pass' => 'x', 'display_name' => 'Jane' ) );
		$this->assertNotWPError( $out );
		$arr = $GLOBALS['_wp_updated_users'][0];
		$this->assertArrayNotHasKey( 'role', $arr );
		$this->assertArrayNotHasKey( 'user_pass', $arr );
		$this->assertArrayNotHasKey( 'password', $arr );
	}

	/** @test */
	public function test_update_user_not_found(): void {
		$this->assertWPError( $this->ability->execute_update_user( array( 'id' => 999, 'display_name' => 'x' ) ), 'user_not_found' );
	}

	/** @test */
	public function test_update_user_requires_id(): void {
		$this->assertWPError( $this->ability->execute_update_user( array( 'display_name' => 'x' ) ), 'missing_params' );
	}
```

- [ ] **Step 2: Run — expect FAIL (undefined execute_update_user)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/users/UserToolsTest.php`

- [ ] **Step 3: Replace the empty `register_update_user()` stub with the real registration + execute**

```php
	// -------------------------------------------------------------------
	// update-user
	// -------------------------------------------------------------------

	private function register_update_user(): void {
		$this->ability_names[] = 'emcp-tools/update-user';
		emcp_tools_register_ability(
			'emcp-tools/update-user',
			array(
				'label'               => __( 'Update User', 'emcp-tools' ),
				'description'         => __( 'Updates a non-admin user\'s profile: email, first/last name, display name, nickname, URL, description. Cannot change roles or passwords, and refuses to edit any user with admin-level capabilities (administrators are off-limits via MCP).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_user' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'           => array( 'type' => 'integer', 'description' => __( 'User ID.', 'emcp-tools' ) ),
						'email'        => array( 'type' => 'string' ),
						'first_name'   => array( 'type' => 'string' ),
						'last_name'    => array( 'type' => 'string' ),
						'display_name' => array( 'type' => 'string' ),
						'nickname'     => array( 'type' => 'string' ),
						'url'          => array( 'type' => 'string' ),
						'description'  => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'id' => array( 'type' => 'integer' ), 'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'email' => array( 'type' => 'string' ), 'display_name' => array( 'type' => 'string' ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_update_user( $input ) {
		$id = absint( $input['id'] ?? 0 );
		if ( ! $id ) {
			return new \WP_Error( 'missing_params', __( 'A user "id" is required.', 'emcp-tools' ) );
		}
		$u = get_userdata( $id );
		if ( ! $u ) {
			return new \WP_Error( 'user_not_found', __( 'User not found.', 'emcp-tools' ) );
		}
		if ( $this->user_has_admin_caps( $id ) ) {
			return new \WP_Error( 'protected_user', __( 'This user has administrator-level capabilities and cannot be edited via MCP.', 'emcp-tools' ) );
		}

		// Build the update from ONLY the allowed profile fields. role/password are
		// deliberately never read, so they can never be changed here.
		$userdata = array( 'ID' => $id );
		$updated  = array();
		foreach ( array( 'first_name' => 'first_name', 'last_name' => 'last_name', 'display_name' => 'display_name', 'nickname' => 'nickname' ) as $in => $key ) {
			if ( array_key_exists( $in, $input ) ) {
				$userdata[ $key ] = sanitize_text_field( (string) $input[ $in ] );
				$updated[]        = $in;
			}
		}
		if ( array_key_exists( 'description', $input ) ) {
			$userdata['description'] = function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( (string) $input['description'] ) : sanitize_text_field( (string) $input['description'] );
			$updated[]               = 'description';
		}
		if ( array_key_exists( 'url', $input ) ) {
			$userdata['user_url'] = esc_url_raw( (string) $input['url'] );
			$updated[]            = 'url';
		}
		if ( array_key_exists( 'email', $input ) ) {
			$email = sanitize_email( (string) $input['email'] );
			if ( ! is_email( $email ) ) {
				return new \WP_Error( 'invalid_email', __( 'That email address is not valid.', 'emcp-tools' ) );
			}
			$userdata['user_email'] = $email;
			$updated[]              = 'email';
		}

		$res = wp_update_user( $userdata );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$fresh = get_userdata( $id );
		return array(
			'id'           => $id,
			'updated'      => $updated,
			'email'        => (string) ( $fresh->user_email ?? '' ),
			'display_name' => (string) ( $fresh->display_name ?? '' ),
		);
	}
```

- [ ] **Step 4: Run — expect PASS (all UserToolsTest)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/users/UserToolsTest.php`
Expected: PASS (16 tests). Full suite green.

- [ ] **Step 5: Commit**

```bash
git add includes/abilities/class-user-abilities.php tests/unit/users/UserToolsTest.php
git commit -m "feat(users): update-user — profile-only, admins refused, no role/password change"
```

---

## Task 5: Capability tests

**Files:** Create `tests/unit/capabilities/UserCapabilityTest.php`

- [ ] **Step 1: Write the test**

```php
<?php
/**
 * Capability gating for the Users tools.
 * @group capabilities
 * @group users
 * @package EMCP_Tools\Tests\Capabilities
 */
namespace EMCP_Tools\Tests\Capabilities;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class UserCapabilityTest extends Ability_Test_Case {
	private \EMCP_Tools_User_Abilities $a;

	protected function setUp(): void {
		parent::setUp();
		$this->a = new \EMCP_Tools_User_Abilities();
		$this->a->register();
	}

	/** @test */
	public function test_reads_require_list_users(): void {
		$this->allow_caps( 'edit_posts' );
		$this->assertFalse( $this->a->can_list() );
		$this->allow_caps( 'list_users' );
		$this->assertTrue( $this->a->can_list() );
	}

	/** @test */
	public function test_create_requires_create_users(): void {
		$this->allow_caps( 'list_users' );
		$this->assertFalse( $this->a->can_create() );
		$this->allow_caps( 'create_users' );
		$this->assertTrue( $this->a->can_create() );
	}

	/** @test */
	public function test_update_requires_edit_users(): void {
		$this->allow_caps( 'list_users' );
		$this->assertFalse( $this->a->can_edit() );
		$this->allow_caps( 'edit_users' );
		$this->assertTrue( $this->a->can_edit() );
	}
}
```

- [ ] **Step 2: Run — expect PASS (3 tests)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/capabilities/UserCapabilityTest.php`

- [ ] **Step 3: Commit**

```bash
git add tests/unit/capabilities/UserCapabilityTest.php
git commit -m "test(users): per-tool capability gating (list/create/edit)"
```

---

## Task 6: Wiring — bootstrap, registrar, essentials, admin catalog, defaults v8, phpunit suite

**Files:** Modify `includes/class-bootstrap.php`, `includes/abilities/class-ability-registrar.php`, `includes/class-plugin.php`, `includes/admin/class-admin.php`, `phpunit.xml`

- [ ] **Step 1: require the class in the bootstrap**

In `includes/class-bootstrap.php`, after the line requiring `class-theme-abilities.php`, add:
```php
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-user-abilities.php';
```

- [ ] **Step 2: register the group in the registrar**

In `includes/abilities/class-ability-registrar.php`, after the theme group block (`$themes = new EMCP_Tools_Theme_Abilities(); … $themes->get_ability_names() );`), add:
```php

			// WordPress Users abilities (list/get + safe profile create/edit).
			// Unconditional — pure WordPress, always available.
			$users = new EMCP_Tools_User_Abilities();
			$users->register();
			$this->ability_names = array_merge( $this->ability_names, $users->get_ability_names() );
```

- [ ] **Step 3: essentials**

In `includes/class-plugin.php` `get_essential_tool_slugs()`, after the media block (`'emcp-tools/update-media',`), add:
```php

			// WordPress users (2 reads — writes opt-in only).
			'emcp-tools/list-users',
			'emcp-tools/get-user',
```

- [ ] **Step 4: admin catalog — new "Users" category**

In `includes/admin/class-admin.php` `get_tool_catalog()`, after the `'wp_media'`/Media category (or the `'wp_packages'` category — place it as a sibling key after the last beyond-Elementor category), add:
```php
			'wp_users'         => array(
				'label' => __( 'Users', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-users'   => array(
						'label'       => __( 'List Users', 'emcp-tools' ),
						'description' => __( 'Lists users (admin-only); filter by role/search.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-user'     => array(
						'label'       => __( 'Get User', 'emcp-tools' ),
						'description' => __( 'Returns one user\'s profile detail.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/create-user'  => array(
						'label'       => __( 'Create User', 'emcp-tools' ),
						'description' => __( 'Creates a non-admin user; auto-password + email.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-user'  => array(
						'label'       => __( 'Update User', 'emcp-tools' ),
						'description' => __( 'Edits a non-admin user\'s profile (no role/password; admins refused).', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
```

- [ ] **Step 5: defaults v8**

In `includes/admin/class-admin.php`: change `const DEFAULTS_VERSION = 7;` to `8`. Add a slugs method next to `media_write_tool_slugs()`:
```php
	/**
	 * Users mutation tool slugs that ship disabled-by-default. The reads
	 * (list-users/get-user) stay enabled. The admin opts in on the Tools tab.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function user_write_tool_slugs(): array {
		return array( 'emcp-tools/create-user', 'emcp-tools/update-user' );
	}
```
In `maybe_apply_default_disabled_tools()`, after the `if ( $applied < 7 ) { … }` block, add:
```php
			// v8 — Users mutation tools ship disabled-by-default (account changes).
			if ( $applied < 8 ) {
				$add = array_merge( $add, self::user_write_tool_slugs() );
			}
```

- [ ] **Step 6: phpunit.xml — add Users suite**

After the `<testsuite name="Media">` block, add:
```xml
        <testsuite name="Users">
            <directory>tests/unit/users</directory>
        </testsuite>
```

- [ ] **Step 7: Lint + both configs**

Run:
```
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe -l includes/class-bootstrap.php
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe -l includes/abilities/class-ability-registrar.php
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe -l includes/class-plugin.php
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe -l includes/admin/class-admin.php
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml
```
Expected: no syntax errors; both suites green (the admin catalog↔registry drift test still passes — all 4 new catalog slugs are registered abilities).

- [ ] **Step 8: Commit**

```bash
git add includes/class-bootstrap.php includes/abilities/class-ability-registrar.php includes/class-plugin.php includes/admin/class-admin.php phpunit.xml
git commit -m "feat(users): wire group + essentials + admin category + writes disabled-by-default (defaults v8)"
```

---

## Task 7: Documentation (fold into the single v3.0.0 entry)

**Files:** `CHANGELOG.md`, `readme.txt`, `README.md`, `CLAUDE.md`

NOT a new version — folds into the EXISTING `[3.0.0]` / `= 3.0.0 =` entries.

- [ ] **Step 1: CHANGELOG.md** — inside the existing `## [3.0.0]` → `### Added`, add:
```markdown
- **WordPress Users tools — beyond Elementor, domain 5.** Four MCP tools for safe user management: `list-users` and `get-user` (read; admin-gated, never expose passwords/auth data) plus `create-user` and `update-user` (write). Hard guardrails: `create-user` can only assign non-admin roles (auto-generates a strong password and emails a set-password link — the password is never returned); `update-user` edits profile fields only (no role or password changes) and refuses any user with admin-level capabilities. There is deliberately **no delete-user** and no role-change tool, so MCP cannot escalate privileges, take over an admin, or lock anyone out. `list-users`/`get-user` are enabled by default; `create-user`/`update-user` ship **disabled-by-default**.
```
Also append "and the WordPress **Users** tools (domain 5)" to the intro blurb sentence.

- [ ] **Step 2: readme.txt** — add a `* Added: WordPress Users tools …` line in the `= 3.0.0 =` block; append a clause to the description paragraph; add a Key Features bullet after the Media Library one:
```
* **Users (beyond Elementor)** — List and read WordPress users, and safely create/edit non-admin profiles over MCP. No delete and no role changes; new users get an auto-generated password by email (never returned); administrators are off-limits to editing. Reads enabled by default; create/update disabled-by-default. (v3.0.0)
```

- [ ] **Step 3: README.md** — add a Users feature bullet alongside the Media one + a 4-tool table section near it. Keep versions v3.0.0.

- [ ] **Step 4: CLAUDE.md** — append a domain-5 clause to the v3.0.0 overview sentence; update the "Current status" line to include the Users domain (all five beyond-Elementor domains); update the count narrative (the surface now also adds 4 User tools — 2 enabled-by-default, 2 disabled-by-default); add a "WordPress Users — domain 5 (4 tools)" section with the tool table + the privilege-guard summary (protected-cap set; non-admin-only create; admins untouchable; no delete/role/password; reads on / writes off).

- [ ] **Step 5: Confirm + commit**

Run `grep -rn "3\.1\.0\|3\.2\.0\|## \[4\.\|= 4\." CHANGELOG.md readme.txt README.md CLAUDE.md` → expect no new version headers.
```bash
git add CHANGELOG.md readme.txt README.md CLAUDE.md
git commit -m "docs: document WordPress Users tools (folded into v3.0.0)"
```

---

## Task 8: Thorough verification (PHP + live MCP + browser)

Verification only — no new production code unless a fix is needed.

- [ ] **Step 1: Full PHPUnit, both configs**

```
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml
```
Expected: both green, including the new Users + capability tests.

- [ ] **Step 2: Live MCP `tools/list`** — pipe initialize + notifications/initialized + tools/list into the WP-CLI stdio server (`--server=emcp-tools-server --user=admin --path=f:/laragon/www/msrplugins`, output to a Windows-absolute path). Confirm `emcp-tools-list-users` + `emcp-tools-get-user` appear; the two writes appear unless the v8 default seeding has run (disabled-by-default once an admin loads the Tools page).

- [ ] **Step 3: Live MCP `tools/call`** — `list-users` (confirm no `user_pass`/password in the payload); `get-user{id:1}` → confirm `is_admin:true`; `create-user{username:'emcp_tmp', email:'emcp_tmp@example.com'}` → confirm success with NO password field, then verify with `wp user get emcp_tmp` and clean up with `wp user delete emcp_tmp --reassign=1`; `create-user{..., role:'administrator'}` → confirm `forbidden_role`; `update-user{id:1, description:'x'}` (the admin) → confirm `protected_user`. (Deletes use WP-CLI since there's no delete-user tool.)

- [ ] **Step 4: Browser** — trigger the v8 seeding (load EMCP Tools → Tools); confirm the "Users" category renders: `list-users`/`get-user` enabled, `create-user`/`update-user` present but off by default; no PHP errors. Verify `wp option get emcp_tools_disabled_tools` contains `create-user`+`update-user` and `emcp_tools_defaults_applied` = 8.

- [ ] **Step 5: Report** — PHPUnit counts (both configs), live `tools/list` + `tools/call` outcomes (with the throwaway user removed), browser observation. Commit any fix with a clear message.
