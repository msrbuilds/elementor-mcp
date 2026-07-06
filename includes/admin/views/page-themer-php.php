<?php
/**
 * EMCP Themer — PHP Templates review page.
 *
 * @package EMCP_Tools
 * @var array      $templates List of summaries.
 * @var array|null $detail    Full record when viewing one.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap emcp-themer-php-wrap">
	<h1><?php esc_html_e( 'EMCP Themer — PHP Templates', 'emcp-tools' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'PHP templates authored via MCP. Review the code and validation, then attach one to a template on its edit screen (Display Conditions box → “Render with PHP template”). A template only runs once attached.', 'emcp-tools' ); ?>
	</p>

	<?php if ( is_array( $detail ) && ! isset( $detail['error'] ) ) : ?>
		<h2><?php echo esc_html( $detail['title'] ); ?> <code><?php echo esc_html( $detail['type'] ); ?></code></h2>
		<?php $val = $detail['validation']; ?>
		<?php if ( ! empty( $val['findings'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Validation findings:', 'emcp-tools' ); ?></strong></p>
			<ul>
			<?php foreach ( $val['findings'] as $f ) : ?>
				<li><strong><?php echo esc_html( $f['severity'] ); ?></strong>: <?php echo esc_html( $f['message'] ); ?> <?php echo $f['line'] ? esc_html( '(line ' . (int) $f['line'] . ')' ) : ''; ?></li>
			<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p style="color:#008a20;">&#10003; <?php esc_html_e( 'No validation findings.', 'emcp-tools' ); ?></p>
		<?php endif; ?>
		<textarea readonly rows="18" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $detail['code'] ); ?></textarea>
		<p><a class="button" href="<?php echo esc_url( add_query_arg( array( 'post_type' => EMCP_Tools_Themer_CPT::POST_TYPE, 'page' => 'emcp-themer-php' ), admin_url( 'edit.php' ) ) ); ?>">&laquo; <?php esc_html_e( 'Back to list', 'emcp-tools' ); ?></a></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Title', 'emcp-tools' ); ?></th>
				<th><?php esc_html_e( 'Type', 'emcp-tools' ); ?></th>
				<th><?php esc_html_e( 'Compiled', 'emcp-tools' ); ?></th>
				<th><?php esc_html_e( 'Last error', 'emcp-tools' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php if ( empty( $templates ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No PHP templates yet. Ask your AI agent to create one with create-theme-php-template.', 'emcp-tools' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $templates as $t ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( add_query_arg( array( 'post_type' => EMCP_Tools_Themer_CPT::POST_TYPE, 'page' => 'emcp-themer-php', 'view' => (int) $t['template_id'] ), admin_url( 'edit.php' ) ) ); ?>"><?php echo esc_html( $t['title'] ); ?></a></td>
						<td><code><?php echo esc_html( $t['type'] ); ?></code></td>
						<td><?php echo $t['compiled'] ? '&#10003;' : '&mdash;'; ?></td>
						<td><?php echo esc_html( $t['last_error'] ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this PHP template?', 'emcp-tools' ) ); ?>');">
								<input type="hidden" name="action" value="emcp_themer_php_delete">
								<input type="hidden" name="template_id" value="<?php echo (int) $t['template_id']; ?>">
								<?php wp_nonce_field( 'emcp_themer_php_delete_' . (int) $t['template_id'] ); ?>
								<button type="submit" class="button-link delete"><?php esc_html_e( 'Delete', 'emcp-tools' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
