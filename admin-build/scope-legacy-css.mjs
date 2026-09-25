/**
 * Build assets/admin/build/legacy.css: the legacy admin stylesheet with every
 * selector scoped under .emcp-legacy. Keyframes and font faces pass through.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire( import.meta.url );
const postcss = require( 'postcss' );
const { scopeSelector } = require( './scope-selector.js' );

const root = path.resolve(
	path.dirname( fileURLToPath( import.meta.url ) ),
	'..'
);
const input = path.join( root, 'admin-src/legacy/admin.css' );
const output = path.join( root, 'assets/admin/build/legacy.css' );

const scope = () => ( {
	postcssPlugin: 'emcp-scope-legacy',
	Rule( rule ) {
		const parent = rule.parent;
		if (
			parent &&
			'atrule' === parent.type &&
			/keyframes$/i.test( parent.name )
		) {
			return;
		}
		rule.selector = scopeSelector( rule.selector );
	},
} );
scope.postcss = true;

// The source sat in assets/css/ and loads assets/fonts/Geist-Variable.woff2 as
// "../fonts/..."; the output sits two levels deeper (assets/admin/build/).
const css = readFileSync( input, 'utf8' ).replace(
	/url\(\s*(["']?)\.\.\/fonts\//g,
	'url($1../../fonts/'
);
const result = await postcss( [ scope ] ).process( css, {
	from: input,
	to: output,
} );
writeFileSync( output, result.css );
console.log(
	`legacy.css: ${ result.root.nodes.length } top-level nodes scoped`
);
