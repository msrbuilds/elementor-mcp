# WordPress Plugin Security Checklist

Comprehensive security review checklist based on WordPress.org Plugin Review Team requirements,
OWASP Top 10, and WordPress VIP coding standards.

## Table of Contents
1. Input Sanitization
2. Output Escaping
3. SQL Injection Prevention
4. Nonce Verification (CSRF)
5. Capability Checks (Authorization)
6. File Security
7. Data Validation
8. External Requests
9. Authentication & Session
10. Information Disclosure
11. Code Quality Security

---

## 1. Input Sanitization

Every superglobal access MUST be sanitized before use.

### What to check
- `$_GET`, `$_POST`, `$_REQUEST` — use `sanitize_text_field()`, `absint()`, `sanitize_email()`, etc.
- `$_SERVER` — use `sanitize_text_field()` especially for `HTTP_HOST`, `REQUEST_URI`, `HTTP_REFERER`
- `$_FILES` — validate file type, size, and use `wp_check_filetype()`
- `$_COOKIE` — sanitize all cookie values

### WordPress sanitization functions
| Function | Use for |
|----------|---------|
| `sanitize_text_field()` | Generic text input |
| `sanitize_textarea_field()` | Multiline text |
| `sanitize_email()` | Email addresses |
| `sanitize_file_name()` | File names |
| `sanitize_title()` | Slugs and titles |
| `sanitize_key()` | Keys and identifiers |
| `sanitize_mime_type()` | MIME types |
| `sanitize_url()` / `esc_url_raw()` | URLs for DB storage |
| `absint()` | Non-negative integers |
| `intval()` | Integers |
| `wp_kses()` | HTML with allowed tags |
| `wp_kses_post()` | HTML (post content allowed tags) |

### Red flags
- Raw `$_POST['field']` used without sanitization
- `strip_tags()` used instead of proper WP sanitization
- Custom sanitization functions instead of WP core functions
- `filter_input()` without proper filter constants

---

## 2. Output Escaping

All dynamic output MUST be escaped. Escape LATE — as close to output as possible.

### Escaping functions by context
| Function | Context |
|----------|---------|
| `esc_html()` | Inside HTML elements |
| `esc_attr()` | Inside HTML attributes |
| `esc_url()` | In `href`, `src`, and URL contexts |
| `esc_textarea()` | Inside `<textarea>` |
| `esc_js()` | Inside inline JavaScript |
| `wp_kses()` | When HTML output is expected |
| `wp_kses_post()` | Post content with allowed HTML |
| `esc_html__()` | Translated strings in HTML |
| `esc_attr__()` | Translated strings in attributes |

### Red flags
- `echo $variable;` without escaping
- `echo __('text', 'domain');` — should be `echo esc_html__('text', 'domain');`
- `printf()` with unescaped variables
- `echo '<input value="' . $value . '">';` — missing `esc_attr()`
- Using `sanitize_text_field()` for output (sanitization ≠ escaping)

---

## 3. SQL Injection Prevention

### Requirements
- ALL custom SQL queries MUST use `$wpdb->prepare()`
- Use WordPress API functions instead of raw SQL when possible:
  - `get_option()` / `update_option()` instead of direct options table queries
  - `get_post_meta()` / `update_post_meta()` for post meta
  - `WP_Query` for post queries
  - `get_users()` / `WP_User_Query` for user queries

### Red flags
- `$wpdb->query("SELECT * FROM table WHERE id = $id")`
- String concatenation in SQL: `"WHERE name = '" . $name . "'"`
- `$wpdb->prepare()` with `%s` inside quotes: `prepare("WHERE x = '%s'", $val)` — the `%s` should NOT be quoted
- Missing `$wpdb->prepare()` on any query with variable input
- Using `esc_sql()` instead of `$wpdb->prepare()` (less safe)

### Correct pattern
```php
$wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}table WHERE id = %d AND name = %s",
        $id,
        $name
    )
);
```

---

## 4. Nonce Verification (CSRF Protection)

### Requirements
- Every form submission MUST include a nonce field
- Every AJAX handler MUST verify a nonce
- Every admin POST action MUST verify a nonce

### Nonce functions
| Function | Use |
|----------|-----|
| `wp_nonce_field('action', 'nonce_name')` | Add nonce to forms |
| `wp_create_nonce('action')` | Create nonce for AJAX/URLs |
| `wp_nonce_url($url, 'action')` | Add nonce to URL |
| `wp_verify_nonce($_POST['nonce'], 'action')` | Verify nonce |
| `check_admin_referer('action', 'nonce_name')` | Verify admin form nonce |
| `check_ajax_referer('action', 'nonce_name')` | Verify AJAX nonce |

### Red flags
- Form without `wp_nonce_field()`
- AJAX handler without `check_ajax_referer()` or `wp_verify_nonce()`
- Nonce action string is too generic (e.g., `'nonce'` instead of `'plugin_name_action'`)
- Nonce verification after processing data (must be BEFORE)

---

## 5. Capability Checks (Authorization)

### Requirements
- All admin actions MUST check `current_user_can()` with appropriate capability
- AJAX handlers registered with `wp_ajax_` MUST check capabilities
- REST API endpoints MUST have `permission_callback`

### Common capabilities
| Capability | Who has it |
|-----------|-----------|
| `manage_options` | Administrators |
| `edit_posts` | Editors, Authors, Contributors |
| `upload_files` | Authors and above |
| `edit_others_posts` | Editors and above |
| `delete_plugins` | Super Admins (multisite) |

### Red flags
- AJAX handler without `current_user_can()` check
- REST API route with `'permission_callback' => '__return_true'` for write operations
- Using `is_admin()` as a security check (it's NOT — it only checks if you're on admin page)
- Hardcoded role checks like `$user->roles[0] === 'administrator'` instead of capabilities

---

## 6. File Security

### Direct file access prevention
Every PHP file should start with:
```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
```

### File upload security
- Validate MIME type with `wp_check_filetype()`
- Check file size against limits
- Use `wp_handle_upload()` for processing
- Store files using `wp_upload_dir()` paths
- Never allow PHP file uploads
- Don't trust `$_FILES['type']` — verify server-side

### Red flags
- PHP files without ABSPATH check
- Direct `move_uploaded_file()` usage
- File paths constructed from user input without validation
- Including files based on user input: `include $_GET['page']`
- Writable files in plugin directory

---

## 7. Data Validation

### Requirements
- Validate data type matches expectations
- Validate data is within expected range
- Validate against a whitelist where possible

### Validation patterns
```php
// Whitelist validation
$allowed = array( 'option1', 'option2', 'option3' );
if ( ! in_array( $value, $allowed, true ) ) {
    return; // Invalid
}

// Type validation
if ( ! is_numeric( $value ) ) {
    return; // Invalid
}

// Range validation
$value = absint( $value );
if ( $value < 1 || $value > 100 ) {
    return; // Out of range
}
```

---

## 8. External Requests

### Requirements
- Use `wp_remote_get()` / `wp_remote_post()` — never raw cURL or `file_get_contents()`
- Validate response codes
- Set reasonable timeouts
- Validate and sanitize response data
- Use HTTPS URLs

### Red flags
- `curl_init()` / `curl_exec()` usage
- `file_get_contents()` with URL
- No timeout set on remote requests
- Response data used without validation
- HTTP (non-HTTPS) external URLs
- Phoning home / tracking without user consent

---

## 9. Authentication & Session

### Red flags
- Custom login/authentication instead of WP auth functions
- Direct session manipulation (`session_start()`)
- Storing passwords in plain text or reversible encryption
- Cookies set without `httponly` and `secure` flags
- Custom password hashing instead of `wp_hash_password()`

---

## 10. Information Disclosure

### Red flags
- `phpinfo()` calls
- Debug output left in production code
- Error messages exposing file paths or DB details
- Stack traces displayed to users
- WordPress version exposed in custom output
- Database table names or structure in client-side code

---

## 11. Code Quality Security

### Red flags
- `eval()` usage
- `exec()`, `shell_exec()`, `system()`, `passthru()` usage
- `preg_replace()` with `e` modifier (code execution)
- `extract()` on user input
- `unserialize()` on untrusted data (use `json_decode()` instead)
- `assert()` with string arguments
- Dynamic function calls: `$func()` with user-controlled `$func`
- `call_user_func()` with user input
