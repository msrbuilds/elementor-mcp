# WordPress Users Tools — Design

**Status:** Approved (design), ready for implementation plan.
**Date:** 2026-06-25
**Sub-project of:** "Beyond Elementor — general WordPress management via MCP." Domain 5 (final) — Users. Content (1), Settings (2), Plugins & Themes (3), Media Library (4) are already built on the unreleased v3.0.0 line; this stacks onto the same line and merges with them as a single v3.0.0.

## Goal

Let an AI agent read WordPress users and make **safe** account changes over MCP — list/get users, create new non-admin accounts (with a secure onboarding flow), and edit non-admin profiles — without any path to privilege escalation, account takeover, or lockout. This is the most security-sensitive domain, so the guardrails are the design.

## Decisions (locked in brainstorming)

- **Scope: read + safe profile edits.** Four tools: `list-users`, `get-user` (read); `create-user`, `update-user` (write). **No `delete-user`** and **no role changes** in v1 (the two highest-risk operations are out).
- **Passwords: auto-generate + email, never returned.** `create-user` always auto-generates a strong password and sends WordPress's new-user notification so the person sets their own password via the email link. The password is **never** included in the MCP response (no secret in the agent transcript).
- **Strict privilege boundary.** `create-user` may only assign roles **without** admin-level capabilities (never `administrator` or any custom admin-grade role). `update-user` **refuses to edit any user that has admin-level capabilities** (administrators are untouchable via MCP) and never changes a role or password. Closes the "edit an admin's email → trigger a password reset → takeover" vector.
- **Default state: reads on, writes off.** `list-users`/`get-user` enabled by default (gated on `list_users`, admin-only anyway); `create-user`/`update-user` ship **disabled-by-default** (admin opts in on the Tools tab).

## Architecture

A new ability group class:

- `includes/abilities/class-user-abilities.php` — `EMCP_Tools_User_Abilities`. Accumulator pattern; registers the 4 tools; per-tool permission callbacks; the privilege-guard helpers; built on WP core user functions only (no new dependency).

Registration wiring (mirrors the content/settings groups):
- `includes/class-bootstrap.php` — `require_once` the class.
- `includes/abilities/class-ability-registrar.php` — instantiate + register (unconditional; pure WP).
- `includes/class-plugin.php` — add `list-users` + `get-user` to `get_essential_tool_slugs()`.
- `includes/admin/class-admin.php` — new "Users" category in `get_tool_catalog()`; bump `DEFAULTS_VERSION` 7→8; add `user_write_tool_slugs()` (`create-user`, `update-user`) seeded disabled on the v8 step.

## The privilege guard (core)

Two helpers define the security boundary; a role/user is **protected** if it holds **any** of these capabilities:

```
manage_options, promote_users, edit_users, delete_users, manage_network
```

- `role_has_admin_caps( string $role_slug ): bool` — loads `get_role( $slug )` and checks its `capabilities` map for any protected cap. Used by `create-user` to reject an admin-grade `role`.
- `user_has_admin_caps( int $user_id ): bool` — `user_can( $user_id, $cap )` for each protected cap. Used by `update-user` to reject editing a privileged target.

This catches `administrator` AND custom admin-grade roles, on single-site and multisite (via `manage_network`).

## The 4 tools (contracts)

Annotations: reads → `readonly:true`; `create-user`/`update-user` → `readonly:false, destructive:false` (no delete in this domain).

### `list-users` — `list_users`
- **In:** `{ role?: string, search?: string, per_page?: int (1–100, default 20), page?: int, orderby?: "registered"|"display_name"|"ID", order?: "ASC"|"DESC" }`.
- **Out:** `{ users: [ { id, username, display_name, email, roles:[…], registered, post_count } ], total, pages, page }`.
- Read-only. `WP_User_Query`. Never returns passwords/hashes/keys.

### `get-user` — `list_users`
- **In:** `{ id: integer }` (required).
- **Out:** `{ id, username, email, display_name, first_name, last_name, nickname, url, description, roles:[…], registered, post_count, is_admin: bool }` (`is_admin` = `user_has_admin_caps`, so an agent knows the account is off-limits to `update-user`). No auth data.
- `user_not_found` if no such user.

### `create-user` — `create_users`
- **In:** `{ username (required), email (required), role?, first_name?, last_name?, display_name?, url?, description? }`.
- **Behavior:**
  - `role` defaults to `subscriber`; if `role_has_admin_caps( role )` (or the role doesn't exist) → `WP_Error('forbidden_role')` — no user created.
  - Auto-generate password via `wp_generate_password( 24, true, true )`; `wp_insert_user` with the sanitized fields; then `wp_send_new_user_notifications( $id, 'user' )` (the set-password email).
  - `wp_insert_user` errors (duplicate username/email, invalid email) flow back as `WP_Error`.
- **Out:** `{ id, username, email, role, edit_link }` — **no password field, ever**.
- Annotation `readonly:false, destructive:false, idempotent:false`. Disabled-by-default.

### `update-user` — `edit_users`
- **In:** `{ id (required), email?, first_name?, last_name?, display_name?, url?, description?, nickname? }`. A `role` or `password` key is ignored (not part of the schema; defensively stripped).
- **Behavior:**
  - Resolve `id` via `get_userdata`; `user_not_found` if missing.
  - If `user_has_admin_caps( id )` → `WP_Error('protected_user')` — administrators (and admin-grade custom roles) cannot be edited via MCP. No write.
  - Otherwise `wp_update_user` with only the passed, sanitized profile fields (`user_email` sanitized + validated; text fields `sanitize_text_field`; `url` `esc_url_raw`; `description` allowed longer text via `sanitize_textarea_field`).
- **Out:** `{ id, updated: [ <fields changed> ], email, display_name }`.
- Annotation `readonly:false, destructive:false`. Disabled-by-default.

## Guardrails (cross-cutting)

1. **Per-op capability** (`permission_callback`): `list_users` (reads), `create_users` (create), `edit_users` (update); `execute` re-checks defensively.
2. **No privilege escalation:** `create-user` cannot assign an admin-grade role; `update-user` cannot change roles at all.
3. **Admins untouchable:** `update-user` refuses any target with admin-level caps.
4. **No secret exposure:** responses never include passwords, hashes, `user_pass`, auth keys, or session tokens. `create-user` returns no password; onboarding is via the WP email.
5. **No delete:** there is no `delete-user` tool, so MCP cannot remove accounts or orphan content — and lockout of the last admin is structurally impossible.
6. **Writes disabled-by-default:** `create-user`/`update-user` are seeded off; the admin opts in.

## File structure

**New:**
- `includes/abilities/class-user-abilities.php` — the ability group (4 tools + guard helpers).
- `tests/unit/users/UserToolsTest.php` — execute-path tests.
- `tests/unit/capabilities/UserCapabilityTest.php` — per-tool capability gating.

**Changed:**
- `includes/class-bootstrap.php` — `require_once` the class.
- `includes/abilities/class-ability-registrar.php` — register the group (unconditional).
- `includes/class-plugin.php` — essentials += `list-users`, `get-user`.
- `includes/admin/class-admin.php` — "Users" category; `DEFAULTS_VERSION` → 8; `user_write_tool_slugs()`; v8 seed.
- `tests/bootstrap.php` — stubs: `WP_User_Query` (or `get_users`), `get_userdata`/`get_user_by`, `wp_insert_user`, `wp_update_user`, `wp_generate_password`, `wp_send_new_user_notifications`, `get_role`, `user_can` (controllable), `count_user_posts`. (`current_user_can` already stubbed.)
- `phpunit.xml` — add a `Users` testsuite.
- `CHANGELOG.md`, `README.md`, `readme.txt`, `CLAUDE.md` — fold into the single v3.0.0 entry.

## Testing strategy

- **Capability:** `list-users`/`get-user` deny without `list_users`; `create-user` denies without `create_users`; `update-user` denies without `edit_users`.
- **Privilege guard:** `create-user` with an admin-grade role (a role whose caps include `manage_options`) → `forbidden_role` and NO `wp_insert_user` call; `update-user` on a user with admin caps → `protected_user` and NO `wp_update_user` call; `update-user` given a `role`/`password` key ignores it (only profile fields reach `wp_update_user`).
- **Execute** (stubbed user functions): `create-user` auto-generates a password (records the `wp_send_new_user_notifications` call) and the result has NO password field; `list-users`/`get-user` row/detail shape incl. `is_admin`; missing/invalid id → `WP_Error`; duplicate-email `wp_insert_user` WP_Error surfaces.
- **Live MCP smoke:** `list-users`/`get-user` read; `create-user` a throwaway subscriber → verify with `wp user get`, confirm no password in the response, then `wp user delete` to clean up (delete is done via WP-CLI, not MCP, since there's no delete tool); `update-user` that subscriber's display_name → verify, then clean up; confirm `create-user{role:'administrator'}` is refused and `update-user` on the admin account is refused.
- **Browser:** EMCP Tools → Tools → "Users" category renders; `list-users`/`get-user` enabled, `create-user`/`update-user` present but off by default; no PHP errors.

## Out of scope (explicit)

- `delete-user` and content reassignment.
- Role/capability changes of any kind (promote/demote, custom-cap grants, role creation).
- Password reset/set via MCP (the create flow uses WP's own email; there is no agent-driven password setter).
- Editing administrators or any admin-grade account.
- Returning any secret (password, hash, auth/session token, application passwords).
- Multisite super-admin / network user management beyond reporting.
- User meta beyond the standard profile fields.

## Open questions

None blocking.
