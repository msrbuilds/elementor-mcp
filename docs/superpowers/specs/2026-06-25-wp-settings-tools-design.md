# WordPress Settings Tools — Design

**Status:** Approved (design), ready for implementation plan.
**Date:** 2026-06-25
**Sub-project of:** "Beyond Elementor — general WordPress management via MCP." Domain 2 (Site settings & options). Content (domain 1) shipped in v3.1.0. Plugins/themes and users are separate later sub-projects.

## Goal

Expose a curated, safe set of WordPress site settings (the Settings → General/Reading/Writing/Discussion/Media/Permalinks screens) as MCP tools under the `emcp-tools/` namespace, so an AI agent can read and update common site configuration — without the risk of breaking the site or leaking secrets that arbitrary option read/write would carry.

## Context & constraints

- **Curated allowlist only.** Arbitrary `get_option`/`update_option` over any key is NOT exposed. Only a hand-maintained, typed allowlist of well-known settings is readable/writable. This is the core safety decision.
- **Excluded by deliberate choice:** `siteurl` / `home` (WordPress/Site Address — the classic lock-yourself-out settings) are NOT writable; `users_can_register` / `default_role` (open-registration escalation path) are NOT included; `admin_email` is **read-only** (changing it triggers WP's `new_admin_email` confirmation-email flow, which is not MCP-friendly).
- **In scope (per the risk-boundary decision):** permalink structure (with automatic rewrite flush) and front-page/blog setup (`show_on_front`/`page_on_front`/`page_for_posts`), plus the standard safe General/Reading/Writing/Discussion/Media settings.
- **`manage_options` for BOTH read and write** — settings can expose/alter admin-level config, matching the WP Settings screens exactly.
- **Lean surface:** 2 tools, consistent with the content domain. `get-settings` doubles as discovery (returns each setting's metadata), so no separate list tool.
- Built on WP core `get_option`/`update_option` + `flush_rewrite_rules`, gated by the typed allowlist. Not the Settings API (core settings aren't uniformly registered with usable metadata).

## Architecture

A single new ability group class:

- `includes/abilities/class-settings-abilities.php` — `EMCP_Tools_Settings_Abilities`. Holds the **typed allowlist** (a private static map), registers 2 tools (`get-settings`, `update-settings`), one `manage_options` permission callback, and private helpers (`allowlist()`, `coerce_value()`, `read_setting()`, `is_permalink_key()`).

> Naming note: there is an existing **`EMCP_Tools_Settings_Validator`** (`includes/validators/class-settings-validator.php`) that validates *Elementor widget settings* — unrelated. The new class is `EMCP_Tools_Settings_Abilities` (MCP site-settings tools). The names don't collide (`_Abilities` vs `_Validator`), but reviewers should not conflate them.

Registration wiring (mirrors the content group):
- `includes/abilities/class-ability-registrar.php` — instantiate + register (unconditional; pure WP).
- `includes/class-bootstrap.php` — `require_once` the class.
- `includes/class-plugin.php` — add the 2 slugs to `get_essential_tool_slugs()`.
- `includes/admin/class-admin.php` — new "WordPress Settings" category in `get_tool_catalog()`.

## The typed allowlist (source of truth)

A private map keyed by option name. Each entry:

```php
'blogname' => array(
    'group'    => 'general',
    'label'    => 'Site Title',
    'type'     => 'string',                 // string | int | bool | enum
    'writable' => true,                     // false → read-only (e.g. admin_email)
    'options'  => null,                     // for enum: array of allowed values; else null
    'min'      => null, 'max' => null,      // for int: clamp range; else null
),
```

`type` drives coercion + validation in `coerce_value()`:
- **string** → `sanitize_text_field` (or `sanitize_textarea_field` for multi-line like `moderation_keys` — not in v1 list); permalink keys keep slashes (`sanitize_text_field` is fine for the structure string).
- **int** → `absint`/`intval`, clamped to `min`/`max` when set.
- **bool** → stored as WP expects (`'1'`/`''` or `1`/`0` depending on the option's historical storage — the map notes per-key storage; default `'1'`/`''`).
- **enum** → must be in `options`, else the key is rejected (added to `skipped` with a reason).

### The settings (v1 allowlist)

| Group | Key | Type | Notes |
|---|---|---|---|
| general | `blogname` | string | Site Title |
| general | `blogdescription` | string | Tagline |
| general | `admin_email` | string | **read-only** (writable:false) |
| general | `timezone_string` | string | e.g. `America/New_York` |
| general | `gmt_offset` | string | numeric offset; used when timezone_string empty |
| general | `date_format` | string | PHP date format |
| general | `time_format` | string | PHP time format |
| general | `start_of_week` | int | 0–6 (min 0, max 6) |
| general | `WPLANG` | string | site language code (e.g. `en_US`) |
| reading | `show_on_front` | enum | `posts` \| `page` |
| reading | `page_on_front` | int | page ID (when show_on_front=page) |
| reading | `page_for_posts` | int | page ID |
| reading | `posts_per_page` | int | min 1, max 100 |
| reading | `posts_per_rss` | int | min 1, max 100 |
| reading | `rss_use_excerpt` | bool | full text vs summary |
| reading | `blog_public` | bool | search-engine visibility |
| writing | `default_category` | int | term ID |
| writing | `default_post_format` | enum | `''(standard)`/`aside`/`gallery`/`link`/`image`/`quote`/`status`/`video`/`audio`/`chat` |
| discussion | `default_comment_status` | enum | `open` \| `closed` |
| discussion | `default_ping_status` | enum | `open` \| `closed` |
| discussion | `comment_registration` | bool | must be registered to comment |
| discussion | `require_name_email` | bool | |
| discussion | `comment_moderation` | bool | hold for moderation |
| discussion | `comments_per_page` | int | min 1, max 200 |
| discussion | `thread_comments` | bool | |
| discussion | `close_comments_for_old_posts` | bool | |
| media | `thumbnail_size_w` | int | min 0, max 9999 |
| media | `thumbnail_size_h` | int | min 0, max 9999 |
| media | `medium_size_w` | int | min 0, max 9999 |
| media | `medium_size_h` | int | min 0, max 9999 |
| media | `large_size_w` | int | min 0, max 9999 |
| media | `large_size_h` | int | min 0, max 9999 |
| media | `uploads_use_yearmonth_folders` | bool | |
| permalinks | `permalink_structure` | string | e.g. `/%postname%/`; triggers rewrite flush |
| permalinks | `category_base` | string | triggers rewrite flush |
| permalinks | `tag_base` | string | triggers rewrite flush |

(`siteurl`, `home`, `users_can_register`, `default_role` are intentionally absent.)

## The 2 tools (contracts)

### `get-settings` — `manage_options`
- **In:** `{ group?: "general"|"reading"|"writing"|"discussion"|"media"|"permalinks", keys?: string[] }`
  - No args → all allowlisted settings. `group` filters to one screen. `keys` returns just those (allowlisted) keys.
- **Out:** `{ settings: [ { key, group, label, type, value, options?, writable } ] }`
  - `value` is the current `get_option()` result, coerced to the declared type for clean JSON (int/bool not "1"/"0" strings). `options` present for enum settings. Doubles as discovery.
- Read-only; idempotent.

### `update-settings` — `manage_options`
- **In:** `{ settings: { "<key>": <value>, ... } }` (a map of allowlisted keys → new values)
- **Out:** `{ updated: { "<key>": <coerced-value> }, skipped: [ { key, reason } ], rewrite_flushed: bool }`
- Behavior:
  - For each provided key: if not in the allowlist → `skipped` (`reason: "not an allowlisted setting"`); if `writable:false` → `skipped` (`reason: "read-only"`); if enum value invalid / int out of range after coercion fails validation → `skipped` (`reason: "invalid value for <type>"`). Valid keys are coerced and written via `update_option`.
  - After the batch, if ANY permalink key changed, load `wp-admin/includes` as needed and call `flush_rewrite_rules( false )` once; set `rewrite_flushed: true`.
  - Partial-failure: applies the valid subset, reports the rest in `skipped` — never aborts the whole call for one bad key.
  - Annotation: `readonly:false, destructive:false, idempotent:true` (writing the same values again is a no-op).
- Empty/absent `settings` map → `WP_Error('missing_params')`.

## Guardrails (cross-cutting)

1. **Capability:** `manage_options` for both tools; `permission_callback` is the gate, `execute` re-checks defensively.
2. **Allowlist is the security boundary.** Only keys in the static map are ever read or written; everything else is rejected. No raw `get_option`/`update_option` over caller-supplied keys outside the map.
3. **Typed coercion + validation** per the map (string/int/bool/enum, clamp ranges, enum membership). Invalid values are skipped with a reason, not coerced to a surprising value.
4. **`admin_email` read-only** — returned by `get-settings`, rejected by `update-settings`.
5. **Permalink flush** only when a permalink key actually changed; `flush_rewrite_rules` deps loaded on demand (REST/WP-CLI don't load wp-admin/includes), guarded by `function_exists`.
6. **No secrets in scope.** The allowlist contains no `*_key`/`*_secret`/`*_token`/password options.

## File structure

**New:**
- `includes/abilities/class-settings-abilities.php` — the ability group (allowlist + 2 tools + helpers).
- `tests/unit/capabilities/SettingsCapabilityTest.php` — `manage_options` gating for both tools.
- `tests/unit/settings/SettingsToolsTest.php` — execute-path: get-settings (all/group/keys + metadata shape + type coercion), update-settings (writes allowlisted, skips non-allowlisted/read-only/invalid-enum, permalink flush flag, partial-failure).

**Changed:**
- `includes/abilities/class-ability-registrar.php` — register the group (unconditional).
- `includes/class-bootstrap.php` — `require_once` the class.
- `includes/class-plugin.php` — add `emcp-tools/get-settings` + `emcp-tools/update-settings` to `get_essential_tool_slugs()`.
- `includes/admin/class-admin.php` — new "WordPress Settings" category (2 tools; `get-settings` read-only badge).
- `emcp-tools.php` — version → 3.2.0 (feature release).
- `CLAUDE.md`, `README.md`, `readme.txt`, `CHANGELOG.md` — document the family + counts.

## Testing strategy

- **Capability tests:** both tools' `permission_callback` denies without `manage_options`, allows with it.
- **Execute tests** (stubbed `get_option`/`update_option`/`flush_rewrite_rules`, matching the harness): `get-settings` returns all vs `group`-filtered vs `keys`-filtered, with correct metadata shape and type-coerced `value`; `update-settings` writes an allowlisted key (records the `update_option` call), skips a non-allowlisted key (`reason`), skips `admin_email` (read-only), skips an invalid enum/out-of-range int, sets `rewrite_flushed:true` only when a permalink key changed, applies the valid subset on partial failure, errors on empty map.
- **Live MCP smoke** (manual, like the prior domains): on `emcp-tools-server`, `get-settings(group:general)` → read title/tagline; `update-settings({blogname:"…"})` → verify via `wp option get blogname`, then restore; `update-settings({permalink_structure:"/%postname%/"})` → confirm `rewrite_flushed:true` and `wp option get permalink_structure`, then restore the original. Confirm a non-allowlisted key (e.g. `siteurl`) is rejected.
- **Browser:** EMCP Tools → Tools → "WordPress Settings" category renders with the 2 toggles, correct badge, enabled by default; no PHP errors.

## Out of scope (explicit)

- Arbitrary option get/set, transients, theme mods, site/network options on multisite.
- `siteurl`/`home`, `admin_email` writes, `users_can_register`/`default_role`, registration/membership flows.
- Settings registered by other plugins (Yoast, WooCommerce, etc.) — only WP core's standard settings.
- Multisite network settings.
- The `emcp_tools_allowed_settings` escape-hatch filter (deferred; this v1 is allowlist-only, no opt-in extension).

## Open questions

None blocking.
