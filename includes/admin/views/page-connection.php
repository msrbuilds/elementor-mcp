<?php
/**
 * Connection Management tab for MCP Tools for Elementor.
 *
 * Premium-style connection dashboard with token generation,
 * connection history, and real revoke functionality.
 *
 * @package Elementor_MCP
 * @since   2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var Elementor_MCP_Admin $this */
$emcp_endpoint      = rest_url( 'mcp/elementor-mcp-server' );
$emcp_enabled_count = $this->get_enabled_tool_count();
$emcp_total_count   = $this->get_total_tool_count();
$emcp_has_adapter   = class_exists( '\WP\MCP\Core\McpAdapter' );

// Load connection history.
$token_manager = new Elementor_MCP_Connection_Tokens();
$connections   = $token_manager->get_all();
$active_count  = 0;
$revoked_count = 0;

foreach ( $connections as $conn ) {
	if ( 'active' === $conn['status'] ) {
		$active_count++;
	} else {
		$revoked_count++;
	}
}
?>

<div class="emcp-connection-page">

	<!-- ================================================================= -->
	<!-- Section 1: Server Status                                          -->
	<!-- ================================================================= -->

	<div class="emcp-card">
		<div class="emcp-card-header">
			<div class="emcp-card-icon">
				<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm14 1a1 1 0 11-2 0 1 1 0 012 0zM2 13a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2zm14 1a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd"/></svg>
			</div>
			<div>
				<h3><?php esc_html_e( 'Server Status', 'elementor-mcp' ); ?></h3>
				<p class="emcp-card-desc"><?php esc_html_e( 'MCP server components and endpoint information.', 'elementor-mcp' ); ?></p>
			</div>
		</div>

		<div class="emcp-card-body">
			<div class="emcp-status-grid-3">
				<div class="emcp-status-pill">
					<span class="emcp-status-dot emcp-status-dot--ok"></span>
					<span class="emcp-status-label"><?php esc_html_e( 'MCP Plugin', 'elementor-mcp' ); ?></span>
					<span class="emcp-badge emcp-badge--success"><?php esc_html_e( 'Active', 'elementor-mcp' ); ?></span>
				</div>
				<div class="emcp-status-pill">
					<span class="emcp-status-dot <?php echo $emcp_has_adapter ? 'emcp-status-dot--ok' : 'emcp-status-dot--warn'; ?>"></span>
					<span class="emcp-status-label"><?php esc_html_e( 'MCP Adapter', 'elementor-mcp' ); ?></span>
					<span class="emcp-badge <?php echo $emcp_has_adapter ? 'emcp-badge--success' : 'emcp-badge--warning'; ?>">
						<?php echo esc_html( $emcp_has_adapter ? __( 'Active', 'elementor-mcp' ) : __( 'Missing', 'elementor-mcp' ) ); ?>
					</span>
				</div>
				<div class="emcp-status-pill">
					<span class="emcp-status-dot emcp-status-dot--ok"></span>
					<span class="emcp-status-label"><?php esc_html_e( 'Tools', 'elementor-mcp' ); ?></span>
					<span class="emcp-badge emcp-badge--info">
						<?php printf( '%d / %d', (int) $emcp_enabled_count, (int) $emcp_total_count ); ?>
					</span>
				</div>
			</div>

			<div class="emcp-endpoint-bar">
				<label><?php esc_html_e( 'MCP Endpoint', 'elementor-mcp' ); ?></label>
				<div class="emcp-endpoint-row">
					<code id="emcp-endpoint-url"><?php echo esc_html( $emcp_endpoint ); ?></code>
					<button type="button" class="emcp-btn emcp-btn--ghost emcp-btn--sm emcp-copy-btn" data-copy="<?php echo esc_attr( $emcp_endpoint ); ?>">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/></svg>
						<span><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></span>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- ================================================================= -->
	<!-- Section 2: Generate New Connection                                -->
	<!-- ================================================================= -->

	<div class="emcp-card">
		<div class="emcp-card-header">
			<div class="emcp-card-icon emcp-card-icon--primary">
				<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
			</div>
			<div>
				<h3><?php esc_html_e( 'Generate New Connection', 'elementor-mcp' ); ?></h3>
				<p class="emcp-card-desc"><?php esc_html_e( 'Create a secure MCP connection token for your AI client. Each connection is independently manageable.', 'elementor-mcp' ); ?></p>
			</div>
		</div>



		<div class="emcp-card-body">
			<div class="emcp-gen-form">
				<div class="emcp-field">
					<label for="emcp-conn-label"><?php esc_html_e( 'Connection Label', 'elementor-mcp' ); ?></label>
					<input type="text" id="emcp-conn-label" placeholder="e.g. Claude Desktop, Production Agent, Testing" />
					<p class="emcp-field-hint"><?php esc_html_e( 'A name to identify this connection in your history.', 'elementor-mcp' ); ?></p>
				</div>
				<div class="emcp-gen-form-row">
					<div class="emcp-field">
						<label for="emcp-conn-username"><?php esc_html_e( 'WordPress Username', 'elementor-mcp' ); ?></label>
						<input type="text" id="emcp-conn-username" value="<?php echo esc_attr( wp_get_current_user()->user_login ); ?>" />
					</div>
					<div class="emcp-field">
						<label for="emcp-conn-password"><?php esc_html_e( 'Application Password', 'elementor-mcp' ); ?></label>
						<input type="text" id="emcp-conn-password" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" />
						<p class="emcp-field-hint">
							<?php
							printf(
								esc_html__( 'Create one at %s', 'elementor-mcp' ),
								'<a href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '">' . esc_html__( 'Users → Profile', 'elementor-mcp' ) . '</a>'
							);
							?>
						</p>
					</div>
				</div>

				<div class="emcp-action-row">
					<button type="button" class="emcp-btn emcp-btn--primary" id="emcp-generate-btn">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
						<span><?php esc_html_e( 'Generate Connection', 'elementor-mcp' ); ?></span>
					</button>
				</div>
				<div class="emcp-toast" id="emcp-gen-toast" style="display:none;"></div>
			</div>

			<!-- One-time token + configs (shown after generation) -->
			<div id="emcp-gen-result" style="display:none;">
				<div class="emcp-divider"></div>

				<div class="emcp-onetime-alert">
					<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;fill:#d97706;flex-shrink:0"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
					<div>
						<strong><?php esc_html_e( 'Save this token now!', 'elementor-mcp' ); ?></strong>
						<p><?php esc_html_e( 'For security, this token will only be shown once. Copy your config below.', 'elementor-mcp' ); ?></p>
					</div>
				</div>

				<div class="emcp-token-display">
					<label><?php esc_html_e( 'Bearer Token', 'elementor-mcp' ); ?></label>
					<div class="emcp-token-row">
						<code id="emcp-raw-token" class="emcp-token-code"></code>
						<button type="button" class="emcp-btn emcp-btn--ghost emcp-btn--sm emcp-copy-btn" id="emcp-copy-token">
							<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/></svg>
							<span><?php esc_html_e( 'Copy', 'elementor-mcp' ); ?></span>
						</button>
					</div>
				</div>

				<div class="emcp-divider"></div>
				<h4><?php esc_html_e( 'Client Configs', 'elementor-mcp' ); ?></h4>
				<p class="emcp-field-hint" style="margin-bottom:12px;"><?php esc_html_e( 'Choose the config for your AI client and paste it into the appropriate config file.', 'elementor-mcp' ); ?></p>

				<div class="emcp-config-tabs">
					<button class="emcp-config-tab is-active" data-config="claude_code">Claude Code</button>
					<button class="emcp-config-tab" data-config="claude_desktop">Claude Desktop</button>
					<button class="emcp-config-tab" data-config="cursor">Cursor</button>
					<button class="emcp-config-tab" data-config="windsurf">Windsurf</button>
					<button class="emcp-config-tab" data-config="antigravity">Antigravity</button>
					<button class="emcp-config-tab" data-config="codex">Codex</button>
					<button class="emcp-config-tab" data-config="mcp_remote">mcp-remote</button>
				</div>
				<div class="emcp-config-display">
					<pre><code id="emcp-config-code"></code></pre>
					<button type="button" class="emcp-btn emcp-btn--ghost emcp-btn--sm emcp-copy-btn" id="emcp-copy-config">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/></svg>
						<span><?php esc_html_e( 'Copy Config', 'elementor-mcp' ); ?></span>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- ================================================================= -->
	<!-- Section 3: Connection History                                     -->
	<!-- ================================================================= -->

	<div class="emcp-card">
		<div class="emcp-card-header">
			<div class="emcp-card-icon">
				<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
			</div>
			<div>
				<h3>
					<?php esc_html_e( 'Connection History', 'elementor-mcp' ); ?>
					<?php if ( $active_count > 0 ) : ?>
						<span class="emcp-badge emcp-badge--success" style="margin-left:8px;"><?php printf( '%d active', $active_count ); ?></span>
					<?php endif; ?>
				</h3>
				<p class="emcp-card-desc"><?php esc_html_e( 'Manage generated connections. Revoked tokens stop working immediately.', 'elementor-mcp' ); ?></p>
			</div>
		</div>

		<div class="emcp-card-body">
			<div id="emcp-conn-history">
				<?php if ( empty( $connections ) ) : ?>
					<div class="emcp-empty-state" id="emcp-empty-state">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" style="width:40px;height:40px;fill:var(--mcp-gray-300)"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
						<p><?php esc_html_e( 'No connections generated yet.', 'elementor-mcp' ); ?></p>
						<p class="emcp-field-hint"><?php esc_html_e( 'Generate your first connection above to get started.', 'elementor-mcp' ); ?></p>
					</div>
				<?php else : ?>
					<table class="emcp-conn-table" id="emcp-conn-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Label', 'elementor-mcp' ); ?></th>
								<th><?php esc_html_e( 'Token', 'elementor-mcp' ); ?></th>
								<th><?php esc_html_e( 'Status', 'elementor-mcp' ); ?></th>
								<th><?php esc_html_e( 'Created', 'elementor-mcp' ); ?></th>
								<th><?php esc_html_e( 'Last Used', 'elementor-mcp' ); ?></th>
								<th><?php esc_html_e( 'Uses', 'elementor-mcp' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'elementor-mcp' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_reverse( $connections, true ) as $conn ) : ?>
								<tr id="emcp-row-<?php echo esc_attr( $conn['id'] ); ?>" class="<?php echo 'revoked' === $conn['status'] ? 'emcp-row--revoked' : ''; ?>">
									<td class="emcp-conn-label-cell">
										<strong><?php echo esc_html( $conn['label'] ); ?></strong>
										<span class="emcp-conn-meta"><?php echo esc_html( $conn['created_by'] ); ?></span>
									</td>
									<td><code class="emcp-token-hint"><?php echo esc_html( $conn['token_hint'] ); ?></code></td>
									<td>
										<span class="emcp-badge <?php echo 'active' === $conn['status'] ? 'emcp-badge--success' : 'emcp-badge--danger'; ?>" id="emcp-status-<?php echo esc_attr( $conn['id'] ); ?>">
											<?php echo esc_html( ucfirst( $conn['status'] ) ); ?>
										</span>
									</td>
									<td class="emcp-conn-date"><?php echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $conn['created_at'] ) ) ); ?></td>
									<td class="emcp-conn-date" id="emcp-used-<?php echo esc_attr( $conn['id'] ); ?>">
										<?php
										echo $conn['last_used_at']
											? esc_html( wp_date( 'M j, g:i A', strtotime( $conn['last_used_at'] ) ) )
											: '<span class="emcp-text-muted">' . esc_html__( 'Never', 'elementor-mcp' ) . '</span>';
										?>
									</td>
									<td><?php echo (int) ( $conn['usage_count'] ?? 0 ); ?></td>
									<td class="emcp-conn-actions">
										<?php if ( 'active' === $conn['status'] ) : ?>
											<button type="button" class="emcp-btn emcp-btn--danger emcp-btn--sm emcp-revoke-btn" data-id="<?php echo esc_attr( $conn['id'] ); ?>" data-label="<?php echo esc_attr( $conn['label'] ); ?>">
												<?php esc_html_e( 'Revoke', 'elementor-mcp' ); ?>
											</button>
										<?php else : ?>
											<button type="button" class="emcp-btn emcp-btn--ghost emcp-btn--sm emcp-delete-btn" data-id="<?php echo esc_attr( $conn['id'] ); ?>">
												<?php esc_html_e( 'Delete', 'elementor-mcp' ); ?>
											</button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="emcp-toast" id="emcp-history-toast" style="display:none;"></div>
		</div>
	</div>

</div><!-- /.emcp-connection-page -->

<!-- Revoke Confirmation Modal -->
<div class="emcp-modal-overlay" id="emcp-revoke-modal" style="display:none;">
	<div class="emcp-modal">
		<div class="emcp-modal-header">
			<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" style="width:22px;height:22px;fill:#ef4444"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
			<h4><?php esc_html_e( 'Revoke Connection', 'elementor-mcp' ); ?></h4>
		</div>
		<p><?php esc_html_e( 'Are you sure you want to revoke', 'elementor-mcp' ); ?> <strong id="emcp-revoke-label"></strong>?</p>
		<p class="emcp-field-hint"><?php esc_html_e( 'This connection will immediately stop working. Any AI client using this token will receive authentication errors.', 'elementor-mcp' ); ?></p>
		<div class="emcp-modal-actions">
			<button type="button" class="emcp-btn emcp-btn--ghost" id="emcp-revoke-cancel"><?php esc_html_e( 'Cancel', 'elementor-mcp' ); ?></button>
			<button type="button" class="emcp-btn emcp-btn--danger" id="emcp-revoke-confirm">
				<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
				<span><?php esc_html_e( 'Revoke Connection', 'elementor-mcp' ); ?></span>
			</button>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var admin = window.elementorMcpAdmin || {};
	var $ = function(id) { return document.getElementById(id); };

	function ajax(action, data) {
		var fd = new FormData();
		fd.append('action', action);
		fd.append('_ajax_nonce', admin.nonce || '');
		for (var k in data) fd.append(k, data[k]);
		return fetch(admin.ajaxUrl || ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function(r) { return r.json(); });
	}

	function showToast(id, msg, type) {
		var el = $(id);
		if (!el) return;
		el.textContent = msg;
		el.className = 'emcp-toast emcp-toast--' + type;
		el.style.display = '';
		setTimeout(function() { el.style.display = 'none'; }, 6000);
	}

	function btnState(btn, state, text) {
		if (!btn) return;
		btn.disabled = (state === 'loading');
		btn.classList.remove('is-loading', 'is-success', 'is-error');
		if (state !== 'default') btn.classList.add('is-' + state);
		var span = btn.querySelector('span');
		if (span) span.textContent = text;
	}

	// Copy to clipboard.
	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text);
		return new Promise(function(resolve) {
			var ta = document.createElement('textarea');
			ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
			document.body.appendChild(ta); ta.select(); document.execCommand('copy');
			document.body.removeChild(ta); resolve();
		});
	}

	// Copy buttons (data-copy attribute).
	document.querySelectorAll('.emcp-copy-btn[data-copy]').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var text = this.getAttribute('data-copy');
			copyText(text).then(function() {
				var span = btn.querySelector('span');
				var orig = span.textContent;
				span.textContent = '✓ Copied';
				setTimeout(function() { span.textContent = orig; }, 2000);
			});
		});
	});

	// ── Config data store ─────────────────────────────────────────────
	var configs = {};
	var activeTab = 'claude_code';

	function showConfig(key) {
		activeTab = key;
		var code = $('emcp-config-code');
		if (code && configs[key]) code.textContent = configs[key];
		document.querySelectorAll('.emcp-config-tab').forEach(function(t) {
			t.classList.toggle('is-active', t.getAttribute('data-config') === key);
		});
	}

	document.querySelectorAll('.emcp-config-tab').forEach(function(tab) {
		tab.addEventListener('click', function() {
			showConfig(this.getAttribute('data-config'));
		});
	});

	// Copy config button.
	var copyConfigBtn = $('emcp-copy-config');
	if (copyConfigBtn) copyConfigBtn.addEventListener('click', function() {
		if (configs[activeTab]) {
			copyText(configs[activeTab]).then(function() {
				var span = copyConfigBtn.querySelector('span');
				span.textContent = '✓ Copied';
				setTimeout(function() { span.textContent = 'Copy Config'; }, 2000);
			});
		}
	});

	// Copy token button.
	var copyTokenBtn = $('emcp-copy-token');
	if (copyTokenBtn) copyTokenBtn.addEventListener('click', function() {
		var token = $('emcp-raw-token');
		if (token) copyText(token.textContent).then(function() {
			var span = copyTokenBtn.querySelector('span');
			span.textContent = '✓ Copied';
			setTimeout(function() { span.textContent = 'Copy'; }, 2000);
		});
	});

	// ── Generate Connection ───────────────────────────────────────────
	var genBtn = $('emcp-generate-btn');
	if (genBtn) genBtn.addEventListener('click', function() {
		var label    = $('emcp-conn-label').value.trim();
		var username = $('emcp-conn-username').value.trim();
		var password = $('emcp-conn-password').value.trim();

		if (!label) { showToast('emcp-gen-toast', 'Connection label is required.', 'error'); return; }
		if (!username || !password) { showToast('emcp-gen-toast', 'Username and Application Password are required.', 'error'); return; }

		btnState(genBtn, 'loading', 'Generating…');

		ajax('emcp_generate_connection', {
			label: label,
			username: username,
			app_password: password
		}).then(function(r) {
			if (r.success) {
				btnState(genBtn, 'success', '✓ Created');
				showToast('emcp-gen-toast', r.data.message, 'success');

				// Show one-time token.
				$('emcp-raw-token').textContent = r.data.raw_token;
				$('emcp-gen-result').style.display = '';

				// Store configs.
				configs = r.data.configs;
				showConfig('claude_code');

				// Add row to history table.
				addHistoryRow(r.data.connection);

				// Clear form.
				$('emcp-conn-label').value = '';
				$('emcp-conn-password').value = '';
			} else {
				btnState(genBtn, 'error', 'Failed');
				showToast('emcp-gen-toast', r.data.message || 'Generation failed.', 'error');
			}
		}).catch(function() {
			btnState(genBtn, 'error', 'Error');
			showToast('emcp-gen-toast', 'Network error.', 'error');
		}).finally(function() {
			setTimeout(function() { btnState(genBtn, 'default', 'Generate Connection'); }, 4000);
		});
	});

	// ── Add row to history table ──────────────────────────────────────
	function addHistoryRow(conn) {
		// Remove empty state if present.
		var empty = $('emcp-empty-state');
		if (empty) empty.remove();

		// Create table if not existing.
		var table = $('emcp-conn-table');
		if (!table) {
			var history = $('emcp-conn-history');
			history.innerHTML = '<table class="emcp-conn-table" id="emcp-conn-table"><thead><tr>' +
				'<th>Label</th><th>Token</th><th>Status</th><th>Created</th><th>Last Used</th><th>Uses</th><th>Actions</th>' +
				'</tr></thead><tbody></tbody></table>';
			table = $('emcp-conn-table');
		}

		var tbody = table.querySelector('tbody');
		var tr = document.createElement('tr');
		tr.id = 'emcp-row-' + conn.id;
		tr.innerHTML =
			'<td class="emcp-conn-label-cell"><strong>' + escHtml(conn.label) + '</strong><span class="emcp-conn-meta">' + escHtml(conn.created_by) + '</span></td>' +
			'<td><code class="emcp-token-hint">' + escHtml(conn.token_hint) + '</code></td>' +
			'<td><span class="emcp-badge emcp-badge--success" id="emcp-status-' + conn.id + '">Active</span></td>' +
			'<td class="emcp-conn-date">Just now</td>' +
			'<td class="emcp-conn-date" id="emcp-used-' + conn.id + '"><span class="emcp-text-muted">Never</span></td>' +
			'<td>0</td>' +
			'<td class="emcp-conn-actions"><button type="button" class="emcp-btn emcp-btn--danger emcp-btn--sm emcp-revoke-btn" data-id="' + conn.id + '" data-label="' + escHtml(conn.label) + '">Revoke</button></td>';

		tbody.insertBefore(tr, tbody.firstChild);
		bindRevoke(tr.querySelector('.emcp-revoke-btn'));
	}

	function escHtml(s) {
		var d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	// ── Revoke Modal ──────────────────────────────────────────────────
	var revokeModal   = $('emcp-revoke-modal');
	var revokeLabel   = $('emcp-revoke-label');
	var revokeConfirm = $('emcp-revoke-confirm');
	var revokeCancel  = $('emcp-revoke-cancel');
	var revokeConnId  = null;

	function openRevoke(id, label) {
		revokeConnId = id;
		revokeLabel.textContent = '"' + label + '"';
		revokeModal.style.display = '';
	}

	if (revokeCancel) revokeCancel.addEventListener('click', function() {
		revokeModal.style.display = 'none';
	});

	if (revokeConfirm) revokeConfirm.addEventListener('click', function() {
		if (!revokeConnId) return;
		btnState(revokeConfirm, 'loading', 'Revoking…');

		ajax('emcp_revoke_connection', { conn_id: revokeConnId }).then(function(r) {
			revokeModal.style.display = 'none';
			if (r.success) {
				// Update row.
				var badge = $('emcp-status-' + revokeConnId);
				if (badge) {
					badge.className = 'emcp-badge emcp-badge--danger';
					badge.textContent = 'Revoked';
				}
				var row = $('emcp-row-' + revokeConnId);
				if (row) {
					row.classList.add('emcp-row--revoked');
					var actionsCell = row.querySelector('.emcp-conn-actions');
					if (actionsCell) {
						actionsCell.innerHTML = '<button type="button" class="emcp-btn emcp-btn--ghost emcp-btn--sm emcp-delete-btn" data-id="' + revokeConnId + '">Delete</button>';
						bindDelete(actionsCell.querySelector('.emcp-delete-btn'));
					}
				}
				showToast('emcp-history-toast', r.data.message, 'success');
			} else {
				showToast('emcp-history-toast', r.data.message || 'Revoke failed.', 'error');
			}
		}).catch(function() {
			revokeModal.style.display = 'none';
			showToast('emcp-history-toast', 'Network error.', 'error');
		}).finally(function() {
			btnState(revokeConfirm, 'default', 'Revoke Connection');
		});
	});

	// Close modal on overlay click.
	if (revokeModal) revokeModal.addEventListener('click', function(e) {
		if (e.target === revokeModal) revokeModal.style.display = 'none';
	});

	// ── Bind revoke buttons ───────────────────────────────────────────
	function bindRevoke(btn) {
		btn.addEventListener('click', function() {
			openRevoke(this.getAttribute('data-id'), this.getAttribute('data-label'));
		});
	}
	document.querySelectorAll('.emcp-revoke-btn').forEach(bindRevoke);

	// ── Bind delete buttons ───────────────────────────────────────────
	function bindDelete(btn) {
		btn.addEventListener('click', function() {
			var id = this.getAttribute('data-id');
			if (!confirm('Permanently delete this connection record?')) return;
			ajax('emcp_delete_connection', { conn_id: id }).then(function(r) {
				if (r.success) {
					var row = $('emcp-row-' + id);
					if (row) row.remove();
					showToast('emcp-history-toast', r.data.message, 'success');
				} else {
					showToast('emcp-history-toast', r.data.message || 'Delete failed.', 'error');
				}
			});
		});
	}
	document.querySelectorAll('.emcp-delete-btn').forEach(bindDelete);
});
</script>
