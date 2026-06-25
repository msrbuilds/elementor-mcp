<h1 align="center">
  <img src="assets/img/icon-sm.png" width="72" alt="EMCP Tools logo"><br>
  MCP Tools for Elementor
</h1>

<div align="center">

[![Version](https://img.shields.io/github/v/release/msrbuilds/elementor-mcp?label=version&color=blue)](https://github.com/msrbuilds/elementor-mcp/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.9-21759B.svg)](https://wordpress.org)
[![Elementor](https://img.shields.io/badge/Elementor-%3E%3D3.20-92003B.svg)](https://elementor.com)
[![MCP Tools](https://img.shields.io/badge/MCP_Tools-up%20to%20120-orange.svg)](#available-tools)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)
[![GitHub Issues](https://img.shields.io/github/issues/msrbuilds/elementor-mcp)](https://github.com/msrbuilds/elementor-mcp/issues)
[![GitHub Stars](https://img.shields.io/github/stars/msrbuilds/elementor-mcp?style=social)](https://github.com/msrbuilds/elementor-mcp)

</div>

A WordPress plugin that extends the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) to expose Elementor data, widgets, and page design tools as [MCP (Model Context Protocol)](https://modelcontextprotocol.io/) tools. This enables AI agents like Claude, Cursor, and other MCP-compatible clients to create and manipulate Elementor page designs programmatically.

<img width="1995" height="1141" alt="image" src="https://github.com/user-attachments/assets/06ab916a-0146-4a5f-828c-d5aa409cb072" />


# Introducing EMCP Pro! 25% Discount for GitHub Community: MSRGIT
## [Grab the Deal](https://emcp.msrbuilds.com/pricing)
<img width="1697" height="250" alt="image" src="https://github.com/user-attachments/assets/f588d365-1986-4132-90d3-4d44a28da464" />


## Features

- **A focused MCP toolset** covering the full Elementor page-building workflow — and, as of v3.0.0, growing beyond Elementor into general WordPress content and site management (content, settings, plugins, and themes). As of v3.0.0 the 62 per-widget tools were folded into a catalog-backed model, so the active surface is much smaller — every widget is still reachable via discover → inspect → act. Counts scale with your environment (the v3.0.0 numbers below add the 8 WordPress Content tools + 3 surfaced core abilities + 2 Settings tools + 13 Plugins & Themes tools, of which 4 read/search tools are enabled by default and 9 mutation tools ship disabled-by-default — estimates pending a fresh live count):
  - ~57 tools — free Elementor only
  - ~71 tools — free Elementor + Elementor 4.0 atomic elements
  - ~83 tools — with Elementor Pro
  - ~97 tools — with Elementor Pro + Elementor 4.0 (and + WooCommerce, which adds no new tools)
  - ~21 of these (SEO & Accessibility, Widget Builder, PHP Snippets) ship **disabled-by-default**, so the typical active surface is smaller
- **WordPress Content (beyond Elementor)** — Create and manage posts, pages, and any custom post type — content, status, taxonomy terms, custom fields, and featured images — via MCP, without touching Elementor data. Built on WP core; every post carries an `is_elementor` flag that steers agents to the Elementor tools for builder pages
- **WordPress Settings (beyond Elementor, domain 2)** — Read and batch-update core WordPress settings (general/reading/writing/discussion/media/permalinks) over MCP. Curated allowlist only — no arbitrary option access; `admin_email` is read-only; permalink changes auto-flush rewrite rules. `manage_options`. (v3.0.0)
- **Plugins & Themes (beyond Elementor, domain 3)** — Discover, install (wordpress.org only), update, activate/deactivate, and delete plugins and themes over MCP. Strong guardrails (EMCP Tools + Elementor protected; per-op capability gating; direct-filesystem-only); the 9 mutation tools ship disabled-by-default. `manage_options`-class capabilities. (v3.0.0)
- **Media Library (beyond Elementor, domain 4)** — Fetch full attachment detail (`get-media`: every registered size, dimensions, metadata, alt/caption/description), edit metadata (`update-media`: alt text, title, caption, description — a one-call accessibility/SEO fix), and delete attachments (`delete-media`: destructive and effectively permanent; disabled-by-default and requires `confirm:true`). URL uploads continue via `sideload-image`. (v3.0.0)
- **Users (beyond Elementor, domain 5)** — List and read WordPress users, and safely create/edit non-admin profiles over MCP. Hard guardrails: no delete-user and no role-change tool; `create-user` assigns only non-admin roles and auto-generates a strong password (emailed to the new user — never returned); `update-user` edits profile fields only and refuses any user with admin-level capabilities (administrators are off-limits). `list-users`/`get-user` are enabled by default; `create-user`/`update-user` are disabled-by-default. (v3.0.0)
- **Query & Discovery** — List widgets, inspect page structures, read element settings, browse templates, view global design tokens
- **Page Management** — Create pages, update settings, clear content, import/export templates
- **Layout Tools** — Add flexbox containers, move/remove/duplicate elements, update containers, find elements, batch update, reorder children, get container schema
- **Widget Tools** — A catalog-backed model: `list-widgets` (filter by tier/category/search) → `get-widget-schema` (curated params, batch, or full raw schema) → `add-free-widget` / `add-pro-widget` (with Pro) → `update-widget`. The 62 widgets' curated params live in a built-in catalog (27 free / 30 Pro / 5 WooCommerce), so every widget and parameter stays reachable while the per-turn tool-list cost drops ~10×
- **Pro Widget Support** — Conditional tools for nav menu, loop grid, loop carousel, media carousel, nested tabs, nested accordion, portfolio, author box, login, code highlight, reviews, off-canvas, progress tracker, search, and more (only register when Elementor Pro is active)
- **Atomic Elements (Elementor 4.0+)** — 13 dedicated tools for Elementor's atomic system: flexbox, div-block, heading, paragraph, button, image, svg, youtube, video, divider, plus universal `add-atomic-widget` / `update-atomic-widget` and `detect-elementor-version`
- **Theme Builder** — Create theme templates (header/footer/single/archive), set display conditions (Pro)
- **Dynamic Tags** — List all available dynamic tags, bind dynamic data to element settings (Pro)
- **Popup Builder** — Create popup templates, configure triggers/conditions/timing (Pro)
- **Template Tools** — Save pages or elements as reusable templates, apply templates to pages
- **Global Settings** — Update site-wide color palettes and typography presets
- **Brand Kits** — One-click coordinated color + typography kits. Apply a curated brand kit and the whole site re-skins (system tokens + Theme Style defaults); back up and restore any time. **10 kits are free to apply**; the full 50-kit library and the `apply-brand-kit` MCP tool are Pro
- **AI Widget Builder (Pro)** — Let an AI agent design custom Elementor widgets from a structured spec — no hand-written PHP. The plugin compiles the spec + an HTML template into a sandboxed `Widget_Base` class (35 control types, optional per-widget CSS/JS) that appears under a "Custom (EMCP)" category in the editor; a runtime safety net keeps a bad widget from breaking the editor
- **Composite Tools** — Build a complete page from a declarative JSON structure in a single call
- **Stock Images** — Search Openverse for Creative Commons images, sideload into Media Library, add to pages
- **SVG Icons** — Upload SVG icons from URL or raw markup for use with Elementor icon widgets
- **Custom Code** — Add custom CSS (element/page level), inject JavaScript, create site-wide code snippets for head/body injection
- **Low-tools Mode** — One-click toggle that filters the active tool list to a curated essentials set, for MCP clients with strict tool caps (Antigravity, Gemini API, etc.). After the v3.0.0 widget consolidation the active count already fits most caps, so this is rarely needed now
- **Admin Dashboard** — Dedicated top-level **EMCP Tools** menu with Tools / Connection / Prompts / Templates / Brand Kits / Skills / Widget Builder / Changelog tabs. Toggle individual tools on/off, view connection configs for all supported MCP clients, and reach the **Get Support** portal from any tab

## Requirements

| Dependency | Version |
|---|---|
| WordPress | >= 6.9 |
| PHP | >= 8.0 |
| Elementor | >= 3.20 (container support required) |
| WordPress MCP Adapter | Bundled (no separate install) |
| WordPress Abilities API | Bundled in WP 6.9+, or via Composer |

## Installation

1. Install and activate [Elementor](https://wordpress.org/plugins/elementor/) (version 3.20+).
2. Install and activate the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin.
3. Download the latest release zip from the [Releases page](https://github.com/msrbuilds/elementor-mcp/releases/).
4. In WordPress, go to **Plugins > Add New > Upload Plugin** and upload the downloaded zip file.
5. Activate the plugin through the **Plugins** menu in WordPress.
6. Open the new **EMCP Tools** top-level menu in the WordPress admin sidebar to configure tools and view connection info.

## Connecting to the MCP Server

Connect to your WordPress site from any AI client using HTTP. No proxy or Node.js needed — just a WordPress Application Password.

### Prerequisites

1. Create an Application Password at **Users > Profile > Application Passwords**.
2. Base64-encode your credentials: `echo -n "username:app-password" | base64`
3. Your MCP endpoint is: `https://your-site.com/wp-json/mcp/emcp-tools-server`

> **Tip:** The plugin's **EMCP Tools > Connection** admin screen can generate all configs automatically — just enter your username and Application Password.

### Claude Code

Add as `.mcp.json` in your project root:

```json
{
    "mcpServers": {
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

### Claude Desktop

Add to `claude_desktop_config.json` (`%APPDATA%\Claude\` on Windows, `~/Library/Application Support/Claude/` on macOS):

```json
{
    "mcpServers": {
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

### Cursor

Add to `.cursor/mcp.json` in your project root, or `~/.cursor/mcp.json` for global config:

```json
{
    "mcpServers": {
        "emcp-tools": {
            "url": "https://your-site.com/wp-json/mcp/emcp-tools-server",
            "headers": {
                "Authorization": "Basic BASE64_ENCODED_CREDENTIALS"
            }
        }
    }
}
```

### Windsurf

Add to `~/.codeium/windsurf/mcp_config.json`:

```json
{
    "mcpServers": {
        "emcp-tools": {
            "serverUrl": "https://your-site.com/wp-json/mcp/emcp-tools-server",
            "headers": {
                "Authorization": "Basic BASE64_ENCODED_CREDENTIALS"
            }
        }
    }
}
```

### Antigravity

Add to `~/.gemini/antigravity/mcp_config.json`:

```json
{
    "mcpServers": {
        "emcp-tools": {
            "serverUrl": "https://your-site.com/wp-json/mcp/emcp-tools-server",
            "headers": {
                "Authorization": "Basic BASE64_ENCODED_CREDENTIALS"
            }
        }
    }
}
```

### WP-CLI stdio (local development)

For local development with WP-CLI available, you can use the stdio transport (no HTTP auth needed):

```json
{
    "mcpServers": {
        "emcp-tools": {
            "type": "stdio",
            "command": "wp",
            "args": [
                "mcp-adapter", "serve",
                "--server=emcp-tools-server",
                "--user=admin",
                "--path=/path/to/wordpress"
            ]
        }
    }
}
```

### Node.js proxy (remote sites or protocol compatibility)

For remote WordPress sites, environments without WP-CLI, or when your AI client needs a different MCP protocol version, use the Node.js proxy. Your AI client launches it as a **local subprocess**, so it must run on the machine with the client — **not** on the WordPress server. On shared hosting you have no local access to the plugin directory, so use one of the two methods below.

**Recommended — `npx` runner (nothing to install or keep in sync):**

```json
{
    "mcpServers": {
        "emcp-tools": {
            "type": "stdio",
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

`npx` fetches the latest proxy on each launch, so there is no local copy to drift from the plugin version. Requires Node.js 18+ on the client machine.

**Alternative — local copy of the proxy file:**

Extract `bin/mcp-proxy.mjs` from the plugin ZIP, save it anywhere on the machine running your AI client, and point `args` at that **local** path (e.g. `["C:\\local\\path\\to\\mcp-proxy.mjs"]`) — not at the copy inside `wp-content/plugins/...` on the server. Re-extract it after plugin updates to pick up proxy fixes.

**Optional environment variables:**

| Variable | Description |
|---|---|
| `MCP_LOG_FILE` | Path to a debug log file (e.g., `/tmp/elementor-mcp.log`) |
| `MCP_PROTOCOL_VERSION` | Override the protocol version in initialize responses (e.g., `2024-11-05`). Use this if your client doesn't support `2025-06-18`. |

### Testing with MCP Inspector

```bash
npx @modelcontextprotocol/inspector wp mcp-adapter serve \
  --server=emcp-tools-server --user=admin --path=/path/to/wordpress
```

## Available Tools

### Query & Discovery (7 tools)

| Tool | Description |
|---|---|
| `list-widgets` | All registered widget types with names, titles, icons, categories, keywords |
| `get-widget-schema` | Full JSON Schema for a widget's settings (auto-generated from Elementor controls) |
| `get-page-structure` | Element tree for a page (containers, widgets, nesting) |
| `get-element-settings` | Current settings for a specific element on a page |
| `list-pages` | All Elementor-enabled pages/posts |
| `list-templates` | Saved Elementor templates from the template library |
| `get-global-settings` | Active kit/global settings (colors, typography, spacing) |

### WordPress Content — beyond Elementor (8 tools, v3.0.0)

General WordPress content management over MCP — built on WordPress core, these tools **never touch Elementor data**. Every returned post carries an `is_elementor` flag so an agent knows to switch to the Elementor tools for builder pages. Enabled by default; capability-gated. Featured image and custom-field meta are parameters of create/update.

| Tool | Description |
|---|---|
| `list-post-types` | List registered public post types (read-only) |
| `list-taxonomies` | List registered taxonomies and their object types (read-only) |
| `create-post` | Create a post/page/CPT — title, content (HTML or block markup), status, slug, author, terms, custom fields, featured image |
| `get-post` | Read a single post by ID (read-only; includes `is_elementor` flag) |
| `update-post` | Update an existing post's fields, terms, custom fields, featured image |
| `list-posts` | List/query posts of any type with filters (read-only) |
| `delete-post` | Delete a post — trashes by default; `force:true` permanently deletes |
| `set-post-terms` | Assign taxonomy terms to a post |

> WordPress core's read-only context abilities (`core/get-site-info`, `core/get-user-info`, `core/get-environment-info`) are also surfaced on the EMCP server.

### WordPress Settings — beyond Elementor, domain 2 (2 tools, v3.0.0)

Core WordPress settings management over MCP — curated allowlist of general/reading/writing/discussion/media/permalink settings. `get-settings` doubles as discovery (returns each setting's type, label, enum options, and writable flag). `update-settings` batch-writes allowlisted keys and reports rejects in `skipped[]` without aborting the batch; permalink changes auto-flush rewrite rules. Both require `manage_options`. Safety: curated allowlist only — no arbitrary option access; `siteurl`/`home` and `users_can_register`/`default_role` are excluded; `admin_email` is read-only.

| Tool | Description |
|---|---|
| `get-settings` | Read allowlisted WordPress settings across all groups; doubles as discovery (read-only, `manage_options`) |
| `update-settings` | Batch-update allowlisted settings; rejects reported in `skipped[]`; permalink changes auto-flush rewrite rules (`manage_options`) |

### WordPress Plugins & Themes — beyond Elementor, domain 3 (13 tools, v3.0.0)

Discover and manage WordPress plugins and themes over MCP — built on WP core upgrader APIs. Installs are wordpress.org-only (by slug; no arbitrary URLs). Guardrails: EMCP Tools and Elementor can never be deactivated or deleted; the active plugin/theme is protected from deletion; install/update/delete require a directly-writable filesystem (clear error instead of an FTP hang). The 4 read/search tools are **enabled by default**; the **9 mutation tools ship disabled-by-default** (admin opts in on the Tools tab).

| Tool | Description |
|---|---|
| `list-plugins` | List installed plugins with active/inactive status, version, update-available flag, and protected marker (read-only, `activate_plugins`) |
| `search-plugins` | Search the wordpress.org plugin directory by keyword — returns slug, name, version, rating, requirements (read-only, `install_plugins`) |
| `install-plugin` | Install a plugin from wordpress.org by slug; optionally activate after install (`install_plugins`) |
| `activate-plugin` | Activate an installed plugin by file path or slug (`activate_plugins`) |
| `deactivate-plugin` | Deactivate a plugin; refuses to deactivate EMCP Tools or Elementor (`activate_plugins`) |
| `update-plugin` | Update an installed plugin to the latest wordpress.org version; reports up-to-date when no update is pending (`update_plugins`) |
| `delete-plugin` | Permanently delete an inactive, unprotected plugin (`delete_plugins`) |
| `list-themes` | List installed themes with active status, version, update-available flag, and whether it is protected (read-only, `switch_themes`) |
| `search-themes` | Search the wordpress.org theme directory by keyword (read-only, `install_themes`) |
| `install-theme` | Install a theme from wordpress.org by slug (`install_themes`) |
| `switch-theme` | Switch the active theme by stylesheet slug (`switch_themes`) |
| `update-theme` | Update an installed theme to the latest wordpress.org version (`update_themes`) |
| `delete-theme` | Permanently delete an inactive, unprotected theme (`delete_themes`) |

### WordPress Media Library — beyond Elementor, domain 4 (3 tools, v3.0.0)

Manage existing Media Library attachments over MCP — built on WP core attachment functions. `get-media` and `update-media` are **enabled by default**; `delete-media` ships **disabled-by-default** and additionally requires an explicit `confirm:true` (destructive and effectively permanent — WordPress bypasses Trash for media unless `MEDIA_TRASH` is defined). URL uploads continue to use the existing `sideload-image`.

| Tool | Description |
|---|---|
| `get-media` | Full detail for one attachment — every registered image size (URL + dimensions), mime type, filesize, alt text, caption, description, and raw attachment metadata (read-only, `edit_posts`) |
| `update-media` | Edit an attachment's title, alt text, caption, and/or description — only the fields you pass change (`edit_post` on ID) |
| `delete-media` | Delete an attachment; **destructive and effectively permanent**; disabled-by-default; requires `confirm:true`; pass `force:true` to skip Trash even when `MEDIA_TRASH` is defined (`delete_post` on ID) |

### WordPress Users — beyond Elementor, domain 5 (4 tools, v3.0.0)

Safe user management over MCP — built on WP core user functions (`WP_User_Query`, `wp_insert_user`, `wp_update_user`). Guardrails: `create-user` only assigns non-admin roles and auto-generates a strong password (emailed to the new user via `wp_send_new_user_notifications` — the password is **never returned**); `update-user` edits profile fields only (no role or password changes) and refuses any user whose capabilities include `manage_options`, `promote_users`, `edit_users`, `delete_users`, or `manage_network`. There is deliberately no delete-user and no role-change tool. `list-users`/`get-user` are **enabled by default** (`list_users` cap); `create-user`/`update-user` ship **disabled-by-default** (admin opts in on the Tools tab).

| Tool | Description |
|---|---|
| `list-users` | List WordPress users; filter by role or search text; paginated. Returns id, username, display name, email, roles, registration date, and post count. Never returns passwords or auth data (read-only, `list_users`) |
| `get-user` | Full profile detail for one user — adds first/last name, nickname, URL, description, and an `is_admin` flag (true users are off-limits to `update-user`) (read-only, `list_users`) |
| `create-user` | Create a new non-admin WordPress user. A strong password is auto-generated and emailed; the password is never returned. Role defaults to `subscriber`; administrator and any admin-grade role are refused (`create_users`) |
| `update-user` | Update a non-admin user's profile (email, first/last name, display name, nickname, URL, description). Cannot change roles or passwords; refuses any user with admin-level capabilities (`edit_users`) |

### Page Management (5 tools)

| Tool | Description |
|---|---|
| `create-page` | Create a new WP page/post with Elementor enabled |
| `update-page-settings` | Update page-level Elementor settings (background, padding, etc.) |
| `delete-page-content` | Clear all Elementor content from a page |
| `import-template` | Import JSON template structure into a page |
| `export-page` | Export page's full Elementor data as JSON |

### Layout & Structure (10 tools)

| Tool | Description |
|---|---|
| `add-container` | Add a flexbox container (top-level or nested) |
| `update-container` | Update settings on an existing container |
| `move-element` | Move an element to a new parent/position |
| `remove-element` | Remove an element and all children |
| `duplicate-element` | Duplicate element with fresh IDs |
| `get-container-schema` | Returns the JSON schema for container settings |
| `find-element` | Find elements by type, settings, or CSS class within a page |
| `update-element` | Update settings on any element (widget or container) by ID |
| `batch-update` | Apply multiple element updates in a single call |
| `reorder-elements` | Reorder child elements within a container |

### Widgets — catalog-backed (5 tools)

As of v3.0.0 the 62 per-widget tools (`add-heading`, `add-button`, `add-form`, the `add-wc-*` set, …) and the universal `add-widget` were replaced by a catalog-backed model. The 62 widgets' curated params now live in a built-in catalog (27 free / 30 Pro / 5 WooCommerce) instead of as individual tools — every widget and parameter is still reachable through **discover → inspect → act**, and any valid Elementor control passes straight through.

| Tool | Description |
|---|---|
| `list-widgets` | Compact catalog index of widgets; filter by `tier` / `category` / `search` |
| `get-widget-schema` | Curated params for a widget (or `types[]` batch); `full:true` for the raw control schema |
| `add-free-widget` | Add any free/core widget by type |
| `add-pro-widget` | Add an Elementor Pro / WooCommerce widget by type (only when Elementor Pro is active) |
| `update-widget` | Update settings on an existing widget |

### Atomic Elements — Elementor 4.0+ (13 tools)

These tools only register when Elementor >= 4.0 is detected. Legacy widget tools continue to work alongside them.

| Tool | Description |
|---|---|
| `detect-elementor-version` | Returns Elementor version and whether atomic elements are supported. Call first to choose tool family. |
| `add-flexbox` | Atomic flexbox container (`e-flexbox`). Params: direction, justify, align, gap, wrap, tag, padding, background_color |
| `add-div-block` | Atomic div-block container (`e-div-block`). Params: tag, padding, background_color |
| `add-atomic-widget` | Universal: add any atomic widget by type with raw `$$type` settings |
| `update-atomic-widget` | Universal: partial-merge update on an existing atomic widget |
| `add-atomic-heading` | Atomic heading (`e-heading`). Params: title, tag (h1-h6), link, css_id |
| `add-atomic-paragraph` | Atomic paragraph (`e-paragraph`). Params: content, link, css_id |
| `add-atomic-button` | Atomic button (`e-button`). Params: text, link, target_blank, css_id |
| `add-atomic-image` | Atomic image (`e-image`). Params: image_id, image_url, alt, link, css_id |
| `add-atomic-svg` | Atomic SVG (`e-svg`). Params: svg_id, svg_url, css_id |
| `add-atomic-youtube` | Atomic YouTube embed (`e-youtube`). Params: video_url, css_id |
| `add-atomic-video` | Atomic self-hosted video (`e-self-hosted-video`). Params: video_url, video_id, css_id |
| `add-atomic-divider` | Atomic divider (`e-divider`). Params: css_id |

### Templates & Theme Builder (8 tools)

| Tool | Description |
|---|---|
| `save-as-template` | Save a page or element as reusable template |
| `apply-template` | Apply a saved template to a page |
| `create-theme-template` | Pro: Create theme builder template (header/footer/single/archive/error-404/loop-item) |
| `set-template-conditions` | Pro: Set display conditions on a theme builder template |
| `list-dynamic-tags` | Pro: List all available dynamic tags with groups and categories |
| `set-dynamic-tag` | Pro: Bind a dynamic tag to a specific element setting |
| `create-popup` | Pro: Create a popup template |
| `set-popup-settings` | Pro: Set triggers, display conditions, and timing on a popup |

### Global Settings (2 tools)

| Tool | Description |
|---|---|
| `update-global-colors` | Update site-wide color palette in Elementor kit |
| `update-global-typography` | Update site-wide typography in Elementor kit |

### Composite (1 tool)

| Tool | Description |
|---|---|
| `build-page` | Create complete page from declarative structure in one call |

### Stock Images (3 tools)

| Tool | Description |
|---|---|
| `search-images` | Search Openverse for Creative Commons images by keyword |
| `sideload-image` | Download an external image URL into the WordPress Media Library |
| `add-stock-image` | Search + sideload + add image widget to page in one call |

### SVG Icons (1 tool)

| Tool | Description |
|---|---|
| `upload-svg-icon` | Upload an SVG icon (from URL or raw markup) for use with icon/icon-box widgets |

### Custom Code (4 tools)

| Tool | Description |
|---|---|
| `add-custom-css` | Add custom CSS to an element or page-level with `selector` keyword support (Pro) |
| `add-custom-js` | Inject JavaScript via HTML widget with automatic `<script>` wrapping |
| `add-code-snippet` | Create site-wide Custom Code snippets for head/body injection (Pro) |
| `list-code-snippets` | List all Custom Code snippets with location and status filters (Pro) |

### Widget Builder — Pro (8 tools, off by default)

Design and register custom Elementor widgets from a structured spec — no hand-written PHP. The generator compiles the spec + an HTML template (`{{control}}`, `{{#if}}`, `{{#each}}`) into a sandboxed `Widget_Base` class, escaping every value by its control type.

| Tool | Description |
|---|---|
| `list-control-types` | List the supported control types + spec shape so agents build valid specs |
| `validate-widget-spec` | Schema + generator dry-run; returns errors without persisting |
| `create-custom-widget` | Generate + register a widget from a spec (auto-activates) |
| `update-custom-widget` | Replace a widget's spec, regenerate, re-validate |
| `get-custom-widget` | Return a widget's spec + generated PHP + status |
| `list-custom-widgets` | List generated widgets (id, title, name, status) |
| `set-widget-status` | Activate or deactivate a widget |
| `delete-custom-widget` | Delete a widget (CPT + sandbox files) |

### PHP Snippets — Sandbox (6 tools, free, off by default)

Let an AI agent author server-side PHP behind a hard human-approval gate. The AI can validate code and create **drafts**, but a draft never runs until an admin activates it in EMCP Tools → Sandbox — there is deliberately no "activate" tool. Every snippet is statically parse-checked and security-scanned (blocks `exec`/`eval`/backticks/file-writes/network/destructive SQL/obfuscation) before it can be saved or activated.

| Tool | Description |
|---|---|
| `validate-php-snippet` | Static parse + security scan; no store, no run |
| `create-php-snippet` | Create an inactive draft (critical findings rejected) |
| `update-php-snippet` | Update a snippet's code/settings; re-validates |
| `get-php-snippet` | Return code, status, shortcode + validation report |
| `list-php-snippets` | List snippets with status and run context |
| `delete-php-snippet` | Delete a snippet and its sandbox file |

### Global Classes — Elementor 4.0+ (1 tool)

| Tool | Description |
|---|---|
| `list-global-classes` | Resolve Class Manager `g-` IDs to their names and the CSS each defines, per breakpoint/state (read-only) |

> All tool names are prefixed with `emcp-tools/` in the MCP namespace (e.g., `emcp-tools/list-widgets`). The MCP Adapter converts these to `emcp-tools-list-widgets` for transport.

## Permission Model

| Tool Group | Required WordPress Capability |
|---|---|
| Read/Query | `edit_posts` |
| Page creation | `publish_pages` or `edit_pages` |
| Widget/layout manipulation | `edit_posts` + ownership check |
| Template management | `edit_posts` |
| Theme builder / Popups | `edit_posts` |
| Dynamic tags | `edit_posts` + ownership check |
| Global settings | `manage_options` |
| Delete operations | `delete_posts` + ownership check |
| Stock image search | `edit_posts` |
| Stock image sideload | `upload_files` |
| Custom CSS/JS | `edit_posts` + ownership check |
| Code snippets | `manage_options` + `unfiltered_html` |

## Troubleshooting

### Tools not appearing in your AI client

If the MCP server connects but no tools appear in Claude Code, Cursor, or other clients:

1. **Verify tools are registered.** Test the endpoint directly with curl to confirm the server returns tools:
   ```bash
   curl -s -u admin:YOUR_APP_PASSWORD \
     https://your-site.com/wp-json/mcp/emcp-tools-server \
     -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}'
   ```
   If this returns a valid JSON-RPC response with `serverInfo`, the server is working. The issue is likely a protocol version mismatch between the server and your client.

2. **Check for protocol version mismatch.** The WordPress MCP Adapter reports protocol version `2025-06-18`. Some clients only support `2024-11-05`. If using the Node.js proxy, set the `MCP_PROTOCOL_VERSION` environment variable to override:
   ```json
   "env": {
       "MCP_PROTOCOL_VERSION": "2024-11-05"
   }
   ```

3. **Enable debug logging.** Add the `MCP_LOG_FILE` environment variable to your proxy config to capture the full request/response flow:
   ```json
   "env": {
       "MCP_LOG_FILE": "/tmp/emcp-tools-debug.log"
   }
   ```
   The log will show the protocol version, session IDs, tools count, and response bodies.

4. **Use the proxy instead of direct HTTP.** If you're using `type: "http"` to connect directly, your client must handle `Mcp-Session-Id` headers. Clients that don't support session management should use the Node.js proxy instead, which handles sessions automatically.

### Common errors

- **"No MCP servers registered"** — Ensure the MCP Tools for Elementor plugin is active and all dependencies (Elementor, MCP Adapter, Abilities API) are met.
- **HTTP 401** — Check your Application Password is correct and the user has `edit_posts` capability.
- **"Missing Mcp-Session-Id header"** — The HTTP endpoint requires an `Mcp-Session-Id` header on all requests after `initialize`. Use the Node.js proxy (which handles this automatically) instead of direct HTTP connections.
- **Session errors** — If connecting via direct HTTP, your client must capture the `Mcp-Session-Id` response header from `initialize` and include it on all subsequent requests.
- **WP-CLI not found on Windows** — Use the full path to `php.exe` and `wp-cli.phar`.

## Sample Prompts

The [`prompts/`](prompts/) directory includes ready-to-use landing page prompts that demonstrate the full power of MCP Tools for Elementor tools. Each prompt is a complete blueprint — paste it into your AI client and watch an entire page get built automatically.

| Prompt | Industry | Description |
|---|---|---|
| [Local Business](prompts/LOCAL_BUSINESS.md) | General | Multi-purpose small business landing page with hero, services, testimonials, and contact |
| [Dental Clinic](prompts/DENTAL_CLINIC.md) | Health & Wellness | Professional dental practice with services, team, insurance info, and appointment booking |
| [Web Developer Portfolio](prompts/WEB_DEVELOPER_PORTFOLIO.md) | Professional Services | Developer portfolio with project showcase, tech stack, and contact form |
| [Hair Salon](prompts/HAIR_SALON.md) | Lifestyle | Stylish salon page with services menu, stylist profiles, and booking |
| [Car Wash](prompts/CAR_WASH.md) | Lifestyle | Car wash with wash packages, add-on services, and membership plans |

Each prompt includes:
- Complete design system (color palette, typography, spacing)
- Image search keywords for stock photo sourcing
- SVG icon specifications
- Full page structure (hero, sections, footer)
- Entrance animations using Elementor's built-in Motion Effects
- Custom CSS for hover states
- Custom JavaScript for scroll animations and counters
- Step-by-step execution order

> **Want more?** A premium collection of **50 industry-specific prompts** covering restaurants, med spas, law firms, florists, photography studios, and more is available separately.

## Contributing

We welcome contributions from the community! Whether it's bug reports, feature requests, documentation improvements, or code contributions — every bit helps.

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines on how to get started.

**Quick start:**

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-tool`)
3. Make your changes and test locally
4. Submit a Pull Request

## Contributors

- **[@msrbuilds](https://github.com/msrbuilds)** — Original author and maintainer
- **[@mhamzashafiq](https://github.com/mhamzashafiq)** — Layout & element tools, widget convenience shortcuts, theme builder, dynamic tags, popup builder, WooCommerce widget tools, motion effects support, settings validator improvements

## License

This project is licensed under the [GNU General Public License v3.0](LICENSE).
