# Changelog

All notable changes to MCP Tools for Elementor are documented in this file.

## [1.7.0]

- New: **Premium Prompts library is now live** for Pro subscribers — 50+ industry-specific landing-page prompts across 10 categories (Automotive, Food & Dining, General, Health & Wellness, Home Services, Lifestyle & Entertainment, Pets, Professional Services, Retail, Weddings). Auto-synced from the EMCP Tools server when a valid license is active. Free users continue to see the 5 bundled sample prompts plus an upgrade CTA.
- New: **Category filter pills + Sync Library button** on the Prompts admin page for Pro users. Click a category to narrow the grid; click Sync Library to force-refresh the 24-hour transient cache.
- Changed: Premium prompts fetcher now sends authentication via the `Authorization: Bearer` HTTP header (with site URL and plugin version in `X-EMCP-Site` / `X-EMCP-Plugin-Version`) instead of as URL query parameters. License keys are credentials and shouldn't be in query strings that intermediate proxies and access logs preserve.
- Changed: Default premium prompts endpoint moved from `https://msrbuilds.com/api/emcp/prompts.json` to the dedicated subdomain `https://emcp.msrbuilds.com/api/emcp/prompts.json`. Filterable via `elementor_mcp_pro_prompts_endpoint`.
- Changed: Plugin admin header — "Contact Me" now points to the dedicated EMCP Tools about page, and a new "Read the Docs" link sits between Watch Tutorial and Contact Me, pointing at the comprehensive docs site at https://emcp.msrbuilds.com/docs.
- Changed: Pricing screen "Get in touch" link points at the new About page rather than the generic MSR Builds contact form.
- Improved: Conservative error handling for the premium prompts endpoint — the server now returns a uniform `403 { "error": "forbidden" }` for every auth failure (no info-leak about whether the license was missing, expired, or used from an unauthorized site), and the plugin maps that to a single "Premium Prompts unavailable — confirm your license" message. `429 Too Many Requests` is mapped to a separate "try again in a few minutes" message.

## [1.6.1]

- Changed: Uninstall logic moved from `uninstall.php` to the Freemius `after_uninstall` hook so Freemius's own uninstall flow and ours run in the right order. Required by Freemius's plugin validator. The `uninstall.php` file has been removed.
- Added: `elementor_mcp_low_tool_mode` and `elementor_mcp_defaults_applied` are now deleted on uninstall (previously missed when those options were added in 1.6.0).
- Added: Branded chrome around the Freemius pricing screen via Freemius's `templates/pricing.php` filter — gradient header that matches the EMCP Tools admin pages, a feature highlights card above the pricing iframe, and a collapsible FAQ + contact link below it. The pricing iframe itself is cross-origin and remains styled by Freemius.

## [1.6.0]

- New: Dedicated **EMCP Tools** top-level admin menu with Tools, Connection, Prompts, and Changelog as native WordPress submenus (replaces the single tabbed screen under Settings).
- New: Atomic element tools (Elementor 4.0+) — `detect-elementor-version`, `add-flexbox`, `add-div-block`, `add-atomic-widget`, `update-atomic-widget`, plus 8 atomic widget convenience shortcuts — are now listed in the admin Tools screen under Atomic Layout / Atomic Widgets categories and can be toggled individually.
- New: **Low-tools mode** toggle on the Tools screen. When on, the registered tool list is filtered down to a curated 51-slug essentials set (46 without Elementor 4.0+) so MCP clients with strict tool caps (Antigravity's 100-tool limit, Gemini API, etc.) stay under their cap without losing the universal `add-widget` / `update-widget` / `build-page` capabilities. Individual toggles are preserved when the mode is off.
- Changed: Pro widget shortcuts are now disabled by default on fresh installs and on the first admin page load after upgrade — fixes the over-100-tool problem from #45 for users with Elementor Pro active. Existing user choices are preserved by merging (union) rather than overwriting. Re-enable any Pro widget from the Tools screen.
- Fixed: The "disabled tools" toggles previously had no effect on what MCP clients saw. The `elementor_mcp_ability_names` filter was registered inside the admin class, which only loaded in `is_admin()` context, so REST API requests (the path MCP clients use) never saw the filter and exposed every registered tool regardless of what the user disabled. The filter now lives in the always-loaded plugin class. Closes #45.
- Fixed: Atomic element tools are now discoverable and toggleable in the admin Tools screen. Previously they registered with the MCP server but were missing from `get_all_tools()`, so they could not be turned off from the UI.

## [1.5.1]

- Fix: Container `justify_content` / `align_items` / `align_content` settings are now remapped to Elementor's prefixed flex keys (`flex_justify_content`, `flex_align_items`, `flex_align_content`) before saving. Without the remap, Elementor's CSS generator never emitted the corresponding `--justify-content` / `--align-items` custom properties and containers rendered with default alignment on the front-end (#32).
- Fix: Factory auto-center default for column containers now uses the prefixed `flex_align_items` key.
- Improved: Tool descriptions for `add-container` / `update-container` now point to the prefixed flex keys, while still accepting the unprefixed shorthand for backward compatibility.

## [1.5.0]

- New: 13 atomic element tools for Elementor 4.0+ — `add-flexbox`, `add-div-block`, `add-atomic-heading`, `add-atomic-paragraph`, `add-atomic-button`, `add-atomic-image`, `add-atomic-svg`, `add-atomic-youtube`, `add-atomic-video`, `add-atomic-divider`, `add-atomic-widget`, `update-atomic-widget`, `detect-elementor-version`.
- New: Typed props (`$$type`) handled automatically — AI agents pass simple flat values; styles persisted in the separate `styles` map matching Elementor 4.0's data model.
- New: All atomic tools self-guard on Elementor >= 4.0 — zero changes to existing 97 legacy tools (#28, #29).
- Total MCP tools increased from 97 to 110.

## [1.4.3]

- New: 5 Pro widget convenience tools — `add-code-highlight`, `add-reviews`, `add-off-canvas`, `add-progress-tracker`, `add-search`.
- Total MCP tools increased from 92 to 97.
- Fix: Gemini API / Antigravity compatibility — new `elementor_mcp_sanitize_schema()` helper strips empty string values from `enum` arrays and ensures empty `properties` objects serialize as `{}` (not `[]`). Applied to all 44 ability registrations via the `elementor_mcp_register_ability()` wrapper.
- Fix: Control mapper hardening — `switcher`, `popover_toggle`, `select`, and `choose` controls no longer emit empty enum values in `get-widget-schema` output.
- Fix: `get-container-schema` input schema now uses `stdClass` for empty properties (resolves `'allOf' failed - got array, want object`).
- Fix: `import-template` tool now declares the missing `items` schema for the `template_json` array property.
- Closes #21.

## [1.4.2]

- Fix: Add missing `items` property to all `array` type JSON Schema definitions across 6 ability files (12 instances). VS Code and other strict MCP clients reject tools with invalid schemas, causing "tool parameters array type must have items" errors (#6).

## [1.4.1]

- Fix: Node.js proxy now supports `MCP_PROTOCOL_VERSION` env var to override the protocol version in initialize responses, working around upstream MCP Adapter hardcoding `2025-06-18` which some clients don't support (#4).
- Improved: Proxy now logs server info, protocol version, and discovered tools count for easier diagnostics.
- Improved: Proxy logs full response bodies to file (not stderr) when `MCP_LOG_FILE` is set.
- Improved: Expanded troubleshooting section in README with protocol version mismatch diagnosis, debug logging instructions, and session management guidance.
- Improved: Added Node.js proxy connection section to README with environment variable documentation.
- Improved: Added proxy config example with `MCP_PROTOCOL_VERSION` to `mcp-config-examples.json`.
- New: Connection tab now auto-generates Node.js proxy configs (recommended) with auto-detected filesystem path, alongside existing HTTP configs.

## [1.4.0]

- New: 22 Pro widget convenience tools — nav menu, loop grid, loop carousel, media carousel, nested tabs, nested accordion, and more.
- New: 5 WooCommerce widget tools — products, add-to-cart, cart, checkout, menu cart (conditional on WooCommerce).
- New: 4 layout tools — update-container, update-element, batch-update, reorder-elements.
- New: 6 template/theme builder tools — create-theme-template, set-template-conditions, list-dynamic-tags, set-dynamic-tag, create-popup, set-popup-settings.
- New: 2 query tools — get-container-schema, find-element.
- New: 4 extended core widget tools — menu-anchor, shortcode, rating, text-path.
- Total MCP tools increased from 70 to 92.
- Improved: Settings validator with stricter schema enforcement.
- Improved: Element factory with enhanced container support.

## [1.3.2]

- Renamed plugin to "MCP Tools for Elementor" to comply with WordPress.org trademark guidelines.
- Updated admin menu label to "EMCP Tools" for brevity.
- Fixed WPCS issues: prefixed all global variables in view templates, escaped integer output, added missing translators comments.
- Updated "Tested up to" to WordPress 6.9.
- Added languages/ directory for Domain Path header.

## [1.3.1]

- New: Prompts tab in admin dashboard — browse and one-click copy 5 sample landing page prompts.
- New: Contributing Prompts guide in CONTRIBUTING.md with structure, guidelines, and submission steps.
- Improved: Admin CSS for prompt card grid with hover effects and responsive breakpoints.

## [1.3.0]

- New: `add-custom-css` tool — add custom CSS to any element or page-level with `selector` keyword support (Pro only).
- New: `add-custom-js` tool — inject JavaScript via HTML widget with automatic `<script>` wrapping and optional DOMContentLoaded wrapper.
- New: `add-code-snippet` tool — create site-wide Custom Code snippets for head/body injection with priority and jQuery support (Pro only).
- New: `list-code-snippets` tool — list all Custom Code snippets with location, priority, and status filters (Pro only).
- Total tools increased from ~64 to ~68.

## [1.2.3]

- Fix: Factory now strips `flex_wrap` and `_flex_size` from container settings — prevents AI agents from setting these values that cause layout overflow.
- Fix: Tool descriptions now include background color instructions (`background_background=classic`, `background_color=#hex`) so AI agents apply colors correctly.
- Improved: Stronger "NEVER set flex_wrap" guidance in build-page and add-container tool descriptions.

## [1.2.2]

- Fix: Row container children now use `content_width: full` with percentage widths (e.g. 25% for 4 columns) matching Elementor's native column layout pattern.
- Fix: Removed all `flex_wrap` and `_flex_size` auto-overrides from factory and build-page — Elementor defaults handle layout correctly.
- Improved: Tool descriptions updated with correct multi-column layout guidance.

## [1.2.1]

- Fix: Row containers now use `flex_wrap: wrap` instead of `nowrap` to prevent children from overflowing.
- Fix: `build-page` auto-sets percentage widths on row children (e.g. 50% for 2 columns, 33.33% for 3) instead of using `_flex_size: grow` which caused layout overflow.
- Improved: Tool descriptions updated with correct layout guidance for multi-column layouts.

## [1.2.0]

- New: 14 free widget convenience tools — accordion, alert, counter, Google Maps, icon list, image box, image carousel, progress bar, social icons, star rating, tabs, testimonial, toggle, HTML.
- New: 10 Pro widget convenience tools — call to action, slides, testimonial carousel, price list, gallery, share buttons, table of contents, blockquote, Lottie animation, hotspot.
- Total widget tools increased from 17 to 41 (~64 MCP tools overall).

## [1.1.1]

- Fix: Container flex layout — row children auto-grow with `_flex_size: grow` for equal distribution.
- Fix: Column containers auto-center content horizontally (`align_items: center`).
- Fix: Row containers auto-set `flex_wrap: nowrap` to prevent wrapping.
- Fix: `_flex_size` now correctly uses string value (`grow`) instead of array — prevents fatal error in Elementor CSS generator.
- Fix: `get-global-settings` input schema uses `stdClass` for empty properties to serialize as JSON `{}` instead of `[]`.
- New: Connection tab configs for Cursor, Windsurf, and Antigravity IDE clients.
- New: 3 stock image tools — `search-images`, `sideload-image`, `add-stock-image` (Openverse API).
- New: SVG icon tool — `add-svg-icon` for custom SVG icons.
- Improved: `build-page` description with detailed layout rules for row/column containers.
- Improved: Admin connection tab streamlined — removed WP-CLI local section, unified HTTP config workflow.

## [1.0.0]

- Initial release.
- 7 read-only query/discovery tools.
- 5 page management tools (create, update settings, delete content, import, export).
- 4 layout tools (add container, move, remove, duplicate elements).
- 2 universal widget tools (add-widget, update-widget).
- 9 core widget convenience shortcuts.
- 6 Pro widget convenience shortcuts (conditional on Elementor Pro).
- 2 template tools (save as template, apply template).
- 2 global settings tools (colors, typography).
- 1 composite build-page tool.
- Admin settings page with tool toggles and connection info.
- Node.js HTTP proxy for remote connections.
