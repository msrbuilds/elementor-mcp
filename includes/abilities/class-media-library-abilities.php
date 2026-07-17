<?php
/**
 * WordPress Media Library MCP ability for Elementor.
 *
 * Registers a single read-only tool, `list-media`, that lets an AI agent
 * discover and query images already uploaded to the WordPress Media Library.
 * This fills the gap left by the stock-image search tools: those find generic
 * stock photos, but can't surface a client's own photos (e.g. 300+
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
			'emcp-tools/list-media',
			'emcp-tools/get-media',
			'emcp-tools/update-media',
			'emcp-tools/delete-media',
		);
	}

	/**
	 * Registers the Media Library abilities.
	 *
	 * @since 2.0.2
	 */
	public function register(): void {
		$this->register_list_media();
		$this->register_get_media();
		$this->register_update_media();
		$this->register_delete_media();
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
			'emcp-tools/list-media',
			array(
				'label'               => __( 'List Media', 'emcp-tools' ),
				'description'         => __( 'Lists and searches images already in the WordPress Media Library. Use this to find a site\'s own uploaded photos (e.g. a client\'s product or job-site images) before reaching for stock photos. The optional "search" matches the title, alt text, caption, and description. Returns attachment IDs and URLs you can pass straight to add-free-widget.', 'emcp-tools' ),
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
	 * Edit permission for a specific attachment (attachments are posts).
	 *
	 * @since 3.0.0
	 * @param array|null $input Tool input; may carry an `id`.
	 * @return bool
	 */
	public function check_edit_permission( $input = null ): bool {
		$id = absint( $input['id'] ?? 0 );
		return $id ? current_user_can( 'edit_post', $id ) : current_user_can( 'edit_posts' );
	}

	/**
	 * Delete permission for a specific attachment.
	 *
	 * @since 3.0.0
	 * @param array|null $input Tool input; may carry an `id`.
	 * @return bool
	 */
	public function check_delete_permission( $input = null ): bool {
		$id = absint( $input['id'] ?? 0 );
		return $id ? current_user_can( 'delete_post', $id ) : current_user_can( 'delete_posts' );
	}

	/**
	 * Resolve an id to an attachment post, or a WP_Error.
	 *
	 * @since 3.0.0
	 * @param mixed $raw
	 * @return object|\WP_Error WP_Post-like on success.
	 */
	private function resolve_attachment( $raw ) {
		$id = absint( $raw );
		if ( ! $id ) {
			return new \WP_Error( 'missing_params', __( 'An attachment "id" is required.', 'emcp-tools' ) );
		}
		$post = get_post( $id );
		if ( ! $post ) {
			return new \WP_Error( 'attachment_not_found', __( 'Attachment not found.', 'emcp-tools' ) );
		}
		if ( 'attachment' !== ( $post->post_type ?? '' ) ) {
			return new \WP_Error( 'not_an_attachment', __( 'That ID is not a media attachment.', 'emcp-tools' ) );
		}
		return $post;
	}

	// -------------------------------------------------------------------------
	// get-media
	// -------------------------------------------------------------------------

	private function register_get_media(): void {
		emcp_tools_register_ability(
			'emcp-tools/get-media',
			array(
				'label'               => __( 'Get Media', 'emcp-tools' ),
				'description'         => __( 'Returns full detail for one Media Library attachment: title, URL, every registered image size (url + dimensions), mime type, filesize, alt text, caption, description, and raw attachment metadata. The single-item complement to list-media.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_media' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'description' => __( 'Attachment ID.', 'emcp-tools' ) ) ),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'id' => array( 'type' => 'integer' ), 'title' => array( 'type' => 'string' ),
					'slug' => array( 'type' => 'string' ), 'url' => array( 'type' => 'string' ),
					'mime_type' => array( 'type' => 'string' ), 'filesize' => array( 'type' => 'integer' ),
					'alt' => array( 'type' => 'string' ), 'caption' => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ), 'date' => array( 'type' => 'string' ),
					'author' => array( 'type' => 'object' ), 'post_parent' => array( 'type' => 'integer' ),
					'width' => array( 'type' => 'integer' ), 'height' => array( 'type' => 'integer' ),
					'sizes' => array( 'type' => 'object' ), 'metadata' => array( 'type' => 'object' ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_get_media( $input ) {
		$post = $this->resolve_attachment( $input['id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$id   = (int) $post->ID;
		$meta = wp_get_attachment_metadata( $id );
		$meta = is_array( $meta ) ? $meta : array();

		$sizes = array();
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( array_keys( $meta['sizes'] ) as $size ) {
				$src = wp_get_attachment_image_src( $id, $size );
				if ( is_array( $src ) ) {
					$sizes[ $size ] = array( 'url' => (string) $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] );
				}
			}
		}

		$author_id  = (int) ( $post->post_author ?? 0 );
		$author_obj = $author_id && function_exists( 'get_userdata' ) ? get_userdata( $author_id ) : null;

		$filesize = 0;
		if ( isset( $meta['filesize'] ) ) {
			$filesize = (int) $meta['filesize'];
		}

		return array(
			'id'          => $id,
			'title'       => (string) $post->post_title,
			'slug'        => (string) $post->post_name,
			'url'         => (string) wp_get_attachment_url( $id ),
			'mime_type'   => (string) ( $post->post_mime_type ?? '' ),
			'filesize'    => $filesize,
			'alt'         => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'caption'     => (string) $post->post_excerpt,
			'description' => (string) $post->post_content,
			'date'        => (string) ( $post->post_date ?? '' ),
			'author'      => array( 'id' => $author_id, 'name' => $author_obj ? (string) $author_obj->display_name : '' ),
			'post_parent' => (int) ( $post->post_parent ?? 0 ),
			'width'       => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
			'height'      => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
			'sizes'       => $sizes,
			'metadata'    => $meta,
		);
	}

	// -------------------------------------------------------------------------
	// update-media
	// -------------------------------------------------------------------------

	private function register_update_media(): void {
		emcp_tools_register_ability(
			'emcp-tools/update-media',
			array(
				'label'               => __( 'Update Media', 'emcp-tools' ),
				'description'         => __( 'Updates an existing attachment\'s metadata: title, alt text, caption, and/or description. Only the fields you pass change. Great for fixing missing alt text (accessibility/SEO) on images already in the library.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_media' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array( 'type' => 'integer', 'description' => __( 'Attachment ID.', 'emcp-tools' ) ),
						'title'       => array( 'type' => 'string' ),
						'alt'         => array( 'type' => 'string', 'description' => __( 'Alt text (accessibility).', 'emcp-tools' ) ),
						'caption'     => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'id' => array( 'type' => 'integer' ), 'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'alt' => array( 'type' => 'string' ), 'title' => array( 'type' => 'string' ),
					'caption' => array( 'type' => 'string' ), 'description' => array( 'type' => 'string' ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_update_media( $input ) {
		$post = $this->resolve_attachment( $input['id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$id      = (int) $post->ID;
		$updated = array();

		$postarr = array( 'ID' => $id );
		if ( array_key_exists( 'title', $input ) ) {
			$postarr['post_title'] = sanitize_text_field( (string) $input['title'] );
			$updated[]             = 'title';
		}
		if ( array_key_exists( 'caption', $input ) ) {
			$postarr['post_excerpt'] = sanitize_text_field( (string) $input['caption'] );
			$updated[]               = 'caption';
		}
		if ( array_key_exists( 'description', $input ) ) {
			// Description maps to post_content, which allows HTML by design;
			// wp_update_post applies wp_filter_post_kses for users without
			// unfiltered_html. Do NOT sanitize_text_field this (it would strip
			// legitimate markup) — title/caption are plain-text, so they are.
			$postarr['post_content'] = (string) $input['description'];
			$updated[]               = 'description';
		}
		if ( count( $postarr ) > 1 ) {
			$res = wp_update_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
		}
		if ( array_key_exists( 'alt', $input ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) $input['alt'] ) );
			$updated[] = 'alt';
		}

		$fresh = get_post( $id );
		return array(
			'id'          => $id,
			'updated'     => $updated,
			'alt'         => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'title'       => (string) ( $fresh->post_title ?? $post->post_title ),
			'caption'     => (string) ( $fresh->post_excerpt ?? $post->post_excerpt ),
			'description' => (string) ( $fresh->post_content ?? $post->post_content ),
		);
	}

	// -------------------------------------------------------------------------
	// delete-media
	// -------------------------------------------------------------------------

	private function register_delete_media(): void {
		emcp_tools_register_ability(
			'emcp-tools/delete-media',
			array(
				'label'               => __( 'Delete Media', 'emcp-tools' ),
				'description'         => __( 'Deletes a Media Library attachment. DESTRUCTIVE and effectively permanent — WordPress bypasses Trash for media unless MEDIA_TRASH is defined. Requires confirm:true. Pass force:true to skip Trash even when MEDIA_TRASH is on.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_delete_media' ),
				'permission_callback' => array( $this, 'check_delete_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer', 'description' => __( 'Attachment ID.', 'emcp-tools' ) ),
						'confirm' => array( 'type' => 'boolean', 'description' => __( 'Must be true to proceed (acknowledges permanent deletion).', 'emcp-tools' ) ),
						'force'   => array( 'type' => 'boolean', 'description' => __( 'Skip Trash even when MEDIA_TRASH is defined. Default: false.', 'emcp-tools' ) ),
					),
					'required'   => array( 'id', 'confirm' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'success' => array( 'type' => 'boolean' ), 'id' => array( 'type' => 'integer' ),
					'deleted' => array( 'type' => 'string' ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_delete_media( $input ) {
		$post = $this->resolve_attachment( $input['id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( true !== ( $input['confirm'] ?? null ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Deleting media is permanent on most sites (WordPress bypasses Trash unless MEDIA_TRASH is defined). Pass confirm:true to proceed.', 'emcp-tools' ) );
		}
		$id      = (int) $post->ID;
		$force   = ! empty( $input['force'] );
		$trashed = ! $force && defined( 'MEDIA_TRASH' ) && MEDIA_TRASH;
		$res     = wp_delete_attachment( $id, $force );
		return array(
			'success' => (bool) $res,
			'id'      => $id,
			'deleted' => $trashed ? 'trashed' : 'deleted',
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
