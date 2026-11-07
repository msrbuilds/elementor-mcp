# Divi 5 integration

## Target and scope

Installed target: Divi theme 5.13. A standalone Divi integration must cover all
three surfaces: native Divi 5 page layouts, Theme Builder, and Theme Options.
Gutenberg stays available; only one other builder can be selected.

## Implementation plan

1. Inspect installed module metadata, block serialization, native permissions,
   cache invalidation, Theme Builder storage/assignments and theme option controls.
   Compare the public divilovewp/divi5-skill references with the installed version.
2. Add native module discovery/schema tools, page and library discovery, draft
   creation, lossless block trees and add/update/move/remove editing. Preserve
   unrelated metadata, status, revisions and unsupported existing content.
3. Add Theme Builder template discovery, header/body/footer layout creation and
   editing, template creation/update, assignments and enable/disable operations.
   Use native storage helpers and validate references and assignment conditions.
   Global operations need theme-management capabilities and concurrency hashes.
4. Add schema-driven Theme Options discovery and partial updates, preserving
   unmodified settings. Keep credentials redacted and executable integration
   fields out of this general settings API. Include design-system discovery.
5. Add the selector, Divi tool sections, AI Chat group, disabled-by-default write
   tools, and an injected/downloadable EMCP skill with upstream attribution.
6. Test parsers, schemas, editing, permissions, stale writes, option preservation,
   assignments, selection/exclusivity and packaging. Copy the integration to the
   test site, create all test pages through MCP and inspect the native editor.
   Never create test pages on msrplugins.test.

## Sources

- https://github.com/divilovewp/divi5-skill — Shashank Gupta / DiviLove, MIT.
- https://dev.elegantthemes.com/ — official Divi 5 developer documentation.
- Installed Divi 5.13 source is authoritative for the version-specific contract.

## Results

Completed on 2026-09-16 against Divi 5.13:

- Standalone selector and Divi tab: 22 tools in six sections (Discovery, Pages,
  Elements, Library & Design, Theme Options, Theme Builder). Gutenberg remains
  available. Twelve mutation tools start disabled; existing tool choices persist.
- Native discovery reads 114 module definitions and 100 theme option definitions;
  90 option fields are writable. Divi's module role restrictions are respected.
- Test page 1889 was created and edited exclusively through MCP on
  https://elementor-mcp.test/?page_id=1889&preview=true (draft, login required).
  Native editor and frontend render headings, text, button, sections, rows and
  columns. Phone preview at 390px is readable; desktop has no horizontal overflow.
  The button resolves to the capabilities section.
- Add/update/move/remove module round trips pass. Stale page hashes and cyclic
  moves are rejected. Native revisions and draft status are preserved.
- Native library layout 1910 contains the reusable landing layout. Theme body
  1893 and footer 1894 are assigned through template 1895 only to page 1889.
  The body uses Divi's Post Content module. Existing default template and header
  are unchanged; header layout 1892 remains an empty, unattached test fixture.
- Template creation, enablement, assignments and removal pass. Invalid
  assignments, wrong layout types and stale hashes are rejected. Removing a
  temporary template restored the exact previous Theme Builder hash.
- RSS-icon option toggled and restored through MCP with an exact settings-hash
  match. No lasting global theme-option changes were made.
- Read-only live probes verify administrator access, theme-management capability
  checks, disabled-integration rejection and anonymous rejection.
- Skill injection was verified through MCP. Tests open both folder and Claude
  Desktop skill archives and verify the Divi instructions and author credit.
- Configured Pro suite: 2,732 tests, 10,462 assertions, six skips, no failures.
  Public suite: 232 tests, 602 assertions, no failures.
- The broader `phpunit.xml.dist` run (2,784 tests) exposes one failure in
  `MirrorAbilitiesTest::test_export_single_writes_file_and_list_finds_it`:
  export returns WP_Error where that test expects an array. It reproduces when
  run alone; this separate content-mirror test is outside the configured Pro
  suite and was not changed as part of Divi integration.

## Current boundaries

Compatibility starts at the inspected Divi 5.13 contract, and later releases are accepted.
Divi 4 shortcode layouts and mixed/unsupported content are never converted or
silently discarded. Discovery is broader than live module coverage; not every
native module has been visually tested. Executable code, external-action forms,
payment and shared-instance modules remain discovery-only. Credentials and
executable theme-option fields are not exposed for writes. Design tokens and
presets are discoverable; this integration does not author the global preset
system. Existing header rendering was preserved during site validation.
