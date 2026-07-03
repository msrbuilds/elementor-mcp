<?php
/**
 * Registry + lifecycle for EMCP Tools modules.
 *
 * Holds every registered module, seeds the default active-set once (marker
 * option), and boots active+available modules on `init`.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module registry (singleton).
 *
 * @since 3.1.0
 */
class EMCP_Tools_Modules_Registry {

	/** One-time seed marker so user toggles are never overwritten. */
	const OPTION_DEFAULTS_MARKER = 'emcp_tools_modules_defaults_applied';

	/** @var self|null */
	private static $instance = null;

	/** @var array<string,EMCP_Tools_Module> id => module */
	private $modules = array();

	private function __construct() {}

	/** @return self */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Test helper: drop the singleton + its modules. */
	public static function reset_for_tests(): void {
		self::$instance = null;
	}

	/**
	 * Register a module (idempotent by id).
	 *
	 * @param EMCP_Tools_Module $module Module instance.
	 */
	public function register( EMCP_Tools_Module $module ): void {
		$this->modules[ $module->id() ] = $module;
	}

	/** @return EMCP_Tools_Module[] All registered modules. */
	public function all(): array {
		return array_values( $this->modules );
	}

	/**
	 * @param string $id Module id.
	 * @return EMCP_Tools_Module|null
	 */
	public function get( string $id ): ?EMCP_Tools_Module {
		return $this->modules[ $id ] ?? null;
	}

	/** @return EMCP_Tools_Module[] Registered modules whose id is in the active option. */
	public function active(): array {
		return array_values(
			array_filter(
				$this->modules,
				static function ( EMCP_Tools_Module $m ) {
					return $m->is_active();
				}
			)
		);
	}

	/**
	 * Seed the active-modules option from each module's default_active(), once.
	 * A marker option guards against re-seeding after the user edits toggles.
	 */
	public function apply_defaults(): void {
		if ( get_option( self::OPTION_DEFAULTS_MARKER ) ) {
			return;
		}
		$defaults = array();
		foreach ( $this->modules as $module ) {
			if ( $module->default_active() ) {
				$defaults[] = $module->id();
			}
		}
		update_option( EMCP_Tools_Module::OPTION_ACTIVE, $defaults );
		update_option( self::OPTION_DEFAULTS_MARKER, '1' );
	}

	/** Call register() on every active + available module. Hooked to `init`. */
	public function boot_active(): void {
		foreach ( $this->active() as $module ) {
			if ( $module->is_available() ) {
				$module->register();
			}
		}
	}
}
