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

	/**
	 * { key => value } for allowlisted keys that are actually set.
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
		return $out;
	}

	/**
	 * Write only allowlisted keys from $data (defends against a tampered blob).
	 *
	 * @param array $data Incoming { key => value }.
	 * @return int Count of keys applied.
	 */
	public static function apply( array $data ) {
		$allow = array_flip( self::sync_keys() );
		$n     = 0;
		foreach ( $data as $key => $val ) {
			if ( isset( $allow[ $key ] ) ) {
				update_option( $key, $val );
				$n++;
			}
		}
		return $n;
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
	 * @return array|\WP_Error
	 */
	public static function push() {
		$gate = self::gate();
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		// Settings are shared by every site in the workspace. Site-scoped storage
		// makes a push visible only to the site that created it.
		return EMCP_Tools_Cloud_Sync::push_config( 'settings', self::collect() );
	}

	/**
	 * Pull the settings blob from the cloud and apply it.
	 *
	 * @return array|\WP_Error
	 */
	public static function pull_and_apply() {
		$gate = self::gate();
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$res = EMCP_Tools_Cloud_Sync::pull_config( 'settings' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data    = isset( $res['data'] ) ? json_decode( (string) $res['data'], true ) : array();
		$applied = is_array( $data ) ? self::apply( $data ) : 0;
		return array( 'applied' => $applied );
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
