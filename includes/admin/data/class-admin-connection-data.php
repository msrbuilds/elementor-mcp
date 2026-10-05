<?php
/**
 * Connection screen data (spec 8.2): payload, advanced and services appliers,
 * and setup validation for the first-call check (spec 9.5).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_Connection_Data {

	/** Clients whose config can run a local `wp mcp-adapter serve`. */
	const CLI_CLIENTS = array( 'claude-desktop', 'claude-code', 'cursor', 'antigravity' );

	/** Methods the wizard offers. */
	const METHODS = array( 'oauth', 'app', 'cli' );

	/** @var EMCP_Tools_Admin */
	private $admin;

	public function __construct( EMCP_Tools_Admin $admin ) {
		$this->admin = $admin;
	}

	/** @return string[] */
	public static function cli_clients(): array {
		return self::CLI_CLIENTS;
	}

	/** WP-CLI stdio only makes sense for a site on the same computer. */
	public static function local_cli_available(): bool {
		return in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
	}

	/** Same rules as the options.php sanitizer (trait-admin-settings.php). */
	public static function sanitize_base_url( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		$value  = esc_url_raw( $value );
		$scheme = wp_parse_url( $value, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}
		return rtrim( $value, '/' );
	}

	private static function oauth_available(): bool {
		return class_exists( 'EMCP_Tools_OAuth_Server' ) && EMCP_Tools_OAuth_Server::is_available();
	}

	public function advanced(): array {
		return array(
			'server_enabled'  => '1' === (string) get_option( 'emcp_tools_server_enabled', '1' ),
			'oauth_enabled'   => class_exists( 'EMCP_Tools_OAuth_Server' ) && EMCP_Tools_OAuth_Server::option_enabled(),
			'strict_schemas'  => '1' === (string) get_option( 'emcp_tools_strict_schemas', '0' ),
			'public_base_url' => (string) get_option( EMCP_Tools_Site_Context::OPTION_BASE_URL, '' ),
		);
	}

	/**
	 * @param array $in Keys present are written: bools and public_base_url.
	 * @return array{ignored: string[]}
	 */
	public function apply_advanced( array $in ): array {
		$ignored = array();
		$bools   = array(
			'server_enabled' => 'emcp_tools_server_enabled',
			'oauth_enabled'  => 'emcp_tools_oauth_enabled',
			'strict_schemas' => 'emcp_tools_strict_schemas',
		);
		foreach ( $bools as $key => $option ) {
			if ( ! array_key_exists( $key, $in ) || null === $in[ $key ] ) {
				continue;
			}
			$on = rest_sanitize_boolean( $in[ $key ] );
			if ( 'oauth_enabled' === $key && $on && ! self::oauth_available() ) {
				$ignored[] = $key;
				continue;
			}
			update_option( $option, $on ? '1' : '0' );
		}
		if ( array_key_exists( 'public_base_url', $in ) && null !== $in['public_base_url'] ) {
			update_option( EMCP_Tools_Site_Context::OPTION_BASE_URL, self::sanitize_base_url( (string) $in['public_base_url'] ) );
		}
		return array( 'ignored' => $ignored );
	}

	/** Service fields; secret values are never included. */
	public function services(): array {
		$fields = array();
		$stock  = array(
			array( EMCP_Tools_Unsplash_Client::OPTION, 'Unsplash', 'EMCP_TOOLS_UNSPLASH_ACCESS_KEY', 'https://unsplash.com/developers' ),
			array( EMCP_Tools_Pexels_Client::OPTION, 'Pexels', 'EMCP_TOOLS_PEXELS_API_KEY', 'https://www.pexels.com/api/' ),
			array( EMCP_Tools_Pixabay_Client::OPTION, 'Pixabay', 'EMCP_TOOLS_PIXABAY_API_KEY', 'https://pixabay.com/api/docs/' ),
		);
		foreach ( $stock as $row ) {
			$fields[] = self::secret_field( $row[0], $row[1], 'stock', $row[2], '', $row[3] );
		}
		if ( class_exists( 'EMCP_Tools_Remote_Keys' ) ) {
			foreach ( EMCP_Tools_Remote_Keys::providers() as $slug => $p ) {
				$fields[] = self::secret_field( EMCP_Tools_Remote_Keys::option( $slug ), (string) $p['label'], 'remote', (string) $p['const'], (string) ( $p['hint'] ?? '' ), (string) ( $p['url'] ?? '' ) );
			}
		}
		$fields[] = array(
			'key'          => 'emcp_tools_wpcli_command',
			'label'        => __( 'WP-CLI command', 'emcp-tools' ),
			'group'        => 'wpcli',
			'secret'       => false,
			'hasValue'     => '' !== (string) get_option( 'emcp_tools_wpcli_command', '' ),
			'fromConstant' => defined( 'EMCP_TOOLS_WPCLI_COMMAND' ),
			'value'        => (string) get_option( 'emcp_tools_wpcli_command', '' ),
			'hint'         => __( 'For example wp, or php /path/to/wp-cli.phar. Leave empty to run WP-CLI tools in-process only.', 'emcp-tools' ),
			'url'          => '',
		);
		return $fields;
	}

	private static function secret_field( string $option, string $label, string $group, string $const, string $hint, string $url ): array {
		$from_constant = '' !== $const && defined( $const );
		return array(
			'key'          => $option,
			'label'        => $label,
			'group'        => $group,
			'secret'       => true,
			'hasValue'     => $from_constant || '' !== (string) get_option( $option, '' ),
			'fromConstant' => $from_constant,
			'value'        => '',
			'hint'         => $hint,
			'url'          => $url,
		);
	}

	/**
	 * Secret: string sets (encrypted), '' keeps, null clears. WP-CLI command:
	 * string sets, null clears. Constants and unknown keys are ignored.
	 *
	 * @param array $values option => value.
	 * @return array{saved: string[], ignored: string[]}
	 */
	public function apply_services( array $values ): array {
		$fields  = array_column( $this->services(), null, 'key' );
		$saved   = array();
		$ignored = array();
		foreach ( $values as $key => $value ) {
			$key = (string) $key;
			if ( ! isset( $fields[ $key ] ) || $fields[ $key ]['fromConstant'] || ( null !== $value && ! is_scalar( $value ) ) ) {
				$ignored[] = $key;
				continue;
			}
			if ( ! $fields[ $key ]['secret'] ) {
				update_option( $key, null === $value ? '' : sanitize_text_field( (string) $value ) );
				$saved[] = $key;
				continue;
			}
			if ( null === $value ) {
				delete_option( $key );
				$saved[] = $key;
				continue;
			}
			$value = sanitize_text_field( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			update_option( $key, EMCP_Tools_Secret::is_encrypted( $value ) ? $value : EMCP_Tools_Secret::encrypt( $value ) );
			$saved[] = $key;
		}
		return array(
			'saved'   => $saved,
			'ignored' => $ignored,
		);
	}

	/** Connected OAuth apps (first 100). */
	public function apps(): array {
		if ( ! class_exists( 'EMCP_Tools_OAuth_Store' ) ) {
			return array();
		}
		$gateway = class_exists( 'EMCP_Tools_Gateway_Credential' ) ? EMCP_Tools_Gateway_Credential::CLIENT_NAME : '';
		$out     = array();
		foreach ( EMCP_Tools_OAuth_Store::list_clients( 100, 0 ) as $row ) {
			$user  = ! empty( $row['user_id'] ) ? get_userdata( (int) $row['user_id'] ) : false;
			$out[] = array(
				'id'           => (string) $row['client_id'],
				'name'         => (string) $row['client_name'],
				'state'        => EMCP_Tools_OAuth_Store::client_state( $row ),
				'activeTokens' => (int) ( $row['active_tokens'] ?? 0 ),
				'user'         => $user ? (string) $user->user_login : '',
				'created'      => (int) ( $row['created_at'] ?? 0 ),
				'gateway'      => '' !== $gateway && $gateway === $row['client_name'],
			);
		}
		return $out;
	}

	/** Server status for the right rail. */
	public function status(): array {
		return array(
			'adapter'       => class_exists( 'EMCP_Tools_Adapter_Bootstrap' ) ? EMCP_Tools_Adapter_Bootstrap::source() : 'none',
			'adapterCopy'   => class_exists( 'EMCP_Tools_Adapter_Bootstrap' ) && method_exists( 'EMCP_Tools_Adapter_Bootstrap', 'core_source' ) ? array_intersect_key( EMCP_Tools_Adapter_Bootstrap::core_source(), array( 'plugin' => 1, 'version' => 1, 'ours' => 1 ) ) : null,
			'abilitiesApi'  => function_exists( 'wp_register_ability' ),
			'serverEnabled' => '1' === (string) get_option( 'emcp_tools_server_enabled', '1' ),
			'toolsEnabled'  => $this->admin->get_enabled_tool_count(),
			'toolsTotal'    => $this->admin->get_total_tool_count(),
		);
	}

	public function oauth(): array {
		return array(
			'available' => self::oauth_available(),
			'enabled'   => class_exists( 'EMCP_Tools_OAuth_Server' ) && EMCP_Tools_OAuth_Server::is_enabled(),
		);
	}

	/**
	 * @param int    $user_id Current user.
	 * @param string $client  Client id.
	 * @param string $method  oauth | app | cli.
	 * @param string $expect  '' | app:{uuid} | oauth:{client_id}.
	 * @param int    $pw_user Owner of the application password named in $expect.
	 * @return true|WP_Error
	 */
	public function validate_setup( int $user_id, string $client, string $method, string $expect, int $pw_user ) {
		if ( ! in_array( $client, array_column( EMCP_Tools_Admin::connection_clients(), 'id' ), true ) ) {
			return new WP_Error( 'emcp_setup_client', __( 'Unknown client.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		if ( ! in_array( $method, self::METHODS, true ) || ( 'cli' === $method && ( ! self::local_cli_available() || ! in_array( $client, self::CLI_CLIENTS, true ) ) ) ) {
			return new WP_Error( 'emcp_setup_method', __( 'That sign-in method is not available for this client.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		if ( '' === $expect ) {
			return true;
		}
		$ok = false;
		if ( 'app' === $method && 0 === strpos( $expect, 'app:' ) ) {
			$owner = $pw_user ? $pw_user : $user_id;
			$ok    = current_user_can( 'edit_user', $owner ) && in_array( substr( $expect, 4 ), array_column( $this->admin->list_app_passwords( $owner ), 'uuid' ), true );
		} elseif ( 'oauth' === $method && 0 === strpos( $expect, 'oauth:' ) && class_exists( 'EMCP_Tools_OAuth_Store' ) ) {
			$ok = null !== EMCP_Tools_OAuth_Store::get_client( substr( $expect, 6 ) );
		}
		return $ok ? true : new WP_Error( 'emcp_setup_expect', __( 'That credential cannot be used for this setup.', 'emcp-tools' ), array( 'status' => 400 ) );
	}

	/** The screen payload. */
	public function payload(): array {
		$clients = array();
		foreach ( EMCP_Tools_Admin::connection_clients() as $c ) {
			$clients[] = array(
				'id'         => (string) $c['id'],
				'label'      => (string) $c['label'],
				'image'      => ! empty( $c['image'] ) ? EMCP_TOOLS_URL . 'assets/img/' . $c['image'] : '',
				'methods'    => $c['methods'],
				'oauth'      => $c['oauth'] ?? null,
				'guideTitle' => (string) ( $c['guide_title'] ?? '' ),
				'guide'      => isset( $c['guide'] ) ? wp_kses_post( (string) $c['guide'] ) : '',
				'cli'        => in_array( $c['id'], self::CLI_CLIENTS, true ),
			);
		}
		$users = array();
		foreach ( get_users( array( 'role' => 'administrator', 'orderby' => 'display_name', 'order' => 'ASC' ) ) as $u ) {
			if ( current_user_can( 'edit_user', $u->ID ) ) {
				$users[] = array( 'id' => (int) $u->ID, 'login' => (string) $u->user_login, 'name' => (string) $u->display_name );
			}
		}
		$me = get_current_user_id();
		usort( $users, static function ( $a, $b ) use ( $me ) { return ( $b['id'] === $me ) <=> ( $a['id'] === $me ); } );

		return array(
			'endpoint'        => EMCP_Tools_Site_Context::mcp_endpoint(),
			'siteUrl'         => EMCP_Tools_Site_Context::public_base_url(),
			'detectedBaseUrl' => EMCP_Tools_Site_Context::detected_base_url(),
			'profileUrl'      => admin_url( 'profile.php#application-passwords-section' ),
			'clients'         => $clients,
			'localCli'        => array(
				'available' => self::local_cli_available(),
				'command'   => '' !== (string) get_option( 'emcp_tools_wpcli_command', '' ) ? (string) get_option( 'emcp_tools_wpcli_command' ) : 'wp',
				'path'      => untrailingslashit( wp_normalize_path( ABSPATH ) ),
			),
			'users'           => $users,
			'currentUserId'   => $me,
			'status'          => $this->status(),
			'oauth'           => $this->oauth(),
			'apps'            => $this->apps(),
			'advanced'        => $this->advanced(),
			'services'        => $this->services(),
			'cloud'           => $this->cloud(),
			'mcpb'            => array(
				'url'    => admin_url( 'admin-post.php' ),
				'action' => 'emcp_tools_download_mcpb',
				'nonce'  => wp_create_nonce( 'emcp_tools_download_mcpb' ),
			),
			'adminPostUrl'    => admin_url( 'admin-post.php' ),
		);
	}

	private function cloud(): ?array {
		if ( ! class_exists( 'EMCP_Tools_Cloud_Module' ) || ! EMCP_Tools_Cloud_Module::is_enabled() || ! class_exists( 'EMCP_Tools_Cloud' ) ) {
			return null;
		}
		$status = EMCP_Tools_Cloud::status();
		$sync   = null;
		if ( $status['connected'] ) {
			$sync = array(
				'entitled'   => class_exists( 'EMCP_Tools_Settings_Sync' ) && EMCP_Tools_Settings_Sync::entitled(),
				'nonce'      => wp_create_nonce( 'emcp_tools_settings_sync' ),
				'billingUrl' => trailingslashit( EMCP_Tools_Cloud::base_url() ) . 'account/billing',
			);
		}
		return array_merge(
			self::cloud_account( EMCP_Tools_Cloud::get_connection(), (string) $status['base_url'] ),
			array(
				'connected'     => (bool) $status['connected'],
				'healthy'       => (bool) $status['healthy'],
				'baseUrl'       => (string) $status['base_url'],
				'gateway'       => class_exists( 'EMCP_Tools_Gateway_Credential' ) && (bool) get_option( EMCP_Tools_Gateway_Credential::OPTION_FLAG, 0 ),
				'connectAction' => EMCP_Tools_Cloud_Connect::ACTION_CONNECT,
				'connectNonce'  => wp_create_nonce( EMCP_Tools_Cloud_Connect::ACTION_CONNECT ),
				'identityConflict' => EMCP_Tools_Cloud::identity_conflict(),
				'separateAction' => EMCP_Tools_Cloud_Connect::ACTION_SEPARATE,
				'separateNonce' => wp_create_nonce( EMCP_Tools_Cloud_Connect::ACTION_SEPARATE ),
				'disconnectUrl' => EMCP_Tools_Cloud_Connect::disconnect_url(),
				'reissueUrl'    => EMCP_Tools_Cloud_Connect::reissue_url(),
				'gatewayOffUrl' => EMCP_Tools_Cloud_Connect::disable_gateway_url(),
				'sync'          => $sync,
			)
		);
	}

	/**
	 * Who the site is linked to, for the Cloud account card. The email is
	 * redacted here, so the full address never reaches the page.
	 *
	 * @param array  $connection EMCP_Tools_Cloud::get_connection().
	 * @param string $base_url   Cloud base URL.
	 * @return array{account:string,host:string,connectedAt:string}
	 */
	public static function cloud_account( array $connection, string $base_url ): array {
		$at = (int) ( $connection['connected_at'] ?? 0 );
		return array(
			'account'     => EMCP_Tools_Cloud::redact_email( (string) ( $connection['account_email'] ?? '' ) ),
			'host'        => (string) wp_parse_url( $base_url, PHP_URL_HOST ),
			'connectedAt' => $at > 0 ? gmdate( 'Y-m-d', $at ) : '',
		);
	}
}
