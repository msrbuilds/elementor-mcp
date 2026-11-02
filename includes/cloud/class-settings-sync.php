<?php
/**
 * Turnkey EMCP settings sync.
 *
 * Collect a curated allowlist of EMCP's own settings, push them to EMCP Cloud,
 * and pull + apply them on another connected site. Paid-Cloud gated
 * (`syncSettings` entitlement). Never touches secrets or site-specific keys.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Settings_Sync {

	/** Fixed v1 managed profile surface. Deliberately separate from the legacy filterable blob. */
	public static function managed_snapshot() {
		if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'forbidden', 'Administrator access required.' ); }
		if ( EMCP_Tools_Cloud::identity_conflict() ) { return new WP_Error( 'site_identity_conflict', 'Separate this installation before collecting settings.' ); }
		$boolean_keys = array(
			'emcp_tools_dispatcher_mode', 'emcp_tools_strict_schemas', 'emcp_tools_content_mirror_enabled',
			'emcp_tools_site_context_enabled', 'emcp_tools_module_themer_force_render',
			'emcp_tools_memory_require_approval', 'emcp_tools_memory_auto_summarize',
		);
		$list_keys = array( 'emcp_tools_disabled_tools', 'emcp_tools_active_modules' );
		$settings = array();
		$missing = new stdClass();
		foreach ( array_merge( $boolean_keys, $list_keys ) as $key ) {
			$value = get_option( $key, $missing );
			if ( $value === $missing ) { continue; }
			if ( in_array( $key, $boolean_keys, true ) ) {
				// WordPress returns a persisted false option as an empty string.
				if ( ! in_array( $value, array( true, false, 0, 1, '', '0', '1' ), true ) ) {
					return new WP_Error( 'invalid_managed_setting', 'A managed setting has an unsupported value.' );
				}
				$settings[ $key ] = in_array( $value, array( true, 1, '1' ), true );
			} else {
				if ( ! is_array( $value ) || count( $value ) > 5000 || array_values( $value ) !== $value ) {
					return new WP_Error( 'invalid_managed_setting', 'A managed setting has an unsupported value.' );
				}
				foreach ( $value as $item ) {
					if ( ! is_string( $item ) || strlen( $item ) > 120 || ! preg_match( '~^[a-z][a-z0-9_-]*(/[a-z][a-z0-9_-]*)?$~D', $item ) ) {
						return new WP_Error( 'invalid_managed_setting', 'A managed setting has an unsupported value.' );
					}
				}
				$value = array_values( array_unique( $value ) ); sort( $value, SORT_STRING );
				$settings[ $key ] = $value;
			}
		}
		ksort( $settings, SORT_STRING );
		return array( 'schema_version' => 1, 'plugin_version' => EMCP_TOOLS_VERSION,
			'site_uuid' => (string) get_option( EMCP_Tools_Cloud::OPTION_SITE_UUID, '' ),
			'settings' => (object) $settings );
	}

	/**
	 * Curated, filterable allowlist of syncable settings. NEVER secrets, tokens,
	 * the cloud connection, the per-site UUID, audit logs, or notice state.
	 *
	 * @return string[]
	 */
	public static function sync_keys() {
		$keys = array(
			'emcp_tools_disabled_tools',            // per-tool toggle grid
			'elementor_mcp_disabled_tools',         // legacy grid key
			'emcp_tools_active_modules',            // module on/off
			'emcp_tools_dispatcher_mode',           // compact tool mode
			'emcp_tools_auto_enable_new_tools',     // leave new tools on after updates
			'emcp_tools_strict_schemas',            // behavior pref
			'emcp_tools_content_mirror_enabled',    // content mirror opt-in
			'emcp_tools_site_context',              // extra instructions (free-text context)
			'emcp_tools_site_context_enabled',      // context delivery on/off
			'emcp_tools_context_sections',          // which context sections agents receive
			// Not emcp_tools_site_profile: it describes one site, so it stays per site.
			'emcp_tools_module_themer_force_render', // themer render pref
			'emcp_tools_memory_require_approval',   // memory prefs
			'emcp_tools_memory_auto_summarize',
		);
		// Access policy is always site-local, even if a legacy extension requests it.
		return array_values( array_diff( array_unique( (array) apply_filters( 'emcp_tools_settings_sync_keys', $keys ) ), array( 'emcp_tools_management_policy' ) ) );
	}

	/** Reserved blob key describing the push; never written as an option. */
	const META_KEY = '_meta';

	/**
	 * { key => value } for allowlisted keys that are actually set, plus a
	 * `_meta` entry naming the source site and its tool defaults version.
	 *
	 * @return array
	 */
	public static function collect() {
		$out      = array();
		$sentinel = '__emcp_absent__';
		foreach ( self::sync_keys() as $key ) {
			$val = get_option( $key, $sentinel );
			if ( $sentinel !== $val ) {
				$out[ $key ] = $val;
			}
		}
		$out[ self::META_KEY ] = array(
			'site'             => home_url(),
			'pushed_at'        => gmdate( 'c' ),
			'plugin_version'   => EMCP_TOOLS_VERSION,
			'defaults_version' => self::defaults_version(),
		);
		return $out;
	}

	/**
	 * Write only allowlisted keys from $data (defends against a tampered blob).
	 *
	 * Tools that this site's later defaults ship disabled stay disabled when the
	 * blob was pushed from a site with older defaults: a copied list cannot
	 * name tools its source did not have yet (unless this site turns new tools
	 * on after updates).
	 *
	 * @param array $data Incoming { key => value }.
	 * @return int Count of keys applied.
	 */
	public static function apply( array $data ) {
		$allow = array_flip( self::sync_keys() );
		$meta  = isset( $data[ self::META_KEY ] ) && is_array( $data[ self::META_KEY ] ) ? $data[ self::META_KEY ] : array();
		if ( isset( $data['emcp_tools_disabled_tools'] ) && is_array( $data['emcp_tools_disabled_tools'] ) ) {
			$data['emcp_tools_disabled_tools'] = self::with_newer_defaults( $data['emcp_tools_disabled_tools'], $meta );
		}
		$n      = 0;
		$before = array();
		foreach ( $data as $key => $val ) {
			if ( isset( $allow[ $key ] ) ) {
				$before[ $key ] = get_option( $key, '__ABSENT__' );
				update_option( $key, $val );
				$n++;
			}
		}
		// A pull replaces this site's settings: record it so History can undo it.
		if ( $n > 0 && class_exists( 'EMCP_Tools_Change_Recorder' ) ) {
			$source = isset( $meta['site'] ) ? esc_url_raw( (string) $meta['site'] ) : '';
			EMCP_Tools_Change_Recorder::record_options(
				$before,
				/* translators: %d: number of settings. */
				sprintf( _n( 'Pulled %d EMCP setting from EMCP Cloud', 'Pulled %d EMCP settings from EMCP Cloud', $n, 'emcp-tools' ), $n ),
				'' !== $source ? $source : 'EMCP Cloud',
				'settings',
				'settings-pull'
			);
		}
		return $n;
	}

	/**
	 * Add the tools this site's newer defaults disable to a pulled disabled list.
	 *
	 * @param array $disabled Pulled disabled tool slugs.
	 * @param array $meta     The blob's `_meta` (empty for a pre-3.21.2 push).
	 * @return array
	 */
	private static function with_newer_defaults( array $disabled, array $meta ) {
		$source = isset( $meta['defaults_version'] ) ? (int) $meta['defaults_version'] : 0;
		$local  = self::defaults_version();
		// A push without _meta has no known defaults version: keep it as sent.
		if ( $source < 1 || $source >= $local || '1' === (string) get_option( 'emcp_tools_auto_enable_new_tools', '0' ) ) {
			return $disabled;
		}
		$admin   = new EMCP_Tools_Admin();
		$changes = $admin->default_disabled_changes( $source );
		$merged  = array_diff( $disabled, $changes['strip'] );
		return array_values( array_unique( array_merge( $merged, $changes['add'] ) ) );
	}

	/**
	 * This build's tool defaults version (0 when the admin classes are absent).
	 *
	 * @return int
	 */
	private static function defaults_version() {
		if ( ! class_exists( 'EMCP_Tools_Admin' ) && class_exists( 'EMCP_Tools_Bootstrap' ) && method_exists( 'EMCP_Tools_Bootstrap', 'require_admin_classes' ) ) {
			EMCP_Tools_Bootstrap::require_admin_classes();
		}
		return class_exists( 'EMCP_Tools_Admin' ) ? (int) EMCP_Tools_Admin::DEFAULTS_VERSION : 0;
	}

	/**
	 * Paid-Cloud gate: the connected account's `syncSettings` entitlement.
	 *
	 * @return bool
	 */
	public static function entitled() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		if ( ! class_exists( 'EMCP_Tools_Cloud_Sync' ) ) {
			return $cache = false;
		}
		$status = EMCP_Tools_Cloud_Sync::status();
		$cache  = is_array( $status ) && ! empty( $status['limits']['syncSettings'] );
		return $cache;
	}

	/**
	 * Collect + push the settings blob to the cloud.
	 *
	 * @return array|\WP_Error { pushed: int } on success.
	 */
	public static function push() {
		$gate = self::gate();
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		// Settings are shared by every site in the workspace. Site-scoped storage
		// makes a push visible only to the site that created it.
		$data = self::collect();
		$res  = EMCP_Tools_Cloud_Sync::push_config( 'settings', $data );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array( 'pushed' => count( $data ) - 1 );
	}

	/**
	 * Pull the settings blob from the cloud and apply it.
	 *
	 * @return array|\WP_Error { applied: int, source: array } on success.
	 */
	public static function pull_and_apply() {
		$gate = self::gate();
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		return self::apply_pulled( EMCP_Tools_Cloud_Sync::pull_config( 'settings' ) );
	}

	/**
	 * Apply a Cloud answer to a settings pull.
	 *
	 * @param array|\WP_Error $res The pull_config() result.
	 * @return array|\WP_Error { applied: int, source: array } on success.
	 */
	public static function apply_pulled( $res ) {
		if ( is_wp_error( $res ) ) {
			// The Cloud answers 404 until a site in this workspace pushes.
			if ( 'cloud_http_404' === $res->get_error_code() ) {
				return new \WP_Error( 'nothing_pushed', __( 'No settings have been pushed to EMCP Cloud yet. Push from the site whose settings you want to copy, then pull here.', 'emcp-tools' ) );
			}
			return $res;
		}
		$data = isset( $res['data'] ) && is_string( $res['data'] ) ? json_decode( $res['data'], true ) : null;
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'invalid_blob', __( 'The settings stored in EMCP Cloud could not be read. Push them again from the source site.', 'emcp-tools' ) );
		}
		$applied = self::apply( $data );
		if ( 0 === $applied ) {
			return new \WP_Error( 'nothing_applied', __( 'The settings stored in EMCP Cloud contain nothing this site can apply. Push them again from the source site.', 'emcp-tools' ) );
		}
		$meta = isset( $data[ self::META_KEY ] ) && is_array( $data[ self::META_KEY ] ) ? $data[ self::META_KEY ] : array();
		return array(
			'applied' => $applied,
			'source'  => array(
				'site'      => isset( $meta['site'] ) ? esc_url_raw( (string) $meta['site'] ) : '',
				'pushed_at' => isset( $meta['pushed_at'] ) ? sanitize_text_field( (string) $meta['pushed_at'] ) : '',
			),
		);
	}

	/**
	 * Connection + entitlement precondition shared by push/pull.
	 *
	 * @return true|\WP_Error
	 */
	private static function gate() {
		if ( ! class_exists( 'EMCP_Tools_Cloud' ) || ! EMCP_Tools_Cloud::is_connected() ) {
			return new \WP_Error( 'not_connected', __( 'Connect to EMCP Cloud first.', 'emcp-tools' ) );
		}
		if ( ! self::entitled() ) {
			return new \WP_Error( 'not_entitled', __( 'Settings sync requires a paid EMCP Cloud plan.', 'emcp-tools' ) );
		}
		return true;
	}
}
