<?php
/**
 * Content search MCP abilities: search-content + reindex-search.
 *
 * @package EMCP_Tools
 * @since   3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the content-search tools.
 *
 * @since 3.3.0
 */
class EMCP_Tools_Search_Abilities {

	/**
	 * Ability names.
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array( 'emcp-tools/search-content', 'emcp-tools/reindex-search' );
	}

	/**
	 * Read permission.
	 *
	 * @return bool
	 */
	public function check_read_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Register the abilities.
	 */
	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/search-content',
			array(
				'label'               => __( 'Search Content', 'emcp-tools' ),
				'description'         => __( 'Searches an indexed corpus of the site\'s own pages, saved templates, widgets, and global styles by natural-language query, returning the best matches ranked by relevance — so you can REUSE an existing page/template/widget instead of building from scratch. Returns object_type + object_id + title + score + snippet; then read/clone the winner with the relevant tool (get-page-structure, apply-template, add-*-widget, etc.). Filter by types. Call reindex-search first if results look stale. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_search' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'query' => array( 'type' => 'string', 'description' => __( 'Natural-language query, e.g. "pricing table" or "team testimonials".', 'emcp-tools' ) ),
						'types' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string', 'enum' => array( 'page', 'template', 'widget', 'global_color', 'global_font', 'global_class' ) ),
							'description' => __( 'Restrict to these object types. Default: all.', 'emcp-tools' ),
						),
						'limit' => array( 'type' => 'integer', 'description' => __( 'Max results (default 20).', 'emcp-tools' ) ),
					),
					'required'   => array( 'query' ),
				),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/reindex-search',
			array(
				'label'               => __( 'Reindex Search', 'emcp-tools' ),
				'description'         => __( 'Rebuilds the content-search index from the current site (pages, templates, widgets, global styles). The index also updates incrementally when a page/template is saved, so this is only needed for a full refresh or a first-time build. Returns the number of items indexed per group. Optionally restrict to certain types.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_reindex' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'types' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string', 'enum' => array( 'page', 'template', 'widget', 'global_color', 'global_font', 'global_class' ) ),
							'description' => __( 'Restrict the rebuild to these types. Default: all.', 'emcp-tools' ),
						),
					),
				),
			)
		);
	}

	/**
	 * search-content.
	 *
	 * @param array $input Input.
	 * @return array|WP_Error
	 */
	public function execute_search( $input ) {
		$query = isset( $input['query'] ) ? trim( (string) $input['query'] ) : '';
		if ( '' === $query ) {
			return new WP_Error( 'query_required', __( 'A query is required.', 'emcp-tools' ) );
		}
		EMCP_Tools_Search_Index::maybe_install();
		$types   = ( isset( $input['types'] ) && is_array( $input['types'] ) ) ? array_map( 'strval', $input['types'] ) : array();
		$limit   = isset( $input['limit'] ) ? max( 1, (int) $input['limit'] ) : 20;
		$results = EMCP_Tools_Search_Index::search( $query, $types, $limit );
		return array(
			'query'   => $query,
			'results' => $results,
			'count'   => count( $results ),
		);
	}

	/**
	 * reindex-search.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public function execute_reindex( $input ) {
		EMCP_Tools_Search_Index::maybe_install();
		$types   = ( isset( $input['types'] ) && is_array( $input['types'] ) ) ? array_map( 'strval', $input['types'] ) : array();
		$indexed = EMCP_Tools_Search_Index::rebuild( $types );
		return array(
			'indexed' => $indexed,
			'total'   => array_sum( $indexed ),
		);
	}
}
