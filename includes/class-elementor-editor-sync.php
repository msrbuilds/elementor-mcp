<?php
/**
 * Keeps EMCP's Elementor writes in step with an open Elementor editor.
 *
 * Elementor 4.3 ships its own MCP server and, with it, two seams in
 * Elementor\Modules\Mcp\Utils\Editor_Sync_State:
 *
 * - the `elementor/mcp/pre_execute_guard` filter, which refuses a write while
 *   another user has the document open in the editor with unsaved changes, so
 *   their next save cannot silently overwrite the MCP edit (or the MCP edit
 *   theirs);
 * - a per-post "changed by MCP" marker that the editor's heartbeat reads, so an
 *   open editor learns the document changed underneath it.
 *
 * EMCP's Elementor tools write through EMCP_Tools_Data, so both seams are
 * applied there. On an Elementor without them every method is a no-op.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Elementor editor sync bridge.
 */
class EMCP_Tools_Elementor_Editor_Sync {

	const GUARD_FILTER = 'elementor/mcp/pre_execute_guard';
	const STATE_CLASS  = '\Elementor\Modules\Mcp\Utils\Editor_Sync_State';

	/**
	 * Refuses a write Elementor's own MCP tools would refuse.
	 *
	 * @param int $post_id Document being written.
	 * @return WP_Error|null Error to return to the caller, or null to proceed.
	 */
	public static function guard( int $post_id ): ?WP_Error {
		if ( $post_id <= 0 || ! function_exists( 'has_filter' ) || ! has_filter( self::GUARD_FILTER ) ) {
			return null;
		}
		try {
			$verdict = apply_filters( self::GUARD_FILTER, null, array( 'post_id' => $post_id ) );
		} catch ( \Throwable $e ) {
			return null; // A broken third-party guard must not block every save.
		}
		if ( ! is_wp_error( $verdict ) ) {
			return null;
		}
		return new WP_Error(
			'elementor_editor_unsaved_changes',
			sprintf(
				/* translators: %d: post ID */
				__( 'Page #%d is open in the Elementor editor with unsaved changes by another user. Ask them to save or discard their changes, then retry. Nothing was written.', 'emcp-tools' ),
				$post_id
			),
			array(
				'status'  => 409,
				'post_id' => $post_id,
			)
		);
	}

	/**
	 * Tells an open Elementor editor that the document changed.
	 *
	 * Call after the write: Elementor clears the marker on `elementor/document/after_save`,
	 * which EMCP's own native save fires.
	 *
	 * @param int $post_id Document that was written.
	 */
	public static function mark_changed( int $post_id ): void {
		$class = self::STATE_CLASS;
		if ( $post_id <= 0 || ! class_exists( $class ) || ! method_exists( $class, 'set_mcp_mutation' ) ) {
			return;
		}
		try {
			$class::set_mcp_mutation( $post_id );
		} catch ( \Throwable $e ) {
			return; // Internal Elementor API: never let it break a completed save.
		}
	}
}
