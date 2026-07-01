<?php
/**
 * Generic Pro-feature upsell fallback (free build).
 *
 * Shown when a Pro-only tab (AI Chat, Skills, …) is opened but the private Pro
 * overlay is absent, so the feature's own view file does not exist. Self-
 * contained: uses core WordPress button classes + a small scoped style block so
 * it renders without any Pro CSS (ai-chat.css et al. ship only in the Pro zip).
 * Flat colors, indigo accent, no gradients (brand design rules).
 *
 * Expects: $emcp_upsell_feature (string) — the feature name to headline.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_upsell_feature = isset( $emcp_upsell_feature ) ? (string) $emcp_upsell_feature : __( 'This feature', 'emcp-tools' );
$emcp_upsell_url     = function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : 'https://emcptools.com/pricing';
?>

<style>
	.emcp-pro-upsell { max-width: 640px; margin: 24px 0; padding: 40px 32px; text-align: center;
		background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; }
	.emcp-pro-upsell__badge { display: inline-block; margin-bottom: 16px; padding: 4px 12px;
		font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
		color: #4f46e5; background: #eef0fe; border-radius: 999px; }
	.emcp-pro-upsell h2 { margin: 0 0 10px; font-size: 22px; color: #1f2330; }
	.emcp-pro-upsell p { margin: 0 auto 24px; max-width: 480px; color: #6b7280; font-size: 14px; line-height: 1.6; }
	.emcp-pro-upsell .button-hero { background: #4f46e5; border-color: #4f46e5; }
</style>

<div class="emcp-pro-upsell">
	<span class="emcp-pro-upsell__badge"><?php esc_html_e( 'EMCP Pro', 'emcp-tools' ); ?></span>
	<h2>
		<?php
		/* translators: %s: Pro feature name, e.g. "AI Chat". */
		printf( esc_html__( '%s is an EMCP Pro feature', 'emcp-tools' ), esc_html( $emcp_upsell_feature ) );
		?>
	</h2>
	<p><?php esc_html_e( 'Upgrade to EMCP Pro to unlock this feature along with the full Pro toolkit — AI Chat, the SEO & Accessibility toolkit, the Widget Builder, Brand Kits, premium prompts and templates, and more.', 'emcp-tools' ); ?></p>
	<a class="button button-primary button-hero" href="<?php echo esc_url( $emcp_upsell_url ); ?>" target="_blank" rel="noopener noreferrer">
		<?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?>
	</a>
</div>
