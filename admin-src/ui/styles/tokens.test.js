import fs from 'fs';
import path from 'path';
import { COLORS, CONTRAST_PAIRS } from './tokens';

function luminance( hex ) {
	const rgb = [ 1, 3, 5 ].map( ( i ) => parseInt( hex.slice( i, i + 2 ), 16 ) / 255 );
	const lin = rgb.map( ( c ) => ( c <= 0.03928 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 ) ) );
	return 0.2126 * lin[ 0 ] + 0.7152 * lin[ 1 ] + 0.0722 * lin[ 2 ];
}
function contrast( a, b ) {
	const [ hi, lo ] = [ luminance( a ), luminance( b ) ].sort( ( x, y ) => y - x );
	return ( hi + 0.05 ) / ( lo + 0.05 );
}

const css = fs.readFileSync( path.join( __dirname, 'tokens.css' ), 'utf8' );
const cssColors = Object.fromEntries(
	[ ...css.matchAll( /--emcp-([a-z0-9-]+):\s*(#[0-9a-f]{6})\s*;/gi ) ].map( ( m ) => [ m[ 1 ], m[ 2 ].toLowerCase() ] )
);

describe( 'design tokens', () => {
	it( 'keeps tokens.css and tokens.js in sync', () => {
		expect( cssColors ).toEqual( COLORS );
	} );

	it( 'uses the accessible muted grey', () => {
		expect( COLORS.muted ).toBe( '#646b78' );
	} );

	it.each( CONTRAST_PAIRS )( '%s on %s meets %s:1 (%s)', ( fg, bg, min ) => {
		expect( contrast( COLORS[ fg ], COLORS[ bg ] ) ).toBeGreaterThanOrEqual( min );
	} );
} );
