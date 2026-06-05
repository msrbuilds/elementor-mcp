/**
 * EMCP Tools — in-plugin AI Chat client (multi-provider, saved conversations).
 *
 * Anthropic (Messages API) + OpenAI-compatible providers (OpenAI, OpenRouter,
 * Gemini). Neutral conversation format converted per-provider by an adapter.
 * Tools run via emcp-tools/v1/execute-ability; destructive tools need approval.
 * Conversations are saved per-user; the model picker is searchable (free models
 * first); keys + default models are managed on a dedicated settings screen.
 */
( function () {
	'use strict';

	var cfg = window.emcpAiChat;
	if ( ! cfg ) { return; }
	var root = document.getElementById( 'emcp-ai-chat' );
	if ( ! root ) { return; }

	var i18n     = cfg.i18n || {};
	var DESTRUCT = cfg.destructive || [];
	var SYSTEM   = buildSystemPrompt();

	// State.
	var convo          = [];
	var toolDefs       = [];
	var keys           = {};
	var activeProvider = '';
	var model          = '';
	var currentConvId  = null;
	var budgetCap      = parseFloat( cfg.defaultBudget ) || 5;
	var convCost       = 0;
	var aborter        = null;
	var busy           = false;

	var $messages, $input, $send, $provider, modelCombo;

	// ── boot ──────────────────────────────────────────────────────────────────

	function init() { setView( 'loading' ); loadTools().then( go, go ); }

	function go() {
		renderSettings();
		var first = firstConnected();
		if ( first ) { selectProvider( first ); startChat(); }
		else { var back = document.getElementById( 'emcp-ai-back' ); if ( back ) { back.hidden = true; } setView( 'settings' ); }
	}

	function setView( name ) {
		root.setAttribute( 'data-state', name );
		[ 'loading', 'settings', 'chat' ].forEach( function ( v ) {
			var el2 = root.querySelector( '[data-emcp-ai-view="' + v + '"]' );
			if ( el2 ) { el2.hidden = ( v !== name ); }
		} );
	}

	function firstConnected() {
		var ids = Object.keys( cfg.providers || {} );
		for ( var i = 0; i < ids.length; i++ ) { if ( isConnected( ids[ i ] ) ) { return ids[ i ]; } }
		return '';
	}
	function isConnected( id ) { return !! ( cfg.connections && cfg.connections[ id ] && cfg.connections[ id ].connected ); }

	// ── REST ──────────────────────────────────────────────────────────────────

	function rest( path, opts ) {
		opts = opts || {};
		return fetch( cfg.restBase + path, {
			method: opts.method || 'GET', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: opts.body ? JSON.stringify( opts.body ) : undefined
		} ).then( function ( r ) { return r.json().then( function ( d ) { if ( ! r.ok ) { throw new Error( ( d && d.message ) || ( 'HTTP ' + r.status ) ); } return d; } ); } );
	}
	function loadTools() { return rest( '/abilities' ).then( function ( d ) { toolDefs = d.tools || []; } ); }
	function applyState( d ) { if ( d.connections ) { cfg.connections = d.connections; } if ( d.models ) { cfg.models = d.models; } if ( d.defaults ) { cfg.defaults = d.defaults; } if ( d.chosen ) { cfg.chosen = d.chosen; } }
	function ensureKey( p ) { if ( keys[ p ] ) { return Promise.resolve( keys[ p ] ); } return rest( '/api-key/reveal?provider=' + encodeURIComponent( p ) ).then( function ( d ) { keys[ p ] = d.key; return d.key; } ); }
	function executeAbility( name, args, approved ) { return rest( '/execute-ability', { method: 'POST', body: { ability: name, args: args || {}, approved: !! approved } } ); }

	// ── searchable combobox ─────────────────────────────────────────────────────

	function makeCombo( host, onChange ) {
		var value = '', options = [];
		host.classList.add( 'emcp-ai-combo' );
		var display = el( 'button', 'emcp-ai-combo-display' ); display.type = 'button';
		var panel = el( 'div', 'emcp-ai-combo-panel' ); panel.hidden = true;
		var search = el( 'input', 'emcp-ai-combo-search' ); search.type = 'text'; search.placeholder = i18n.searchModels || 'Search models…';
		var list = el( 'div', 'emcp-ai-combo-list' );
		panel.appendChild( search ); panel.appendChild( list );
		host.appendChild( display ); host.appendChild( panel );

		function renderList( filter ) {
			list.innerHTML = '';
			var f = ( filter || '' ).toLowerCase();
			var groups = {}, order = [];
			options.forEach( function ( o ) {
				if ( f && o.label.toLowerCase().indexOf( f ) === -1 && o.value.toLowerCase().indexOf( f ) === -1 ) { return; }
				var g = o.group || '';
				if ( ! groups[ g ] ) { groups[ g ] = []; order.push( g ); }
				groups[ g ].push( o );
			} );
			order.forEach( function ( g ) {
				if ( g ) { var h = el( 'div', 'emcp-ai-combo-group' ); h.textContent = g; list.appendChild( h ); }
				groups[ g ].forEach( function ( o ) {
					var item = el( 'div', 'emcp-ai-combo-item' + ( o.value === value ? ' is-sel' : '' ) );
					item.textContent = o.label; item.title = o.value;
					item.addEventListener( 'click', function () { setValue( o.value ); close(); onChange( o.value ); } );
					list.appendChild( item );
				} );
			} );
			if ( ! list.childElementCount ) { var e = el( 'div', 'emcp-ai-combo-empty' ); e.textContent = i18n.noMatches || 'No matches'; list.appendChild( e ); }
		}
		function open() { panel.hidden = false; search.value = ''; renderList( '' ); search.focus(); }
		function close() { panel.hidden = true; }
		display.addEventListener( 'click', function () { panel.hidden ? open() : close(); } );
		search.addEventListener( 'input', function () { renderList( search.value ); } );
		document.addEventListener( 'click', function ( e ) { if ( ! host.contains( e.target ) ) { close(); } } );

		function setValue( v ) { value = v; var o = options.filter( function ( x ) { return x.value === v; } )[ 0 ]; display.textContent = ( o ? o.label : v ) || ( i18n.selectModel || 'Select model…' ); }
		function setOptions( opts, v ) { options = opts; setValue( v !== undefined ? v : value ); }
		return { setOptions: setOptions, getValue: function () { return value; }, setValue: setValue };
	}

	function modelOptions( provider ) {
		var ms = ( cfg.models && cfg.models[ provider ] ) || [];
		var free = ms.filter( function ( m ) { return m.free; } ), paid = ms.filter( function ( m ) { return ! m.free; } );
		return free.concat( paid ).map( function ( m ) { return { value: m.id, label: m.display_name || m.id, group: m.free ? ( i18n.freeGroup || 'Free' ) : '' }; } );
	}

	// ── settings screen ──────────────────────────────────────────────────────────

	function renderSettings() {
		var c = document.getElementById( 'emcp-ai-providers' );
		if ( c ) { renderProviders( c ); }
		var back = document.getElementById( 'emcp-ai-back' );
		if ( back && ! back.dataset.bound ) { back.dataset.bound = '1'; back.addEventListener( 'click', function () { if ( firstConnected() ) { setView( 'chat' ); } } ); }
	}

	function renderProviders( container ) {
		container.innerHTML = '';
		Object.keys( cfg.providers ).forEach( function ( id ) {
			var p = cfg.providers[ id ], conn = isConnected( id );
			var row = el( 'div', 'emcp-ai-provider-row' ); row.setAttribute( 'data-provider', id );

			var head = el( 'div', 'emcp-ai-provider-head' );
			head.appendChild( elText( 'strong', p.label ) );
			var status = el( 'span', 'emcp-ai-provider-status ' + ( conn ? 'is-on' : '' ) );
			var mc = ( cfg.models[ id ] || [] ).length;
			status.textContent = conn ? ( ( i18n.connected || 'Connected' ) + ' · ' + ( cfg.connections[ id ].masked || '' ) + ' · ' + mc + ' models' ) : ( i18n.notConnected || 'Not connected' );
			head.appendChild( status );
			row.appendChild( head );

			var body = el( 'div', 'emcp-ai-provider-body' );
			if ( conn ) {
				var dis = btn( i18n.disconnect || 'Disconnect', 'button-link-delete' );
				dis.addEventListener( 'click', function () { disconnect( id ); } );
				body.appendChild( dis );
				// Default model picker for this provider.
				var dl = el( 'label', 'emcp-ai-default-model' );
				dl.appendChild( elText( 'span', i18n.defaultModel || 'Default model' ) );
				var combo = el( 'div', '' );
				dl.appendChild( combo );
				body.appendChild( dl );
				var c2 = makeCombo( combo, function ( v ) { rest( '/default-model', { method: 'POST', body: { provider: id, model: v } } ).then( applyState ); } );
				c2.setOptions( modelOptions( id ), ( cfg.defaults && cfg.defaults[ id ] ) || '' );
			} else {
				var input = document.createElement( 'input' );
				input.type = 'password'; input.placeholder = p.keyHint || 'API key'; input.autocomplete = 'off'; input.spellcheck = false; input.className = 'emcp-ai-provider-key';
				var b = btn( i18n.connect || 'Connect', 'button-primary' ), st = el( 'span', 'emcp-ai-provider-msg' );
				function submit() {
					var key = ( input.value || '' ).trim(); if ( ! key ) { return; }
					b.disabled = true; st.textContent = i18n.validating || 'Validating…'; st.className = 'emcp-ai-provider-msg';
					connect( id, key ).then( function () { input.value = ''; } ).catch( function ( err ) { st.textContent = err.message || 'Failed.'; st.className = 'emcp-ai-provider-msg is-error'; } ).finally( function () { b.disabled = false; } );
				}
				b.addEventListener( 'click', submit );
				input.addEventListener( 'keydown', function ( e ) { if ( 'Enter' === e.key ) { submit(); } } );
				var link = document.createElement( 'a' ); link.href = p.consoleUrl; link.target = '_blank'; link.rel = 'noopener'; link.className = 'emcp-ai-provider-getkey'; link.textContent = i18n.getApiKey || 'Get an API key →';
				body.appendChild( input ); body.appendChild( b ); body.appendChild( link ); body.appendChild( st );
			}
			row.appendChild( body );
			container.appendChild( row );
		} );
	}

	function connect( id, key ) {
		return rest( '/api-key', { method: 'POST', body: { provider: id, key: key } } ).then( function ( d ) {
			applyState( d ); renderSettings();
			if ( ! activeProvider ) { selectProvider( id ); }
			var back = document.getElementById( 'emcp-ai-back' ); if ( back ) { back.hidden = false; }
			startChatIfNeeded();
		} );
	}
	function disconnect( id ) {
		rest( '/api-key', { method: 'DELETE', body: { provider: id } } ).then( function ( d ) {
			applyState( d ); delete keys[ id ]; renderSettings();
			if ( ! firstConnected() ) { activeProvider = ''; var back = document.getElementById( 'emcp-ai-back' ); if ( back ) { back.hidden = true; } }
			else if ( id === activeProvider ) { selectProvider( firstConnected() ); }
		} );
	}
	function startChatIfNeeded() { if ( ! $messages ) { startChat(); } }

	// ── pickers ──────────────────────────────────────────────────────────────────

	function populateProviderPicker() {
		$provider = document.getElementById( 'emcp-ai-provider' );
		if ( ! $provider ) { return; }
		$provider.innerHTML = '';
		Object.keys( cfg.providers ).forEach( function ( id ) {
			if ( ! isConnected( id ) ) { return; }
			var o = document.createElement( 'option' ); o.value = id; o.textContent = cfg.providers[ id ].label;
			if ( id === activeProvider ) { o.selected = true; }
			$provider.appendChild( o );
		} );
		if ( ! $provider.dataset.bound ) { $provider.dataset.bound = '1'; $provider.addEventListener( 'change', function () { selectProvider( $provider.value ); } ); }
	}
	function refreshModelCombo() {
		var host = document.getElementById( 'emcp-ai-model-combo' );
		if ( ! host ) { return; }
		if ( ! modelCombo ) { modelCombo = makeCombo( host, function ( v ) { model = v; } ); }
		modelCombo.setOptions( modelOptions( activeProvider ), model );
	}
	function selectProvider( id ) {
		activeProvider = id;
		model = ( cfg.defaults && cfg.defaults[ id ] ) || ( ( cfg.models[ id ] || [] )[ 0 ] || {} ).id || '';
		populateProviderPicker(); refreshModelCombo();
		ensureKey( id ).catch( function () {} );
	}

	// ── chat shell ──────────────────────────────────────────────────────────────

	function startChat() {
		$messages = document.getElementById( 'emcp-ai-messages' );
		$input    = document.getElementById( 'emcp-ai-input' );
		$send     = document.getElementById( 'emcp-ai-send' );

		populateProviderPicker(); refreshModelCombo(); renderConvos();

		if ( ! $send.dataset.bound ) {
			$send.dataset.bound = '1';
			$send.addEventListener( 'click', onSend );
			$input.addEventListener( 'keydown', function ( e ) { if ( 'Enter' === e.key && ! e.shiftKey ) { e.preventDefault(); onSend(); } } );
			$input.addEventListener( 'input', autoGrow );
			document.getElementById( 'emcp-ai-new' ).addEventListener( 'click', newChat );
			document.getElementById( 'emcp-ai-settings-toggle' ).addEventListener( 'click', function () { renderSettings(); document.getElementById( 'emcp-ai-back' ).hidden = ! firstConnected(); setView( 'settings' ); } );
		}
		if ( ! convo.length && ! $messages.childElementCount ) { greeting(); }
		setView( 'chat' ); $input.focus();
	}
	function greeting() { renderAssistantText( "Hi! I can build pages, add containers, insert widgets, edit content, and more. Tell me what you'd like — e.g. “Build a dental clinic landing page with a hero, services, testimonials, and a contact form.”" ); }
	function autoGrow() { $input.style.height = 'auto'; $input.style.height = Math.min( $input.scrollHeight, 160 ) + 'px'; }

	// ── conversations ────────────────────────────────────────────────────────────

	function renderConvos() {
		var c = document.getElementById( 'emcp-ai-convos' ); if ( ! c ) { return; }
		c.innerHTML = '';
		( cfg.conversations || [] ).forEach( function ( cv ) {
			var row = el( 'div', 'emcp-ai-convo' + ( cv.id === currentConvId ? ' is-active' : '' ) );
			var t = el( 'button', 'emcp-ai-convo-title' ); t.type = 'button'; t.textContent = cv.title; t.title = cv.title;
			t.addEventListener( 'click', function () { if ( ! busy ) { loadConv( cv.id ); } } );
			var d = btn( '×', 'emcp-ai-convo-del' ); d.title = 'Delete';
			d.addEventListener( 'click', function ( e ) { e.stopPropagation(); deleteConv( cv.id ); } );
			row.appendChild( t ); row.appendChild( d ); c.appendChild( row );
		} );
	}
	function newChat() {
		if ( busy ) { return; }
		currentConvId = null; convo = []; convCost = 0; $messages.innerHTML = ''; greeting(); renderConvos(); $input.focus();
	}
	function loadConv( id ) {
		rest( '/conversations/' + id ).then( function ( d ) {
			currentConvId = d.id; convo = d.messages || []; convCost = 0;
			if ( d.provider && isConnected( d.provider ) ) { activeProvider = d.provider; model = d.model || model; populateProviderPicker(); refreshModelCombo(); ensureKey( activeProvider ).catch( function () {} ); }
			replay( convo ); renderConvos(); setView( 'chat' );
		} );
	}
	function deleteConv( id ) {
		rest( '/conversations/' + id, { method: 'DELETE' } ).then( function ( d ) {
			cfg.conversations = d.conversations || [];
			if ( id === currentConvId ) { newChat(); } else { renderConvos(); }
		} );
	}
	function autoSave() {
		if ( ! convo.length ) { return; }
		rest( '/conversations', { method: 'POST', body: { id: currentConvId || undefined, title: convoTitle(), provider: activeProvider, model: model, messages: convo } } )
			.then( function ( d ) { currentConvId = d.id; cfg.conversations = d.conversations || []; renderConvos(); } ).catch( function () {} );
	}
	function convoTitle() {
		for ( var i = 0; i < convo.length; i++ ) { if ( 'user' === convo[ i ].role && convo[ i ].text ) { return convo[ i ].text.slice( 0, 80 ); } }
		return 'Untitled chat';
	}

	// ── conversation loop ───────────────────────────────────────────────────────

	function onSend() {
		if ( busy ) { return; }
		var text = ( $input.value || '' ).trim(); if ( ! text || ! activeProvider || ! model ) { return; }
		if ( budgetCap > 0 && convCost >= budgetCap ) { renderError( i18n.budgetHit ); return; }
		$input.value = ''; autoGrow();
		renderUserText( text ); convo.push( { role: 'user', text: text } );
		runConversation();
	}

	function runConversation() {
		busy = true; setSending( true );
		var iterations = 0, adapter = ADAPTERS[ ( cfg.providers[ activeProvider ] || {} ).format ] || ADAPTERS.openai;
		function step() {
			if ( iterations >= ( cfg.maxIterations - 0 || 50 ) ) { renderError( i18n.loopStopped ); return finish(); }
			iterations++;
			var turn = renderAssistantPending();
			ensureKey( activeProvider ).then( function ( key ) { return adapter.stream( key, model, convo, toolDefs, turn ); } ).then( function ( res ) {
				turn.finalizeText();
				convo.push( { role: 'assistant', text: res.text, tools: res.toolCalls } );
				addUsage( turn, res.usage );
				if ( ! res.toolCalls.length ) { return finish(); }
				runTools( res.toolCalls, turn ).then( function ( results ) { convo.push( { role: 'tool', results: results } ); step(); } );
			} ).catch( function ( err ) { turn.fail( ( i18n.apiError || 'API error' ) + ': ' + ( err.message || err ) ); finish(); } );
		}
		function finish() { busy = false; setSending( false ); scrollDown(); autoSave(); }
		step();
	}

	function runTools( toolCalls, turn ) {
		var results = [], chain = Promise.resolve();
		toolCalls.forEach( function ( tc ) {
			chain = chain.then( function () {
				var b = turn.addTool( tc.name, tc.input );
				var destructive = DESTRUCT.indexOf( tc.name ) !== -1;
				return ( destructive ? b.awaitApproval() : Promise.resolve( true ) ).then( function ( ok ) {
					if ( ! ok ) { b.setError( i18n.rejected || 'Rejected.' ); results.push( { id: tc.id, content: ( i18n.rejected || 'User rejected this tool call.' ), isError: true } ); return; }
					return executeAbility( 'elementor-mcp/' + tc.name, tc.input, destructive ).then( function ( d ) { b.setOk( d.result ); results.push( { id: tc.id, content: JSON.stringify( d.result ) } ); } )
						.catch( function ( err ) { b.setError( err.message || 'Failed.' ); results.push( { id: tc.id, content: ( err.message || 'Failed.' ), isError: true } ); } );
				} );
			} );
		} );
		return chain.then( function () { return results; } );
	}

	// ── adapters ─────────────────────────────────────────────────────────────────

	function providerHeaders( key ) {
		var p = cfg.providers[ activeProvider ], h = { 'content-type': 'application/json' };
		Object.keys( p.headers || {} ).forEach( function ( k ) { h[ k ] = p.headers[ k ]; } );
		if ( 'bearer' === p.auth ) { h.Authorization = 'Bearer ' + key; } else { h[ 'x-api-key' ] = key; }
		return h;
	}
	function postStream( body ) {
		aborter = new AbortController();
		return fetch( cfg.providers[ activeProvider ].chatUrl, { method: 'POST', signal: aborter.signal, headers: providerHeaders( keys[ activeProvider ] ), body: JSON.stringify( body ) } )
			.then( function ( resp ) { if ( ! resp.ok ) { return resp.json().then( function ( e ) { throw new Error( ( e && e.error && e.error.message ) || ( 'HTTP ' + resp.status ) ); }, function () { throw new Error( 'HTTP ' + resp.status ); } ); } return resp; } );
	}
	function sseLoop( resp, onEvent ) {
		var reader = resp.body.getReader(), dec = new TextDecoder(), buf = '';
		function pump() { return reader.read().then( function ( r ) { if ( r.done ) { return; } buf += dec.decode( r.value, { stream: true } ); var parts = buf.split( '\n\n' ); buf = parts.pop(); parts.forEach( function ( raw ) { var data = ''; raw.split( '\n' ).forEach( function ( l ) { if ( 0 === l.indexOf( 'data:' ) ) { data += l.slice( 5 ).trim(); } } ); if ( data && '[DONE]' !== data ) { onEvent( data ); } } ); return pump(); } ); }
		return pump();
	}

	var ADAPTERS = {
		anthropic: {
			stream: function ( key, model, convo, tools, turn ) {
				var body = { model: model, max_tokens: 8000, system: [ { type: 'text', text: SYSTEM, cache_control: { type: 'ephemeral' } } ], tools: anthropicTools( tools ), messages: anthropicMessages( convo ), stream: true };
				var blocks = [], usage = newUsage();
				return postStream( body ).then( function ( resp ) { return sseLoop( resp, function ( data ) {
					var ev; try { ev = JSON.parse( data ); } catch ( e ) { return; }
					if ( 'message_start' === ev.type ) { mergeUsage( usage, ev.message && ev.message.usage ); }
					else if ( 'content_block_start' === ev.type ) { blocks[ ev.index ] = ev.content_block.type === 'tool_use' ? { type: 'tool_use', id: ev.content_block.id, name: ev.content_block.name, _j: '' } : { type: 'text', text: '' }; }
					else if ( 'content_block_delta' === ev.type ) { var b = blocks[ ev.index ]; if ( ! b ) { return; } if ( 'text_delta' === ev.delta.type ) { b.text += ev.delta.text; turn.appendText( ev.delta.text ); } else if ( 'input_json_delta' === ev.delta.type ) { b._j += ev.delta.partial_json; } }
					else if ( 'content_block_stop' === ev.type ) { var bb = blocks[ ev.index ]; if ( bb && 'tool_use' === bb.type ) { try { bb.input = bb._j ? JSON.parse( bb._j ) : {}; } catch ( e ) { bb.input = {}; } } }
					else if ( 'message_delta' === ev.type ) { mergeUsage( usage, ev.usage ); }
					else if ( 'error' === ev.type ) { throw new Error( ( ev.error && ev.error.message ) || 'stream error' ); }
				} ); } ).then( function () { return collect( blocks, usage ); } );
			}
		},
		openai: {
			stream: function ( key, model, convo, tools, turn ) {
				var body = { model: model, messages: openaiMessages( convo ), tools: openaiTools( tools ), stream: true, stream_options: { include_usage: true } };
				var text = '', calls = {}, usage = newUsage();
				return postStream( body ).then( function ( resp ) { return sseLoop( resp, function ( data ) {
					var ev; try { ev = JSON.parse( data ); } catch ( e ) { return; }
					if ( ev.usage ) { usage.input_tokens = ev.usage.prompt_tokens || usage.input_tokens; usage.output_tokens = ev.usage.completion_tokens || usage.output_tokens; }
					var ch = ev.choices && ev.choices[ 0 ]; if ( ! ch || ! ch.delta ) { return; }
					if ( ch.delta.content ) { text += ch.delta.content; turn.appendText( ch.delta.content ); }
					( ch.delta.tool_calls || [] ).forEach( function ( tc ) { var k = tc.index || 0; calls[ k ] = calls[ k ] || { id: '', name: '', args: '' }; if ( tc.id ) { calls[ k ].id = tc.id; } if ( tc.function && tc.function.name ) { calls[ k ].name = tc.function.name; } if ( tc.function && tc.function.arguments ) { calls[ k ].args += tc.function.arguments; } } );
				} ); } ).then( function () {
					var toolCalls = Object.keys( calls ).map( function ( k ) { var c = calls[ k ], input = {}; try { input = c.args ? JSON.parse( c.args ) : {}; } catch ( e ) {} return { id: c.id || ( 'call_' + k ), name: c.name, input: input }; } ).filter( function ( c ) { return c.name; } );
					return { text: text, toolCalls: toolCalls, usage: usage };
				} );
			}
		}
	};

	function anthropicMessages( convo ) {
		return convo.map( function ( t ) {
			if ( 'user' === t.role ) { return { role: 'user', content: t.text }; }
			if ( 'assistant' === t.role ) { var content = []; if ( t.text ) { content.push( { type: 'text', text: t.text } ); } ( t.tools || [] ).forEach( function ( tc ) { content.push( { type: 'tool_use', id: tc.id, name: tc.name, input: tc.input } ); } ); return { role: 'assistant', content: content.length ? content : [ { type: 'text', text: '' } ] }; }
			return { role: 'user', content: ( t.results || [] ).map( function ( r ) { var o = { type: 'tool_result', tool_use_id: r.id, content: r.content }; if ( r.isError ) { o.is_error = true; } return o; } ) };
		} );
	}
	function anthropicTools( tools ) { var out = tools.map( function ( t ) { return { name: t.name, description: t.description, input_schema: t.input_schema }; } ); if ( out.length ) { out[ out.length - 1 ] = Object.assign( {}, out[ out.length - 1 ], { cache_control: { type: 'ephemeral' } } ); } return out; }
	function openaiMessages( convo ) {
		var msgs = [ { role: 'system', content: SYSTEM } ];
		convo.forEach( function ( t ) {
			if ( 'user' === t.role ) { msgs.push( { role: 'user', content: t.text } ); }
			else if ( 'assistant' === t.role ) { var m = { role: 'assistant', content: t.text || '' }; if ( ( t.tools || [] ).length ) { m.tool_calls = t.tools.map( function ( tc ) { return { id: tc.id, type: 'function', function: { name: tc.name, arguments: JSON.stringify( tc.input || {} ) } }; } ); } msgs.push( m ); }
			else { ( t.results || [] ).forEach( function ( r ) { msgs.push( { role: 'tool', tool_call_id: r.id, content: String( r.content ) } ); } ); }
		} );
		return msgs;
	}
	function openaiTools( tools ) { return tools.map( function ( t ) { return { type: 'function', function: { name: t.name, description: t.description, parameters: t.input_schema } }; } ); }
	function collect( blocks, usage ) { var text = '', toolCalls = []; blocks.forEach( function ( b ) { if ( ! b ) { return; } if ( 'text' === b.type ) { text += b.text; } else if ( 'tool_use' === b.type ) { toolCalls.push( { id: b.id, name: b.name, input: b.input || {} } ); } } ); return { text: text, toolCalls: toolCalls, usage: usage }; }

	// ── usage / cost ─────────────────────────────────────────────────────────────

	function newUsage() { return { input_tokens: 0, output_tokens: 0, cache_read_input_tokens: 0, cache_creation_input_tokens: 0 }; }
	function mergeUsage( acc, u ) { if ( ! u ) { return; } [ 'input_tokens', 'cache_read_input_tokens', 'cache_creation_input_tokens' ].forEach( function ( k ) { if ( typeof u[ k ] === 'number' ) { acc[ k ] = ( acc[ k ] || 0 ) + u[ k ]; } } ); if ( typeof u.output_tokens === 'number' ) { acc.output_tokens = u.output_tokens; } }
	function priceFor( id ) { var lid = String( id ).toLowerCase(), pr = cfg.pricing || {}, ks = Object.keys( pr ); for ( var i = 0; i < ks.length; i++ ) { if ( lid.indexOf( ks[ i ] ) !== -1 ) { return pr[ ks[ i ] ]; } } return null; }
	function turnCost( u ) { var p = priceFor( model ); if ( ! p ) { return null; } var inP = ( p[ 0 ] - 0 ) / 1e6, outP = ( p[ 1 ] - 0 ) / 1e6; return ( u.input_tokens || 0 ) * inP + ( u.cache_creation_input_tokens || 0 ) * inP * 1.25 + ( u.cache_read_input_tokens || 0 ) * inP * 0.1 + ( u.output_tokens || 0 ) * outP; }
	function totalTokens( u ) { return ( u.input_tokens || 0 ) + ( u.output_tokens || 0 ) + ( u.cache_read_input_tokens || 0 ) + ( u.cache_creation_input_tokens || 0 ); }
	function addUsage( turn, usage ) { var c = turnCost( usage ); if ( null !== c ) { convCost += c; } var f = turn.footer(); f.textContent = 'ⓘ ' + ( i18n.thisTurn || 'This turn' ) + ': ' + fmt( totalTokens( usage ) ) + ' ' + ( i18n.tokens || 'tokens' ) + ( null !== c ? ( '  ·  ' + ( i18n.convTotal || 'Conversation total' ) + ': $' + convCost.toFixed( 3 ) ) : '' ); }

	// ── rendering ─────────────────────────────────────────────────────────────────

	function bubble( role ) { var wrap = el( 'div', 'emcp-ai-msg emcp-ai-msg--' + role ); var av = el( 'div', 'emcp-ai-msg-avatar' ); av.textContent = role === 'user' ? 'You' : 'AI'; var body = el( 'div', 'emcp-ai-msg-body' ); wrap.appendChild( av ); wrap.appendChild( body ); $messages.appendChild( wrap ); scrollDown(); return body; }
	function renderUserText( t ) { bubble( 'user' ).innerHTML = mdToHtml( t ); }
	function renderAssistantText( t ) { bubble( 'assistant' ).innerHTML = mdToHtml( t ); }
	function renderError( msg ) { bubble( 'assistant' ).innerHTML = '<p style="color:#b32d2e">' + escapeHtml( msg ) + '</p>'; }

	function replay( convo ) {
		$messages.innerHTML = '';
		for ( var i = 0; i < convo.length; i++ ) {
			var t = convo[ i ];
			if ( 'user' === t.role ) { renderUserText( t.text ); }
			else if ( 'assistant' === t.role ) {
				var body = bubble( 'assistant' ); if ( t.text ) { body.innerHTML = mdToHtml( t.text ); }
				var results = ( convo[ i + 1 ] && 'tool' === convo[ i + 1 ].role ) ? ( convo[ i + 1 ].results || [] ) : [];
				( t.tools || [] ).forEach( function ( tc ) {
					var tb = toolBubble( body, tc.name, tc.input );
					var r = results.filter( function ( x ) { return x.id === tc.id; } )[ 0 ];
					if ( r ) { if ( r.isError ) { tb.setError( r.content ); } else { var parsed; try { parsed = JSON.parse( r.content ); } catch ( e ) { parsed = r.content; } tb.setOk( parsed ); } }
				} );
			}
		}
		scrollDown();
	}

	function renderAssistantPending() {
		var body = bubble( 'assistant' ), textEl = document.createElement( 'div' ), raw = '';
		body.appendChild( textEl );
		return {
			appendText: function ( t ) { raw += t; textEl.innerHTML = mdToHtml( raw ); scrollDown(); },
			finalizeText: function () { textEl.innerHTML = raw ? mdToHtml( raw ) : ''; },
			fail: function ( msg ) { var e = document.createElement( 'p' ); e.style.color = '#b32d2e'; e.textContent = msg; body.appendChild( e ); },
			addTool: function ( name, input ) { return toolBubble( body, name, input ); },
			footer: function () { var f = el( 'div', 'emcp-ai-usage' ); body.appendChild( f ); return f; }
		};
	}

	function toolBubble( parent, name, input ) {
		var box = el( 'div', 'emcp-ai-tool emcp-ai-tool--pending' );
		box.innerHTML = '<div class="emcp-ai-tool-head"><span class="emcp-ai-spin"></span><span class="emcp-ai-tool-name"></span><span class="emcp-ai-tool-state">' + escapeHtml( i18n.calling || 'Calling' ) + '…</span></div><div class="emcp-ai-tool-body"></div>';
		box.querySelector( '.emcp-ai-tool-name' ).textContent = name;
		var bodyEl = box.querySelector( '.emcp-ai-tool-body' ); bodyEl.appendChild( pre( 'input', input ) );
		box.querySelector( '.emcp-ai-tool-head' ).addEventListener( 'click', function () { box.classList.toggle( 'is-open' ); } );
		parent.appendChild( box ); scrollDown();
		function setState( cls, label ) { box.className = 'emcp-ai-tool ' + cls; var s = box.querySelector( '.emcp-ai-spin' ); if ( s ) { s.remove(); } box.querySelector( '.emcp-ai-tool-state' ).textContent = label; }
		return {
			setOk: function ( result ) { setState( 'emcp-ai-tool--ok', '✓' ); bodyEl.appendChild( pre( 'result', result ) ); maybeCta( bodyEl, result ); },
			setError: function ( msg ) { setState( 'emcp-ai-tool--err', '✗ ' + msg ); },
			awaitApproval: function () { return new Promise( function ( resolve ) {
				setState( 'emcp-ai-tool--wait', '⚠ ' + ( i18n.approve || 'Approve' ) + '?' ); box.classList.add( 'is-open' );
				var bar = el( 'div', 'emcp-ai-approve' ), yes = btn( i18n.approve || 'Approve', 'button-primary' ), no = btn( i18n.reject || 'Reject', '' );
				bar.appendChild( yes ); bar.appendChild( no ); box.appendChild( bar );
				yes.addEventListener( 'click', function () { bar.remove(); resolve( true ); } );
				no.addEventListener( 'click', function () { bar.remove(); resolve( false ); } );
			} ); }
		};
	}
	function maybeCta( parent, result ) { var id = result && ( result.post_id || result.page_id ); if ( ! id ) { return; } var a = document.createElement( 'a' ); a.className = 'emcp-ai-cta'; a.href = cfg.siteUrl + '/wp-admin/post.php?post=' + id + '&action=elementor'; a.target = '_blank'; a.rel = 'noopener'; a.textContent = 'Edit in Elementor →'; parent.appendChild( a ); }

	// ── helpers ───────────────────────────────────────────────────────────────────

	function setSending( on ) { if ( ! $send ) { return; } $send.disabled = false; $send.textContent = on ? ( i18n.stop || 'Stop' ) : ( i18n.send || 'Send' ); $send.onclick = on ? function () { if ( aborter ) { aborter.abort(); } busy = false; setSending( false ); } : onSend; }
	function scrollDown() { if ( $messages ) { $messages.scrollTop = $messages.scrollHeight; } }
	function el( tag, cls ) { var e = document.createElement( tag ); if ( cls ) { e.className = cls; } return e; }
	function elText( tag, t ) { var e = document.createElement( tag ); e.textContent = t; return e; }
	function pre( label, obj ) { var p = document.createElement( 'pre' ); p.textContent = label + ': ' + safe( obj ); return p; }
	function btn( label, cls ) { var b = document.createElement( 'button' ); b.type = 'button'; b.className = 'button ' + cls; b.textContent = label; return b; }
	function fmt( n ) { return ( n || 0 ).toLocaleString(); }
	function safe( o ) { try { return JSON.stringify( o, null, 2 ); } catch ( e ) { return String( o ); } }

	function buildSystemPrompt() {
		return 'You are an AI assistant embedded in a WordPress site running Elementor and the EMCP Tools plugin. ' +
			'You have tools to create pages, add containers/widgets, edit content, and manipulate the Elementor element tree.\n\n' +
			'Site: ' + cfg.siteUrl + ' | WordPress ' + cfg.wpVersion + ' | Elementor ' + cfg.elementorVer + ' | Elementor Pro: ' + ( cfg.elementorPro ? 'active' : 'not active' ) + ' | User: ' + cfg.userName + '\n\n' +
			'When the user references an existing page, call list-pages first. Prefer container-based layouts (not legacy sections/columns) and convenience tools (add-heading, add-button) over the universal add-widget. ' +
			'Destructive operations (deletes, global changes, code) require user approval — the system pauses and asks. If a tool errors, read the message and adjust rather than retrying blindly.';
	}
	function escapeHtml( s ) { return String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ); }
	function mdToHtml( src ) {
		var t = escapeHtml( src );
		t = t.replace( /```([\s\S]*?)```/g, function ( m, c ) { return '<pre>' + c.replace( /^\n/, '' ) + '</pre>'; } );
		t = t.replace( /`([^`]+)`/g, '<code>$1</code>' );
		t = t.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' );
		t = t.replace( /(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>' );
		t = t.replace( /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>' );
		t = t.replace( /(?:^|\n)([-*] .+(?:\n[-*] .+)*)/g, function ( m, list ) { return '\n<ul>' + list.split( /\n/ ).map( function ( li ) { return '<li>' + li.replace( /^[-*]\s+/, '' ) + '</li>'; } ).join( '' ) + '</ul>'; } );
		return t.split( /\n{2,}/ ).map( function ( para ) { return /^\s*<(pre|ul|ol)/.test( para ) ? para : '<p>' + para.replace( /\n/g, '<br>' ) + '</p>'; } ).join( '' );
	}

	init();
}() );
