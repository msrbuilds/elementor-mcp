import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'jest-axe';
import { Toggle, Checkbox, Field, TextInput, Textarea, Select } from './Form';

describe( 'Toggle', () => {
	it( 'is a labelled switch that reports the next value', async () => {
		const onChange = jest.fn();
		const { container } = render(
			<Toggle
				checked={ false }
				onChange={ onChange }
				label="OAuth sign-in"
			/>
		);
		const sw = screen.getByRole( 'switch', { name: 'OAuth sign-in' } );
		expect( sw ).toHaveAttribute( 'aria-checked', 'false' );
		await userEvent.click( sw );
		expect( onChange ).toHaveBeenCalledWith( true );
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	it( 'toggles from its visible label too', async () => {
		const onChange = jest.fn();
		render( <Toggle checked onChange={ onChange } label="Compact mode" /> );
		await userEvent.click( screen.getByText( 'Compact mode' ) );
		expect( onChange ).toHaveBeenCalledWith( false );
	} );

	it( 'keeps an accessible name when the label is hidden', () => {
		render(
			<Toggle
				checked
				onChange={ () => {} }
				label="Enable Menu Read"
				hideLabel
			/>
		);
		expect(
			screen.getByRole( 'switch', { name: 'Enable Menu Read' } )
		).toBeInTheDocument();
	} );

	it( 'does nothing when disabled', async () => {
		const onChange = jest.fn();
		render(
			<Toggle
				checked={ false }
				onChange={ onChange }
				label="X"
				disabled
			/>
		);
		await userEvent.click( screen.getByRole( 'switch' ) );
		expect( onChange ).not.toHaveBeenCalled();
	} );
} );

describe( 'Checkbox', () => {
	it( 'reports checked state', async () => {
		const onChange = jest.fn();
		render(
			<Checkbox
				checked={ false }
				onChange={ onChange }
				label="Match regardless of query string"
			/>
		);
		await userEvent.click(
			screen.getByRole( 'checkbox', {
				name: 'Match regardless of query string',
			} )
		);
		expect( onChange ).toHaveBeenCalledWith( true );
	} );
} );

describe( 'Field', () => {
	it( 'wires label, help and error to the control', async () => {
		const { container } = render(
			<Field
				label="Backup name"
				help="A timestamp is used if left blank."
				error="Too long"
				optional
			>
				{ ( p ) => (
					<TextInput { ...p } value="" onChange={ () => {} } />
				) }
			</Field>
		);
		const input = screen.getByRole( 'textbox', { name: /Backup name/ } );
		expect( input ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( input ).toHaveAccessibleDescription(
			'A timestamp is used if left blank. Too long'
		);
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent( 'Too long' );
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	it( 'supports textareas', () => {
		render(
			<Field label="Extra instructions">
				{ ( p ) => (
					<Textarea { ...p } value="a" onChange={ () => {} } />
				) }
			</Field>
		);
		expect(
			screen.getByRole( 'textbox', { name: 'Extra instructions' } )
				.tagName
		).toBe( 'TEXTAREA' );
	} );
} );

describe( 'Select', () => {
	it( 'reports the selected value', async () => {
		const onChange = jest.fn();
		render(
			<Field label="Type">
				{ ( p ) => (
					<Select
						{ ...p }
						value="301"
						onChange={ onChange }
						options={ [
							{ value: '301', label: '301' },
							{ value: '302', label: '302' },
						] }
					/>
				) }
			</Field>
		);
		await userEvent.selectOptions(
			screen.getByRole( 'combobox', { name: 'Type' } ),
			'302'
		);
		expect( onChange ).toHaveBeenCalledWith( '302' );
	} );
} );
