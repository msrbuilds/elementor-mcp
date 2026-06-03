<?php
/**
 * Global dismissible "Join our Facebook community" admin notice.
 *
 * Deliberately sequenced behind the Upgrade notice so we never stack two
 * banners on a user's dashboard. It only renders for admins who are NOT
 * currently being shown the upgrade banner — i.e. either:
 *   - Pro users (who never see the upgrade banner), or
 *   - free users who have already dismissed the upgrade banner.
 * Dismissed-per-user via user_meta — once dismissed, stays dismissed.
 *
 * @package EMCP_Tools
 * @since   1.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Community_Notice {

	/**
	 * User-meta key recording when this user dismissed the community notice.
	 * Value: Unix timestamp, or empty/false if never dismissed.
	 */
	const META_KEY = 'emcp_tools_community_notice_dismissed';

	/**
	 * AJAX nonce action name.
	 */
	const NONCE_ACTION = 'emcp_tools_dismiss_community_notice';

	/**
	 * The Facebook group URL.
	 */
	const GROUP_URL = 'https://www.facebook.com/groups/emcptools';

	public function init(): void {
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
		add_action( 'wp_ajax_emcp_tools_dismiss_community_notice', array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Decide whether the notice should render on the current request.
	 */
	private function should_show(): bool {
		// Admins only.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Hide if the current user has dismissed it.
		if ( get_user_meta( get_current_user_id(), self::META_KEY, true ) ) {
			return false;
		}

		// Hide on the EMCP Tools own admin screens — the header already carries
		// plenty of CTAs; keep those screens uncluttered.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' !== $page && 0 === strpos( $page, 'emcp-tools' ) ) {
			return false;
		}

		// Never stack with the upgrade banner. Only show once the upgrade banner
		// is out of the user's way: Pro users never see it; free users must have
		// already dismissed it.
		$is_pro = function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code();
		if ( ! $is_pro ) {
			$upgrade_meta_key = class_exists( 'EMCP_Tools_Upgrade_Notice' )
				? EMCP_Tools_Upgrade_Notice::META_KEY
				: 'emcp_tools_upgrade_notice_dismissed';
			if ( ! get_user_meta( get_current_user_id(), $upgrade_meta_key, true ) ) {
				return false;
			}
		}

		return true;
	}

	public function maybe_render(): void {
		if ( ! $this->should_show() ) {
			return;
		}

		$nonce = wp_create_nonce( self::NONCE_ACTION );
		?>
		<style>
		.emcp-community-banner {
			position: relative;
			overflow: hidden;
			margin: 20px 20px 0 2px;
			padding: 18px 22px;
			border-radius: 14px;
			color: #fff;
			background: #1e293b;
			border: 1px solid #0f172a;
			width: calc(100% - 80px);
		}
		.emcp-community-banner__dismiss {
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
		.emcp-community-banner__dismiss:hover { background: rgba(255, 255, 255, 0.22); }
		.emcp-community-banner__dismiss:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
		.emcp-community-banner__content {
			position: relative;
			z-index: 1;
			display: flex;
			align-items: center;
			gap: 18px;
			flex-wrap: wrap;
			padding-right: 28px;
		}
		.emcp-community-banner__icon {
			flex-shrink: 0;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 44px;
			height: 44px;
			border-radius: 10px;
			background: #1877f2;
		}
		.emcp-community-banner__text { display: flex; flex-direction: column; gap: 3px; min-width: 220px; flex: 1 1 320px; }
		.emcp-community-banner__eyebrow {
			font-size: 11px;
			font-weight: 700;
			letter-spacing: 0.1em;
			text-transform: uppercase;
			color: #94a3b8;
		}
		.emcp-community-banner__title { margin: 0; font-size: 16px; font-weight: 600; line-height: 1.3; color: #fff; }
		.emcp-community-banner__subtitle { margin: 0; font-size: 13px; color: rgba(255, 255, 255, 0.7); line-height: 1.4; }
		.emcp-community-banner__btn {
			display: inline-flex;
			align-items: center;
			gap: 7px;
			padding: 9px 18px;
			border-radius: 8px;
			font-size: 13px;
			font-weight: 600;
			text-decoration: none;
			background: #1877f2;
			color: #fff;
			flex-shrink: 0;
			transition: background-color 0.15s ease;
		}
		.emcp-community-banner__btn:hover { background: #166fe0; color: #fff; }
		.emcp-community-banner.is-dismissed {
			opacity: 0;
			transform: translateY(-8px);
			transition: opacity 0.25s ease, transform 0.25s ease;
			pointer-events: none;
		}
		@media (max-width: 782px) {
			.emcp-community-banner__btn { flex: 1 1 100%; justify-content: center; }
		}
		</style>

		<div class="emcp-community-banner" data-emcp-nonce="<?php echo esc_attr( $nonce ); ?>">
			<button type="button" class="emcp-community-banner__dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'emcp-tools' ); ?>">
				<svg viewBox="0 0 20 20" width="14" height="14" aria-hidden="true"><path d="M5.3 4.3a1 1 0 011.4 0L10 7.6l3.3-3.3a1 1 0 111.4 1.4L11.4 9l3.3 3.3a1 1 0 11-1.4 1.4L10 10.4l-3.3 3.3a1 1 0 11-1.4-1.4L8.6 9 5.3 5.7a1 1 0 010-1.4z" fill="currentColor"/></svg>
			</button>

			<div class="emcp-community-banner__content">
				<span class="emcp-community-banner__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="24" height="24"><path fill="#fff" d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.43c0-3.014 1.792-4.679 4.533-4.679 1.313 0 2.686.235 2.686.235v2.96h-1.514c-1.491 0-1.956.93-1.956 1.886v2.264h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
				</span>
				<div class="emcp-community-banner__text">
					<span class="emcp-community-banner__eyebrow"><?php esc_html_e( 'Community', 'emcp-tools' ); ?></span>
					<h2 class="emcp-community-banner__title"><?php esc_html_e( 'Join the EMCP Tools community on Facebook', 'emcp-tools' ); ?></h2>
					<p class="emcp-community-banner__subtitle"><?php esc_html_e( 'Share builds, get tips, request features, and hear about new releases first.', 'emcp-tools' ); ?></p>
				</div>
				<a class="emcp-community-banner__btn" href="<?php echo esc_url( self::GROUP_URL ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Join the Group', 'emcp-tools' ); ?>
					<svg viewBox="0 0 20 20" width="13" height="13" aria-hidden="true"><path d="M11 3a1 1 0 100 2h2.6L7.3 11.3a1 1 0 101.4 1.4L15 6.4V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z" fill="currentColor"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 100-2H5z" fill="currentColor"/></svg>
				</a>
			</div>
		</div>

		<script>
		( function () {
			var banner = document.querySelector( '.emcp-community-banner' );
			if ( ! banner ) return;
			var dismissBtn = banner.querySelector( '.emcp-community-banner__dismiss' );
			if ( ! dismissBtn ) return;
			dismissBtn.addEventListener( 'click', function () {
				banner.classList.add( 'is-dismissed' );
				var body = new URLSearchParams();
				body.append( 'action', 'emcp_tools_dismiss_community_notice' );
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
