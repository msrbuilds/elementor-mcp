<?php
/**
 * Render a Themer template's built content, whatever builder made it.
 *
 * detect_builder() inspects post meta/content; render() dispatches to the right
 * strategy (Elementor frontend + per-post CSS; Gutenberg do_blocks; else the
 * the_content filter). The default the_content path guarantees nothing ever fatals
 * on an unknown builder.
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
class EMCP_Tools_Themer_Content_Renderer {

	/**
	 * Detect the owning builder for a post id.
	 *
	 * @param int $post_id Post id.
	 * @return string elementor|gutenberg|classic
	 */
	public static function detect_builder( int $post_id ): string {
		if ( 'builder' === (string) get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			return 'elementor';
		}
		$post = get_post( $post_id );
		return self::detect_builder_for_content( $post ? (string) $post->post_content : '' );
	}

	/**
	 * Detect builder from raw content (Elementor already ruled out).
	 *
	 * @param string $content Post content.
	 * @return string gutenberg|classic
	 */
	public static function detect_builder_for_content( string $content ): string {
		return has_blocks( $content ) ? 'gutenberg' : 'classic';
	}

	/**
	 * Render a template's built content.
	 *
	 * @param int $post_id Template post id.
	 * @return string HTML.
	 */
	public static function render( int $post_id ): string {
		// A PHP template attached to this Themer post takes over the region's render
		// (feature-gated + human-attached). Empty/error output falls back to builder.
		if ( class_exists( 'EMCP_Tools_Themer_PHP' ) && EMCP_Tools_Themer_PHP::enabled() ) {
			$php_id = (int) get_post_meta( $post_id, '_emcp_themer_php_template', true );
			if ( $php_id > 0 && class_exists( 'EMCP_Tools_Themer_PHP_Renderer' ) ) {
				$php_out = EMCP_Tools_Themer_PHP_Renderer::render( $php_id );
				if ( '' !== $php_out ) {
					return $php_out;
				}
			}
		}

		$builder = self::detect_builder( $post_id );

		if ( 'elementor' === $builder && class_exists( '\\Elementor\\Plugin' ) ) {
			return self::render_elementor( $post_id );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		// Gutenberg + classic both run through the the_content filter (do_blocks is
		// attached there), which resolves blocks and shortcodes.
		return apply_filters( 'the_content', $post->post_content );
	}

	/**
	 * Render an Elementor template: its CSS, then its content, each contained.
	 *
	 * Both steps run every dynamic tag bound on the template, so a third-party
	 * tag that returns the wrong shape throws from inside Elementor, and on a
	 * front-end request nothing catches it: the page fatals, or in a loop the
	 * card renders blank. The CSS step is guarded first. The content step is
	 * guarded too, because Elementor's own get_builder_content() enqueues the
	 * CSS again, and for an autosave (an editor previewing unsaved template
	 * changes) it uses Post_Preview, a different handle that generates afresh
	 * and throws again. Either failure leaves an editor-only comment, and the
	 * Elementor state the throw interrupted is put back.
	 *
	 * @since 3.18.0
	 * @param int           $post_id Template post id.
	 * @param callable|null $enqueue Test seam for the CSS step.
	 * @param callable|null $content Test seam for the content step.
	 * @return string
	 */
	public static function render_elementor( int $post_id, ?callable $enqueue = null, ?callable $content = null ): string {
		// Ensure the template's own generated CSS is enqueued out of context.
		$note = self::enqueue_elementor_css( $post_id, $enqueue );

		if ( null === $content ) {
			$content = static function ( int $id ): string {
				return (string) \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $id );
			};
		}
		$state = self::elementor_state();
		try {
			return $note . $content( $post_id );
		} catch ( \Throwable $e ) {
			self::restore_elementor_state( $state );
			return $note . self::failure_note( 'render_failed', $e );
		}
	}

	/**
	 * Enqueue a template's Elementor CSS without letting it take the content down.
	 *
	 * Enqueueing also generates the template's dynamic CSS, which runs every
	 * dynamic tag bound to a style control. When one throws, the CSS is skipped,
	 * Elementor's interrupted state is restored, and the content still renders.
	 * Elementor marks a handle printed before it generates, so a second enqueue
	 * of the SAME handle returns early; an autosave preview uses a different
	 * handle, which render_elementor() guards separately.
	 *
	 * @since 3.18.0
	 * @param int           $post_id Template post id.
	 * @param callable|null $enqueue Test seam; defaults to Elementor's Post CSS enqueue.
	 * @return string An HTML comment for editors when the CSS failed, else ''.
	 */
	public static function enqueue_elementor_css( int $post_id, ?callable $enqueue = null ): string {
		if ( null === $enqueue ) {
			if ( ! class_exists( '\\Elementor\\Core\\Files\\CSS\\Post' ) ) {
				return '';
			}
			$enqueue = static function ( int $id ): void {
				\Elementor\Core\Files\CSS\Post::create( $id )->enqueue();
			};
		}
		$state = self::elementor_state();
		try {
			$enqueue( $post_id );
		} catch ( \Throwable $e ) {
			self::restore_elementor_state( $state );
			return self::failure_note( 'css_failed', $e );
		}
		return '';
	}

	/**
	 * Snapshot the Elementor state a throw can leave changed.
	 *
	 * Css\Base::parse_content() switches style controls on and changes the
	 * responsive-control duplication mode before render_css(), and puts both
	 * back only on success. get_builder_content_for_display() turns edit mode
	 * off, switches the current document and opens an output buffer, again
	 * restoring only on success. Each read is guarded, so a missing or older
	 * Elementor simply records less.
	 *
	 * @since 3.18.0
	 * @return array
	 */
	public static function elementor_state(): array {
		$state = array( 'ob' => ob_get_level() );

		$perf = '\\Elementor\\Core\\Frontend\\Performance';
		if ( class_exists( $perf ) && method_exists( $perf, 'is_use_style_controls' ) ) {
			$state['style_controls'] = (bool) $perf::is_use_style_controls();
		}
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return $state;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( isset( $plugin->breakpoints ) && method_exists( $plugin->breakpoints, 'get_responsive_control_duplication_mode' ) ) {
			$state['duplication'] = $plugin->breakpoints->get_responsive_control_duplication_mode();
		}
		if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) ) {
			$state['edit_mode'] = (bool) $plugin->editor->is_edit_mode();
		}
		if ( isset( $plugin->documents ) && method_exists( $plugin->documents, 'get_current' ) ) {
			$state['has_doc'] = true;
			$state['doc']     = $plugin->documents->get_current();
		}
		return $state;
	}

	/**
	 * Put back what elementor_state() recorded.
	 *
	 * @since 3.18.0
	 * @param array $state What elementor_state() returned.
	 */
	public static function restore_elementor_state( array $state ): void {
		while ( ob_get_level() > (int) ( $state['ob'] ?? 0 ) ) {
			ob_end_clean();
		}

		$perf = '\\Elementor\\Core\\Frontend\\Performance';
		if ( array_key_exists( 'style_controls', $state ) && class_exists( $perf ) && method_exists( $perf, 'set_use_style_controls' ) ) {
			$perf::set_use_style_controls( (bool) $state['style_controls'] );
		}
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( array_key_exists( 'duplication', $state ) && isset( $plugin->breakpoints ) && method_exists( $plugin->breakpoints, 'set_responsive_control_duplication_mode' ) ) {
			$plugin->breakpoints->set_responsive_control_duplication_mode( $state['duplication'] );
		}
		if ( array_key_exists( 'edit_mode', $state ) && isset( $plugin->editor ) && method_exists( $plugin->editor, 'set_edit_mode' ) ) {
			$plugin->editor->set_edit_mode( (bool) $state['edit_mode'] );
		}
		if ( ! empty( $state['has_doc'] ) && isset( $plugin->documents ) && method_exists( $plugin->documents, 'restore_document' ) ) {
			// Each switch pushes one frame; pop until the recorded document is
			// current again. Bounded, so a frame that switched to the same
			// document (it pops without changing anything) cannot spin.
			for ( $i = 0; $i < 8 && $plugin->documents->get_current() !== $state['doc']; $i++ ) {
				$plugin->documents->restore_document();
			}
		}
	}

	/**
	 * An HTML comment for editors, nothing for visitors.
	 *
	 * @param string     $code Short code.
	 * @param \Throwable $e    The failure.
	 * @return string
	 */
	private static function failure_note( string $code, \Throwable $e ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		$text = str_replace( '--', '', $code . ': ' . wp_strip_all_tags( $e->getMessage() ) );
		return '<!-- emcp-themer: ' . esc_html( $text ) . ' -->';
	}
}
