# WordPress Content Tools — Design

**Status:** Approved (design), ready for implementation plan.
**Date:** 2026-06-25
**Sub-project of:** "Beyond Elementor — general WordPress management via MCP." This is the **first** domain (Content); settings, plugins/themes, and users are separate later sub-projects.

## Goal

Expose general WordPress **content** management (posts, pages, and any custom post type) as MCP tools under the existing `emcp-tools/` namespace, so an AI agent can create/read/update/delete WP content, categorize it, set custom fields, and set a featured image — without it being Elementor-specific. Self-contained, capability-gated, no new runtime dependencies.

## Context & constraints

- **WP core ships only 3 read-only abilities** (`core/get-site-info`, `core/get-user-info`, `core/get-environment-info`) — there are no built-in CRUD abilities. We register our own.
- **No collision with the Elementor tools.** The plugin already has `create-page` (creates an *Elementor-enabled* page), `list-pages` (Elementor pages only), `get-page-structure`, `export-page`, `delete-page-content`. The new content tools operate on **`post_content`** (classic HTML or Gutenberg block markup) for **any** post type and are general-purpose. Tool descriptions must cross-reference so an agent picks the right family (Elementor builder vs general WP content).
- **Lean surface.** We just consolidated 62 widget tools → 5. Carry that discipline: **8 rich tools**, not 15 thin ones. Featured image and post meta are *parameters* of create/update, not separate tools.
- **Enabled by default** (capability-gated, like the existing Elementor tools) — not disabled-by-default like the PHP-snippets sandbox. `delete-post` is the only sharp edge and it trashes by default.
- Built on WP core functions (`wp_insert_post`, `wp_update_post`, `wp_delete_post`, `wp_set_object_terms`, `get_post`, `WP_Query`, `set_post_thumbnail`, `update_post_meta`) — **not** the REST API (heavier; can be disabled).

## Architecture

A single new ability group class, registered through the existing registrar, exposed on the `emcp-tools-server`:

- `includes/abilities/class-content-abilities.php` — `EMCP_Tools_Content_Abilities`, registers 8 abilities; mirrors the structure of the other ability classes (`register()`, `get_ability_names()`, per-tool `register_*` + `execute_*`, `emcp_tools_register_ability()` shim, capability callbacks).
- A small internal helper for the shared post-shape serialization (`get-post` output, and the post summary used by `list-posts`) and for the shared write-params handling (terms + meta + featured image) used by both `create-post` and `update-post`. Kept private to the class unless it grows enough to warrant `includes/class-content-helper.php`.
- Surface the **3 core read-only abilities** (`core/get-site-info`, `core/get-user-info`, `core/get-environment-info`) on our server too, by appending them to the tools array in `EMCP_Tools_Plugin::register_mcp_server()` (they're already registered by core; we just include them as tools). Low-risk freebie; gives agents site/user/environment context.

Registration wiring: add to `EMCP_Tools_Ability_Registrar::register_all()` (unconditional — no env gate; pure WP, always available) and `require_once` in `class-bootstrap.php::load_classes()`.

## The 8 tools (contracts)

All names are under `emcp-tools/`. Inputs/outputs are JSON Schema objects; schemas pass through the existing `emcp_tools_register_ability()` normalizer.

### Discovery (2, read-only — `edit_posts`)

#### `list-post-types`
- **In:** `{ public_only?: bool=true }`
- **Out:** `{ post_types: [ { name, label, hierarchical, public, supports: string[], taxonomies: string[], rest_base } ] }`
- Source: `get_post_types([...], 'objects')`. Excludes internal types (`revision`, `nav_menu_item`, etc.) unless `public_only:false`. Lets an agent discover targetable CPTs.

#### `list-taxonomies`
- **In:** `{ post_type?: string, include_terms?: bool=false, terms_limit?: int=100 }`
- **Out:** `{ taxonomies: [ { name, label, hierarchical, object_types: string[], terms?: [ { term_id, name, slug, parent, count } ] } ] }`
- Source: `get_taxonomies()` / `get_terms()`. `post_type` filters to taxonomies attached to that type. `include_terms` optionally embeds terms (capped) so a follow-up call isn't needed for small taxonomies.

### Content CRUD (5)

#### `create-post` — `edit_posts` (+ `publish_posts` when `status=publish`, `edit_others_posts` when `author` ≠ current user)
- **In:**
  ```
  {
    post_type: string = "post",          // any registered, non-internal type
    title?: string,
    content?: string,                    // post_content — classic HTML or Gutenberg block markup, stored verbatim
    excerpt?: string,
    status?: "draft"|"publish"|"pending"|"private"|"future" = "draft",
    slug?: string,
    author?: int,                        // user ID; defaults to current user
    date?: string,                       // "Y-m-d H:i:s"; required-ish for status=future
    parent?: int,                        // hierarchical types
    menu_order?: int,
    comment_status?: "open"|"closed",
    terms?: { "<taxonomy>": (int|string)[] },   // term IDs or names; names auto-created if missing & user can manage_terms
    meta?: { "<key>": scalar|array },    // custom fields; protected/`_`-prefixed keys rejected (see Guardrails)
    featured_image?: { id?: int, url?: string }  // attachment ID, or URL to sideload into the media library
  }
  ```
- **Out:** `{ post_id, status, permalink, edit_link }`
- Validation: reject unknown/internal `post_type`; validate `status` enum; validate `author` exists. Uses `wp_insert_post(..., true)` (returns WP_Error). Then applies terms / meta / featured image (shared writer). Partial-failure policy: the post is created first; term/meta/image failures are collected and returned in a `warnings: string[]` field rather than aborting (the post still exists). 

#### `get-post` — `edit_posts` (+ `edit_post` ownership for non-public/private)
- **In:** `{ post_id: int }`
- **Out:**
  ```
  {
    post_id, post_type, title, slug, status, content, excerpt,
    author: { id, name }, date, modified, parent, menu_order, comment_status,
    permalink, edit_link,
    terms: { "<taxonomy>": [ { term_id, name, slug } ] },
    meta: { "<key>": value },            // non-protected meta only
    featured_image: { id, url, alt } | null,
    is_elementor: bool                   // true if _elementor_edit_mode=builder — hint to use the Elementor tools instead
  }
  ```
- The `is_elementor` flag steers agents to the Elementor family for builder pages.

#### `update-post` — `edit_posts` + `edit_post` ownership (+ `publish_posts` if transitioning to publish, `edit_others_posts` if reassigning author)
- **In:** `{ post_id: int, ...same optional fields as create-post (all partial) }`
- **Out:** `{ post_id, status, permalink, warnings?: string[] }`
- Merge semantics: only provided fields change. `terms` replaces the given taxonomy's set by default; `terms_mode?: "replace"|"append" = "replace"` controls it. `meta` upserts provided keys (does not delete omitted keys). `featured_image: null` clears it.
- Refuses to write to a post whose `post_type` the user can't edit; protected-meta guard applies.

#### `list-posts` — `edit_posts`
- **In:**
  ```
  {
    post_type?: string|string[] = "post",
    status?: string|string[] = "any",
    search?: string,
    taxonomy?: { "<taxonomy>": (int|string)[] },   // tax_query AND
    author?: int,
    parent?: int,
    per_page?: int = 20,    // max 100
    page?: int = 1,
    orderby?: "date"|"modified"|"title"|"menu_order"|"ID" = "date",
    order?: "ASC"|"DESC" = "DESC"
  }
  ```
- **Out:** `{ posts: [ { post_id, post_type, title, slug, status, date, modified, author_id, permalink, is_elementor } ], total, pages, page }`
- `WP_Query` with `no_found_rows:false` (we need totals for paging). Compact rows (no content body) — a follow-up `get-post` fetches full content. Mirrors the `list-widgets` compact-index philosophy.

#### `delete-post` — `delete_posts` + `delete_post` ownership
- **In:** `{ post_id: int, force?: bool=false }`
- **Out:** `{ success: bool, post_id, deleted: "trashed"|"deleted" }`
- `force:false` → `wp_trash_post` (recoverable). `force:true` → `wp_delete_post(..., true)` (permanent). Annotated **destructive**. Refuses to force-delete a post the user can't `delete_post`.

### Taxonomy (1)

#### `set-post-terms` — `edit_posts` + `edit_post` ownership (+ `manage_terms`/`edit_terms` of the taxonomy when creating terms)
- **In:** `{ post_id: int, taxonomy: string, terms: (int|string)[], mode?: "replace"|"append"|"remove" = "replace", create_missing?: bool=true }`
- **Out:** `{ post_id, taxonomy, terms: [ { term_id, name, slug } ], created: string[] }`
- Dedicated incremental term management (the `create-post`/`update-post` `terms` param covers the bulk-on-write case; this covers "just change the categories on an existing post"). `wp_set_object_terms($id, $terms, $taxonomy, $append)`. Names resolved to term IDs; missing names created when `create_missing` and capability allows; `mode:remove` removes the listed terms.

## Guardrails (cross-cutting)

1. **Capability matrix** — per the table above; every `execute_*` re-checks (permission_callback is the gate, execute re-checks defensively, matching the codebase pattern). Ownership via `current_user_can('edit_post'|'delete_post', $id)`.
2. **Protected meta allowlist.** `update_post_meta`/`meta` param rejects any key that is `is_protected_meta($key, 'post')` true OR starts with `_` (covers `_elementor_data`, `_edit_lock`, `_thumbnail_id`, `_wp_page_template`, etc.), UNLESS the key is in a short, explicitly-safe set we maintain (e.g. none initially — featured image goes through `featured_image`, not raw `_thumbnail_id`). A `emcp_tools_content_allowed_protected_meta` filter lets advanced users opt specific keys in. Reading: `get-post` returns only non-protected meta.
3. **Post-type allowlist.** Only registered, non-internal post types are writable. Internal types (`revision`, `nav_menu_item`, `custom_css`, `customize_changeset`, `oembed_cache`, `user_request`, `wp_block`?, `wp_template*`) are excluded from create/update/delete targets. Reading via `get-post` is allowed for any type the user can edit.
4. **Elementor coexistence.** Content tools never touch `_elementor_data`. `get-post`/`list-posts` expose `is_elementor` so agents don't clobber a builder page's `post_content` (which Elementor ignores/overwrites). Descriptions say: "to edit an Elementor-built page, use the Elementor tools."
5. **Featured image sideload** reuses the existing media sideload path (`EMCP_Tools_Stock_Image_Abilities` / `media_sideload_image` equivalent) and requires `upload_files` when a `url` is given.
6. **Status transitions** gated: moving to `publish` requires `publish_posts` for that type; `future` requires a valid future `date`.

## File structure

**New:**
- `includes/abilities/class-content-abilities.php` — the ability group (8 tools + shared private helpers).
- `tests/unit/capabilities/ContentCapabilityTest.php` — permission-callback gating per tool.
- `tests/unit/input/ContentInputTest.php` — input validation (missing required, bad enums, protected-meta rejection, internal post-type rejection).
- `tests/unit/content/ContentToolsTest.php` — execute-path behavior with stubbed WP functions (create returns id+permalink; delete trash vs force; list compact shape; set-post-terms modes; protected-meta guard).

**Changed:**
- `includes/abilities/class-ability-registrar.php` — instantiate + register `EMCP_Tools_Content_Abilities` (unconditional).
- `includes/class-bootstrap.php` — `require_once` the new class.
- `includes/class-plugin.php` — append the 3 `core/*` read-only abilities to the server tools array in `register_mcp_server()`; add content slugs to `get_essential_tool_slugs()` (the discovery + create/get/update/list belong in the low-tools essentials).
- `includes/admin/class-admin.php` — new **"WordPress Content"** category in `get_tool_catalog()` with the 8 tools (badges: read-only on the 2 discovery + get/list; destructive on delete-post). No `DEFAULTS_VERSION` bump (enabled by default).
- `CLAUDE.md`, `README.md`, `readme.txt`, `CHANGELOG.md` — document the new tool family + updated counts; note the "beyond Elementor" positioning.

## Testing strategy

- **Capability tests:** each tool's `permission_callback` denies without the right cap, allows with it; ownership-gated tools deny on non-owned posts.
- **Input tests:** required-field omissions → WP_Error; bad `status`/`post_type` enums rejected; protected/`_`-prefixed meta keys rejected; internal post types rejected for write.
- **Execute tests** (stubbed WP funcs, matching the existing test harness): create-post returns `{post_id, permalink}` and applies terms/meta/featured image; partial-failure returns `warnings` without aborting; update-post merge semantics (only provided fields, `terms_mode`, `featured_image:null` clears); delete-post trash vs force; list-posts compact shape + paging totals; set-post-terms replace/append/remove + create_missing.
- **Live MCP smoke** (manual, like the consolidation verify): on `emcp-tools-server`, create a draft post → get-post shows it → set-post-terms → update to publish → list-posts finds it → delete-post trashes it. Confirm `is_elementor` is false and `_elementor_data` untouched.

## Out of scope (explicit)

- Settings/options, plugins, themes, users — separate sub-projects.
- Comments, menus, widgets (WP widgets), media-library management beyond featured-image sideload.
- Block-level editing helpers (parsing/serializing Gutenberg blocks) — `content` is stored verbatim; agents author block markup themselves.
- Revisions/autosave management.
- Bulk operations across many posts in one call (single-post tools; agents loop).

## Open questions (none blocking)

- Whether to bump the README "MCP Tools for Elementor" branding toward "WordPress + Elementor" — deferred to a positioning pass, not this build.
