<?php
/**
 * EMCP Themer / Ultimate Addons for Elementor conflict handling.
 *
 * UAE was formerly named Header Footer Elementor. Its hooks, class and CPT still
 * use the HFE names, so this class keeps them for the things it filters.
 *
 * Both systems build headers and footers and inject them into the same render
 * slots. With both enabled the page can end up with two headers, or with
 * whichever plugin hooked last silently winning, which looks like a random bug
 * to the site owner.
 *
 * This class does two things:
 *
 *  1. Warns. An admin notice explains the clash and asks the owner to pick one:
 *     turn off the Themer module, or stop using HFE for headers/footers.
 *  2. Resolves it deterministically in the meantime. EMCP Themer takes
 *     priority: when Themer has a template for a slot on this request, HFE's
 *     own render gate for that slot is filtered off, so exactly one header and
 *     one footer render. HFE keeps rendering any slot Themer does not claim, so
 *     an existing HFE-only footer still works.
 *
 * This lives in the FREE tree because the clash exists whenever the (free)
 * Themer module and HFE are both active, independently of the Pro HFE tools.
 *
 * @package EMCP_Tools
 * @since   3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects and resolves the Themer/HFE overlap.
 *
 * @since 3.6.0
 */
class EMCP_Tools_Themer_HFE_Conflict {

	/**
	 * Option that lets an admin dismiss the notice.
	 *
	 * @var string
	 */
	const OPTION_DISMISSED = 'emcp_tools_hfe_conflict_dismissed';

	/**
	 * Wires the notice and the render-priority filters.
	 *
	 * Called from the Themer module's register(), so it only runs when the
	 * Themer module is actually on.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! self::hfe_active() ) {
			return;
		}

		// Themer wins. Filter HFE's render gates for any slot Themer claims.
		add_filter( 'enable_hfe_render_header', array( __CLASS__, 'filter_header' ), 20 );
		add_filter( 'enable_hfe_render_footer', array( __CLASS__, 'filter_footer' ), 20 );
		add_filter( 'enable_hfe_render_before_footer', array( __CLASS__, 'filter_footer' ), 20 );

		if ( is_admin() ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
			add_action( 'admin_post_emcp_tools_dismiss_hfe_conflict', array( __CLASS__, 'handle_dismiss' ) );
		}
	}

	/**
	 * True when Ultimate Addons for Elementor (formerly Header Footer
	 * Elementor) is active. Keys off the plugin's own identifiers.
	 *
	 * @return bool
	 */
	public static function hfe_active(): bool {
		return class_exists( 'Header_Footer_Elementor' ) || post_type_exists( 'elementor-hf' );
	}

	/**
	 * True when both systems are live and would compete.
	 *
	 * @return bool
	 */
	public static function in_conflict(): bool {
		return self::hfe_active()
			&& class_exists( 'EMCP_Tools_Themer_Module' )
			&& EMCP_Tools_Themer_Module::is_enabled();
	}

	// ---------------------------------------------------------------------
	// Render priority
	// ---------------------------------------------------------------------

	/**
	 * Suppresses HFE's header when Themer supplies one for this request.
	 *
	 * @param bool $enabled HFE's own decision.
	 * @return bool
	 */
	public static function filter_header( $enabled ) {
		return self::themer_claims( 'header' ) ? false : $enabled;
	}

	/**
	 * Suppresses HFE's footer when Themer supplies one for this request.
	 *
	 * @param bool $enabled HFE's own decision.
	 * @return bool
	 */
	public static function filter_footer( $enabled ) {
		return self::themer_claims( 'footer' ) ? false : $enabled;
	}

	/**
	 * Whether Themer resolved a template for the given slot on this request.
	 *
	 * Deliberately conservative: any failure to resolve returns false, so HFE
	 * keeps rendering and the site never loses its header or footer because of
	 * this integration.
	 *
	 * @param string $slot header|footer.
	 * @return bool
	 */
	protected static function themer_claims( string $slot ): bool {
		if ( is_admin() || ! class_exists( 'EMCP_Tools_Themer_Render_Controller' ) ) {
			return false;
		}

		$slots = EMCP_Tools_Themer_Render_Controller::slots();
		return is_array( $slots ) && ! empty( $slots[ $slot ] );
	}

	// ---------------------------------------------------------------------
	// Admin notice
	// ---------------------------------------------------------------------

	/**
	 * Renders the conflict notice.
	 *
	 * @return void
	 */
	public static function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! self::in_conflict() ) {
			return;
		}
		if ( get_option( self::OPTION_DISMISSED ) ) {
			return;
		}

		$modules_url = admin_url( 'admin.php?page=emcp-tools-modules' );
		$plugins_url = admin_url( 'plugins.php' );
		$dismiss_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=emcp_tools_dismiss_hfe_conflict' ),
			'emcp_tools_dismiss_hfe_conflict'
		);
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'EMCP Themer and Ultimate Addons for Elementor are both active.', 'emcp-tools' ); ?></strong>
			</p>
			<p>
				<?php
				esc_html_e(
					'Both build headers and footers and inject them into the same place, so a page can end up with two headers. To avoid surprises, pick one system: turn off the EMCP Themer module, or stop using Ultimate Addons for Elementor for headers and footers.',
					'emcp-tools'
				);
				?>
			</p>
			<p>
				<?php
				esc_html_e(
					'Until you choose, EMCP Themer takes priority: where Themer has a matching template, its header or footer renders and the Ultimate Addons one is skipped. Any slot Themer does not claim still renders from Ultimate Addons for Elementor.',
					'emcp-tools'
				);
				?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $modules_url ); ?>">
					<?php esc_html_e( 'Manage EMCP Modules', 'emcp-tools' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( $plugins_url ); ?>">
					<?php esc_html_e( 'Manage Plugins', 'emcp-tools' ); ?>
				</a>
				<a class="button-link" href="<?php echo esc_url( $dismiss_url ); ?>">
					<?php esc_html_e( 'Dismiss', 'emcp-tools' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Stores the dismissal and returns where the admin came from.
	 *
	 * @return void
	 */
	public static function handle_dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'emcp_tools_dismiss_hfe_conflict' );

		update_option( self::OPTION_DISMISSED, '1' );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
