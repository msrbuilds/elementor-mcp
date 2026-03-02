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
?>

<div class="elementor-mcp-connection">

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
					<span class="elementor-mcp-status-card-value"><?php echo esc_html( $elementor_mcp_has_adapter ? __( 'Active', 'elementor-mcp' ) : __( 'Not Active', 'elementor-mcp' ) ); ?></span>
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

		<div id="elementor-mcp-http-configs" style="display: none;">

			<h3><?php esc_html_e( 'Step 2: Copy Your Config', 'elementor-mcp' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Choose the config for your AI client and paste it into the appropriate config file.', 'elementor-mcp' ); ?>
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
