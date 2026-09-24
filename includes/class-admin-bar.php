<?php
/**
 * MCP status + exposure toggle in the WordPress admin bar.
 *
 * A small, admin-only quick menu (front-end + wp-admin) showing whether the
 * Abilities API is available and whether the EMCP MCP server is exposed, with a
 * one-click nonce toggle that flips the SAME option the Connection tab writes
 * (emcp_tools_server_enabled). Free; loaded unconditionally because the admin bar
 * renders on the front end too. All display logic derives from the pure status()
 * snapshot; the two environment seams (api_available/active_tool_count) are
 * protected so status() is unit-testable without WordPress.
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
class EMCP_Tools_Admin_Bar {

	const TOGGLE_ACTION = 'emcp_tools_toggle_server';

	/**
	 * @since 3.1.0
	 */
	public function init(): void {
		add_action( 'admin_bar_menu', array( $this, 'render_node' ), 90 );
		add_action( 'admin_post_' . self::TOGGLE_ACTION, array( $this, 'handle_toggle' ) );
	}

	/**
	 * Pure status snapshot for the menu.
	 *
	 * @since 3.1.0
	 * @return array{api_available:bool, enabled:bool, tool_count:int, color:string}
	 */
	public function status(): array {
		$api     = $this->api_available();
		$enabled = $this->is_enabled();
		$count   = ( $api && $enabled ) ? $this->active_tool_count() : 0;

		if ( ! $api ) {
			$color = 'red';
		} elseif ( $enabled ) {
			$color = 'green';
		} else {
			$color = 'grey';
		}

		return array(
			'api_available' => (bool) $api,
			'enabled'       => (bool) $enabled,
			'tool_count'    => (int) $count,
			'color'         => $color,
		);
	}

	/**
	 * Flip the exposure option; returns the new value ('1'|'0').
	 *
	 * @since 3.1.0
	 * @return string
	 */
	public function flip(): string {
		$new = $this->is_enabled() ? '0' : '1';
		update_option( 'emcp_tools_server_enabled', $new );
		return $new;
	}

	/**
	 * Whether MCP exposure is on. Prefers the plugin's canonical accessor; falls
	 * back to reading the option directly (identical logic) so this class is
	 * decoupled and unit-testable without booting the plugin singleton.
	 *
	 * @since 3.1.0
	 * @return bool
	 */
	private function is_enabled(): bool {
		if ( class_exists( 'EMCP_Tools_Plugin' ) ) {
			return EMCP_Tools_Plugin::is_server_enabled();
		}
		return '1' === (string) get_option( 'emcp_tools_server_enabled', '1' );
	}

	/**
	 * Whether the WordPress Abilities API is present (WP 6.9+). Seam for tests.
	 *
	 * @since 3.1.0
	 * @return bool
	 */
	protected function api_available(): bool {
		return function_exists( 'wp_register_ability' );
	}

	/**
	 * Count of currently-exposed tools. Seam for tests (avoids booting the
	 * plugin singleton in unit tests).
	 *
	 * @since 3.1.0
	 * @return int
	 */
	protected function active_tool_count(): int {
		if ( ! class_exists( 'EMCP_Tools_Plugin' ) ) {
			return 0;
		}
		$names = EMCP_Tools_Plugin::instance()->get_active_ability_names();
		return is_array( $names ) ? count( $names ) : 0;
	}

	/**
	 * Dot color hex for a status color.
	 *
	 * @since 3.1.0
	 * @param string $color green|red|grey.
	 * @return string
	 */
	private function dot_hex( string $color ): string {
		switch ( $color ) {
			case 'green':
				return '#22c55e';
			case 'red':
				return '#ef4444';
			default:
				return '#94a3b8';
		}
	}

	/**
	 * Render the admin-bar node + children. manage_options only; front-end + admin.
	 *
	 * @since 3.1.0
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function render_node( $wp_admin_bar ): void {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s        = $this->status();
		$settings = admin_url( 'admin.php?page=emcp-tools-connection' );
		$dot      = '<span style="display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px;background:' . esc_attr( $this->dot_hex( $s['color'] ) ) . '"></span>';

		$wp_admin_bar->add_node( array(
			'id'    => 'emcp-tools-mcp',
			'title' => $dot . esc_html__( 'EMCP', 'emcp-tools' ),
			'href'  => esc_url( $settings ),
			'meta'  => array( 'title' => esc_attr__( 'EMCP Tools, MCP status', 'emcp-tools' ) ),
		) );

		$wp_admin_bar->add_node( array(
			'parent' => 'emcp-tools-mcp',
			'id'     => 'emcp-tools-mcp-api',
			'title'  => esc_html__( 'Abilities API:', 'emcp-tools' ) . ' ' . ( $s['api_available'] ? esc_html__( 'Available', 'emcp-tools' ) : esc_html__( 'Unavailable', 'emcp-tools' ) ),
		) );

		$wp_admin_bar->add_node( array(
			'parent' => 'emcp-tools-mcp',
			'id'     => 'emcp-tools-mcp-state',
			'title'  => esc_html__( 'MCP exposure:', 'emcp-tools' ) . ' ' . ( $s['enabled'] ? esc_html__( 'Enabled', 'emcp-tools' ) : esc_html__( 'Disabled', 'emcp-tools' ) ),
		) );

		if ( $s['api_available'] && $s['enabled'] && $s['tool_count'] > 0 ) {
			$wp_admin_bar->add_node( array(
				'parent' => 'emcp-tools-mcp',
				'id'     => 'emcp-tools-mcp-count',
				/* translators: %d: number of active MCP tools */
				'title'  => sprintf( esc_html__( 'Active tools: %d', 'emcp-tools' ), (int) $s['tool_count'] ),
			) );
		}

		if ( $s['api_available'] ) {
			$toggle_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::TOGGLE_ACTION ), self::TOGGLE_ACTION );
			$wp_admin_bar->add_node( array(
				'parent' => 'emcp-tools-mcp',
				'id'     => 'emcp-tools-mcp-toggle',
				'title'  => $s['enabled'] ? esc_html__( 'Turn the EMCP server off', 'emcp-tools' ) : esc_html__( 'Turn the EMCP server on', 'emcp-tools' ),
				'href'   => esc_url( $toggle_url ),
			) );
		}

		$wp_admin_bar->add_node( array(
			'parent' => 'emcp-tools-mcp',
			'id'     => 'emcp-tools-mcp-settings',
			'title'  => esc_html__( 'Connection settings', 'emcp-tools' ) . ' →',
			'href'   => esc_url( $settings ),
		) );
	}

	/**
	 * admin-post handler: flip exposure (cap + nonce guarded) and redirect back.
	 *
	 * @since 3.1.0
	 */
	public function handle_toggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::TOGGLE_ACTION );
		$this->flip();
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}
}
