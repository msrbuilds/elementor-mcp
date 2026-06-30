<?php
/**
 * Global dismissible "Upgrade to Pro" admin notice.
 *
 * Renders on every WP admin screen (except the EMCP Tools own screens to
 * avoid redundancy with the header CTA there). Dismissed-per-user via
 * user_meta — once dismissed, stays dismissed indefinitely.
 *
 * @package EMCP_Tools
 * @since   1.7.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Upgrade_Notice {

	/**
	 * User-meta key that records when this user dismissed the notice.
	 * Value: Unix timestamp, or empty/false if never dismissed.
	 */
	const META_KEY = 'emcp_tools_upgrade_notice_dismissed';

	/**
	 * AJAX nonce action name.
	 */
	const NONCE_ACTION = 'emcp_tools_dismiss_upgrade_notice';

	public function init(): void {
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
		add_action( 'wp_ajax_emcp_tools_dismiss_upgrade_notice', array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Decide whether the notice should render on the current request.
	 */
	private function should_show(): bool {
		// Admins only — non-managers can't act on the CTA anyway.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Hide for sites with an active Pro license.
		if ( function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code() ) {
			return false;
		}

		// Hide if the current user has dismissed it.
		if ( get_user_meta( get_current_user_id(), self::META_KEY, true ) ) {
			return false;
		}

		// Hide on the EMCP Tools own admin screens — the header already shows
		// an Upgrade button there, and we don't want two CTAs stacked.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' !== $page && 0 === strpos( $page, 'emcp-tools' ) ) {
			return false;
		}

		return true;
	}

	public function maybe_render(): void {
		if ( ! $this->should_show() ) {
			return;
		}

		$upgrade_url = function_exists( 'emcp_tools_upgrade_url' )
			? emcp_tools_upgrade_url()
			: 'https://emcptools.com/pricing';
		$docs_url    = 'https://emcptools.com/docs/prompts/premium-library';
		$nonce       = wp_create_nonce( self::NONCE_ACTION );
		?>
		<style>
		.emcp-upgrade-banner {
			position: relative;
			overflow: hidden;
			margin: 20px 20px 0 2px;
			padding: 24px 28px;
			border-radius: 14px;
			color: #fff;
			background: linear-gradient(120deg, #4338ca 0%, #6366f1 45%, #8b5cf6 100%);
			box-shadow: 0 10px 30px -10px rgba(76, 29, 149, 0.45);
			isolation: isolate;
			width: calc(100% - 80px);
		}
		.emcp-upgrade-banner__shape {
			position: absolute;
			pointer-events: none;
			z-index: 0;
		}
		.emcp-upgrade-banner__shape--a { top: -60px;  right: -40px;  width: 260px; height: 260px; }
		.emcp-upgrade-banner__shape--b { bottom: -30px; right: 30%;   width: 130px; height: 130px; opacity: 0.9; }
		.emcp-upgrade-banner__shape--c { top: 30%;     left: -20px;   width: 110px; height: 110px; }
		.emcp-upgrade-banner__dismiss {
			position: absolute;
			top: 12px;
			right: 12px;
			z-index: 2;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 26px;
			height: 26px;
			padding: 0;
			border: 0;
			border-radius: 999px;
			background: rgba(255, 255, 255, 0.12);
			color: #fff;
			cursor: pointer;
			transition: background-color 0.15s ease;
		}
		.emcp-upgrade-banner__dismiss:hover { background: rgba(255, 255, 255, 0.22); }
		.emcp-upgrade-banner__dismiss:focus-visible {
			outline: 2px solid #fff;
			outline-offset: 2px;
		}
		.emcp-upgrade-banner__content {
			position: relative;
			z-index: 1;
			display: grid;
			grid-template-columns: minmax(0, 1.6fr) minmax(0, 1.4fr);
			gap: 20px 32px;
			align-items: center;
		}
		.emcp-upgrade-banner__lede { display: flex; flex-direction: column; gap: 8px; }
		.emcp-upgrade-banner__eyebrow {
			display: inline-block;
			width: max-content;
			padding: 4px 10px;
			font-size: 11px;
			font-weight: 700;
			letter-spacing: 0.1em;
			text-transform: uppercase;
			background: rgba(255, 255, 255, 0.16);
			border: 1px solid rgba(255, 255, 255, 0.25);
			border-radius: 999px;
			color: #fff;
		}
		.emcp-upgrade-banner__title {
			margin: 0;
			font-size: 20px;
			font-weight: 600;
			line-height: 1.3;
			color: #fff;
		}
		.emcp-upgrade-banner__features {
			margin: 0;
			padding: 0;
			list-style: none;
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 10px 20px;
			font-size: 13px;
		}
		.emcp-upgrade-banner__features li {
			margin: 0;
			display: flex;
			align-items: center;
			gap: 10px;
			color: rgba(255, 255, 255, 0.92);
		}
		.emcp-upgrade-banner__feature-text {
			display: flex;
			flex-direction: column;
			line-height: 1.35;
		}
		.emcp-upgrade-banner__feature-text strong {
			color: #fff;
			font-weight: 600;
		}
		.emcp-upgrade-banner__feature-text > span {
			color: rgba(255, 255, 255, 0.78);
			font-size: 12px;
		}
		.emcp-upgrade-banner__check {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 18px;
			height: 18px;
			border-radius: 999px;
			background: rgba(255, 255, 255, 0.2);
			font-size: 10px;
			font-weight: 700;
			color: #fff;
			flex-shrink: 0;
		}
		.emcp-upgrade-banner__actions {
			grid-column: 1 / -1;
			display: flex;
			gap: 10px;
			margin-top: 4px;
			flex-wrap: wrap;
		}
		.emcp-upgrade-banner__btn {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 9px 18px;
			border-radius: 8px;
			font-size: 13px;
			font-weight: 600;
			text-decoration: none;
			transition: transform 0.1s ease, background-color 0.15s ease, color 0.15s ease;
		}
		.emcp-upgrade-banner__btn--primary {
			background: #fff;
			color: #4338ca;
			box-shadow: 0 4px 14px -4px rgba(0, 0, 0, 0.25);
		}
		.emcp-upgrade-banner__btn--primary:hover {
			color: #3730a3;
			transform: translateY(-1px);
		}
		.emcp-upgrade-banner__btn--ghost {
			color: #fff;
			background: transparent;
			border: 1px solid rgba(255, 255, 255, 0.35);
		}
		.emcp-upgrade-banner__btn--ghost:hover {
			background: rgba(255, 255, 255, 0.1);
			color: #fff;
		}
		.emcp-upgrade-banner.is-dismissed {
			opacity: 0;
			transform: translateY(-8px);
			transition: opacity 0.25s ease, transform 0.25s ease;
			pointer-events: none;
		}
		@media (max-width: 900px) {
			.emcp-upgrade-banner__content { grid-template-columns: 1fr; }
			.emcp-upgrade-banner__features { grid-template-columns: 1fr; }
		}
		</style>

		<div class="emcp-upgrade-banner" data-emcp-nonce="<?php echo esc_attr( $nonce ); ?>">

			<svg class="emcp-upgrade-banner__shape emcp-upgrade-banner__shape--a" viewBox="0 0 200 200" aria-hidden="true">
				<circle cx="100" cy="100" r="90" fill="rgba(255,255,255,0.08)" />
				<circle cx="100" cy="100" r="55" fill="rgba(255,255,255,0.06)" />
			</svg>
			<svg class="emcp-upgrade-banner__shape emcp-upgrade-banner__shape--b" viewBox="0 0 100 100" aria-hidden="true">
				<polygon points="50,5 95,80 5,80" fill="rgba(255,255,255,0.07)" />
			</svg>
			<svg class="emcp-upgrade-banner__shape emcp-upgrade-banner__shape--c" viewBox="0 0 100 100" aria-hidden="true">
				<rect x="10" y="10" width="80" height="80" rx="14" fill="rgba(255,255,255,0.05)" transform="rotate(18 50 50)" />
			</svg>

			<button type="button" class="emcp-upgrade-banner__dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'emcp-tools' ); ?>">
				<svg viewBox="0 0 20 20" width="14" height="14" aria-hidden="true"><path d="M5.3 4.3a1 1 0 011.4 0L10 7.6l3.3-3.3a1 1 0 111.4 1.4L11.4 9l3.3 3.3a1 1 0 11-1.4 1.4L10 10.4l-3.3 3.3a1 1 0 11-1.4-1.4L8.6 9 5.3 5.7a1 1 0 010-1.4z" fill="currentColor"/></svg>
			</button>

			<div class="emcp-upgrade-banner__content">
				<div class="emcp-upgrade-banner__lede">
					<span class="emcp-upgrade-banner__eyebrow"><?php esc_html_e( 'EMCP Tools Pro', 'emcp-tools' ); ?></span>
					<h2 class="emcp-upgrade-banner__title">
						<?php esc_html_e( 'Branded landing pages in minutes — straight from your AI.', 'emcp-tools' ); ?>
					</h2>
					<div class="emcp-upgrade-banner__actions">
					<a class="emcp-upgrade-banner__btn emcp-upgrade-banner__btn--primary" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?>
						<svg viewBox="0 0 20 20" width="14" height="14" aria-hidden="true"><path d="M11 3a1 1 0 100 2h2.6L7.3 11.3a1 1 0 101.4 1.4L15 6.4V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z" fill="currentColor"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 100-2H5z" fill="currentColor"/></svg>
					</a>
					<a class="emcp-upgrade-banner__btn emcp-upgrade-banner__btn--ghost" href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'What\'s included', 'emcp-tools' ); ?>
					</a>
				</div>
				</div>

				<ul class="emcp-upgrade-banner__features">
					<li>
						<span class="emcp-upgrade-banner__check" aria-hidden="true">&#10003;</span>
						<div class="emcp-upgrade-banner__feature-text">
							<strong><?php esc_html_e( '50+ Premium Prompts', 'emcp-tools' ); ?></strong>
							<span><?php esc_html_e( 'across 10 industries', 'emcp-tools' ); ?></span>
						</div>
					</li>
					<li>
						<span class="emcp-upgrade-banner__check" aria-hidden="true">&#10003;</span>
						<div class="emcp-upgrade-banner__feature-text">
							<strong><?php esc_html_e( 'Templates Library', 'emcp-tools' ); ?></strong>
							<span><?php esc_html_e( 'one-click apply to any page', 'emcp-tools' ); ?></span>
						</div>
					</li>
					<li>
						<span class="emcp-upgrade-banner__check" aria-hidden="true">&#10003;</span>
						<div class="emcp-upgrade-banner__feature-text">
							<strong><?php esc_html_e( 'Priority Support', 'emcp-tools' ); ?></strong>
							<span><?php esc_html_e( 'fast email responses', 'emcp-tools' ); ?></span>
						</div>
					</li>
					<li>
						<span class="emcp-upgrade-banner__check" aria-hidden="true">&#10003;</span>
						<div class="emcp-upgrade-banner__feature-text">
							<strong><?php esc_html_e( 'Lifetime Option', 'emcp-tools' ); ?></strong>
							<span><?php esc_html_e( 'pay once, own forever', 'emcp-tools' ); ?></span>
						</div>
					</li>
				</ul>

				
			</div>
		</div>

		<script>
		( function () {
			var banner = document.querySelector( '.emcp-upgrade-banner' );
			if ( ! banner ) return;
			var dismissBtn = banner.querySelector( '.emcp-upgrade-banner__dismiss' );
			if ( ! dismissBtn ) return;
			dismissBtn.addEventListener( 'click', function () {
				banner.classList.add( 'is-dismissed' );
				var body = new URLSearchParams();
				body.append( 'action', 'emcp_tools_dismiss_upgrade_notice' );
				body.append( 'nonce', banner.getAttribute( 'data-emcp-nonce' ) || '' );
				fetch( ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString(),
				} ).finally( function () {
					setTimeout( function () {
						if ( banner.parentNode ) banner.parentNode.removeChild( banner );
					}, 280 );
				} );
			} );
		} )();
		</script>
		<?php
	}

	public function ajax_dismiss(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		update_user_meta( get_current_user_id(), self::META_KEY, time() );
		wp_send_json_success();
	}
}
