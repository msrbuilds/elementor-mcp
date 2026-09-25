import fs from 'fs';
import path from 'path';

const read = ( file ) =>
	fs
		.readFileSync( path.join( __dirname, file ), 'utf8' )
		.replace( /\/\*[\s\S]*?\*\//g, '' );

describe.each( [ 'shell.css', 'palette.css' ] )(
	'frame CSS rules (%s)',
	( file ) => {
		const css = read( file );

		it( 'uses no gradients', () => {
			expect( css ).not.toMatch( /gradient\(/i );
		} );

		it( 'uses logical properties only', () => {
			expect( css ).not.toMatch(
				/(margin|padding|border)-(left|right)\s*:/
			);
			expect( css ).not.toMatch( /(^|[\s;{])(left|right)\s*:/m );
		} );

		it( 'never styles legacy emcp- classes except the scoping classes', () => {
			const hits = (
				css.match( /(^|[,}\s])\.emcp-[a-z-]+/gm ) || []
			).filter(
				( s ) => ! /\.emcp-(app|legacy|admin-frame)\b/.test( s )
			);
			expect( hits ).toEqual( [] );
		} );
	}
);
