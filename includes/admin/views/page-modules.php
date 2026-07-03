<?php
/**
 * Modules tab: one card per registered module with a master on/off toggle,
 * tier badge, availability notice, and (when active) the module's knobs.
 * A single Settings-API form posts the active-modules list + all module keys.
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

		<div class="emcp-modules-grid">
			<?php
			foreach ( $modules as $module ) :
				$active    = $module->is_active();
				$available = $module->is_available();
				$is_pro    = 'pro' === $module->tier();
				?>
				<div class="emcp-module-card<?php echo $active ? ' is-active' : ''; ?>">
					<div class="emcp-module-card-head">
						<label class="emcp-module-toggle">
							<input
								type="checkbox"
								name="<?php echo esc_attr( EMCP_Tools_Module::OPTION_ACTIVE ); ?>[]"
								value="<?php echo esc_attr( $module->id() ); ?>"
								<?php checked( $active ); ?>
								<?php disabled( ! $available ); ?>
							/>
							<span class="emcp-module-title"><?php echo esc_html( $module->title() ); ?></span>
						</label>
						<span class="emcp-module-badge emcp-module-badge--<?php echo $is_pro ? 'pro' : 'free'; ?>">
							<?php echo $is_pro ? esc_html__( 'Pro', 'emcp-tools' ) : esc_html__( 'Free', 'emcp-tools' ); ?>
						</span>
					</div>

					<p class="emcp-module-desc"><?php echo esc_html( $module->description() ); ?></p>

					<?php if ( ! $available ) : ?>
						<p class="emcp-module-unavailable">
							<?php esc_html_e( 'Not available on this server (WebP support missing in the image editor).', 'emcp-tools' ); ?>
						</p>
					<?php elseif ( $active ) : ?>
						<div class="emcp-module-settings"><?php $module->render_settings(); ?></div>
					<?php endif; ?>

					<?php
					// Bulk optimizer controls live under the image-optimization card.
					if ( $active && $available && EMCP_Tools_Image_Optimization_Module::ID === $module->id() ) :
						?>
						<div class="emcp-module-bulk">
							<button type="button" class="button" id="emcp-bulk-optimize"><?php esc_html_e( 'Optimize existing library', 'emcp-tools' ); ?></button>
							<button type="button" class="button" id="emcp-bulk-restore"><?php esc_html_e( 'Restore originals', 'emcp-tools' ); ?></button>
							<div class="emcp-bulk-progress" hidden>
								<div class="emcp-bulk-bar"><span></span></div>
								<span class="emcp-bulk-status"></span>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php submit_button( __( 'Save Modules', 'emcp-tools' ) ); ?>
	</form>
</div>
