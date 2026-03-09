<?php
/**
 * Settings tab — Premium SaaS Dashboard.
 *
 * Features two unified cards:
 *   1. Screenshot Service (config + actions + usage + preview)
 *   2. Stock Images (provider selection + API key)
 *
 * All saves go through AJAX — no page reloads.
 *
 * @package Elementor_MCP
 * @since   2.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Current option values ────────────────────────────────────────────────────
$ss_enabled = get_option( 'elementor_mcp_screenshot_enabled', '1' );
$ss_api_url = get_option( 'elementor_mcp_screenshot_api_url', Elementor_MCP_Screenshot_Abilities::DEFAULT_API_URL );
$ss_api_key = get_option( 'elementor_mcp_screenshot_api_key', Elementor_MCP_Screenshot_Abilities::DEFAULT_API_KEY );
$ss_is_on   = ! empty( $ss_enabled );
$ss_configured = ! empty( $ss_api_url ) && ! empty( $ss_api_key );

$stock_enabled  = get_option( 'elementor_mcp_stock_enabled', '1' );
$stock_provider = get_option( 'elementor_mcp_stock_provider', 'openverse' );
$stock_api_key  = get_option( 'elementor_mcp_stock_api_key', '' );
$stock_is_on    = ! empty( $stock_enabled );

// Per-provider API keys.
$stock_keys = array(
	'pexels'    => get_option( 'elementor_mcp_stock_key_pexels', '' ),
	'pixabay'   => get_option( 'elementor_mcp_stock_key_pixabay', '' ),
	'unsplash'  => get_option( 'elementor_mcp_stock_key_unsplash', '' ),
	'openverse' => get_option( 'elementor_mcp_stock_key_openverse', '' ),
);
// Migrate: if per-provider key is empty but generic key exists, use generic.
if ( empty( $stock_keys[ $stock_provider ] ) && ! empty( $stock_api_key ) ) {
	$stock_keys[ $stock_provider ] = $stock_api_key;
}

// Badge logic.
$ss_badge = 'disabled';
if ( $ss_is_on && $ss_configured ) {
	$ss_badge = 'connected'; // Will be verified live via JS.
} elseif ( $ss_is_on ) {
	$ss_badge = 'disconnected';
}
?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Page Header
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="emcp-settings-header">
	<h2>
		<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" style="width:22px;height:22px;fill:var(--mcp-primary)">
			<path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
		</svg>
		<?php esc_html_e( 'Plugin Settings', 'elementor-mcp' ); ?>
	</h2>
	<p><?php esc_html_e( 'Configure integrations, APIs, and services used by MCP Tools for Elementor.', 'elementor-mcp' ); ?></p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Two-Column Grid
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="emcp-settings-grid emcp-settings-grid--asymmetric">

	<!-- ─────────────────────────────────────────────────────────────────────
	     Card 1 — Screenshot Service (unified)
	     ───────────────────────────────────────────────────────────────── -->
	<div class="emcp-card emcp-card--primary" id="emcp-ss-card">

		<!-- Card Header ──────────────────────────────────────────────── -->
		<div class="emcp-card-header">
			<div class="emcp-card-icon emcp-card-icon--purple">
				<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"/></svg>
			</div>
			<div class="emcp-card-title-wrap">
				<h3 class="emcp-card-title">
					<?php esc_html_e( 'Screenshot Service', 'elementor-mcp' ); ?>
					<span class="emcp-badge emcp-badge--<?php echo esc_attr( $ss_badge ); ?>" id="emcp-ss-badge">
						<?php
						$badge_labels = array(
							'connected'    => __( 'Connected', 'elementor-mcp' ),
							'disconnected' => __( 'Disconnected', 'elementor-mcp' ),
							'disabled'     => __( 'Disabled', 'elementor-mcp' ),
							'invalid'      => __( 'Invalid Key', 'elementor-mcp' ),
						);
						echo esc_html( $badge_labels[ $ss_badge ] ?? $badge_labels['disabled'] );
						?>
					</span>
				</h3>
				<p class="emcp-card-desc"><?php esc_html_e( 'Capture and verify pages during AI-powered generation with screenshot tools.', 'elementor-mcp' ); ?></p>
			</div>
		</div>

		<div class="emcp-card-body">

			<!-- Section A: Configuration ───────────────────────────── -->
			<div class="elementor-mcp-tools-grid">
				<label class="elementor-mcp-tool-card <?php echo esc_attr( $ss_is_on ? 'is-enabled' : 'is-disabled' ); ?>" id="emcp-ss-toggle-card">
					<input type="checkbox" id="emcp-ss-enabled" value="1" <?php checked( $ss_is_on ); ?> />
					<span class="elementor-mcp-toggle" aria-hidden="true"><span class="elementor-mcp-toggle-track"></span></span>
					<span class="elementor-mcp-tool-info">
						<span class="elementor-mcp-tool-name"><?php esc_html_e( 'Screenshot Tools', 'elementor-mcp' ); ?></span>
						<span class="elementor-mcp-tool-desc"><?php esc_html_e( 'Enable take-screenshot and get-page-screenshot tools for AI agents.', 'elementor-mcp' ); ?></span>
						<code class="elementor-mcp-tool-slug">elementor-mcp/take-screenshot, elementor-mcp/get-page-screenshot</code>
					</span>
				</label>
			</div>

			<div id="emcp-ss-fields" style="<?php echo $ss_is_on ? '' : 'display:none;'; ?>">
				<div class="emcp-field">
					<label for="emcp-ss-api-url"><?php esc_html_e( 'API Server URL', 'elementor-mcp' ); ?></label>
					<input type="url" id="emcp-ss-api-url" value="<?php echo esc_attr( $ss_api_url ); ?>" placeholder="https://your-screenshot-server.com" />
					<p class="emcp-field-hint"><?php esc_html_e( 'Base URL of your Screenshot SaaS server (no trailing slash).', 'elementor-mcp' ); ?></p>
				</div>

				<div class="emcp-field">
					<label for="emcp-ss-api-key"><?php esc_html_e( 'API Key', 'elementor-mcp' ); ?></label>
					<div class="emcp-field-row">
						<input type="password" id="emcp-ss-api-key" value="<?php echo esc_attr( $ss_api_key ); ?>" placeholder="ss_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" autocomplete="off" />
						<button type="button" class="emcp-btn emcp-btn--ghost emcp-btn--sm" onclick="var f=document.getElementById('emcp-ss-api-key');f.type=f.type==='password'?'text':'password';">
							<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
						</button>
					</div>
					<p class="emcp-field-hint"><?php esc_html_e( 'Your Screenshot SaaS API key (starts with ss_live_).', 'elementor-mcp' ); ?></p>
				</div>

				<!-- Section B: Action Row ──────────────────────────── -->
				<div class="emcp-divider"></div>
				<div class="emcp-action-row">
					<button type="button" class="emcp-btn emcp-btn--primary" id="emcp-ss-save">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
						<span><?php esc_html_e( 'Save Settings', 'elementor-mcp' ); ?></span>
					</button>
					<button type="button" class="emcp-btn emcp-btn--secondary" id="emcp-ss-test">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z" clip-rule="evenodd"/></svg>
						<span><?php esc_html_e( 'Test Connection', 'elementor-mcp' ); ?></span>
					</button>
					<button type="button" class="emcp-btn emcp-btn--secondary" id="emcp-ss-capture">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
						<span><?php esc_html_e( 'Take Test Screenshot', 'elementor-mcp' ); ?></span>
					</button>
				</div>

				<!-- Toast (inline feedback) ────────────────────────── -->
				<div class="emcp-toast" id="emcp-ss-toast" style="display:none;"></div>

				<!-- Section C: Usage Stats ─────────────────────────── -->
				<div class="emcp-divider"></div>
				<div id="emcp-ss-usage" style="display:none;">
					<h4 class="emcp-subsection-title"><?php esc_html_e( 'API Usage', 'elementor-mcp' ); ?></h4>
					<div class="emcp-usage-grid">
						<div class="emcp-usage-item">
							<span class="emcp-usage-label"><?php esc_html_e( 'Plan', 'elementor-mcp' ); ?></span>
							<span class="emcp-usage-value" id="emcp-ss-plan">—</span>
						</div>
						<div class="emcp-usage-item">
							<span class="emcp-usage-label"><?php esc_html_e( 'Used', 'elementor-mcp' ); ?></span>
							<span class="emcp-usage-value" id="emcp-ss-used">—</span>
						</div>
						<div class="emcp-usage-item">
							<span class="emcp-usage-label"><?php esc_html_e( 'Remaining', 'elementor-mcp' ); ?></span>
							<span class="emcp-usage-value" id="emcp-ss-remaining">—</span>
						</div>
						<div class="emcp-usage-item">
							<span class="emcp-usage-label"><?php esc_html_e( 'Rate Limit', 'elementor-mcp' ); ?></span>
							<span class="emcp-usage-value" id="emcp-ss-rate">—</span>
						</div>
					</div>
					<div class="emcp-usage-bar-wrap">
						<div class="emcp-usage-bar" id="emcp-ss-bar"></div>
					</div>
					<p class="emcp-usage-meta" id="emcp-ss-meta"></p>
				</div>

				<!-- Empty state (shown when no usage loaded) -->
				<div id="emcp-ss-usage-empty">
					<p class="emcp-empty-hint">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;vertical-align:text-bottom;margin-right:4px;fill:var(--mcp-gray-400)"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'Save your credentials, then click "Test Connection" to view usage data.', 'elementor-mcp' ); ?>
					</p>
				</div>

				<!-- Section D: Screenshot Preview ──────────────────── -->
				<div id="emcp-ss-preview" style="display:none;">
					<div class="emcp-divider"></div>
					<h4 class="emcp-subsection-title"><?php esc_html_e( 'Screenshot Preview', 'elementor-mcp' ); ?></h4>
					<div class="emcp-preview-area">
						<img id="emcp-ss-preview-img" src="" alt="Screenshot preview" />
					</div>
					<p class="emcp-usage-meta" id="emcp-ss-preview-meta"></p>
				</div>

			</div><!-- /#emcp-ss-fields -->
		</div>
	</div>

	<!-- ─────────────────────────────────────────────────────────────────────
	     Card 2 — Stock Images (separate)
	     ───────────────────────────────────────────────────────────────── -->
	<div class="emcp-card" id="emcp-stock-card">

		<!-- Card Header ──────────────────────────────────────────────── -->
		<div class="emcp-card-header">
			<div class="emcp-card-icon emcp-card-icon--green">
				<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
			</div>
			<div class="emcp-card-title-wrap">
				<h3 class="emcp-card-title">
					<?php esc_html_e( 'Stock Images', 'elementor-mcp' ); ?>
					<span class="emcp-badge emcp-badge--<?php echo esc_attr( $stock_is_on ? 'connected' : 'disabled' ); ?>" id="emcp-stock-badge">
						<?php echo esc_html( $stock_is_on ? __( 'Enabled', 'elementor-mcp' ) : __( 'Disabled', 'elementor-mcp' ) ); ?>
					</span>
				</h3>
				<p class="emcp-card-desc"><?php esc_html_e( 'Configure stock image provider for AI-powered image search and sideloading.', 'elementor-mcp' ); ?></p>
			</div>
		</div>

		<div class="emcp-card-body">
			<div class="elementor-mcp-tools-grid">
				<label class="elementor-mcp-tool-card <?php echo esc_attr( $stock_is_on ? 'is-enabled' : 'is-disabled' ); ?>" id="emcp-stock-toggle-card">
					<input type="checkbox" id="emcp-stock-enabled" value="1" <?php checked( $stock_is_on ); ?> />
					<span class="elementor-mcp-toggle" aria-hidden="true"><span class="elementor-mcp-toggle-track"></span></span>
					<span class="elementor-mcp-tool-info">
						<span class="elementor-mcp-tool-name"><?php esc_html_e( 'Stock Image Tools', 'elementor-mcp' ); ?></span>
						<span class="elementor-mcp-tool-desc"><?php esc_html_e( 'Enable search-images, sideload-image, and add-stock-image tools.', 'elementor-mcp' ); ?></span>
						<code class="elementor-mcp-tool-slug">elementor-mcp/search-images, elementor-mcp/sideload-image</code>
					</span>
				</label>
			</div>

			<div id="emcp-stock-fields" style="<?php echo $stock_is_on ? '' : 'display:none;'; ?>">
				<div class="emcp-field">
					<label for="emcp-stock-provider"><?php esc_html_e( 'Image Provider', 'elementor-mcp' ); ?></label>
					<select id="emcp-stock-provider">
						<option value="pexels" <?php selected( $stock_provider, 'pexels' ); ?>><?php esc_html_e( 'Pexels — High-quality photos (200 req/hour)', 'elementor-mcp' ); ?></option>
						<option value="pixabay" <?php selected( $stock_provider, 'pixabay' ); ?>><?php esc_html_e( 'Pixabay — Photos, illustrations & vectors (100 req/min)', 'elementor-mcp' ); ?></option>
						<option value="unsplash" <?php selected( $stock_provider, 'unsplash' ); ?>><?php esc_html_e( 'Unsplash — Premium photos (50 req/hour)', 'elementor-mcp' ); ?></option>
						<option value="openverse" <?php selected( $stock_provider, 'openverse' ); ?>><?php esc_html_e( 'Openverse — Creative Commons (no key needed)', 'elementor-mcp' ); ?></option>
					</select>
					<p class="emcp-field-hint" id="emcp-stock-provider-hint"><?php esc_html_e( 'Select where stock images are searched from.', 'elementor-mcp' ); ?></p>
				</div>

				<div class="emcp-field">
					<label for="emcp-stock-api-key"><?php esc_html_e( 'API Key', 'elementor-mcp' ); ?></label>
					<div class="emcp-field-row">
						<input type="password" id="emcp-stock-api-key" value="<?php echo esc_attr( $stock_keys[ $stock_provider ] ?? '' ); ?>" placeholder="Enter API key" autocomplete="off" />
						<button type="button" class="emcp-btn emcp-btn--ghost emcp-btn--sm" onclick="var f=document.getElementById('emcp-stock-api-key');f.type=f.type==='password'?'text':'password';">
							<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
						</button>
					</div>
					<p class="emcp-field-hint" id="emcp-stock-key-help"></p>
				</div>

				<!-- Openverse Registration Dialog (shown only when provider=openverse) -->
				<div id="emcp-ov-register-section" style="display:none;">
					<div class="emcp-divider"></div>
					<div class="emcp-ov-register-card">
						<div class="emcp-ov-header">
							<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" style="width:18px;height:18px;fill:var(--mcp-primary);flex-shrink:0"><path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z" clip-rule="evenodd"/></svg>
							<div>
								<strong><?php esc_html_e( 'Register for Openverse API', 'elementor-mcp' ); ?></strong>
								<p class="emcp-field-hint" style="margin:2px 0 0"><?php esc_html_e( 'Get an OAuth2 token for higher rate limits. Token is saved automatically.', 'elementor-mcp' ); ?></p>
							</div>
						</div>
						<div class="emcp-ov-form">
							<div class="emcp-field">
								<label for="emcp-ov-name"><?php esc_html_e( 'Application Name', 'elementor-mcp' ); ?></label>
								<input type="text" id="emcp-ov-name" value="MCP Tools for Elementor" placeholder="My Project" />
							</div>
							<div class="emcp-field">
								<label for="emcp-ov-desc"><?php esc_html_e( 'Description', 'elementor-mcp' ); ?></label>
								<input type="text" id="emcp-ov-desc" value="AI-powered page builder stock image search" placeholder="What you'll use it for" />
							</div>
							<div class="emcp-field">
								<label for="emcp-ov-email"><?php esc_html_e( 'Email', 'elementor-mcp' ); ?></label>
								<input type="email" id="emcp-ov-email" value="<?php echo esc_attr( get_option( 'admin_email', '' ) ); ?>" placeholder="you@example.com" />
							</div>
							<div class="emcp-action-row">
								<button type="button" class="emcp-btn emcp-btn--primary" id="emcp-ov-register-btn">
									<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
									<span><?php esc_html_e( 'Register & Get Token', 'elementor-mcp' ); ?></span>
								</button>
							</div>
							<div class="emcp-toast" id="emcp-ov-toast" style="display:none;"></div>
						</div>
					</div>
				</div>

				<div class="emcp-divider"></div>
				<div class="emcp-action-row">
					<button type="button" class="emcp-btn emcp-btn--primary" id="emcp-stock-save">
						<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
						<span><?php esc_html_e( 'Save Settings', 'elementor-mcp' ); ?></span>
					</button>
				</div>

				<div class="emcp-toast" id="emcp-stock-toast" style="display:none;"></div>
			</div>
		</div>
	</div>

</div><!-- /.emcp-settings-grid -->

<!-- ═══════════════════════════════════════════════════════════════════════════
     Settings Tab JavaScript — AJAX interactions
     ═══════════════════════════════════════════════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
	'use strict';

	var C = window.elementorMcpAdmin || {};
	var I = C.i18n || {};

	// ── Helpers ───────────────────────────────────────────────────────────

	function $(id) { return document.getElementById(id); }

	function formatNum(n) {
		if (n >= 999999999) return 'Unlimited';
		if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
		if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
		return n.toLocaleString();
	}

	function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

	function timeAgo(ts) {
		if (!ts) return '';
		var diff = (Date.now() - new Date(ts).getTime()) / 1000;
		if (diff < 60) return Math.floor(diff) + 's ago';
		if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
		if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
		return Math.floor(diff / 86400) + 'd ago';
	}

	// ── Button state machine ─────────────────────────────────────────────

	function btnState(btn, state, text) {
		if (!btn) return;
		var span = btn.querySelector('span');
		btn.classList.remove('emcp-btn--loading', 'emcp-btn--success', 'emcp-btn--error');

		if (state === 'loading') {
			btn.classList.add('emcp-btn--loading');
			btn.disabled = true;
			if (span) span.textContent = text || '';
		} else if (state === 'success') {
			btn.classList.add('emcp-btn--success');
			btn.disabled = false;
			if (span) span.textContent = text || '';
			setTimeout(function() { btn.classList.remove('emcp-btn--success'); }, 2500);
		} else if (state === 'error') {
			btn.classList.add('emcp-btn--error');
			btn.disabled = false;
			if (span) span.textContent = text || '';
			setTimeout(function() { btn.classList.remove('emcp-btn--error'); }, 3000);
		} else {
			btn.disabled = false;
			if (span) span.textContent = text || '';
		}
	}

	// ── Toast ─────────────────────────────────────────────────────────────

	function showToast(id, msg, type) {
		var t = $(id);
		if (!t) return;
		t.className = 'emcp-toast emcp-toast--' + (type || 'info');
		t.textContent = msg;
		t.style.display = '';
		clearTimeout(t._tid);
		t._tid = setTimeout(function() { t.style.display = 'none'; }, 5000);
	}

	// ── Badge ─────────────────────────────────────────────────────────────

	function setBadge(id, status, label) {
		var b = $(id);
		if (!b) return;
		b.className = 'emcp-badge emcp-badge--' + status;
		b.textContent = label || cap(status);
	}

	// ── AJAX helper ──────────────────────────────────────────────────────

	function ajax(action, data) {
		var fd = new FormData();
		fd.append('action', action);
		fd.append('nonce', C.nonce);
		if (data) {
			Object.keys(data).forEach(function(k) { fd.append(k, data[k]); });
		}
		return fetch(C.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function(r) { return r.json(); });
	}

	// ── Toggle card state + field visibility ─────────────────────────────

	var togglePairs = [
		{ card: 'emcp-ss-toggle-card',    fields: 'emcp-ss-fields',    cb: 'emcp-ss-enabled' },
		{ card: 'emcp-stock-toggle-card', fields: 'emcp-stock-fields', cb: 'emcp-stock-enabled' }
	];

	togglePairs.forEach(function(t) {
		var card = $(t.card), fields = $(t.fields), cb = $(t.cb);
		if (!card || !cb) return;
		cb.addEventListener('change', function() {
			card.className = 'elementor-mcp-tool-card ' + (cb.checked ? 'is-enabled' : 'is-disabled');
			if (fields) fields.style.display = cb.checked ? '' : 'none';
		});
	});

	// ══════════════════════════════════════════════════════════════════════
	//  Screenshot Service — AJAX Actions
	// ══════════════════════════════════════════════════════════════════════

	var ssRefreshTimer = null;

	// ── Save ──────────────────────────────────────────────────────────────
	var ssBtn = $('emcp-ss-save');
	if (ssBtn) ssBtn.addEventListener('click', function() {
		btnState(ssBtn, 'loading', I.saving);
		ajax('emcp_save_settings', {
			group: 'screenshot',
			screenshot_enabled: $('emcp-ss-enabled').checked ? '1' : '',
			screenshot_api_url: $('emcp-ss-api-url').value,
			screenshot_api_key: $('emcp-ss-api-key').value
		}).then(function(r) {
			if (r.success) {
				btnState(ssBtn, 'success', I.saved);
				showToast('emcp-ss-toast', r.data.message, 'success');
				setBadge('emcp-ss-badge', r.data.enabled ? 'connected' : 'disabled', r.data.enabled ? I.connected : I.disabled);
				scheduleUsageRefresh();
			} else {
				btnState(ssBtn, 'error', I.saveFailed);
				showToast('emcp-ss-toast', r.data.message || I.saveFailed, 'error');
			}
		}).catch(function() {
			btnState(ssBtn, 'error', I.saveFailed);
		}).finally(function() {
			setTimeout(function() { btnState(ssBtn, 'default', I.saveSettings); }, 3000);
		});
	});

	// ── Test Connection ──────────────────────────────────────────────────
	var testBtn = $('emcp-ss-test');
	if (testBtn) testBtn.addEventListener('click', function() {
		btnState(testBtn, 'loading', I.testing);
		ajax('emcp_test_connection', {}).then(function(r) {
			if (r.success) {
				btnState(testBtn, 'success', I.connected);
				showToast('emcp-ss-toast', r.data.message, 'success');
				setBadge('emcp-ss-badge', 'connected', I.connected);
				if (r.data.usage) renderUsage(r.data.usage);
				scheduleUsageRefresh();
			} else {
				btnState(testBtn, 'error', r.data.message || I.disconnected);
				showToast('emcp-ss-toast', r.data.message, 'error');
				setBadge('emcp-ss-badge', r.data.status || 'disconnected', r.data.message || I.disconnected);
			}
		}).catch(function(e) {
			btnState(testBtn, 'error', I.disconnected);
			showToast('emcp-ss-toast', e.message || I.disconnected, 'error');
		}).finally(function() {
			setTimeout(function() { btnState(testBtn, 'default', I.testConnection); }, 3000);
		});
	});

	// ── Take Screenshot ──────────────────────────────────────────────────
	var capBtn = $('emcp-ss-capture');
	if (capBtn) capBtn.addEventListener('click', function() {
		btnState(capBtn, 'loading', I.capturing);
		ajax('emcp_take_screenshot', {}).then(function(r) {
			if (r.success) {
				btnState(capBtn, 'success', I.captureSuccess);
				showToast('emcp-ss-toast', r.data.message, 'success');
				// Show preview.
				var preview = $('emcp-ss-preview');
				var img = $('emcp-ss-preview-img');
				var meta = $('emcp-ss-preview-meta');
				if (preview && img) {
					img.src = r.data.image_url;
					preview.style.display = '';
				}
				if (meta) meta.textContent = '✓ ' + (r.data.message || '') + ' — ' + (r.data.timestamp || '');
				scheduleUsageRefresh();
			} else {
				btnState(capBtn, 'error', I.captureFailed);
				showToast('emcp-ss-toast', r.data.message || I.captureFailed, 'error');
			}
		}).catch(function() {
			btnState(capBtn, 'error', I.captureFailed);
		}).finally(function() {
			setTimeout(function() { btnState(capBtn, 'default', I.takeScreenshot); }, 3000);
		});
	});

	// ── Usage Rendering ──────────────────────────────────────────────────
	function renderUsage(u) {
		$('emcp-ss-usage').style.display = '';
		$('emcp-ss-usage-empty').style.display = 'none';

		$('emcp-ss-plan').textContent      = cap(u.plan || 'unknown');
		$('emcp-ss-used').textContent      = formatNum(u.current_usage || 0);
		$('emcp-ss-remaining').textContent = formatNum(u.remaining || 0);
		$('emcp-ss-rate').textContent      = formatNum(u.rate_limit || 0) + '/min';

		var pct = u.monthly_limit ? Math.min(((u.current_usage || 0) / u.monthly_limit) * 100, 100) : 0;
		var bar = $('emcp-ss-bar');
		bar.style.width = pct + '%';
		bar.className = 'emcp-usage-bar' + (pct > 90 ? ' emcp-usage-bar--danger' : pct > 70 ? ' emcp-usage-bar--warn' : '');

		var meta = $('emcp-ss-meta');
		meta.textContent = formatNum(u.current_usage || 0) + ' / ' + formatNum(u.monthly_limit || 0) + ' screenshots (' + pct.toFixed(1) + '%)';
		if (u.last_used_at) meta.textContent += ' · Last used ' + timeAgo(u.last_used_at);
	}

	// ── Smart Refresh (60s after save/test) ──────────────────────────────
	function scheduleUsageRefresh() {
		clearTimeout(ssRefreshTimer);
		ssRefreshTimer = setTimeout(function() {
			ajax('emcp_refresh_usage', {}).then(function(r) {
				if (r.success && r.data.usage) {
					renderUsage(r.data.usage);
					setBadge('emcp-ss-badge', 'connected', I.connected);
				}
			});
		}, 60000);
	}

	// ══════════════════════════════════════════════════════════════════════
	//  Stock Images — AJAX Actions
	// ══════════════════════════════════════════════════════════════════════

	var stockBtn = $('emcp-stock-save');
	if (stockBtn) stockBtn.addEventListener('click', function() {
		btnState(stockBtn, 'loading', I.saving);
		ajax('emcp_save_settings', {
			group: 'stock',
			stock_enabled:  $('emcp-stock-enabled').checked ? '1' : '',
			stock_provider: $('emcp-stock-provider').value,
			stock_api_key:  $('emcp-stock-api-key').value
		}).then(function(r) {
			if (r.success) {
				btnState(stockBtn, 'success', I.saved);
				showToast('emcp-stock-toast', r.data.message, 'success');
				setBadge('emcp-stock-badge', r.data.enabled ? 'connected' : 'disabled', r.data.enabled ? '<?php echo esc_js( __( 'Enabled', 'elementor-mcp' ) ); ?>' : I.disabled);
			} else {
				btnState(stockBtn, 'error', I.saveFailed);
				showToast('emcp-stock-toast', r.data.message || I.saveFailed, 'error');
			}
		}).catch(function() {
			btnState(stockBtn, 'error', I.saveFailed);
		}).finally(function() {
			setTimeout(function() { btnState(stockBtn, 'default', I.saveSettings); }, 3000);
		});
	});

	// ── Provider help text + Openverse registration visibility ──────────
	var provSel = $('emcp-stock-provider');
	var ovSection = $('emcp-ov-register-section');

	// Per-provider keys map (loaded from DB).
	var providerKeys = <?php echo wp_json_encode( $stock_keys ); ?>;

	if (provSel) {
		var keyInput = $('emcp-stock-api-key');
		var keyHelp  = $('emcp-stock-key-help');
		var lastProvider = provSel.value;

		var info = {
			pexels:    { ph: 'Enter your Pexels API key',      help: 'Get a free key at <a href="https://www.pexels.com/api/" target="_blank">pexels.com/api</a>' },
			pixabay:   { ph: 'Enter your Pixabay API key',     help: 'Get a free key at <a href="https://pixabay.com/api/docs/" target="_blank">pixabay.com/api/docs</a>' },
			unsplash:  { ph: 'Enter your Unsplash Access Key', help: 'Get a free key at <a href="https://unsplash.com/developers" target="_blank">unsplash.com/developers</a>' },
			openverse: { ph: 'Paste Openverse Bearer token (optional)', help: 'Optional — use the form below to register, or <a href="https://api.openverse.org/v1/#tag/auth/operation/register" target="_blank">register manually</a>.' }
		};

		function updateProv() {
			var p = provSel.value;

			// Save current key back to map before switching.
			if (keyInput && lastProvider) {
				providerKeys[lastProvider] = keyInput.value;
			}

			// Load key for new provider.
			if (keyInput) keyInput.value = providerKeys[p] || '';
			if (info[p] && keyInput) keyInput.placeholder = info[p].ph;
			if (info[p] && keyHelp)  keyHelp.innerHTML    = info[p].help;
			if (ovSection) ovSection.style.display = (p === 'openverse') ? '' : 'none';

			lastProvider = p;
		}
		provSel.addEventListener('change', updateProv);
		updateProv();
	}

	// ── Openverse Registration ───────────────────────────────────────────
	var ovBtn = $('emcp-ov-register-btn');
	if (ovBtn) ovBtn.addEventListener('click', function() {
		var name  = $('emcp-ov-name').value.trim();
		var desc  = $('emcp-ov-desc').value.trim();
		var email = $('emcp-ov-email').value.trim();

		if (!name || !desc || !email) {
			showToast('emcp-ov-toast', 'All fields are required.', 'error');
			return;
		}

		btnState(ovBtn, 'loading', 'Registering…');
		ajax('emcp_openverse_register', {
			ov_name:  name,
			ov_desc:  desc,
			ov_email: email
		}).then(function(r) {
			if (r.success) {
				btnState(ovBtn, 'success', '✓ Token Saved');
				showToast('emcp-ov-toast', r.data.message, 'success');
				// Auto-fill the API key field.
				var keyInput = $('emcp-stock-api-key');
				if (keyInput) {
					keyInput.value = r.data.access_token;
					keyInput.type = 'text'; // Show it briefly.
					setTimeout(function() { keyInput.type = 'password'; }, 3000);
				}
			} else {
				btnState(ovBtn, 'error', 'Failed');
				showToast('emcp-ov-toast', r.data.message || 'Registration failed.', 'error');
			}
		}).catch(function(e) {
			btnState(ovBtn, 'error', 'Error');
			showToast('emcp-ov-toast', e.message || 'Network error.', 'error');
		}).finally(function() {
			setTimeout(function() { btnState(ovBtn, 'default', 'Register & Get Token'); }, 4000);
		});
	});
});
</script>
