# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

MCP Tools for Elementor Plugin — a WordPress plugin that extends the official WordPress MCP Adapter to expose Elementor data, widgets, structures, and methods as MCP (Model Context Protocol) tools. This enables AI tools (Claude, Cursor, etc.) to create and manipulate Elementor page designs programmatically via up to ~130 MCP tools (scales with environment; see live-verified counts below). **v3.0.0 is the first major release of the rebranded EMCP Tools and bundles the whole step beyond Elementor as a single release** (previous release: 2.2.0): (1) the MCP namespace + server route renamed `elementor-mcp` → `emcp-tools`; (2) the 62 per-widget convenience tools folded into a catalog-backed model (5 widget tools), so the active surface is far smaller while every widget remains reachable; (3) **domain 1 — 8 general-WordPress Content tools** (create/read/update/list/delete posts of any type, plus taxonomy and post-type discovery) built on WP core, never touching `_elementor_data`; (4) **domain 2 — 2 WordPress Settings tools** (`get-settings`/`update-settings`) over a curated, typed allowlist of core WordPress settings; (5) **domain 3 — 13 WordPress Plugins & Themes tools** (discover/install/update/activate/delete plugins and themes; wordpress.org-only; writes disabled-by-default); (6) **domain 4 — 3 WordPress Media Library tools** (`get-media`/`update-media`/`delete-media`) to fetch attachment detail, edit metadata, and delete attachments — `delete-media` ships disabled-by-default and requires `confirm:true`; (7) **domain 5 — 4 WordPress Users tools** (`list-users`/`get-user`/`create-user`/`update-user`) for safe user management — reads enabled-by-default, writes disabled-by-default, no delete/role-change tool, administrators untouchable via MCP; (8) **domain 7 — 6 Filesystem tools** (`read-file`/`list-directory`/`search-files` enabled-by-default; `write-file`/`edit-file`/`delete-file` disabled-by-default) confined to ABSPATH, writes auto-backed-up and audit-logged, `wp-config.php`/`.htaccess` refused, `edit_files` capability enforced; and (9) **domain 8 — 6 Database tools** (`list-tables`/`describe-table`/`query` read-only enabled-by-default; `insert-row`/`update-rows`/`delete-rows` structured/parameterized writes disabled-by-default) — read path bounded by a read-only-SQL validator, writes use `$wpdb` (no raw write-SQL/DDL), refuse `wp_users`/`wp_usermeta`, force a WHERE clause, snapshot a before-image to an audit log. The Performance domain also gains a **Security & Malware Scanner** (`scan-security`) — the security counterpart to the Performance Analyzer — a read-only, self-contained scan across malware heuristics, core-file integrity, hardening, and outdated/abandoned software that returns a scored report; it registers unconditionally and is enabled by default.

## Companion projects (sibling folders, edit from here)

| Project | Path | What it is |
|---|---|---|
| **Master prompts library** | `E:\MSR Builds\Products\EMCP\prompts\` | Source-of-truth markdown files for the 50+ Premium Prompts. 10 categories (Automotive, Food & Dining, General, Health & Wellness, Home Services, Lifestyle & Entertainment, Pets, Professional Services, Retail, Weddings). Never bundled in the plugin zip. |
| **Website + docs + API** | `E:\MSR Builds\Products\EMCP\website\` | Astro 5 + Starlight + Tailwind + Postgres + Drizzle. Hosts the marketing site, comprehensive docs, and the `/api/emcp/prompts.json` license-gated endpoint the plugin's Pro Prompts page fetches from. See `website/PLAN.md` for the full implementation spec. Hosted via Dokploy at `emcptools.com` (planned). |

When editing premium-prompts behavior, the plugin code (`includes/admin/class-pro-prompts.php`) and the website's API endpoint (`website/src/pages/api/emcp/prompts.json.ts` per the PLAN) must stay in sync via the contract in `docs/PREMIUM_PROMPTS_API.md`.

**Current status: v3.0.0 — All phases implemented (P0/P1/P2) plus Elementor 4.0 atomic elements, top-level admin menu, the catalog-backed widget consolidation, the namespace rename, and the seven beyond-Elementor domains (8 WordPress Content tools + 2 WordPress Settings tools + 13 WordPress Plugins & Themes tools + 3 WordPress Media Library tools + 4 WordPress Users tools + 6 Filesystem tools + 6 Database tools), all shipping together as the single 3.0.0 release.** Foundation layer, query tools, page CRUD, layout, the 5 catalog-backed widget tools, template, global, composite tools, stock images, SVG icons, custom code tools, 13 atomic element tools for Elementor 4.0+, 8 general-WordPress content tools, 2 WordPress settings tools, 13 plugins & themes tools, 3 media library tools, 4 WordPress users tools, 6 filesystem tools, 6 database tools, the Security & Malware Scanner (`scan-security`, the security counterpart to the Performance Analyzer), and a curated essentials filter (Low-tools mode, now largely obsolete after the consolidation).

**Tool counts by configuration (v3.0.0 — REGISTERED counts, live-verified via `WP_Abilities_Registry` on Elementor 4.1.4 + Pro 4.1.2, 2026-06-27; each total = `emcp-tools/*` + the 3 surfaced `core/*` abilities):**
- Free Elementor only: **107** (104 emcp + 3 core) — **83** active by default
- Free Elementor + Elementor 4.0+ atomic: **121** (118 + 3) — **97** active by default
- With Elementor Pro: **117** (114 + 3) — **78** active by default
- With Elementor Pro + Elementor 4.0+: **131** (128 + 3) — **92** active by default
- With Pro + WooCommerce + Elementor 4.0+: **131** — WooCommerce widgets are reached through `add-pro-widget` (catalog tier `woo`), so they add **no** new tools.
- Low-tools mode (any config): still available but largely obsolete — the consolidation already keeps the surface well under common client caps.

> The beyond-Elementor surface in v3.0.0 adds: 8 Content + 3 `core/*` + 2 Settings + 13 Plugins & Themes + 3 Media Library + 4 Users + 6 Filesystem + 6 Database = 45 tools. Of those 45, the 9 Plugins & Themes mutation tools, 1 Media Library delete tool, 2 Users write tools, 3 Filesystem write tools, and 3 Database write tools ship disabled-by-default (admin opts in on the Tools tab), so the net enabled-by-default addition is +27. Separately, the Performance domain is now effectively 2 read-only tools — the Performance Analyzer (`analyze-performance`) plus the Security & Malware Scanner (`scan-security`) — both enabled by default (+1 registered and +1 enabled-by-default in every configuration).

> **These are REGISTERED counts.** **39 tools ship disabled-by-default** — SEO & Accessibility (**7**, Pro), Widget Builder (**8**, Pro), PHP Snippets / Sandbox (**6**, free), the **9** Plugins & Themes write tools, **1** `delete-media`, the **2** Users write tools, the **3** Filesystem write tools, and the **3** Database write tools. So the typical **active** surface is up to 39 smaller until a user enables them on the Tools tab (e.g. Pro + Elementor 4.0+ = **131** registered, **92** active by default). Verified reconciliation: 131 registered − 12 currently-off writes (SEO/WB/PHP-Snippets) − 3 filesystem writes − 3 database writes = 113 in the live `tools/list` when SEO/Widget-Builder/PHP-Snippets are manually enabled.
>
> **What the v3.0.0 consolidation changed.** Every per-widget convenience tool (62) plus the old universal `add-widget` were removed; `add-free-widget` (always) and `add-pro-widget` (Pro only) were added. The 62 curated widgets are now catalog DATA (27 free / 30 pro / 5 woo) served by `EMCP_Tools_Widget_Catalog`, not individual tools. The widget portion of the per-turn `tools/list` dropped from ~18–20k tokens to ~1.2k (the five widget tool schemas total ~5 KB). Verified end-to-end via the WP-CLI MCP stdio server: `tools/list` shows exactly the 5 widget tools and none of the 62 old names, and a real `add-container` → `add-free-widget` round-trip persists to `_elementor_data`.

See `PLAN.md` for the full architectural specification.

## Dependencies & Requirements

- WordPress >= 6.9 (the Abilities API — `wp_register_ability()` — is core in 6.9+/7.0)
- Elementor — **optional**. The plugin and every beyond-Elementor tool (WordPress Content, Settings, Plugins & Themes, Users, Media, Performance, Security, Filesystem, Database, PHP Snippets) load and work without it. Installing/activating Elementor (>= 3.20; >= 4.0 for atomic elements) enables the Elementor tool family (query, pages, layout, widgets, templates, globals, composite, stock images, SVG icons, custom code, atomic, global classes, brand kits, widget builder, SEO/A11y). When Elementor is inactive those groups don't register and the admin shows a warning. The only hard dependencies are PHP 8.1+, the WordPress Abilities API (core in WP 6.9+), and the bundled MCP Adapter.
- WordPress Abilities API — core in WP 6.9+/7.0
- WordPress MCP Adapter — **bundled** with the plugin since v1.7.4 (`includes/vendors/mcp-adapter/`); no separate install needed. If a standalone MCP Adapter plugin is active, the plugin defers to it (see `Elementor_MCP_Adapter_Bootstrap`).
- PHP >= 8.1

## Build & Development Commands

No external dependencies. The plugin uses WordPress core, Elementor, the core Abilities API, and a **bundled** copy of the MCP Adapter (loaded by `Elementor_MCP_Adapter_Bootstrap::ensure()` only when no standalone adapter plugin is active). Only the adapter's `includes/` source is vendored — its dev-only Composer `vendor/` is not, since the package has zero runtime dependencies.

For plugin review tooling, the `.claude/skills/wp-plugin-review/scripts/setup_tools.sh` script installs PHPCS, WPCS, PHPStan, and PHPUnit.

### Release build (free/pro split)

- **Free zip** (`emcp-tools-X.Y.Z.zip`, GitHub release): built from the public repo **excluding `pro/`, `tests/`, `tools/`, `skills/`** (plus the usual `.*`, `vendor/`, `docs/`, etc.). No `.emcp-pro` marker.
- **Pro zip** (`emcp-pro-X.Y.Z.zip`, Freemius): the free tree with **`pro/*` overlaid onto plugin paths** (`cp -r pro/includes/. includes/`, `cp -r pro/assets/. assets/`), plus `skills/` and the `.emcp-pro` marker. `pro/tests` + `pro/tools` are NOT shipped.
- **Always run** `tools/verify-release-zip.sh <zip> pro-manifest.txt` on the free zip (hard-fails on any Pro-path leak) and `tools/verify-release-zip.sh <pro-zip>` on the Pro zip. `tools/` lives in the `pro/` submodule.

### Freemius policy (do NOT regress)

Freemius has **no separate free-zip upload** — it auto-generates a free build from the premium upload. Since that generated build is derived from the premium tree, it would contain Pro source. Therefore: **upload the premium zip only, and keep the auto-generated free build UNRELEASED on Freemius.** Free distribution **and updates** happen via **GitHub only** (`is_org_compliant => false` means the SDK would otherwise serve Freemius updates to connected free installs). The generated free zip is reachable only from the private Developer Dashboard — never publish/Release it.

## Repository topology (free/pro split — 2026-07-02)

The plugin is split across **two repos** so no Pro-tier source ships in any free artifact:

- **`msrbuilds/elementor-mcp`** (public) — the free plugin + source of truth for all free-tier code.
- **`msrbuilds/emcp-pro`** (**private**) — the Pro overlay, mounted as a **git submodule at `pro/`**. Mirrors plugin paths (`pro/includes/…`, `pro/assets/…`) plus `tests/`, `tools/`, and `skills/`.

**Loader.** `includes/class-pro-loader.php` (`EMCP_Tools_Pro_Loader`, a free class) is the single boundary that conditionally loads Pro units. `path()`/`url()` resolve each Pro file **dual-root**: from the plugin root (premium build, where `pro/*` is overlaid) or from `pro/<rel>` (dev checkout with the submodule). The free plugin runs with `pro/` absent — every Pro `require`/instantiation is guarded by `file_exists`/`class_exists`, and each Pro unit still self-gates on `can_use_premium_code()`.

**Classification rule.** A file belongs in `pro/` **only if every consumer is Pro**. Anything a free file references stays free (its Pro-only methods stay license-gated). `pro-manifest.txt` (public repo root) is the authoritative Pro-path list; the build + `tools/verify-release-zip.sh` Pro-leak gate consume it.

**Dev workflow.** Clone with `--recurse-submodules` (Pro devs only; free clones can't fetch the private submodule and just get the free plugin). Edit Pro code directly in `pro/` — no in-place copies, no sync. Run tests from the plugin root: `phpunit` (config points at `pro/tests`). Verified counts: Pro present = 134 abilities; free (`pro/` absent) = 115.

**History note.** Pre-split Pro code remains in the public repo's *history* (HEAD-only removal, by decision). The AI-chat crypto was reviewed — no hardcoded secrets, keys derive from per-site `AUTH_KEY` — so nothing exploitable was disclosed. See `pro/SECURITY.md`.

## Architecture

### MCP Server Registration

The plugin registers a dedicated MCP server `emcp-tools-server` at `/wp-json/mcp/emcp-tools-server`. All abilities use the `emcp-tools/` namespace.

### Directory Structure

```
emcp-tools/
├── elementor-mcp.php                          # Bootstrap: plugin header, constants, dependency checks, require_once, singleton init
├── includes/
│   ├── class-plugin.php                       # Singleton orchestrator — hooks into wp_abilities_api_categories_init, wp_abilities_api_init, mcp_adapter_init
│   ├── class-elementor-data.php               # Data access layer wrapping Elementor documents, widgets, element tree
│   ├── class-element-factory.php              # Builds valid Elementor JSON element structures (container, widget, section, column)
│   ├── class-id-generator.php                 # 7-char hex unique IDs via random_bytes()
│   ├── class-unsplash-client.php              # Stock-photo client: Unsplash (also class-pexels-client.php, class-pixabay-client.php + class-stock-image-providers.php registry). Each needs a free API key
│   ├── class-pro-loader.php                   # EMCP_Tools_Pro_Loader — conditional dual-root loader for the private Pro overlay (pro/). Free class; no-op when pro/ absent
│   ├── widgets/                               # Curated widget catalog (v3.0.0): class-widget-catalog.php + catalog-{free,pro,woo}.php (27/30/5 = 62 widgets as DATA, not tools)
│   ├── abilities/
│   │   ├── class-ability-registrar.php        # Coordinates registration of all ability groups across all phases
│   │   ├── class-query-abilities.php          # P0: 7 read-only tools (list-widgets, get-widget-schema, get-page-structure, etc.)
│   │   ├── class-page-abilities.php           # P1: 5 page CRUD tools (create-page, update-page-settings, delete-page-content, import-template, export-page)
│   │   ├── class-layout-abilities.php         # P1: 4 layout tools (add-container, move-element, remove-element, duplicate-element)
│   │   ├── class-widget-abilities.php         # Catalog-backed: add-free-widget + update-widget (+ add-pro-widget when Pro). Serves EMCP_Tools_Widget_Catalog
│   │   ├── class-template-abilities.php       # P2: 2 template tools (save-as-template, apply-template)
│   │   ├── class-global-abilities.php         # P2: 2 global tools (update-global-colors, update-global-typography)
│   │   ├── class-composite-abilities.php      # P2: 1 composite tool (build-page)
│   │   ├── class-stock-image-abilities.php    # 3 stock image tools (search-images, sideload-image, add-stock-image)
│   │   ├── class-custom-code-abilities.php   # 4 custom code tools (add-custom-css, add-custom-js, add-code-snippet, list-code-snippets)
│   │   └── class-content-abilities.php       # v3.0.0: 8 WordPress Content tools (list-post-types, list-taxonomies, create/get/update/list/delete-post, set-post-terms) — WP core only, never touches _elementor_data
│   ├── admin/
│   │   ├── class-admin.php                    # Admin top-level menu (EMCP Tools) with 4 native submenu pages (Tools, Connection, Prompts, Changelog), stats bar, header, Low-tools mode + Pro-disabled-by-default defaults
│   │   └── views/
│   │       ├── page-tools.php                 # Tools tab: category-grouped tool toggles with bulk actions
│   │       ├── page-connection.php            # Connection tab: status cards, credential form, MCP client configs
│   │       └── page-prompts.php               # Prompts tab: sample prompt cards with one-click copy, CTA banner
│   ├── schemas/
│   │   ├── class-schema-generator.php         # Generates JSON Schema from Elementor widget controls
│   │   └── class-control-mapper.php           # Maps individual Elementor control types → JSON Schema fragments
│   └── validators/
│       ├── class-element-validator.php         # Validates element structure (id, elType, widgetType)
│       └── class-settings-validator.php        # Validates widget settings against generated schema
├── prompts/                                    # Sample landing page prompt blueprints (Markdown)
│   ├── LOCAL_BUSINESS.md
│   ├── DENTAL_CLINIC.md
│   ├── WEB_DEVELOPER_PORTFOLIO.md
│   ├── HAIR_SALON.md
│   └── CAR_WASH.md
└── pro/                                        # PRIVATE Pro overlay — git submodule → msrbuilds/emcp-pro. Absent in free clones.
    ├── includes/abilities/                     #   Pro abilities: seo, a11y, widget-builder, system-kit
    ├── includes/ai-chat/                       #   AI Chat (key-crypto, providers, controller, store, admin page)
    ├── includes/admin/                         #   class-pro-{prompts,templates,ajax,skills,brand-kits}.php + views page-ai-chat/page-skills
    ├── includes/                               #   Pro helpers: color-contrast, content-extractor, seo-meta, widget-generator
    ├── assets/{js,css}/ai-chat.*               #   Pro assets
    ├── skills/                                 #   premium-only skills content
    ├── tests/                                  #   PHPUnit suite (run from plugin root: `phpunit` → config points at pro/tests)
    └── tools/                                  #   release + verify tooling (verify-release-zip.sh)
```

> **Note:** `tests/` and `tools/` moved into the private `pro/` submodule; the public repo ships neither. `phpunit.xml`/`.dist` (dev-only, excluded from zips) point at `pro/tests`.

### Hook Registration Flow

The plugin integrates via three WordPress hooks (in execution order):
1. **`wp_abilities_api_categories_init`** → Registers the `elementor-mcp` ability category
2. **`wp_abilities_api_init`** → Registers all abilities via `wp_register_ability()` (ability names must match `[a-z0-9-]+/[a-z0-9-]+`)
3. **`mcp_adapter_init`** → Creates MCP server via `$mcp_adapter->create_server()`, passing ability names as the tools array

The MCP Adapter converts ability names like `emcp-tools/list-widgets` to tool names `emcp-tools-list-widgets` (replacing `/` with `-`).

### Core Layers

1. **Data Layer** (`class-elementor-data.php`) — Read/write wrapper around `_elementor_data` post meta, widget registry, and element tree traversal. All saves go through `\Elementor\Plugin::$instance->documents->get()->save()` (never raw meta updates) to trigger CSS regeneration and cache busting.

2. **Element Factory** (`class-element-factory.php`) — Creates valid Elementor JSON structures for containers, widgets, sections, and columns. Each element gets a 7-char random hex ID.

3. **Schema Generator** (`class-schema-generator.php` + `class-control-mapper.php`) — Maps Elementor control types (TEXT, SLIDER, SELECT, MEDIA, DIMENSIONS, REPEATER, etc.) to JSON Schema. This powers the `get-widget-schema` tool that tells AI agents what settings each widget accepts.

4. **Abilities** — Grouped by domain: query (7 read-only tools, including the catalog-backed `list-widgets`/`get-widget-schema`), page CRUD (5), layout/container (4), widgets (3 catalog-backed: `add-free-widget`, `update-widget`, and `add-pro-widget` when Pro), templates (2), globals (2), and the composite `build-page` tool.

### Implementation Phases (from PLAN.md)

| Priority | Phase | Scope |
|----------|-------|-------|
| P0 | Foundation | Bootstrap, data layer, factory, schemas, 7 read/query tools |
| P1 | Pages & Widgets | Page CRUD (5), layout tools (4), widget tools (10+) |
| P2 | Templates & Composite | Template tools (2), global tools (2), build-page composite (1) |

### Key Design Patterns

- **Container-first**: Uses modern Elementor Container element (flexbox), not legacy Sections/Columns
- **Schema-driven validation**: Widget settings validated against auto-generated JSON schemas before saving
- **Catalog-backed widgets (v3.0.0)**: instead of 62 per-widget tools, a curated catalog (`includes/widgets/`) holds each widget's tier/category/curated params as DATA. The flow is discover → inspect → act: `list-widgets` (compact index, `tier`/`category`/`search` filters) → `get-widget-schema` (curated `params`, `types[]` batch, `full:true` for raw control schema) → `add-free-widget` / `add-pro-widget` (catalog defaults merged automatically) → `update-widget`. Per-turn widget tool-list cost drops ~10×; no capability is lost (any valid Elementor control passes through).
- **Pro-aware**: Pro widget tools only register when Elementor Pro is active; core tools work with free Elementor
- **`elementor_mcp_ability_names` filter** lives in `Elementor_MCP_Plugin::filter_disabled_tools()` (always loaded — NOT in the admin class, which only loads on `is_admin()`). It reads two options: `elementor_mcp_disabled_tools` (user toggles) and `elementor_mcp_low_tool_mode` (essentials-only). When low-tools mode is on, every name outside `Elementor_MCP_Plugin::get_essential_tool_slugs()` is excluded — this is the runtime path that keeps the count under client tool caps.
- **Pro widgets disabled-by-default**: on first admin page load (tracked via `elementor_mcp_defaults_applied` marker option), every Pro-badged slug from `get_all_tools()` is merged into `elementor_mcp_disabled_tools`. Users can re-enable individual Pro widgets from the Tools admin screen.

### Modules Framework (v3.1.0)

A pluggable feature system: substantial features an admin turns on/off from the **Modules** admin tab. `EMCP_Tools_Module` (abstract base) + `EMCP_Tools_Modules_Registry` (singleton) live in `includes/modules/` (free). Active module IDs are stored in the single `emcp_tools_active_modules` option; each module owns `emcp_tools_module_<id>_*` keys. The registry seeds defaults once (marker option `emcp_tools_modules_defaults_applied`) and boots active+available modules on `init` (priority 5). Pro modules register via `EMCP_Tools_Pro_Loader::register_modules()` or the `emcp_tools_register_modules` action. Wired in `EMCP_Tools_Bootstrap::wire_hooks()`.

**Image Optimization module** (`includes/modules/image-optimization/`, free, opt-in — it mutates uploads): compresses generated image sub-sizes on upload and generates `.webp` siblings (`WP_Image_Editor`, no external binaries), then serves WebP by rewriting `wp_get_attachment_*` URLs — frontend gated by `Accept: image/webp`, always in REST/CLI so the MCP media tools (`get-media`/`sideload-image`/`add-stock-image`/etc.) resolve to WebP with no tool changes. Full-size is preserved (trimmed only by an optional max-dimension cap); touched files are backed up under `uploads/emcp-originals/` (reversible). A resumable admin-ajax bulk optimizer (`class-bulk-optimizer.php`, batches of 10, cursor option) processes the existing library, with a Restore-originals companion. Each module's settings live in a per-module overlay modal (own settings group via `EMCP_Tools_Module::settings_group()`, saved independently of the active-modules toggles). Options: `compress`, `webp`, `webp_serve` (frontend WebP serving — MCP/REST/CLI always serve WebP regardless), `quality` (40–95, default 60), `max_dimension` (0=off), `keep_originals`. Availability gates on the image editor supporting WebP output; the card disables the toggle + shows a notice when unsupported. Classes: `class-module.php`, `class-modules-registry.php`, `image-optimization/class-{image-optimization-module,image-optimizer,webp-generator,webp-rewriter,bulk-optimizer}.php`. Tests: `pro/tests/unit/Modules/` (phpunit.xml `Modules` suite).

### Permission Model

| Ability Group | Required WordPress Capability |
|---------------|-------------------------------|
| Read/Query | `edit_posts` |
| Page creation | `publish_pages` or `edit_pages` |
| Widget/layout manipulation | `edit_posts` + ownership check |
| Template management | `edit_posts` |
| Global settings | `manage_options` |
| Delete operations | `delete_posts` + ownership check |
| Stock image search | `edit_posts` |
| Stock image sideload | `upload_files` |
| Stock image add | `edit_posts` + `upload_files` + ownership check |
| Custom CSS (element/page) | `edit_posts` + ownership check |
| Custom JS via HTML widget | `edit_posts` + ownership check |
| Code snippets (create) | `manage_options` + `unfiltered_html` |
| Code snippets (list) | `manage_options` |

## All Implemented Tools (130 registered with Pro + Elementor 4.0+; 127 `emcp-tools/*` + 3 `core/*` — see counts above)

### P0 — Query/Discovery (7 read-only)

| Ability Name | Purpose |
|---|---|
| `emcp-tools/list-widgets` | Compact catalog index of widgets; filter by `tier`/`category`/`search` (v3.0.0) |
| `emcp-tools/get-widget-schema` | Curated `params` for a widget by default (or `types[]` batch); `full:true` returns the raw auto-generated control schema (v3.0.0) |
| `emcp-tools/get-page-structure` | Element tree for a page (containers, widgets, nesting) |
| `emcp-tools/get-element-settings` | Current settings for a specific element on a page |
| `emcp-tools/list-pages` | All Elementor-enabled pages/posts |
| `emcp-tools/list-templates` | Saved Elementor templates from the template library |
| `emcp-tools/get-global-settings` | Active kit/global settings (colors, typography, spacing) |

### WordPress Content (beyond Elementor) — v3.0.0 (8 tools + 3 surfaced core abilities)

The plugin's first step beyond Elementor: general WordPress content management over MCP. Built entirely on WP core functions (`wp_insert_post`, `wp_update_post`, `get_post`, `WP_Query`, `wp_set_object_terms`, etc.) — these tools **never touch `_elementor_data`**. Every returned post carries an `is_elementor` flag so an agent knows to switch to the Elementor tools for builder pages. Capability-gated and **enabled by default**. Featured image + custom-field meta are parameters of create/update (not separate tools). `delete-post` trashes by default (pass `force` to permanently delete).

| Ability Name | Purpose |
|---|---|
| `emcp-tools/list-post-types` | Discovery: list registered public post types (read-only) |
| `emcp-tools/list-taxonomies` | Discovery: list registered taxonomies and their object types (read-only) |
| `emcp-tools/create-post` | Create a post/page/CPT — title, content (classic HTML or block markup), status, slug, author, terms, custom fields, featured image |
| `emcp-tools/get-post` | Read a single post by ID (read-only; includes `is_elementor` flag) |
| `emcp-tools/update-post` | Update an existing post's fields, terms, custom fields, and featured image |
| `emcp-tools/list-posts` | List/query posts of any type with filters (read-only) |
| `emcp-tools/delete-post` | Delete a post — trashes by default; `force:true` permanently deletes (destructive) |
| `emcp-tools/set-post-terms` | Assign taxonomy terms to a post |

Additionally, WordPress core's three read-only context abilities — `core/get-site-info`, `core/get-user-info`, `core/get-environment-info` — are now **surfaced on the EMCP server** so agents can read site/user/environment context without a separate connection.

### WordPress Plugins & Themes — domain 3 (13 tools, v3.0.0)

Discover and manage WordPress plugins and themes over MCP — built on WP core upgrader APIs (`get_plugins`, `activate_plugin`, `delete_plugins`, `Plugin_Upgrader`, `Theme_Upgrader`, `plugins_api`, `themes_api`, `switch_theme`, etc.). Installs are **wordpress.org-only** (by slug; no arbitrary URLs accepted). All 13 tools are enabled-by-default only for the 4 read/search tools; the **9 mutation tools ship disabled-by-default** (admin opts in on the Tools tab).

**Safety model:**
- `EMCP_Tools_Package_Guard` — shared guard class; protected-plugin list (`EMCP_TOOLS_BASENAME`, `elementor/elementor.php`, `elementor-pro/elementor-pro.php`); active-plugin/active-theme checks; direct-filesystem gate (`get_filesystem_method()` must return `'direct'` — otherwise a clean WP_Error is returned instead of hanging on an FTP-credential prompt); on-demand wp-admin upgrader includes; quiet `Automatic_Upgrader_Skin` (messages captured, not echoed)
- Per-op capability gating: `activate_plugins` for list/activate/deactivate; `install_plugins`/`install_themes` for install/search; `update_plugins`/`update_themes` for update; `delete_plugins`/`delete_themes` for delete; `switch_themes` for list-themes/switch-theme
- EMCP Tools, Elementor, and Elementor Pro can **never** be deactivated or deleted via MCP
- The active plugin/theme is protected from delete

| Ability Name | Purpose |
|---|---|
| `emcp-tools/list-plugins` | List installed plugins with active/inactive status, version, update-available flag, and protected marker (read-only) |
| `emcp-tools/search-plugins` | Search the wordpress.org plugin directory by keyword — returns slug, name, version, rating, requirements (read-only) |
| `emcp-tools/install-plugin` | Install a plugin from wordpress.org by slug; optionally activate after install |
| `emcp-tools/activate-plugin` | Activate an installed plugin by file path or folder slug |
| `emcp-tools/deactivate-plugin` | Deactivate a plugin; refuses EMCP Tools and Elementor |
| `emcp-tools/update-plugin` | Update an installed plugin to the latest wordpress.org version; reports up-to-date when no update is pending |
| `emcp-tools/delete-plugin` | Permanently delete an inactive, unprotected plugin (direct FS required) |
| `emcp-tools/list-themes` | List installed themes with active status, version, update-available flag, and protected marker (read-only) |
| `emcp-tools/search-themes` | Search the wordpress.org theme directory by keyword (read-only) |
| `emcp-tools/install-theme` | Install a theme from wordpress.org by slug (direct FS required) |
| `emcp-tools/switch-theme` | Switch the active theme by stylesheet slug |
| `emcp-tools/update-theme` | Update an installed theme to the latest wordpress.org version (direct FS required) |
| `emcp-tools/delete-theme` | Permanently delete an inactive, unprotected theme (direct FS required) |

### WordPress Media Library — domain 4 (3 tools, v3.0.0)

Manage existing Media Library attachments over MCP — built on WP core attachment functions (`get_post`, `wp_get_attachment_metadata`, `wp_get_attachment_image_src`, `wp_get_attachment_url`, `wp_update_post`, `update_post_meta`, `wp_delete_attachment`). `get-media` and `update-media` are **enabled by default**; `delete-media` ships **disabled-by-default**.

**Three-way delete gate:** `delete-media` requires (1) it is enabled in the Tools tab (disabled-by-default), (2) the caller has `delete_post` on the attachment ID, and (3) an explicit `confirm:true` in the tool input. WordPress bypasses Trash for media unless the `MEDIA_TRASH` constant is defined — so deletion is effectively permanent on most sites. Pass `force:true` to skip Trash even when `MEDIA_TRASH` is on. URL uploads continue to use the existing `sideload-image`.

| Ability Name | Purpose |
|---|---|
| `emcp-tools/get-media` | Full detail for one attachment — every registered image size (URL + dimensions), mime type, filesize, alt text, caption, description, raw attachment metadata. Read-only (`edit_posts`). |
| `emcp-tools/update-media` | Edit an attachment's title, alt text, caption, and/or description. Only fields passed in the input change (`edit_post` on attachment ID). |
| `emcp-tools/delete-media` | Delete an attachment; **destructive and effectively permanent**; disabled-by-default; requires `confirm:true`; pass `force:true` to skip Trash even when `MEDIA_TRASH` is defined (`delete_post` on attachment ID). |

### WordPress Users — domain 5 (4 tools, v3.0.0)

Safe user management over MCP — built on WP core user functions (`WP_User_Query`, `get_userdata`, `wp_insert_user`, `wp_update_user`, `wp_generate_password`, `wp_send_new_user_notifications`, `get_role`, `user_can`). The security boundary is the design itself: no delete-user tool, no role-change tool, passwords are auto-generated and emailed (never returned), and a strict privilege guard means agents can only create non-admin accounts and can never edit any user with admin-level capabilities.

**Privilege guard:** A protected-capability set (`manage_options`, `promote_users`, `edit_users`, `delete_users`, `manage_network`) is checked against both role capabilities (`role_has_admin_caps`) and the target user's actual capabilities (`user_has_admin_caps`). Any role or user that holds one of these caps is off-limits via MCP. `list-users`/`get-user` are **enabled by default** (`list_users`); `create-user`/`update-user` ship **disabled-by-default** (admin opts in on the Tools tab; `create_users`/`edit_users` caps).

| Ability Name | Purpose |
|---|---|
| `emcp-tools/list-users` | List WordPress users; filter by role or search text; paginated. Returns id, username, display name, email, roles, registration date, and post count. Never returns passwords or auth data. Read-only (`list_users`). |
| `emcp-tools/get-user` | Full profile detail for one user — adds first/last name, nickname, URL, description, and an `is_admin` flag (true = off-limits to `update-user`). Read-only (`list_users`). |
| `emcp-tools/create-user` | Create a new non-admin WordPress user. A strong password is auto-generated and emailed via `wp_send_new_user_notifications` — the password is **never returned**. Role defaults to `subscriber`; administrator and any admin-grade role are refused. (`create_users`) |
| `emcp-tools/update-user` | Update a non-admin user's profile (email, first/last name, display name, nickname, URL, description). Cannot change roles or passwords; refuses any user with admin-level capabilities. (`edit_users`) |

### P1 — Page CRUD (5 tools)

| Ability Name | Purpose |
|---|---|
| `emcp-tools/create-page` | Create a new WP page/post with Elementor enabled |
| `emcp-tools/update-page-settings` | Update page-level Elementor settings (background, padding, etc.) |
| `emcp-tools/delete-page-content` | Clear all Elementor content from a page (destructive) |
| `emcp-tools/import-template` | Import JSON template structure into a page |
| `emcp-tools/export-page` | Export page's full Elementor data as JSON |

### P1 — Layout (4 tools)

| Ability Name | Purpose |
|---|---|
| `emcp-tools/add-container` | Add a flexbox container (top-level or nested) |
| `emcp-tools/move-element` | Move an element to a new parent/position |
| `emcp-tools/remove-element` | Remove an element and all children (destructive) |
| `emcp-tools/duplicate-element` | Duplicate element with fresh IDs |

### Widgets — catalog-backed (5 tools, v3.0.0)

The 62 per-widget convenience tools and the old universal `add-widget` were removed in v3.0.0. They are replaced by a **catalog-backed** model: the 62 widgets' tiers, categories, and curated params now live as DATA in `includes/widgets/` (`class-widget-catalog.php` + `catalog-{free,pro,woo}.php`, 27 free / 30 pro / 5 woo), served by `EMCP_Tools_Widget_Catalog`. No capability is lost — every widget and every curated parameter is still reachable through **discover → inspect → act**, and any valid Elementor control passes straight through. This cuts per-turn widget tool-list context ~10× (~18–20k → ~2k tokens).

| Ability Name | Purpose |
|---|---|
| `emcp-tools/list-widgets` | Compact catalog index of widgets; filter by `tier`/`category`/`search`. Step 1 of discover → inspect → act. |
| `emcp-tools/get-widget-schema` | Curated `params` for a widget (or `types[]` batch); `full:true` for the raw control schema. |
| `emcp-tools/add-free-widget` | Add any free/core widget by type (always registered; folds the old `add-widget`). |
| `emcp-tools/add-pro-widget` | Add an Elementor Pro / WooCommerce widget by type (registered only when Elementor Pro is active). |
| `emcp-tools/update-widget` | Update settings on an existing widget. |

### P2 — Templates (2 tools)

| Ability Name | Purpose |
|---|---|
| `emcp-tools/save-as-template` | Save a page or element as reusable template |
| `emcp-tools/apply-template` | Apply a saved template to a page |

### P2 — Global Settings (2 tools)

| Ability Name | Purpose |
|---|---|
| `emcp-tools/update-global-colors` | Update site-wide color palette in Elementor kit |
| `emcp-tools/update-global-typography` | Update site-wide typography in Elementor kit |

### P2 — Composite (1 tool)

| Ability Name | Purpose |
|---|---|
| `emcp-tools/build-page` | Create complete page from declarative structure in one call |

### Media Library (4 tools total — 1 legacy + 3 domain-4 additions, v3.0.0)

| Ability Name | Purpose |
|---|---|
| `emcp-tools/list-media` | List/search images already in the WordPress Media Library (the site's own uploads). Optional `search` matches title, alt text, caption, and description; `mime_type` / pagination / sort filters. Read-only WP_Query, no HTTP. ([#25](https://github.com/msrbuilds/elementor-mcp/issues/25)) |
| `emcp-tools/get-media` | Full detail for one attachment — every registered image size (URL + dimensions), mime type, filesize, alt text, caption, description, raw attachment metadata. Read-only. (v3.0.0) |
| `emcp-tools/update-media` | Edit an attachment's title, alt text, caption, and/or description. Only fields passed change. (v3.0.0) |
| `emcp-tools/delete-media` | Delete an attachment; **destructive and effectively permanent**; disabled-by-default; requires `confirm:true`. (v3.0.0) |

### WordPress Users — domain 5 (4 tools, v3.0.0)

| Ability Name | Purpose |
|---|---|
| `emcp-tools/list-users` | List WordPress users (admin-only); filter by role/search; paginated. Never returns passwords or auth data. Read-only, enabled by default. |
| `emcp-tools/get-user` | Full profile detail for one user plus an `is_admin` flag. Read-only, enabled by default. |
| `emcp-tools/create-user` | Create a new non-admin user; auto-generates a password and emails a set-password link — password never returned. Admin-grade roles refused. Disabled-by-default. |
| `emcp-tools/update-user` | Edit a non-admin user's profile fields only (no role/password change; admins refused). Disabled-by-default. |

### WordPress Performance & Security — domain 6 (2 tools, v3.0.0)

Two read-only diagnostics over MCP, both self-contained (the only off-site calls are wordpress.org, optional + graceful). The admin tools section was renamed **"Performance" → "Performance & Security"** (internal category id unchanged); both tools appear under it with a read-only badge.

**Performance Analyzer.** Built on WP core (`$wpdb`, `wp_remote_get`, `_get_cron_array`, `DOMDocument`). Audits server config + WordPress internals in-process, and analyzes a target page via a **same-host-enforced loopback fetch** (rejects off-host URLs and off-host redirects). Returns a scored report (0-100 + A–F grade) grouped into `server`/`database`/`config`/`page`/`assets`, with ranked `top_recommendations`. Degrades gracefully when the loopback fetch is blocked. `manage_options`; enabled by default. Implemented in `includes/abilities/class-performance-abilities.php` + `includes/performance/class-performance-{finding,server-audit,page-audit,analyzer}.php`.

**Security & Malware Scanner.** The security counterpart to the Performance Analyzer — a read-only scan across 4 dimensions returning a scored report (0-100 + A–F grade) with severities and ranked recommendations: (1) **malware heuristics** — scans PHP in uploads + active plugins/themes (`deep:true` for the whole tree) for eval/base64 obfuscation, request-driven code execution, command execution, webshell markers, large encoded blobs, and executable PHP under uploads — ABSPATH-confined, bounded by file-count + time caps (partial results flagged), never returns full file contents (path:line + short snippet only), excludes the plugin's own dir + the managed snippet sandbox, skips benign empty/comment-only guards; (2) **core integrity** — verifies WordPress core files against official wordpress.org checksums (modified→critical, missing→warning; degrades gracefully offline); (3) **hardening** — DISALLOW_FILE_EDIT, debug output in production, `admin` username, XML-RPC, version disclosure (readme.html / generator meta), HTTPS, security headers (one same-host loopback GET); (4) **outdated/abandoned software** — outdated core/plugins/themes + plugins closed/removed from wordpress.org (bounded, transient-cached) and inactive-component housekeeping. Inputs (all optional): `checks` (subset of malware/integrity/hardening/software), `deep`, `max_files`, `max_seconds`. `manage_options`; enabled by default; registers unconditionally. Implemented in `includes/abilities/class-security-abilities.php` + `includes/security/class-security-{finding,malware-audit,integrity-audit,hardening-audit,software-audit,scanner}.php`.

| Ability Name | Purpose |
|---|---|
| `emcp-tools/analyze-performance` | Audit server config, WordPress internals (DB size, autoloaded options, revisions, cron backlog, object cache, OPcache, plugin count), and a target page (default frontpage; optional `url`/`post_id`) for bottlenecks; returns a scored, severity-tagged report with recommendations. Read-only. |
| `emcp-tools/scan-security` | Scan across malware heuristics, core-file integrity, hardening, and outdated/abandoned software; returns a scored (0-100 + A–F) report with severities + ranked recommendations. Bounded ABSPATH-confined malware walk (`deep`/`max_files`/`max_seconds`; partial results flagged) that never returns full file contents; optional `checks` subset. Self-contained (only wordpress.org calls, graceful offline). Read-only; enabled by default. |

### Filesystem — domain 7 (6 tools, v3.0.0)

Read and scan any file inside the WordPress install; write/delete are disabled-by-default. All paths are confined to ABSPATH — no directory traversal and no symlink escape outside the root. Writes auto-back up the original file before mutating, refuse `wp-config.php` and `.htaccess`, require the `edit_files` capability (honoring `DISALLOW_FILE_EDIT`), and record every mutation to an audit log. `delete-file` requires an explicit `confirm:true`. All tools require `manage_options`. Implemented in `includes/abilities/class-filesystem-abilities.php`.

> **Risk notice (per explicit project decision).** File read can expose `wp-config.php` and any secret stored as a plain file in the WordPress tree. Enabling the write tools is effectively remote code execution — an agent can edit any PHP file that WordPress then loads and executes. These tools are confined to ABSPATH, ship disabled-by-default, are admin-gated, auto-back up files, and audit-log every write. Arbitrary filesystem access is contrary to WordPress.org plugin guidelines — this feature is included per an explicit project decision and is disabled unless the admin opts in on the Tools tab.

| Ability Name | Purpose |
|---|---|
| `emcp-tools/read-file` | Return the contents of any file inside ABSPATH. Read-only. (`manage_options`) |
| `emcp-tools/list-directory` | List entries in a directory inside ABSPATH — names, types, sizes, modified times. Read-only. (`manage_options`) |
| `emcp-tools/search-files` | Search for files matching a name pattern or containing a string within ABSPATH. Read-only. (`manage_options`) |
| `emcp-tools/write-file` | Write (create or overwrite) a file inside ABSPATH; auto-backs up original; refuses `wp-config.php`/`.htaccess`; audit-logged. Disabled-by-default. (`edit_files` + `manage_options`) |
| `emcp-tools/edit-file` | Apply a targeted find-and-replace or line-range edit to a file inside ABSPATH; auto-backs up original; refuses `wp-config.php`/`.htaccess`; audit-logged. Disabled-by-default. (`edit_files` + `manage_options`) |
| `emcp-tools/delete-file` | Delete a file inside ABSPATH; requires `confirm:true`; refuses `wp-config.php`/`.htaccess`; audit-logged. Disabled-by-default. (`edit_files` + `manage_options`) |

### Database — domain 8 (6 tools, v3.0.0)

Direct database inspection and structured writes over MCP. The 3 read tools are **enabled by default**; the **3 write tools ship disabled-by-default** (admin opts in on the Tools tab). All tools require `manage_options`. Implemented in `includes/abilities/class-database-abilities.php`.

> **Direct DB access. The read path (`query`) is bounded by a read-only-SQL validator that rejects writes/DDL/stacked statements, MySQL `/*!` executable comments, and file-access SQL (`INTO OUTFILE`/`LOAD_FILE`); writes are structured/parameterized via `$wpdb` (no raw write-SQL/DDL), disabled-by-default + admin-gated, refuse `wp_users`/`wp_usermeta`, force a non-empty WHERE clause, snapshot a before-image to an audit log, and `delete-rows` needs `confirm:true`. Arbitrary DB access is contrary to WordPress.org plugin guidelines — included per an explicit project decision.**

| Ability Name | Purpose |
|---|---|
| `emcp-tools/list-tables` | List all tables in the WordPress database with row counts and sizes. Read-only. (`manage_options`) |
| `emcp-tools/describe-table` | Return column definitions and indexes for a table. Read-only. (`manage_options`) |
| `emcp-tools/query` | Run a read-only SQL query (SELECT/SHOW/DESCRIBE/EXPLAIN only); results capped; writes/DDL/stacked statements/`/*!` comments/file-access SQL rejected. Read-only. (`manage_options`) |
| `emcp-tools/insert-row` | Insert a row into a table using `$wpdb`; refuses `wp_users`/`wp_usermeta`; audit-logged. Disabled-by-default. (`manage_options`) |
| `emcp-tools/update-rows` | Update rows matching a forced non-empty WHERE clause; before-image snapshot; audit-logged. Disabled-by-default. (`manage_options`) |
| `emcp-tools/delete-rows` | Delete rows matching a forced non-empty WHERE clause; requires `confirm:true`; before-image snapshot; audit-logged. Disabled-by-default. (`manage_options`) |

### Stock Images (3 tools)

| Ability Name | Purpose |
|---|---|
| `emcp-tools/search-images` | Search a stock provider (Unsplash / Pexels / Pixabay) for photos by keyword; optional `provider` param, else first connected. Needs a free API key for ≥1 provider on the Connection tab |
| `emcp-tools/sideload-image` | Download an external image URL into the WordPress Media Library |
| `emcp-tools/add-stock-image` | Search + sideload + add image widget to page in one call |

### Custom Code (4 tools)

| Ability Name | Purpose |
|---|---|
| `emcp-tools/add-custom-css` | Add custom CSS to a specific element or page-level (Pro only, uses `selector` keyword) |
| `emcp-tools/add-custom-js` | Add JavaScript to a page via HTML widget with auto `<script>` wrapping |
| `emcp-tools/add-code-snippet` | Create a site-wide Custom Code snippet for head/body injection (Pro only) |
| `emcp-tools/list-code-snippets` | List existing Custom Code snippets with locations and statuses (Pro only) |

### PHP Snippets — Sandbox (6 tools, FREE, off by default)

Free but capability-gated and powerful, so all six are **disabled-by-default** (the `php_snippets` category in `get_all_tools()`; the v4 defaults marker seeds them via `EMCP_Tools_Admin::php_snippet_tool_slugs()`). The whole point is the **human approval gate**: AI can author + validate **drafts**, but there is **no activate tool** — only an admin can activate a snippet (in EMCP Tools → Sandbox), which is the only way it ever executes. Write tools require `manage_options` + `unfiltered_html`; reads require `manage_options`.

- `EMCP_Tools_PHP_Snippet_Validator` (`includes/class-php-snippet-validator.php`) — parse check (`token_get_all(…, TOKEN_PARSE)`) + token-walk security scan. CRITICAL findings block create/activate (exec/eval/backtick/variable-functions/file-writes/network/obfuscation-decoders/destructive-SQL/dynamic-include); WARNING findings are surfaced to the reviewer. Static analysis is a guardrail, not a guarantee.
- `EMCP_Tools_PHP_Snippet_Store` (`includes/class-php-snippet-store.php`) — `emcp_php_snippet` CPT + sandbox (`uploads/emcp-widgets/snippets/{id}.php`, `.htaccess`-blocked). Drafts have **no executable file**; activation wraps the code in `emcp_php_snippet_{id}()`, writes it, records a sha256 manifest entry.
- `EMCP_Tools_PHP_Snippet_Loader` (`includes/class-php-snippet-loader.php`) — manifest-only, hash-verified include; runs ACTIVE snippets on their hook and/or via `[emcp_snippet id="N"]`, inside try/catch + a `register_shutdown_function` that auto-deactivates a snippet that fatals.

| Ability Name | Purpose |
|---|---|
| `emcp-tools/validate-php-snippet` | Static parse + security scan of code; no store, no run. Returns critical/warning findings |
| `emcp-tools/create-php-snippet` | Create an **inactive draft** (validated; critical = rejected). Never runs until an admin activates it |
| `emcp-tools/update-php-snippet` | Update a snippet's code/settings; re-validates |
| `emcp-tools/get-php-snippet` | Return code, status, shortcode, and validation report |
| `emcp-tools/list-php-snippets` | List snippets with status and run context |
| `emcp-tools/delete-php-snippet` | Delete a snippet and its sandbox file |

### SEO & Accessibility Toolkit — Pro (7 tools, off by default)

Pro-only, registered only when `emcp_pro_fs()->can_use_premium_code()` (self-guarded like the brand-kit tools). All seven are **disabled-by-default** (the `seo_a11y` category in `get_all_tools()` carries the `pro` badge; the v2 defaults marker in `maybe_apply_default_disabled_tools()` seeds them into `elementor_mcp_disabled_tools` on upgrade — `Elementor_MCP_Admin::seo_a11y_tool_slugs()` is the canonical list). Users re-enable individual tools on the Tools tab. No external API — pure PHP over the Elementor data layer + SEO-plugin meta. The five audits/generators are **read-only**; the two fixers **mutate only when `apply: true`** (dry-run preview by default) and are reversible via Elementor revisions. Shared helpers: `Elementor_MCP_Content_Extractor` (normalized page view), `Elementor_MCP_Color_Contrast` (WCAG math), `Elementor_MCP_Seo_Meta` (Yoast/Rank Math/core).

| Ability Name | Purpose |
|---|---|
| `emcp-tools/audit-page-seo` | Scored on-page SEO report: H1 presence, title/meta length, canonical, heading hierarchy, image alts, internal links, word count, optional target-keyword usage |
| `emcp-tools/extract-keywords-from-content` | Frequency/TF-IDF-style keyword + bigram extraction from page text (stop-word filtered, no external service) |
| `emcp-tools/generate-meta-tags` | Proposes an SEO title (≤60) + meta description (≤155) from page content, keyword-front-loaded; proposal-only |
| `emcp-tools/generate-schema-markup` | Generates JSON-LD (Article / LocalBusiness / FAQPage / Service / Product); LocalBusiness takes a `business` object, FAQPage a `faqs` array; proposal-only |
| `emcp-tools/audit-page-a11y` | WCAG-oriented report: color contrast (best-effort, `inconclusive` when background unresolved), missing alts, heading order, generic/empty link text, form-label coverage |
| `emcp-tools/fix-color-contrast` | Proposes (and with `apply:true` writes) adjusted text colors so failing pairs meet WCAG AA. Dry-run by default; writes the resolved `*_color` setting via the data layer |
| `emcp-tools/add-alt-text-from-context` | Proposes (and with `apply:true` writes) alt text for images lacking it, derived from filename → nearest heading → page title. Dry-run by default; writes `_wp_attachment_image_alt` + the image widget's alt |

### Atomic Elements — Elementor 4.0+ (13 tools)

These tools only register when Elementor >= 4.0 is detected. Legacy tools continue to work alongside them.

**Atomic elements use a typed props system (`$$type` wrappers) and a separate `styles` map for visual styling. The convenience tools handle this automatically — AI agents pass simple flat values.**

| Ability Name | Purpose |
|---|---|
| `emcp-tools/detect-elementor-version` | Returns Elementor version and whether atomic elements are supported. Call first to choose tool family. |
| `emcp-tools/add-atomic-widget` | Universal: add any atomic widget by type name with raw $$type settings |
| `emcp-tools/update-atomic-widget` | Universal: partial-merge update on an existing atomic widget's settings |
| `emcp-tools/add-atomic-heading` | Convenience: atomic heading (e-heading). Params: title, tag (h1-h6), link, css_id |
| `emcp-tools/add-atomic-paragraph` | Convenience: atomic paragraph (e-paragraph). Params: content, link, css_id |
| `emcp-tools/add-atomic-button` | Convenience: atomic button (e-button). Params: text, link, target_blank, css_id |
| `emcp-tools/add-atomic-image` | Convenience: atomic image (e-image). Params: image_id, image_url, alt, link, css_id |
| `emcp-tools/add-atomic-svg` | Convenience: atomic SVG (e-svg). Params: svg_id, svg_url, css_id |
| `emcp-tools/add-atomic-youtube` | Convenience: atomic YouTube embed (e-youtube). Params: video_url, css_id |
| `emcp-tools/add-atomic-video` | Convenience: atomic self-hosted video (e-self-hosted-video). Params: video_url, video_id, css_id |
| `emcp-tools/add-atomic-divider` | Convenience: atomic divider (e-divider). Params: css_id |
| `emcp-tools/add-flexbox` | Atomic flexbox container (e-flexbox). Params: direction, justify, align, gap, wrap, tag, padding, background_color |
| `emcp-tools/add-div-block` | Atomic div-block container (e-div-block). Params: tag, padding, background_color |

### Global Classes — Class Manager (1 tool, Elementor 4.0+)

Registers only when Elementor's `Global_Classes_Repository` exists (Elementor 4.0+). Read-only (`edit_posts`); resolves the `g-` style IDs applied to elements. Implemented in `includes/abilities/class-global-classes-abilities.php`; uses Elementor's own repository + `EMCP_Tools_Atomic_Props::unwrap()` to flatten the `$$type` CSS props. ([#55](https://github.com/msrbuilds/elementor-mcp/issues/55))

| Ability Name | Purpose |
|---|---|
| `emcp-tools/list-global-classes` | Map Class Manager `g-` IDs → human label + CSS properties (per breakpoint/state). Optional `class_ids` filter; omit for all. |

### Atomic Element Data Model (Elementor 4.0+)

Atomic elements have a different JSON structure from legacy widgets:

- **`settings`** contains content props using `$$type` wrappers: `{ "tag": { "$$type": "string", "value": "h2" } }`
- **`styles`** (sibling of settings) contains visual/layout CSS using local style classes
- **`interactions`** (sibling) stores entrance/hover/click interactions
- Style properties use CSS property names (kebab-case): `flex-direction`, `justify-content`, `gap`
- Layout props are NOT in settings — they're in `styles[class_id].variants[].props`

### Key Architecture Files (Atomic)

| File | Purpose |
|---|---|
| `includes/class-atomic-props.php` | Static helpers to wrap/unwrap $$type values |
| `includes/class-atomic-styles.php` | Builds local style classes for flex layout, spacing, colors |
| `includes/abilities/class-atomic-widget-abilities.php` | 10 atomic widget tools |
| `includes/abilities/class-atomic-layout-abilities.php` | 2 container tools + detect-version |

## Connecting to the MCP Server

### Prerequisites

- WordPress with Elementor + MCP Adapter + MCP Tools for Elementor all active
- One of: WP-CLI (for local) or Node.js 18+ (for remote/proxy)
- A WordPress Application Password (Users > Profile > Application Passwords)

### Option A: WP-CLI stdio (local dev, recommended)

The MCP Adapter includes a built-in WP-CLI stdio bridge. No HTTP round-trip, no sessions, no auth config needed.

**Claude Code** (`.mcp.json` already in project root):
```json
{
  "mcpServers": {
    "emcp-tools": {
      "type": "stdio",
      "command": "wp",
      "args": ["mcp-adapter", "serve", "--server=emcp-tools-server", "--user=admin", "--path=/path/to/wordpress"]
    }
  }
}
```

**Claude Desktop** (add to `%APPDATA%\Claude\claude_desktop_config.json`):
```json
{
  "mcpServers": {
    "emcp-tools": {
      "command": "wp",
      "args": ["mcp-adapter", "serve", "--server=emcp-tools-server", "--user=admin", "--path=/path/to/wordpress"]
    }
  }
}
```

**Verify:** `wp mcp-adapter list --path=/path/to/wordpress` should show `emcp-tools-server`.

### Option B: Node.js proxy (remote sites)

For remote WordPress sites or environments without WP-CLI, use the Node.js proxy. It bridges stdio ↔ WordPress HTTP endpoint with Application Password auth. **The client launches it as a local subprocess, so it must run on the client machine, not the server** — on shared hosting there's no local access to `wp-content/plugins/...`. Two ways to run it:

**Recommended — npx runner** (`@msrbuilds/emcp-proxy`, published from `bin/` — see [bin/package.json](bin/package.json)). Nothing to copy or keep in sync:

```json
{
  "mcpServers": {
    "emcp-tools": {
      "command": "npx",
      "args": ["-y", "@msrbuilds/emcp-proxy@latest"],
      "env": {
        "WP_URL": "https://your-site.com",
        "WP_USERNAME": "admin",
        "WP_APP_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx",
        "MCP_PROTOCOL_VERSION": "2024-11-05"
      }
    }
  }
}
```

**Alternative — local copy:** extract `bin/mcp-proxy.mjs` from the ZIP to the client machine and point `args` at that local path (`["C:\\local\\path\\to\\mcp-proxy.mjs"]`). For local dev from the plugin dir, `["bin/mcp-proxy.mjs"]` works since client and server share the filesystem.

`MCP_PROTOCOL_VERSION=2024-11-05` makes the proxy rewrite the adapter's `2025-06-18` handshake for clients that reject it (e.g. some Claude Desktop builds).

### Option C: Direct HTTP (VS Code MCP extension)

```json
{
  "servers": {
    "emcp-tools": {
      "type": "http",
      "url": "https://your-site.com/wp-json/mcp/emcp-tools-server",
      "headers": {
        "Authorization": "Basic BASE64_ENCODED_CREDENTIALS"
      }
    }
  }
}
```

### Testing with MCP Inspector

```bash
npx @modelcontextprotocol/inspector wp mcp-adapter serve --server=emcp-tools-server --user=admin --path=/path/to/wordpress
```

### Troubleshooting

- **"No MCP servers registered"**: Ensure MCP Tools for Elementor plugin is active and dependencies are met
- **HTTP 401**: Check Application Password is correct and user has `edit_posts` capability
- **Session errors**: The HTTP endpoint requires `Mcp-Session-Id` header after `initialize`; the proxy handles this automatically
- **WP-CLI not found on Windows**: Use full path to `php.exe` and `wp-cli.phar`

See `mcp-config-examples.json` for copy-paste configs for all supported clients.

## WordPress Plugin Conventions

This project follows the patterns defined in `.claude/skills/wp-plugin-dev/`:

- **Bootstrap file is minimal** — `elementor-mcp.php` contains only plugin header, constants, dependency checks, `require_once` statements, and singleton init. No feature logic.
- **WordPress naming**: `snake_case` for functions/variables, `Upper_Snake_Case` for classes, `UPPER_SNAKE` for constants
- **Prefix everything**: All functions, classes, hooks, options use the plugin prefix
- **All strings translatable** using `__()`, `_e()`, `esc_html__()`, etc.
- **Security is non-negotiable**: Sanitize all input (`sanitize_text_field`, `absint`, etc.), escape all output (`esc_html`, `esc_attr`, `esc_url`), use `$wpdb->prepare()` for SQL, verify nonces on forms/AJAX, check capabilities before privileged operations
- **Enqueue assets properly** via `wp_enqueue_script()`/`wp_enqueue_style()`, never hardcode tags
- **GPL-2.0-or-later** license required

## Claude Skills Available

Two custom skills are configured in `.claude/skills/`:

- **wp-plugin-dev** — Scaffolding and building WordPress plugins following WP coding standards. Has reference docs for architecture patterns, security functions, and WP.org guidelines.
- **wp-plugin-review** — Automated + manual plugin review (PHPCS/WPCS, PHPStan, PHPUnit, security audit, accessibility). Produces a structured Markdown report.
