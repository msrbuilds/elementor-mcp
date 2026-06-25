# WordPress Media Library Tools — Design

**Status:** Approved (design), ready for implementation plan.
**Date:** 2026-06-25
**Sub-project of:** "Beyond Elementor — general WordPress management via MCP." Domain 4 (Media Library). Content (1), Settings (2), and Plugins & Themes (3) are already built on the unreleased v3.0.0 line; this stacks onto the same line and merges with them as a single v3.0.0.

## Goal

Let an AI agent manage existing WordPress Media Library attachments over MCP: fetch full detail of one attachment, edit its metadata (title / alt text / caption / description), and delete it — complementing (not duplicating) the existing read-only `list-media` and the existing `sideload-image` (which already handles URL→Library uploads).

## Decisions (locked in brainstorming)

- **Scope: get + update + delete.** Three new tools. URL uploads are NOT re-implemented — `sideload-image` already does that.
- **Default state: get + update ON, delete OFF.** `get-media` and `update-media` ship enabled-by-default (reading detail and fixing alt-text/captions is a low-risk accessibility/SEO win). `delete-media` ships disabled-by-default (admin opts in) because media deletion is effectively permanent.
- **`delete-media` requires explicit `confirm: true`.** On top of being disabled-by-default, the tool refuses to delete unless the caller passes `confirm: true` — a deliberate acknowledgment gate, since WordPress media bypasses Trash on most sites.
- **Extend the existing class.** The 3 tools are added to `EMCP_Tools_Media_Library_Abilities` (which already holds `list-media`), keeping all Media Library operations in one cohesive group.

## Architecture

Add three tools to the existing group class:

- `includes/abilities/class-media-library-abilities.php` — `EMCP_Tools_Media_Library_Abilities`. Already registers `list-media`; gains `register_get_media()`, `register_update_media()`, `register_delete_media()` + their `execute_*` methods and a small `resolve_attachment()` helper. `register()` calls the three new registrars; `get_ability_names()` adds the three slugs. Built on WP core attachment functions — no new dependency (the class's existing `EMCP_Tools_Data` is untouched by the new tools).

No new registrar/bootstrap wiring is needed — the group is already required in `class-bootstrap.php` and registered in `class-ability-registrar.php`.

Changed elsewhere:
- `includes/class-plugin.php` — add `get-media` + `update-media` to `get_essential_tool_slugs()` (not `delete-media`).
- `includes/admin/class-admin.php` — add the 3 tools to the Media catalog category; bump `DEFAULTS_VERSION` 6→7; add `media_write_tool_slugs()` (just `delete-media`) seeded into the disabled set on the v7 step.

## The 3 tools (contracts)

Annotations: `get-media` → `readonly:true`; `update-media` → `readonly:false, destructive:false`; `delete-media` → `destructive:true`.

### `get-media` — `edit_posts`
- **In:** `{ id: integer }` (required).
- **Out:** `{ id, title, slug, url, mime_type, filesize, alt, caption, description, date, author:{id,name}, post_parent, width, height, sizes: { <size>: { url, width, height } }, metadata: <raw _wp_attachment_metadata> }`.
- Resolves via `get_post`; errors `not_an_attachment` if `post_type !== 'attachment'`. Read-only, idempotent.

### `update-media` — `edit_post` on the attachment
- **In:** `{ id, title?, alt?, caption?, description? }` — only the passed fields change.
- Maps: `title` → `post_title`, `caption` → `post_excerpt`, `description` → `post_content` (via `wp_update_post`); `alt` → `update_post_meta( id, '_wp_attachment_image_alt', … )` (sanitized with `sanitize_text_field`).
- **Out:** `{ id, updated: [ <fields changed> ], alt, title, caption, description }`.
- Permission re-checks `edit_post` on `id` defensively in execute. `not_an_attachment` / `missing_params` errors as above.

### `delete-media` — `delete_post` on the attachment
- **In:** `{ id, confirm: boolean (must be true), force?: boolean }`.
- **Behavior:**
  - If `confirm !== true` → `WP_Error('confirmation_required', 'Deleting media is permanent on most sites (WordPress bypasses Trash unless MEDIA_TRASH is defined). Pass confirm:true to proceed.')` — no deletion.
  - Else `wp_delete_attachment( id, (bool) force )`. `force` defaults to `false` (respects `MEDIA_TRASH` when defined; otherwise core deletes permanently regardless — that's WordPress behavior, surfaced in the response).
- **Out:** `{ success: bool, id, deleted: "trashed"|"deleted" }` — `"trashed"` only when the attachment went to trash (i.e. it still exists as `trash` status afterward), else `"deleted"`.
- Annotation `destructive:true`. Disabled-by-default; the `confirm` gate is an additional acknowledgment.

## Guardrails (cross-cutting)

1. **Per-attachment capability.** Attachments are posts, so `edit_post`/`delete_post` are checked against the specific `id` — an agent can only touch attachments the user could. `get-media` uses `edit_posts` (matches `list-media`).
2. **Type check.** Every tool confirms `post_type === 'attachment'` before acting.
3. **Delete is gated three ways:** disabled-by-default (admin opt-in) + `delete_post` capability + required `confirm:true`.
4. **No metadata corruption.** `update-media` only writes the four known fields; it never touches `_wp_attachment_metadata` or `_elementor_data`.
5. **No in-use detection.** The plugin cannot reliably know whether an image is referenced in an Elementor page; the three-way delete gate is the safety model (documented).

## File structure

**Changed:**
- `includes/abilities/class-media-library-abilities.php` — +3 tools + helper (~300→~600 lines, still one responsibility).
- `includes/class-plugin.php` — essentials += `get-media`, `update-media`.
- `includes/admin/class-admin.php` — Media catalog entries; `DEFAULTS_VERSION` → 7; `media_write_tool_slugs()`; v7 seed step.
- `tests/bootstrap.php` — stubs as needed: `wp_get_attachment_metadata` (controllable), `wp_get_attachment_image_src`, `wp_update_post`, `wp_delete_attachment` (records calls + controllable trash/permanent), `get_post_field`. (`get_post`/`update_post_meta`/`get_post_meta` stubs already exist.)
- `CHANGELOG.md`, `README.md`, `readme.txt`, `CLAUDE.md` — fold into the single v3.0.0 entry.

**New:**
- `tests/unit/media/MediaToolsTest.php` — execute-path tests.
- `tests/unit/capabilities/MediaCapabilityTest.php` — per-tool capability gating + confirm gate.

## Testing strategy

- **Capability:** `get-media` denies without `edit_posts`; `update-media` denies without `edit_post` on the id; `delete-media` denies without `delete_post`.
- **Execute** (stubbed attachment functions): `get-media` returns the size map + metadata shape and errors on a non-attachment id; `update-media` writes only the passed fields (records the `_wp_attachment_image_alt` meta call + the `wp_update_post` fields) and leaves others untouched; `delete-media` without `confirm:true` → `WP_Error('confirmation_required')` and NO `wp_delete_attachment` call; with `confirm:true` calls `wp_delete_attachment(id, force)` and reports trashed-vs-deleted; missing/invalid id → `WP_Error`.
- **Live MCP smoke:** `get-media` on a real attachment; `update-media` to set alt text → verify via `wp post meta get <id> _wp_attachment_image_alt`, then restore; confirm `delete-media` without `confirm` is refused; optionally upload a throwaway image via `sideload-image`, then `delete-media{confirm:true}` it and confirm removal.
- **Browser:** EMCP Tools → Tools → Media category shows the 3 tools; `get-media`/`update-media` enabled, `delete-media` present but off with the destructive badge; no PHP errors.

## Out of scope (explicit)

- New upload tools (URL or base64) — `sideload-image` already covers URL→Library.
- Image editing/cropping/regenerating thumbnails, replacing the underlying file.
- Bulk operations (per-attachment only).
- In-use / reference detection before delete.
- Attachment taxonomy (media categories) management.

## Open questions

None blocking.
