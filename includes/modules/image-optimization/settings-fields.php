<?php
/**
 * Knobs for the Image Optimization module card. Included by render_settings()
 * with $settings in scope. Rendered inside the Modules settings <form>.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p = EMCP_Tools_Image_Optimization_Module::PREFIX;
?>
<div class="emcp-module-knobs">
	<label class="emcp-module-knob">
		<input type="checkbox" name="<?php echo esc_attr( $p . 'compress' ); ?>" value="1" <?php checked( $settings['compress'] ); ?> />
		<?php esc_html_e( 'Compress generated image sizes on upload', 'emcp-tools' ); ?>
	</label>
	<label class="emcp-module-knob">
		<input type="checkbox" name="<?php echo esc_attr( $p . 'webp' ); ?>" value="1" <?php checked( $settings['webp'] ); ?> />
		<?php esc_html_e( 'Generate & serve WebP versions', 'emcp-tools' ); ?>
	</label>
	<label class="emcp-module-knob">
		<?php esc_html_e( 'Quality (40–95)', 'emcp-tools' ); ?>
		<input type="number" min="40" max="95" name="<?php echo esc_attr( $p . 'quality' ); ?>" value="<?php echo esc_attr( (string) $settings['quality'] ); ?>" />
	</label>
	<label class="emcp-module-knob">
		<?php esc_html_e( 'Max dimension cap in px (0 = off)', 'emcp-tools' ); ?>
		<input type="number" min="0" name="<?php echo esc_attr( $p . 'max_dimension' ); ?>" value="<?php echo esc_attr( (string) $settings['max_dimension'] ); ?>" />
	</label>
	<label class="emcp-module-knob">
		<input type="checkbox" name="<?php echo esc_attr( $p . 'keep_originals' ); ?>" value="1" <?php checked( $settings['keep_originals'] ); ?> />
		<?php esc_html_e( 'Keep backups of originals (reversible)', 'emcp-tools' ); ?>
	</label>
</div>
