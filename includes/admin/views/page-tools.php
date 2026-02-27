<?php
/**
 * Tools tab view for the Elementor MCP admin settings page.
 *
 * Displays all MCP tools grouped by category with toggle checkboxes.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var Elementor_MCP_Admin $this */
$all_tools     = $this->get_all_tools();
$disabled      = get_option( Elementor_MCP_Admin::OPTION_DISABLED_TOOLS, array() );
$enabled_count = $this->get_enabled_tool_count();
$total_count   = $this->get_total_tool_count();
?>

<form method="post" action="options.php" id="elementor-mcp-tools-form">
	<?php settings_fields( Elementor_MCP_Admin::SETTINGS_GROUP ); ?>

	<p class="description">
		<?php
		printf(
			/* translators: %1$d: enabled count, %2$d: total count */
			esc_html__( '%1$d of %2$d tools enabled.', 'elementor-mcp' ),
			$enabled_count,
			$total_count
		);
		?>
	</p>

	<div class="elementor-mcp-bulk-actions">
		<button type="button" class="button elementor-mcp-enable-all"><?php esc_html_e( 'Enable All', 'elementor-mcp' ); ?></button>
		<button type="button" class="button elementor-mcp-disable-all"><?php esc_html_e( 'Disable All', 'elementor-mcp' ); ?></button>
	</div>

	<?php foreach ( $all_tools as $category_id => $category ) : ?>
		<div class="elementor-mcp-category" data-category="<?php echo esc_attr( $category_id ); ?>">
			<h2 class="elementor-mcp-category-header">
				<?php echo esc_html( $category['label'] ); ?>
				<span class="elementor-mcp-category-count">
					<?php
					$cat_total   = count( $category['tools'] );
					$cat_enabled = 0;
					foreach ( $category['tools'] as $slug => $tool ) {
						if ( ! in_array( $slug, $disabled, true ) ) {
							$cat_enabled++;
						}
					}
					printf(
						/* translators: %1$d: enabled, %2$d: total */
						esc_html__( '(%1$d/%2$d)', 'elementor-mcp' ),
						$cat_enabled,
						$cat_total
					);
					?>
				</span>
				<span class="elementor-mcp-category-actions">
					<button type="button" class="button-link elementor-mcp-cat-enable-all"><?php esc_html_e( 'All', 'elementor-mcp' ); ?></button>
					<span class="elementor-mcp-separator">|</span>
					<button type="button" class="button-link elementor-mcp-cat-disable-all"><?php esc_html_e( 'None', 'elementor-mcp' ); ?></button>
				</span>
			</h2>

			<div class="elementor-mcp-tools-grid">
				<?php foreach ( $category['tools'] as $slug => $tool ) : ?>
					<?php $is_enabled = ! in_array( $slug, $disabled, true ); ?>
					<label class="elementor-mcp-tool-card <?php echo esc_attr( $is_enabled ? 'is-enabled' : 'is-disabled' ); ?>">
						<input
							type="checkbox"
							name="<?php echo esc_attr( Elementor_MCP_Admin::OPTION_DISABLED_TOOLS ); ?>[]"
							value="<?php echo esc_attr( $slug ); ?>"
							<?php checked( $is_enabled ); ?>
						/>
						<span class="elementor-mcp-tool-info">
							<span class="elementor-mcp-tool-name">
								<?php echo esc_html( $tool['label'] ); ?>
								<?php foreach ( $tool['badges'] as $badge ) : ?>
									<span class="elementor-mcp-badge elementor-mcp-badge--<?php echo esc_attr( $badge ); ?>">
										<?php echo esc_html( $badge ); ?>
									</span>
								<?php endforeach; ?>
							</span>
							<span class="elementor-mcp-tool-desc"><?php echo esc_html( $tool['description'] ); ?></span>
							<code class="elementor-mcp-tool-slug"><?php echo esc_html( $slug ); ?></code>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>

	<?php submit_button( __( 'Save Changes', 'elementor-mcp' ) ); ?>
</form>
