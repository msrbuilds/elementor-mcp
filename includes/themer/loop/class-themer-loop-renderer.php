<?php
/**
 * Render a Loop Item once per post.
 *
 * Per item: save the post context, make the item current, push the loop
 * context, render the template through its builder, print per-item dynamic
 * CSS for Elementor templates, then restore everything in a finally. Nothing
 * here calls wp_reset_postdata(): that restores the MAIN query's post, which
 * inside a nested loop is the wrong post.
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
class EMCP_Tools_Themer_Loop_Renderer {

	const ITEM_CLASS = 'emcp-loop__item';

	/** Globals WP_Query::setup_postdata() writes, restored per item. */
	const POSTDATA_GLOBALS = array( 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' );

	/** Wrapper tags an item may use. */
	const TAGS = array( 'div', 'article', 'li', 'section' );

	/** @var array<string,bool> "template:post" pairs whose dynamic CSS was printed. */
	private static $dyn_printed = array();

	/**
	 * Render a template for each post.
	 *
	 * @param int   $template_id Loop Item id.
	 * @param array $posts       WP_Post objects or ids.
	 * @param array $opts        uid, index_base, tag, config, item_classes.
	 * @return string[] One HTML string per post, in order.
	 */
	public static function render_items( int $template_id, array $posts, array $opts = array() ): array {
		$out = array();
		$i   = 0;
		foreach ( $posts as $p ) {
			$post = is_object( $p ) ? $p : get_post( (int) $p );
			if ( ! $post ) {
				continue;
			}
			$out[] = self::render_item( $template_id, $post, $i, $opts );
			$i++;
		}
		return $out;
	}

	/**
	 * Render one item.
	 *
	 * @param int    $template_id Loop Item id.
	 * @param object $post        The card's post.
	 * @param int    $index       Position within this render (0-based).
	 * @param array  $opts        uid, index_base, tag, config, item_classes (extra
	 *                            classes for THIS call's own outer wrapper only,
	 *                            e.g. a carousel's 'swiper-slide'; never inherited
	 *                            by a nested render_items() call inside a card,
	 *                            since that call builds its own $opts from
	 *                            scratch).
	 * @return string
	 */
	public static function render_item( int $template_id, $post, int $index, array $opts = array() ): string {
		$post_id = (int) ( $post->ID ?? 0 );
		if ( $post_id <= 0 ) {
			return '';
		}
		$uid    = (string) ( $opts['uid'] ?? '' );
		$abs    = (int) ( $opts['index_base'] ?? 0 ) + $index;
		$config = is_array( $opts['config'] ?? null ) ? $opts['config'] : array();

		/**
		 * Pick the template for one position (alternate templates live in Pro).
		 *
		 * @since 3.18.0
		 * @param int   $template_id Primary Loop Item.
		 * @param int   $abs         Absolute position in the grid (0-based).
		 * @param int   $post_id     The card's post.
		 * @param array $config      The loop config.
		 */
		$tid = (int) apply_filters( 'emcp_themer_loop_item_template', $template_id, $abs, $post_id, $config );
		if ( ! EMCP_Tools_Themer_CPT::is_published_loop_template( $tid ) ) {
			$tid = $template_id;
		}
		if ( ! EMCP_Tools_Themer_CPT::is_published_loop_template( $tid ) ) {
			return '';
		}
		if ( in_array( $tid, EMCP_Tools_Themer_Loop_Context::template_stack(), true ) ) {
			return self::admin_note( 'loop_recursion', 'template ' . $tid . ' contains itself' );
		}

		$classes = array( self::ITEM_CLASS, 'emcp-loop-item-' . $post_id, self::ITEM_CLASS . '--' . ( $abs + 1 ) );
		if ( 0 === $abs ) {
			$classes[] = 'is-first';
		}
		// Extra classes THIS render_items() call wants on its own items' outer
		// wrapper (a carousel's 'swiper-slide'). Merged before the filter below
		// so a filter callback still sees and can adjust the full class list.
		// Sanitised per entry: this can carry caller input by the time a later
		// REST route reaches it.
		foreach ( (array) ( $opts['item_classes'] ?? array() ) as $extra ) {
			$extra = sanitize_html_class( (string) $extra );
			if ( '' !== $extra ) {
				$classes[] = $extra;
			}
		}
		/**
		 * Filter the item wrapper classes.
		 *
		 * @since 3.18.0
		 * @param string[] $classes Classes.
		 * @param int      $abs     Absolute position.
		 * @param int      $post_id The card's post.
		 * @param array    $config  The loop config.
		 */
		$classes  = (array) apply_filters( 'emcp_themer_loop_item_classes', $classes, $abs, $post_id, $config );
		$tag_opt  = (string) ( $opts['tag'] ?? 'div' );
		$tag      = in_array( $tag_opt, self::TAGS, true ) ? $tag_opt : 'div';

		// snapshot() only reads globals, so it cannot throw and is safe to run
		// before the try. push() runs first inside the try, before apply():
		// push() is a single array append with no failure mode, so it cannot
		// throw and leave the stack unbalanced, and if apply() throws (it runs
		// setup_postdata() and, for a product, the third-party wc_get_product())
		// the finally's pop() must have a frame to pop, or a nested loop's
		// failing inner item would pop the OUTER card's context instead of its
		// own. apply() mutates globals, so a throw from it is still covered by
		// the finally that calls leave().
		$saved = self::snapshot();
		$inner = '';
		try {
			EMCP_Tools_Themer_Loop_Context::push( $post_id, $tid, $abs, $uid );
			self::apply( $post );
			$inner = self::render_template_content( $tid );
			if ( 'elementor' === self::builder( $tid ) && false !== strpos( $inner, 'elementor-' . $tid ) ) {
				// Only when the rendered card actually carries the template's
				// Elementor root class: an attached PHP region template can win
				// inside render_template_content() and produce no such markup,
				// in which case a scoped <style> here would be dead weight.
				$inner .= self::dynamic_css( $tid, $post_id );
			}
		} catch ( \Throwable $e ) {
			$inner = self::admin_note( 'item_failed', $e->getMessage() );
		} finally {
			EMCP_Tools_Themer_Loop_Context::pop();
			self::leave( $saved );
		}

		return '<' . $tag . ' class="' . esc_attr( implode( ' ', array_unique( array_map( 'strval', $classes ) ) ) ) . '" data-post="' . $post_id . '">'
			. $inner . '</' . $tag . '>';
	}

	/**
	 * Render a template's content through its builder, with no per-item wrapper.
	 *
	 * @param int $template_id Template id.
	 * @return string
	 */
	public static function render_template_content( int $template_id ): string {
		if ( 'elementor' === self::builder( $template_id ) ) {
			if ( ! class_exists( '\Elementor\Plugin' ) ) {
				return '';
			}
			// EMCP_Tools_Themer_Content_Renderer::render() already resolves an
			// attached PHP region template before falling back to the Elementor
			// document (and enqueues the template's static CSS once). Checking
			// the PHP template again here, ahead of this branch, would execute
			// the same user PHP a second time per card.
			return EMCP_Tools_Themer_Content_Renderer::render( $template_id );
		}
		// Non-Elementor path only: an attached PHP region template wins, as
		// everywhere in Themer. Content_Renderer::render() would duplicate this
		// check AND run the full the_content chain, which render_blocks_content()
		// deliberately avoids, so it is not used here.
		if ( class_exists( 'EMCP_Tools_Themer_PHP' ) && EMCP_Tools_Themer_PHP::enabled() ) {
			$php_id = (int) get_post_meta( $template_id, '_emcp_themer_php_template', true );
			if ( $php_id > 0 && class_exists( 'EMCP_Tools_Themer_PHP_Renderer' ) ) {
				$out = EMCP_Tools_Themer_PHP_Renderer::render( $php_id );
				if ( '' !== $out ) {
					return $out;
				}
			}
		}
		$post = get_post( $template_id );
		return $post ? self::render_blocks_content( (string) $post->post_content ) : '';
	}

	/**
	 * Block or classic content, deliberately NOT the full the_content chain:
	 * third-party filters that append sharing buttons or related posts would
	 * fire once per card. The rest of the chain is hand-assembled from core's
	 * own functions (none of which can fire third-party code) in core's order,
	 * with one deliberate swap: wp_filter_content_tags runs AFTER do_shortcode
	 * here, not before as core does it, so images a shortcode produces also
	 * get width/height and loading="lazy" attributes; core's order misses those.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	public static function render_blocks_content( string $content ): string {
		$html = has_blocks( $content ) ? do_blocks( $content ) : ( function_exists( 'wpautop' ) ? wpautop( $content ) : $content );
		if ( function_exists( 'shortcode_unautop' ) ) {
			// Undoes wpautop's paragraph wrap around a bare block-level shortcode.
			$html = shortcode_unautop( $html );
		}
		if ( function_exists( 'wptexturize' ) ) {
			$html = wptexturize( $html );
		}
		$html = do_shortcode( $html );
		if ( function_exists( 'wp_filter_content_tags' ) ) {
			$html = wp_filter_content_tags( $html );
		}
		return (string) $html;
	}

	/**
	 * @param int $template_id Template id.
	 * @return string elementor|gutenberg|classic
	 */
	public static function builder( int $template_id ): string {
		return EMCP_Tools_Themer_Content_Renderer::detect_builder( $template_id );
	}

	/**
	 * Scope a template's dynamic CSS to one card.
	 *
	 * @param string $css         CSS rendered for the template.
	 * @param int    $template_id Template id.
	 * @param int    $post_id     The card's post.
	 * @return string
	 */
	public static function rewrite_dynamic_css( string $css, int $template_id, int $post_id ): string {
		return str_replace( '.elementor-' . $template_id, '.emcp-loop-item-' . $post_id . ' .elementor-' . $template_id, $css );
	}

	/** Test seam. */
	public static function reset_for_tests(): void {
		self::$dyn_printed = array();
	}

	// ---- internals ---------------------------------------------------------

	/**
	 * Snapshot the globals about to be overwritten. Read-only, so this cannot
	 * throw and is safe to call before the try in render_item().
	 *
	 * @return array
	 */
	private static function snapshot(): array {
		$saved = array(
			'post'    => $GLOBALS['post'] ?? null,
			'product' => $GLOBALS['product'] ?? null,
		);
		foreach ( self::POSTDATA_GLOBALS as $g ) {
			$saved[ $g ] = $GLOBALS[ $g ] ?? null;
		}
		return $saved;
	}

	/**
	 * Make a post current. Mutates globals and calls setup_postdata() and,
	 * for a product, the third-party wc_get_product(); callers must run this
	 * inside a try whose finally calls leave(), since either call can throw.
	 * Call AFTER Loop_Context::push(): if this throws, the finally's pop()
	 * must already have a frame to pop.
	 *
	 * @param object $post The post.
	 */
	private static function apply( $post ): void {
		$GLOBALS['post'] = $post; // setup_postdata() does not assign this.
		setup_postdata( $post );
		if ( 'product' === (string) ( $post->post_type ?? '' ) && function_exists( 'wc_get_product' ) ) {
			$GLOBALS['product'] = wc_get_product( $post );
		}
	}

	/**
	 * @param array $saved What snapshot() returned.
	 */
	private static function leave( array $saved ): void {
		$GLOBALS['post']    = $saved['post'];
		$GLOBALS['product'] = $saved['product'];
		foreach ( self::POSTDATA_GLOBALS as $g ) {
			$GLOBALS[ $g ] = $saved[ $g ];
		}
	}

	/**
	 * Per-item CSS for controls bound to dynamic tags (a featured-image
	 * background differs per card). Rendered while the item is current, then
	 * scoped to the card. Printed once per (template, post) per request.
	 *
	 * @param int $template_id Template id.
	 * @param int $post_id     The card's post.
	 * @return string
	 */
	private static function dynamic_css( int $template_id, int $post_id ): string {
		if ( ! class_exists( '\Elementor\Core\DynamicTags\Dynamic_CSS' ) || ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			return '';
		}
		$key = $template_id . ':' . $post_id;
		if ( isset( self::$dyn_printed[ $key ] ) ) {
			return '';
		}
		// Css\Base::parse_content() changes Elementor's style-controls flag and
		// duplication mode and restores them only on success, so a throwing tag
		// would leave every later render in the request generating CSS wrongly.
		$state = EMCP_Tools_Themer_Content_Renderer::elementor_state();
		try {
			$post_css = \Elementor\Core\Files\CSS\Post::create( $template_id );
			// Never Dynamic_CSS::create(): Elementor's files manager caches one
			// instance per class and arguments, so every card would receive the
			// first card's CSS. A direct instance renders fresh content.
			$dynamic = new \Elementor\Core\DynamicTags\Dynamic_CSS( $template_id, $post_css );
			$css     = trim( (string) $dynamic->get_content() );
		} catch ( \Throwable $e ) {
			EMCP_Tools_Themer_Content_Renderer::restore_elementor_state( $state );
			return '';
		}
		if ( '' === $css ) {
			return '';
		}
		self::$dyn_printed[ $key ] = true;
		EMCP_Tools_Themer_Loop_Assets::enqueue_dynamic_css( $template_id, $post_id, wp_strip_all_tags( self::rewrite_dynamic_css( $css, $template_id, $post_id ) ) );
		return '';
	}

	/**
	 * An HTML comment for editors, nothing for visitors.
	 *
	 * @param string $code    Short code.
	 * @param string $message Detail.
	 * @return string
	 */
	private static function admin_note( string $code, string $message ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		$text = str_replace( '--', '', $code . ': ' . wp_strip_all_tags( $message ) );
		return '<!-- emcp-loop: ' . esc_html( $text ) . ' -->';
	}
}
