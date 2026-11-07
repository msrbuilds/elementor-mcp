# WPBakery integration plan and contract

Target: installed WPBakery 9.0.1, with later releases accepted. Separate standalone selection, with Gutenberg and enabled block packs preserved.

## Implementation

- Dedicated tab and 17 individually toggleable tools across Discovery, Pages, Elements, Styles, Templates, Settings and Recovery.
- Native WPBMap discovery; verified core shortcode authoring in post_content. Unknown markup is never discarded by parsing.
- Page-local CSS and native custom/default element CSS regeneration after writes.
- Local/default template discovery and validated application; native editor and element permissions enforced.
- Fresh-state hashes, edit locks, ten content/CSS recovery snapshots, explicit publication.
- Runtime and both downloadable skill formats from pro/skills/emcp-wpbakery.

## Validation plan

Run malformed/nesting/permission/size regression tests and existing catalog, defaults and archive tests. Deploy to the local test plugin, temporarily enable writes, build a native landing page through MCP, exercise stale saves, invalid content, CSS, fragment edits and recovery. Verify native editor Save/readback and responsive frontend. Restore default write toggles after testing.

## Scope boundaries

Other installed elements are discoverable, but authoring is limited to the verified core subset returned by context. Remote library downloads, saved-template mutations, Grid Builder editing and global settings mutations are not implemented. Recovery tools expose EMCP snapshots rather than arbitrary WordPress revisions.

## Validation results

- Pro suite: 2,852 tests, 11,703 assertions, six existing skips; no failures.
- Public suite: 232 tests, 602 assertions; no failures.
- MCP discovery exposed 71 installed element schemas. Fifteen verified core types support authoring.
- Test landing page: https://elementor-mcp.test/fieldwork-wpbakery-integration-test/ (2190), created and edited through MCP. Draft compatibility fixture: 2195.
- Verified stale-hash and invalid-shortcode rejection, fragment editing, CSS readback, recovery, explicit publication, native generated CSS, default-template application and restoration.
- Runtime denial checks cover anonymous users, inactive builder, missing/inaccessible pages, unfiltered HTML restrictions and native element restrictions.
- Native backend Save/readback passed twice. Desktop frontend and native smartphone portrait preview visually reviewed. Native TinyMCE normalized unstyled spans; separate classed paragraphs preserve the brand typography.
- New pages open in native backend mode. Test tools restored to ten reads enabled and seven writes disabled. WPBakery selected on both sites.
- Runtime skill verified in folder and Claude Desktop downloads. No external skill copied; official WPBakery documentation credited.
- Existing user/Claude edits preserved. No commit or push performed.
- Existing unrelated trailing whitespace in pro/tests/unit/WidgetGeneratorTest.php:515 remains untouched.
