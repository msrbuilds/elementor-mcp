# Breakdance integration plan

## Target and scope

Implement a standalone Pro integration for installed Breakdance 2.8.3. Gate this
first adapter to 2.8.3 or later and Breakdance mode (not Oxygen). Gutenberg stays
available alongside the selected builder. Existing explicit selections are kept
until the user selects another integration.

## Implementation

1. Discover native element classes, filtered controls/defaults, nesting rules,
   breakpoints and existing design settings. Never return license/account options.
2. Add a Breakdance Page Builders card, Tools tab with Discovery, Pages, Elements,
   and Templates & Design sections, and AI Chat tool group. Register tools only
   when selected; retain live permission and disabled-tool checks on callbacks.
3. Provide context, element catalog/schema, page/template listing, tree reading,
   draft creation, tree replacement, add/update/move/remove node operations, and
   read-only design discovery. Start structural writes disabled by default.
4. Validate native nested trees (root, unique integer IDs, depth/count limits,
   registered types and nesting). Use native controls to reject unknown property
   paths. Require full builder permissions for writes, per-post capability,
   current content hash, and an edit lock check. Preserve document metadata.
5. Save through native document APIs with global writes omitted. Capture a prior
   revision, verify readback, regenerate native CSS/dependencies and report errors.
   Leave page status and unrelated WordPress content unchanged.
6. Verify actual frontend and native builder editing, including EMCP Themer if
   active. Add a bundled EMCP usage skill describing the tested adapter contract.

## Initial boundaries

No global design writes, template creation/conditions, form submission access,
custom PHP/code element authoring or Oxygen support. Native feature discovery
does not imply every third-party element is safe for structural authoring.

## Validation

- Unit tests: nested tree edits, invalid IDs/nesting/depth, preservation of sibling
  data, stale edits and builder selector/registration/default migration behavior.
- Local registered-ability smoke test: draft lifecycle, schemas, responsive
  settings, revisions, CSS regeneration, rejected writes and capability checks.
- Browser: own tab/sections, exclusive selection with Gutenberg, draft preview
  and native editor tree. Remove disposable smoke content after checks.
- Run relevant existing public/Pro tests, PHP lint and diff whitespace checks.

## Source references

- Installed `breakdance/plugin/data/{tree,save}.php`: nested tree and native save.
- Installed `breakdance/plugin/elements/`: runtime controls and nesting rules.
- Installed `breakdance/plugin/permissions/`: native full/edit access.
- Installed `breakdance/plugin/revisions/` and `plugin/render/`: history and caches.
- https://breakdance.com/documentation/builder/basics/structure-panel/
- https://github.com/soflyy/breakdance-developer-docs

## Results

Implemented 13 tools in four sections: Discovery (3), Pages (4), Elements (4),
Templates & Design (2). The six write tools are seeded disabled by defaults
migration 39, preserving subsequent user choices. A bundled `emcp-breakdance`
skill explains the native format and tool contract.

Verified on the local WordPress site with Breakdance 2.8.3 and the active
`breakdance-zero` theme:

- 35 live checks through registered WordPress abilities: draft creation, native
  schemas, responsive tree round trip, add/update/move/remove, prior revisions,
  changed CSS cache hashes, universal HTML controls, template editing/listing,
  preserved WordPress content and builder metadata, rejected stale/invalid edits,
  per-post permissions, content-only builder users, locks, per-tool disable and
  retained callbacks after integration selection changes.
- Browser: Breakdance Tools tab shows 7/13 enabled in four sections; disabling
  the integration removes its tab while Gutenberg remains. Selection restored to
  Breakdance. Persistent write tools remain disabled.
- Browser: native frontend renders the saved heading with its desktop color;
  native editor exposes editable text/tag controls and correctly renders the
  Phone Portrait breakpoint at 400px with the alternate color and wrapped text.
- Full Pro suite: 2707 tests, 10265 assertions, 6 skipped. Public suite: 232 tests,
  602 assertions. After universal-control support, skill and migration additions,
  focused Functional/Admin/Skills suites: 313 tests, 1899 assertions, 1 skipped.
- PHP lint and whitespace checks passed. Skill frontmatter validation passed.

Transport-level external MCP client reconnection was not tested; live checks
execute the registered WordPress abilities. Native frontend/editor checks cover
the installed Breakdance Zero theme; other theme combinations remain unverified.
Specialized nesting, forms, code and global-block instances are rejected for
structural writes, including when already present in the tree. Read tools can
inspect these trees. Global design writes and template conditions remain outside
this adapter's scope. Exceptions after native save begins return the prior
revision ID and require readback before retrying; they are not atomic rollbacks.
