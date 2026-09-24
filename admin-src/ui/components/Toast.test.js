import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ToastProvider, useToast } from './Toast';

function Harness() {
	const toast = useToast();
	return (
		<>
			<button onClick={ () => toast.success( 'Settings saved.' ) }>ok</button>
			<button onClick={ () => toast.error( 'Could not save.' ) }>fail</button>
		</>
	);
}

describe( 'toasts', () => {
	beforeEach( () => jest.useFakeTimers() );
	afterEach( () => jest.useRealTimers() );

	it( 'shows success as status and removes it after 5 seconds', async () => {
		const user = userEvent.setup( { advanceTimers: jest.advanceTimersByTime } );
		render( <ToastProvider><Harness /></ToastProvider> );
		await user.click( screen.getByRole( 'button', { name: 'ok' } ) );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Settings saved.' );
		act( () => jest.advanceTimersByTime( 5000 ) );
		expect( screen.queryByText( 'Settings saved.' ) ).not.toBeInTheDocument();
	} );

	it( 'keeps errors as alerts until dismissed', async () => {
		const user = userEvent.setup( { advanceTimers: jest.advanceTimersByTime } );
		render( <ToastProvider><Harness /></ToastProvider> );
		await user.click( screen.getByRole( 'button', { name: 'fail' } ) );
		act( () => jest.advanceTimersByTime( 20000 ) );
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent( 'Could not save.' );
		await user.click( screen.getByRole( 'button', { name: 'Dismiss' } ) );
		expect( screen.queryByRole( 'alert' ) ).not.toBeInTheDocument();
	} );

	it( 'throws a clear error outside the provider', () => {
		jest.spyOn( console, 'error' ).mockImplementation( () => {} );
		expect( () => render( <Harness /> ) ).toThrow( 'useToast must be used inside ToastProvider' );
		console.error.mockRestore();
	} );
} );
