<?php
/**
 * Per-item render context for Loop Items.
 *
 * A Loop Item renders once per post. While a card renders, every consumer of
 * "the current post" must see that card's post: Themer dynamic sources, the
 * Themer Elementor tags and block bindings, and core post blocks. The stack
 * below is what the dynamic provider consults before the queried object, and
 * the render_block_context filter is what hands core blocks their postId, the
 * same way core/post-template does inside Query Loop.
 *
 * Each entry also carries the id of the instance that rendered it, so nested
 * loop elements can derive instance ids that differ per card.
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
class EMCP_Tools_Themer_Loop_Context {

	/** @var array<int,array{post_id:int,template_id:int,index:int,uid:string}> */
	private static $stack = array();

	/** @var bool */
	private static $hooked = false;

	/** Register the block context injector once. */
	public static function init(): void {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
		add_filter( 'render_block_context', array( __CLASS__, 'inject_block_context' ), 10, 1 );
	}

	/**
	 * Enter an item.
	 *
	 * @param int    $post_id     The card's post.
	 * @param int    $template_id The Loop Item template rendering it.
	 * @param int    $index       Absolute position in the grid (0-based).
	 * @param string $uid         Instance id of the element rendering the loop.
	 */
	public static function push( int $post_id, int $template_id, int $index, string $uid = '' ): void {
		self::$stack[] = array(
			'post_id'     => $post_id,
			'template_id' => $template_id,
			'index'       => $index,
			'uid'         => $uid,
		);
	}

	/** Leave the current item. Popping an empty stack is a no-op. */
	public static function pop(): void {
		array_pop( self::$stack );
	}

	/**
	 * The innermost entry, or null outside a loop.
	 *
	 * @return array{post_id:int,template_id:int,index:int,uid:string}|null
	 */
	public static function current(): ?array {
		$n = count( self::$stack );
		return $n > 0 ? self::$stack[ $n - 1 ] : null;
	}

	/** @return int The current card's post id, 0 outside a loop. */
	public static function current_post_id(): int {
		$c = self::current();
		return $c ? (int) $c['post_id'] : 0;
	}

	/** @return bool */
	public static function in_loop(): bool {
		return ! empty( self::$stack );
	}

	/** @return int */
	public static function depth(): int {
		return count( self::$stack );
	}

	/**
	 * Template ids from the outermost to the innermost item (recursion guard).
	 *
	 * @return int[]
	 */
	public static function template_stack(): array {
		$out = array();
		foreach ( self::$stack as $e ) {
			$out[] = (int) $e['template_id'];
		}
		return $out;
	}

	/**
	 * "uid:post_id" pairs joined by "/", outermost first. Empty at the top level.
	 * Nested loop elements hash this into their own instance id.
	 *
	 * @return string
	 */
	public static function scope_key(): string {
		$parts = array();
		foreach ( self::$stack as $e ) {
			$parts[] = (string) $e['uid'] . ':' . (int) $e['post_id'];
		}
		return implode( '/', $parts );
	}

	/**
	 * render_block_context filter: give core post blocks the card's post.
	 *
	 * @param mixed $context Block context.
	 * @return array
	 */
	public static function inject_block_context( $context ) {
		$context = is_array( $context ) ? $context : array();
		$id      = self::current_post_id();
		if ( $id <= 0 ) {
			return $context;
		}
		$context['postId']   = $id;
		$context['postType'] = (string) get_post_type( $id );
		return $context;
	}

	/** Test seam. */
	public static function reset_for_tests(): void {
		self::$stack  = array();
		self::$hooked = false;
	}
}
