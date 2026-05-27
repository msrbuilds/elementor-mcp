/**
 * MCP Tools for Elementor — Admin Settings Scripts
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

(function () {
	'use strict';

	/**
	 * Tools tab — Enable/Disable all toggles.
	 */
	function initToolsForm() {
		var form = document.getElementById( 'elementor-mcp-tools-form' );
		if ( ! form ) {
			return;
		}

		// Global enable/disable all.
		var enableAll = form.querySelector( '.elementor-mcp-enable-all' );
		var disableAll = form.querySelector( '.elementor-mcp-disable-all' );

		if ( enableAll ) {
			enableAll.addEventListener( 'click', function () {
				form.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( cb ) {
					cb.checked = true;
				} );
				updateCards( form );
			} );
		}

		if ( disableAll ) {
			disableAll.addEventListener( 'click', function () {
				form.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( cb ) {
					cb.checked = false;
				} );
				updateCards( form );
			} );
		}

		// Per-category enable/disable.
		form.querySelectorAll( '.elementor-mcp-category' ).forEach( function ( cat ) {
			var catEnableAll = cat.querySelector( '.elementor-mcp-cat-enable-all' );
			var catDisableAll = cat.querySelector( '.elementor-mcp-cat-disable-all' );

			if ( catEnableAll ) {
				catEnableAll.addEventListener( 'click', function () {
					cat.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( cb ) {
						cb.checked = true;
					} );
					updateCards( form );
				} );
			}

			if ( catDisableAll ) {
				catDisableAll.addEventListener( 'click', function () {
					cat.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( cb ) {
						cb.checked = false;
					} );
					updateCards( form );
				} );
			}
		} );

		// Toggle card visual state on checkbox change.
		form.addEventListener( 'change', function ( e ) {
			if ( e.target.type === 'checkbox' ) {
				updateCards( form );
			}
		} );
	}

	/**
	 * Update card visual state based on checkbox.
	 *
	 * @param {HTMLElement} form The form element.
	 */
	function updateCards( form ) {
		form.querySelectorAll( '.elementor-mcp-tool-card' ).forEach( function ( card ) {
			var cb = card.querySelector( 'input[type="checkbox"]' );
			card.classList.toggle( 'is-enabled', cb.checked );
			card.classList.toggle( 'is-disabled', ! cb.checked );
		} );
	}

	/**
	 * Populate a code block and its hidden copy source.
	 *
	 * @param {string} codeId  The ID of the <code> element.
	 * @param {string} copyId  The ID of the <textarea> copy source.
	 * @param {string} json    The JSON string to display.
	 */
	function setConfigBlock( codeId, copyId, json ) {
		var codeEl = document.getElementById( codeId );
		var copyEl = document.getElementById( copyId );
		if ( codeEl ) {
			codeEl.textContent = json;
		}
		if ( copyEl ) {
			copyEl.value = json;
		}
	}

	/**
	 * Connection tab — Generate credentials and populate all HTTP config blocks.
	 */
	function initBase64Generator() {
		var generateBtn = document.getElementById( 'elementor-mcp-generate-b64' );
		if ( ! generateBtn ) {
			return;
		}

		generateBtn.addEventListener( 'click', function () {
			var username = document.getElementById( 'elementor-mcp-b64-username' );
			var appPassword = document.getElementById( 'elementor-mcp-b64-app-password' );

			if ( ! username || ! appPassword || ! username.value.trim() || ! appPassword.value.trim() ) {
				/* global alert */
				alert( 'Please enter both username and application password.' );
				return;
			}

			var credentials = username.value.trim() + ':' + appPassword.value.trim();
			var base64 = btoa( credentials );
			var headerValue = 'Basic ' + base64;

			// Show the result row.
			var resultRow = document.getElementById( 'elementor-mcp-b64-result-row' );
			var resultCode = document.getElementById( 'elementor-mcp-b64-result' );
			var resultCopy = document.getElementById( 'elementor-mcp-b64-result-copy' );

			if ( resultRow && resultCode && resultCopy ) {
				resultRow.style.display = '';
				resultCode.textContent = headerValue;
				resultCopy.value = headerValue;
			}

			if ( typeof elementorMcpAdmin === 'undefined' || ! elementorMcpAdmin.mcpEndpoint ) {
				return;
			}

			var endpoint = elementorMcpAdmin.mcpEndpoint;
			var siteUrl = elementorMcpAdmin.siteUrl || '';
			var proxyPath = elementorMcpAdmin.proxyPath || '';
			var rawUsername = username.value.trim();
			var rawAppPassword = appPassword.value.trim();

			// Show the proxy config blocks container.
			var proxyConfigsDiv = document.getElementById( 'elementor-mcp-proxy-configs' );
			if ( proxyConfigsDiv ) {
				proxyConfigsDiv.style.display = '';
			}

			// Use the absolute filesystem path from the server.
			var fullProxyPath = proxyPath;

			// Determine a sensible log file path based on OS.
			var isWindows = fullProxyPath.indexOf( '\\' ) !== -1 || fullProxyPath.match( /^[A-Z]:/i );
			var logFilePath = isWindows ? 'C:\\tmp\\elementor-mcp-debug.log' : '/tmp/elementor-mcp-debug.log';

			// Claude Code proxy config (.mcp.json) — uses type: stdio with Node.js proxy.
			var claudeCodeProxyConfig = {
				mcpServers: {
					'elementor-mcp': {
						type: 'stdio',
						command: 'node',
						args: [ fullProxyPath ],
						env: {
							WP_URL: siteUrl,
							WP_USERNAME: rawUsername,
							WP_APP_PASSWORD: rawAppPassword,
							MCP_PROTOCOL_VERSION: '2024-11-05',
							MCP_LOG_FILE: logFilePath
						}
					}
				}
			};
			setConfigBlock(
				'elementor-mcp-claude-code-proxy-code',
				'claude-code-proxy',
				JSON.stringify( claudeCodeProxyConfig, null, 4 )
			);

			// Claude Desktop proxy config — same but without type field.
			var claudeDesktopProxyConfig = {
				mcpServers: {
					'elementor-mcp': {
						command: 'node',
						args: [ fullProxyPath ],
						env: {
							WP_URL: siteUrl,
							WP_USERNAME: rawUsername,
							WP_APP_PASSWORD: rawAppPassword,
							MCP_PROTOCOL_VERSION: '2024-11-05',
							MCP_LOG_FILE: logFilePath
						}
					}
				}
			};
			setConfigBlock(
				'elementor-mcp-claude-desktop-proxy-code',
				'claude-desktop-proxy',
				JSON.stringify( claudeDesktopProxyConfig, null, 4 )
			);

			// Show the HTTP config blocks container.
			var configsDiv = document.getElementById( 'elementor-mcp-http-configs' );
			if ( configsDiv ) {
				configsDiv.style.display = '';
			}

			// Claude Code (.mcp.json) — uses type: http, url field.
			var claudeCodeConfig = {
				mcpServers: {
					'elementor-mcp': {
						type: 'http',
						url: endpoint,
						headers: {
							Authorization: headerValue
						}
					}
				}
			};
			setConfigBlock(
				'elementor-mcp-claude-code-http-code',
				'claude-code-http',
				JSON.stringify( claudeCodeConfig, null, 4 )
			);

			// Claude Desktop — same format as Claude Code.
			setConfigBlock(
				'elementor-mcp-claude-desktop-http-code',
				'claude-desktop-http',
				JSON.stringify( claudeCodeConfig, null, 4 )
			);

			// Cursor — uses url field, no type needed.
			var cursorConfig = {
				mcpServers: {
					'elementor-mcp': {
						url: endpoint,
						headers: {
							Authorization: headerValue
						}
					}
				}
			};
			setConfigBlock(
				'elementor-mcp-cursor-code',
				'cursor-config',
				JSON.stringify( cursorConfig, null, 4 )
			);

			// Windsurf — uses serverUrl field.
			var windsurfConfig = {
				mcpServers: {
					'elementor-mcp': {
						serverUrl: endpoint,
						headers: {
							Authorization: headerValue
						}
					}
				}
			};
			setConfigBlock(
				'elementor-mcp-windsurf-code',
				'windsurf-config',
				JSON.stringify( windsurfConfig, null, 4 )
			);

			// Antigravity — uses serverUrl field.
			setConfigBlock(
				'elementor-mcp-antigravity-code',
				'antigravity-config',
				JSON.stringify( windsurfConfig, null, 4 )
			);

			// Codex — uses TOML format with url and http_headers.
			var codexConfig = '[mcp_servers.elementor-mcp]\n' +
				'url = "' + endpoint + '"\n\n' +
				'[mcp_servers.elementor-mcp.http_headers]\n' +
				'"Authorization" = "' + headerValue + '"';
			setConfigBlock(
				'elementor-mcp-codex-code',
				'codex-config',
				codexConfig
			);

			// npx mcp-remote — bridges HTTP endpoint via stdio.
			var mcpRemoteConfig = {
				mcpServers: {
					'elementor-mcp': {
						command: 'npx',
						args: [
							'-y',
							'mcp-remote',
							endpoint,
							'--header',
							'Authorization: ' + headerValue
						]
					}
				}
			};
			setConfigBlock(
				'elementor-mcp-mcp-remote-code',
				'mcp-remote-config',
				JSON.stringify( mcpRemoteConfig, null, 4 )
			);
		} );
	}

	/**
	 * Copy text to clipboard with fallback for non-HTTPS contexts.
	 *
	 * @param {string} text The text to copy.
	 * @returns {Promise} Resolves when copied.
	 */
	function copyToClipboard( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}

		// Fallback for HTTP (non-secure) contexts.
		return new Promise( function ( resolve ) {
			var textarea = document.createElement( 'textarea' );
			textarea.value = text;
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild( textarea );
			textarea.select();
			document.execCommand( 'copy' );
			document.body.removeChild( textarea );
			resolve();
		} );
	}

	/**
	 * Connection tab — Copy to clipboard buttons.
	 */
	function initCopyButtons() {
		document.querySelectorAll( '.elementor-mcp-copy-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var targetId = this.getAttribute( 'data-target' );
				var source = document.getElementById( targetId );
				if ( ! source ) {
					return;
				}

				var copiedText = ( typeof elementorMcpAdmin !== 'undefined' && elementorMcpAdmin.copied ) ? elementorMcpAdmin.copied : 'Copied!';

				copyToClipboard( source.value ).then( function () {
					var original = btn.textContent;
					btn.textContent = copiedText;
					setTimeout( function () {
						btn.textContent = original;
					}, 2000 );
				} );
			} );
		} );
	}

	/**
	 * Premium prompts — category filter pills.
	 */
	function initProPromptFilters() {
		var filterBar = document.querySelector( '.elementor-mcp-pro-filters' );
		var grid = document.querySelector( '.elementor-mcp-pro-prompts-grid' );
		if ( ! filterBar || ! grid ) {
			return;
		}

		filterBar.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.elementor-mcp-pro-filter' );
			if ( ! btn ) {
				return;
			}
			var category = btn.getAttribute( 'data-category' );
			filterBar.querySelectorAll( '.elementor-mcp-pro-filter' ).forEach( function ( b ) {
				b.classList.toggle( 'is-active', b === btn );
			} );
			grid.querySelectorAll( '.elementor-mcp-pro-prompt-card' ).forEach( function ( card ) {
				var match = ( 'all' === category ) || card.getAttribute( 'data-category' ) === category;
				card.style.display = match ? '' : 'none';
			} );
		} );
	}

	/**
	 * Premium prompts — "Sync Library" button.
	 */
	function initProSync() {
		document.querySelectorAll( '.elementor-mcp-pro-sync-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( typeof elementorMcpAdmin === 'undefined' || ! elementorMcpAdmin.ajaxUrl ) {
					return;
				}
				var original = btn.innerHTML;
				btn.disabled = true;
				btn.innerHTML = '<span class="dashicons dashicons-update spin" aria-hidden="true"></span> ' + ( elementorMcpAdmin.syncing || 'Syncing…' );

				var body = new URLSearchParams();
				body.append( 'action', 'elementor_mcp_sync_pro_prompts' );
				body.append( 'nonce', btn.getAttribute( 'data-nonce' ) || '' );

				fetch( elementorMcpAdmin.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString(),
				} )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( res && res.success ) {
							window.location.reload();
						} else {
							var msg = ( res && res.data && res.data.message ) ? res.data.message : 'Sync failed.';
							window.alert( msg );
							btn.disabled = false;
							btn.innerHTML = original;
						}
					} )
					.catch( function () {
						window.alert( 'Sync failed. Check your connection and try again.' );
						btn.disabled = false;
						btn.innerHTML = original;
					} );
			} );
		} );
	}

	// Initialize on DOM ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initToolsForm();
			initBase64Generator();
			initCopyButtons();
			initProPromptFilters();
			initProSync();
		} );
	} else {
		initToolsForm();
		initBase64Generator();
		initCopyButtons();
		initProPromptFilters();
		initProSync();
	}
})();
