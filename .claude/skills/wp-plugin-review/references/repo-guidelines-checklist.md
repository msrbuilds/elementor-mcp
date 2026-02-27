# WordPress.org Plugin Repository Guidelines Checklist

Based on the official WordPress.org Detailed Plugin Guidelines and common rejection reasons.

## Table of Contents
1. Plugin Headers
2. readme.txt
3. Licensing
4. Code Requirements
5. Data & Privacy
6. Admin Experience
7. Submission Requirements
8. Common Rejection Reasons

---

## 1. Plugin Headers

The main plugin file MUST contain these headers:

```php
/**
 * Plugin Name:       My Plugin Name
 * Plugin URI:        https://example.com/my-plugin
 * Description:       A brief description of what this plugin does.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Author Name
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       my-plugin-name
 * Domain Path:       /languages
 */
```

### Check
- [ ] Plugin Name is present and descriptive
- [ ] Version follows semantic versioning (X.Y.Z)
- [ ] Requires at least specifies minimum WP version
- [ ] Requires PHP specifies minimum PHP version (7.4+)
- [ ] License is GPL-2.0-or-later or compatible
- [ ] Text Domain matches the plugin slug
- [ ] Domain Path is set if translations exist

---

## 2. readme.txt

Must follow the WordPress readme.txt standard format. Validate at https://wordpress.org/plugins/developers/readme-validator/

### Required sections
```
=== Plugin Name ===
Contributors: wordpress-username
Tags: tag1, tag2, tag3
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Short description (150 chars max).

== Description ==

Full description of the plugin.

== Installation ==

Installation instructions.

== Frequently Asked Questions ==

= Question? =

Answer.

== Screenshots ==

1. Description of screenshot 1

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
```

### Check
- [ ] readme.txt exists in plugin root
- [ ] Contributors use valid WordPress.org usernames
- [ ] Tags are relevant (max 5 tags)
- [ ] Tested up to references a recent WP version
- [ ] Stable tag matches current version (NOT `trunk`)
- [ ] Short description is under 150 characters
- [ ] Changelog has entries for each version
- [ ] Description clearly explains what the plugin does
- [ ] No excessive marketing language or spam links
- [ ] If external service is used, it is documented with ToS link

---

## 3. Licensing

### Requirements
- [ ] License must be GPL-2.0-or-later or a GPL-compatible license
- [ ] All included code must be compatible with GPL
- [ ] Third-party libraries must have GPL-compatible licenses
- [ ] A LICENSE or LICENSE.txt file should be included
- [ ] License headers in individual files are recommended

### Red flags
- Proprietary license restrictions
- "All rights reserved" notices
- Non-GPL-compatible third-party code
- Missing license declarations for bundled libraries

---

## 4. Code Requirements

### Must follow
- [ ] No PHP short tags (`<?` or `<?=`) — always use `<?php`
- [ ] No closing PHP tag `?>` at end of files (prevents whitespace issues)
- [ ] All functions, classes, and global variables are prefixed with plugin slug
- [ ] No naming conflicts with WordPress core, other popular plugins, or PHP built-ins
- [ ] No deprecated WordPress functions used
- [ ] No deprecated PHP functions used
- [ ] WordPress default libraries not bundled (jQuery, jQuery UI, Underscore, Backbone, etc.)
- [ ] No obfuscated or encoded code (base64_decode, eval, etc.)
- [ ] No minified code without source files
- [ ] All scripts and styles enqueued properly (not hardcoded)
- [ ] No inline JavaScript or CSS in PHP (use `wp_add_inline_script/style` if needed)
- [ ] Proper use of WordPress cron for scheduled tasks (not system cron)
- [ ] Database changes use `dbDelta()` and proper upgrade routines

### Prefix check
All of the following must be prefixed:
- Functions: `my_plugin_function_name()`
- Classes: `My_Plugin_Class_Name` or namespaced
- Constants: `MY_PLUGIN_CONSTANT`
- Options: `my_plugin_option_name`
- Transients: `my_plugin_transient_name`
- Custom post types: `my_plugin_cpt`
- Taxonomies: `my_plugin_taxonomy`
- Meta keys: `_my_plugin_meta_key`
- AJAX actions: `my_plugin_ajax_action`
- Hooks (custom): `my_plugin_action_name`
- REST routes: `my-plugin/v1`
- Database tables: `{$wpdb->prefix}my_plugin_table`
- Cron hooks: `my_plugin_cron_event`
- Enqueue handles: `my-plugin-script`, `my-plugin-style`

---

## 5. Data & Privacy

### Requirements
- [ ] No unauthorized data collection or tracking
- [ ] No external requests without user knowledge/consent
- [ ] If connecting to external services, clearly documented in readme
- [ ] Privacy policy considerations noted if collecting user data
- [ ] Proper uninstall routine that cleans up all plugin data
- [ ] Uninstall via `uninstall.php` or `register_uninstall_hook()` — NOT `register_deactivation_hook()`
- [ ] No data should remain after uninstall unless user explicitly opts to keep it

### Red flags
- Google Analytics or tracking pixels without consent
- Sending site URL, admin email, or user data to external servers
- Creating user accounts on external services without consent
- No uninstall cleanup

---

## 6. Admin Experience

### Requirements
- [ ] Plugin does not hijack the WordPress admin with excessive notices
- [ ] Admin notices are dismissible and use `is_dismissible` class
- [ ] Notices only appear on relevant pages (not sitewide)
- [ ] Settings page is placed in a logical location (not top-level unless justified)
- [ ] No upsell/ads on every admin page
- [ ] Plugin deactivation doesn't show intrusive surveys or popups
- [ ] "Powered by" or credit links are optional and default to OFF

### Red flags
- Non-dismissible admin notices
- Admin notices on every single page
- Taking over the dashboard with promotional content
- Forced deactivation feedback surveys
- Adding top-level admin menu when a submenu would suffice

---

## 7. Submission Requirements

### Pre-submission checklist
- [ ] Plugin has been tested on current WordPress version
- [ ] Plugin slug doesn't use trademarks (WordPress, Woo, Gutenberg, etc.)
- [ ] No references to "premium" or "pro" version that doesn't exist yet
- [ ] Plugin is functional on submission (not a placeholder)
- [ ] All external service connections are documented
- [ ] Two-Factor Authentication (2FA) enabled on WordPress.org account
- [ ] Plugin passes the Plugin Check tool (PCP) scan

---

## 8. Common Rejection Reasons (Top 95%)

Based on WordPress.org Plugin Review Team data, these account for most rejections:

1. **Missing sanitization** — `$_POST`/`$_GET` used without sanitization
2. **Missing escaping** — Output not escaped with `esc_*()` functions
3. **Missing nonces** — Forms/AJAX without CSRF protection
4. **Missing capability checks** — Admin actions without `current_user_can()`
5. **Bundled libraries** — Including jQuery or other WP core libraries
6. **Missing prefixes** — Functions/classes not uniquely prefixed
7. **Direct file access** — PHP files accessible directly (no ABSPATH check)
8. **Generic function names** — Risk of naming conflicts
9. **Using `$_SERVER` unsafely** — Not sanitizing server variables
10. **Missing `$wpdb->prepare()`** — SQL queries with variable input
11. **Calling external files unnecessarily** — CDN versions when WP bundles them
12. **Not using WordPress APIs** — Raw PHP where WP functions exist
13. **Incorrect readme.txt format** — Missing sections or wrong format
14. **Stable tag set to `trunk`** — Must be a version number
15. **Insufficient plugin description** — Unclear what the plugin does
