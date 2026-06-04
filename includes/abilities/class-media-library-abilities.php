<?php
/**
 * WordPress Media Library MCP ability for Elementor.
 *
 * Registers a single read-only tool, `list-media`, that lets an AI agent
 * discover and query images already uploaded to the WordPress Media Library.
 * This fills the gap left by the Openverse search tools: those find generic
 * Creative Commons stock, but can't surface a client's own photos (e.g. 300+
 * job-site images already in their library). Backed by a direct WP_Query on
 * attachments — no HTTP round-trip.
 *
 * @package EMCP_Tools
 * @since   2.0.2
 * @link    https://github.com/msrbuilds/elementor-mcp/issues/25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the Media Library query ability.
 *
 * @since 2.0.2
 */
class EMCP_Tools_Media_Library_Abilities {

	/**
	 * The data access layer.
	 *
	 * @var EMCP_Tools_Data
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @since 2.0.2
	 *
	 * @param EMCP_Tools_Data $data The data access layer.
	 */
	public function __construct( EMCP_Tools_Data $data ) {
		$this->data = $data;
	}

	/**
	 * Returns the ability names registered by this class.
	 *
	 * @since 2.0.2
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'elementor-mcp/list-media',
		);
	}

	/**
	 * Registers the Media Library abilities.
	 *
	 * @since 2.0.2
	 */
	public function register(): void {
		$this->register_list_media();
	}

	/**
	 * Permission check for read-only library queries.
	 *
	 * Mirrors search-images: read access is gated on `edit_posts`.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	public function check_read_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	// -------------------------------------------------------------------------
	// list-media
	// -------------------------------------------------------------------------

	private function register_list_media(): void {
		emcp_tools_register_ability(
			'elementor-mcp/list-media',
			array(
				'label'               => __( 'List Media', 'emcp-tools' ),
				'description'         => __( 'Lists and searches images already in the WordPress Media Library. Use this to find a site\'s own uploaded photos (e.g. a client\'s product or job-site images) before reaching for Openverse stock. The optional "search" matches the title, alt text, caption, and description. Returns attachment IDs and URLs you can pass straight to add-image / add-widget.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_media' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'    => array(
							'type'        => 'string',
							'description' => __( 'Keyword to match against the attachment title, alt text, caption, and description. Omit to list everything.', 'emcp-tools' ),
						),
						'mime_type' => array(
							'type'        => 'string',
							'description' => __( 'MIME type filter. Accepts a top-level type ("image") or a specific type ("image/jpeg", "image/png"). Use "any" for all media types. Default: image.', 'emcp-tools' ),
						),
						'page'      => array(
							'type'        => 'integer',
							'description' => __( 'Page number (1-based). Default: 1.', 'emcp-tools' ),
						),
						'per_page'  => array(
							'type'        => 'integer',
							'description' => __( 'Results per page (1-100). Default: 20.', 'emcp-tools' ),
						),
						'orderby'   => array(
							'type'        => 'string',
							'enum'        => array( 'date', 'title' ),
							'description' => __( 'Sort field. Default: date (newest first).', 'emcp-tools' ),
						),
						'order'     => array(
							'type'        => 'string',
							'enum'        => array( 'desc', 'asc' ),
							'description' => __( 'Sort direction. Default: desc.', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'result_count' => array( 'type' => 'integer' ),
						'page'         => array( 'type' => 'integer' ),
						'page_count'   => array( 'type' => 'integer' ),
						'total'        => array( 'type' => 'integer' ),
						'results'      => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'            => array( 'type' => 'integer' ),
									'title'         => array( 'type' => 'string' ),
									'url'           => array( 'type' => 'string' ),
									'thumbnail_url' => array( 'type' => 'string' ),
									'alt'           => array( 'type' => 'string' ),
									'mime_type'     => array( 'type' => 'string' ),
									'width'         => array( 'type' => 'integer' ),
									'height'        => array( 'type' => 'integer' ),
									'filesize'      => array( 'type' => 'integer' ),
									'date'          => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the list-media ability.
	 *
	 * @since 2.0.2
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_list_media( $input ) {
		$search   = sanitize_text_field( $input['search'] ?? '' );
		$mime     = sanitize_text_field( $input['mime_type'] ?? 'image' );
		$page     = max( 1, absint( $input['page'] ?? 1 ) );
		$per_page = absint( $input['per_page'] ?? 20 );
		$per_page = max( 1, min( 100, $per_page ) );

		$orderby = ( isset( $input['orderby'] ) && 'title' === $input['orderby'] ) ? 'title' : 'date';
		$order   = ( isset( $input['order'] ) && 'asc' === strtolower( (string) $input['order'] ) ) ? 'ASC' : 'DESC';

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $orderby,
			'order'          => $order,
		);

		// Default to images; allow a specific MIME or "any" to widen.
		if ( '' !== $mime && 'any' !== strtolower( $mime ) && '*' !== $mime ) {
			$args['post_mime_type'] = $mime;
		}

		// Keyword search. WP_Query's `s` covers the title, caption (excerpt) and
		// description (content) but NOT the alt text, which lives in postmeta.
		// So we resolve the matching attachment IDs from both sources up front
		// (lightweight id-only queries) and feed the union into the paginated
		// query via post__in — no global query filters, nothing to leak.
		if ( '' !== $search ) {
			$id_args = array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			);
			if ( isset( $args['post_mime_type'] ) ) {
				$id_args['post_mime_type'] = $args['post_mime_type'];
			}

			$text_ids = get_posts( array_merge( $id_args, array( 's' => $search ) ) );
			$alt_ids  = get_posts(
				array_merge(
					$id_args,
					array(
						'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded to attachments; the alt-text postmeta has no dedicated column.
							array(
								'key'     => '_wp_attachment_image_alt',
								'value'   => $search,
								'compare' => 'LIKE',
							),
						),
					)
				)
			);

			$ids = array_values( array_unique( array_map( 'absint', array_merge( (array) $text_ids, (array) $alt_ids ) ) ) );

			if ( empty( $ids ) ) {
				return array(
					'result_count' => 0,
					'page'         => $page,
					'page_count'   => 0,
					'total'        => 0,
					'results'      => array(),
				);
			}

			$args['post__in'] = $ids;
		}

		$query   = new \WP_Query( $args );
		$results = array();
		foreach ( $query->posts as $attachment ) {
			$results[] = $this->format_attachment( $attachment );
		}

		return array(
			'result_count' => count( $results ),
			'page'         => $page,
			'page_count'   => (int) $query->max_num_pages,
			'total'        => (int) $query->found_posts,
			'results'      => $results,
		);
	}

	/**
	 * Normalizes an attachment post into the tool's result shape.
	 *
	 * @since 2.0.2
	 *
	 * @param \WP_Post $attachment The attachment post object.
	 * @return array
	 */
	private function format_attachment( $attachment ): array {
		$id   = (int) $attachment->ID;
		$meta = wp_get_attachment_metadata( $id );
		$meta = is_array( $meta ) ? $meta : array();

		$filesize = 0;
		if ( isset( $meta['filesize'] ) ) {
			$filesize = (int) $meta['filesize'];
		} else {
			$file = get_attached_file( $id );
			if ( $file && file_exists( $file ) ) {
				$filesize = (int) filesize( $file );
			}
		}

		$thumb = wp_get_attachment_image_url( $id, 'thumbnail' );

		return array(
			'id'            => $id,
			'title'         => $attachment->post_title,
			'url'           => (string) wp_get_attachment_url( $id ),
			'thumbnail_url' => $thumb ? $thumb : '',
			'alt'           => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'mime_type'     => $attachment->post_mime_type,
			'width'         => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
			'height'        => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
			'filesize'      => $filesize,
			'date'          => $attachment->post_date_gmt,
		);
	}
}
