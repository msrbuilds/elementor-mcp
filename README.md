# Elementor MCP — Digitizers fork

**MCP Tools for Elementor.** A WordPress plugin that exposes Elementor's data, widgets, and page-design tools to AI agents over the [Model Context Protocol](https://modelcontextprotocol.io/).

[![Version](https://img.shields.io/github/v/release/Digitizers/elementor-mcp?label=version&color=blue)](https://github.com/Digitizers/elementor-mcp/releases)
[![Tests](https://github.com/Digitizers/elementor-mcp/actions/workflows/tests.yml/badge.svg)](https://github.com/Digitizers/elementor-mcp/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.9-21759B.svg)](https://wordpress.org)
[![Elementor](https://img.shields.io/badge/Elementor-%3E%3D3.20-92003B.svg)](https://elementor.com)
[![GitHub Issues](https://img.shields.io/github/issues/Digitizers/elementor-mcp)](https://github.com/Digitizers/elementor-mcp/issues)
[![GitHub Stars](https://img.shields.io/github/stars/Digitizers/elementor-mcp?style=social)](https://github.com/Digitizers/elementor-mcp)

This is the **Digitizers fork of [`msrbuilds/elementor-mcp`](https://github.com/msrbuilds/elementor-mcp)** (GPL). It keeps the original plugin's identity — the `elementor-mcp` slug, the `Elementor_MCP_*` classes, and the `elementor-mcp/` MCP namespace — and takes it in its own direction: a **correct Elementor 4.x atomic engine**, a **bundled MCP Adapter** so it installs as a single plugin, net-new **read-only inspection tools** (`list-media`, `list-global-classes`, `analyze-performance`, `scan-security`), and a **hardened, CI-tested** security posture. It is the engine behind the **Elementor Pro Studio** skill ([`Digitizers/claude-elementor-pro`](https://github.com/Digitizers/claude-elementor-pro), published on ClawHub as `elementor-pro-studio`), which drives it to build and edit real [Elementor](https://digitizer.li/elementor) pages.

The plugin extends the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) so AI clients — Claude Code, Claude Desktop, Cursor, and other MCP-compatible tools — can create and manipulate Elementor page designs programmatically over HTTP with just a WordPress Application Password.

## What this fork adds

Relative to upstream, and verified against [`CHANGELOG.md`](CHANGELOG.md) and the plugin source:

- **Elementor 4.x-correct atomic engine.** Independent 4.0 GA work: atomic-element detection gating (`is_v4` based on registered atomic types / active experiments, not the `ELEMENTOR_VERSION` string), correct atomic prop shapes, and working flex / padding / margin / colour / background / typography output — ahead of upstream on 4.x correctness.
- **Bundled WordPress MCP Adapter.** The adapter ships inside the plugin (`includes/vendors/mcp-adapter/`), so it installs as **one** plugin. When a standalone MCP Adapter plugin is already active, the fork defers to it (no double-load).
- **Net-new read-only tools**, ported and adapted for this fork:
  - `list-media` — query the site's own Media Library uploads.
  - `list-global-classes` — read Elementor 4.0's Class Manager (Global Classes), mapping opaque `g-<hash>` IDs to their names and the CSS each defines per breakpoint/state.
  - `analyze-performance` — read-only page + server + WordPress performance audit → scored (0–100 / A–F) report with ranked recommendations, with SSRF-guarded loopback fetches.
  - `scan-security` — read-only malware / core-integrity / hardening / outdated-software scan → scored report that returns `path:line` + snippet, never full file contents.
- **Security hardening + CI.** A full **F-001…F-027** regression suite (XSS / SVG-sanitiser / path-disclosure and other fixes) plus a committed **PHPUnit CI** workflow ([`.github/workflows/tests.yml`](.github/workflows/tests.yml)) running PHP 8.0 (syntax lint) and PHP 8.1 / 8.2 (full suite: Security / Capabilities / Input / Functional / Regression / SEO / Unit).

This fork deliberately keeps the `elementor-mcp` identity and does **not** adopt upstream's later `emcp-tools` rename.

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
2. Download the latest release zip from this fork's [Releases page](https://github.com/Digitizers/elementor-mcp/releases/latest). (The Elementor Pro Studio skill's installer pulls `releases/latest` automatically.)
3. In WordPress, go to **Plugins → Add New → Upload Plugin** and upload the zip. The MCP Adapter is bundled — no separate plugin install is required.
4. Activate the plugin through the **Plugins** menu.
5. Open the new **EMCP Tools** top-level menu in the WordPress admin sidebar to configure tools and view connection info.

## Connecting to the MCP server

Connect to your WordPress site from any AI client over HTTP. No proxy or Node.js needed — just a WordPress Application Password.

### Prerequisites

1. Create an Application Password at **Users → Profile → Application Passwords**.
2. Base64-encode your credentials: `echo -n "username:app-password" | base64`
3. Your MCP endpoint is: `https://your-site.com/wp-json/mcp/elementor-mcp-server`

> **Tip:** The plugin's **EMCP Tools → Connection** admin screen can generate every client config automatically — just enter your username and Application Password.

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

### WP-CLI stdio (local development)

For local development with WP-CLI available, use the stdio transport (no HTTP auth needed):

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

### Node.js proxy (remote sites or protocol compatibility)

For remote WordPress sites, environments without WP-CLI, or when your AI client needs a different MCP protocol version, use the Node.js proxy. Your AI client launches it as a **local subprocess**, so it must run on the machine with the client — **not** on the WordPress server.

Extract `bin/mcp-proxy.mjs` from the plugin ZIP, save it on the machine running your AI client, and point `args` at that **local** path (e.g. `["C:\\local\\path\\to\\mcp-proxy.mjs"]`) — not at the copy inside `wp-content/plugins/...` on the server. Re-extract it after plugin updates to pick up proxy fixes.

**Required environment variables** (the proxy exits immediately if any is missing):

| Variable | Description |
|---|---|
| `WP_URL` | Your WordPress site URL (e.g., `https://example.com`). |
| `WP_USERNAME` | WordPress username. |
| `WP_APP_PASSWORD` | An [Application Password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) for that user. |

**Optional environment variables:**

| Variable | Description |
|---|---|
| `MCP_LOG_FILE` | Path to a debug log file (e.g., `/tmp/elementor-mcp.log`) |
| `MCP_PROTOCOL_VERSION` | Override the protocol version in initialize responses (e.g., `2024-11-05`). Use this if your client doesn't support `2025-06-18`. |

### Testing with MCP Inspector

```bash
npx @modelcontextprotocol/inspector wp mcp-adapter serve \
  --server=elementor-mcp-server --user=admin --path=/path/to/wordpress
```

## Tool overview

All tool names are prefixed with `elementor-mcp/` in the MCP namespace (e.g. `elementor-mcp/list-widgets`); the MCP Adapter converts these to `elementor-mcp-list-widgets` for transport. The active tool count scales with the environment — some tools register only when Elementor 4.0 atomic elements, Elementor Pro, or WooCommerce are present.

- **Query & discovery** — list widgets, inspect page structure, read element settings, list pages, browse templates, read global design tokens, `list-media`, `list-global-classes`.
- **Page management** — create pages, update page settings, clear content, import/export templates.
- **Layout & structure** — add/update flexbox containers, move/remove/duplicate elements, find elements, batch update, reorder children, read container schema.
- **Widgets** — a universal `add-widget` / `update-widget` pair plus convenience shortcuts; Pro and WooCommerce widget tools register only when those plugins are active.
- **Atomic elements (Elementor 4.0+)** — dedicated tools for the atomic system (flexbox, div-block, heading, paragraph, button, image, svg, youtube, video, divider) plus universal `add-atomic-widget` / `update-atomic-widget` and `detect-elementor-version`. These register only when atomic elements are actually available.
- **Templates & theme builder** — save/apply templates; theme templates, display conditions, dynamic tags, and popups (Pro).
- **Global settings** — update site-wide colour palette and typography.
- **Composite** — `build-page`: create a complete page from a declarative JSON structure in one call.
- **Stock images & SVG** — search Openverse, sideload images into the Media Library, upload SVG icons.
- **Custom code** — add element/page CSS, inject JavaScript, manage site-wide code snippets.
- **Audits** — `analyze-performance` (page + server + WP performance) and `scan-security` (malware / integrity / hardening / outdated software).

The full tool tables (per-category, with parameters) are available in-plugin under **EMCP Tools**, where individual tools can be toggled on/off.

## Permission model

| Tool group | Required WordPress capability |
|---|---|
| Read / query | `edit_posts` |
| Page creation | `publish_pages` or `edit_pages` |
| Widget / layout manipulation | `edit_posts` + ownership check |
| Template management | `edit_posts` |
| Theme builder / popups | `edit_posts` |
| Dynamic tags | `edit_posts` + ownership check |
| Global settings | `manage_options` |
| Delete operations | `delete_posts` + ownership check |
| Stock image search | `edit_posts` |
| Stock image sideload | `upload_files` |
| Custom CSS / JS | `edit_posts` + ownership check |
| Code snippets | `manage_options` + `unfiltered_html` |

## Troubleshooting

### Tools not appearing in your AI client

If the MCP server connects but no tools appear:

1. **Verify tools are registered.** Test the endpoint directly to confirm the server responds:
   ```bash
   curl -s -u admin:YOUR_APP_PASSWORD \
     https://your-site.com/wp-json/mcp/elementor-mcp-server \
     -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}'
   ```
   A valid JSON-RPC response with `serverInfo` means the server is working; the issue is likely a protocol version mismatch with your client.
2. **Check for a protocol version mismatch.** The MCP Adapter reports `2025-06-18`. Some clients only support `2024-11-05`. If using the Node.js proxy, set `MCP_PROTOCOL_VERSION` to override.
3. **Enable debug logging.** Add `MCP_LOG_FILE` to the proxy config to capture the full request/response flow.
4. **Use the proxy instead of direct HTTP.** Clients that don't handle `Mcp-Session-Id` headers should use the Node.js proxy, which manages sessions automatically.

### Common errors

- **"No MCP servers registered"** — ensure the plugin is active and its dependencies (Elementor, bundled MCP Adapter, Abilities API) are met.
- **HTTP 401** — check the Application Password and that the user has `edit_posts`.
- **"Missing Mcp-Session-Id header"** — the HTTP endpoint requires an `Mcp-Session-Id` header after `initialize`; use the Node.js proxy instead of direct HTTP.
- **WP-CLI not found on Windows** — use the full path to `php.exe` and `wp-cli.phar`.

## Sample prompts

The [`prompts/`](prompts/) directory includes ready-to-use landing-page prompts that demonstrate the full workflow. Each is a complete blueprint — paste it into your AI client and watch a page get built.

| Prompt | Industry | Description |
|---|---|---|
| [Local Business](prompts/LOCAL_BUSINESS.md) | General | Multi-purpose small-business landing page with hero, services, testimonials, and contact |
| [Dental Clinic](prompts/DENTAL_CLINIC.md) | Health & Wellness | Dental practice with services, team, insurance info, and appointment booking |
| [Web Developer Portfolio](prompts/WEB_DEVELOPER_PORTFOLIO.md) | Professional Services | Developer portfolio with project showcase, tech stack, and contact form |
| [Hair Salon](prompts/HAIR_SALON.md) | Lifestyle | Salon page with services menu, stylist profiles, and booking |
| [Car Wash](prompts/CAR_WASH.md) | Lifestyle | Car wash with wash packages, add-on services, and membership plans |

Each prompt includes a full design system (palette, typography, spacing), image-search keywords, SVG icon specs, page structure, entrance animations, custom CSS for hover states, and a step-by-step execution order.

## Contributing

Contributions are welcome — bug reports, feature requests, documentation, and code. See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

**Quick start:**

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/amazing-tool`).
3. Make your changes and run the test suite (`composer install && vendor/bin/phpunit`).
4. Open a pull request.

## Credits & attribution

This project is a fork of **[`elementor-mcp`](https://github.com/msrbuilds/elementor-mcp)**, originally created and maintained by **[@msrbuilds](https://github.com/msrbuilds)** (Mian Shahzad Raza) and released under the GPL. Full credit for the original plugin — its architecture, the WordPress MCP Adapter integration, and the core Elementor tool set — goes to the upstream author. This fork builds on that foundation with the additions described above and is maintained by the **[Digitizers](https://github.com/Digitizers)** organization.

## License

This project is licensed under the [GNU General Public License v2.0 or later](LICENSE), the same license as the upstream project.
