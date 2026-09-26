/**
 * Serves scripted provider replies for AI Chat e2e runs, so no real model is
 * called and no tokens are spent. Handles browser-direct calls (Anthropic
 * Messages, OpenAI Chat Completions, OpenAI Responses) and the site's
 * /chat-relay route, and answers in the format the request asked for.
 */
const LLM_PATH = /(\/messages|\/chat\/completions|\/responses)$/;

function kindOf( url, body ) {
	if ( url.includes( '/emcp-tools/v1/chat-relay' ) ) {
		return { messages: 'anthropic', responses: 'responses' }[ body.api ] || 'chat';
	}
	if ( Array.isArray( body.system ) || url.endsWith( '/messages' ) ) {
		return 'anthropic';
	}
	return Array.isArray( body.input ) ? 'responses' : 'chat';
}

function events( kind, step, n ) {
	const id = 'call_e2e_' + n;
	if ( 'anthropic' === kind ) {
		return step.tool
			? [
					{ type: 'message_start', message: { usage: { input_tokens: 5 } } },
					{ type: 'content_block_start', index: 0, content_block: { type: 'tool_use', id, name: step.tool.name } },
					{ type: 'content_block_delta', index: 0, delta: { type: 'input_json_delta', partial_json: JSON.stringify( step.tool.input || {} ) } },
					{ type: 'content_block_stop', index: 0 },
			  ]
			: [
					{ type: 'message_start', message: { usage: { input_tokens: 5 } } },
					{ type: 'content_block_start', index: 0, content_block: { type: 'text' } },
					{ type: 'content_block_delta', index: 0, delta: { type: 'text_delta', text: step.text } },
					{ type: 'content_block_stop', index: 0 },
			  ];
	}
	if ( 'responses' === kind ) {
		return step.tool
			? [ { type: 'response.output_item.done', output_index: 0, item: { type: 'function_call', call_id: id, name: step.tool.name, arguments: JSON.stringify( step.tool.input || {} ) } } ]
			: [ { type: 'response.output_text.delta', delta: step.text } ];
	}
	return step.tool
		? [ { choices: [ { delta: { tool_calls: [ { index: 0, id, function: { name: step.tool.name, arguments: JSON.stringify( step.tool.input || {} ) } } ] } } ] }, '[DONE]' ]
		: [ { choices: [ { delta: { content: step.text } } ] }, '[DONE]' ];
}

async function mockLlm( page, script ) {
	const requests = [];
	let n = 0;
	await page.route(
		( url ) =>
			LLM_PATH.test( url.pathname ) ||
			url.pathname.endsWith( '/emcp-tools/v1/chat-relay' ),
		async ( route ) => {
			const req = route.request();
			// Browser-direct providers are cross-origin: answer the CORS preflight too.
			if ( 'OPTIONS' === req.method() ) {
				return route.fulfill( {
					status: 204,
					headers: {
						'access-control-allow-origin': '*',
						'access-control-allow-headers': '*',
						'access-control-allow-methods': 'POST, OPTIONS',
					},
				} );
			}
			if ( 'POST' !== req.method() ) {
				return route.continue();
			}
			const body = req.postDataJSON() || {};
			requests.push( { url: req.url(), body } );
			const step = script[ Math.min( n, script.length - 1 ) ];
			const kind = kindOf( req.url(), body );
			const data = events( kind, step, ++n )
				.map( ( e ) => 'data: ' + ( 'string' === typeof e ? e : JSON.stringify( e ) ) + '\n\n' )
				.join( '' );
			return route.fulfill( {
				status: 200,
				headers: {
					'content-type': 'text/event-stream',
					'access-control-allow-origin': '*',
				},
				body: data,
			} );
		}
	);
	return { requests };
}

module.exports = { mockLlm };
