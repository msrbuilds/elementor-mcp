<?php
/**
 * History ledger actions and return URLs.
 *
 * Internal implementation of EMCP_Tools_Admin; loaded by class-admin.php.
 * Methods retain the admin class scope for existing callbacks and callers.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * History ledger actions and return URLs.
 */
trait EMCP_Tools_Admin_History_Trait {

	/**
	 * Nonce-protected URL that rolls back one change-ledger entry.
	 *
	 * @since 3.3.0
	 * @param string $id     Change id.
	 * @param bool   $force  Whether to bypass the conflict guard.
	 * @param int    $page   History page to return to.
	 * @param string $domain Active History domain filter.
	 * @return string
	 */
	public static function rollback_change_url( string $id, bool $force = false, int $page = 1, string $domain = '' ): string {
		$args = array(
			'action' => self::ACTION_ROLLBACK_CHANGE,
			'change' => $id,
		);
		if ( $force ) {
			$args['force'] = 1;
		}
		if ( $page > 1 ) {
			$args['history_page'] = $page;
		}
		if ( '' !== $domain ) {
			$args['domain'] = sanitize_key( $domain );
		}
		$url = add_query_arg( $args, admin_url( 'admin-post.php' ) );
		return wp_nonce_url( $url, self::ACTION_ROLLBACK_CHANGE . '_' . $id );
	}

	/**
	 * Roll back a change from the History tab, then bounce back with a notice.
	 *
	 * @since 3.3.0
	 */
	public function handle_rollback_change(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified just below against the per-id action.
		$id = isset( $_GET['change'] ) ? sanitize_text_field( wp_unslash( $_GET['change'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified just below; force is a display flag on an admin-gated action.
		$force = ! empty( $_GET['force'] );
		check_admin_referer( self::ACTION_ROLLBACK_CHANGE . '_' . $id );

		$result = class_exists( 'EMCP_Tools_Change_Log' ) ? EMCP_Tools_Change_Log::rollback( $id, $force ) : new WP_Error( 'unavailable', 'unavailable' );
		$redirect_args = array(
			'page' => self::PAGE_SLUG . '-history',
		);
		if ( is_wp_error( $result ) ) {
			// A conflict is recoverable — bounce back with the id so the History
			// tab can offer a "roll back anyway" (force) action.
			if ( 'conflict' === $result->get_error_code() ) {
				$redirect_args['rollback'] = 'conflict';
				$redirect_args['change']   = $id;
			} else {
				$redirect_args['rollback'] = 'error';
				$redirect_args['msg']      = $result->get_error_message();
			}
		} else {
			$redirect_args['rollback'] = ! empty( $result['partial'] ) ? 'partial' : 'ok';
		}
		$redirect_args = array_merge( $redirect_args, self::history_return_args() );

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Nonce'd URL that deletes one History entry.
	 *
	 * @since 3.4.2
	 * @param string $id     Entry id.
	 * @param int    $page   History page to return to.
	 * @param string $domain Active History domain filter.
	 * @return string
	 */
	public static function delete_change_url( string $id, int $page = 1, string $domain = '' ): string {
		$args = array(
			'action' => self::ACTION_DELETE_CHANGE,
			'change' => $id,
		);
		if ( $page > 1 ) {
			$args['history_page'] = $page;
		}
		if ( '' !== $domain ) {
			$args['domain'] = sanitize_key( $domain );
		}
		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			self::ACTION_DELETE_CHANGE . '_' . $id
		);
	}

	/**
	 * Sanitized History location carried through row actions.
	 *
	 * @since 3.16.0
	 * @return array<string,int|string>
	 */
	private static function history_return_args(): array {
		$args = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the action nonce is verified by the caller.
		$page = isset( $_GET['history_page'] ) ? absint( wp_unslash( $_GET['history_page'] ) ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the action nonce is verified by the caller.
		$domain = isset( $_GET['domain'] ) ? sanitize_key( wp_unslash( $_GET['domain'] ) ) : '';
		if ( $page > 1 ) {
			$args['history_page'] = $page;
		}
		if ( '' !== $domain ) {
			$args['domain'] = $domain;
		}
		return $args;
	}

	/**
	 * Nonce'd URL that clears the whole History ledger.
	 *
	 * @since 3.4.2
	 * @return string
	 */
	public static function clear_changes_url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION_CLEAR_CHANGES ),
			self::ACTION_CLEAR_CHANGES
		);
	}

	/**
	 * Delete one entry from the History ledger, then bounce back with a notice.
	 *
	 * @since 3.4.2
	 */
	public function handle_delete_change(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified just below against the per-id action.
		$id = isset( $_GET['change'] ) ? sanitize_text_field( wp_unslash( $_GET['change'] ) ) : '';
		check_admin_referer( self::ACTION_DELETE_CHANGE . '_' . $id );

		$deleted = class_exists( 'EMCP_Tools_Change_Log' ) && EMCP_Tools_Change_Log::delete( $id );

		$redirect_args = array_merge(
			array(
				'page'    => self::PAGE_SLUG . '-history',
				'deleted' => $deleted ? '1' : '0',
			),
			self::history_error_args(),
			self::history_return_args()
		);
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Clear the whole History ledger, then bounce back with a notice.
	 *
	 * @since 3.4.2
	 */
	public function handle_clear_changes(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION_CLEAR_CHANGES );

		$count = class_exists( 'EMCP_Tools_Change_Log' ) ? EMCP_Tools_Change_Log::clear() : 0;

		$args = self::history_error_args();
		$args = $args ? $args : array( 'cleared' => (int) $count );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-history' ) ) );
		exit;
	}

	/**
	 * Query args naming why the last delete or clear did nothing (409 history_busy or history_upgrading, spec 9.1).
	 *
	 * @return array
	 */
	private static function history_error_args(): array {
		$error = class_exists( 'EMCP_Tools_Change_Log' ) ? EMCP_Tools_Change_Log::last_error() : null;
		return $error ? array( 'history_error' => $error->get_error_code() ) : array();
	}
}
