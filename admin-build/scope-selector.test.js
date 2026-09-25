const { scopeSelector, splitTopLevel } = require( './scope-selector' );

describe( 'scopeSelector', () => {
	it( 'prefixes ordinary selectors', () => {
		expect( scopeSelector( '.elementor-mcp-admin .tab-content' ) ).toBe(
			'.emcp-legacy .elementor-mcp-admin .tab-content'
		);
		expect( scopeSelector( '.emcp-toast' ) ).toBe(
			'.emcp-legacy .emcp-toast'
		);
	} );

	it( 'keeps ancestor-only prefixes (body, html, .wp-core-ui) outside the scope', () => {
		expect(
			scopeSelector( '.wp-core-ui .elementor-mcp-pro-sync-btn:focus' )
		).toBe( '.wp-core-ui .emcp-legacy .elementor-mcp-pro-sync-btn:focus' );
		expect(
			scopeSelector(
				'body[data-emcp-auth="oauth"] [data-authfor="app-password"]'
			)
		).toBe(
			'body[data-emcp-auth="oauth"] .emcp-legacy [data-authfor="app-password"]'
		);
	} );

	it( 'leaves rules that target the ancestor itself alone', () => {
		expect( scopeSelector( 'body.emcp-modal-open' ) ).toBe(
			'body.emcp-modal-open'
		);
		expect( scopeSelector( ':root' ) ).toBe( ':root' );
	} );

	it( 'handles lists, child combinators and parentheses', () => {
		expect( scopeSelector( '.a, .b > .c' ) ).toBe(
			'.emcp-legacy .a, .emcp-legacy .b > .c'
		);
		expect( scopeSelector( '.x:not(.y, .z)' ) ).toBe(
			'.emcp-legacy .x:not(.y, .z)'
		);
		expect( splitTopLevel( '.a:is(.b, .c), .d' ) ).toEqual( [
			'.a:is(.b, .c)',
			' .d',
		] );
	} );

	it( 'does not double-scope', () => {
		expect( scopeSelector( '.emcp-legacy .a' ) ).toBe( '.emcp-legacy .a' );
	} );
	it( 'leaves elements that legacy scripts append to <body> unscoped', () => {
		expect( scopeSelector( '.emcp-code-overlay__panel' ) ).toBe(
			'.emcp-code-overlay__panel'
		);
		expect( scopeSelector( '.elementor-mcp-bk-toast.is-visible' ) ).toBe(
			'.elementor-mcp-bk-toast.is-visible'
		);
		expect( scopeSelector( '.emcp-code-overlayish' ) ).toBe(
			'.emcp-legacy .emcp-code-overlayish'
		);
	} );
} );
