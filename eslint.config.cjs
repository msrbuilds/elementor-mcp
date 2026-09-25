/**
 * Lint config for the admin UI. @wordpress/scripts 36 points its default unit-test
 * lint rules at Vitest; this project keeps Jest, so the Jest plugin's rules apply
 * to *.test.js files (per the scripts migration guide).
 */
const wpPlugin = require( '@wordpress/eslint-plugin' );
const jestPlugin = require( 'eslint-plugin-jest' );

module.exports = [
	{
		ignores: [
			'**/build/**',
			'**/build-pro/**',
			'**/node_modules/**',
			'**/vendor/**',
		],
	},
	...wpPlugin.configs.recommended,
	{
		languageOptions: {
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [
						require.resolve( '@wordpress/babel-preset-default' ),
					],
				},
			},
		},
	},
	{
		// Screens import the shared library as @emcp/ui; webpack maps it to the
		// emcp-admin-ui handle (window.emcpUI), so it never resolves on disk.
		settings: { 'import/core-modules': [ '@emcp/ui' ] },
	},
	{
		...jestPlugin.configs[ 'flat/recommended' ],
		files: [ '**/*.test.js', 'admin-src/test-setup.js' ],
	},
	{
		files: [
			'admin-build/**/*.js',
			'admin-build/**/*.mjs',
			'webpack.config.js',
			'jest.config.js',
			'eslint.config.cjs',
		],
		languageOptions: {
			globals: {
				require: 'readonly',
				module: 'writable',
				__dirname: 'readonly',
				process: 'readonly',
				console: 'readonly',
			},
		},
		rules: { 'no-console': 'off' },
	},
];
