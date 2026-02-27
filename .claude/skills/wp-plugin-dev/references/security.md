# WordPress Plugin Security Reference

Source: https://developer.wordpress.org/apis/security/

## Input Sanitization

ALWAYS sanitize user input before processing or storing.

### Common Sanitization Functions

| Function | Use For |
|---|---|
| `sanitize_text_field()` | Single-line text inputs |
| `sanitize_textarea_field()` | Multi-line textarea inputs |
| `sanitize_email()` | Email addresses |
| `sanitize_url()` | URLs |
| `sanitize_title()` | Slugs and titles |
| `sanitize_file_name()` | File names |
| `sanitize_key()` | Keys (lowercase alphanumeric + dashes + underscores) |
| `sanitize_mime_type()` | MIME types |
| `absint()` | Non-negative integers |
| `intval()` | Integers (allows negative) |
| `wp_kses()` | HTML with allowed tags |
| `wp_kses_post()` | HTML allowed in post content |
| `sanitize_hex_color()` | Hex color values |
| `rest_sanitize_boolean()` | Boolean values in REST context |
| `map_deep()` | Recursively sanitize arrays |

### Usage Pattern
```php
// Always sanitize on input
$title = sanitize_text_field( wp_unslash( $_POST['title'] ) );
$email = sanitize_email( wp_unslash( $_POST['email'] ) );
$content = wp_kses_post( wp_unslash( $_POST['content'] ) );
$page_id = absint( $_GET['page_id'] );

// Sanitize arrays recursively
$data = map_deep( wp_unslash( $_POST['data'] ), 'sanitize_text_field' );
```

## Output Escaping

ALWAYS escape output right before rendering. Late escaping principle: escape at the point of output, not earlier.

### Common Escaping Functions

| Function | Use For |
|---|---|
| `esc_html()` | Output inside HTML elements |
| `esc_attr()` | Output inside HTML attributes |
| `esc_url()` | Output in `href`, `src`, or URL context |
| `esc_js()` | Inline JavaScript strings |
| `esc_textarea()` | Inside `<textarea>` |
| `wp_kses()` | Allowing specific HTML tags |
| `wp_kses_post()` | Allowing post-level HTML |
| `esc_html__()` | Translatable + escaped text |
| `esc_html_e()` | Translatable + escaped + echoed |
| `esc_attr__()` | Translatable + attr-escaped text |
| `esc_attr_e()` | Translatable + attr-escaped + echoed |
| `wp_kses_allowed_html()` | Get allowed HTML for context |

### Usage Pattern
```php
// Inside HTML
<p><?php echo esc_html( $user_name ); ?></p>

// Inside attributes
<input value="<?php echo esc_attr( $field_value ); ?>">

// URLs
<a href="<?php echo esc_url( $link ); ?>">Link</a>

// Translatable strings
<h2><?php esc_html_e( 'Settings', 'my-plugin' ); ?></h2>
<p><?php echo esc_html( sprintf( __( 'Hello %s', 'my-plugin' ), $name ) ); ?></p>
```

## Database Security

### $wpdb->prepare() — MANDATORY for all custom SQL

```php
global $wpdb;

// SELECT with placeholders
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}custom_table WHERE status = %s AND user_id = %d",
        $status,
        $user_id
    )
);

// INSERT
$wpdb->insert(
    "{$wpdb->prefix}custom_table",
    array(
        'title'   => $title,
        'user_id' => $user_id,
    ),
    array( '%s', '%d' )
);

// UPDATE
$wpdb->update(
    "{$wpdb->prefix}custom_table",
    array( 'title' => $new_title ),
    array( 'id' => $row_id ),
    array( '%s' ),
    array( '%d' )
);

// DELETE
$wpdb->delete(
    "{$wpdb->prefix}custom_table",
    array( 'id' => $row_id ),
    array( '%d' )
);
```

### Placeholder Types
- `%s` — string
- `%d` — integer
- `%f` — float

**NEVER** concatenate variables directly into SQL strings. Always use `$wpdb->prepare()`.

## Nonces (CSRF Protection)

```php
// Create nonce field in form
wp_nonce_field( 'my_plugin_action', 'my_plugin_nonce' );

// Verify nonce on submission
if ( ! isset( $_POST['my_plugin_nonce'] ) ||
     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['my_plugin_nonce'] ) ), 'my_plugin_action' ) ) {
    wp_die( esc_html__( 'Security check failed.', 'my-plugin' ) );
}

// For AJAX
check_ajax_referer( 'my_plugin_ajax_action', 'nonce' );

// For URL-based actions
$action_url = wp_nonce_url( admin_url( 'admin.php?action=do_thing' ), 'do_thing_nonce' );
```

## Capability Checks

```php
// Check before performing actions
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Unauthorized access.', 'my-plugin' ) );
}

// Common capabilities
// 'manage_options'     — Admins (settings pages)
// 'edit_posts'         — Authors+ (content operations)
// 'upload_files'       — Authors+ (media operations)
// 'edit_others_posts'  — Editors+ (editing others' content)
// 'manage_categories'  — Editors+ (taxonomy management)
// 'activate_plugins'   — Super Admin on multisite
```

## Caching

### Transients API (with expiration)
```php
// Get cached data
$data = get_transient( 'my_plugin_data' );

if ( false === $data ) {
    // Cache miss — fetch and cache
    $data = expensive_query_or_api_call();
    set_transient( 'my_plugin_data', $data, HOUR_IN_SECONDS );
}

// Delete when data changes
delete_transient( 'my_plugin_data' );
```

### Object Cache (per-request or persistent with plugin)
```php
// Set cache
wp_cache_set( 'my_key', $data, 'my_plugin_group', 3600 );

// Get cache
$data = wp_cache_get( 'my_key', 'my_plugin_group' );

// Delete cache
wp_cache_delete( 'my_key', 'my_plugin_group' );
```

### Cache Invalidation on Data Changes
```php
// Invalidate related caches when data is modified
public function save_item( $data ) {
    // ... save logic ...
    delete_transient( 'my_plugin_items_list' );
    wp_cache_delete( 'items_count', 'my_plugin' );
}
```

## File Operations Security
```php
// Validate file types
$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif' );
$file_type = wp_check_filetype( $file_name );
if ( ! in_array( $file_type['type'], $allowed_types, true ) ) {
    wp_die( 'Invalid file type.' );
}

// Use WP filesystem API
global $wp_filesystem;
WP_Filesystem();
$wp_filesystem->put_contents( $file_path, $content, FS_CHMOD_FILE );
```
