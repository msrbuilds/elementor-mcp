/**
 * Elementor MCP — Admin Settings Scripts
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

			// Show the config blocks container.
			var configsDiv = document.getElementById( 'elementor-mcp-http-configs' );
			if ( configsDiv ) {
				configsDiv.style.display = '';
			}

			// Claude Code (.mcp.json) — uses type: http.
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

			// Claude Desktop — uses stdio proxy with env vars.
			var claudeDesktopConfig = {
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
				'elementor-mcp-claude-desktop-http-code',
				'claude-desktop-http',
				JSON.stringify( claudeDesktopConfig, null, 4 )
			);

			// VS Code / Cursor.
			var vscodeConfig = {
				servers: {
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
				'elementor-mcp-vscode-code',
				'vscode-config',
				JSON.stringify( vscodeConfig, null, 4 )
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

	// Initialize on DOM ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initToolsForm();
			initBase64Generator();
			initCopyButtons();
		} );
	} else {
		initToolsForm();
		initBase64Generator();
		initCopyButtons();
	}
})();
