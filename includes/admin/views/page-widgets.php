<?php
/**
 * Widget Builder tab view.
 *
 * Pro users: a table of AI-generated custom Elementor widgets with status,
 * last-error, view spec/PHP, activate/deactivate, and delete. The widgets are
 * created by AI agents through the MCP tools and live in an isolated uploads
 * sandbox — this screen is the human management / kill-switch surface.
 * Free users: upgrade CTA.
 *
 * @package Elementor_MCP
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$elementor_mcp_wb_pro = class_exists( 'Elementor_MCP_Widget_Store' ) && Elementor_MCP_Widget_Store::user_has_access();
$elementor_mcp_wb_url = function_exists( 'elementor_mcp_upgrade_url' ) ? elementor_mcp_upgrade_url() : '#';
?>

<div class="elementor-mcp-widget-builder">

	<div class="elementor-mcp-pro-prompts">
		<div class="elementor-mcp-pro-prompts-header">
			<div class="elementor-mcp-pro-prompts-heading">
				<h2>
					<?php esc_html_e( 'Widget Builder', 'elementor-mcp' ); ?>
					<span class="elementor-mcp-badge elementor-mcp-badge--pro">PRO</span>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Custom Elementor widgets your AI agent generated through the MCP tools. They live in an isolated sandbox under wp-content/uploads — never in your theme, core, or other plugins. Active widgets appear in the Elementor panel under "Custom (EMCP)".', 'elementor-mcp' ); ?>
				</p>
			</div>
		</div>

		<?php if ( ! $elementor_mcp_wb_pro ) : ?>

			<div class="elementor-mcp-pro-cta">
				<p>
					<?php esc_html_e( 'The Widget Builder is a Pro feature. Upgrade to let AI agents design and ship custom Elementor widgets.', 'elementor-mcp' ); ?>
				</p>
				<a class="button button-primary" href="<?php echo esc_url( $elementor_mcp_wb_url ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Upgrade to Pro', 'elementor-mcp' ); ?>
				</a>
			</div>

		<?php else : ?>

			<?php $elementor_mcp_wb_list = Elementor_MCP_Widget_Store::list_widgets( 'any' ); ?>

			<div class="notice notice-warning inline" style="margin: 12px 0;">
				<p>
					<strong><?php esc_html_e( 'Heads up:', 'elementor-mcp' ); ?></strong>
					<?php esc_html_e( 'These widgets are PHP compiled by this plugin from an AI-supplied spec (the AI never writes raw PHP). Output is escaped by control type. You can deactivate or delete any widget here at any time.', 'elementor-mcp' ); ?>
				</p>
			</div>

			<?php if ( empty( $elementor_mcp_wb_list ) ) : ?>

				<p class="description" style="margin-top: 16px;">
					<?php esc_html_e( 'No custom widgets yet. Ask your AI agent to create one with the create-custom-widget tool.', 'elementor-mcp' ); ?>
				</p>

			<?php else : ?>

				<table class="widefat striped elementor-mcp-widgets-table" data-nonce="<?php echo esc_attr( wp_create_nonce( 'elementor_mcp_widgets' ) ); ?>" style="margin-top: 16px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Widget', 'elementor-mcp' ); ?></th>
							<th><?php esc_html_e( 'Machine name', 'elementor-mcp' ); ?></th>
							<th><?php esc_html_e( 'Status', 'elementor-mcp' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'elementor-mcp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $elementor_mcp_wb_list as $elementor_mcp_w ) :
							$elementor_mcp_wid    = (int) $elementor_mcp_w['widget_id'];
							$elementor_mcp_active = ( 'active' === $elementor_mcp_w['status'] );
							?>
							<tr data-widget-id="<?php echo esc_attr( (string) $elementor_mcp_wid ); ?>">
								<td>
									<strong><?php echo esc_html( $elementor_mcp_w['title'] ); ?></strong>
									<?php if ( ! empty( $elementor_mcp_w['last_error'] ) ) : ?>
										<br /><span style="color:#b32d2e;font-size:12px;">
											<?php
											printf(
												/* translators: %s: error message */
												esc_html__( 'Auto-deactivated after an error: %s', 'elementor-mcp' ),
												esc_html( $elementor_mcp_w['last_error'] )
											);
											?>
										</span>
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html( $elementor_mcp_w['widget_name'] ); ?></code></td>
								<td>
									<span class="elementor-mcp-badge <?php echo $elementor_mcp_active ? 'elementor-mcp-badge--pro' : ''; ?>">
										<?php echo $elementor_mcp_active ? esc_html__( 'Active', 'elementor-mcp' ) : esc_html__( 'Inactive', 'elementor-mcp' ); ?>
									</span>
								</td>
								<td>
									<button type="button" class="button elementor-mcp-wb-toggle" data-status="<?php echo $elementor_mcp_active ? 'draft' : 'active'; ?>">
										<?php echo $elementor_mcp_active ? esc_html__( 'Deactivate', 'elementor-mcp' ) : esc_html__( 'Activate', 'elementor-mcp' ); ?>
									</button>
									<button type="button" class="button elementor-mcp-wb-delete">
										<?php esc_html_e( 'Delete', 'elementor-mcp' ); ?>
									</button>
									<details style="margin-top: 6px;">
										<summary style="cursor:pointer;"><?php esc_html_e( 'View code', 'elementor-mcp' ); ?></summary>
										<pre style="max-height:320px;overflow:auto;background:#1e1e1e;color:#d4d4d4;padding:10px;border-radius:6px;font-size:12px;"><?php echo esc_html( Elementor_MCP_Widget_Store::get_php( $elementor_mcp_wid ) ); ?></pre>
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
							post( 'elementor_mcp_toggle_widget', b ).then( function ( res ) {
								if ( res && res.success ) { window.location.reload(); }
								else { e.target.disabled = false; alert( ( res && res.data && res.data.message ) || 'Failed.' ); }
							} ).catch( function () { e.target.disabled = false; } );
						}

						if ( e.target.classList.contains( 'elementor-mcp-wb-delete' ) ) {
							/* global confirm */
							if ( ! confirm( '<?php echo esc_js( __( 'Delete this widget permanently? Pages using it will lose it.', 'elementor-mcp' ) ); ?>' ) ) { return; }
							e.target.disabled = true;
							var d = new FormData();
							d.append( 'widget_id', id );
							post( 'elementor_mcp_delete_widget', d ).then( function ( res ) {
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
