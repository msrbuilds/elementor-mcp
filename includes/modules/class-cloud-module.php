<?php
/**
 * EMCP Cloud module (free, on by default). Boots the OAuth client admin flow.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Cloud_Module extends EMCP_Tools_Module {
	public function id(): string {
		return 'cloud';
	}

	public function title(): string {
		return __( 'EMCP Cloud', 'emcp-tools' );
	}

	public function description(): string {
		return __( 'Connect this site to your EMCP Cloud account to back up and sync your work.', 'emcp-tools' );
	}

	public function tier(): string {
		return 'free';
	}

	/** Modules screen group. */
	public function group(): string {
		return 'site';
	}

	/** Modules screen icon. */
	public function icon(): string {
		return 'cloud';
	}

	public function default_active(): bool {
		return true;
	}

	public function register(): void {
		EMCP_Tools_Cloud_Connect::init();
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once EMCP_TOOLS_DIR . 'includes/cloud/class-cloud-onboarding.php';
			WP_CLI::add_command( 'emcp cloud onboard', array( 'EMCP_Tools_Cloud_Onboarding', 'command' ) );
		}
	}

	public function render_settings(): void {
		echo '<p>' . esc_html__( 'Connect or disconnect on the Connection tab.', 'emcp-tools' ) . '</p>';
	}

	public function settings_url(): string {
		return admin_url( 'admin.php?page=emcp-tools-connection#emcp-conn-main' );
	}

	/**
	 * Static gate for the Connection-tab card (runs before init:5).
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$active = (array) get_option( EMCP_Tools_Module::OPTION_ACTIVE, array() );
		return in_array( 'cloud', $active, true );
	}
}
