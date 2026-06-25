<?php
/**
 * WordPress site-settings MCP abilities.
 *
 * Two tools — get-settings (read + discovery) and update-settings (batch write) —
 * over a CURATED, TYPED ALLOWLIST of core WordPress settings (the Settings →
 * General/Reading/Writing/Discussion/Media/Permalinks screens). Arbitrary
 * get_option/update_option over any key is deliberately NOT exposed: only keys
 * in self::allowlist() are ever read or written. siteurl/home (lock-out),
 * users_can_register/default_role (registration escalation) are absent;
 * admin_email is read-only. Both tools require manage_options.
 *
 * Naming: distinct from EMCP_Tools_Settings_Validator (which validates Elementor
 * widget settings) — unrelated class, no collision.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the WordPress settings abilities.
 *
 * @since 3.0.0
 */
class EMCP_Tools_Settings_Abilities {

	/**
	 * Names of the abilities actually registered by register().
	 *
	 * @since 3.0.0
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * Returns the names of all abilities registered by this group.
	 *
	 * @since 3.0.0
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/**
	 * Registers this group's MCP abilities.
	 *
	 * @since 3.0.0
	 */
	public function register(): void {
		$this->register_get_settings();
		$this->register_update_settings();
	}

	/**
	 * Permission gate: both tools require manage_options.
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function check_manage_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ---------------------------------------------------------------------
	// Allowlist (source of truth)
	// ---------------------------------------------------------------------

	/**
	 * The typed allowlist: option name => metadata.
	 *
	 * type: string|int|bool|enum. writable:false → read-only. options: enum
	 * members. min/max: int clamp range. group: the Settings screen it belongs to.
	 *
	 * @since 3.0.0
	 * @return array<string,array>
	 */
	private static function allowlist(): array {
		return array(
			// General.
			'blogname'                      => array( 'group' => 'general', 'label' => 'Site Title', 'type' => 'string', 'writable' => true ),
			'blogdescription'               => array( 'group' => 'general', 'label' => 'Tagline', 'type' => 'string', 'writable' => true ),
			'admin_email'                   => array( 'group' => 'general', 'label' => 'Administration Email', 'type' => 'string', 'writable' => false ),
			'timezone_string'               => array( 'group' => 'general', 'label' => 'Timezone', 'type' => 'string', 'writable' => true ),
			'gmt_offset'                    => array( 'group' => 'general', 'label' => 'GMT Offset', 'type' => 'string', 'writable' => true ),
			'date_format'                   => array( 'group' => 'general', 'label' => 'Date Format', 'type' => 'string', 'writable' => true ),
			'time_format'                   => array( 'group' => 'general', 'label' => 'Time Format', 'type' => 'string', 'writable' => true ),
			'start_of_week'                 => array( 'group' => 'general', 'label' => 'Week Starts On', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 6 ),
			'WPLANG'                        => array( 'group' => 'general', 'label' => 'Site Language', 'type' => 'string', 'writable' => true ),
			// Reading.
			'show_on_front'                 => array( 'group' => 'reading', 'label' => 'Front Page Displays', 'type' => 'enum', 'writable' => true, 'options' => array( 'posts', 'page' ) ),
			'page_on_front'                 => array( 'group' => 'reading', 'label' => 'Front Page', 'type' => 'int', 'writable' => true, 'min' => 0 ),
			'page_for_posts'                => array( 'group' => 'reading', 'label' => 'Posts Page', 'type' => 'int', 'writable' => true, 'min' => 0 ),
			'posts_per_page'                => array( 'group' => 'reading', 'label' => 'Blog Pages Show At Most', 'type' => 'int', 'writable' => true, 'min' => 1, 'max' => 100 ),
			'posts_per_rss'                 => array( 'group' => 'reading', 'label' => 'Syndication Feeds Show', 'type' => 'int', 'writable' => true, 'min' => 1, 'max' => 100 ),
			'rss_use_excerpt'               => array( 'group' => 'reading', 'label' => 'Feed Shows Summary', 'type' => 'bool', 'writable' => true ),
			'blog_public'                   => array( 'group' => 'reading', 'label' => 'Search Engine Visibility', 'type' => 'bool', 'writable' => true ),
			// Writing.
			'default_category'              => array( 'group' => 'writing', 'label' => 'Default Post Category', 'type' => 'int', 'writable' => true, 'min' => 0 ),
			'default_post_format'           => array( 'group' => 'writing', 'label' => 'Default Post Format', 'type' => 'enum', 'writable' => true, 'options' => array( '0', 'aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio', 'chat' ) ),
			// Discussion.
			'default_comment_status'        => array( 'group' => 'discussion', 'label' => 'Allow Comments By Default', 'type' => 'enum', 'writable' => true, 'options' => array( 'open', 'closed' ) ),
			'default_ping_status'           => array( 'group' => 'discussion', 'label' => 'Allow Pingbacks By Default', 'type' => 'enum', 'writable' => true, 'options' => array( 'open', 'closed' ) ),
			'comment_registration'          => array( 'group' => 'discussion', 'label' => 'Users Must Register To Comment', 'type' => 'bool', 'writable' => true ),
			'require_name_email'            => array( 'group' => 'discussion', 'label' => 'Comment Author Must Fill Name/Email', 'type' => 'bool', 'writable' => true ),
			'comment_moderation'            => array( 'group' => 'discussion', 'label' => 'Hold Comments For Moderation', 'type' => 'bool', 'writable' => true ),
			'comments_per_page'             => array( 'group' => 'discussion', 'label' => 'Comments Per Page', 'type' => 'int', 'writable' => true, 'min' => 1, 'max' => 200 ),
			'thread_comments'               => array( 'group' => 'discussion', 'label' => 'Enable Threaded Comments', 'type' => 'bool', 'writable' => true ),
			'close_comments_for_old_posts'  => array( 'group' => 'discussion', 'label' => 'Auto-Close Comments On Old Posts', 'type' => 'bool', 'writable' => true ),
			// Media.
			'thumbnail_size_w'              => array( 'group' => 'media', 'label' => 'Thumbnail Width', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 9999 ),
			'thumbnail_size_h'              => array( 'group' => 'media', 'label' => 'Thumbnail Height', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 9999 ),
			'medium_size_w'                 => array( 'group' => 'media', 'label' => 'Medium Width', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 9999 ),
			'medium_size_h'                 => array( 'group' => 'media', 'label' => 'Medium Height', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 9999 ),
			'large_size_w'                  => array( 'group' => 'media', 'label' => 'Large Width', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 9999 ),
			'large_size_h'                  => array( 'group' => 'media', 'label' => 'Large Height', 'type' => 'int', 'writable' => true, 'min' => 0, 'max' => 9999 ),
			'uploads_use_yearmonth_folders' => array( 'group' => 'media', 'label' => 'Organize Uploads Into Month/Year Folders', 'type' => 'bool', 'writable' => true ),
			// Permalinks.
			'permalink_structure'           => array( 'group' => 'permalinks', 'label' => 'Permalink Structure', 'type' => 'string', 'writable' => true ),
			'category_base'                 => array( 'group' => 'permalinks', 'label' => 'Category Base', 'type' => 'string', 'writable' => true ),
			'tag_base'                      => array( 'group' => 'permalinks', 'label' => 'Tag Base', 'type' => 'string', 'writable' => true ),
		);
	}

	/** Valid group names (the Settings screens). */
	private static function groups(): array {
		return array( 'general', 'reading', 'writing', 'discussion', 'media', 'permalinks' );
	}

	/** Whether a key belongs to the permalinks group. */
	private function is_permalink_key( string $key ): bool {
		$map = self::allowlist();
		return isset( $map[ $key ] ) && 'permalinks' === $map[ $key ]['group'];
	}

	// ---------------------------------------------------------------------
	// get-settings
	// ---------------------------------------------------------------------

	private function register_get_settings(): void {
		$this->ability_names[] = 'emcp-tools/get-settings';
		emcp_tools_register_ability(
			'emcp-tools/get-settings',
			array(
				'label'               => __( 'Get Settings', 'emcp-tools' ),
				'description'         => __( 'Reads curated WordPress site settings (General, Reading, Writing, Discussion, Media, Permalinks). With no args returns every allowlisted setting; pass "group" to filter to one screen or "keys" for specific settings. Each row carries the value plus metadata (type, label, writable, enum options) so this doubles as discovery for update-settings.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_settings' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'group' => array( 'type' => 'string', 'enum' => self::groups(), 'description' => __( 'Filter to one Settings screen.', 'emcp-tools' ) ),
						'keys'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => __( 'Return only these allowlisted keys.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	// ---------------------------------------------------------------------
	// Execute: get-settings
	// ---------------------------------------------------------------------

	/**
	 * @param array $input
	 * @return array
	 */
	public function execute_get_settings( $input ): array {
		$map   = self::allowlist();
		$group = isset( $input['group'] ) ? sanitize_key( (string) $input['group'] ) : '';
		$keys  = ( isset( $input['keys'] ) && is_array( $input['keys'] ) )
			? array_map( 'strval', $input['keys'] )
			: array();

		$rows = array();
		foreach ( $map as $key => $entry ) {
			if ( '' !== $group && $entry['group'] !== $group ) {
				continue;
			}
			if ( ! empty( $keys ) && ! in_array( $key, $keys, true ) ) {
				continue;
			}
			$row = array(
				'key'      => $key,
				'group'    => $entry['group'],
				'label'    => $entry['label'],
				'type'     => $entry['type'],
				'value'    => $this->read_setting( $key, $entry ),
				'writable' => ! empty( $entry['writable'] ),
			);
			if ( 'enum' === $entry['type'] && ! empty( $entry['options'] ) ) {
				$row['options'] = array_values( $entry['options'] );
			}
			$rows[] = $row;
		}
		return array( 'settings' => $rows );
	}

	/**
	 * Read an option and coerce it to the declared type for clean JSON.
	 *
	 * @param string $key
	 * @param array  $entry Allowlist entry.
	 * @return mixed
	 */
	private function read_setting( string $key, array $entry ) {
		$raw = get_option( $key );
		switch ( $entry['type'] ) {
			case 'int':
				return (int) $raw;
			case 'bool':
				return ! empty( $raw ) && '0' !== $raw;
			case 'enum':
			case 'string':
			default:
				return null === $raw ? '' : (string) $raw;
		}
	}

	// ---------------------------------------------------------------------
	// Execute: update-settings
	// ---------------------------------------------------------------------

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_update_settings( $input ) {
		$settings = ( isset( $input['settings'] ) && is_array( $input['settings'] ) ) ? $input['settings'] : array();
		if ( empty( $settings ) ) {
			return new \WP_Error( 'missing_params', __( 'A non-empty "settings" map is required.', 'emcp-tools' ) );
		}

		$map              = self::allowlist();
		$updated          = array();
		$skipped          = array();
		$permalink_change = false;

		foreach ( $settings as $key => $value ) {
			$key = (string) $key;
			if ( ! isset( $map[ $key ] ) ) {
				$skipped[] = array( 'key' => $key, 'reason' => 'not an allowlisted setting' );
				continue;
			}
			$entry = $map[ $key ];
			if ( empty( $entry['writable'] ) ) {
				$skipped[] = array( 'key' => $key, 'reason' => 'read-only' );
				continue;
			}
			$coerced = $this->coerce_write( $entry, $value );
			if ( is_wp_error( $coerced ) ) {
				$skipped[] = array( 'key' => $key, 'reason' => $coerced->get_error_message() );
				continue;
			}
			$stored = $coerced['store'];
			update_option( $key, $stored );
			$updated[ $key ] = $coerced['report'];
			if ( $this->is_permalink_key( $key ) ) {
				$permalink_change = true;
			}
		}

		// flush_rewrite_rules() lives in wp-includes/rewrite.php and is loaded on
		// every request (REST/WP-CLI included), so no on-demand require is needed —
		// the function_exists guard is belt-and-suspenders for the unit harness.
		$rewrite_flushed = false;
		if ( $permalink_change && function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
			$rewrite_flushed = true;
		}

		return array(
			'updated'         => $updated,
			'skipped'         => $skipped,
			'rewrite_flushed' => $rewrite_flushed,
		);
	}

	/**
	 * Coerce + validate a write value against its allowlist entry.
	 *
	 * Returns [ 'store' => <value for update_option>, 'report' => <clean JSON value> ]
	 * or a WP_Error whose message becomes the skip reason.
	 *
	 * @param array $entry
	 * @param mixed $value
	 * @return array|\WP_Error
	 */
	private function coerce_write( array $entry, $value ) {
		switch ( $entry['type'] ) {
			case 'int':
				if ( ! is_numeric( $value ) ) {
					return new \WP_Error( 'invalid', 'invalid value for int' );
				}
				$n = (int) $value;
				if ( isset( $entry['min'] ) ) {
					$n = max( (int) $entry['min'], $n );
				}
				if ( isset( $entry['max'] ) ) {
					$n = min( (int) $entry['max'], $n );
				}
				return array( 'store' => $n, 'report' => $n );

			case 'bool':
				$b = (bool) $value;
				if ( is_string( $value ) ) {
					$b = ! in_array( strtolower( $value ), array( '', '0', 'false', 'no', 'off' ), true );
				}
				return array( 'store' => $b ? '1' : '', 'report' => $b );

			case 'enum':
				$v = (string) $value;
				if ( ! in_array( $v, (array) ( $entry['options'] ?? array() ), true ) ) {
					return new \WP_Error( 'invalid', 'invalid value for enum' );
				}
				return array( 'store' => $v, 'report' => $v );

			case 'string':
			default:
				$s = sanitize_text_field( (string) $value );
				return array( 'store' => $s, 'report' => $s );
		}
	}

	// ---------------------------------------------------------------------
	// update-settings (registration)
	// ---------------------------------------------------------------------

	private function register_update_settings(): void {
		$this->ability_names[] = 'emcp-tools/update-settings';
		emcp_tools_register_ability(
			'emcp-tools/update-settings',
			array(
				'label'               => __( 'Update Settings', 'emcp-tools' ),
				'description'         => __( 'Updates curated WordPress site settings from a map of key → value. Only allowlisted, writable keys are changed; non-allowlisted, read-only (admin_email), or invalid values are returned in "skipped" with a reason — one bad key never aborts the batch. Changing a permalink setting (permalink_structure, category_base, tag_base) flushes rewrite rules automatically.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_settings' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array( 'type' => 'object', 'description' => __( 'Map of allowlisted setting key → new value.', 'emcp-tools' ) ),
					),
					'required'   => array( 'settings' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'updated'         => array( 'type' => 'object' ),
						'skipped'         => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
						'rewrite_flushed' => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}
}
