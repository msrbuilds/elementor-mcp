<?php
/**
 * Cloud backup, library, marketplace, and settings sync actions.
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
 * Cloud backup, library, marketplace, and settings sync actions.
 */
trait EMCP_Tools_Admin_Cloud_Trait {

	/**
	 * Push the local EMCP settings to EMCP Cloud (paid Cloud feature).
	 */
	public function handle_settings_push(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'emcp_tools_settings_sync' );
		$res = class_exists( 'EMCP_Tools_Settings_Sync' ) ? EMCP_Tools_Settings_Sync::push() : new \WP_Error( 'unavailable', '' );
		$this->redirect_settings_sync( is_wp_error( $res ) ? 'err' : 'push', is_wp_error( $res ) ? $res->get_error_code() : '' );
	}

	/**
	 * Pull the EMCP settings from EMCP Cloud and apply them (paid Cloud feature).
	 */
	public function handle_settings_pull(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'emcp_tools_settings_sync' );
		$res = class_exists( 'EMCP_Tools_Settings_Sync' ) ? EMCP_Tools_Settings_Sync::pull_and_apply() : new \WP_Error( 'unavailable', '' );
		$this->redirect_settings_sync( is_wp_error( $res ) ? 'err' : 'pull', is_wp_error( $res ) ? $res->get_error_code() : '' );
	}

	/**
	 * Back up a Sandbox artifact (block/widget/snippet) to EMCP Cloud. AJAX.
	 */
	public function ajax_backup_artifact(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
		$kind   = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$nonces = array(
			'widget'  => 'emcp_tools_widgets',
			'block'   => 'emcp_tools_blocks',
			'snippet' => 'emcp_tools_php_snippets',
		);
		if ( ! isset( $nonces[ $kind ] ) || ! check_ajax_referer( $nonces[ $kind ], 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'emcp-tools' ) ), 403 );
		}
		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		if ( ! $id || ! class_exists( 'EMCP_Tools_Cloud_Sync' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nothing to save.', 'emcp-tools' ) ) );
		}
		$res = EMCP_Tools_Cloud_Sync::backup( $kind, $id );
		if ( is_wp_error( $res ) ) {
			$msg = ( 'not_connected' === $res->get_error_code() )
				? __( 'Connect this site to EMCP Cloud first.', 'emcp-tools' )
				: $res->get_error_message();
			wp_send_json_error( array( 'message' => $msg ) );
		}
		// Record that this artifact now exists in the cloud + the checksum of what
		// was pushed (to later detect local edits), and refresh its marketplace
		// state so the buttons reflect reality.
		EMCP_Tools_Sandbox_Cloud_State::after_push( $kind, $id );
		$payload            = self::cloud_action_payload( $kind, $id );
		$payload['message'] = __( 'Saved to cloud.', 'emcp-tools' );
		wp_send_json_success( $payload );
	}

	/**
	 * Back up EVERY Sandbox artifact of a kind to EMCP Cloud in one call — the
	 * bulk counterpart to ajax_backup_artifact(), driving the "Save all to Cloud"
	 * button. AJAX.
	 */
	public function ajax_bulk_backup_artifacts(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
		$kind   = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$nonces = array(
			'widget'  => 'emcp_tools_widgets',
			'block'   => 'emcp_tools_blocks',
			'snippet' => 'emcp_tools_php_snippets',
		);
		if ( ! isset( $nonces[ $kind ] ) || ! check_ajax_referer( $nonces[ $kind ], 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'emcp-tools' ) ), 403 );
		}
		if ( ! class_exists( 'EMCP_Tools_Cloud_Sync' ) ) {
			wp_send_json_error( array( 'message' => __( 'Cloud sync is unavailable.', 'emcp-tools' ) ) );
		}
		$force = ! empty( $_POST['force'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['force'] ) );
		$res   = EMCP_Tools_Cloud_Sync::bulk_backup( array( $kind ), $force );
		if ( is_wp_error( $res ) ) {
			$msg = ( 'not_connected' === $res->get_error_code() )
				? __( 'Connect this site to EMCP Cloud first.', 'emcp-tools' )
				: $res->get_error_message();
			wp_send_json_error( array( 'message' => $msg ) );
		}
		// Mirror the per-artifact post-processing so each pushed row reflects "Saved".
		foreach ( (array) ( $res['items'] ?? array() ) as $emcp_item ) {
			if ( empty( $emcp_item['ok'] ) || ! empty( $emcp_item['skipped'] ) ) {
				continue;
			}
			$emcp_iid = (int) ( $emcp_item['id'] ?? 0 );
			if ( $emcp_iid ) {
				EMCP_Tools_Sandbox_Cloud_State::after_push( $kind, $emcp_iid );
			}
		}
		$pushed  = (int) ( $res['pushed'] ?? 0 );
		$skipped = (int) ( $res['skipped'] ?? 0 );
		$failed  = (int) ( $res['failed'] ?? 0 );
		if ( 0 === $pushed && 0 === $failed && $skipped > 0 ) {
			$message = __( 'Everything is already up to date in the cloud.', 'emcp-tools' );
		} else {
			/* translators: %d: number of artifacts saved to the cloud. */
			$message = sprintf( _n( 'Saved %d item to the cloud.', 'Saved %d items to the cloud.', $pushed, 'emcp-tools' ), $pushed );
		}
		if ( $skipped > 0 && ( $pushed > 0 || $failed > 0 ) ) {
			/* translators: %d: number of artifacts that were already up to date. */
			$message .= ' ' . sprintf( _n( '%d already up to date.', '%d already up to date.', $skipped, 'emcp-tools' ), $skipped );
		}
		if ( $failed > 0 ) {
			/* translators: %d: number of artifacts that failed to save. */
			$message .= ' ' . sprintf( _n( '%d failed.', '%d failed.', $failed, 'emcp-tools' ), $failed );
		}
		wp_send_json_success(
			array(
				'pushed'  => $pushed,
				'skipped' => $skipped,
				'failed'  => $failed,
				'message' => $message,
			)
		);
	}

	/** Nonce action for a sandbox artifact kind. */
	private static function cloud_nonce_action( string $kind ): string {
		$map = array( 'widget' => 'emcp_tools_widgets', 'block' => 'emcp_tools_blocks', 'snippet' => 'emcp_tools_php_snippets' );
		return $map[ $kind ] ?? '';
	}

	/** Cache the current content checksum as the last-pushed checksum. */
	private static function store_artifact_checksum( string $kind, int $id ): void {
		EMCP_Tools_Sandbox_Cloud_State::store_checksum( $kind, $id );
	}

	/** Current content checksum for an artifact ('' if unresolvable). */
	private static function artifact_checksum( string $kind, int $id ): string {
		return EMCP_Tools_Sandbox_Cloud_State::checksum( $kind, $id );
	}

	/** True when local content differs from what was last pushed to the cloud. */
	private static function artifact_changed( string $kind, int $id ): bool {
		return EMCP_Tools_Sandbox_Cloud_State::changed( $kind, $id );
	}

	/** Fetch marketplace state from the cloud and cache it locally (best effort). */
	private static function refresh_marketplace_state( string $kind, int $id ): ?array {
		return EMCP_Tools_Sandbox_Cloud_State::refresh_marketplace( $kind, $id );
	}

	/** Clear the "pushed" flags when the Cloud copy was deleted (definitive 404/410 only). */
	private static function verify_cloud_backup( string $kind, int $id ): void {
		EMCP_Tools_Sandbox_Cloud_State::verify_backup( $kind, $id );
	}

	/** JS payload describing an artifact's cloud/marketplace state (from cached meta). */
	public static function cloud_action_payload( string $kind, int $id ): array {
		return EMCP_Tools_Sandbox_Cloud_State::payload( $kind, $id );
	}

	/**
	 * Renders the Sandbox cloud/marketplace button cluster. The correct buttons
	 * are shown server-side (works without JS); sandbox-cloud.js refines them
	 * after refreshing state. Visibility is toggled via inline display because
	 * WordPress's `.button` (display:inline-block) overrides the [hidden] attr.
	 */
	public static function render_sandbox_cloud_actions( string $kind, int $id ): string {
		if ( ! class_exists( 'EMCP_Tools_Cloud' ) || ! EMCP_Tools_Cloud::is_connected() ) {
			return '';
		}
		$s     = self::cloud_action_payload( $kind, $id );
		$nonce = wp_create_nonce( self::cloud_nonce_action( $kind ) );

		$pushed    = ! empty( $s['pushed'] );
		$published = ! empty( $s['published'] );
		$changed   = ! empty( $s['changed'] );
		$has_slug  = '' !== (string) $s['slug'];
		$pending   = ! empty( $s['has_pending_update'] );

		// Save button label/state. Hidden once published (updates go via Push update).
		$save_show = true;
		$save_dis  = false;
		if ( ! $pushed ) {
			$save_txt = __( 'Save to Cloud', 'emcp-tools' );
		} elseif ( $published ) {
			$save_show = false;
			$save_txt  = __( 'Save to Cloud', 'emcp-tools' );
		} elseif ( $changed ) {
			$save_txt = __( 'Update cloud', 'emcp-tools' );
		} else {
			$save_txt = __( 'Saved', 'emcp-tools' );
			$save_dis = true;
		}
		$publish_show = $pushed && ! $has_slug;
		$view_show    = $has_slug;
		$update_show  = $published && $changed && ! $pending;

		$tag_txt = '';
		if ( $pending ) {
			$tag_txt = __( 'Update in review', 'emcp-tools' );
		} elseif ( $has_slug && 'pending' === (string) $s['status'] ) {
			$tag_txt = __( 'In review', 'emcp-tools' );
		} elseif ( $published && ! $changed ) {
			$tag_txt = __( 'Up to date', 'emcp-tools' );
		}

		$hide = static function ( bool $show ): string {
			return $show ? '' : ' style="display:none"';
		};
		$icon = static function ( string $d ): string {
			return '<span class="dashicons dashicons-' . esc_attr( $d ) . '" aria-hidden="true"></span>';
		};

		ob_start();
		?>
		<span class="emcp-sb-cloud" data-kind="<?php echo esc_attr( $kind ); ?>" data-id="<?php echo esc_attr( (string) $id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-state="<?php echo esc_attr( (string) wp_json_encode( $s ) ); ?>">
			<button type="button" class="button emcp-sb-save"<?php echo $hide( $save_show ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php disabled( $save_dis ); ?>
				data-t-save="<?php echo esc_attr__( 'Save to Cloud', 'emcp-tools' ); ?>"
				data-t-update="<?php echo esc_attr__( 'Update cloud', 'emcp-tools' ); ?>"
				data-t-saved="<?php echo esc_attr__( 'Saved', 'emcp-tools' ); ?>"><?php
				echo $icon( 'backup' ) . '<span class="emcp-sb-txt">' . esc_html( $save_txt ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?></button>
			<a class="button button-primary emcp-sb-publish" href="<?php echo esc_url( $s['publish_url'] ); ?>" target="_blank" rel="noopener"<?php echo $hide( $publish_show ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php
				echo $icon( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html_e( 'Publish to Marketplace', 'emcp-tools' );
			?></a>
			<a class="button emcp-sb-view" href="<?php echo esc_url( $s['view_url'] ); ?>" target="_blank" rel="noopener"<?php echo $hide( $view_show ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php
				echo $icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html_e( 'View on Marketplace', 'emcp-tools' );
			?></a>
			<button type="button" class="button button-primary emcp-sb-update"<?php echo $hide( $update_show ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php
				echo $icon( 'update' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html_e( 'Push update', 'emcp-tools' );
			?></button>
			<span class="emcp-sb-tag"<?php echo $hide( '' !== $tag_txt ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				data-t-inreview="<?php echo esc_attr__( 'Update in review', 'emcp-tools' ); ?>"
				data-t-pending="<?php echo esc_attr__( 'In review', 'emcp-tools' ); ?>"
				data-t-uptodate="<?php echo esc_attr__( 'Up to date', 'emcp-tools' ); ?>"><?php echo esc_html( $tag_txt ); ?></span>
			<span class="emcp-sb-msg" aria-live="polite"></span>
		</span>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders the "Cloud Library" panel for a sandbox screen — a collapsible list
	 * of the whole workspace's cloud artifacts of this kind (across every connected
	 * site), each importable into THIS site as a new inactive draft. Empty string
	 * when the site isn't cloud-connected. The list is fetched lazily on first open
	 * (see assets/js/cloud-library.js); import runs EMCP_Tools_Cloud_Sync::pull().
	 *
	 * @param string $kind Artifact kind (widget/block/snippet).
	 * @return string
	 */
	public static function render_cloud_library( string $kind ): string {
		if ( ! class_exists( 'EMCP_Tools_Cloud' ) || ! EMCP_Tools_Cloud::is_connected() ) {
			return '';
		}
		$na = self::cloud_nonce_action( $kind );
		if ( '' === $na ) {
			return '';
		}
		$plural = array(
			'widget'  => __( 'widgets', 'emcp-tools' ),
			'block'   => __( 'blocks', 'emcp-tools' ),
			'snippet' => __( 'snippets', 'emcp-tools' ),
		);
		$kl = $plural[ $kind ] ?? $kind;

		ob_start();
		?>
		<details class="emcp-cloud-lib emcp-sb-disclosure emcp-sb-disclosure--cloud" data-kind="<?php echo esc_attr( $kind ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( $na ) ); ?>" data-site="<?php echo esc_attr( EMCP_Tools_Cloud::site_uuid() ); ?>"
			data-t-loading="<?php echo esc_attr__( 'Loading…', 'emcp-tools' ); ?>"
			data-t-import="<?php echo esc_attr__( 'Import', 'emcp-tools' ); ?>"
			data-t-importing="<?php echo esc_attr__( 'Importing…', 'emcp-tools' ); ?>"
			data-t-imported="<?php echo esc_attr__( 'Imported', 'emcp-tools' ); ?>"
			data-t-thissite="<?php echo esc_attr__( 'This site', 'emcp-tools' ); ?>"
			data-t-othersite="<?php echo esc_attr__( 'Another site', 'emcp-tools' ); ?>"
			data-t-empty="<?php echo esc_attr__( 'Nothing in your cloud library yet. Save one from another connected site, then it appears here.', 'emcp-tools' ); ?>"
			data-t-error="<?php echo esc_attr__( 'Could not reach the cloud. Try again.', 'emcp-tools' ); ?>"
			data-t-reloadhint="<?php echo esc_attr__( 'Imported as a new inactive draft below.', 'emcp-tools' ); ?>"
			data-t-reload="<?php echo esc_attr__( 'Reload to view →', 'emcp-tools' ); ?>">
			<summary>
				<span class="dashicons dashicons-cloud" aria-hidden="true"></span>
				<?php
				/* translators: %s: artifact kind, plural (widgets / blocks / snippets). */
				echo esc_html( sprintf( __( 'Cloud Library — import %s from your other connected sites', 'emcp-tools' ), $kl ) );
				?>
				<span class="emcp-sb-disclosure__badge"><?php esc_html_e( 'Cross-site', 'emcp-tools' ); ?></span>
			</summary>
			<div class="emcp-cloud-lib__body" style="margin-top:12px;">
				<p class="emcp-cloud-lib__status description"><?php esc_html_e( 'Open to load your cloud library…', 'emcp-tools' ); ?></p>
				<table class="widefat striped emcp-cloud-lib__table" style="display:none;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'From', 'emcp-tools' ); ?></th>
							<th style="width:70px;"><?php esc_html_e( 'Version', 'emcp-tools' ); ?></th>
							<th style="width:110px;"><?php esc_html_e( 'Updated', 'emcp-tools' ); ?></th>
							<th style="width:120px;"></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</details>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * List the workspace's cloud artifacts of a kind (across all connected sites).
	 * Feeds the Cloud Library panel. AJAX.
	 */
	public function ajax_cloud_library(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'emcp-tools' ) ), 403 );
		}
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$na   = self::cloud_nonce_action( $kind );
		if ( '' === $na || ! check_ajax_referer( $na, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'emcp-tools' ) ), 403 );
		}
		if ( ! class_exists( 'EMCP_Tools_Cloud_Sync' ) ) {
			wp_send_json_error( array( 'message' => __( 'Cloud is unavailable.', 'emcp-tools' ) ) );
		}
		$res = EMCP_Tools_Cloud_Sync::list_remote( $kind );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		$arts = ( is_array( $res ) && isset( $res['artifacts'] ) && is_array( $res['artifacts'] ) ) ? $res['artifacts'] : array();
		$out  = array();
		foreach ( $arts as $a ) {
			$out[] = array(
				'uuid'        => (string) ( $a['artifact_uuid'] ?? '' ),
				'title'       => (string) ( $a['title'] ?? '' ),
				'version'     => (int) ( $a['version'] ?? 1 ),
				'origin'      => (string) ( $a['origin_site_uuid'] ?? '' ),
				'origin_url'  => (string) ( $a['origin_site_url'] ?? '' ),
				'origin_name' => (string) ( $a['origin_site_name'] ?? '' ),
				'updated'     => (string) ( $a['updated_at'] ?? '' ),
			);
		}
		wp_send_json_success(
			array(
				'artifacts' => $out,
				'site'      => class_exists( 'EMCP_Tools_Cloud' ) ? EMCP_Tools_Cloud::site_uuid() : '',
			)
		);
	}

	/**
	 * Pull one cloud artifact into this site as a new inactive draft. AJAX.
	 * Delegates to EMCP_Tools_Cloud_Sync::pull() (imports the portable bundle).
	 */
	public function ajax_cloud_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'emcp-tools' ) ), 403 );
		}
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$na   = self::cloud_nonce_action( $kind );
		if ( '' === $na || ! check_ajax_referer( $na, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'emcp-tools' ) ), 403 );
		}
		$uuid = isset( $_POST['uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['uuid'] ) ) : '';
		if ( '' === $uuid || ! class_exists( 'EMCP_Tools_Cloud_Sync' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nothing to import.', 'emcp-tools' ) ) );
		}
		$res = EMCP_Tools_Cloud_Sync::pull( $uuid, $kind );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'id'      => (int) ( $res['id'] ?? 0 ),
				'message' => __( 'Imported as a new inactive draft.', 'emcp-tools' ),
			)
		);
	}

	/** Push an update to an already-published marketplace listing. AJAX. */
	public function ajax_push_update(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$na   = self::cloud_nonce_action( $kind );
		if ( '' === $na || ! check_ajax_referer( $na, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'emcp-tools' ) ), 403 );
		}
		$id        = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$changelog = isset( $_POST['changelog'] ) ? sanitize_textarea_field( wp_unslash( $_POST['changelog'] ) ) : '';
		if ( ! $id || ! class_exists( 'EMCP_Tools_Cloud_Sync' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nothing to update.', 'emcp-tools' ) ) );
		}
		$res = EMCP_Tools_Cloud_Sync::push_update( $kind, $id, $changelog );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		self::store_artifact_checksum( $kind, $id );
		self::refresh_marketplace_state( $kind, $id );
		$payload            = self::cloud_action_payload( $kind, $id );
		$payload['message'] = __( 'Update pushed — pending review.', 'emcp-tools' );
		wp_send_json_success( $payload );
	}

	/** Refresh + return an artifact's marketplace state. AJAX (page-load sync). */
	public function ajax_marketplace_state(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'emcp-tools' ) ), 403 );
		}
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$na   = self::cloud_nonce_action( $kind );
		if ( '' === $na || ! check_ajax_referer( $na, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'emcp-tools' ) ), 403 );
		}
		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Missing id.', 'emcp-tools' ) ) );
		}
		self::refresh_marketplace_state( $kind, $id );
		wp_send_json_success( self::cloud_action_payload( $kind, $id ) );
	}

	/**
	 * Full resync of an artifact's cloud state: verify the backup still exists
	 * remotely (self-heals a stale "Saved" after a cloud-side delete) AND refresh
	 * its marketplace listing state. Drives the "Refresh cloud status" button.
	 */
	public function ajax_resync_cloud(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'emcp-tools' ) ), 403 );
		}
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$na   = self::cloud_nonce_action( $kind );
		if ( '' === $na || ! check_ajax_referer( $na, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'emcp-tools' ) ), 403 );
		}
		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Missing id.', 'emcp-tools' ) ) );
		}
		self::verify_cloud_backup( $kind, $id );
		self::refresh_marketplace_state( $kind, $id );
		$payload            = self::cloud_action_payload( $kind, $id );
		$payload['message'] = __( 'Cloud status refreshed.', 'emcp-tools' );
		wp_send_json_success( $payload );
	}

	/**
	 * Install a marketplace listing into this site (as a draft artifact).
	 */
	public function handle_marketplace_install(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'emcp_tools_marketplace_install' );
		$slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$back = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-marketplace' );

		$res = ( '' !== $slug && class_exists( 'EMCP_Tools_Cloud_Sync' ) )
			? EMCP_Tools_Cloud_Sync::marketplace_install( $slug )
			: new \WP_Error( 'bad_request', '' );

		if ( is_wp_error( $res ) ) {
			$data   = $res->get_error_data();
			$status = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
			$q      = ( 402 === $status || 'pro_required' === $res->get_error_message() ) ? 'pro' : 'err';
			wp_safe_redirect( add_query_arg( 'mk', $q, $back ) );
			exit;
		}
		$post_id = is_array( $res ) ? (int) ( $res['id'] ?? 0 ) : 0;
		wp_safe_redirect( add_query_arg( array( 'mk' => 'installed', 'mk_id' => $post_id ), $back ) );
		exit;
	}

	/**
	 * Redirect back to the Connection tab after a settings-sync action.
	 *
	 * @param string $status     push|pull|err.
	 * @param string $error_code Optional safe WP_Error code for a useful notice.
	 */
	private function redirect_settings_sync( string $status, string $error_code = '' ): void {
		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-connection' );
		}
		$args = array( 'synced' => $status );
		if ( 'err' === $status && '' !== $error_code ) {
			$args['sync_error'] = sanitize_key( $error_code );
		}
		wp_safe_redirect( add_query_arg( $args, $back ) );
		exit;
	}
}
