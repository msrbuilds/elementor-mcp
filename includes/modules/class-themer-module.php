<?php
/**
 * EMCP Themer as a free module.
 *
 * On by default. register() owns ALL front-end wiring: the CPT, the condition-index
 * rebuild hooks, the render controller, and the metabox. The MCP ability group is
 * gated separately in the ability registrar on the module's active state (abilities
 * register on wp_abilities_api_init, before this init:5 boot). Disabling the module
 * stops the CPT, the front-end takeover, and the tab; the registrar then omits the
 * tools too — a true kill switch.
 *
 * @package EMCP_Tools
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.2.0
 */
class EMCP_Tools_Themer_Module extends EMCP_Tools_Module {

	public function id(): string {
		return 'themer';
	}

	public function title(): string {
		return __( 'Themer', 'emcp-tools' );
	}

	public function description(): string {
		return __( 'Build your site\'s header, footer, single, archive, search & 404 layouts with any page builder, and control where each applies.', 'emcp-tools' );
	}

	public function tier(): string {
		return 'free';
	}

	public function default_active(): bool {
		return true;
	}

	/** The native CPT screen (its own dashboard menu) is the config surface. */
	public function settings_url(): string {
		return admin_url( 'edit.php?post_type=' . EMCP_Tools_Themer_CPT::POST_TYPE );
	}

	public function render_settings(): void {}

	/**
	 * Whether the module is active (static helper for the ability registrar, which
	 * runs before init:5). Reads the active-modules option directly.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$active = (array) get_option( EMCP_Tools_Module::OPTION_ACTIVE, array() );
		return in_array( 'themer', $active, true );
	}

	/** Option marker: the condition index was healed after the save-order fix. */
	const OPTION_INDEX_HEALED = 'emcp_tools_themer_index_healed';

	/** Wire everything. Booted by the registry on init:5 only when active. */
	public function register(): void {
		$cpt = new EMCP_Tools_Themer_CPT();
		$cpt->register();

		EMCP_Tools_Themer_Index::register_hooks();

		// One-time heal: a prior build could leave the condition index empty (the
		// rebuild raced the metabox meta writes), so existing templates silently
		// stopped applying. Rebuild once on upgrade so they resolve again without
		// the admin re-saving each template.
		if ( '1' !== (string) get_option( self::OPTION_INDEX_HEALED, '' ) ) {
			EMCP_Tools_Themer_Index::rebuild();
			update_option( self::OPTION_INDEX_HEALED, '1', true );
		}

		if ( ! is_admin() ) {
			( new EMCP_Tools_Themer_Render_Controller() )->init();
		}

		if ( is_admin() && class_exists( 'EMCP_Tools_Themer_Metabox' ) ) {
			( new EMCP_Tools_Themer_Metabox() )->init();
		}
	}
}
