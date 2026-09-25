<?php
/**
 * Standalone PHPUnit bootstrap for the public test suite.
 *
 * The project's main phpunit.xml points at the private pro/tests submodule,
 * which outside contributors cannot fetch. This bootstrap is a self-contained
 * WordPress + ACF stub harness so the public tests run with plain PHPUnit:
 *
 *     vendor/bin/phpunit -c tests/phpunit.xml
 *
 * Stubs are driven by the $GLOBALS['emcp_test'] fixture array, reset per test
 * via emcp_test_reset().
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
}
if ( ! defined( 'EMCP_TOOLS_DIR' ) ) {
	define( 'EMCP_TOOLS_DIR', dirname( __DIR__ ) . '/' );
}

/**
 * Resets the shared stub fixture. Call from setUp().
 */
function emcp_test_reset(): void {
	$GLOBALS['emcp_test'] = array(
		'caps'               => array( 'edit_posts', 'manage_options' ),
		'post_caps'          => array(),   // post_id => bool for edit_post checks.
		'acf_pro'            => true,
		'field_groups'       => array(),   // Every group, keyed numerically.
		'groups_for_post'    => array(),   // post_id => group arrays (location match).
		'group_fields'       => array(),   // group_key => top-level field arrays.
		'fields_by_key'      => array(),   // field_key => field array (acf_get_field).
		'field_objects'      => array(),   // target => name => field object (options targets).
		'values'             => array(),   // target => field_key => stored value.
		'update_field_calls' => array(),   // Recorded [key, value, target] triples.
		'imported_groups'    => array(),
		'updated_groups'     => array(),
		'updated_fields'     => array(),
		'posts'              => array(),   // post_id => post-ish object.
		'terms'              => array(),   // term_id => term-like object.
		'users'              => array(),   // user_id => user-like object.
		'attachment_meta'    => array(),   // attachment_id => image metadata.
		'attachment_urls'    => array(),   // attachment_id => URL.
		'attached_files'     => array(),   // attachment_id => local path.
		'uploads_basedir'    => '',        // wp_get_upload_dir() basedir.
		'post_mime_types'    => array(),   // attachment_id => MIME type.
		'options_pages'      => array(),
		'abilities'          => array(),   // name => registration args.
		'options'            => array(),   // option name => value (get_option/update_option).
		'cpt_tax_supported'  => true,      // Toggles the ACF 6.1+ CPT/tax API stubs.
		'acf_post_types'     => array(),   // key/ID => acf-post-type definition.
		'acf_taxonomies'     => array(),   // key/ID => acf-taxonomy definition.
		'existing_types'     => array(),   // slugs seen as already-registered post types.
		'existing_taxes'     => array(),   // slugs seen as already-registered taxonomies.
		'imported_types'     => array(),   // recorded acf_import_post_type() args.
		'imported_taxes'     => array(),   // recorded acf_import_taxonomy() args.
		'updated_internal'   => array(),   // recorded acf_update_internal_post_type() args.
	);
}
emcp_test_reset();

// ---------------------------------------------------------------------------
// Minimal WordPress stubs
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public $ID         = 0;
		public $post_title = '';
		public $post_type  = 'post';
		public function __construct( array $props = array() ) {
			foreach ( $props as $k => $v ) {
				$this->$k = $v;
			}
		}
	}
}

function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function absint( $value ): int {
	return abs( (int) $value );
}

function sanitize_text_field( $value ): string {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) );
}

function sanitize_key( $value ): string {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function esc_url_raw( $value ): string {
	return (string) $value;
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value = null ) {
		return $value; // Pass-through: no registered filters in the public harness.
	}
}

// Fixture-driven: $GLOBALS['emcp_test']['rest_url_base'] sets the REST base so a
// staging-style split (rest_url host != home_url host) can be simulated.
if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '', $scheme = 'rest' ) {
		$base = $GLOBALS['emcp_test']['rest_url_base'] ?? ( home_url() . '/wp-json' );
		$base = rtrim( (string) $base, '/' );
		return '' === (string) $path ? $base . '/' : $base . '/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( $key, $group = '' ) {
		return true;
	}
}
function get_option( $name, $default = false ) {
	return $GLOBALS['emcp_test']['options'][ $name ] ?? $default;
}

function update_option( $name, $value, $autoload = null ): bool {
	$GLOBALS['emcp_test']['options'][ $name ] = $value;
	return true;
}

function current_user_can( $cap, $object_id = null ): bool {
	if ( 'edit_post' === $cap ) {
		$map = $GLOBALS['emcp_test']['post_caps'];
		if ( array_key_exists( (int) $object_id, $map ) ) {
			return (bool) $map[ (int) $object_id ];
		}
		return in_array( 'edit_posts', $GLOBALS['emcp_test']['caps'], true );
	}
	return in_array( $cap, $GLOBALS['emcp_test']['caps'], true );
}

function get_post( $post_id ) {
	return $GLOBALS['emcp_test']['posts'][ (int) $post_id ] ?? null;
}

if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $post = null ) {
		$post = is_object( $post ) ? $post : get_post( $post );
		return $post ? (string) $post->post_type : false;
	}
}

if ( ! function_exists( 'wp_attachment_is_image' ) ) {
	function wp_attachment_is_image( $post = 0 ): bool {
		$mime = $GLOBALS['emcp_test']['post_mime_types'][ (int) $post ] ?? '';
		return 0 === strpos( (string) $mime, 'image/' );
	}
}

if ( ! function_exists( 'get_post_mime_type' ) ) {
	function get_post_mime_type( $post = null ) {
		return $GLOBALS['emcp_test']['post_mime_types'][ (int) $post ] ?? '';
	}
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( $post_id = 0 ) {
		return $GLOBALS['emcp_test']['attachment_urls'][ (int) $post_id ] ?? false;
	}
}

if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
	function wp_get_attachment_metadata( $post_id = 0 ) {
		return $GLOBALS['emcp_test']['attachment_meta'][ (int) $post_id ] ?? array();
	}
}

if ( ! function_exists( 'get_attached_file' ) ) {
	function get_attached_file( $post_id ) {
		return $GLOBALS['emcp_test']['attached_files'][ (int) $post_id ] ?? false;
	}
}

if ( ! function_exists( 'wp_get_upload_dir' ) ) {
	function wp_get_upload_dir(): array {
		return array( 'basedir' => $GLOBALS['emcp_test']['uploads_basedir'] ?? '' );
	}
}

if ( ! function_exists( 'get_term' ) ) {
	function get_term( $term_id, $taxonomy = '' ) {
		return $GLOBALS['emcp_test']['terms'][ (int) $term_id ] ?? null;
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $user_id ) {
		return $GLOBALS['emcp_test']['users'][ (int) $user_id ] ?? false;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	// Records deletions in $GLOBALS['emcp_test']['deleted_meta'] as [post_id, key].
	function delete_post_meta( $post_id, $key, $value = '' ) {
		$GLOBALS['emcp_test']['deleted_meta'][] = array( (int) $post_id, (string) $key );
		return true;
	}
}

function get_permalink( $post = null ): string {
	return 'http://example.test/?p=' . ( is_object( $post ) ? (int) $post->ID : (int) $post );
}

function admin_url( $path = '' ): string {
	return 'http://example.test/wp-admin/' . $path;
}

function home_url( $path = '' ): string {
	// Overridable per test (e.g. a subdirectory install) via the fixture global;
	// defaults to the root host so existing tests are unaffected.
	$base = rtrim( (string) ( $GLOBALS['emcp_test']['home_url'] ?? 'http://example.test' ), '/' );
	return $base . ( '' === $path ? '' : '/' . ltrim( (string) $path, '/' ) );
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $s ) {
		return rtrim( (string) $s, '/' );
	}
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
}

// Fixture-driven: $GLOBALS['emcp_test']['url_to_postid'][ normalized-path ] => post id.
function url_to_postid( $url ) {
	$path = parse_url( (string) $url, PHP_URL_PATH ); // phpcs:ignore
	$path = '/' . trim( strtolower( (string) $path ), '/' );
	return (int) ( $GLOBALS['emcp_test']['url_to_postid'][ $path ] ?? 0 );
}

// Fixture-driven: $GLOBALS['emcp_test']['post_status'][ id ] => 'publish'|'trash'|...
function get_post_status( $id ) {
	return $GLOBALS['emcp_test']['post_status'][ (int) $id ] ?? false;
}

// ---------------------------------------------------------------------------
// ACF stubs (fixture-driven)
// ---------------------------------------------------------------------------

function acf_get_field_groups( $args = array() ): array {
	if ( isset( $args['post_id'] ) ) {
		return $GLOBALS['emcp_test']['groups_for_post'][ (int) $args['post_id'] ] ?? array();
	}
	return $GLOBALS['emcp_test']['field_groups'];
}

function acf_get_field_group( $key ) {
	foreach ( $GLOBALS['emcp_test']['field_groups'] as $group ) {
		if ( ( $group['key'] ?? '' ) === $key || ( isset( $group['ID'] ) && $group['ID'] === $key ) ) {
			return $group;
		}
	}
	return false;
}

function acf_get_fields( $group ): array {
	$key = is_array( $group ) ? ( $group['key'] ?? '' ) : (string) $group;
	return $GLOBALS['emcp_test']['group_fields'][ $key ] ?? array();
}

function acf_get_field( $key ) {
	return $GLOBALS['emcp_test']['fields_by_key'][ $key ] ?? false;
}

function acf_get_field_type( $type ) {
	return 'unknown_type' !== $type;
}

function acf_get_setting( $name ) {
	if ( 'pro' === $name ) {
		return $GLOBALS['emcp_test']['acf_pro'];
	}
	return null;
}

function acf_get_options_pages() {
	return $GLOBALS['emcp_test']['options_pages'];
}

function get_field( $key, $target = false, $format = true ) {
	return $GLOBALS['emcp_test']['values'][ (string) $target ][ $key ] ?? null;
}

function get_field_objects( $target = false, $format = true ) {
	return $GLOBALS['emcp_test']['field_objects'][ (string) $target ] ?? array();
}

function get_field_object( $name, $target = false, $format = true, $load_value = true ) {
	$objects = $GLOBALS['emcp_test']['field_objects'][ (string) $target ] ?? array();
	return $objects[ $name ] ?? false;
}

function update_field( $key, $value, $target = false ): bool {
	$GLOBALS['emcp_test']['update_field_calls'][]                    = array( $key, $value, $target );
	$GLOBALS['emcp_test']['values'][ (string) $target ][ $key ] = $value;
	return true;
}

function acf_import_field_group( $group ) {
	$group['ID']                                = 101 + count( $GLOBALS['emcp_test']['imported_groups'] );
	$GLOBALS['emcp_test']['imported_groups'][] = $group;
	return $group;
}

function acf_update_field_group( $group ) {
	$GLOBALS['emcp_test']['updated_groups'][] = $group;
	return $group;
}

function acf_update_field( $field ) {
	if ( empty( $field['key'] ) ) {
		$field['key'] = uniqid( 'field_' );
	}
	$GLOBALS['emcp_test']['updated_fields'][] = $field;
	return $field;
}

// ---------------------------------------------------------------------------
// WordPress post-type / taxonomy registry stubs
// ---------------------------------------------------------------------------

function post_type_exists( $slug ): bool {
	return in_array( (string) $slug, $GLOBALS['emcp_test']['existing_types'], true );
}

function taxonomy_exists( $slug ): bool {
	return in_array( (string) $slug, $GLOBALS['emcp_test']['existing_taxes'], true );
}

// ---------------------------------------------------------------------------
// ACF 6.1+ CPT / taxonomy stubs (present in the harness = "ACF 6.1+"; the
// EMCP_Tools_ACF_Abilities::cpt_tax_supported() gate keys off function_exists).
// ---------------------------------------------------------------------------

function acf_get_acf_post_types(): array {
	return array_values( $GLOBALS['emcp_test']['acf_post_types'] );
}

function acf_get_acf_taxonomies(): array {
	return array_values( $GLOBALS['emcp_test']['acf_taxonomies'] );
}

function acf_get_internal_post_type( $id, $post_type ) {
	$store = 'acf-post-type' === $post_type ? 'acf_post_types' : 'acf_taxonomies';
	foreach ( $GLOBALS['emcp_test'][ $store ] as $item ) {
		if ( ( $item['key'] ?? '' ) === $id || ( isset( $item['ID'] ) && (int) $item['ID'] === (int) $id ) ) {
			return $item;
		}
	}
	return false;
}

function acf_import_post_type( $def ) {
	$def['ID']                                = 201 + count( $GLOBALS['emcp_test']['imported_types'] );
	$GLOBALS['emcp_test']['imported_types'][] = $def;
	$GLOBALS['emcp_test']['acf_post_types'][ $def['key'] ] = $def;
	return $def;
}

function acf_import_taxonomy( $def ) {
	$def['ID']                                = 301 + count( $GLOBALS['emcp_test']['imported_taxes'] );
	$GLOBALS['emcp_test']['imported_taxes'][] = $def;
	$GLOBALS['emcp_test']['acf_taxonomies'][ $def['key'] ] = $def;
	return $def;
}

function acf_update_internal_post_type( $item, $post_type ) {
	$GLOBALS['emcp_test']['updated_internal'][] = array( 'post_type' => $post_type, 'item' => $item );
	return $item;
}

// ---------------------------------------------------------------------------
// Plugin shim + class under test
// ---------------------------------------------------------------------------

function emcp_tools_register_ability( string $name, array $args ) {
	$GLOBALS['emcp_test']['abilities'][ $name ] = $args;
	return true;
}

// ---------------------------------------------------------------------------
// Meta Box (rwmb_*) stubs, fixture-driven via $GLOBALS['emcp_test']['metabox']
// ---------------------------------------------------------------------------

if ( ! defined( 'RWMB_VER' ) ) { define( 'RWMB_VER', '5.13.1' ); }

if ( ! class_exists( 'EMCP_Test_MB' ) ) {
	/** Minimal RW_Meta_Box test double. */
	class EMCP_Test_MB {
		public $meta_box;
		private $object_type;
		public function __construct( array $meta_box, string $object_type = 'post' ) {
			$this->meta_box = $meta_box; $this->object_type = $object_type;
		}
		public function __get( $k ) { return $this->meta_box[ $k ] ?? null; }
		public function get_object_type() { return $this->object_type; }
	}
}
if ( ! class_exists( 'EMCP_Test_MB_Registry' ) ) {
	class EMCP_Test_MB_Registry {
		public function all() { return $GLOBALS['emcp_test']['metabox']['boxes'] ?? array(); }
		public function get_by( $filter ) {
			$ot = $filter['object_type'] ?? null;
			return array_filter( $this->all(), static function ( $mb ) use ( $ot ) {
				return null === $ot || $mb->get_object_type() === $ot;
			} );
		}
	}
}
if ( ! function_exists( 'rwmb_get_registry' ) ) {
	function rwmb_get_registry( $type ) { return new EMCP_Test_MB_Registry(); }
}
if ( ! function_exists( 'rwmb_meta' ) ) {
	function rwmb_meta( $key, $args = array(), $object_id = null ) {
		$ot  = $args['object_type'] ?? 'post';
		return $GLOBALS['emcp_test']['metabox']['values'][ $ot ][ (string) $object_id ][ $key ] ?? '';
	}
}
if ( ! function_exists( 'rwmb_set_meta' ) ) {
	function rwmb_set_meta( $object_id, $key, $value, $args = array() ) {
		$ot = $args['object_type'] ?? 'post';
		// Emulate MB: no-op for unregistered fields.
		$known = false;
		foreach ( $GLOBALS['emcp_test']['metabox']['boxes'] ?? array() as $mb ) {
			foreach ( (array) $mb->fields as $f ) { if ( ( $f['id'] ?? '' ) === $key ) { $known = true; break 2; } }
		}
		if ( $known ) { $GLOBALS['emcp_test']['metabox']['values'][ $ot ][ (string) $object_id ][ $key ] = $value; }
	}
}

// ---------------------------------------------------------------------------
// Contact Form 7 stubs, fixture-driven via $GLOBALS['emcp_test']['cf7']['forms']
// ---------------------------------------------------------------------------

if ( ! defined( 'WPCF7_VERSION' ) ) { define( 'WPCF7_VERSION', '6.1.6' ); }

if ( ! class_exists( 'WPCF7_FormTag' ) ) {
	class WPCF7_FormTag {
		public $name = '';
		public $type = '';
		public $basetype = '';
		public $values = array();
		public $labels = array();
		public function __construct( array $d = array() ) {
			foreach ( $d as $k => $v ) {
				if ( property_exists( $this, $k ) ) {
					$this->{$k} = $v;
				}
			}
		}
		public function is_required(): bool {
			return str_ends_with( (string) $this->type, '*' );
		}
	}
}

if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
	class WPCF7_ContactForm {
		private $id_;
		public function __construct( $id ) {
			$this->id_ = (int) $id;
		}
		public static function find( $args = array() ) {
			$out = array();
			foreach ( array_keys( $GLOBALS['emcp_test']['cf7']['forms'] ?? array() ) as $id ) {
				$out[] = new self( $id );
			}
			return $out;
		}
		public static function get_instance( $id ) {
			$id = (int) ( is_object( $id ) ? ( $id->ID ?? 0 ) : $id );
			return isset( $GLOBALS['emcp_test']['cf7']['forms'][ $id ] ) ? new self( $id ) : null;
		}
		private function store(): array {
			return $GLOBALS['emcp_test']['cf7']['forms'][ $this->id_ ] ?? array();
		}
		public function id() {
			return $this->id_;
		}
		public function name() {
			return $this->store()['slug'] ?? '';
		}
		public function title() {
			return $this->store()['title'] ?? '';
		}
		public function prop( $k ) {
			return $this->store()['props'][ $k ] ?? '';
		}
		public function set_properties( $props ) {
			foreach ( (array) $props as $k => $v ) {
				$GLOBALS['emcp_test']['cf7']['forms'][ $this->id_ ]['props'][ $k ] = $v;
			}
		}
		public function save() {
			return true;
		}
		public function scan_form_tags() {
			$tags = array();
			foreach ( $this->store()['tags'] ?? array() as $t ) {
				$tags[] = new WPCF7_FormTag( $t );
			}
			return $tags;
		}
	}
}

require_once EMCP_TOOLS_DIR . 'includes/abilities/forms/class-form-integration.php';
require_once EMCP_TOOLS_DIR . 'includes/abilities/forms/class-cf7-integration.php';
require_once EMCP_TOOLS_DIR . 'includes/abilities/class-acf-abilities.php';
require_once EMCP_TOOLS_DIR . 'includes/abilities/class-metabox-abilities.php';
require_once EMCP_TOOLS_DIR . 'includes/class-package-guard.php';
require_once EMCP_TOOLS_DIR . 'includes/redirects/class-redirect-store.php';
require_once EMCP_TOOLS_DIR . 'includes/abilities/class-redirect-abilities.php';
