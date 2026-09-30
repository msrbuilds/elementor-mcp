/**
 * Admin-wide CSS guards: every token a stylesheet uses exists, the hidden
 * attribute always hides, scrollbars are ours, and sidebar links draw our
 * focus ring instead of core's.
 */
import fs from 'fs';
import path from 'path';

const root = path.join( __dirname, '../../..' );
function cssFiles( dir ) {
	if ( ! fs.existsSync( dir ) ) {
		return [];
	}
	return fs.readdirSync( dir, { withFileTypes: true } ).flatMap( ( e ) => {
		const full = path.join( dir, e.name );
		if ( e.isDirectory() ) {
			return cssFiles( full );
		}
		return e.name.endsWith( '.css' ) ? [ full ] : [];
	} );
}
const sources = [
	...cssFiles( path.join( root, 'admin-src' ) ),
	...cssFiles( path.join( root, 'pro/admin-src' ) ),
].map( ( f ) => [
	path.relative( root, f ),
	fs.readFileSync( f, 'utf8' ).replace( /\/\*[\s\S]*?\*\//g, '' ),
] );
const read = ( rel ) => fs.readFileSync( path.join( root, rel ), 'utf8' );

describe( 'admin CSS', () => {
	it( 'uses only custom properties that are declared somewhere', () => {
		const declared = new Set();
		sources.forEach( ( [ , css ] ) =>
			( css.match( /--emcp-[a-z0-9-]+(?=\s*:)/g ) || [] ).forEach(
				( n ) => declared.add( n )
			)
		);
		const missing = [];
		sources.forEach( ( [ file, css ] ) => {
			// A var() with a fallback is allowed to be undeclared.
			( css.match( /var\(\s*--emcp-[a-z0-9-]+\s*\)/g ) || [] ).forEach(
				( use ) => {
					const name = use.match( /--emcp-[a-z0-9-]+/ )[ 0 ];
					if ( ! declared.has( name ) ) {
						missing.push( `${ file }: ${ name }` );
					}
				}
			);
		} );
		expect( missing ).toEqual( [] );
	} );

	it( 'makes the hidden attribute win over display rules', () => {
		expect( read( 'admin-src/ui/styles/base.css' ) ).toMatch(
			/:where\(\.emcp-app, \.eui-portal\) \[hidden\]\s*\{\s*display:\s*none !important;/
		);
	} );

	it( 'draws thin accent scrollbars on EMCP screens', () => {
		const base = read( 'admin-src/ui/styles/base.css' );
		expect( base ).toMatch( /scrollbar-width:\s*thin/ );
		expect( base ).toMatch(
			/scrollbar-color:\s*var\(--emcp-scrollbar\) transparent/
		);
		expect( read( 'admin-src/ui/styles/tokens.css' ) ).toMatch(
			/--emcp-scrollbar:/
		);
		// The page itself scrolls on html, outside .emcp-app.
		expect( read( 'admin-src/shell/shell.css' ) ).toMatch(
			/html:has\(\.eui-frame\)\s*\{[^}]*scrollbar-color/
		);
	} );

	it( 'puts the sidebar toggle in the top bar, divided from the breadcrumb', () => {
		const shell = read( 'admin-src/shell/shell.css' );
		// An in-flow top-bar icon button, not a handle floating over the rail.
		expect( shell ).not.toMatch(
			/\.eui-frame-collapse\.eui-frame-iconlink\s*\{[^}]*position:\s*absolute/
		);
		expect( shell ).toMatch(
			/\.eui-frame-collapse \+ nav\s*\{[^}]*border-inline-start:\s*1px solid var\(--emcp-border\)/
		);
		// The actions still sit at the end once the bar is no longer space-between.
		expect( shell ).toMatch(
			/\.eui-frame__topbar-actions\s*\{[^}]*margin-inline-start:\s*auto/
		);
	} );

	it( 'replaces core’s focus box-shadow on sidebar links with our ring', () => {
		const shell = read( 'admin-src/shell/shell.css' );
		expect( shell ).toMatch(
			/\.eui-frame-nav__item:focus\s*\{[^}]*box-shadow:\s*none[^}]*outline:\s*none/
		);
		expect( shell ).toMatch(
			/\.eui-frame-nav__item:focus-visible\s*\{[^}]*outline:\s*2px solid var\(--emcp-primary\)/
		);
	} );
} );
