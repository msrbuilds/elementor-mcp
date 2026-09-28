import { render, screen, within, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppProviders } from '@emcp/ui';
import { CustomizeDrawer } from './CustomizeDrawer';

const CONTENT = `**Page builder:** Elementor

# Golden Crumb Bakery — artisan bakery landing page

Build a page for "Golden Crumb Bakery".

**Palette — flat colors only, no gradients:**

| Role | Value |
|---|---|
| Surface, light | \`#FBF6EE\` (cream) |
| Accent | \`#C2410C\` (burnt orange) |

**Typography:** Display = **DM Serif Display** (fallback: Georgia). Body = **Nunito Sans**, 16px.

## Content facts (use these verbatim)

**Best sellers:** Sourdough $8 · Croissant $4

**Visit:** 12 Main St · (555) 111-2222

## Standards (non-negotiable)

- One H1.
`;

const prompt = {
	slug: 'bakery',
	category: 'food-dining',
	categoryLabel: 'Food & Dining',
	title: 'Bakery',
	content: CONTENT,
};

function mount( props = {} ) {
	const onClose = jest.fn();
	render(
		<AppProviders>
			<CustomizeDrawer
				prompt={ prompt }
				aiChatUrl="/wp-admin/admin.php?page=emcp-tools-ai-chat"
				onClose={ onClose }
				{ ...props }
			/>
		</AppProviders>
	);
	return {
		onClose,
		drawer: screen.getByRole( 'dialog', { name: 'Customize Bakery' } ),
	};
}

const preview = ( drawer ) =>
	within( drawer ).getByLabelText( 'Customized prompt' ).textContent;

describe( 'CustomizeDrawer', () => {
	beforeEach( () => {
		Object.assign( navigator, {
			clipboard: { writeText: jest.fn( () => Promise.resolve() ) },
		} );
		Object.defineProperty( window, 'isSecureContext', {
			value: true,
			configurable: true,
		} );
		window.localStorage.clear();
	} );

	it( 'offers every part the prompt can change, filled with its values', () => {
		const { drawer } = mount();
		const d = within( drawer );
		expect( d.getByLabelText( 'Page builder' ) ).toHaveValue( 'Elementor' );
		expect( d.getByLabelText( 'Business name' ) ).toHaveValue(
			'Golden Crumb Bakery'
		);
		expect( d.getByLabelText( 'Surface, light hex' ) ).toHaveValue(
			'#FBF6EE'
		);
		expect( d.getByLabelText( 'Accent hex' ) ).toHaveValue( '#C2410C' );
		expect( d.getByLabelText( 'Accent colour' ) ).toHaveValue( '#c2410c' );
		expect( d.getByLabelText( 'Display font' ) ).toHaveValue(
			'DM Serif Display'
		);
		expect( d.getByLabelText( 'Body font' ) ).toHaveValue( 'Nunito Sans' );
		expect( d.getByLabelText( 'Best sellers' ) ).toHaveValue(
			'Sourdough $8 · Croissant $4'
		);
		expect( d.getByLabelText( 'Visit' ) ).toHaveValue(
			'12 Main St · (555) 111-2222'
		);
	} );

	it( 'writes each change into the prompt it shows, copies and hands over', async () => {
		const { drawer } = mount();
		const d = within( drawer );
		await userEvent.selectOptions(
			d.getByLabelText( 'Page builder' ),
			'Bricks'
		);
		const name = d.getByLabelText( 'Business name' );
		await userEvent.clear( name );
		await userEvent.type( name, 'Rye & Co' );
		fireEvent.change( d.getByLabelText( 'Accent colour' ), {
			target: { value: '#2563eb' },
		} );
		const visit = d.getByLabelText( 'Visit' );
		await userEvent.clear( visit );
		await userEvent.type( visit, '9 Oak Ave' );
		const text = preview( drawer );
		expect( text ).toContain( '**Page builder:** Bricks' );
		expect( text ).toContain( '# Rye & Co — artisan bakery' );
		expect( text ).toContain( '`#2563EB` (burnt orange)' );
		expect( text ).toContain( '**Visit:** 9 Oak Ave' );

		await userEvent.click(
			d.getByRole( 'button', { name: 'Copy prompt' } )
		);
		expect( navigator.clipboard.writeText ).toHaveBeenCalledWith( text );

		const link = d.getByRole( 'link', {
			name: 'Use in AI Chat (opens in a new tab)',
		} );
		expect( link ).toHaveAttribute( 'target', '_blank' );
		const id = new URL( link.href ).searchParams.get( 'handoff' );
		link.addEventListener( 'click', ( e ) => e.preventDefault() );
		await userEvent.click( link );
		expect(
			JSON.parse(
				window.localStorage.getItem( 'emcp.aiChat.handoff.' + id )
			).text
		).toBe( text );
	} );

	it( 'a typed hex updates the swatch, and an invalid one is not applied', async () => {
		const { drawer } = mount();
		const d = within( drawer );
		const hex = d.getByLabelText( 'Accent hex' );
		await userEvent.clear( hex );
		await userEvent.type( hex, '#16a34a' );
		expect( d.getByLabelText( 'Accent colour' ) ).toHaveValue( '#16a34a' );
		expect( preview( drawer ) ).toContain( '#16A34A' );
		await userEvent.clear( hex );
		await userEvent.type( hex, 'blue' );
		expect( preview( drawer ) ).toContain( '#C2410C' );
	} );

	it( 'Reset puts every value back', async () => {
		const { drawer } = mount();
		const d = within( drawer );
		const reset = d.getByRole( 'button', { name: 'Reset' } );
		expect( reset ).toBeDisabled();
		const name = d.getByLabelText( 'Business name' );
		await userEvent.type( name, ' Bakehouse' );
		expect( reset ).toBeEnabled();
		await userEvent.click( reset );
		expect( name ).toHaveValue( 'Golden Crumb Bakery' );
		expect( preview( drawer ) ).toBe( CONTENT );
	} );

	it( 'says so when a prompt has nothing to customize', () => {
		const { drawer } = mount( {
			prompt: { ...prompt, content: 'Build me a page.' },
		} );
		expect(
			within( drawer ).getByText(
				'This prompt has no builder, business, colour, font or content fields to change.'
			)
		).toBeInTheDocument();
	} );

	it( 'hides the AI Chat hand-off when AI Chat is off', () => {
		const { drawer } = mount( { aiChatUrl: '' } );
		expect(
			within( drawer ).queryByRole( 'link', { name: /Use in AI Chat/ } )
		).toBeNull();
	} );
} );
