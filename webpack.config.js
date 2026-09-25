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
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/admin/build' ),
		clean: true,
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
