<?php
/**
 * AI Chat admin view. The chat client (ai-chat.js) drives state, renders the
 * provider settings, the conversation sidebar, and the searchable model picker.
 *
 * @package EMCP_Tools
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_ai_has_access = class_exists( 'EMCP_Tools_AI_Chat_Provider' ) && EMCP_Tools_AI_Chat_Provider::has_access();
?>

<div class="emcp-ai-wrap">

<?php if ( ! $emcp_ai_has_access ) : ?>

	<div class="emcp-ai-upsell">
		<div class="emcp-ai-upsell-icon" aria-hidden="true">
			<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
		</div>
		<h2><?php esc_html_e( 'Build pages by chatting — right here in WordPress', 'emcp-tools' ); ?></h2>
		<p><?php esc_html_e( 'AI Chat is an EMCP Pro feature. Bring your own key from Anthropic, OpenAI, OpenRouter, or Google Gemini and tell the assistant what to build. Every tool call is shown live, and destructive actions always ask first. You pay the provider directly — we don\'t mark up tokens.', 'emcp-tools' ); ?></p>
		<a class="button button-primary button-hero" href="<?php echo esc_url( function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : '#' ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?>
		</a>
	</div>

<?php else : ?>

	<div id="emcp-ai-chat" class="emcp-ai-chat" data-state="loading">

		<!-- Loading -->
		<div class="emcp-ai-loading" data-emcp-ai-view="loading">
			<span class="spinner is-active"></span>
		</div>

		<!-- Settings (dedicated screen): providers, keys, default models -->
		<div class="emcp-ai-settings-screen" data-emcp-ai-view="settings" hidden>
			<div class="emcp-ai-settings-head">
				<h2><?php esc_html_e( 'AI Chat settings', 'emcp-tools' ); ?></h2>
				<button type="button" class="button" id="emcp-ai-back" hidden><?php esc_html_e( '← Back to chat', 'emcp-tools' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Connect one or more providers with your own API key (stored encrypted, used only from your browser), and pick a default model for each. You can switch provider and model per chat.', 'emcp-tools' ); ?></p>
			<div class="emcp-ai-providers" id="emcp-ai-providers"></div>
		</div>

		<!-- Chat -->
		<div class="emcp-ai-main" data-emcp-ai-view="chat" hidden>
			<div class="emcp-ai-layout">
				<aside class="emcp-ai-sidebar">
					<button type="button" class="button button-primary emcp-ai-newbtn" id="emcp-ai-new"><?php esc_html_e( '+ New chat', 'emcp-tools' ); ?></button>
					<div class="emcp-ai-convos" id="emcp-ai-convos"></div>
				</aside>

				<div class="emcp-ai-conv">
					<div class="emcp-ai-bar">
						<label class="emcp-ai-pick">
							<span><?php esc_html_e( 'Provider', 'emcp-tools' ); ?></span>
							<select id="emcp-ai-provider"></select>
						</label>
						<div class="emcp-ai-pick emcp-ai-pick--model">
							<span><?php esc_html_e( 'Model', 'emcp-tools' ); ?></span>
							<div id="emcp-ai-model-combo" class="emcp-ai-combo"></div>
						</div>
						<div class="emcp-ai-bar-actions">
							<button type="button" class="button-link" id="emcp-ai-settings-toggle"><?php esc_html_e( 'Settings', 'emcp-tools' ); ?></button>
						</div>
					</div>

					<div class="emcp-ai-messages" id="emcp-ai-messages"></div>

					<div class="emcp-ai-composer">
						<textarea id="emcp-ai-input" rows="1" placeholder="<?php esc_attr_e( 'Describe what to build, or ask the assistant to edit a page… (Shift+Enter for a new line)', 'emcp-tools' ); ?>"></textarea>
						<button type="button" class="button button-primary" id="emcp-ai-send"><?php esc_html_e( 'Send', 'emcp-tools' ); ?></button>
					</div>
					<p class="emcp-ai-disclaimer description"><?php esc_html_e( 'The assistant acts on your live site with your permissions. Destructive actions ask for approval. Review what it builds.', 'emcp-tools' ); ?></p>
				</div>
			</div>
		</div>

	</div>

<?php endif; ?>

</div>
