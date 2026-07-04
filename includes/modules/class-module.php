<?php
/**
 * Base class for EMCP Tools modules.
 *
 * A module is a substantial, self-contained feature an admin turns on/off from
 * the Modules tab. Active module IDs live in the single `emcp_tools_active_modules`
 * option; each module owns its own `emcp_tools_module_<id>_*` option keys.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract module.
 *
 * @since 3.1.0
 */
abstract class EMCP_Tools_Module {

	/** Option holding the array of active module IDs. */
	const OPTION_ACTIVE = 'emcp_tools_active_modules';

	/** Stable module id (a-z0-9-). Used as the option-key infix and toggle value. */
	abstract public function id(): string;

	/** Human-readable title for the Modules card. */
	abstract public function title(): string;

	/** One-line description for the Modules card. */
	abstract public function description(): string;

	/** 'free' | 'pro' — drives the tier badge. */
	abstract public function tier(): string;

	/** Whether the module is on by default (seeded once via the defaults marker). */
	abstract public function default_active(): bool;

	/** Wire the module's hooks. Called by the registry only when active + available. */
	abstract public function register(): void;

	/** Render the module's settings/knobs inside its card (shown when active). */
	abstract public function render_settings(): void;

	/**
	 * Dependency/capability probe. Override to gate on server features.
	 *
	 * @return bool True when the module can run on this site.
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Option keys this module owns, for sanitized registration on the Modules
	 * settings group. Each entry: key => [ 'type' => ..., 'default' => ...,
	 * 'sanitize_callback' => callable ].
	 *
	 * @return array<string,array>
	 */
	public function settings_fields(): array {
		return array();
	}

	/** True when this module's id is in the active-modules option. */
	public function is_active(): bool {
		$active = get_option( self::OPTION_ACTIVE, array() );
		return is_array( $active ) && in_array( $this->id(), $active, true );
	}

	/**
	 * Settings-API group for this module's own option keys. Each module gets a
	 * dedicated group so its settings form saves independently of the active-
	 * modules toggles and of other modules' forms.
	 *
	 * @return string
	 */
	public function settings_group(): string {
		return 'emcp_tools_module_' . str_replace( '-', '_', $this->id() ) . '_settings';
	}

	/** Whether this module exposes any settings (drives the "Show Settings" UI). */
	public function has_settings(): bool {
		return array() !== $this->settings_fields();
	}

	/**
	 * URL of a dedicated admin page that configures this module, if any. When
	 * set, the Modules card shows a "Configure →" link instead of an overlay.
	 *
	 * @return string
	 */
	public function settings_url(): string {
		return '';
	}
}
