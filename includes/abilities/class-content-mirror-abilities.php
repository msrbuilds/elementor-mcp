<?php
/**
 * Content mirror MCP abilities: export/restore/list the git-trackable content mirror.
 *
 * @package EMCP_Tools
 * @since   3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers export-content / restore-content / list-content-exports.
 *
 * @since 3.3.0
 */
class EMCP_Tools_Content_Mirror_Abilities {

	/**
	 * Ability names.
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'emcp-tools/export-content',
			'emcp-tools/restore-content',
			'emcp-tools/list-content-exports',
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Register the abilities.
	 */
	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/export-content',
			array(
				'label'               => __( 'Export Content', 'emcp-tools' ),
				'description'         => __( 'Exports Elementor page/template content to git-trackable JSON files under uploads/emcp-content-mirror/, so an external version-control system can diff and version your designs. Pass post_id to export one page/template, or omit it to export all. (Enable auto-export-on-save in EMCP Tools → Tools to keep the mirror current automatically.)', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_export' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => __( 'Export just this page/template. Omit to export all.', 'emcp-tools' ) ),
					),
				),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/restore-content',
			array(
				'label'               => __( 'Restore Content', 'emcp-tools' ),
				'description'         => __( 'Restores a page/template\'s Elementor content from its mirror file (the JSON previously written by export-content) — a file-based undo. Overwrites the current content with the mirrored version.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_restore' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => __( 'Page/template to restore from its mirror file.', 'emcp-tools' ) ),
					),
					'required'   => array( 'post_id' ),
				),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/list-content-exports',
			array(
				'label'               => __( 'List Content Exports', 'emcp-tools' ),
				'description'         => __( 'Lists the mirror files currently on disk (id, type, title, exported time) so you can see what is versioned and pick something to restore. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
			)
		);
	}

	/**
	 * export-content.
	 *
	 * @param array $input Input.
	 * @return array|WP_Error
	 */
	public function execute_export( $input ) {
		if ( isset( $input['post_id'] ) && (int) $input['post_id'] > 0 ) {
			$path = EMCP_Tools_Content_Mirror::export_post( (int) $input['post_id'] );
			if ( is_wp_error( $path ) ) {
				return $path;
			}
			return array( 'exported' => $path );
		}
		return array( 'exported_all' => EMCP_Tools_Content_Mirror::export_all() );
	}

	/**
	 * restore-content.
	 *
	 * @param array $input Input.
	 * @return array|WP_Error
	 */
	public function execute_restore( $input ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'invalid_post', __( 'A valid post_id is required.', 'emcp-tools' ) );
		}
		$res = EMCP_Tools_Content_Mirror::restore_post( $post_id );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array( 'restored' => $post_id );
	}

	/**
	 * list-content-exports.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public function execute_list( $input ) {
		$dir     = EMCP_Tools_Content_Mirror::dir();
		$exports = array();
		foreach ( (array) glob( $dir . '/*.json' ) as $file ) {
			$decoded = json_decode( (string) file_get_contents( $file ), true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			$exports[] = array(
				'file'        => basename( $file ),
				'id'          => (int) ( $decoded['id'] ?? 0 ),
				'type'        => (string) ( $decoded['type'] ?? '' ),
				'title'       => (string) ( $decoded['title'] ?? '' ),
				'exported_at' => (int) ( $decoded['exported_at'] ?? 0 ),
			);
		}
		return array(
			'exports' => $exports,
			'count'   => count( $exports ),
			'dir'     => $dir,
		);
	}
}
