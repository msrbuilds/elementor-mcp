=== MCP Tools for Elementor ===
Contributors: mianshahzadraza
Tags: elementor, mcp, ai, page-builder, automation
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 3.18.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extends the WordPress MCP Adapter to expose Elementor data, widgets, and page design tools as MCP tools for AI agents.

== Description ==

MCP Tools for Elementor bridges the gap between AI tools and Elementor page design. It extends the official WordPress MCP Adapter to expose a focused set of MCP (Model Context Protocol) tools that let AI agents like Claude, Cursor, and other MCP-compatible clients create and manipulate Elementor page designs programmatically.

As of v3.0.0 the 62 per-widget tools were folded into a catalog-backed model, so the active tool surface is much smaller while every widget stays reachable, and the toolset takes its first steps beyond Elementor with general WordPress content management, curated site-settings control, full plugin and theme management, media attachment management, safe user management, filesystem access, and database inspection over MCP. The v3.0.0 beyond-Elementor surface adds 8 WordPress Content tools + 3 surfaced WordPress core abilities + 2 WordPress Settings tools + 13 Plugins & Themes tools + 3 Media Library tools + 4 Users tools + 1 Performance Analyzer tool + 1 Security & Malware Scanner tool + 6 Filesystem tools + 6 Database tools. Tool counts scale with your environment (registered counts, verified on Elementor 4.1.4): around 107 tools on a free Elementor install, ~121 with the Elementor 4.0+ atomic elements, ~117 with Elementor Pro, and ~131 with Pro + Elementor 4.0+ (WooCommerce adds no new tools; its widgets are reached through add-pro-widget). When Advanced Custom Fields (free or PRO) is active, 2 ACF tools register on top of any of those counts: `acf-read` and `acf-write`, two dispatchers that expose 18 ACF operations (9 read, 9 write; the Custom Post Type and taxonomy operations need ACF 6.1+). Each dispatcher is a single toggle under Tools → Plugins → ACF, listing the operations it covers; `acf-read` is on by default and `acf-write` ships off. About 39 other tools ship disabled-by-default (SEO & Accessibility, Widget Builder, PHP Snippets, the 9 Plugins & Themes write tools, delete-media, the 2 Users write tools, the 3 Filesystem write tools, and the 3 Database write tools), so the typical active surface is smaller.

**Key Features:**

* **Query & Discovery**: List widgets, inspect page structures, read element settings, browse templates, and view global design tokens.
* **WordPress Content (beyond Elementor)**: Create and manage posts, pages, and any custom post type (content, status, taxonomy terms, custom fields, and featured images) via MCP, without touching Elementor data. Built on WP core; every post carries an `is_elementor` flag that steers agents to the Elementor tools for builder pages. (v3.0.0)
* **Front-end Page HTML**: Fetch the public front-end HTML for a same-origin URL or published post ID with `get-page-html`. The read-only tool includes theme and SEO-plugin output, returns large documents in checksum-guarded chunks, and never forwards the connected user's credentials. It returns the server response source; JavaScript is not executed.
* **WordPress Settings (beyond Elementor)**: Read and batch-update core WordPress settings (general/reading/writing/discussion/media/permalinks) over MCP. Curated allowlist only: no arbitrary option access; `admin_email` is read-only; permalink changes auto-flush rewrite rules. `manage_options`. (v3.0.0)
* **Plugins & Themes (beyond Elementor)**: Discover, install, update, activate/deactivate, and delete plugins and themes over MCP. Installs accept a wordpress.org slug or a confirmed, hash-verified ZIP first stored with `upload-media`; URLs, server paths, overwrites, unsafe archives, and protected destinations are refused. (v3.0.0)
* **Media Library (beyond Elementor)**: Fetch full attachment detail, edit metadata (alt text, title, caption, description), and delete attachments over MCP. get/update are enabled by default; delete is disabled-by-default and requires explicit confirmation. (v3.0.0)
* **Users (beyond Elementor)**: List and read WordPress users, and safely create/edit non-admin profiles over MCP. No delete and no role changes; new users get an auto-generated password by email (never returned); administrators are off-limits to editing. Reads enabled by default; create/update disabled-by-default. (v3.0.0)
* **ACF / ACF PRO (beyond Elementor)**: Read and write Advanced Custom Fields values on posts and options pages, including agent-parsed document imports with whole-chunk validation, provenance, hash-bound confirmation, per-row results, and read-back. Full Pro field support (repeater, flexible content, gallery, group, clone) as nested JSON. No document parsing runs on WordPress. (v3.2.1)
* **GSAP Integration (Pro)**: Locally bundles GSAP core and all 24 plugins from the official 3.15.0 distribution, registers stable WordPress script handles for pages, themes, and plugins, and provides per-plugin and loading-context switches plus MCP read/write management. Its dedicated `emcp-gsap` Agent Skill ships in both Skills-tab download formats and is also available through the runtime skill catalog. The module is opt-in. (v3.16.0)
* **Meta Box (beyond Elementor)**: Read/write Meta Box custom-field values and discover field groups over MCP. Only registers when Meta Box is active; `metabox-read` is on by default, `metabox-write` ships disabled-by-default (values only, no delete; unknown fields are skipped rather than created). (v3.4.2)
* **Forms (8 plugins, beyond Elementor)**: Two dispatcher tools per plugin (read/write) for Contact Form 7 (free) plus WPForms, Gravity Forms, Fluent Forms, Ninja Forms, Formidable, MetForm, and SureForms (Pro). Read forms, fields, notifications and entries; manage entries. Each pair registers only when its plugin is active; reads on, writes off by default; `delete-entry` needs `confirm:true`. (v3.5.0)
* **SEO (7 plugins, beyond Elementor)**: Two dispatcher tools per plugin (read/write) for Slim SEO (free) plus Yoast, Rank Math, All in One SEO, SEOPress, The SEO Framework, and SureRank (Pro). Read/write post & term SEO metadata (title, description, canonical, robots, social) over one unified field set, plus schema-read where supported. (v3.5.0)
* **BeTheme and BeBuilder (beyond Elementor)**: Two dispatcher tools (`betheme-read` / `betheme-write`) covering both halves of the theme: Theme Options through a curated 34-key allowlist (colours, typography, layout, header/footer), and BeBuilder page content as a section/wrap/item tree that can be read, replaced, or extended a section at a time. `list-item-types` and `get-item-schema` report every builder item the installed theme registers with the fields each accepts. Registers only when BeTheme is the active theme; `betheme-write` ships disabled-by-default. (v3.14.0)
* **Performance Analyzer (beyond Elementor)**: Scan the server config, WordPress internals (database size, autoloaded options, post revisions, cron backlog, object cache, OPcache, plugin count), and a target page (defaults to the frontpage; optional URL or post) for performance bottlenecks. Returns a scored report (0-100 + A–F grade) with severity-tagged findings and ranked recommendations. Read-only, self-contained (no external API), enabled by default. (v3.0.0)
* **Security & Malware Scanner (beyond Elementor)**: The security counterpart to the Performance Analyzer: scan across malware heuristics (eval/base64 obfuscation, request-driven execution, command execution, webshells, encoded blobs, executable PHP under uploads), WordPress core-file integrity (against the official wordpress.org checksums), hardening (file editing, debug output, admin username, XML-RPC, version disclosure, HTTPS, security headers), and outdated/abandoned software. Returns a scored report (0-100 + A–F grade) with severity-tagged findings and ranked recommendations. The malware walk is bounded and never returns full file contents (path:line + snippet only). Read-only, self-contained (only wordpress.org calls, graceful offline), enabled by default. (v3.0.0)
* **Filesystem (beyond Elementor)**: read/scan any file in the WordPress install; modify/delete off by default. (v3.0.0)
* **Database (beyond Elementor)** -- flexible read-only SQL plus safe structured writes (off by default). (v3.0.0)
* **Page Management**: Create pages, update page settings, clear content, import/export templates.
* **Layout Tools**: Add flexbox containers, move/remove/duplicate elements, batch updates, reorder children.
* **Widget Tools**: A catalog-backed model: list-widgets (filter by tier/category/search) -> get-widget-schema (curated params, batch, or full raw schema) -> add-free-widget / add-pro-widget (with Pro) -> update-widget. The 62 widgets' curated params live in a built-in catalog (27 free / 30 Pro / 5 WooCommerce), so every widget and parameter stays reachable while the per-turn tool-list cost drops ~10x.
* **Pro Widget Support**: Conditional tools for Elementor Pro widgets (form, posts grid, countdown, price table, flip box, animated headline, call to action, slides, testimonial carousel, price list, gallery, share buttons, table of contents, blockquote, Lottie, hotspot, loop grid/carousel, nested tabs/accordion, portfolio, author box, login, code highlight, reviews, off-canvas, progress tracker, search, and more) that only register when Pro is active.
* **Atomic Elements (Elementor 4.0+)**: 13 dedicated tools for Elementor's new atomic system: flexbox, div-block, heading, paragraph, button, image, svg, youtube, video, divider, plus universal `add-atomic-widget` / `update-atomic-widget` and `detect-elementor-version`.
* **Template Tools**: Save pages or elements as reusable templates, apply templates to pages, theme builder, popups, dynamic tags (Pro).
* **Global Settings**: Update site-wide color palettes and typography presets.
* **Composite Tools**: Build a complete page from a declarative JSON structure in a single call.
* **Stock & Media Images**: Search Unsplash, Pexels & Pixabay for stock photos, sideload into the Media Library, add to pages, plus `list-media` to discover and search the site's own existing uploads (by title, alt text, caption, and description).
* **SVG Icons**: Upload SVG icons from URL or raw markup for use with Elementor icon widgets.
* **Custom Code**: Add custom CSS (element/page level), inject JavaScript, create site-wide code snippets for head/body injection.
* **AI Widget Builder (Pro)**: Let an AI agent design custom Elementor widgets from a structured spec (no hand-written PHP). The plugin compiles the spec into a sandboxed widget that appears in the Elementor panel: 35 control types, optional CSS/JS, with a runtime safety net so a bad widget can never break the editor.
* **Brand Kits**: One-click color + typography kits that re-skin your whole site. 10 kits are free to apply (with backup + restore); 50+ with Pro.
* **Low-tools Mode**: One-click toggle that trims the active tool list to a curated essentials set for MCP clients with strict tool caps (Antigravity, Gemini API, etc.). After the v3.0.0 widget consolidation the active count already fits most caps, so this is rarely needed now.
* **Sample Prompts**: Ready-to-use landing page blueprints with one-click copy from the admin dashboard.
* **Admin Dashboard**: Dedicated top-level menu with Tools, Connection, Prompts, Templates, Brand Kits, Skills, Widget Builder, and Changelog tabs. Toggle individual tools on/off, view connection configs for all supported MCP clients, and get help via the built-in Get Support link.

**Requires:**

* WordPress 6.9 or later
* WordPress Abilities API: included in WordPress core 6.9+
* WordPress MCP Adapter: bundled with the plugin (no separate install needed; an active standalone MCP Adapter plugin is used instead when present)

**Recommended (optional):**

* Elementor 3.20 or later: enables the full Elementor tool family (query, pages, layout, widgets, templates, globals, composite, stock images, SVG icons, custom code, atomic elements, global classes, brand kits, widget builder, SEO/A11y). The plugin and all beyond-Elementor tools work without it; the admin shows a warning when Elementor is not active.

**Connection Methods:**

* WP-CLI stdio (recommended for local development)
* Node.js HTTP proxy (for remote sites)
* Direct HTTP (for VS Code MCP extension)

== Installation ==

1. Upload the `emcp-tools` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress. The MCP Adapter is bundled. No separate install is required (WordPress 6.9+ already includes the Abilities API).
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

= ChatGPT App (Codex) Connection =

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

= A remote MCP connector intermittently says "the connector's server isn't responding" =

On shared LiteSpeed hosting (e.g. Hostinger) this is usually the host caching/buffering or timing out the request, not the plugin. The MCP route already sends no-store/no-cache headers; in addition: exclude `/wp-json/mcp/` from LiteSpeed Cache (Cache -> Excludes -> Do Not Cache URIs), raise PHP `max_execution_time` (>=60) and the concurrent PHP worker limit, and check the new **EMCP Tools -> MCP Log** tab (with `WP_DEBUG` on it records the underlying error) to tell a real error from a transport timeout. For large operations use `build-page` `dry_run`, `sideload-image` `convert_webp:false`, and `get-page-structure` `summary:true`.

== Screenshots ==

1. Tools management page with category-grouped toggles.
2. Connection configuration page with copy-paste configs.

== Changelog ==

= 3.18.0 =

Adds a loop builder to EMCP Themer, so one post card can be repeated in grids and carousels in Elementor or Gutenberg, a Pro module that exports your active Sandbox widgets, blocks and snippets as a standalone plugin, and OpenCode Go and OpenCode Zen in AI Chat. It also fixes a crash with Themer image sources in Elementor, blank padding and margin sides, and curated widget settings that Elementor ignored.

* New: Loop Items, Loop Grid and Loop Carousel in EMCP Themer (free). Design one post card and repeat it in a responsive grid or a carousel, in Elementor or Gutenberg, with queries over posts, the current archive, related posts, a manual selection or WooCommerce products, and pagination from page numbers to load more and infinite scroll. Free includes one Loop Item; Pro adds unlimited Loop Items and alternate templates for the Loop Grid widget.
* New: OpenCode Go and OpenCode Zen in AI Chat (Pro, #150). Two new providers; their requests go through a server relay because OpenCode's API refuses browser requests, and your keys stay on the server. OpenCode describes Go as designed for coding agents, so review its terms before use. Zen bills a prepaid balance that reloads $20 below $5 by default. Gemini models are not offered on Zen (use the Gemini provider), and Zen's free models are hidden because OpenCode limits them to its own client.
* New: Works alongside Elementor's own MCP server (Elementor 4.3+). EMCP's Elementor tools refuse to write to a page another user has open with unsaved changes, tell an open editor when a page changed, and tell connected agents which server to use for which job. The admin bar item now reads EMCP.
* Fixed: Themer dynamic images and links in Elementor. Binding the Featured Image, Site Logo or Author Avatar source to an Elementor image or background no longer crashes the page, and the Featured Image size setting now takes effect.
* Fixed: A padding, margin, border radius or border width sent with some sides blank is no longer saved in a way that makes Elementor drop the whole rule; blank sides are filled from the saved value or the setting is left unchanged, and the warning says which (#151).
* Fixed: The Brand Kits tab no longer disappears when Elementor is not the selected page builder; it follows its module switch, like Templates.
* Fixed: Bricks 2.4 compatibility. The Bricks integration can now be enabled on Bricks 2.4.x, verified natively on 2.4.1.
* Fixed: 55 curated widget parameters used names Elementor does not recognise, so their values were ignored; all are corrected or removed, catalog defaults now use values Elementor accepts (progress bars show their percentage and code blocks their line numbers and copy button again), and the widget tools now warn about unrecognised settings. Re-send values set under the old names (#152).
* Changed: EMCP Themer is off by default on new installs; turn it on from EMCP Tools > Modules. Sites that already use it keep it on.
* New: Starter prompts in AI Chat (Pro). A new chat suggests six prompts above the message box, your most used one first and the rest shuffled from ones you have not tried: site tasks on the AI Chat page, page tasks in the editors.
* New: Sandbox Plugin Export (Pro module, off by default). Package every active Sandbox widget, block and PHP snippet as a standalone plugin that keeps working after EMCP Tools is deactivated. Identities are preserved, the runtime the widgets call is bundled, API keys come from constants, and the export stays dormant while EMCP Tools is active.

= 3.17.1 =

Patch release: three data-safety fixes for Elementor writes, a Cloud gateway reconnect fix, an OAuth discovery fix for sites running a second MCP plugin, and a community-contributed Visibility SEO integration.

* Fixed: update-page-settings merges into the stored settings instead of replacing the whole object, so a one-key patch on the active kit no longer wipes custom colors, typography presets and site identity (#145).
* Fixed: update-global-colors refuses the system color ids (primary, secondary, text, accent) instead of writing a shadow custom color, and reports which ids it wrote (#145).
* Fixed: Dimension values (margin, padding, gap, border) are stored as strings so the Elementor editor's Layout panel shows the real value instead of 0 (#146).
* Fixed: Third-party widgets Elementor cannot instantiate in the current context (FunnelKit Pro, CartFlows checkout forms) are no longer silently dropped when another element on the page is edited over MCP (#143).
* Fixed: Reconnecting to EMCP Cloud re-issues or withdraws the gateway credential according to the gateway box, and a Re-issue gateway credential button covers a credential revoked or lost outside a reconnect (#148).
* Fixed: OAuth discovery answers only the well-known paths scoped to EMCP's own MCP resource, so a second OAuth-capable MCP plugin on the same site (path-based issuer) can serve its own discovery document and connect without switching EMCP sign-in off.
* New: Visibility SEO integration, free, two tools, read and write, over Visibility's per-post SEO metadata including robots flags, Open Graph fields and the Schema.org type override. Contributed by Fernando Tellado (#141, #149).

= 3.17.0 =

Adds independent page-builder integrations and Gutenberg block packs, expands Widget Builder data controls, bundles native authoring skills, and strengthens History recording and rollback.

* New: Dedicated page-builder integrations. Bricks, Breakdance, Avada, Divi 5, Thrive Architect, Oxygen 6, Kirki, WPBakery, Beaver Builder and Visual Composer join the existing Elementor and BeBuilder workflows. Each has its own tools and sections. Availability follows the installed vendor version and tested scope; optional vendor features may remain read-only or unsupported.
* New: Page Builders management screen. Choose one standalone builder integration while keeping Gutenberg available. Inactive builder tools stay out of discovery, and new write tools start disabled.
* Improved: Independent Gutenberg block packs. Spectra, Kadence Blocks, GenerateBlocks, Blocksy Blocks and Otter Blocks have independent toggles and dedicated tools. Theme settings remain in Themes; Blocksy Companion extensions and Pro Content Blocks have separate plugin tools.
* New: Otter Free and Pro support. Adds 19 tools for native block discovery and editing, local patterns, generated page styles and allowlisted feature settings. Verified with 45 registered blocks in Free and 63 with Pro. Static blocks retain native Gutenberg markup.
* Improved: Oxygen integration coverage. Adds native library, template, component and design-system workflows, version-aware schemas, conditional data handling and recovery checks. The bundled skill and integration guide document tested write boundaries.
* New: Widget Builder data controls. Queries can supply posts, products, terms, menus, site data, breadcrumbs, carts and remote JSON. Allowlisted shortcode providers support saved Elementor templates and form plugins. Live data keys have a dedicated Connection section and remain outside cloud settings sync.
* Fixed: Widget control collisions. Reserved Elementor setting names are rejected so a custom control cannot accidentally hide a widget through entrance-animation settings. Structured control defaults and live registry synchronization improve generated widget behavior.
* Improved: Bundled authoring skills. Builder-specific guidance is available in runtime discovery and both downloadable skill formats, with native editor contracts, test findings and upstream attribution.
* Fixed: Templates module visibility. The Templates page follows its module toggle even when another builder is selected. The current library remains Elementor-specific. The general Elementor installation notice no longer appears merely because Elementor is absent.
* Maintenance: Shared agent guidance. AGENTS.md is the single source of project guidance; CLAUDE.md imports it. Native integration probes, MCP smoke fixtures and regression tests accompany the implementation.

* Fixed: create-page records one creation event and returns its change_id. Undo removes the created page, including its initial Elementor content.
* New: upload-media records a creation event and returns its change_id. Undo removes the attachment and verifies deletion of recorded WordPress-managed files, including generated sizes on Windows.
* Fixed: Page settings and custom CSS changes record exact prior metadata for undo, preserve absence and escaped values, and invalidate Elementor caches. Identical CSS saves do not add redundant entries.
* Improved: Conflict guards protect later creation edits, metadata, taxonomy assignments, user fields, ACF values, and changed upload bytes. Unrelated post-field changes remain intact.
* Fixed: Failed restoration or History persistence no longer reports ordinary rollback success. Missing backups and occupied IDs are refused, and failed ledger cleanup retains its snapshots.
* Changed: Incomplete or unverified database, legacy post, and generic attachment-deletion snapshots refuse automatic undo even with force. History explains unavailability and retains snapshots for inspection. Previously omitted actions are not reconstructed; rollback coverage remains operation-specific.

= 3.16.1 =

Fixes detected server URLs on plain-permalink WordPress sites in both Free and Pro.

* Fixed: Remove the trailing index.php from the detected Server URL while preserving subdirectory paths. Corrects the default displayed on the Connection tab and the site URL embedded in generated .mcpb bundles (#142).

= 3.16.0 =

Adds structured imports, uploaded package installs, broader ACF coverage, FunnelKit reads, GSAP integration, and correct Hello Elementor Themer rendering.

* New: GSAP Integration is an opt-in Pro module with locally bundled GSAP 3.15.0 core and all 24 distributed plugins, stable WordPress handles, per-plugin and context controls, dependency resolution, MCP read/write dispatchers, and a dedicated Agent Skill included in both downloadable skill formats.
* New: FunnelKit Funnel Builder read support (Pro) adds five operations for funnels and ordered steps, decoded funnel-page configuration, checkout products with effective prices, and checkout fields in saved order (#140). Automations, contacts, statistics, and writes remain outside 3.16.0.
* New: ACF and WooCommerce accept bounded structured imports extracted by an agent from PDF, spreadsheet, CSV, DOCX, OCR, or other source documents; WordPress validates and writes the structured payload without parsing the source file server-side.
* New: plugin and theme installers accept confirmed, SHA-256-bound ZIP attachments uploaded through the media tool, with archive, compatibility, traversal, symlink, overwrite, and protected-destination guards.
* Improved: ACF Free and Pro coverage is runtime-discoverable and type-aware, including nested Pro values, clone sources, relationship updates, field-group settings, and full validation before mutation.
* Improved: Change History now shows 20 entries per page with result counts and previous, next, and numbered controls; filters and row actions retain the current History page.
* Fixed: EMCP Themer headers and footers now replace Hello Elementor 3.x native parts through Hello's real render-time filter, without duplicate output or interference with settings checks and Elementor Pro precedence (#138).

= 3.15.2 =

Restores the intended formatting of the 3.15.1 release notes in plugin changelog views.

* Fixed: the 3.15.1 changelog now keeps the required spacing between its heading, summary, and bullet list so WordPress-compatible readme renderers display it correctly.

= 3.15.1 =

Fixes AI Chat for the latest OpenAI models, reduces token usage, and restores cross-site settings sync.

* Fixed: GPT-5.6 and Astra models now use OpenAI's Responses API, while older OpenAI models and OpenAI-compatible providers keep using Chat Completions. Follow-up messages no longer abort because of a stale send handler.
* Improved: AI Chat sends a smaller tool and conversation context, loads specialist tool groups only when needed, bounds large tool results and histories, and reports cached input tokens correctly.
* Fixed: settings pushed to EMCP Cloud are shared across the account so another connected site can pull them. Sync failures now explain whether the plan, connection, or missing cloud data caused the problem. Secrets, API keys, and connection credentials remain excluded.
* Compatibility: Cloud sign-in accepts older server registration responses while using the stronger registration proof when supplied, allowing website and plugin updates to roll out independently.

= 3.15.0 =

Adds public page HTML inspection, Elementor CSS regeneration, and native WooCommerce Brands support, and improves Navigator labels and layout guidance.

* Added: read-only public front-end HTML inspection with bounded chunks and same-origin redirect protection (#132). Returns server-rendered HTML; JavaScript is not executed.
* Added: page or site-wide Elementor CSS/data regeneration (#136). Ships disabled by default; site-wide use requires administrator permission and confirmation.
* Added: native WooCommerce brand list/get/create/update workflows (Pro), with dry runs, duplicate checks, validated logo attachments, and guidance for the active SEO plugin's term tools (#137).
* Fixed: classic and atomic Navigator label persistence, with saved-value verification (#133).
* Improved: layout tools warn when dimension values mix blank and specified sides, without changing the supplied values (#134).
* Improved: grid discovery and creation explain Elementor's two-row default and the explicit single-row setting (#135).

= 3.14.2 =
Fixes intermittent MCP connection failures affecting OAuth and Application Password clients, especially on managed hosts and during concurrent startup.
* Fixed: the MCP proxy serializes initialization, reuses the resulting session for immediately-following requests, and sends the negotiated protocol-version header.
* Fixed: OAuth discovery, authorization, token exchange, and host validation now share the configured public Server URL and work correctly when WordPress is installed in a subdirectory. The challenge uses an EMCP-specific metadata URL, and diagnostics reject valid-looking metadata that belongs to another plugin (#130).
* Fixed: OAuth authorization codes and tokens are bound to the intended MCP resource, with REST metadata fallbacks and a discovery diagnostic for hosts or CDNs that intercept standard .well-known routes.
* Fixed: Application Password creation respects WordPress availability policy, and its connection test now performs a complete MCP initialize/initialized/tools-list handshake instead of testing only the WordPress user endpoint.
* Updated: the bundled WordPress MCP Adapter to 0.6.1 and PHP MCP Schema to 0.1.3 for current protocol and session handling.
* Compatibility: tested end-to-end on WordPress 7.1 and PHP 8.3, covering OAuth and Application Password handshakes through tools/list.

= 3.14.1 =
Adds Elementor Global Variables and responsive typography, hardens Global Class writes and friendly CSS sizes, and acts on an independent security review.
* Added: six Elementor Global Variables tools for listing, creating, updating, soft-deleting, restoring, and batching design tokens (#124). Writes ship disabled by default and use Elementor's storage watermark to reject stale changes.
* Added: tablet and mobile values for global font size, line height, letter spacing, and word spacing (#129).
* Fixed: Global Class friendly sizes preserve percentages, relative units, and CSS functions instead of coercing them to pixels or zero (#126).
* Fixed: Global Class writes validate the complete property map against Elementor's live style schema before saving, so unsupported names and malformed values return an error without changing the class (#125).
* Fixed: Premium Prompts use ETags, validate versioned bundles and their SHA-256 digest, and recover cleanly when a cached bundle and its ETag drift apart.
* Fixed: clipboard fallback reports success and records usage only after the browser actually copies the prompt.
* Security: URL fetches validate every redirect and every resolved address; filesystem reads resolve symlinks and protect common secret files; public `.dev` domains keep TLS verification; sandbox paths resolve traversal; GitHub update packages are pinned to this repository; and PHP snippet review now surfaces method calls.

= 3.14.0 =
Adds BeTheme and BeBuilder support, tells you why a tool cannot be switched on, and settles a malware-scanner false positive reported from a live site.
* Added: BeTheme and BeBuilder support (Pro). Two tools, betheme-read and betheme-write, cover both halves of the theme: Theme Options through a curated 34-key allowlist (colours, typography, layout, header and footer) so an agent can restyle a site without wading through all 830 settings, and BeBuilder page content as a section/wrap/item tree that can be read, replaced, or extended a section at a time. list-item-types and get-item-schema report every builder item the installed theme registers, 135 on the version tested, with the fields each accepts. Registers only when BeTheme is the active theme; betheme-write ships disabled-by-default. Includes a new emcp-betheme agent skill.
* Added: a notice when EMCP Themer and BeTheme would both try to render the same page. Nothing is switched off for you: the notice appears only on a real overlap, says which one is winning, and points to the setting to change if you wanted the other.
* Changed: the "Settings saved" confirmation now slides in as a toast in the bottom right instead of pushing the page down from the top. It closes itself after a few seconds and waits while you hover it. Only that one confirmation moved; notices that carry buttons or explain page state stay where they are.
* Changed: a tool that cannot be switched on now says why. A greyed-out toggle used to look the same whether you had turned the tool off or it needs software you do not have installed, and the second case reads as broken. Every such card now carries a badge naming what is missing, such as "Needs Essential Addons for Elementor", with a line saying whether it is a plugin that is not active or a theme that is not the active one.
* Fixed: a host's malware scanner reported the Backup and Migrate restore file as infected. It was a false positive and nothing was compromised. The file receives an uploaded backup archive, and one path accepts it base64-encoded because some hosts' firewalls reject the raw bytes; decoding incoming data and writing it to a file has the same shape a real backdoor has, so scanners flag it on sight. What they cannot see is the gate in front of it: a one-time nonce, the administrator capability, and a fixed destination in the plugin's own protected backups folder. Decoding now happens in a stream filter as the bytes travel to disk, so the archive contents never sit in memory next to the write. Two real limits were added at the same time: the upload now refuses any file type the restore flow cannot read, and the migration connector refuses any media filename the server might execute, checking every extension in the name. Connector to 1.3.0; one already installed on a live site keeps working.
* Fixed: three of the bundled agent skills were rejected when uploaded to Claude Desktop. Their descriptions used a placeholder the upload validator reads as an XML tag, and one description had grown past the 1024-character limit. All nineteen packages now upload as shipped.

= 3.13.2 =
Fixes an atomic rich-text element being quietly flattened by an edit, and rewrites the PHP snippet validator's warnings so a routine snippet no longer looks alarming.
* Fixed: editing the text of an atomic element containing an inline tag emptied its rich-text structure (#121, reported by @kerk12). An atomic text value stores the same text twice, as markup and as a node tree the editor reads from, and updating the text rebuilt the markup but always wrote an empty tree. The page kept rendering exactly as before and the tool reported success, so the only symptom was the element opening empty in the editor. Both halves now come from a single parse of the markup, so they cannot drift apart. Elements already damaged are not repaired automatically: re-apply the text once and the structure is rebuilt.
* Changed: PHP snippet warnings say what to check, and routine code no longer looks like a problem. Findings come in three levels rather than two: anything that blocks a snippet still blocks it, things worth a reviewer's attention such as writing a site option are warnings, and things that are ordinary in working code such as reading request input are notes. Almost every well-written snippet was arriving covered in warnings, and a reviewer told that ten routine things are warnings learns to skim. Each snippet now leads with a plain verdict such as "Safe to activate. 3 notes, all ordinary in working code."

= 3.13.1 =
Fixes a critical error on the front end when a dynamic value is used on a standard Elementor widget. If you are on 3.13.0 and using dynamic data, please update.
* Fixed: saving an Elementor document could hang the site and fill the error log (#119). Opening or saving a document the builder had not yet converted sent the search indexer into a loop until PHP ran out of memory. Thanks to @HopeItBuilds for the report and @HafizMMoaz for the fix.
* Fixed: a page showed "There has been a critical error on this website" when a normal Elementor widget such as a Heading was given a dynamic value. Elementor rebuilds a dynamic source from its class when the page renders, and 3.13.0 used one shared class for all seventeen sources, so the rebuilt source no longer knew which one it was. Each source is now its own class. Nothing was damaged: affected pages render again as soon as you update, with nothing to redo. The block editor was never affected.

= 3.13.0 =
Adds nine widgets and dynamic data to EMCP Themer, and rewrites how raw SQL is checked. Please update.
* Added: nine new Themer widgets, so a Single, Archive, Search or 404 template can actually be finished: Author Box, Post Navigation, Post Comments, Search Form, Sitemap, Post Info, Archive Posts, Featured Image and Post Excerpt. All free. Author Box and Sitemap also ship as blocks; comments, search and post navigation already exist as core blocks so they are not duplicated.
* Added: the EMCP Themer widgets sit at the top of the panel while you edit a theme template, instead of near the bottom. On an ordinary page or post they stay where they were and remain available, since a sitemap, search form or posts grid is useful outside a template too.
* Added: Post Info and Archive Posts are new widgets beside Post Meta and Archive Loop rather than replacements, so templates you have already built keep rendering exactly as they do now. Archive Loop is relabelled "Archive Posts (classic)" so the two are tellable apart.
* Added: a new "EMCP Modules" tab on the Tools screen. The WordPress tab had grown to 21 sections, about half of which were EMCP features rather than WordPress management (theme builder, backup and migrate, redirects, changes and rollback, content search, content mirror, PHP snippets, project memory, cloud). Those move to their own tab. Nothing changed about what any tool does.
* Added: the Cloud and Marketplace tools now appear on the Tools screen, and Resize Media moves into its own Image Optimization section. They worked before but were not listed anywhere.
* Added: the nine EMCP Themer tools now appear on the Tools screen, in their own section under the WordPress tab. They always worked but had no entry there, so you could not see or toggle them individually. All nine stay on by default.
* Added: Themer templates can pull in live data the way Elementor Pro's dynamic widgets do. A heading can show the post title, an image the featured image, a button can link to the post. You pick the source from the field itself: in Elementor it appears in the dynamic picker, and in the block editor it binds to a Heading, Paragraph, Image or Button.
* Added: this works on free Elementor. Elementor ships the machinery for dynamic fields but none of the sources, so on a free install that picker is empty. EMCP fills it with ten free sources: post title, excerpt, URL, date and ID, featured image, archive title, site title, site logo and site description. Pro adds custom fields (ACF-aware), author details, taxonomy terms, and a fallback value for when a field is empty.
* Added: connected AI agents can build dynamic templates too. A new list-dynamic-sources tool shows what is available, and the existing widget and block tools accept a binding. A binding that cannot work, such as an image source in a heading, is refused when the agent makes the call rather than rendering blank later.
* Added: post excerpt, post URL, post date and post ID as new sources. Featured image existed in the code but was never listed anywhere, so nothing could use it.
Security notes below. Started as a patch for an external audit and grew into a rewrite of how raw SQL is checked. Please update.
* Changed: the read-only database guard was rebuilt around a real tokenizer. The old version turned a query into plain text and pattern-matched it, so a query only had to be read slightly differently from how MySQL reads it for something to slip past. It now inspects typed pieces of the query instead, refuses anything it cannot account for, and allows a query only if it is safe under every way the server could read it.
* Fixed: Codex and other command-line AI apps could not finish signing in, failing with "Invalid client or redirect URI". A command-line app can spell your own machine three ways (localhost, 127.0.0.1, ::1) on a port it picks fresh each run; the check accepted a changing port but insisted the spelling match exactly. All three are now treated as the same machine, and a trailing slash no longer counts as a difference. Return addresses that leave your machine, including every https one, are still matched exactly.
* Fixed: the sign-in error page now says which of the two things went wrong, the app not being recognised or its return address not matching, and shows both addresses.
* Fixed: "Manage connected apps" went nowhere when no apps were connected yet. The section is now always shown.
* Security: several tricks could hide a table name from the check (a comment marker MySQL does not treat as a comment, a backslash in a text value, a column alias in backquotes, a name in double quotes), so a query that looked harmless could still read the user table. All are closed.
* Security: the database server's own tables (mysql, information_schema, performance_schema, sys) are now off limits to the query tool.
* Security: assigning a variable inside a read-only query is refused.
* Security (Pro): the migration connector now requires a one-time pairing code. Previously, while the pairing window was open on a destination site, anyone who could reach it could pair and then push a restore. The destination now shows a code when you arm pairing, you enter it on the source, and it works once. Re-install the connector on any destination before your next migration.
* Security: AI Chat's "require approval" setting now covers every destructive tool automatically, including newer ones it previously missed, and fails closed if a tool's metadata cannot be read.
* Security: AI Chat page fetching now connects to the exact address it validated, closing a DNS rebinding gap that could reach internal services.
* Security: read-only database queries now refuse delay/lock/benchmark functions and are bounded by a server-side timeout. The row limit is applied by the database: a query with no limit of its own gets one, an oversized limit is refused rather than rewritten, and a limit inside a subquery no longer counts as bounding the result.
* Security: public OAuth client registration is now size-bounded, and throttled by peer address so a forwarded header cannot mint a fresh allowance, with a hard ceiling on registrations that never complete sign-in. Redirect URIs must be https with a real host, http on loopback, or an app's own private-use scheme.
* Security (Pro): a connector pairing created before pairing codes existed is now refused and deleted when you update, since reinstalling files does not remove stored settings. Pair the destination again.
* Security (Pro): failed pairing attempts are counted per caller, so an outsider can no longer spend the attempt budget and lock the real source site out of its pairing window.
* Security (Pro): AI Chat page fetching cancels rather than falling back to an unpinned connection on servers that cannot pin the checked address.

= 3.12.3 =
Fixes connected AI apps repeatedly opening a sign-in page that failed with "Invalid client or redirect URI".
* Fixed: Claude Desktop, ChatGPT and other MCP clients could periodically open the OAuth page by themselves, show a connection error, and never reconnect. Routine housekeeping was deleting the app's registration whenever its tokens had lapsed, so the app kept asking about a registration this site had already thrown away. Apps that have signed in once are now kept, so an expired token just means signing in again. Existing connections are protected automatically when you update.

= 3.12.2 =
Uses noticeably less memory on the front end, fixes a tool error on sites without Elementor Pro, auto-repairs AI-inserted Kadence blocks in the editor, and fixes OAuth sign-in on subfolder installs.
* Fixed: on sites without Elementor Pro, the detect-elementor-version tool returned an error instead of an answer. Since that is the first call an AI agent makes to work out which tools to use, this affected every free-Elementor site. Thanks to Winsoftpedia for the report and the fix.
* Added: Kadence blocks inserted by your AI no longer need block-by-block "Attempt recovery" clicking in the editor. Opening the post now repairs all of them automatically with the editor's own recovery (content preserved), and a single Update click stores the clean markup.
* Fixed: on a WordPress site installed in a subfolder (for example example.com/gpt-build/), connecting over OAuth from ChatGPT, Claude, or another MCP client failed because the OAuth discovery document returned a 404. It is now served correctly on subfolder installs, so the connection completes and your tools appear.
* Performance: the plugin no longer loads all of its AI tool code on every page view. The heavy MCP tool classes (roughly 120 of them, plus their audits, catalogs, guards and integrations) now load only when they are actually needed: an AI request, the admin Tools screen, WP-CLI, or cron. A normal front-end page view uses about 6 MB less memory per request, which gives sites on a 128 MB PHP memory limit real headroom. The full set of tools is unchanged for every AI, admin and CLI request.

= 3.12.1 =
Upload local images to the Media Library straight from your AI.
* Added: a new upload-media tool that uploads a file from your own computer into the WordPress Media Library (sent as base64), the companion to sideload-image which only fetches a server-reachable URL. Now you can ask your AI to upload a local image and it just works. Only standard WordPress file types are accepted, with optional alt text, title, caption, and description.

= 3.12.0 =
New Pro suite: Backup, Sync & Migrate. Back up your whole site, migrate it to a live server, and sync individual pages. Plus fixes for dynamic content and container-off sites.
* Added: Backup, Sync, and Migrate module (Pro) with its own admin tab. Back up the full site (database + files) or database/files only into one portable .emcp archive and restore it locally or from an upload. Backups and restores run in adaptive, resumable chunks so large sites finish reliably on modest and shared hosts, and restore rewrites URLs to the destination while leaving your login and wp-config untouched.
* Added: local to live Migration. Install the small "EMCP Migrate Connector" on the destination, pair the two sites, and Push & Restore. The backup transfers in resumable, firewall-safe packets and the connector restores it without ever touching wp-config or salts. Optionally remove the connector automatically when the migration completes (zero residue).
* Added: one-way Sync. Push the whole site or a selective scope (chosen database tables and/or file areas) to a paired live server.
* Added: Content Sync. A "Changes to sync" list shows the pages, posts and CPTs you changed locally; sync any one of them to your live site with a click. Its content, custom fields, terms and attached media only, matched by a stable id, with no full database import and no downtime.
* Added: seven MCP tools so an AI agent can drive the whole feature (create-backup, list-backups, migrate-site, sync-to-live, sync-content-item, list-syncable-changes, discard-sync-change). Destructive tools are disabled by default and require a confirm flag.
* Added: a server upload-limits panel on the Restore tab (with a link to the docs), and a configurable pairing window on the connector.
* Fixed: reliable large backups, downloads, and restore uploads on any host. They are driven from the browser in short, resumable, byte-offset slices that automatically adapt to your host's real limits, encoded so a firewall passes them, and memory-safe (no more "Out of Memory" tab crash). Very large or firewalled hosts can still drop the .emcp into wp-content/emcp-backups/uploads/ via FTP.
* Fixed: several migration edge cases, including a false "archive is missing" error, a mid-transfer server timeout on long pushes, a confusing error shown after a successful restore, and being unable to re-pair after a migration.
* Fixed (#112): editing any element of a Theme Builder template that used a dynamic value (post date, category, author) as a paragraph failed with "invalid_value" and locked the whole template out of the tools. Such edits now save correctly.
* Fixed (#111): on sites with Elementor's Flexbox Container feature turned off, creating a page with the tools stored an empty-rendering page. The tools now tell you to enable the feature first instead of silently creating a blank page.
* Fixed: OAuth sign-in could return a token that wasn't saved on some sites (leaving you unable to reconnect); token creation now verifies the write, repairs the database if needed, and reports a clear error instead of an unusable token.

= 3.11.2 =
Fixes pages created via MCP rendering empty on Elementor 4.2, plus connecting from staging sites whose address is pinned to a different (not-yet-live) domain, and adds an editable Server URL.
* Fixed: pages created through the tools could render completely blank on Elementor 4.2.x (front end and editor) even though the data was stored correctly. Elementor's rendered-element cache held an empty render and the plugin never cleared it; it now invalidates that cache on every content write. This also makes edits to existing pages show up immediately instead of after a manual "Regenerate CSS & Data".
* Fixed: on hosts that pin the WordPress Address to a domain that isn't live yet (common on staging, e.g. Hostinger), the downloadable bundle and OAuth sign-in used that unreachable address and couldn't connect. They now use the URL your site actually answers on (the same one shown on the Connection tab).
* Added: a "Server URL" field on the Connection tab. It auto-detects the reachable URL; override it only when your site is served on a different address than its configured WordPress Address. The value is baked into the bundle and used for OAuth and the client config examples.
* Added: an emcp_tools_public_base_url filter so agencies can set the server URL fleet-wide from a small must-use plugin.

= 3.11.1 =
Hotfix for the free/Pro activation error introduced in 3.11.0.
* Fixed: activating EMCP Tools Pro while the free version was active could still trigger a fatal error. The cause was a top-level function PHP binds at compile time — before the single-instance guard could run — so the guard is now paired with a compile-safe function wrapper. Pro activates cleanly over free again and takes over automatically.

= 3.11.0 =
A built-in Redirect Manager so an AI edit never leaves a dead URL, a header notifications center, and a fix for running the free and Pro versions side by side.
* Added: Redirect Manager. Create and manage 301/302 redirects from a new Redirects tab (or via MCP). When you delete or rename a page, the tools now suggest a redirect for the old URL so visitors and search engines don't hit a 404 — nothing is created until you confirm. Every redirect is reversible from the History tab. Ships as a module (on by default) — you can turn it off on the Modules tab.
* Added: Find broken links — a read-only scan that flags internal links pointing at trashed/missing pages or at a URL that already has a redirect, and proposes the fix.
* Added: notifications center in the header — a bell with product announcements in a slide-in drawer, plus a cloud button that shows your EMCP Cloud connection status and links to the Connection tab.
* Fixed: installing and activating EMCP Tools Pro while the free version is active no longer causes a fatal error. Pro now takes over automatically and the free version is deactivated (with a notice); activating the free version while Pro is active leaves it safely paused.
* Improved: the "Save all to Cloud" bulk sync is now surfaced in the Sandbox (push every local artifact at once).

= 3.10.0 =
Every AI change is reversible, project-memory guardrails are enforced, safer OAuth connections, and the default-on read tools no longer expose secrets — plus a batch of intelligence, theme, and cloud improvements.
* Added: project-memory guardrails are now ENFORCED — an approved block-severity memory entry with a target (tool/post/post-type) refuses a matching write. The Memory tab shows a pending-proposal count badge.
* Added: AI Chat cost guard — a per-user per-minute and per-day tool-call budget stops runaway loops.
* Added: Themer dynamic custom-field (ACF-aware) and featured-image elements, plus query-args on the archive-loop preview.
* Added: bulk cloud sync (push all local sandbox artifacts at once); multi-site per-call routing in the proxy (pass site:"alias"); normalized theme-pack capabilities descriptor.
* Added: EMCP Cloud multi-site gateway groundwork (Agency, preview) — connecting a site to EMCP Cloud can now self-issue a revocable, scoped "gateway" credential behind one disclosed opt-in consent (off if you decline), so a single AI connection can manage all your sites. No password ever leaves your site; revoke any time from Users -> Authorized Apps or your Cloud dashboard.
* Fixed: page-snapshot SEO reads Yoast/Rank Math/SEOPress (+ AIOSEO seam); build-page flags unknown widget types; corrupt _elementor_data is backed up before overwrite.
* Added: the change ledger now records and can roll back writes across every domain — pages, content, settings, global colours/typography, the Media Library (including a reversible delete that trashes the files first), Gutenberg blocks, ACF, users, filesystem, and database. Before-images are stored durably so undo points aren't evicted.
* Added: rollback won't clobber newer edits — it detects that a target changed since and asks you to confirm (force) before overwriting; the History tab shows a "roll back anyway" button.
* Added: OAuth connections survive a dropped refresh-token response (grace window on rotation), fixing the intermittent mid-chat reconnect for site-hosted OAuth.
* Added: Connection tab panel for the OAuth/Cloudflare "Couldn't register" case (paths to allow-list + Application-Password fallback).
* Security: the default-on read tools no longer expose secrets — the database query tool refuses raw reads of the user tables (password hashes/session tokens), and read-file/search-files refuse wp-config.php.
* Improved: safer database rollback (no unscoped updates; large changes flagged partial) and a single audit source (removed the old write-only fs/db audit logs).

= 3.9.1 =
GeneratePress + GenerateBlocks support and Blocksy support (both Pro), plus reordering Elementor 4 Global Classes.
* Added: GeneratePress + GenerateBlocks support (Pro). Read/write GeneratePress settings (palette, colours, layout, typography); discover the GenerateBlocks V2 blocks; and insert them with a friendly flat styles map that's turned into the styles object with the CSS compiled for you. New emcp-generatepress skill.
* Added: Blocksy support (Pro). Insert Blocksy's dynamic blocks (query/tax-query loops with auto-scaffolded template child, dynamic-data, about-me, socials, contact info, share box, breadcrumbs, search, quote, widgets area) and manage Blocksy Companion extensions (list + activate/deactivate). New emcp-blocksy skill.
* Added: reorder-global-classes (#110). Set the Elementor 4 Class Manager order (= CSS source order / specificity); omitted classes are appended, never dropped. Completes create/update/delete/reorder for v4 global classes.
* The new Pro theme integrations' write tools ship disabled-by-default (enable on the Tools tab).

= 3.9.0 =
Kadence support (theme settings, blocks, and Design Library patterns), writing Elementor 4 Global Classes, and two fixes.
* Added: Kadence support. A new Themes-tab integration for the Kadence theme and Kadence Blocks — read/write the theme's palette, typography, layout and buttons; discover and insert all 32 Kadence blocks; and build whole sections from Kadence's Design Library patterns (list-patterns / insert-pattern), which drop in Kadence's own palette-matched, fully-editable markup and can pull the pattern's images into your Media Library. Registers automatically when Kadence and/or Kadence Blocks are active; write tools disabled-by-default. Includes a new emcp-kadence agent skill.
* Added: write Elementor 4 Global Classes (#108). create-global-class / update-global-class / delete-global-class let an AI author your v4 design system — create a reusable g- class with styles, update them per breakpoint/state, or delete it. Friendly flat styles plus a raw props escape hatch; gated on Elementor's own class-editing capability; disabled-by-default.
* Fixed: Widget Builder media controls can now select audio and video (#107). Add media_types (e.g. ["audio"]) to a media control to open the right Media Library picker instead of Elementor's image-only default — including for a media control inside a repeater. Also fixes the bundled Audio Player widget.
* Fixed: the Sandbox "Push update" button now appears for artifacts published before update-tracking existed (they previously showed "Up to date" forever); the state self-heals after the first push.

= 3.8.2 =
Fixes a fatal error when activating Elementor after EMCP Tools, plus OAuth token/client housekeeping.
* Fixed: activating Elementor after EMCP Tools no longer triggers a fatal error. Elementor's activation creates its default kit before its document manager is ready; the EMCP search indexer now skips cleanly until Elementor is fully initialized instead of dereferencing a null manager (#105).
* Fixed: OAuth housekeeping. Expired tokens are now purged on a schedule (they previously accumulated indefinitely); repeat connections from an MCP client reuse their existing client registration instead of creating a new row every time; orphaned registrations are pruned after a grace period; and the OAuth timestamp columns are now 2038-safe.
* Fixed: connection reliability. On sites with error display enabled, a stray PHP notice printed into an MCP/OAuth JSON response could corrupt it and drop the connection. The MCP and OAuth routes now force on-screen error display off for the request (errors still log), so the JSON response is always clean.

= 3.8.1 =
Cloud Library — browse and import your saved artifacts on any connected site — plus two reliability fixes.
* Added: Cloud Library. Each Sandbox screen (Blocks / Widgets / PHP Snippets) gains a Cloud Library panel that lists every artifact of that kind saved to your EMCP Cloud account across all your connected sites, and imports one into this site as a new inactive draft to review — completing the cross-site half of Cloud sync. The Import-a-bundle and Cloud Library rows are now prominent cards.
* Fixed: the Marketplace "Review it" link after installing an item no longer just reloads the page — it opens the artifact's Sandbox management screen.
* Fixed: recurring "Reconnect needed" for EMCP Cloud (follow-up to 3.8.0). Under heavy concurrency the refresh lock could time out and a second request would still present the just-rotated refresh token, which the provider treats as reuse and invalidates the whole token family. The plugin now never presents a refresh token when it can't acquire the refresh lock, closing the last rotation-race hole.

= 3.8.0 =
EMCP Cloud — back up, sync, and share your work — plus OAuth/connector reliability fixes and Forminator support.
* Added: EMCP Cloud (Pro). Connect your site to an EMCP Cloud account to back up your Sandbox blocks, widgets, and PHP snippets, sync them across every connected site, and publish them to a community/Pro Marketplace. A new Marketplace admin tab browses published artifacts (search, type/category/access filters, and the author's avatar, name, and Verified badge) and installs them as drafts to review. One-click "Save to Cloud" buttons on the Sandbox back up an artifact, and a "Publish to Marketplace" button (enabled once an artifact is backed up) takes you to the website's submission page for it.
* Added: live Sandbox cloud controls. Each artifact shows its real cloud state — Save to Cloud / Update cloud / Saved — plus Publish to Marketplace, View on Marketplace, and Push update to re-publish an edited listing for review. A page-level "Refresh cloud status" button resyncs every artifact against the cloud and clears a stale "Saved" if it was deleted from the cloud elsewhere.
* Added: Settings Sync (paid Cloud). Copy your EMCP settings — tool toggles, active modules, compact-tool mode, and preferences — between connected sites from the Connection tab. Secrets, API keys, and the site's own connection are never synced.
* Added: Forminator integration (Pro). Two dispatcher tools (forminator-read / forminator-write) list forms with their shortcodes, read fields and submissions, and delete a submission (confirm:true). Verified against Forminator 1.56.2.
* Added: MCP Log admin tab. A record of recent MCP requests (tool, status, duration, request id) so a connector failure can be matched to a server-side outcome; with WP_DEBUG on it also captures the underlying error.
* Added: get-page-structure gains summary and max_depth options so very large pages return a shallow, workable tree instead of timing out.
* Added: build-page gains a dry_run option that validates a structure and reports the element count and any coerced or skipped widgets without writing a page, and warns when a page is large enough to risk a remote-connector timeout.
* Added: sideload-image and add-stock-image gain a convert_webp option — set false to skip WebP conversion when it is timing out on shared hosting.
* Changed: the Connection page is reorganised into three tabs — MCP, Cloud, and 3rd Party Services — with the EMCP Cloud connect and settings-sync section moved into its own Cloud tab. History moved next to Changelog in the app bar.
* Changed: Sandbox artifacts (snippets, widgets, blocks, PHP theme templates) now live in one dedicated wp-content/emcp-sandbox directory instead of being scattered under wp-content/uploads. Existing sites are migrated automatically and safely on update — active artifacts keep working with no rebuild.
* Changed: the dashboard gains Cloud and Pro promo banners and a permanent top strip; the MCP Log moved into the app bar; "Affiliate Program" is now just "Affiliate"; Marketplace cards show the full 1200x630 artifact screenshot.
* Fixed: spurious "Reconnect needed" for EMCP Cloud caused by two concurrent OAuth token refreshes (the provider rotates the refresh token each time, so one request poisoned the connection). Refresh is now serialized and rotation-race-safe, and a transient blip no longer flips the connection to "Reconnect needed".
* Fixed: OAuth-connected MCP clients no longer disconnect mid-chat when a token refreshes. The previous access token is left to expire on its own short lifetime instead of being revoked instantly, so in-flight tool calls are not dropped. App Password connections were unaffected.
* Fixed: stale-domain connectors now fail loudly. If a connector is left pointed at an old domain after a site URL change, the MCP endpoint returns an explicit "Site URL mismatch" error (HTTP 421) with the correct URL instead of a generic tool-execution failure.
* Fixed: intermittent "connector isn't responding" on shared LiteSpeed hosting. The MCP route now sends no-store/no-cache headers so LiteSpeed/QUIC never caches or buffers MCP responses; a new FAQ covers cache-exclusion and PHP-limit tuning.

= 3.7.0 =
Custom Gutenberg blocks, plus a memory so your AI stops guessing.
* Added: Gutenberg Block authoring in the Sandbox (Pro). Your AI agent designs a custom block from a structured spec, the plugin compiles it into a sandboxed dynamic block under wp-content/uploads (never your theme or core), and you review and activate it before it goes live. Active blocks appear in the editor inserter under "EMCP Custom" with a live preview and a Settings panel. The Sandbox page is now split into Blocks, Widgets, and PHP Snippets, each with its own management screen. Blocks and widgets can be exported and imported as portable JSON bundles.
* Added: Agent Project Memory (Pro). The plugin now remembers your site across agent sessions so a connected AI stops guessing. Approved guidance (guardrails, facts, conventions, instructions) is injected into the agent's context, and three MCP tools let the agent recall what it knows, propose new guidance, and save a session summary. Agent-proposed guidance stays inactive until you approve it on the new Memory tab, which is the hallucination gate. Session history is built automatically from the change ledger.

= 3.6.5 =
A Claude Desktop friendly skills download.
* Added: a Claude Desktop skill bundle. Claude Desktop uploads one zip per skill, so this download packages each skill as its own zip inside a single archive. The folder bundle is still there for Claude Code and Cursor.

= 3.6.4 =
New AI client setups plus an OAuth flow improvement.
* Added: Connection setup for OpenClaw and Hermes, each with direct-HTTP and Node-proxy config in App Password and OAuth modes.
* Changed: OpenClaw config is shown as the mergeable "mcp" block, since openclaw.json usually already has other settings.
* Changed: OAuth authorization codes now last 5 minutes instead of 60 seconds, so CLI clients that require pasting the code back by hand (like OpenClaw) are not racing a one-minute timer. Codes stay single-use and PKCE-bound.

= 3.6.3 =
Maintenance re-release. No functional changes from 3.6.2; corrects the distributed package.

= 3.6.2 =
Atomic-widget fixes found by auditing every atomic element, UAE Pro support, and a refreshed upgrade banner.
* Fixed: #102, paragraph text written under an alias name (content) was dropped. Elementor advertises alternative prop names but discards unrecognised keys without error, so the text vanished the first time a page saved successfully. Alias names are now mapped onto the name Elementor stores before saving.
* Fixed: whole-page repair. 3.6.1 only converted the widget you edited, but Elementor validates the entire page on save, so one un-converted widget elsewhere blocked the repair. Conversion now runs across the whole element tree, and legacy button links are mapped to the shape Elementor expects.
* Fixed: self-hosted video could not be placed at all, and image alt text was discarded. The video source needed a typed shape rather than a plain URL, and alt belongs inside the image, not at the top level. Both were found by sweeping every atomic element.
* Fixed: container background, padding, margin and text colour were discarded because they used names that are not Elementor style props. They now use the correct names and shapes and render.
* Fixed: the Ultimate Addons for Elementor tools were missing on sites running UAE Pro, which is a separate standalone plugin from the free one. Either plugin now activates the integration; template tools appear only when the free plugin (which owns the templates) is present. (Pro)
* Added: build-page now builds Elementor v4 atomic widgets from the same friendly params the add-atomic-* tools accept, so images and videos are no longer skipped.
* Changed: the dashboard upgrade banner now reflects the current Pro feature set (AI Chat, plugin integrations, SEO and accessibility, the widget builder, prompts and templates) instead of the old landing-pages pitch.

= 3.6.1 =
Two fixes for problems that could block real work.
* Fixed: #101, editing an Elementor v4 atomic widget could lock the page. Passing a plain text value (the natural thing for an AI to do) was stored raw, so widgets rendered placeholder text and every later save failed with "Settings validation failed". Plain values are now converted to the shape Elementor expects, and pages already damaged by this are repaired on the next edit.
* Fixed: tool results that are a list were rejected by some AI clients. structuredContent must be a JSON object per the MCP spec; lists and single values are now returned under a "data" key. Affected several WooCommerce reads (product, order and customer lists). Reported upstream as WordPress/mcp-adapter#253.

= 3.6.0 =
Elementor addon plugins over MCP, plus a fix for a fatal that could lock you out of wp-admin.
* Added: Elementor Addons domain. Essential Addons and Premium Addons get a discovery tool each (their widgets are placed with the ordinary add-free-widget); Ultimate Addons for Elementor, formerly Header Footer Elementor, gets a read/write pair covering both its widgets and its header/footer templates with display conditions. (Pro)
* Added: filtered widget schemas. A single addon widget can report 400 to 700 controls, so get-widget-schema returns content-bearing controls by default and reports total_controls vs shown; pass full:true for everything. (Pro)
* Added: EMCP Themer / Ultimate Addons conflict handling. Both build headers and footers into the same slots, so an admin notice explains the clash and EMCP Themer takes priority per slot until you pick one. Any slot Themer does not claim still renders from Ultimate Addons.
* Added: new agent skill emcp-plugins/addons covering the discover, inspect, place workflow and the template-record versus template-content split. (Pro)
* Fixed: #100, fatal error on every wp-admin page load and REST request. Server-side malware scanners were quarantining our malware-audit file, because a malware scanner necessarily contains the webshell signatures it looks for. Signature literals are now assembled at runtime, so no intact signature sits on disk; detection is byte-identical.
* Fixed: a missing class can no longer take down the site. The security scanner builds its audits lazily and ability registration is guarded, so one unavailable tool group degrades to those tools being absent instead of fataling wp-admin.

= 3.5.1 =
A redesigned AI Chat (local models, vision, skills) + three connectivity/builder fixes.
* Added: AI Chat gets a full-width two-column settings screen (built-in controls + default model on the left, providers on the right). (Pro)
* Added: AI Chat providers DeepSeek, Moonshot, Z.ai, plus local runtimes Ollama and LM Studio, auto-detected on localhost, no API key needed. Refresh-models button; remembers your last provider/model. (Pro)
* Added: AI Chat loads the EMCP agent skills, and handles images for text-only models via a local vision model or a built-in dependency-free image reader. (Pro)
* Changed: AI Chat hardened for small local models, lean tool schemas, capped tool results, empty-turn recovery, reasoning-stream capture, one-page-per-request and background-format guidance; list-skills is now a compact catalog. (Pro)
* Fixed: #99, a different MCP-adapter version bundled by another active plugin (e.g. Rank Math) caused "McpServerError: Session terminated"; EMCP now serves the whole WP\MCP namespace from its bundled copy so the server starts regardless of load order.
* Fixed: #97, duplicating a v4 atomic element now re-mints its local style classes so the copy keeps its styling.
* Fixed: #98, verify-and-fallback when Elementor's document save reports success but persists nothing (CLI/proxy), so edits aren't silently lost.
* Fixed: backgrounds emitted as a nested/array shorthand now render, the factory coerces them to Elementor's flat keys and adds the required classic activator (the "sideloaded photo doesn't show" cause).
* Fixed: build-page no longer silently drops shorthand nodes (accepts them, returns warnings); sideload-image resolves api.unsplash.com download URLs with actionable errors; clearer web-fetch SSL/timeout errors.

= 3.5.0 =
Forms + SEO plugin integrations, 15 plugins over MCP.
* Added: Forms integrations, two dispatcher tools per plugin (read/write) for 8 form builders: Contact Form 7 (free) plus WPForms, Gravity Forms, Fluent Forms, Ninja Forms, Formidable, MetForm, and SureForms (Pro). Read forms, fields, notifications and entries (submissions); manage entries. Each pair registers only when its plugin is active; reads on by default, writes off by default; delete-entry needs confirm:true. CF7 has no entries; WPForms entry ops need WPForms Pro.
* Added: SEO integrations, two dispatcher tools per plugin (read/write) for 7 SEO plugins: Slim SEO (free) plus Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, and SureRank (Pro). Read/write the SEO metadata each plugin stores (title, description, canonical, robots, focus keyword, Open Graph / Twitter) for posts and terms, over one unified field vocabulary, plus schema-read where supported. Distinct from the SEO & Accessibility toolkit (which audits/generates).
* Changed: Pro tool sections (WooCommerce, SEO & Accessibility, Widget Builder) now always appear on free builds, Pro-badged, disabled, with an Upgrade link, so it's clear what Pro unlocks.
* Changed: Dashboard "Your usage" is now a 4-card activity panel (Usage, History, Most used, Sandbox). The Plugins tab is grouped by category (Dynamic Content, E-Commerce, Forms, SEO).
* New agent skills: emcp-plugins/forms and emcp-plugins/seo (Pro).

= 3.4.2 =
WooCommerce integration + template & prompt usage insights.
* Added: WooCommerce Pro integration, two tools (woo-read / woo-write) over the full wc/v3 surface (~120 operations): products, variations, orders, refunds, customers, coupons, reports, settings, shipping, taxes, webhooks, system status. Runs through WooCommerce's own REST (HPOS-safe). Reads on by default; writes off by default; refunds/deletes/batch require confirm:true. Registers only when WooCommerce is active.
* Added: Meta Box integration (community PR #96), two tools (metabox-read / metabox-write) to read/write Meta Box custom-field values and discover field groups over MCP. Values only (no delete, no field authoring); registers only when Meta Box is active; metabox-read on by default, metabox-write off. Thanks @Mrshahidali420.
* Added: History tab, delete individual entries and a "Clear all" button to wipe the change ledger. Confirm-gated; deleting an entry that can still be rolled back warns you first.
* Fixed: the Affiliate Program link in the header led to "you are not allowed to access this page" on fresh installs, Freemius only registers that page after the user opts in or skips, so the link now shows only when the page exists.
* Added: "Used N times" usage counts on Pro template cards, plus a "Your usage" widget on the Dashboard (templates you've applied and prompts you've copied on this site + the globally-popular templates). Lightweight, non-blocking telemetry (license + site only; no page content, customer data, or AI conversations are ever sent).

= 3.4.1 =
OAuth sign-in for MCP clients + atomic-element and compact-mode fixes.
* Added: OAuth sign-in for AI clients (free). Claude and other MCP clients connect through a standard OAuth 2.1 flow, approve access from your WordPress login, no Application Password to copy. Self-contained authorization server (discovery, dynamic client registration, PKCE, admin-only consent, short-lived + refresh tokens); the token acts as the authorizing admin, and Application Passwords keep working alongside it. HTTPS-gated, on by default when available.
* Added: Connection tab authentication-method chooser (OAuth vs Application password) with per-client setup steps (mcp-add command, custom-connector walkthrough with one-click deep links, or config-file snippet), a Connected apps list with Revoke, and a Claude.ai client.
* Fixed: OAuth discovery 404, MCP clients request the resource-scoped well-known path (RFC 9728), which 404'd and broke OAuth with real clients; now served.
* Fixed: get-page-snapshot returned zeros on atomic (v4) pages (#91), now extracts atomic heading/paragraph/button/image content.
* Fixed: atomic style-class writes were persisted but never applied (#92), writes now reference the class on the element.
* Fixed: compact tool mode surfaced 6 tools instead of 3 (core context abilities now fold into the dispatcher catalog).
* Fixed: subdirectory WordPress installs (e.g. example.com/subdir) were unreachable via the Node proxy (#93), it ignored the URL path, building every request against the domain root; the proxy now honors the site base path (root installs unchanged) and returns a JSON-RPC error instead of corrupting stdio framing on a non-JSON upstream body. Proxy 1.9.1. Thanks @carstenbaumhoegger.
* Changed: renamed the Codex connection card to "ChatGPT App" and corrected its OAuth setup to the app's in-app MCP flow (Add server → Streamable HTTP → Save → Authenticate → Approve). The Application Password (config.toml) method is unchanged.

= 3.4.0 =
New Themes domain, WP-CLI tools, SVG uploads, and 50 starter templates.
* Added: Themes tab with theme integrations on the ACF two-dispatcher pattern (one Read tool, one Write tool, each bundling internal operations). Active Theme (theme-read / theme-write, any theme): get-theme-context (framework, block-theme, supports, menu locations, child status), get-mods / set-mods (theme_mod values, structural keys refused), and create-child-theme (scaffold + activate a child of the active parent so the agent can edit theme files via the Filesystem tools; requires confirm:true, idempotent). Astra (astra-read / astra-write, when Astra is active): generic get-settings / update-settings over a curated astra-settings allowlist (colors, typography, layout, header/footer). Reads on by default; the two write dispatchers ship disabled-by-default. More framework packs (Kadence, GeneratePress) build on the same base.
* Added: WP-CLI tools (4 tools, disabled-by-default, manage_options): run-wp-cli (synchronous), dispatch-wp-cli (background job), get-wp-cli-job, list-wp-cli-jobs. A command validator blocks destructive/host-level commands (eval, shell, db query/import/export/reset, config set, package/cli mutations) and dangerous flags; runs in-process when available, else via a spawned shell. Every run is recorded to the change ledger.
* Added: SVG Uploads module (opt-in): allow sanitized SVG/SVGZ uploads to the Media Library. Every uploaded SVG is scrubbed with enshrined/svg-sanitize (scripts, event handlers, external references removed) and rejected if it can't be made safe. Fixes the common programmatic-upload failure where WordPress mis-detects SVG as a text file.
* Added: get-block-schema now surfaces Spectra's shared-helper attributes so Gutenberg/Spectra blocks report their real attribute names and defaults.
* Changed: search-images and sideload-image are now core WordPress tools, they register on any site regardless of Elementor (they only need a stock-provider API key and the Media Library).

= 3.3.0 =
Foundation release: understand a page from one call, undo any change, and reuse existing content.
* Added: get-page-snapshot (always-on, read-only) returns one normalized digest of a page (structure tree + counts, global colors/typography/classes actually in use, per-device responsive overrides, content outline, and an SEO-lite summary) so an AI agent can reason about a page from a single call. Opt-in performance/accessibility/SEO audit summaries via include:[performance,a11y,seo] (a11y/seo are Pro); heavy sections are transient-cached.
* Added: AI-safe transactions: a unified change ledger + rollback (list-changes / get-change / rollback-change). Every AI-made Elementor edit, filesystem write, and database write is recorded, and any recorded change can be undone (restore a page's prior data, restore/remove a file from its backup, or inverse a database write from its before-image). Requires manage_options; the ledger is capped by count and size.
* Added: Content search (search-content / reindex-search): find the site's own pages, templates, widgets, and global styles by natural-language query so an AI agent can reuse existing content instead of rebuilding it. Lexical, field-weighted ranking over a materialized index that also updates when pages/templates are saved.
* Added: Content mirror (export-content / restore-content / list-content-exports): export Elementor page/template content to git-trackable JSON files for external version control, with an opt-in auto-export-on-save toggle. The plugin maintains the files; your git/CI versions them.
* Added: Multi-site proxy (@msrbuilds/emcp-proxy 1.9.0): drive several WordPress installs from one MCP connection via a site registry (EMCP_SITES) with emcp_list_sites / emcp_use_site to switch sites mid-session.

= 3.2.1 =
Atomic-element write fixes + a new ACF / ACF PRO integration.
* Fixed: add-atomic-image / add-atomic-svg no longer reject a valid image_id / image_url. The image envelope now matches Elementor's Image_Src prop type (id XOR url, image-attachment-id), and e-svg uses its own svg-src prop (#74).
* Fixed: an atomic element's local styles map and editor_settings (Navigator label) are now writable: update-element / batch-update / update-atomic-widget hoist those sibling-root keys to the element root instead of silently dropping them into settings (#72, #73).
* Added: set-element-label: a convenience tool for renaming an element in the Navigator (editor_settings.title).
* Added: ACF / ACF PRO integration exposed as 2 dispatcher tools, acf-read and acf-write, that register only when Advanced Custom Fields (free or PRO) is active. Call either with no operation to list its operations, then again with { operation, arguments }. 15 operations in total: 8 read (field groups, field values, options pages, and, on ACF 6.1+, ACF-managed post types and taxonomies) and 7 write (field values, field groups, and ACF 6.1+ post types / taxonomies). acf-read is enabled by default; acf-write ships disabled-by-default. Each dispatcher is a single toggle under Tools → Plugins → ACF (listing its operations), and every operation keeps its original capability check (enforced per call by the dispatcher).
* Added: full ACF PRO field support: repeaters, flexible content (rows validated against the field's layouts), galleries, groups, and clones round-trip as nested JSON; options pages are targetable via options_page. Pro-only features degrade gracefully on free ACF.
* Added: ACF-managed Custom Post Types and taxonomies are registered as data through ACF (acf_import_post_type / acf_import_taxonomy). No PHP is written or executed. Together with the field operations this is enough to build a full content structure end-to-end: CPT to taxonomy to field group to posts with values.
* Added: conservative authoring guardrails: no delete operations; fields can never be removed; a field's name/key/type, or a post type / taxonomy slug, can never change (renames orphan stored content); code-registered (acf-json/PHP) groups are refused by the group editor; the CPT/taxonomy read operations list only the ACF-managed ones.
* Added: a new "Plugins" sub-tab on the Tools screen, with an "ACF (Advanced Custom Fields)" section showing the two dispatcher toggles (acf-read on, acf-write off), each listing the operations it covers.
* Added: set-social-image (Pro SEO): sets the Open Graph + Twitter/X share image for a page via the active SEO plugin's meta (Yoast: opengraph-image(-id)/twitter-image(-id); Rank Math: facebook/twitter image(_id)), so link previews use the image you choose instead of the first content image Yoast otherwise keeps. Disabled-by-default with the SEO toolkit. (#82)
* Added: a full_bleed flag on add-container that seeds an edge-to-edge container recipe (full content width, 100% width, zero padding, zero flex/gap, column + stretch), the correct top-level container for Elementor Canvas pages, where the boxed defaults leave white strips. Explicit settings still override the preset. (#83)
* Fixed: list-posts now accepts array post_type / status (multi-type and multi-status queries) instead of stringifying arrays to "Array" and silently returning wrong or unfiltered results. (#80)
* Fixed: get-post now returns protected meta keys a site opts into via the emcp_tools_content_allowed_protected_meta filter, matching create/update-post (read-after-write no longer broken). (#77)

= 3.2.0 =
A core-engine release, and a rewrite of the Premium Prompts.
* Added: all 50 Premium Prompts rewritten. Instead of dictating a section-by-section Elementor layout, each prompt now gives the AI a style guide, a design direction, the exact content, and hard standards (WCAG 2.1 AA, real photography in every slot, one consistent SVG icon set, builder-native construction, a completeness rule, and a lead-capture form), then lets it design the page. Expect more distinctive results.
* Added: the prompts are builder-agnostic. Change the first line of any prompt from Elementor to Gutenberg, Bricks, or plain HTML/CSS and the rest still applies.
* Added (Pro): "Download v1 Prompts" on the Prompts screen, the original 50 prompts as an archive, for anyone who prefers the older, prescriptive style. License-, capability- and nonce-gated.
* Added (Pro): web_fetch in the AI Chat: the model can read a URL you give it (a reference design, a competitor page, docs, a JSON endpoint, or a stylesheet). Fetching a page also returns the absolute URLs of its stylesheets, so the AI can read a design's colours and type scale from source. It cannot search the web; it needs a URL. Works in the Chat tab and both editor panels.
* Security: web_fetch runs on your server, so every URL and every redirect hop is validated first: http(s) only, no credentials in the URL, ports 80/443 only, and refused if any resolved address is loopback, private, link-local (including the cloud-metadata address), CGNAT, multicast or reserved. Responses are size-capped, limited to text, never executed, and handed to the model labelled as untrusted data.
* Fixed: updated the bundled Freemius SDK from 2.13.3 to 2.13.4. Freemius withdrew 2.13.3 after finding a regression in its add-on checkout flow. This plugin does not use Freemius add-ons, so it was never affected, but it should not ship a version the vendor unpublished.
* Fixed: plugin scripts and styles are now versioned by their own file modification time, so a changed asset is never served stale from the browser cache between releases.
* Changed: the Prompts screen shows a one-time, per-user notice explaining the rewritten prompts.
* Added: Compact tool mode (opt-in Tools-tab toggle): surfaces 3 dispatcher meta-tools (list-tools / get-tool-schema / call-tool) instead of ~140 individual tools, so clients that cap tool counts can still reach the whole surface. call-tool delegates each tool's own permission check; your per-tool toggles still apply. Replaces the old Low-tools mode.
* Added: richer discovery context: the server description now includes a compact environment summary (WordPress/PHP/Elementor versions, atomic-element support, notable active plugins) so agents orient without extra calls.
* Added (Pro): Agent-facing Skills: the bundled Agent Skills are now discoverable and loadable by any connected MCP agent at runtime via two read-only tools, list-skills and get-skill, plus a Skills catalog in the discovery context. The bundle grows to 7 skills (Elementor page building, Gutenberg, EMCP Themer, performance, security, SEO & accessibility, PHP snippets). The Skills page now shows two ways to use them: install locally or load at runtime.
* Added (Pro): Agent Skills module (Modules tab, on by default): the on/off switch for that runtime exposure; turn it off to remove the injection (the local Skills download is unaffected).
* Changed: the bundled MCP Adapter is now a Composer dependency loaded via the Automattic Jetpack Autoloader, which arbitrates the highest adapter version process-wide when multiple active plugins bundle it (WooCommerce, Automattic MCP), with no "class already declared" clashes regardless of load order.
* Changed: removed Low-tools mode (superseded by Compact tool mode).
* Fixed: a clean install no longer emits a "Failed to open stream" notice from the autoloader's file map (regenerated without dev dependencies); trimmed non-runtime files from the shipped vendor bundle.

= 3.1.3 =
A bug-fix patch (thanks @gthibo for two detailed reports).
* Fixed: the "update available" notice no longer persists after you update the plugin. The free GitHub updater compared against the compiled version constant, which is stale in the same request right after a self-update; it now uses the version WordPress parsed from the plugin header and clears the update transient immediately.
* Fixed: MCP tool-name collision: the EMCP Themer and Elementor Pro Theme Builder tools both claimed create-theme-template / set-template-conditions, so one was silently dropped. The Elementor Pro tools are renamed to create-elementor-theme-template / set-elementor-template-conditions; the Themer keeps the originals. (#71)
* Fixed: debug-log noise ("Ability ... not found") from the admin tool-catalog drift check on environment-gated tools (resize-media, Themer PHP). Now uses a silent registry check. (#71)
* Fixed: the Node proxy (@msrbuilds/emcp-proxy) corrupted non-ASCII text on large responses (multi-byte characters split across network chunks). Now buffers and decodes once. Update with npx @msrbuilds/emcp-proxy@latest (>= 1.8.4). (#70)

= 3.1.2 =
A bug-fix patch for three community-reported issues (thanks @Mrshahidali420).
* Fixed: MCP endpoint 404 when WooCommerce 10.5+ is active: WooCommerce autoloads the same MCP Adapter but only boots it behind its own off-by-default flag; EMCP now boots the already-loaded adapter itself so /wp-json/mcp/emcp-tools-server works regardless. (#64)
* Fixed: move-block could silently delete a block when moving it inside a later container or across levels (the target index shifted on removal). The index compensation now covers every mode/depth, and a move into the block's own subtree is a no-op. (#67)
* Fixed: write-file/edit-file/delete-file now invalidate OPcache after writing PHP, so edits take effect immediately on hosts with opcache.validate_timestamps=0 instead of running stale bytecode. (#66)

= 3.1.1 =
A follow-up patch to 3.1.0.
* Added: Resize images over MCP: the Image Optimization module adds an in-place image resizer and a resize-media tool (scale to fit, or crop:true for exact width x height); the original is backed up (reversible) and all sub-sizes + WebP are regenerated. Registers only when the Image Optimization module is enabled.
* Added: Themer PHP Templates review screen is now a full editor: CodeMirror (PHP syntax highlighting) plus Title and Type fields; saving re-validates and recompiles an attached template.
* Added: Codex connection help: a field-by-field guide for Codex's "Custom MCP" form, and a Node-proxy (npx) config.toml option (via @msrbuilds/emcp-proxy) alongside the streamable-HTTP config.
* Added: EMCP Themer now shows a Free-limits banner (free installs only) on the templates screen, a per-type usage chip for each slot (Header/Footer/Single/Archive/Search/404, used/cap) so the 1-per-type limit is visible before you hit it, with a one-click Upgrade to Pro for unlimited templates and granular conditions.
* Changed: Generated MCP client configs now name the server after the site's domain (e.g. emcp-your-site-com) instead of a fixed "emcp-tools", so connecting several sites in one AI client no longer collides.
* Changed: Bundled Freemius SDK updated 2.13.2 -> 2.13.3; optimized an oversized bundled admin logo (~700KB smaller download).
* Changed: Modules now appears before Tools in both the EMCP Tools sidebar submenu and the page header nav.
* Fixed: Changelog page no longer errors ("Sorry, you are not allowed to access this page." / "Cannot load..."). Removing it from the sidebar had broken both its access check and its render hook; it is now kept as a normal submenu (renders + reachable by URL) with only its sidebar row hidden via CSS.
* Fixed: Codex config.toml now uses http_headers (not headers) so the generated config connects.
* Fixed: Themer "Render with PHP template" dropdown updates live when the template type changes (new templates no longer stay on "choose a type first").

= 3.1.0 =
A big feature release: a builder-agnostic theme builder (EMCP Themer), a pluggable Modules framework (with Image Optimization), 10 always-on Gutenberg block tools, an in-editor AI Chat panel for the block editor, a dedicated plugin Dashboard, and in-dashboard updates for free users via GitHub releases.
* Added: EMCP Themer: a builder-agnostic theme builder (free, on by default). Design Header / Footer / Single / Archive / Search / 404 layouts with any page builder (Gutenberg, Elementor, ...), attach display conditions, and the plugin injects them on the front end. One CPT with its own dashboard menu; a slot-based resolver (header/body/footer) picks one winner per slot by condition specificity, priority, then newest. An Elementor-style step-wise condition builder drives the metabox and warns on overlapping same-type templates. Free = the whole engine + 1 template per type + broad scope selectors + all 8 MCP tools. Per-theme header/footer adapters for Astra/GeneratePress/Kadence/OceanWP/Blocksy/Neve/Hello. Dynamic content elements (Post/Archive Title, Breadcrumbs, Post Meta, Site Logo/Title, Menu, Description, Post Content, Archive Posts) ship for both builders.
* Added: EMCP Themer PHP Templates (free, off by default). A connected AI agent can author a fully custom PHP region template (header/footer/single/archive) into a validated, hash-verified sandbox; you then select it on a template to take over that region's render. The human selection is the execution gate. An AI can only create drafts, and there is deliberately no attach tool. The strict validator rejects code execution, file loading, network, and file writes. Gated behind a Tools-tab master switch; adds 5 disabled-by-default MCP tools and a PHP Templates review screen.
* Added: Gutenberg block tools (10, always-on). A block-editor counterpart to the Elementor family, pure WordPress core: list-blocks, get-block-schema, get-post-blocks, list-patterns, add-block, update-block, remove-block, move-block, duplicate-block, insert-pattern. All enabled by default (only remove-block badged destructive).
* Added: Modules framework: a pluggable feature system an admin turns on/off from a new Modules tab. Ships with an Image Optimization module (free, opt-in): compresses image sub-sizes on upload and generates .webp siblings via WP_Image_Editor (no external binaries), serves WebP in REST/CLI + optionally on the front end, preserves originals (reversible), plus a resumable bulk optimizer + restore. Prompts, Brand Kits, and Templates are modules too.
* Added: AI Chat in the block editor (Pro). The AI Chat editor panel now runs in the Gutenberg editor as well as the Elementor editor. AI Chat is now a proper module (a true on/off kill switch).
* Added: Plugin Dashboard: a dedicated landing screen with headline stat cards, an "Explore your toolkit" grid, featured video guides, a Help & resources panel, and a version / update-available indicator.
* Added: In-dashboard updates for free users via GitHub releases: free installs now get native "update available" prompts (and auto-updates). Premium builds continue to update via Freemius.
* Added: Admin-bar MCP status node (green/grey/red) with a one-click toggle to enable/disable MCP exposure.
* Added: Self-hosted Geist font for the plugin admin headings and accents (SIL OFL, no third-party CDN).
* Changed: The admin now opens on the new Dashboard tab; Tools moves to its own tab.
* Fixed: Themer body templates now fill the theme's content column on flexbox-container themes (e.g. Astra's two-container layout) instead of leaving empty space beside the content.

= 3.0.0 =
The first major release of the rebranded EMCP Tools, a step beyond Elementor into general WordPress management, plus a leaner catalog-backed widget surface. This single 3.0.0 release bundles the MCP namespace rename, the widget consolidation, and beyond-Elementor domains: WordPress Content (domain 1), Settings (domain 2), Plugins & Themes (domain 3), Media Library (domain 4), Users (domain 5), Performance & Security (domain 6, a Performance Analyzer plus a Security & Malware Scanner), Filesystem (domain 7), and Database (domain 8). (Previous release: 2.2.0.)
* Changed (BREAKING): MCP namespace + server route renamed elementor-mcp -> emcp-tools. Every tool is now under the emcp-tools/ ability namespace (tool names become emcp-tools-<tool>), and the server route moved from /wp-json/mcp/elementor-mcp-server to /wp-json/mcp/emcp-tools-server (WP-CLI --server=emcp-tools-server). Every existing AI-client connection must be reconnected with the new route. Regenerate configs from EMCP Tools > Connection. Stored per-tool toggles migrate automatically to the new slugs.
* Changed (BREAKING): Widget tools consolidated. The 62 per-widget convenience tools (add-heading, add-button, add-form, ...) and the universal add-widget are removed, replaced by 5 catalog-backed tools: list-widgets (now with tier/category/search filters), get-widget-schema (curated params by default, types[] batch, full:true escape hatch), add-free-widget, add-pro-widget, and update-widget. No capability is lost. Every widget and every curated parameter is still reachable via discover -> inspect -> act. AI scripts that hardcoded an old tool name must switch to add-free-widget / add-pro-widget with a widget_type.
* Changed: Elementor is now OPTIONAL. The plugin and all beyond-Elementor tools (WordPress content, plugins & themes, users, media, performance, security, filesystem, database) work without Elementor. The Elementor tool family registers only when Elementor is active; otherwise the admin shows a warning, and the Brand Kits / Templates tabs show a notice.
* Changed: Tools admin page reorganized into Elementor / WordPress sub-tabs. Tool categories are now grouped under two tabs: Elementor (page-building tools + Accessibility) and WordPress (Content, Settings, Plugins & Themes, Users, Stock & Media, PHP Snippets, SEO), so the growing tool set is easier to manage. Presentation only; no change to which tools are enabled or how they're gated.
* Added: WordPress Content tools: the first step beyond Elementor (domain 1). Eight new MCP tools to manage general WordPress content: list-post-types, list-taxonomies, create-post, get-post, update-post, list-posts, delete-post, and set-post-terms. Create and edit posts, pages, and any custom post type (title, content (classic HTML or block markup), status, slug, author, taxonomy terms, custom fields, and featured image) without touching Elementor data (an is_elementor flag steers you to the Elementor tools for builder pages). Capability-gated and enabled by default; delete-post trashes by default (pass force to permanently delete).
* Added: WordPress Settings tools (beyond Elementor, domain 2). Two new MCP tools over a curated, typed allowlist of core WordPress settings: get-settings (read general/reading/writing/discussion/media/permalinks settings; doubles as discovery: returns each setting's type, label, enum options, and writable flag; manage_options, read-only) and update-settings (batch-update allowlisted settings; non-allowlisted, read-only, or invalid values are reported in skipped[] without aborting the batch; changing a permalink setting auto-flushes rewrite rules; manage_options). Safety: curated allowlist only, with no arbitrary option access; siteurl/home and users_can_register/default_role are excluded; admin_email is read-only.
* Added: WordPress Plugins & Themes tools (beyond Elementor, domain 3). Thirteen MCP tools to discover, install (wordpress.org only), update, activate/deactivate, and delete plugins and themes. Guardrails: EMCP Tools and Elementor can never be disabled/deleted; the active plugin/theme is protected; per-op capability gating; direct-filesystem-only. The 2 read tools + 2 search tools are enabled by default; the 9 mutation tools ship disabled-by-default.
* Added: WordPress Media Library tools (beyond Elementor, domain 4). Three MCP tools for existing attachments: get-media (full detail: every registered size, dimensions, metadata, alt/caption/description), update-media (edit title, alt text, caption, description), and delete-media (destructive and effectively permanent: WordPress bypasses Trash for media unless MEDIA_TRASH is defined; disabled-by-default and requires explicit confirm:true). get-media and update-media are enabled by default. URL uploads continue via the existing sideload-image.
* Added: WordPress Users tools (beyond Elementor, domain 5). Four MCP tools for safe user management: list-users and get-user (read; admin-gated, never expose passwords/auth data) plus create-user and update-user (write). Hard guardrails: create-user can only assign non-admin roles (auto-generates a strong password and emails a set-password link, and the password is never returned); update-user edits profile fields only (no role or password changes) and refuses any user with admin-level capabilities. There is deliberately no delete-user and no role-change tool, so MCP cannot escalate privileges, take over an admin, or lock anyone out. list-users/get-user are enabled by default; create-user/update-user ship disabled-by-default.
* Added: Filesystem tools. Read/scan any file in the WordPress install (core, plugins, themes, uploads): read-file, list-directory, search-files (enabled by default), and, disabled by default, modify/delete: write-file, edit-file, delete-file. Every path is confined to the WordPress installation (no traversal/symlink escape); writes auto-back up the original, refuse wp-config.php/.htaccess, require the edit_files capability (honoring DISALLOW_FILE_EDIT), record to an audit log, and delete-file needs confirm:true. manage_options.
* Added: Database tools. Inspect the database with flexible read-only SQL -- list-tables, describe-table, query (SELECT/SHOW/DESCRIBE/EXPLAIN only; writes, DDL, stacked statements, MySQL executable comments, and file-access SQL like INTO OUTFILE/LOAD_FILE are rejected; results capped) -- and, disabled by default, structured parameterized writes: insert-row, update-rows, delete-rows. Writes use $wpdb (no raw write-SQL, so no DDL), force a non-empty WHERE on update/delete, refuse wp_users/wp_usermeta, capture a before-image snapshot into an audit log, and delete-rows needs confirm:true. manage_options.
* Added: Context page: site-wide guidance for AI agents. A new EMCP Tools > Context screen where you write stable, site-wide guidance (business identity, brand voice, content rules, guardrails) in Markdown. It is delivered to every connecting AI agent as the MCP server's instructions and applied automatically. Includes a starter template, a character/token counter, a live "what agents receive" preview, and an on/off toggle.
* Added: Connect tab: client-first flow + Claude Desktop one-click bundle. The Connection screen now asks you to pick your AI client first; the connection options (one-click bundle, terminal command, AI setup prompt, or manual JSON) appear only after that choice, tailored to the selected client. Claude Desktop gets a downloadable .mcpb bundle that installs the MCP server without editing any config files.
* Added: WordPress Performance Analyzer (beyond Elementor). New read-only MCP tool analyze-performance that audits server config, WordPress internals (database size, autoloaded options, post revisions, cron backlog, object cache, OPcache, plugin count), and a target page (defaults to the frontpage; pass url or post_id) for bottlenecks. Returns a scored report (0-100 + A–F grade) with severity-tagged findings and ranked recommendations. Self-contained (no external API); same-host-enforced loopback fetch; degrades gracefully when the page fetch is blocked. manage_options, enabled by default.
* Added: Security & Malware Scanner: the security counterpart to the Performance Analyzer. New read-only MCP tool scan-security that scans across four dimensions: malware heuristics (eval/base64 obfuscation, request-driven execution, command execution, webshells, encoded blobs, executable PHP under uploads; pass deep:true for the whole tree), WordPress core-file integrity (against official wordpress.org checksums), hardening (file editing, debug output, admin username, XML-RPC, version disclosure, HTTPS, security headers), and outdated/abandoned software (closed/removed wordpress.org plugins, outdated core/plugins/themes). Returns a scored report (0-100 + A–F grade) with severity-tagged findings and ranked recommendations. The malware walk is confined to the WordPress install, bounded by file-count + time caps (partial results flagged), and never returns full file contents (path:line + snippet only). Self-contained (only wordpress.org calls, graceful offline). manage_options, enabled by default. The admin "Performance" tools section was renamed to "Performance & Security".
* Added: WordPress core's read-only context abilities (core/get-site-info, core/get-user-info, core/get-environment-info) are now surfaced on the EMCP server too.
* Added: A curated widget catalog (62 widgets: 27 free / 30 Pro / 5 WooCommerce) that powers the consolidated widget tools.
* Changed: Per-turn widget tool-list context cut ~10x (~18-20k -> ~2k tokens), freeing the model's context window and removing the need for Low-tools mode on most clients.
* Migration: Old per-widget disabled-tool toggles are cleared automatically (defaults seeder v5). Existing pages and templates are unaffected. This changes the tools, not _elementor_data.

= 2.2.0 =
* Performance: Leaner widget tool schemas. Each per-widget convenience tool now publishes a focused set of core parameters instead of a fully-enumerated schema, cutting the MCP tools/list payload (re-sent on every request) by roughly a third with no loss of capability. Every other setting still passes through to Elementor and stays discoverable via get-widget-schema.
* Fixed: get-widget-schema now returns the complete control set, and valid styling is no longer rejected. Outside the editor (WP-CLI/REST) Elementor strips a widget's style controls (typography, colours, alignment) from get_controls(); the generator now opts into the full set the way Elementor's own CSS generator does, and settings validation is non-fatal so unknown keys pass through to Elementor instead of aborting the insert.
* Fixed: Low-tools mode is now a true override: it exposes exactly the curated essentials regardless of your per-tool toggles (which are preserved), the Tools grid greys out to show the paused state, and saving now shows a "Settings saved" confirmation.
* Fixed: add-atomic-paragraph saved blank paragraphs (#56): content was written to the wrong prop (text instead of the e-paragraph "paragraph" prop). Also fixed a sibling where add-atomic-youtube wrote the URL to "url" instead of the e-youtube "source" prop.
* Fixed: list-global-classes failed when called with no arguments (#57). Resolving the whole registry aborted if one class had an unexpected structure; each class is now resolved defensively so one bad entry can't break enumeration of the rest.
* Security: Custom CSS can no longer break out of its <style> block (F-004): add-custom-css now neutralises the </style> end tag with a bypass-proof loop while preserving all valid CSS (combinators, media ranges, content strings).
* Security: SVG sanitiser closes a multiline event-handler bypass (F-008): inline on*= handlers whose quoted value spanned a line break could slip past the regex.
* Security: The admin no longer localises the absolute server proxy path into page JavaScript (F-020); it exposes only the filename.
* Fixed: Page saves are more robust in CLI/REST (F-005): the direct-meta fallback now runs when Elementor's document save returns null, not only false.
* Performance: list-pages and list-templates now set no_found_rows (F-017/F-018).
* Developer: PHPUnit suite green again (448 tests); get_all_tools() cross-checks the admin catalog against the live ability registry to catch drift.

= 2.1.0 =
* New: PHP Code Snippets (Sandbox). A free, capability-gated way for an AI agent to author server-side PHP behind a hard human-approval gate. The AI can validate code and create drafts over MCP, but a draft never runs until an admin activates it in EMCP Tools > Sandbox (there is no "activate" tool). Every snippet passes a static parse + security scan (blocks exec/eval/backticks/variable-functions/file-writes/network/destructive SQL/obfuscation) before it can be saved or activated; activation writes a sha256-verified file that runs inside try/catch with a shutdown guard that auto-deactivates a snippet that fatals. Six tools (validate/create/update/get/list/delete). Off by default.
* New: list-global-classes tool (#55). Resolves Elementor's Class Manager (Global Classes): maps the opaque g- class IDs on elements back to their human-readable names (g-037bb9c -> card-base) and the CSS each defines, per breakpoint/state, so an AI can understand and debug a design-system-driven page. Read-only; Elementor 4.0+.
* New: One-click authentication test on the Connection tab (#41). After generating credentials, "Test authentication" sends a real request using only the Authorization header and tells you whether a client will connect, and if your server is stripping the Authorization header (the usual cause of "initialize: Unauthorized" on Plesk/Apache/IIS), it shows the exact .htaccess / nginx fix.
* New: OpenAI-strict tool schemas (opt-in, #42). A Connection-tab toggle that emits strict JSON Schemas (every property required, optionals nullable, additionalProperties:false) for OpenAI-compatible strict function-calling clients like CrewAI. Off by default. The default schemas keep working for Claude, Gemini and Antigravity.
* Fixed: Atomic widgets and containers silently failed to save (#36). add-atomic-widget, update-atomic-widget and nested add-div-block passed a boolean to the save layer ("save_page_data: bool given"), so the element was never written. They now save correctly, and invalid atomic settings return a clean error instead of fataling the request.
* Fixed: Setting theme-template conditions broke other templates (#38). set-template-conditions and set-popup-settings cleared Elementor Pro's conditions cache without rebuilding it, silently stopping unrelated headers/footers from rendering. They now use Elementor's own conditions manager, which regenerates the location cache correctly.
* Fixed: Prompts and Brand Kits cards resetting to 0. Counts now read a durable mirror and refresh in the background, so they no longer drop to zero when the cache expires.
* Fixed: Broken "Generate Configs" button when admin.js was quarantined by security software/host (#44). The plugin now detects a missing/renamed admin.js and shows a precise notice, and a release-time verifier guards against shipping such a build.
* Improved: Styled Changelog screen: version cards, category tags and formatted notes instead of raw text.
* Improved: Code viewer and copy: generated code opens in a slide-in viewer with copy/download, and shortcodes are click-to-copy.
* Developer: the bootstrap file was slimmed to bootstrap-only and all feature logic moved into dedicated classes; restored the uninstall-cleanup hook dropped during the 2.0 rename.

= 2.0.2 =
* Fixed: Tool toggles & Low-tools mode wouldn't save. After the 2.0 rename, the legacy-settings migration ran on every page load and copied the old elementor_mcp_* options (still in the database) over your current settings, so anything you saved on the Tools screen, including Low-tools mode, was silently reset on the next load. The migration now only seeds a new setting when it has never been set, so it can't overwrite your live choices.
* Fixed: "Enable All" / "Disable All" also flipped Low-tools mode. The bulk buttons are now scoped to the tool checkboxes only, leaving the separate Low-tools-mode toggle alone.
* Fixed: First-ever save on the Tools screen could invert (the disabled-tools sanitizer is now idempotent).
* New: list-media tool (#25). Lets an AI agent discover and search images already in the WordPress Media Library (the site's own uploads) where Openverse only finds generic stock. Backed by a direct WP_Query; optional search matches the title, alt text, caption, and description, with mime-type, pagination, and sort filters. Read-only; not part of the Low-tools essentials.
* Improved: Tools screen UI: section headers are now collapsible (state remembered per section) and the per-section All / None controls are a segmented button instead of text links.
* Improved: "Elementor Pro" vs "Pro" badges. Tools that need Elementor Pro (widget shortcuts, theme builder, popups, dynamic tags, Pro custom code) now show a distinct "Elementor Pro" badge, so they aren't confused with the "Pro" badge reserved for EMCP Tools Pro features. The "Pro Widget Shortcuts" section is renamed "Elementor Pro Widgets".

= 2.0.1 =
* Fixed: Pro license activation. The premium build now correctly identifies as premium (it reads the bundled .emcp-pro marker), so Freemius shows the license-activation screen instead of the free opt-in. Previously the Pro zip behaved like the free version: if you skipped opt-in or didn't click the confirmation email, no "Activate License" link appeared.
* Stuck on Pro 2.0.0? Update to 2.0.1 and the Activate License option will appear. If your install is still wedged mid opt-in, run the official Freemius Fixer (https://github.com/Freemius/freemius-fixer) to re-trigger the opt-in, complete the opt-in, then activate your license.
* New: Community button in the admin header linking to the EMCP Tools Facebook group.

= 2.0.0 =
* ⚠️ PRO USERS, ACTION NEEDED: Because the plugin folder/slug changed (elementor-mcp -> emcp-tools), the new install is a separate plugin to WordPress, so your Pro license does NOT carry over automatically. After deleting the old plugin and activating "EMCP Tools", you will likely need to re-activate your license and complete the Freemius opt-in/connection again. Your license stays valid. This only re-links it to the renamed plugin. Free users have nothing extra to do.
* Changed: The plugin was renamed from "emcp-tools" to "emcp-tools" as it grows beyond Elementor. The folder, main file, text domain, and all internal PHP identifiers were rebranded. In the Plugins list it now shows as "EMCP Tools" (the old one stays "MCP Tools for Elementor", so it's clear which to remove). Your AI clients keep working unchanged: the MCP tool names and server (emcp-tools/..., emcp-tools-server) are intentionally unchanged, so no connection config or skill needs editing.
* New: Safe automatic migration. If the old "emcp-tools" plugin is still active, EMCP Tools pauses and shows a notice to deactivate and delete it; your settings and banner dismissals are carried over to the new keys (captured before the old plugin's uninstall can wipe them).
* Note: Install emcp-tools as a new plugin, then remove the old elementor-mcp one when prompted. All PHP symbols are uniquely re-prefixed so the two coexist during the switch without fatal errors.

= 1.9.0 =
* New: AI Widget Builder (Pro): 8 MCP tools let an agent design custom Elementor widgets from a structured spec (no hand-written PHP). The plugin compiles the spec + an HTML template into a sandboxed Widget_Base class under uploads/emcp-widgets/, escaping every value by control type. 35 control types incl. group controls (typography/border/box-shadow/background), repeaters, responsive, and conditions, plus optional per-widget CSS/JS. New widgets auto-activate under a "Custom (EMCP)" category; a runtime safety net keeps a bad widget from breaking the editor. Off-by-default; managed on the new Widget Builder tab.
* New: 10 free brand kits: the Brand Kits tab now ships 10 curated color + typography kits anyone can apply for free, with backup-before-apply and restore. The full 50-kit library stays Pro.
* New: Get Support button in the admin header on every tab, linking to the support portal (support.msrbuilds.com).
* New: Pagination on the Prompts, Templates, Brand Kits, and Changelog pages, filter-aware, and it revived the Templates category filter.
* Fixed: Prompts page froze for several seconds with 50+ prompts: off-screen 1px-wide copy textareas forced a pathological reflow; they're now display:none.
* Fixed: Atomic (V4) tool detection (#47): atomic tools now register based on whether the atomic element types are registered (or the e_atomic_elements/atomic_widgets experiment is on), not the ELEMENTOR_VERSION constant, and not on the page-editor opt-in alone (which let writes silently no-op).

= 1.8.3 =
* New: One-click credentials on the Connection tab: pick an administrator from a dropdown (admins only, you at the top) and click Generate to automatically create a new Application Password and fill in every client config. No more creating one by hand under your profile.
* New: "Use an existing Application Password instead" fallback for anyone who prefers to paste their own.
* Security: the generator is nonce-protected, requires manage_options plus edit_user on the chosen account, only targets administrator accounts, and won't mint a password over plain HTTP (where it could not authenticate).

= 1.8.2 =
* New: The Connection tab now generates ready-to-copy npx proxy configs for Claude Code and Claude Desktop ("npx -y @msrbuilds/emcp-proxy@latest"): the recommended way to connect a remote/shared-hosting site, with no local proxy file to maintain. The bundled-proxy-file configs are still offered for local WordPress.
* Fixed: Reorganized the Connection tab proxy section into "Remote (npx)" and "Local (bundled file)" groups so remote users no longer copy a server-side filesystem path that can't work from their machine.

= 1.8.1 =
* Fixed: Clarified the Node.js proxy docs for remote/shared-hosting setups. The proxy runs as a local subprocess on the machine with your AI client, so its file path must be local, not the copy inside wp-content/plugins on the server. The Connection tab, README, and config examples now make this explicit.
* New: Zero-install npx runner for remote connections: use "command": "npx", "args": ["-y", "@msrbuilds/emcp-proxy@latest"] instead of maintaining a local copy of the proxy file that can drift from the server version.
* Changed: Documented the MCP_PROTOCOL_VERSION=2024-11-05 override in the connection docs (previously only in release notes), for clients that reject the adapter's 2025-06-18 handshake.

= 1.8.0 =
* New: SEO & Accessibility toolkit for Pro subscribers: 7 new MCP tools that audit and improve a page at the structure level (no external API, no AI cost). SEO: audit-page-seo (scored on-page report), extract-keywords-from-content, generate-meta-tags (writes to Yoast/Rank Math with apply), generate-schema-markup (JSON-LD: Article/LocalBusiness/FAQPage/Service/Product, injects with apply). Accessibility: audit-page-a11y (WCAG-oriented: contrast, alts, heading order, link text, form labels), fix-color-contrast, add-alt-text-from-context.
* New: Every page-mutating tool is dry-run by default: fixers and the generator write-back only change the site when apply:true is passed, and edits are reversible via Elementor revisions.
* New: The 7 tools are Pro-gated and disabled-by-default; enable individual tools on the EMCP Tools tab (new "SEO & Accessibility" category).
* Changed: CLAUDE.md / documentation corrected to state the real minimums (WordPress 6.9+, PHP 8.0+).

= 1.7.4 =
* New: The WordPress MCP Adapter is now bundled with the plugin: no separate adapter plugin install required. On WordPress 6.9+/7.0 (where the Abilities API is in core), Elementor is the only thing you need to install. If a standalone MCP Adapter plugin is active, the plugin automatically defers to it.
* New: "Activate Abilities API for EMCP" toggle on the Connection tab: switch the MCP server on or off for the site (on by default), with a security note that connected AI agents can create, edit, and delete Elementor content when enabled.
* New: Connection tab now shows the MCP Adapter source (bundled vs. external plugin) and the MCP Server enabled/disabled status.
* Changed: Dependency checks no longer require a separately installed MCP Adapter; the bundled copy loads automatically. Only the adapter's runtime source is bundled (it has zero runtime dependencies).

= 1.7.3 =
* New: Industry Skill Packs for the Pro Agent Skill: 10 vertical knowledge files (Dental, Med-Spa, Therapy, Fitness, Automotive, Food & Restaurant, Wedding, Real Estate, Legal, Photography). When the AI agent recognizes the site's industry it reads the matching pack before building and applies that trade's brand voice, SEO keywords, page structure, conversion patterns, compliance notes, and the exact Brand Kit + prompt + template combo.
* New: Skills admin tab now lists the bundled industry packs and explains how the skill auto-routes to the right vertical, no configuration needed.
* Changed: The bundled EMCP Agent Skill gained a vertical-routing section so it loads only the one relevant industry pack (progressive disclosure keeps token cost low). Packs ship in the premium build only.

= 1.7.2 =
* New: Brand Kits Library for Pro subscribers: one-click coordinated color palettes + typography. 16 curated kits across 4 categories (Corporate & Tech, Creative, Hospitality, Trades). Click Apply and the whole site re-skins; back up and restore any time. Auto-synced from the EMCP Tools server with the same 24h cache as Prompts and Templates.
* New: Applying a brand kit replaces the four Elementor system color + typography slots AND sets the kit's Theme Style defaults (default body/heading fonts and body/heading/link colors) so the change is visible site-wide, not just on elements that reference global tokens. Google Fonts load automatically.
* New: Backup-before-apply: current global settings are snapshotted into a private backup before each apply, with a Restore-from-backup section (selective by default, full-clobber option) on the Brand Kits page.
* New: Four Pro-gated MCP tools: list-brand-kits, apply-brand-kit, replace-system-colors, replace-system-typography, so AI clients can browse and apply kits too.
* New: Brand Kits admin tab between Templates and Skills, with category filter pills, self-contained preview cards, an apply-confirmation modal, and a "View site" toast.
* Changed: Admin stats bar shows a Brand Kits count for Pro sites with a synced library.

= 1.7.1 =
* New: Premium Templates library: apply ready-made Elementor designs to a new draft page or import them into Elementor's Saved Templates library. Auto-synced from the EMCP Tools server, category filter + thumbnails. Accepts Elementor's native template export shape.
* New: EMCP Agent Skill download for Pro subscribers: pre-written Anthropic Agent Skill with install guides for Claude Code, Claude Desktop, Cursor, Windsurf, Antigravity. New Skills admin tab.
* New: Global "Upgrade to Pro" admin banner on non-EMCP screens for non-Pro sites. Dismissible per-user.
* New: "Read the Docs" header button in the admin. Upgrade-to-Pro button hidden for active Pro sites.
* Changed: Prompts tab hides the 5 bundled samples for Pro users: premium library supersedes them.
* Changed: Stats-bar prompt count reflects the synced premium library size (e.g. 50) on Pro sites.
* Changed: All in-plugin "Upgrade to Pro" CTAs point at https://emcp.msrbuilds.com/pricing and open in a new tab.
* Changed: Reverted the Freemius pricing-screen wrapper: pricing iframe renders native again.
* Fixed: Premium prompts/templates transient caches and upgrade-banner user-meta scrubbed on uninstall.

= 1.7.0 =
* New: Premium Prompts library is now live for Pro subscribers: 50+ industry-specific landing-page prompts across 10 categories, auto-synced from the EMCP Tools server. Free users continue to see the 5 bundled sample prompts plus an upgrade CTA.
* New: Category filter pills + Sync Library button on the Prompts admin page for Pro users.
* New: "Read the Docs" link in the admin header pointing at the comprehensive docs site at https://emcp.msrbuilds.com/docs.
* New: Two-build distribution: separate free and premium zips so the Freemius account screen labels the install correctly. Paying customers see "Pro version", non-paying customers see "Free version", instead of everyone seeing "Free version" regardless of license.
* Changed: Premium prompts fetcher now sends authentication via Authorization Bearer header instead of URL query parameters. License keys belong in headers, not query strings.
* Changed: Default premium prompts endpoint moved to the dedicated subdomain `https://emcp.msrbuilds.com/api/emcp/prompts.json`.
* Changed: Admin header: removed redundant "Contact Me" button, renamed "Get Premium Prompts" → "Upgrade to Pro" pointing at the Freemius checkout, and hidden for sites with an active Pro license.
* Changed: Pricing-screen "Get in touch" link points at the new EMCP Tools about page.
* Improved: Uniform 403 error handling for the premium prompts endpoint: no info-leak about which auth condition failed.

= 1.6.1 =
* Changed: Uninstall logic moved from `uninstall.php` to the Freemius `after_uninstall` hook so Freemius's own cleanup and ours run in the right order. The `uninstall.php` file has been removed.
* Added: `elementor_mcp_low_tool_mode` and `elementor_mcp_defaults_applied` options are now cleaned up on uninstall (previously missed when those options were added in 1.6.0).
* Added: Branded chrome around the Freemius pricing screen: gradient header matching the EMCP Tools admin pages, feature highlights card above the pricing iframe, and a collapsible FAQ + contact link below it.

= 1.6.0 =
* New: Dedicated **EMCP Tools** top-level admin menu with Tools, Connection, Prompts, and Changelog submenus (previously a single tabbed screen under Settings).
* New: Atomic element tools (Elementor 4.0+) are now listed in the admin Tools screen and can be toggled individually.
* New: **Low-tools mode**: one-click toggle on the Tools screen that filters the registered tool list down to a curated essentials set, keeping the active count under 60 so MCP clients with strict tool caps (Antigravity, Gemini API, etc.) stay under their limits. Your individual toggles are preserved.
* Changed: Pro widget shortcuts are now disabled by default on fresh installs and on the first admin page load after upgrade. Re-enable any of them from the Tools screen.
* Fixed: The "disabled tools" toggles in the admin Tools screen previously had no effect on what MCP clients saw: the filter was only registered in admin context and never fired on REST API requests (#45).
* Fixed: Atomic element tools are now visible in the Tools screen and can be toggled individually (previously missing from the UI).

= 1.5.1 =
* Fixed: Container `justify_content` / `align_items` / `align_content` settings are now remapped to Elementor's prefixed flex keys (`flex_justify_content`, `flex_align_items`, `flex_align_content`) before saving: fixes containers rendering with default alignment on the front-end despite the values being persisted (#32).
* Fixed: Factory auto-center default for column containers now uses the prefixed `flex_align_items` key.
* Improved: Tool descriptions for `add-container` / `update-container` now point to the prefixed flex keys.

= 1.5.0 =
* New: 13 atomic element tools for Elementor 4.0+: atomic flexbox, div-block, heading, paragraph, button, image, svg, youtube, video, divider, plus universal `add-atomic-widget`, `update-atomic-widget`, and `detect-elementor-version`.
* New: Typed props (`$$type`) handled automatically: AI agents pass simple flat values; styles stored in the separate `styles` map matching Elementor 4.0's data model.
* New: All atomic tools self-guard on Elementor >= 4.0: zero changes to existing 97 legacy tools.
* Total MCP tools increased from 97 to 110.
* Addresses #28 and #29.

= 1.4.3 =
* New: 5 Pro widget convenience tools: `add-code-highlight`, `add-reviews`, `add-off-canvas`, `add-progress-tracker`, `add-search`.
* Total MCP tools increased from 92 to 97.
* Fixed: Gemini API / Antigravity compatibility: strip empty string values from enum arrays and ensure empty `properties` objects serialize as `{}` (not `[]`). Applied to all 44 ability registrations.
* Fixed: `switcher`, `popover_toggle`, `select`, and `choose` control types no longer emit empty enum values in `get-widget-schema` output.
* Fixed: `get-container-schema` input schema now uses `stdClass` for empty properties (resolves `'allOf' failed - got array, want object`).
* Fixed: Added missing `items` schema to `template_json` array property in `import-template` tool.
* Closes #21.

= 1.4.0 =
* New: 22 Pro widget convenience tools: nav menu, loop grid, loop carousel, media carousel, nested tabs, nested accordion, and more.
* New: 5 WooCommerce widget tools: products, add-to-cart, cart, checkout, menu cart (conditional on WooCommerce).
* New: 4 layout tools: update-container, update-element, batch-update, reorder-elements.
* New: 6 template/theme builder tools: create-theme-template, set-template-conditions, list-dynamic-tags, set-dynamic-tag, create-popup, set-popup-settings.
* New: 2 query tools: get-container-schema, find-element.
* New: 4 extended core widget tools: menu-anchor, shortcode, rating, text-path.
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
* New: Prompts tab in admin dashboard: browse and one-click copy 5 sample landing page prompts.
* New: Contributing Prompts guide in CONTRIBUTING.md with structure, guidelines, and submission steps.
* Improved: Admin CSS for prompt card grid with hover effects and responsive breakpoints.

= 1.3.0 =
* New: `add-custom-css` tool: add custom CSS to any element or page-level with `selector` keyword support (Pro only).
* New: `add-custom-js` tool: inject JavaScript via HTML widget with automatic `<script>` wrapping and optional DOMContentLoaded wrapper.
* New: `add-code-snippet` tool: create site-wide Custom Code snippets for head/body injection with priority and jQuery support (Pro only).
* New: `list-code-snippets` tool: list all Custom Code snippets with location, priority, and status filters (Pro only).
* Total tools increased from ~64 to ~68.

= 1.2.3 =
* Fix: Factory now strips `flex_wrap` and `_flex_size` from container settings: prevents AI agents from setting these values that cause layout overflow.
* Fix: Tool descriptions now include background color instructions (`background_background=classic`, `background_color=#hex`) so AI agents apply colors correctly.
* Improved: Stronger "NEVER set flex_wrap" guidance in build-page and add-container tool descriptions.

= 1.2.2 =
* Fix: Row container children now use `content_width: full` with percentage widths (e.g. 25% for 4 columns) matching Elementor's native column layout pattern.
* Fix: Removed all `flex_wrap` and `_flex_size` auto-overrides from factory and build-page: Elementor defaults handle layout correctly.
* Improved: Tool descriptions updated with correct multi-column layout guidance.

= 1.2.1 =
* Fix: Row containers now use `flex_wrap: wrap` instead of `nowrap` to prevent children from overflowing.
* Fix: `build-page` auto-sets percentage widths on row children (e.g. 50% for 2 columns, 33.33% for 3) instead of using `_flex_size: grow` which caused layout overflow.
* Improved: Tool descriptions updated with correct layout guidance for multi-column layouts.

= 1.2.0 =
* New: 14 free widget convenience tools: accordion, alert, counter, Google Maps, icon list, image box, image carousel, progress bar, social icons, star rating, tabs, testimonial, toggle, HTML.
* New: 10 Pro widget convenience tools: call to action, slides, testimonial carousel, price list, gallery, share buttons, table of contents, blockquote, Lottie animation, hotspot.
* Total widget tools increased from 17 to 41 (~64 MCP tools overall).

= 1.1.1 =
* Fix: Container flex layout: row children auto-grow with `_flex_size: grow` for equal distribution.
* Fix: Column containers auto-center content horizontally (`align_items: center`).
* Fix: Row containers auto-set `flex_wrap: nowrap` to prevent wrapping.
* Fix: `_flex_size` now correctly uses string value (`grow`) instead of array: prevents fatal error in Elementor CSS generator.
* Fix: `get-global-settings` input schema uses `stdClass` for empty properties to serialize as JSON `{}` instead of `[]`.
* New: Connection tab configs for Cursor, Windsurf, and Antigravity IDE clients.
* New: 3 stock image tools: `search-images`, `sideload-image`, `add-stock-image` (Openverse API).
* New: SVG icon tool: `add-svg-icon` for custom SVG icons.
* Improved: `build-page` description with detailed layout rules for row/column containers.
* Improved: Admin connection tab streamlined: removed WP-CLI local section, unified HTTP config workflow.

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
