/**
 * A Button with an href renders an <a>. Core wp-admin colours links on
 * hover, focus and active with `a:focus` / `a:active` (0,1,1) and adds its
 * own blue box-shadow ring, which beat our single-class variant rules, so a
 * clicked primary link button turned blue on blue. These rules must outrank
 * core.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const css = fs.readFileSync( path.join( __dirname, 'Button.css' ), 'utf8' );

const escape = ( s ) => s.replace( /[-()]/g, '\\$&' );

describe( 'link buttons keep their colours in every state', () => {
	it.each( [
		[ 'primary', 'var(--emcp-surface)' ],
		[ 'secondary', 'var(--emcp-ink-2)' ],
		[ 'outline', 'var(--emcp-primary-strong)' ],
		[ 'danger', 'var(--emcp-danger-fg)' ],
		[ 'ghost', 'var(--emcp-primary-strong)' ],
		[ 'ghost-inverse', 'var(--emcp-surface)' ],
	] )(
		'%s keeps its text colour on hover, focus, active and visited',
		( variant, color ) => {
			const rule = new RegExp(
				`\\.eui-btn\\.eui-btn--${ escape(
					variant
				) }:is\\(:hover, :focus, :active, :visited\\)\\s*\\{[^}]*color:\\s*${ escape(
					color
				) }`
			);
			expect( css ).toMatch( rule );
		}
	);

	it( 'replaces core’s focus box-shadow with our own ring', () => {
		expect( css ).toMatch(
			/\.eui-btn\.eui-btn:focus\s*\{[^}]*box-shadow:\s*none/
		);
		expect( css ).toMatch(
			/\.eui-btn\.eui-btn:focus-visible\s*\{[^}]*outline:\s*2px solid var\(--emcp-primary\)/
		);
	} );
} );
