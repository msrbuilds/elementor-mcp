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
$site_url       = home_url();
$mcp_endpoint   = rest_url( 'mcp/elementor-mcp-server' );
$enabled_count  = $this->get_enabled_tool_count();
$total_count    = $this->get_total_tool_count();
$has_mcp_adapter = class_exists( '\WP\MCP\Core\McpAdapter' );
$has_wpcli       = defined( 'WP_CLI' ) || file_exists( ABSPATH . 'wp-cli.phar' );
$plugin_dir      = str_replace( '\\', '/', ELEMENTOR_MCP_DIR );
$wp_path         = str_replace( '\\', '/', ABSPATH );
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

	<!-- WP-CLI (Local) -->
	<div class="elementor-mcp-section">
		<h2><?php esc_html_e( 'WP-CLI — Local Connection (Recommended)', 'elementor-mcp' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'The fastest connection method. Requires WP-CLI and runs as a subprocess. Best for local development.', 'elementor-mcp' ); ?>
		</p>

		<h3><?php esc_html_e( 'Command', 'elementor-mcp' ); ?></h3>
		<div class="elementor-mcp-code-block">
			<pre><code>wp mcp-adapter serve --server=elementor-mcp-server --user=admin --path=<?php echo esc_html( $wp_path ); ?></code></pre>
			<button type="button" class="button elementor-mcp-copy-btn" data-target="wpcli-cmd"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
			<textarea id="wpcli-cmd" class="elementor-mcp-copy-source">wp mcp-adapter serve --server=elementor-mcp-server --user=admin --path=<?php echo esc_attr( $wp_path ); ?></textarea>
		</div>

		<h3><?php esc_html_e( 'Claude Desktop Config', 'elementor-mcp' ); ?></h3>
		<p class="description">
			<?php
			printf(
				/* translators: %s: config file path */
				esc_html__( 'Add to %s:', 'elementor-mcp' ),
				'<code>%APPDATA%\\Claude\\claude_desktop_config.json</code> (Windows) / <code>~/Library/Application Support/Claude/claude_desktop_config.json</code> (macOS)'
			);
			?>
		</p>
		<?php
		$claude_desktop_local = wp_json_encode(
			array(
				'mcpServers' => array(
					'elementor-mcp' => array(
						'command' => 'wp',
						'args'    => array(
							'mcp-adapter',
							'serve',
							'--server=elementor-mcp-server',
							'--user=admin',
							'--path=' . $wp_path,
						),
					),
				),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
		?>
		<div class="elementor-mcp-code-block">
			<pre><code><?php echo esc_html( $claude_desktop_local ); ?></code></pre>
			<button type="button" class="button elementor-mcp-copy-btn" data-target="claude-desktop-local"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
			<textarea id="claude-desktop-local" class="elementor-mcp-copy-source"><?php echo esc_textarea( $claude_desktop_local ); ?></textarea>
		</div>

		<h3><?php esc_html_e( 'Claude Code Config', 'elementor-mcp' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Place as .mcp.json in your project root:', 'elementor-mcp' ); ?>
		</p>
		<?php
		$claude_code_local = wp_json_encode(
			array(
				'mcpServers' => array(
					'elementor-mcp' => array(
						'type'    => 'stdio',
						'command' => 'wp',
						'args'    => array(
							'mcp-adapter',
							'serve',
							'--server=elementor-mcp-server',
							'--user=admin',
							'--path=' . $wp_path,
						),
					),
				),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
		?>
		<div class="elementor-mcp-code-block">
			<pre><code><?php echo esc_html( $claude_code_local ); ?></code></pre>
			<button type="button" class="button elementor-mcp-copy-btn" data-target="claude-code-local"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
			<textarea id="claude-code-local" class="elementor-mcp-copy-source"><?php echo esc_textarea( $claude_code_local ); ?></textarea>
		</div>
	</div>

	<!-- HTTP Direct (Remote) -->
	<div class="elementor-mcp-section">
		<h2><?php esc_html_e( 'HTTP — Remote Connection', 'elementor-mcp' ); ?></h2>
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

			<h4><?php esc_html_e( 'Claude Code (.mcp.json)', 'elementor-mcp' ); ?></h4>
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

			<h4><?php esc_html_e( 'VS Code / Cursor', 'elementor-mcp' ); ?></h4>
			<div class="elementor-mcp-code-block">
				<pre><code id="elementor-mcp-vscode-code"></code></pre>
				<button type="button" class="button elementor-mcp-copy-btn" data-target="vscode-config"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
				<textarea id="vscode-config" class="elementor-mcp-copy-source"></textarea>
			</div>

		</div>
	</div>

	<!-- MCP Inspector -->
	<div class="elementor-mcp-section">
		<h2><?php esc_html_e( 'MCP Inspector — Debugging', 'elementor-mcp' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Use the MCP Inspector to test and debug tools interactively.', 'elementor-mcp' ); ?>
		</p>

		<h3><?php esc_html_e( 'Via WP-CLI', 'elementor-mcp' ); ?></h3>
		<?php $inspector_wpcli = 'npx @modelcontextprotocol/inspector wp mcp-adapter serve --server=elementor-mcp-server --user=admin --path=' . $wp_path; ?>
		<div class="elementor-mcp-code-block">
			<pre><code><?php echo esc_html( $inspector_wpcli ); ?></code></pre>
			<button type="button" class="button elementor-mcp-copy-btn" data-target="inspector-wpcli"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
			<textarea id="inspector-wpcli" class="elementor-mcp-copy-source"><?php echo esc_textarea( $inspector_wpcli ); ?></textarea>
		</div>

		<h3><?php esc_html_e( 'Via HTTP Proxy', 'elementor-mcp' ); ?></h3>
		<?php $inspector_proxy = 'npx @modelcontextprotocol/inspector -e WP_URL=' . $site_url . ' -e WP_USERNAME=admin -e WP_APP_PASSWORD=xxxx node bin/mcp-proxy.mjs'; ?>
		<div class="elementor-mcp-code-block">
			<pre><code><?php echo esc_html( $inspector_proxy ); ?></code></pre>
			<button type="button" class="button elementor-mcp-copy-btn" data-target="inspector-proxy"><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></button>
			<textarea id="inspector-proxy" class="elementor-mcp-copy-source"><?php echo esc_textarea( $inspector_proxy ); ?></textarea>
		</div>
	</div>

</div>

