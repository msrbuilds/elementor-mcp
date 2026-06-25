<?php
/**
 * WordPress Plugin lifecycle MCP abilities.
 *
 * Seven tools to discover, install (wordpress.org only), activate, deactivate,
 * update, and delete plugins. Built on WP core's plugin + upgrader APIs and
 * guarded by EMCP_Tools_Package_Guard (protected list, active checks, direct
 * filesystem). Reads ship enabled; the five mutation tools ship disabled-by-
 * default (admin opts in on the Tools tab).
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the plugin lifecycle abilities.
 *
 * @since 3.0.0
 */
class EMCP_Tools_Plugin_Abilities {

	/** @since 3.0.0 @var string[] */
	private $ability_names = array();

	/** @since 3.0.0 @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/** @since 3.0.0 */
	public function register(): void {
		$this->register_list_plugins();
		$this->register_search_plugins();
		$this->register_install_plugin();
		$this->register_activate_plugin();
		$this->register_deactivate_plugin();
		$this->register_update_plugin();
		$this->register_delete_plugin();
	}

	// -------------------------------------------------------------------
	// Permission callbacks (per-op capability)
	// -------------------------------------------------------------------

	public function can_list(): bool { return current_user_can( 'activate_plugins' ); }
	public function can_install(): bool { return current_user_can( 'install_plugins' ); }
	public function can_activate(): bool { return current_user_can( 'activate_plugins' ); }
	public function can_update(): bool { return current_user_can( 'update_plugins' ); }
	public function can_delete(): bool { return current_user_can( 'delete_plugins' ); }

	// -------------------------------------------------------------------
	// list-plugins
	// -------------------------------------------------------------------

	private function register_list_plugins(): void {
		$this->ability_names[] = 'emcp-tools/list-plugins';
		emcp_tools_register_ability(
			'emcp-tools/list-plugins',
			array(
				'label'               => __( 'List Plugins', 'emcp-tools' ),
				'description'         => __( 'Lists installed WordPress plugins with status (active/inactive/network), version, whether an update is available, and whether the plugin is protected (EMCP Tools / Elementor can never be disabled via MCP). Optional "status" filter.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_plugins' ),
				'permission_callback' => array( $this, 'can_list' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array( 'type' => 'string', 'enum' => array( 'all', 'active', 'inactive' ), 'description' => __( 'Filter by status. Default: all.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'plugins' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array
	 */
	public function execute_list_plugins( $input ): array {
		EMCP_Tools_Package_Guard::load_upgrader_deps();
		$filter  = in_array( $input['status'] ?? 'all', array( 'all', 'active', 'inactive' ), true ) ? ( $input['status'] ?? 'all' ) : 'all';
		$all     = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$updates = get_site_transient( 'update_plugins' );
		$resp    = ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ) ? $updates->response : array();

		$rows = array();
		foreach ( $all as $file => $data ) {
			$active = EMCP_Tools_Package_Guard::is_active_plugin( (string) $file );
			if ( 'active' === $filter && ! $active ) { continue; }
			if ( 'inactive' === $filter && $active ) { continue; }
			$rows[] = array(
				'file'             => (string) $file,
				'slug'             => dirname( (string) $file ),
				'name'             => (string) ( $data['Name'] ?? $file ),
				'version'          => (string) ( $data['Version'] ?? '' ),
				'author'           => wp_strip_all_tags( (string) ( $data['Author'] ?? '' ) ),
				'active'           => $active,
				'is_protected'     => EMCP_Tools_Package_Guard::is_protected_plugin( (string) $file ),
				'update_available' => isset( $resp[ $file ] ),
				'new_version'      => isset( $resp[ $file ]->new_version ) ? (string) $resp[ $file ]->new_version : '',
			);
		}
		return array( 'plugins' => $rows );
	}

	// -------------------------------------------------------------------
	// search-plugins
	// -------------------------------------------------------------------

	private function register_search_plugins(): void {
		$this->ability_names[] = 'emcp-tools/search-plugins';
		emcp_tools_register_ability(
			'emcp-tools/search-plugins',
			array(
				'label'               => __( 'Search Plugins', 'emcp-tools' ),
				'description'         => __( 'Searches the wordpress.org plugin directory by keyword so you can find a slug to install. Returns slug, name, version, rating, and requirements. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_search_plugins' ),
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
	public function execute_search_plugins( $input ) {
		$search = sanitize_text_field( $input['search'] ?? '' );
		if ( '' === $search ) {
			return new \WP_Error( 'missing_params', __( 'A "search" keyword is required.', 'emcp-tools' ) );
		}
		EMCP_Tools_Package_Guard::load_upgrader_deps();
		$per_page = max( 1, min( 50, absint( $input['per_page'] ?? 10 ) ) );
		$api      = plugins_api( 'query_plugins', array( 'search' => $search, 'per_page' => $per_page, 'fields' => array( 'short_description' => true, 'icons' => false ) ) );
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		$rows = array();
		foreach ( (array) ( $api->plugins ?? array() ) as $p ) {
			$p     = (object) $p;
			$rows[] = array(
				'slug'              => (string) ( $p->slug ?? '' ),
				'name'              => wp_strip_all_tags( (string) ( $p->name ?? '' ) ),
				'version'           => (string) ( $p->version ?? '' ),
				'rating'            => (int) ( $p->rating ?? 0 ),
				'num_ratings'       => (int) ( $p->num_ratings ?? 0 ),
				'requires'          => (string) ( $p->requires ?? '' ),
				'tested'            => (string) ( $p->tested ?? '' ),
				'short_description' => wp_strip_all_tags( (string) ( $p->short_description ?? '' ) ),
			);
		}
		return array( 'results' => $rows );
	}

	// -------------------------------------------------------------------
	// install-plugin
	// -------------------------------------------------------------------

	private function register_install_plugin(): void {
		$this->ability_names[] = 'emcp-tools/install-plugin';
		emcp_tools_register_ability(
			'emcp-tools/install-plugin',
			array(
				'label'               => __( 'Install Plugin', 'emcp-tools' ),
				'description'         => __( 'Installs a plugin from the wordpress.org directory by slug (e.g. "contact-form-7"). Optionally activates it. Source is always wordpress.org — arbitrary URLs are not accepted.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_install_plugin' ),
				'permission_callback' => array( $this, 'can_install' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'slug'     => array( 'type' => 'string', 'description' => __( 'wordpress.org plugin slug.', 'emcp-tools' ) ),
						'activate' => array( 'type' => 'boolean', 'description' => __( 'Activate after install. Default: false.', 'emcp-tools' ) ),
					),
					'required'   => array( 'slug' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'installed' => array( 'type' => 'boolean' ), 'activated' => array( 'type' => 'boolean' ),
					'file' => array( 'type' => 'string' ), 'slug' => array( 'type' => 'string' ),
					'messages' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_install_plugin( $input ) {
		$slug = sanitize_key( $input['slug'] ?? '' );
		if ( '' === $slug ) {
			return new \WP_Error( 'missing_params', __( 'A plugin "slug" is required.', 'emcp-tools' ) );
		}
		$activate = ! empty( $input['activate'] );
		if ( $activate && ! current_user_can( 'activate_plugins' ) ) {
			return new \WP_Error( 'cannot_activate', __( 'You cannot activate plugins.', 'emcp-tools' ) );
		}
		$ready = EMCP_Tools_Package_Guard::filesystem_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$api = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		$skin     = EMCP_Tools_Package_Guard::make_skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result || null === $result ) {
			return new \WP_Error( 'install_failed', __( 'Plugin installation failed.', 'emcp-tools' ) );
		}
		$file      = (string) $upgrader->plugin_info();
		$activated = false;
		if ( $activate && '' !== $file ) {
			$act = activate_plugin( $file );
			$activated = ! is_wp_error( $act );
		}
		return array(
			'installed' => true,
			'activated' => $activated,
			'file'      => $file,
			'slug'      => $slug,
			'messages'  => EMCP_Tools_Package_Guard::skin_messages( $skin ),
		);
	}

	// -------------------------------------------------------------------
	// activate-plugin
	// -------------------------------------------------------------------

	private function register_activate_plugin(): void {
		$this->ability_names[] = 'emcp-tools/activate-plugin';
		emcp_tools_register_ability(
			'emcp-tools/activate-plugin',
			array(
				'label'               => __( 'Activate Plugin', 'emcp-tools' ),
				'description'         => __( 'Activates an installed plugin by its file path (e.g. "akismet/akismet.php") or folder slug.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_activate_plugin' ),
				'permission_callback' => array( $this, 'can_activate' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'plugin' => array( 'type' => 'string', 'description' => __( 'Plugin file (folder/file.php) or folder slug.', 'emcp-tools' ) ) ),
					'required'   => array( 'plugin' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'success' => array( 'type' => 'boolean' ), 'plugin' => array( 'type' => 'string' ), 'active' => array( 'type' => 'boolean' ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_activate_plugin( $input ) {
		$file = $this->resolve_plugin_file( $input['plugin'] ?? '' );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$res = activate_plugin( $file );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array( 'success' => true, 'plugin' => $file, 'active' => EMCP_Tools_Package_Guard::is_active_plugin( $file ) );
	}

	// -------------------------------------------------------------------
	// deactivate-plugin
	// -------------------------------------------------------------------

	private function register_deactivate_plugin(): void {
		$this->ability_names[] = 'emcp-tools/deactivate-plugin';
		emcp_tools_register_ability(
			'emcp-tools/deactivate-plugin',
			array(
				'label'               => __( 'Deactivate Plugin', 'emcp-tools' ),
				'description'         => __( 'Deactivates an active plugin. Refuses to deactivate EMCP Tools itself or Elementor (its hard dependency).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_deactivate_plugin' ),
				'permission_callback' => array( $this, 'can_activate' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'plugin' => array( 'type' => 'string', 'description' => __( 'Plugin file or folder slug.', 'emcp-tools' ) ) ),
					'required'   => array( 'plugin' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'success' => array( 'type' => 'boolean' ), 'plugin' => array( 'type' => 'string' ), 'active' => array( 'type' => 'boolean' ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_deactivate_plugin( $input ) {
		$file = $this->resolve_plugin_file( $input['plugin'] ?? '' );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( EMCP_Tools_Package_Guard::is_protected_plugin( $file ) ) {
			return new \WP_Error( 'protected_plugin', sprintf( /* translators: %s: plugin file */ __( '"%s" is protected and cannot be deactivated via MCP (it would break EMCP Tools or Elementor).', 'emcp-tools' ), $file ) );
		}
		deactivate_plugins( array( $file ) );
		return array( 'success' => true, 'plugin' => $file, 'active' => EMCP_Tools_Package_Guard::is_active_plugin( $file ) );
	}

	// -------------------------------------------------------------------
	// update-plugin
	// -------------------------------------------------------------------

	private function register_update_plugin(): void {
		$this->ability_names[] = 'emcp-tools/update-plugin';
		emcp_tools_register_ability(
			'emcp-tools/update-plugin',
			array(
				'label'               => __( 'Update Plugin', 'emcp-tools' ),
				'description'         => __( 'Updates an installed plugin to the latest wordpress.org version. Reports up_to_date when no update is pending.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_plugin' ),
				'permission_callback' => array( $this, 'can_update' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'plugin' => array( 'type' => 'string', 'description' => __( 'Plugin file or folder slug.', 'emcp-tools' ) ) ),
					'required'   => array( 'plugin' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'success' => array( 'type' => 'boolean' ), 'up_to_date' => array( 'type' => 'boolean' ),
					'plugin' => array( 'type' => 'string' ), 'old_version' => array( 'type' => 'string' ),
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
	public function execute_update_plugin( $input ) {
		$file = $this->resolve_plugin_file( $input['plugin'] ?? '' );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$ready = EMCP_Tools_Package_Guard::filesystem_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}
		$updates = get_site_transient( 'update_plugins' );
		$resp    = ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ) ? $updates->response : array();
		$all     = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$old     = (string) ( $all[ $file ]['Version'] ?? '' );
		if ( ! isset( $resp[ $file ] ) ) {
			return array( 'success' => true, 'up_to_date' => true, 'plugin' => $file, 'old_version' => $old, 'new_version' => $old, 'messages' => array() );
		}
		$skin     = EMCP_Tools_Package_Guard::make_skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade( $file );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new \WP_Error( 'update_failed', __( 'Plugin update failed.', 'emcp-tools' ) );
		}
		return array(
			'success'     => true,
			'up_to_date'  => false,
			'plugin'      => $file,
			'old_version' => $old,
			'new_version' => (string) ( $resp[ $file ]->new_version ?? '' ),
			'messages'    => EMCP_Tools_Package_Guard::skin_messages( $skin ),
		);
	}

	// -------------------------------------------------------------------
	// delete-plugin
	// -------------------------------------------------------------------

	private function register_delete_plugin(): void {
		$this->ability_names[] = 'emcp-tools/delete-plugin';
		emcp_tools_register_ability(
			'emcp-tools/delete-plugin',
			array(
				'label'               => __( 'Delete Plugin', 'emcp-tools' ),
				'description'         => __( 'Permanently deletes an installed plugin. Destructive. Refuses protected plugins (EMCP Tools / Elementor) and any active plugin (deactivate it first).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_delete_plugin' ),
				'permission_callback' => array( $this, 'can_delete' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'plugin' => array( 'type' => 'string', 'description' => __( 'Plugin file or folder slug.', 'emcp-tools' ) ) ),
					'required'   => array( 'plugin' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'deleted' => array( 'type' => 'boolean' ), 'plugin' => array( 'type' => 'string' ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_delete_plugin( $input ) {
		$file = $this->resolve_plugin_file( $input['plugin'] ?? '' );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( EMCP_Tools_Package_Guard::is_protected_plugin( $file ) ) {
			return new \WP_Error( 'protected_plugin', sprintf( /* translators: %s: plugin file */ __( '"%s" is protected and cannot be deleted via MCP.', 'emcp-tools' ), $file ) );
		}
		if ( EMCP_Tools_Package_Guard::is_active_plugin( $file ) ) {
			return new \WP_Error( 'plugin_active', __( 'Deactivate the plugin before deleting it.', 'emcp-tools' ) );
		}
		$ready = EMCP_Tools_Package_Guard::filesystem_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$res = delete_plugins( array( $file ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( ! $res ) {
			return new \WP_Error( 'delete_failed', __( 'Plugin deletion failed.', 'emcp-tools' ) );
		}
		return array( 'deleted' => true, 'plugin' => $file );
	}

	// -------------------------------------------------------------------
	// helper
	// -------------------------------------------------------------------

	/**
	 * Resolve a user-supplied plugin reference (file path or folder slug) to an
	 * installed plugin file. Returns WP_Error('plugin_not_found') if unknown.
	 *
	 * @param string $ref
	 * @return string|\WP_Error
	 */
	private function resolve_plugin_file( $ref ) {
		$ref = (string) $ref;
		if ( '' === $ref ) {
			return new \WP_Error( 'missing_params', __( 'A "plugin" reference is required.', 'emcp-tools' ) );
		}
		EMCP_Tools_Package_Guard::load_upgrader_deps();
		$all = function_exists( 'get_plugins' ) ? get_plugins() : array();
		if ( isset( $all[ $ref ] ) ) {
			return $ref;
		}
		// Treat $ref as a folder slug and match the first file in that folder.
		foreach ( array_keys( $all ) as $file ) {
			if ( dirname( (string) $file ) === $ref ) {
				return (string) $file;
			}
		}
		return new \WP_Error( 'plugin_not_found', sprintf( /* translators: %s: plugin reference */ __( 'No installed plugin matches "%s".', 'emcp-tools' ), $ref ) );
	}
}
