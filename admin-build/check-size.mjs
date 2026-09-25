/** Prints gzipped sizes of the admin bundles and fails when one is over budget. */
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync } from 'node:zlib';

const require = createRequire( import.meta.url );
const { budgetFor, overBudget } = require( './budgets.js' );
const root = path.resolve(
	path.dirname( fileURLToPath( import.meta.url ) ),
	'..'
);
const dirs = [ 'assets/admin/build', 'pro/assets/admin/build-pro' ]
	.map( ( d ) => path.join( root, d ) )
	.filter( existsSync );

const files = dirs.flatMap( ( dir ) =>
	readdirSync( dir )
		.filter( ( f ) => f.endsWith( '.js' ) )
		.map( ( f ) => ( {
			name: f.replace( /\.js$/, '' ),
			gzip: gzipSync( readFileSync( path.join( dir, f ) ) ).length,
		} ) )
);
files.forEach( ( f ) => {
	const budget = budgetFor( f.name );
	console.log(
		`${ f.name.padEnd( 24 ) } ${ ( f.gzip / 1024 ).toFixed( 1 ).padStart( 7 ) } KB${ budget ? ` / ${ budget / 1024 } KB` : '' }`
	);
} );
const over = overBudget( files );
if ( over.length ) {
	console.error(
		`Over budget: ${ over.map( ( f ) => f.name ).join( ', ' ) }`
	);
	process.exit( 1 );
}
