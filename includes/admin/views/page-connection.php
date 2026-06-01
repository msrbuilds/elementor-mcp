<?php
/**
 * Connection info tab view for the MCP Tools for Elementor admin settings page.
 *
 * Displays MCP connection configurations for various clients.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var Elementor_MCP_Admin $this */
$elementor_mcp_endpoint      = rest_url( 'mcp/elementor-mcp-server' );
$elementor_mcp_enabled_count = $this->get_enabled_tool_count();
$elementor_mcp_total_count   = $this->get_total_tool_count();
$elementor_mcp_has_adapter   = class_exists( '\WP\MCP\Core\McpAdapter' );

// Adapter provenance: bundled with EMCP, an external plugin, or unavailable.
$elementor_mcp_adapter_source = class_exists( 'Elementor_MCP_Adapter_Bootstrap' )
	? Elementor_MCP_Adapter_Bootstrap::source()
	: ( $elementor_mcp_has_adapter ? 'external' : 'none' );
$elementor_mcp_adapter_label = 'bundled' === $elementor_mcp_adapter_source
	? __( 'Active (bundled)', 'elementor-mcp' )
	: ( 'external' === $elementor_mcp_adapter_source ? __( 'Active (plugin)', 'elementor-mcp' ) : __( 'Not Active', 'elementor-mcp' ) );

// Abilities API is core in WordPress 6.9+/7.0.
$elementor_mcp_has_abilities = function_exists( 'wp_register_ability' );

// The "Activate Abilities API for EMCP" gate (on by default).
$elementor_mcp_server_enabled = class_exists( 'Elementor_MCP_Plugin' )
	? Elementor_MCP_Plugin::is_server_enabled()
	: ( '1' === (string) get_option( 'elementor_mcp_server_enabled', '1' ) );
?>

<div class="elementor-mcp-connection">

	<?php // ===== Step 1: Activate Abilities API for EMCP ===== ?>
	<div class="elementor-mcp-section elementor-mcp-activate-card">
	<div class="elementor-mcp-activate-head">
		<span class="elementor-mcp-step-num" aria-hidden="true">1</span>
		<h2><?php esc_html_e( 'Activate Abilities API for EMCP', 'elementor-mcp' ); ?></h2>
	</div>

	<form method="post" action="options.php" class="elementor-mcp-activate-form">
		<?php settings_fields( Elementor_MCP_Admin::SETTINGS_GROUP_SERVER ); ?>

		<label class="elementor-mcp-activate-toggle">
			<input
				type="checkbox"
				name="<?php echo esc_attr( Elementor_MCP_Plugin::OPTION_SERVER_ENABLED ); ?>"
				value="1"
				<?php checked( $elementor_mcp_server_enabled ); ?>
			/>
			<strong><?php esc_html_e( 'Expose EMCP tools to AI agents on this site', 'elementor-mcp' ); ?></strong>
		</label>

		<p class="elementor-mcp-activate-note elementor-mcp-activate-note--security">
			<strong><?php esc_html_e( 'Security note:', 'elementor-mcp' ); ?></strong>
			<?php esc_html_e( 'When enabled, connected AI agents can create, edit, and delete Elementor pages and content on this site through the MCP server. Use a capable AI model and set your client to ask for confirmation before every action — read what the agent is about to do before approving.', 'elementor-mcp' ); ?>
		</p>
		<p class="elementor-mcp-activate-note">
			<?php
			if ( $elementor_mcp_has_abilities ) {
				printf(
					/* translators: %s: how the MCP Adapter is provided. */
					esc_html__( 'WordPress Abilities API: core (no separate plugin needed). MCP Adapter: %s.', 'elementor-mcp' ),
					'bundled' === $elementor_mcp_adapter_source
						? esc_html__( 'bundled with EMCP', 'elementor-mcp' )
						: ( 'external' === $elementor_mcp_adapter_source ? esc_html__( 'provided by an active MCP Adapter plugin', 'elementor-mcp' ) : esc_html__( 'unavailable', 'elementor-mcp' ) )
				);
			} else {
				esc_html_e( 'WordPress Abilities API is unavailable — WordPress 6.9 or newer is required.', 'elementor-mcp' );
			}
			?>
		</p>

		<?php submit_button( __( 'Save Settings', 'elementor-mcp' ), 'primary', 'submit', false ); ?>
	</form>
	</div>

	<!-- Server Status -->
	<div class="elementor-mcp-section">
		<h2><?php esc_html_e( 'Server Status', 'elementor-mcp' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Current status of your MCP server and connected components.', 'elementor-mcp' ); ?></p>

		<div class="elementor-mcp-status-grid">
			<div class="elementor-mcp-status-card">
				<span class="elementor-mcp-status-card-icon elementor-mcp-status-card-icon--ok">
					<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>
				</span>
				<span class="elementor-mcp-status-card-info">
					<span class="elementor-mcp-status-card-label"><?php esc_html_e( 'MCP Tools for Elementor', 'elementor-mcp' ); ?></span>
					<span class="elementor-mcp-status-card-value"><?php esc_html_e( 'Active', 'elementor-mcp' ); ?></span>
				</span>
			</div>

			<div class="elementor-mcp-status-card">
				<span class="elementor-mcp-status-card-icon <?php echo esc_attr( $elementor_mcp_has_adapter ? 'elementor-mcp-status-card-icon--ok' : 'elementor-mcp-status-card-icon--warn' ); ?>">
					<?php if ( $elementor_mcp_has_adapter ) : ?>
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>
					<?php else : ?>
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/></svg>
					<?php endif; ?>
				</span>
				<span class="elementor-mcp-status-card-info">
					<span class="elementor-mcp-status-card-label"><?php esc_html_e( 'MCP Adapter', 'elementor-mcp' ); ?></span>
					<span class="elementor-mcp-status-card-value"><?php echo esc_html( $elementor_mcp_adapter_label ); ?></span>
				</span>
			</div>

			<div class="elementor-mcp-status-card">
				<span class="elementor-mcp-status-card-icon <?php echo esc_attr( $elementor_mcp_server_enabled ? 'elementor-mcp-status-card-icon--ok' : 'elementor-mcp-status-card-icon--warn' ); ?>">
					<?php if ( $elementor_mcp_server_enabled ) : ?>
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>
					<?php else : ?>
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/></svg>
					<?php endif; ?>
				</span>
				<span class="elementor-mcp-status-card-info">
					<span class="elementor-mcp-status-card-label"><?php esc_html_e( 'MCP Server', 'elementor-mcp' ); ?></span>
					<span class="elementor-mcp-status-card-value"><?php echo esc_html( $elementor_mcp_server_enabled ? __( 'Enabled', 'elementor-mcp' ) : __( 'Disabled', 'elementor-mcp' ) ); ?></span>
				</span>
			</div>

			<div class="elementor-mcp-status-card">
				<span class="elementor-mcp-status-card-icon elementor-mcp-status-card-icon--ok">
					<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
				</span>
				<span class="elementor-mcp-status-card-info">
					<span class="elementor-mcp-status-card-label"><?php esc_html_e( 'Tools Enabled', 'elementor-mcp' ); ?></span>
					<span class="elementor-mcp-status-card-value">
						<?php
						printf(
							/* translators: %1$d: enabled count, %2$d: total count */
							esc_html__( '%1$d / %2$d', 'elementor-mcp' ),
							(int) $elementor_mcp_enabled_count,
							(int) $elementor_mcp_total_count
						);
						?>
					</span>
				</span>
			</div>
		</div>

		<div class="elementor-mcp-endpoint">
			<code><?php echo esc_html( $elementor_mcp_endpoint ); ?></code>
			<button type="button" class="button elementor-mcp-copy-btn" data-target="elementor-mcp-endpoint-copy"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
			<textarea id="elementor-mcp-endpoint-copy" class="elementor-mcp-copy-source"><?php echo esc_html( $elementor_mcp_endpoint ); ?></textarea>
		</div>
	</div>

	<!-- HTTP Connection -->
	<div class="elementor-mcp-section">
		<h2><?php esc_html_e( 'Connect Your AI Client', 'elementor-mcp' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Connect to this site from any AI client using HTTP. No proxy or Node.js needed — just an Application Password.', 'elementor-mcp' ); ?>
		</p>

		<h3><?php esc_html_e( 'Step 1: Generate Your Credentials', 'elementor-mcp' ); ?></h3>
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to application passwords */
				esc_html__( 'Enter your username and Application Password (create one at %s).', 'elementor-mcp' ),
				'<a href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '">' . esc_html__( 'Users > Profile', 'elementor-mcp' ) . '</a>'
			);
			?>
		</p>

		<div class="elementor-mcp-cred-form">
			<div class="elementor-mcp-cred-field">
				<label for="elementor-mcp-b64-username"><?php esc_html_e( 'Username', 'elementor-mcp' ); ?></label>
				<input type="text" id="elementor-mcp-b64-username" value="<?php echo esc_attr( wp_get_current_user()->user_login ); ?>" />
			</div>
			<div class="elementor-mcp-cred-field">
				<label for="elementor-mcp-b64-app-password"><?php esc_html_e( 'Application Password', 'elementor-mcp' ); ?></label>
				<input type="text" id="elementor-mcp-b64-app-password" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" />
				<p class="description">
					<?php
					printf(
						/* translators: %s: link */
						esc_html__( 'Create one at %s', 'elementor-mcp' ),
						'<a href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '">' . esc_html__( 'Application Passwords', 'elementor-mcp' ) . '</a>'
					);
					?>
				</p>
			</div>
			<button type="button" class="button elementor-mcp-generate-btn" id="elementor-mcp-generate-b64"><?php esc_html_e( 'Generate Configs', 'elementor-mcp' ); ?></button>

			<div id="elementor-mcp-b64-result-row" style="display: none;">
				<div class="elementor-mcp-auth-result">
					<code id="elementor-mcp-b64-result"></code>
					<button type="button" class="button elementor-mcp-copy-btn" data-target="elementor-mcp-b64-result-copy"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
					<textarea id="elementor-mcp-b64-result-copy" class="elementor-mcp-copy-source"></textarea>
				</div>
			</div>
		</div>

		<div id="elementor-mcp-proxy-configs" style="display: none;">

			<h3><?php esc_html_e( 'Step 2: Node.js Proxy Configs (Recommended)', 'elementor-mcp' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'These configs use the bundled Node.js proxy which handles session management and protocol version compatibility automatically. Requires Node.js 18+ installed on the machine running your AI client.', 'elementor-mcp' ); ?>
			</p>

			<!-- Claude Code (Proxy) -->
			<div class="elementor-mcp-config-card">
				<div class="elementor-mcp-config-card-header">
					<span class="elementor-mcp-config-card-title"><?php esc_html_e( 'Claude Code', 'elementor-mcp' ); ?> <span style="font-weight: 400; color: var(--mcp-gray-400);">&mdash; .mcp.json</span></span>
					<button type="button" class="button elementor-mcp-copy-btn" data-target="claude-code-proxy"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				</div>
				<pre><code id="elementor-mcp-claude-code-proxy-code"></code></pre>
				<textarea id="claude-code-proxy" class="elementor-mcp-copy-source"></textarea>
			</div>

			<!-- Claude Desktop (Proxy) -->
			<div class="elementor-mcp-config-card">
				<div class="elementor-mcp-config-card-header">
					<span class="elementor-mcp-config-card-title"><?php esc_html_e( 'Claude Desktop', 'elementor-mcp' ); ?> <span style="font-weight: 400; color: var(--mcp-gray-400);">&mdash; claude_desktop_config.json</span></span>
					<button type="button" class="button elementor-mcp-copy-btn" data-target="claude-desktop-proxy"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				</div>
				<pre><code id="elementor-mcp-claude-desktop-proxy-code"></code></pre>
				<textarea id="claude-desktop-proxy" class="elementor-mcp-copy-source"></textarea>
			</div>

			<p class="description">
				<strong><?php esc_html_e( 'Local WordPress:', 'elementor-mcp' ); ?></strong>
				<?php esc_html_e( 'The path above already points to bin/mcp-proxy.mjs in this installation — no change needed.', 'elementor-mcp' ); ?>
			</p>
			<p class="description">
				<strong><?php esc_html_e( 'Remote WordPress (e.g. shared hosting):', 'elementor-mcp' ); ?></strong>
				<?php esc_html_e( 'Your AI client launches this proxy as a local subprocess, so the file must exist on the machine running the client — not on the server. The path above points to the server and will not work remotely. Use one of these instead:', 'elementor-mcp' ); ?>
			</p>
			<ul class="description" style="list-style: disc; margin-left: 1.5em;">
				<li><?php
					printf(
						/* translators: %s: npx command code */
						esc_html__( 'Recommended — zero-install npx runner (nothing to keep in sync): set %s and keep the same env block.', 'elementor-mcp' ),
						'<code>"command": "npx", "args": ["-y", "@msrbuilds/emcp-proxy@latest"]</code>'
					);
				?></li>
				<li><?php esc_html_e( 'Or copy bin/mcp-proxy.mjs from the plugin ZIP to your local machine and point "args" at that local path. Re-copy it after plugin updates to pick up proxy fixes.', 'elementor-mcp' ); ?></li>
			</ul>

		</div>

		<div id="elementor-mcp-http-configs" style="display: none;">

			<h3><?php esc_html_e( 'Step 3: Direct HTTP Configs (Advanced)', 'elementor-mcp' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Direct HTTP connections require your AI client to handle session management (Mcp-Session-Id headers). Use only if the Node.js proxy is not an option.', 'elementor-mcp' ); ?>
			</p>

			<!-- Claude Code -->
			<div class="elementor-mcp-config-card">
				<div class="elementor-mcp-config-card-header">
					<span class="elementor-mcp-config-card-title"><?php esc_html_e( 'Claude Code', 'elementor-mcp' ); ?> <span style="font-weight: 400; color: var(--mcp-gray-400);">&mdash; .mcp.json</span></span>
					<button type="button" class="button elementor-mcp-copy-btn" data-target="claude-code-http"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				</div>
				<pre><code id="elementor-mcp-claude-code-http-code"></code></pre>
				<textarea id="claude-code-http" class="elementor-mcp-copy-source"></textarea>
			</div>

			<!-- Claude Desktop -->
			<div class="elementor-mcp-config-card">
				<div class="elementor-mcp-config-card-header">
					<span class="elementor-mcp-config-card-title"><?php esc_html_e( 'Claude Desktop', 'elementor-mcp' ); ?> <span style="font-weight: 400; color: var(--mcp-gray-400);">&mdash; claude_desktop_config.json</span></span>
					<button type="button" class="button elementor-mcp-copy-btn" data-target="claude-desktop-http"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				</div>
				<pre><code id="elementor-mcp-claude-desktop-http-code"></code></pre>
				<textarea id="claude-desktop-http" class="elementor-mcp-copy-source"></textarea>
			</div>

			<!-- Cursor -->
			<div class="elementor-mcp-config-card">
				<div class="elementor-mcp-config-card-header">
					<span class="elementor-mcp-config-card-title"><?php esc_html_e( 'Cursor', 'elementor-mcp' ); ?> <span style="font-weight: 400; color: var(--mcp-gray-400);">&mdash; .cursor/mcp.json</span></span>
					<button type="button" class="button elementor-mcp-copy-btn" data-target="cursor-config"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				</div>
				<pre><code id="elementor-mcp-cursor-code"></code></pre>
				<textarea id="cursor-config" class="elementor-mcp-copy-source"></textarea>
			</div>

			<!-- Windsurf -->
			<div class="elementor-mcp-config-card">
				<div class="elementor-mcp-config-card-header">
					<span class="elementor-mcp-config-card-title"><?php esc_html_e( 'Windsurf', 'elementor-mcp' ); ?> <span style="font-weight: 400; color: var(--mcp-gray-400);">&mdash; mcp_config.json</span></span>
					<button type="button" class="button elementor-mcp-copy-btn" data-target="windsurf-config"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				</div>
				<pre><code id="elementor-mcp-windsurf-code"></code></pre>
				<textarea id="windsurf-config" class="elementor-mcp-copy-source"></textarea>
			</div>

			<!-- Antigravity -->
			<div class="elementor-mcp-config-card">
				<div class="elementor-mcp-config-card-header">
					<span class="elementor-mcp-config-card-title"><?php esc_html_e( 'Antigravity', 'elementor-mcp' ); ?> <span style="font-weight: 400; color: var(--mcp-gray-400);">&mdash; mcp_config.json</span></span>
					<button type="button" class="button elementor-mcp-copy-btn" data-target="antigravity-config"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				</div>
				<pre><code id="elementor-mcp-antigravity-code"></code></pre>
				<textarea id="antigravity-config" class="elementor-mcp-copy-source"></textarea>
			</div>

			<!-- Codex -->
			<div class="elementor-mcp-config-card">
				<div class="elementor-mcp-config-card-header">
					<span class="elementor-mcp-config-card-title"><?php esc_html_e( 'Codex', 'elementor-mcp' ); ?> <span style="font-weight: 400; color: var(--mcp-gray-400);">&mdash; config.toml</span></span>
					<button type="button" class="button elementor-mcp-copy-btn" data-target="codex-config"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				</div>
				<pre><code id="elementor-mcp-codex-code"></code></pre>
				<textarea id="codex-config" class="elementor-mcp-copy-source"></textarea>
			</div>

			<!-- npx mcp-remote -->
			<div class="elementor-mcp-config-card">
				<div class="elementor-mcp-config-card-header">
					<span class="elementor-mcp-config-card-title"><?php esc_html_e( 'npx mcp-remote', 'elementor-mcp' ); ?> <span style="font-weight: 400; color: var(--mcp-gray-400);">&mdash; <?php esc_html_e( 'any stdio client', 'elementor-mcp' ); ?></span></span>
					<button type="button" class="button elementor-mcp-copy-btn" data-target="mcp-remote-config"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				</div>
				<pre><code id="elementor-mcp-mcp-remote-code"></code></pre>
				<textarea id="mcp-remote-config" class="elementor-mcp-copy-source"></textarea>
			</div>

		</div>
	</div>

</div>
