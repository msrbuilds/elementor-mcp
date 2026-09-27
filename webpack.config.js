const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const {
	requestToExternal,
	requestToHandle,
} = require( './admin-build/externals' );

module.exports = {
	...defaultConfig,
	entry: {
		ui: {
			import: './admin-src/ui/index.js',
			library: { name: 'emcpUI', type: 'window' },
		},
		fallback: './admin-src/fallback/index.js',
		shell: './admin-src/shell/index.js',
		'screen-locked': './admin-src/screens/locked/index.js',
		'screen-tools': './admin-src/screens/tools/index.js',
		'screen-builders': './admin-src/screens/builders/index.js',
		'screen-modules': './admin-src/screens/modules/index.js',
		'screen-connection': './admin-src/screens/connection/index.js',
		'screen-prompts': './admin-src/screens/prompts/index.js',
		'screen-brand-kits': './admin-src/screens/brand-kits/index.js',
		'screen-marketplace': './admin-src/screens/marketplace/index.js',
		'screen-context': './admin-src/screens/context/index.js',
		'screen-sandbox': './admin-src/screens/sandbox/index.js',
		'screen-snippets': './admin-src/screens/snippets/index.js',
		'screen-history': './admin-src/screens/history/index.js',
		'screen-dashboard': './admin-src/screens/dashboard/index.js',
		'screen-redirects': './admin-src/screens/redirects/index.js',
		'screen-log': './admin-src/screens/log/index.js',
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/admin/build' ),
		clean: true,
	},
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...( defaultConfig.resolve && defaultConfig.resolve.alias ),
			// Shared Sandbox list code: bundled into each screen that imports it
			// (the Pro config inherits this through its spread of this file).
			'@emcp/sandbox': path.resolve( __dirname, 'admin-src/shared/sandbox' ),
		},
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			requestToExternal,
			requestToHandle,
		} ),
	],
};
