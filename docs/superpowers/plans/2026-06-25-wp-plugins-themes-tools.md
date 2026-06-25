# WordPress Plugins & Themes Tools Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 13 MCP tools that let an AI agent discover, install (wordpress.org only), update, activate/deactivate, and delete WordPress plugins and themes, behind per-op capability gating and self-protection guardrails.

**Architecture:** Two focused ability-group classes (`EMCP_Tools_Plugin_Abilities`, `EMCP_Tools_Theme_Abilities`) plus a shared `EMCP_Tools_Package_Guard` (protected-list, active-checks, direct-filesystem gate, on-demand upgrader-dep loading, quiet upgrader skin). Built entirely on WP core upgrader/plugin/theme APIs, in-process. Reads enabled by default; the 9 mutation tools seeded disabled-by-default.

**Tech Stack:** PHP 8.2, WordPress core (`get_plugins`, `activate_plugin`, `delete_plugins`, `Plugin_Upgrader`/`Theme_Upgrader`, `plugins_api`/`themes_api`, `wp_get_themes`, `switch_theme`, `delete_theme`, `get_filesystem_method`), the plugin's `emcp_tools_register_ability()` shim, PHPUnit function-stub harness.

**Spec:** `docs/superpowers/specs/2026-06-25-wp-plugins-themes-tools-design.md`

---

## File Structure

| File | Responsibility |
|---|---|
| `includes/class-package-guard.php` | NEW — `EMCP_Tools_Package_Guard`: protected-list, active checks, capability constants, filesystem gate, on-demand dep loading, quiet skin factory. |
| `includes/abilities/class-plugin-abilities.php` | NEW — `EMCP_Tools_Plugin_Abilities`: 7 plugin tools + permission callbacks. |
| `includes/abilities/class-theme-abilities.php` | NEW — `EMCP_Tools_Theme_Abilities`: 6 theme tools + permission callbacks. |
| `tests/bootstrap.php` | MODIFY — stubs for plugin/theme/upgrader/filesystem/api functions + fake upgrader & skin classes. |
| `tests/unit/packages/PluginToolsTest.php` | NEW — plugin execute-path tests. |
| `tests/unit/packages/ThemeToolsTest.php` | NEW — theme execute-path tests. |
| `tests/unit/capabilities/PluginCapabilityTest.php` | NEW — plugin per-tool capability gating + guard refusals. |
| `tests/unit/capabilities/ThemeCapabilityTest.php` | NEW — theme per-tool capability gating + guard refusals. |
| `includes/class-bootstrap.php` | MODIFY — `require_once` the three new classes. |
| `includes/abilities/class-ability-registrar.php` | MODIFY — register both groups. |
| `includes/class-plugin.php` | MODIFY — add `list-plugins`/`list-themes` to `get_essential_tool_slugs()`. |
| `includes/admin/class-admin.php` | MODIFY — "Plugins & Themes" catalog category; `DEFAULTS_VERSION` 5→6 + `package_write_tool_slugs()`. |
| `phpunit.xml` | MODIFY — add a `Packages` testsuite. |
| `CHANGELOG.md`, `README.md`, `readme.txt`, `CLAUDE.md` | MODIFY — fold into the single v3.0.0 entry. |

**Naming conventions to follow (from the content/settings groups):** accumulator pattern (`private $ability_names = array();` appended at the top of each `register_*`), `emcp_tools_register_ability()` shim, `'category' => 'emcp-tools'`, text domain `'emcp-tools'`, tabs, `\WP_Error` with code+message, meta annotations `array( 'readonly' => …, 'destructive' => …, 'idempotent' => … )` + `'show_in_rest' => true`.

---

## Task 1: Test harness — plugin/theme/upgrader/filesystem stubs

**Files:**
- Modify: `tests/bootstrap.php`

The harness defines `if ( ! function_exists() )` stubs. Add controllable stubs for the WP plugin/theme/upgrader surface, backed by `$GLOBALS`, plus fake `Automatic_Upgrader_Skin`, `Plugin_Upgrader`, `Theme_Upgrader`, `WP_Theme` classes. First grep to confirm none already exist.

- [ ] **Step 1: Check for pre-existing stubs**

Run: `grep -n "function get_plugins\|function wp_get_themes\|class Plugin_Upgrader\|function get_filesystem_method\|function plugins_api\|class WP_Theme" tests/bootstrap.php`
Expected: no matches (if any exist, do NOT duplicate — reuse them).

- [ ] **Step 2: Add the function stubs**

In `tests/bootstrap.php`, inside the same `namespace { … }` block that holds the other global function stubs (where `get_option`/`update_option` live), add:

```php
	if ( ! function_exists( 'get_plugins' ) ) {
		function get_plugins( $folder = '' ) {
			return isset( $GLOBALS['_wp_plugins'] ) && is_array( $GLOBALS['_wp_plugins'] ) ? $GLOBALS['_wp_plugins'] : array();
		}
	}
	if ( ! function_exists( 'is_plugin_active' ) ) {
		function is_plugin_active( $file ) {
			return in_array( $file, $GLOBALS['_wp_active_plugins'] ?? array(), true );
		}
	}
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		function is_plugin_active_for_network( $file ) {
			return in_array( $file, $GLOBALS['_wp_network_active_plugins'] ?? array(), true );
		}
	}
	if ( ! function_exists( 'activate_plugin' ) ) {
		function activate_plugin( $file, $redirect = '', $network_wide = false, $silent = false ) {
			$GLOBALS['_wp_activated_plugins'][] = $file;
			$GLOBALS['_wp_active_plugins'][]    = $file;
			return null;
		}
	}
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		function deactivate_plugins( $files, $silent = false, $network_wide = null ) {
			foreach ( (array) $files as $f ) {
				$GLOBALS['_wp_deactivated_plugins'][] = $f;
				$GLOBALS['_wp_active_plugins']        = array_values( array_diff( $GLOBALS['_wp_active_plugins'] ?? array(), array( $f ) ) );
			}
		}
	}
	if ( ! function_exists( 'delete_plugins' ) ) {
		function delete_plugins( $files ) {
			foreach ( (array) $files as $f ) {
				$GLOBALS['_wp_deleted_plugins'][] = $f;
			}
			return true;
		}
	}
	if ( ! function_exists( 'wp_get_themes' ) ) {
		function wp_get_themes( $args = array() ) {
			return isset( $GLOBALS['_wp_themes'] ) && is_array( $GLOBALS['_wp_themes'] ) ? $GLOBALS['_wp_themes'] : array();
		}
	}
	if ( ! function_exists( 'wp_get_theme' ) ) {
		function wp_get_theme( $stylesheet = null, $theme_root = null ) {
			$themes = $GLOBALS['_wp_themes'] ?? array();
			if ( null === $stylesheet ) {
				$stylesheet = $GLOBALS['_wp_active_stylesheet'] ?? '';
			}
			return $themes[ $stylesheet ] ?? new \WP_Theme( $stylesheet, false );
		}
	}
	if ( ! function_exists( 'switch_theme' ) ) {
		function switch_theme( $stylesheet ) {
			$GLOBALS['_wp_switched_theme']     = $stylesheet;
			$GLOBALS['_wp_active_stylesheet']  = $stylesheet;
		}
	}
	if ( ! function_exists( 'delete_theme' ) ) {
		function delete_theme( $stylesheet, $redirect = '' ) {
			$GLOBALS['_wp_deleted_themes'][] = $stylesheet;
			return true;
		}
	}
	if ( ! function_exists( 'get_stylesheet' ) ) {
		function get_stylesheet() { return $GLOBALS['_wp_active_stylesheet'] ?? 'activetheme'; }
	}
	if ( ! function_exists( 'get_template' ) ) {
		function get_template() { return $GLOBALS['_wp_active_template'] ?? ( $GLOBALS['_wp_active_stylesheet'] ?? 'activetheme' ); }
	}
	if ( ! function_exists( 'get_filesystem_method' ) ) {
		function get_filesystem_method( $args = array(), $context = '', $allow_relaxed = false ) {
			return $GLOBALS['_wp_fs_method'] ?? 'direct';
		}
	}
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		function WP_Filesystem( $args = false, $context = false, $allow_relaxed = false ) {
			return ( ( $GLOBALS['_wp_fs_method'] ?? 'direct' ) === 'direct' );
		}
	}
	if ( ! function_exists( 'request_filesystem_credentials' ) ) {
		function request_filesystem_credentials( ...$a ) { return array(); }
	}
	if ( ! function_exists( 'plugins_api' ) ) {
		function plugins_api( $action, $args = array() ) {
			if ( isset( $GLOBALS['_wp_plugins_api_error'] ) ) {
				return new \WP_Error( 'plugins_api_failed', $GLOBALS['_wp_plugins_api_error'] );
			}
			if ( 'query_plugins' === $action ) {
				return (object) array( 'plugins' => $GLOBALS['_wp_plugins_api_query'] ?? array(), 'info' => array( 'results' => 0 ) );
			}
			$args = (object) $args;
			return (object) array( 'name' => $args->slug ?? 'x', 'slug' => $args->slug ?? 'x', 'version' => '1.0', 'download_link' => 'https://downloads.wordpress.org/plugin/' . ( $args->slug ?? 'x' ) . '.zip' );
		}
	}
	if ( ! function_exists( 'themes_api' ) ) {
		function themes_api( $action, $args = array() ) {
			if ( isset( $GLOBALS['_wp_themes_api_error'] ) ) {
				return new \WP_Error( 'themes_api_failed', $GLOBALS['_wp_themes_api_error'] );
			}
			if ( 'query_themes' === $action ) {
				return (object) array( 'themes' => $GLOBALS['_wp_themes_api_query'] ?? array(), 'info' => array( 'results' => 0 ) );
			}
			$args = (object) $args;
			return (object) array( 'name' => $args->slug ?? 'x', 'slug' => $args->slug ?? 'x', 'version' => '1.0', 'download_link' => 'https://downloads.wordpress.org/theme/' . ( $args->slug ?? 'x' ) . '.zip' );
		}
	}
	if ( ! function_exists( 'wp_update_plugins' ) ) {
		function wp_update_plugins( $extra = array() ) {}
	}
	if ( ! function_exists( 'wp_update_themes' ) ) {
		function wp_update_themes( $extra = array() ) {}
	}
	if ( ! function_exists( 'get_site_transient' ) ) {
		function get_site_transient( $key ) {
			return $GLOBALS['_wp_site_transients'][ $key ] ?? false;
		}
	}
	if ( ! function_exists( 'get_plugin_data' ) ) {
		function get_plugin_data( $file, $markup = true, $translate = true ) {
			return $GLOBALS['_wp_plugin_data'][ $file ] ?? array( 'Name' => basename( $file ), 'Version' => '1.0', 'Author' => '' );
		}
	}
```

- [ ] **Step 3: Add the fake classes**

In `tests/bootstrap.php`, in the global namespace block (alongside the other class stubs like `WP_Error`), add:

```php
	if ( ! class_exists( 'WP_Theme' ) ) {
		class WP_Theme {
			public $stylesheet; public $data; private $exists;
			public function __construct( $stylesheet, $data = array() ) {
				$this->stylesheet = (string) $stylesheet;
				$this->exists     = false !== $data;
				$this->data       = is_array( $data ) ? $data : array();
			}
			public function get( $k ) { return $this->data[ $k ] ?? ''; }
			public function get_stylesheet() { return $this->stylesheet; }
			public function get_template() { return $this->data['Template'] ?? $this->stylesheet; }
			public function exists() { return $this->exists; }
			public function errors() { return ! empty( $this->data['__errors'] ) ? new \WP_Error( 'theme_error', 'broken' ) : false; }
			public function parent() { return ! empty( $this->data['Template'] ) && $this->data['Template'] !== $this->stylesheet; }
		}
	}
	if ( ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
		class Automatic_Upgrader_Skin {
			public function get_upgrade_messages() { return array( 'Done.' ); }
		}
	}
	if ( ! class_exists( 'Plugin_Upgrader' ) ) {
		class Plugin_Upgrader {
			public $skin; public $result;
			public function __construct( $skin = null ) { $this->skin = $skin; }
			public function install( $package, $args = array() ) {
				if ( ! empty( $GLOBALS['_wp_upgrader_install_error'] ) ) { return new \WP_Error( 'install_failed', $GLOBALS['_wp_upgrader_install_error'] ); }
				$GLOBALS['_wp_installed_packages'][] = $package; return true;
			}
			public function upgrade( $file, $args = array() ) {
				if ( ! empty( $GLOBALS['_wp_upgrader_upgrade_error'] ) ) { return new \WP_Error( 'upgrade_failed', $GLOBALS['_wp_upgrader_upgrade_error'] ); }
				$GLOBALS['_wp_upgraded'][] = $file; return true;
			}
			public function plugin_info() { return $GLOBALS['_wp_upgrader_plugin_info'] ?? 'installed-plugin/installed-plugin.php'; }
		}
	}
	if ( ! class_exists( 'Theme_Upgrader' ) ) {
		class Theme_Upgrader {
			public $skin; public $result;
			public function __construct( $skin = null ) { $this->skin = $skin; }
			public function install( $package, $args = array() ) {
				if ( ! empty( $GLOBALS['_wp_upgrader_install_error'] ) ) { return new \WP_Error( 'install_failed', $GLOBALS['_wp_upgrader_install_error'] ); }
				$GLOBALS['_wp_installed_packages'][] = $package; return true;
			}
			public function upgrade( $stylesheet, $args = array() ) {
				if ( ! empty( $GLOBALS['_wp_upgrader_upgrade_error'] ) ) { return new \WP_Error( 'upgrade_failed', $GLOBALS['_wp_upgrader_upgrade_error'] ); }
				$GLOBALS['_wp_upgraded'][] = $stylesheet; return true;
			}
			public function theme_info() { return new \WP_Theme( $GLOBALS['_wp_upgrader_theme_info'] ?? 'installed-theme', array( 'Version' => '1.0' ) ); }
		}
	}
```

- [ ] **Step 4: Verify the harness still loads**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist 2>&1 | tail -3`
Expected: `OK (515 tests, …)` — unchanged, proving no stub collision.

- [ ] **Step 5: Commit**

```bash
git add tests/bootstrap.php
git commit -m "test: plugin/theme/upgrader/filesystem stubs for the Plugins & Themes tools"
```

---

## Task 2: Package_Guard — shared safety helper

**Files:**
- Create: `includes/class-package-guard.php`
- Test: `tests/unit/packages/PluginToolsTest.php` (guard-focused tests live here for now; reused by later tasks)

- [ ] **Step 1: Write the failing guard test**

Create `tests/unit/packages/PluginToolsTest.php`:

```php
<?php
/**
 * Execute-path + guard tests for the Plugins tools.
 * @group packages
 * @package EMCP_Tools\Tests\Packages
 */
namespace EMCP_Tools\Tests\Packages;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class PluginToolsTest extends Ability_Test_Case {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_plugins'] = array(
			'akismet/akismet.php'           => array( 'Name' => 'Akismet', 'Version' => '5.0', 'Author' => 'Automattic' ),
			'elementor/elementor.php'       => array( 'Name' => 'Elementor', 'Version' => '4.1', 'Author' => 'Elementor' ),
			'elementor-mcp/emcp-tools.php'  => array( 'Name' => 'EMCP Tools', 'Version' => '3.0.0', 'Author' => 'MSR' ),
			'hello-dolly/hello.php'         => array( 'Name' => 'Hello Dolly', 'Version' => '1.7', 'Author' => 'Matt' ),
		);
		$GLOBALS['_wp_active_plugins']     = array( 'elementor/elementor.php', 'elementor-mcp/emcp-tools.php', 'akismet/akismet.php' );
		$GLOBALS['_wp_fs_method']          = 'direct';
		$GLOBALS['_wp_deactivated_plugins'] = array();
		$GLOBALS['_wp_deleted_plugins']     = array();
		$GLOBALS['_wp_installed_packages']  = array();
		$GLOBALS['_wp_upgraded']            = array();
		if ( ! defined( 'EMCP_TOOLS_BASENAME' ) ) {
			define( 'EMCP_TOOLS_BASENAME', 'elementor-mcp/emcp-tools.php' );
		}
	}

	/** @test */
	public function test_guard_protects_emcp_and_elementor(): void {
		$this->assertTrue( \EMCP_Tools_Package_Guard::is_protected_plugin( 'elementor-mcp/emcp-tools.php' ) );
		$this->assertTrue( \EMCP_Tools_Package_Guard::is_protected_plugin( 'elementor/elementor.php' ) );
		$this->assertTrue( \EMCP_Tools_Package_Guard::is_protected_plugin( 'elementor-pro/elementor-pro.php' ) );
		$this->assertFalse( \EMCP_Tools_Package_Guard::is_protected_plugin( 'akismet/akismet.php' ) );
	}

	/** @test */
	public function test_guard_filesystem_ready_ok_when_direct(): void {
		$GLOBALS['_wp_fs_method'] = 'direct';
		$this->assertTrue( \EMCP_Tools_Package_Guard::filesystem_ready() );
	}

	/** @test */
	public function test_guard_filesystem_error_when_not_direct(): void {
		$GLOBALS['_wp_fs_method'] = 'ftpext';
		$this->assertWPError( \EMCP_Tools_Package_Guard::filesystem_ready(), 'filesystem_unavailable' );
	}
}
```

- [ ] **Step 2: Run — expect FAIL (class not found)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/packages/PluginToolsTest.php`
Expected: FAIL — `Class "EMCP_Tools_Package_Guard" not found`.

- [ ] **Step 3: Create the guard**

Create `includes/class-package-guard.php`:

```php
<?php
/**
 * Shared safety helper for the Plugins & Themes MCP tools.
 *
 * Centralizes the guardrails that protect a site from an AI agent (or a buggy
 * caller) breaking it: a protected-plugin list (never disable/delete EMCP Tools,
 * Elementor, or Elementor Pro), active-target checks, a direct-filesystem gate
 * (so a headless MCP request never hangs on an FTP-credential prompt), on-demand
 * loading of the wp-admin upgrader includes (absent on REST/WP-CLI requests),
 * and a quiet upgrader skin so installer output is captured, not echoed.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static guard utilities shared by the plugin + theme ability groups.
 *
 * @since 3.0.0
 */
class EMCP_Tools_Package_Guard {

	/**
	 * Plugin files that must never be deactivated or deleted via MCP.
	 *
	 * EMCP Tools itself (disabling it kills the MCP server mid-session) and
	 * Elementor / Elementor Pro (EMCP's hard dependency). The EMCP basename is
	 * self-resolved from the constant, never hardcoded.
	 *
	 * @since 3.0.0
	 * @return string[]
	 */
	public static function protected_plugin_files(): array {
		$files = array( 'elementor/elementor.php', 'elementor-pro/elementor-pro.php' );
		if ( defined( 'EMCP_TOOLS_BASENAME' ) ) {
			$files[] = EMCP_TOOLS_BASENAME;
		}
		/**
		 * Filter the MCP-protected plugin list.
		 *
		 * @since 3.0.0
		 * @param string[] $files Protected plugin basenames.
		 */
		return array_values( array_unique( (array) apply_filters( 'emcp_tools_protected_plugins', $files ) ) );
	}

	/**
	 * Whether a plugin file is protected from deactivate/delete.
	 *
	 * @since 3.0.0
	 * @param string $file Plugin basename (e.g. "elementor/elementor.php").
	 * @return bool
	 */
	public static function is_protected_plugin( string $file ): bool {
		return in_array( $file, self::protected_plugin_files(), true );
	}

	/**
	 * Whether a plugin is currently active (site or network).
	 *
	 * @since 3.0.0
	 * @param string $file Plugin basename.
	 * @return bool
	 */
	public static function is_active_plugin( string $file ): bool {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $file ) ) {
			return true;
		}
		return function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $file );
	}

	/**
	 * The active stylesheet plus its template (parent), both protected from delete.
	 *
	 * @since 3.0.0
	 * @return string[]
	 */
	public static function active_theme_stylesheets(): array {
		$out = array();
		if ( function_exists( 'get_stylesheet' ) ) {
			$out[] = (string) get_stylesheet();
		}
		if ( function_exists( 'get_template' ) ) {
			$out[] = (string) get_template();
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * Ensure the filesystem is directly writable before any install/update/delete.
	 *
	 * Returns true when WP can write without credentials; otherwise a WP_Error,
	 * so the tool fails cleanly instead of triggering an interactive FTP prompt
	 * that would hang a headless MCP request.
	 *
	 * @since 3.0.0
	 * @return true|\WP_Error
	 */
	public static function filesystem_ready() {
		self::load_upgrader_deps();
		$method = function_exists( 'get_filesystem_method' ) ? get_filesystem_method() : 'direct';
		if ( 'direct' !== $method ) {
			return new \WP_Error(
				'filesystem_unavailable',
				__( 'The WordPress filesystem is not directly writable on this host (it needs FTP/SSH credentials), so plugin/theme install, update, and delete cannot run over MCP. Use SFTP or set the FS_METHOD/credentials in wp-config.php.', 'emcp-tools' )
			);
		}
		if ( function_exists( 'WP_Filesystem' ) && ! WP_Filesystem() ) {
			return new \WP_Error( 'filesystem_unavailable', __( 'Could not initialise the WordPress filesystem.', 'emcp-tools' ) );
		}
		return true;
	}

	/**
	 * Load the wp-admin upgrader/plugin/theme includes on demand.
	 *
	 * These live under wp-admin/includes and are NOT loaded on the REST/WP-CLI
	 * requests the MCP server runs in. Guarded so each file loads at most once.
	 *
	 * @since 3.0.0
	 */
	public static function load_upgrader_deps(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			return;
		}
		$includes = array(
			'wp-admin/includes/plugin.php',
			'wp-admin/includes/plugin-install.php',
			'wp-admin/includes/theme.php',
			'wp-admin/includes/theme-install.php',
			'wp-admin/includes/file.php',
			'wp-admin/includes/misc.php',
			'wp-admin/includes/update.php',
			'wp-admin/includes/class-wp-upgrader.php',
		);
		foreach ( $includes as $rel ) {
			$path = ABSPATH . $rel;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * A quiet upgrader skin that captures messages instead of echoing HTML.
	 *
	 * @since 3.0.0
	 * @return object|null Automatic_Upgrader_Skin instance, or null if unavailable.
	 */
	public static function make_skin() {
		self::load_upgrader_deps();
		if ( class_exists( '\Automatic_Upgrader_Skin' ) ) {
			return new \Automatic_Upgrader_Skin();
		}
		return null;
	}

	/**
	 * Pull captured messages off an upgrader skin, normalized to strings.
	 *
	 * @since 3.0.0
	 * @param object|null $skin
	 * @return string[]
	 */
	public static function skin_messages( $skin ): array {
		if ( $skin && method_exists( $skin, 'get_upgrade_messages' ) ) {
			return array_map( 'wp_strip_all_tags', (array) $skin->get_upgrade_messages() );
		}
		return array();
	}
}
```

- [ ] **Step 4: Run — expect PASS (3 tests)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/packages/PluginToolsTest.php`
Expected: PASS (3 tests). The harness autoloads classes via the `tests/bootstrap.php` autoloader map — add `'EMCP_Tools_Package_Guard' => 'includes/class-package-guard.php'` to that map if the test errors with class-not-found (mirror the content/settings entries).

- [ ] **Step 5: Commit**

```bash
git add includes/class-package-guard.php tests/unit/packages/PluginToolsTest.php tests/bootstrap.php
git commit -m "feat(packages): Package_Guard — protected list, active checks, FS gate, upgrader deps"
```

---

## Task 3: Plugin abilities — discovery (list-plugins, search-plugins)

**Files:**
- Create: `includes/abilities/class-plugin-abilities.php`
- Test: `tests/unit/packages/PluginToolsTest.php` (append)

- [ ] **Step 1: Append failing tests**

Append to `tests/unit/packages/PluginToolsTest.php` (before the closing `}`):

```php
	private function plugins(): \EMCP_Tools_Plugin_Abilities {
		$a = new \EMCP_Tools_Plugin_Abilities();
		$a->register();
		return $a;
	}

	/** @test */
	public function test_registers_seven_tools(): void {
		$names = $this->plugins()->get_ability_names();
		foreach ( array( 'list-plugins', 'search-plugins', 'install-plugin', 'activate-plugin', 'deactivate-plugin', 'update-plugin', 'delete-plugin' ) as $slug ) {
			$this->assertContains( 'emcp-tools/' . $slug, $names );
		}
		$this->assertCount( 7, $names );
	}

	/** @test */
	public function test_list_plugins_rows_and_flags(): void {
		$GLOBALS['_wp_site_transients']['update_plugins'] = (object) array(
			'response' => array( 'hello-dolly/hello.php' => (object) array( 'new_version' => '1.8' ) ),
		);
		$out  = $this->plugins()->execute_list_plugins( array() );
		$this->assertResultHasKey( $out, 'plugins' );
		$rows = array();
		foreach ( $out['plugins'] as $r ) { $rows[ $r['file'] ] = $r; }
		$this->assertTrue( $rows['elementor/elementor.php']['is_protected'] );
		$this->assertTrue( $rows['elementor/elementor.php']['active'] );
		$this->assertFalse( $rows['hello-dolly/hello.php']['active'] );
		$this->assertTrue( $rows['hello-dolly/hello.php']['update_available'] );
		$this->assertSame( '1.8', $rows['hello-dolly/hello.php']['new_version'] );
		$this->assertFalse( $rows['akismet/akismet.php']['is_protected'] );
	}

	/** @test */
	public function test_search_plugins_returns_rows(): void {
		$GLOBALS['_wp_plugins_api_query'] = array(
			(object) array( 'slug' => 'contact-form-7', 'name' => 'Contact Form 7', 'version' => '5.9', 'rating' => 90, 'num_ratings' => 200, 'requires' => '6.0', 'tested' => '6.9', 'short_description' => 'Just a CF.' ),
		);
		$out = $this->plugins()->execute_search_plugins( array( 'search' => 'contact form' ) );
		$this->assertResultHasKey( $out, 'results' );
		$this->assertSame( 'contact-form-7', $out['results'][0]['slug'] );
	}

	/** @test */
	public function test_search_plugins_requires_query(): void {
		$this->assertWPError( $this->plugins()->execute_search_plugins( array() ), 'missing_params' );
	}
```

- [ ] **Step 2: Run — expect FAIL (class not found)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/packages/PluginToolsTest.php`
Expected: FAIL — `Class "EMCP_Tools_Plugin_Abilities" not found`.

- [ ] **Step 3: Create the class with the accumulator, permission callbacks, and the two discovery tools**

Create `includes/abilities/class-plugin-abilities.php`:

```php
<?php
/**
 * WordPress Plugin lifecycle MCP abilities.
 *
 * Seven tools to discover, install (wordpress.org only), activate, deactivate,
 * update, and delete plugins. Built on WP core's plugin + upgrader APIs and
 * guarded by EMCP_Tools_Package_Guard (protected list, active checks, direct
 * filesystem). Reads ship enabled; the five mutation tools ship disabled-by-
 * default (admin opts in on the Tools tab).
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the plugin lifecycle abilities.
 *
 * @since 3.0.0
 */
class EMCP_Tools_Plugin_Abilities {

	/** @since 3.0.0 @var string[] */
	private $ability_names = array();

	/** @since 3.0.0 @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/** @since 3.0.0 */
	public function register(): void {
		$this->register_list_plugins();
		$this->register_search_plugins();
		$this->register_install_plugin();
		$this->register_activate_plugin();
		$this->register_deactivate_plugin();
		$this->register_update_plugin();
		$this->register_delete_plugin();
	}

	// -------------------------------------------------------------------
	// Permission callbacks (per-op capability)
	// -------------------------------------------------------------------

	public function can_list(): bool { return current_user_can( 'activate_plugins' ); }
	public function can_install(): bool { return current_user_can( 'install_plugins' ); }
	public function can_activate(): bool { return current_user_can( 'activate_plugins' ); }
	public function can_update(): bool { return current_user_can( 'update_plugins' ); }
	public function can_delete(): bool { return current_user_can( 'delete_plugins' ); }

	// -------------------------------------------------------------------
	// list-plugins
	// -------------------------------------------------------------------

	private function register_list_plugins(): void {
		$this->ability_names[] = 'emcp-tools/list-plugins';
		emcp_tools_register_ability(
			'emcp-tools/list-plugins',
			array(
				'label'               => __( 'List Plugins', 'emcp-tools' ),
				'description'         => __( 'Lists installed WordPress plugins with status (active/inactive/network), version, whether an update is available, and whether the plugin is protected (EMCP Tools / Elementor can never be disabled via MCP). Optional "status" filter.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_plugins' ),
				'permission_callback' => array( $this, 'can_list' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array( 'type' => 'string', 'enum' => array( 'all', 'active', 'inactive' ), 'description' => __( 'Filter by status. Default: all.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'plugins' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array
	 */
	public function execute_list_plugins( $input ): array {
		EMCP_Tools_Package_Guard::load_upgrader_deps();
		$filter  = in_array( $input['status'] ?? 'all', array( 'all', 'active', 'inactive' ), true ) ? ( $input['status'] ?? 'all' ) : 'all';
		$all     = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$updates = get_site_transient( 'update_plugins' );
		$resp    = ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ) ? $updates->response : array();

		$rows = array();
		foreach ( $all as $file => $data ) {
			$active = EMCP_Tools_Package_Guard::is_active_plugin( (string) $file );
			if ( 'active' === $filter && ! $active ) { continue; }
			if ( 'inactive' === $filter && $active ) { continue; }
			$rows[] = array(
				'file'             => (string) $file,
				'slug'             => dirname( (string) $file ),
				'name'             => (string) ( $data['Name'] ?? $file ),
				'version'          => (string) ( $data['Version'] ?? '' ),
				'author'           => wp_strip_all_tags( (string) ( $data['Author'] ?? '' ) ),
				'active'           => $active,
				'is_protected'     => EMCP_Tools_Package_Guard::is_protected_plugin( (string) $file ),
				'update_available' => isset( $resp[ $file ] ),
				'new_version'      => isset( $resp[ $file ]->new_version ) ? (string) $resp[ $file ]->new_version : '',
			);
		}
		return array( 'plugins' => $rows );
	}

	// -------------------------------------------------------------------
	// search-plugins
	// -------------------------------------------------------------------

	private function register_search_plugins(): void {
		$this->ability_names[] = 'emcp-tools/search-plugins';
		emcp_tools_register_ability(
			'emcp-tools/search-plugins',
			array(
				'label'               => __( 'Search Plugins', 'emcp-tools' ),
				'description'         => __( 'Searches the wordpress.org plugin directory by keyword so you can find a slug to install. Returns slug, name, version, rating, and requirements. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_search_plugins' ),
				'permission_callback' => array( $this, 'can_install' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'   => array( 'type' => 'string', 'description' => __( 'Keyword(s) to search the .org directory.', 'emcp-tools' ) ),
						'per_page' => array( 'type' => 'integer', 'description' => __( '1-50. Default: 10.', 'emcp-tools' ) ),
					),
					'required'   => array( 'search' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'results' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_search_plugins( $input ) {
		$search = sanitize_text_field( $input['search'] ?? '' );
		if ( '' === $search ) {
			return new \WP_Error( 'missing_params', __( 'A "search" keyword is required.', 'emcp-tools' ) );
		}
		EMCP_Tools_Package_Guard::load_upgrader_deps();
		$per_page = max( 1, min( 50, absint( $input['per_page'] ?? 10 ) ) );
		$api      = plugins_api( 'query_plugins', array( 'search' => $search, 'per_page' => $per_page, 'fields' => array( 'short_description' => true, 'icons' => false ) ) );
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		$rows = array();
		foreach ( (array) ( $api->plugins ?? array() ) as $p ) {
			$p     = (object) $p;
			$rows[] = array(
				'slug'              => (string) ( $p->slug ?? '' ),
				'name'              => wp_strip_all_tags( (string) ( $p->name ?? '' ) ),
				'version'           => (string) ( $p->version ?? '' ),
				'rating'            => (int) ( $p->rating ?? 0 ),
				'num_ratings'       => (int) ( $p->num_ratings ?? 0 ),
				'requires'          => (string) ( $p->requires ?? '' ),
				'tested'            => (string) ( $p->tested ?? '' ),
				'short_description' => wp_strip_all_tags( (string) ( $p->short_description ?? '' ) ),
			);
		}
		return array( 'results' => $rows );
	}
}
```

- [ ] **Step 4: Run — expect PASS (existing 3 guard tests + 4 new = 7 in this file)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/packages/PluginToolsTest.php`
Expected: FAIL on `test_registers_seven_tools` only (registers 2 so far). The other new tests (list/search) PASS. This is expected — the lifecycle tools come in Task 4. To keep the suite green between tasks, TEMPORARILY change `test_registers_seven_tools` to assert the 2 discovery tools are present (not count 7); Task 4 restores the full assertion. Implementer: make that temporary edit now, run, expect all PASS.

Temporary body for this task:
```php
	/** @test */
	public function test_registers_seven_tools(): void {
		$names = $this->plugins()->get_ability_names();
		$this->assertContains( 'emcp-tools/list-plugins', $names );
		$this->assertContains( 'emcp-tools/search-plugins', $names );
	}
```

Run again: Expected PASS (7 tests in file).

- [ ] **Step 5: Add the autoloader entry + commit**

Add `'EMCP_Tools_Plugin_Abilities' => 'includes/abilities/class-plugin-abilities.php'` to the `tests/bootstrap.php` autoloader map (beside the content/settings entries).

```bash
git add includes/abilities/class-plugin-abilities.php tests/unit/packages/PluginToolsTest.php tests/bootstrap.php
git commit -m "feat(plugins): list-plugins + search-plugins discovery tools"
```

---

## Task 4: Plugin abilities — lifecycle (install/activate/deactivate/update/delete)

**Files:**
- Modify: `includes/abilities/class-plugin-abilities.php` (add 5 register_* + execute_* methods)
- Test: `tests/unit/packages/PluginToolsTest.php` (append; restore the count-7 assertion)

- [ ] **Step 1: Restore the full registration assertion + append lifecycle tests**

In `tests/unit/packages/PluginToolsTest.php`, restore `test_registers_seven_tools` to its original full-count form (the 7-slug loop + `assertCount( 7, $names )`). Then append:

```php
	/** @test */
	public function test_install_plugin_installs_and_optionally_activates(): void {
		$GLOBALS['_wp_upgrader_plugin_info'] = 'contact-form-7/wp-contact-form-7.php';
		$out = $this->plugins()->execute_install_plugin( array( 'slug' => 'contact-form-7', 'activate' => true ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['installed'] );
		$this->assertTrue( $out['activated'] );
		$this->assertSame( 'contact-form-7/wp-contact-form-7.php', $out['file'] );
		$this->assertNotEmpty( $GLOBALS['_wp_installed_packages'] );
	}

	/** @test */
	public function test_install_plugin_requires_slug(): void {
		$this->assertWPError( $this->plugins()->execute_install_plugin( array() ), 'missing_params' );
	}

	/** @test */
	public function test_install_plugin_surfaces_api_error(): void {
		$GLOBALS['_wp_plugins_api_error'] = 'no such plugin';
		$this->assertWPError( $this->plugins()->execute_install_plugin( array( 'slug' => 'nope-xyz' ) ), 'plugins_api_failed' );
	}

	/** @test */
	public function test_install_plugin_blocked_when_filesystem_not_direct(): void {
		$GLOBALS['_wp_fs_method'] = 'ftpext';
		$this->assertWPError( $this->plugins()->execute_install_plugin( array( 'slug' => 'contact-form-7' ) ), 'filesystem_unavailable' );
	}

	/** @test */
	public function test_deactivate_refuses_protected(): void {
		$this->assertWPError( $this->plugins()->execute_deactivate_plugin( array( 'plugin' => 'elementor/elementor.php' ) ), 'protected_plugin' );
		$this->assertWPError( $this->plugins()->execute_deactivate_plugin( array( 'plugin' => 'elementor-mcp/emcp-tools.php' ) ), 'protected_plugin' );
		$this->assertSame( array(), $GLOBALS['_wp_deactivated_plugins'] );
	}

	/** @test */
	public function test_deactivate_allows_normal_plugin(): void {
		$out = $this->plugins()->execute_deactivate_plugin( array( 'plugin' => 'akismet/akismet.php' ) );
		$this->assertNotWPError( $out );
		$this->assertContains( 'akismet/akismet.php', $GLOBALS['_wp_deactivated_plugins'] );
	}

	/** @test */
	public function test_activate_unknown_plugin_errors(): void {
		$this->assertWPError( $this->plugins()->execute_activate_plugin( array( 'plugin' => 'ghost/ghost.php' ) ), 'plugin_not_found' );
	}

	/** @test */
	public function test_delete_refuses_active_plugin(): void {
		$this->assertWPError( $this->plugins()->execute_delete_plugin( array( 'plugin' => 'akismet/akismet.php' ) ), 'plugin_active' );
	}

	/** @test */
	public function test_delete_refuses_protected_plugin(): void {
		$this->assertWPError( $this->plugins()->execute_delete_plugin( array( 'plugin' => 'elementor/elementor.php' ) ), 'protected_plugin' );
	}

	/** @test */
	public function test_delete_removes_inactive_plugin(): void {
		$out = $this->plugins()->execute_delete_plugin( array( 'plugin' => 'hello-dolly/hello.php' ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['deleted'] );
		$this->assertContains( 'hello-dolly/hello.php', $GLOBALS['_wp_deleted_plugins'] );
	}

	/** @test */
	public function test_update_reports_up_to_date(): void {
		$GLOBALS['_wp_site_transients']['update_plugins'] = (object) array( 'response' => array() );
		$out = $this->plugins()->execute_update_plugin( array( 'plugin' => 'akismet/akismet.php' ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['up_to_date'] );
	}

	/** @test */
	public function test_update_runs_when_update_available(): void {
		$GLOBALS['_wp_site_transients']['update_plugins'] = (object) array(
			'response' => array( 'hello-dolly/hello.php' => (object) array( 'new_version' => '1.8' ) ),
		);
		$out = $this->plugins()->execute_update_plugin( array( 'plugin' => 'hello-dolly/hello.php' ) );
		$this->assertNotWPError( $out );
		$this->assertFalse( $out['up_to_date'] );
		$this->assertContains( 'hello-dolly/hello.php', $GLOBALS['_wp_upgraded'] );
	}
```

- [ ] **Step 2: Run — expect FAIL (undefined execute_install_plugin etc.)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/packages/PluginToolsTest.php`
Expected: FAIL — undefined methods.

- [ ] **Step 3: Add the five lifecycle register_* + execute_* methods**

In `includes/abilities/class-plugin-abilities.php`, before the final class `}`, add:

```php
	// -------------------------------------------------------------------
	// install-plugin
	// -------------------------------------------------------------------

	private function register_install_plugin(): void {
		$this->ability_names[] = 'emcp-tools/install-plugin';
		emcp_tools_register_ability(
			'emcp-tools/install-plugin',
			array(
				'label'               => __( 'Install Plugin', 'emcp-tools' ),
				'description'         => __( 'Installs a plugin from the wordpress.org directory by slug (e.g. "contact-form-7"). Optionally activates it. Source is always wordpress.org — arbitrary URLs are not accepted.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_install_plugin' ),
				'permission_callback' => array( $this, 'can_install' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'slug'     => array( 'type' => 'string', 'description' => __( 'wordpress.org plugin slug.', 'emcp-tools' ) ),
						'activate' => array( 'type' => 'boolean', 'description' => __( 'Activate after install. Default: false.', 'emcp-tools' ) ),
					),
					'required'   => array( 'slug' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'installed' => array( 'type' => 'boolean' ), 'activated' => array( 'type' => 'boolean' ),
					'file' => array( 'type' => 'string' ), 'slug' => array( 'type' => 'string' ),
					'messages' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_install_plugin( $input ) {
		$slug = sanitize_key( $input['slug'] ?? '' );
		if ( '' === $slug ) {
			return new \WP_Error( 'missing_params', __( 'A plugin "slug" is required.', 'emcp-tools' ) );
		}
		$activate = ! empty( $input['activate'] );
		if ( $activate && ! current_user_can( 'activate_plugins' ) ) {
			return new \WP_Error( 'cannot_activate', __( 'You cannot activate plugins.', 'emcp-tools' ) );
		}
		$ready = EMCP_Tools_Package_Guard::filesystem_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$api = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		$skin     = EMCP_Tools_Package_Guard::make_skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result || null === $result ) {
			return new \WP_Error( 'install_failed', __( 'Plugin installation failed.', 'emcp-tools' ) );
		}
		$file      = (string) $upgrader->plugin_info();
		$activated = false;
		if ( $activate && '' !== $file ) {
			$act = activate_plugin( $file );
			$activated = ! is_wp_error( $act );
		}
		return array(
			'installed' => true,
			'activated' => $activated,
			'file'      => $file,
			'slug'      => $slug,
			'messages'  => EMCP_Tools_Package_Guard::skin_messages( $skin ),
		);
	}

	// -------------------------------------------------------------------
	// activate-plugin
	// -------------------------------------------------------------------

	private function register_activate_plugin(): void {
		$this->ability_names[] = 'emcp-tools/activate-plugin';
		emcp_tools_register_ability(
			'emcp-tools/activate-plugin',
			array(
				'label'               => __( 'Activate Plugin', 'emcp-tools' ),
				'description'         => __( 'Activates an installed plugin by its file path (e.g. "akismet/akismet.php") or folder slug.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_activate_plugin' ),
				'permission_callback' => array( $this, 'can_activate' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'plugin' => array( 'type' => 'string', 'description' => __( 'Plugin file (folder/file.php) or folder slug.', 'emcp-tools' ) ) ),
					'required'   => array( 'plugin' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'success' => array( 'type' => 'boolean' ), 'plugin' => array( 'type' => 'string' ), 'active' => array( 'type' => 'boolean' ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_activate_plugin( $input ) {
		$file = $this->resolve_plugin_file( $input['plugin'] ?? '' );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$res = activate_plugin( $file );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array( 'success' => true, 'plugin' => $file, 'active' => EMCP_Tools_Package_Guard::is_active_plugin( $file ) );
	}

	// -------------------------------------------------------------------
	// deactivate-plugin
	// -------------------------------------------------------------------

	private function register_deactivate_plugin(): void {
		$this->ability_names[] = 'emcp-tools/deactivate-plugin';
		emcp_tools_register_ability(
			'emcp-tools/deactivate-plugin',
			array(
				'label'               => __( 'Deactivate Plugin', 'emcp-tools' ),
				'description'         => __( 'Deactivates an active plugin. Refuses to deactivate EMCP Tools itself or Elementor (its hard dependency).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_deactivate_plugin' ),
				'permission_callback' => array( $this, 'can_activate' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'plugin' => array( 'type' => 'string', 'description' => __( 'Plugin file or folder slug.', 'emcp-tools' ) ) ),
					'required'   => array( 'plugin' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'success' => array( 'type' => 'boolean' ), 'plugin' => array( 'type' => 'string' ), 'active' => array( 'type' => 'boolean' ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_deactivate_plugin( $input ) {
		$file = $this->resolve_plugin_file( $input['plugin'] ?? '' );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( EMCP_Tools_Package_Guard::is_protected_plugin( $file ) ) {
			return new \WP_Error( 'protected_plugin', sprintf( /* translators: %s: plugin file */ __( '"%s" is protected and cannot be deactivated via MCP (it would break EMCP Tools or Elementor).', 'emcp-tools' ), $file ) );
		}
		deactivate_plugins( array( $file ) );
		return array( 'success' => true, 'plugin' => $file, 'active' => EMCP_Tools_Package_Guard::is_active_plugin( $file ) );
	}

	// -------------------------------------------------------------------
	// update-plugin
	// -------------------------------------------------------------------

	private function register_update_plugin(): void {
		$this->ability_names[] = 'emcp-tools/update-plugin';
		emcp_tools_register_ability(
			'emcp-tools/update-plugin',
			array(
				'label'               => __( 'Update Plugin', 'emcp-tools' ),
				'description'         => __( 'Updates an installed plugin to the latest wordpress.org version. Reports up_to_date when no update is pending.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_plugin' ),
				'permission_callback' => array( $this, 'can_update' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'plugin' => array( 'type' => 'string', 'description' => __( 'Plugin file or folder slug.', 'emcp-tools' ) ) ),
					'required'   => array( 'plugin' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'success' => array( 'type' => 'boolean' ), 'up_to_date' => array( 'type' => 'boolean' ),
					'plugin' => array( 'type' => 'string' ), 'old_version' => array( 'type' => 'string' ),
					'new_version' => array( 'type' => 'string' ), 'messages' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_update_plugin( $input ) {
		$file = $this->resolve_plugin_file( $input['plugin'] ?? '' );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$ready = EMCP_Tools_Package_Guard::filesystem_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}
		$updates = get_site_transient( 'update_plugins' );
		$resp    = ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ) ? $updates->response : array();
		$all     = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$old     = (string) ( $all[ $file ]['Version'] ?? '' );
		if ( ! isset( $resp[ $file ] ) ) {
			return array( 'success' => true, 'up_to_date' => true, 'plugin' => $file, 'old_version' => $old, 'new_version' => $old, 'messages' => array() );
		}
		$skin     = EMCP_Tools_Package_Guard::make_skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade( $file );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new \WP_Error( 'update_failed', __( 'Plugin update failed.', 'emcp-tools' ) );
		}
		return array(
			'success'     => true,
			'up_to_date'  => false,
			'plugin'      => $file,
			'old_version' => $old,
			'new_version' => (string) ( $resp[ $file ]->new_version ?? '' ),
			'messages'    => EMCP_Tools_Package_Guard::skin_messages( $skin ),
		);
	}

	// -------------------------------------------------------------------
	// delete-plugin
	// -------------------------------------------------------------------

	private function register_delete_plugin(): void {
		$this->ability_names[] = 'emcp-tools/delete-plugin';
		emcp_tools_register_ability(
			'emcp-tools/delete-plugin',
			array(
				'label'               => __( 'Delete Plugin', 'emcp-tools' ),
				'description'         => __( 'Permanently deletes an installed plugin. Destructive. Refuses protected plugins (EMCP Tools / Elementor) and any active plugin (deactivate it first).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_delete_plugin' ),
				'permission_callback' => array( $this, 'can_delete' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'plugin' => array( 'type' => 'string', 'description' => __( 'Plugin file or folder slug.', 'emcp-tools' ) ) ),
					'required'   => array( 'plugin' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'deleted' => array( 'type' => 'boolean' ), 'plugin' => array( 'type' => 'string' ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_delete_plugin( $input ) {
		$file = $this->resolve_plugin_file( $input['plugin'] ?? '' );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( EMCP_Tools_Package_Guard::is_protected_plugin( $file ) ) {
			return new \WP_Error( 'protected_plugin', sprintf( /* translators: %s: plugin file */ __( '"%s" is protected and cannot be deleted via MCP.', 'emcp-tools' ), $file ) );
		}
		if ( EMCP_Tools_Package_Guard::is_active_plugin( $file ) ) {
			return new \WP_Error( 'plugin_active', __( 'Deactivate the plugin before deleting it.', 'emcp-tools' ) );
		}
		$ready = EMCP_Tools_Package_Guard::filesystem_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$res = delete_plugins( array( $file ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array( 'deleted' => (bool) $res, 'plugin' => $file );
	}

	// -------------------------------------------------------------------
	// helper
	// -------------------------------------------------------------------

	/**
	 * Resolve a user-supplied plugin reference (file path or folder slug) to an
	 * installed plugin file. Returns WP_Error('plugin_not_found') if unknown.
	 *
	 * @param string $ref
	 * @return string|\WP_Error
	 */
	private function resolve_plugin_file( $ref ) {
		$ref = (string) $ref;
		if ( '' === $ref ) {
			return new \WP_Error( 'missing_params', __( 'A "plugin" reference is required.', 'emcp-tools' ) );
		}
		EMCP_Tools_Package_Guard::load_upgrader_deps();
		$all = function_exists( 'get_plugins' ) ? get_plugins() : array();
		if ( isset( $all[ $ref ] ) ) {
			return $ref;
		}
		// Treat $ref as a folder slug and match the first file in that folder.
		foreach ( array_keys( $all ) as $file ) {
			if ( dirname( (string) $file ) === $ref ) {
				return (string) $file;
			}
		}
		return new \WP_Error( 'plugin_not_found', sprintf( /* translators: %s: plugin reference */ __( 'No installed plugin matches "%s".', 'emcp-tools' ), $ref ) );
	}
```

- [ ] **Step 4: Run — expect PASS (all PluginToolsTest)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/packages/PluginToolsTest.php`
Expected: PASS (18 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/abilities/class-plugin-abilities.php tests/unit/packages/PluginToolsTest.php
git commit -m "feat(plugins): install/activate/deactivate/update/delete with guardrails"
```

---

## Task 5: Theme abilities — discovery (list-themes, search-themes)

**Files:**
- Create: `includes/abilities/class-theme-abilities.php`
- Test: `tests/unit/packages/ThemeToolsTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/unit/packages/ThemeToolsTest.php`:

```php
<?php
/**
 * Execute-path tests for the Themes tools.
 * @group packages
 * @package EMCP_Tools\Tests\Packages
 */
namespace EMCP_Tools\Tests\Packages;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class ThemeToolsTest extends Ability_Test_Case {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_themes'] = array(
			'twentytwentyfour' => new \WP_Theme( 'twentytwentyfour', array( 'Name' => 'Twenty Twenty-Four', 'Version' => '1.2' ) ),
			'astra'            => new \WP_Theme( 'astra', array( 'Name' => 'Astra', 'Version' => '4.6' ) ),
			'astra-child'      => new \WP_Theme( 'astra-child', array( 'Name' => 'Astra Child', 'Version' => '1.0', 'Template' => 'astra' ) ),
		);
		$GLOBALS['_wp_active_stylesheet'] = 'astra-child';
		$GLOBALS['_wp_active_template']   = 'astra';
		$GLOBALS['_wp_fs_method']         = 'direct';
		$GLOBALS['_wp_deleted_themes']    = array();
		$GLOBALS['_wp_installed_packages'] = array();
		$GLOBALS['_wp_upgraded']          = array();
		if ( ! defined( 'EMCP_TOOLS_BASENAME' ) ) {
			define( 'EMCP_TOOLS_BASENAME', 'elementor-mcp/emcp-tools.php' );
		}
	}

	private function themes(): \EMCP_Tools_Theme_Abilities {
		$a = new \EMCP_Tools_Theme_Abilities();
		$a->register();
		return $a;
	}

	/** @test */
	public function test_registers_six_tools(): void {
		$names = $this->themes()->get_ability_names();
		foreach ( array( 'list-themes', 'search-themes', 'install-theme', 'switch-theme', 'update-theme', 'delete-theme' ) as $slug ) {
			$this->assertContains( 'emcp-tools/' . $slug, $names );
		}
		$this->assertCount( 6, $names );
	}

	/** @test */
	public function test_list_themes_marks_active_and_parent(): void {
		$out  = $this->themes()->execute_list_themes( array() );
		$this->assertResultHasKey( $out, 'themes' );
		$rows = array();
		foreach ( $out['themes'] as $r ) { $rows[ $r['stylesheet'] ] = $r; }
		$this->assertTrue( $rows['astra-child']['is_active'] );
		$this->assertSame( 'astra', $rows['astra-child']['parent'] );
		$this->assertFalse( $rows['astra']['is_active'] );
	}

	/** @test */
	public function test_search_themes_returns_rows(): void {
		$GLOBALS['_wp_themes_api_query'] = array(
			(object) array( 'slug' => 'hello-elementor', 'name' => 'Hello Elementor', 'version' => '3.0', 'rating' => 98, 'requires' => '6.0' ),
		);
		$out = $this->themes()->execute_search_themes( array( 'search' => 'elementor' ) );
		$this->assertSame( 'hello-elementor', $out['results'][0]['slug'] );
	}

	/** @test */
	public function test_search_themes_requires_query(): void {
		$this->assertWPError( $this->themes()->execute_search_themes( array() ), 'missing_params' );
	}
}
```

- [ ] **Step 2: Run — expect FAIL (class not found)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/packages/ThemeToolsTest.php`
Expected: FAIL — `Class "EMCP_Tools_Theme_Abilities" not found`.

- [ ] **Step 3: Create the class with the two discovery tools**

Create `includes/abilities/class-theme-abilities.php`:

```php
<?php
/**
 * WordPress Theme lifecycle MCP abilities.
 *
 * Six tools to discover, install (wordpress.org only), switch (activate),
 * update, and delete themes. Built on WP core's theme + upgrader APIs and
 * guarded by EMCP_Tools_Package_Guard. Reads ship enabled; the four mutation
 * tools ship disabled-by-default.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the theme lifecycle abilities.
 *
 * @since 3.0.0
 */
class EMCP_Tools_Theme_Abilities {

	/** @since 3.0.0 @var string[] */
	private $ability_names = array();

	/** @since 3.0.0 @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/** @since 3.0.0 */
	public function register(): void {
		$this->register_list_themes();
		$this->register_search_themes();
		$this->register_install_theme();
		$this->register_switch_theme();
		$this->register_update_theme();
		$this->register_delete_theme();
	}

	public function can_list(): bool { return current_user_can( 'switch_themes' ); }
	public function can_install(): bool { return current_user_can( 'install_themes' ); }
	public function can_switch(): bool { return current_user_can( 'switch_themes' ); }
	public function can_update(): bool { return current_user_can( 'update_themes' ); }
	public function can_delete(): bool { return current_user_can( 'delete_themes' ); }

	// -------------------------------------------------------------------
	// list-themes
	// -------------------------------------------------------------------

	private function register_list_themes(): void {
		$this->ability_names[] = 'emcp-tools/list-themes';
		emcp_tools_register_ability(
			'emcp-tools/list-themes',
			array(
				'label'               => __( 'List Themes', 'emcp-tools' ),
				'description'         => __( 'Lists installed themes with version, active status, parent (for child themes), and whether an update is available.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_themes' ),
				'permission_callback' => array( $this, 'can_list' ),
				'input_schema'        => array( 'type' => 'object', 'properties' => new \stdClass() ),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'themes' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array
	 */
	public function execute_list_themes( $input ): array {
		EMCP_Tools_Package_Guard::load_upgrader_deps();
		$active  = function_exists( 'get_stylesheet' ) ? (string) get_stylesheet() : '';
		$updates = get_site_transient( 'update_themes' );
		$resp    = ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ) ? $updates->response : array();
		$themes  = function_exists( 'wp_get_themes' ) ? wp_get_themes() : array();

		$rows = array();
		foreach ( $themes as $stylesheet => $theme ) {
			$stylesheet = (string) $stylesheet;
			$parent     = ( is_object( $theme ) && method_exists( $theme, 'get_template' ) ) ? (string) $theme->get_template() : $stylesheet;
			$rows[]     = array(
				'stylesheet'       => $stylesheet,
				'name'             => is_object( $theme ) ? (string) $theme->get( 'Name' ) : $stylesheet,
				'version'          => is_object( $theme ) ? (string) $theme->get( 'Version' ) : '',
				'parent'           => ( $parent !== $stylesheet ) ? $parent : '',
				'is_active'        => ( $stylesheet === $active ),
				'update_available' => isset( $resp[ $stylesheet ] ),
				'new_version'      => isset( $resp[ $stylesheet ]['new_version'] ) ? (string) $resp[ $stylesheet ]['new_version'] : '',
			);
		}
		return array( 'themes' => $rows );
	}

	// -------------------------------------------------------------------
	// search-themes
	// -------------------------------------------------------------------

	private function register_search_themes(): void {
		$this->ability_names[] = 'emcp-tools/search-themes';
		emcp_tools_register_ability(
			'emcp-tools/search-themes',
			array(
				'label'               => __( 'Search Themes', 'emcp-tools' ),
				'description'         => __( 'Searches the wordpress.org theme directory by keyword. Returns slug, name, version, rating, and requirements. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_search_themes' ),
				'permission_callback' => array( $this, 'can_install' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'   => array( 'type' => 'string', 'description' => __( 'Keyword(s) to search the .org directory.', 'emcp-tools' ) ),
						'per_page' => array( 'type' => 'integer', 'description' => __( '1-50. Default: 10.', 'emcp-tools' ) ),
					),
					'required'   => array( 'search' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'results' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_search_themes( $input ) {
		$search = sanitize_text_field( $input['search'] ?? '' );
		if ( '' === $search ) {
			return new \WP_Error( 'missing_params', __( 'A "search" keyword is required.', 'emcp-tools' ) );
		}
		EMCP_Tools_Package_Guard::load_upgrader_deps();
		$per_page = max( 1, min( 50, absint( $input['per_page'] ?? 10 ) ) );
		$api      = themes_api( 'query_themes', array( 'search' => $search, 'per_page' => $per_page, 'fields' => array( 'description' => false ) ) );
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		$rows = array();
		foreach ( (array) ( $api->themes ?? array() ) as $t ) {
			$t      = (object) $t;
			$rows[] = array(
				'slug'     => (string) ( $t->slug ?? '' ),
				'name'     => wp_strip_all_tags( (string) ( $t->name ?? '' ) ),
				'version'  => (string) ( $t->version ?? '' ),
				'rating'   => (int) ( $t->rating ?? 0 ),
				'requires' => (string) ( $t->requires ?? '' ),
			);
		}
		return array( 'results' => $rows );
	}
}
```

- [ ] **Step 4: Run — expect FAIL only on count-6 (registers 2); rest PASS**

As in Task 3, temporarily reduce `test_registers_six_tools` to assert the 2 discovery tools are present (Task 6 restores the full count). Then:

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/packages/ThemeToolsTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Autoloader entry + commit**

Add `'EMCP_Tools_Theme_Abilities' => 'includes/abilities/class-theme-abilities.php'` to the `tests/bootstrap.php` autoloader map.

```bash
git add includes/abilities/class-theme-abilities.php tests/unit/packages/ThemeToolsTest.php tests/bootstrap.php
git commit -m "feat(themes): list-themes + search-themes discovery tools"
```

---

## Task 6: Theme abilities — lifecycle (install/switch/update/delete)

**Files:**
- Modify: `includes/abilities/class-theme-abilities.php`
- Test: `tests/unit/packages/ThemeToolsTest.php` (restore count-6; append)

- [ ] **Step 1: Restore the full count assertion + append lifecycle tests**

Restore `test_registers_six_tools` to its full form. Append:

```php
	/** @test */
	public function test_install_theme_installs_and_optionally_activates(): void {
		$GLOBALS['_wp_upgrader_theme_info'] = 'hello-elementor';
		$out = $this->themes()->execute_install_theme( array( 'slug' => 'hello-elementor', 'activate' => true ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['installed'] );
		$this->assertTrue( $out['activated'] );
		$this->assertSame( 'hello-elementor', $GLOBALS['_wp_switched_theme'] );
	}

	/** @test */
	public function test_install_theme_requires_slug(): void {
		$this->assertWPError( $this->themes()->execute_install_theme( array() ), 'missing_params' );
	}

	/** @test */
	public function test_install_theme_blocked_when_filesystem_not_direct(): void {
		$GLOBALS['_wp_fs_method'] = 'ftpext';
		$this->assertWPError( $this->themes()->execute_install_theme( array( 'slug' => 'astra' ) ), 'filesystem_unavailable' );
	}

	/** @test */
	public function test_switch_theme_activates_installed(): void {
		$out = $this->themes()->execute_switch_theme( array( 'stylesheet' => 'twentytwentyfour' ) );
		$this->assertNotWPError( $out );
		$this->assertSame( 'twentytwentyfour', $GLOBALS['_wp_switched_theme'] );
	}

	/** @test */
	public function test_switch_theme_unknown_errors(): void {
		$this->assertWPError( $this->themes()->execute_switch_theme( array( 'stylesheet' => 'ghost' ) ), 'theme_not_found' );
	}

	/** @test */
	public function test_delete_theme_refuses_active(): void {
		$this->assertWPError( $this->themes()->execute_delete_theme( array( 'stylesheet' => 'astra-child' ) ), 'theme_active' );
	}

	/** @test */
	public function test_delete_theme_refuses_active_parent(): void {
		$this->assertWPError( $this->themes()->execute_delete_theme( array( 'stylesheet' => 'astra' ) ), 'theme_active' );
	}

	/** @test */
	public function test_delete_theme_removes_inactive(): void {
		$out = $this->themes()->execute_delete_theme( array( 'stylesheet' => 'twentytwentyfour' ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['deleted'] );
		$this->assertContains( 'twentytwentyfour', $GLOBALS['_wp_deleted_themes'] );
	}

	/** @test */
	public function test_update_theme_reports_up_to_date(): void {
		$GLOBALS['_wp_site_transients']['update_themes'] = (object) array( 'response' => array() );
		$out = $this->themes()->execute_update_theme( array( 'stylesheet' => 'astra' ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['up_to_date'] );
	}

	/** @test */
	public function test_update_theme_runs_when_available(): void {
		$GLOBALS['_wp_site_transients']['update_themes'] = (object) array(
			'response' => array( 'astra' => array( 'new_version' => '4.7' ) ),
		);
		$out = $this->themes()->execute_update_theme( array( 'stylesheet' => 'astra' ) );
		$this->assertNotWPError( $out );
		$this->assertFalse( $out['up_to_date'] );
		$this->assertContains( 'astra', $GLOBALS['_wp_upgraded'] );
	}
```

- [ ] **Step 2: Run — expect FAIL (undefined execute_install_theme etc.)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/packages/ThemeToolsTest.php`
Expected: FAIL — undefined methods.

- [ ] **Step 3: Add the four lifecycle register_* + execute_* methods + helper**

In `includes/abilities/class-theme-abilities.php`, before the final class `}`, add:

```php
	// -------------------------------------------------------------------
	// install-theme
	// -------------------------------------------------------------------

	private function register_install_theme(): void {
		$this->ability_names[] = 'emcp-tools/install-theme';
		emcp_tools_register_ability(
			'emcp-tools/install-theme',
			array(
				'label'               => __( 'Install Theme', 'emcp-tools' ),
				'description'         => __( 'Installs a theme from the wordpress.org directory by slug. Optionally activates it. Source is always wordpress.org.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_install_theme' ),
				'permission_callback' => array( $this, 'can_install' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'slug'     => array( 'type' => 'string', 'description' => __( 'wordpress.org theme slug.', 'emcp-tools' ) ),
						'activate' => array( 'type' => 'boolean', 'description' => __( 'Activate after install. Default: false.', 'emcp-tools' ) ),
					),
					'required'   => array( 'slug' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'installed' => array( 'type' => 'boolean' ), 'activated' => array( 'type' => 'boolean' ),
					'stylesheet' => array( 'type' => 'string' ), 'messages' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_install_theme( $input ) {
		$slug = sanitize_key( $input['slug'] ?? '' );
		if ( '' === $slug ) {
			return new \WP_Error( 'missing_params', __( 'A theme "slug" is required.', 'emcp-tools' ) );
		}
		$activate = ! empty( $input['activate'] );
		if ( $activate && ! current_user_can( 'switch_themes' ) ) {
			return new \WP_Error( 'cannot_switch', __( 'You cannot switch themes.', 'emcp-tools' ) );
		}
		$ready = EMCP_Tools_Package_Guard::filesystem_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$api = themes_api( 'theme_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		$skin     = EMCP_Tools_Package_Guard::make_skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result || null === $result ) {
			return new \WP_Error( 'install_failed', __( 'Theme installation failed.', 'emcp-tools' ) );
		}
		$activated = false;
		if ( $activate ) {
			switch_theme( $slug );
			$activated = true;
		}
		return array(
			'installed'  => true,
			'activated'  => $activated,
			'stylesheet' => $slug,
			'messages'   => EMCP_Tools_Package_Guard::skin_messages( $skin ),
		);
	}

	// -------------------------------------------------------------------
	// switch-theme
	// -------------------------------------------------------------------

	private function register_switch_theme(): void {
		$this->ability_names[] = 'emcp-tools/switch-theme';
		emcp_tools_register_ability(
			'emcp-tools/switch-theme',
			array(
				'label'               => __( 'Switch Theme', 'emcp-tools' ),
				'description'         => __( 'Activates an installed theme by its stylesheet (folder) name. Refuses a theme that is missing or has load errors.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_switch_theme' ),
				'permission_callback' => array( $this, 'can_switch' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'stylesheet' => array( 'type' => 'string', 'description' => __( 'Theme stylesheet/folder name.', 'emcp-tools' ) ) ),
					'required'   => array( 'stylesheet' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'success' => array( 'type' => 'boolean' ), 'stylesheet' => array( 'type' => 'string' ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_switch_theme( $input ) {
		$theme = $this->resolve_theme( $input['stylesheet'] ?? '' );
		if ( is_wp_error( $theme ) ) {
			return $theme;
		}
		$stylesheet = $theme->get_stylesheet();
		if ( $theme->errors() ) {
			return new \WP_Error( 'theme_broken', __( 'That theme has load errors and cannot be activated.', 'emcp-tools' ) );
		}
		switch_theme( $stylesheet );
		return array( 'success' => true, 'stylesheet' => $stylesheet );
	}

	// -------------------------------------------------------------------
	// update-theme
	// -------------------------------------------------------------------

	private function register_update_theme(): void {
		$this->ability_names[] = 'emcp-tools/update-theme';
		emcp_tools_register_ability(
			'emcp-tools/update-theme',
			array(
				'label'               => __( 'Update Theme', 'emcp-tools' ),
				'description'         => __( 'Updates an installed theme to the latest wordpress.org version. Reports up_to_date when no update is pending.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_theme' ),
				'permission_callback' => array( $this, 'can_update' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'stylesheet' => array( 'type' => 'string', 'description' => __( 'Theme stylesheet/folder name.', 'emcp-tools' ) ) ),
					'required'   => array( 'stylesheet' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'success' => array( 'type' => 'boolean' ), 'up_to_date' => array( 'type' => 'boolean' ),
					'stylesheet' => array( 'type' => 'string' ), 'old_version' => array( 'type' => 'string' ),
					'new_version' => array( 'type' => 'string' ), 'messages' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_update_theme( $input ) {
		$theme = $this->resolve_theme( $input['stylesheet'] ?? '' );
		if ( is_wp_error( $theme ) ) {
			return $theme;
		}
		$stylesheet = $theme->get_stylesheet();
		$ready      = EMCP_Tools_Package_Guard::filesystem_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		if ( function_exists( 'wp_update_themes' ) ) {
			wp_update_themes();
		}
		$updates = get_site_transient( 'update_themes' );
		$resp    = ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ) ? $updates->response : array();
		$old     = (string) $theme->get( 'Version' );
		if ( ! isset( $resp[ $stylesheet ] ) ) {
			return array( 'success' => true, 'up_to_date' => true, 'stylesheet' => $stylesheet, 'old_version' => $old, 'new_version' => $old, 'messages' => array() );
		}
		$skin     = EMCP_Tools_Package_Guard::make_skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->upgrade( $stylesheet );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new \WP_Error( 'update_failed', __( 'Theme update failed.', 'emcp-tools' ) );
		}
		return array(
			'success'     => true,
			'up_to_date'  => false,
			'stylesheet'  => $stylesheet,
			'old_version' => $old,
			'new_version' => (string) ( $resp[ $stylesheet ]['new_version'] ?? '' ),
			'messages'    => EMCP_Tools_Package_Guard::skin_messages( $skin ),
		);
	}

	// -------------------------------------------------------------------
	// delete-theme
	// -------------------------------------------------------------------

	private function register_delete_theme(): void {
		$this->ability_names[] = 'emcp-tools/delete-theme';
		emcp_tools_register_ability(
			'emcp-tools/delete-theme',
			array(
				'label'               => __( 'Delete Theme', 'emcp-tools' ),
				'description'         => __( 'Permanently deletes an installed theme. Destructive. Refuses the active theme and the parent of the active theme.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_delete_theme' ),
				'permission_callback' => array( $this, 'can_delete' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'stylesheet' => array( 'type' => 'string', 'description' => __( 'Theme stylesheet/folder name.', 'emcp-tools' ) ) ),
					'required'   => array( 'stylesheet' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'deleted' => array( 'type' => 'boolean' ), 'stylesheet' => array( 'type' => 'string' ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_delete_theme( $input ) {
		$theme = $this->resolve_theme( $input['stylesheet'] ?? '' );
		if ( is_wp_error( $theme ) ) {
			return $theme;
		}
		$stylesheet = $theme->get_stylesheet();
		if ( in_array( $stylesheet, EMCP_Tools_Package_Guard::active_theme_stylesheets(), true ) ) {
			return new \WP_Error( 'theme_active', __( 'Cannot delete the active theme or the parent of the active theme. Switch themes first.', 'emcp-tools' ) );
		}
		$ready = EMCP_Tools_Package_Guard::filesystem_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$res = delete_theme( $stylesheet );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array( 'deleted' => (bool) $res, 'stylesheet' => $stylesheet );
	}

	// -------------------------------------------------------------------
	// helper
	// -------------------------------------------------------------------

	/**
	 * Resolve a stylesheet to an installed WP_Theme, or WP_Error if missing.
	 *
	 * @param string $stylesheet
	 * @return \WP_Theme|\WP_Error
	 */
	private function resolve_theme( $stylesheet ) {
		$stylesheet = sanitize_key( (string) $stylesheet );
		if ( '' === $stylesheet ) {
			return new \WP_Error( 'missing_params', __( 'A "stylesheet" is required.', 'emcp-tools' ) );
		}
		EMCP_Tools_Package_Guard::load_upgrader_deps();
		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme( $stylesheet ) : null;
		if ( ! $theme || ! ( is_object( $theme ) && method_exists( $theme, 'exists' ) ? $theme->exists() : false ) ) {
			return new \WP_Error( 'theme_not_found', sprintf( /* translators: %s: stylesheet */ __( 'No installed theme named "%s".', 'emcp-tools' ), $stylesheet ) );
		}
		return $theme;
	}
```

> Note on `sanitize_key` for slugs: WordPress slugs may contain hyphens, which `sanitize_key` preserves (it lowercases and strips to `[a-z0-9_-]`). Theme stylesheets like `astra-child` survive intact. Plugin install slugs likewise.

- [ ] **Step 4: Run — expect PASS (all ThemeToolsTest)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/packages/ThemeToolsTest.php`
Expected: PASS (15 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/abilities/class-theme-abilities.php tests/unit/packages/ThemeToolsTest.php
git commit -m "feat(themes): install/switch/update/delete with active-theme guardrails"
```

---

## Task 7: Capability tests for both groups

**Files:**
- Create: `tests/unit/capabilities/PluginCapabilityTest.php`, `tests/unit/capabilities/ThemeCapabilityTest.php`

- [ ] **Step 1: Write the plugin capability test**

Create `tests/unit/capabilities/PluginCapabilityTest.php`:

```php
<?php
/**
 * Capability gating for the Plugins tools.
 * @group capabilities
 * @group packages
 * @package EMCP_Tools\Tests\Capabilities
 */
namespace EMCP_Tools\Tests\Capabilities;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class PluginCapabilityTest extends Ability_Test_Case {
	private \EMCP_Tools_Plugin_Abilities $a;

	protected function setUp(): void {
		parent::setUp();
		$this->a = new \EMCP_Tools_Plugin_Abilities();
		$this->a->register();
	}

	/** @test */
	public function test_list_requires_activate_plugins(): void {
		$this->allow_caps( 'edit_posts' );
		$this->assertFalse( $this->a->can_list() );
		$this->allow_caps( 'activate_plugins' );
		$this->assertTrue( $this->a->can_list() );
	}

	/** @test */
	public function test_install_requires_install_plugins(): void {
		$this->allow_caps( 'activate_plugins' );
		$this->assertFalse( $this->a->can_install() );
		$this->allow_caps( 'install_plugins' );
		$this->assertTrue( $this->a->can_install() );
	}

	/** @test */
	public function test_update_requires_update_plugins(): void {
		$this->allow_caps( 'activate_plugins' );
		$this->assertFalse( $this->a->can_update() );
		$this->allow_caps( 'update_plugins' );
		$this->assertTrue( $this->a->can_update() );
	}

	/** @test */
	public function test_delete_requires_delete_plugins(): void {
		$this->allow_caps( 'activate_plugins' );
		$this->assertFalse( $this->a->can_delete() );
		$this->allow_caps( 'delete_plugins' );
		$this->assertTrue( $this->a->can_delete() );
	}
}
```

- [ ] **Step 2: Write the theme capability test**

Create `tests/unit/capabilities/ThemeCapabilityTest.php`:

```php
<?php
/**
 * Capability gating for the Themes tools.
 * @group capabilities
 * @group packages
 * @package EMCP_Tools\Tests\Capabilities
 */
namespace EMCP_Tools\Tests\Capabilities;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class ThemeCapabilityTest extends Ability_Test_Case {
	private \EMCP_Tools_Theme_Abilities $a;

	protected function setUp(): void {
		parent::setUp();
		$this->a = new \EMCP_Tools_Theme_Abilities();
		$this->a->register();
	}

	/** @test */
	public function test_list_requires_switch_themes(): void {
		$this->allow_caps( 'edit_posts' );
		$this->assertFalse( $this->a->can_list() );
		$this->allow_caps( 'switch_themes' );
		$this->assertTrue( $this->a->can_list() );
	}

	/** @test */
	public function test_install_requires_install_themes(): void {
		$this->allow_caps( 'switch_themes' );
		$this->assertFalse( $this->a->can_install() );
		$this->allow_caps( 'install_themes' );
		$this->assertTrue( $this->a->can_install() );
	}

	/** @test */
	public function test_update_requires_update_themes(): void {
		$this->allow_caps( 'switch_themes' );
		$this->assertFalse( $this->a->can_update() );
		$this->allow_caps( 'update_themes' );
		$this->assertTrue( $this->a->can_update() );
	}

	/** @test */
	public function test_delete_requires_delete_themes(): void {
		$this->allow_caps( 'switch_themes' );
		$this->assertFalse( $this->a->can_delete() );
		$this->allow_caps( 'delete_themes' );
		$this->assertTrue( $this->a->can_delete() );
	}
}
```

- [ ] **Step 3: Run — expect PASS (8 tests)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/capabilities/PluginCapabilityTest.php tests/unit/capabilities/ThemeCapabilityTest.php`
Expected: PASS (8 tests).

- [ ] **Step 4: Commit**

```bash
git add tests/unit/capabilities/PluginCapabilityTest.php tests/unit/capabilities/ThemeCapabilityTest.php
git commit -m "test(packages): per-tool capability gating for plugins + themes"
```

---

## Task 8: Wire both groups into the runtime

**Files:**
- Modify: `includes/class-bootstrap.php`, `includes/abilities/class-ability-registrar.php`, `includes/class-plugin.php`

- [ ] **Step 1: require the three new files in the bootstrap**

In `includes/class-bootstrap.php`, after the line:

```php
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-settings-abilities.php';
```

add:

```php
		require_once EMCP_TOOLS_DIR . 'includes/class-package-guard.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-plugin-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-theme-abilities.php';
```

- [ ] **Step 2: register both groups in the registrar**

In `includes/abilities/class-ability-registrar.php`, after the settings group block (`$settings = new EMCP_Tools_Settings_Abilities(); … $settings->get_ability_names() );`), add:

```php

			// WordPress Plugins & Themes abilities (discover/install/update/
			// activate/delete). Unconditional — pure WordPress, always available.
			$plugins = new EMCP_Tools_Plugin_Abilities();
			$plugins->register();
			$this->ability_names = array_merge( $this->ability_names, $plugins->get_ability_names() );

			$themes = new EMCP_Tools_Theme_Abilities();
			$themes->register();
			$this->ability_names = array_merge( $this->ability_names, $themes->get_ability_names() );
```

- [ ] **Step 3: add the read slugs to essentials**

In `includes/class-plugin.php`, inside `get_essential_tool_slugs()`, after the settings block (`'emcp-tools/update-settings',`), add:

```php

			// WordPress plugins & themes (2 reads — writes opt-in only).
			'emcp-tools/list-plugins',
			'emcp-tools/list-themes',
```

- [ ] **Step 4: Run the full suite + lint**

Run:
```
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe -l includes/class-bootstrap.php
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe -l includes/abilities/class-ability-registrar.php
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe -l includes/class-plugin.php
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist
```
Expected: all "No syntax errors"; suite green.

- [ ] **Step 5: Commit**

```bash
git add includes/class-bootstrap.php includes/abilities/class-ability-registrar.php includes/class-plugin.php
git commit -m "feat(packages): wire plugin + theme ability groups into bootstrap, registrar, essentials"
```

---

## Task 9: Admin catalog category + disabled-by-default seeding + phpunit suite

**Files:**
- Modify: `includes/admin/class-admin.php` (catalog category; `DEFAULTS_VERSION` 5→6; `package_write_tool_slugs()`; v6 seed step)
- Modify: `phpunit.xml` (add `Packages` testsuite)

- [ ] **Step 1: Add the "Plugins & Themes" catalog category**

In `includes/admin/class-admin.php`, inside `get_tool_catalog()`, after the `'wp_settings' => array( … ),` category, insert:

```php
			'wp_packages'      => array(
				'label' => __( 'Plugins & Themes', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-plugins'      => array(
						'label'       => __( 'List Plugins', 'emcp-tools' ),
						'description' => __( 'Lists installed plugins, status, versions, and updates.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/search-plugins'    => array(
						'label'       => __( 'Search Plugins', 'emcp-tools' ),
						'description' => __( 'Searches the wordpress.org plugin directory.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/install-plugin'    => array(
						'label'       => __( 'Install Plugin', 'emcp-tools' ),
						'description' => __( 'Installs a plugin from wordpress.org by slug.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/activate-plugin'   => array(
						'label'       => __( 'Activate Plugin', 'emcp-tools' ),
						'description' => __( 'Activates an installed plugin.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/deactivate-plugin' => array(
						'label'       => __( 'Deactivate Plugin', 'emcp-tools' ),
						'description' => __( 'Deactivates a plugin (never EMCP Tools or Elementor).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-plugin'     => array(
						'label'       => __( 'Update Plugin', 'emcp-tools' ),
						'description' => __( 'Updates a plugin to the latest wordpress.org version.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-plugin'     => array(
						'label'       => __( 'Delete Plugin', 'emcp-tools' ),
						'description' => __( 'Permanently deletes an inactive plugin.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/list-themes'       => array(
						'label'       => __( 'List Themes', 'emcp-tools' ),
						'description' => __( 'Lists installed themes, active status, and updates.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/search-themes'     => array(
						'label'       => __( 'Search Themes', 'emcp-tools' ),
						'description' => __( 'Searches the wordpress.org theme directory.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/install-theme'     => array(
						'label'       => __( 'Install Theme', 'emcp-tools' ),
						'description' => __( 'Installs a theme from wordpress.org by slug.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/switch-theme'      => array(
						'label'       => __( 'Switch Theme', 'emcp-tools' ),
						'description' => __( 'Activates an installed theme.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-theme'      => array(
						'label'       => __( 'Update Theme', 'emcp-tools' ),
						'description' => __( 'Updates a theme to the latest wordpress.org version.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-theme'      => array(
						'label'       => __( 'Delete Theme', 'emcp-tools' ),
						'description' => __( 'Permanently deletes an inactive theme.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
				),
			),
```

- [ ] **Step 2: Add `package_write_tool_slugs()` and bump DEFAULTS_VERSION**

In `includes/admin/class-admin.php`, change `const DEFAULTS_VERSION = 5;` to `const DEFAULTS_VERSION = 6;`.

Add this static method next to `php_snippet_tool_slugs()`:

```php
	/**
	 * The 9 Plugins & Themes mutation tool slugs. Powerful (install/delete/
	 * activate), so they ship disabled-by-default; reads stay enabled. The admin
	 * opts in on the Tools tab.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function package_write_tool_slugs(): array {
		return array(
			'emcp-tools/install-plugin',
			'emcp-tools/activate-plugin',
			'emcp-tools/deactivate-plugin',
			'emcp-tools/update-plugin',
			'emcp-tools/delete-plugin',
			'emcp-tools/install-theme',
			'emcp-tools/switch-theme',
			'emcp-tools/update-theme',
			'emcp-tools/delete-theme',
		);
	}
```

- [ ] **Step 3: Add the v6 seed step**

In `maybe_apply_default_disabled_tools()`, after the `if ( $applied < 5 ) { … }` block and before `$merged = …`, add:

```php
			// v6 — Plugins & Themes mutation tools ship disabled-by-default
			// (powerful: install/activate/deactivate/update/delete). Reads stay on.
			if ( $applied < 6 ) {
				$add = array_merge( $add, self::package_write_tool_slugs() );
			}
```

- [ ] **Step 4: Add the Packages testsuite to phpunit.xml**

In `phpunit.xml`, after the `<testsuite name="Settings">` block, add:

```xml
        <testsuite name="Packages">
            <directory>tests/unit/packages</directory>
        </testsuite>
```

- [ ] **Step 5: Lint + run both configs**

Run:
```
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe -l includes/admin/class-admin.php
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml
```
Expected: no syntax errors; both suites green (the admin catalog↔registry drift cross-check still passes because every new slug is in both the catalog and the registry).

- [ ] **Step 6: Commit**

```bash
git add includes/admin/class-admin.php phpunit.xml
git commit -m "feat(packages): admin Plugins & Themes category + writes disabled-by-default (defaults v6)"
```

---

## Task 10: Documentation (fold into the single v3.0.0 entry)

**Files:**
- Modify: `CHANGELOG.md`, `readme.txt`, `README.md`, `CLAUDE.md`

Per the single-v3.0.0 release decision (see `docs/superpowers/specs/2026-06-25-wp-plugins-themes-tools-design.md` and the release memory), this domain is NOT a new version — it folds into the existing `[3.0.0]` entry.

- [ ] **Step 1: CHANGELOG.md — add a bullet under the existing `[3.0.0]` → `### Added`**

In `CHANGELOG.md`, inside the existing `## [3.0.0]` entry's `### Added` list, add:

```markdown
- **WordPress Plugins & Themes tools — beyond Elementor, domain 3.** Thirteen MCP tools to discover and manage plugins and themes: `list-plugins`, `search-plugins`, `install-plugin`, `activate-plugin`, `deactivate-plugin`, `update-plugin`, `delete-plugin`, `list-themes`, `search-themes`, `install-theme`, `switch-theme`, `update-theme`, `delete-theme`. Installs come **only** from the wordpress.org directory (by slug; no arbitrary URLs). Guardrails: EMCP Tools and Elementor can never be deactivated or deleted; the active plugin/theme is protected from deletion; each operation checks its own capability; install/update/delete need a directly-writable filesystem (clear error instead of an FTP hang). The 2 read tools (`list-plugins`/`list-themes`) plus the two `search-*` are enabled by default; the **9 mutation tools ship disabled-by-default** (admin opts in on the Tools tab).
```

- [ ] **Step 2: readme.txt — add to the `= 3.0.0 =` block + the description + a Key Features bullet**

In `readme.txt`, inside the `= 3.0.0 =` changelog block, add:

```
* Added: WordPress Plugins & Themes tools — beyond Elementor, domain 3. Thirteen MCP tools to discover, install (wordpress.org only), update, activate/deactivate, and delete plugins and themes. Guardrails: EMCP Tools and Elementor can never be disabled/deleted; the active plugin/theme is protected; per-op capability gating; direct-filesystem-only. The 2 read tools + 2 search tools are enabled by default; the 9 mutation tools ship disabled-by-default.
```

In the description paragraph (the "As of v3.0.0 …" sentence), append a phrase noting the toolset now also manages plugins and themes. Add a Key Features bullet after the WordPress Settings one:

```
* **Plugins & Themes (beyond Elementor)** — Discover, install (wordpress.org only), update, activate/deactivate, and delete plugins and themes over MCP. EMCP Tools and Elementor are protected; install/update/delete are disabled-by-default and per-op capability-gated. (v3.0.0)
```

- [ ] **Step 3: README.md — add a Plugins & Themes feature bullet + a tool-table section**

In `README.md`, add a feature bullet alongside the WordPress Settings one:

```markdown
- **Plugins & Themes (beyond Elementor, domain 3)** — Discover, install (wordpress.org only), update, activate/deactivate, and delete plugins and themes over MCP. Strong guardrails (EMCP Tools + Elementor protected; per-op capability gating; direct-filesystem-only); the 9 mutation tools ship disabled-by-default. `manage_options`-class capabilities. (v3.0.0)
```

Add a tool-table section near the WordPress Settings section listing the 13 tools (slug + one-line purpose), mirroring that section's format.

- [ ] **Step 4: CLAUDE.md — overview sentence, status line, count, and a tool section**

In `CLAUDE.md`:
- Append to the v3.0.0 overview sentence (line ~7) a clause: "…and **domain 3 — 13 WordPress Plugins & Themes tools** (discover/install/update/activate/delete; wordpress.org-only; writes disabled-by-default)."
- Update the "Current status" line to mention the Plugins & Themes domain.
- Update the count narrative: the beyond-Elementor surface now adds 8 Content + 3 core/* + 2 Settings + 13 Plugins/Themes; of the Plugins/Themes 13, **4 are enabled by default** (`list-plugins`, `search-plugins`, `list-themes`, `search-themes`) and **9 ship disabled-by-default**. State counts as estimates pending the Task 11 live count.
- Add a "WordPress Plugins & Themes — domain 3 (13 tools)" section with the tool table + the safety model summary (wordpress.org-only, protected list, direct-FS, per-op caps, writes off by default).

- [ ] **Step 5: Commit**

```bash
git add CHANGELOG.md readme.txt README.md CLAUDE.md
git commit -m "docs: document WordPress Plugins & Themes tools (folded into v3.0.0)"
```

---

## Task 11: Thorough verification (PHP + live MCP + browser)

Verification only — no new production code unless a fix is needed.

- [ ] **Step 1: Full PHPUnit, both configs**

Run:
```
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml
```
Expected: both green, including the new Packages + capability tests.

- [ ] **Step 2: Live MCP `tools/list`**

Pipe `initialize` + `notifications/initialized` + `tools/list` JSON-RPC into:
```
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe /c/wp-cli/wp-cli.phar mcp-adapter serve --server=emcp-tools-server --user=admin --path=f:/laragon/www/msrplugins
```
(Write output to a Windows-absolute path readable by both bash and PHP.) Confirm all 13 `emcp-tools-{list,search,install,activate,deactivate,update,delete}-{plugin,theme}` style tool names appear. Note: the 9 write tools appear in the registry but are toggled off by the disabled-by-default option on a fresh install — if the admin defaults marker has already run on this dev site at < v6, they may show; that's fine for the registry check.

- [ ] **Step 3: Live MCP `tools/call` round-trips (non-destructive)**

1. `list-plugins` → confirm Elementor + EMCP show `is_protected:true`, Elementor `active:true`.
2. `list-themes` → confirm the active theme `is_active:true`.
3. `search-plugins {"search":"contact form"}` → returns slugs.
4. `deactivate-plugin {"plugin":"elementor/elementor.php"}` → confirm `protected_plugin` refusal (no state change; verify with `wp plugin list`).
5. `delete-plugin {"plugin":"<an active plugin>"}` → confirm `plugin_active` refusal.
6. (Optional, reversible) `install-plugin {"slug":"hello-dolly"}` then `delete-plugin {"plugin":"hello-dolly/hello.php"}` to verify a real round-trip, then confirm with `wp plugin list` that the site is back to its original set. Skip if the dev box can't reach wordpress.org.

- [ ] **Step 4: Browser check (Playwright + injected auth cookie, ignoreHTTPSErrors)**

Load EMCP Tools → Tools → confirm the "Plugins & Themes" category renders; the 4 read/search tools are **enabled**, the 9 mutation tools are present but **off** by default with the right badges (delete-* show "destructive"); no PHP errors/notices on the page.

- [ ] **Step 5: Report results**

Summarize PHPUnit counts (both configs), the live `tools/list` confirmation, the `tools/call` outcomes (with any installed test plugin removed), and the browser observation. Commit any fix needed with a clear message.
