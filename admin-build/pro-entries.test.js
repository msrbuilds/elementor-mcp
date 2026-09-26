const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { proEntries } = require( './pro-entries' );

describe( 'proEntries', () => {
	let dir;
	beforeEach( () => {
		dir = fs.mkdtempSync( path.join( os.tmpdir(), 'emcp-pro-' ) );
	} );
	afterEach( () => fs.rmSync( dir, { recursive: true, force: true } ) );

	it( 'maps each screen folder with an index.js to a screen-<id> entry', () => {
		fs.mkdirSync( path.join( dir, 'templates' ) );
		fs.writeFileSync( path.join( dir, 'templates', 'index.js' ), '' );
		fs.mkdirSync( path.join( dir, 'notes' ) );
		fs.writeFileSync( path.join( dir, 'notes', 'README.md' ), '' );
		expect( proEntries( dir, './admin-src/screens' ) ).toEqual( {
			'screen-templates': './admin-src/screens/templates/index.js',
		} );
	} );

	it( 'returns no entries when the folder is missing (free clone)', () => {
		expect( proEntries( path.join( dir, 'missing' ), './x' ) ).toEqual(
			{}
		);
	} );
} );
