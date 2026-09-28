import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'jest-axe';
import { useState } from '@wordpress/element';
import { Dialog, Drawer, ConfirmProvider, useConfirm } from './Dialog';

function DialogHarness( { Cmp = Dialog } ) {
	const [ open, setOpen ] = useState( false );
	return (
		<>
			<button onClick={ () => setOpen( true ) }>Open</button>
			<Cmp
				open={ open }
				title="Review code"
				onClose={ () => setOpen( false ) }
				footer={ <button>Done</button> }
			>
				<input aria-label="First field" />
			</Cmp>
		</>
	);
}

describe( 'Dialog and Drawer', () => {
	// The panel portals to <body>, where a <footer> is a second contentinfo
	// landmark beside core's #wpfooter (axe landmark-no-duplicate-contentinfo).
	it( 'renders its footer without a landmark element', async () => {
		render( <DialogHarness Cmp={ Drawer } /> );
		await userEvent.click( screen.getByRole( 'button', { name: 'Open' } ) );
		expect( document.querySelector( '.eui-panel__foot' ).tagName ).toBe(
			'DIV'
		);
		expect( screen.queryByRole( 'contentinfo' ) ).toBeNull();
	} );

	it.each( [
		[ 'Dialog', Dialog ],
		[ 'Drawer', Drawer ],
	] )(
		'%s traps focus, closes on Escape and restores focus',
		async ( name, Cmp ) => {
			render( <DialogHarness Cmp={ Cmp } /> );
			const opener = screen.getByRole( 'button', { name: 'Open' } );
			await userEvent.click( opener );
			const dialog = screen.getByRole( 'dialog', {
				name: 'Review code',
			} );
			expect( dialog ).toHaveAttribute( 'aria-modal', 'true' );
			expect( dialog.closest( '.eui-portal' ) ).not.toBeNull();
			expect(
				screen.getByRole( 'button', { name: 'Close' } )
			).toHaveFocus();
			await userEvent.tab();
			await userEvent.tab();
			await userEvent.tab();
			expect( dialog ).toContainElement( document.activeElement );
			await userEvent.keyboard( '{Escape}' );
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
			expect( opener ).toHaveFocus();
		}
	);

	it( 'has no axe violations when open', async () => {
		render(
			<Dialog open title="Review code" onClose={ () => {} }>
				Body
			</Dialog>
		);
		expect( await axe( document.body ) ).toHaveNoViolations();
	} );
} );

function ConfirmHarness( { onResult, options } ) {
	const confirm = useConfirm();
	return (
		<button onClick={ async () => onResult( await confirm( options ) ) }>
			Delete
		</button>
	);
}

describe( 'useConfirm', () => {
	it( 'resolves true on confirm and false on cancel', async () => {
		const onResult = jest.fn();
		render(
			<ConfirmProvider>
				<ConfirmHarness
					onResult={ onResult }
					options={ {
						title: 'Delete snippet?',
						message: 'This cannot be undone.',
						confirmLabel: 'Delete',
						tone: 'danger',
					} }
				/>
			</ConfirmProvider>
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Delete' } )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Cancel' } )
		);
		await waitFor( () =>
			expect( onResult ).toHaveBeenLastCalledWith( false )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Delete' } )
		);
		await userEvent.click(
			screen.getAllByRole( 'button', { name: 'Delete' } ).pop()
		);
		await waitFor( () =>
			expect( onResult ).toHaveBeenLastCalledWith( true )
		);
	} );

	it( 'requires typed confirmation when asked', async () => {
		const onResult = jest.fn();
		render(
			<ConfirmProvider>
				<ConfirmHarness
					onResult={ onResult }
					options={ {
						title: 'Clear log?',
						confirmLabel: 'Clear log',
						requireText: 'CLEAR',
					} }
				/>
			</ConfirmProvider>
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Delete' } )
		);
		const confirmBtn = screen.getByRole( 'button', { name: 'Clear log' } );
		expect( confirmBtn ).toBeDisabled();
		await userEvent.type( screen.getByRole( 'textbox' ), 'CLEAR' );
		expect( confirmBtn ).toBeEnabled();
	} );
} );
