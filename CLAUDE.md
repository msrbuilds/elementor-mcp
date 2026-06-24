# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

MCP Tools for Elementor Plugin — a WordPress plugin that extends the official WordPress MCP Adapter to expose Elementor data, widgets, structures, and methods as MCP (Model Context Protocol) tools. This enables AI tools (Claude, Cursor, etc.) to create and manipulate Elementor page designs programmatically via up to ~70 MCP tools (scales with environment). As of v3.0.0 the 62 per-widget convenience tools were folded into a catalog-backed model (5 widget tools), so the active surface is far smaller while every widget remains reachable. As of **v3.1.0** the toolset takes its first step beyond Elementor: 8 general-WordPress **Content tools** (create/read/update/list/delete posts of any type, plus taxonomy and post-type discovery) built on WP core, never touching `_elementor_data`.

## Companion projects (sibling folders, edit from here)

| Project | Path | What it is |
|---|---|---|
| **Master prompts library** | `E:\MSR Builds\Products\EMCP\prompts\` | Source-of-truth markdown files for the 50+ Premium Prompts. 10 categories (Automotive, Food & Dining, General, Health & Wellness, Home Services, Lifestyle & Entertainment, Pets, Professional Services, Retail, Weddings). Never bundled in the plugin zip. |
| **Website + docs + API** | `E:\MSR Builds\Products\EMCP\website\` | Astro 5 + Starlight + Tailwind + Postgres + Drizzle. Hosts the marketing site, comprehensive docs, and the `/api/emcp/prompts.json` license-gated endpoint the plugin's Pro Prompts page fetches from. See `website/PLAN.md` for the full implementation spec. Hosted via Dokploy at `emcp.msrbuilds.com` (planned). |

When editing premium-prompts behavior, the plugin code (`includes/admin/class-pro-prompts.php`) and the website's API endpoint (`website/src/pages/api/emcp/prompts.json.ts` per the PLAN) must stay in sync via the contract in `docs/PREMIUM_PROMPTS_API.md`.

**Current status: v3.1.0 — All phases implemented (P0/P1/P2) plus Elementor 4.0 atomic elements, top-level admin menu, the v3.0.0 catalog-backed widget consolidation, and the v3.1.0 WordPress Content tools (the first step beyond Elementor).** Foundation layer, query tools, page CRUD, layout, the 5 catalog-backed widget tools, template, global, composite tools, stock images, SVG icons, custom code tools, 13 atomic element tools for Elementor 4.0+, 8 general-WordPress content tools, and a curated essentials filter (Low-tools mode, now largely obsolete after the consolidation).

**Tool counts by configuration (v3.1.0 — measured against a live `tools/list` via WP-CLI; v3.1.0 adds the 8 Content tools + 3 surfaced `core/*` abilities = +11, all enabled-by-default):**
- Free Elementor only: **~55** (44 base + 11)
- Free Elementor + Elementor 4.0+ atomic: **~69** (58 base + 11)
- With Elementor Pro: **~81** (70 base + 11)
- With Elementor Pro + Elementor 4.0+: **95** (84 base + 11) — **measured live** (Pro + Elementor 4.1), confirming the +11 delta
- With Pro + WooCommerce + Elementor 4.0+: **95** — WooCommerce widgets are reached through `add-pro-widget` (catalog tier `woo`), so they add **no** new tools.
- Low-tools mode (any config): still available but largely obsolete — the consolidation already keeps the surface well under common client caps.

> The Pro + Elementor 4.x config was measured live at **95 tools** (84 v3.0.0 base + 8 Content + 3 `core/*`), confirming the +11 delta. The other rows apply the same measured +11 to their v3.0.0 base. Of the registered total, ~21 still ship disabled-by-default (SEO/A11y, Widget Builder, PHP Snippets); the Content tools and `core/*` abilities are all enabled by default.

> **These are REGISTERED counts.** Three groups ship **disabled-by-default** — SEO & Accessibility (**7**, Pro), Widget Builder (**8**, Pro), and PHP Snippets / Sandbox (**6**, free) = **21** tools registered-but-off. So the typical **active** surface is ~21 smaller until a user enables them on the Tools tab (e.g. Pro + Elementor 4.0+ ≈ **63** active by default).
>
> **What the v3.0.0 consolidation changed.** Every per-widget convenience tool (62) plus the old universal `add-widget` were removed; `add-free-widget` (always) and `add-pro-widget` (Pro only) were added. The 62 curated widgets are now catalog DATA (27 free / 30 pro / 5 woo) served by `EMCP_Tools_Widget_Catalog`, not individual tools. The widget portion of the per-turn `tools/list` dropped from ~18–20k tokens to ~1.2k (the five widget tool schemas total ~5 KB). Verified end-to-end via the WP-CLI MCP stdio server: `tools/list` shows exactly the 5 widget tools and none of the 62 old names, and a real `add-container` → `add-free-widget` round-trip persists to `_elementor_data`.

See `PLAN.md` for the full architectural specification.

## Dependencies & Requirements

- WordPress >= 6.9 (the Abilities API — `wp_register_ability()` — is core in 6.9+/7.0)
- Elementor >= 3.20 (container support required; >= 4.0 for atomic elements)
- WordPress Abilities API — core in WP 6.9+/7.0 (the only hard external dep is Elementor)
- WordPress MCP Adapter — **bundled** with the plugin since v1.7.4 (`includes/vendors/mcp-adapter/`); no separate install needed. If a standalone MCP Adapter plugin is active, the plugin defers to it (see `Elementor_MCP_Adapter_Bootstrap`).
- PHP >= 8.2

## Build & Development Commands

No external dependencies. The plugin uses WordPress core, Elementor, the core Abilities API, and a **bundled** copy of the MCP Adapter (loaded by `Elementor_MCP_Adapter_Bootstrap::ensure()` only when no standalone adapter plugin is active). Only the adapter's `includes/` source is vendored — its dev-only Composer `vendor/` is not, since the package has zero runtime dependencies.

For plugin review tooling, the `.claude/skills/wp-plugin-review/scripts/setup_tools.sh` script installs PHPCS, WPCS, PHPStan, and PHPUnit.

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
│   ├── class-openverse-client.php             # HTTP client for Openverse image search API
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
│   │   └── class-content-abilities.php       # v3.1.0: 8 WordPress Content tools (list-post-types, list-taxonomies, create/get/update/list/delete-post, set-post-terms) — WP core only, never touches _elementor_data
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
└── tests/                                      # PHPUnit tests (not yet created)
```

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

## All Implemented Tools (up to ~58 — see counts above)

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

### WordPress Content (beyond Elementor) — v3.1.0 (8 tools + 3 surfaced core abilities)

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

### Media Library (1 tool)

| Ability Name | Purpose |
|---|---|
| `emcp-tools/list-media` | List/search images already in the WordPress Media Library (the site's own uploads). Optional `search` matches title, alt text, caption, and description; `mime_type` / pagination / sort filters. Read-only WP_Query, no HTTP. ([#25](https://github.com/msrbuilds/elementor-mcp/issues/25)) |

### Stock Images (3 tools)

| Ability Name | Purpose |
|---|---|
| `emcp-tools/search-images` | Search Openverse (WordPress.org) for Creative Commons images by keyword |
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
