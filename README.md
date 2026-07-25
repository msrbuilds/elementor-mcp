[![MCP Toplist](https://mcptoplist.com/badge/pulsemcp%2Fmsrbuilds-elementor.svg)](https://mcptoplist.com/server/pulsemcp%2Fmsrbuilds-elementor)

<h1 align="center">
  <img src="assets/img/icon-sm.png" width="72" alt="EMCP Tools logo"><br>
  MCP Tools for WordPress &amp; Page Builders
</h1>

<div align="center">

[![Version](https://img.shields.io/github/v/release/msrbuilds/elementor-mcp?label=version&color=blue)](https://github.com/msrbuilds/elementor-mcp/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-8892BF.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.9-21759B.svg)](https://wordpress.org)
[![MCP Tools](https://img.shields.io/badge/MCP_Tools-200%2B-orange.svg)](https://emcptools.com/docs/tools/overview/)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)
[![GitHub Issues](https://img.shields.io/github/issues/msrbuilds/elementor-mcp)](https://github.com/msrbuilds/elementor-mcp/issues)
[![GitHub Stars](https://img.shields.io/github/stars/msrbuilds/elementor-mcp?style=social)](https://github.com/msrbuilds/elementor-mcp)

**[Docs](https://emcptools.com/docs/) · [Integrations](https://emcptools.com/integrations/) · [Changelog](https://emcptools.com/changelog) · [Pro](https://emcptools.com/pricing)**

</div>

Turn your WordPress site into something an AI agent can actually operate.

EMCP Tools is a WordPress plugin that exposes your site as **[MCP](https://modelcontextprotocol.io/) tools**, so Claude, Cursor, and any other MCP client can build Elementor pages, write content, manage plugins and users, audit performance and security, and drive the plugins you already run. It builds on the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter), which ships bundled.

<img width="1995" height="1141" alt="EMCP Tools admin" src="https://github.com/user-attachments/assets/06ab916a-0146-4a5f-828c-d5aa409cb072" />

> **EMCP Pro, 25% off for the GitHub community**: use code **`MSRGIT`** at [emcptools.com/pricing](https://emcptools.com/pricing).

## What it does

**Build pages.** The full Elementor workflow, containers, widgets, templates, global styles, and atomic elements for Elementor 4.0+. Also Gutenberg blocks, and a builder-agnostic theme builder for headers, footers, and archives.

**Run the site.** Content and taxonomies, media, users, settings, plugins and themes, nav menus, the filesystem, and the database, all over MCP.

**Understand and undo.** One-call page snapshots, content search across your own pages and templates, a change ledger with rollback, and read-only performance and security scans that return a scored report.

**Speak your plugins.** Integrations that register only when the plugin is active: ACF, Meta Box, WooCommerce, 8 form builders, 7 SEO plugins, and the Elementor addon packs. See the [integrations directory](https://emcptools.com/integrations/).

Elementor is **optional**. Every WordPress domain works without it; installing Elementor unlocks the page-building family.

→ **[Full tool reference](https://emcptools.com/docs/tools/overview/)**

## Install

1. Download the latest `emcp-tools-*.zip` from [Releases](https://github.com/msrbuilds/elementor-mcp/releases/).
2. In WordPress: **Plugins → Add New → Upload Plugin**, then activate.
3. Open the **EMCP Tools** menu in the admin sidebar.

Free installs update in place from **Dashboard → Updates**.

**Requires** WordPress 6.9+ and PHP 8.1+. Elementor 3.20+ is optional (4.0+ for atomic elements). The MCP Adapter and Abilities API need no separate install.

→ [Installation guide](https://emcptools.com/docs/getting-started/installation/) · [Requirements](https://emcptools.com/docs/getting-started/requirements/)

## Connect your AI client

The **Connection** tab in the admin generates a ready-to-paste config for your client, including a one-click `.mcpb` bundle for Claude Desktop. That is the fastest route, and it fills in your site URL and credentials for you.

Step-by-step guides per client:

[Claude Code](https://emcptools.com/docs/connecting/claude-code/) · [Claude Desktop](https://emcptools.com/docs/connecting/claude-desktop/) · [Cursor](https://emcptools.com/docs/connecting/cursor/) · [VS Code](https://emcptools.com/docs/connecting/vscode/) · [Antigravity](https://emcptools.com/docs/connecting/antigravity/) · [ChatGPT](https://emcptools.com/docs/connecting/chatgpt-app/) · [WP-CLI](https://emcptools.com/docs/connecting/wp-cli/) · [OAuth](https://emcptools.com/docs/connecting/oauth/) · [Multiple sites](https://emcptools.com/docs/connecting/multiple-sites/)

Tools not showing up? Start with [no tools appearing](https://emcptools.com/docs/troubleshooting/no-tools-appearing/) or [tool cap exceeded](https://emcptools.com/docs/troubleshooting/tool-cap-exceeded/).

## Safe by default

Every tool runs a real WordPress capability check before it does anything, so an agent can only do what the authenticating user could do by hand.

Anything that writes, deletes, or renders site-wide **ships disabled** and is opt-in from **EMCP Tools → Tools**. Destructive operations additionally require an explicit `confirm: true`. Administrators cannot be edited over MCP, there is no delete-user tool, and filesystem access is confined to the WordPress root with automatic backups and an audit log.

→ [Permission model](https://emcptools.com/docs/tools/overview/#permission-model)

## Sample prompts

The [`prompts/`](prompts/) directory has five complete landing-page blueprints, design system, structure, images, and animations, that build an entire page from a single paste: [Local Business](prompts/LOCAL_BUSINESS.md), [Dental Clinic](prompts/DENTAL_CLINIC.md), [Developer Portfolio](prompts/WEB_DEVELOPER_PORTFOLIO.md), [Hair Salon](prompts/HAIR_SALON.md), [Car Wash](prompts/CAR_WASH.md).

A library of 50+ industry-specific prompts is included with [Pro](https://emcptools.com/pricing).

## Contributing

Bug reports, feature requests, and pull requests are all welcome. See [CONTRIBUTING.md](CONTRIBUTING.md).

1. Fork the repo
2. Create a branch (`git checkout -b feature/amazing-tool`)
3. Make your changes and test locally
4. Open a Pull Request

**Contributors**: [@msrbuilds](https://github.com/msrbuilds) (author and maintainer)

## License

[GNU General Public License v2.0 or later](LICENSE).
