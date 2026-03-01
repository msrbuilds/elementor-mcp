<?php
/**
 * Connection info tab view for the Elementor MCP admin settings page.
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
$mcp_endpoint    = rest_url( 'mcp/elementor-mcp-server' );
$enabled_count   = $this->get_enabled_tool_count();
$total_count     = $this->get_total_tool_count();
$has_mcp_adapter = class_exists( '\WP\MCP\Core\McpAdapter' );
?>

<div class="elementor-mcp-connection">

	<!-- Server Status -->
	<div class="elementor-mcp-section">
		<h2><?php esc_html_e( 'Server Status', 'elementor-mcp' ); ?></h2>
		<table class="form-table elementor-mcp-status-table">
			<tr>
				<th><?php esc_html_e( 'Elementor MCP Plugin', 'elementor-mcp' ); ?></th>
				<td><span class="elementor-mcp-status elementor-mcp-status--active"><?php esc_html_e( 'Active', 'elementor-mcp' ); ?></span></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'MCP Adapter Plugin', 'elementor-mcp' ); ?></th>
				<td>
					<?php if ( $has_mcp_adapter ) : ?>
						<span class="elementor-mcp-status elementor-mcp-status--active"><?php esc_html_e( 'Active', 'elementor-mcp' ); ?></span>
					<?php else : ?>
						<span class="elementor-mcp-status elementor-mcp-status--inactive"><?php esc_html_e( 'Not Active', 'elementor-mcp' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Tools Enabled', 'elementor-mcp' ); ?></th>
				<td>
					<?php
					printf(
						/* translators: %1$d: enabled count, %2$d: total count */
						esc_html__( '%1$d of %2$d', 'elementor-mcp' ),
						$enabled_count,
						$total_count
					);
					?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'MCP Endpoint', 'elementor-mcp' ); ?></th>
				<td><code><?php echo esc_html( $mcp_endpoint ); ?></code></td>
			</tr>
		</table>
	</div>

	<!-- HTTP Connection -->
	<div class="elementor-mcp-section">
		<h2><?php esc_html_e( 'Connect Your AI Client', 'elementor-mcp' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Connect to this site from any AI client using HTTP. No proxy or Node.js needed — just an Application Password.', 'elementor-mcp' ); ?>
		</p>
		<p class="description">
			<?php
			printf(
				/* translators: %s: URL path */
				esc_html__( 'Create an Application Password at: %s', 'elementor-mcp' ),
				'<a href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '">' . esc_html__( 'Users > Profile > Application Passwords', 'elementor-mcp' ) . '</a>'
			);
			?>
		</p>

		<h3><?php esc_html_e( 'Step 1: Generate Your Credentials', 'elementor-mcp' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Enter your username and Application Password to generate the Base64 authorization string needed for HTTP connections.', 'elementor-mcp' ); ?>
		</p>
		<table class="form-table elementor-mcp-base64-form">
			<tr>
				<th><label for="elementor-mcp-b64-username"><?php esc_html_e( 'Username', 'elementor-mcp' ); ?></label></th>
				<td>
					<input type="text" id="elementor-mcp-b64-username" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_login ); ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="elementor-mcp-b64-app-password"><?php esc_html_e( 'Application Password', 'elementor-mcp' ); ?></label></th>
				<td>
					<input type="text" id="elementor-mcp-b64-app-password" class="regular-text" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" />
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to application passwords */
							esc_html__( 'Create one at %s', 'elementor-mcp' ),
							'<a href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '">' . esc_html__( 'Users > Profile > Application Passwords', 'elementor-mcp' ) . '</a>'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<button type="button" class="button button-primary" id="elementor-mcp-generate-b64"><?php esc_html_e( 'Generate Configs', 'elementor-mcp' ); ?></button>
				</td>
			</tr>
			<tr id="elementor-mcp-b64-result-row" style="display: none;">
				<th><?php esc_html_e( 'Authorization Header', 'elementor-mcp' ); ?></th>
				<td>
					<code id="elementor-mcp-b64-result"></code>
					<button type="button" class="button elementor-mcp-copy-btn" data-target="elementor-mcp-b64-result-copy"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
					<textarea id="elementor-mcp-b64-result-copy" class="elementor-mcp-copy-source"></textarea>
				</td>
			</tr>
		</table>

		<div id="elementor-mcp-http-configs" style="display: none;">

			<h3><?php esc_html_e( 'Step 2: Copy Your Config', 'elementor-mcp' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Choose the config for your AI client and paste it into the appropriate config file.', 'elementor-mcp' ); ?>
			</p>

			<h4><?php esc_html_e( 'Claude Code', 'elementor-mcp' ); ?></h4>
			<p class="description">
				<?php esc_html_e( 'Place as .mcp.json in your project root:', 'elementor-mcp' ); ?>
			</p>
			<div class="elementor-mcp-code-block">
				<pre><code id="elementor-mcp-claude-code-http-code"></code></pre>
				<button type="button" class="button elementor-mcp-copy-btn" data-target="claude-code-http"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				<textarea id="claude-code-http" class="elementor-mcp-copy-source"></textarea>
			</div>

			<h4><?php esc_html_e( 'Claude Desktop', 'elementor-mcp' ); ?></h4>
			<p class="description">
				<?php
				printf(
					/* translators: %s: config file path */
					esc_html__( 'Add to %s:', 'elementor-mcp' ),
					'<code>%APPDATA%\\Claude\\claude_desktop_config.json</code> (Windows) / <code>~/Library/Application Support/Claude/claude_desktop_config.json</code> (macOS)'
				);
				?>
			</p>
			<div class="elementor-mcp-code-block">
				<pre><code id="elementor-mcp-claude-desktop-http-code"></code></pre>
				<button type="button" class="button elementor-mcp-copy-btn" data-target="claude-desktop-http"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				<textarea id="claude-desktop-http" class="elementor-mcp-copy-source"></textarea>
			</div>

			<h4><?php esc_html_e( 'Cursor', 'elementor-mcp' ); ?></h4>
			<p class="description">
				<?php esc_html_e( 'Add to .cursor/mcp.json in your project root, or ~/.cursor/mcp.json for global config:', 'elementor-mcp' ); ?>
			</p>
			<div class="elementor-mcp-code-block">
				<pre><code id="elementor-mcp-cursor-code"></code></pre>
				<button type="button" class="button elementor-mcp-copy-btn" data-target="cursor-config"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				<textarea id="cursor-config" class="elementor-mcp-copy-source"></textarea>
			</div>

			<h4><?php esc_html_e( 'Windsurf', 'elementor-mcp' ); ?></h4>
			<p class="description">
				<?php
				printf(
					/* translators: %s: config file path */
					esc_html__( 'Add to %s:', 'elementor-mcp' ),
					'<code>~/.codeium/windsurf/mcp_config.json</code>'
				);
				?>
			</p>
			<div class="elementor-mcp-code-block">
				<pre><code id="elementor-mcp-windsurf-code"></code></pre>
				<button type="button" class="button elementor-mcp-copy-btn" data-target="windsurf-config"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				<textarea id="windsurf-config" class="elementor-mcp-copy-source"></textarea>
			</div>

			<h4><?php esc_html_e( 'Antigravity', 'elementor-mcp' ); ?></h4>
			<p class="description">
				<?php
				printf(
					/* translators: %s: config file path */
					esc_html__( 'Add to %s:', 'elementor-mcp' ),
					'<code>~/.gemini/antigravity/mcp_config.json</code>'
				);
				?>
			</p>
			<div class="elementor-mcp-code-block">
				<pre><code id="elementor-mcp-antigravity-code"></code></pre>
				<button type="button" class="button elementor-mcp-copy-btn" data-target="antigravity-config"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				<textarea id="antigravity-config" class="elementor-mcp-copy-source"></textarea>
			</div>

		</div>
	</div>


</div>

