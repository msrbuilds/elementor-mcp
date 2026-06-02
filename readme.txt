=== MCP Tools for Elementor ===
Contributors: mianshahzadraza
Tags: elementor, mcp, ai, page-builder, automation
Requires at least: 6.9
Tested up to: 6.9
Stable tag: 1.9.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extends the WordPress MCP Adapter to expose Elementor data, widgets, and page design tools as MCP tools for AI agents.

== Description ==

MCP Tools for Elementor bridges the gap between AI tools and Elementor page design. It extends the official WordPress MCP Adapter to expose up to 118 MCP (Model Context Protocol) tools that let AI agents like Claude, Cursor, and other MCP-compatible clients create and manipulate Elementor page designs programmatically.

Tool counts scale with your environment: 61 tools on a free Elementor install, 100 with Elementor Pro, 105 with Pro + WooCommerce, and 13 additional atomic tools when Elementor 4.0+ is active (74 / 113 / 118 respectively).

**Key Features:**

* **Query & Discovery** — List widgets, inspect page structures, read element settings, browse templates, and view global design tokens.
* **Page Management** — Create pages, update page settings, clear content, import/export templates.
* **Layout Tools** — Add flexbox containers, move/remove/duplicate elements, batch updates, reorder children.
* **Widget Tools** — Universal add/update for any widget, plus 27 free convenience shortcuts, 30 conditional Pro widget tools, and 5 WooCommerce widget tools.
* **Pro Widget Support** — Conditional tools for Elementor Pro widgets (form, posts grid, countdown, price table, flip box, animated headline, call to action, slides, testimonial carousel, price list, gallery, share buttons, table of contents, blockquote, Lottie, hotspot, loop grid/carousel, nested tabs/accordion, portfolio, author box, login, code highlight, reviews, off-canvas, progress tracker, search, and more) that only register when Pro is active.
* **Atomic Elements (Elementor 4.0+)** — 13 dedicated tools for Elementor's new atomic system: flexbox, div-block, heading, paragraph, button, image, svg, youtube, video, divider, plus universal `add-atomic-widget` / `update-atomic-widget` and `detect-elementor-version`.
* **Template Tools** — Save pages or elements as reusable templates, apply templates to pages, theme builder, popups, dynamic tags (Pro).
* **Global Settings** — Update site-wide color palettes and typography presets.
* **Composite Tools** — Build a complete page from a declarative JSON structure in a single call.
* **Stock Images** — Search Openverse for Creative Commons images, sideload into Media Library, add to pages.
* **SVG Icons** — Upload SVG icons from URL or raw markup for use with Elementor icon widgets.
* **Custom Code** — Add custom CSS (element/page level), inject JavaScript, create site-wide code snippets for head/body injection.
* **AI Widget Builder (Pro)** — Let an AI agent design custom Elementor widgets from a structured spec (no hand-written PHP). The plugin compiles the spec into a sandboxed widget that appears in the Elementor panel — 35 control types, optional CSS/JS, with a runtime safety net so a bad widget can never break the editor.
* **Brand Kits** — One-click color + typography kits that re-skin your whole site. 10 kits are free to apply (with backup + restore); 50+ with Pro.
* **Low-tools Mode** — One-click toggle that trims the active tool list to a curated 50-or-so essentials so MCP clients with strict tool caps (Antigravity, Gemini API, etc.) stay under their limits.
* **Sample Prompts** — Ready-to-use landing page blueprints with one-click copy from the admin dashboard.
* **Admin Dashboard** — Dedicated top-level menu with Tools, Connection, Prompts, Templates, Brand Kits, Skills, Widget Builder, and Changelog tabs. Toggle individual tools on/off, view connection configs for all supported MCP clients, and get help via the built-in Get Support link.

**Requires:**

* WordPress 6.9 or later
* Elementor 3.20 or later (container support required)
* WordPress Abilities API — included in WordPress core 6.9+ (and 7.0)
* WordPress MCP Adapter — bundled with the plugin (no separate install needed; an active standalone MCP Adapter plugin is used instead when present)

**Connection Methods:**

* WP-CLI stdio (recommended for local development)
* Node.js HTTP proxy (for remote sites)
* Direct HTTP (for VS Code MCP extension)

== Installation ==

1. Install and activate [Elementor](https://wordpress.org/plugins/elementor/) (version 3.20+).
2. Upload the `elementor-mcp` folder to `/wp-content/plugins/`.
3. Activate the plugin through the 'Plugins' menu in WordPress. The MCP Adapter is bundled — no separate install is required (WordPress 6.9+/7.0 already includes the Abilities API).
4. Open the new **EMCP Tools** top-level menu, go to the **Connection** tab, and confirm **Activate Abilities API for EMCP** is enabled (on by default) to expose the MCP server.

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

Yes. Open the **EMCP Tools** top-level admin menu and use the **Tools** screen to toggle individual tools on or off. If your MCP client has a strict tool cap (e.g. Antigravity's 100-tool limit), flip on **Low-tools mode** at the top of that screen to expose only a curated set of essentials.

= Does this plugin require the WordPress MCP Adapter? =

Yes. The MCP Adapter handles the MCP protocol transport layer. This plugin registers its tools through the Adapter's server infrastructure.

= Is this plugin safe to use on production sites? =

The plugin enforces WordPress capability checks on every tool. Read operations require `edit_posts`, write operations check `edit_post` ownership, and global settings require `manage_options`. All input is sanitized and validated.

== Screenshots ==

1. Tools management page with category-grouped toggles.
2. Connection configuration page with copy-paste configs.

== Changelog ==

= 1.9.0 =
* New: AI Widget Builder (Pro) — 8 MCP tools let an agent design custom Elementor widgets from a structured spec (no hand-written PHP). The plugin compiles the spec + an HTML template into a sandboxed Widget_Base class under uploads/emcp-widgets/, escaping every value by control type. 35 control types incl. group controls (typography/border/box-shadow/background), repeaters, responsive, and conditions, plus optional per-widget CSS/JS. New widgets auto-activate under a "Custom (EMCP)" category; a runtime safety net keeps a bad widget from breaking the editor. Off-by-default; managed on the new Widget Builder tab.
* New: 10 free brand kits — the Brand Kits tab now ships 10 curated color + typography kits anyone can apply for free, with backup-before-apply and restore. The full 50-kit library stays Pro.
* New: Get Support button in the admin header on every tab, linking to the support portal (support.msrbuilds.com).
* New: Pagination on the Prompts, Templates, Brand Kits, and Changelog pages — filter-aware, and it revived the Templates category filter.
* Fixed: Prompts page froze for several seconds with 50+ prompts — off-screen 1px-wide copy textareas forced a pathological reflow; they're now display:none.
* Fixed: Atomic (V4) tool detection (#47) — atomic tools now register based on whether the atomic element types are registered (or the e_atomic_elements/atomic_widgets experiment is on), not the ELEMENTOR_VERSION constant, and not on the page-editor opt-in alone (which let writes silently no-op).

= 1.8.3 =
* New: One-click credentials on the Connection tab — pick an administrator from a dropdown (admins only, you at the top) and click Generate to automatically create a new Application Password and fill in every client config. No more creating one by hand under your profile.
* New: "Use an existing Application Password instead" fallback for anyone who prefers to paste their own.
* Security: the generator is nonce-protected, requires manage_options plus edit_user on the chosen account, only targets administrator accounts, and won't mint a password over plain HTTP (where it could not authenticate).

= 1.8.2 =
* New: The Connection tab now generates ready-to-copy npx proxy configs for Claude Code and Claude Desktop ("npx -y @msrbuilds/emcp-proxy@latest") — the recommended way to connect a remote/shared-hosting site, with no local proxy file to maintain. The bundled-proxy-file configs are still offered for local WordPress.
* Fixed: Reorganized the Connection tab proxy section into "Remote (npx)" and "Local (bundled file)" groups so remote users no longer copy a server-side filesystem path that can't work from their machine.

= 1.8.1 =
* Fixed: Clarified the Node.js proxy docs for remote/shared-hosting setups. The proxy runs as a local subprocess on the machine with your AI client, so its file path must be local — not the copy inside wp-content/plugins on the server. The Connection tab, README, and config examples now make this explicit.
* New: Zero-install npx runner for remote connections — use "command": "npx", "args": ["-y", "@msrbuilds/emcp-proxy@latest"] instead of maintaining a local copy of the proxy file that can drift from the server version.
* Changed: Documented the MCP_PROTOCOL_VERSION=2024-11-05 override in the connection docs (previously only in release notes), for clients that reject the adapter's 2025-06-18 handshake.

= 1.8.0 =
* New: SEO & Accessibility toolkit for Pro subscribers — 7 new MCP tools that audit and improve a page at the structure level (no external API, no AI cost). SEO: audit-page-seo (scored on-page report), extract-keywords-from-content, generate-meta-tags (writes to Yoast/Rank Math with apply), generate-schema-markup (JSON-LD: Article/LocalBusiness/FAQPage/Service/Product, injects with apply). Accessibility: audit-page-a11y (WCAG-oriented: contrast, alts, heading order, link text, form labels), fix-color-contrast, add-alt-text-from-context.
* New: Every page-mutating tool is dry-run by default — fixers and the generator write-back only change the site when apply:true is passed, and edits are reversible via Elementor revisions.
* New: The 7 tools are Pro-gated and disabled-by-default; enable individual tools on the EMCP Tools tab (new "SEO & Accessibility" category).
* Changed: CLAUDE.md / documentation corrected to state the real minimums (WordPress 6.9+, PHP 8.0+).

= 1.7.4 =
* New: The WordPress MCP Adapter is now bundled with the plugin — no separate adapter plugin install required. On WordPress 6.9+/7.0 (where the Abilities API is in core), Elementor is the only thing you need to install. If a standalone MCP Adapter plugin is active, the plugin automatically defers to it.
* New: "Activate Abilities API for EMCP" toggle on the Connection tab — switch the MCP server on or off for the site (on by default), with a security note that connected AI agents can create, edit, and delete Elementor content when enabled.
* New: Connection tab now shows the MCP Adapter source (bundled vs. external plugin) and the MCP Server enabled/disabled status.
* Changed: Dependency checks no longer require a separately installed MCP Adapter; the bundled copy loads automatically. Only the adapter's runtime source is bundled (it has zero runtime dependencies).

= 1.7.3 =
* New: Industry Skill Packs for the Pro Agent Skill — 10 vertical knowledge files (Dental, Med-Spa, Therapy, Fitness, Automotive, Food & Restaurant, Wedding, Real Estate, Legal, Photography). When the AI agent recognizes the site's industry it reads the matching pack before building and applies that trade's brand voice, SEO keywords, page structure, conversion patterns, compliance notes, and the exact Brand Kit + prompt + template combo.
* New: Skills admin tab now lists the bundled industry packs and explains how the skill auto-routes to the right vertical — no configuration needed.
* Changed: The bundled EMCP Agent Skill gained a vertical-routing section so it loads only the one relevant industry pack (progressive disclosure keeps token cost low). Packs ship in the premium build only.

= 1.7.2 =
* New: Brand Kits Library for Pro subscribers — one-click coordinated color palettes + typography. 16 curated kits across 4 categories (Corporate & Tech, Creative, Hospitality, Trades). Click Apply and the whole site re-skins; back up and restore any time. Auto-synced from the EMCP Tools server with the same 24h cache as Prompts and Templates.
* New: Applying a brand kit replaces the four Elementor system color + typography slots AND sets the kit's Theme Style defaults (default body/heading fonts and body/heading/link colors) so the change is visible site-wide, not just on elements that reference global tokens. Google Fonts load automatically.
* New: Backup-before-apply — current global settings are snapshotted into a private backup before each apply, with a Restore-from-backup section (selective by default, full-clobber option) on the Brand Kits page.
* New: Four Pro-gated MCP tools — list-brand-kits, apply-brand-kit, replace-system-colors, replace-system-typography — so AI clients can browse and apply kits too.
* New: Brand Kits admin tab between Templates and Skills, with category filter pills, self-contained preview cards, an apply-confirmation modal, and a "View site" toast.
* Changed: Admin stats bar shows a Brand Kits count for Pro sites with a synced library.

= 1.7.1 =
* New: Premium Templates library — apply ready-made Elementor designs to a new draft page or import them into Elementor's Saved Templates library. Auto-synced from the EMCP Tools server, category filter + thumbnails. Accepts Elementor's native template export shape.
* New: EMCP Agent Skill download for Pro subscribers — pre-written Anthropic Agent Skill with install guides for Claude Code, Claude Desktop, Cursor, Windsurf, Antigravity. New Skills admin tab.
* New: Global "Upgrade to Pro" admin banner on non-EMCP screens for non-Pro sites. Dismissible per-user.
* New: "Read the Docs" header button in the admin. Upgrade-to-Pro button hidden for active Pro sites.
* Changed: Prompts tab hides the 5 bundled samples for Pro users — premium library supersedes them.
* Changed: Stats-bar prompt count reflects the synced premium library size (e.g. 50) on Pro sites.
* Changed: All in-plugin "Upgrade to Pro" CTAs point at https://emcp.msrbuilds.com/pricing and open in a new tab.
* Changed: Reverted the Freemius pricing-screen wrapper — pricing iframe renders native again.
* Fixed: Premium prompts/templates transient caches and upgrade-banner user-meta scrubbed on uninstall.

= 1.7.0 =
* New: Premium Prompts library is now live for Pro subscribers — 50+ industry-specific landing-page prompts across 10 categories, auto-synced from the EMCP Tools server. Free users continue to see the 5 bundled sample prompts plus an upgrade CTA.
* New: Category filter pills + Sync Library button on the Prompts admin page for Pro users.
* New: "Read the Docs" link in the admin header pointing at the comprehensive docs site at https://emcp.msrbuilds.com/docs.
* New: Two-build distribution — separate free and premium zips so the Freemius account screen labels the install correctly. Paying customers see "Pro version", non-paying customers see "Free version", instead of everyone seeing "Free version" regardless of license.
* Changed: Premium prompts fetcher now sends authentication via Authorization Bearer header instead of URL query parameters. License keys belong in headers, not query strings.
* Changed: Default premium prompts endpoint moved to the dedicated subdomain `https://emcp.msrbuilds.com/api/emcp/prompts.json`.
* Changed: Admin header — removed redundant "Contact Me" button, renamed "Get Premium Prompts" → "Upgrade to Pro" pointing at the Freemius checkout, and hidden for sites with an active Pro license.
* Changed: Pricing-screen "Get in touch" link points at the new EMCP Tools about page.
* Improved: Uniform 403 error handling for the premium prompts endpoint — no info-leak about which auth condition failed.

= 1.6.1 =
* Changed: Uninstall logic moved from `uninstall.php` to the Freemius `after_uninstall` hook so Freemius's own cleanup and ours run in the right order. The `uninstall.php` file has been removed.
* Added: `elementor_mcp_low_tool_mode` and `elementor_mcp_defaults_applied` options are now cleaned up on uninstall (previously missed when those options were added in 1.6.0).
* Added: Branded chrome around the Freemius pricing screen — gradient header matching the EMCP Tools admin pages, feature highlights card above the pricing iframe, and a collapsible FAQ + contact link below it.

= 1.6.0 =
* New: Dedicated **EMCP Tools** top-level admin menu with Tools, Connection, Prompts, and Changelog submenus (previously a single tabbed screen under Settings).
* New: Atomic element tools (Elementor 4.0+) are now listed in the admin Tools screen and can be toggled individually.
* New: **Low-tools mode** — one-click toggle on the Tools screen that filters the registered tool list down to a curated essentials set, keeping the active count under 60 so MCP clients with strict tool caps (Antigravity, Gemini API, etc.) stay under their limits. Your individual toggles are preserved.
* Changed: Pro widget shortcuts are now disabled by default on fresh installs and on the first admin page load after upgrade. Re-enable any of them from the Tools screen.
* Fixed: The "disabled tools" toggles in the admin Tools screen previously had no effect on what MCP clients saw — the filter was only registered in admin context and never fired on REST API requests (#45).
* Fixed: Atomic element tools are now visible in the Tools screen and can be toggled individually (previously missing from the UI).

= 1.5.1 =
* Fixed: Container `justify_content` / `align_items` / `align_content` settings are now remapped to Elementor's prefixed flex keys (`flex_justify_content`, `flex_align_items`, `flex_align_content`) before saving — fixes containers rendering with default alignment on the front-end despite the values being persisted (#32).
* Fixed: Factory auto-center default for column containers now uses the prefixed `flex_align_items` key.
* Improved: Tool descriptions for `add-container` / `update-container` now point to the prefixed flex keys.

= 1.5.0 =
* New: 13 atomic element tools for Elementor 4.0+ — atomic flexbox, div-block, heading, paragraph, button, image, svg, youtube, video, divider, plus universal `add-atomic-widget`, `update-atomic-widget`, and `detect-elementor-version`.
* New: Typed props (`$$type`) handled automatically — AI agents pass simple flat values; styles stored in the separate `styles` map matching Elementor 4.0's data model.
* New: All atomic tools self-guard on Elementor >= 4.0 — zero changes to existing 97 legacy tools.
* Total MCP tools increased from 97 to 110.
* Addresses #28 and #29.

= 1.4.3 =
* New: 5 Pro widget convenience tools — `add-code-highlight`, `add-reviews`, `add-off-canvas`, `add-progress-tracker`, `add-search`.
* Total MCP tools increased from 92 to 97.
* Fixed: Gemini API / Antigravity compatibility — strip empty string values from enum arrays and ensure empty `properties` objects serialize as `{}` (not `[]`). Applied to all 44 ability registrations.
* Fixed: `switcher`, `popover_toggle`, `select`, and `choose` control types no longer emit empty enum values in `get-widget-schema` output.
* Fixed: `get-container-schema` input schema now uses `stdClass` for empty properties (resolves `'allOf' failed - got array, want object`).
* Fixed: Added missing `items` schema to `template_json` array property in `import-template` tool.
* Closes #21.

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

= 1.7.1 =
Premium Templates library + EMCP Agent Skill download go live for Pro subscribers. New Skills and Templates admin tabs. Global upgrade banner on non-EMCP admin screens. All in-plugin Upgrade CTAs now route to the external pricing page on emcp.msrbuilds.com.

= 1.7.0 =
Premium Prompts go live — 50+ landing-page prompts across 10 industries, auto-synced from the EMCP Tools server for Pro subscribers. Authentication moves from query parameters to the Authorization Bearer header so license keys stop showing up in server access logs. New "Read the Docs" link in the admin header points at the new docs site.

= 1.6.1 =
Cleanup-only release: moves the uninstall handler from `uninstall.php` to the Freemius `after_uninstall` hook (required by Freemius), and adds the two options introduced in 1.6.0 to the cleanup list. No behavior changes during normal use.

= 1.6.0 =
Fixes #45 — admin tool toggles now actually filter what MCP clients see. New top-level admin menu with submenus, Low-tools mode for Antigravity/Gemini-friendly tool counts, and Pro widgets now disabled by default to stay under 100-tool client caps.

= 1.5.1 =
Fixes container `justify_content` / `align_items` / `align_content` settings not being applied on the front-end (#32). Recommended for anyone using `add-container`, `update-container`, `update-element`, `batch-update`, or `build-page` to control flex alignment.

= 1.5.0 =
Adds 13 new MCP tools for Elementor 4.0's atomic element system (110 tools total). All atomic tools self-guard on Elementor >= 4.0 with zero changes to the existing 97 legacy tools.

= 1.4.3 =
Adds 5 new Pro widget convenience tools (97 tools total) and fixes Gemini API / Antigravity compatibility — removes empty enum values and adds missing array items schema for non-Claude MCP clients.

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
