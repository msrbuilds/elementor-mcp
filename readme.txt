=== MCP Tools for Elementor ===
Contributors: mianshahzadraza
Tags: elementor, mcp, ai, page-builder, automation
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 3.3.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extends the WordPress MCP Adapter to expose Elementor data, widgets, and page design tools as MCP tools for AI agents.

== Description ==

MCP Tools for Elementor bridges the gap between AI tools and Elementor page design. It extends the official WordPress MCP Adapter to expose a focused set of MCP (Model Context Protocol) tools that let AI agents like Claude, Cursor, and other MCP-compatible clients create and manipulate Elementor page designs programmatically.

As of v3.0.0 the 62 per-widget tools were folded into a catalog-backed model, so the active tool surface is much smaller while every widget stays reachable, and the toolset takes its first steps beyond Elementor with general WordPress content management, curated site-settings control, full plugin and theme management, media attachment management, safe user management, filesystem access, and database inspection over MCP. The v3.0.0 beyond-Elementor surface adds 8 WordPress Content tools + 3 surfaced WordPress core abilities + 2 WordPress Settings tools + 13 Plugins & Themes tools + 3 Media Library tools + 4 Users tools + 1 Performance Analyzer tool + 1 Security & Malware Scanner tool + 6 Filesystem tools + 6 Database tools. Tool counts scale with your environment (registered counts, verified on Elementor 4.1.4): around 107 tools on a free Elementor install, ~121 with the Elementor 4.0+ atomic elements, ~117 with Elementor Pro, and ~131 with Pro + Elementor 4.0+ (WooCommerce adds no new tools — its widgets are reached through add-pro-widget). When Advanced Custom Fields (free or PRO) is active, 2 ACF tools register on top of any of those counts — `acf-read` and `acf-write`, two dispatchers that expose 15 ACF operations (8 read, 7 write; the Custom Post Type and taxonomy operations need ACF 6.1+). Each dispatcher is a single toggle under Tools → Plugins → ACF, listing the operations it covers; `acf-read` is on by default and `acf-write` ships off. About 39 other tools ship disabled-by-default (SEO & Accessibility, Widget Builder, PHP Snippets, the 9 Plugins & Themes write tools, delete-media, the 2 Users write tools, the 3 Filesystem write tools, and the 3 Database write tools), so the typical active surface is smaller.

**Key Features:**

* **Query & Discovery** — List widgets, inspect page structures, read element settings, browse templates, and view global design tokens.
* **WordPress Content (beyond Elementor)** — Create and manage posts, pages, and any custom post type — content, status, taxonomy terms, custom fields, and featured images — via MCP, without touching Elementor data. Built on WP core; every post carries an `is_elementor` flag that steers agents to the Elementor tools for builder pages. (v3.0.0)
* **WordPress Settings (beyond Elementor)** — Read and batch-update core WordPress settings (general/reading/writing/discussion/media/permalinks) over MCP. Curated allowlist only — no arbitrary option access; `admin_email` is read-only; permalink changes auto-flush rewrite rules. `manage_options`. (v3.0.0)
* **Plugins & Themes (beyond Elementor)** — Discover, install (wordpress.org only), update, activate/deactivate, and delete plugins and themes over MCP. EMCP Tools and Elementor are protected; install/update/delete are disabled-by-default and per-op capability-gated. (v3.0.0)
* **Media Library (beyond Elementor)** — Fetch full attachment detail, edit metadata (alt text, title, caption, description), and delete attachments over MCP. get/update are enabled by default; delete is disabled-by-default and requires explicit confirmation. (v3.0.0)
* **Users (beyond Elementor)** — List and read WordPress users, and safely create/edit non-admin profiles over MCP. No delete and no role changes; new users get an auto-generated password by email (never returned); administrators are off-limits to editing. Reads enabled by default; create/update disabled-by-default. (v3.0.0)
* **ACF / ACF PRO (beyond Elementor)** — Read and write Advanced Custom Fields values on posts and options pages, discover and author field groups, and register ACF-managed Custom Post Types and taxonomies (ACF 6.1+) — enough to build a full content structure end-to-end. Full Pro field support (repeater, flexible content, gallery, group, clone) as nested JSON. Only registers when ACF is active; the 7 write tools ship disabled-by-default; no deletes and no slug/field renames. (v3.2.1)
* **Performance Analyzer (beyond Elementor)** — Scan the server config, WordPress internals (database size, autoloaded options, post revisions, cron backlog, object cache, OPcache, plugin count), and a target page (defaults to the frontpage; optional URL or post) for performance bottlenecks. Returns a scored report (0-100 + A–F grade) with severity-tagged findings and ranked recommendations. Read-only, self-contained (no external API), enabled by default. (v3.0.0)
* **Security & Malware Scanner (beyond Elementor)** — The security counterpart to the Performance Analyzer: scan across malware heuristics (eval/base64 obfuscation, request-driven execution, command execution, webshells, encoded blobs, executable PHP under uploads), WordPress core-file integrity (against the official wordpress.org checksums), hardening (file editing, debug output, admin username, XML-RPC, version disclosure, HTTPS, security headers), and outdated/abandoned software. Returns a scored report (0-100 + A–F grade) with severity-tagged findings and ranked recommendations. The malware walk is bounded and never returns full file contents (path:line + snippet only). Read-only, self-contained (only wordpress.org calls, graceful offline), enabled by default. (v3.0.0)
* **Filesystem (beyond Elementor)** — read/scan any file in the WordPress install; modify/delete off by default. (v3.0.0)
* **Database (beyond Elementor)** -- flexible read-only SQL plus safe structured writes (off by default). (v3.0.0)
* **Page Management** — Create pages, update page settings, clear content, import/export templates.
* **Layout Tools** — Add flexbox containers, move/remove/duplicate elements, batch updates, reorder children.
* **Widget Tools** — A catalog-backed model: list-widgets (filter by tier/category/search) -> get-widget-schema (curated params, batch, or full raw schema) -> add-free-widget / add-pro-widget (with Pro) -> update-widget. The 62 widgets' curated params live in a built-in catalog (27 free / 30 Pro / 5 WooCommerce), so every widget and parameter stays reachable while the per-turn tool-list cost drops ~10x.
* **Pro Widget Support** — Conditional tools for Elementor Pro widgets (form, posts grid, countdown, price table, flip box, animated headline, call to action, slides, testimonial carousel, price list, gallery, share buttons, table of contents, blockquote, Lottie, hotspot, loop grid/carousel, nested tabs/accordion, portfolio, author box, login, code highlight, reviews, off-canvas, progress tracker, search, and more) that only register when Pro is active.
* **Atomic Elements (Elementor 4.0+)** — 13 dedicated tools for Elementor's new atomic system: flexbox, div-block, heading, paragraph, button, image, svg, youtube, video, divider, plus universal `add-atomic-widget` / `update-atomic-widget` and `detect-elementor-version`.
* **Template Tools** — Save pages or elements as reusable templates, apply templates to pages, theme builder, popups, dynamic tags (Pro).
* **Global Settings** — Update site-wide color palettes and typography presets.
* **Composite Tools** — Build a complete page from a declarative JSON structure in a single call.
* **Stock & Media Images** — Search Openverse for Creative Commons images, sideload into the Media Library, add to pages — plus `list-media` to discover and search the site's own existing uploads (by title, alt text, caption, and description).
* **SVG Icons** — Upload SVG icons from URL or raw markup for use with Elementor icon widgets.
* **Custom Code** — Add custom CSS (element/page level), inject JavaScript, create site-wide code snippets for head/body injection.
* **AI Widget Builder (Pro)** — Let an AI agent design custom Elementor widgets from a structured spec (no hand-written PHP). The plugin compiles the spec into a sandboxed widget that appears in the Elementor panel — 35 control types, optional CSS/JS, with a runtime safety net so a bad widget can never break the editor.
* **Brand Kits** — One-click color + typography kits that re-skin your whole site. 10 kits are free to apply (with backup + restore); 50+ with Pro.
* **Low-tools Mode** — One-click toggle that trims the active tool list to a curated essentials set for MCP clients with strict tool caps (Antigravity, Gemini API, etc.). After the v3.0.0 widget consolidation the active count already fits most caps, so this is rarely needed now.
* **Sample Prompts** — Ready-to-use landing page blueprints with one-click copy from the admin dashboard.
* **Admin Dashboard** — Dedicated top-level menu with Tools, Connection, Prompts, Templates, Brand Kits, Skills, Widget Builder, and Changelog tabs. Toggle individual tools on/off, view connection configs for all supported MCP clients, and get help via the built-in Get Support link.

**Requires:**

* WordPress 6.9 or later
* WordPress Abilities API — included in WordPress core 6.9+ (and 7.0)
* WordPress MCP Adapter — bundled with the plugin (no separate install needed; an active standalone MCP Adapter plugin is used instead when present)

**Recommended (optional):**

* Elementor 3.20 or later — enables the full Elementor tool family (query, pages, layout, widgets, templates, globals, composite, stock images, SVG icons, custom code, atomic elements, global classes, brand kits, widget builder, SEO/A11y). The plugin and all beyond-Elementor tools work without it; the admin shows a warning when Elementor is not active.

**Connection Methods:**

* WP-CLI stdio (recommended for local development)
* Node.js HTTP proxy (for remote sites)
* Direct HTTP (for VS Code MCP extension)

== Installation ==

1. Upload the `emcp-tools` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress. The MCP Adapter is bundled — no separate install is required (WordPress 6.9+/7.0 already includes the Abilities API).
3. Open the new **EMCP Tools** top-level menu, go to the **Connection** tab, and confirm **Activate Abilities API for EMCP** is enabled (on by default) to expose the MCP server.
4. (Optional) Install and activate [Elementor](https://wordpress.org/plugins/elementor/) (version 3.20+) to enable the Elementor tool family (page design, widgets, layout, templates, brand kits, and more). All beyond-Elementor tools are fully functional without it.

= WP-CLI Connection (Local) =

Add to your MCP client configuration:

`
{
  "mcpServers": {
    "emcp-tools": {
      "command": "wp",
      "args": ["mcp-adapter", "serve", "--server=emcp-tools-server", "--user=admin", "--path=/path/to/wordpress"]
    }
  }
}
`

= Codex Connection =

Add to `~/.codex/config.toml` or `.codex/config.toml`:

`
[mcp_servers.elementor-mcp]
url = "https://your-site.com/wp-json/mcp/emcp-tools-server"

[mcp_servers.elementor-mcp.http_headers]
"Authorization" = "Basic BASE64_ENCODED_CREDENTIALS"
`

= npx mcp-remote Connection (Local) =

For local development, use `mcp-remote` to bridge your AI client to the WordPress HTTP endpoint:

`
{
  "mcpServers": {
    "emcp-tools": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "http://localhost:10003/wp-json/mcp/emcp-tools-server",
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
    "emcp-tools": {
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

Yes. The free/core widgets are added via `add-free-widget` and work with free Elementor. The `add-pro-widget` tool (which covers Elementor Pro and WooCommerce widgets) only registers when Elementor Pro is active.

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

= 3.3.0 =
Foundation release: understand a page from one call, undo any change, and reuse existing content.
* Added: get-page-snapshot (always-on, read-only) returns one normalized digest of a page — structure tree + counts, global colors/typography/classes actually in use, per-device responsive overrides, content outline, and an SEO-lite summary — so an AI agent can reason about a page from a single call. Opt-in performance/accessibility/SEO audit summaries via include:[performance,a11y,seo] (a11y/seo are Pro); heavy sections are transient-cached.
* Added: AI-safe transactions — a unified change ledger + rollback (list-changes / get-change / rollback-change). Every AI-made Elementor edit, filesystem write, and database write is recorded, and any recorded change can be undone (restore a page's prior data, restore/remove a file from its backup, or inverse a database write from its before-image). Requires manage_options; the ledger is capped by count and size.
* Added: Content search (search-content / reindex-search) — find the site's own pages, templates, widgets, and global styles by natural-language query so an AI agent can reuse existing content instead of rebuilding it. Lexical, field-weighted ranking over a materialized index that also updates when pages/templates are saved.

= 3.2.1 =
Atomic-element write fixes + a new ACF / ACF PRO integration.
* Fixed: add-atomic-image / add-atomic-svg no longer reject a valid image_id / image_url — the image envelope now matches Elementor's Image_Src prop type (id XOR url, image-attachment-id), and e-svg uses its own svg-src prop (#74).
* Fixed: an atomic element's local styles map and editor_settings (Navigator label) are now writable — update-element / batch-update / update-atomic-widget hoist those sibling-root keys to the element root instead of silently dropping them into settings (#72, #73).
* Added: set-element-label — a convenience tool for renaming an element in the Navigator (editor_settings.title).
* Added: ACF / ACF PRO integration exposed as 2 dispatcher tools — acf-read and acf-write — that register only when Advanced Custom Fields (free or PRO) is active. Call either with no operation to list its operations, then again with { operation, arguments }. 15 operations in total: 8 read (field groups, field values, options pages, and — on ACF 6.1+ — ACF-managed post types and taxonomies) and 7 write (field values, field groups, and ACF 6.1+ post types / taxonomies). acf-read is enabled by default; acf-write ships disabled-by-default. Each dispatcher is a single toggle under Tools → Plugins → ACF (listing its operations), and every operation keeps its original capability check (enforced per call by the dispatcher).
* Added: full ACF PRO field support — repeaters, flexible content (rows validated against the field's layouts), galleries, groups, and clones round-trip as nested JSON; options pages are targetable via options_page. Pro-only features degrade gracefully on free ACF.
* Added: ACF-managed Custom Post Types and taxonomies are registered as data through ACF (acf_import_post_type / acf_import_taxonomy) — no PHP is written or executed. Together with the field operations this is enough to build a full content structure end-to-end: CPT to taxonomy to field group to posts with values.
* Added: conservative authoring guardrails — no delete operations; fields can never be removed; a field's name/key/type, or a post type / taxonomy slug, can never change (renames orphan stored content); code-registered (acf-json/PHP) groups are refused by the group editor; the CPT/taxonomy read operations list only the ACF-managed ones.
* Added: a new "Plugins" sub-tab on the Tools screen, with an "ACF (Advanced Custom Fields)" section showing the two dispatcher toggles (acf-read on, acf-write off), each listing the operations it covers.
* Added: set-social-image (Pro SEO) — sets the Open Graph + Twitter/X share image for a page via the active SEO plugin's meta (Yoast: opengraph-image(-id)/twitter-image(-id); Rank Math: facebook/twitter image(_id)), so link previews use the image you choose instead of the first content image Yoast otherwise keeps. Disabled-by-default with the SEO toolkit. (#82)
* Added: a full_bleed flag on add-container that seeds an edge-to-edge container recipe (full content width, 100% width, zero padding, zero flex/gap, column + stretch) — the correct top-level container for Elementor Canvas pages, where the boxed defaults leave white strips. Explicit settings still override the preset. (#83)
* Fixed: list-posts now accepts array post_type / status (multi-type and multi-status queries) instead of stringifying arrays to "Array" and silently returning wrong or unfiltered results. (#80)
* Fixed: get-post now returns protected meta keys a site opts into via the emcp_tools_content_allowed_protected_meta filter, matching create/update-post (read-after-write no longer broken). (#77)

= 3.2.0 =
A core-engine release, and a rewrite of the Premium Prompts.
* Added: all 50 Premium Prompts rewritten. Instead of dictating a section-by-section Elementor layout, each prompt now gives the AI a style guide, a design direction, the exact content, and hard standards — WCAG 2.1 AA, real photography in every slot, one consistent SVG icon set, builder-native construction, a completeness rule, and a lead-capture form — then lets it design the page. Expect more distinctive results.
* Added: the prompts are builder-agnostic. Change the first line of any prompt from Elementor to Gutenberg, Bricks, or plain HTML/CSS and the rest still applies.
* Added (Pro): "Download v1 Prompts" on the Prompts screen — the original 50 prompts as an archive, for anyone who prefers the older, prescriptive style. License-, capability- and nonce-gated.
* Added (Pro): web_fetch in the AI Chat — the model can read a URL you give it (a reference design, a competitor page, docs, a JSON endpoint, or a stylesheet). Fetching a page also returns the absolute URLs of its stylesheets, so the AI can read a design's colours and type scale from source. It cannot search the web; it needs a URL. Works in the Chat tab and both editor panels.
* Security: web_fetch runs on your server, so every URL and every redirect hop is validated first — http(s) only, no credentials in the URL, ports 80/443 only, and refused if any resolved address is loopback, private, link-local (including the cloud-metadata address), CGNAT, multicast or reserved. Responses are size-capped, limited to text, never executed, and handed to the model labelled as untrusted data.
* Fixed: updated the bundled Freemius SDK from 2.13.3 to 2.13.4. Freemius withdrew 2.13.3 after finding a regression in its add-on checkout flow. This plugin does not use Freemius add-ons, so it was never affected — but it should not ship a version the vendor unpublished.
* Fixed: plugin scripts and styles are now versioned by their own file modification time, so a changed asset is never served stale from the browser cache between releases.
* Changed: the Prompts screen shows a one-time, per-user notice explaining the rewritten prompts.
* Added: Compact tool mode (opt-in Tools-tab toggle) — surfaces 3 dispatcher meta-tools (list-tools / get-tool-schema / call-tool) instead of ~140 individual tools, so clients that cap tool counts can still reach the whole surface. call-tool delegates each tool's own permission check; your per-tool toggles still apply. Replaces the old Low-tools mode.
* Added: richer discovery context — the server description now includes a compact environment summary (WordPress/PHP/Elementor versions, atomic-element support, notable active plugins) so agents orient without extra calls.
* Added (Pro): Agent-facing Skills — the bundled Agent Skills are now discoverable and loadable by any connected MCP agent at runtime via two read-only tools, list-skills and get-skill, plus a Skills catalog in the discovery context. The bundle grows to 7 skills (Elementor page building, Gutenberg, EMCP Themer, performance, security, SEO & accessibility, PHP snippets). The Skills page now shows two ways to use them — install locally or load at runtime.
* Added (Pro): Agent Skills module (Modules tab, on by default) — the on/off switch for that runtime exposure; turn it off to remove the injection (the local Skills download is unaffected).
* Changed: the bundled MCP Adapter is now a Composer dependency loaded via the Automattic Jetpack Autoloader, which arbitrates the highest adapter version process-wide when multiple active plugins bundle it (WooCommerce, Automattic MCP) — no "class already declared" clashes regardless of load order.
* Changed: removed Low-tools mode (superseded by Compact tool mode).
* Fixed: a clean install no longer emits a "Failed to open stream" notice from the autoloader's file map (regenerated without dev dependencies); trimmed non-runtime files from the shipped vendor bundle.

= 3.1.3 =
A bug-fix patch (thanks @gthibo for two detailed reports).
* Fixed: the "update available" notice no longer persists after you update the plugin. The free GitHub updater compared against the compiled version constant, which is stale in the same request right after a self-update; it now uses the version WordPress parsed from the plugin header and clears the update transient immediately.
* Fixed: MCP tool-name collision — the EMCP Themer and Elementor Pro Theme Builder tools both claimed create-theme-template / set-template-conditions, so one was silently dropped. The Elementor Pro tools are renamed to create-elementor-theme-template / set-elementor-template-conditions; the Themer keeps the originals. (#71)
* Fixed: debug-log noise ("Ability ... not found") from the admin tool-catalog drift check on environment-gated tools (resize-media, Themer PHP). Now uses a silent registry check. (#71)
* Fixed: the Node proxy (@msrbuilds/emcp-proxy) corrupted non-ASCII text on large responses (multi-byte characters split across network chunks). Now buffers and decodes once. Update with npx @msrbuilds/emcp-proxy@latest (>= 1.8.4). (#70)

= 3.1.2 =
A bug-fix patch for three community-reported issues (thanks @Mrshahidali420).
* Fixed: MCP endpoint 404 when WooCommerce 10.5+ is active — WooCommerce autoloads the same MCP Adapter but only boots it behind its own off-by-default flag; EMCP now boots the already-loaded adapter itself so /wp-json/mcp/emcp-tools-server works regardless. (#64)
* Fixed: move-block could silently delete a block when moving it inside a later container or across levels (the target index shifted on removal). The index compensation now covers every mode/depth, and a move into the block's own subtree is a no-op. (#67)
* Fixed: write-file/edit-file/delete-file now invalidate OPcache after writing PHP, so edits take effect immediately on hosts with opcache.validate_timestamps=0 instead of running stale bytecode. (#66)

= 3.1.1 =
A follow-up patch to 3.1.0.
* Added: Resize images over MCP — the Image Optimization module adds an in-place image resizer and a resize-media tool (scale to fit, or crop:true for exact width x height); the original is backed up (reversible) and all sub-sizes + WebP are regenerated. Registers only when the Image Optimization module is enabled.
* Added: Themer PHP Templates review screen is now a full editor — CodeMirror (PHP syntax highlighting) plus Title and Type fields; saving re-validates and recompiles an attached template.
* Added: Codex connection help — a field-by-field guide for Codex's "Custom MCP" form, and a Node-proxy (npx) config.toml option (via @msrbuilds/emcp-proxy) alongside the streamable-HTTP config.
* Added: EMCP Themer now shows a Free-limits banner (free installs only) on the templates screen — a per-type usage chip for each slot (Header/Footer/Single/Archive/Search/404, used/cap) so the 1-per-type limit is visible before you hit it, with a one-click Upgrade to Pro for unlimited templates and granular conditions.
* Changed: Generated MCP client configs now name the server after the site's domain (e.g. emcp-your-site-com) instead of a fixed "emcp-tools", so connecting several sites in one AI client no longer collides.
* Changed: Bundled Freemius SDK updated 2.13.2 -> 2.13.3; optimized an oversized bundled admin logo (~700KB smaller download).
* Changed: Modules now appears before Tools in both the EMCP Tools sidebar submenu and the page header nav.
* Fixed: Changelog page no longer errors ("Sorry, you are not allowed to access this page." / "Cannot load...") — removing it from the sidebar had broken both its access check and its render hook; it is now kept as a normal submenu (renders + reachable by URL) with only its sidebar row hidden via CSS.
* Fixed: Codex config.toml now uses http_headers (not headers) so the generated config connects.
* Fixed: Themer "Render with PHP template" dropdown updates live when the template type changes (new templates no longer stay on "choose a type first").

= 3.1.0 =
A big feature release: a builder-agnostic theme builder (EMCP Themer), a pluggable Modules framework (with Image Optimization), 10 always-on Gutenberg block tools, an in-editor AI Chat panel for the block editor, a dedicated plugin Dashboard, and in-dashboard updates for free users via GitHub releases.
* Added: EMCP Themer — a builder-agnostic theme builder (free, on by default). Design Header / Footer / Single / Archive / Search / 404 layouts with any page builder (Gutenberg, Elementor, ...), attach display conditions, and the plugin injects them on the front end. One CPT with its own dashboard menu; a slot-based resolver (header/body/footer) picks one winner per slot by condition specificity, priority, then newest. An Elementor-style step-wise condition builder drives the metabox and warns on overlapping same-type templates. Free = the whole engine + 1 template per type + broad scope selectors + all 8 MCP tools. Per-theme header/footer adapters for Astra/GeneratePress/Kadence/OceanWP/Blocksy/Neve/Hello. Dynamic content elements (Post/Archive Title, Breadcrumbs, Post Meta, Site Logo/Title, Menu, Description, Post Content, Archive Posts) ship for both builders.
* Added: EMCP Themer PHP Templates (free, off by default). A connected AI agent can author a fully custom PHP region template (header/footer/single/archive) into a validated, hash-verified sandbox; you then select it on a template to take over that region's render. The human selection is the execution gate — an AI can only create drafts, and there is deliberately no attach tool. The strict validator rejects code execution, file loading, network, and file writes. Gated behind a Tools-tab master switch; adds 5 disabled-by-default MCP tools and a PHP Templates review screen.
* Added: Gutenberg block tools (10, always-on). A block-editor counterpart to the Elementor family, pure WordPress core: list-blocks, get-block-schema, get-post-blocks, list-patterns, add-block, update-block, remove-block, move-block, duplicate-block, insert-pattern. All enabled by default (only remove-block badged destructive).
* Added: Modules framework — a pluggable feature system an admin turns on/off from a new Modules tab. Ships with an Image Optimization module (free, opt-in): compresses image sub-sizes on upload and generates .webp siblings via WP_Image_Editor (no external binaries), serves WebP in REST/CLI + optionally on the front end, preserves originals (reversible), plus a resumable bulk optimizer + restore. Prompts, Brand Kits, and Templates are modules too.
* Added: AI Chat in the block editor (Pro). The AI Chat editor panel now runs in the Gutenberg editor as well as the Elementor editor. AI Chat is now a proper module (a true on/off kill switch).
* Added: Plugin Dashboard — a dedicated landing screen with headline stat cards, an "Explore your toolkit" grid, featured video guides, a Help & resources panel, and a version / update-available indicator.
* Added: In-dashboard updates for free users via GitHub releases — free installs now get native "update available" prompts (and auto-updates). Premium builds continue to update via Freemius.
* Added: Admin-bar MCP status node (green/grey/red) with a one-click toggle to enable/disable MCP exposure.
* Added: Self-hosted Geist font for the plugin admin headings and accents (SIL OFL, no third-party CDN).
* Changed: The admin now opens on the new Dashboard tab; Tools moves to its own tab.
* Fixed: Themer body templates now fill the theme's content column on flexbox-container themes (e.g. Astra's two-container layout) instead of leaving empty space beside the content.

= 3.0.0 =
The first major release of the rebranded EMCP Tools — a step beyond Elementor into general WordPress management, plus a leaner catalog-backed widget surface. This single 3.0.0 release bundles the MCP namespace rename, the widget consolidation, and beyond-Elementor domains: WordPress Content (domain 1), Settings (domain 2), Plugins & Themes (domain 3), Media Library (domain 4), Users (domain 5), Performance & Security (domain 6 — a Performance Analyzer plus a Security & Malware Scanner), Filesystem (domain 7), and Database (domain 8). (Previous release: 2.2.0.)
* Changed (BREAKING): MCP namespace + server route renamed elementor-mcp -> emcp-tools. Every tool is now under the emcp-tools/ ability namespace (tool names become emcp-tools-<tool>), and the server route moved from /wp-json/mcp/elementor-mcp-server to /wp-json/mcp/emcp-tools-server (WP-CLI --server=emcp-tools-server). Every existing AI-client connection must be reconnected with the new route — regenerate configs from EMCP Tools > Connection. Stored per-tool toggles migrate automatically to the new slugs.
* Changed (BREAKING): Widget tools consolidated. The 62 per-widget convenience tools (add-heading, add-button, add-form, ...) and the universal add-widget are removed, replaced by 5 catalog-backed tools: list-widgets (now with tier/category/search filters), get-widget-schema (curated params by default, types[] batch, full:true escape hatch), add-free-widget, add-pro-widget, and update-widget. No capability is lost — every widget and every curated parameter is still reachable via discover -> inspect -> act. AI scripts that hardcoded an old tool name must switch to add-free-widget / add-pro-widget with a widget_type.
* Changed: Elementor is now OPTIONAL. The plugin and all beyond-Elementor tools (WordPress content, plugins & themes, users, media, performance, security, filesystem, database) work without Elementor. The Elementor tool family registers only when Elementor is active; otherwise the admin shows a warning, and the Brand Kits / Templates tabs show a notice.
* Changed: Tools admin page reorganized into Elementor / WordPress sub-tabs. Tool categories are now grouped under two tabs — Elementor (page-building tools + Accessibility) and WordPress (Content, Settings, Plugins & Themes, Users, Stock & Media, PHP Snippets, SEO) — so the growing tool set is easier to manage. Presentation only; no change to which tools are enabled or how they're gated.
* Added: WordPress Content tools — the first step beyond Elementor (domain 1). Eight new MCP tools to manage general WordPress content: list-post-types, list-taxonomies, create-post, get-post, update-post, list-posts, delete-post, and set-post-terms. Create and edit posts, pages, and any custom post type — title, content (classic HTML or block markup), status, slug, author, taxonomy terms, custom fields, and featured image — without touching Elementor data (an is_elementor flag steers you to the Elementor tools for builder pages). Capability-gated and enabled by default; delete-post trashes by default (pass force to permanently delete).
* Added: WordPress Settings tools — beyond Elementor, domain 2. Two new MCP tools over a curated, typed allowlist of core WordPress settings: get-settings (read general/reading/writing/discussion/media/permalinks settings; doubles as discovery — returns each setting's type, label, enum options, and writable flag; manage_options, read-only) and update-settings (batch-update allowlisted settings; non-allowlisted, read-only, or invalid values are reported in skipped[] without aborting the batch; changing a permalink setting auto-flushes rewrite rules; manage_options). Safety: curated allowlist only — no arbitrary option access; siteurl/home and users_can_register/default_role are excluded; admin_email is read-only.
* Added: WordPress Plugins & Themes tools — beyond Elementor, domain 3. Thirteen MCP tools to discover, install (wordpress.org only), update, activate/deactivate, and delete plugins and themes. Guardrails: EMCP Tools and Elementor can never be disabled/deleted; the active plugin/theme is protected; per-op capability gating; direct-filesystem-only. The 2 read tools + 2 search tools are enabled by default; the 9 mutation tools ship disabled-by-default.
* Added: WordPress Media Library tools — beyond Elementor, domain 4. Three MCP tools for existing attachments: get-media (full detail — every registered size, dimensions, metadata, alt/caption/description), update-media (edit title, alt text, caption, description), and delete-media (destructive and effectively permanent — WordPress bypasses Trash for media unless MEDIA_TRASH is defined; disabled-by-default and requires explicit confirm:true). get-media and update-media are enabled by default. URL uploads continue via the existing sideload-image.
* Added: WordPress Users tools — beyond Elementor, domain 5. Four MCP tools for safe user management: list-users and get-user (read; admin-gated, never expose passwords/auth data) plus create-user and update-user (write). Hard guardrails: create-user can only assign non-admin roles (auto-generates a strong password and emails a set-password link — the password is never returned); update-user edits profile fields only (no role or password changes) and refuses any user with admin-level capabilities. There is deliberately no delete-user and no role-change tool, so MCP cannot escalate privileges, take over an admin, or lock anyone out. list-users/get-user are enabled by default; create-user/update-user ship disabled-by-default.
* Added: Filesystem tools. Read/scan any file in the WordPress install (core, plugins, themes, uploads) — read-file, list-directory, search-files (enabled by default) — and, disabled by default, modify/delete: write-file, edit-file, delete-file. Every path is confined to the WordPress installation (no traversal/symlink escape); writes auto-back up the original, refuse wp-config.php/.htaccess, require the edit_files capability (honoring DISALLOW_FILE_EDIT), record to an audit log, and delete-file needs confirm:true. manage_options.
* Added: Database tools. Inspect the database with flexible read-only SQL -- list-tables, describe-table, query (SELECT/SHOW/DESCRIBE/EXPLAIN only; writes, DDL, stacked statements, MySQL executable comments, and file-access SQL like INTO OUTFILE/LOAD_FILE are rejected; results capped) -- and, disabled by default, structured parameterized writes: insert-row, update-rows, delete-rows. Writes use $wpdb (no raw write-SQL, so no DDL), force a non-empty WHERE on update/delete, refuse wp_users/wp_usermeta, capture a before-image snapshot into an audit log, and delete-rows needs confirm:true. manage_options.
* Added: Context page — site-wide guidance for AI agents. A new EMCP Tools > Context screen where you write stable, site-wide guidance (business identity, brand voice, content rules, guardrails) in Markdown. It is delivered to every connecting AI agent as the MCP server's instructions and applied automatically. Includes a starter template, a character/token counter, a live "what agents receive" preview, and an on/off toggle.
* Added: Connect tab — client-first flow + Claude Desktop one-click bundle. The Connection screen now asks you to pick your AI client first; the connection options (one-click bundle, terminal command, AI setup prompt, or manual JSON) appear only after that choice, tailored to the selected client. Claude Desktop gets a downloadable .mcpb bundle that installs the MCP server without editing any config files.
* Added: WordPress Performance Analyzer — beyond Elementor. New read-only MCP tool analyze-performance that audits server config, WordPress internals (database size, autoloaded options, post revisions, cron backlog, object cache, OPcache, plugin count), and a target page (defaults to the frontpage; pass url or post_id) for bottlenecks. Returns a scored report (0-100 + A–F grade) with severity-tagged findings and ranked recommendations. Self-contained (no external API); same-host-enforced loopback fetch; degrades gracefully when the page fetch is blocked. manage_options, enabled by default.
* Added: Security & Malware Scanner — the security counterpart to the Performance Analyzer. New read-only MCP tool scan-security that scans across four dimensions: malware heuristics (eval/base64 obfuscation, request-driven execution, command execution, webshells, encoded blobs, executable PHP under uploads; pass deep:true for the whole tree), WordPress core-file integrity (against official wordpress.org checksums), hardening (file editing, debug output, admin username, XML-RPC, version disclosure, HTTPS, security headers), and outdated/abandoned software (closed/removed wordpress.org plugins, outdated core/plugins/themes). Returns a scored report (0-100 + A–F grade) with severity-tagged findings and ranked recommendations. The malware walk is confined to the WordPress install, bounded by file-count + time caps (partial results flagged), and never returns full file contents (path:line + snippet only). Self-contained (only wordpress.org calls, graceful offline). manage_options, enabled by default. The admin "Performance" tools section was renamed to "Performance & Security".
* Added: WordPress core's read-only context abilities (core/get-site-info, core/get-user-info, core/get-environment-info) are now surfaced on the EMCP server too.
* Added: A curated widget catalog (62 widgets — 27 free / 30 Pro / 5 WooCommerce) that powers the consolidated widget tools.
* Changed: Per-turn widget tool-list context cut ~10x (~18-20k -> ~2k tokens), freeing the model's context window and removing the need for Low-tools mode on most clients.
* Migration: Old per-widget disabled-tool toggles are cleared automatically (defaults seeder v5). Existing pages and templates are unaffected — this changes the tools, not _elementor_data.

= 2.2.0 =
* Performance: Leaner widget tool schemas. Each per-widget convenience tool now publishes a focused set of core parameters instead of a fully-enumerated schema, cutting the MCP tools/list payload (re-sent on every request) by roughly a third with no loss of capability — every other setting still passes through to Elementor and stays discoverable via get-widget-schema.
* Fixed: get-widget-schema now returns the complete control set, and valid styling is no longer rejected. Outside the editor (WP-CLI/REST) Elementor strips a widget's style controls (typography, colours, alignment) from get_controls(); the generator now opts into the full set the way Elementor's own CSS generator does, and settings validation is non-fatal so unknown keys pass through to Elementor instead of aborting the insert.
* Fixed: Low-tools mode is now a true override — it exposes exactly the curated essentials regardless of your per-tool toggles (which are preserved), the Tools grid greys out to show the paused state, and saving now shows a "Settings saved" confirmation.
* Fixed: add-atomic-paragraph saved blank paragraphs (#56) — content was written to the wrong prop (text instead of the e-paragraph "paragraph" prop). Also fixed a sibling where add-atomic-youtube wrote the URL to "url" instead of the e-youtube "source" prop.
* Fixed: list-global-classes failed when called with no arguments (#57). Resolving the whole registry aborted if one class had an unexpected structure; each class is now resolved defensively so one bad entry can't break enumeration of the rest.
* Security: Custom CSS can no longer break out of its <style> block (F-004) — add-custom-css now neutralises the </style> end tag with a bypass-proof loop while preserving all valid CSS (combinators, media ranges, content strings).
* Security: SVG sanitiser closes a multiline event-handler bypass (F-008) — inline on*= handlers whose quoted value spanned a line break could slip past the regex.
* Security: The admin no longer localises the absolute server proxy path into page JavaScript (F-020); it exposes only the filename.
* Fixed: Page saves are more robust in CLI/REST (F-005) — the direct-meta fallback now runs when Elementor's document save returns null, not only false.
* Performance: list-pages and list-templates now set no_found_rows (F-017/F-018).
* Developer: PHPUnit suite green again (448 tests); get_all_tools() cross-checks the admin catalog against the live ability registry to catch drift.

= 2.1.0 =
* New: PHP Code Snippets (Sandbox). A free, capability-gated way for an AI agent to author server-side PHP behind a hard human-approval gate. The AI can validate code and create drafts over MCP, but a draft never runs until an admin activates it in EMCP Tools > Sandbox (there is no "activate" tool). Every snippet passes a static parse + security scan (blocks exec/eval/backticks/variable-functions/file-writes/network/destructive SQL/obfuscation) before it can be saved or activated; activation writes a sha256-verified file that runs inside try/catch with a shutdown guard that auto-deactivates a snippet that fatals. Six tools (validate/create/update/get/list/delete). Off by default.
* New: list-global-classes tool (#55). Resolves Elementor's Class Manager (Global Classes) — maps the opaque g- class IDs on elements back to their human-readable names (g-037bb9c -> card-base) and the CSS each defines, per breakpoint/state, so an AI can understand and debug a design-system-driven page. Read-only; Elementor 4.0+.
* New: One-click authentication test on the Connection tab (#41). After generating credentials, "Test authentication" sends a real request using only the Authorization header and tells you whether a client will connect — and if your server is stripping the Authorization header (the usual cause of "initialize: Unauthorized" on Plesk/Apache/IIS), it shows the exact .htaccess / nginx fix.
* New: OpenAI-strict tool schemas (opt-in, #42). A Connection-tab toggle that emits strict JSON Schemas (every property required, optionals nullable, additionalProperties:false) for OpenAI-compatible strict function-calling clients like CrewAI. Off by default — the default schemas keep working for Claude, Gemini and Antigravity.
* Fixed: Atomic widgets and containers silently failed to save (#36). add-atomic-widget, update-atomic-widget and nested add-div-block passed a boolean to the save layer ("save_page_data: bool given"), so the element was never written. They now save correctly, and invalid atomic settings return a clean error instead of fataling the request.
* Fixed: Setting theme-template conditions broke other templates (#38). set-template-conditions and set-popup-settings cleared Elementor Pro's conditions cache without rebuilding it, silently stopping unrelated headers/footers from rendering. They now use Elementor's own conditions manager, which regenerates the location cache correctly.
* Fixed: Prompts and Brand Kits cards resetting to 0. Counts now read a durable mirror and refresh in the background, so they no longer drop to zero when the cache expires.
* Fixed: Broken "Generate Configs" button when admin.js was quarantined by security software/host (#44). The plugin now detects a missing/renamed admin.js and shows a precise notice, and a release-time verifier guards against shipping such a build.
* Improved: Styled Changelog screen — version cards, category tags and formatted notes instead of raw text.
* Improved: Code viewer and copy — generated code opens in a slide-in viewer with copy/download, and shortcodes are click-to-copy.
* Developer: the bootstrap file was slimmed to bootstrap-only and all feature logic moved into dedicated classes; restored the uninstall-cleanup hook dropped during the 2.0 rename.

= 2.0.2 =
* Fixed: Tool toggles & Low-tools mode wouldn't save. After the 2.0 rename, the legacy-settings migration ran on every page load and copied the old elementor_mcp_* options (still in the database) over your current settings — so anything you saved on the Tools screen, including Low-tools mode, was silently reset on the next load. The migration now only seeds a new setting when it has never been set, so it can't overwrite your live choices.
* Fixed: "Enable All" / "Disable All" also flipped Low-tools mode. The bulk buttons are now scoped to the tool checkboxes only, leaving the separate Low-tools-mode toggle alone.
* Fixed: First-ever save on the Tools screen could invert (the disabled-tools sanitizer is now idempotent).
* New: list-media tool (#25). Lets an AI agent discover and search images already in the WordPress Media Library — the site's own uploads — where Openverse only finds generic stock. Backed by a direct WP_Query; optional search matches the title, alt text, caption, and description, with mime-type, pagination, and sort filters. Read-only; not part of the Low-tools essentials.
* Improved: Tools screen UI — section headers are now collapsible (state remembered per section) and the per-section All / None controls are a segmented button instead of text links.
* Improved: "Elementor Pro" vs "Pro" badges. Tools that need Elementor Pro (widget shortcuts, theme builder, popups, dynamic tags, Pro custom code) now show a distinct "Elementor Pro" badge, so they aren't confused with the "Pro" badge reserved for EMCP Tools Pro features. The "Pro Widget Shortcuts" section is renamed "Elementor Pro Widgets".

= 2.0.1 =
* Fixed: Pro license activation. The premium build now correctly identifies as premium (it reads the bundled .emcp-pro marker), so Freemius shows the license-activation screen instead of the free opt-in. Previously the Pro zip behaved like the free version — if you skipped opt-in or didn't click the confirmation email, no "Activate License" link appeared.
* Stuck on Pro 2.0.0? Update to 2.0.1 and the Activate License option will appear. If your install is still wedged mid opt-in, run the official Freemius Fixer (https://github.com/Freemius/freemius-fixer) to re-trigger the opt-in, complete the opt-in, then activate your license.
* New: Community button in the admin header linking to the EMCP Tools Facebook group.

= 2.0.0 =
* ⚠️ PRO USERS — ACTION NEEDED: Because the plugin folder/slug changed (elementor-mcp -> emcp-tools), the new install is a separate plugin to WordPress, so your Pro license does NOT carry over automatically. After deleting the old plugin and activating "EMCP Tools", you will likely need to re-activate your license and complete the Freemius opt-in/connection again. Your license stays valid — this only re-links it to the renamed plugin. Free users have nothing extra to do.
* Changed: The plugin was renamed from "emcp-tools" to "emcp-tools" as it grows beyond Elementor. The folder, main file, text domain, and all internal PHP identifiers were rebranded. In the Plugins list it now shows as "EMCP Tools" (the old one stays "MCP Tools for Elementor", so it's clear which to remove). Your AI clients keep working unchanged — the MCP tool names and server (emcp-tools/..., emcp-tools-server) are intentionally unchanged, so no connection config or skill needs editing.
* New: Safe automatic migration. If the old "emcp-tools" plugin is still active, EMCP Tools pauses and shows a notice to deactivate and delete it; your settings and banner dismissals are carried over to the new keys (captured before the old plugin's uninstall can wipe them).
* Note: Install emcp-tools as a new plugin, then remove the old elementor-mcp one when prompted. All PHP symbols are uniquely re-prefixed so the two coexist during the switch without fatal errors.

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
