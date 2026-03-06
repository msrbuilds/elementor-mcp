=== MCP Tools for Elementor ===
Contributors: mianshahzadraza
Tags: elementor, mcp, ai, page-builder, automation
Requires at least: 6.7
Tested up to: 6.9
Stable tag: 1.4.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extends the WordPress MCP Adapter to expose Elementor data, widgets, and page design tools as MCP tools for AI agents.

== Description ==

MCP Tools for Elementor bridges the gap between AI tools and Elementor page design. It extends the official WordPress MCP Adapter to expose 92 MCP (Model Context Protocol) tools that let AI agents like Claude, Cursor, and other MCP-compatible clients create and manipulate Elementor page designs programmatically.

**Key Features:**

* **Query & Discovery** — List widgets, inspect page structures, read element settings, browse templates, and view global design tokens.
* **Page Management** — Create pages, update page settings, clear content, import/export templates.
* **Layout Tools** — Add flexbox containers, move/remove/duplicate elements.
* **Widget Tools** — 41 widget tools: universal add/update for any widget, plus 23 free convenience shortcuts and 16 conditional Pro widget tools.
* **Pro Widget Support** — Conditional tools for Elementor Pro widgets (form, posts grid, countdown, price table, flip box, animated headline, call to action, slides, testimonial carousel, price list, gallery, share buttons, table of contents, blockquote, Lottie, hotspot) that only register when Pro is active.
* **Template Tools** — Save pages or elements as reusable templates, apply templates to pages.
* **Global Settings** — Update site-wide color palettes and typography presets.
* **Composite Tools** — Build a complete page from a declarative JSON structure in a single call.
* **Stock Images** — Search Openverse for Creative Commons images, sideload into Media Library, add to pages.
* **SVG Icons** — Upload SVG icons from URL or raw markup for use with Elementor icon widgets.
* **Custom Code** — Add custom CSS (element/page level), inject JavaScript, create site-wide code snippets for head/body injection.
* **Sample Prompts** — 5 ready-to-use landing page blueprints with one-click copy from the admin dashboard.
* **Admin Dashboard** — Toggle individual tools on/off, view connection configs for all supported MCP clients, and browse/copy sample prompts.

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
5. Go to **Settings > MCP Tools for Elementor** to configure tools and view connection info.

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

= Codex Connection =

Add to `~/.codex/config.toml` or `.codex/config.toml`:

`
[mcp_servers.elementor-mcp]
url = "https://your-site.com/wp-json/mcp/elementor-mcp-server"

[mcp_servers.elementor-mcp.http_headers]
"Authorization" = "Basic BASE64_ENCODED_CREDENTIALS"
`

= npx mcp-remote Connection (Local) =

For local development, use `mcp-remote` to bridge your AI client to the WordPress HTTP endpoint:

`
{
  "mcpServers": {
    "elementor-mcp": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "http://localhost:10003/wp-json/mcp/elementor-mcp-server",
        "--header",
        "Authorization: Basic BASE64_ENCODED_CREDENTIALS"
      ]
    }
  }
}
`

Replace `localhost:10003` with your local WordPress address and `BASE64_ENCODED_CREDENTIALS` with your Base64-encoded `username:app-password`.

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

Yes. Go to Settings > MCP Tools for Elementor > Tools tab to toggle individual tools on or off.

= Does this plugin require the WordPress MCP Adapter? =

Yes. The MCP Adapter handles the MCP protocol transport layer. This plugin registers its tools through the Adapter's server infrastructure.

= Is this plugin safe to use on production sites? =

The plugin enforces WordPress capability checks on every tool. Read operations require `edit_posts`, write operations check `edit_post` ownership, and global settings require `manage_options`. All input is sanitized and validated.

== Screenshots ==

1. Tools management page with category-grouped toggles.
2. Connection configuration page with copy-paste configs.

== Changelog ==

= 1.4.0 =
* New: 22 Pro widget convenience tools — nav menu, loop grid, loop carousel, media carousel, nested tabs, nested accordion, and more.
* New: 5 WooCommerce widget tools — products, add-to-cart, cart, checkout, menu cart (conditional on WooCommerce).
* New: 4 layout tools — update-container, update-element, batch-update, reorder-elements.
* New: 6 template/theme builder tools — create-theme-template, set-template-conditions, list-dynamic-tags, set-dynamic-tag, create-popup, set-popup-settings.
* New: 2 query tools — get-container-schema, find-element.
* New: 4 extended core widget tools — menu-anchor, shortcode, rating, text-path.
* Total MCP tools increased from 70 to 92.
* Improved: Settings validator with stricter schema enforcement.
* Improved: Element factory with enhanced container support.

= 1.3.2 =
* Renamed plugin to "MCP Tools for Elementor" to comply with WordPress.org trademark guidelines.
* Updated admin menu label to "EMCP Tools" for brevity.
* Fixed WPCS issues: prefixed all global variables in view templates, escaped integer output, added missing translators comments.
* Updated "Tested up to" to WordPress 6.9.
* Added languages/ directory for Domain Path header.

= 1.3.1 =
* New: Prompts tab in admin dashboard — browse and one-click copy 5 sample landing page prompts.
* New: Contributing Prompts guide in CONTRIBUTING.md with structure, guidelines, and submission steps.
* Improved: Admin CSS for prompt card grid with hover effects and responsive breakpoints.

= 1.3.0 =
* New: `add-custom-css` tool — add custom CSS to any element or page-level with `selector` keyword support (Pro only).
* New: `add-custom-js` tool — inject JavaScript via HTML widget with automatic `<script>` wrapping and optional DOMContentLoaded wrapper.
* New: `add-code-snippet` tool — create site-wide Custom Code snippets for head/body injection with priority and jQuery support (Pro only).
* New: `list-code-snippets` tool — list all Custom Code snippets with location, priority, and status filters (Pro only).
* Total tools increased from ~64 to ~68.

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

= 1.4.0 =
Major update: 22 new tools including theme builder, dynamic tags, popup builder, WooCommerce widgets, and enhanced layout management. Total tools now 92.

= 1.3.2 =
Plugin renamed to "MCP Tools for Elementor". WPCS fixes and WordPress 6.9 compatibility.

= 1.3.1 =
New Prompts tab in admin — browse and copy sample landing page prompts directly from WordPress.

= 1.3.0 =
4 new Custom Code tools: add-custom-css, add-custom-js, add-code-snippet, list-code-snippets. Enables AI agents to inject CSS, JS, and site-wide code snippets.

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
