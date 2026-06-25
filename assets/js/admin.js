/**
 * MCP Tools for Elementor — Admin Settings Scripts
 *
 * @package EMCP_Tools
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

		// Scope bulk actions to the per-tool checkboxes only — NOT the separate
		// low-tools-mode toggle, which also lives in this form. (A bare
		// form.querySelectorAll('input[type="checkbox"]') would flip low-tools
		// mode too, silently overriding every individual toggle.)
		var toolCheckboxSelector = '.elementor-mcp-tool-card input[type="checkbox"]';

		if ( enableAll ) {
			enableAll.addEventListener( 'click', function () {
				form.querySelectorAll( toolCheckboxSelector ).forEach( function ( cb ) {
					cb.checked = true;
				} );
				updateCards( form );
			} );
		}

		if ( disableAll ) {
			disableAll.addEventListener( 'click', function () {
				form.querySelectorAll( toolCheckboxSelector ).forEach( function ( cb ) {
					cb.checked = false;
				} );
				updateCards( form );
			} );
		}

		// Per-category enable/disable + collapsible section headers.
		// (cat scopes to .elementor-mcp-category, which never contains the
		// low-tools-mode toggle, so the bulk selects below are safe.)
		var COLLAPSE_KEY = 'emcpToolsCollapsed:';
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

			// Collapse/expand the section, persisting state per category.
			var toggle = cat.querySelector( '.elementor-mcp-category-toggle' );
			if ( toggle ) {
				var key = COLLAPSE_KEY + ( cat.getAttribute( 'data-category' ) || '' );
				var stored = null;
				try {
					stored = window.localStorage.getItem( key );
				} catch ( e ) {}
				if ( '1' === stored ) {
					cat.classList.add( 'is-collapsed' );
					toggle.setAttribute( 'aria-expanded', 'false' );
				}
				toggle.addEventListener( 'click', function () {
					var collapsed = cat.classList.toggle( 'is-collapsed' );
					toggle.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
					try {
						window.localStorage.setItem( key, collapsed ? '1' : '0' );
					} catch ( e ) {}
				} );
			}
		} );

		// Toggle card visual state on checkbox change.
		form.addEventListener( 'change', function ( e ) {
			if ( e.target.type === 'checkbox' ) {
				updateCards( form );
			}
		} );

		// Low-tools mode — live preview without a save round-trip: pause/grey the
		// grid and show the effective (essentials-only) state, then restore the
		// stored toggles when switched back off. The stored + essential state of
		// each tool is carried in data-* attributes rendered server-side.
		var lowToggle = form.querySelector( 'input[type="checkbox"][value="1"][name$="low_tool_mode"]' );
		if ( lowToggle ) {
			lowToggle.addEventListener( 'change', function () {
				var on = lowToggle.checked;
				form.classList.toggle( 'is-low-mode', on );
				form.querySelectorAll( toolCheckboxSelector ).forEach( function ( cb ) {
					if ( on ) {
						cb.checked = ( '1' === cb.dataset.essential );
						cb.disabled = true;
					} else {
						cb.checked = ( '1' === cb.dataset.storedEnabled );
						cb.disabled = false;
					}
				} );
				updateCards( form );
				updateToolCounts( form );
			} );
		}
	}

	/**
	 * Recompute the summary + per-category counts from the live checkbox state.
	 *
	 * @param {HTMLElement} form The tools form.
	 */
	function updateToolCounts( form ) {
		var enabled = 0;
		form.querySelectorAll( '.elementor-mcp-tool-card input[type="checkbox"]' ).forEach( function ( cb ) {
			if ( cb.checked ) {
				enabled++;
			}
		} );
		var strong = form.querySelector( '.elementor-mcp-tools-summary strong' );
		if ( strong ) {
			// Replace just the leading "enabled" number, keeping the localized
			// "N of M" wording intact.
			strong.textContent = strong.textContent.replace( /^\s*\d+/, enabled );
		}
		form.querySelectorAll( '.elementor-mcp-category' ).forEach( function ( cat ) {
			var cbs = cat.querySelectorAll( 'input[type="checkbox"]' );
			var ce = 0;
			cbs.forEach( function ( cb ) {
				if ( cb.checked ) {
					ce++;
				}
			} );
			var el = cat.querySelector( '.elementor-mcp-category-count' );
			if ( el ) {
				el.textContent = ce + ' / ' + cbs.length;
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

	// Tools-page platform sub-tabs (Elementor / WordPress). Presentation only —
	// hidden panels keep their checkboxes in the form, so switching tabs never
	// affects what gets saved.
	( function initToolSubtabs() {
		var tabs = document.querySelectorAll( '.elementor-mcp-subtab' );
		var panels = document.querySelectorAll( '.elementor-mcp-tabpanel' );
		if ( ! tabs.length || ! panels.length ) {
			return;
		}
		var STORAGE_KEY = 'emcpToolsActiveTab';

		function activate( tabId ) {
			var matched = false;
			panels.forEach( function ( panel ) {
				var on = panel.getAttribute( 'data-tab' ) === tabId;
				panel.classList.toggle( 'is-active', on );
				if ( on ) { matched = true; }
			} );
			if ( ! matched ) {
				return; // unknown stored id (e.g. a removed tab) — leave server default.
			}
			tabs.forEach( function ( tab ) {
				var on = tab.getAttribute( 'data-tab' ) === tabId;
				tab.classList.toggle( 'is-active', on );
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
			try { window.localStorage.setItem( STORAGE_KEY, tabId ); } catch ( e ) {}
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				activate( tab.getAttribute( 'data-tab' ) );
			} );
		} );

		// Restore the last-used tab (falls back to the server-rendered active one).
		var stored = null;
		try { stored = window.localStorage.getItem( STORAGE_KEY ); } catch ( e ) {}
		if ( stored ) {
			activate( stored );
		}
	} )();

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

		// The Basic Authorization header from the last generated credentials,
		// used by the auth self-test (#41).
		var emcpAuthHeader = '';

		// Connection auth self-test (#41): proves whether the Authorization
		// header actually reaches WordPress. Servers like Plesk/Apache/IIS often
		// strip it, which is the usual cause of the MCP "initialize: Unauthorized"
		// error. credentials:'omit' ensures ONLY the Authorization header
		// authenticates (not the admin login cookie), so a 401 here is a true
		// Basic-auth failure, not a false pass.
		var authBtn = document.getElementById( 'elementor-mcp-authtest-btn' );
		if ( authBtn ) {
			authBtn.addEventListener( 'click', function () {
				if ( ! emcpAuthHeader || typeof emcpToolsAdmin === 'undefined' || ! emcpToolsAdmin.restMeUrl ) {
					return;
				}
				var statusEl = document.getElementById( 'elementor-mcp-authtest-status' );
				var fixEl = document.getElementById( 'elementor-mcp-authtest-fix' );
				if ( statusEl ) {
					statusEl.style.display = '';
					statusEl.className = 'description';
					statusEl.textContent = emcpToolsAdmin.authTesting || 'Testing…';
				}
				if ( fixEl ) {
					fixEl.style.display = 'none';
				}
				authBtn.disabled = true;

				/* global fetch */
				fetch( emcpToolsAdmin.restMeUrl + '?_=' + ( new Date() ).getTime(), {
					method: 'GET',
					credentials: 'omit',
					headers: { Authorization: emcpAuthHeader }
				} ).then( function ( response ) {
					authBtn.disabled = false;
					if ( ! statusEl ) {
						return;
					}
					if ( response.ok ) {
						statusEl.className = 'description elementor-mcp-authtest-ok';
						statusEl.textContent = emcpToolsAdmin.authOk || 'Authentication works.';
						if ( fixEl ) {
							fixEl.style.display = 'none';
						}
					} else {
						statusEl.className = 'description elementor-mcp-authtest-bad';
						statusEl.textContent = ( emcpToolsAdmin.authFail || 'Authentication failed (HTTP %d).' ).replace( '%d', response.status );
						if ( fixEl ) {
							fixEl.style.display = '';
						}
					}
				} ).catch( function () {
					authBtn.disabled = false;
					if ( statusEl ) {
						statusEl.className = 'description elementor-mcp-authtest-bad';
						statusEl.textContent = emcpToolsAdmin.authError || 'Could not reach the REST API.';
					}
					if ( fixEl ) {
						fixEl.style.display = '';
					}
				} );
			} );
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

			if ( typeof emcpToolsAdmin === 'undefined' || ! emcpToolsAdmin.ajaxUrl || ! emcpToolsAdmin.createPwNonce ) {
				setCredStatus( 'Cannot create an application password automatically. Enter one manually below.', true );
				return;
			}

			var origLabel = generateBtn.textContent;
			generateBtn.disabled = true;
			generateBtn.textContent = emcpToolsAdmin.generating || 'Generating…';
			setCredStatus( '', false );

			var payload = new FormData();
			payload.append( 'action', 'emcp_tools_create_app_password' );
			payload.append( 'nonce', emcpToolsAdmin.createPwNonce );
			payload.append( 'user_id', usernameEl.value );

			/* global fetch */
			fetch( emcpToolsAdmin.ajaxUrl, {
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

				setCredStatus( emcpToolsAdmin.pwCreated || 'Application password created — save it below, it is shown only once.', false );
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

			// Arm the auth self-test (#41) with these credentials.
			emcpAuthHeader = headerValue;
			var authRow = document.getElementById( 'elementor-mcp-authtest-row' );
			if ( authRow ) {
				authRow.style.display = '';
			}

			if ( typeof emcpToolsAdmin === 'undefined' || ! emcpToolsAdmin.mcpEndpoint ) {
				return;
			}

			var endpoint = emcpToolsAdmin.mcpEndpoint;
			var siteUrl = emcpToolsAdmin.siteUrl || '';
			var proxyPath = emcpToolsAdmin.proxyPath || '';

			// Show the proxy config blocks container.
			var proxyConfigsDiv = document.getElementById( 'elementor-mcp-proxy-configs' );
			if ( proxyConfigsDiv ) {
				proxyConfigsDiv.style.display = '';
			}

			// The proxy filename only — the user supplies its full local path (or
			// uses the npx config below). We never receive the server path (F-020).
			var fullProxyPath = proxyPath;

			// Determine a sensible debug-log path from the admin's browser OS (the
			// proxy value is just a filename now, so we can't infer OS from it).
			var isWindows = /win/i.test( navigator.platform || navigator.userAgent || '' );
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

			var copiedText = ( typeof emcpToolsAdmin !== 'undefined' && emcpToolsAdmin.copied ) ? emcpToolsAdmin.copied : 'Copied!';

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
				if ( typeof emcpToolsAdmin === 'undefined' || ! emcpToolsAdmin.ajaxUrl ) {
					return;
				}
				var original = btn.innerHTML;
				btn.disabled = true;
				btn.innerHTML = '<span class="dashicons dashicons-update spin" aria-hidden="true"></span> ' + ( emcpToolsAdmin.syncing || 'Syncing…' );

				// Action override via data-sync-action lets the same button
				// pattern work for prompts and templates. Falls back to the
				// prompts action for backwards compat with the existing UI.
				var action = btn.getAttribute( 'data-sync-action' ) || 'emcp_tools_sync_pro_prompts';
				var body = new URLSearchParams();
				body.append( 'action', action );
				body.append( 'nonce', btn.getAttribute( 'data-nonce' ) || '' );

				fetch( emcpToolsAdmin.ajaxUrl, {
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
			if ( typeof emcpToolsAdmin === 'undefined' || ! emcpToolsAdmin.ajaxUrl ) {
				return;
			}

			var isApply = !! applyBtn;
			var action  = isApply ? 'emcp_tools_apply_pro_template' : 'emcp_tools_import_pro_template';
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

			fetch( emcpToolsAdmin.ajaxUrl, {
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
			link.textContent = ( typeof emcpToolsAdmin !== 'undefined' && emcpToolsAdmin.viewSite ) ? emcpToolsAdmin.viewSite : 'View site →';
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
		if ( ! root || typeof emcpToolsAdmin === 'undefined' || ! emcpToolsAdmin.ajaxUrl ) {
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
					var tpl = ( emcpToolsAdmin.applyKitTitle || 'Apply "%s" brand kit?' );
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
				confirmBtn.textContent = emcpToolsAdmin.applying || 'Applying…';

				var body = new URLSearchParams();
				body.append( 'action', 'emcp_tools_apply_pro_brand_kit' );
				body.append( 'nonce', grid.getAttribute( 'data-apply-nonce' ) || '' );
				body.append( 'kit_slug', pending.slug );
				body.append( 'category_slug', pending.cat );
				body.append( 'backup', doBackup ? '1' : '0' );

				fetch( emcpToolsAdmin.ajaxUrl, {
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
							var applied = ( emcpToolsAdmin.kitApplied || '%s applied.' ).replace( '%s', title );
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
					if ( ! window.confirm( emcpToolsAdmin.restoreConfirm || 'Restore global colors and typography from this backup?' ) ) {
						return;
					}
					var orig = restoreBtn.textContent;
					restoreBtn.disabled = true;
					restoreBtn.textContent = emcpToolsAdmin.restoring || 'Restoring…';

					var body = new URLSearchParams();
					body.append( 'action', 'emcp_tools_restore_pro_brand_kit' );
					body.append( 'nonce', restore.getAttribute( 'data-restore-nonce' ) || '' );
					body.append( 'backup_id', select.value );
					body.append( 'full_clobber', ( clobber && clobber.checked ) ? '1' : '0' );

					fetch( emcpToolsAdmin.ajaxUrl, {
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

	/**
	 * Slide-in code viewer overlay. Any element with [data-emcp-code-view] opens
	 * it; the code is read from the nearest .emcp-code-src in the same table cell
	 * (or a selector in the attribute's value). Provides Copy + Download.
	 */
	function initCodeOverlay() {
		if ( document.getElementById( 'emcp-code-overlay' ) ) {
			return;
		}
		var overlay = document.createElement( 'div' );
		overlay.id = 'emcp-code-overlay';
		overlay.className = 'emcp-code-overlay';
		overlay.innerHTML =
			'<div class="emcp-code-overlay__backdrop" data-emcp-close></div>' +
			'<div class="emcp-code-overlay__panel" role="dialog" aria-modal="true" aria-label="Code viewer">' +
				'<div class="emcp-code-overlay__header">' +
					'<span class="emcp-code-overlay__title"></span>' +
					'<button type="button" class="emcp-code-overlay__close" data-emcp-close aria-label="Close">&times;</button>' +
				'</div>' +
				'<div class="emcp-code-overlay__toolbar">' +
					'<button type="button" class="emcp-code-overlay__btn" data-emcp-copy></button>' +
					'<button type="button" class="emcp-code-overlay__btn" data-emcp-download></button>' +
				'</div>' +
				'<pre class="emcp-code-overlay__body"><code></code></pre>' +
			'</div>';
		document.body.appendChild( overlay );

		var titleEl = overlay.querySelector( '.emcp-code-overlay__title' );
		var codeEl  = overlay.querySelector( '.emcp-code-overlay__body code' );
		var copyBtn = overlay.querySelector( '[data-emcp-copy]' );
		var dlBtn   = overlay.querySelector( '[data-emcp-download]' );
		copyBtn.textContent = window.emcpToolsAdmin && window.emcpToolsAdmin.copy ? window.emcpToolsAdmin.copy : 'Copy';
		dlBtn.textContent   = window.emcpToolsAdmin && window.emcpToolsAdmin.download ? window.emcpToolsAdmin.download : 'Download';
		var filename = 'code.txt';

		function open( title, code, fname ) {
			titleEl.textContent = title || 'Code';
			codeEl.textContent = code || '';
			filename = fname || 'code.txt';
			copyBtn.textContent = window.emcpToolsAdmin && window.emcpToolsAdmin.copy ? window.emcpToolsAdmin.copy : 'Copy';
			overlay.classList.add( 'is-open' );
			document.body.style.overflow = 'hidden';
		}
		function close() {
			overlay.classList.remove( 'is-open' );
			document.body.style.overflow = '';
		}

		// Open from any trigger.
		document.addEventListener( 'click', function ( e ) {
			var trigger = e.target.closest( '[data-emcp-code-view]' );
			if ( ! trigger ) { return; }
			e.preventDefault();
			var sel = trigger.getAttribute( 'data-emcp-code-view' );
			var src = null;
			if ( sel ) { src = document.querySelector( sel ); }
			if ( ! src ) {
				var scope = trigger.closest( 'td' ) || trigger.parentNode;
				src = scope ? scope.querySelector( '.emcp-code-src' ) : null;
			}
			open(
				trigger.getAttribute( 'data-emcp-code-title' ) || 'Code',
				src ? src.textContent : '',
				trigger.getAttribute( 'data-emcp-code-filename' ) || 'code.txt'
			);
		} );

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-emcp-close]' ) ) { close(); }
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && overlay.classList.contains( 'is-open' ) ) { close(); }
		} );

		copyBtn.addEventListener( 'click', function () {
			var text = codeEl.textContent || '';
			var done = function () {
				copyBtn.textContent = window.emcpToolsAdmin && window.emcpToolsAdmin.copied ? window.emcpToolsAdmin.copied : 'Copied!';
				setTimeout( function () { copyBtn.textContent = window.emcpToolsAdmin && window.emcpToolsAdmin.copy ? window.emcpToolsAdmin.copy : 'Copy'; }, 1500 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( done ).catch( function () { fallbackCopy( text, codeEl ); done(); } );
			} else {
				fallbackCopy( text, codeEl );
				done();
			}
		} );

		dlBtn.addEventListener( 'click', function () {
			var blob = new Blob( [ codeEl.textContent || '' ], { type: 'text/plain' } );
			var url  = URL.createObjectURL( blob );
			var a    = document.createElement( 'a' );
			a.href = url;
			a.download = filename;
			document.body.appendChild( a );
			a.click();
			document.body.removeChild( a );
			setTimeout( function () { URL.revokeObjectURL( url ); }, 1000 );
		} );
	}

	/**
	 * Clipboard fallback (older browsers / insecure context): select the code
	 * node and execCommand('copy').
	 *
	 * @param {string}      text Text to copy.
	 * @param {HTMLElement} node Element whose text can be range-selected.
	 */
	function fallbackCopy( text, node ) {
		try {
			var range = document.createRange();
			range.selectNodeContents( node );
			var sel = window.getSelection();
			sel.removeAllRanges();
			sel.addRange( range );
			document.execCommand( 'copy' );
			sel.removeAllRanges();
		} catch ( e ) {}
	}

	/**
	 * Copy text to the clipboard (Clipboard API with an execCommand fallback for
	 * older browsers / insecure contexts). Returns a Promise that always resolves.
	 *
	 * @param {string} text Text to copy.
	 * @return {Promise}
	 */
	function emcpCopyText( text ) {
		return new Promise( function ( resolve ) {
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( resolve ).catch( function () {
					emcpExecCopy( text );
					resolve();
				} );
			} else {
				emcpExecCopy( text );
				resolve();
			}
		} );
	}

	function emcpExecCopy( text ) {
		try {
			var ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild( ta );
			ta.select();
			document.execCommand( 'copy' );
			document.body.removeChild( ta );
		} catch ( e ) {}
	}

	/**
	 * Click-to-copy for any [data-emcp-copy-text] element (e.g. a shortcode
	 * chip). Copies the attribute value (or the element text) and flashes a
	 * "Copied!" tooltip via the .is-copied class.
	 */
	function initClickToCopy() {
		document.addEventListener( 'click', function ( e ) {
			var el = e.target.closest( '[data-emcp-copy-text]' );
			if ( ! el ) { return; }
			var text = el.getAttribute( 'data-emcp-copy-text' ) || el.textContent || '';
			emcpCopyText( text ).then( function () {
				el.classList.add( 'is-copied' );
				clearTimeout( el._emcpCopiedTimer );
				el._emcpCopiedTimer = setTimeout( function () { el.classList.remove( 'is-copied' ); }, 1200 );
			} );
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( 'Enter' === e.key || ' ' === e.key ) && e.target && e.target.matches && e.target.matches( '[data-emcp-copy-text]' ) ) {
				e.preventDefault();
				e.target.click();
			}
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
		initCodeOverlay();
		initClickToCopy();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
})();
