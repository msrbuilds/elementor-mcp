# Changelog

All notable changes to MCP Tools for Elementor are documented in this file.

## 1.17.0 — 2026-07-07

- New: **SiteAgent governance bridge** — capture-before-write safety for destructive page edits, active only when the [SiteAgent worker](https://github.com/Digitizers/SiteAgent) (`digitizer-site-worker`) is installed alongside this plugin. When present, every **destructive** ability that targets a page (any tool carrying a `post_id` — `delete-page-content`, `update-element`, `remove-element`, `import-template`, `batch-update`, the atomic add/update tools, etc.) is transparently wrapped so the page's Elementor state (`_elementor_data` + `_elementor_page_settings`) is snapshotted through SiteAgent's snapshot engine **before** the write, and rolled back if the write fails. This gives agent-driven Elementor edits the same reversal safety SiteAgent already gives its own power tools.
  - **Soft dependency** — when SiteAgent is not installed, `Elementor_MCP_Governance::is_active()` is false, nothing is wrapped, and behaviour is identical to the standalone plugin. The plugin never hard-requires SiteAgent.
  - **Fail closed** — if the pre-write snapshot cannot be captured (no rollback point), the write is refused rather than made blind. A write that returns `WP_Error` or throws is rolled back to the pre-write page state.
  - **Governance seams** — fires `elementor_mcp_governance_write` (name, post_id, snapshot_id, result) after a successful governed write so the gateway can offer an undo, and `elementor_mcp_governance_rolled_back` after a failure is reverted.
  - Scope: page-targeting writes only. Kit- and repository-scoped writes (global classes, variables, system kit — no `post_id`) pass through ungoverned for now; server-enforced approval grants and post-write render checks are follow-up planks.

## 1.16.0 — 2026-07-07

- New: **Interactions (per-element animations) CRUD** (Elementor 4.0+) — four tools that let an agent author Elementor 4's Interactions: the per-element scroll / hover / click animations attached to an atomic element. An interaction pairs a trigger (when it fires) with an animation preset (a fade/slide/scale in/out, with timing + easing). Each element in `_elementor_data` carries a top-level `interactions` field (sibling to `id`/`elType`/`settings`/`elements`), stored as a JSON-encoded string of `{ version:1, items:[…] }` where each item is a nested atomic `$$type` tree (`interaction-item` → `animation-preset-props` → `timing-config` / `config-v2`). Because the field is top-level — not inside `settings` — the tools walk the page tree and mutate `$element['interactions']` directly, then save the page. Registers only when BOTH the `e_interactions` and Atomic Widgets experiments are active (class/feature existence alone would let writes land while the runtime feature is off); writes are gated on `manage_options`, the read tool on `edit_posts`. Pro triggers / effects / easing are gated on Elementor Pro (permissive only when unresolvable).
  - New: `list-interactions` — lists the Interactions on an atomic element in ergonomic shape `{ interaction_id, trigger, effect, type, direction, duration_ms, delay_ms, easing }`, decoded/unwrapped from the element's `interactions` field.
  - New: `add-interaction` — adds an animation from ergonomic fields (trigger default `load`, effect default `fade`, type `in`, direction `''`, duration 600ms, delay 0ms, easing `easeIn`), validated against the enums (+ Pro gating). Writes a `temp-<hex>` interaction_id, saves the page through the document (whose `elementor/document/save/data` filter runs `Parser::assign_interaction_ids()`), then re-reads the page to surface the canonical `{post_id}-{element_id}-{hash}` id. If the save falls back to a raw meta write, the temp id persists.
  - New: `edit-interaction` — the differentiator: an id-addressable in-place edit. Finds the item by its (canonical) `interaction_id`, patches only the provided fields in the `$$type` tree — preserving the id and every untouched field (including Pro-only nodes we don't model) — and saves. Returns `not_found` if the id isn't on the element.
  - New: `delete-interaction` — removes the item with the given `interaction_id` from the element's items and saves; `not_found` if absent.

## 1.15.0 — 2026-07-07

- New: **Variables (design tokens) CRUD** (Elementor 4.0+) — six tools that let an agent author Elementor 4's Variables: the global color / font / size tokens that Global Classes and atomic styles reference. Writes go through Elementor's canonical Variables `Repository` (the REST backend) on the active kit (`_elementor_global_variables`), so token records, uniqueness/count-limit enforcement and the tombstone shape match the editor exactly. Registers only when the Variables repository is present **and** the `e_variables` + Atomic Widgets experiments are active (class-existence alone would let writes land while the runtime feature is off); writes are gated on `manage_options`, reads on `edit_posts`.
  - New: `list-variables` — lists all Variables (colors, fonts, sizes) in public shape `{ id, type, label, value, order }`, excluding soft-deleted.
  - New: `get-variable` — returns a single Variable by id, or a `not_found` error.
  - New: `create-variable` — creates a Variable from `label` + `type` (color|font|size) + `value`. Validates the value per type (color = strict hex `#RGB`/`#RRGGBB`/`#RRGGBBAA`, named colors rejected; size = `<number><unit>` or a CSS-function expression like `clamp()`/`calc()`; font = a font-family name), stores under the registered internal type key (`global-color-variable` / `global-font-variable` / `global-size-variable` — size dimensions and CSS-function expressions both use the size type, the only one Elementor registers), mints an `e-gv-` id, and enforces Elementor's label-uniqueness + count limit — mapping each to a clear error.
  - New: `edit-variable` — edits a Variable in place (label and/or value), preserving its id so bindings survive. The public type is fixed (matching the editor's own update semantics); the new label/value are validated before the write.
  - New: `delete-variable` — soft-deletes a Variable by id — tombstoned (BOTH `deleted` + `deleted_at`, so Elementor's own services/renderer/snapshot filters all hide it), not purged, so it can be brought back.
  - New: `restore-variable` — restores a previously soft-deleted Variable back into the active token set (guarded when a build lacks `restore()`).

## 1.14.0 — 2026-07-06

- New: **Global Classes write tools** (Elementor 4.0+ Class Manager) — the companion write half of `list-global-classes`, letting an agent author the design system itself. All four are gated on `manage_options` and register only when Elementor exposes a supported Global Classes write API.
  - `create-global-class` — authors a reusable Global Class from a human-readable label and an ergonomic CSS-prop->value `styles` map (values are wrapped into Elementor's atomic `$$type` prop format automatically), with optional responsive/state `variants`. Mints a `g-<7hex>` id (editor format), and validates props against Elementor's atomic `Style_Schema` when present — rejecting unknown properties / type mismatches with the schema embedded in the error so an agent can self-correct without a round trip. Refuses when Elementor's Global Classes count cap (100) is reached.
  - `update-global-class` — edits a Global Class in place, preserving its `g-` id so element bindings survive. `styles` replaces only the base/desktop variant (other variants kept); `variants` replaces matching breakpoint/state variants; `label` renames.
  - `delete-global-class` — removes a Global Class by `g-` id. Elementor ignores dangling class references left on elements (no cascade).
  - `apply-global-class` — appends a Global Class to an atomic element's `settings.classes` on a page. Non-atomic elements (no classes control) are rejected with their compact schema embedded in the error; re-applying an already-present class is a no-op.
  - Writes go through Elementor's touched-item `apply_changes()` API (bulk `put()` fallback on older builds) so a single change reconciles only that class's editor-preview state and never clobbers a user's unpublished drafts for other classes; writes target the published/frontend context, not the editor preview, respecting the publish boundary.

## 1.13.0 — 2026-07-06

- **Premium tier unlocked.** 17 tools upstream gated behind a Freemius paid license this fork cannot activate now register for everyone: SEO audits (4), accessibility audits (3), the full Widget Builder (8), and the two **local** system-kit writers (`replace-system-colors`, `replace-system-typography`) — plus the generated-widget loader/store runtime. Six `can_use_premium_code()` gates are replaced by `emcp_fork_premium_tools_enabled()` (default `true`, filterable kill-switch).
- **Brand-kit split.** `list-brand-kits` / `apply-brand-kit` fetch from upstream's licensed hosted content service, which the fork does **not** unlock, so they now register only on a site that actually carries a license (previously they'd have surfaced but every call returned `no_license`). The two local system-kit writers act on this site's own kit and unlock freely.
- **Admin: unlock takes effect.** The Tools tab renders the SEO/A11y + Widget Builder categories for any install with the pack enabled (was gated behind the dead license check, leaving the tools invisible/unremovable), and a one-time reconciliation (`Elementor_MCP_Plugin::ensure_premium_unlock_applied()`, run on **every** request path — not just admin — so headless/cron/WP-CLI-upgraded installs are covered too) un-disables those slugs on previously-seeded installs so the unlock takes effect — but **preserves deliberate admin choices**: a pack is un-disabled only when the seeder default-disabled it *and* every tool in it is still off (pristine default); if an admin enabled any tool in a pack, the pack is left untouched.
- The admin marketplace fetchers for upstream's hosted Pro content (templates/prompts/brand-kit bundles/skills downloads from emcp.msrbuilds.com) remain gated — that content is not ours to unlock.
- Regression suite: `PremiumTierUnlockedTest` pins the unlocked surface per pack, the filter kill-switch, the Widget Store `manage_options` guard, the hosted-brand-kit fail-closed path, and a cross-check that the migration slug lists stay in lockstep with the ability classes. (Also wired the test file into the phpunit `Unit` suite — root-level test files were not being auto-discovered.)

## 1.12.2 — 2026-06-13

- Plugin header now reflects the Digitizers fork identity (`Author: Digitizers`, `Plugin URI: github.com/Digitizers/elementor-mcp`); the original author (Mian Shahzad Raza / msrbuilds) is credited in the description. Also fixed a malformed `Plugin URI`.
- Admin Changelog tab: the parser now accepts unbracketed `## x.y.z` headers (not only `## [x.y.z]`), so every recent release — which had been invisible in the admin view — displays again.

## 1.12.1 — 2026-06-12

Codex review follow-ups on the ported performance + security tools:
- analyze-performance: count `auto-on` autoloaded options (WP 6.6+); bound the loopback response at the request level; validate the full origin (scheme+host+port), not just host, incl. redirects (SSRF).
- scan-security: restrict "uploads" detection to the real uploads root (no false positives on plugin/theme `uploads/` subdirs); scan active single-file plugins in shallow mode; don't penalize header scoring when the loopback fetch failed; fall back to substr() when mbstring is absent.

## 1.12.0 — 2026-06-12

- New: `scan-security` — read-only security & malware scan (malware heuristics, core-integrity checksum diff, hardening audit, outdated/abandoned software) → scored report; returns path:line + snippet, never full file contents. Ported from upstream msrbuilds/elementor-mcp (v3.0.0), including the sibling-prefix walk-confinement, regex, and false-positive hardening fixes (snippet-sandbox exclusion dropped — the fork ships no sandbox).

## 1.11.0 — 2026-06-12

- New: `analyze-performance` — read-only page + server + WordPress performance audit producing a scored (0-100 / A-F) report with ranked recommendations. Ported from upstream msrbuilds/elementor-mcp (v3.0.0), including the same-host-redirect SSRF hardening.

## 1.10.1 — 2026-06-12

- CI: PHPUnit now runs the full committed suite (added SEO + tests/unit root coverage that were previously unregistered); the matrix includes PHP 8.0 (php -l lint) to match the declared `Requires PHP: 8.0`.

## 1.10.0 — 2026-06-11

- New: `list-global-classes` — reads Elementor's Class Manager (Global Classes), mapping opaque `g-<hash>` IDs to their human names and the CSS each defines per breakpoint/state. Read-only, Elementor 4.0+. Ported from upstream msrbuilds/elementor-mcp (#55, with the #57 defensive-resolve fix).
- Fixed (#56): `add-atomic-youtube` now writes the `e-youtube` `source` prop (was `url` → silently dropped, no video). `add-atomic-paragraph` already wrote the correct `paragraph` prop in this fork's 4.x work, so no change was needed there.
- Improved: `get-widget-schema` returns the complete control set (style controls — typography/colour/shadow — included) via the full-controls path (`Performance::set_use_style_controls`); settings validation is now non-fatal — unrecognised keys pass through to Elementor (the real authority) instead of aborting the insert.
- Improved: leaner convenience-widget schemas — core params published, the rest pass through and stay discoverable via `get-widget-schema`.

## 1.9.1 — 2026-06-11

Security hardening (ported from upstream msrbuilds/elementor-mcp 4bcefc5):

- F-004: add-custom-css neutralises the `</style>` breakout (bypass-proof loop strip; valid CSS preserved).
- F-008: SVG sanitiser matches `on*=` event handlers across line breaks (multiline bypass closed).
- F-020: admin no longer localises the absolute server path to page JS (filename only).
- Null-save: save-page-data/settings persist meta even when Elementor's document save() returns null.
- Query perf: list-pages WP_Query uses no_found_rows.
- Test suite updated to the fork's actual file layout (elementor-mcp.php; uninstall.php removed in 1.6.1).

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
