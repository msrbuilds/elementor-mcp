<h1 align="center">
  <img src="assets/img/icon-sm.png" width="72" alt="EMCP Tools logo"><br>
  MCP Tools for WordPress & Page Builders
</h1>

<div align="center">

[![Version](https://img.shields.io/github/v/release/msrbuilds/elementor-mcp?label=version&color=blue)](https://github.com/msrbuilds/elementor-mcp/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-8892BF.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.9-21759B.svg)](https://wordpress.org)
[![Elementor](https://img.shields.io/badge/Elementor-%3E%3D3.20-92003B.svg)](https://elementor.com)
[![MCP Tools](https://img.shields.io/badge/MCP_Tools-up%20to%20150-orange.svg)](#available-tools)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)
[![GitHub Issues](https://img.shields.io/github/issues/msrbuilds/elementor-mcp)](https://github.com/msrbuilds/elementor-mcp/issues)
[![GitHub Stars](https://img.shields.io/github/stars/msrbuilds/elementor-mcp?style=social)](https://github.com/msrbuilds/elementor-mcp)

</div>

A WordPress plugin that extends the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) to expose Elementor data, widgets, and page design tools as [MCP (Model Context Protocol)](https://modelcontextprotocol.io/) tools. This enables AI agents like Claude, Cursor, and other MCP-compatible clients to create and manipulate Elementor page designs programmatically.

<img width="1995" height="1141" alt="image" src="https://github.com/user-attachments/assets/06ab916a-0146-4a5f-828c-d5aa409cb072" />


# Introducing EMCP Pro! 25% Discount for GitHub Community: MSRGIT
## [Grab the Deal](https://emcptools.com/pricing)
<img width="1697" height="250" alt="image" src="https://github.com/user-attachments/assets/f588d365-1986-4132-90d3-4d44a28da464" />


## Features

- **New in v3.3.0** — five "intelligence, not just transport" features that let an AI agent *understand*, *undo*, and *reuse* your site, plus a big community contribution:
  - **Page Snapshot** (`get-page-snapshot`) — one normalized digest of a page (structure, tokens-in-use, responsive overrides, content outline, SEO-lite, + opt-in performance/a11y/SEO audits) so the AI reasons about a page in a single call
  - **Change History & Rollback** (`list-changes` / `get-change` / `rollback-change`) — every AI-made Elementor/filesystem/database change is recorded to one ledger and can be undone, from MCP **and** a new **History** admin tab
  - **Content Search** (`search-content` / `reindex-search`) — find the site's own pages, templates, widgets, and global styles by natural-language query so the AI reuses existing work instead of rebuilding it
  - **Content Mirror** (`export-content` / `restore-content` / `list-content-exports`) — export page/template content to git-trackable JSON files for external version control
  - **Navigation Menus** (`menu-read` / `menu-write` + `[emcp_menu]` shortcode) — full WordPress nav-menu management over MCP (community-contributed)
  - **Multi-site proxy** (`@msrbuilds/emcp-proxy` 1.9.0) — drive many WordPress installs from one connection with a site registry and `emcp_use_site`
- **A focused MCP toolset** covering the full Elementor page-building workflow — and, since v3.0.0, growing beyond Elementor into general WordPress management across many domains: content, nav menus, settings, plugins & themes, media library, users, performance, filesystem, and database. The 62 per-widget tools are folded into a catalog-backed model, so the active surface stays small — every widget is still reachable via discover → inspect → act. Counts scale with your environment (registered counts; v3.3.0 adds 11 always-on tools over the live-verified 4.1.4 baseline):
  - **128 tools** — free Elementor only (104 active by default)
  - **142 tools** — free Elementor + Elementor 4.0 atomic elements (118 active)
  - **138 tools** — with Elementor Pro (99 active)
  - **152 tools** — with Elementor Pro + Elementor 4.0 (113 active; + WooCommerce adds no new tools)
  - **+2 ACF tools** when Advanced Custom Fields (free or PRO) is active — `acf-read` and `acf-write`, two dispatchers exposing **15 ACF operations** (8 read, 7 write; the Custom-Post-Type/taxonomy operations need ACF 6.1+). Each dispatcher is a single toggle under Tools → Plugins → ACF (listing the operations it covers); `acf-read` is on by default, `acf-write` ships off (v3.2.1)
  - **39 of these ship disabled-by-default** (SEO & Accessibility, Widget Builder, PHP Snippets, the 9 Plugins & Themes write tools, `delete-media`, the 2 Users write tools, the 3 Filesystem write tools, and the 3 Database write tools) — plus the 7 ACF write operations are off by default within `acf-write` — so the typical active surface is smaller until you opt in on the Tools tab
- **WordPress Content (beyond Elementor)** — Create and manage posts, pages, and any custom post type — content, status, taxonomy terms, custom fields, and featured images — via MCP, without touching Elementor data. Built on WP core; every post carries an `is_elementor` flag that steers agents to the Elementor tools for builder pages
- **WordPress Settings (beyond Elementor, domain 2)** — Read and batch-update core WordPress settings (general/reading/writing/discussion/media/permalinks) over MCP. Curated allowlist only — no arbitrary option access; `admin_email` is read-only; permalink changes auto-flush rewrite rules. `manage_options`. (v3.0.0)
- **Plugins & Themes (beyond Elementor, domain 3)** — Discover, install (wordpress.org only), update, activate/deactivate, and delete plugins and themes over MCP. Strong guardrails (EMCP Tools + Elementor protected; per-op capability gating; direct-filesystem-only); the 9 mutation tools ship disabled-by-default. `manage_options`-class capabilities. (v3.0.0)
- **Media Library (beyond Elementor, domain 4)** — Fetch full attachment detail (`get-media`: every registered size, dimensions, metadata, alt/caption/description), edit metadata (`update-media`: alt text, title, caption, description — a one-call accessibility/SEO fix), and delete attachments (`delete-media`: destructive and effectively permanent; disabled-by-default and requires `confirm:true`). URL uploads continue via `sideload-image`. (v3.0.0)
- **Users (beyond Elementor, domain 5)** — List and read WordPress users, and safely create/edit non-admin profiles over MCP. Hard guardrails: no delete-user and no role-change tool; `create-user` assigns only non-admin roles and auto-generates a strong password (emailed to the new user — never returned); `update-user` edits profile fields only and refuses any user with admin-level capabilities (administrators are off-limits). `list-users`/`get-user` are enabled by default; `create-user`/`update-user` are disabled-by-default. (v3.0.0)
- **ACF / ACF PRO (beyond Elementor)** — Read and write Advanced Custom Fields values on posts and options pages, discover and author field groups, and register ACF-managed Custom Post Types and taxonomies (ACF 6.1+) — enough to stand up a full content structure end-to-end (CPT → taxonomy → field group → posts with values). Full Pro field support: repeaters, flexible content (layout-validated), galleries, groups, and clones round-trip as nested JSON. Only registers when ACF is active; writes resolve field names to keys, ship disabled-by-default, and there is deliberately no delete and no slug/field rename (renames orphan stored content). (v3.2.1)
- **Performance & Security (beyond Elementor, domain 6)** — Two read-only diagnostics. **`analyze-performance`** audits server config, WordPress internals (database size, autoloaded options, post revisions, cron backlog, persistent object cache, OPcache, plugin count), and a target page (defaults to the frontpage; optional `url`/`post_id`) for performance bottlenecks. **`scan-security`** — the security counterpart — scans across malware heuristics (eval/base64 obfuscation, request-driven execution, command execution, webshells, encoded blobs, executable PHP under uploads), WordPress core-file integrity (against the official wordpress.org checksums), hardening (file editing, debug output, `admin` username, XML-RPC, version disclosure, HTTPS, security headers), and outdated/abandoned software. Both return a scored report (0-100 + A–F grade) with severity-tagged findings and ranked recommendations; the malware walk is bounded and never returns full file contents (path:line + snippet only). Read-only, self-contained (no external API beyond optional, graceful wordpress.org calls), enabled by default. (v3.0.0)
- **Filesystem (beyond Elementor)** — Read and scan any file inside the WordPress install (`read-file`, `list-directory`, `search-files` — enabled by default). Modify and delete files (`write-file`, `edit-file`, `delete-file`) are disabled-by-default: every path is confined to ABSPATH (no traversal/symlink escape), writes auto-back up the original, `wp-config.php`/`.htaccess` are refused, the `edit_files` capability is enforced (honoring `DISALLOW_FILE_EDIT`), and all writes are audit-logged. `delete-file` requires `confirm:true`. `manage_options`. (v3.0.0)
- **Database (beyond Elementor)** — Inspect the database with flexible read-only SQL (`list-tables`, `describe-table`, `query` — SELECT/SHOW/DESCRIBE/EXPLAIN only; writes, DDL, stacked statements, MySQL `/*!` executable comments, and file-access SQL are rejected; results capped; enabled by default). Structured parameterized writes (`insert-row`, `update-rows`, `delete-rows`) are disabled-by-default: uses `$wpdb` throughout (no raw write-SQL/DDL), forces a non-empty WHERE on update/delete, refuses `wp_users`/`wp_usermeta`, captures a before-image snapshot to an audit log, and `delete-rows` requires `confirm:true`. `manage_options`. (v3.0.0)
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
- **Stock Images** — Search Unsplash, Pexels & Pixabay for stock photos, sideload into Media Library, add to pages
- **SVG Icons** — Upload SVG icons from URL or raw markup for use with Elementor icon widgets
- **Custom Code** — Add custom CSS (element/page level), inject JavaScript, create site-wide code snippets for head/body injection
- **Low-tools Mode** — One-click toggle that filters the active tool list to a curated essentials set, for MCP clients with strict tool caps (Antigravity, Gemini API, etc.). After the v3.0.0 widget consolidation the active count already fits most caps, so this is rarely needed now
- **Admin Dashboard** — Dedicated top-level **EMCP Tools** menu with Dashboard / Modules / Tools / Connection / Prompts / Templates / Brand Kits / Skills / Sandbox / **History** / Changelog tabs. Toggle individual tools on/off, view connection configs for all supported MCP clients, review and roll back AI-made changes on the History tab, and reach the **Get Support** portal from any tab

## Requirements

| Dependency | Version |
|---|---|
| WordPress | >= 6.9 |
| PHP | >= 8.1 |
| Elementor | >= 3.20 (container support required) |
| WordPress MCP Adapter | Bundled (no separate install) |
| WordPress Abilities API | Bundled in WP 6.9+, or via Composer |

## Installation

1. Install and activate [Elementor](https://wordpress.org/plugins/elementor/) (version 3.20+).
2. Download the latest release zip from the [Releases page](https://github.com/msrbuilds/elementor-mcp/releases/).
3. In WordPress, go to **Plugins > Add New > Upload Plugin** and upload the downloaded zip file.
4. Activate the plugin through the **Plugins** menu in WordPress. The WordPress MCP Adapter is **bundled** — no separate install needed (WordPress 6.9+/7.0 already includes the Abilities API).
5. Open the new **EMCP Tools** top-level menu in the WordPress admin sidebar to configure tools and view connection info.

## Connecting to the MCP Server

The MCP server lives at `https://your-site.com/wp-json/mcp/emcp-tools-server` (WP-CLI: `--server=emcp-tools-server`). You can connect any MCP client to it with a WordPress Application Password.

### Recommended: the in-admin Connection manager

The fastest path is the built-in **EMCP Tools → Connection** screen. It walks you through it: toggle the server on, generate an Application Password in one click, then **pick your AI client** — and only the options that fit that client appear, tailored and ready to copy:

- **Claude Desktop** — a one-click **`.mcpb` bundle**. Download it, double-click to install, done — no config files to edit. The bundle is fully self-contained: it ships a single `server/index.js` (the proxy compiled to CommonJS with your site URL + a fresh Application Password embedded) that runs on Claude Desktop's **built-in Node.js** — no `npx`, no separate Node install, no PATH setup. (The `.mcpb` contains a live credential — delete it after importing.)
- **Claude Code / Cursor / VS Code / Antigravity** — ready-to-paste JSON (HTTP) or a terminal command, generated with your endpoint and credentials filled in.
- **Any client** — a copy-paste **AI setup prompt** that tells the agent how to wire itself up, or the raw manual JSON.

The same screen includes a one-click **authentication test** (confirms a client can actually connect and flags a host that strips the `Authorization` header) and a **Context** page where you write site-wide guidance delivered to every connecting agent as the server's MCP `instructions`.

### Manual configuration

Prefer to wire it up by hand? Create an Application Password at **Users → Profile → Application Passwords**, base64-encode `username:app-password` (`echo -n "username:app-password" | base64`), and point your client at `https://your-site.com/wp-json/mcp/emcp-tools-server`.

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
| `MCP_LOG_FILE` | Path to a debug log file (e.g., `/tmp/emcp-tools.log`) |
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

### ACF (Advanced Custom Fields) — beyond Elementor (2 dispatcher tools / 15 operations, v3.2.1)

Read and write ACF field values, discover and author field groups, and register ACF-managed Custom Post Types and taxonomies over MCP — built on the public ACF API (`acf_get_field_groups`, `get_field`, `update_field`, `acf_import_field_group`, `acf_import_post_type`, `acf_import_taxonomy`). To keep the MCP tool-list small, the domain is exposed as **2 dispatcher tools** — `acf-read` and `acf-write` — each covering the operations of its kind. Call either with no `operation` to list its operations (the discovery catalog), then again with `{ operation, arguments }`. The dispatchers **only register when ACF (free or PRO) is active**; Pro-only capabilities (options pages; repeater / flexible content / gallery / clone fields) degrade gracefully on free ACF, and the CPT/taxonomy operations are available only on **ACF 6.1+**. Writes always resolve field names to field keys first (writing by name silently fails on posts with no stored value), and complex Pro fields round-trip as nested JSON arrays. Deliberately conservative authoring: no delete operations, fields can never be removed, and a field's `name`/`key`/`type` — or a post type / taxonomy slug — can never change (renames orphan stored content). Code-registered field groups (acf-json / PHP) are listed with a `local` flag and refused by the group editor; the CPT/taxonomy read operations list only the ACF-managed ones. Each dispatcher is a single toggle under **Tools → Plugins → ACF** (listing the operations it covers): `acf-read` is **enabled by default**, `acf-write` ships **disabled-by-default**. Every operation keeps its original capability check, enforced per call by the dispatcher.

**Field values, field groups & options pages:**

| Tool | Description |
|---|---|
| `list-acf-field-groups` | List field groups: key, title, active state, field count, `local` flag; filter by search text, active state, or the post they apply to (read-only, `edit_posts`) |
| `get-acf-field-group` | One group's location rules and recursive field tree — names, keys, types, required, choices, `sub_fields`, flexible `layouts`. The schema-discovery step before writing values (read-only, `edit_posts`) |
| `list-acf-options-pages` | Registered ACF options pages with the `post_id` string to target them (ACF PRO; empty list with `pro:false` on free ACF) (read-only, `edit_posts`) |
| `get-acf-fields` | Read field values from a post (`post_id`) or options page (`options_page`) — formatted values, repeaters/flexible as nested arrays, images/posts as compact objects, unsaved fields as `null`/empty; optional `fields[]` filter (read-only, `edit_post` / `manage_options` for options) |
| `update-acf-fields` | Write field values on a post or options page: repeater rows, flexible content rows (validated `acf_fc_layout`), galleries (attachment IDs), relationships. Unknown fields are skipped with a reason; results are confirmed by re-reading (`edit_post` / `manage_options`; disabled-by-default) |
| `create-acf-field-group` | Create a field group with fields (including nested sub_fields/layouts) and location rules, persisted to the database (`manage_options`; disabled-by-default) |
| `update-acf-field-group` | Edit a database-stored group: settings, append new fields, or adjust existing fields' settings by key — no deletes, no renames, local groups refused (`manage_options`; disabled-by-default) |

**Custom Post Types & Taxonomies (ACF 6.1+):**

| Tool | Description |
|---|---|
| `list-acf-post-types` | List the Custom Post Types managed by ACF — slug, title, visibility, hierarchy, supports, attached taxonomies; `search`/`active_only` filters (read-only, `manage_options`) |
| `get-acf-post-type` | One ACF-managed CPT's full definition (labels, supports, REST, taxonomies) by key or ID (read-only, `manage_options`) |
| `create-acf-post-type` | Register a new CPT through ACF (saved as data, then registered by ACF — no code executed); slug validated (≤ 20 chars, not reserved/existing); labels auto-generated (`manage_options`; disabled-by-default) |
| `update-acf-post-type` | Edit an ACF-managed CPT (labels, visibility, supports, taxonomies); slug is immutable; native CPTs refused (`manage_options`; disabled-by-default) |
| `list-acf-taxonomies` | List the taxonomies managed by ACF — slug, title, hierarchy, and the post types each is attached to (`object_type`); `search`/`active_only` filters (read-only, `manage_options`) |
| `get-acf-taxonomy` | One ACF-managed taxonomy's full definition (labels, hierarchy, REST, `object_type`) by key or ID (read-only, `manage_options`) |
| `create-acf-taxonomy` | Register a new taxonomy through ACF; requires `object_type` (post type slugs); slug validated (≤ 32 chars); labels auto-generated (`manage_options`; disabled-by-default) |
| `update-acf-taxonomy` | Edit an ACF-managed taxonomy (labels, hierarchy, attached post types); slug is immutable (`manage_options`; disabled-by-default) |

### WordPress Performance & Security — beyond Elementor, domain 6 (2 tools, v3.0.0)

Two read-only diagnostics over MCP, both self-contained (the only off-site calls are to wordpress.org — optional and graceful offline). Both return a scored report (0-100 + A–F grade) with severity-tagged findings and ranked recommendations. `manage_options`; enabled by default.

| Tool | Description |
|---|---|
| `analyze-performance` | Audit server config, WordPress internals (DB size, autoloaded options, revisions, cron backlog, object cache, OPcache, plugin count), and a target page (default frontpage; optional `url`/`post_id`) for bottlenecks. Returns a scored report grouped into `server`/`database`/`config`/`page`/`assets` with ranked `top_recommendations`. Same-host-enforced loopback fetch (rejects off-host URLs/redirects); degrades gracefully when the page fetch is blocked (read-only, `manage_options`) |
| `scan-security` | Scan across malware heuristics (eval/base64 obfuscation, request-driven execution, command execution, webshells, encoded blobs, executable PHP under uploads), WordPress core-file integrity (against the official wordpress.org checksums), hardening (file editing, debug output, `admin` username, XML-RPC, version disclosure, HTTPS, security headers), and outdated/abandoned software. The malware walk is confined to ABSPATH and bounded by file-count + time caps (`deep`/`max_files`/`max_seconds`; partial results flagged) and never returns full file contents (path:line + snippet only); optional `checks` selects a subset. (read-only, `manage_options`) |

### Filesystem — beyond Elementor (6 tools, v3.0.0)

Read and scan any file inside the WordPress installation — core, plugins, themes, uploads — confined to ABSPATH (no traversal or symlink escape). The 3 read tools are **enabled by default**; the **3 write/delete tools ship disabled-by-default** and require the `edit_files` capability (honoring `DISALLOW_FILE_EDIT`). Writes auto-back up the original file; `wp-config.php` and `.htaccess` are refused by all write/delete tools; every mutation is audit-logged. `manage_options`.

| Tool | Description |
|---|---|
| `read-file` | Return the contents of any file inside ABSPATH (read-only, `manage_options`) |
| `list-directory` | List the entries in a directory inside ABSPATH — names, types, sizes, modified times (read-only, `manage_options`) |
| `search-files` | Search for files matching a pattern or containing a string within ABSPATH (read-only, `manage_options`) |
| `write-file` | Write (create or overwrite) a file inside ABSPATH; auto-backs up the original; refuses `wp-config.php`/`.htaccess`; audit-logged (`edit_files` + `manage_options`; disabled-by-default) |
| `edit-file` | Apply a targeted find-and-replace or line-range edit to a file inside ABSPATH; auto-backs up the original; refuses `wp-config.php`/`.htaccess`; audit-logged (`edit_files` + `manage_options`; disabled-by-default) |
| `delete-file` | Delete a file inside ABSPATH; requires `confirm:true`; refuses `wp-config.php`/`.htaccess`; audit-logged (`edit_files` + `manage_options`; disabled-by-default) |

### Database — beyond Elementor (6 tools, v3.0.0)

Inspect and manage the WordPress database over MCP. The 3 read tools are **enabled by default**; the **3 write tools ship disabled-by-default** and use `$wpdb` (no raw write-SQL, so no DDL). Writes force a non-empty WHERE clause on update/delete, refuse `wp_users`/`wp_usermeta`, snapshot a before-image into an audit log, and `delete-rows` requires an explicit `confirm:true`. `manage_options`.

> **Direct DB access.** The read path (`query`) is bounded by a read-only-SQL validator that rejects writes/DDL/stacked statements, MySQL `/*!` executable comments, and file-access SQL. Writes are structured/parameterized via `$wpdb` (no raw write-SQL/DDL), disabled-by-default, admin-gated, refuse `wp_users`/`wp_usermeta`, force a WHERE clause, snapshot a before-image, and confirm on delete. Arbitrary DB access is contrary to WordPress.org plugin guidelines — included per an explicit project decision.

| Tool | Description |
|---|---|
| `list-tables` | List all tables in the WordPress database with row counts and sizes (read-only, `manage_options`) |
| `describe-table` | Return the column definitions and indexes for a table (read-only, `manage_options`) |
| `query` | Run a read-only SQL query (SELECT/SHOW/DESCRIBE/EXPLAIN only); results capped (read-only, `manage_options`) |
| `insert-row` | Insert a row into a table using `$wpdb`; refuses `wp_users`/`wp_usermeta`; audit-logged (`manage_options`; disabled-by-default) |
| `update-rows` | Update rows matching a WHERE clause; forced non-empty WHERE; before-image snapshot; audit-logged (`manage_options`; disabled-by-default) |
| `delete-rows` | Delete rows matching a WHERE clause; requires `confirm:true`; forced non-empty WHERE; before-image snapshot; audit-logged (`manage_options`; disabled-by-default) |

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
| `search-images` | Search Unsplash, Pexels or Pixabay for stock photos by keyword |
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

## License

This project is licensed under the [GNU General Public License v2.0 or later](LICENSE).
