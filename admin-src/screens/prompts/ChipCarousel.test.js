import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppProviders } from '@emcp/ui';
import { ChipCarousel } from './ChipCarousel';

const fs = require( 'fs' );
const path = require( 'path' );

/**
 * jsdom lays nothing out: give the row a width, a content width and a
 * scrollBy that moves it and fires scroll, as a browser would.
 *
 * @param {number} scrollWidth Content width.
 * @param {number} clientWidth Visible width.
 */
function mount( scrollWidth, clientWidth ) {
	const spies = [
		jest
			.spyOn( window.HTMLElement.prototype, 'scrollWidth', 'get' )
			.mockReturnValue( scrollWidth ),
		jest
			.spyOn( window.HTMLElement.prototype, 'clientWidth', 'get' )
			.mockReturnValue( clientWidth ),
	];
	window.HTMLElement.prototype.scrollBy = function ( opts ) {
		const max = scrollWidth - clientWidth;
		this.scrollLeft = Math.max(
			0,
			Math.min( max, this.scrollLeft + opts.left )
		);
		this.dispatchEvent( new window.Event( 'scroll' ) );
	};
	render(
		<AppProviders>
			<ChipCarousel label="Categories">
				<button type="button">All</button>
				<button type="button">Pets</button>
			</ChipCarousel>
		</AppProviders>
	);
	return () => spies.forEach( ( s ) => s.mockRestore() );
}

describe( 'ChipCarousel', () => {
	afterEach( () => {
		delete window.HTMLElement.prototype.scrollBy;
	} );

	it( 'pages an overflowing row with previous and next, disabled at the ends', async () => {
		const restore = mount( 1000, 400 );
		const prev = screen.getByRole( 'button', {
			name: 'Scroll Categories back',
		} );
		const next = screen.getByRole( 'button', {
			name: 'Scroll Categories forward',
		} );
		const row = screen.getByRole( 'group', { name: 'Categories' } );
		expect( prev ).toBeDisabled();
		expect( next ).toBeEnabled();
		expect( next ).toHaveAttribute( 'aria-controls', row.id );

		await userEvent.click( next );
		// 80% of the visible width, so the last chip stays in view.
		expect( row.scrollLeft ).toBe( 320 );
		expect( prev ).toBeEnabled();

		await userEvent.click( next );
		expect( row.scrollLeft ).toBe( 600 );
		expect( next ).toBeDisabled();

		await userEvent.click( prev );
		expect( row.scrollLeft ).toBe( 280 );
		expect( next ).toBeEnabled();
		restore();
	} );

	it( 'follows a swipe or wheel scroll of the row itself', () => {
		const restore = mount( 1000, 400 );
		const row = screen.getByRole( 'group', { name: 'Categories' } );
		act( () => {
			row.scrollLeft = 600;
			row.dispatchEvent( new window.Event( 'scroll' ) );
		} );
		expect(
			screen.getByRole( 'button', { name: 'Scroll Categories forward' } )
		).toBeDisabled();
		restore();
	} );

	it( 'shows no arrows when the chips fit', () => {
		const restore = mount( 400, 400 );
		expect(
			screen.queryByRole( 'button', { name: /^Scroll/ } )
		).toBeNull();
		expect(
			screen.getByRole( 'button', { name: 'Pets' } )
		).toBeInTheDocument();
		restore();
	} );

	it( 'hides the native scrollbar', () => {
		const css = fs.readFileSync(
			path.join( __dirname, 'prompts.css' ),
			'utf8'
		);
		expect( css ).toMatch(
			/\.eui-prompts__chips\s*\{[^}]*scrollbar-width:\s*none/
		);
		expect( css ).toMatch(
			/\.eui-prompts__chips::-webkit-scrollbar\s*\{[^}]*display:\s*none/
		);
	} );
} );
