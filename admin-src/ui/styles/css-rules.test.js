import fs from 'fs';
import path from 'path';

const uiDir = path.join( __dirname, '..' );
function cssFiles( dir ) {
	return fs.readdirSync( dir, { withFileTypes: true } ).flatMap( ( e ) => {
		const full = path.join( dir, e.name );
		if ( e.isDirectory() ) {
			return cssFiles( full );
		}
		return e.name.endsWith( '.css' ) ? [ full ] : [];
	} );
}
const files = cssFiles( uiDir ).map( ( f ) => [ path.relative( uiDir, f ), fs.readFileSync( f, 'utf8' ) ] );

describe( 'component CSS rules', () => {
	it( 'has CSS files to check', () => {
		expect( files.length ).toBeGreaterThan( 0 );
	} );

	it.each( files )( '%s never styles legacy emcp- classes', ( name, css ) => {
		const selectors = css.replace( /\/\*[\s\S]*?\*\//g, '' ).match( /(^|[,}\s])\.emcp-[a-z-]+/gm ) || [];
		const allowed = selectors.filter( ( s ) => ! /\.emcp-app\b/.test( s ) );
		expect( allowed ).toEqual( [] );
	} );

	it.each( files )( '%s uses no gradients', ( name, css ) => {
		expect( css ).not.toMatch( /gradient\(/i );
	} );

	it.each( files )( '%s uses logical properties only', ( name, css ) => {
		const body = css.replace( /\/\*[\s\S]*?\*\//g, '' );
		expect( body ).not.toMatch( /(margin|padding|border)-(left|right)\s*:/ );
		expect( body ).not.toMatch( /(^|[\s;{])(left|right)\s*:/m );
		expect( body ).not.toMatch( /text-align:\s*(left|right)/ );
	} );
} );
