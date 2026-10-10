<?php
/**
 * EMCP Cloud MCP abilities — status, backup, list, pull, config sync.
 *
 * Free tree. Registered only when the site is connected to EMCP Cloud (the
 * Cloud module is active and a token bundle is stored). Each tool delegates to
 * EMCP_Tools_Cloud_Sync and requires manage_options.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Cloud_Abilities {

	/**
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'emcp-tools/cloud-safe-updates',
			'emcp-tools/cloud-status',
			'emcp-tools/cloud-backup',
			'emcp-tools/cloud-list',
			'emcp-tools/cloud-pull',
			'emcp-tools/cloud-config-sync',
			'emcp-tools/cloud-config-inspect',
			'emcp-tools/cloud-config-deploy',
			'emcp-tools/cloud-marketplace-list',
			'emcp-tools/cloud-marketplace-install',
		);
	}

	/**
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @return void
	 */
	public function register(): void {
		emcp_tools_register_ability( 'emcp-tools/cloud-safe-updates', array(
			'label' => __( 'Cloud safe updates', 'emcp-tools' ),
			'description' => __( 'Inspect available updates or prepare an explicitly approved maintenance and recovery job. Never accepts arbitrary download URLs.', 'emcp-tools' ),
			'category' => 'emcp-tools', 'execute_callback' => array( $this, 'execute_safe_updates' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'input_schema' => array( 'type' => 'object', 'required' => array( 'action' ), 'additionalProperties' => false, 'properties' => array(
				'action' => array( 'type' => 'string', 'enum' => array( 'inspect', 'prepare' ) ),
				'job' => array( 'type' => array( 'string', 'null' ) ), 'token' => array( 'type' => array( 'string', 'null' ) ),
				'site_uuid' => array( 'type' => array( 'string', 'null' ) ), 'fingerprint' => array( 'type' => array( 'string', 'null' ) ),
				'groups' => array( 'type' => array( 'array', 'null' ), 'items' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) ),
				'paths' => array( 'type' => array( 'array', 'null' ), 'items' => array( 'type' => 'string' ) ), 'maintenance_accepted' => array( 'type' => 'boolean' ),
			) ), 'output_schema' => array( 'type' => 'object' ),
			'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => true ), 'show_in_rest' => true ),
		) );
		emcp_tools_register_ability( 'emcp-tools/cloud-config-deploy', array(
			'label' => __( 'Deploy managed Cloud settings', 'emcp-tools' ),
			'description' => __( 'Apply an explicitly approved managed revision with a current fingerprint, inspect its durable receipt, or restore its before-image if changed keys still match. Cannot disable the Cloud recovery channel.', 'emcp-tools' ),
			'category' => 'emcp-tools', 'execute_callback' => array( $this, 'execute_config_deploy' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'input_schema' => array('type'=>'object','required'=>array('action','operation_id','site_uuid'),'additionalProperties'=>false,'properties'=>array(
				'action'=>array('type'=>'string','enum'=>array('apply','rollback','status')),'operation_id'=>array('type'=>'string'),'site_uuid'=>array('type'=>'string'),
				'expected'=>array('type'=>array('string','null')),'settings'=>array('type'=>array('object','null')),'confirm'=>array('type'=>'boolean'),
			)),
			'output_schema' => array('type'=>'object'), 'meta'=>array('annotations'=>array('readonly'=>false,'destructive'=>true),'show_in_rest'=>true),
		));
		emcp_tools_register_ability( 'emcp-tools/cloud-config-inspect', array(
			'label' => __( 'Inspect managed Cloud settings', 'emcp-tools' ),
			'description' => __( 'Read a versioned, fixed allowlist of stored EMCP settings for configuration previews. No settings are changed; secrets and free-text context are excluded.', 'emcp-tools' ),
			'category' => 'emcp-tools', 'execute_callback' => array( $this, 'execute_config_inspect' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'input_schema' => array( 'type' => 'object', 'properties' => new stdClass(), 'additionalProperties' => false ),
			'output_schema' => array( 'type' => 'object' ),
			'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false ), 'show_in_rest' => true ),
		) );
		emcp_tools_register_ability(
			'emcp-tools/cloud-status',
			array(
				'label'               => __( 'Cloud Status', 'emcp-tools' ),
				'description'         => __( 'Return the EMCP Cloud plan, limits, and usage for the connected account.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_status' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array( 'type' => 'object', 'properties' => array( 'description' => array( 'type' => 'string' ) ) ),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false ), 'show_in_rest' => true ),
			)
		);
		emcp_tools_register_ability(
			'emcp-tools/cloud-backup',
			array(
				'label'               => __( 'Cloud Backup', 'emcp-tools' ),
				'description'         => __( 'Back up a local sandbox artifact (block, widget, or PHP snippet) to your EMCP Cloud account.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_backup' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'kind' => array( 'type' => 'string', 'enum' => array( 'block', 'widget', 'snippet' ) ),
						'id'   => array( 'type' => 'integer' ),
					),
					'required'   => array( 'kind', 'id' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
		emcp_tools_register_ability(
			'emcp-tools/cloud-list',
			array(
				'label'               => __( 'Cloud List', 'emcp-tools' ),
				'description'         => __( 'List the artifacts backed up to your EMCP Cloud account (optionally filtered by kind).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array( 'type' => 'object', 'properties' => array( 'kind' => array( 'type' => 'string' ) ) ),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false ), 'show_in_rest' => true ),
			)
		);
		emcp_tools_register_ability(
			'emcp-tools/cloud-pull',
			array(
				'label'               => __( 'Cloud Pull', 'emcp-tools' ),
				'description'         => __( 'Pull a cloud artifact into this site by its UUID. It is imported as a new inactive draft.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_pull' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'artifact_uuid' => array( 'type' => 'string' ),
						'kind'          => array( 'type' => 'string' ),
					),
					'required'   => array( 'artifact_uuid' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false ), 'show_in_rest' => true ),
			)
		);
		emcp_tools_register_ability(
			'emcp-tools/cloud-config-sync',
			array(
				'label'               => __( 'Cloud Config Sync', 'emcp-tools' ),
				'description'         => __( 'Push or pull a config blob (settings, brand_kit, tool_toggles) to/from EMCP Cloud.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_config_sync' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'      => array( 'type' => 'string', 'enum' => array( 'settings', 'brand_kit', 'tool_toggles' ) ),
						'direction' => array( 'type' => 'string', 'enum' => array( 'push', 'pull' ) ),
						'data'      => array( 'type' => 'object' ),
					),
					'required'   => array( 'type', 'direction' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false ), 'show_in_rest' => true ),
			)
		);
		emcp_tools_register_ability(
			'emcp-tools/cloud-marketplace-list',
			array(
				'label'               => __( 'Cloud Marketplace List', 'emcp-tools' ),
				'description'         => __( 'Browse published EMCP Cloud marketplace listings (blocks, widgets, snippets).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_marketplace_list' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array( 'type' => 'object', 'properties' => array( 'category' => array( 'type' => 'string' ) ) ),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false ), 'show_in_rest' => true ),
			)
		);
		emcp_tools_register_ability(
			'emcp-tools/cloud-marketplace-install',
			array(
				'label'               => __( 'Cloud Marketplace Install', 'emcp-tools' ),
				'description'         => __( 'Install a marketplace listing by slug. It is imported into this site as a new inactive draft.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_marketplace_install' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'slug' => array( 'type' => 'string' ) ),
					'required'   => array( 'slug' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false ), 'show_in_rest' => true ),
			)
		);
	}

	public function execute_config_inspect( $input ) {
		if ( ! empty( (array) $input ) ) { return new WP_Error( 'invalid_input', 'This read accepts no settings or actions.' ); }
		return EMCP_Tools_Settings_Sync::managed_snapshot();
	}
	public function execute_safe_updates( $input ) {
		require_once __DIR__ . '/../cloud/class-safe-updates.php';
		return EMCP_Tools_Safe_Updates::execute( $input );
	}
	public function execute_config_deploy( $input ) {
		require_once __DIR__ . '/../cloud/class-config-deployment.php';
		$result = EMCP_Tools_Config_Deployment::execute( $input );
		return is_wp_error($result) ? array('status'=>'rejected','code'=>$result->get_error_code()) : $result;
	}

	/**
	 * @param array $input { category? }.
	 * @return array|WP_Error
	 */
	public function execute_marketplace_list( $input ) {
		$category = isset( $input['category'] ) ? sanitize_key( (string) $input['category'] ) : '';
		$r        = EMCP_Tools_Cloud_Sync::marketplace_list( $category );
		return is_wp_error( $r ) ? $r : (array) $r;
	}

	/**
	 * @param array $input { slug }.
	 * @return array|WP_Error
	 */
	public function execute_marketplace_install( $input ) {
		$slug = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : '';
		$r    = EMCP_Tools_Cloud_Sync::marketplace_install( $slug );
		return is_wp_error( $r ) ? $r : (array) $r;
	}

	/**
	 * @return array|WP_Error
	 */
	public function execute_status() {
		$r = EMCP_Tools_Cloud_Sync::status();
		return is_wp_error( $r ) ? $r : (array) $r;
	}

	/**
	 * @param array $input { kind, id }.
	 * @return array|WP_Error
	 */
	public function execute_backup( $input ) {
		$kind = isset( $input['kind'] ) ? sanitize_key( (string) $input['kind'] ) : '';
		$id   = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$r    = EMCP_Tools_Cloud_Sync::backup( $kind, $id );
		return is_wp_error( $r ) ? $r : (array) $r;
	}

	/**
	 * @param array $input { kind? }.
	 * @return array|WP_Error
	 */
	public function execute_list( $input ) {
		$kind = isset( $input['kind'] ) ? sanitize_key( (string) $input['kind'] ) : '';
		$r    = EMCP_Tools_Cloud_Sync::list_remote( $kind );
		return is_wp_error( $r ) ? $r : (array) $r;
	}

	/**
	 * @param array $input { artifact_uuid, kind? }.
	 * @return array|WP_Error
	 */
	public function execute_pull( $input ) {
		$uuid = isset( $input['artifact_uuid'] ) ? sanitize_text_field( (string) $input['artifact_uuid'] ) : '';
		$kind = isset( $input['kind'] ) ? sanitize_key( (string) $input['kind'] ) : '';
		$r    = EMCP_Tools_Cloud_Sync::pull( $uuid, $kind );
		return is_wp_error( $r ) ? $r : (array) $r;
	}

	/**
	 * @param array $input { type, direction, data? }.
	 * @return array|WP_Error
	 */
	public function execute_config_sync( $input ) {
		$type      = isset( $input['type'] ) ? sanitize_key( (string) $input['type'] ) : '';
		$direction = isset( $input['direction'] ) ? sanitize_key( (string) $input['direction'] ) : 'pull';
		if ( 'push' === $direction ) {
			$data = isset( $input['data'] ) && is_array( $input['data'] ) ? $input['data'] : array();
			$r    = EMCP_Tools_Cloud_Sync::push_config( $type, $data );
		} else {
			$r = EMCP_Tools_Cloud_Sync::pull_config( $type );
		}
		return is_wp_error( $r ) ? $r : (array) $r;
	}
}
