<?php
/**
 * Connection info tab view for the MCP Tools for Elementor admin settings page.
 *
 * Organised into two sub-tabs: "Connections" (server gate + strict schemas +
 * status + client wizard) and "3rd Party Services" (stock-image provider keys).
 *
 * @package EMCP_Tools
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var EMCP_Tools_Admin $this */
$emcp_tools_endpoint      = rest_url( 'mcp/emcp-tools-server' );
$emcp_tools_enabled_count = $this->get_enabled_tool_count();
$emcp_tools_total_count   = $this->get_total_tool_count();
$emcp_tools_has_adapter   = class_exists( '\WP\MCP\Core\McpAdapter' );

// Adapter provenance: bundled with EMCP, an external plugin, or unavailable.
$emcp_tools_adapter_source = class_exists( 'EMCP_Tools_Adapter_Bootstrap' )
	? EMCP_Tools_Adapter_Bootstrap::source()
	: ( $emcp_tools_has_adapter ? 'external' : 'none' );
$emcp_tools_adapter_label = 'bundled' === $emcp_tools_adapter_source
	? __( 'Active (bundled)', 'emcp-tools' )
	: ( 'external' === $emcp_tools_adapter_source ? __( 'Active (plugin)', 'emcp-tools' ) : __( 'Not Active', 'emcp-tools' ) );

// Abilities API is core in WordPress 6.9+/7.0.
$emcp_tools_has_abilities = function_exists( 'wp_register_ability' );

// The "Activate Abilities API for EMCP" gate (on by default).
$emcp_tools_server_enabled = class_exists( 'EMCP_Tools_Plugin' )
	? EMCP_Tools_Plugin::is_server_enabled()
	: ( '1' === (string) get_option( 'emcp_tools_server_enabled', '1' ) );
?>

<div class="elementor-mcp-connection">

	<div class="emcp-conn-subhead">
		<div class="elementor-mcp-subtabs elementor-mcp-subtabs--flush" data-subtab-key="connection" role="tablist" aria-label="<?php esc_attr_e( 'Connection sections', 'emcp-tools' ); ?>">
			<button type="button" class="elementor-mcp-subtab is-active" role="tab" data-tab="conn-main" aria-selected="true" aria-controls="emcp-conn-main">
				<span class="elementor-mcp-subtab-label"><?php esc_html_e( 'Connections', 'emcp-tools' ); ?></span>
			</button>
			<button type="button" class="elementor-mcp-subtab" role="tab" data-tab="conn-services" aria-selected="false" aria-controls="emcp-conn-services">
				<span class="elementor-mcp-subtab-label"><?php esc_html_e( '3rd Party Services', 'emcp-tools' ); ?></span>
			</button>
		</div>
		<div class="emcp-subtab-actions">
			<button type="submit" form="emcp-conn-form" class="button button-primary emcp-subtab-save" data-save-for="conn-main"><?php esc_html_e( 'Save Settings', 'emcp-tools' ); ?></button>
			<button type="submit" form="emcp-conn-services-form" class="button button-primary emcp-subtab-save" data-save-for="conn-services" hidden><?php esc_html_e( 'Save Settings', 'emcp-tools' ); ?></button>
		</div>
	</div>

	<?php // ===== Sub-tab: Connections ===== ?>
	<div class="elementor-mcp-tabpanel is-active" id="emcp-conn-main" role="tabpanel" data-tab="conn-main">

		<!-- Server Status -->
		<div class="elementor-mcp-section">
			<h2><?php esc_html_e( 'Server Status', 'emcp-tools' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Current status of your MCP server and connected components.', 'emcp-tools' ); ?></p>

			<div class="elementor-mcp-status-grid">
				<div class="elementor-mcp-status-card">
					<span class="elementor-mcp-status-card-icon elementor-mcp-status-card-icon--ok">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>
					</span>
					<span class="elementor-mcp-status-card-info">
						<span class="elementor-mcp-status-card-label"><?php esc_html_e( 'MCP Tools for Elementor', 'emcp-tools' ); ?></span>
						<span class="elementor-mcp-status-card-value"><?php esc_html_e( 'Active', 'emcp-tools' ); ?></span>
					</span>
				</div>

				<div class="elementor-mcp-status-card">
					<span class="elementor-mcp-status-card-icon <?php echo esc_attr( $emcp_tools_has_adapter ? 'elementor-mcp-status-card-icon--ok' : 'elementor-mcp-status-card-icon--warn' ); ?>">
						<?php if ( $emcp_tools_has_adapter ) : ?>
							<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>
						<?php else : ?>
							<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/></svg>
						<?php endif; ?>
					</span>
					<span class="elementor-mcp-status-card-info">
						<span class="elementor-mcp-status-card-label"><?php esc_html_e( 'MCP Adapter', 'emcp-tools' ); ?></span>
						<span class="elementor-mcp-status-card-value"><?php echo esc_html( $emcp_tools_adapter_label ); ?></span>
					</span>
				</div>

				<div class="elementor-mcp-status-card">
					<span class="elementor-mcp-status-card-icon <?php echo esc_attr( $emcp_tools_server_enabled ? 'elementor-mcp-status-card-icon--ok' : 'elementor-mcp-status-card-icon--warn' ); ?>">
						<?php if ( $emcp_tools_server_enabled ) : ?>
							<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>
						<?php else : ?>
							<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/></svg>
						<?php endif; ?>
					</span>
					<span class="elementor-mcp-status-card-info">
						<span class="elementor-mcp-status-card-label"><?php esc_html_e( 'MCP Server', 'emcp-tools' ); ?></span>
						<span class="elementor-mcp-status-card-value"><?php echo esc_html( $emcp_tools_server_enabled ? __( 'Enabled', 'emcp-tools' ) : __( 'Disabled', 'emcp-tools' ) ); ?></span>
					</span>
				</div>

				<div class="elementor-mcp-status-card">
					<span class="elementor-mcp-status-card-icon elementor-mcp-status-card-icon--ok">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
					</span>
					<span class="elementor-mcp-status-card-info">
						<span class="elementor-mcp-status-card-label"><?php esc_html_e( 'Tools Enabled', 'emcp-tools' ); ?></span>
						<span class="elementor-mcp-status-card-value">
							<?php
							printf(
								/* translators: %1$d: enabled count, %2$d: total count */
								esc_html__( '%1$d / %2$d', 'emcp-tools' ),
								(int) $emcp_tools_enabled_count,
								(int) $emcp_tools_total_count
							);
							?>
						</span>
					</span>
				</div>
			</div>

			<div class="elementor-mcp-endpoint">
				<code><?php echo esc_html( $emcp_tools_endpoint ); ?></code>
				<button type="button" class="button elementor-mcp-copy-btn" data-target="elementor-mcp-endpoint-copy"><?php esc_html_e( 'Copy', 'emcp-tools' ); ?></button>
				<textarea id="elementor-mcp-endpoint-copy" class="elementor-mcp-copy-source"><?php echo esc_html( $emcp_tools_endpoint ); ?></textarea>
			</div>
		</div>

		<form method="post" action="options.php" id="emcp-conn-form" class="elementor-mcp-activate-form">
			<?php settings_fields( EMCP_Tools_Admin::SETTINGS_GROUP_SERVER ); ?>

			<div class="emcp-conn-cards">

				<?php // Card A: server gate. ?>
				<div class="emcp-conn-card">
					<h2 class="emcp-conn-card-title"><?php esc_html_e( 'Activate Abilities API for EMCP', 'emcp-tools' ); ?></h2>

					<label class="emcp-switch emcp-conn-toggle">
						<input
							type="checkbox"
							name="<?php echo esc_attr( EMCP_Tools_Plugin::OPTION_SERVER_ENABLED ); ?>"
							value="1"
							<?php checked( $emcp_tools_server_enabled ); ?>
						/>
						<span class="elementor-mcp-toggle" aria-hidden="true"><span class="elementor-mcp-toggle-track"></span></span>
						<span class="emcp-switch-label"><?php esc_html_e( 'Expose EMCP tools to AI agents on this site', 'emcp-tools' ); ?></span>
					</label>

					<p class="elementor-mcp-activate-note elementor-mcp-activate-note--security">
						<strong><?php esc_html_e( 'Security note:', 'emcp-tools' ); ?></strong>
						<?php esc_html_e( 'When enabled, connected AI agents can create, edit, and delete Elementor pages and content on this site through the MCP server. Use a capable AI model and set your client to ask for confirmation before every action — read what the agent is about to do before approving.', 'emcp-tools' ); ?>
					</p>
					<p class="elementor-mcp-activate-note">
						<?php
						if ( $emcp_tools_has_abilities ) {
							printf(
								/* translators: %s: how the MCP Adapter is provided. */
								esc_html__( 'WordPress Abilities API: core (no separate plugin needed). MCP Adapter: %s.', 'emcp-tools' ),
								'bundled' === $emcp_tools_adapter_source
									? esc_html__( 'bundled with EMCP', 'emcp-tools' )
									: ( 'external' === $emcp_tools_adapter_source ? esc_html__( 'provided by an active MCP Adapter plugin', 'emcp-tools' ) : esc_html__( 'unavailable', 'emcp-tools' ) )
							);
						} else {
							esc_html_e( 'WordPress Abilities API is unavailable — WordPress 6.9 or newer is required.', 'emcp-tools' );
						}
						?>
					</p>
				</div>

				<?php // Card B: strict schemas. ?>
				<div class="emcp-conn-card">
					<h2 class="emcp-conn-card-title"><?php esc_html_e( 'OpenAI-strict tool schemas', 'emcp-tools' ); ?></h2>

					<label class="emcp-switch emcp-conn-toggle">
						<input
							type="checkbox"
							name="emcp_tools_strict_schemas"
							value="1"
							<?php checked( '1' === (string) get_option( 'emcp_tools_strict_schemas', '0' ) ); ?>
						/>
						<span class="elementor-mcp-toggle" aria-hidden="true"><span class="elementor-mcp-toggle-track"></span></span>
						<span class="emcp-switch-label"><?php esc_html_e( 'Enable strict function-calling schemas', 'emcp-tools' ); ?></span>
					</label>

					<p class="elementor-mcp-activate-note">
						<?php esc_html_e( 'Enable only for OpenAI-compatible strict function-calling clients (e.g. CrewAI) that reject the default tool schemas. It lists every property as required (optional ones become nullable) and sets additionalProperties:false. Leave this OFF for Claude, Gemini, and Antigravity — they work with the default schemas, and strict mode can break Gemini/Antigravity.', 'emcp-tools' ); ?>
					</p>
					<p class="elementor-mcp-activate-note">
						<?php
						printf(
							/* translators: %s: link to the Tools tab. */
							esc_html__( 'Looking for Compact tool mode? It now lives on the %s tab, next to the per-tool toggles it works with.', 'emcp-tools' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=emcp-tools-tools' ) ) . '">' . esc_html__( 'Tools', 'emcp-tools' ) . '</a>'
						);
						?>
					</p>
				</div>

				<?php // Card C: OAuth sign-in. ?>
				<?php $emcp_oauth_available = class_exists( 'EMCP_Tools_OAuth_Server' ) && EMCP_Tools_OAuth_Server::is_available(); ?>
				<div class="emcp-conn-card">
					<h2 class="emcp-conn-card-title"><?php esc_html_e( 'OAuth sign-in for AI clients', 'emcp-tools' ); ?></h2>

					<label class="emcp-switch emcp-conn-toggle">
						<input
							type="checkbox"
							name="<?php echo esc_attr( EMCP_Tools_OAuth_Server::OPTION_ENABLED ); ?>"
							value="1"
							<?php checked( class_exists( 'EMCP_Tools_OAuth_Server' ) && EMCP_Tools_OAuth_Server::option_enabled() ); ?>
							<?php disabled( ! $emcp_oauth_available ); ?>
						/>
						<span class="elementor-mcp-toggle" aria-hidden="true"><span class="elementor-mcp-toggle-track"></span></span>
						<span class="emcp-switch-label"><?php esc_html_e( 'Let clients connect by signing in (OAuth) — no password to copy', 'emcp-tools' ); ?></span>
					</label>

					<?php if ( ! $emcp_oauth_available ) : ?>
						<p class="elementor-mcp-activate-note elementor-mcp-activate-note--security">
							<?php esc_html_e( 'OAuth requires HTTPS. This site is not served over HTTPS, so OAuth sign-in is unavailable — use an Application Password below.', 'emcp-tools' ); ?>
						</p>
					<?php endif; ?>
					<p class="elementor-mcp-activate-note">
						<?php esc_html_e( 'Claude and other MCP clients connect through a standard authorization flow: they open a page where you approve access from your WordPress login. Administrators only; Application Passwords keep working alongside it.', 'emcp-tools' ); ?>
					</p>
				</div>

			</div>
		</form>

		<?php // ===== OAuth sign-in details ===== ?>
		<?php if ( class_exists( 'EMCP_Tools_OAuth_Server' ) && EMCP_Tools_OAuth_Server::is_enabled() ) : ?>
			<?php $emcp_oauth_url = esc_url_raw( rest_url( 'mcp/emcp-tools-server' ) ); ?>
			<div class="elementor-mcp-section">
				<h2><?php esc_html_e( 'Sign in with OAuth', 'emcp-tools' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Add this site as a connector in your AI client, then approve the connection from your WordPress login — no Application Password to copy.', 'emcp-tools' ); ?></p>

				<div class="elementor-mcp-cred-field" style="max-width:640px;">
					<label for="emcp-oauth-url-copy"><?php esc_html_e( 'Connector URL (MCP server)', 'emcp-tools' ); ?></label>
					<div class="elementor-mcp-auth-result">
						<code id="emcp-oauth-url"><?php echo esc_html( $emcp_oauth_url ); ?></code>
						<button type="button" class="button elementor-mcp-copy-btn" data-target="emcp-oauth-url-copy"><?php esc_html_e( 'Copy', 'emcp-tools' ); ?></button>
						<textarea id="emcp-oauth-url-copy" class="elementor-mcp-copy-source"><?php echo esc_textarea( $emcp_oauth_url ); ?></textarea>
					</div>
				</div>

				<details class="elementor-mcp-cred-advanced" style="margin-top:12px;">
					<summary><?php esc_html_e( 'Per-client steps', 'emcp-tools' ); ?></summary>
					<ul style="margin:10px 0 0 18px;list-style:disc;">
						<li><strong>Claude</strong> — <?php esc_html_e( 'Settings → Connectors → Add custom connector → paste the URL → Connect → approve the sign-in.', 'emcp-tools' ); ?></li>
						<li><strong>Cursor / VS Code</strong> — <?php esc_html_e( 'Add an MCP server of type "http" with the URL above; complete the browser sign-in when prompted.', 'emcp-tools' ); ?></li>
					</ul>
				</details>

				<?php
				$emcp_oauth_clients = class_exists( 'EMCP_Tools_OAuth_Store' ) ? EMCP_Tools_OAuth_Store::list_authorized_clients() : array();
				if ( ! empty( $emcp_oauth_clients ) ) :
					?>
					<h3 style="margin-top:22px;"><?php esc_html_e( 'Authorized clients', 'emcp-tools' ); ?></h3>
					<table class="widefat striped" style="max-width:760px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Client', 'emcp-tools' ); ?></th>
								<th><?php esc_html_e( 'Connected as', 'emcp-tools' ); ?></th>
								<th><?php esc_html_e( 'Active tokens', 'emcp-tools' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
						<?php
						foreach ( $emcp_oauth_clients as $emcp_oc ) :
							$emcp_oc_user = get_userdata( $emcp_oc['user_id'] );
							$emcp_revoke  = wp_nonce_url(
								admin_url( 'admin-post.php?action=' . EMCP_Tools_Admin::ACTION_REVOKE_OAUTH . '&client=' . rawurlencode( $emcp_oc['client_id'] ) ),
								EMCP_Tools_Admin::ACTION_REVOKE_OAUTH . '_' . $emcp_oc['client_id']
							);
							?>
							<tr>
								<td><?php echo esc_html( $emcp_oc['client_name'] ); ?></td>
								<td><?php echo esc_html( $emcp_oc_user ? $emcp_oc_user->user_login : '#' . (int) $emcp_oc['user_id'] ); ?></td>
								<td><?php echo (int) $emcp_oc['active_tokens']; ?></td>
								<td><a href="<?php echo esc_url( $emcp_revoke ); ?>" class="button button-small"><?php esc_html_e( 'Revoke', 'emcp-tools' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<!-- HTTP Connection -->
		<div class="elementor-mcp-section">
			<h2><?php esc_html_e( 'Connect Your AI Client', 'emcp-tools' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Connect to this site from any AI client using HTTP. No proxy or Node.js needed — just an Application Password.', 'emcp-tools' ); ?>
			</p>

			<h3><?php esc_html_e( 'Step 1: Generate Your Credentials', 'emcp-tools' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Pick an administrator and click Generate — a new Application Password is created automatically and every client config below is filled in. No need to visit your profile.', 'emcp-tools' ); ?>
			</p>

			<?php
			$emcp_tools_current_user = wp_get_current_user();
			$emcp_tools_admins       = get_users(
				array(
					'role'    => 'administrator',
					'orderby' => 'display_name',
					'order'   => 'ASC',
				)
			);
			// Only offer users the current user is allowed to manage.
			$emcp_tools_admins = array_values(
				array_filter(
					$emcp_tools_admins,
					static function ( $emcp_tools_u ) {
						return current_user_can( 'edit_user', $emcp_tools_u->ID );
					}
				)
			);
			// Sort the current user to the top, then alphabetically.
			usort(
				$emcp_tools_admins,
				static function ( $a, $b ) use ( $emcp_tools_current_user ) {
					if ( (int) $a->ID === (int) $emcp_tools_current_user->ID ) {
						return -1;
					}
					if ( (int) $b->ID === (int) $emcp_tools_current_user->ID ) {
						return 1;
					}
					return strcasecmp( (string) $a->display_name, (string) $b->display_name );
				}
			);
			?>

			<div class="elementor-mcp-cred-form">
				<div class="elementor-mcp-cred-field">
					<label for="elementor-mcp-b64-username"><?php esc_html_e( 'Administrator account', 'emcp-tools' ); ?></label>
					<select id="elementor-mcp-b64-username">
						<?php foreach ( $emcp_tools_admins as $emcp_tools_u ) : ?>
							<option
								value="<?php echo esc_attr( (string) $emcp_tools_u->ID ); ?>"
								data-login="<?php echo esc_attr( $emcp_tools_u->user_login ); ?>"
								<?php selected( (int) $emcp_tools_u->ID, (int) $emcp_tools_current_user->ID ); ?>
							>
								<?php
								echo esc_html(
									(int) $emcp_tools_u->ID === (int) $emcp_tools_current_user->ID
										/* translators: %s: username */
										? sprintf( __( '%s (you)', 'emcp-tools' ), $emcp_tools_u->user_login )
										: $emcp_tools_u->user_login
								);
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="button" class="button button-primary elementor-mcp-generate-btn" id="elementor-mcp-generate-b64"><?php esc_html_e( 'Generate Password &amp; Configs', 'emcp-tools' ); ?></button>

				<p id="elementor-mcp-cred-status" class="description" style="display: none;"></p>

				<div id="elementor-mcp-generated-pw-row" style="display: none;">
					<div class="elementor-mcp-cred-field">
						<label for="elementor-mcp-generated-pw-copy"><?php esc_html_e( 'New Application Password (save it — shown only once)', 'emcp-tools' ); ?></label>
						<div class="elementor-mcp-auth-result">
							<code id="elementor-mcp-generated-pw"></code>
							<button type="button" class="button elementor-mcp-copy-btn" data-target="elementor-mcp-generated-pw-copy"><?php esc_html_e( 'Copy', 'emcp-tools' ); ?></button>
							<textarea id="elementor-mcp-generated-pw-copy" class="elementor-mcp-copy-source"></textarea>
						</div>
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to profile */
								esc_html__( 'Manage or revoke application passwords under %s.', 'emcp-tools' ),
								'<a href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '">' . esc_html__( 'Users > Profile', 'emcp-tools' ) . '</a>'
							);
							?>
						</p>
					</div>
				</div>

				<details class="elementor-mcp-cred-advanced">
					<summary><?php esc_html_e( 'Use an existing Application Password instead', 'emcp-tools' ); ?></summary>
					<div class="elementor-mcp-cred-field" style="margin-top: 8px;">
						<label for="elementor-mcp-b64-app-password"><?php esc_html_e( 'Application Password', 'emcp-tools' ); ?></label>
						<input type="text" id="elementor-mcp-b64-app-password" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'If filled in, this is used as-is and no new password is created.', 'emcp-tools' ); ?></p>
					</div>
				</details>

				<div id="elementor-mcp-b64-result-row" style="display: none;">
					<div class="elementor-mcp-cred-field">
						<label for="elementor-mcp-b64-result-copy"><?php esc_html_e( 'Authorization header (for direct HTTP clients)', 'emcp-tools' ); ?></label>
						<div class="elementor-mcp-auth-result">
							<code id="elementor-mcp-b64-result"></code>
							<button type="button" class="button elementor-mcp-copy-btn" data-target="elementor-mcp-b64-result-copy"><?php esc_html_e( 'Copy', 'emcp-tools' ); ?></button>
							<textarea id="elementor-mcp-b64-result-copy" class="elementor-mcp-copy-source"></textarea>
						</div>
					</div>
				</div>

				<div id="elementor-mcp-authtest-row" style="display: none;">
					<button type="button" class="button" id="elementor-mcp-authtest-btn"><?php esc_html_e( 'Test authentication', 'emcp-tools' ); ?></button>
					<p id="elementor-mcp-authtest-status" class="description" style="display: none;"></p>

					<div id="elementor-mcp-authtest-fix" class="elementor-mcp-authtest-fix" style="display: none;">
						<p><strong><?php esc_html_e( 'Got 401 Unauthorized? Your server is most likely stripping the Authorization header.', 'emcp-tools' ); ?></strong></p>
						<p class="description"><?php esc_html_e( 'Common on Apache, Plesk, LiteSpeed and some Azure/IIS stacks: the Authorization header never reaches PHP, so WordPress never sees the Application Password and every MCP "initialize" fails with Unauthorized. Pass the header through to PHP, then re-test:', 'emcp-tools' ); ?></p>

						<p class="description" style="margin-bottom: 4px;"><strong><?php esc_html_e( 'Apache / Plesk / LiteSpeed', 'emcp-tools' ); ?></strong> — <?php esc_html_e( 'add to .htaccess, above the # BEGIN WordPress block:', 'emcp-tools' ); ?></p>
						<pre class="elementor-mcp-authtest-snippet">&lt;IfModule mod_rewrite.c&gt;
RewriteEngine On
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
&lt;/IfModule&gt;
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1</pre>

						<p class="description" style="margin-bottom: 4px;"><strong><?php esc_html_e( 'Nginx', 'emcp-tools' ); ?></strong> — <?php esc_html_e( 'add inside the PHP location block, then reload nginx:', 'emcp-tools' ); ?></p>
						<pre class="elementor-mcp-authtest-snippet">fastcgi_param HTTP_AUTHORIZATION $http_authorization;</pre>

						<p class="description">
							<?php
							printf(
								/* translators: %s: command-line example */
								esc_html__( 'You can also confirm from a terminal: %s — a 200 response means auth works; 401 means the header is being stripped.', 'emcp-tools' ),
								'<code>curl -u "USER:APP PASSWORD" ' . esc_html( esc_url_raw( rest_url( 'wp/v2/users/me' ) ) ) . '</code>'
							);
							?>
						</p>
					</div>
				</div>
			</div>

			<div id="elementor-mcp-client-picker" style="display: none;">
				<h3><?php esc_html_e( 'Step 2: Choose Your AI Client', 'emcp-tools' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Pick the app you will connect from — the setup options below are tailored to it.', 'emcp-tools' ); ?></p>

				<div class="elementor-mcp-client-grid" role="tablist" aria-label="<?php esc_attr_e( 'AI client', 'emcp-tools' ); ?>">
					<?php foreach ( EMCP_Tools_Admin::connection_clients() as $emcp_tools_client ) : ?>
						<button
							type="button"
							class="elementor-mcp-client-card"
							role="tab"
							aria-selected="false"
							data-client="<?php echo esc_attr( $emcp_tools_client['id'] ); ?>"
						>
							<?php if ( ! empty( $emcp_tools_client['image'] ) ) : ?>
								<img
									class="elementor-mcp-client-card-logo"
									src="<?php echo esc_url( EMCP_TOOLS_URL . 'assets/img/' . $emcp_tools_client['image'] ); ?>"
									alt=""
									aria-hidden="true"
								/>
							<?php else : ?>
								<span class="dashicons dashicons-<?php echo esc_attr( $emcp_tools_client['icon'] ); ?>" aria-hidden="true"></span>
							<?php endif; ?>
							<span class="elementor-mcp-client-card-label"><?php echo esc_html( $emcp_tools_client['label'] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>

				<h3 id="elementor-mcp-connect-heading" style="display: none;">
					<?php esc_html_e( 'Step 3: Connect', 'emcp-tools' ); ?> <span id="elementor-mcp-connect-client-name"></span>
				</h3>

				<?php // JS renders the selected client's option blocks here. ?>
				<div id="elementor-mcp-client-options"></div>

				<?php // Hidden form used to POST the .mcpb download (Claude Desktop). ?>
				<form
					id="elementor-mcp-mcpb-form"
					method="post"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					style="display: none;"
				>
					<input type="hidden" name="action" value="emcp_tools_download_mcpb" />
					<input type="hidden" name="_emcp_nonce" value="<?php echo esc_attr( wp_create_nonce( EMCP_Tools_Admin::NONCE_DOWNLOAD_MCPB ) ); ?>" />
					<input type="hidden" name="user_id" id="elementor-mcp-mcpb-user-id" value="" />
					<input type="hidden" name="app_password" id="elementor-mcp-mcpb-app-password" value="" />
				</form>
			</div>
		</div>

	</div><?php // /#emcp-conn-main ?>

	<?php // ===== Sub-tab: 3rd Party Services ===== ?>
	<div class="elementor-mcp-tabpanel" id="emcp-conn-services" role="tabpanel" data-tab="conn-services">
		<div class="elementor-mcp-section">
			<h2><?php esc_html_e( '3rd Party Services', 'emcp-tools' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Connect external services EMCP tools can use. Stock-image providers power the search-images / add-stock-image tools — add at least one free key; the tools use the first connected provider unless a specific one is requested.', 'emcp-tools' ); ?></p>

			<form method="post" action="options.php" id="emcp-conn-services-form" class="emcp-services-form">
				<?php settings_fields( EMCP_Tools_Admin::SETTINGS_GROUP_SERVICES ); ?>

				<div class="emcp-services-grid">
					<?php
					$emcp_stock_providers = array(
						array( 'label' => 'Unsplash', 'option' => EMCP_Tools_Unsplash_Client::OPTION, 'const' => 'EMCP_TOOLS_UNSPLASH_ACCESS_KEY', 'url' => 'https://unsplash.com/developers' ),
						array( 'label' => 'Pexels', 'option' => EMCP_Tools_Pexels_Client::OPTION, 'const' => 'EMCP_TOOLS_PEXELS_API_KEY', 'url' => 'https://www.pexels.com/api/' ),
						array( 'label' => 'Pixabay', 'option' => EMCP_Tools_Pixabay_Client::OPTION, 'const' => 'EMCP_TOOLS_PIXABAY_API_KEY', 'url' => 'https://pixabay.com/api/docs/' ),
					);
					foreach ( $emcp_stock_providers as $emcp_sp ) :
						$emcp_sp_const = defined( $emcp_sp['const'] );
						// A key is on file when the option is non-empty and not
						// constant-overridden. The value itself is NEVER rendered.
						$emcp_sp_saved = ! $emcp_sp_const && '' !== (string) get_option( $emcp_sp['option'], '' );
						if ( $emcp_sp_const ) {
							/* translators: %s: PHP constant name. */
							$emcp_sp_placeholder = sprintf( __( 'Set via the %s constant', 'emcp-tools' ), $emcp_sp['const'] );
						} elseif ( $emcp_sp_saved ) {
							$emcp_sp_placeholder = __( '•••••••••••••• saved — leave blank to keep', 'emcp-tools' );
						} else {
							$emcp_sp_placeholder = __( 'Paste your API key', 'emcp-tools' );
						}
						?>
						<div class="emcp-service-field">
							<div class="emcp-service-field-head">
								<label for="emcp-tools-<?php echo esc_attr( $emcp_sp['option'] ); ?>">
									<?php
									/* translators: %s: provider name (Unsplash / Pexels / Pixabay). */
									echo esc_html( sprintf( __( '%s API key', 'emcp-tools' ), $emcp_sp['label'] ) );
									?>
									<?php if ( $emcp_sp_saved ) : ?>
										<span class="emcp-service-badge"><?php esc_html_e( 'saved', 'emcp-tools' ); ?></span>
									<?php endif; ?>
								</label>
								<a href="<?php echo esc_url( $emcp_sp['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get a free key', 'emcp-tools' ); ?> &rarr;</a>
							</div>
							<input
								type="password"
								id="emcp-tools-<?php echo esc_attr( $emcp_sp['option'] ); ?>"
								name="<?php echo esc_attr( $emcp_sp['option'] ); ?>"
								value=""
								placeholder="<?php echo esc_attr( $emcp_sp_placeholder ); ?>"
								autocomplete="off"
								autocapitalize="off"
								spellcheck="false"
								<?php disabled( $emcp_sp_const ); ?>
							/>
							<?php if ( $emcp_sp_saved ) : ?>
								<label class="emcp-service-clear">
									<input type="checkbox" name="<?php echo esc_attr( $emcp_sp['option'] . '__clear' ); ?>" value="1" />
									<?php esc_html_e( 'Remove saved key', 'emcp-tools' ); ?>
								</label>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<?php $emcp_wpcli_const = defined( 'EMCP_TOOLS_WPCLI_COMMAND' ); ?>
				<div class="emcp-service-field" style="margin-top:22px;padding-top:20px;border-top:1px solid var(--mcp-line,#e5e7eb);">
					<div class="emcp-service-field-head">
						<label for="emcp-tools-wpcli-command">
							<?php esc_html_e( 'WP-CLI base command', 'emcp-tools' ); ?>
							<?php if ( $emcp_wpcli_const ) : ?><span class="emcp-service-badge"><?php esc_html_e( 'constant', 'emcp-tools' ); ?></span><?php endif; ?>
						</label>
					</div>
					<input
						type="text"
						id="emcp-tools-wpcli-command"
						name="emcp_tools_wpcli_command"
						value="<?php echo esc_attr( $emcp_wpcli_const ? '' : (string) get_option( 'emcp_tools_wpcli_command', '' ) ); ?>"
						placeholder="<?php echo esc_attr( $emcp_wpcli_const ? sprintf( __( 'Set via the %s constant', 'emcp-tools' ), 'EMCP_TOOLS_WPCLI_COMMAND' ) : 'wp   —   or   php /path/to/wp-cli.phar' ); ?>"
						autocomplete="off" spellcheck="false"
						<?php disabled( $emcp_wpcli_const ); ?>
					/>
					<p class="description"><?php esc_html_e( 'The wp launcher used by the WP-CLI tools over HTTP and for background jobs (e.g. "wp", or "php /path/to/wp-cli.phar"). Leave blank if you connect only over the WP-CLI stdio transport — commands then run in-process, no binary needed.', 'emcp-tools' ); ?></p>
				</div>
			</form>
		</div>
	</div><?php // /#emcp-conn-services ?>

</div>
