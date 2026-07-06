<?php
/**
 * Wire the resolver into WordPress (hybrid render engine).
 *
 * Body template present -> take over template_include with the canvas. Otherwise,
 * a standalone header/footer injects through the active theme adapter, or (for
 * unsupported themes, when the admin enabled it) through a full-page takeover.
 * slots() is memoized per request.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.1.0
 */
class EMCP_Tools_Themer_Render_Controller {

	/** @var array<string,?int>|null */
	private static $slots = null;

	/**
	 * Wire hooks. Called by the module on `init`.
	 */
	public function init(): void {
		// Late so we can defer to Elementor Pro's own theme builder when it wins.
		add_filter( 'template_include', array( $this, 'maybe_take_over' ), 99 );
		add_action( 'template_redirect', array( $this, 'maybe_inject_parts' ) );
	}

	/**
	 * Resolve slots for the current request (memoized).
	 *
	 * @return array{header:?int,body:?int,footer:?int}
	 */
	public static function slots(): array {
		if ( null !== self::$slots ) {
			return self::$slots;
		}
		$registry = EMCP_Tools_Themer_Matcher_Registry::fresh();
		$ctx      = EMCP_Tools_Themer_Context::from_query();
		/**
		 * Filters the Themer priority ranker (Pro supplies priority; free returns 0).
		 *
		 * @param callable $ranker fn(array $row): int.
		 */
		$ranker = apply_filters(
			'emcp_themer_rank',
			static function ( array $row ): int {
				return 0;
			}
		);
		self::$slots = EMCP_Tools_Themer_Resolver::resolve( EMCP_Tools_Themer_Index::get(), $ctx, $registry, $ranker );
		return self::$slots;
	}

	/**
	 * How the current request should render.
	 *
	 * - `none`  — no body template; leave the theme template alone (a standalone
	 *            Themer header/footer may still inject via the adapter).
	 * - `body`  — a body template wins but Themer does NOT supply both header and
	 *            footer: wrap the body in the THEME's header/footer (get_header /
	 *            get_footer) so the site chrome is kept. Any single Themer header
	 *            or footer injects via the adapter.
	 * - `full`  — Themer supplies BOTH header and footer (or the force-render
	 *            option is on): render a complete standalone document, no theme
	 *            chrome.
	 *
	 * @return string none|body|full
	 */
	public static function render_mode(): string {
		$slots = self::slots();
		if ( empty( $slots['body'] ) ) {
			return 'none';
		}
		$force = '1' === (string) get_option( 'emcp_tools_module_themer_force_render', '0' );
		if ( ( ! empty( $slots['header'] ) && ! empty( $slots['footer'] ) ) || $force ) {
			return 'full';
		}
		return 'body';
	}

	/**
	 * Take over the page when a body template wins.
	 *
	 * @param string $template Resolved theme template path.
	 * @return string
	 */
	public function maybe_take_over( $template ) {
		// Editing/viewing a Themer template's OWN singular view: serve a blank
		// the_content canvas so Elementor's editor can attach and the template
		// renders standalone. Never apply Themer resolution to our own CPT.
		if ( is_singular( EMCP_Tools_Themer_CPT::POST_TYPE ) ) {
			$edit = EMCP_TOOLS_DIR . 'includes/themer/templates/template-edit-canvas.php';
			return is_readable( $edit ) ? $edit : $template;
		}
		// Defer to an existing Elementor Pro Theme Builder body match (avoid double-takeover).
		if ( self::elementor_theme_builder_owns_body() ) {
			return $template;
		}
		$mode = self::render_mode();
		if ( 'full' === $mode ) {
			$canvas = EMCP_TOOLS_DIR . 'includes/themer/templates/template-canvas.php';
			return is_readable( $canvas ) ? $canvas : $template;
		}
		if ( 'body' === $mode ) {
			// Keep the theme's header/footer; swap only the content area.
			$body = EMCP_TOOLS_DIR . 'includes/themer/templates/template-body.php';
			return is_readable( $body ) ? $body : $template;
		}
		return $template;
	}

	/**
	 * Inject a standalone header/footer on a normal theme page.
	 */
	public function maybe_inject_parts(): void {
		// Don't inject header/footer when previewing a Themer template itself.
		if ( is_singular( EMCP_Tools_Themer_CPT::POST_TYPE ) ) {
			return;
		}
		// In 'full' mode the standalone canvas renders the Themer header/footer
		// itself; injecting again would duplicate them.
		if ( 'full' === self::render_mode() ) {
			return;
		}
		$slots = self::slots();
		if ( empty( $slots['header'] ) && empty( $slots['footer'] ) ) {
			return;
		}
		$adapter = EMCP_Tools_Themer_Theme_Adapters::current();
		if ( null !== $adapter ) {
			$this->wire_adapter( $adapter, $slots );
			return;
		}
		// Unsupported theme + admin opted into full-page takeover: swap template.
		if ( '1' === (string) get_option( 'emcp_tools_module_themer_force_render', '0' ) ) {
			add_filter(
				'template_include',
				static function () {
					return EMCP_TOOLS_DIR . 'includes/themer/templates/template-canvas.php';
				},
				100
			);
		}
		// Otherwise: header/footer only render where the theme calls emcp_themer_location().
	}

	/**
	 * Wire a supported theme's header/footer hooks to render our parts.
	 *
	 * @param string $adapter Adapter key.
	 * @param array  $slots   Resolved slots.
	 */
	private function wire_adapter( string $adapter, array $slots ): void {
		$map = EMCP_Tools_Themer_Theme_Adapters::map();
		if ( ! isset( $map[ $adapter ] ) ) {
			return;
		}
		if ( ! empty( $slots['header'] ) ) {
			// Replace the theme's header: drop its callbacks on the render hook,
			// then print ours in their place (so the theme header doesn't ALSO
			// render alongside/behind the Themer one).
			remove_all_actions( $map[ $adapter ]['header'] );
			add_action(
				$map[ $adapter ]['header'],
				static function () use ( $slots ) {
					echo EMCP_Tools_Themer_Content_Renderer::render( (int) $slots['header'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				},
				10
			);
		}
		if ( ! empty( $slots['footer'] ) ) {
			remove_all_actions( $map[ $adapter ]['footer'] );
			add_action(
				$map[ $adapter ]['footer'],
				static function () use ( $slots ) {
					echo EMCP_Tools_Themer_Content_Renderer::render( (int) $slots['footer'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				},
				10
			);
		}
	}

	/**
	 * Whether Elementor Pro's theme builder has a body location for this request.
	 *
	 * @return bool
	 */
	private static function elementor_theme_builder_owns_body(): bool {
		if ( ! class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
			return false;
		}
		$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
		if ( ! method_exists( $module, 'get_conditions_manager' ) ) {
			return false;
		}
		$docs = $module->get_conditions_manager()->get_documents_for_location( 'single' );
		$docs = $docs ? $docs : $module->get_conditions_manager()->get_documents_for_location( 'archive' );
		return ! empty( $docs );
	}

	/** Reset memoized slots (tests). */
	public static function reset_for_tests(): void {
		self::$slots = null;
	}
}

if ( ! function_exists( 'emcp_themer_location' ) ) {
	/**
	 * Template tag for unsupported themes to place a Themer header/footer manually.
	 *
	 * @param string $slot 'header' | 'footer'.
	 */
	function emcp_themer_location( string $slot ): void {
		$slots = EMCP_Tools_Themer_Render_Controller::slots();
		if ( ! empty( $slots[ $slot ] ) ) {
			echo EMCP_Tools_Themer_Content_Renderer::render( (int) $slots[ $slot ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
