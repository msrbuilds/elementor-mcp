<?php
/**
 * The free "Needs your attention" checks (spec 9.7).
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared item builder.
 */
abstract class EMCP_Tools_Attention_Base implements EMCP_Tools_Attention_Check {

	/**
	 * @param string $page Admin page suffix after `emcp-tools`, e.g. '-tools'.
	 */
	protected static function url( string $page ): string {
		return admin_url( 'admin.php?page=' . EMCP_Tools_Admin::PAGE_SLUG . $page );
	}

	/**
	 * @param string $severity info, warning or danger.
	 * @param string $icon     Icon name.
	 * @param string $title    Title.
	 * @param string $body     Body.
	 * @param string $label    Action label.
	 * @param string $url      Action URL.
	 */
	protected function make( string $severity, string $icon, string $title, string $body, string $label, string $url ): array {
		return array(
			'id'           => $this->id(),
			'icon'         => $icon,
			'title'        => $title,
			'body'         => $body,
			'action_label' => $label,
			'action_url'   => $url,
			'severity'     => $severity,
		);
	}
}

/** The MCP server is switched off. */
final class EMCP_Tools_Attention_Server_Off extends EMCP_Tools_Attention_Base {
	public function id(): string {
		return 'mcp-server-off';
	}
	public function applies(): bool {
		return ! get_option( 'emcp_tools_server_enabled', true );
	}
	public function state(): string {
		return 'off';
	}
	public function item(): array {
		return $this->make( 'danger', 'server', __( 'The MCP server is off', 'emcp-tools' ), __( 'AI clients can\'t connect until you turn it back on.', 'emcp-tools' ), __( 'Open Connection', 'emcp-tools' ), self::url( '-connection' ) );
	}
}

/** Changes that could not be recorded, an old-code client, a stuck cutover. */
final class EMCP_Tools_Attention_History extends EMCP_Tools_Attention_Base {
	private function notices(): array {
		return class_exists( 'EMCP_Tools_Change_Notices' ) ? EMCP_Tools_Change_Notices::state() : array();
	}
	public function id(): string {
		return 'history-not-recorded';
	}
	public function applies(): bool {
		$n = $this->notices();
		return (int) ( $n['unrecorded'] ?? 0 ) > 0 || ! empty( $n['stray'] ) || '' !== (string) ( $n['cutover_error'] ?? '' );
	}
	public function state(): string {
		$n = $this->notices();
		return (int) ( $n['unrecorded'] ?? 0 ) . '|' . ( empty( $n['stray'] ) ? '0' : '1' ) . '|' . (string) ( $n['cutover_error'] ?? '' );
	}
	public function item(): array {
		return $this->make( 'warning', 'history', __( 'Some AI changes weren\'t recorded', 'emcp-tools' ), __( 'History can\'t undo them. Open History for details.', 'emcp-tools' ), __( 'Open History', 'emcp-tools' ), self::url( '-history' ) );
	}
}

/** Sandbox drafts whose review flagged something. */
final class EMCP_Tools_Attention_Sandbox extends EMCP_Tools_Attention_Base {
	private function count(): int {
		return class_exists( 'EMCP_Tools_Admin_Sandbox_Data' ) ? EMCP_Tools_Admin_Sandbox_Data::flagged_count() : 0;
	}
	public function id(): string {
		return 'sandbox-review';
	}
	public function applies(): bool {
		return $this->count() > 0;
	}
	public function state(): string {
		return (string) $this->count();
	}
	public function item(): array {
		$n = $this->count();
		/* translators: %d: number of sandbox items. */
		return $this->make( 'warning', 'code', sprintf( _n( '%d sandbox item awaiting review', '%d sandbox items awaiting review', $n, 'emcp-tools' ), $n ), __( 'AI-authored code stays inactive until you approve it.', 'emcp-tools' ), __( 'Review drafts', 'emcp-tools' ), self::url( '-widgets' ) );
	}
}

/**
 * Tools switched off on the Tools screen, counted like the Dashboard health
 * strip (visible tools minus enabled ones): the disabled option also lists
 * tools of other builders and inactive packs.
 */
final class EMCP_Tools_Attention_Tools extends EMCP_Tools_Attention_Base {
	/** @var callable|null */
	private $counter;

	/**
	 * @param callable|null $counter Returns the disabled count (tests).
	 */
	public function __construct( ?callable $counter = null ) {
		$this->counter = $counter;
	}

	private function count(): int {
		if ( $this->counter ) {
			return (int) call_user_func( $this->counter );
		}
		if ( ! class_exists( 'EMCP_Tools_Admin' ) && class_exists( 'EMCP_Tools_Bootstrap' ) ) {
			EMCP_Tools_Bootstrap::require_admin_classes();
		}
		if ( class_exists( 'EMCP_Tools_Admin' ) ) {
			$admin = new EMCP_Tools_Admin();
			return max( 0, $admin->get_total_tool_count() - $admin->get_enabled_tool_count() );
		}
		return count( (array) get_option( 'emcp_tools_disabled_tools', array() ) );
	}
	public function id(): string {
		return 'tools-disabled';
	}
	public function applies(): bool {
		return $this->count() > 0;
	}
	public function state(): string {
		return (string) $this->count();
	}
	public function item(): array {
		$n = $this->count();
		/* translators: %d: number of disabled tools. */
		return $this->make( 'info', 'wrench', sprintf( _n( '%d tool is disabled', '%d tools are disabled', $n, 'emcp-tools' ), $n ), __( 'Your AI can\'t call them.', 'emcp-tools' ), __( 'Review tools', 'emcp-tools' ), self::url( '-tools&status=disabled' ) );
	}
}

/** Plugin and theme updates from core's update transients. */
final class EMCP_Tools_Attention_Updates extends EMCP_Tools_Attention_Base {
	/** @return int[] { plugins, themes } */
	private function counts(): array {
		$p = get_site_transient( 'update_plugins' );
		$t = get_site_transient( 'update_themes' );
		return array(
			is_object( $p ) && ! empty( $p->response ) ? count( (array) $p->response ) : 0,
			is_object( $t ) && ! empty( $t->response ) ? count( (array) $t->response ) : 0,
		);
	}
	public function id(): string {
		return 'updates';
	}
	public function applies(): bool {
		return array_sum( $this->counts() ) > 0;
	}
	public function state(): string {
		return implode( ':', $this->counts() );
	}
	public function item(): array {
		$n = array_sum( $this->counts() );
		/* translators: %d: number of updates. */
		return $this->make( 'info', 'refresh-cw', sprintf( _n( '%d update available', '%d updates available', $n, 'emcp-tools' ), $n ), __( 'Plugins and themes with a newer version.', 'emcp-tools' ), __( 'Open Updates', 'emcp-tools' ), self_admin_url( 'update-core.php' ) );
	}
}

/** EMCP Cloud disconnected while Marketplace installs depend on it. */
final class EMCP_Tools_Attention_Cloud extends EMCP_Tools_Attention_Base {
	public function id(): string {
		return 'cloud-disconnected';
	}
	public function applies(): bool {
		if ( ! class_exists( 'EMCP_Tools_Cloud' ) || EMCP_Tools_Cloud::is_connected() ) {
			return false;
		}
		return class_exists( 'EMCP_Tools_Marketplace_Installs' ) && array() !== EMCP_Tools_Marketplace_Installs::all();
	}
	public function state(): string {
		return 'off';
	}
	public function item(): array {
		return $this->make( 'warning', 'cloud', __( 'EMCP Cloud is disconnected', 'emcp-tools' ), __( 'Your Marketplace items can\'t get updates until you reconnect.', 'emcp-tools' ), __( 'Reconnect', 'emcp-tools' ), self::url( '-connection&section=cloud' ) );
	}
}
