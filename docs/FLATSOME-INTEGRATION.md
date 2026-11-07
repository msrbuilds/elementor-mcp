# Flatsome Site Builder integration

EMCP Pro supports the **Flatsome Site Builder plugin and Flatsome Block Theme pair, 1.0.0-beta.7 or later**. This is native WordPress block storage. A child of the Flatsome Block Theme works too. Classic Flatsome and UX Builder shortcodes are a different product and are not supported by this adapter. Versions below 1.0.0-beta.7 fail the qualification gate.

Select Flatsome in EMCP's builder setting to expose its 16 builder tools. The two theme dispatchers are independent of that selection. All seven builder mutations and the theme write dispatcher default off. Existing selections and toggles are preserved during installation.

## Discover before authoring

Use `flatsome-get-context`, `flatsome-list-blocks`, `flatsome-get-block-schema`, and `flatsome-get-block-supports`. A schema can exist while its block is unavailable or not writable. WooCommerce blocks require WooCommerce and the native module to be active.

The public tree uses `{blockName, attrs, innerBlocks}`. Heading text is `attrs.content`; a button uses `text` and `url`. Responsive values use `lg`, `md`, and `sm` container-query variants. Native validation and serialization generate the stored markup. Do not supply retained HTML or underscore-prefixed internal fields.

`flatsome/grid` is an internal component: the native PHP serializer accepts it, but the native editor deliberately throws when it is used directly. It is discovery-only. Use `flatsome/banner-grid` with `columns` and `rows` on its children, or `row` and `col`, for editable grid layouts. Forms, custom HTML/code and unknown third-party blocks are also discovery-only. Untouched unsupported markup is retained during a patch.

## Documents and patterns

`create-page` creates a draft. Validate a proposed tree with `validate-page`; save an intentional replacement with `set-page-tree` and `replace_all: true`. Read `get-page` before later changes, then pass its `expected_hash` to `patch-page`. Paths are dotted zero-based indices from the original read snapshot for the whole batch.

Operations are `set`, `insert`, `replace`, `move`, `duplicate`, and `remove`. Objects merge; arrays and scalars replace; null unsets a key; an empty string clears it; omitted values remain. Conflicting paths and moves into descendants are refused. Content writes preserve status. Title/slug changes and publication use separate tools.

Saved patterns are native `wp_block` posts. `insert-pattern` requires an explicit `synced` choice: true inserts a live reference, false copies its blocks. Read/update a saved pattern through its document ID. Referenced media, patterns, templates, parts and native shared repeater cards participate in dependency checks. New incoming references block destructive creation undo.

Media dependencies include responsive background layers, gallery and comparison images, posters, logos and attachment-backed icons. A patch can remove an existing broken reference; undo cannot reintroduce an unavailable target. Creation undo also refuses later child pages, attached media and comments, even with force.

## Theme operations

`flatsome-theme-read` and `flatsome-theme-write` accept `{operation, arguments}`. Their schemas describe each operation separately.

Reads cover context, saved global style overrides and effective theme defaults, style schemas, and resolved templates/parts. Reading styles does not create a global-styles record.

Writes cover native global styles, detached custom templates/parts, patching database-backed templates/parts, assigning a page-specific template and resetting unused integration-owned customizations. Template files are not edited. Reset moves the owned customization to trash; History can restore it. Existing sitewide documents require `edit_theme_options`, including direct-ID access.

Global style patches use native theme.json sanitization and Flatsome block styles. Palette presets merge by slug using native origin semantics. If native sanitization drops requested values, the adapter reports recovery rather than claiming success. Undo can restore original absence; later site work blocks deleting a newly created global-styles record.

## Concurrency and recovery

Every mutation requires an operation UUID. Retry the same UUID and identical input after response loss; completed receipts replay the original result. Incomplete receipts require inspection before another attempt. Updates require a fresh dependency-aware hash.

The writer coordinates EMCP and supported native/core REST, WordPress post, delete, metadata and normal comment-submission/update paths with a non-waiting, connection-owned MySQL mutex and ordered object leases. Databases without named locks refuse writes. Arbitrary SQL and low-level writers bypassing these hooks, including direct `wp_insert_comment`, are outside this coordination boundary.

Successful changes return a History `change_id`. Undo rechecks permissions, editor locks, versions and dependencies inside its locks. Force can skip an update version conflict, but cannot remove later work or restore unavailable links. Save-hook errors, readback failures and History failures trigger before-image recovery. A failed or uncertain recovery returns `partial_write` with a receipt and available recovery History entry. Ambiguous created IDs are never automatically deleted.

Global styles are verified against durable content and metadata, including removals and untouched sections. A successful native REST response alone is insufficient. Receipts are read back from storage after option hooks; a completed template-reset receipt can be replayed while its target is in trash. If undo fails, the writer first restores the state from before the undo attempt. A second failure records a recovery change for inspection.

## Qualification and boundaries

Live qualification covers drafts, native serialization, responsive values, patterns, template assignment/reset, global-style undo, permissions, recovery and competing native/MCP writers. Browser qualification covers sections, rows/columns, flex, banner grids, headings/text, buttons/images, icons/icon boxes, cards, banners, testimonials, lists, tabs, sliders, accordions, reusable patterns and non-empty WooCommerce product displays. The native Builder must open and save a document without recovery or preview-error blocks.

Discovery of an optional block is not a claim that every configuration has browser coverage. Forms delivery/submissions, orders/payments, module activation, SEO settings, credentialed integrations, icon-library installation and Classic migration are separate domains. Native enhanced agent heartbeat attribution is not emulated. Reload the editor after external changes and reconnect long-lived MCP stdio processes after updating PHP.

Agent workflow: `pro/skills/emcp-flatsome/SKILL.md`. Maintained native smoke: `pro/tests/smoke/flatsome-native.php`. Real MCP and browser harnesses: `scripts/e2e/flatsome-*.py` and `tests-e2e/flatsome.spec.js`.
