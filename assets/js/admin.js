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
			var usernameEl = document.getElementById( 'elementor-mcp-b64-username' );
			if ( ! usernameEl || ! usernameEl.value ) {
				/* global alert */
				alert( 'Please select an administrator account.' );
				return;
			}

			var selectedOption = usernameEl.options[ usernameEl.selectedIndex ];
			var selectedLogin = selectedOption ? ( selectedOption.getAttribute( 'data-login' ) || '' ) : '';
			var manualEl = document.getElementById( 'elementor-mcp-b64-app-password' );
			var manualPassword = manualEl ? manualEl.value.trim() : '';

			// If an existing password is supplied, use it directly and skip creation.
			if ( manualPassword ) {
				renderConfigs( selectedLogin, manualPassword );
				return;
			}

			if ( typeof elementorMcpAdmin === 'undefined' || ! elementorMcpAdmin.ajaxUrl || ! elementorMcpAdmin.createPwNonce ) {
				setCredStatus( 'Cannot create an application password automatically. Enter one manually below.', true );
				return;
			}

			var origLabel = generateBtn.textContent;
			generateBtn.disabled = true;
			generateBtn.textContent = elementorMcpAdmin.generating || 'Generating…';
			setCredStatus( '', false );

			var payload = new FormData();
			payload.append( 'action', 'elementor_mcp_create_app_password' );
			payload.append( 'nonce', elementorMcpAdmin.createPwNonce );
			payload.append( 'user_id', usernameEl.value );

			/* global fetch */
			fetch( elementorMcpAdmin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: payload
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( result ) {
				generateBtn.disabled = false;
				generateBtn.textContent = origLabel;

				if ( ! result || ! result.success || ! result.data || ! result.data.password ) {
					var message = ( result && result.data && result.data.message ) ? result.data.message : 'Could not create an application password.';
					setCredStatus( message, true );
					return;
				}

				setCredStatus( elementorMcpAdmin.pwCreated || 'Application password created — save it below, it is shown only once.', false );
				renderGeneratedPassword( result.data.password );
				renderConfigs( result.data.username, result.data.password );
			} ).catch( function () {
				generateBtn.disabled = false;
				generateBtn.textContent = origLabel;
				setCredStatus( 'Network error while creating the application password.', true );
			} );
		} );

		/**
		 * Shows an inline status message under the credential form.
		 *
		 * @param {string}  message  Message text ('' hides it).
		 * @param {boolean} isError  Whether to style it as an error.
		 */
		function setCredStatus( message, isError ) {
			var statusEl = document.getElementById( 'elementor-mcp-cred-status' );
			if ( ! statusEl ) {
				return;
			}
			statusEl.style.display = message ? '' : 'none';
			statusEl.textContent = message || '';
			statusEl.style.color = isError ? '#b32d2e' : '';
		}

		/**
		 * Reveals and fills the generated application password field.
		 *
		 * @param {string} password  The newly created application password.
		 */
		function renderGeneratedPassword( password ) {
			var row = document.getElementById( 'elementor-mcp-generated-pw-row' );
			var code = document.getElementById( 'elementor-mcp-generated-pw' );
			var copy = document.getElementById( 'elementor-mcp-generated-pw-copy' );
			if ( row && code && copy ) {
				row.style.display = '';
				code.textContent = password;
				copy.value = password;
			}
		}

		/**
		 * Builds every client config block from a username + application password.
		 *
		 * @param {string} rawUsername     WordPress username.
		 * @param {string} rawAppPassword  Application password.
		 */
		function renderConfigs( rawUsername, rawAppPassword ) {
			var headerValue = 'Basic ' + btoa( rawUsername + ':' + rawAppPassword );

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

			// Claude Code npx config (.mcp.json) — zero-install runner, best for remote sites.
			var claudeCodeNpxConfig = {
				mcpServers: {
					'elementor-mcp': {
						type: 'stdio',
						command: 'npx',
						args: [ '-y', '@msrbuilds/emcp-proxy@latest' ],
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
				'elementor-mcp-claude-code-npx-code',
				'claude-code-npx',
				JSON.stringify( claudeCodeNpxConfig, null, 4 )
			);

			// Claude Desktop npx config — same but without type field.
			var claudeDesktopNpxConfig = {
				mcpServers: {
					'elementor-mcp': {
						command: 'npx',
						args: [ '-y', '@msrbuilds/emcp-proxy@latest' ],
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
				'elementor-mcp-claude-desktop-npx-code',
				'claude-desktop-npx',
				JSON.stringify( claudeDesktopNpxConfig, null, 4 )
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
		}
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
	 * Copy-to-clipboard buttons (Connection tab + every prompt card).
	 *
	 * Single delegated listener on document — avoids attaching 50+ listeners on the
	 * Prompts page, which used to slow first paint and inflate memory.
	 */
	function initCopyButtons() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.elementor-mcp-copy-btn' );
			if ( ! btn ) {
				return;
			}
			var targetId = btn.getAttribute( 'data-target' );
			var source = targetId ? document.getElementById( targetId ) : null;
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
	}

	/**
	 * Reusable, filter-aware client-side pagination for a card grid.
	 *
	 * Owns BOTH the category filter pills and the pager, so the two stay in
	 * sync: changing the filter recomputes the matching set and resets to page
	 * one. Only the cards on the current page are shown; the rest are
	 * display:none, which keeps the DOM light and the page responsive even with
	 * 50+ cards. Safe to call on any page — it no-ops when the grid is absent.
	 *
	 * @param {Object} opts
	 * @param {string} opts.gridSelector   Selector for the grid container.
	 * @param {string} opts.cardSelector   Selector for cards within the grid.
	 * @param {string} [opts.filterSelector] Selector for the filter-pill bar.
	 * @param {number} [opts.pageSize]      Cards per page (default 12).
	 * @param {string} [opts.label]         Noun for the status line (e.g. 'prompts').
	 */
	function initGridPagination( opts ) {
		var grid = document.querySelector( opts.gridSelector );
		if ( ! grid ) {
			return;
		}
		var cards = Array.prototype.slice.call( grid.querySelectorAll( opts.cardSelector ) );
		if ( ! cards.length ) {
			return;
		}

		var pageSize = opts.pageSize || 12;
		var label = opts.label || 'items';
		var filterBar = opts.filterSelector ? document.querySelector( opts.filterSelector ) : null;
		var activeCategory = 'all';
		var currentPage = 1;

		// Pager container lives directly after the grid.
		var pager = document.createElement( 'nav' );
		pager.className = 'elementor-mcp-pager';
		pager.setAttribute( 'aria-label', 'Pagination' );
		grid.parentNode.insertBefore( pager, grid.nextSibling );

		function matching() {
			return cards.filter( function ( card ) {
				return 'all' === activeCategory || card.getAttribute( 'data-category' ) === activeCategory;
			} );
		}

		function makeBtn( text, page, opt ) {
			opt = opt || {};
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'elementor-mcp-pager-btn' + ( opt.current ? ' is-current' : '' );
			btn.textContent = text;
			if ( opt.disabled ) {
				btn.disabled = true;
			}
			if ( opt.current ) {
				btn.setAttribute( 'aria-current', 'page' );
			}
			if ( opt.ariaLabel ) {
				btn.setAttribute( 'aria-label', opt.ariaLabel );
			}
			if ( ! opt.disabled && ! opt.current ) {
				btn.addEventListener( 'click', function () {
					currentPage = page;
					render();
				} );
			}
			return btn;
		}

		// Windowed page list with ellipses: 1 … 4 5 [6] 7 8 … 20.
		function pageList( total ) {
			var pages = [];
			var add = function ( p ) { if ( pages.indexOf( p ) === -1 ) { pages.push( p ); } };
			add( 1 );
			add( total );
			for ( var p = currentPage - 1; p <= currentPage + 1; p++ ) {
				if ( p >= 1 && p <= total ) {
					add( p );
				}
			}
			pages.sort( function ( a, b ) { return a - b; } );
			var withGaps = [];
			for ( var i = 0; i < pages.length; i++ ) {
				if ( i > 0 && pages[ i ] - pages[ i - 1 ] > 1 ) {
					withGaps.push( '…' );
				}
				withGaps.push( pages[ i ] );
			}
			return withGaps;
		}

		function render() {
			var list = matching();
			var totalPages = Math.max( 1, Math.ceil( list.length / pageSize ) );
			if ( currentPage > totalPages ) {
				currentPage = totalPages;
			}
			var start = ( currentPage - 1 ) * pageSize;
			var end = start + pageSize;

			cards.forEach( function ( card ) { card.style.display = 'none'; } );
			list.slice( start, end ).forEach( function ( card ) { card.style.display = ''; } );

			pager.innerHTML = '';
			if ( totalPages <= 1 ) {
				return;
			}

			pager.appendChild( makeBtn( '‹', currentPage - 1, {
				disabled: currentPage === 1,
				ariaLabel: 'Previous page'
			} ) );

			pageList( totalPages ).forEach( function ( item ) {
				if ( '…' === item ) {
					var span = document.createElement( 'span' );
					span.className = 'elementor-mcp-pager-ellipsis';
					span.textContent = '…';
					pager.appendChild( span );
				} else {
					pager.appendChild( makeBtn( String( item ), item, {
						current: item === currentPage,
						ariaLabel: 'Page ' + item
					} ) );
				}
			} );

			pager.appendChild( makeBtn( '›', currentPage + 1, {
				disabled: currentPage === totalPages,
				ariaLabel: 'Next page'
			} ) );

			var status = document.createElement( 'p' );
			status.className = 'elementor-mcp-pager-status';
			status.textContent = 'Showing ' + ( start + 1 ) + '–' + Math.min( end, list.length ) +
				' of ' + list.length + ' ' + label;
			pager.appendChild( status );
		}

		// Own the category filter pills (active state + reset to page 1).
		if ( filterBar ) {
			filterBar.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '.elementor-mcp-pro-filter' );
				if ( ! btn ) {
					return;
				}
				activeCategory = btn.getAttribute( 'data-category' ) || 'all';
				filterBar.querySelectorAll( '.elementor-mcp-pro-filter' ).forEach( function ( b ) {
					b.classList.toggle( 'is-active', b === btn );
				} );
				currentPage = 1;
				render();
			} );
		}

		render();
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

				// Action override via data-sync-action lets the same button
				// pattern work for prompts and templates. Falls back to the
				// prompts action for backwards compat with the existing UI.
				var action = btn.getAttribute( 'data-sync-action' ) || 'elementor_mcp_sync_pro_prompts';
				var body = new URLSearchParams();
				body.append( 'action', action );
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

	/**
	 * Templates page — Apply-to-New-Page and Import-to-Library buttons.
	 * Single delegated click handler routes to whichever AJAX action the
	 * button is configured for. Both open the result in a new tab on success.
	 */
	function initProTemplateActions() {
		var grid = document.querySelector( '.elementor-mcp-template-grid' );
		if ( ! grid ) {
			return;
		}
		var applyNonce  = grid.getAttribute( 'data-apply-nonce' )  || '';
		var importNonce = grid.getAttribute( 'data-import-nonce' ) || '';

		grid.addEventListener( 'click', function ( e ) {
			var applyBtn  = e.target.closest( '.elementor-mcp-template-apply' );
			var importBtn = e.target.closest( '.elementor-mcp-template-import' );
			var btn = applyBtn || importBtn;
			if ( ! btn ) {
				return;
			}
			if ( typeof elementorMcpAdmin === 'undefined' || ! elementorMcpAdmin.ajaxUrl ) {
				return;
			}

			var isApply = !! applyBtn;
			var action  = isApply ? 'elementor_mcp_apply_pro_template' : 'elementor_mcp_import_pro_template';
			var nonce   = isApply ? applyNonce : importNonce;
			var pending = isApply ? 'Creating…' : 'Importing…';
			var failMsg = isApply ? 'Create failed.' : 'Import failed.';

			var category = btn.getAttribute( 'data-category-slug' ) || '';
			var template = btn.getAttribute( 'data-template-slug' ) || '';
			var original = btn.innerHTML;
			btn.disabled = true;
			btn.textContent = pending;

			var body = new URLSearchParams();
			body.append( 'action', action );
			body.append( 'nonce', nonce );
			body.append( 'category_slug', category );
			body.append( 'template_slug', template );
			if ( isApply ) {
				body.append( 'target_post_id', '0' );
			}

			fetch( elementorMcpAdmin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					var ok = res && res.success && res.data;
					// Apply → open the new page's Elementor editor.
					// Import → open the Saved Templates library list so the
					// user can see the new entry.
					var openUrl = ok ? ( isApply ? res.data.edit_url : res.data.library_url ) : '';
					if ( openUrl ) {
						window.open( openUrl, '_blank', 'noopener' );
						btn.disabled = false;
						btn.innerHTML = original;
					} else {
						var msg = ( res && res.data && res.data.message ) ? res.data.message : failMsg;
						window.alert( msg );
						btn.disabled = false;
						btn.innerHTML = original;
					}
				} )
				.catch( function () {
					window.alert( failMsg + ' Check your connection and try again.' );
					btn.disabled = false;
					btn.innerHTML = original;
				} );
		} );
	}

	/**
	 * Brand Kits page — transient success toast with an optional "View site" link.
	 *
	 * @param {string} message The toast message.
	 * @param {string} viewUrl Optional URL to surface as a "View site →" link.
	 */
	function showBrandKitToast( message, viewUrl ) {
		var toast = document.createElement( 'div' );
		toast.className = 'elementor-mcp-bk-toast';
		var span = document.createElement( 'span' );
		span.textContent = message;
		toast.appendChild( span );
		if ( viewUrl ) {
			var link = document.createElement( 'a' );
			link.href = viewUrl;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.textContent = ( typeof elementorMcpAdmin !== 'undefined' && elementorMcpAdmin.viewSite ) ? elementorMcpAdmin.viewSite : 'View site →';
			toast.appendChild( link );
		}
		document.body.appendChild( toast );
		// Force reflow then animate in.
		window.requestAnimationFrame( function () {
			toast.classList.add( 'is-visible' );
		} );
		setTimeout( function () {
			toast.classList.remove( 'is-visible' );
			setTimeout( function () { toast.remove(); }, 400 );
		}, 7000 );
	}

	/**
	 * Brand Kits page — category filters, apply-with-confirmation modal, and
	 * restore-from-backup.
	 */
	function initBrandKits() {
		var root = document.querySelector( '.elementor-mcp-brand-kits' );
		if ( ! root || typeof elementorMcpAdmin === 'undefined' || ! elementorMcpAdmin.ajaxUrl ) {
			return;
		}

		var grid = root.querySelector( '.elementor-mcp-brand-kit-grid' );

		// Note: the category filter pills are handled by initGridPagination(),
		// which owns both filtering and pagination so they stay in sync.

		// Apply confirmation modal.
		var modal = root.querySelector( '.elementor-mcp-brand-kit-modal' );
		var pending = null;

		function closeModal() {
			if ( modal ) {
				modal.hidden = true;
			}
			pending = null;
		}

		if ( grid && modal ) {
			grid.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '.elementor-mcp-brand-kit-apply' );
				if ( ! btn ) {
					return;
				}
				pending = {
					slug:  btn.getAttribute( 'data-kit-slug' ) || '',
					cat:   btn.getAttribute( 'data-category-slug' ) || '',
					title: btn.getAttribute( 'data-kit-title' ) || ''
				};
				var titleEl = modal.querySelector( '.elementor-mcp-brand-kit-modal__title' );
				if ( titleEl ) {
					var tpl = ( elementorMcpAdmin.applyKitTitle || 'Apply "%s" brand kit?' );
					titleEl.textContent = tpl.replace( '%s', pending.title );
				}
				var bk = modal.querySelector( '.elementor-mcp-brand-kit-modal__backup-input' );
				if ( bk ) {
					bk.checked = true;
				}
				modal.hidden = false;
			} );

			modal.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '[data-modal-dismiss]' ) ) {
					closeModal();
					return;
				}
				var confirmBtn = e.target.closest( '.elementor-mcp-brand-kit-modal__confirm' );
				if ( ! confirmBtn || ! pending ) {
					return;
				}

				var backup = modal.querySelector( '.elementor-mcp-brand-kit-modal__backup-input' );
				var doBackup = backup ? backup.checked : true;
				var title = pending.title;
				var orig = confirmBtn.textContent;
				confirmBtn.disabled = true;
				confirmBtn.textContent = elementorMcpAdmin.applying || 'Applying…';

				var body = new URLSearchParams();
				body.append( 'action', 'elementor_mcp_apply_pro_brand_kit' );
				body.append( 'nonce', grid.getAttribute( 'data-apply-nonce' ) || '' );
				body.append( 'kit_slug', pending.slug );
				body.append( 'category_slug', pending.cat );
				body.append( 'backup', doBackup ? '1' : '0' );

				fetch( elementorMcpAdmin.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				} )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						confirmBtn.disabled = false;
						confirmBtn.textContent = orig;
						if ( res && res.success ) {
							closeModal();
							var applied = ( elementorMcpAdmin.kitApplied || '%s applied.' ).replace( '%s', title );
							showBrandKitToast( applied, res.data && res.data.view_url );
						} else {
							var msg = ( res && res.data && res.data.message ) ? res.data.message : 'Apply failed.';
							window.alert( msg );
						}
					} )
					.catch( function () {
						confirmBtn.disabled = false;
						confirmBtn.textContent = orig;
						window.alert( 'Apply failed. Check your connection and try again.' );
					} );
			} );
		}

		// Restore from backup.
		var restore = root.querySelector( '.elementor-mcp-brand-kit-restore' );
		if ( restore ) {
			var restoreBtn = restore.querySelector( '.elementor-mcp-brand-kit-restore-btn' );
			if ( restoreBtn ) {
				restoreBtn.addEventListener( 'click', function () {
					var select = restore.querySelector( '.elementor-mcp-brand-kit-backup-select' );
					var clobber = restore.querySelector( '.elementor-mcp-brand-kit-clobber-input' );
					if ( ! select || ! select.value ) {
						return;
					}
					if ( ! window.confirm( elementorMcpAdmin.restoreConfirm || 'Restore global colors and typography from this backup?' ) ) {
						return;
					}
					var orig = restoreBtn.textContent;
					restoreBtn.disabled = true;
					restoreBtn.textContent = elementorMcpAdmin.restoring || 'Restoring…';

					var body = new URLSearchParams();
					body.append( 'action', 'elementor_mcp_restore_pro_brand_kit' );
					body.append( 'nonce', restore.getAttribute( 'data-restore-nonce' ) || '' );
					body.append( 'backup_id', select.value );
					body.append( 'full_clobber', ( clobber && clobber.checked ) ? '1' : '0' );

					fetch( elementorMcpAdmin.ajaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: body.toString()
					} )
						.then( function ( r ) { return r.json(); } )
						.then( function ( res ) {
							restoreBtn.disabled = false;
							restoreBtn.textContent = orig;
							if ( res && res.success ) {
								var msg = ( res.data && res.data.message ) ? res.data.message : 'Restored.';
								showBrandKitToast( msg, res.data && res.data.view_url );
							} else {
								var emsg = ( res && res.data && res.data.message ) ? res.data.message : 'Restore failed.';
								window.alert( emsg );
							}
						} )
						.catch( function () {
							restoreBtn.disabled = false;
							restoreBtn.textContent = orig;
							window.alert( 'Restore failed. Check your connection and try again.' );
						} );
				} );
			}
		}
	}

	/**
	 * Wire pagination (and the category filter it owns) into each library grid.
	 * Each call no-ops when its grid isn't on the current page, so it's safe to
	 * run all three regardless of which tab rendered.
	 */
	function initPagers() {
		initGridPagination( {
			gridSelector: '.elementor-mcp-pro-prompts-grid',
			cardSelector: '.elementor-mcp-pro-prompt-card',
			filterSelector: '.elementor-mcp-pro-prompts .elementor-mcp-pro-filters',
			pageSize: 12,
			label: 'prompts'
		} );
		initGridPagination( {
			gridSelector: '.elementor-mcp-template-grid',
			cardSelector: '.elementor-mcp-template-card',
			filterSelector: '.elementor-mcp-templates .elementor-mcp-pro-filters',
			pageSize: 12,
			label: 'templates'
		} );
		initGridPagination( {
			gridSelector: '.elementor-mcp-brand-kit-grid',
			cardSelector: '.elementor-mcp-brand-kit-card',
			filterSelector: '.elementor-mcp-brand-kits .elementor-mcp-pro-filters',
			pageSize: 12,
			label: 'brand kits'
		} );
		initGridPagination( {
			gridSelector: '.elementor-mcp-changelog-list',
			cardSelector: '.elementor-mcp-changelog-version',
			pageSize: 10,
			label: 'releases'
		} );
	}

	// Initialize on DOM ready.
	function initAll() {
		initToolsForm();
		initBase64Generator();
		initCopyButtons();
		initPagers();
		initProSync();
		initProTemplateActions();
		initBrandKits();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
})();
