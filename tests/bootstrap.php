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
 * @package Elementor_MCP\Tests
 */

// ---------------------------------------------------------------------------
// Global constants and WordPress / plugin function stubs
// ---------------------------------------------------------------------------

namespace {

	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	define( 'ELEMENTOR_VERSION', '3.25.0' );
	define( 'ELEMENTOR_MCP_VERSION', '1.5.0' );
	define( 'ELEMENTOR_MCP_DIR', dirname( __DIR__ ) . '/' );

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
	// Use helper Elementor_MCP_Test_Caps::allow(), ::deny(), ::none(), ::all()
	// -----------------------------------------------------------------------
	$GLOBALS['_caps'] = null;

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
			return null;
		}
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

			private function __construct() {
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
			'Elementor_MCP_Data'                  => 'includes/class-elementor-data.php',
			'Elementor_MCP_Element_Factory'        => 'includes/class-element-factory.php',
			'Elementor_MCP_Id_Generator'           => 'includes/class-id-generator.php',
			'Elementor_MCP_Openverse_Client'       => 'includes/class-openverse-client.php',
			// Validators / schemas
			'Elementor_MCP_Settings_Validator'     => 'includes/validators/class-settings-validator.php',
			'Elementor_MCP_Schema_Generator'       => 'includes/schemas/class-schema-generator.php',
			'Elementor_MCP_Control_Mapper'         => 'includes/schemas/class-control-mapper.php',
			// Ability classes — all groups
			'Elementor_MCP_Custom_Code_Abilities'  => 'includes/abilities/class-custom-code-abilities.php',
			'Elementor_MCP_Stock_Image_Abilities'  => 'includes/abilities/class-stock-image-abilities.php',
			'Elementor_MCP_Composite_Abilities'    => 'includes/abilities/class-composite-abilities.php',
			'Elementor_MCP_Page_Abilities'         => 'includes/abilities/class-page-abilities.php',
			'Elementor_MCP_Svg_Icon_Abilities'     => 'includes/abilities/class-svg-icon-abilities.php',
			'Elementor_MCP_Layout_Abilities'       => 'includes/abilities/class-layout-abilities.php',
			'Elementor_MCP_Query_Abilities'        => 'includes/abilities/class-query-abilities.php',
			'Elementor_MCP_Global_Abilities'       => 'includes/abilities/class-global-abilities.php',
			'Elementor_MCP_Template_Abilities'     => 'includes/abilities/class-template-abilities.php',
			'Elementor_MCP_Widget_Abilities'       => 'includes/abilities/class-widget-abilities.php',
		];

		if ( isset( $map[ $class ] ) ) {
			$path = $plugin_root . '/' . $map[ $class ];
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	} );

}  // end namespace {}
