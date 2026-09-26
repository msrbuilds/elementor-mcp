<?php
/**
 * Ledger notices (spec 9.1 step 7): an AI connection still running the
 * previous version (it keeps writing the old option after cleanup), and the
 * one-time "reconnect every client" notice after the upgrade.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change ledger notices.
 */
final class EMCP_Tools_Change_Notices {

	private static function s(): EMCP_Tools_Change_Storage {
		return EMCP_Tools_Change_Log::store()->storage();
	}

	const DISMISS_STRAY   = 'emcp_tools_changes_dismiss_stray';
	const DISMISS_UPGRADE = 'emcp_tools_changes_dismiss_upgrade';

	/** Admin notices and their Dismiss handlers. */
	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_' . self::DISMISS_STRAY, array( __CLASS__, 'handle_dismiss_stray' ) );
		add_action( 'admin_post_' . self::DISMISS_UPGRADE, array( __CLASS__, 'handle_dismiss_upgrade' ) );
	}

	/** Only after cleanup does a reappearing option mean an old-code writer. */
	public static function stray_option(): bool {
		$s     = self::s();
		$state = $s->get_meta( EMCP_Tools_Change_Names::cutover() );
		return is_array( $state ) && 'done' === ( $state['cleanup'] ?? '' ) && null !== $s->option_raw();
	}

	/** The one-time "reconnect every client" notice set at publication. */
	public static function upgrade_notice_pending(): bool {
		return (bool) self::s()->get_meta( EMCP_Tools_Change_Names::reconnect_notice() );
	}

	/** Dismiss deletes the stray option; it comes back only if an old process writes again. */
	public static function dismiss_stray(): void {
		if ( self::stray_option() ) {
			self::s()->option_remove();
		}
	}

	public static function dismiss_upgrade(): void {
		self::s()->delete_meta( EMCP_Tools_Change_Names::reconnect_notice() );
	}

	/**
	 * What the History banners (5b) and attention items (Part 6) need.
	 *
	 * @return array{stray:bool, upgrade:bool, unrecorded:int, fallback:string}
	 */
	public static function state(): array {
		$s  = self::s();
		$u  = $s->get_meta( EMCP_Tools_Change_Names::unrecorded() );
		$fb = $s->get_meta( EMCP_Tools_Change_Names::fallback() );
		return array(
			'stray'      => self::stray_option(),
			'upgrade'    => self::upgrade_notice_pending(),
			'unrecorded' => is_array( $u ) ? (int) ( $u['count'] ?? 0 ) : 0,
			'fallback'   => is_array( $fb ) ? (string) ( $fb['reason'] ?? '' ) : '',
		);
	}

	/**
	 * @param string $text   Notice text.
	 * @param string $action admin-post action of its Dismiss button.
	 */
	private static function notice( string $text, string $action ): void {
		printf(
			'<div class="notice notice-warning"><p>%1$s</p><p><a class="button" href="%2$s">%3$s</a></p></div>',
			esc_html( $text ),
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . $action ), $action ) ),
			esc_html__( 'Dismiss', 'emcp-tools' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( self::stray_option() ) {
			self::notice( __( "An AI connection is still running the previous version of EMCP Tools. Its changes aren't being recorded in History and can't be undone. Reconnect your AI client (restart it, or reload its MCP server) to fix this.", 'emcp-tools' ), self::DISMISS_STRAY );
		}
		if ( self::upgrade_notice_pending() ) {
			self::notice( __( 'EMCP Tools 3.18.0 moved History to its own table. Reconnect every AI client (restart it, or reload its MCP server) so its changes keep being recorded.', 'emcp-tools' ), self::DISMISS_UPGRADE );
		}
	}

	public static function handle_dismiss_stray(): void {
		self::handle( self::DISMISS_STRAY, array( __CLASS__, 'dismiss_stray' ) );
	}

	public static function handle_dismiss_upgrade(): void {
		self::handle( self::DISMISS_UPGRADE, array( __CLASS__, 'dismiss_upgrade' ) );
	}

	/**
	 * @param string   $action Nonce action.
	 * @param callable $fn     Dismissal.
	 */
	private static function handle( string $action, callable $fn ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
		$fn();
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}
}
