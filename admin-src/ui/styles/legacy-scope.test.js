/**
 * Review finding 4: the base resets must not reach legacy views, which the
 * frame renders inside .emcp-app > .emcp-legacy until each screen is ported.
 */
import fs from 'fs';
import path from 'path';

const css = fs
	.readFileSync( path.join( __dirname, 'base.css' ), 'utf8' )
	.replace( /\/\*[\s\S]*?\*\//g, '' );

it( 'excludes .emcp-legacy from every universal or focus rule', () => {
	const selectors = css
		.split( '{' )
		.map( ( chunk ) => chunk.split( '}' ).pop().trim() )
		.filter( ( sel ) => /\*|:focus-visible/.test( sel ) );
	expect( selectors.length ).toBeGreaterThan( 0 );
	selectors.forEach( ( sel ) => {
		expect( [ sel, sel.includes( '.emcp-legacy' ) ] ).toEqual( [
			sel,
			true,
		] );
	} );
} );

it( 'restores WordPress admin typography inside legacy views', () => {
	expect( css ).toMatch(
		/\.emcp-legacy\s*\)?\s*\{[^}]*font-family:[^}]*-apple-system/
	);
} );
