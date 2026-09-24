import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'jest-axe';
import { SaveBar } from './SaveBar';

describe( 'SaveBar', () => {
	it( 'is hidden without changes', () => {
		const { container } = render( <SaveBar count={ 0 } onSave={ () => {} } onDiscard={ () => {} } /> );
		expect( container.firstChild ).toBeNull();
	} );

	it( 'shows the count, hint and actions', async () => {
		const onSave = jest.fn();
		const onDiscard = jest.fn();
		const { container } = render( <SaveBar count={ 2 } hint="Reconnect your client after saving" onSave={ onSave } onDiscard={ onDiscard } /> );
		expect( screen.getByRole( 'region', { name: 'Unsaved changes' } ) ).toHaveTextContent( '2 unsaved changes' );
		await userEvent.click( screen.getByRole( 'button', { name: 'Save changes' } ) );
		await userEvent.click( screen.getByRole( 'button', { name: 'Discard' } ) );
		expect( onSave ).toHaveBeenCalled();
		expect( onDiscard ).toHaveBeenCalled();
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	it( 'uses the singular for one change', () => {
		render( <SaveBar count={ 1 } onSave={ () => {} } onDiscard={ () => {} } saveLabel="Save modules" /> );
		expect( screen.getByText( '1 unsaved change' ) ).toBeInTheDocument();
		expect( screen.getByRole( 'button', { name: 'Save modules' } ) ).toBeInTheDocument();
	} );
} );
