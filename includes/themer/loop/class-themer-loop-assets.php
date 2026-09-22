<?php
/**
 * Front-end assets for the loop elements.
 *
 * One script and one stylesheet, plus Swiper for carousels: Elementor's own
 * `swiper` handle when it is registered (front end and editor preview), else
 * a bundled Swiper 8. One handle decides at enqueue time, so a page never
 * loads two copies. `enqueue()` must be called from an element's own render,
 * not from an early `wp_enqueue_scripts` callback, because the handle choice
 * depends on Elementor having already registered its `swiper` handle by then.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.18.0
 */
class EMCP_Tools_Themer_Loop_Assets {

	const SCRIPT          = 'emcp-themer-loop';
	const STYLE           = 'emcp-themer-loop';
	const SWIPER_FALLBACK = 'emcp-swiper';
	const SWIPER_VERSION  = '8.4.5';

	/** @var bool */
	private static $registered = false;

	/** Hook registration on every surface that renders a loop. */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	/** Register handles (idempotent). */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;

		$dir = defined( 'EMCP_TOOLS_DIR' ) ? EMCP_TOOLS_DIR : '';
		$url = defined( 'EMCP_TOOLS_URL' ) ? EMCP_TOOLS_URL : '';
		$fallback_ver = defined( 'EMCP_TOOLS_VERSION' ) ? EMCP_TOOLS_VERSION : '0';
		$script_ver   = $fallback_ver;
		$style_ver    = $fallback_ver;
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && '' !== $dir ) {
			if ( file_exists( $dir . 'assets/js/themer-loop.js' ) ) {
				$script_ver = (string) filemtime( $dir . 'assets/js/themer-loop.js' );
			}
			if ( file_exists( $dir . 'assets/css/themer-loop.css' ) ) {
				$style_ver = (string) filemtime( $dir . 'assets/css/themer-loop.css' );
			}
		}

		// Each fallback is gated on its own Elementor handle: a `swiper`
		// script does not guarantee a `swiper` style is registered too, and
		// the reverse, so the two are decided independently.
		if ( ! wp_script_is( 'swiper', 'registered' ) ) {
			wp_register_script( self::SWIPER_FALLBACK, $url . 'assets/lib/swiper/swiper-bundle.min.js', array(), self::SWIPER_VERSION, true );
		}
		if ( ! wp_style_is( 'swiper', 'registered' ) ) {
			wp_register_style( self::SWIPER_FALLBACK, $url . 'assets/lib/swiper/swiper-bundle.min.css', array(), self::SWIPER_VERSION );
		}

		wp_register_style( self::STYLE, $url . 'assets/css/themer-loop.css', array(), $style_ver );
		wp_register_script( self::SCRIPT, $url . 'assets/js/themer-loop.js', array(), $script_ver, true );
		wp_localize_script( self::SCRIPT, 'emcpThemerLoop', self::localize_data() );
	}

	/** @return string */
	public static function swiper_handle(): string {
		return wp_script_is( 'swiper', 'registered' ) ? 'swiper' : self::SWIPER_FALLBACK;
	}

	/** @return string */
	public static function swiper_style_handle(): string {
		return wp_style_is( 'swiper', 'registered' ) ? 'swiper' : self::SWIPER_FALLBACK;
	}

	/**
	 * Enqueue for a render.
	 *
	 * @param bool $carousel Whether a carousel is on the page.
	 */
	public static function enqueue( bool $carousel ): void {
		self::register();
		if ( $carousel ) {
			wp_enqueue_style( self::swiper_style_handle() );
			wp_enqueue_script( self::swiper_handle() );
		}
		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( self::SCRIPT );
	}

	/**
	 * Data for the script. The nonce keeps a logged-in visitor logged in on
	 * REST pages; anonymous visitors send none.
	 *
	 * @return array{rest:string,nonce:string,rest_nonce_url:string,i18n:array}
	 */
	public static function localize_data(): array {
		return array(
			'rest'           => rest_url( 'emcp-tools/v1/themer/loop' ),
			'nonce'          => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'rest_nonce_url' => admin_url( 'admin-ajax.php?action=rest-nonce' ),
			'i18n'           => array(
				'loading' => __( 'Loading', 'emcp-tools' ),
				'error'   => __( 'Could not load more posts.', 'emcp-tools' ),
			),
		);
	}

	/** Test seam. */
	public static function reset_for_tests(): void {
		self::$registered = false;
	}
}
