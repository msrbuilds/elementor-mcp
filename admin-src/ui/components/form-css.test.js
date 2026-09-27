/**
 * Core wp-admin styles text fields with `input[type="text"]` (0,1,1) and
 * `.wp-core-ui select` (0,1,1), and draws its own blue focus ring, so our
 * single-class rules lose. These rules must outrank core.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const form = fs.readFileSync( path.join( __dirname, 'Form.css' ), 'utf8' );
const shell = fs.readFileSync(
	path.join( __dirname, '../../shell/shell.css' ),
	'utf8'
);

describe( 'form field styles outrank core wp-admin', () => {
	it( 'uses a doubled class for inputs, textareas and selects', () => {
		expect( form ).toMatch( /\.eui-input\.eui-input\s*\{/ );
		expect( form ).toMatch( /\.eui-select\.eui-select select\s*\{/ );
	} );

	it( 'draws its own focus ring instead of core’s', () => {
		expect( form ).toMatch(
			/\.eui-input\.eui-input:focus[^{]*\{[^}]*border-color:\s*var\(--emcp-primary\)[^}]*box-shadow:\s*0 0 0 3px var\(--emcp-primary-tint\)/
		);
		expect( form ).toMatch(
			/\.eui-select\.eui-select select:focus[^{]*\{[^}]*border-color:\s*var\(--emcp-primary\)/
		);
	} );

	it( 'resets core notice styles for notices inside the frame', () => {
		expect( shell ).toMatch(
			/\.eui-frame__content > \.notice\.eui-notice\s*\{[^}]*margin:\s*0[^}]*box-shadow:\s*none/
		);
	} );
} );
