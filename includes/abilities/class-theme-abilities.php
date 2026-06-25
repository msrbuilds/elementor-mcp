<?php
/**
 * WordPress Theme lifecycle MCP abilities.
 *
 * Six tools to discover, install (wordpress.org only), switch (activate),
 * update, and delete themes. Built on WP core's theme + upgrader APIs and
 * guarded by EMCP_Tools_Package_Guard. Reads ship enabled; the four mutation
 * tools ship disabled-by-default.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the theme lifecycle abilities.
 *
 * @since 3.0.0
 */
class EMCP_Tools_Theme_Abilities {

	/** @since 3.0.0 @var string[] */
	private $ability_names = array();

	/** @since 3.0.0 @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/** @since 3.0.0 */
	public function register(): void {
		$this->register_list_themes();
		$this->register_search_themes();
		$this->register_install_theme();
		$this->register_switch_theme();
		$this->register_update_theme();
		$this->register_delete_theme();
	}

	public function can_list(): bool { return current_user_can( 'switch_themes' ); }
	public function can_install(): bool { return current_user_can( 'install_themes' ); }
	public function can_switch(): bool { return current_user_can( 'switch_themes' ); }
	public function can_update(): bool { return current_user_can( 'update_themes' ); }
	public function can_delete(): bool { return current_user_can( 'delete_themes' ); }

	// -------------------------------------------------------------------
	// list-themes
	// -------------------------------------------------------------------

	private function register_list_themes(): void {
		$this->ability_names[] = 'emcp-tools/list-themes';
		emcp_tools_register_ability(
			'emcp-tools/list-themes',
			array(
				'label'               => __( 'List Themes', 'emcp-tools' ),
				'description'         => __( 'Lists installed themes with version, active status, parent (for child themes), and whether an update is available.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_themes' ),
				'permission_callback' => array( $this, 'can_list' ),
				'input_schema'        => array( 'type' => 'object', 'properties' => new \stdClass() ),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'themes' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array
	 */
	public function execute_list_themes( $input ): array {
		EMCP_Tools_Package_Guard::load_upgrader_deps();
		$active  = function_exists( 'get_stylesheet' ) ? (string) get_stylesheet() : '';
		$updates = get_site_transient( 'update_themes' );
		$resp    = ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ) ? $updates->response : array();
		$themes  = function_exists( 'wp_get_themes' ) ? wp_get_themes() : array();

		$rows = array();
		foreach ( $themes as $stylesheet => $theme ) {
			$stylesheet = (string) $stylesheet;
			$parent     = ( is_object( $theme ) && method_exists( $theme, 'get_template' ) ) ? (string) $theme->get_template() : $stylesheet;
			$rows[]     = array(
				'stylesheet'       => $stylesheet,
				'name'             => is_object( $theme ) ? (string) $theme->get( 'Name' ) : $stylesheet,
				'version'          => is_object( $theme ) ? (string) $theme->get( 'Version' ) : '',
				'parent'           => ( $parent !== $stylesheet ) ? $parent : '',
				'is_active'        => ( $stylesheet === $active ),
				'update_available' => isset( $resp[ $stylesheet ] ),
				'new_version'      => isset( $resp[ $stylesheet ]['new_version'] ) ? (string) $resp[ $stylesheet ]['new_version'] : '',
			);
		}
		return array( 'themes' => $rows );
	}

	// -------------------------------------------------------------------
	// search-themes
	// -------------------------------------------------------------------

	private function register_search_themes(): void {
		$this->ability_names[] = 'emcp-tools/search-themes';
		emcp_tools_register_ability(
			'emcp-tools/search-themes',
			array(
				'label'               => __( 'Search Themes', 'emcp-tools' ),
				'description'         => __( 'Searches the wordpress.org theme directory by keyword. Returns slug, name, version, rating, and requirements. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_search_themes' ),
				'permission_callback' => array( $this, 'can_install' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'   => array( 'type' => 'string', 'description' => __( 'Keyword(s) to search the .org directory.', 'emcp-tools' ) ),
						'per_page' => array( 'type' => 'integer', 'description' => __( '1-50. Default: 10.', 'emcp-tools' ) ),
					),
					'required'   => array( 'search' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'results' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_search_themes( $input ) {
		$search = sanitize_text_field( $input['search'] ?? '' );
		if ( '' === $search ) {
			return new \WP_Error( 'missing_params', __( 'A "search" keyword is required.', 'emcp-tools' ) );
		}
		EMCP_Tools_Package_Guard::load_upgrader_deps();
		$per_page = max( 1, min( 50, absint( $input['per_page'] ?? 10 ) ) );
		$api      = themes_api( 'query_themes', array( 'search' => $search, 'per_page' => $per_page, 'fields' => array( 'description' => false ) ) );
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		$rows = array();
		foreach ( (array) ( $api->themes ?? array() ) as $t ) {
			$t      = (object) $t;
			$rows[] = array(
				'slug'     => (string) ( $t->slug ?? '' ),
				'name'     => wp_strip_all_tags( (string) ( $t->name ?? '' ) ),
				'version'  => (string) ( $t->version ?? '' ),
				'rating'   => (int) ( $t->rating ?? 0 ),
				'requires' => (string) ( $t->requires ?? '' ),
			);
		}
		return array( 'results' => $rows );
	}

	// -------------------------------------------------------------------
	// install-theme
	// -------------------------------------------------------------------

	private function register_install_theme(): void {
		$this->ability_names[] = 'emcp-tools/install-theme';
		emcp_tools_register_ability(
			'emcp-tools/install-theme',
			array(
				'label'               => __( 'Install Theme', 'emcp-tools' ),
				'description'         => __( 'Installs a theme from the wordpress.org directory by slug. Optionally activates it. Source is always wordpress.org.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_install_theme' ),
				'permission_callback' => array( $this, 'can_install' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'slug'     => array( 'type' => 'string', 'description' => __( 'wordpress.org theme slug.', 'emcp-tools' ) ),
						'activate' => array( 'type' => 'boolean', 'description' => __( 'Activate after install. Default: false.', 'emcp-tools' ) ),
					),
					'required'   => array( 'slug' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'installed' => array( 'type' => 'boolean' ), 'activated' => array( 'type' => 'boolean' ),
					'stylesheet' => array( 'type' => 'string' ), 'messages' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_install_theme( $input ) {
		$slug = sanitize_key( $input['slug'] ?? '' );
		if ( '' === $slug ) {
			return new \WP_Error( 'missing_params', __( 'A theme "slug" is required.', 'emcp-tools' ) );
		}
		$activate = ! empty( $input['activate'] );
		if ( $activate && ! current_user_can( 'switch_themes' ) ) {
			return new \WP_Error( 'cannot_switch', __( 'You cannot switch themes.', 'emcp-tools' ) );
		}
		$ready = EMCP_Tools_Package_Guard::filesystem_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$api = themes_api( 'theme_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		$skin     = EMCP_Tools_Package_Guard::make_skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result || null === $result ) {
			return new \WP_Error( 'install_failed', __( 'Theme installation failed.', 'emcp-tools' ) );
		}
		$activated = false;
		if ( $activate ) {
			switch_theme( $slug );
			$activated = true;
		}
		return array(
			'installed'  => true,
			'activated'  => $activated,
			'stylesheet' => $slug,
			'messages'   => EMCP_Tools_Package_Guard::skin_messages( $skin ),
		);
	}

	// -------------------------------------------------------------------
	// switch-theme
	// -------------------------------------------------------------------

	private function register_switch_theme(): void {
		$this->ability_names[] = 'emcp-tools/switch-theme';
		emcp_tools_register_ability(
			'emcp-tools/switch-theme',
			array(
				'label'               => __( 'Switch Theme', 'emcp-tools' ),
				'description'         => __( 'Activates an installed theme by its stylesheet (folder) name. Refuses a theme that is missing or has load errors.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_switch_theme' ),
				'permission_callback' => array( $this, 'can_switch' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'stylesheet' => array( 'type' => 'string', 'description' => __( 'Theme stylesheet/folder name.', 'emcp-tools' ) ) ),
					'required'   => array( 'stylesheet' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'success' => array( 'type' => 'boolean' ), 'stylesheet' => array( 'type' => 'string' ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_switch_theme( $input ) {
		$theme = $this->resolve_theme( $input['stylesheet'] ?? '' );
		if ( is_wp_error( $theme ) ) {
			return $theme;
		}
		$stylesheet = $theme->get_stylesheet();
		if ( $theme->errors() ) {
			return new \WP_Error( 'theme_broken', __( 'That theme has load errors and cannot be activated.', 'emcp-tools' ) );
		}
		switch_theme( $stylesheet );
		return array( 'success' => true, 'stylesheet' => $stylesheet );
	}

	// -------------------------------------------------------------------
	// update-theme
	// -------------------------------------------------------------------

	private function register_update_theme(): void {
		$this->ability_names[] = 'emcp-tools/update-theme';
		emcp_tools_register_ability(
			'emcp-tools/update-theme',
			array(
				'label'               => __( 'Update Theme', 'emcp-tools' ),
				'description'         => __( 'Updates an installed theme to the latest wordpress.org version. Reports up_to_date when no update is pending.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_theme' ),
				'permission_callback' => array( $this, 'can_update' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'stylesheet' => array( 'type' => 'string', 'description' => __( 'Theme stylesheet/folder name.', 'emcp-tools' ) ) ),
					'required'   => array( 'stylesheet' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'success' => array( 'type' => 'boolean' ), 'up_to_date' => array( 'type' => 'boolean' ),
					'stylesheet' => array( 'type' => 'string' ), 'old_version' => array( 'type' => 'string' ),
					'new_version' => array( 'type' => 'string' ), 'messages' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_update_theme( $input ) {
		$theme = $this->resolve_theme( $input['stylesheet'] ?? '' );
		if ( is_wp_error( $theme ) ) {
			return $theme;
		}
		$stylesheet = $theme->get_stylesheet();
		$ready      = EMCP_Tools_Package_Guard::filesystem_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		if ( function_exists( 'wp_update_themes' ) ) {
			wp_update_themes();
		}
		$updates = get_site_transient( 'update_themes' );
		$resp    = ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ) ? $updates->response : array();
		$old     = (string) $theme->get( 'Version' );
		if ( ! isset( $resp[ $stylesheet ] ) ) {
			return array( 'success' => true, 'up_to_date' => true, 'stylesheet' => $stylesheet, 'old_version' => $old, 'new_version' => $old, 'messages' => array() );
		}
		$skin     = EMCP_Tools_Package_Guard::make_skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->upgrade( $stylesheet );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new \WP_Error( 'update_failed', __( 'Theme update failed.', 'emcp-tools' ) );
		}
		return array(
			'success'     => true,
			'up_to_date'  => false,
			'stylesheet'  => $stylesheet,
			'old_version' => $old,
			'new_version' => (string) ( $resp[ $stylesheet ]['new_version'] ?? '' ),
			'messages'    => EMCP_Tools_Package_Guard::skin_messages( $skin ),
		);
	}

	// -------------------------------------------------------------------
	// delete-theme
	// -------------------------------------------------------------------

	private function register_delete_theme(): void {
		$this->ability_names[] = 'emcp-tools/delete-theme';
		emcp_tools_register_ability(
			'emcp-tools/delete-theme',
			array(
				'label'               => __( 'Delete Theme', 'emcp-tools' ),
				'description'         => __( 'Permanently deletes an installed theme. Destructive. Refuses the active theme and the parent of the active theme.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_delete_theme' ),
				'permission_callback' => array( $this, 'can_delete' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'stylesheet' => array( 'type' => 'string', 'description' => __( 'Theme stylesheet/folder name.', 'emcp-tools' ) ) ),
					'required'   => array( 'stylesheet' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'deleted' => array( 'type' => 'boolean' ), 'stylesheet' => array( 'type' => 'string' ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_delete_theme( $input ) {
		$theme = $this->resolve_theme( $input['stylesheet'] ?? '' );
		if ( is_wp_error( $theme ) ) {
			return $theme;
		}
		$stylesheet = $theme->get_stylesheet();
		if ( in_array( $stylesheet, EMCP_Tools_Package_Guard::active_theme_stylesheets(), true ) ) {
			return new \WP_Error( 'theme_active', __( 'Cannot delete the active theme or the parent of the active theme. Switch themes first.', 'emcp-tools' ) );
		}
		$ready = EMCP_Tools_Package_Guard::filesystem_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$res = delete_theme( $stylesheet );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( ! $res ) {
			return new \WP_Error( 'delete_failed', __( 'Theme deletion failed.', 'emcp-tools' ) );
		}
		return array( 'deleted' => (bool) $res, 'stylesheet' => $stylesheet );
	}

	// -------------------------------------------------------------------
	// helper
	// -------------------------------------------------------------------

	/**
	 * Resolve a stylesheet to an installed WP_Theme, or WP_Error if missing.
	 *
	 * @param string $stylesheet
	 * @return \WP_Theme|\WP_Error
	 */
	private function resolve_theme( $stylesheet ) {
		$stylesheet = sanitize_key( (string) $stylesheet );
		if ( '' === $stylesheet ) {
			return new \WP_Error( 'missing_params', __( 'A "stylesheet" is required.', 'emcp-tools' ) );
		}
		EMCP_Tools_Package_Guard::load_upgrader_deps();
		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme( $stylesheet ) : null;
		if ( ! $theme || ! ( is_object( $theme ) && method_exists( $theme, 'exists' ) ? $theme->exists() : false ) ) {
			return new \WP_Error( 'theme_not_found', sprintf( /* translators: %s: stylesheet */ __( 'No installed theme named "%s".', 'emcp-tools' ), $stylesheet ) );
		}
		return $theme;
	}
}
