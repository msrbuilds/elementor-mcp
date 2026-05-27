<?php
/**
 * Wraps the Freemius pricing screen with branded chrome.
 *
 * Uses Freemius's `templates/pricing.php` filter to inject a branded header
 * above the pricing iframe and a value-prop footer below it. The iframe
 * content itself is served cross-origin by Freemius and cannot be styled.
 *
 * @package Elementor_MCP
 * @since   1.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pricing screen wrapper.
 *
 * @since 1.6.1
 */
class Elementor_MCP_Pricing_Page {

	/**
	 * Slug suffix Freemius uses for the pricing submenu page.
	 *
	 * @var string
	 */
	const PAGE_SLUG_SUFFIX = '-pricing';

	/**
	 * Initialize hooks.
	 *
	 * @since 1.6.1
	 */
	public function init(): void {
		if ( ! function_exists( 'emcp_pro_fs' ) ) {
			return;
		}

		emcp_pro_fs()->add_filter( 'templates/pricing.php', array( $this, 'wrap_pricing_template' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue the plugin's admin CSS on the pricing screen so the wrapper
	 * shares the styling already used on the EMCP Tools admin pages.
	 *
	 * @since 1.6.1
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( Elementor_MCP_Admin::PAGE_SLUG . self::PAGE_SLUG_SUFFIX !== $page ) {
			return;
		}

		wp_enqueue_style(
			'elementor-mcp-admin',
			ELEMENTOR_MCP_URL . 'assets/css/admin.css',
			array(),
			ELEMENTOR_MCP_VERSION
		);
	}

	/**
	 * Wrap the rendered pricing template with a branded header and footer.
	 *
	 * @since 1.6.1
	 *
	 * @param string $html The HTML produced by Freemius's pricing.php template.
	 * @return string Wrapped HTML.
	 */
	public function wrap_pricing_template( string $html ): string {
		ob_start();
		$this->render_header();
		echo $html;
		$this->render_footer();
		return ob_get_clean();
	}

	/**
	 * Render the branded header above the pricing iframe.
	 *
	 * @since 1.6.1
	 */
	private function render_header(): void {
		?>
		<div class="elementor-mcp-pricing-chrome">
			<div class="elementor-mcp-header elementor-mcp-pricing-header">
				<span class="elementor-mcp-header-icon">
					<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
				</span>
				<div class="elementor-mcp-header-info">
					<h2 class="elementor-mcp-header-title">
						<?php esc_html_e( 'Upgrade to EMCP Tools Pro', 'elementor-mcp' ); ?>
					</h2>
					<p class="elementor-mcp-header-subtitle">
						<?php esc_html_e( 'Unlock 50+ premium landing-page prompts, exclusive Elementor templates, and priority support — power your AI page-building with the full library.', 'elementor-mcp' ); ?>
					</p>
				</div>
			</div>

			<ul class="elementor-mcp-pricing-highlights">
				<li>
					<span class="elementor-mcp-pricing-check" aria-hidden="true">&#10003;</span>
					<strong><?php esc_html_e( '50+ Premium Prompts', 'elementor-mcp' ); ?></strong>
					<?php esc_html_e( '— curated, production-ready prompts for niches like SaaS, e-commerce, agencies, and more.', 'elementor-mcp' ); ?>
				</li>
				<li>
					<span class="elementor-mcp-pricing-check" aria-hidden="true">&#10003;</span>
					<strong><?php esc_html_e( 'Exclusive Page Templates', 'elementor-mcp' ); ?></strong>
					<?php esc_html_e( '— one-click apply, designed to convert.', 'elementor-mcp' ); ?>
				</li>
				<li>
					<span class="elementor-mcp-pricing-check" aria-hidden="true">&#10003;</span>
					<strong><?php esc_html_e( 'Priority Support', 'elementor-mcp' ); ?></strong>
					<?php esc_html_e( '— direct email and help center access.', 'elementor-mcp' ); ?>
				</li>
				<li>
					<span class="elementor-mcp-pricing-check" aria-hidden="true">&#10003;</span>
					<strong><?php esc_html_e( 'Lifetime Updates Option', 'elementor-mcp' ); ?></strong>
					<?php esc_html_e( '— pay once, own forever.', 'elementor-mcp' ); ?>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render the value-prop and FAQ section below the pricing iframe.
	 *
	 * @since 1.6.1
	 */
	private function render_footer(): void {
		?>
		<div class="elementor-mcp-pricing-chrome elementor-mcp-pricing-footer">
			<h3 class="elementor-mcp-pricing-footer-title">
				<?php esc_html_e( 'Frequently asked', 'elementor-mcp' ); ?>
			</h3>

			<div class="elementor-mcp-pricing-faq">
				<details>
					<summary><?php esc_html_e( 'Do I keep access if my subscription ends?', 'elementor-mcp' ); ?></summary>
					<p><?php esc_html_e( 'Yes. The plugin and your previously-downloaded premium assets keep working. You only lose access to updates and new premium content released after your subscription ends. Lifetime plans never expire.', 'elementor-mcp' ); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e( 'Can I upgrade or downgrade later?', 'elementor-mcp' ); ?></summary>
					<p><?php esc_html_e( 'Yes. Switch plans at any time from your account. Annual plans are pro-rated when upgrading mid-cycle.', 'elementor-mcp' ); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e( 'What\'s your refund policy?', 'elementor-mcp' ); ?></summary>
					<p><?php esc_html_e( 'Every paid plan comes with a 14-day, no-questions-asked refund. Try it on real projects — if it doesn\'t click, we\'ll send your money back.', 'elementor-mcp' ); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e( 'Does the free version stop working?', 'elementor-mcp' ); ?></summary>
					<p><?php esc_html_e( 'Never. The 118 MCP tools you have today stay available forever. Pro adds premium prompts, templates, and support on top.', 'elementor-mcp' ); ?></p>
				</details>
			</div>

			<p class="elementor-mcp-pricing-contact">
				<?php
				printf(
					/* translators: %s: contact link */
					esc_html__( 'Still have questions? %s and I\'ll get back to you within one business day.', 'elementor-mcp' ),
					'<a href="https://emcp.msrbuilds.com/about" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Get in touch', 'elementor-mcp' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
