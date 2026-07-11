<?php
/**
 * Content search index — a materialized, searchable corpus of pages, templates,
 * widgets, and global styles, plus the pure document builders that feed it.
 *
 * v1 is lexical (see EMCP_Tools_Search_Ranker); the storage is a single custom
 * table. Document builders are pure/static so they can be unit-tested without a
 * database, and reuse the P1 page-snapshot helpers to normalize page text.
 *
 * @package EMCP_Tools
 * @since   3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds + stores + queries the content search index.
 *
 * @since 3.3.0
 */
class EMCP_Tools_Search_Index {

	const DB_VERSION        = 1;
	const DB_VERSION_OPTION = 'emcp_tools_search_index_db_version';
	const OBJECT_TYPES      = array( 'page', 'template', 'widget', 'global_color', 'global_font', 'global_class' );

	/**
	 * The index table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'emcp_search_index';
	}

	/**
	 * Wire hooks: install-on-init + incremental re-index on save/delete.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 20 );
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 30, 2 );
		add_action( 'deleted_post', array( __CLASS__, 'on_deleted_post' ), 10, 1 );
	}

	/**
	 * Create/upgrade the index table when the stored version is behind.
	 */
	public static function maybe_install(): void {
		$installed = (int) get_option( self::DB_VERSION_OPTION, 0 );
		if ( $installed >= self::DB_VERSION ) {
			return;
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			$upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( is_readable( $upgrade ) ) {
				require_once $upgrade;
			}
		}
		if ( function_exists( 'dbDelta' ) ) {
			global $wpdb;
			$table   = self::table();
			$charset = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
			$sql     = "CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				object_type VARCHAR(20) NOT NULL,
				object_id VARCHAR(64) NOT NULL,
				title TEXT NOT NULL,
				content LONGTEXT NOT NULL,
				tokens LONGTEXT NOT NULL,
				meta LONGTEXT NULL,
				updated_at INT NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY object_unique (object_type, object_id),
				KEY object_type_idx (object_type)
			) {$charset};";
			dbDelta( $sql );
		}
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Insert or replace a document row.
	 *
	 * @param string $object_type Type.
	 * @param string $object_id   Object id.
	 * @param string $title       Title.
	 * @param string $content     Searchable text.
	 * @param array  $meta        Structured meta.
	 */
	public static function upsert( string $object_type, string $object_id, string $title, string $content, array $meta = array() ): void {
		global $wpdb;
		$tokens = implode( ' ', EMCP_Tools_Search_Ranker::tokenize( $title . ' ' . $content ) );
		$wpdb->replace(
			self::table(),
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'title'       => $title,
				'content'     => $content,
				'tokens'      => $tokens,
				'meta'        => $meta ? wp_json_encode( $meta ) : null,
				'updated_at'  => time(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
	}

	/**
	 * Remove a document.
	 *
	 * @param string $object_type Type.
	 * @param string $object_id   Object id.
	 */
	public static function remove( string $object_type, string $object_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'object_type' => $object_type, 'object_id' => $object_id ), array( '%s', '%s' ) );
	}

	/**
	 * Search the index.
	 *
	 * @param string $query Query.
	 * @param array  $types Restrict to these object types.
	 * @param int    $limit Max results.
	 * @return array
	 */
	public static function search( string $query, array $types = array(), int $limit = 20 ): array {
		global $wpdb;
		$table = self::table();
		$types = array_values( array_intersect( self::OBJECT_TYPES, $types ) );
		if ( $types ) {
			$place = implode( ',', array_fill( 0, count( $types ), '%s' ) );
			$sql   = $wpdb->prepare( "SELECT object_type,object_id,title,content,meta FROM {$table} WHERE object_type IN ({$place})", $types ); // phpcs:ignore WordPress.DB
		} else {
			$sql = "SELECT object_type,object_id,title,content,meta FROM {$table}"; // phpcs:ignore WordPress.DB
		}
		$rows = (array) $wpdb->get_results( $sql, ARRAY_A );

		$docs = array();
		foreach ( $rows as $r ) {
			$meta = null;
			if ( ! empty( $r['meta'] ) && is_string( $r['meta'] ) ) {
				$decoded = json_decode( $r['meta'], true );
				if ( is_array( $decoded ) ) {
					$meta = $decoded;
				}
			}
			$docs[] = array(
				'object_type' => (string) ( $r['object_type'] ?? '' ),
				'object_id'   => (string) ( $r['object_id'] ?? '' ),
				'title'       => (string) ( $r['title'] ?? '' ),
				'content'     => (string) ( $r['content'] ?? '' ),
				'meta'        => $meta,
			);
		}

		return EMCP_Tools_Search_Ranker::rank( $docs, $query, $limit );
	}

	/**
	 * Rebuild the index (all types, or a subset).
	 *
	 * @param array $types Object types (empty = all).
	 * @return array<string,int> Per-group counts.
	 */
	public static function rebuild( array $types = array() ): array {
		$all    = empty( $types );
		$counts = array();
		if ( $all || in_array( 'widget', $types, true ) ) {
			self::clear_type( 'widget' );
			$counts['widget'] = self::index_widgets();
		}
		if ( $all || in_array( 'page', $types, true ) ) {
			self::clear_type( 'page' );
			$counts['page'] = self::index_pages();
		}
		if ( $all || in_array( 'template', $types, true ) ) {
			self::clear_type( 'template' );
			$counts['template'] = self::index_templates();
		}
		if ( $all || array_intersect( array( 'global_color', 'global_font', 'global_class' ), $types ) ) {
			self::clear_type( 'global_color' );
			self::clear_type( 'global_font' );
			self::clear_type( 'global_class' );
			$counts['globals'] = self::index_globals();
		}
		return $counts;
	}

	/**
	 * Delete all rows of a type.
	 *
	 * @param string $type Object type.
	 */
	private static function clear_type( string $type ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE object_type = %s', $type ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Index every cataloged widget.
	 *
	 * @return int Count.
	 */
	private static function index_widgets(): int {
		$n = 0;
		foreach ( self::widget_documents() as $d ) {
			self::upsert( $d['object_type'], $d['object_id'], $d['title'], $d['content'], $d['meta'] ?? array() );
			++$n;
		}
		return $n;
	}

	/**
	 * Index all Elementor-built pages/posts.
	 *
	 * @return int Count.
	 */
	private static function index_pages(): int {
		$q = new WP_Query( array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_elementor_edit_mode', 'value' => 'builder' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		return self::index_post_ids( (array) $q->posts, 'page' );
	}

	/**
	 * Index all saved Elementor templates.
	 *
	 * @return int Count.
	 */
	private static function index_templates(): int {
		$q = new WP_Query( array(
			'post_type'      => 'elementor_library',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
		return self::index_post_ids( (array) $q->posts, 'template' );
	}

	/**
	 * Index a set of post ids as $type.
	 *
	 * @param array  $ids  Post IDs.
	 * @param string $type Object type.
	 * @return int Count.
	 */
	private static function index_post_ids( array $ids, string $type ): int {
		if ( ! class_exists( 'EMCP_Tools_Data' ) ) {
			return 0;
		}
		$data = new EMCP_Tools_Data();
		$n    = 0;
		foreach ( $ids as $id ) {
			$id       = (int) $id;
			$elements = $data->get_page_data( $id );
			if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $elements ) ) || ! is_array( $elements ) ) {
				continue;
			}
			$doc = self::page_document( $elements, (string) get_the_title( $id ) );
			self::upsert( $type, (string) $id, $doc['title'], $doc['content'], array( 'url' => (string) get_permalink( $id ) ) );
			++$n;
		}
		return $n;
	}

	/**
	 * Index global colors + typography from the active Elementor kit.
	 *
	 * @return int Count.
	 */
	private static function index_globals(): int {
		$kit_id = (int) get_option( 'elementor_active_kit', 0 );
		if ( $kit_id <= 0 ) {
			return 0;
		}
		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		if ( is_string( $settings ) ) {
			$settings = json_decode( $settings, true );
		}
		if ( ! is_array( $settings ) ) {
			return 0;
		}
		$n = 0;
		foreach ( array( 'system_colors', 'custom_colors' ) as $grp ) {
			foreach ( (array) ( $settings[ $grp ] ?? array() ) as $c ) {
				if ( ! is_array( $c ) || empty( $c['_id'] ) ) {
					continue;
				}
				$title = (string) ( $c['title'] ?? $c['_id'] );
				$color = (string) ( $c['color'] ?? '' );
				self::upsert( 'global_color', (string) $c['_id'], $title, trim( $title . ' ' . $color . ' color ' . $c['_id'] ), array( 'value' => $color ) );
				++$n;
			}
		}
		foreach ( array( 'system_typography', 'custom_typography' ) as $grp ) {
			foreach ( (array) ( $settings[ $grp ] ?? array() ) as $f ) {
				if ( ! is_array( $f ) || empty( $f['_id'] ) ) {
					continue;
				}
				$title = (string) ( $f['title'] ?? $f['_id'] );
				$font  = (string) ( $f['typography_font_family'] ?? '' );
				self::upsert( 'global_font', (string) $f['_id'], $title, trim( $title . ' ' . $font . ' typography font ' . $f['_id'] ), array( 'font' => $font ) );
				++$n;
			}
		}
		return $n;
	}

	/**
	 * save_post handler: re-index the changed page/template.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object.
	 */
	public static function on_save_post( $post_id, $post = null ): void {
		$post_id = (int) $post_id;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) < self::DB_VERSION ) {
			return;
		}
		$ptype = $post && isset( $post->post_type ) ? $post->post_type : ( function_exists( 'get_post_type' ) ? get_post_type( $post_id ) : '' );
		if ( 'elementor_library' === $ptype ) {
			self::index_post_ids( array( $post_id ), 'template' );
		} elseif ( in_array( $ptype, array( 'page', 'post' ), true ) && 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			self::index_post_ids( array( $post_id ), 'page' );
		}
	}

	/**
	 * deleted_post handler: drop the page/template from the index.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_deleted_post( $post_id ): void {
		$post_id = (string) (int) $post_id;
		self::remove( 'page', $post_id );
		self::remove( 'template', $post_id );
	}

	/**
	 * Build a searchable document for an Elementor page from its element tree.
	 *
	 * @param array  $elements Elementor elements array.
	 * @param string $title    Post title.
	 * @return array{title:string,content:string}
	 */
	public static function page_document( array $elements, string $title ): array {
		$parts = array( $title );

		if ( class_exists( 'EMCP_Tools_Page_Snapshot' ) ) {
			$norm    = EMCP_Tools_Page_Snapshot::normalize_tree( $elements );
			$content = EMCP_Tools_Page_Snapshot::content_stats( $elements );
			$tokens  = EMCP_Tools_Page_Snapshot::extract_tokens( $elements );

			foreach ( ( $content['headings'] ?? array() ) as $h ) {
				$parts[] = (string) ( $h['text'] ?? '' );
			}
			foreach ( array_keys( $norm['counts']['by_widget_type'] ?? array() ) as $wt ) {
				$parts[] = str_replace( array( '-', '_' ), ' ', (string) $wt );
			}
			self::collect_labels( $norm['tree'] ?? array(), $parts );
			foreach ( array_keys( $tokens['global_colors'] ?? array() ) as $c ) {
				$parts[] = (string) $c;
			}
			foreach ( array_keys( $tokens['global_classes'] ?? array() ) as $c ) {
				$parts[] = (string) $c;
			}
		}

		$content = trim( implode( ' ', array_filter( array_map( 'strval', $parts ), static function ( $s ) {
			return '' !== trim( $s );
		} ) ) );

		return array(
			'title'   => $title,
			'content' => $content,
		);
	}

	/**
	 * Recursively collect element labels into $parts.
	 *
	 * @param array $tree  Normalized tree nodes.
	 * @param array $parts Accumulator (by reference).
	 */
	private static function collect_labels( array $tree, array &$parts ): void {
		foreach ( $tree as $node ) {
			if ( ! empty( $node['label'] ) ) {
				$parts[] = (string) $node['label'];
			}
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				self::collect_labels( $node['children'], $parts );
			}
		}
	}

	/**
	 * Searchable documents for every cataloged widget.
	 *
	 * @return array<int,array{object_type:string,object_id:string,title:string,content:string,meta:array}>
	 */
	public static function widget_documents(): array {
		$docs = array();
		if ( ! class_exists( 'EMCP_Tools_Widget_Catalog' ) ) {
			return $docs;
		}
		foreach ( EMCP_Tools_Widget_Catalog::get() as $type => $e ) {
			$keywords = ( isset( $e['keywords'] ) && is_array( $e['keywords'] ) ) ? implode( ' ', $e['keywords'] ) : '';
			$params   = ( isset( $e['params'] ) && is_array( $e['params'] ) ) ? implode( ' ', array_keys( $e['params'] ) ) : '';
			$content  = trim( implode( ' ', array(
				(string) ( $e['title'] ?? $type ),
				(string) ( $e['use_case'] ?? '' ),
				$keywords,
				(string) ( $e['category'] ?? '' ),
				str_replace( array( '-', '_' ), ' ', (string) $type ),
				$params,
			) ) );
			$docs[]   = array(
				'object_type' => 'widget',
				'object_id'   => (string) $type,
				'title'       => (string) ( $e['title'] ?? $type ),
				'content'     => $content,
				'meta'        => array(
					'category' => (string) ( $e['category'] ?? '' ),
					'tier'     => (string) ( $e['tier'] ?? 'free' ),
				),
			);
		}
		return $docs;
	}
}
