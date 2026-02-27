# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Elementor MCP Plugin — a WordPress plugin that extends the official WordPress MCP Adapter to expose Elementor data, widgets, structures, and methods as MCP (Model Context Protocol) tools. This enables AI tools (Claude, Cursor, etc.) to create and manipulate Elementor page designs programmatically via ~37 MCP tools.

**Current status: All phases implemented (P0/P1/P2).** Foundation layer, 7 read-only query tools, page CRUD, layout, widget, template, global, and composite tools are all complete (~37 MCP tools total). See `PLAN.md` for the full architectural specification.

## Dependencies & Requirements

- WordPress >= 6.8
- Elementor >= 3.20 (container support required)
- WordPress Abilities API (bundled in WP 6.9+, or via composer)
- WordPress MCP Adapter (`wordpress/mcp-adapter` via composer)
- PHP 7.4+

## Build & Development Commands

No external dependencies. The plugin uses WordPress core, Elementor, MCP Adapter, and the Abilities API (all loaded as separate plugins or WP core).

For plugin review tooling, the `.claude/skills/wp-plugin-review/scripts/setup_tools.sh` script installs PHPCS, WPCS, PHPStan, and PHPUnit.

## Architecture

### MCP Server Registration

The plugin registers a dedicated MCP server `elementor-mcp-server` at `/wp-json/mcp/elementor-mcp-server`. All abilities use the `elementor-mcp/` namespace.

### Directory Structure

```
elementor-mcp/
├── elementor-mcp.php                          # Bootstrap: plugin header, constants, dependency checks, require_once, singleton init
├── includes/
│   ├── class-plugin.php                       # Singleton orchestrator — hooks into wp_abilities_api_categories_init, wp_abilities_api_init, mcp_adapter_init
│   ├── class-elementor-data.php               # Data access layer wrapping Elementor documents, widgets, element tree
│   ├── class-element-factory.php              # Builds valid Elementor JSON element structures (container, widget, section, column)
│   ├── class-id-generator.php                 # 7-char hex unique IDs via random_bytes()
│   ├── abilities/
│   │   ├── class-ability-registrar.php        # Coordinates registration of all ability groups across all phases
│   │   ├── class-query-abilities.php          # P0: 7 read-only tools (list-widgets, get-widget-schema, get-page-structure, etc.)
│   │   ├── class-page-abilities.php           # P1: 5 page CRUD tools (create-page, update-page-settings, delete-page-content, import-template, export-page)
│   │   ├── class-layout-abilities.php         # P1: 4 layout tools (add-container, move-element, remove-element, duplicate-element)
│   │   ├── class-widget-abilities.php         # P1/P2: 2 universal + 9 core + 6 Pro convenience widget tools
│   │   ├── class-template-abilities.php       # P2: 2 template tools (save-as-template, apply-template)
│   │   ├── class-global-abilities.php         # P2: 2 global tools (update-global-colors, update-global-typography)
│   │   └── class-composite-abilities.php      # P2: 1 composite tool (build-page)
│   ├── schemas/
│   │   ├── class-schema-generator.php         # Generates JSON Schema from Elementor widget controls
│   │   └── class-control-mapper.php           # Maps individual Elementor control types → JSON Schema fragments
│   └── validators/
│       ├── class-element-validator.php         # Validates element structure (id, elType, widgetType)
│       └── class-settings-validator.php        # Validates widget settings against generated schema
└── tests/                                      # PHPUnit tests (not yet created)
```

### Hook Registration Flow

The plugin integrates via three WordPress hooks (in execution order):
1. **`wp_abilities_api_categories_init`** → Registers the `elementor-mcp` ability category
2. **`wp_abilities_api_init`** → Registers all abilities via `wp_register_ability()` (ability names must match `[a-z0-9-]+/[a-z0-9-]+`)
3. **`mcp_adapter_init`** → Creates MCP server via `$mcp_adapter->create_server()`, passing ability names as the tools array

The MCP Adapter converts ability names like `elementor-mcp/list-widgets` to tool names `elementor-mcp-list-widgets` (replacing `/` with `-`).

### Core Layers

1. **Data Layer** (`class-elementor-data.php`) — Read/write wrapper around `_elementor_data` post meta, widget registry, and element tree traversal. All saves go through `\Elementor\Plugin::$instance->documents->get()->save()` (never raw meta updates) to trigger CSS regeneration and cache busting.

2. **Element Factory** (`class-element-factory.php`) — Creates valid Elementor JSON structures for containers, widgets, sections, and columns. Each element gets a 7-char random hex ID.

3. **Schema Generator** (`class-schema-generator.php` + `class-control-mapper.php`) — Maps Elementor control types (TEXT, SLIDER, SELECT, MEDIA, DIMENSIONS, REPEATER, etc.) to JSON Schema. This powers the `get-widget-schema` tool that tells AI agents what settings each widget accepts.

4. **Abilities** — Grouped by domain: query (7 read-only tools), page CRUD (5), layout/container (4), widgets (16 including convenience shortcuts), templates (2), globals (2), and the composite `build-page` tool.

### Implementation Phases (from PLAN.md)

| Priority | Phase | Scope |
|----------|-------|-------|
| P0 | Foundation | Bootstrap, data layer, factory, schemas, 7 read/query tools |
| P1 | Pages & Widgets | Page CRUD (5), layout tools (4), widget tools (10+) |
| P2 | Templates & Composite | Template tools (2), global tools (2), build-page composite (1) |

### Key Design Patterns

- **Container-first**: Uses modern Elementor Container element (flexbox), not legacy Sections/Columns
- **Schema-driven validation**: Widget settings validated against auto-generated JSON schemas before saving
- **Universal + convenience**: `add-widget` works for any widget type; convenience tools (`add-heading`, `add-button`) provide simpler interfaces for common widgets
- **Pro-aware**: Pro widget tools only register when Elementor Pro is active; core tools work with free Elementor

### Permission Model

| Ability Group | Required WordPress Capability |
|---------------|-------------------------------|
| Read/Query | `edit_posts` |
| Page creation | `publish_pages` or `edit_pages` |
| Widget/layout manipulation | `edit_posts` + ownership check |
| Template management | `edit_posts` |
| Global settings | `manage_options` |
| Delete operations | `delete_posts` + ownership check |

## All Implemented Tools (~37 total)

### P0 — Query/Discovery (7 read-only)

| Ability Name | Purpose |
|---|---|
| `elementor-mcp/list-widgets` | All registered widget types with names, titles, icons, categories, keywords |
| `elementor-mcp/get-widget-schema` | Full JSON Schema for a widget's settings (auto-generated from Elementor controls) |
| `elementor-mcp/get-page-structure` | Element tree for a page (containers, widgets, nesting) |
| `elementor-mcp/get-element-settings` | Current settings for a specific element on a page |
| `elementor-mcp/list-pages` | All Elementor-enabled pages/posts |
| `elementor-mcp/list-templates` | Saved Elementor templates from the template library |
| `elementor-mcp/get-global-settings` | Active kit/global settings (colors, typography, spacing) |

### P1 — Page CRUD (5 tools)

| Ability Name | Purpose |
|---|---|
| `elementor-mcp/create-page` | Create a new WP page/post with Elementor enabled |
| `elementor-mcp/update-page-settings` | Update page-level Elementor settings (background, padding, etc.) |
| `elementor-mcp/delete-page-content` | Clear all Elementor content from a page (destructive) |
| `elementor-mcp/import-template` | Import JSON template structure into a page |
| `elementor-mcp/export-page` | Export page's full Elementor data as JSON |

### P1 — Layout (4 tools)

| Ability Name | Purpose |
|---|---|
| `elementor-mcp/add-container` | Add a flexbox container (top-level or nested) |
| `elementor-mcp/move-element` | Move an element to a new parent/position |
| `elementor-mcp/remove-element` | Remove an element and all children (destructive) |
| `elementor-mcp/duplicate-element` | Duplicate element with fresh IDs |

### P1/P2 — Widgets (2 universal + 9 core + 6 Pro convenience)

| Ability Name | Purpose |
|---|---|
| `elementor-mcp/add-widget` | Universal: add any widget type to a container |
| `elementor-mcp/update-widget` | Universal: update settings on an existing widget |
| `elementor-mcp/add-heading` | Convenience: heading widget |
| `elementor-mcp/add-text-editor` | Convenience: rich text editor widget |
| `elementor-mcp/add-image` | Convenience: image widget |
| `elementor-mcp/add-button` | Convenience: button widget |
| `elementor-mcp/add-video` | Convenience: video widget |
| `elementor-mcp/add-icon` | Convenience: icon widget |
| `elementor-mcp/add-spacer` | Convenience: spacer widget |
| `elementor-mcp/add-divider` | Convenience: divider widget |
| `elementor-mcp/add-icon-box` | Convenience: icon box widget |
| `elementor-mcp/add-form` | Pro: form widget |
| `elementor-mcp/add-posts-grid` | Pro: posts grid widget |
| `elementor-mcp/add-countdown` | Pro: countdown timer widget |
| `elementor-mcp/add-price-table` | Pro: price table widget |
| `elementor-mcp/add-flip-box` | Pro: flip box widget |
| `elementor-mcp/add-animated-headline` | Pro: animated headline widget |

### P2 — Templates (2 tools)

| Ability Name | Purpose |
|---|---|
| `elementor-mcp/save-as-template` | Save a page or element as reusable template |
| `elementor-mcp/apply-template` | Apply a saved template to a page |

### P2 — Global Settings (2 tools)

| Ability Name | Purpose |
|---|---|
| `elementor-mcp/update-global-colors` | Update site-wide color palette in Elementor kit |
| `elementor-mcp/update-global-typography` | Update site-wide typography in Elementor kit |

### P2 — Composite (1 tool)

| Ability Name | Purpose |
|---|---|
| `elementor-mcp/build-page` | Create complete page from declarative structure in one call |

## Connecting to the MCP Server

### Prerequisites

- WordPress with Elementor + MCP Adapter + Elementor MCP all active
- One of: WP-CLI (for local) or Node.js 18+ (for remote/proxy)
- A WordPress Application Password (Users > Profile > Application Passwords)

### Option A: WP-CLI stdio (local dev, recommended)

The MCP Adapter includes a built-in WP-CLI stdio bridge. No HTTP round-trip, no sessions, no auth config needed.

**Claude Code** (`.mcp.json` already in project root):
```json
{
  "mcpServers": {
    "elementor-mcp": {
      "type": "stdio",
      "command": "wp",
      "args": ["mcp-adapter", "serve", "--server=elementor-mcp-server", "--user=admin", "--path=C:/laragon/www/msrplugins"]
    }
  }
}
```

**Claude Desktop** (add to `%APPDATA%\Claude\claude_desktop_config.json`):
```json
{
  "mcpServers": {
    "elementor-mcp": {
      "command": "wp",
      "args": ["mcp-adapter", "serve", "--server=elementor-mcp-server", "--user=admin", "--path=C:/laragon/www/msrplugins"]
    }
  }
}
```

**Verify:** `wp mcp-adapter list --path=C:/laragon/www/msrplugins` should show `elementor-mcp-server`.

### Option B: Node.js HTTP proxy (remote sites)

For remote WordPress sites or environments without WP-CLI, use the bundled proxy at `bin/mcp-proxy.mjs`. It bridges stdio ↔ WordPress HTTP endpoint with Application Password auth.

```json
{
  "mcpServers": {
    "elementor-mcp": {
      "command": "node",
      "args": ["bin/mcp-proxy.mjs"],
      "env": {
        "WP_URL": "https://your-site.com",
        "WP_USERNAME": "admin",
        "WP_APP_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx"
      }
    }
  }
}
```

### Option C: Direct HTTP (VS Code MCP extension)

```json
{
  "servers": {
    "elementor-mcp": {
      "type": "http",
      "url": "http://msrplugins.test/wp-json/mcp/elementor-mcp-server",
      "headers": {
        "Authorization": "Basic BASE64_ENCODED_CREDENTIALS"
      }
    }
  }
}
```

### Testing with MCP Inspector

```bash
npx @modelcontextprotocol/inspector wp mcp-adapter serve --server=elementor-mcp-server --user=admin --path=C:/laragon/www/msrplugins
```

### Troubleshooting

- **"No MCP servers registered"**: Ensure Elementor MCP plugin is active and dependencies are met
- **HTTP 401**: Check Application Password is correct and user has `edit_posts` capability
- **Session errors**: The HTTP endpoint requires `Mcp-Session-Id` header after `initialize`; the proxy handles this automatically
- **WP-CLI not found on Windows**: Use full path: `C:\laragon\bin\php\php-8.x\php.exe C:\path\to\wp-cli.phar`

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
