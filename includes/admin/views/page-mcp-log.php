<?php
/**
 * MCP Log tab — recent MCP requests (tool, status, duration) so failures can be
 * matched to server-side outcomes. Bug report Issue 5.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once EMCP_TOOLS_DIR . 'includes/class-mcp-request-log.php';

// Handle "Clear log" (self-POST, nonce + capability gated).
if ( isset( $_POST['emcp_mcp_log_clear'] ) && current_user_can( 'manage_options' )
	&& isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'emcp_mcp_log_clear' ) ) {
	EMCP_Tools_MCP_Request_Log::clear();
	echo '<div class="notice notice-success inline"><p>' . esc_html__( 'MCP log cleared.', 'emcp-tools' ) . '</p></div>';
}

$emcp_log   = array_reverse( EMCP_Tools_MCP_Request_Log::all() ); // newest first.
$emcp_debug = EMCP_Tools_MCP_Request_Log::debug_enabled();
?>
<div class="elementor-mcp-section">
	<h2><?php esc_html_e( 'MCP Log', 'emcp-tools' ); ?></h2>
	<p class="elementor-mcp-activate-note">
		<?php esc_html_e( 'The last 500 MCP requests to this site, with client, tool, result and duration. Use this to match a connector failure to a server-side outcome.', 'emcp-tools' ); ?>
		<?php if ( ! $emcp_debug ) : ?>
			<br><?php esc_html_e( 'Enable WP_DEBUG to also record the full error detail for failed requests.', 'emcp-tools' ); ?>
		<?php endif; ?>
	</p>

	<?php if ( empty( $emcp_log ) ) : ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'No MCP requests recorded yet.', 'emcp-tools' ); ?></p></div>
	<?php else : ?>
		<form method="post" style="margin:10px 0;">
			<?php wp_nonce_field( 'emcp_mcp_log_clear' ); ?>
			<button type="submit" name="emcp_mcp_log_clear" value="1" class="button"><?php esc_html_e( 'Clear log', 'emcp-tools' ); ?></button>
		</form>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When (UTC)', 'emcp-tools' ); ?></th>
					<th><?php esc_html_e( 'Client', 'emcp-tools' ); ?></th>
					<th><?php esc_html_e( 'Tool', 'emcp-tools' ); ?></th>
					<th><?php esc_html_e( 'Status', 'emcp-tools' ); ?></th>
					<th><?php esc_html_e( 'Duration', 'emcp-tools' ); ?></th>
					<th><?php esc_html_e( 'Request ID', 'emcp-tools' ); ?></th>
					<th><?php esc_html_e( 'Error', 'emcp-tools' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $emcp_log as $emcp_row ) : ?>
					<?php $emcp_status = (string) ( $emcp_row['status'] ?? '' ); ?>
					<tr>
						<td><?php echo esc_html( gmdate( 'Y-m-d H:i:s', (int) ( $emcp_row['ts'] ?? 0 ) ) ); ?></td>
						<td><?php echo esc_html( (string) ( $emcp_row['client'] ?? '' ) ); ?></td>
						<td><code><?php echo esc_html( (string) ( '' !== (string) ( $emcp_row['tool'] ?? '' ) ? $emcp_row['tool'] : ( $emcp_row['method'] ?? '' ) ) ); ?></code></td>
						<td>
							<span style="<?php echo ( 'error' === $emcp_status || ( is_numeric( $emcp_status ) && (int) $emcp_status >= 400 ) ) ? 'color:#b32d2e;font-weight:600' : 'color:#1a7f4b'; ?>">
								<?php echo esc_html( $emcp_status ); ?>
							</span>
						</td>
						<td><?php echo esc_html( (int) ( $emcp_row['ms'] ?? 0 ) ); ?> ms</td>
						<td><code style="font-size:11px;"><?php echo esc_html( (string) ( $emcp_row['req_id'] ?? '' ) ); ?></code></td>
						<?php
						// The short failure reason is always kept; the full error only under WP_DEBUG.
						$emcp_reason = '' !== (string) ( $emcp_row['failure_reason'] ?? '' ) ? (string) $emcp_row['failure_reason'] : (string) ( $emcp_row['error'] ?? '' );
						?>
						<td style="max-width:340px;word-break:break-word;font-size:12px;color:#b32d2e;"><?php echo esc_html( $emcp_reason ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
