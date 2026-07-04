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

/**
 * Render one toggle-switch row.
 *
 * @param string $name  Option key.
 * @param bool   $on    Current state.
 * @param string $title Row title.
 * @param string $desc  Row description.
 * @param bool   $child Whether this is an indented sub-toggle.
 */
$emcp_io_toggle = static function ( $name, $on, $title, $desc, $child = false ) {
	printf(
		'<label class="emcp-switch emcp-io-toggle%s">
			<input type="checkbox" name="%s" value="1"%s />
			<span class="elementor-mcp-toggle" aria-hidden="true"><span class="elementor-mcp-toggle-track"></span></span>
			<span class="emcp-io-toggle-text">
				<span class="emcp-io-toggle-title">%s</span>
				<span class="emcp-io-toggle-desc">%s</span>
			</span>
		</label>',
		$child ? ' emcp-io-toggle--child' : '',
		esc_attr( $name ),
		checked( $on, true, false ),
		esc_html( $title ),
		esc_html( $desc )
	);
};
?>
<div class="emcp-io">

	<?php
	$emcp_io_toggle(
		$p . 'compress',
		$settings['compress'],
		__( 'Compress images on upload', 'emcp-tools' ),
		__( 'Re-encode generated image sizes to shrink their file size.', 'emcp-tools' )
	);

	$emcp_io_toggle(
		$p . 'webp',
		$settings['webp'],
		__( 'Generate WebP versions', 'emcp-tools' ),
		__( 'Create a .webp copy of each image size on upload.', 'emcp-tools' )
	);

	$emcp_io_toggle(
		$p . 'webp_serve',
		$settings['webp_serve'],
		__( 'Serve WebP on the frontend', 'emcp-tools' ),
		__( 'Send WebP to browsers that support it. MCP & AI Chat always use WebP regardless of this.', 'emcp-tools' ),
		true
	);
	?>

	<hr class="emcp-io-sep" />

	<div class="emcp-io-field">
		<div class="emcp-io-field-head">
			<label for="<?php echo esc_attr( $p . 'quality' ); ?>"><?php esc_html_e( 'Quality', 'emcp-tools' ); ?></label>
			<output class="emcp-io-range-out" for="<?php echo esc_attr( $p . 'quality' ); ?>"><?php echo esc_html( (string) $settings['quality'] ); ?></output>
		</div>
		<input
			type="range"
			id="<?php echo esc_attr( $p . 'quality' ); ?>"
			name="<?php echo esc_attr( $p . 'quality' ); ?>"
			class="emcp-io-range"
			min="1"
			max="100"
			step="1"
			value="<?php echo esc_attr( (string) $settings['quality'] ); ?>"
		/>
		<div class="emcp-io-range-scale"><span><?php esc_html_e( 'Smaller files', 'emcp-tools' ); ?></span><span><?php esc_html_e( 'Higher quality', 'emcp-tools' ); ?></span></div>
	</div>

	<div class="emcp-io-field">
		<div class="emcp-io-field-head">
			<label for="<?php echo esc_attr( $p . 'max_dimension' ); ?>"><?php esc_html_e( 'Max dimension cap', 'emcp-tools' ); ?></label>
		</div>
		<div class="emcp-io-inline">
			<input
				type="number"
				id="<?php echo esc_attr( $p . 'max_dimension' ); ?>"
				name="<?php echo esc_attr( $p . 'max_dimension' ); ?>"
				class="emcp-io-number"
				min="0"
				step="1"
				value="<?php echo esc_attr( (string) $settings['max_dimension'] ); ?>"
			/>
			<span class="emcp-io-unit"><?php esc_html_e( 'px', 'emcp-tools' ); ?></span>
		</div>
		<span class="emcp-io-hint"><?php esc_html_e( 'Downscale large uploads to this width/height. 0 = off.', 'emcp-tools' ); ?></span>
	</div>

	<hr class="emcp-io-sep" />

	<?php
	$emcp_io_toggle(
		$p . 'keep_originals',
		$settings['keep_originals'],
		__( 'Keep backups of originals', 'emcp-tools' ),
		__( 'Store an untouched copy of every file so optimization stays reversible.', 'emcp-tools' )
	);
	?>
</div>
