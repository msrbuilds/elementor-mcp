<?php
/**
 * PHPUnit bootstrap for elementor-mcp unit tests.
 *
 * Provides minimal WordPress and Elementor stubs so security-focused unit
 * tests can run without a full WordPress installation.
 *
 * NOTE: PHP requires all code to be in namespace blocks once any named
 * namespace appears.  Global code lives in `namespace { ... }` blocks;
 * Elementor stubs live in `namespace Elementor { ... }`.
 *
 * @package EMCP_Tools\Tests
 */

// ---------------------------------------------------------------------------
// Global constants and WordPress / plugin function stubs
// ---------------------------------------------------------------------------

namespace {

	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	define( 'ELEMENTOR_VERSION', '3.25.0' );
	define( 'EMCP_TOOLS_VERSION', '1.5.0' );
	define( 'EMCP_TOOLS_DIR', dirname( __DIR__ ) . '/' );

	// -----------------------------------------------------------------------
	// Global state used by recording stubs.
	// Reset per-test via $GLOBALS['_wp_meta_calls'] = [] in setUp().
	// -----------------------------------------------------------------------
	$GLOBALS['_wp_meta_calls']    = [];
	$GLOBALS['_wp_http_calls']    = [];
	$GLOBALS['_wp_deleted_posts'] = [];

	// -----------------------------------------------------------------------
	// Capability control for T2 capability tests.
	//
	// $GLOBALS['_caps'] controls what current_user_can() returns:
	//   null (default) → always returns true  (backward compat with Step 7)
	//   []             → always returns false (no capabilities)
	//   ['edit_posts'] → returns true only for listed capabilities
	//
	// Use helper EMCP_Tools_Test_Caps::allow(), ::deny(), ::none(), ::all()
	// -----------------------------------------------------------------------
	$GLOBALS['_caps'] = null;

	// -----------------------------------------------------------------------
	// Active Elementor experiments for atomic-detection tests.
	// $GLOBALS['_active_experiments'] lists the experiment slugs the
	// \Elementor\Plugin stub's experiments->is_feature_active() reports active.
	// Reset per-test in setUp(); empty by default.
	// -----------------------------------------------------------------------
	$GLOBALS['_active_experiments'] = [];

	// -----------------------------------------------------------------------
	// Registered atomic element types for atomic-detection tests.
	// $GLOBALS['_registered_element_types'] lists the element-type slugs the
	// \Elementor\Plugin stub's elements_manager->get_element_types() returns
	// (e.g. 'e-flexbox'). Reset per-test in setUp(); empty by default.
	// -----------------------------------------------------------------------
	$GLOBALS['_registered_element_types'] = [];

	// -----------------------------------------------------------------------
	// WordPress function stubs
	// -----------------------------------------------------------------------

	if ( ! function_exists( 'absint' ) ) {
		function absint( $maybeint ): int {
			return abs( (int) $maybeint );
		}
	}

	if ( ! function_exists( '__' ) ) {
		function __( string $text, string $domain = 'default' ): string {
			return $text;
		}
	}

	if ( ! function_exists( '_e' ) ) {
		function _e( string $text, string $domain = 'default' ): void {
			echo $text;
		}
	}

	if ( ! function_exists( 'esc_html__' ) ) {
		function esc_html__( string $text, string $domain = 'default' ): string {
			return htmlspecialchars( $text, ENT_QUOTES );
		}
	}

	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $str ): string {
			return trim( strip_tags( (string) $str ) );
		}
	}

	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $key ): string {
			return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ) );
		}
	}

	if ( ! function_exists( 'sanitize_file_name' ) ) {
		function sanitize_file_name( string $filename ): string {
			return preg_replace( '/[^a-zA-Z0-9._\-]/', '-', $filename );
		}
	}

	if ( ! function_exists( 'sanitize_title' ) ) {
		function sanitize_title( string $title, string $fallback_title = '', string $context = 'save' ): string {
			return sanitize_key( str_replace( ' ', '-', strtolower( $title ) ) );
		}
	}

	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing ): bool {
			return $thing instanceof \WP_Error;
		}
	}

	/**
	 * WordPress stub: esc_url_raw() normalises URL syntax but does NOT filter
	 * RFC1918 / loopback / link-local addresses — faithfully mirrors real WP.
	 */
	if ( ! function_exists( 'esc_url_raw' ) ) {
		function esc_url_raw( string $url, array $protocols = [] ): string {
			$url = trim( $url );
			if ( '' === $url ) {
				return '';
			}
			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
			if ( $scheme !== false && $scheme !== null ) {
				$allowed = [
					'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6',
					'ircs', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms',
					'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn',
				];
				if ( ! in_array( strtolower( $scheme ), $allowed, true ) ) {
					return '';
				}
			}
			return $url;
		}
	}

	if ( ! function_exists( 'wp_parse_url' ) ) {
		function wp_parse_url( string $url, int $component = -1 ) {
			return parse_url( $url, $component );
		}
	}

	if ( ! function_exists( 'wp_json_encode' ) ) {
		function wp_json_encode( $data, int $options = 0, int $depth = 512 ) {
			$json = json_encode( $data, $options, $depth );
			return ( JSON_ERROR_NONE === json_last_error() ) ? $json : false;
		}
	}

	if ( ! function_exists( 'wp_slash' ) ) {
		function wp_slash( $value ) {
			if ( is_array( $value ) ) {
				return array_map( 'wp_slash', $value );
			}
			return addslashes( (string) $value );
		}
	}

	/**
	 * Recording stub: stores every call so tests can assert which meta keys
	 * were written.  $GLOBALS['_wp_meta_calls'] is cleared in setUp().
	 */
	if ( ! function_exists( 'update_post_meta' ) ) {
		function update_post_meta( int $post_id, string $meta_key, $meta_value, $prev_value = '' ) {
			$GLOBALS['_wp_meta_calls'][] = [
				'action'   => 'update',
				'post_id'  => $post_id,
				'meta_key' => $meta_key,
			];
			return true;
		}
	}

	if ( ! function_exists( 'get_post_meta' ) ) {
		function get_post_meta( int $post_id, string $key = '', bool $single = false ) {
			return $single ? '' : [];
		}
	}

	if ( ! function_exists( 'delete_post_meta' ) ) {
		function delete_post_meta( int $post_id, string $meta_key, $meta_value = '' ): bool {
			$GLOBALS['_wp_meta_calls'][] = [
				'action'   => 'delete',
				'post_id'  => $post_id,
				'meta_key' => $meta_key,
			];
			return true;
		}
	}

	if ( ! function_exists( 'wp_get_upload_dir' ) ) {
		function wp_get_upload_dir(): array {
			return [
				'basedir' => sys_get_temp_dir(),
				'baseurl' => 'http://example.com/wp-content/uploads',
			];
		}
	}

	if ( ! function_exists( 'wp_delete_file' ) ) {
		function wp_delete_file( string $file ): bool {
			return @unlink( $file );
		}
	}

	/**
	 * Recording stub: records download_url calls so tests can assert whether
	 * the HTTP request was (or was not) reached.
	 */
	if ( ! function_exists( 'download_url' ) ) {
		function download_url( string $url, int $timeout = 300, bool $signature_verification = false ) {
			$GLOBALS['_wp_http_calls'][] = [ 'url' => $url ];
			return new \WP_Error( 'download_disabled', 'HTTP requests are disabled in unit tests.' );
		}
	}

	if ( ! function_exists( 'wp_register_ability' ) ) {
		function wp_register_ability( string $name, array $args ): void {
			// No-op in unit tests.
		}
	}

	if ( ! function_exists( 'emcp_tools_register_ability' ) ) {
		// The ability groups register through this shim (real implementation in
		// includes/class-schema-compat.php, which sanitizes schemas then calls
		// wp_register_ability). Unit tests only need it to exist so register()
		// methods don't fatal; forward to the wp_register_ability stub.
		function emcp_tools_register_ability( string $name, array $args ): void {
			wp_register_ability( $name, $args );
		}
	}

	if ( ! function_exists( 'current_user_can' ) ) {
		/**
		 * Configurable current_user_can stub.
		 *
		 * Behaviour is controlled by $GLOBALS['_caps']:
		 *   null  → always true  (Step 7 backward compat)
		 *   array → true only when $cap is in the array
		 */
		function current_user_can( string $cap, ...$args ): bool {
			if ( null === $GLOBALS['_caps'] ) {
				return true;
			}
			return in_array( $cap, (array) $GLOBALS['_caps'], true );
		}
	}

	if ( ! function_exists( 'wp_insert_post' ) ) {
		function wp_insert_post( array $args, bool $wp_error = false ) {
			static $id = 100;
			return ++$id;
		}
	}

	if ( ! function_exists( 'wp_delete_post' ) ) {
		function wp_delete_post( int $post_id, bool $force_delete = false ) {
			$GLOBALS['_wp_deleted_posts'][] = $post_id;
			return true;
		}
	}

	if ( ! function_exists( 'get_the_title' ) ) {
		function get_the_title( $post = 0 ): string {
			return 'Test Post';
		}
	}

	if ( ! function_exists( 'admin_url' ) ) {
		function admin_url( string $path = '', string $scheme = 'admin' ): string {
			return 'http://example.com/wp-admin/' . ltrim( $path, '/' );
		}
	}

	if ( ! function_exists( 'get_permalink' ) ) {
		function get_permalink( $post = 0 ) {
			return 'http://example.com/test-page/';
		}
	}

	if ( ! function_exists( 'wp_update_post' ) ) {
		function wp_update_post( array $args, bool $wp_error = false ) {
			return $args['ID'] ?? 0;
		}
	}

	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( string $hook_name, $value ) {
			return $value;
		}
	}

	if ( ! function_exists( 'wp_remote_get' ) ) {
		function wp_remote_get( string $url, array $args = [] ) {
			return new \WP_Error( 'http_request_failed', 'HTTP requests are disabled in unit tests.' );
		}
	}

	if ( ! function_exists( 'is_wp_error' ) ) {
		// already defined above — this guard is a safety net
		function is_wp_error_compat( $thing ): bool {
			return $thing instanceof \WP_Error;
		}
	}

	if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
		function wp_remote_retrieve_response_code( $response ) {
			if ( is_wp_error( $response ) ) {
				return 0;
			}
			return $response['response']['code'] ?? 0;
		}
	}

	if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
		function wp_remote_retrieve_body( $response ): string {
			if ( is_wp_error( $response ) ) {
				return '';
			}
			return $response['body'] ?? '';
		}
	}

	if ( ! function_exists( 'trailingslashit' ) ) {
		function trailingslashit( string $string ): string {
			return rtrim( $string, '/\\' ) . '/';
		}
	}

	if ( ! function_exists( 'add_query_arg' ) ) {
		function add_query_arg( $args, string $url = '' ): string {
			if ( is_array( $args ) ) {
				$query = http_build_query( $args );
				return $url . ( strpos( $url, '?' ) !== false ? '&' : '?' ) . $query;
			}
			return $url;
		}
	}

	if ( ! function_exists( 'media_sideload_image' ) ) {
		function media_sideload_image( string $url, int $post_id = 0, string $desc = '', string $return = 'html' ) {
			return new \WP_Error( 'sideload_disabled', 'media_sideload_image is disabled in unit tests.' );
		}
	}

	if ( ! function_exists( 'get_post' ) ) {
		function get_post( $post = null, string $output = 'OBJECT', string $filter = 'raw' ) {
			if ( is_object( $post ) ) { return $post; }
			$id = absint( $post );
			return $GLOBALS['_wp_posts'][ $id ] ?? null;
		}
	}

	if ( ! function_exists( 'maybe_unserialize' ) ) {
		function maybe_unserialize( $value ) { return is_string( $value ) ? ( @unserialize( $value ) !== false || 'b:0;' === $value ? unserialize( $value ) : $value ) : $value; }
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( string $option, $default = false ) {
			return $default;
		}
	}

	if ( ! function_exists( 'update_option' ) ) {
		function update_option( string $option, $value, $autoload = null ): bool {
			return true;
		}
	}

	if ( ! function_exists( 'esc_url' ) ) {
		function esc_url( string $url, array $protocols = [], string $context = 'display' ): string {
			return esc_url_raw( $url );
		}
	}

	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( string $text ): string {
			return htmlspecialchars( $text, ENT_QUOTES );
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( string $text ): string {
			return htmlspecialchars( $text, ENT_QUOTES );
		}
	}

	if ( ! function_exists( 'wp_check_filetype' ) ) {
		function wp_check_filetype( string $filename, array $mimes = null ): array {
			return [ 'ext' => 'svg', 'type' => 'image/svg+xml' ];
		}
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		function wp_handle_upload( array &$file, array $overrides = [], $time = null ): array {
			return [ 'error' => 'wp_handle_upload is disabled in unit tests.' ];
		}
	}

	if ( ! function_exists( 'wp_insert_attachment' ) ) {
		function wp_insert_attachment( array $args, $file = false, $parent = 0, bool $wp_error = false ) {
			static $id = 500;
			return ++$id;
		}
	}

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		function wp_generate_attachment_metadata( int $attachment_id, string $file ): array {
			return [];
		}
	}

	if ( ! function_exists( 'wp_update_attachment_metadata' ) ) {
		function wp_update_attachment_metadata( int $attachment_id, array $data ): bool {
			return true;
		}
	}

	if ( ! function_exists( 'get_attached_file' ) ) {
		function get_attached_file( int $attachment_id, bool $unfiltered = false ): string {
			return '';
		}
	}

	if ( ! function_exists( 'add_filter' ) ) {
		function add_filter( string $tag, $function_to_add, int $priority = 10, int $accepted_args = 1 ): bool {
			return true;
		}
	}

	if ( ! function_exists( 'remove_filter' ) ) {
		function remove_filter( string $tag, $function_to_remove, int $priority = 10 ): bool {
			return true;
		}
	}

	if ( ! function_exists( 'wp_tempnam' ) ) {
		function wp_tempnam( string $filename = '', string $dir = '' ): string {
			return tempnam( sys_get_temp_dir(), 'wp_' );
		}
	}

	if ( ! function_exists( 'do_action' ) ) {
		function do_action( string $tag, ...$args ): void {
			// no-op
		}
	}

	// --- Content-tool stubs (added for WordPress Content tools) ---
	// Note: sanitize_key() and sanitize_title() already defined above; skipped here.

	if ( ! function_exists( 'wp_trash_post' ) ) {
		function wp_trash_post( int $post_id ) {
			$GLOBALS['_wp_trashed_posts'][] = $post_id;
			return (object) array( 'ID' => $post_id );
		}
	}

	if ( ! function_exists( 'wp_set_object_terms' ) ) {
		function wp_set_object_terms( int $object_id, $terms, string $taxonomy, bool $append = false ) {
			$GLOBALS['_wp_term_calls'][] = compact( 'object_id', 'terms', 'taxonomy', 'append' );
			return is_array( $terms ) ? array_map( 'absint', array_values( array_filter( $terms, 'is_numeric' ) ) ) : array();
		}
	}

	if ( ! function_exists( 'set_post_thumbnail' ) ) {
		function set_post_thumbnail( $post, int $thumbnail_id ): bool {
			$GLOBALS['_wp_thumbnail_calls'][] = array( 'post' => is_object( $post ) ? $post->ID : $post, 'thumb' => $thumbnail_id );
			return true;
		}
	}

	if ( ! function_exists( 'delete_post_thumbnail' ) ) {
		function delete_post_thumbnail( $post ): bool {
			$GLOBALS['_wp_thumbnail_calls'][] = array( 'post' => is_object( $post ) ? $post->ID : $post, 'thumb' => 0 );
			return true;
		}
	}

	if ( ! function_exists( 'is_protected_meta' ) ) {
		function is_protected_meta( string $meta_key, string $meta_type = '' ): bool {
			return '_' === substr( $meta_key, 0, 1 );
		}
	}

	if ( ! function_exists( 'get_userdata' ) ) {
		function get_userdata( int $user_id ) {
			if ( isset( $GLOBALS['_wp_users'] ) && array_key_exists( $user_id, $GLOBALS['_wp_users'] ) ) {
				return $GLOBALS['_wp_users'][ $user_id ];
			}
			return (object) array( 'ID' => $user_id, 'display_name' => 'User ' . $user_id );
		}
	}

	if ( ! function_exists( 'get_current_user_id' ) ) {
		function get_current_user_id(): int {
			return $GLOBALS['_wp_current_user_id'] ?? 1;
		}
	}

	if ( ! function_exists( 'post_type_exists' ) ) {
		function post_type_exists( string $post_type ): bool {
			$known = $GLOBALS['_wp_post_types'] ?? array( 'post', 'page' );
			return in_array( $post_type, array_keys( (array) $known ), true ) || in_array( $post_type, (array) $known, true );
		}
	}

	if ( ! function_exists( 'get_post_types' ) ) {
		function get_post_types( array $args = array(), string $output = 'names', string $operator = 'and' ): array {
			$objs = $GLOBALS['_wp_post_type_objects'] ?? array();
			if ( 'objects' === $output ) {
				return $objs;
			}
			return array_keys( $objs );
		}
	}

	if ( ! function_exists( 'get_taxonomies' ) ) {
		function get_taxonomies( array $args = array(), string $output = 'names' ): array {
			$objs = $GLOBALS['_wp_taxonomy_objects'] ?? array();
			return 'objects' === $output ? $objs : array_keys( $objs );
		}
	}

	if ( ! function_exists( 'get_object_taxonomies' ) ) {
		function get_object_taxonomies( $object, string $output = 'names' ): array {
			return array_keys( $GLOBALS['_wp_taxonomy_objects'] ?? array() );
		}
	}

	if ( ! function_exists( 'get_terms' ) ) {
		function get_terms( $args = array() ): array {
			return $GLOBALS['_wp_terms'] ?? array();
		}
	}

	if ( ! function_exists( 'get_the_terms' ) ) {
		function get_the_terms( $post, string $taxonomy ) {
			return $GLOBALS['_wp_post_terms'][ $taxonomy ] ?? array();
		}
	}

	if ( ! function_exists( 'get_term_by' ) ) {
		function get_term_by( string $field, $value, string $taxonomy = '' ) {
			return $GLOBALS['_wp_existing_terms'][ $taxonomy ][ (string) $value ] ?? false;
		}
	}

	if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
		function get_post_thumbnail_id( $post = null ) {
			return $GLOBALS['_wp_post_thumbnail_id'] ?? 0;
		}
	}

	if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
		function wp_get_attachment_image_url( int $id, $size = 'thumbnail' ) {
			return 'http://example.com/img-' . $id . '.jpg';
		}
	}

	if ( ! function_exists( 'wp_remove_object_terms' ) ) {
		function wp_remove_object_terms( int $object_id, $terms, string $taxonomy ) {
			$GLOBALS['_wp_term_calls'][] = array( 'object_id' => $object_id, 'terms' => $terms, 'taxonomy' => $taxonomy, 'remove' => true );
			return true;
		}
	}

	if ( ! class_exists( 'WP_Query' ) ) {
		class WP_Query {
			public $posts = array();
			public $found_posts = 0;
			public $max_num_pages = 0;
			public function __construct( $args = array() ) {
				$set                 = $GLOBALS['_wp_query_result'] ?? array();
				$this->posts         = $set['posts'] ?? array();
				$this->found_posts   = $set['found'] ?? count( $this->posts );
				$per                 = max( 1, (int) ( $args['posts_per_page'] ?? 20 ) );
				$this->max_num_pages = (int) ceil( $this->found_posts / $per );
				$GLOBALS['_wp_query_args'] = $args;
			}
		}
	}

	// -----------------------------------------------------------------------
	// WP_Error stub
	// -----------------------------------------------------------------------

	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			private string $code;
			private string $message;
			private $data;

			public function __construct( string $code = '', string $message = '', $data = '' ) {
				$this->code    = $code;
				$this->message = $message;
				$this->data    = $data;
			}

			public function get_error_code(): string {
				return $this->code;
			}

			public function get_error_message( string $code = '' ): string {
				return $this->message;
			}

			public function get_error_data( string $code = '' ) {
				return $this->data;
			}
		}
	}

}  // end namespace {}

// ---------------------------------------------------------------------------
// Elementor namespace stubs
// ---------------------------------------------------------------------------

namespace Elementor {

	if ( ! class_exists( 'Elementor\\Plugin' ) ) {

		/**
		 * Stub for \Elementor\Plugin.
		 *
		 * The $instance->documents property is a plain stdClass-like object.
		 * Tests replace it per-test with an anonymous class that returns
		 * whatever mock Document the test requires.
		 */
		class Plugin {
			/** @var self */
			public static $instance;

			/** @var object Replaced per-test with a mock documents manager. */
			public $documents;

			/** @var object Stub widgets_manager returning null for all widget types. */
			public $widgets_manager;

			/** @var object Stub dynamic_tags returning empty array. */
			public $dynamic_tags;

			/** @var object Stub kits_manager returning null (no active kit). */
			public $kits_manager;

			/** @var object Stub experiments manager reading $GLOBALS['_active_experiments']. */
			public $experiments;

			/** @var object Stub elements_manager reading $GLOBALS['_registered_element_types']. */
			public $elements_manager;

			private function __construct() {
				$this->experiments = new class {
					/**
					 * Reports an experiment active iff its slug is in
					 * $GLOBALS['_active_experiments'] (set per-test).
					 *
					 * @param string $feature Experiment slug.
					 * @return bool
					 */
					public function is_feature_active( string $feature ): bool {
						return in_array( $feature, $GLOBALS['_active_experiments'] ?? [], true );
					}
				};

				$this->elements_manager = new class {
					/**
					 * Returns a map of registered element types keyed by slug,
					 * driven by $GLOBALS['_registered_element_types'] (set per-test).
					 *
					 * @return array<string, object>
					 */
					public function get_element_types(): array {
						$out = [];
						foreach ( $GLOBALS['_registered_element_types'] ?? [] as $slug ) {
							$out[ $slug ] = new \stdClass();
						}
						return $out;
					}
				};

				$this->documents = new class {
					public function get( int $post_id ) {
						return null;
					}
				};

				$this->widgets_manager = new class {
					/** @return null — widget type not found in tests */
					public function get_widget_types( string $widget_type = '' ) {
						return null;
					}
				};

				$this->dynamic_tags = new class {
					public function get_tags(): array {
						return [];
					}
				};

				$this->kits_manager = new class {
					/** @return null — no active Elementor kit in test env */
					public function get_active_kit() {
						return null;
					}
				};
			}

			public static function getInstance(): self {
				if ( ! isset( static::$instance ) ) {
					static::$instance = new self();
				}
				return static::$instance;
			}

			/** Real Elementor exposes the singleton via instance(); mirror it. */
			public static function instance(): self {
				return static::getInstance();
			}
		}

		Plugin::$instance = Plugin::getInstance();
	}

}  // end namespace Elementor

// ---------------------------------------------------------------------------
// Plugin class autoloader (back in global namespace)
// ---------------------------------------------------------------------------

namespace {

	spl_autoload_register( function ( string $class ): void {
		$plugin_root = dirname( __DIR__ );

		$map = [
			// Core classes
			'EMCP_Tools_Atomic_Props'           => 'includes/class-atomic-props.php',
			'EMCP_Tools_Data'                  => 'includes/class-elementor-data.php',
			'EMCP_Tools_Element_Factory'        => 'includes/class-element-factory.php',
			'EMCP_Tools_Id_Generator'           => 'includes/class-id-generator.php',
			'EMCP_Tools_Openverse_Client'       => 'includes/class-openverse-client.php',
			// Validators / schemas
			'EMCP_Tools_Settings_Validator'     => 'includes/validators/class-settings-validator.php',
			'EMCP_Tools_Schema_Generator'       => 'includes/schemas/class-schema-generator.php',
			'EMCP_Tools_Control_Mapper'         => 'includes/schemas/class-control-mapper.php',
			// SEO / A11y toolkit helpers + abilities
			'EMCP_Tools_Color_Contrast'         => 'includes/class-color-contrast.php',
			'EMCP_Tools_Content_Extractor'      => 'includes/class-content-extractor.php',
			'EMCP_Tools_Seo_Meta'               => 'includes/class-seo-meta.php',
			'EMCP_Tools_Seo_Abilities'          => 'includes/abilities/class-seo-abilities.php',
			'EMCP_Tools_A11y_Abilities'         => 'includes/abilities/class-a11y-abilities.php',
			// Ability classes — all groups
			'EMCP_Tools_Content_Abilities'      => 'includes/abilities/class-content-abilities.php',
			'EMCP_Tools_Custom_Code_Abilities'  => 'includes/abilities/class-custom-code-abilities.php',
			'EMCP_Tools_Stock_Image_Abilities'  => 'includes/abilities/class-stock-image-abilities.php',
			'EMCP_Tools_Composite_Abilities'    => 'includes/abilities/class-composite-abilities.php',
			'EMCP_Tools_Page_Abilities'         => 'includes/abilities/class-page-abilities.php',
			'EMCP_Tools_Svg_Icon_Abilities'     => 'includes/abilities/class-svg-icon-abilities.php',
			'EMCP_Tools_Layout_Abilities'       => 'includes/abilities/class-layout-abilities.php',
			'EMCP_Tools_Query_Abilities'        => 'includes/abilities/class-query-abilities.php',
			'EMCP_Tools_Global_Abilities'       => 'includes/abilities/class-global-abilities.php',
			'EMCP_Tools_Template_Abilities'     => 'includes/abilities/class-template-abilities.php',
			'EMCP_Tools_Widget_Abilities'       => 'includes/abilities/class-widget-abilities.php',
		];

		if ( isset( $map[ $class ] ) ) {
			$path = $plugin_root . '/' . $map[ $class ];
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	} );

}  // end namespace {}
