<?php
/**
 * AI Chat tab — Pro upsell for free users. Reuses the Templates/Prompts CTA
 * banner (.elementor-mcp-prompts-cta) so the upsell is visually consistent.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_tools_upgrade_url = function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : '#';
?>
<div class="elementor-mcp-prompts-cta">
	<div class="elementor-mcp-prompts-cta-content">
		<h3><?php esc_html_e( 'Unlock AI Chat', 'emcp-tools' ); ?></h3>
		<p><?php esc_html_e( 'Chat with any AI model right inside WordPress and the Elementor editor to build pages, add sections, and edit widgets — powered by the EMCP tools. Bring your own key (Claude, OpenAI, Gemini, or OpenRouter) and let the assistant do the work.', 'emcp-tools' ); ?></p>
		<a href="<?php echo esc_url( $emcp_tools_upgrade_url ); ?>" class="button button-primary elementor-mcp-prompts-cta-btn" target="_blank" rel="noopener noreferrer">
			<svg viewBox="0 0 20 20" width="16" height="16" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
			<?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?>
		</a>
	</div>
</div>
