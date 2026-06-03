<?php
/**
 * Sandbox tab view (custom widgets; extensible to other sandboxed code).
 *
 * Pro users: a table of AI-generated custom Elementor widgets with status,
 * last-error, view spec/PHP, activate/deactivate, and delete. The widgets are
 * created by AI agents through the MCP tools and live in an isolated uploads
 * sandbox — this screen is the human management / kill-switch surface.
 * Free users: upgrade CTA.
 *
 * @package EMCP_Tools
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_tools_wb_pro = class_exists( 'EMCP_Tools_Widget_Store' ) && EMCP_Tools_Widget_Store::user_has_access();
$emcp_tools_wb_url = function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : '#';
?>

<div class="elementor-mcp-widget-builder">

	<div class="elementor-mcp-pro-prompts">
		<div class="elementor-mcp-pro-prompts-header">
			<div class="elementor-mcp-pro-prompts-heading">
				<h2>
					<?php esc_html_e( 'Sandbox', 'emcp-tools' ); ?>
					<span class="elementor-mcp-badge elementor-mcp-badge--pro">PRO</span>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Code your AI agent generated through the MCP tools — starting with custom Elementor widgets. Everything lives in an isolated sandbox under wp-content/uploads, never in your theme, core, or other plugins. Active widgets appear in the Elementor panel under "Custom (EMCP)".', 'emcp-tools' ); ?>
				</p>
			</div>
		</div>

		<?php if ( ! $emcp_tools_wb_pro ) : ?>

			<div class="elementor-mcp-pro-cta">
				<p>
					<?php esc_html_e( 'The Sandbox is a Pro feature. Upgrade to let AI agents design and ship custom Elementor widgets in an isolated sandbox.', 'emcp-tools' ); ?>
				</p>
				<a class="button button-primary" href="<?php echo esc_url( $emcp_tools_wb_url ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?>
				</a>
			</div>

		<?php else : ?>

			<?php $emcp_tools_wb_list = EMCP_Tools_Widget_Store::list_widgets( 'any' ); ?>

			<div class="notice notice-warning inline" style="margin: 12px 0;">
				<p>
					<strong><?php esc_html_e( 'Heads up:', 'emcp-tools' ); ?></strong>
					<?php esc_html_e( 'These widgets are PHP compiled by this plugin from an AI-supplied spec (the AI never writes raw PHP). Output is escaped by control type. You can deactivate or delete any widget here at any time.', 'emcp-tools' ); ?>
				</p>
			</div>

			<?php if ( empty( $emcp_tools_wb_list ) ) : ?>

				<p class="description" style="margin-top: 16px;">
					<?php esc_html_e( 'No custom widgets yet. Ask your AI agent to create one with the create-custom-widget tool.', 'emcp-tools' ); ?>
				</p>

			<?php else : ?>

				<table class="widefat striped elementor-mcp-widgets-table" data-nonce="<?php echo esc_attr( wp_create_nonce( 'emcp_tools_widgets' ) ); ?>" style="margin-top: 16px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Widget', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Machine name', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Status', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'emcp-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $emcp_tools_wb_list as $emcp_tools_w ) :
							$emcp_tools_wid    = (int) $emcp_tools_w['widget_id'];
							$emcp_tools_active = ( 'active' === $emcp_tools_w['status'] );
							?>
							<tr data-widget-id="<?php echo esc_attr( (string) $emcp_tools_wid ); ?>">
								<td>
									<strong><?php echo esc_html( $emcp_tools_w['title'] ); ?></strong>
									<?php if ( ! empty( $emcp_tools_w['last_error'] ) ) : ?>
										<br /><span style="color:#b32d2e;font-size:12px;">
											<?php
											printf(
												/* translators: %s: error message */
												esc_html__( 'Auto-deactivated after an error: %s', 'emcp-tools' ),
												esc_html( $emcp_tools_w['last_error'] )
											);
											?>
										</span>
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html( $emcp_tools_w['widget_name'] ); ?></code></td>
								<td>
									<span class="elementor-mcp-badge <?php echo $emcp_tools_active ? 'elementor-mcp-badge--pro' : ''; ?>">
										<?php echo $emcp_tools_active ? esc_html__( 'Active', 'emcp-tools' ) : esc_html__( 'Inactive', 'emcp-tools' ); ?>
									</span>
								</td>
								<td>
									<button type="button" class="button elementor-mcp-wb-toggle" data-status="<?php echo $emcp_tools_active ? 'draft' : 'active'; ?>">
										<?php echo $emcp_tools_active ? esc_html__( 'Deactivate', 'emcp-tools' ) : esc_html__( 'Activate', 'emcp-tools' ); ?>
									</button>
									<button type="button" class="button elementor-mcp-wb-delete">
										<?php esc_html_e( 'Delete', 'emcp-tools' ); ?>
									</button>
									<details style="margin-top: 6px;">
										<summary style="cursor:pointer;"><?php esc_html_e( 'View code', 'emcp-tools' ); ?></summary>
										<pre style="max-height:320px;overflow:auto;background:#1e1e1e;color:#d4d4d4;padding:10px;border-radius:6px;font-size:12px;"><?php echo esc_html( EMCP_Tools_Widget_Store::get_php( $emcp_tools_wid ) ); ?></pre>
									</details>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<script>
				( function () {
					var table = document.querySelector( '.elementor-mcp-widgets-table' );
					if ( ! table ) { return; }
					var nonce = table.getAttribute( 'data-nonce' ) || '';
					var ajaxUrl = window.ajaxurl || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

					function post( action, body ) {
						body.append( 'action', action );
						body.append( 'nonce', nonce );
						return fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) { return r.json(); } );
					}

					table.addEventListener( 'click', function ( e ) {
						var row = e.target.closest( 'tr[data-widget-id]' );
						if ( ! row ) { return; }
						var id = row.getAttribute( 'data-widget-id' );

						if ( e.target.classList.contains( 'elementor-mcp-wb-toggle' ) ) {
							e.target.disabled = true;
							var b = new FormData();
							b.append( 'widget_id', id );
							b.append( 'status', e.target.getAttribute( 'data-status' ) );
							post( 'emcp_tools_toggle_widget', b ).then( function ( res ) {
								if ( res && res.success ) { window.location.reload(); }
								else { e.target.disabled = false; alert( ( res && res.data && res.data.message ) || 'Failed.' ); }
							} ).catch( function () { e.target.disabled = false; } );
						}

						if ( e.target.classList.contains( 'elementor-mcp-wb-delete' ) ) {
							/* global confirm */
							if ( ! confirm( '<?php echo esc_js( __( 'Delete this widget permanently? Pages using it will lose it.', 'emcp-tools' ) ); ?>' ) ) { return; }
							e.target.disabled = true;
							var d = new FormData();
							d.append( 'widget_id', id );
							post( 'emcp_tools_delete_widget', d ).then( function ( res ) {
								if ( res && res.success ) { row.parentNode.removeChild( row ); }
								else { e.target.disabled = false; alert( ( res && res.data && res.data.message ) || 'Failed.' ); }
							} ).catch( function () { e.target.disabled = false; } );
						}
					} );
				} )();
				</script>

			<?php endif; ?>

		<?php endif; ?>

	</div>
</div>
