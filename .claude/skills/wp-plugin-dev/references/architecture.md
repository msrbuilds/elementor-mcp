# WordPress Plugin Architecture Reference

## Standard Plugin Structure

```
plugin-name/
├── plugin-name.php              ← Main bootstrap file (minimal)
├── uninstall.php                ← Clean uninstall handler
├── readme.txt                   ← WordPress.org readme
├── LICENSE                      ← GPLv2 or later
├── includes/
│   ├── class-plugin-name.php         ← Main plugin class (orchestrator)
│   ├── class-plugin-name-loader.php  ← Hook/filter registration
│   ├── class-plugin-name-i18n.php    ← Internationalization
│   ├── class-plugin-name-activator.php
│   └── class-plugin-name-deactivator.php
├── admin/
│   ├── class-plugin-name-admin.php   ← Admin-specific functionality
│   ├── css/
│   │   └── plugin-name-admin.css
│   ├── js/
│   │   └── plugin-name-admin.js
│   └── partials/
│       └── plugin-name-admin-display.php
├── public/
│   ├── class-plugin-name-public.php  ← Public-facing functionality
│   ├── css/
│   │   └── plugin-name-public.css
│   ├── js/
│   │   └── plugin-name-public.js
│   └── partials/
│       └── plugin-name-public-display.php
├── languages/
│   └── plugin-name.pot
└── templates/                        ← Overridable templates (optional)
    └── single-cpt.php
```

## Main Plugin File — Bootstrap Only

The main plugin file (`plugin-name.php`) MUST only contain:
- Plugin header comment
- Constants (version, paths)
- Activation/deactivation hooks
- Autoloader or manual requires
- Plugin initialization

```php
<?php
/**
 * Plugin Name:       {Plugin Name}
 * Plugin URI:        {Plugin URI}
 * Description:       {Description}
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            {Author}
 * Author URI:        {Author URI}
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       {text-domain}
 * Domain Path:       /languages
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( '{PLUGIN_PREFIX}_VERSION', '1.0.0' );
define( '{PLUGIN_PREFIX}_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( '{PLUGIN_PREFIX}_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( '{PLUGIN_PREFIX}_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Activation and deactivation.
register_activation_hook( __FILE__, array( '{Plugin_Name}_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '{Plugin_Name}_Deactivator', 'deactivate' ) );

// Autoloader or manual includes.
require_once {PLUGIN_PREFIX}_PLUGIN_DIR . 'includes/class-{plugin-slug}.php';

/**
 * Begin plugin execution.
 *
 * @since 1.0.0
 */
function {prefix}_run() {
    $plugin = new {Plugin_Name}();
    $plugin->run();
}
{prefix}_run();
```

**DO NOT** add settings registration, shortcode handlers, AJAX callbacks, CPT registration, or any feature logic here. Everything is loaded via class files.

## Modular Feature Pattern

Each feature gets its own class file:

```php
<?php
/**
 * Handles {Feature Name} functionality.
 *
 * @package    {Plugin_Name}
 * @subpackage {Plugin_Name}/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class {Plugin_Name}_{Feature} {

    /**
     * Initialize the feature.
     *
     * @since 1.0.0
     */
    public function init() {
        add_action( 'init', array( $this, 'register' ) );
        add_filter( 'some_filter', array( $this, 'filter_callback' ) );
    }

    /**
     * Register feature components.
     *
     * @since 1.0.0
     */
    public function register() {
        // Feature registration logic.
    }
}
```

## Custom Post Type Module

```php
<?php
class {Plugin_Name}_Post_Types {

    public function init() {
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'init', array( $this, 'register_taxonomies' ) );
    }

    public function register_post_types() {
        $labels = array(
            'name'               => _x( '{Plural}', 'post type general name', '{text-domain}' ),
            'singular_name'      => _x( '{Singular}', 'post type singular name', '{text-domain}' ),
            'menu_name'          => _x( '{Plural}', 'admin menu', '{text-domain}' ),
            'add_new'            => _x( 'Add New', '{singular}', '{text-domain}' ),
            'add_new_item'       => __( 'Add New {Singular}', '{text-domain}' ),
            'edit_item'          => __( 'Edit {Singular}', '{text-domain}' ),
            'new_item'           => __( 'New {Singular}', '{text-domain}' ),
            'view_item'          => __( 'View {Singular}', '{text-domain}' ),
            'search_items'       => __( 'Search {Plural}', '{text-domain}' ),
            'not_found'          => __( 'No {plural} found.', '{text-domain}' ),
            'not_found_in_trash' => __( 'No {plural} found in Trash.', '{text-domain}' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,  // Required for Gutenberg support
            'query_var'          => true,
            'rewrite'            => array( 'slug' => '{slug}' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-{icon}',
            'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        );

        register_post_type( '{prefix}_{cpt}', $args );
    }
}
```

## REST API Module

```php
<?php
class {Plugin_Name}_REST_API {

    /**
     * API namespace.
     *
     * @var string
     */
    private $namespace = '{plugin-slug}/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/items',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'get_items_permissions_check' ),
                    'args'                => $this->get_collection_params(),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'create_item_permissions_check' ),
                    'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/items/(?P<id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'get_item_permissions_check' ),
                    'args'                => array(
                        'id' => array(
                            'validate_callback' => function( $param ) {
                                return is_numeric( $param );
                            },
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
            )
        );
    }

    public function get_items_permissions_check( $request ) {
        return true; // Public endpoint — adjust per requirement
    }

    public function create_item_permissions_check( $request ) {
        return current_user_can( 'edit_posts' );
    }

    public function get_items( $request ) {
        $per_page = absint( $request->get_param( 'per_page' ) ) ?: 10;
        $page     = absint( $request->get_param( 'page' ) ) ?: 1;

        // Query logic here with $wpdb->prepare() or WP_Query
        $items = array(); // Fetch items

        return new WP_REST_Response( $items, 200 );
    }

    public function create_item( $request ) {
        $title = sanitize_text_field( $request->get_param( 'title' ) );

        if ( empty( $title ) ) {
            return new WP_Error(
                'missing_title',
                __( 'Title is required.', '{text-domain}' ),
                array( 'status' => 400 )
            );
        }

        // Create logic here
        return new WP_REST_Response( $item, 201 );
    }
}
```

## Gutenberg Block Module

```
block-name/
├── block.json         ← Block metadata (required)
├── src/
│   ├── index.js       ← Block registration
│   ├── edit.js        ← Editor component
│   ├── save.js        ← Frontend save
│   └── style.scss
└── build/             ← Compiled output (via wp-scripts)
```

### block.json
```json
{
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "{plugin-slug}/{block-name}",
    "version": "1.0.0",
    "title": "{Block Title}",
    "category": "widgets",
    "icon": "smiley",
    "description": "{Block description}",
    "supports": {
        "html": false,
        "align": true
    },
    "attributes": {
        "content": {
            "type": "string",
            "default": ""
        }
    },
    "textdomain": "{text-domain}",
    "editorScript": "file:./build/index.js",
    "editorStyle": "file:./build/index.css",
    "style": "file:./build/style-index.css",
    "render": "file:./render.php"
}
```

### PHP Registration
```php
<?php
class {Plugin_Name}_Blocks {

    public function init() {
        add_action( 'init', array( $this, 'register_blocks' ) );
    }

    public function register_blocks() {
        register_block_type( {PLUGIN_PREFIX}_PLUGIN_DIR . 'blocks/block-name' );
    }
}
```

## WooCommerce Extension Module

```php
<?php
class {Plugin_Name}_WooCommerce {

    public function init() {
        // Check WooCommerce dependency.
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'add_product_panel' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
    }

    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( '{Plugin Name} requires WooCommerce to be installed and activated.', '{text-domain}' ); ?></p>
        </div>
        <?php
    }

    public function save_product_meta( $post_id ) {
        // Verify nonce.
        if ( ! isset( $_POST['_wpnonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-post_' . $post_id ) ) {
            return;
        }

        // Check capabilities.
        if ( ! current_user_can( 'edit_product', $post_id ) ) {
            return;
        }

        // Sanitize and save.
        if ( isset( $_POST['{prefix}_custom_field'] ) ) {
            update_post_meta(
                $post_id,
                '_{prefix}_custom_field',
                sanitize_text_field( wp_unslash( $_POST['{prefix}_custom_field'] ) )
            );
        }
    }
}
```

## AJAX Handler Pattern

```php
<?php
class {Plugin_Name}_Ajax {

    public function init() {
        add_action( 'wp_ajax_{prefix}_action', array( $this, 'handle_action' ) );
        add_action( 'wp_ajax_nopriv_{prefix}_action', array( $this, 'handle_action' ) ); // If public
    }

    public function handle_action() {
        // 1. Verify nonce
        check_ajax_referer( '{prefix}_nonce', 'nonce' );

        // 2. Check capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', '{text-domain}' ) ), 403 );
        }

        // 3. Sanitize input
        $item_id = absint( $_POST['item_id'] ?? 0 );
        $title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );

        if ( empty( $title ) ) {
            wp_send_json_error( array( 'message' => __( 'Title is required.', '{text-domain}' ) ), 400 );
        }

        // 4. Process
        $result = $this->process( $item_id, $title );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
        }

        // 5. Return
        wp_send_json_success( array( 'data' => $result ) );
    }
}
```

## Settings Page Pattern

```php
<?php
class {Plugin_Name}_Settings {

    private $option_name = '{prefix}_settings';

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_menu_page() {
        add_options_page(
            __( '{Plugin Name} Settings', '{text-domain}' ),
            __( '{Plugin Name}', '{text-domain}' ),
            'manage_options',
            '{plugin-slug}-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting(
            '{prefix}_settings_group',
            $this->option_name,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => $this->get_defaults(),
            )
        );

        add_settings_section(
            '{prefix}_general',
            __( 'General Settings', '{text-domain}' ),
            '__return_false',
            '{plugin-slug}-settings'
        );

        add_settings_field(
            '{prefix}_api_key',
            __( 'API Key', '{text-domain}' ),
            array( $this, 'render_text_field' ),
            '{plugin-slug}-settings',
            '{prefix}_general',
            array( 'field' => 'api_key' )
        );
    }

    public function sanitize_settings( $input ) {
        $sanitized = array();
        $sanitized['api_key'] = sanitize_text_field( $input['api_key'] ?? '' );
        return $sanitized;
    }

    public function get_defaults() {
        return array(
            'api_key' => '',
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( '{prefix}_settings_group' );
                do_settings_sections( '{plugin-slug}-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_text_field( $args ) {
        $options = get_option( $this->option_name, $this->get_defaults() );
        $field   = $args['field'];
        $value   = $options[ $field ] ?? '';
        ?>
        <input
            type="text"
            id="<?php echo esc_attr( $field ); ?>"
            name="<?php echo esc_attr( $this->option_name . '[' . $field . ']' ); ?>"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
        />
        <?php
    }
}
```

## Custom Database Table Pattern

```php
<?php
class {Plugin_Name}_Database {

    public static function create_tables() {
        global $wpdb;

        $table_name      = $wpdb->prefix . '{prefix}_items';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL DEFAULT '',
            content longtext NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'active',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY user_id (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( '{prefix}_db_version', '1.0.0' );
    }

    public static function get_item( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . '{prefix}_items';

        // Check cache first
        $cache_key = 'item_' . $id;
        $item      = wp_cache_get( $cache_key, '{prefix}' );

        if ( false === $item ) {
            $item = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
            );

            if ( $item ) {
                wp_cache_set( $cache_key, $item, '{prefix}', HOUR_IN_SECONDS );
            }
        }

        return $item;
    }

    public static function insert_item( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . '{prefix}_items';

        $result = $wpdb->insert(
            $table,
            array(
                'title'   => sanitize_text_field( $data['title'] ),
                'content' => wp_kses_post( $data['content'] ),
                'status'  => sanitize_key( $data['status'] ?? 'active' ),
                'user_id' => absint( $data['user_id'] ),
            ),
            array( '%s', '%s', '%s', '%d' )
        );

        if ( false !== $result ) {
            // Invalidate caches
            wp_cache_delete( 'items_list', '{prefix}' );
        }

        return $result ? $wpdb->insert_id : false;
    }
}
```

## Uninstall Pattern (uninstall.php)

```php
<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package {Plugin_Name}
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Delete options.
delete_option( '{prefix}_settings' );
delete_option( '{prefix}_db_version' );

// Delete transients.
delete_transient( '{prefix}_cache' );

// Drop custom tables (optional — some plugins keep data).
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{prefix}_items" );

// Clean up user meta.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '{prefix}\_%'" );

// Clean up post meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_{prefix}\_%'" );
```

## readme.txt Template

```
=== {Plugin Name} ===
Contributors: {wp-username}
Donate link: {donate-url}
Tags: {tag1}, {tag2}, {tag3}
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

{Short description — max 150 characters.}

== Description ==

{Detailed description of the plugin.}

**Features:**

* Feature 1
* Feature 2
* Feature 3

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/{plugin-slug}` or install through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to Settings > {Plugin Name} to configure.

== Frequently Asked Questions ==

= Question 1? =

Answer 1.

== Screenshots ==

1. Screenshot description 1.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
```

## Enqueueing Assets Properly

```php
<?php
// Admin assets — only on plugin pages
public function enqueue_admin_assets( $hook ) {
    // Only load on our settings page
    if ( 'settings_page_{plugin-slug}-settings' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        '{plugin-slug}-admin',
        {PLUGIN_PREFIX}_PLUGIN_URL . 'admin/css/{plugin-slug}-admin.css',
        array(),
        {PLUGIN_PREFIX}_VERSION
    );

    wp_enqueue_script(
        '{plugin-slug}-admin',
        {PLUGIN_PREFIX}_PLUGIN_URL . 'admin/js/{plugin-slug}-admin.js',
        array( 'jquery' ),  // Use WP bundled jQuery
        {PLUGIN_PREFIX}_VERSION,
        true
    );

    wp_localize_script(
        '{plugin-slug}-admin',
        '{prefix}Admin',
        array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( '{prefix}_nonce' ),
            'i18n'     => array(
                'confirm' => __( 'Are you sure?', '{text-domain}' ),
            ),
        )
    );
}

// Public assets — conditionally
public function enqueue_public_assets() {
    // Only load when needed (e.g., shortcode is present)
    if ( ! has_shortcode( get_post()->post_content ?? '', '{prefix}_shortcode' ) ) {
        return;
    }

    wp_enqueue_style(
        '{plugin-slug}-public',
        {PLUGIN_PREFIX}_PLUGIN_URL . 'public/css/{plugin-slug}-public.css',
        array(),
        {PLUGIN_PREFIX}_VERSION
    );
}
```
