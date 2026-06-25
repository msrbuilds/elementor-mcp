# WordPress Plugins & Themes Tools — Design

**Status:** Approved (design), ready for implementation plan.
**Date:** 2026-06-25
**Sub-project of:** "Beyond Elementor — general WordPress management via MCP." Domain 3 (Plugins & Themes). Content (domain 1) and Settings (domain 2) already implemented on the unreleased v3.0.0 line; this stacks onto the same line and merges with them as a single v3.0.0.

## Goal

Let an AI agent discover, install, update, activate/deactivate, and delete WordPress **plugins and themes** over MCP — the full lifecycle — built on WordPress's own upgrader/plugin/theme APIs, gated behind strong capability checks and self-protection guardrails, with installs restricted to the official wordpress.org directory.

## Decisions (locked in brainstorming)

- **Scope: full lifecycle.** Discovery + install + activate/deactivate + update + delete for plugins; discovery + install + switch + update + delete for themes.
- **Install source: wordpress.org only.** Packages are resolved by slug through `plugins_api()` / `themes_api()`; the package URL is never caller-supplied. No arbitrary ZIP/URL upload (closes the RCE/supply-chain hole an agent could be steered into).
- **Full guardrails.** Self-protection (never disable/delete EMCP Tools, Elementor, or Elementor Pro; never delete an active plugin or the active/parent theme); per-op capability gating; direct-filesystem-only (clear error instead of an FTP-credential hang).
- **Defaults: reads on, writes off.** Discovery tools (`list-*`, `search-*`) ship enabled-by-default; the 9 mutation tools ship **disabled-by-default** (admin opts in per-tool on the Tools tab), matching how PHP Snippets / SEO ship off-by-default because they're powerful.
- **Search kept.** `search-plugins` / `search-themes` are included so an agent can find a slug before installing.

## Architecture

Two focused ability-group classes + one shared guard helper, all built on WP core (no WP-CLI shelling; everything runs in-process over the MCP REST/CLI request).

- `includes/abilities/class-plugin-abilities.php` — `EMCP_Tools_Plugin_Abilities`: the 7 plugin tools + their permission callbacks.
- `includes/abilities/class-theme-abilities.php` — `EMCP_Tools_Theme_Abilities`: the 6 theme tools + their permission callbacks.
- `includes/class-package-guard.php` — `EMCP_Tools_Package_Guard`: shared safety surface used by both groups:
  - `protected_plugin_files(): string[]` — `EMCP_TOOLS_BASENAME`, `elementor/elementor.php`, `elementor-pro/elementor-pro.php` (the EMCP basename self-resolves; never hardcoded).
  - `is_protected_plugin( string $file ): bool`.
  - `is_active_plugin( string $file ): bool` (wraps `is_plugin_active`).
  - `active_theme_stylesheets(): string[]` — active stylesheet + its template (parent) so a child theme's parent is also protected from deletion.
  - `filesystem_ready(): true|WP_Error` — returns a `WP_Error('filesystem_unavailable')` unless `get_filesystem_method() === 'direct'` and `WP_Filesystem()` initialises.
  - `load_upgrader_deps(): void` — on-demand `require_once` (guarded by `function_exists`/`class_exists`) of `wp-admin/includes/{plugin,plugin-install,theme,theme-install,file,misc,update,class-wp-upgrader}.php`. REST/WP-CLI don't preload these.
  - `make_skin()` — returns a quiet upgrader skin (`Automatic_Upgrader_Skin`) so upgrader output is captured, never echoed into the MCP response; its `get_upgrade_messages()` feeds the tool's `messages`.

Registration wiring mirrors the content/settings groups:
- `includes/class-bootstrap.php` — `require_once` the guard + both ability classes.
- `includes/abilities/class-ability-registrar.php` — instantiate + register both groups (unconditional; pure WP).
- `includes/class-plugin.php` — add the read slugs (`list-plugins`, `list-themes`) to `get_essential_tool_slugs()`.
- `includes/admin/class-admin.php` — new "Plugins & Themes" category in `get_tool_catalog()`; the 9 write tools carry a "destructive"/"" badge and are seeded disabled-by-default.

## The 13 tools (contracts)

Annotations: reads → `readonly:true`; install/activate/deactivate/update/switch → `readonly:false, destructive:false`; delete → `destructive:true`.

### Plugins (7)

| Tool | Cap | Notes |
|---|---|---|
| `list-plugins` | `activate_plugins` | Read. All installed plugins: `file`, `name`, `slug`, `status` (active/inactive/network-active/must-use), `version`, `update_available`, `new_version`, `is_protected`, `author`. Optional `status` filter. |
| `search-plugins` | `install_plugins` | Read. Query the .org directory (`plugins_api('query_plugins', {search})`) → `[ {slug, name, version, rating, num_ratings, requires, tested, short_description} ]`, capped. |
| `install-plugin` | `install_plugins` (+ `activate_plugins` when `activate:true`) | `{ slug, activate? }`. Resolve via `plugins_api('plugin_information', {slug})`, install with `Plugin_Upgrader`. Returns installed `file`, `version`, `activated`. |
| `activate-plugin` | `activate_plugins` | `{ plugin }` (file or slug). `activate_plugin()`; returns the resulting status. |
| `deactivate-plugin` | `activate_plugins` | `{ plugin }`. **Refuses** protected plugins. `deactivate_plugins()`. |
| `update-plugin` | `update_plugins` | `{ plugin }`. `Plugin_Upgrader::upgrade()` against the .org update API. Returns `old_version` → `new_version` (or `up_to_date:true`). |
| `delete-plugin` | `delete_plugins` | `{ plugin }`. **Destructive.** Refuses protected and **active** plugins (must deactivate first). `delete_plugins()`. |

### Themes (6)

| Tool | Cap | Notes |
|---|---|---|
| `list-themes` | `switch_themes` | Read. All installed themes: `stylesheet`, `name`, `version`, `status` (active/inactive), `parent`, `update_available`, `new_version`, `is_active`. |
| `search-themes` | `install_themes` | Read. `themes_api('query_themes', {search})` → capped list of `{slug, name, version, rating, requires}`. |
| `install-theme` | `install_themes` (+ `switch_themes` when `activate:true`) | `{ slug, activate? }`. `themes_api` + `Theme_Upgrader`. Returns installed `stylesheet`, `version`, `activated`. |
| `switch-theme` | `switch_themes` | `{ stylesheet }`. Verifies the theme exists and has no `errors()`; `switch_theme()`. |
| `update-theme` | `update_themes` | `{ stylesheet }`. `Theme_Upgrader::upgrade()`. Returns `old_version` → `new_version` (or `up_to_date:true`). |
| `delete-theme` | `delete_themes` | `{ stylesheet }`. **Destructive.** Refuses the **active** theme and the **parent** of the active theme. `delete_theme()`. |

Every mutation tool first calls `Package_Guard::filesystem_ready()` and returns its `WP_Error` if the filesystem isn't directly writable. Missing/unknown slugs, `plugins_api`/`themes_api` errors, and upgrader `WP_Error`s are returned verbatim (never fatal).

## Guardrails (cross-cutting)

1. **Per-op capability** is the `permission_callback` gate; `execute` re-checks defensively (and checks the *secondary* cap, e.g. `activate_plugins` when `install-plugin` is asked to also activate).
2. **Self-protection** via `Package_Guard`: deactivate/delete of EMCP Tools, Elementor, or Elementor Pro → `WP_Error('protected_plugin')` with a message the agent can explain. Delete of an active plugin → `WP_Error('plugin_active')`. Delete of the active/parent theme → `WP_Error('theme_active')`.
3. **wordpress.org-only**: install/update never accept a URL; the package always comes from `plugins_api`/`themes_api` + the core update API.
4. **Direct-filesystem-only**: no interactive FS credential request is ever triggered (would hang a headless MCP request).
5. **No echoed HTML**: the quiet upgrader skin captures messages; the tool returns them as a `messages` array.
6. **Disabled-by-default writes**: the 9 mutation tools are seeded into `elementor_mcp_disabled_tools` on the defaults-version bump, so a freshly connected agent cannot install/delete until an admin opts in.

## File structure

**New:**
- `includes/class-package-guard.php` — the shared guard.
- `includes/abilities/class-plugin-abilities.php` — `EMCP_Tools_Plugin_Abilities` (7 tools).
- `includes/abilities/class-theme-abilities.php` — `EMCP_Tools_Theme_Abilities` (6 tools).
- `tests/unit/capabilities/PluginCapabilityTest.php`, `tests/unit/capabilities/ThemeCapabilityTest.php` — per-tool capability gating + guard refusals.
- `tests/unit/packages/PluginToolsTest.php`, `tests/unit/packages/ThemeToolsTest.php` — execute paths against stubbed core functions/upgrader.

**Changed:**
- `includes/class-bootstrap.php` — `require_once` the three new files.
- `includes/abilities/class-ability-registrar.php` — register both groups (unconditional).
- `includes/class-plugin.php` — add `list-plugins` + `list-themes` to `get_essential_tool_slugs()`.
- `includes/admin/class-admin.php` — new "Plugins & Themes" category; bump `DEFAULTS_VERSION`; add `package_write_tool_slugs()` seeded into the disabled set.
- `tests/bootstrap.php` — stubs for `get_plugins`, `get_plugin_data`, `is_plugin_active`, `activate_plugin`, `deactivate_plugins`, `delete_plugins`, `wp_get_themes`, `wp_get_theme`, `switch_theme`, `delete_theme`, `get_filesystem_method`, `plugins_api`/`themes_api`, and a fake upgrader, controllable via `$GLOBALS`.
- `CHANGELOG.md`, `README.md`, `readme.txt`, `CLAUDE.md` — fold into the single v3.0.0 entry; document the family.

## Testing strategy

- **Capability tests:** each tool's `permission_callback` denies without its specific cap and allows with it; `install-plugin{activate:true}` additionally requires `activate_plugins`.
- **Guard tests:** `deactivate-plugin`/`delete-plugin` refuse EMCP Tools, Elementor, Elementor Pro; `delete-plugin` refuses an active plugin; `delete-theme` refuses the active theme and the active theme's parent; mutations return `filesystem_unavailable` when `get_filesystem_method()` isn't `direct`.
- **Execute tests** (stubbed core + fake upgrader): `list-plugins`/`list-themes` row shape + `is_protected`/`is_active`/`update_available` flags; `install-plugin` activates only when `activate:true`; `update-*` reports `up_to_date` vs `old→new`; `search-*` shape; unknown slug → `WP_Error`.
- **Live MCP smoke** (manual, like the prior domains): `list-plugins`/`list-themes` read; `search-plugins("contact form")`; confirm `deactivate-plugin` on Elementor and `delete-plugin` on an active plugin are refused; optionally install + delete a small throwaway plugin (e.g. `hello-dolly`) from .org and clean up.
- **Browser:** EMCP Tools → Tools → "Plugins & Themes" category renders; reads enabled, the 9 write tools present but **off** by default with the right badges; no PHP errors.

## Out of scope (explicit)

- Arbitrary ZIP/URL installs; premium/3rd-party package sources; private update servers.
- Multisite network activation toggles beyond reporting `network-active` status.
- Editing plugin/theme files, the plugin/theme editor, or rolling back to a prior version.
- Bulk "update everything" in one call (per-item only, for blast-radius control).
- Translations/language-pack management.

## Open questions

None blocking.
