import fs from 'fs';
import path from 'path';

function sources( dir ) {
	return fs.readdirSync( dir, { withFileTypes: true } ).flatMap( ( e ) => {
		const full = path.join( dir, e.name );
		if ( e.isDirectory() ) {
			return sources( full );
		}
		return /\.js$/.test( e.name ) && ! /\.test\.js$/.test( e.name )
			? [ full ]
			: [];
	} );
}

it( 'uses dangerouslySetInnerHTML only in Code.js (SafeHtml)', () => {
	const root = path.join( __dirname, '..', '..' );
	const offenders = sources( root ).filter( ( f ) =>
		fs.readFileSync( f, 'utf8' ).includes( 'dangerouslySetInnerHTML' )
	);
	expect( offenders.map( ( f ) => path.relative( root, f ) ) ).toEqual( [
		path.join( 'ui', 'components', 'Code.js' ),
	] );
} );
