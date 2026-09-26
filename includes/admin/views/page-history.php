<?php
/**
 * History tab — the AI-safe change ledger with one-click rollback.
 *
 * Surfaces EMCP_Tools_Change_Log entries (Elementor edits, filesystem writes,
 * database writes) to a human administrator, with a rollback action per
 * reversible entry. Included from EMCP_Tools_Admin::render_page().
 *
 * @package EMCP_Tools
 * @since   3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_entries = class_exists( 'EMCP_Tools_Change_Log' ) ? array_reverse( EMCP_Tools_Change_Log::all() ) : array();

// Domain filter.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter.
$emcp_domain = isset( $_GET['domain'] ) ? sanitize_key( wp_unslash( $_GET['domain'] ) ) : '';
if ( '' !== $emcp_domain ) {
	$emcp_entries = array_values( array_filter( $emcp_entries, static function ( $e ) use ( $emcp_domain ) {
		return ( $e['domain'] ?? '' ) === $emcp_domain;
	} ) );
}

$emcp_domain_labels = array(
	'elementor'  => __( 'Elementor', 'emcp-tools' ),
	'filesystem' => __( 'Filesystem', 'emcp-tools' ),
	'database'   => __( 'Database', 'emcp-tools' ),
);

// Keep the ledger scannable even when it reaches its 500-entry cap.
$emcp_history_per_page = 20;
$emcp_history_total    = count( $emcp_entries );
$emcp_history_page     = class_exists( 'EMCP_Tools_Admin_Pager' ) ? EMCP_Tools_Admin_Pager::current( 'history_page' ) : 1;
$emcp_history_pages    = max( 1, (int) ceil( $emcp_history_total / $emcp_history_per_page ) );
$emcp_history_page     = min( $emcp_history_page, $emcp_history_pages );
$emcp_entries          = array_slice( $emcp_entries, ( $emcp_history_page - 1 ) * $emcp_history_per_page, $emcp_history_per_page );
$emcp_history_href     = static function ( $emcp_n ) use ( $emcp_domain ) {
	$emcp_args = array(
		'page' => EMCP_Tools_Admin::PAGE_SLUG . '-history',
	);
	if ( '' !== $emcp_domain ) {
		$emcp_args['domain'] = $emcp_domain;
	}
	if ( $emcp_n > 1 ) {
		$emcp_args['history_page'] = (int) $emcp_n;
	}
	return add_query_arg( $emcp_args, admin_url( 'admin.php' ) );
};

// Result notice after a rollback.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice.
$emcp_rb = isset( $_GET['rollback'] ) ? sanitize_key( wp_unslash( $_GET['rollback'] ) ) : '';
// Result notices after a delete / clear-all.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice.
$emcp_del = isset( $_GET['deleted'] ) ? sanitize_key( wp_unslash( $_GET['deleted'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice.
$emcp_cleared = isset( $_GET['cleared'] ) ? absint( wp_unslash( $_GET['cleared'] ) ) : -1;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice.
$emcp_history_error = isset( $_GET['history_error'] ) ? sanitize_key( wp_unslash( $_GET['history_error'] ) ) : '';
?>

<div class="emcp-history">
	<?php if ( 'ok' === $emcp_rb ) : ?>
		<div class="notice notice-success is-dismissible"><p><strong><?php esc_html_e( 'Change rolled back.', 'emcp-tools' ); ?></strong></p></div>
	<?php elseif ( 'partial' === $emcp_rb ) : ?>
		<div class="notice notice-warning is-dismissible"><p><strong><?php esc_html_e( 'Change rolled back — partially.', 'emcp-tools' ); ?></strong>
			<?php esc_html_e( 'The before-image was capped, so some rows may not have been restored.', 'emcp-tools' ); ?></p></div>
	<?php elseif ( 'conflict' === $emcp_rb ) : ?>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only; the force action below carries its own nonce.
		$emcp_conflict_id = isset( $_GET['change'] ) ? sanitize_text_field( wp_unslash( $_GET['change'] ) ) : '';
		?>
		<div class="notice notice-warning"><p>
			<strong><?php esc_html_e( 'This target changed since the change was recorded.', 'emcp-tools' ); ?></strong>
			<?php esc_html_e( 'Rolling back now would overwrite the newer edits.', 'emcp-tools' ); ?>
			<?php if ( '' !== $emcp_conflict_id ) : ?>
				<a class="button button-secondary" style="margin-left:8px;"
					href="<?php echo esc_url( EMCP_Tools_Admin::rollback_change_url( $emcp_conflict_id, true, $emcp_history_page, $emcp_domain ) ); ?>"
					onclick="return confirm('<?php echo esc_js( __( 'Overwrite the newer state and roll back anyway?', 'emcp-tools' ) ); ?>');">
					<?php esc_html_e( 'Roll back anyway', 'emcp-tools' ); ?>
				</a>
			<?php endif; ?>
		</p></div>
	<?php elseif ( 'error' === $emcp_rb ) : ?>
		<div class="notice notice-error is-dismissible"><p><strong><?php esc_html_e( 'Rollback failed.', 'emcp-tools' ); ?></strong>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only message text.
		echo isset( $_GET['msg'] ) ? ' ' . esc_html( sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ) : '';
		?>
		</p></div>
	<?php endif; ?>

	<?php if ( 'history_busy' === $emcp_history_error ) : ?>
		<div class="notice notice-warning is-dismissible"><p><strong><?php esc_html_e( 'History is busy, try again in a moment.', 'emcp-tools' ); ?></strong></p></div>
	<?php elseif ( 'history_upgrading' === $emcp_history_error ) : ?>
		<div class="notice notice-warning is-dismissible"><p><strong><?php esc_html_e( 'History is being upgraded, try again in a moment.', 'emcp-tools' ); ?></strong></p></div>
	<?php elseif ( '1' === $emcp_del ) : ?>
		<div class="notice notice-success is-dismissible"><p><strong><?php esc_html_e( 'History entry deleted.', 'emcp-tools' ); ?></strong></p></div>
	<?php elseif ( '0' === $emcp_del ) : ?>
		<div class="notice notice-error is-dismissible"><p><strong><?php esc_html_e( 'That history entry no longer exists.', 'emcp-tools' ); ?></strong></p></div>
	<?php endif; ?>

	<?php if ( $emcp_cleared > -1 ) : ?>
		<div class="notice notice-success is-dismissible"><p><strong>
			<?php
			printf(
				/* translators: %s: number of history entries removed */
				esc_html( _n( 'Cleared %s history entry.', 'Cleared %s history entries.', $emcp_cleared, 'emcp-tools' ) ),
				esc_html( number_format_i18n( $emcp_cleared ) )
			);
			?>
		</strong></p></div>
	<?php endif; ?>

	<div class="emcp-history__head">
		<div>
			<h2 class="emcp-history__title"><?php esc_html_e( 'Change history', 'emcp-tools' ); ?></h2>
			<p class="emcp-history__intro">
				<?php esc_html_e( 'Every AI-made change to Elementor pages, files, and the database is recorded here. Reversible changes can be rolled back with one click.', 'emcp-tools' ); ?>
			</p>
			<?php if ( ! empty( $emcp_entries ) ) : ?>
				<p class="emcp-history__clear-wrap">
					<a class="button button-link-delete emcp-history__clear"
						href="<?php echo esc_url( EMCP_Tools_Admin::clear_changes_url() ); ?>"
						onclick="return confirm('<?php echo esc_js( __( 'Clear the entire change history? This cannot be undone, and any entries that could still be rolled back will lose that ability.', 'emcp-tools' ) ); ?>');">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<?php esc_html_e( 'Clear all', 'emcp-tools' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<ul class="emcp-history__filters">
			<li<?php echo '' === $emcp_domain ? ' class="is-active"' : ''; ?>><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . EMCP_Tools_Admin::PAGE_SLUG . '-history' ) ); ?>"><?php esc_html_e( 'All', 'emcp-tools' ); ?></a></li>
			<?php foreach ( $emcp_domain_labels as $emcp_dk => $emcp_dl ) : ?>
				<li<?php echo $emcp_domain === $emcp_dk ? ' class="is-active"' : ''; ?>><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . EMCP_Tools_Admin::PAGE_SLUG . '-history&domain=' . $emcp_dk ) ); ?>"><?php echo esc_html( $emcp_dl ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</div>

	<?php if ( empty( $emcp_entries ) ) : ?>
		<div class="emcp-history__empty">
			<span class="dashicons dashicons-undo" aria-hidden="true"></span>
			<p><?php esc_html_e( 'No changes recorded yet. As soon as a connected AI edits a page, writes a file, or changes the database, it will appear here, ready to roll back.', 'emcp-tools' ); ?></p>
		</div>
	<?php else : ?>
		<?php if ( class_exists( 'EMCP_Tools_Admin_Pager' ) ) : ?>
			<p class="description emcp-pager-count">
				<?php echo EMCP_Tools_Admin_Pager::summary( $emcp_history_page, $emcp_history_per_page, count( $emcp_entries ), $emcp_history_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by summary(). ?>
			</p>
		<?php endif; ?>
		<table class="widefat striped emcp-history__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'emcp-tools' ); ?></th>
					<th><?php esc_html_e( 'Who', 'emcp-tools' ); ?></th>
					<th><?php esc_html_e( 'Type', 'emcp-tools' ); ?></th>
					<th><?php esc_html_e( 'Change', 'emcp-tools' ); ?></th>
					<th><?php esc_html_e( 'Target', 'emcp-tools' ); ?></th>
					<th class="emcp-history__actions-col"><?php esc_html_e( 'Action', 'emcp-tools' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $emcp_entries as $emcp_e ) : ?>
					<?php
					$emcp_id         = (string) ( $emcp_e['id'] ?? '' );
					$emcp_ts         = (int) ( $emcp_e['ts'] ?? 0 );
					$emcp_blocker    = EMCP_Tools_Change_Log::rollback_blocker( $emcp_e );
					$emcp_reversible = ! is_wp_error( $emcp_blocker );
					$emcp_dk         = (string) ( $emcp_e['domain'] ?? '' );
					?>
					<tr>
						<td>
							<?php echo esc_html( $emcp_ts ? date_i18n( 'Y-m-d H:i', $emcp_ts ) : ', ' ); ?>
							<?php if ( $emcp_ts ) : ?>
								<span class="emcp-history__ago"><?php echo esc_html( sprintf( /* translators: %s: human time diff */ __( '%s ago', 'emcp-tools' ), human_time_diff( $emcp_ts ) ) ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) ( $emcp_e['user_login'] ?? '' ) ); ?></td>
						<td><span class="emcp-history__badge emcp-history__badge--<?php echo esc_attr( $emcp_dk ); ?>"><?php echo esc_html( $emcp_domain_labels[ $emcp_dk ] ?? $emcp_dk ); ?></span></td>
						<td>
							<strong><?php echo esc_html( (string) ( $emcp_e['action'] ?? '' ) ); ?></strong><br />
							<span class="emcp-history__summary"><?php echo esc_html( (string) ( $emcp_e['summary'] ?? '' ) ); ?></span>
						</td>
						<td class="emcp-history__target"><?php echo esc_html( (string) ( $emcp_e['target'] ?? '' ) ); ?></td>
						<td class="emcp-history__actions-col">
							<?php if ( ! empty( $emcp_e['rolled_back'] ) ) : ?>
								<span class="emcp-history__state emcp-history__state--done"><?php esc_html_e( 'Rolled back', 'emcp-tools' ); ?></span>
							<?php elseif ( $emcp_reversible ) : ?>
								<a class="button button-secondary emcp-history__rollback"
									href="<?php echo esc_url( EMCP_Tools_Admin::rollback_change_url( $emcp_id, false, $emcp_history_page, $emcp_domain ) ); ?>"
									onclick="return confirm('<?php echo esc_js( __( 'Roll this change back? This restores the previous state.', 'emcp-tools' ) ); ?>');">
									<span class="dashicons dashicons-undo" aria-hidden="true"></span>
									<?php esc_html_e( 'Roll back', 'emcp-tools' ); ?>
								</a>
							<?php else : ?>
								<span class="emcp-history__state" title="<?php echo esc_attr( is_wp_error( $emcp_blocker ) ? $emcp_blocker->get_error_message() : '' ); ?>"><?php esc_html_e( 'Undo unavailable', 'emcp-tools' ); ?></span>
							<?php endif; ?>
							<a class="emcp-history__delete"
								href="<?php echo esc_url( EMCP_Tools_Admin::delete_change_url( $emcp_id, $emcp_history_page, $emcp_domain ) ); ?>"
								aria-label="<?php esc_attr_e( 'Delete this history entry', 'emcp-tools' ); ?>"
								onclick="return confirm('<?php echo esc_js(
									$emcp_reversible
										? __( 'Delete this entry? It can still be rolled back, deleting it discards that ability permanently.', 'emcp-tools' )
										: __( 'Delete this history entry? This cannot be undone.', 'emcp-tools' )
								); ?>');">
								<span class="dashicons dashicons-trash" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Delete', 'emcp-tools' ); ?></span>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		if ( class_exists( 'EMCP_Tools_Admin_Pager' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by render().
			echo EMCP_Tools_Admin_Pager::render( $emcp_history_page, $emcp_history_pages, $emcp_history_href );
		}
		?>
		<p class="emcp-history__note"><?php esc_html_e( 'The ledger keeps the most recent changes (older entries age out). Rolling a change back records its own entry.', 'emcp-tools' ); ?></p>
	<?php endif; ?>
</div>
