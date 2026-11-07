# Oxygen 6 integration

## Plan and implementation

Target the installed Oxygen **6.1.3** on both local sites. Gate the adapter to
Oxygen mode and 6.1.3 or later, excluding Oxygen Classic and Breakdance mode.

1. Verify native tree, storage keys, permissions, save/cache APIs and styles.
2. Register standalone Oxygen selection, dedicated tab, 11 sections and 38 tools.
3. Provide native page CRUD, element schemas, templates/components discovery,
   design-system reads/writes, advanced authoring and dependency-aware library transfers.
4. Keep new write tools disabled on upgrade; preserve prior tool choices and
   independent Gutenberg block-pack toggles.
5. Bundle a self-contained guide for injection and both download formats.
6. Test validation/permissions/defaults and native editor/frontend. Test pages
   belong only on elementor-mcp.test and must be authored over MCP.

## Native contracts

- Shared Breakdance PHP namespace, but BREAKDANCE_MODE=oxygen.
- Native storage: _oxygen_data, _oxygen_futurelayer_meta,
  _oxygen_template_settings; no writes to _breakdance_* or classic ct_* data.
- Nested root tree, unique IDs and _nextNodeId. Native save_document handles
  modified timestamps, revisions, stylesheet and dependency caches.
- CSS classes are global oxy_selectors_json_string records. Elements attach
  class IDs at meta.classes. Class properties are breakpoint-first; saveSelectors
  manages global revisions and cache generation. Existing collections and other
  selectors are preserved. Hash checks and EMCP locks guard stale/concurrent calls.
- Native editor races after the hash check cannot be eliminated by EMCP locks;
  existing WordPress editor locks are honored for page writes.

## Coverage — September 20, 2026

The adapter provides **38 tools across 11 sections**, gated to the installed
Oxygen 6.1.3 or later native APIs. Gutenberg and independently enabled block packs
remain available beside the selected standalone Oxygen integration.

| Area | Implementation |
|---|---|
| Pages/elements | Native tree CRUD, registered schemas, hashes, locks, revisions and caches |
| Templates/headers/footers | Create/copy/edit, location, priority, disable, condition groups and inheritance |
| Conditions | Registered catalog, supported operands/contexts and searchable native values |
| Components | Master editing, exposed properties, instance overrides, nested references and cycle checks |
| Design Library | Provider settings, bounded safe-HTTP browsing, native design export/import and dependency remapping |
| Document lifecycle | Rename, trash and restore |
| Global design | Variables, selectors (including children/pseudo states), collections, preferences and extension global settings |
| Revisions | Native tree list/restore, EMCP template-settings snapshots, complete global-document restore |
| Dynamic/interactions | Native field/action/trigger discovery and advanced tree authoring |
| Advanced elements | Native code, loop and registered extension controls through a separate privileged tool |
| Skills | Updated runtime injection and both downloadable package formats |

### Write contracts

- Defaults migration 46 disables eight new write tools without changing existing
  choices. Advanced tree/global/import writes require `unfiltered_html` as well
  as native full access. Global writes also require `edit_theme_options`.
- Library records are published by Oxygen itself. Matching templates start
  `disabled: true`; pages/posts start as drafts. Enabling a template is explicit.
- Template-setting revisions store an additional snapshot on the revision ID;
  ordinary native Oxygen revisions store only the tree. Template-settings
  restoration is opt-in and rejects revisions without that snapshot.
- Global scopes use separate hashes. Twenty complete prior documents include
  collections/preferences omitted by native global revisions.
- Imports validate bounded native bundles, resolve dependencies before saving,
  allocate new post/design IDs and namespace classes/variables. Request IDs
  prevent duplicate replay. Partial results retain created IDs and global
  revision IDs for recovery; imports do not claim transaction atomicity.

### Boundaries

Design transfer is not a complete WordPress migration. Media URLs remain remote;
custom fields, attachments, menus, homepage assignment and ordinary post content
are not transferred. Review imported conditions referencing external WordPress
records before enabling matching. Global selector rules require a separate
explicit design save; automatic imports accept class selectors.

Remote providers use WordPress safe HTTP with no redirects and bounded responses.
Local/private hosts may be refused. Transport success, error envelopes, bounds
and password-redaction behavior are unit-tested; a third-party provider import
has not been tested live in this run. Local export/import was tested through MCP.

Oxygen core disables Breakdance global settings; the corresponding scope exposes
controls only when an installed extension supplies them. Advanced authoring uses
registered schemas and native saves, but does not install optional extensions or
author Element Studio plugin source. Uninstalled extensions and every possible
dynamic field/action combination are not claimed as individually verified.

## References

Installed plugin.php; plugin/data/{tree,save}.php; plugin/util/bdox.php;
plugin/breakdance-oxygen/selectors.{php,twig}; native element definitions and
builder class property panel. Official docs: https://oxygenbuilder.com/documentation/.
Public skill discovery found Respira's platform-specific workflow:
https://www.respira.press/skills/build-oxygen6-page. EMCP's guide is independently
written; no third-party skill source is redistributed.

## Validation completed

- Main Pro suite: 2,771 tests, 11,147 assertions, six existing skips, no failures.
- Public suite: 232 tests, 602 assertions, no failures.
- PHP lint passed for all eight changed runtime files. Existing unrelated
  trailing blank lines remain in three theme skills; Oxygen files are clean.
- Both skill archive formats tested against the runtime skill; MCP get-skill
  successfully returned emcp-oxygen.
- Twelve read-only runtime assertions passed: selected builder, Breakdance
  isolation, Gutenberg coexistence, draft status, Oxygen-only storage, revisions,
  unknown element rejection, disabled/inactive tools, post capability and anonymous access.
- Test draft 2001, FIELDWORK, created entirely through MCP with 46 native nodes
  and 15 dedicated classes. All seven write operations exercised, including
  class update; stale page and design hashes rejected. Temporary test element
  removed over MCP. No dev-site test page was created.
- Native editor exposes text, h1 tag and class association. Desktop frontend,
  tablet (754px content width) and phone (386px) previews checked; responsive
  heading sizes 76/62/44px and no horizontal content overflow after layout settles.
- Oxygen 6.1.3 URL-image alt quirk documented in the bundled guide. Existing
  theme/header configuration was not edited.
- Nine runtime/skill files copied to the flattened test plugin, backups retained
  in releases/test-site-backup-oxygen, and final hashes matched. Seven write tools
  restored to disabled defaults; final MCP create-page request was denied.

Preview: https://elementor-mcp.test/?page_id=2001&preview=true (signed-in draft).

## Extension validation — September 17, 2026

- Main Pro suite: 2,772 tests, 11,174 assertions, six existing skips; no failures.
- Public suite: 232 tests, 602 assertions; no failures. PHP lint passed.
- Both downloadable skill formats contain the updated runtime skill.
- MCP authored fixtures: header 2015, footer 2017, template 2019, component 2021,
  copied header 2030 and copied page 2032. No dev-site test content created.
- Native runtime checks: 14 passed, including real component rendering in page
  2032, populated template locations, inactive template records, stale-hash and
  unknown-location rejection, recursive/wrong-type component rejection.
- Original FIELDWORK page 2001 restored over MCP after temporary instance test.
- Updated runtime/skill files deployed to test plugin. Temporary tool opt-ins
  restored; create-template then rejected as disabled. Dev migration applied.
- Native editor round-trip checks for these added library workflows remain
  pending; the earlier desktop/tablet/mobile page checks are not counted as
  coverage of these new workflows.

Component/library test draft: https://elementor-mcp.test/?page_id=2032&preview=true

## Advanced integration validation — September 20, 2026

- Full Pro suite: 2,813 tests, 11,408 assertions, six existing skips at the first
  full checkpoint. Public suite: 232 tests, 602 assertions. Additional focused
  transport/skill tests added afterward; see final run results below.
- MCP fixtures on elementor-mcp.test: component 2145, page 2151, header 2156,
  imported page/component 2159/2161 and advanced page 2167. All were authored
  through the configured MCP stdio dispatcher. No dev-site test page was created.
- Verified exposed properties/overrides, cyclic and forged-target rejection,
  stale global hashes, variable and preference restore, header conditions,
  document trash/restore, native tree and template-settings revision restore.
- Local export/import remapped the component ID; replay returned the same result.
- Chrome native editor displayed the Heading control and override. Native Save
  round trip preserved it. Frontend rendered the override, HTML and a two-item
  posts loop. A page-scoped header appeared only on page 2151; it was restored
  disabled after the matching test.
- Claude's existing changes remain in the working tree. Nothing was pushed.

### Final checks

- Final full suite: **2,828 tests, 11,488 assertions, six existing skips**, no
  failures. Public suite: **232 tests, 602 assertions**, no failures.
- Native variable rendered `rgb(36, 104, 75)` and the generated nested hover rule
  rendered `rgb(18, 52, 86)` in the stylesheet. Styles are scoped to the advanced
  fixture's dedicated class. Library configuration toggle/save/stale-hash checks
  passed and the original sharing flags were restored.
- Both downloadable skill archives contain the same updated Oxygen skill body;
  focused Oxygen/skill tests passed. PHP lint passed for all Oxygen runtime files.

Temporary write opt-ins were restored. A subsequent MCP create-page request was
denied, and the updated skill was retrieved successfully over MCP.
