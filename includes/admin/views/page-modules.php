<?php
/**
 * Modules tab: one card per registered module with a toggle switch (matching the
 * Tools tab), tier badge, availability notice, and (when active) the module's
 * knobs. A single Settings-API form posts the active-modules list + all module
 * keys.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$registry = class_exists( 'EMCP_Tools_Modules_Registry' ) ? EMCP_Tools_Modules_Registry::instance() : null;
$modules  = $registry ? $registry->all() : array();
?>
<form method="post" action="options.php" class="emcp-modules-form">
	<?php settings_fields( EMCP_Tools_Admin::SETTINGS_GROUP_MODULES ); ?>

	<div class="elementor-mcp-bulk-actions">
		<p class="elementor-mcp-tools-summary">
			<?php esc_html_e( 'Turn substantial features on or off. Each module is self-contained; some are free and some are Pro.', 'emcp-tools' ); ?>
		</p>
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Modules', 'emcp-tools' ); ?></button>
	</div>

	<div class="elementor-mcp-tools-grid emcp-modules-grid">
		<?php
		foreach ( $modules as $module ) :
			$active    = $module->is_active();
			$available = $module->is_available();
			$is_pro    = 'pro' === $module->tier();
			$state     = $active ? 'is-enabled' : 'is-disabled';
			if ( ! $available ) {
				$state .= ' is-unavailable';
			}
			?>
			<div class="elementor-mcp-tool-card emcp-module-card <?php echo esc_attr( $state ); ?>">
				<label class="emcp-module-head emcp-switch">
					<input
						type="checkbox"
						name="<?php echo esc_attr( EMCP_Tools_Module::OPTION_ACTIVE ); ?>[]"
						value="<?php echo esc_attr( $module->id() ); ?>"
						<?php checked( $active ); ?>
						<?php disabled( ! $available ); ?>
					/>
					<span class="elementor-mcp-toggle" aria-hidden="true">
						<span class="elementor-mcp-toggle-track"></span>
					</span>
					<span class="elementor-mcp-tool-info">
						<span class="elementor-mcp-tool-name">
							<?php echo esc_html( $module->title() ); ?>
							<span class="elementor-mcp-badge elementor-mcp-badge--<?php echo $is_pro ? 'pro' : 'free'; ?>">
								<?php echo $is_pro ? esc_html__( 'Pro', 'emcp-tools' ) : esc_html__( 'Free', 'emcp-tools' ); ?>
							</span>
						</span>
						<span class="elementor-mcp-tool-desc"><?php echo esc_html( $module->description() ); ?></span>
					</span>
				</label>

				<?php if ( ! $available ) : ?>
					<p class="emcp-module-unavailable">
						<?php esc_html_e( 'Not available on this server (WebP support is missing in the image editor).', 'emcp-tools' ); ?>
					</p>
				<?php elseif ( $active ) : ?>
					<div class="emcp-module-settings"><?php $module->render_settings(); ?></div>

					<?php if ( EMCP_Tools_Image_Optimization_Module::ID === $module->id() ) : ?>
						<div class="emcp-module-bulk">
							<button type="button" class="button" id="emcp-bulk-optimize"><?php esc_html_e( 'Optimize existing library', 'emcp-tools' ); ?></button>
							<button type="button" class="button" id="emcp-bulk-restore"><?php esc_html_e( 'Restore originals', 'emcp-tools' ); ?></button>
							<div class="emcp-bulk-progress" hidden>
								<div class="emcp-bulk-bar"><span></span></div>
								<span class="emcp-bulk-status"></span>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<?php submit_button( __( 'Save Modules', 'emcp-tools' ) ); ?>
</form>
