<?php
/**
 * Agent Skills as a module.
 *
 * Controls the *runtime* exposure of the bundled skills to connected AI agents:
 * the `list-skills` / `get-skill` MCP tools and the `## Skills` catalog injected
 * into the discovery context. Turning it off removes both (and their ~900-token
 * footprint) without touching the local-install path on the Skills tab.
 *
 * Pro tier, on by default. Metadata only — the gating is read statically:
 *   - the ability registrar checks is_enabled() before registering the tools,
 *   - EMCP_Tools_Skill_Catalog::discovery_catalog() checks it before injecting.
 * So this class carries no Pro logic and lives safely in the free tree, like
 * EMCP_Tools_Templates_Module.
 *
 * @package EMCP_Tools
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agent Skills module.
 *
 * @since 3.2.0
 */
class EMCP_Tools_Agent_Skills_Module extends EMCP_Tools_Module {

	public function id(): string {
		return 'agent-skills';
	}

	public function title(): string {
		return __( 'Agent Skills', 'emcp-tools' );
	}

	public function description(): string {
		return __( 'Expose the bundled skills to connected AI agents at runtime, the list-skills / get-skill tools plus a Skills catalog in the discovery context. Turn off to remove that injection (the Skills download on the Skills tab is unaffected).', 'emcp-tools' );
	}

	public function tier(): string {
		return 'pro';
	}

	/** Modules screen group. */
	public function group(): string {
		return 'ai';
	}

	/** Modules screen icon. */
	public function icon(): string {
		return 'sparkles';
	}

	public function default_active(): bool {
		return true;
	}

	/** Requires an active Pro license (the skills are Pro content). */
	public function is_available(): bool {
		return function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code();
	}

	/** The Skills tab is where the skills live (download + guides). */
	public function settings_url(): string {
		return admin_url( 'admin.php?page=' . EMCP_Tools_Admin::PAGE_SLUG . '-skills' );
	}

	/** No overlay knobs — the on/off toggle is the whole control. */
	public function render_settings(): void {}

	/** Nothing to wire on boot — the two consumers gate themselves via is_enabled(). */
	public function register(): void {}

	/**
	 * Whether the module is active (static helper for the ability registrar,
	 * which runs on wp_abilities_api_init, before the module's init:5 boot).
	 * Reads the active-modules option directly.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$active = (array) get_option( EMCP_Tools_Module::OPTION_ACTIVE, array() );
		return in_array( 'agent-skills', $active, true );
	}
}
