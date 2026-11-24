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

require_once __DIR__ . '/class-sale.php';

class EMCP_Tools_Upgrade_Notice {

	/**
	 * User-meta key that records when this user dismissed the notice.
	 * Value: Unix timestamp, or empty/false if never dismissed.
	 */
	const META_KEY = 'emcp_tools_upgrade_notice_dismissed';

	/**
	 * User-meta key for dismissing the BFCM 2026 sale banner. Separate from
	 * META_KEY so people who closed the regular banner still see the sale.
	 */
	const SALE_META_KEY = 'emcp_tools_bfcm2026_notice_dismissed';

	/**
	 * AJAX nonce action name.
	 */
	const NONCE_ACTION = 'emcp_tools_dismiss_upgrade_notice';

	public function init(): void {
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_emcp_tools_dismiss_upgrade_notice', array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Which banner to show: nothing once the sale banner was dismissed (it
	 * stays gone and no other banner replaces it), 'bfcm' while the sale
	 * runs, else 'upgrade' unless that one was dismissed, else ''.
	 *
	 * @param bool     $dismissed      The regular upgrade banner was dismissed.
	 * @param bool     $sale_dismissed The sale banner was dismissed.
	 * @param int|null $now            Unix time; defaults to now.
	 */
	public static function variant( bool $dismissed, bool $sale_dismissed, ?int $now = null ): string {
		// A dismissed sale banner stays gone: no other banner takes its place,
		// during the sale or after it.
		if ( $sale_dismissed ) {
			return '';
		}
		if ( EMCP_Tools_Sale::active( $now ) ) {
			return 'bfcm';
		}
		return $dismissed ? '' : 'upgrade';
	}

	/**
	 * The banner for the current request: '', 'upgrade' or 'bfcm'.
	 */
	private function current_variant(): string {
		// Admins only — non-managers can't act on the CTA anyway.
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		// Hide for sites with an active Pro license.
		if ( function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code() ) {
			return '';
		}

		// Hide on the EMCP Tools own admin screens — the announcement bar
		// there already carries the upgrade and sale slides.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' !== $page && 0 === strpos( $page, 'emcp-tools' ) ) {
			return '';
		}

		$user = get_current_user_id();
		return self::variant(
			(bool) get_user_meta( $user, self::META_KEY, true ),
			(bool) get_user_meta( $user, self::SALE_META_KEY, true )
		);
	}

	/**
	 * Decide whether the notice should render on the current request.
	 */
	private function should_show(): bool {
		return '' !== $this->current_variant();
	}

	/** Loads the banner's style and dismiss script only where it shows. */
	public function enqueue(): void {
		if ( ! $this->should_show() ) {
			return;
		}
		wp_enqueue_style( 'emcp-tools-upgrade-notice', EMCP_TOOLS_URL . 'assets/css/upgrade-notice.css', array(), EMCP_TOOLS_VERSION );
		wp_enqueue_script( 'emcp-tools-upgrade-notice', EMCP_TOOLS_URL . 'assets/js/upgrade-notice.js', array(), EMCP_TOOLS_VERSION, true );
	}

	public function maybe_render(): void {
		$variant = $this->current_variant();
		if ( 'bfcm' === $variant ) {
			$this->render_bfcm();
		} elseif ( 'upgrade' === $variant ) {
			$this->render_upgrade();
		}
	}

	/**
	 * BFCM 2026 sale banner. Shown instead of render_upgrade() until
	 * EMCP_Tools_Sale::END; the regular banner then returns by itself.
	 */
	private function render_bfcm(): void {
		$nonce = wp_create_nonce( self::NONCE_ACTION );
		$units = array(
			'd' => __( 'D', 'emcp-tools' ),
			'h' => __( 'H', 'emcp-tools' ),
			'm' => __( 'M', 'emcp-tools' ),
			's' => __( 'S', 'emcp-tools' ),
		);
		$left  = max( 0, EMCP_Tools_Sale::END - time() );
		$start = array(
			'd' => intdiv( $left, DAY_IN_SECONDS ),
			'h' => intdiv( $left % DAY_IN_SECONDS, HOUR_IN_SECONDS ),
			'm' => intdiv( $left % HOUR_IN_SECONDS, MINUTE_IN_SECONDS ),
			's' => $left % MINUTE_IN_SECONDS,
		);
		?>

		<div class="emcp-sale" data-emcp-nonce="<?php echo esc_attr( $nonce ); ?>" data-emcp-banner="bfcm" data-emcp-end="<?php echo esc_attr( (string) EMCP_Tools_Sale::END ); ?>" role="region" aria-label="<?php esc_attr_e( 'EMCP Black Friday sale', 'emcp-tools' ); ?>">

			<svg class="emcp-sale__grid emcp-sale__grid--l" viewBox="0 0 120 56" aria-hidden="true" preserveAspectRatio="xMinYMid slice">
				<rect x="0" y="0" width="14" height="14" /><rect x="28" y="0" width="14" height="14" class="b1" />
				<rect x="14" y="14" width="14" height="14" class="b2" /><rect x="56" y="14" width="14" height="14" />
				<rect x="0" y="28" width="14" height="14" class="b3" /><rect x="42" y="28" width="14" height="14" />
				<rect x="28" y="42" width="14" height="14" /><rect x="84" y="42" width="14" height="14" class="b1" />
			</svg>
			<svg class="emcp-sale__grid emcp-sale__grid--r" viewBox="0 0 120 56" aria-hidden="true" preserveAspectRatio="xMaxYMid slice">
				<rect x="106" y="0" width="14" height="14" class="b2" /><rect x="78" y="0" width="14" height="14" />
				<rect x="92" y="14" width="14" height="14" /><rect x="50" y="14" width="14" height="14" class="b3" />
				<rect x="106" y="28" width="14" height="14" /><rect x="64" y="28" width="14" height="14" class="b1" />
				<rect x="92" y="42" width="14" height="14" class="b2" /><rect x="22" y="42" width="14" height="14" />
			</svg>

			<button type="button" class="emcp-upgrade-banner__dismiss emcp-sale__dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'emcp-tools' ); ?>">
				<svg viewBox="0 0 20 20" width="12" height="12" aria-hidden="true"><path d="M5.3 4.3a1 1 0 011.4 0L10 7.6l3.3-3.3a1 1 0 111.4 1.4L11.4 9l3.3 3.3a1 1 0 11-1.4 1.4L10 10.4l-3.3 3.3a1 1 0 11-1.4-1.4L8.6 9 5.3 5.7a1 1 0 010-1.4z" fill="currentColor"/></svg>
			</button>

			<div class="emcp-sale__in">
				<div class="emcp-sale__brand">
					<img class="emcp-sale__logo" src="<?php echo esc_url( EMCP_TOOLS_URL . 'assets/img/icon-sm.png' ); ?>" alt="" width="44" height="44">
					<span class="emcp-sale__name">EMCP<small><?php esc_html_e( 'Tools', 'emcp-tools' ); ?></small></span>
				</div>

				<div class="emcp-sale__badge">
					<span class="emcp-sale__bf"><span><?php esc_html_e( 'Black', 'emcp-tools' ); ?></span><span><?php esc_html_e( 'Friday', 'emcp-tools' ); ?></span></span>
					<span class="emcp-sale__pill"><?php esc_html_e( 'Sale', 'emcp-tools' ); ?></span>
					<svg class="emcp-sale__flame" viewBox="0 0 32 40" aria-hidden="true">
						<path class="emcp-sale__flame-out" d="M16 1c2 7 9 10 11 18 2 9-4 19-11 19S3 31 5 22c1-6 6-8 6-14 3 2 4 5 4 8 2-3 2-9 1-15Z" />
						<path class="emcp-sale__flame-in" d="M16 18c1 4 6 6 6 11 0 5-3 8-6 8s-6-3-6-7c0-4 3-5 4-9 1 1 2 3 2 5 1-2 1-5 0-8Z" />
						<text x="16" y="34" text-anchor="middle">%</text>
					</svg>
				</div>

				<ul class="emcp-sale__deals">
					<li>
						<span class="emcp-sale__pct"><?php echo esc_html( EMCP_Tools_Sale::PLUGIN_PERCENT ); ?><i>%</i></span>
						<span class="emcp-sale__what"><b><?php esc_html_e( 'Off', 'emcp-tools' ); ?></b><?php esc_html_e( 'EMCP Pro', 'emcp-tools' ); ?></span>
					</li>
					<li>
						<span class="emcp-sale__pct"><?php echo esc_html( EMCP_Tools_Sale::CLOUD_PERCENT ); ?><i>%</i></span>
						<span class="emcp-sale__what"><b><?php esc_html_e( 'Off', 'emcp-tools' ); ?></b><?php esc_html_e( 'EMCP Cloud', 'emcp-tools' ); ?></span>
					</li>
					<li class="emcp-sale__ltd">
						<span class="emcp-sale__what"><b><?php esc_html_e( 'Lifetime', 'emcp-tools' ); ?></b><?php esc_html_e( 'deals, limited stock', 'emcp-tools' ); ?></span>
					</li>
				</ul>

				<div class="emcp-sale__foot">
				<div class="emcp-sale__clock">
					<span class="emcp-sale__ends"><?php esc_html_e( 'Ends in:', 'emcp-tools' ); ?></span>
					<span class="emcp-sale__digits" role="timer" aria-label="<?php esc_attr_e( 'Time left in the Black Friday sale', 'emcp-tools' ); ?>">
						<?php
						$i = 0;
						foreach ( $units as $key => $label ) {
							if ( $i++ > 0 ) {
								echo '<span class="emcp-sale__colon" aria-hidden="true">:</span>';
							}
							printf(
								'<span class="emcp-sale__unit"><span data-emcp-t="%1$s">%2$s</span><small>%3$s</small></span>',
								esc_attr( $key ),
								esc_html( str_pad( (string) $start[ $key ], 2, '0', STR_PAD_LEFT ) ),
								esc_html( $label )
							);
						}
						?>
					</span>
				</div>

				<a class="emcp-sale__cta" href="<?php echo esc_url( EMCP_Tools_Sale::URL ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Grab the deal', 'emcp-tools' ); ?> <span aria-hidden="true">&rarr;</span>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'emcp-tools' ); ?></span>
				</a>
				</div>
			</div>
		</div>

		<?php
	}

	/**
	 * The regular "Upgrade to Pro" banner, shown outside the sale.
	 */
	private function render_upgrade(): void {
		$upgrade_url = function_exists( 'emcp_tools_upgrade_url' )
			? emcp_tools_upgrade_url()
			: 'https://emcptools.com/pricing';
		$docs_url    = 'https://emcptools.com/pricing';
		$nonce       = wp_create_nonce( self::NONCE_ACTION );
		?>

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
						<?php esc_html_e( 'Give your AI a copilot for all of WordPress, not just Elementor.', 'emcp-tools' ); ?>
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
							<strong><?php esc_html_e( 'AI Chat in your editor', 'emcp-tools' ); ?></strong>
							<span><?php esc_html_e( 'build and edit by chatting, local or cloud models', 'emcp-tools' ); ?></span>
						</div>
					</li>
					<li>
						<span class="emcp-upgrade-banner__check" aria-hidden="true">&#10003;</span>
						<div class="emcp-upgrade-banner__feature-text">
							<strong><?php esc_html_e( 'Speaks your whole stack', 'emcp-tools' ); ?></strong>
							<span><?php esc_html_e( 'ACF, WooCommerce, SEO and form plugins', 'emcp-tools' ); ?></span>
						</div>
					</li>
					<li>
						<span class="emcp-upgrade-banner__check" aria-hidden="true">&#10003;</span>
						<div class="emcp-upgrade-banner__feature-text">
							<strong><?php esc_html_e( 'SEO and Accessibility toolkit', 'emcp-tools' ); ?></strong>
							<span><?php esc_html_e( 'audit and auto-fix any page', 'emcp-tools' ); ?></span>
						</div>
					</li>
					<li>
						<span class="emcp-upgrade-banner__check" aria-hidden="true">&#10003;</span>
						<div class="emcp-upgrade-banner__feature-text">
							<strong><?php esc_html_e( 'Widget Builder', 'emcp-tools' ); ?></strong>
							<span><?php esc_html_e( 'custom Elementor widgets from a prompt', 'emcp-tools' ); ?></span>
						</div>
					</li>
					<li>
						<span class="emcp-upgrade-banner__check" aria-hidden="true">&#10003;</span>
						<div class="emcp-upgrade-banner__feature-text">
							<strong><?php esc_html_e( '100 Premium Prompts', 'emcp-tools' ); ?></strong>
							<span><?php esc_html_e( 'landing pages and complete websites across 20 categories', 'emcp-tools' ); ?></span>
						</div>
					</li>
					<li>
						<span class="emcp-upgrade-banner__check" aria-hidden="true">&#10003;</span>
						<div class="emcp-upgrade-banner__feature-text">
							<strong><?php esc_html_e( 'Templates Library', 'emcp-tools' ); ?></strong>
							<span><?php esc_html_e( 'one-click, apply to any page', 'emcp-tools' ); ?></span>
						</div>
					</li>
				</ul>

				
			</div>
		</div>

		<?php
	}

	public function ajax_dismiss(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$banner = isset( $_POST['banner'] ) ? sanitize_key( wp_unslash( $_POST['banner'] ) ) : '';
		update_user_meta( get_current_user_id(), 'bfcm' === $banner ? self::SALE_META_KEY : self::META_KEY, time() );
		wp_send_json_success();
	}
}
