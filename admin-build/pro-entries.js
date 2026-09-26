const fs = require( 'fs' );
const path = require( 'path' );

/**
 * Webpack entries for the Pro screens: one per `<screensDir>/<id>/index.js`.
 *
 * @param {string} screensDir Absolute folder holding one folder per screen.
 * @param {string} relPrefix  Entry path prefix, relative to the webpack context.
 * @return {Object<string,string>} Entry name to entry path.
 */
function proEntries( screensDir, relPrefix ) {
	if ( ! fs.existsSync( screensDir ) ) {
		return {};
	}
	const entries = {};
	for ( const id of fs.readdirSync( screensDir ).sort() ) {
		if ( fs.existsSync( path.join( screensDir, id, 'index.js' ) ) ) {
			entries[ `screen-${ id }` ] = `${ relPrefix }/${ id }/index.js`;
		}
	}
	return entries;
}

module.exports = { proEntries };
