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
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.1.0
 */
class EMCP_Tools_Themer_Module extends EMCP_Tools_Module {

	public function id(): string {
		return 'themer';
	}

	public function title(): string {
		return __( 'Themer', 'emcp-tools' );
	}

	public function description(): string {
		return __( 'Build your site\'s header, footer, single, archive, search & 404 layouts and Loop Items (post cards for the Loop Grid and Loop Carousel) with any page builder, and control where each applies.', 'emcp-tools' );
	}

	public function tier(): string {
		return 'free';
	}

	/**
	 * Off on new installs: an admin turns Themer on from the Modules tab. The
	 * registry seeds each module once, so a site that already has Themer on
	 * keeps it.
	 */
	public function default_active(): bool {
		return false;
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

		// Header Footer Elementor builds the same header/footer slots. Warn the
		// admin and, until they pick one system, let Themer win deterministically.
		if ( class_exists( 'EMCP_Tools_Themer_HFE_Conflict' ) ) {
			EMCP_Tools_Themer_HFE_Conflict::init();
		}

		// BeTheme renders page content with BeBuilder, which a Themer body
		// template replaces wholesale. Nothing errors, the content is just
		// absent, so the admin is told rather than left to debug a blank page.
		if ( class_exists( 'EMCP_Tools_Themer_BeTheme_Conflict' ) ) {
			EMCP_Tools_Themer_BeTheme_Conflict::init();
		}

		EMCP_Tools_Themer_Index::register_hooks();

		// Core post blocks inside a Loop Item read the card's post from here.
		if ( class_exists( 'EMCP_Tools_Themer_Loop_Context' ) ) {
			EMCP_Tools_Themer_Loop_Context::init();
		}

		if ( class_exists( 'EMCP_Tools_Themer_Loop_Assets' ) ) {
			EMCP_Tools_Themer_Loop_Assets::init();
		}
		if ( class_exists( 'EMCP_Tools_Themer_Loop_REST' ) ) {
			EMCP_Tools_Themer_Loop_REST::init();
		}
		// The option lists both loop builders offer, memoised per request.
		if ( class_exists( 'EMCP_Tools_Themer_Loop_Options' ) ) {
			EMCP_Tools_Themer_Loop_Options::init();
		}

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

		// Dynamic content blocks (Gutenberg) — register on both front end (render)
		// and admin (editor). Elementor dynamic widgets self-gate on Elementor.
		if ( class_exists( 'EMCP_Tools_Themer_Blocks' ) ) {
			( new EMCP_Tools_Themer_Blocks() )->init();
		}
		if ( class_exists( 'EMCP_Tools_Themer_Widgets' ) ) {
			( new EMCP_Tools_Themer_Widgets() )->init();
		}
		// Dynamic tags let ANY Elementor widget bind a field to these sources,
		// not just the Themer widgets above.
		if ( class_exists( 'EMCP_Tools_Themer_Elementor_Tags' ) ) {
			EMCP_Tools_Themer_Elementor_Tags::init();
		}
		// Gutenberg counterpart: core blocks bind to the same sources.
		if ( class_exists( 'EMCP_Tools_Themer_Block_Bindings' ) ) {
			EMCP_Tools_Themer_Block_Bindings::init();
		}
		if ( class_exists( 'EMCP_Tools_Themer_PHP' ) ) {
			( new EMCP_Tools_Themer_PHP() )->init();
		}
	}
}
