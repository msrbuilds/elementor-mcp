<?php
/**
 * Front-end assets for the loop elements.
 *
 * One script and one stylesheet, plus Swiper for carousels: Elementor's own
 * `swiper` handle when it is registered (front end and editor preview), else
 * the bundled Swiper. One handle decides at enqueue time, so a page never
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

	/** @var bool Whether a deferred Swiper enqueue is hooked. */
	private static $swiper_pending = false;

	/** @var bool Whether the stylesheet handle is registered. */
	private static $style_registered = false;

	/** @var string[] Styles discovered after the document head. */
	private static $dynamic_handles = array();

	/** Generated design CSS uses the same queue on full pages and REST renders. */
	public static function enqueue_dynamic_css( int $template_id, int $post_id, string $css ): void {
		$handle = 'emcp-loop-dynamic-' . $template_id . '-' . $post_id;
		if ( in_array( $handle, self::$dynamic_handles, true ) ) {
			return;
		}
		wp_register_style( $handle, false, array(), EMCP_TOOLS_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
		if ( empty( self::$dynamic_handles ) ) {
			add_action( 'wp_footer', array( __CLASS__, 'print_dynamic_styles' ), 20 );
		}
		self::$dynamic_handles[] = $handle;
	}

	/** Print only queued styles that WordPress has not printed in the head. */
	public static function print_dynamic_styles(): void {
		wp_print_styles( self::$dynamic_handles );
	}

	/**
	 * Hook registration on every surface that renders a loop.
	 *
	 * Deliberately NOT rest_api_init: that fires before a cookie-but-no-nonce
	 * request is demoted to anonymous, and building the scripts object that
	 * early is exactly the first link in the nonce-leak chain the loop REST
	 * route now guards against (see the comment in
	 * EMCP_Tools_Themer_Loop_REST::handle()). The REST route already calls
	 * register() itself, after that guard has run.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'register' ) );
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
		self::$style_registered = true;
		wp_register_script( self::SCRIPT, $url . 'assets/js/themer-loop.js', array(), $script_ver, true );
		wp_localize_script( self::SCRIPT, 'emcpThemerLoop', self::localize_data() );
	}

	/**
	 * Register the loop stylesheet only (idempotent). The block editor needs
	 * it for the loop blocks' previews and it must be registered when the
	 * blocks are (on init). Unlike register(), it builds no script data (no
	 * nonce, no REST URL) and decides no Swiper handle, so it is safe that
	 * early on any request.
	 */
	public static function register_style(): void {
		if ( self::$style_registered ) {
			return;
		}
		self::$style_registered = true;
		$dir = defined( 'EMCP_TOOLS_DIR' ) ? EMCP_TOOLS_DIR : '';
		$url = defined( 'EMCP_TOOLS_URL' ) ? EMCP_TOOLS_URL : '';
		$ver = defined( 'EMCP_TOOLS_VERSION' ) ? EMCP_TOOLS_VERSION : '0';
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && '' !== $dir && file_exists( $dir . 'assets/css/themer-loop.css' ) ) {
			$ver = (string) filemtime( $dir . 'assets/css/themer-loop.css' );
		}
		wp_register_style( self::STYLE, $url . 'assets/css/themer-loop.css', array(), $ver );
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
	 * The Swiper handle a carousel widget may declare as a dependency.
	 *
	 * Elementor enqueues widget depends as each widget prints, which on a
	 * block theme is before wp_head (before Elementor registers `swiper` at
	 * wp_enqueue_scripts), and it stores them in `_elementor_page_assets` when
	 * a document saves over admin-ajax, REST or WP-CLI, where `swiper` is
	 * never registered. Naming the bundled fallback there would load a second
	 * Swiper once Elementor's own arrives. So the depends carry `swiper` only
	 * when Elementor has registered it, the chosen handle only in the editor
	 * or preview (which renders over AJAX and needs the depends), and nothing
	 * otherwise: the element's render-time enqueue() then defers the choice.
	 *
	 * @param bool $style  Whether the stylesheet handle is wanted.
	 * @param bool $editor Whether the request is the Elementor editor or preview.
	 * @return string[] Zero or one handle.
	 */
	public static function swiper_depends( bool $style, bool $editor ): array {
		$registered = $style ? wp_style_is( 'swiper', 'registered' ) : wp_script_is( 'swiper', 'registered' );
		if ( $registered ) {
			return array( 'swiper' );
		}
		if ( $editor ) {
			return array( $style ? self::swiper_style_handle() : self::swiper_handle() );
		}
		return array();
	}

	/**
	 * Enqueue for a render.
	 *
	 * @param bool $carousel Whether a carousel is on the page.
	 */
	public static function enqueue( bool $carousel ): void {
		self::register();
		if ( $carousel ) {
			if ( did_action( 'wp_enqueue_scripts' ) ) {
				self::enqueue_swiper();
			} elseif ( ! self::$swiper_pending ) {
				// A render before wp_head (a block theme renders its template
				// first; an Elementor widget can too) runs before Elementor
				// registers its own `swiper` at wp_enqueue_scripts priority 5.
				// Choosing now would pick the fallback, and a page that also
				// loads Elementor's Swiper would get two. Choose once the
				// action has registered everything.
				self::$swiper_pending = true;
				add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_swiper' ), 20 );
			}
		}
		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( self::SCRIPT );
	}

	/**
	 * Enqueue Swiper under the handle registered by now: Elementor's own
	 * `swiper` when it exists, else the bundled fallback (registered here if
	 * register() ran while Elementor's was still expected).
	 */
	public static function enqueue_swiper(): void {
		self::$swiper_pending = false;
		self::register();
		$url = defined( 'EMCP_TOOLS_URL' ) ? EMCP_TOOLS_URL : '';
		if ( ! wp_script_is( 'swiper', 'registered' ) && ! wp_script_is( self::SWIPER_FALLBACK, 'registered' ) ) {
			wp_register_script( self::SWIPER_FALLBACK, $url . 'assets/lib/swiper/swiper-bundle.min.js', array(), self::SWIPER_VERSION, true );
		}
		if ( ! wp_style_is( 'swiper', 'registered' ) && ! wp_style_is( self::SWIPER_FALLBACK, 'registered' ) ) {
			wp_register_style( self::SWIPER_FALLBACK, $url . 'assets/lib/swiper/swiper-bundle.min.css', array(), self::SWIPER_VERSION );
		}
		wp_enqueue_style( self::swiper_style_handle() );
		wp_enqueue_script( self::swiper_handle() );
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
				'retry'   => __( 'Try again', 'emcp-tools' ),
				'next'    => __( 'Open the next page', 'emcp-tools' ),
			),
		);
	}

	/** Test seam. */
	public static function reset_for_tests(): void {
		self::$registered       = false;
		self::$style_registered = false;
		self::$swiper_pending   = false;
		self::$dynamic_handles  = array();
	}
}
