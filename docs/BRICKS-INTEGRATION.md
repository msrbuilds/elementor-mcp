# Bricks integration plan

## Target and architecture

Verified against Bricks **2.3.13 and 2.4.1**, supporting 2.3.13 through 2.4.x. The adapter continues to use native element, metadata, permissions and CSS APIs; this does not add integration with Bricks native AI abilities.

Bricks is an EMCP Pro standalone integration. It shares the scalar Page Builders selector with Elementor/BeBuilder, while Gutenberg and its existing block packs remain independent. Only a selected, active, supported Bricks installation exposes the Bricks tab and abilities. Every execution rechecks availability. AI Chat gets a dedicated Bricks group.

## Implementation sequence

1. **Discovery:** runtime version/context, loaded element catalog and control schemas from Bricks (including registered addons).
2. **Pages and elements:** list/read pages; create drafts; replace a flat element tree; add, update, move, and remove elements. Read and edit existing local templates in their correct content/header/footer area.
3. **Design and templates:** discover local templates and read the allowlisted global classes, variables, palettes, breakpoints and theme styles. Global design writes and template conditions/creation are follow-up coverage, not implied by page editing.
4. **Admin and transport:** dedicated Bricks sections, default-disabled write tools, Pro loading/packaging, registry gating, AI Chat discovery.
5. **Verification:** unit/regression suites, then disposable local pages through the registered abilities, Bricks save/CSS lifecycle and frontend rendering. Verify selector exclusivity, Gutenberg coexistence, stale edits, invalid trees, permission denial, and disabled callbacks.

## Save contract

- Require WordPress post capabilities and Bricks builder access. Structural writes require full Bricks access; partial/custom builder roles are not supported for writes in this first release.
- Require the content hash returned by `bricks-get-page` on every mutation of an existing page. Respect WordPress editor locks and use an EMCP per-post lock for competing EMCP requests.
- Validate IDs, registered element names, parent/children symmetry, duplicate children, cycles and nestability before mutation. Preserve unknown settings/envelope fields on incremental edits.
- Use Bricks' element security helper, WordPress metadata APIs with correct slash handling, revisions and `wp_update_post` to run Bricks' CSS save hook. Never emulate its AJAX request or generate code signatures.
- Component instances and executable-code changes are explicitly rejected in this first release. Existing code can be preserved unchanged. No raw site-settings or credential access.
- Leave Gutenberg `post_content` intact. New pages are drafts; publishing is a separate WordPress action.

## Acceptance checklist

- [x] Bricks card and its own Tools tab/sections; browser-tested disable/save hides Bricks, enable/save restores it, Gutenberg remains.
- [x] Runtime element discovery/schema works on 2.3.13.
- [x] Draft create → insert → update → move → remove → reload → native Bricks frontend render.
- [x] External CSS regeneration through the native save hook; test files are redirected into the writable workspace and removed afterward. Inline element rendering also passes.
- [x] Stale hashes, invalid/cyclic trees, competing-writer locks, anonymous/per-post permission denial and disabled integrations/tools tested. WordPress editor-lock handling uses its native check.
- [x] Header/footer template areas resolved correctly; design reads use an explicit option allowlist.
- [x] Public and Pro regression suites, PHP lint and manifest inclusion checked.

## Implementation and test record (2026-09-16)

13 tools in four Bricks-only sections: Discovery, Pages, Elements, Templates & Design. Six write tools default to off. The local site is left with Bricks selected and its write switches still off. Reconnect MCP clients after changing the selection or tool switches.

Run the repeatable local test with `php scripts/e2e/bricks-smoke.php`. It exercises registered WordPress abilities on the real theme, creates disposable drafts/templates and revisions, and cleans them up in `finally`. It temporarily opts Bricks tools in for the test and restores the previous disabled-tool list. The test uses the existing local administrator and refuses non-local hostnames.

This is the initial 2.3.13–2.3.x adapter. It does not claim full Bricks parity: component instances, PHP/code editing, template creation/conditions and global design writes remain follow-up work. It does not install the supplied third-party skill globally or redistribute its example library.

## Sources

- [User-provided Bricks skill](https://github.com/wpgaurav/bricks-skills/blob/main/SKILL.md), especially JSON formats, style settings and nested element structures. Its examples target 2.3.6; implementation APIs are checked against local 2.3.13. Skill use here concerns integration engineering, not a client layout: no rem conversion or reference-asset choices are required.

- Installed Bricks 2.3.13: `includes/elements.php`, `includes/ajax.php`, `includes/helpers.php`, `includes/capabilities.php`, `includes/builder-permissions.php`, `includes/assets/files.php`.
- [Official data schema](https://academy.bricksbuilder.io/developer/schema/)
- [Official AI abilities documentation](https://academy.bricksbuilder.io/builder/features/ai-abilities-and-skills/)

## Reusable plan for subsequent builders

For each builder, record the installed/tested version and storage format; identify its discovery, permission, save and asset APIs; define the bounded initial tool set and exclusions; wire its independent selector/tab/tools; add malformed-data and permission tests; then run an actual build/edit/render round trip before expanding to the next builder.

## Bricks 2.4 compatibility (2026-09-23)

The previous upper bound rejected every 2.4 installation. Extended the tested minor range to below 2.5, updated admin requirements and context, and added regression coverage for accepted 2.3.13/2.4 versions and rejected older/future versions. Read-only `wp eval-file` probes confirmed the native API entry points on both local sites with Bricks 2.4.1.

Run `php scripts/e2e/bricks-smoke.php` directly from the development plugin checkout. It now boots elementor-mcp.test, refuses the dev hostname, creates only disposable drafts/templates, and restores the previous tool settings. Run directly rather than through WP-CLI, which can initialize the ability registry before the smoke selects its builder. All 35 live checks passed on 2.4.1, including native rendering, CSS regeneration, revisions, header/footer areas and permission/lock guards. Focused PHPUnit run: 38 tests, 274 assertions. Existing component/code/global-write limitations remain.
