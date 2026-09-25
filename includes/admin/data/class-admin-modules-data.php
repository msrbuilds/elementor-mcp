<?php
/**
 * Modules screen data (spec 8.4).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_Modules_Data {

	/** The screen payload. */
	public function payload(): array {
		$modules = array();
		foreach ( EMCP_Tools_Modules_Registry::instance()->all() as $module ) {
			$available = $module->is_available();
			$modules[] = array(
				'id'          => $module->id(),
				'title'       => $module->title(),
				'description' => $module->description(),
				'tier'        => $module->tier(),
				'group'       => $module->group(),
				'icon'        => $module->icon(),
				'active'      => $module->is_active(),
				'available'   => $available,
				'reason'      => $available ? '' : $module->unavailable_reason(),
				'settingsUrl' => $module->settings_url(),
				'hasSettings' => array() !== $module->settings_schema(),
			);
		}
		return array(
			'modules'  => $modules,
			'groups'   => array(
				array( 'id' => 'content', 'label' => __( 'Content & design', 'emcp-tools' ) ),
				array( 'id' => 'ai', 'label' => __( 'AI & agents', 'emcp-tools' ) ),
				array( 'id' => 'site', 'label' => __( 'Site & safety', 'emcp-tools' ) ),
			),
			'licensed' => function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code(),
		);
	}

	/**
	 * Apply an activation diff.
	 *
	 * @param string[] $active     Active module ids.
	 * @param string[] $activate   Ids to turn on.
	 * @param string[] $deactivate Ids to turn off.
	 * @param string[] $known      Registered ids.
	 * @param string[] $available  Ids that can be turned on here.
	 * @return array{active: string[], ignored: string[]}
	 */
	public static function apply( array $active, array $activate, array $deactivate, array $known, array $available ): array {
		$ignored = array();
		$out     = array_values( array_intersect( $active, $known ) );
		foreach ( $activate as $id ) {
			if ( in_array( $id, $available, true ) ) {
				$out[] = $id;
			} else {
				$ignored[] = $id;
			}
		}
		foreach ( $deactivate as $id ) {
			if ( in_array( $id, $known, true ) ) {
				$out = array_values( array_diff( $out, array( $id ) ) );
			} else {
				$ignored[] = $id;
			}
		}
		return array(
			'active'  => array_values( array_unique( $out ) ),
			'ignored' => array_values( array_unique( $ignored ) ),
		);
	}

	/**
	 * Drawer form: labelled fields and their typed current values.
	 *
	 * @param EMCP_Tools_Module $module Module.
	 * @return array{fields: array, values: array}
	 */
	public function settings( EMCP_Tools_Module $module ): array {
		$registered = $module->settings_fields();
		$values     = array();
		foreach ( $module->settings_schema() as $field ) {
			$key     = $field['key'];
			$default = $registered[ $key ]['default'] ?? null;
			$raw     = get_option( $key, $default );
			switch ( $field['type'] ) {
				case 'toggle':
					$values[ $key ] = '1' === (string) $raw;
					break;
				case 'range':
				case 'number':
					$values[ $key ] = (int) $raw;
					break;
				case 'checkboxes':
					$values[ $key ] = array_values( array_map( 'strval', (array) $raw ) );
					break;
			}
		}
		return array(
			'fields' => $module->settings_schema(),
			'values' => $values,
		);
	}

	/**
	 * Save drawer values through each field's own sanitizer.
	 *
	 * @param EMCP_Tools_Module $module Module.
	 * @param array             $values Submitted values, key => value.
	 * @return array{saved: string[], ignored: string[]}
	 */
	public function save_settings( EMCP_Tools_Module $module, array $values ): array {
		$registered = $module->settings_fields();
		$schema     = array_column( $module->settings_schema(), null, 'key' );
		$saved      = array();
		$ignored    = array();
		foreach ( $values as $key => $value ) {
			if ( ! isset( $registered[ $key ], $schema[ $key ] ) ) {
				$ignored[] = (string) $key;
				continue;
			}
			$field = $schema[ $key ];
			switch ( $field['type'] ) {
				case 'toggle':
					$value = rest_sanitize_boolean( $value ) ? '1' : '0';
					break;
				case 'range':
				case 'number':
					$value = (int) $value;
					if ( isset( $field['min'] ) ) {
						$value = max( (int) $field['min'], $value );
					}
					if ( isset( $field['max'] ) ) {
						$value = min( (int) $field['max'], $value );
					}
					$value = (string) $value;
					break;
				case 'checkboxes':
					$value = array_values( array_map( 'sanitize_key', array_map( 'strval', array_filter( (array) $value, 'is_scalar' ) ) ) );
					break;
			}
			$sanitize = $registered[ $key ]['sanitize_callback'] ?? null;
			update_option( $key, is_callable( $sanitize ) ? call_user_func( $sanitize, $value ) : $value );
			$saved[] = (string) $key;
		}
		return array(
			'saved'   => $saved,
			'ignored' => $ignored,
		);
	}
}
