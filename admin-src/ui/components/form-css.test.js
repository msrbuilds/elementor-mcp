/**
 * Core wp-admin styles text fields with `input[type="text"]` (0,1,1) and
 * `.wp-core-ui select` (0,1,1), and draws its own blue focus ring, so our
 * single-class rules lose. These rules must outrank core.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const form = fs.readFileSync( path.join( __dirname, 'Form.css' ), 'utf8' );
const layout = fs.readFileSync( path.join( __dirname, 'Layout.css' ), 'utf8' );
const steps = fs.readFileSync( path.join( __dirname, 'Steps.css' ), 'utf8' );
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

	// Core shows disabled radios at opacity .7 with input[type=radio]:disabled
	// (0,2,1), which beat a two-class hide: the native radio then sat over the
	// custom dot on every unavailable card.
	it( 'keeps the native radio hidden over core’s disabled style', () => {
		expect( steps ).toMatch(
			/\.eui-radio-card \.eui-radio-card__input\.eui-radio-card__input\s*\{[^}]*opacity:\s*0[^}]*clip-path:\s*inset\(50%\)/
		);
	} );

	it( 'keeps the requirement lock full size on wrapped lines', () => {
		expect( steps ).toMatch(
			/\.eui-radio-card__req \.eui-icon\s*\{[^}]*flex:\s*none/
		);
	} );

	it( 'resets core notice styles for notices inside the frame', () => {
		expect( shell ).toMatch(
			/\.eui-frame__content > \.notice\.eui-notice\s*\{[^}]*margin:\s*0[^}]*box-shadow:\s*none/
		);
	} );

	it( 'keeps the page title bold over core’s .wrap h1 (0,1,1)', () => {
		expect( layout ).toMatch(
			/\.eui-page-header__title\.eui-page-header__title\s*\{[^}]*padding:\s*0[^}]*font-weight:\s*700/
		);
	} );
} );
