# Changelog

All notable changes to MCP Tools for Elementor are documented in this file.

## [3.3.0]

> A foundation release: two tools that let an AI agent **understand** a page from one call and **undo** any change it makes. **`get-page-snapshot`** returns one normalized digest of a page (structure, tokens-in-use, responsive overrides, content outline, SEO-lite, + opt-in performance/accessibility/SEO audits). **AI-safe transactions** record every mutation to a unified change ledger and can roll any of them back.

### Added
- **AI-safe transactions — a unified change ledger + rollback (3 tools, free, always-on).** Every AI-made mutation across Elementor page edits, filesystem writes, and database writes is now recorded to one ledger, and any recorded change can be undone. `list-changes` (recent changes, newest-first, filter by domain/rolled_back/reversible), `get-change` (one entry's full detail incl. its rollback reference), and `rollback-change` (undo by id — restores a page's prior Elementor data, restores/removes a file from its backup, or inverses a database write from its before-image; marks the entry rolled back and records a compensating entry). This unifies the previously fragmented per-domain audit logs, file backups, and row before-images behind one recorder (`EMCP_Tools_Change_Log`). All three tools require `manage_options`; the ledger is capped by count and size (oldest entries age out). Rollback covers the three domains that capture a "before" today; broad dry-run/preview and media/settings rollback are planned follow-ups.
- **`get-page-snapshot` (always-on, read-only).** One normalized page digest so an agent can reason about a page from a single call instead of chaining `get-page-structure` + `get-global-settings` + `list-global-classes` and reassembling. Core sections (free, computed in-process): normalized structure tree + counts (`by_widget_type`, `max_depth`), global colors/typography/`g-` classes **actually in use** with usage counts, per-device responsive overrides, a content outline (headings, word/image/link/button counts, images-missing-alt), an SEO-lite summary (Yoast/Rank Math/core), and structural warnings. Opt-in heavy sections via `include:[performance,a11y,seo]` — `performance` is free (page-level, needs `manage_options`), `a11y` and deep `seo` are Pro and degrade to `pro_gated` on free builds; heavy sections are transient-cached (15 min, `fresh:true` to bypass). A `sections` param subsets the core output to control token cost.

## [3.2.1]

> A feature + fix release. **ACF / ACF PRO** becomes a first-class integration — exposed as **two dispatcher tools** (`acf-read` / `acf-write`) behind a new **Plugins** tab — letting an agent build a whole content structure (custom post type → taxonomy → field group → posts with values) end to end. Atomic-element writes get three fixes so v4 pages actually persist. New tools round out AI publishing: **`set-social-image`** fixes wrong social link-previews, and a **`full_bleed`** container preset kills the white strips on Canvas pages. Plus a new **`emcp-plugins`** agent skill, a couple of content-query fixes, and admin polish.

### Added
- **ACF / ACF PRO integration (2 dispatcher tools, 15 operations).** When Advanced Custom Fields (free or PRO) is active, EMCP registers two tools — `acf-read` and `acf-write` — instead of a tool per operation. Call either with no `operation` to list what it can do, then again with `{ operation, arguments }`. The 15 operations cover field groups, field values, options pages, and (ACF 6.1+) ACF-managed custom post types and taxonomies — enough to stand up a full content structure end to end. Registers custom post types / taxonomies **as data** through ACF (`acf_import_*`), so nothing executable is written. Full PRO field support (repeaters, flexible content, galleries, groups, clones) round-trips as nested JSON. Conservative by design: no delete operations, immutable slugs/field keys, and every operation keeps its own capability check (enforced per call). A new **Plugins** sub-tab on the Tools screen presents the two dispatchers as toggles — `acf-read` on, `acf-write` off by default — each card listing the operations it covers. Thanks to @DeibyRo for the original contribution ([#76](https://github.com/msrbuilds/elementor-mcp/pull/76)).
- **`emcp-plugins` agent skill (Pro).** A new bundled Agent Skill (bringing the bundle to **8**) that teaches a connected agent how plugin integrations work — the two-dispatcher model, the discover→act flow, per-operation capability checks — with a full ACF operation reference and a worked CPT → taxonomy → field-group → values example.
- **`set-social-image` (Pro SEO).** Sets the Open Graph + Twitter/X share image for a page via the active SEO plugin's meta — Yoast (`opengraph-image`/`twitter-image` + `-id`) and Rank Math (`facebook`/`twitter` `image` + `_id`) — so link previews on Facebook, WhatsApp, X and LinkedIn render the image you choose. Without it, Yoast keeps the first image it found in the content, even after a featured image is assigned. Resolves an attachment URL from its ID so Yoast can emit dimensions. Ships disabled-by-default with the SEO toolkit. ([#82](https://github.com/msrbuilds/elementor-mcp/issues/82))
- **`full_bleed` preset on `add-container`.** A single flag seeds the known-good edge-to-edge recipe — full content width, 100% width, zero padding, zero flex/gap, column + stretch — for the top-level container on Elementor **Canvas** pages, where the boxed defaults otherwise leave white strips around headers, footers and full-width sections. Any explicit `settings` you pass still override the preset. ([#83](https://github.com/msrbuilds/elementor-mcp/issues/83))
- **`set-element-label`.** A convenience tool to rename an element in the Elementor Navigator (`editor_settings.title`).
- **`LOCAL_NEWS_SITE` prompt.** A bundled playbook for publishing local-news articles as standalone Elementor Canvas pages with Google-News-grade structured data, image SEO and lazy-loaded social embeds. Thanks to @Mrshahidali420 ([#84](https://github.com/msrbuilds/elementor-mcp/pull/84)).

### Fixed
- **Atomic image/SVG widgets rejected valid images.** `add-atomic-image` / `add-atomic-svg` failed with `image: invalid_value` on a valid `image_id`/`image_url`: the envelope emitted both an id and a url (Elementor's `image-src` requires exactly one) with the id typed as a plain number. The envelope now matches Elementor's prop types (`image`/`image-src`/`image-attachment-id`, id XOR url), and `e-svg` uses its own `svg-src` prop. ([#74](https://github.com/msrbuilds/elementor-mcp/issues/74))
- **Atomic `styles` and `editor_settings` weren't writable.** On v4 atomic elements the local `styles` map and `editor_settings` (the Navigator label) are sibling-root keys, but `update-element` / `batch-update` / `update-atomic-widget` merged everything into `settings`, so those values were silently dropped (the call still reported success). They're now hoisted to the element root and deep-merged. ([#72](https://github.com/msrbuilds/elementor-mcp/issues/72), [#73](https://github.com/msrbuilds/elementor-mcp/issues/73))
- **`list-posts` silently ignored array filters.** `post_type: ["post","page"]` / `status: ["publish","draft"]` were stringified to `"Array"`, so multi-type/multi-status queries returned empty or unfiltered results with no error. Arrays are now accepted and sanitized element-by-element. Thanks to @Mrshahidali420 ([#80](https://github.com/msrbuilds/elementor-mcp/issues/80)).
- **`get-post` hid opted-in protected meta.** The `emcp_tools_content_allowed_protected_meta` allowlist was honoured on write but not on read, so a key `update-post` accepted couldn't be read back. `get-post` now returns allowlisted protected keys, so read-after-write works. Thanks to @Mrshahidali420 ([#77](https://github.com/msrbuilds/elementor-mcp/issues/77)).

### Changed
- **Admin polish.** The Skills page shows a live count of bundled skills and a runtime badge that reads **Live** (green) only when the Agent Skills module and the MCP server are both on, otherwise **Offline** (red). The Compact tool mode and Themer PHP Templates cards sit two-up. The header's **Help & Support** button gains a matching icon, and its menu gains a **Discord** link.
- Registered the previously-unwired `Seo` PHPUnit suite so the SEO toolkit's tests run in CI.

## [3.2.0]

> A core-engine release, and a rewrite of the Premium Prompts. The bundled MCP Adapter becomes a Composer dependency arbitrated by the Jetpack Autoloader (robust coexistence with other adapter-bundling plugins). An opt-in **Compact tool mode** collapses the tool surface to 3 dispatcher meta-tools to escape client tool-count caps, and a richer discovery context hands agents the site's environment + active-plugin inventory. On Pro, the bundled **Agent Skills** (now **7** playbooks) become discoverable and loadable by any connected MCP agent at runtime, with a Modules-tab switch to turn that injection off. All **50 Premium Prompts are rewritten** to hand the AI a design direction instead of a fixed layout — and they now work with any page builder. The AI Chat gains a **`web_fetch` tool** so a model can read a reference design you point it at.

### Added
- **Premium Prompts, rewritten (v2).** Every prompt used to dictate an explicit, section-by-section Elementor layout — column counts, padding values, widget names. They now hand the AI a **style guide** (palette, typography, feel, motion), a **design signature** (compositional direction, never layout), **section intents** rather than section layouts, verbatim **content facts**, and non-negotiable **standards**: WCAG 2.1 AA contrast and heading structure, real photography from Unsplash/Pexels/Pixabay in every slot, one consistent SVG icon set, builder-native construction, and a completeness rule that forbids half-built pages. The AI researches, decides the composition, and builds. Every prompt now also carries a **lead-capture form** appropriate to the business (appointment, quote, reservation, consultation).
- **Prompts are builder-agnostic.** The first line of every prompt reads `**Page builder:** Elementor`. Change that one word to Gutenberg, Bricks, or plain HTML/CSS and the rest of the prompt still applies.
- **Prompts v1 download (Pro).** The original 50 prompts ship as a downloadable archive from the Prompts screen, for anyone who prefers the older, more prescriptive style. The download is capability-, nonce- and licence-gated (never a direct link) and the archive ships only in premium builds.
- **`web_fetch` in the AI Chat (Pro).** The model can now read a URL you give it — a reference design, a competitor page, documentation, a JSON endpoint, or a stylesheet. Returns readable text by default, or sanitized markup (`format:"html"`, `<head>` included) when it needs class names to reason about layout. Fetching an HTML page also returns a **`stylesheets` array of absolute CSS URLs**, so the model can read a design's colour tokens and type scale straight from source rather than guessing URLs. It cannot search the web; it needs a URL. Available in the Chat tab and both editor FABs.
- **Compact tool mode (dispatcher), opt-in.** A **Tools-tab** toggle (`emcp_tools_dispatcher_mode`, default OFF) surfaces just **3 meta-tools** — `list-tools` (compact catalog with `search`/`category` filters), `get-tool-schema` (batch input schemas), and `call-tool` (run a tool by name + arguments) — instead of the ~140 individual tools, so clients that cap tool counts can still reach the whole surface. `call-tool` delegates to each target tool's own permission check (no privilege escalation) and refuses disabled/unknown tools. The per-tool toggles stay in effect (they gate what `call-tool` may run). **Replaces the old Low-tools mode.**
- **Richer discovery context.** The MCP server description (and `list-tools`) now include a compact environment summary — WordPress/PHP/Elementor versions, atomic-element support, and an inventory of notable active plugins (WooCommerce, ACF) — so agents orient without extra calls.
- **Agent-facing Skills (read-side, Pro).** The bundled Agent Skills are now exposed to connected MCP agents at runtime via two read-only tools — `list-skills` (catalog + `search`) and `get-skill` (load a skill's full instructions by slug) — plus a `## Skills` catalog in the discovery context for progressive disclosure (the agent sees a compact menu, then pulls a full playbook only when it matches). The bundle grows to **7 skills**: Elementor page building, the Gutenberg block editor, the EMCP Themer theme builder, performance auditing, security & malware work, SEO & accessibility, and the human-gated PHP snippet sandbox. The Skills admin page now presents **two ways to use them** — install locally in your AI client, or let the agent load them at runtime.
- **Agent Skills module.** A new module (Modules tab, on by default) is the on/off switch for that runtime exposure — turn it off to remove the `list-skills`/`get-skill` tools and the `## Skills` injection (the local Skills download is unaffected).

### Changed
- **MCP Adapter is now a Composer dependency loaded via the Automattic Jetpack Autoloader.** When several active plugins bundle the same adapter (WooCommerce, Automattic MCP, …), the Jetpack Autoloader arbitrates the highest version process-wide, eliminating "class already declared" clashes regardless of load order. The runtime `vendor/` subset is committed and shipped.
- **Removed Low-tools mode.** The catalog-backed widget consolidation already keeps the surface small; clients that still cap tool counts use Compact tool mode instead.
- **Prompts screen.** A one-time, dismissible notice explains the rewritten prompts (dismissal is per-user, so one administrator clearing it does not hide it from the others). The Pro "Download v1 Prompts" button sits beside "Sync Library".

### Security
- **`web_fetch` is SSRF-guarded.** The fetch runs on your server, so every URL — and **every redirect hop**, followed manually rather than by the HTTP client — is validated first: `http(s)` only, no embedded credentials, ports 80/443 only, and rejected if **any** resolved A/AAAA record lands in a loopback, private, link-local (including the cloud-metadata address `169.254.169.254`), CGNAT, multicast or reserved range. Responses are capped at 2 MB, restricted to textual content types, and never executed. Fetched content is wrapped and labelled as untrusted data, and the model is instructed never to act on instructions found inside it.
- **One SSRF guard, not two.** The strict validation lives on the existing `EMCP_Tools_Url_Guard`, which already protected the image/SVG sideload tools, rather than in a second implementation that would drift out of step with it.

### Fixed
- **Updated the bundled Freemius SDK from 2.13.3 to 2.13.4.** Freemius withdrew 2.13.3 after finding a regression in its add-on checkout flow. EMCP Tools does not use Freemius add-ons (`has_addons => false`), so the regression never affected this plugin, but a plugin should not ship a dependency version its vendor unpublished.
- **Plugin assets no longer serve stale from the browser cache.** Scripts and styles were versioned by the plugin version unless `WP_DEBUG` was on, so a changed file could be served from cache between releases. Each asset is now versioned by its own modification time — which also fixes a CSS change never busting its own cache entry because all handles shared the JavaScript file's version.
- Regenerated the shipped Jetpack autoloader without dev dependencies, so a clean install no longer emits a "Failed to open stream" notice for a dev-only file referenced in the autoloader's file map.
- Pruned non-runtime metadata (lockfiles, package manifests, docs) from the shipped `vendor/` subset — a smaller download with no functional change.

## [3.1.3]

> A bug-fix patch: resolves a plugin-update notice that lingered after updating, an MCP tool-name collision, debug-log noise, and a proxy text-corruption bug. Thanks to @gthibo for two detailed reports.

### Fixed
- **"Update available" notice persisted after updating.** The free GitHub updater compared the latest release against the compiled `EMCP_TOOLS_VERSION` constant, which is stale in the same request right after a self-update (the old file is still loaded, and can be OPcache-cached) — so it kept offering the version you just installed. It now compares against the version WordPress freshly parsed from the plugin header, and clears its entry from the update transient immediately after updating.
- **MCP tool-name collision (`create-theme-template` / `set-template-conditions`).** The EMCP Themer tools and the Elementor Pro Theme Builder tools claimed the same two ability names; with both active, one was silently dropped (and logged "already registered"). The Elementor Pro Theme Builder tools are renamed to `create-elementor-theme-template` / `set-elementor-template-conditions`; the Themer keeps the original names. Both now coexist. ([#71](https://github.com/msrbuilds/elementor-mcp/issues/71))
- **Debug-log noise ("Ability … not found").** The admin tool-catalog drift check used a logging registry lookup for environment-gated tools (e.g. `resize-media`, the Themer PHP tools). It now uses a silent registry check and skips legitimately conditional tools, so it flags real drift only. ([#71](https://github.com/msrbuilds/elementor-mcp/issues/71))
- **Proxy corrupted non-ASCII on large responses.** `@msrbuilds/emcp-proxy` decoded each network chunk independently, mangling any multi-byte character split across a chunk boundary. It now buffers and decodes once (`Buffer.concat`). Update with `npx @msrbuilds/emcp-proxy@latest` (≥ 1.8.4). ([#70](https://github.com/msrbuilds/elementor-mcp/issues/70))

## [3.1.2]

> A bug-fix patch resolving three community-reported issues (thanks @Mrshahidali420): the MCP endpoint 404ing when WooCommerce 10.5+ is active, `move-block` silently dropping a block, and filesystem writes not taking effect under OPcache.

### Fixed
- **MCP endpoint 404 with WooCommerce 10.5+ active.** WooCommerce 10.5+ bundles and autoloads the same MCP Adapter classes but only *boots* the adapter when its own (off-by-default) "MCP Integration" feature flag is on. EMCP treated "classes loadable" as "adapter booted" and deferred, so `mcp_adapter_init` never fired and `/wp-json/mcp/emcp-tools-server` returned 404. EMCP now boots the already-loaded adapter itself (idempotent), so its endpoint no longer depends on an unrelated WooCommerce flag. ([#64](https://github.com/msrbuilds/elementor-mcp/issues/64))
- **`move-block` could silently delete a block.** Moving a block *inside* a later container, or across levels, shifted the target path when the source was removed — losing the block (or dropping it in the wrong container) while reporting success. The index-shift compensation is now generalized to every mode and depth, and a move whose target is inside the moved node's own subtree is rejected as a no-op. ([#67](https://github.com/msrbuilds/elementor-mcp/issues/67))
- **Filesystem writes didn't take effect under OPcache.** `write-file` / `edit-file` / `delete-file` didn't invalidate the OPcache entry for a PHP file, so on hosts with `opcache.validate_timestamps=0` the old bytecode kept running and an agent's edit appeared to do nothing. They now invalidate OPcache after each `.php` write, matching the plugin's other PHP-writing paths. ([#66](https://github.com/msrbuilds/elementor-mcp/issues/66))

## [3.1.1]

> A follow-up patch: a proper code editor for Themer PHP Templates, better Codex connection help (a form guide + an npx config), per-site MCP server names, and the bundled Freemius SDK bumped to 2.13.3.

### Added
- **Resize images over MCP.** The Image Optimization module now ships an in-place image **resizer** and a `resize-media` MCP tool, so an AI agent can shrink or crop a Media Library image during a build (or any existing image): scale-to-fit by default, or `crop:true` for an exact width×height. The original is backed up (reversible), all sub-sizes + WebP are regenerated, and the attachment ID and URLs stay the same. Registers only when the Image Optimization module is enabled.
- **Themer PHP Templates — a real editor.** The review screen is now editable: a full **CodeMirror** editor (PHP syntax highlighting, the same core WordPress uses for its theme/plugin file editor) plus **Title** and **Type** fields. Saving re-validates the code and recompiles the template if it's attached.
- **Codex connection help.** The Connection tab's Codex option now includes a field-by-field guide for Codex's “Custom MCP” form, and a **Node-proxy (npx) `config.toml`** option (via `@msrbuilds/emcp-proxy`) alongside the streamable-HTTP config — a robust fallback when the HTTP handshake misbehaves.
- **EMCP Themer — Free limits made visible.** The Themer templates screen now shows a banner (free installs only) with a per-type usage chip for each slot — Header, Footer, Single, Archive, Search, 404 (used / cap) — so the 1-template-per-type limit is clear before you hit it, plus a one-click **Upgrade to Pro** for unlimited templates per type and granular display conditions.

### Changed
- **Per-site MCP server names.** Every generated client config now names the server after the site's domain (e.g. `emcp-your-site-com`) instead of a fixed `emcp-tools`, so connecting several sites in one AI client no longer collides.
- **Menu order:** **Modules** now sits before **Tools** in both the EMCP Tools sidebar submenu and the page header nav.
- **Bundled Freemius SDK** updated 2.13.2 → 2.13.3.
- Smaller download — optimized an oversized bundled admin logo (~700 KB lighter).

### Fixed
- **Changelog page access.** Opening the Changelog (from the header app-bar button or its URL) no longer errors (“Sorry, you are not allowed to access this page.” / “Cannot load…”). It was being removed from the sidebar with `remove_submenu_page()`, which also broke both its access check and its render hook; it's now kept as a normal submenu (so it renders and is reachable) and only its sidebar row is hidden with CSS.
- **Codex `config.toml`** now uses `http_headers` (not `headers`), so the generated Codex config connects.
- **Themer:** the “Render with PHP template” dropdown now updates live when you change the template type — a new template no longer stays stuck on “choose a template type first.”

## [3.1.0]

> A big feature release: a builder-agnostic **theme builder (EMCP Themer)**, a pluggable **Modules** framework (with **Image Optimization**), **10 always-on Gutenberg block tools**, an in-editor **AI Chat** panel for the block editor, a dedicated plugin **Dashboard**, and **in-dashboard updates for free users** via GitHub releases.

### Added
- **EMCP Themer — a builder-agnostic theme builder (free, on by default).** Design **Header / Footer / Single / Archive / Search / 404** layouts with any page builder (Gutenberg, Elementor, …), attach display conditions, and the plugin injects them on the front end. One CPT `emcp_theme_template` with its own top-level dashboard menu; a slot-based resolver (header/body/footer) picks one winner per slot by condition specificity → priority → newest. An Elementor-style **step-wise condition builder** (Relation → Group → Sub-type → Object) drives the metabox, and it warns when another template of the same type targets an overlapping condition (only one can render a given slot). Free = the whole engine + 1 template per type + broad scope selectors + all **8 MCP tools** (`create-/list-/get-/update-/delete-theme-template`, `set-template-conditions`, `resolve-template`, `list-condition-targets`). Loops are out of scope. Per-theme header/footer injection adapters for Astra/GeneratePress/Kadence/OceanWP/Blocksy/Neve/Hello, with a template-tag + force-render fallback for other themes. Dynamic content elements (Post/Archive Title, Breadcrumbs, Post Meta, Site Logo/Title, Menu, Description, Post Content, Archive Posts) ship for **both** builders so classic themes get dynamic widgets.
- **EMCP Themer — PHP Templates (free, off by default).** For layouts neither builder can express, a connected AI agent can author a fully custom **PHP** region template (header/footer/single/archive) into a validated, hash-verified sandbox; you then **select** it on a template to take over that region's render (region-only output that still respects the theme's header/footer + container). The human selection is the execution gate — an AI can only create drafts, and there is deliberately no attach tool. The same strict validator as the PHP-Snippet sandbox rejects code execution, file loading, network calls, and file writes. Gated behind a Tools-tab master switch; adds **5 MCP tools** (`create-/list-/get-/update-/delete-theme-php-template`, all disabled-by-default) and a **PHP Templates** review screen under the EMCP Themer menu.
- **Gutenberg block tools (10, always-on).** A block-editor counterpart to the Elementor family, pure WordPress core (no Elementor dependency): `list-blocks`, `get-block-schema`, `get-post-blocks`, `list-patterns`, `add-block`, `update-block`, `remove-block`, `move-block`, `duplicate-block`, `insert-pattern`. Existing blocks are addressed by an index path from `get-post-blocks`; raw block markup in/out. Surfaced under a new **Gutenberg** tab on the Tools screen. All enabled by default (only `remove-block` badged destructive).
- **Modules framework.** A pluggable feature system — substantial features an admin turns on/off from a new **Modules** tab. Ships with an **Image Optimization** module (free, opt-in): compresses generated image sub-sizes on upload and generates `.webp` siblings (via `WP_Image_Editor`, no external binaries), serves WebP in REST/CLI (so the MCP media tools resolve to WebP) and optionally on the front end, preserves full-size originals (reversible), plus a resumable bulk optimizer + restore. **Prompts**, **Brand Kits**, and **Templates** are now modules too.
- **AI Chat in the block editor (Pro).** The AI Chat editor panel now runs in the WordPress block (Gutenberg) editor as well as the Elementor editor — a floating chat that edits the current post's block content through the WordPress Content tools. AI Chat is now a proper **module** (a true on/off kill switch).
- **Plugin Dashboard.** A dedicated landing screen for EMCP Tools: large headline stat cards, an "Explore your toolkit" feature grid, featured video guides, and a Help & resources panel — with an at-a-glance **version / update-available** indicator.
- **In-dashboard updates for free users (via GitHub releases).** Free installs now receive native "update available" prompts on the Plugins / Updates screens (and support auto-updates), sourced from the project's GitHub releases — no more manual re-install to get the latest free version. Premium builds continue to update via Freemius.
- **Admin-bar MCP status.** A status node in the WordPress admin bar (green/grey/red) with a one-click toggle to enable/disable MCP exposure.
- **Self-hosted Geist font** for the plugin admin headings and accents (SIL OFL, no third-party font CDN).

### Changed
- The admin now opens on the new **Dashboard** tab; **Tools** moves to its own tab. The stat cards were removed from every other tab (they live on the Dashboard).

### Fixed
- **Themer body templates now fill the theme's content column.** On themes that wrap content in a flexbox container (e.g. Astra's two-container layout) a Themer body template only took its intrinsic width, leaving empty space beside it; it now stretches to fill the column.

## [3.0.0]

> The first major release of the rebranded **EMCP Tools** — the toolset's step beyond Elementor into general WordPress management, alongside a leaner, catalog-backed widget surface. This single 3.0.0 release bundles the MCP namespace rename, the widget consolidation, the WordPress **Content** tools (domain 1), the WordPress **Settings** tools (domain 2), the WordPress **Plugins & Themes** tools (domain 3), the WordPress **Media Library** tools (domain 4), the WordPress **Users** tools (domain 5), and — alongside the Performance Analyzer — a new **Security & Malware Scanner**. (Previous release: 2.2.0.)

### Changed (BREAKING)
- **MCP namespace + server route renamed `elementor-mcp` → `emcp-tools`.** As the toolset grows beyond Elementor, every tool is now under the `emcp-tools/` ability namespace (MCP tool names become `emcp-tools-<tool>`, e.g. `emcp-tools-list-widgets`), and the server route moved from `/wp-json/mcp/elementor-mcp-server` to `/wp-json/mcp/emcp-tools-server` (WP-CLI `--server=emcp-tools-server`). **Every existing AI-client connection (Claude Desktop/Code, Cursor, WP-CLI, the proxy) must be reconnected with the new route** — regenerate configs from the EMCP Tools → Connection tab. Your stored per-tool enable/disable toggles migrate automatically to the new slugs, so Pro tools stay disabled-by-default as before.
- **Widget tools consolidated.** The 62 per-widget convenience tools (`add-heading`, `add-button`, `add-form`, …) and the universal `add-widget` are removed, replaced by 5 catalog-backed tools: `list-widgets` (now with `tier`/`category`/`search` filters), `get-widget-schema` (curated params by default, `types[]` batch, `full:true` escape hatch), `add-free-widget`, `add-pro-widget`, and `update-widget`. **No capability is lost** — every widget and every curated parameter is still reachable via discover → inspect → act. AI scripts that hardcoded an old tool name must switch to `add-free-widget`/`add-pro-widget` with a `widget_type`.

### Added
- **WordPress Content tools — the first step beyond Elementor (domain 1).** Eight new MCP tools to manage general WordPress content from an AI agent: `list-post-types`, `list-taxonomies`, `create-post`, `get-post`, `update-post`, `list-posts`, `delete-post`, and `set-post-terms`. Create and edit posts, pages, and any custom post type — title, content (classic HTML or block markup), status, slug, author, taxonomy terms, custom fields, and featured image — without touching Elementor data (a `is_elementor` flag steers you to the Elementor tools for builder pages). Capability-gated and enabled by default; `delete-post` trashes by default (pass `force` to permanently delete).
- **WordPress Settings tools — beyond Elementor, domain 2.** Two MCP tools over a curated, typed allowlist of core WordPress settings.
  - `emcp-tools/get-settings` — read general/reading/writing/discussion/media/permalinks settings; doubles as discovery (returns each setting's type, label, enum options, writable flag). `manage_options`, read-only.
  - `emcp-tools/update-settings` — batch-update allowlisted settings; non-allowlisted, read-only (`admin_email`), or invalid values are reported in `skipped[]` (partial failure never aborts the batch). Changing a permalink setting auto-flushes rewrite rules. `manage_options`.
  - Safety: curated allowlist only (no arbitrary option access); `siteurl`/`home`, `users_can_register`/`default_role` excluded; `admin_email` read-only.
- **WordPress Plugins & Themes tools — beyond Elementor, domain 3.** Thirteen MCP tools to discover and manage plugins and themes: `list-plugins`, `search-plugins`, `install-plugin`, `activate-plugin`, `deactivate-plugin`, `update-plugin`, `delete-plugin`, `list-themes`, `search-themes`, `install-theme`, `switch-theme`, `update-theme`, `delete-theme`. Installs come **only** from the wordpress.org directory (by slug; no arbitrary URLs). Guardrails: EMCP Tools and Elementor can never be deactivated or deleted; the active plugin/theme is protected from deletion; each operation checks its own capability; install/update/delete need a directly-writable filesystem (clear error instead of an FTP hang). The 2 read tools (`list-plugins`/`list-themes`) plus the two `search-*` are enabled by default; the **9 mutation tools ship disabled-by-default** (admin opts in on the Tools tab).
- **WordPress Media Library tools — beyond Elementor, domain 4.** Three MCP tools to manage existing attachments: `get-media` (full detail of one attachment — every registered size, dimensions, metadata, alt/caption/description), `update-media` (edit title, alt text, caption, description — a one-call accessibility/SEO fix for library images), and `delete-media` (delete an attachment; **destructive and effectively permanent** — WordPress bypasses Trash for media unless `MEDIA_TRASH` is defined). `get-media`/`update-media` are enabled by default; `delete-media` ships **disabled-by-default** and additionally requires an explicit `confirm:true`. URL uploads continue to use the existing `sideload-image`.
- **WordPress Users tools — beyond Elementor, domain 5.** Four MCP tools for safe user management: `list-users` and `get-user` (read; admin-gated, never expose passwords/auth data) plus `create-user` and `update-user` (write). Hard guardrails: `create-user` can only assign non-admin roles (auto-generates a strong password and emails a set-password link — the password is never returned); `update-user` edits profile fields only (no role or password changes) and refuses any user with admin-level capabilities. There is deliberately **no delete-user** and no role-change tool, so MCP cannot escalate privileges, take over an admin, or lock anyone out. `list-users`/`get-user` are enabled by default; `create-user`/`update-user` ship **disabled-by-default**.
- **Filesystem tools.** Read/scan any file in the WordPress install (core, plugins, themes, uploads) — `read-file`, `list-directory`, `search-files` (enabled by default) — and, disabled by default, modify/delete: `write-file`, `edit-file`, `delete-file`. Every path is confined to the WordPress installation (no traversal/symlink escape); writes auto-back-up the original, refuse `wp-config.php`/`.htaccess`, require the `edit_files` capability (honoring `DISALLOW_FILE_EDIT`), record to an audit log, and `delete-file` needs `confirm:true`. `manage_options`.
- **Database tools.** Inspect the database with flexible read-only SQL — `list-tables`, `describe-table`, `query` (SELECT/SHOW/DESCRIBE/EXPLAIN only; writes, DDL, stacked statements, MySQL executable comments `/*! … */`, and file-access SQL like `INTO OUTFILE`/`LOAD_FILE` are rejected; results capped) — and, disabled by default, structured parameterized writes: `insert-row`, `update-rows`, `delete-rows`. Writes use `$wpdb` (no raw write-SQL, so no DDL), force a non-empty WHERE on update/delete, refuse `wp_users`/`wp_usermeta`, capture a before-image snapshot into an audit log, and `delete-rows` needs `confirm:true`. `manage_options`.
- **Context page — site-wide guidance for AI agents.** A new EMCP Tools → Context screen (after Connection) where you write stable, site-wide guidance (business identity, brand voice, content rules, guardrails) in Markdown. It is delivered to every connecting AI agent as the MCP server's `instructions` and applied automatically — no per-prompt setup. Includes a starter template, a character/token counter, and a live "what agents receive" preview; an on/off toggle controls delivery.
- **Connect tab — client-first flow + Claude Desktop one-click bundle.** The Connection screen now asks you to pick your AI client first; the connection options (one-click bundle, terminal command, AI setup prompt, or manual JSON) appear only after that choice, tailored to the selected client. Claude Desktop gets a downloadable `.mcpb` bundle (MCPB 0.3) that installs the MCP server without editing any config files — credentials baked in. The bundle is fully self-contained: it ships a single `server/index.js` (the proxy converted to CommonJS, with the credentials embedded) that runs on Claude Desktop's built-in Node.js — no `npx`, no network install, no separate Node.js, and no PATH or working-directory assumptions (the entry point is resolved via the MCPB `${__dirname}` variable).
- **WordPress Performance Analyzer.** New read-only MCP tool `analyze-performance` that audits server configuration, WordPress internals (database size, autoloaded options, post revisions, cron backlog, persistent object cache, OPcache, plugin count), and a target page (defaults to the frontpage; pass `url` or `post_id`) for performance bottlenecks. Returns a scored report (0-100 + A–F grade) with per-finding severities and ranked, actionable recommendations. Self-contained (no external API); same-host-enforced loopback fetch (rejects off-host and off-host redirects); degrades gracefully when the page fetch is blocked (server/database/config audit still returned). `manage_options`, enabled by default.
- **WordPress Security & Malware Scanner.** New read-only MCP tool `scan-security` — the security counterpart to the Performance Analyzer — that scans across four dimensions and returns a scored report (0-100 + A–F grade) with per-finding severities and ranked, actionable recommendations: (1) **malware heuristics** — scans PHP in uploads + active plugins/themes (pass `deep:true` for the whole tree) for eval/base64 obfuscation, request-driven code execution, command execution, webshell markers, large encoded blobs, and executable PHP under uploads; (2) **core integrity** — verifies WordPress core files against the official wordpress.org checksums (modified→critical, missing→warning); (3) **hardening** — DISALLOW_FILE_EDIT, debug output in production, `admin` username, XML-RPC, version disclosure (readme.html / generator meta), HTTPS, security headers; (4) **outdated/abandoned software** — outdated core/plugins/themes plus plugins closed or removed from wordpress.org, and inactive-component housekeeping. Self-contained (the only off-site calls are wordpress.org, optional and graceful offline); read-only. The malware walk is confined to the WordPress installation and bounded by file-count + time caps (`max_files`/`max_seconds`; partial results are flagged) and **never returns full file contents** — only path:line plus a short snippet — while excluding the plugin's own directory and its managed snippet sandbox. Optional `checks` selects a subset of malware/integrity/hardening/software. `manage_options`, enabled by default. The admin tools section was renamed **"Performance" → "Performance & Security"** and the scanner appears under it with a read-only badge.
- WordPress core's read-only context abilities (`core/get-site-info`, `core/get-user-info`, `core/get-environment-info`) are now surfaced on the EMCP server too.
- **`includes/widgets/`** — a curated widget catalog (`class-widget-catalog.php` + `catalog-{free,pro,woo}.php`, 62 widgets) that powers the consolidated widget tools.

### Changed
- **Elementor is now optional.** Previously the plugin refused to load without Elementor; now it loads and every WordPress tool (Content, Settings, Plugins & Themes, Users, Media, Performance, Security, Filesystem, Database, PHP Snippets) works on its own. The Elementor tool family (widgets, layout, templates, globals, brand kits, SEO/A11y, atomic, …) registers only when Elementor is active; otherwise a non-blocking admin warning explains how to enable it, and the Brand Kits and Templates tabs show a notice. Hard dependencies are now just PHP 8.1+, the WordPress Abilities API, and the bundled MCP Adapter.
- **Per-turn widget tool-list context cut ~10×** (~18–20k → ~2k tokens), freeing the model's context window and removing the need for Low-tools mode on most clients.
- **Tools admin page reorganized into Elementor / WordPress sub-tabs.** As the toolset grew well beyond page-building, the Tools screen now groups categories under two tabs — **Elementor** (Query, Page, Layout, Widgets, Templates, Global, Composite, SVG, Custom Code, Accessibility, Widget Builder, atomic) and **WordPress** (Content, Settings, Plugins & Themes, Users, Stock & Media, PHP Snippets, SEO). Each category is tagged with its platform so future page builders can get their own tab. Presentation only — no change to which tools are enabled or how they're gated.

### Migration
- Old per-widget disabled-tool toggles are cleared automatically (defaults seeder v5). Existing pages and templates are unaffected — this changes the tools, not `_elementor_data`.

## [2.2.0]

- Performance: **Leaner tool schemas — a lighter tool list on every request.** The per-widget convenience tools each published a large, fully-enumerated settings schema; multiplied across ~70 widgets, those dominated the MCP `tools/list` payload that an AI client re-sends on *every* turn. Each convenience tool now publishes a focused set of core parameters (content + primary layout + colours), while **every other setting still works** — it passes straight through to Elementor and stays fully discoverable via `get-widget-schema`. The tool list drops by roughly a third (~36% off the widget tools) with **no loss of capability**, leaving far more of the model's context for the actual page build.
- Fixed: **`get-widget-schema` now returns the COMPLETE control set — and styling is no longer wrongly rejected.** Outside the editor (WP-CLI / REST), Elementor's "Optimized Control Loading" strips a widget's style controls — typography, colours, alignment, shadows — from `get_controls()`. The schema generator was built from that stripped list, so an agent couldn't *discover* those controls and the settings validator **rejected valid style settings** (e.g. _"`title_color` is not a valid control for heading"_). The generator now opts into the full control set the same way Elementor's own CSS generator does, so schemas are complete in every context. Validation is also non-fatal as a backstop — an unrecognised key is passed through to Elementor (the real authority) instead of aborting the whole insert.
- Fixed: **Low-tools mode now genuinely caps the surface, and the UI reflects it.** Turning Low-tools mode on is now an override: it exposes exactly the curated essentials regardless of your per-tool toggles (which are preserved and resume when it's switched off). The Tools grid greys out and shows the paused, essentials-only state instead of looking unchanged, and saving any EMCP settings screen now shows a clear "Settings saved" confirmation.
- Fixed: **`add-atomic-paragraph` saved blank paragraphs** ([#56](https://github.com/msrbuilds/elementor-mcp/issues/56)). The convenience tool wrote the content to the wrong property name (`text` instead of the `e-paragraph` `paragraph` prop), so the text was silently dropped. Fixed — and while there, a sibling bug where `add-atomic-youtube` wrote the video URL to `url` instead of the `e-youtube` `source` prop was fixed too.
- Fixed: **`list-global-classes` failed when called with no arguments** ([#57](https://github.com/msrbuilds/elementor-mcp/issues/57)). Resolving the entire Class Manager registry aborted with a generic error if any single class had an unexpected structure (passing explicit `class_ids` still worked). Each class is now resolved defensively, so one malformed entry can no longer break enumeration of all the others.
- Security: **Custom CSS can no longer break out of its `<style>` block** (F-004). `add-custom-css` now neutralises the `</style>` end tag — the only way CSS injected into a `<style>` block can escape into live HTML — with a bypass-proof, loop-based strip that leaves all *valid* CSS intact (child/sibling combinators, media-range queries, `content` strings).
- Security: **SVG sanitiser closes a multiline event-handler bypass** (F-008). An inline `on*=` handler whose quoted value spanned a line break could slip past the sanitiser's regex; it now matches across line boundaries.
- Security: **The admin no longer exposes the server path to JavaScript** (F-020). The Connection tab localised the absolute server filesystem path to the proxy (e.g. `/var/www/.../bin/mcp-proxy.mjs`) into page JS — an information disclosure, and useless anyway since the proxy runs on the *client* machine. It now exposes only the filename; the npx runner remains the recommended config.
- Fixed: **Page saves are more robust in CLI/REST** (F-005). When Elementor's document save returns `null` (not only `false`) in a non-browser context, the direct-meta fallback now still runs, so content is never silently lost.
- Performance: `list-pages` and `list-templates` now set `no_found_rows`, skipping an unnecessary `SQL_CALC_FOUND_ROWS` pass (F-017/F-018).
- Developer: the PHPUnit suite is green again (448 tests). The v2 ability-registration refactor had left the capability tests unable to run; several security tests still referenced the pre-2.0 filename and the `uninstall.php` removed in 1.6.1. The suite now reflects the current architecture, and `get_all_tools()` cross-checks the curated admin catalog against the live ability registry to catch drift.

## [2.1.0]

- New: **PHP Code Snippets (Sandbox).** A free, capability-gated way for an AI agent to author server-side PHP — behind a hard human-approval gate. The AI can **validate** code and **create drafts** over MCP, but a draft has no executable file and **never runs until an admin activates it** in EMCP Tools → Sandbox (there is deliberately no "activate" tool). Every snippet passes a static **parse + security scan** (blocks `exec`/`eval`/backticks/variable-functions/file-writes/network/destructive SQL/obfuscation decoders) before it can be saved or activated; activation writes a sha256-verified file that runs inside try/catch with a `register_shutdown_function` guard that auto-deactivates a snippet that fatals. Six tools (validate / create / update / get / list / delete). Off by default.
- New: **`list-global-classes` tool** ([#55](https://github.com/msrbuilds/elementor-mcp/issues/55)). Resolves Elementor's **Class Manager** (Global Classes): it maps the opaque `g-` class IDs that show up on elements back to their human-readable names (`g-037bb9c` → `card-base`) and the CSS each one defines, per breakpoint/state. An AI agent can finally understand and debug a design-system-driven page instead of seeing only meaningless IDs. Read-only; Elementor 4.0+.
- New: **One-click authentication test** on the Connection tab ([#41](https://github.com/msrbuilds/elementor-mcp/issues/41)). After generating credentials, "Test authentication" sends a real request using only the Authorization header (not your login cookie) and tells you definitively whether a client will connect. If your server is **stripping the Authorization header** — the usual cause of `initialize: Unauthorized` on Plesk/Apache/LiteSpeed/IIS — it surfaces the exact `.htaccess` / nginx fix and a `curl` command to confirm.
- New: **OpenAI-strict tool schemas** (opt-in, [#42](https://github.com/msrbuilds/elementor-mcp/issues/42)). A Connection-tab toggle that emits strict JSON Schemas (every property required, optionals nullable, `additionalProperties:false`) for OpenAI-compatible strict function-calling clients like CrewAI that reject the default schemas. Off by default — the default schemas keep working for Claude, Gemini and Antigravity, which strict mode can break.
- Fixed: **Atomic widgets & containers silently failed to save** ([#36](https://github.com/msrbuilds/elementor-mcp/issues/36)). `add-atomic-widget`, `update-atomic-widget` and nested `add-div-block` were handing a boolean to the save layer (`save_page_data: bool given`), so the element was never written to the page. They now save correctly. Separately, Elementor 4.0 *throws* on invalid atomic settings — that's now caught and returned as a clean error instead of fataling the whole request.
- Fixed: **Setting theme-template conditions broke other templates** ([#38](https://github.com/msrbuilds/elementor-mcp/issues/38)). `set-template-conditions` (and `set-popup-settings`) cleared Elementor Pro's global conditions cache without rebuilding it, so applying a condition to one template silently stopped unrelated headers/footers from rendering. They now go through Elementor's own conditions manager, which writes the meta **and** regenerates the location cache correctly.
- Fixed: **Prompts & Brand Kits cards resetting to 0.** The library cards no longer drop to zero/defaults when the cached bundle expires — the counts read a durable mirror and refresh in the background, so you no longer have to keep clicking "Sync Library".
- Fixed: **Broken "Generate Configs" button when `admin.js` was quarantined** ([#44](https://github.com/msrbuilds/elementor-mcp/issues/44)). If security software or a host renames `assets/js/admin.js` (e.g. to `admin.j_`) during install, the JS-driven admin features silently did nothing. The plugin now detects this and shows a precise notice naming the mangled file, and a release-time verifier guards against ever shipping such a build.
- Improved: **Styled Changelog screen.** The in-plugin Changelog tab now renders like the website — version cards, category tags, formatted notes and sub-items — instead of raw markdown.
- Improved: **Code viewer & copy.** Generated code (snippets/widgets) opens in a slide-in viewer with copy/download, and shortcodes are click-to-copy.
- Developer: the bootstrap file (`emcp-tools.php`) was slimmed to bootstrap-only (910 → ~150 lines); all feature logic moved into dedicated classes (`EMCP_Tools_Bootstrap`, `…_Schema_Compat`, `…_Migration`, `…_Uninstaller`, `…_Pro_Ajax`, `…_Library_Refresher`). This also restored the uninstall-cleanup hook that was inadvertently dropped during the 2.0 rename.

## [2.0.2]

- Fixed: **Tool toggles & Low-tools mode wouldn't save.** After the 2.0 rename, the legacy-settings migration ran on *every* page load and copied the old `elementor_mcp_*` options (which linger in the database) over your current settings — so any change you saved on the **Tools** screen, including Low-tools mode, was silently reset on the next load. The migration now only seeds a new setting from the old one when it has **never** been set, so it can never overwrite your live choices.
- Fixed: **"Enable All" / "Disable All" also flipped Low-tools mode.** The bulk buttons selected every checkbox in the form, including the separate Low-tools-mode toggle — so "Enable All" silently turned Low-tools mode on, which then masked your individual toggles. They're now scoped to the tool checkboxes only.
- Fixed: **First-ever save on the Tools screen could invert.** The disabled-tools sanitizer is now idempotent (it reads the submitted checkboxes directly), so WordPress re-running it while creating the option for the first time can no longer flip the result.
- New: **`list-media` tool** ([#25](https://github.com/msrbuilds/elementor-mcp/issues/25)). Lets an AI agent discover and search images already in the **WordPress Media Library** — the site's own uploads — filling the gap where Openverse can only find generic stock. Backed by a direct `WP_Query` (no HTTP round-trip); the optional `search` matches the title, **alt text**, caption, and description, with `mime_type` / pagination / sort filters. Returns attachment IDs and URLs ready to hand to `add-image` / `add-widget`. Read-only (`edit_posts`); not part of the Low-tools essentials set.
- Improved: **Tools screen UI.** Section headers are now **collapsible** (click to show/hide a category; the state is remembered per section), and each category's **All / None** controls are now a clear segmented button instead of plain text links.
- Improved: **"Elementor Pro" vs "Pro" badges.** Tools that need **Elementor Pro** (widget shortcuts, theme builder, popups, dynamic tags, Pro custom code) now carry a distinct **"Elementor Pro"** badge, so they're no longer confused with the purple **"Pro"** badge reserved for **EMCP Tools Pro** features (SEO/Accessibility, Widget Builder). The "Pro Widget Shortcuts" section is renamed **"Elementor Pro Widgets"**.

## [2.0.1]

- Fixed: **Pro license activation.** The premium build now correctly reports itself as the premium version (it reads the bundled `.emcp-pro` marker), so Freemius shows the **license-activation** flow instead of the free connect/opt-in screen. Previously `is_premium` was hardcoded off, so the Pro zip behaved like the free version — if a customer skipped opt-in (or didn't click the confirmation email), no "Activate License" link or Account page appeared. Also corrected `has_paid_plans`/`has_premium_version` to reflect that the plugin has a paid tier.
  - **Already stuck on Pro 2.0.0?** Update to 2.0.1 and the **Activate License** option will appear. If your install is still wedged in the half-finished opt-in state, run the official **Freemius Fixer** (https://github.com/Freemius/freemius-fixer) to re-trigger the opt-in, **complete the opt-in**, and then **activate your license**.
- New: **Community button** in the admin header (every tab) linking to the EMCP Tools Facebook group.

## [2.0.0]

> **⚠️ Pro customers — one action after upgrading.** Because the plugin folder/slug changed (`elementor-mcp` → `emcp-tools`), the new install is a **separate plugin** to WordPress, so your Pro license does **not** carry over automatically. After you delete the old plugin and activate **EMCP Tools**, you will likely need to **re-activate your license and complete the Freemius opt-in/connection again** (EMCP Tools → opt-in / "Activate License"). **Your license stays valid** — this just re-links it to the renamed plugin. Free users have nothing extra to do.

- Changed: **The plugin was renamed from `elementor-mcp` to `emcp-tools`** as it grows beyond Elementor. The plugin folder, main file, text domain, and every internal PHP identifier were rebranded (`EMCP_Tools_*` classes, `emcp_tools_*` functions, `EMCP_TOOLS_*` constants). In the Plugins list it now shows as **"EMCP Tools"** (the old one stays "MCP Tools for Elementor", so it's clear which to remove). **Your AI clients keep working unchanged** — the MCP tool names and server (`emcp-tools/...`, `emcp-tools-server`) are intentionally left the same, so no connection config or skill needs editing.
- New: **Automatic, safe migration from the old install.** If the previous `elementor-mcp` plugin is still active, EMCP Tools pauses (it doesn't register anything) and shows a notice asking you to deactivate and delete it. Your settings — disabled-tool toggles, Low-tools mode, defaults marker, server toggle — and your banner dismissals are copied to the new keys automatically, and the migration runs *before* the old plugin's uninstall can wipe them.
- Note: Because the folder/slug changed, install `emcp-tools` as a new plugin, then remove the old `elementor-mcp` one when prompted. All PHP symbols are uniquely re-prefixed, so the two can sit side by side during the switch without fatal errors.

## [1.9.0]

- New: **AI Widget Builder (Pro).** Eight MCP tools let an AI agent design and register custom Elementor widgets from a structured spec — **no hand-written PHP**. A plugin-owned generator compiles the spec + an HTML template (`{{control}}`, `{{#if}}`, `{{#each}}`) into a `Widget_Base` class, escaping every value by control type, and writes it to an isolated sandbox under `uploads/emcp-widgets/` that never touches core, theme, or other plugin files. 35 supported control types — including group controls (typography, border, box-shadow, background), repeaters, responsive controls, and conditions — plus optional per-widget CSS/JS. New widgets auto-activate and appear under a **"Custom (EMCP)"** category in the Elementor panel. A runtime safety net (validation on create/update + a shutdown-handler isolation guard) demotes any malformed widget to draft instead of breaking the editor. Tools: `list-control-types`, `validate-widget-spec`, `create-custom-widget`, `update-custom-widget`, `get-custom-widget`, `list-custom-widgets`, `set-widget-status`, `delete-custom-widget`. Off-by-default; managed on the new **Widget Builder** admin tab. Ships with a `widget-builder` Agent Skill so connected agents know the spec format.
- New: **10 free brand kits.** The Brand Kits tab now ships 10 curated color + typography kits (spanning all 7 industries) that **anyone can apply for free** — one click replaces your site's global palette and fonts, with **backup-before-apply and restore included**. The full 50-kit library remains Pro. Under the hood, applying is now a free feature: the system-kit writer and backup store are capability-gated (`manage_options`) rather than license-gated. The MCP brand-kit tools (`list-brand-kits` / `apply-brand-kit`) stay Pro.
- New: **Get Support button** in the admin header, on every EMCP tab, linking to the new support portal at `support.msrbuilds.com`.
- New: **Pagination** on the Prompts, Templates, Brand Kits, and Changelog admin pages (12 cards/page; 10 releases/page). It's filter-aware, so it works alongside the category pills, and it also revived the previously-dead Templates category filter.
- Fixed: **Prompts page freeze.** The hidden copy-source textareas were positioned off-screen at `width:1px` but still in the layout tree, so the browser reflowed every prompt's full markdown into a 1px-wide column — a multi-second whole-tab hang with 50+ prompts. They're now `display:none`, removing them from layout entirely.
- Fixed: **Atomic (V4) detection** (#47 — thanks @BenKalsky). Atomic tools now register based on whether the atomic element types are actually registered (or the `e_atomic_elements` / `atomic_widgets` experiment is active) — not the `ELEMENTOR_VERSION` constant, which still reads `3.x` on atomic sites — and deliberately **not** on the page-editor opt-in alone, where atomic writes would silently no-op.

## [1.8.3]

- New: **One-click credential generation on the Connection tab.** Step 1 now shows an administrator dropdown (admins only, current user first and labeled "(you)") and a single **Generate Password & Configs** button that creates a fresh Application Password via `WP_Application_Passwords::create_new_application_password()`, shows it once, and auto-fills every client config below — no profile visit required. A collapsed "Use an existing Application Password instead" preserves manual entry. New AJAX handler `Elementor_MCP_Admin::ajax_create_app_password()` is nonce-protected, requires `manage_options` plus per-target `edit_user`, only targets administrators, and is HTTPS-gated (an app password can't authenticate over plain HTTP). Touches `includes/admin/views/page-connection.php`, `includes/admin/class-admin.php`, `assets/js/admin.js`.

## [1.8.2]

- New: **npx proxy configs in the Connection tab.** The "Generate Configs" flow now emits ready-to-copy npx runner blocks for Claude Code and Claude Desktop (`npx -y @msrbuilds/emcp-proxy@latest`, with the `WP_*` env + `MCP_PROTOCOL_VERSION`), grouped under "Remote WordPress — npx runner (recommended)". The bundled-proxy-file cards remain under "Local WordPress" for same-machine setups. This closes the gap where the only generated proxy config used this server's absolute filesystem path, which a remote AI client (which launches the proxy locally) can't reach. Touches `includes/admin/views/page-connection.php` + `assets/js/admin.js`.
- Note: the npx runner depends on the `@msrbuilds/emcp-proxy` npm package (published independently; `@latest` resolves to a working build).

## [1.8.1]

- Fixed: **Remote-proxy documentation.** Clarified that the Node.js proxy is launched by the AI client as a **local subprocess**, so for remote/shared-hosting sites its file path must point to the **client** machine — not to `bin/mcp-proxy.mjs` inside `wp-content/plugins/...` on the server, which the client can't reach. A user reported the old docs (which showed a server-side path under a "remote WordPress" heading) led to exactly this misread. Updated the Connection tab note (`includes/admin/views/page-connection.php`), `README.md`, and `mcp-config-examples.json`.
- New: **`npx` runner for remote connections** — `@msrbuilds/emcp-proxy`, published from `bin/` (`bin/package.json`, zero-dependency single file). Config becomes `"command": "npx", "args": ["-y", "@msrbuilds/emcp-proxy@latest"]`, so there's no local proxy copy to extract or keep in sync with the plugin version. The local-file method remains documented as the alternative.
- Changed: **Documented `MCP_PROTOCOL_VERSION`** (`2024-11-05`) in the connection docs, not just the release notes — it makes the proxy rewrite the adapter's `2025-06-18` handshake for clients (e.g. some Claude Desktop builds) that reject it.

## [1.8.0]

- New: **SEO & Accessibility toolkit (Pro)** — 7 MCP tools that audit and improve a page at the **structure** level, with **no external API and no inference cost** (pure PHP over the Elementor data layer + the site's SEO-plugin meta). The competitive wedge vs. prompt-only AI plugins is that these operate on the real page structure.
  - **SEO (4):** `audit-page-seo` (scored on-page report: H1, title/meta length, canonical, heading hierarchy, image alts, internal links, word count, optional target-keyword usage); `extract-keywords-from-content` (frequency/TF-IDF keywords + bigrams, stop-word filtered); `generate-meta-tags` (proposes an SEO title ≤60 + meta description ≤155; **writes to Yoast / Rank Math with `apply:true`**); `generate-schema-markup` (JSON-LD for Article / LocalBusiness / FAQPage / Service / Product; **injects via a managed, replace-in-place HTML widget with `apply:true`**).
  - **Accessibility (3):** `audit-page-a11y` (WCAG-oriented: best-effort color contrast with honest `inconclusive` when a background can't be resolved, missing alts, heading order, generic/empty link text, form-label coverage); `fix-color-contrast` (suggests/writes adjusted text colors to meet WCAG AA); `add-alt-text-from-context` (derives alt text from filename → nearest heading → page title).
- New: **Dry-run by default for every mutating path.** The two fixers and the two generator write-backs only change the site when `apply: true` is passed; all edits are reversible via Elementor revisions, and writes additionally enforce per-post ownership.
- New: **Off-by-default + user-toggleable.** All 7 tools are Pro-gated and ship disabled-by-default (seeded via a versioned defaults marker, `Elementor_MCP_Admin::DEFAULTS_VERSION`), so they don't push Pro users over a client tool cap. A new **"SEO & Accessibility"** category on the EMCP Tools tab lets users enable individual tools.
- New: Shared, unit-tested helpers — `Elementor_MCP_Content_Extractor` (one normalized page view: headings, text, images + alt resolution, links, form fields, word count, contrast contexts), `Elementor_MCP_Color_Contrast` (WCAG relative-luminance/contrast/suggest math), `Elementor_MCP_Seo_Meta` (Yoast / Rank Math / core read + write abstraction).
- Changed: **Documentation accuracy** — CLAUDE.md and readme.txt now state the real minimums (WordPress **6.9+**, PHP **8.0+**) instead of the stale 6.7/6.8/7.4 claims.

## [1.7.4]

- New: **Bundled MCP Adapter.** The `wordpress/mcp-adapter` package now ships inside the plugin (`includes/vendors/mcp-adapter/`), so users no longer install it as a separate plugin. WordPress 6.9+/7.0 already includes the Abilities API in core, so on those versions Elementor is the only external dependency. `Elementor_MCP_Adapter_Bootstrap::ensure()` loads the bundled copy via a minimal PSR-4 autoloader **only when no standalone MCP Adapter plugin is already active** — when one is, the plugin defers to it (no double-load, no version clash). Only the adapter's `includes/` source is vendored; its dev-only Composer `vendor/` is not (the package has zero runtime dependencies).
- New: **"Activate Abilities API for EMCP" gate.** A toggle on the Connection tab controls whether the MCP server is exposed (option `elementor_mcp_server_enabled`, **on by default**). When off, abilities remain registered in core but no `/wp-json/mcp/...` server is created — nothing is reachable by AI agents. Includes a security note that connected agents can create/edit/delete Elementor content when enabled. The toggle uses its own settings group so it can't overwrite the Tools-page options.
- New: **Connection tab status** now reports the MCP Adapter source (Active (bundled) vs. Active (plugin)) and an MCP Server Enabled/Disabled card.
- Changed: **Dependency check no longer treats the MCP Adapter as a separate install to chase.** The adapter is provided by the bundle (or an active standalone plugin); the check only fails if the bundled source is missing/corrupt. The Abilities API line notes it is core in WordPress 6.9+.

## [1.7.3]

- New: **Industry Skill Packs** for the Pro Agent Skill — 10 vertical knowledge files (`verticals/<slug>.md`) covering Dental, Med-Spa, Therapy, Fitness, Automotive, Food & Restaurant, Wedding, Real Estate, Legal, and Photography. When the AI agent recognizes the site's industry, it reads the one matching pack **before building** and applies that trade's brand voice, SEO keywords, page set/section order, conversion patterns, and compliance notes — plus the exact **Brand Kit + prompt slug(s) + template** combo for the vertical. This is the connective tissue that turns the three Pro content libraries (Prompts, Templates, Brand Kits) into curated per-industry combos the agent applies automatically.
- New: The bundled `SKILL.md` gained a **vertical-routing section** (a trigger table near the top) so the agent loads only the single relevant pack — progressive disclosure keeps token cost flat regardless of how many packs ship.
- New: **Skills admin tab** lists the bundled industry packs (read live from the shipped `verticals/` folder) and explains the zero-config auto-routing, so the value is visible before download.
- New: **Build-time validator** (`tools/verticals/`) — a zero-dependency Node linter that checks every vertical file's `brand_kits` / `prompt_category` / `prompt_slugs` / `template_slugs` against a vendored, content-free slug snapshot of the shipped libraries; existence failures error, deliberate cross-category prompt pulls warn. Caught two real reference defects during authoring.
- Changed: Industry packs ship in the **premium build only** (inside the already-premium `skills/` folder); free installs are unaffected and the download endpoint required no change (it bundles the whole skill folder as-is).

## [1.7.2]

- New: **Brand Kits Library** for Pro subscribers — one-click coordinated color palettes + typography systems. 16 curated kits across 4 categories (Corporate & Tech, Creative, Hospitality, Trades). Click **Apply** on a kit and the entire Elementor site re-skins in seconds. Auto-synced from the EMCP Tools server with the same 24h transient-cache pattern as Premium Prompts and Templates.
- New: **Site-wide re-skin on apply.** Applying a kit (a) replaces the four Elementor **system** color + typography slots (`system_colors` / `system_typography`) so elements referencing the global tokens update, **and** (b) writes the active kit's **Theme Style defaults** — default body/heading typography (font family + weight, sizes left intact) and body/heading/link colors — which is what actually changes the visible site font and palette. The matching Google Fonts are enqueued automatically via the regenerated kit CSS.
- New: **Backup & restore.** Each apply snapshots the current global settings (system + custom colors/typography **and** Theme Style defaults) into a private `emcp_kit_backup` post type before mutating anything. A **Restore from backup** section on the Brand Kits page rolls back — selective by default (only kit-applied tokens), with an opt-in full-clobber mode. Backups are intentionally retained on uninstall as recoverable user content.
- New: **Four Pro-gated MCP tools** — `list-brand-kits`, `apply-brand-kit`, `replace-system-colors`, `replace-system-typography` — so AI clients can enumerate and apply kits programmatically. Free sites see none of them (no impact on client tool caps).
- New: **Brand Kits admin tab** between Templates and Skills — category filter pills, self-contained preview cards (font-outlined SVGs, no third-party requests from wp-admin), an apply-confirmation modal with a backup checkbox, and a "View site →" toast linking to the most-recently-modified Elementor page.
- New: **`Elementor_MCP_System_Kit_Writer`** — a single, capability-gated write path for the kit's system + Theme Style settings, with a verified persistence fallback for WP-CLI / HTTP-proxy contexts where `Document::save()` is unreliable.
- Changed: **Stats bar** shows a Brand Kits count for Pro sites with a synced library.

## [1.7.1]

- New: **Premium Templates library** for Pro subscribers — apply ready-made Elementor page designs to a new draft page in one click, or import them into Elementor's Saved Templates library where they're insertable from any page's "Add Template" picker. Auto-synced from the EMCP Tools server with the same 24h cache pattern as Premium Prompts. Category filter pills + per-card thumbnail support. Accepts Elementor's native template export shape (`content` + `page_settings`) so designs exported from the editor's "Save as Template" flow drop straight in.
- New: **EMCP Agent Skill download** for Pro subscribers — a pre-written Anthropic Agent Skill that teaches Claude (and any compatible AI client) exactly how to build, edit, and style Elementor pages through the MCP tools. New Skills admin tab with a one-click `emcp-skills.zip` download and per-client install guides for Claude Code, Claude Desktop, Cursor, Windsurf, Antigravity, and a universal fallback. Skill folder is bundled in the premium build only.
- New: **Global "Upgrade to Pro" admin banner** on non-EMCP admin screens for non-Pro sites — gradient design with feature highlights, dismissible per-user (persists indefinitely via user-meta).
- New: **"Read the Docs" & "Upgrade to Pro" header buttons** wired to the external docs and pricing pages at `https://emcp.msrbuilds.com`. Upgrade button hidden for sites with an active Pro license.
- New: **"50+ more templates on the way"** inline notice on the Templates tab so Pro users know the library is actively expanding.
- New: **`Elementor_MCP_Pro_Templates::import_to_library()`** — programmatic API for adding templates into Elementor's `elementor_library` CPT with the right `elementor_library_type` taxonomy term, `_elementor_data` meta, and optional `_elementor_page_settings` meta.
- Changed: **Prompts tab hides the bundled 5 sample prompts when a Pro user has the premium library loaded** — the 50+ premium prompts already include the samples, so showing both was duplication. Fallback to samples still kicks in if a Pro user's bundle fetch fails (network blip, sync error).
- Changed: **Stats bar prompt count reflects the active premium library** — Pro sites with a synced bundle see the real number (50 prompts) instead of the 5 bundled samples.
- Changed: **All in-plugin "Upgrade to Pro" links now point at the external website pricing page** (`https://emcp.msrbuilds.com/pricing`) and open in a new tab — gives users the full plan comparison + FAQ rather than the Freemius in-admin pricing iframe.
- Changed: **Admin menu** — added Templates and Skills submenus between Prompts and Changelog.
- Changed: **Templates build script** on the server now auto-discovers thumbnails by slug from `public/screenshots/templates/` so template JSON files don't need to repeat the URL; case-insensitive match works for any of `.png`, `.jpg`, `.jpeg`, `.webp`, `.avif`.
- Changed: **Reverted the Freemius pricing-screen wrapper** — the pricing iframe renders with its native Freemius styling again. Users land on the external website pricing page from any in-plugin Upgrade button instead.
- Fixed: **Premium prompts/templates cache transients are scrubbed on uninstall** — prevents stale bundles persisting if the plugin is reinstalled later.
- Fixed: **Upgrade-banner dismissal user-meta is scrubbed on uninstall** across every user.

## [1.7.0]

- New: **Premium Prompts library is now live** for Pro subscribers — 50+ industry-specific landing-page prompts across 10 categories (Automotive, Food & Dining, General, Health & Wellness, Home Services, Lifestyle & Entertainment, Pets, Professional Services, Retail, Weddings). Auto-synced from the EMCP Tools server when a valid license is active. Free users continue to see the 5 bundled sample prompts plus an upgrade CTA.
- New: **Category filter pills + Sync Library button** on the Prompts admin page for Pro users. Click a category to narrow the grid; click Sync Library to force-refresh the 24-hour transient cache.
- New: **"Read the Docs" link** in the admin header pointing at the comprehensive docs site at https://emcp.msrbuilds.com/docs.
- New: **Two-build distribution.** Each release now ships two zips: `elementor-mcp-{version}.zip` (free) and `emcp-pro-{version}.zip` (premium). The premium zip includes a `.emcp-pro` marker file at the plugin root; the bootstrap detects it and flips Freemius's `is_premium` flag so the account screen reads "Pro version" on paying customers and "Free version" on everyone else. Freemius serves the right zip per license automatically.
- Changed: Premium prompts fetcher now sends authentication via the `Authorization: Bearer` HTTP header (with site URL and plugin version in `X-EMCP-Site` / `X-EMCP-Plugin-Version`) instead of as URL query parameters. License keys are credentials and shouldn't be in query strings that intermediate proxies and access logs preserve.
- Changed: Default premium prompts endpoint moved from `https://msrbuilds.com/api/emcp/prompts.json` to the dedicated subdomain `https://emcp.msrbuilds.com/api/emcp/prompts.json`. Filterable via `elementor_mcp_pro_prompts_endpoint`.
- Changed: Admin header — removed redundant "Contact Me" button (Freemius adds Contact and Account items to the EMCP Tools menu automatically), renamed "Get Premium Prompts" → "Upgrade to Pro" pointing at the Freemius checkout (`emcp_pro_fs()->get_upgrade_url()`), and the upgrade button is now hidden for sites with an active Pro license.
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
