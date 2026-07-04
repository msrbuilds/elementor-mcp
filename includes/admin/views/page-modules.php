<?php
/**
 * Modules tab. The main form holds one simple card per module (toggle + tier
 * badge + description + a "Show Settings" button when active). Each module's
 * knobs live in a separate overlay with its own form + Save button (its own
 * settings group), rendered after the main form so forms never nest.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$registry = class_exists( 'EMCP_Tools_Modules_Registry' ) ? EMCP_Tools_Modules_Registry::instance() : null;
$modules  = $registry ? $registry->all() : array();
?>
<div class="emcp-modules-tab">
	<h2><?php esc_html_e( 'Modules', 'emcp-tools' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Turn substantial features on or off. Each module is self-contained; some are free and some are Pro.', 'emcp-tools' ); ?>
	</p>

	<form method="post" action="options.php">
		<?php settings_fields( EMCP_Tools_Admin::SETTINGS_GROUP_MODULES ); ?>

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
						<span class="elementor-mcp-toggle" aria-hidden="true"><span class="elementor-mcp-toggle-track"></span></span>
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
							<?php
							if ( EMCP_Tools_Image_Optimization_Module::ID === $module->id() ) {
								esc_html_e( 'Not available on this server (WebP support is missing in the image editor).', 'emcp-tools' );
							} else {
								esc_html_e( 'Not available — requires an active Pro license.', 'emcp-tools' );
							}
							?>
						</p>
					<?php elseif ( $active && $module->has_settings() ) : ?>
						<div class="emcp-module-card-actions">
							<button type="button" class="button emcp-module-settings-btn" data-modal="emcp-modal-<?php echo esc_attr( $module->id() ); ?>">
								<?php esc_html_e( 'Show Settings', 'emcp-tools' ); ?>
							</button>
						</div>
					<?php elseif ( $active && '' !== $module->settings_url() ) : ?>
						<div class="emcp-module-card-actions">
							<a class="button emcp-module-settings-btn" href="<?php echo esc_url( $module->settings_url() ); ?>">
								<?php
								/* translators: %s: module title */
								echo esc_html( sprintf( __( 'Configure %s →', 'emcp-tools' ), $module->title() ) );
								?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php submit_button( __( 'Save Modules', 'emcp-tools' ) ); ?>
	</form>

	<?php
	// Per-module settings overlays — outside the main form (no nested forms).
	foreach ( $modules as $module ) :
		if ( ! $module->is_active() || ! $module->is_available() || ! $module->has_settings() ) {
			continue;
		}
		$modal_id = 'emcp-modal-' . $module->id();
		?>
		<div class="emcp-modal" id="<?php echo esc_attr( $modal_id ); ?>" hidden>
			<div class="emcp-modal-backdrop" data-close></div>
			<div class="emcp-modal-panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: module title */ __( '%s settings', 'emcp-tools' ), $module->title() ) ); ?>">
				<div class="emcp-modal-head">
					<h2><?php echo esc_html( $module->title() ); ?></h2>
					<button type="button" class="emcp-modal-close" data-close aria-label="<?php esc_attr_e( 'Close', 'emcp-tools' ); ?>">
						<svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true"><path d="M6 6l8 8M14 6l-8 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
					</button>
				</div>

				<form method="post" action="options.php" class="emcp-modal-form">
					<?php settings_fields( $module->settings_group() ); ?>
					<div class="emcp-modal-body"><?php $module->render_settings(); ?></div>
					<div class="emcp-modal-foot">
						<?php submit_button( __( 'Save Settings', 'emcp-tools' ), 'primary', 'submit', false ); ?>
					</div>
				</form>

				<?php if ( EMCP_Tools_Image_Optimization_Module::ID === $module->id() ) : ?>
					<div class="emcp-modal-bulk">
						<p class="emcp-modal-bulk-title"><?php esc_html_e( 'Existing library', 'emcp-tools' ); ?></p>
						<div class="emcp-module-bulk">
							<button type="button" class="button" id="emcp-bulk-optimize"><?php esc_html_e( 'Optimize existing library', 'emcp-tools' ); ?></button>
							<button type="button" class="button" id="emcp-bulk-restore"><?php esc_html_e( 'Restore originals', 'emcp-tools' ); ?></button>
							<div class="emcp-bulk-progress" hidden>
								<div class="emcp-bulk-bar"><span></span></div>
								<span class="emcp-bulk-status"></span>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
