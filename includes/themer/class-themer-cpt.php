<?php
/**
 * Themer template CPT + quota gate.
 *
 * Registers `emcp_theme_template` (editable by any builder), enables Elementor on
 * it, and enforces the free 1-per-type quota. The cap is a seam: the Pro overlay
 * raises `emcp_themer_quota` to PHP_INT_MAX. register() runs on `init`.
 *
 * @package EMCP_Tools
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.2.0
 */
class EMCP_Tools_Themer_CPT {

	const POST_TYPE = 'emcp_theme_template';

	/** Valid template types. */
	const TYPES = array( 'header', 'footer', 'single', 'archive', 'search', '404' );

	/**
	 * Register the CPT + Elementor support + its own dashboard menu. Hooked to
	 * `init` by the module, which only boots when the module is active — so the
	 * whole menu is gated behind module status.
	 */
	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				// Its own top-level dashboard menu (not buried in the EMCP Tools page).
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-layout',
				'menu_position'       => 59, // just above Appearance.
				'show_in_admin_bar'   => true,
				'show_in_rest'        => true,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor', 'author', 'custom-fields' ),
				'labels'              => array(
					'name'               => __( 'EMCP Themer', 'emcp-tools' ),
					'singular_name'      => __( 'Theme Template', 'emcp-tools' ),
					'menu_name'          => __( 'EMCP Themer', 'emcp-tools' ),
					'all_items'          => __( 'All Templates', 'emcp-tools' ),
					'add_new'            => __( 'Add New', 'emcp-tools' ),
					'add_new_item'       => __( 'Add Theme Template', 'emcp-tools' ),
					'new_item'           => __( 'New Theme Template', 'emcp-tools' ),
					'edit_item'          => __( 'Edit Theme Template', 'emcp-tools' ),
					'view_item'          => __( 'View Theme Template', 'emcp-tools' ),
					'search_items'       => __( 'Search Theme Templates', 'emcp-tools' ),
					'not_found'          => __( 'No theme templates yet.', 'emcp-tools' ),
					'not_found_in_trash' => __( 'No theme templates in Trash.', 'emcp-tools' ),
				),
			)
		);

		// Native CPT-screen niceties: a Type column + the theme-adapter status notice.
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'render_adapter_notice' ) );

		// Let Elementor offer "Edit with Elementor" on our CPT.
		add_filter(
			'elementor/cpt_support/get_public_post_types',
			static function ( $types ) {
				$types[] = self::POST_TYPE;
				return array_unique( (array) $types );
			}
		);
		add_filter(
			'elementor/utils/get_public_post_types',
			static function ( $types ) {
				if ( is_array( $types ) && ! isset( $types[ self::POST_TYPE ] ) ) {
					$types[ self::POST_TYPE ] = __( 'Theme Template', 'emcp-tools' );
				}
				return $types;
			}
		);
	}

	/**
	 * Add a "Type" column to the CPT list table (after the title).
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function admin_columns( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['emcp_themer_type'] = __( 'Type', 'emcp-tools' );
			}
		}
		return $out;
	}

	/**
	 * Render the Type column value.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post id.
	 */
	public function render_admin_column( $column, $post_id ): void {
		if ( 'emcp_themer_type' !== $column ) {
			return;
		}
		$type = (string) get_post_meta( (int) $post_id, EMCP_Tools_Themer_Index::META_TYPE, true );
		echo $type ? '<strong>' . esc_html( ucfirst( $type ) ) . '</strong>' : '&mdash;';
	}

	/**
	 * Show the detected theme-adapter status as a notice on the CPT list screen.
	 */
	public function render_adapter_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . self::POST_TYPE !== $screen->id ) {
			return;
		}
		$supported = class_exists( 'EMCP_Tools_Themer_Theme_Adapters' ) && null !== EMCP_Tools_Themer_Theme_Adapters::current();
		if ( $supported ) {
			$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme()->get( 'Name' ) : '';
			echo '<div class="notice notice-success"><p>' . sprintf(
				/* translators: %s: theme name */
				esc_html__( 'EMCP Themer: your theme (%s) is directly supported — standalone headers & footers inject cleanly. Body templates (single/archive/search/404) work on every theme.', 'emcp-tools' ),
				esc_html( (string) $theme )
			) . '</p></div>';
		} else {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'EMCP Themer: body templates (single/archive/search/404) work on every theme. For standalone header/footer replacement your theme is not directly supported — add emcp_themer_location( \'header\' ) / emcp_themer_location( \'footer\' ) to your theme, or enable the full-page-takeover fallback (set the emcp_tools_module_themer_force_render option to 1).', 'emcp-tools' ) . '</p></div>';
		}
	}

	/**
	 * The per-type cap (free 1; Pro raises via the filter).
	 *
	 * @param string $type Template type.
	 * @return int
	 */
	public static function quota( string $type ): int {
		/**
		 * Filters the max number of templates allowed per type.
		 *
		 * @param int    $cap  Default 1 (free).
		 * @param string $type Template type.
		 */
		return (int) apply_filters( 'emcp_themer_quota', 1, $type );
	}

	/**
	 * Whether another template of $type may be created given the current count.
	 *
	 * @param string $type           Template type.
	 * @param int    $existing_count Existing count of that type.
	 * @return bool
	 */
	public static function can_create( string $type, int $existing_count ): bool {
		return $existing_count < self::quota( $type );
	}

	/**
	 * Count existing templates of a type (live).
	 *
	 * @param string $type Template type.
	 * @return int
	 */
	public static function count_of_type( string $type ): int {
		$q = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => EMCP_Tools_Themer_Index::META_TYPE,
				'meta_value'     => $type,
			)
		);
		return (int) $q->found_posts;
	}
}
