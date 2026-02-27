=== Elementor MCP ===
Contributors: developer
Tags: elementor, mcp, ai, page-builder, automation
Requires at least: 6.8
Tested up to: 6.8
Stable tag: 1.0.0
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

= 1.0.0 =
Initial release.
