# MCP Tools for Elementor

[![Version](https://img.shields.io/badge/version-1.3.1-blue.svg)](https://github.com/msrbuilds/elementor-mcp/releases)
[![License](https://img.shields.io/badge/license-GPL--3.0-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-8892BF.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.8-21759B.svg)](https://wordpress.org)
[![Elementor](https://img.shields.io/badge/Elementor-%3E%3D3.20-92003B.svg)](https://elementor.com)
[![MCP Tools](https://img.shields.io/badge/MCP_Tools-70-orange.svg)](#available-tools)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)
[![GitHub Issues](https://img.shields.io/github/issues/msrbuilds/elementor-mcp)](https://github.com/msrbuilds/elementor-mcp/issues)
[![GitHub Stars](https://img.shields.io/github/stars/msrbuilds/elementor-mcp?style=social)](https://github.com/msrbuilds/elementor-mcp)

A WordPress plugin that extends the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) to expose Elementor data, widgets, and page design tools as [MCP (Model Context Protocol)](https://modelcontextprotocol.io/) tools. This enables AI agents like Claude, Cursor, and other MCP-compatible clients to create and manipulate Elementor page designs programmatically.

[![Watch the video](https://img.youtube.com/vi/tXCpGa-hqxk/maxresdefault.jpg)](https://www.youtube.com/watch?v=tXCpGa-hqxk)


## Features

- **70 MCP Tools** covering the full Elementor page-building workflow
- **Query & Discovery** — List widgets, inspect page structures, read element settings, browse templates, view global design tokens
- **Page Management** — Create pages, update settings, clear content, import/export templates
- **Layout Tools** — Add flexbox containers, move/remove/duplicate elements
- **Widget Tools** — 41 widget tools: universal add/update for any widget, plus 23 free convenience shortcuts (heading, text, image, button, video, icon, spacer, divider, icon box, accordion, alert, counter, Google Maps, icon list, image box, image carousel, progress bar, social icons, star rating, tabs, testimonial, toggle, HTML)
- **Pro Widget Support** — 16 conditional Pro widget tools (form, posts grid, countdown, price table, flip box, animated headline, call to action, slides, testimonial carousel, price list, gallery, share buttons, table of contents, blockquote, Lottie, hotspot)
- **Template Tools** — Save pages or elements as reusable templates, apply templates to pages
- **Global Settings** — Update site-wide color palettes and typography presets
- **Composite Tools** — Build a complete page from a declarative JSON structure in a single call
- **Stock Images** — Search Openverse for Creative Commons images, sideload into Media Library, add to pages
- **SVG Icons** — Upload SVG icons from URL or raw markup for use with Elementor icon widgets
- **Custom Code** — Add custom CSS (element/page level), inject JavaScript, create site-wide code snippets for head/body injection
- **Admin Dashboard** — Toggle individual tools on/off and view connection configs for all supported MCP clients


# Get 50+ Premium Prompts to Build High Quality Landing Pages
## [I Want This](https://wpacademy.gumroad.com/l/vlrihk)
<img width="1280" height="720" alt="banner" src="https://github.com/user-attachments/assets/03e02c73-9fa8-40af-997b-99515352ebbb" />

## Requirements

| Dependency | Version |
|---|---|
| WordPress | >= 6.8 |
| PHP | >= 7.4 |
| Elementor | >= 3.20 (container support required) |
| WordPress MCP Adapter | Latest |
| WordPress Abilities API | Bundled in WP 6.9+, or via Composer |

## Installation

1. Install and activate [Elementor](https://wordpress.org/plugins/elementor/) (version 3.20+).
2. Install and activate the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin.
3. Download the latest release zip from the [Releases page](https://github.com/msrbuilds/elementor-mcp/releases/).
4. In WordPress, go to **Plugins > Add New > Upload Plugin** and upload the downloaded zip file.
5. Activate the plugin through the **Plugins** menu in WordPress.
6. Go to **Settings > MCP Tools for Elementor** to configure tools and view connection info.

## Connecting to the MCP Server

Connect to your WordPress site from any AI client using HTTP. No proxy or Node.js needed — just a WordPress Application Password.

### Prerequisites

1. Create an Application Password at **Users > Profile > Application Passwords**.
2. Base64-encode your credentials: `echo -n "username:app-password" | base64`
3. Your MCP endpoint is: `https://your-site.com/wp-json/mcp/elementor-mcp-server`

> **Tip:** The plugin's admin page at **Settings > MCP Tools for Elementor > Connection** can generate all configs automatically — just enter your username and Application Password.

### Claude Code

Add as `.mcp.json` in your project root:

```json
{
    "mcpServers": {
        "elementor-mcp": {
            "type": "http",
            "url": "https://your-site.com/wp-json/mcp/elementor-mcp-server",
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
        "elementor-mcp": {
            "type": "http",
            "url": "https://your-site.com/wp-json/mcp/elementor-mcp-server",
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
        "elementor-mcp": {
            "url": "https://your-site.com/wp-json/mcp/elementor-mcp-server",
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
        "elementor-mcp": {
            "serverUrl": "https://your-site.com/wp-json/mcp/elementor-mcp-server",
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
        "elementor-mcp": {
            "serverUrl": "https://your-site.com/wp-json/mcp/elementor-mcp-server",
            "headers": {
                "Authorization": "Basic BASE64_ENCODED_CREDENTIALS"
            }
        }
    }
}
```

### npx mcp-remote (Local Development)

For local development, you can use [`mcp-remote`](https://www.npmjs.com/package/mcp-remote) to bridge your AI client to the WordPress HTTP endpoint via stdio. This works with any MCP client that supports stdio transport:

```json
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
```

Replace `localhost:10003` with your local WordPress address and `BASE64_ENCODED_CREDENTIALS` with your Base64-encoded `username:app-password`.

### WP-CLI stdio (Local Development)

For local development with WP-CLI available, you can use the stdio transport (no HTTP auth needed):

```json
{
    "mcpServers": {
        "elementor-mcp": {
            "type": "stdio",
            "command": "wp",
            "args": [
                "mcp-adapter", "serve",
                "--server=elementor-mcp-server",
                "--user=admin",
                "--path=/path/to/wordpress"
            ]
        }
    }
}
```

### Testing with MCP Inspector

```bash
npx @modelcontextprotocol/inspector wp mcp-adapter serve \
  --server=elementor-mcp-server --user=admin --path=/path/to/wordpress
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

### Page Management (5 tools)

| Tool | Description |
|---|---|
| `create-page` | Create a new WP page/post with Elementor enabled |
| `update-page-settings` | Update page-level Elementor settings (background, padding, etc.) |
| `delete-page-content` | Clear all Elementor content from a page |
| `import-template` | Import JSON template structure into a page |
| `export-page` | Export page's full Elementor data as JSON |

### Layout (4 tools)

| Tool | Description |
|---|---|
| `add-container` | Add a flexbox container (top-level or nested) |
| `move-element` | Move an element to a new parent/position |
| `remove-element` | Remove an element and all children |
| `duplicate-element` | Duplicate element with fresh IDs |

### Widgets (41 tools)

| Tool | Description |
|---|---|
| `add-widget` | Universal: add any widget type to a container |
| `update-widget` | Universal: update settings on an existing widget |
| `add-heading` | Convenience: heading widget |
| `add-text-editor` | Convenience: rich text editor widget |
| `add-image` | Convenience: image widget |
| `add-button` | Convenience: button widget |
| `add-video` | Convenience: video widget |
| `add-icon` | Convenience: icon widget |
| `add-spacer` | Convenience: spacer widget |
| `add-divider` | Convenience: divider widget |
| `add-icon-box` | Convenience: icon box widget |
| `add-accordion` | Convenience: collapsible accordion widget |
| `add-alert` | Convenience: alert/notice widget |
| `add-counter` | Convenience: animated counter widget |
| `add-google-maps` | Convenience: embedded Google Maps widget |
| `add-icon-list` | Convenience: icon list for features/checklists |
| `add-image-box` | Convenience: image box (image + title + description) |
| `add-image-carousel` | Convenience: rotating image carousel |
| `add-progress` | Convenience: animated progress bar |
| `add-social-icons` | Convenience: social media icon links |
| `add-star-rating` | Convenience: star rating display |
| `add-tabs` | Convenience: tabbed content widget |
| `add-testimonial` | Convenience: testimonial with quote and author |
| `add-toggle` | Convenience: toggle/expandable content |
| `add-html` | Convenience: custom HTML code widget |
| `add-form` | Pro: form widget |
| `add-posts-grid` | Pro: posts grid widget |
| `add-countdown` | Pro: countdown timer widget |
| `add-price-table` | Pro: price table widget |
| `add-flip-box` | Pro: flip box widget |
| `add-animated-headline` | Pro: animated headline widget |
| `add-call-to-action` | Pro: call-to-action widget |
| `add-slides` | Pro: full-width slides/slider |
| `add-testimonial-carousel` | Pro: testimonial carousel/slider |
| `add-price-list` | Pro: price list for menus/services |
| `add-gallery` | Pro: advanced gallery (grid/masonry/justified) |
| `add-share-buttons` | Pro: social share buttons |
| `add-table-of-contents` | Pro: auto-generated table of contents |
| `add-blockquote` | Pro: styled blockquote widget |
| `add-lottie` | Pro: Lottie animation widget |
| `add-hotspot` | Pro: image hotspot widget |

### Templates (2 tools)

| Tool | Description |
|---|---|
| `save-as-template` | Save a page or element as reusable template |
| `apply-template` | Apply a saved template to a page |

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

> All tool names are prefixed with `elementor-mcp/` in the MCP namespace (e.g., `elementor-mcp/list-widgets`). The MCP Adapter converts these to `elementor-mcp-list-widgets` for transport.

## Permission Model

| Tool Group | Required WordPress Capability |
|---|---|
| Read/Query | `edit_posts` |
| Page creation | `publish_pages` or `edit_pages` |
| Widget/layout manipulation | `edit_posts` + ownership check |
| Template management | `edit_posts` |
| Global settings | `manage_options` |
| Delete operations | `delete_posts` + ownership check |
| Stock image search | `edit_posts` |
| Stock image sideload | `upload_files` |
| Custom CSS/JS | `edit_posts` + ownership check |
| Code snippets | `manage_options` + `unfiltered_html` |

## Troubleshooting

- **"No MCP servers registered"** — Ensure the MCP Tools for Elementor plugin is active and all dependencies are met.
- **HTTP 401** — Check your Application Password is correct and the user has `edit_posts` capability.
- **Session errors** — The HTTP endpoint requires `Mcp-Session-Id` header after `initialize`; the proxy handles this automatically.
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

## License

This project is licensed under the [GNU General Public License v3.0](LICENSE).
