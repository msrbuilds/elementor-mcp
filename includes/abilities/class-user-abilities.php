<?php
/**
 * WordPress Users management MCP abilities.
 *
 * Four tools — list-users, get-user (read), create-user, update-user (write) —
 * for SAFE user management. The security boundary is the design: no delete tool,
 * no role changes, passwords are auto-generated and emailed (never returned), and
 * a strict privilege guard means agents can only create non-admin accounts and
 * can never edit any user that holds admin-level capabilities.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the WordPress user abilities.
 *
 * @since 3.0.0
 */
class EMCP_Tools_User_Abilities {

	/** @since 3.0.0 @var string[] */
	private $ability_names = array();

	/** @since 3.0.0 @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/** @since 3.0.0 */
	public function register(): void {
		$this->register_list_users();
		$this->register_get_user();
		$this->register_create_user();
		$this->register_update_user();
	}

	// -------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------

	public function can_list(): bool { return current_user_can( 'list_users' ); }
	public function can_create(): bool { return current_user_can( 'create_users' ); }
	public function can_edit(): bool { return current_user_can( 'edit_users' ); }

	// -------------------------------------------------------------------
	// Privilege guard
	// -------------------------------------------------------------------

	/**
	 * Capabilities that mark a role or user as admin-grade (protected).
	 *
	 * @since 3.0.0
	 * @return string[]
	 */
	private static function protected_caps(): array {
		return array( 'manage_options', 'promote_users', 'edit_users', 'delete_users', 'manage_network' );
	}

	/**
	 * Whether a role slug carries any admin-level capability.
	 *
	 * @since 3.0.0
	 * @param string $role_slug
	 * @return bool
	 */
	private function role_has_admin_caps( string $role_slug ): bool {
		$role = function_exists( 'get_role' ) ? get_role( $role_slug ) : null;
		if ( ! $role || empty( $role->capabilities ) || ! is_array( $role->capabilities ) ) {
			return false;
		}
		foreach ( self::protected_caps() as $cap ) {
			if ( ! empty( $role->capabilities[ $cap ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a user holds any admin-level capability (untouchable via MCP).
	 *
	 * @since 3.0.0
	 * @param int $user_id
	 * @return bool
	 */
	private function user_has_admin_caps( int $user_id ): bool {
		foreach ( self::protected_caps() as $cap ) {
			if ( user_can( $user_id, $cap ) ) {
				return true;
			}
		}
		return false;
	}

	// -------------------------------------------------------------------
	// list-users
	// -------------------------------------------------------------------

	private function register_list_users(): void {
		$this->ability_names[] = 'emcp-tools/list-users';
		emcp_tools_register_ability(
			'emcp-tools/list-users',
			array(
				'label'               => __( 'List Users', 'emcp-tools' ),
				'description'         => __( 'Lists WordPress users (admin-only). Filter by role or search text; paginated. Returns id, username, display name, email, roles, registration date, and post count. Never returns passwords or auth data.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_users' ),
				'permission_callback' => array( $this, 'can_list' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'role'     => array( 'type' => 'string', 'description' => __( 'Filter by role slug (e.g. author).', 'emcp-tools' ) ),
						'search'   => array( 'type' => 'string', 'description' => __( 'Search login/email/display name.', 'emcp-tools' ) ),
						'per_page' => array( 'type' => 'integer', 'description' => __( '1-100. Default: 20.', 'emcp-tools' ) ),
						'page'     => array( 'type' => 'integer', 'description' => __( 'Default: 1.', 'emcp-tools' ) ),
						'orderby'  => array( 'type' => 'string', 'enum' => array( 'registered', 'display_name', 'ID' ) ),
						'order'    => array( 'type' => 'string', 'enum' => array( 'ASC', 'DESC' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'users' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					'total' => array( 'type' => 'integer' ), 'pages' => array( 'type' => 'integer' ), 'page' => array( 'type' => 'integer' ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array
	 */
	public function execute_list_users( $input ): array {
		$per_page = max( 1, min( 100, absint( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, absint( $input['page'] ?? 1 ) );
		$orderby  = in_array( $input['orderby'] ?? '', array( 'registered', 'display_name', 'ID' ), true ) ? $input['orderby'] : 'registered';
		$order    = ( isset( $input['order'] ) && 'ASC' === strtoupper( (string) $input['order'] ) ) ? 'ASC' : 'DESC';

		$args = array(
			'number'  => $per_page,
			'paged'   => $page,
			'orderby' => $orderby,
			'order'   => $order,
		);
		if ( ! empty( $input['role'] ) ) {
			$args['role'] = sanitize_key( $input['role'] );
		}
		if ( ! empty( $input['search'] ) ) {
			$args['search']         = '*' . sanitize_text_field( $input['search'] ) . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$query = new \WP_User_Query( $args );
		$rows  = array();
		foreach ( (array) $query->get_results() as $u ) {
			$rows[] = $this->format_user_row( $u );
		}
		$total = (int) $query->get_total();
		return array(
			'users' => $rows,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
			'page'  => $page,
		);
	}

	/**
	 * Compact public row for list-users.
	 *
	 * @param object $u WP_User-like.
	 * @return array
	 */
	private function format_user_row( $u ): array {
		return array(
			'id'           => (int) ( $u->ID ?? 0 ),
			'username'     => (string) ( $u->user_login ?? '' ),
			'display_name' => (string) ( $u->display_name ?? '' ),
			'email'        => (string) ( $u->user_email ?? '' ),
			'roles'        => array_values( (array) ( $u->roles ?? array() ) ),
			'registered'   => (string) ( $u->user_registered ?? '' ),
			'post_count'   => function_exists( 'count_user_posts' ) ? (int) count_user_posts( (int) ( $u->ID ?? 0 ) ) : 0,
		);
	}

	// -------------------------------------------------------------------
	// get-user
	// -------------------------------------------------------------------

	private function register_get_user(): void {
		$this->ability_names[] = 'emcp-tools/get-user';
		emcp_tools_register_ability(
			'emcp-tools/get-user',
			array(
				'label'               => __( 'Get User', 'emcp-tools' ),
				'description'         => __( 'Returns one user\'s detail: username, email, display name, first/last name, URL, description, roles, registration date, post count, and an is_admin flag (true users are off-limits to update-user). Never returns passwords or auth data.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_user' ),
				'permission_callback' => array( $this, 'can_list' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'description' => __( 'User ID.', 'emcp-tools' ) ) ),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'id' => array( 'type' => 'integer' ), 'username' => array( 'type' => 'string' ),
					'email' => array( 'type' => 'string' ), 'display_name' => array( 'type' => 'string' ),
					'first_name' => array( 'type' => 'string' ), 'last_name' => array( 'type' => 'string' ),
					'nickname' => array( 'type' => 'string' ), 'url' => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ), 'roles' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'registered' => array( 'type' => 'string' ), 'post_count' => array( 'type' => 'integer' ),
					'is_admin' => array( 'type' => 'boolean' ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_get_user( $input ) {
		$id = absint( $input['id'] ?? 0 );
		if ( ! $id ) {
			return new \WP_Error( 'missing_params', __( 'A user "id" is required.', 'emcp-tools' ) );
		}
		$u = get_userdata( $id );
		if ( ! $u ) {
			return new \WP_Error( 'user_not_found', __( 'User not found.', 'emcp-tools' ) );
		}
		return array(
			'id'           => (int) $u->ID,
			'username'     => (string) ( $u->user_login ?? '' ),
			'email'        => (string) ( $u->user_email ?? '' ),
			'display_name' => (string) ( $u->display_name ?? '' ),
			'first_name'   => (string) ( $u->first_name ?? '' ),
			'last_name'    => (string) ( $u->last_name ?? '' ),
			'nickname'     => (string) ( $u->nickname ?? '' ),
			'url'          => (string) ( $u->user_url ?? '' ),
			'description'  => (string) ( $u->description ?? '' ),
			'roles'        => array_values( (array) ( $u->roles ?? array() ) ),
			'registered'   => (string) ( $u->user_registered ?? '' ),
			'post_count'   => function_exists( 'count_user_posts' ) ? (int) count_user_posts( $id ) : 0,
			'is_admin'     => $this->user_has_admin_caps( $id ),
		);
	}

	// -------------------------------------------------------------------
	// create-user (stub — implemented in Task 3)
	// -------------------------------------------------------------------

	private function register_create_user(): void {}

	// -------------------------------------------------------------------
	// update-user (stub — implemented in Task 4)
	// -------------------------------------------------------------------

	private function register_update_user(): void {}
}
