<?php
/**
 * Keeps an Elementor Pro Custom Code snippet's extra options through saves
 * made from code.
 *
 * Elementor Pro's own save_post handler for snippets rebuilds
 * `_elementor_extra_options` from its admin form fields on every save. A save
 * from code (an MCP tool, a History undo, trash or untrash) has no form, so the
 * handler stores an empty list and "ensure jQuery" is lost (#197, found live
 * on Elementor Pro 4.4.0). This guard snapshots the option before that handler
 * runs and puts it back after, but only for saves without the admin form.
 *
 * @package EMCP_Tools
 * @since   3.22.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Snapshot and restore of `_elementor_extra_options` around programmatic saves.
 */
final class EMCP_Tools_Snippet_Save_Guard {

	const POST_TYPE = 'elementor_snippet';
	const META      = '_elementor_extra_options';

	/** @var array<int, mixed> Values seen before Elementor's handler, by post id. */
	private static $before = array();

	/** Hook in around Elementor Pro's handler (default priority 10). */
	public static function init(): void {
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'snapshot' ), 9, 1 );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'restore' ), 11, 1 );
	}

	/**
	 * Pure: whether a save came from Elementor's own snippet form, which sends
	 * the code field and owns the extra options.
	 *
	 * @param array $post_data $_POST.
	 * @return bool
	 */
	public static function is_form_save( array $post_data ): bool {
		return isset( $post_data['code'] );
	}

	/**
	 * @param int $post_id Snippet id.
	 */
	public static function snapshot( $post_id ): void {
		$post_id = (int) $post_id;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read only to tell form saves apart.
		if ( $post_id <= 0 || self::is_form_save( (array) $_POST ) || ! metadata_exists( 'post', $post_id, self::META ) ) {
			unset( self::$before[ $post_id ] );
			return;
		}
		self::$before[ $post_id ] = get_post_meta( $post_id, self::META, true );
	}

	/**
	 * @param int $post_id Snippet id.
	 */
	public static function restore( $post_id ): void {
		$post_id = (int) $post_id;
		if ( ! array_key_exists( $post_id, self::$before ) ) {
			return;
		}
		$value = self::$before[ $post_id ];
		unset( self::$before[ $post_id ] );
		if ( get_post_meta( $post_id, self::META, true ) !== $value ) {
			update_post_meta( $post_id, self::META, wp_slash( $value ) );
		}
	}
}
