---
name: wp-plugin-dev
description: >
  Develop WordPress plugins following official WordPress coding standards, security best practices, and WordPress.org
  directory guidelines. Use this skill whenever the user wants to create, scaffold, or develop a WordPress plugin —
  including standard plugins (settings pages, CPTs, shortcodes), WooCommerce extensions, Gutenberg block plugins,
  or REST API / headless plugins. Also trigger when the user mentions "WordPress plugin", "WP plugin", asks to build
  a feature as a plugin, wants to add admin pages, register custom post types, create blocks, build WooCommerce
  add-ons, or extend WordPress in any way via plugin architecture. Even if the user just says "build me a plugin for X",
  use this skill.
---

# WordPress Plugin Development

## Overview

Create production-ready WordPress plugins that follow official WordPress coding standards, the WordPress.org
plugin directory guidelines, and security best practices. Every plugin produced by this skill is modular,
secure, translatable, and ready for WordPress.org submission.

## Quick Start Workflow

1. **Read references** — Before writing any code, read the relevant reference files:
   - `references/architecture.md` — Plugin structure, boilerplate patterns for all plugin types
   - `references/security.md` — Sanitization, escaping, prepared statements, nonces, caching
   - `references/wp-org-guidelines.md` — WordPress.org directory rules (18 guidelines)
2. **Gather requirements** — Ask the user what the plugin should do, then determine which modules are needed
3. **Scaffold** — Generate the directory structure and bootstrap file
4. **Build features** — Create each feature as a separate modular class
5. **Generate readme.txt** — Always include a WordPress.org-compliant readme.txt
6. **Deliver** — Ask user if they want files in `/mnt/user-data/outputs/` for download or a custom path

## Core Principles — ALWAYS Follow These

### 1. Clean Bootstrap File
The main plugin file (`plugin-name.php`) is ONLY a bootstrap loader. It contains:
- Plugin header comment (with all required headers)
- Constants (`VERSION`, `PLUGIN_DIR`, `PLUGIN_URL`, `PLUGIN_BASENAME`)
- `register_activation_hook()` / `register_deactivation_hook()`
- Autoloader or `require_once` statements
- A single init function that instantiates the main class

**NEVER** put settings registration, shortcode handlers, AJAX callbacks, CPT registration, hook callbacks,
template rendering, or ANY feature logic in the main plugin file.

### 2. Modular Architecture
Every distinct feature gets its own class file in the appropriate directory:
- `includes/` — Core classes, shared utilities, data models
- `admin/` — Admin-only functionality (settings pages, meta boxes, admin notices)
- `public/` — Public-facing functionality (shortcodes, frontend rendering)
- `blocks/` — Gutenberg blocks (each block gets its own subdirectory)
- `api/` — REST API endpoints

Each class follows the single-responsibility principle. When a feature grows beyond ~200 lines,
split it into sub-components.

### 3. Security — Non-Negotiable
Apply ALL of the following on EVERY piece of code:

**Input:** Sanitize ALL user input immediately upon receipt.
```php
$title = sanitize_text_field( wp_unslash( $_POST['title'] ) );
```

**Output:** Escape ALL output at the point of rendering (late escaping).
```php
echo esc_html( $title );
```

**Database:** Use `$wpdb->prepare()` for ALL custom SQL queries. No exceptions.
```php
$wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
```

**Auth:** Check nonces for ALL form submissions and AJAX requests. Check capabilities before ALL privileged operations.

**Caching:** Use Transients API or `wp_cache_*` for expensive queries and external API calls.

Read `references/security.md` for the complete function reference.

### 4. WordPress Coding Standards
- Use WordPress naming conventions: `snake_case` for functions/variables, `Upper_Snake_Case` for classes
- Prefix ALL functions, classes, hooks, options, transients, and database tables with the plugin prefix
- Use proper PHPDoc blocks on all classes, methods, and functions
- All user-facing strings must be translatable using `__()`, `_e()`, `esc_html__()`, etc.
- Set the text domain to match the plugin slug
- Use `wp_enqueue_script()` / `wp_enqueue_style()` — never hardcode `<script>` or `<link>` tags
- Use WordPress bundled libraries (jQuery, etc.) — never bundle your own copies
- Enqueue admin assets only on plugin pages (check `$hook` parameter)
- Enqueue public assets conditionally (only when the plugin's output is on the page)

### 5. WordPress.org Compliance
Every plugin MUST:
- Use GPLv2 or later license
- Include a complete `readme.txt` following the WordPress.org format
- Include `uninstall.php` for clean removal
- Not include obfuscated code
- Not include tracking without opt-in consent
- Not bundle WordPress default libraries
- Have human-readable code with meaningful names
- Not embed credit links without explicit user opt-in

Read `references/wp-org-guidelines.md` for all 18 guidelines.

## Plugin Type Reference

When the user's requirements include any of these, read `references/architecture.md` and use the corresponding patterns:

| User Wants | Module Pattern | Key File |
|---|---|---|
| Settings page | Settings class with Settings API | `admin/class-*-settings.php` |
| Custom post type | CPT registration class | `includes/class-*-post-types.php` |
| Custom taxonomy | Taxonomy registration (in CPT class or separate) | `includes/class-*-taxonomies.php` |
| Shortcodes | Shortcode handler class | `public/class-*-shortcodes.php` |
| REST API endpoints | REST controller class | `api/class-*-rest-controller.php` |
| Gutenberg blocks | Block with `block.json` + JS/React | `blocks/{block-name}/` |
| WooCommerce extension | WooCommerce integration class (with dependency check) | `includes/class-*-woocommerce.php` |
| AJAX handlers | AJAX handler class | `includes/class-*-ajax.php` |
| Custom database table | Database class with `dbDelta()` | `includes/class-*-database.php` |
| Admin notices | Notices class | `admin/class-*-notices.php` |
| Cron jobs | Cron scheduler class | `includes/class-*-cron.php` |
| Meta boxes | Meta box class | `admin/class-*-meta-boxes.php` |
| Widgets | Widget class extending `WP_Widget` | `includes/class-*-widget.php` |

## Naming Conventions

When scaffolding, derive all names from the plugin name the user provides:

| Element | Convention | Example (plugin: "Smart Bookmarks") |
|---|---|---|
| Plugin slug | lowercase-hyphenated | `smart-bookmarks` |
| Text domain | same as slug | `smart-bookmarks` |
| Function prefix | lowercase underscore | `smb_` |
| Class prefix | Upper_Snake | `Smart_Bookmarks_` |
| Constant prefix | UPPER_SNAKE | `SMB_` |
| Option names | prefix + name | `smb_settings` |
| Transient names | prefix + name | `smb_cache_items` |
| DB table prefix | prefix + name | `smb_items` |
| Hook names | prefix/name | `smb_after_save` |
| REST namespace | slug/v1 | `smart-bookmarks/v1` |
| Block namespace | slug/block | `smart-bookmarks/featured-list` |

Choose a short prefix (2-4 characters) derived from the plugin name initials.

## Delivery

After building the plugin:

1. Ask the user where they want the files:
   - **Download:** Copy to `/mnt/user-data/outputs/{plugin-slug}/` and present as downloadable
   - **Custom path:** Write to the path the user specifies
2. Always include `readme.txt` in the plugin root
3. Provide a brief summary of the generated files and what each module does
4. If the plugin includes Gutenberg blocks, note that `npm install && npm run build` is needed for the block assets

## Checklist Before Delivery

Run through this before presenting the final plugin:

- [ ] Main plugin file contains ONLY bootstrap code
- [ ] Every feature is in its own class file
- [ ] ALL user input is sanitized
- [ ] ALL output is escaped
- [ ] ALL custom SQL uses `$wpdb->prepare()`
- [ ] ALL forms use nonces
- [ ] ALL privileged actions check capabilities
- [ ] ALL strings are translatable with correct text domain
- [ ] ALL functions/classes/hooks are prefixed
- [ ] Assets are properly enqueued (not hardcoded)
- [ ] Admin assets only load on plugin pages
- [ ] `readme.txt` is present and complete
- [ ] `uninstall.php` handles clean removal
- [ ] License header is GPLv2 or later
- [ ] No bundled WP default libraries
- [ ] Caching is used for expensive operations
- [ ] Activation hook creates any needed DB tables
- [ ] Deactivation hook cleans up scheduled events
