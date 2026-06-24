# WordPress Settings Tools Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add two MCP tools (`emcp-tools/get-settings`, `emcp-tools/update-settings`) that read and write a curated, typed allowlist of core WordPress site settings, gated by `manage_options`.

**Architecture:** A single new ability group class `EMCP_Tools_Settings_Abilities` holds a private typed allowlist (option → group/label/type/writable/options/min/max), registers the two tools using the accumulator pattern, and exposes one `manage_options` permission callback. `get-settings` reads + coerces values (doubling as discovery); `update-settings` validates each key against the allowlist, writes the valid subset via `update_option`, reports the rest in `skipped[]`, and flushes rewrite rules once if any permalink key changed. Wired into the registrar/bootstrap/essentials/admin exactly like the content group.

**Tech Stack:** PHP 8.2, WordPress core (`get_option`/`update_option`/`flush_rewrite_rules`), the plugin's `emcp_tools_register_ability()` shim, PHPUnit with the function-stub harness in `tests/bootstrap.php`.

**Spec:** `docs/superpowers/specs/2026-06-25-wp-settings-tools-design.md`

---

## File Structure

| File | Responsibility |
|---|---|
| `includes/abilities/class-settings-abilities.php` | NEW — the ability group: allowlist + 2 tools + coercion/validation helpers. |
| `tests/bootstrap.php` | MODIFY — make `get_option`/`update_option` controllable; add `flush_rewrite_rules` + `sanitize_textarea_field` stubs. |
| `tests/unit/capabilities/SettingsCapabilityTest.php` | NEW — `manage_options` gating for both tools. |
| `tests/unit/settings/SettingsToolsTest.php` | NEW — execute-path: read/group/keys + coercion; write/skip/flush/partial-failure/empty. |
| `includes/class-bootstrap.php` | MODIFY — `require_once` the new class. |
| `includes/abilities/class-ability-registrar.php` | MODIFY — instantiate + register the group (unconditional). |
| `includes/class-plugin.php` | MODIFY — add the 2 slugs to `get_essential_tool_slugs()`. |
| `includes/admin/class-admin.php` | MODIFY — new "WordPress Settings" category in `get_tool_catalog()`. |
| `emcp-tools.php` | MODIFY — version → 3.2.0. |
| `CLAUDE.md`, `README.md`, `readme.txt`, `CHANGELOG.md` | MODIFY — document the family + counts. |

---

## Task 1: Test harness — controllable options + rewrite-flush stub

**Files:**
- Modify: `tests/bootstrap.php` (the `get_option`, `update_option` stubs ~lines 366-376; add two new stubs nearby)

The existing `get_option`/`update_option` stubs are static (return default / true). The settings tools need to read seeded values and record writes. Back them with `$GLOBALS['_wp_options']` and record updates in `$GLOBALS['_wp_options_updates']`. Add `flush_rewrite_rules` (records calls) and `sanitize_textarea_field`.

- [ ] **Step 1: Replace the `get_option` / `update_option` stubs**

In `tests/bootstrap.php`, replace these two blocks:

```php
	if ( ! function_exists( 'get_option' ) ) {
		function get_option( string $option, $default = false ) {
			return $default;
		}
	}

	if ( ! function_exists( 'update_option' ) ) {
		function update_option( string $option, $value, $autoload = null ): bool {
			return true;
		}
	}
```

with:

```php
	if ( ! function_exists( 'get_option' ) ) {
		function get_option( string $option, $default = false ) {
			if ( isset( $GLOBALS['_wp_options'] ) && array_key_exists( $option, $GLOBALS['_wp_options'] ) ) {
				return $GLOBALS['_wp_options'][ $option ];
			}
			return $default;
		}
	}

	if ( ! function_exists( 'update_option' ) ) {
		function update_option( string $option, $value, $autoload = null ): bool {
			if ( ! isset( $GLOBALS['_wp_options'] ) ) {
				$GLOBALS['_wp_options'] = array();
			}
			if ( ! isset( $GLOBALS['_wp_options_updates'] ) ) {
				$GLOBALS['_wp_options_updates'] = array();
			}
			$GLOBALS['_wp_options'][ $option ]   = $value;
			$GLOBALS['_wp_options_updates'][]    = array( 'option' => $option, 'value' => $value );
			return true;
		}
	}
```

- [ ] **Step 2: Add `flush_rewrite_rules` and `sanitize_textarea_field` stubs**

Immediately after the `update_option` block, add:

```php
	if ( ! function_exists( 'flush_rewrite_rules' ) ) {
		function flush_rewrite_rules( $hard = true ): void {
			if ( ! isset( $GLOBALS['_wp_flush_calls'] ) ) {
				$GLOBALS['_wp_flush_calls'] = array();
			}
			$GLOBALS['_wp_flush_calls'][] = $hard;
		}
	}

	if ( ! function_exists( 'sanitize_textarea_field' ) ) {
		function sanitize_textarea_field( $str ) {
			return is_string( $str ) ? trim( $str ) : '';
		}
	}
```

- [ ] **Step 3: Verify the harness still loads**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist --group content`
Expected: PASS (content tests unaffected — they don't touch options; this confirms no stub collision or fatal in bootstrap).

- [ ] **Step 4: Commit**

```bash
git add tests/bootstrap.php
git commit -m "test: controllable get_option/update_option + flush_rewrite_rules stubs"
```

---

## Task 2: Settings ability class — allowlist + skeleton + permission

**Files:**
- Create: `includes/abilities/class-settings-abilities.php`
- Test: `tests/unit/capabilities/SettingsCapabilityTest.php`

Build the class shell: the typed allowlist (source of truth), the accumulator + `get_ability_names()`, `register()` calling both registrars (stubbed empty for now is NOT allowed — register the tools in this task so capability wiring is real), and the single `manage_options` permission callback. Coercion/execute logic lands in Tasks 3-4, but the registration + permission callback are complete here so the capability test passes.

- [ ] **Step 1: Write the failing capability test**

Create `tests/unit/capabilities/SettingsCapabilityTest.php`:

```php
<?php
/**
 * Capability gating for the WordPress Settings tools.
 * @group capabilities
 * @group settings
 * @package EMCP_Tools\Tests\Capabilities
 */
namespace EMCP_Tools\Tests\Capabilities;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class SettingsCapabilityTest extends Ability_Test_Case {
	private \EMCP_Tools_Settings_Abilities $ability;

	protected function setUp(): void {
		parent::setUp();
		$this->ability = new \EMCP_Tools_Settings_Abilities();
		$this->ability->register();
	}

	/** @test */
	public function test_registers_both_tools(): void {
		$names = $this->ability->get_ability_names();
		$this->assertContains( 'emcp-tools/get-settings', $names );
		$this->assertContains( 'emcp-tools/update-settings', $names );
		$this->assertCount( 2, $names );
	}

	/** @test */
	public function test_denied_without_manage_options(): void {
		$this->allow_caps( 'edit_posts' );
		$this->assertFalse( $this->ability->check_manage_permission() );
	}

	/** @test */
	public function test_allowed_with_manage_options(): void {
		$this->allow_caps( 'manage_options' );
		$this->assertTrue( $this->ability->check_manage_permission() );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/capabilities/SettingsCapabilityTest.php`
Expected: FAIL — `Class "EMCP_Tools_Settings_Abilities" not found`.

- [ ] **Step 3: Create the class with allowlist, registration, and permission**

Create `includes/abilities/class-settings-abilities.php`:

```php
<?php
/**
 * WordPress site-settings MCP abilities.
 *
 * Two tools — get-settings (read + discovery) and update-settings (batch write) —
 * over a CURATED, TYPED ALLOWLIST of core WordPress settings (the Settings →
 * General/Reading/Writing/Discussion/Media/Permalinks screens). Arbitrary
 * get_option/update_option over any key is deliberately NOT exposed: only keys
 * in self::allowlist() are ever read or written. siteurl/home (lock-out),
 * users_can_register/default_role (registration escalation) are absent;
 * admin_email is read-only. Both tools require manage_options.
 *
 * Naming: distinct from EMCP_Tools_Settings_Validator (which validates Elementor
 * widget settings) — unrelated class, no collision.
 *
 * @package EMCP_Tools
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the WordPress settings abilities.
 *
 * @since 3.2.0
 */
class EMCP_Tools_Settings_Abilities {

	/**
	 * Names of the abilities actually registered by register().
	 *
	 * @since 3.2.0
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * Returns the names of all abilities registered by this group.
	 *
	 * @since 3.2.0
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/**
	 * Registers this group's MCP abilities.
	 *
	 * @since 3.2.0
	 */
	public function register(): void {
		$this->register_get_settings();
		$this->register_update_settings();
	}

	/**
	 * Permission gate: both tools require manage_options.
	 *
	 * @since 3.2.0
	 * @return bool
	 */
	public function check_manage_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ---------------------------------------------------------------------
	// Allowlist (source of truth)
	// ---------------------------------------------------------------------

	/**
	 * The typed allowlist: option name => metadata.
	 *
	 * type: string|int|bool|enum. writable:false → read-only. options: enum
	 * members. min/max: int clamp range. group: the Settings screen it belongs to.
	 *
	 * @since 3.2.0
	 * @return array<string,array>
	 */
	private static function allowlist(): array {
		return array(
			// General.
			'blogname'                     => array( 'group' => 'general', 'label' => 'Site Title', 'type' => 'string', 'writable' => true ),
			'blogdescription'              => array( 'group' => 'general', 'label' => 'Tagline', 'type' => 'string', 'writable' => true ),
			'admin_email'                  => array( 'group' => 'general', 'label' => 'Administration Email', 'type' => 'string', 'writable' => false ),
			'timezone_string'              => array( 'group' => 'general', 'label' => 'Timezone', 'type' => 'string', 'writable' => true ),
			'gmt_offset'                   => array( 'group' => 'general', 'label' => 'GMT Offset', 'type' => 'string', 'writable' => true ),
			'date_format'                  => array( 'group' => 'general', 'label' => 'Date Format', 'type' => 'string', 'writable' => true ),
			'time_format'                  => array( 'group' => 'general', 'label' => 'Time Format', 'type' => 'string', 'writable' => true ),
			'start_of_week'                => array( 'group' => 'general', 'label' => 'Week Starts On', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 6 ),
			'WPLANG'                       => array( 'group' => 'general', 'label' => 'Site Language', 'type' => 'string', 'writable' => true ),
			// Reading.
			'show_on_front'                => array( 'group' => 'reading', 'label' => 'Front Page Displays', 'type' => 'enum', 'writable' => true, 'options' => array( 'posts', 'page' ) ),
			'page_on_front'                => array( 'group' => 'reading', 'label' => 'Front Page', 'type' => 'int', 'writable' => true, 'min' => 0 ),
			'page_for_posts'               => array( 'group' => 'reading', 'label' => 'Posts Page', 'type' => 'int', 'writable' => true, 'min' => 0 ),
			'posts_per_page'               => array( 'group' => 'reading', 'label' => 'Blog Pages Show At Most', 'type' => 'int', 'writable' => true, 'min' => 1, 'max' => 100 ),
			'posts_per_rss'                => array( 'group' => 'reading', 'label' => 'Syndication Feeds Show', 'type' => 'int', 'writable' => true, 'min' => 1, 'max' => 100 ),
			'rss_use_excerpt'              => array( 'group' => 'reading', 'label' => 'Feed Shows Summary', 'type' => 'bool', 'writable' => true ),
			'blog_public'                  => array( 'group' => 'reading', 'label' => 'Search Engine Visibility', 'type' => 'bool', 'writable' => true ),
			// Writing.
			'default_category'             => array( 'group' => 'writing', 'label' => 'Default Post Category', 'type' => 'int', 'writable' => true, 'min' => 0 ),
			'default_post_format'          => array( 'group' => 'writing', 'label' => 'Default Post Format', 'type' => 'enum', 'writable' => true, 'options' => array( '0', 'aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio', 'chat' ) ),
			// Discussion.
			'default_comment_status'       => array( 'group' => 'discussion', 'label' => 'Allow Comments By Default', 'type' => 'enum', 'writable' => true, 'options' => array( 'open', 'closed' ) ),
			'default_ping_status'          => array( 'group' => 'discussion', 'label' => 'Allow Pingbacks By Default', 'type' => 'enum', 'writable' => true, 'options' => array( 'open', 'closed' ) ),
			'comment_registration'         => array( 'group' => 'discussion', 'label' => 'Users Must Register To Comment', 'type' => 'bool', 'writable' => true ),
			'require_name_email'           => array( 'group' => 'discussion', 'label' => 'Comment Author Must Fill Name/Email', 'type' => 'bool', 'writable' => true ),
			'comment_moderation'           => array( 'group' => 'discussion', 'label' => 'Hold Comments For Moderation', 'type' => 'bool', 'writable' => true ),
			'comments_per_page'            => array( 'group' => 'discussion', 'label' => 'Comments Per Page', 'type' => 'int', 'writable' => true, 'min' => 1, 'max' => 200 ),
			'thread_comments'              => array( 'group' => 'discussion', 'label' => 'Enable Threaded Comments', 'type' => 'bool', 'writable' => true ),
			'close_comments_for_old_posts' => array( 'group' => 'discussion', 'label' => 'Auto-Close Comments On Old Posts', 'type' => 'bool', 'writable' => true ),
			// Media.
			'thumbnail_size_w'             => array( 'group' => 'media', 'label' => 'Thumbnail Width', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 9999 ),
			'thumbnail_size_h'             => array( 'group' => 'media', 'label' => 'Thumbnail Height', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 9999 ),
			'medium_size_w'                => array( 'group' => 'media', 'label' => 'Medium Width', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 9999 ),
			'medium_size_h'                => array( 'group' => 'media', 'label' => 'Medium Height', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 9999 ),
			'large_size_w'                 => array( 'group' => 'media', 'label' => 'Large Width', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 9999 ),
			'large_size_h'                 => array( 'group' => 'media', 'label' => 'Large Height', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 9999 ),
			'uploads_use_yearmonth_folders' => array( 'group' => 'media', 'label' => 'Organize Uploads Into Month/Year Folders', 'type' => 'bool', 'writable' => true ),
			// Permalinks.
			'permalink_structure'          => array( 'group' => 'permalinks', 'label' => 'Permalink Structure', 'type' => 'string', 'writable' => true ),
			'category_base'                => array( 'group' => 'permalinks', 'label' => 'Category Base', 'type' => 'string', 'writable' => true ),
			'tag_base'                     => array( 'group' => 'permalinks', 'label' => 'Tag Base', 'type' => 'string', 'writable' => true ),
		);
	}

	/** Valid group names (the Settings screens). */
	private static function groups(): array {
		return array( 'general', 'reading', 'writing', 'discussion', 'media', 'permalinks' );
	}

	/** Whether a key belongs to the permalinks group. */
	private function is_permalink_key( string $key ): bool {
		$map = self::allowlist();
		return isset( $map[ $key ] ) && 'permalinks' === $map[ $key ]['group'];
	}

	// ---------------------------------------------------------------------
	// get-settings
	// ---------------------------------------------------------------------

	private function register_get_settings(): void {
		$this->ability_names[] = 'emcp-tools/get-settings';
		emcp_tools_register_ability(
			'emcp-tools/get-settings',
			array(
				'label'               => __( 'Get Settings', 'emcp-tools' ),
				'description'         => __( 'Reads curated WordPress site settings (General, Reading, Writing, Discussion, Media, Permalinks). With no args returns every allowlisted setting; pass "group" to filter to one screen or "keys" for specific settings. Each row carries the value plus metadata (type, label, writable, enum options) so this doubles as discovery for update-settings.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_settings' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'group' => array( 'type' => 'string', 'enum' => array( 'general', 'reading', 'writing', 'discussion', 'media', 'permalinks' ), 'description' => __( 'Filter to one Settings screen.', 'emcp-tools' ) ),
						'keys'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => __( 'Return only these allowlisted keys.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	// ---------------------------------------------------------------------
	// update-settings
	// ---------------------------------------------------------------------

	private function register_update_settings(): void {
		$this->ability_names[] = 'emcp-tools/update-settings';
		emcp_tools_register_ability(
			'emcp-tools/update-settings',
			array(
				'label'               => __( 'Update Settings', 'emcp-tools' ),
				'description'         => __( 'Updates curated WordPress site settings from a map of key → value. Only allowlisted, writable keys are changed; non-allowlisted, read-only (admin_email), or invalid values are returned in "skipped" with a reason — one bad key never aborts the batch. Changing a permalink setting (permalink_structure, category_base, tag_base) flushes rewrite rules automatically.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_settings' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array( 'type' => 'object', 'description' => __( 'Map of allowlisted setting key → new value.', 'emcp-tools' ) ),
					),
					'required'   => array( 'settings' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'updated'         => array( 'type' => 'object' ),
						'skipped'         => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
						'rewrite_flushed' => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}
}
```

- [ ] **Step 4: Run the capability test to verify it passes**

First load the class for the test run. Add `require_once` for the new file at the top of `tests/bootstrap.php`'s class-loading block is NOT needed if the bootstrap already globs ability classes — check how `class-content-abilities.php` is loaded in tests. If the test harness loads plugin classes explicitly, add the new file alongside the others. (See Task 2a below if the class is not autoloaded.)

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/capabilities/SettingsCapabilityTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/abilities/class-settings-abilities.php tests/unit/capabilities/SettingsCapabilityTest.php
git commit -m "feat(settings): settings ability class — allowlist + registration + manage_options gate"
```

---

## Task 2a: Ensure the class is loaded by the test harness

**Files:**
- Modify: `tests/bootstrap.php` (the section that `require_once`s plugin ability classes)

The PHPUnit harness must `require_once` `class-settings-abilities.php` so the test can `new EMCP_Tools_Settings_Abilities()`. Find where `class-content-abilities.php` is required in `tests/bootstrap.php` and add the settings class beside it.

- [ ] **Step 1: Locate the content-abilities require in the harness**

Run: `grep -n "class-content-abilities" tests/bootstrap.php`
Expected: one line requiring the content abilities class (the harness loads ability classes directly, not via the plugin bootstrap).

- [ ] **Step 2: Add the settings-abilities require beside it**

After the `class-content-abilities.php` require line, add:

```php
require_once __DIR__ . '/../includes/abilities/class-settings-abilities.php';
```

(Match the exact path style used by the adjacent require — if the file uses `dirname( __DIR__ )` or `EMCP_TOOLS_DIR`, mirror that.)

- [ ] **Step 3: Re-run the capability test**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/capabilities/SettingsCapabilityTest.php`
Expected: PASS (3 tests).

- [ ] **Step 4: Commit**

```bash
git add tests/bootstrap.php
git commit -m "test: load settings-abilities class in the PHPUnit harness"
```

---

## Task 3: get-settings execute path + coercion helpers

**Files:**
- Modify: `includes/abilities/class-settings-abilities.php` (add `execute_get_settings`, `read_setting`, `coerce_read`)
- Test: `tests/unit/settings/SettingsToolsTest.php` (new)

`get-settings` returns rows `{ key, group, label, type, value, options?, writable }`. `value` is the current `get_option()` result coerced to the declared type for clean JSON. No args → all; `group` → one screen; `keys` → those allowlisted keys only.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/settings/SettingsToolsTest.php`:

```php
<?php
/**
 * Execute-path tests for the WordPress Settings tools.
 * @group settings
 * @package EMCP_Tools\Tests\Settings
 */
namespace EMCP_Tools\Tests\Settings;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class SettingsToolsTest extends Ability_Test_Case {
	private \EMCP_Tools_Settings_Abilities $ability;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_options']         = array(
			'blogname'            => 'My Site',
			'blogdescription'     => 'Just another site',
			'admin_email'         => 'admin@example.com',
			'posts_per_page'      => '12',
			'blog_public'         => '1',
			'show_on_front'       => 'posts',
			'permalink_structure' => '/%postname%/',
		);
		$GLOBALS['_wp_options_updates'] = array();
		$GLOBALS['_wp_flush_calls']     = array();
		$this->ability = new \EMCP_Tools_Settings_Abilities();
		$this->ability->register();
	}

	private function find( array $out, string $key ): ?array {
		foreach ( $out['settings'] as $row ) {
			if ( $row['key'] === $key ) {
				return $row;
			}
		}
		return null;
	}

	/** @test */
	public function test_get_all_returns_rows_with_metadata(): void {
		$out = $this->ability->execute_get_settings( array() );
		$this->assertResultHasKey( $out, 'settings' );
		$row = $this->find( $out, 'blogname' );
		$this->assertNotNull( $row );
		$this->assertSame( 'general', $row['group'] );
		$this->assertSame( 'string', $row['type'] );
		$this->assertSame( 'My Site', $row['value'] );
		$this->assertTrue( $row['writable'] );
	}

	/** @test */
	public function test_get_coerces_int_and_bool(): void {
		$out  = $this->ability->execute_get_settings( array() );
		$ppp  = $this->find( $out, 'posts_per_page' );
		$pub  = $this->find( $out, 'blog_public' );
		$this->assertSame( 12, $ppp['value'] );      // int, not "12"
		$this->assertTrue( $pub['value'] );          // bool, not "1"
	}

	/** @test */
	public function test_enum_row_carries_options(): void {
		$out = $this->ability->execute_get_settings( array( 'keys' => array( 'show_on_front' ) ) );
		$this->assertCount( 1, $out['settings'] );
		$this->assertSame( array( 'posts', 'page' ), $out['settings'][0]['options'] );
	}

	/** @test */
	public function test_admin_email_is_read_only(): void {
		$out = $this->ability->execute_get_settings( array( 'keys' => array( 'admin_email' ) ) );
		$this->assertFalse( $out['settings'][0]['writable'] );
		$this->assertSame( 'admin@example.com', $out['settings'][0]['value'] );
	}

	/** @test */
	public function test_group_filter_only_returns_that_screen(): void {
		$out    = $this->ability->execute_get_settings( array( 'group' => 'permalinks' ) );
		$groups = array_unique( array_column( $out['settings'], 'group' ) );
		$this->assertSame( array( 'permalinks' ), $groups );
	}

	/** @test */
	public function test_keys_filter_ignores_non_allowlisted(): void {
		$out  = $this->ability->execute_get_settings( array( 'keys' => array( 'blogname', 'siteurl', 'not_a_setting' ) ) );
		$keys = array_column( $out['settings'], 'key' );
		$this->assertSame( array( 'blogname' ), $keys );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/settings/SettingsToolsTest.php`
Expected: FAIL — `Call to undefined method ...::execute_get_settings()`.

- [ ] **Step 3: Implement `execute_get_settings` + read coercion**

In `includes/abilities/class-settings-abilities.php`, add these methods (place after `register_update_settings`):

```php
	// ---------------------------------------------------------------------
	// Execute: get-settings
	// ---------------------------------------------------------------------

	/**
	 * @param array $input
	 * @return array
	 */
	public function execute_get_settings( $input ): array {
		$map   = self::allowlist();
		$group = isset( $input['group'] ) ? sanitize_key( (string) $input['group'] ) : '';
		$keys  = ( isset( $input['keys'] ) && is_array( $input['keys'] ) )
			? array_map( 'strval', $input['keys'] )
			: array();

		$rows = array();
		foreach ( $map as $key => $entry ) {
			if ( '' !== $group && $entry['group'] !== $group ) {
				continue;
			}
			if ( ! empty( $keys ) && ! in_array( $key, $keys, true ) ) {
				continue;
			}
			$row = array(
				'key'      => $key,
				'group'    => $entry['group'],
				'label'    => $entry['label'],
				'type'     => $entry['type'],
				'value'    => $this->read_setting( $key, $entry ),
				'writable' => ! empty( $entry['writable'] ),
			);
			if ( 'enum' === $entry['type'] && ! empty( $entry['options'] ) ) {
				$row['options'] = array_values( $entry['options'] );
			}
			$rows[] = $row;
		}
		return array( 'settings' => $rows );
	}

	/**
	 * Read an option and coerce it to the declared type for clean JSON.
	 *
	 * @param string $key
	 * @param array  $entry Allowlist entry.
	 * @return mixed
	 */
	private function read_setting( string $key, array $entry ) {
		$raw = get_option( $key );
		switch ( $entry['type'] ) {
			case 'int':
				return (int) $raw;
			case 'bool':
				return ! empty( $raw ) && '0' !== $raw;
			case 'enum':
			case 'string':
			default:
				return null === $raw ? '' : (string) $raw;
		}
	}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/settings/SettingsToolsTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/abilities/class-settings-abilities.php tests/unit/settings/SettingsToolsTest.php
git commit -m "feat(settings): get-settings execute path with typed read coercion"
```

---

## Task 4: update-settings execute path + write coercion/validation + permalink flush

**Files:**
- Modify: `includes/abilities/class-settings-abilities.php` (add `execute_update_settings`, `coerce_write`)
- Test: `tests/unit/settings/SettingsToolsTest.php` (append tests)

`update-settings` validates each key: not in allowlist → skipped `"not an allowlisted setting"`; `writable:false` → skipped `"read-only"`; invalid enum / out-of-range int → skipped `"invalid value for <type>"`. Valid keys are coerced and written via `update_option`. After the batch, if any permalink key changed, load rewrite deps and `flush_rewrite_rules( false )` once → `rewrite_flushed:true`. Empty/absent `settings` → `WP_Error('missing_params')`.

- [ ] **Step 1: Append failing tests**

Append to `tests/unit/settings/SettingsToolsTest.php` (before the closing `}`):

```php
	/** @test */
	public function test_update_writes_allowlisted_key(): void {
		$out = $this->ability->execute_update_settings( array( 'settings' => array( 'blogname' => 'Renamed' ) ) );
		$this->assertSame( 'Renamed', $out['updated']['blogname'] );
		$this->assertFalse( $out['rewrite_flushed'] );
		$this->assertSame( 'Renamed', get_option( 'blogname' ) );
	}

	/** @test */
	public function test_update_skips_non_allowlisted_key(): void {
		$out     = $this->ability->execute_update_settings( array( 'settings' => array( 'siteurl' => 'http://evil' ) ) );
		$reasons = array_column( $out['skipped'], 'key' );
		$this->assertContains( 'siteurl', $reasons );
		$this->assertArrayNotHasKey( 'siteurl', $out['updated'] );
		$this->assertSame( array(), $GLOBALS['_wp_options_updates'] );
	}

	/** @test */
	public function test_update_skips_read_only_admin_email(): void {
		$out = $this->ability->execute_update_settings( array( 'settings' => array( 'admin_email' => 'new@example.com' ) ) );
		$skipped = array();
		foreach ( $out['skipped'] as $s ) {
			$skipped[ $s['key'] ] = $s['reason'];
		}
		$this->assertArrayHasKey( 'admin_email', $skipped );
		$this->assertSame( 'read-only', $skipped['admin_email'] );
		$this->assertSame( 'admin@example.com', get_option( 'admin_email' ) );
	}

	/** @test */
	public function test_update_skips_invalid_enum(): void {
		$out     = $this->ability->execute_update_settings( array( 'settings' => array( 'show_on_front' => 'banana' ) ) );
		$skipped = array_column( $out['skipped'], 'key' );
		$this->assertContains( 'show_on_front', $skipped );
		$this->assertArrayNotHasKey( 'show_on_front', $out['updated'] );
	}

	/** @test */
	public function test_update_clamps_int_to_range(): void {
		$out = $this->ability->execute_update_settings( array( 'settings' => array( 'posts_per_page' => 5000 ) ) );
		$this->assertSame( 100, $out['updated']['posts_per_page'] ); // clamped to max
	}

	/** @test */
	public function test_update_coerces_bool(): void {
		$out = $this->ability->execute_update_settings( array( 'settings' => array( 'blog_public' => false ) ) );
		$this->assertSame( '', get_option( 'blog_public' ) ); // bool false → ''
		$this->assertSame( false, $out['updated']['blog_public'] );
	}

	/** @test */
	public function test_permalink_change_sets_flush_flag(): void {
		$out = $this->ability->execute_update_settings( array( 'settings' => array( 'permalink_structure' => '/blog/%postname%/' ) ) );
		$this->assertTrue( $out['rewrite_flushed'] );
		$this->assertNotEmpty( $GLOBALS['_wp_flush_calls'] );
	}

	/** @test */
	public function test_partial_failure_applies_valid_subset(): void {
		$out = $this->ability->execute_update_settings( array(
			'settings' => array(
				'blogname'      => 'Good',
				'siteurl'       => 'http://evil',  // skipped
				'show_on_front' => 'banana',       // skipped
			),
		) );
		$this->assertSame( 'Good', $out['updated']['blogname'] );
		$this->assertCount( 2, $out['skipped'] );
	}

	/** @test */
	public function test_empty_map_returns_error(): void {
		$this->assertWPError( $this->ability->execute_update_settings( array( 'settings' => array() ) ), 'missing_params' );
		$this->assertWPError( $this->ability->execute_update_settings( array() ), 'missing_params' );
	}
```

> Note: delete the `$this->assertArrayHasKey( 'Renamed', ... ); // noop guard` line — it was a paste artifact. The real assertions follow it. (Keep only the three lines after it.)

- [ ] **Step 2: Run to verify it fails**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/settings/SettingsToolsTest.php`
Expected: FAIL — `Call to undefined method ...::execute_update_settings()`.

- [ ] **Step 3: Implement `execute_update_settings` + write coercion**

In `includes/abilities/class-settings-abilities.php`, add after `read_setting`:

```php
	// ---------------------------------------------------------------------
	// Execute: update-settings
	// ---------------------------------------------------------------------

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_update_settings( $input ) {
		$settings = ( isset( $input['settings'] ) && is_array( $input['settings'] ) ) ? $input['settings'] : array();
		if ( empty( $settings ) ) {
			return new \WP_Error( 'missing_params', __( 'A non-empty "settings" map is required.', 'emcp-tools' ) );
		}

		$map              = self::allowlist();
		$updated          = array();
		$skipped          = array();
		$permalink_change = false;

		foreach ( $settings as $key => $value ) {
			$key = (string) $key;
			if ( ! isset( $map[ $key ] ) ) {
				$skipped[] = array( 'key' => $key, 'reason' => 'not an allowlisted setting' );
				continue;
			}
			$entry = $map[ $key ];
			if ( empty( $entry['writable'] ) ) {
				$skipped[] = array( 'key' => $key, 'reason' => 'read-only' );
				continue;
			}
			$coerced = $this->coerce_write( $entry, $value );
			if ( is_wp_error( $coerced ) ) {
				$skipped[] = array( 'key' => $key, 'reason' => $coerced->get_error_message() );
				continue;
			}
			$stored = $coerced['store'];
			update_option( $key, $stored );
			$updated[ $key ] = $coerced['report'];
			if ( $this->is_permalink_key( $key ) ) {
				$permalink_change = true;
			}
		}

		// flush_rewrite_rules() lives in wp-includes/rewrite.php and is loaded on
		// every request (REST/WP-CLI included), so no on-demand require is needed —
		// the function_exists guard is belt-and-suspenders for the unit harness.
		$rewrite_flushed = false;
		if ( $permalink_change && function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
			$rewrite_flushed = true;
		}

		return array(
			'updated'         => $updated,
			'skipped'         => $skipped,
			'rewrite_flushed' => $rewrite_flushed,
		);
	}

	/**
	 * Coerce + validate a write value against its allowlist entry.
	 *
	 * Returns [ 'store' => <value for update_option>, 'report' => <clean JSON value> ]
	 * or a WP_Error whose message becomes the skip reason.
	 *
	 * @param array $entry
	 * @param mixed $value
	 * @return array|\WP_Error
	 */
	private function coerce_write( array $entry, $value ) {
		switch ( $entry['type'] ) {
			case 'int':
				if ( ! is_numeric( $value ) ) {
					return new \WP_Error( 'invalid', 'invalid value for int' );
				}
				$n = (int) $value;
				if ( isset( $entry['min'] ) ) {
					$n = max( (int) $entry['min'], $n );
				}
				if ( isset( $entry['max'] ) ) {
					$n = min( (int) $entry['max'], $n );
				}
				return array( 'store' => $n, 'report' => $n );

			case 'bool':
				$b = (bool) $value;
				if ( is_string( $value ) ) {
					$b = ! in_array( strtolower( $value ), array( '', '0', 'false', 'no', 'off' ), true );
				}
				return array( 'store' => $b ? '1' : '', 'report' => $b );

			case 'enum':
				$v = (string) $value;
				if ( ! in_array( $v, (array) ( $entry['options'] ?? array() ), true ) ) {
					return new \WP_Error( 'invalid', 'invalid value for enum' );
				}
				return array( 'store' => $v, 'report' => $v );

			case 'string':
			default:
				$s = sanitize_text_field( (string) $value );
				return array( 'store' => $s, 'report' => $s );
		}
	}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/settings/SettingsToolsTest.php`
Expected: PASS (15 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/abilities/class-settings-abilities.php tests/unit/settings/SettingsToolsTest.php
git commit -m "feat(settings): update-settings — typed coercion, skip reasons, permalink flush"
```

---

## Task 5: Wire the group into the runtime (bootstrap + registrar + essentials)

**Files:**
- Modify: `includes/class-bootstrap.php` (`load_classes`, after the content-abilities require ~line 91)
- Modify: `includes/abilities/class-ability-registrar.php` (`register_all`, after the content block ~line 136)
- Modify: `includes/class-plugin.php` (`get_essential_tool_slugs`, after the content block ~line 246)

- [ ] **Step 1: require the class in the plugin bootstrap**

In `includes/class-bootstrap.php`, after:

```php
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-content-abilities.php';
```

add:

```php
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-settings-abilities.php';
```

- [ ] **Step 2: register the group in the registrar**

In `includes/abilities/class-ability-registrar.php`, after the content block:

```php
			// WordPress Content abilities (posts/pages/CPT CRUD + taxonomy + meta).
			// Unconditional — pure WordPress, always available.
			$content = new EMCP_Tools_Content_Abilities();
			$content->register();
			$this->ability_names = array_merge( $this->ability_names, $content->get_ability_names() );
```

add:

```php
			// WordPress Settings abilities (curated site-settings read/update).
			// Unconditional — pure WordPress, always available.
			$settings = new EMCP_Tools_Settings_Abilities();
			$settings->register();
			$this->ability_names = array_merge( $this->ability_names, $settings->get_ability_names() );
```

- [ ] **Step 3: add the slugs to essentials**

In `includes/class-plugin.php`, inside `get_essential_tool_slugs()`, after the content block (`'emcp-tools/set-post-terms',`) and before the closing `);`, add:

```php
			// WordPress settings (2) — curated site-settings read/update.
			'emcp-tools/get-settings',
			'emcp-tools/update-settings',
```

- [ ] **Step 4: Run the full suite to confirm no regressions**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist`
Expected: PASS — all prior tests + the new 18 settings tests green.

- [ ] **Step 5: Commit**

```bash
git add includes/class-bootstrap.php includes/abilities/class-ability-registrar.php includes/class-plugin.php
git commit -m "feat(settings): wire settings ability group into bootstrap, registrar, essentials"
```

---

## Task 6: Admin Tools catalog category + version bump

**Files:**
- Modify: `includes/admin/class-admin.php` (`get_tool_catalog()`, after the `wp_content` category block ~line 1144)
- Modify: `emcp-tools.php` (version constant + plugin header)

- [ ] **Step 1: Add the "WordPress Settings" category**

In `includes/admin/class-admin.php`, immediately after the closing of the `'wp_content' => array( ... ),` category (the line with `),` that closes it ~line 1144), add:

```php
			'wp_settings'      => array(
				'label' => __( 'WordPress Settings', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/get-settings'    => array(
						'label'       => __( 'Get Settings', 'emcp-tools' ),
						'description' => __( 'Reads curated site settings (general, reading, writing, discussion, media, permalinks).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/update-settings' => array(
						'label'       => __( 'Update Settings', 'emcp-tools' ),
						'description' => __( 'Updates curated site settings; auto-flushes rewrite rules on permalink changes.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
```

- [ ] **Step 2: Bump the version constant + header**

In `emcp-tools.php`, find the `Version:` header line and the `EMCP_TOOLS_VERSION` constant. Change both from `3.1.0` to `3.2.0`.

Run: `grep -n "3\.1\.0" emcp-tools.php`
Expected: the two lines to change (plugin header `Version:` and `define( 'EMCP_TOOLS_VERSION', '3.1.0' )`). Edit both to `3.2.0`.

- [ ] **Step 3: Verify the admin file has no syntax errors**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe -l includes/admin/class-admin.php && /f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe -l emcp-tools.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add includes/admin/class-admin.php emcp-tools.php
git commit -m "feat(settings): admin Tools category + version bump to 3.2.0"
```

---

## Task 7: Documentation

**Files:**
- Modify: `CHANGELOG.md` (new 3.2.0 entry at top)
- Modify: `readme.txt` (changelog + stable tag if present)
- Modify: `README.md` (tool list / counts where the content tools are mentioned)
- Modify: `CLAUDE.md` (add the Settings family to the tool inventory + counts)

- [ ] **Step 1: Add the CHANGELOG entry**

At the top of `CHANGELOG.md`'s entries, add:

```markdown
## 3.2.0

### Added
- **WordPress Settings tools (beyond Elementor, domain 2):** two MCP tools over a curated, typed allowlist of core WordPress settings.
  - `emcp-tools/get-settings` — read general/reading/writing/discussion/media/permalinks settings; doubles as discovery (returns each setting's type, label, enum options, writable flag). `manage_options`, read-only.
  - `emcp-tools/update-settings` — batch-update allowlisted settings; non-allowlisted, read-only (`admin_email`), or invalid values are reported in `skipped[]` (partial failure never aborts the batch). Changing a permalink setting auto-flushes rewrite rules. `manage_options`.
  - Safety: curated allowlist only (no arbitrary option access); `siteurl`/`home`, `users_can_register`/`default_role` excluded; `admin_email` read-only.
```

- [ ] **Step 2: Update `readme.txt`**

If `readme.txt` has a `Stable tag:` line set to `3.1.0`, change it to `3.2.0`. Add a matching `= 3.2.0 =` changelog block mirroring the CHANGELOG summary (the two new tools + the allowlist-only safety note).

Run: `grep -n "Stable tag" readme.txt`
Expected: one line; bump if it reads `3.1.0`.

- [ ] **Step 3: Update `README.md`**

Find where the v3.1.0 Content tools / tool counts are described and add a one-row mention of the Settings family (2 tools, `manage_options`, curated allowlist) and bump the "+11" enabled-by-default delta note to "+13" (8 Content + 3 core/* + 2 Settings). Keep edits surgical — only the count lines and a single Settings bullet/row.

- [ ] **Step 4: Update `CLAUDE.md`**

In `CLAUDE.md`, under the tool inventory, add a "WordPress Settings — v3.2.0 (2 tools)" subsection mirroring the Content section's style (table of the 2 tools + the allowlist-only safety note + `manage_options`). Update the "Current status" line to mention v3.2.0 and the Settings domain. Do not restate counts you can't verify — note them as estimates consistent with the spec (+2 over the v3.1.0 surface).

- [ ] **Step 5: Commit**

```bash
git add CHANGELOG.md readme.txt README.md CLAUDE.md
git commit -m "docs: document WordPress Settings tools (v3.2.0)"
```

---

## Task 8: Thorough verification (PHP + live MCP + browser)

This task is verification only — no new production code. Mirror the three-way check used for the Content domain.

- [ ] **Step 1: Full PHPUnit suite (both configs)**

Run:
```
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml
```
Expected: both green, including the new settings + capability tests.

- [ ] **Step 2: Live MCP `tools/list` — confirm the 2 new tools register**

Pipe `initialize` + `notifications/initialized` + `tools/list` JSON-RPC into:
```
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe /c/wp-cli/wp-cli.phar mcp-adapter serve --server=emcp-tools-server --user=admin --path=f:/laragon/www/msrplugins
```
(Write output to a Windows-absolute path readable by both bash and PHP.) Confirm `emcp-tools-get-settings` and `emcp-tools-update-settings` appear.

- [ ] **Step 3: Live MCP `tools/call` round-trip**

1. `get-settings` with `{"group":"general"}` → read `blogname`/`blogdescription`.
2. `update-settings` with `{"settings":{"blogname":"EMCP Test Title"}}` → then `wp option get blogname` confirms the change; restore the original with another `update-settings` call.
3. `update-settings` with `{"settings":{"permalink_structure":"/%postname%/"}}` → confirm `rewrite_flushed:true`; restore the original structure afterward.
4. `update-settings` with `{"settings":{"siteurl":"http://x"}}` → confirm it lands in `skipped` (`not an allowlisted setting`) and `wp option get siteurl` is unchanged.

Record the original `blogname` and `permalink_structure` BEFORE step 2/3 so they can be restored exactly.

- [ ] **Step 4: Browser check (Playwright + injected auth cookie)**

Load EMCP Tools → Tools tab. Confirm the "WordPress Settings" category renders with the two toggles (Get Settings has the read-only badge), both enabled by default, and no PHP notices/errors on the page.

- [ ] **Step 5: Report results**

Summarize: PHPUnit counts (both configs), the live `tools/list` confirmation, the four `tools/call` outcomes (with values restored), and the browser screenshot/observation. No commit (verification only) unless a fix was needed — in which case commit the fix with a clear message.
