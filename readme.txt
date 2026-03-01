=== Elementor MCP ===
Contributors: developer
Tags: elementor, mcp, ai, page-builder, automation
Requires at least: 6.8
Tested up to: 6.8
Stable tag: 1.2.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extends the WordPress MCP Adapter to expose Elementor data, widgets, and page design tools as MCP tools for AI agents.

== Description ==

Elementor MCP bridges the gap between AI tools and Elementor page design. It extends the official WordPress MCP Adapter to expose ~37 MCP (Model Context Protocol) tools that let AI agents like Claude, Cursor, and other MCP-compatible clients create and manipulate Elementor page designs programmatically.

**Key Features:**

* **Query & Discovery** — List widgets, inspect page structures, read element settings, browse templates, and view global design tokens.
* **Page Management** — Create pages, update page settings, clear content, import/export templates.
* **Layout Tools** — Add containers, move elements, remove elements, duplicate elements.
* **Widget Tools** — Add any widget type with full settings control, plus convenience shortcuts for heading, text, image, button, video, icon, spacer, divider, and icon box.
* **Pro Widget Support** — Conditional tools for Elementor Pro widgets (form, posts grid, countdown, price table, flip box, animated headline) that only register when Pro is active.
* **Template Tools** — Save pages or elements as reusable templates, apply templates to pages.
* **Global Settings** — Update site-wide color palettes and typography presets.
* **Composite Tools** — Build a complete page from a declarative structure in a single call.
* **Admin Dashboard** — Toggle individual tools on/off and view connection configurations for all supported MCP clients.

**Requires:**

* WordPress 6.8 or later
* Elementor 3.20 or later (container support required)
* WordPress MCP Adapter plugin
* WordPress Abilities API (bundled in WP 6.9+)

**Connection Methods:**

* WP-CLI stdio (recommended for local development)
* Node.js HTTP proxy (for remote sites)
* Direct HTTP (for VS Code MCP extension)

== Installation ==

1. Install and activate [Elementor](https://wordpress.org/plugins/elementor/) (version 3.20+).
2. Install and activate the WordPress MCP Adapter plugin.
3. Upload the `elementor-mcp` folder to `/wp-content/plugins/`.
4. Activate the plugin through the 'Plugins' menu in WordPress.
5. Go to **Settings > Elementor MCP** to configure tools and view connection info.

= WP-CLI Connection (Local) =

Add to your MCP client configuration:

`
{
  "mcpServers": {
    "elementor-mcp": {
      "command": "wp",
      "args": ["mcp-adapter", "serve", "--server=elementor-mcp-server", "--user=admin", "--path=/path/to/wordpress"]
    }
  }
}
`

= HTTP Proxy Connection (Remote) =

1. Create a WordPress Application Password at Users > Profile > Application Passwords.
2. Configure your MCP client with the included Node.js proxy:

`
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
`

== Frequently Asked Questions ==

= What is MCP? =

MCP (Model Context Protocol) is an open standard that allows AI tools to interact with external services. This plugin exposes Elementor's page building capabilities as MCP tools.

= Does this plugin work without Elementor Pro? =

Yes. Core widget tools work with free Elementor. Pro widget shortcuts (form, posts grid, countdown, price table, flip box, animated headline) only register when Elementor Pro is active.

= Can I disable specific tools? =

Yes. Go to Settings > Elementor MCP > Tools tab to toggle individual tools on or off.

= Does this plugin require the WordPress MCP Adapter? =

Yes. The MCP Adapter handles the MCP protocol transport layer. This plugin registers its tools through the Adapter's server infrastructure.

= Is this plugin safe to use on production sites? =

The plugin enforces WordPress capability checks on every tool. Read operations require `edit_posts`, write operations check `edit_post` ownership, and global settings require `manage_options`. All input is sanitized and validated.

== Screenshots ==

1. Tools management page with category-grouped toggles.
2. Connection configuration page with copy-paste configs.

== Changelog ==

= 1.2.3 =
* Fix: Factory now strips `flex_wrap` and `_flex_size` from container settings — prevents AI agents from setting these values that cause layout overflow.
* Fix: Tool descriptions now include background color instructions (`background_background=classic`, `background_color=#hex`) so AI agents apply colors correctly.
* Improved: Stronger "NEVER set flex_wrap" guidance in build-page and add-container tool descriptions.

= 1.2.2 =
* Fix: Row container children now use `content_width: full` with percentage widths (e.g. 25% for 4 columns) matching Elementor's native column layout pattern.
* Fix: Removed all `flex_wrap` and `_flex_size` auto-overrides from factory and build-page — Elementor defaults handle layout correctly.
* Improved: Tool descriptions updated with correct multi-column layout guidance.

= 1.2.1 =
* Fix: Row containers now use `flex_wrap: wrap` instead of `nowrap` to prevent children from overflowing.
* Fix: `build-page` auto-sets percentage widths on row children (e.g. 50% for 2 columns, 33.33% for 3) instead of using `_flex_size: grow` which caused layout overflow.
* Improved: Tool descriptions updated with correct layout guidance for multi-column layouts.

= 1.2.0 =
* New: 14 free widget convenience tools — accordion, alert, counter, Google Maps, icon list, image box, image carousel, progress bar, social icons, star rating, tabs, testimonial, toggle, HTML.
* New: 10 Pro widget convenience tools — call to action, slides, testimonial carousel, price list, gallery, share buttons, table of contents, blockquote, Lottie animation, hotspot.
* Total widget tools increased from 17 to 41 (~64 MCP tools overall).

= 1.1.1 =
* Fix: Container flex layout — row children auto-grow with `_flex_size: grow` for equal distribution.
* Fix: Column containers auto-center content horizontally (`align_items: center`).
* Fix: Row containers auto-set `flex_wrap: nowrap` to prevent wrapping.
* Fix: `_flex_size` now correctly uses string value (`grow`) instead of array — prevents fatal error in Elementor CSS generator.
* Fix: `get-global-settings` input schema uses `stdClass` for empty properties to serialize as JSON `{}` instead of `[]`.
* New: Connection tab configs for Cursor, Windsurf, and Antigravity IDE clients.
* New: 3 stock image tools — `search-images`, `sideload-image`, `add-stock-image` (Openverse API).
* New: SVG icon tool — `add-svg-icon` for custom SVG icons.
* Improved: `build-page` description with detailed layout rules for row/column containers.
* Improved: Admin connection tab streamlined — removed WP-CLI local section, unified HTTP config workflow.

= 1.0.0 =
* Initial release.
* 7 read-only query/discovery tools.
* 5 page management tools (create, update settings, delete content, import, export).
* 4 layout tools (add container, move, remove, duplicate elements).
* 2 universal widget tools (add-widget, update-widget).
* 9 core widget convenience shortcuts.
* 6 Pro widget convenience shortcuts (conditional on Elementor Pro).
* 2 template tools (save as template, apply template).
* 2 global settings tools (colors, typography).
* 1 composite build-page tool.
* Admin settings page with tool toggles and connection info.
* Node.js HTTP proxy for remote connections.

== Upgrade Notice ==

= 1.2.3 =
Factory now strips flex_wrap and _flex_size from settings to prevent layout overflow. Background color guidance added to tool descriptions.

= 1.2.2 =
Fixes row layout — inner containers use content_width=full with percentage widths, no flex_wrap or _flex_size overrides.

= 1.2.1 =
Fixes row container overflow — children now use percentage widths and flex-wrap for correct multi-column layouts.

= 1.2.0 =
24 new widget convenience tools covering all major Elementor free and Pro widgets.

= 1.1.1 =
Container layout fixes, stock image tools, multi-IDE connection configs. Fixes fatal error with `_flex_size` on row containers.

= 1.0.0 =
Initial release.
