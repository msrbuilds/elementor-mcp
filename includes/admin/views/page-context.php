<?php
/**
 * Context tab: site-wide guidance the MCP server delivers to AI agents as
 * `instructions` (applied automatically at connection).
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_tools_ctx       = EMCP_Tools_Site_Context::get_context();
$emcp_tools_ctx_on    = EMCP_Tools_Site_Context::is_enabled();
$emcp_tools_ctx_base  = EMCP_Tools_Site_Context::default_base();
$emcp_tools_ctx_final = EMCP_Tools_Site_Context::compose_instructions( $emcp_tools_ctx_base );
?>

<div class="elementor-mcp-context">
	<div class="elementor-mcp-section">
		<h2><?php esc_html_e( 'Site Context', 'emcp-tools' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Stable, site-wide guidance that every AI agent connecting to this site receives automatically and applies to all of its work here — your business identity, brand voice, content rules, technical constraints, and guardrails. It is delivered as the MCP server\'s instructions at connection; changes take effect the next time an agent connects.', 'emcp-tools' ); ?>
		</p>

		<form method="post" action="options.php" class="elementor-mcp-context-form">
			<?php settings_fields( EMCP_Tools_Admin::SETTINGS_GROUP_CONTEXT ); ?>

			<label class="elementor-mcp-activate-toggle">
				<input type="checkbox" name="<?php echo esc_attr( EMCP_Tools_Site_Context::OPTION_ENABLED ); ?>" value="1" <?php checked( $emcp_tools_ctx_on ); ?> />
				<strong><?php esc_html_e( 'Send this context to connected AI agents', 'emcp-tools' ); ?></strong>
			</label>

			<p class="elementor-mcp-context-toolbar">
				<button type="button" class="button" id="elementor-mcp-context-template"><?php esc_html_e( 'Insert starter template', 'emcp-tools' ); ?></button>
				<span class="elementor-mcp-context-counter" id="elementor-mcp-context-counter" aria-live="polite"></span>
			</p>

			<textarea
				id="elementor-mcp-context-text"
				name="<?php echo esc_attr( EMCP_Tools_Site_Context::OPTION_CONTEXT ); ?>"
				class="large-text code"
				rows="16"
				maxlength="<?php echo esc_attr( (string) EMCP_Tools_Site_Context::MAX_CHARS ); ?>"
				placeholder="<?php esc_attr_e( '# About this site&#10;&#10;Write guidance in Markdown…', 'emcp-tools' ); ?>"
			><?php echo esc_textarea( $emcp_tools_ctx ); ?></textarea>

			<p class="elementor-mcp-activate-note elementor-mcp-activate-note--security">
				<strong><?php esc_html_e( 'Note:', 'emcp-tools' ); ?></strong>
				<?php esc_html_e( 'Whatever you write here steers every connected agent. Keep it accurate, and avoid instructions you would not want an agent to follow.', 'emcp-tools' ); ?>
			</p>

			<?php submit_button( __( 'Save Context', 'emcp-tools' ) ); ?>
		</form>
	</div>

	<div class="elementor-mcp-section">
		<h2><?php esc_html_e( 'What agents receive', 'emcp-tools' ); ?></h2>
		<p class="description"><?php esc_html_e( 'The exact instructions string sent to an AI client when it connects (server overview + your context).', 'emcp-tools' ); ?></p>
		<pre class="elementor-mcp-context-preview" id="elementor-mcp-context-preview"><?php echo esc_html( $emcp_tools_ctx_final ); ?></pre>
	</div>
</div>
