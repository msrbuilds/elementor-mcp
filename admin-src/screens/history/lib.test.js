import {
	lineDiff,
	toQuery,
	writeBody,
	sessionResultMessage,
	relativeTime,
} from './lib';

describe( 'lineDiff', () => {
	it( 'marks added and removed lines', () => {
		expect( lineDiff( 'a\nb\n', 'a\nc\n' ) ).toEqual( [
			{ type: 'same', text: 'a' },
			{ type: 'del', text: 'b' },
			{ type: 'add', text: 'c' },
		] );
	} );

	it( 'collapses long unchanged runs around the changes', () => {
		const same = Array.from( { length: 20 }, ( _, i ) => `l${ i }` );
		const before = [ ...same, 'x' ].join( '\n' );
		const after = [ ...same, 'y' ].join( '\n' );
		const out = lineDiff( before, after, 2 );
		expect( out[ 0 ] ).toEqual( { type: 'skip', count: 18, text: '' } );
		expect( out.slice( 1 ).map( ( l ) => l.text ) ).toEqual( [
			'l18',
			'l19',
			'x',
			'y',
		] );
	} );

	it( 'collapses identical text into one skip', () => {
		expect( lineDiff( 'same', 'same' ) ).toEqual( [
			{ type: 'skip', count: 1, text: '' },
		] );
	} );
} );

describe( 'query helpers', () => {
	it( 'drops empty and default filters', () => {
		expect(
			toQuery( { search: 'hero', kind: 'all', client: '', range: 'all' } )
		).toBe( 'search=hero' );
		expect(
			toQuery(
				{ search: '', kind: 'design', client: 'WP-CLI', range: '7d' },
				{ before: 42 }
			)
		).toBe( 'kind=design&client=WP-CLI&range=7d&before=42' );
	} );

	it( 'sends the loaded page count with writes', () => {
		expect(
			writeBody(
				{ search: '', kind: 'all', client: '', range: 'all' },
				3
			)
		).toEqual( { limit: 20 } );
		expect(
			writeBody(
				{ search: 'x', kind: 'content', client: '', range: '24h' },
				45
			)
		).toEqual( { search: 'x', kind: 'content', range: '24h', limit: 45 } );
	} );
} );

describe( 'sessionResultMessage', () => {
	it( 'reports a complete undo', () => {
		expect(
			sessionResultMessage( {
				undone: [ 'a', 'b' ],
				remaining: 0,
				total: 2,
			} )
		).toEqual( { tone: 'success', text: 'Undid 2 changes.' } );
	} );

	it( 'reports where it stopped', () => {
		expect(
			sessionResultMessage( {
				undone: [ 'a', 'b', 'c' ],
				remaining: 1,
				total: 4,
				stoppedTitle: 'Home',
				reason: 'changed since',
			} )
		).toEqual( {
			tone: 'error',
			text: "Undid 3 of 4 changes; stopped at 'Home': changed since",
		} );
	} );
} );

describe( 'relativeTime', () => {
	it( 'uses minutes, hours and days', () => {
		const now = 1_000_000_000_000;
		expect( relativeTime( now / 1000 - 120, now ) ).toMatch(
			/2 minutes ago/
		);
		expect( relativeTime( now / 1000 - 7200, now ) ).toMatch(
			/2 hours ago/
		);
		expect( relativeTime( now / 1000 - 3 * 86400, now ) ).toMatch(
			/3 days ago/
		);
	} );
} );
