/**
 * Jest for the admin UI. @wordpress/scripts 36 defaults test-unit-js to
 * Vitest; this project keeps Jest through test-unit-jest and the published
 * WordPress preset, as the scripts migration guide documents.
 */
module.exports = {
	preset: '@wordpress/jest-preset-default',
	rootDir: __dirname,
	roots: [ '<rootDir>/admin-src', '<rootDir>/admin-build' ].concat(
		require( 'fs' ).existsSync( __dirname + '/pro/admin-src' )
			? [ '<rootDir>/pro/admin-src' ]
			: []
	),
	transform: {
		'\\.[jt]sx?$': [ 'babel-jest', { presets: [ '@wordpress/babel-preset-default' ] } ],
	},
	setupFilesAfterEnv: [ '<rootDir>/admin-src/test-setup.js' ],
	moduleNameMapper: {
		'\\.css$': '<rootDir>/admin-src/test-style-stub.js',
		'^@emcp/ui$': '<rootDir>/admin-src/ui/index.js',
		'^@emcp/sandbox$': '<rootDir>/admin-src/shared/sandbox/index.js',
	},
};
