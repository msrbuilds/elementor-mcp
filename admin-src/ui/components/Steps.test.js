import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'jest-axe';
import { Stepper, Step, RadioCardGroup, RadioCard } from './Steps';

describe( 'Stepper', () => {
	it( 'shows the active body, collapses complete steps and locks later ones', async () => {
		const onEdit = jest.fn();
		const { container } = render(
			<Stepper label="Connect an AI client">
				<Step
					number={ 1 }
					title="Choose your AI client"
					meta="Step 1 of 4"
					status="complete"
					summary="Claude Desktop"
					onEdit={ onEdit }
				/>
				<Step number={ 2 } title="Pick how it signs in" status="active">
					Body two
				</Step>
				<Step
					number={ 3 }
					title="Test the connection"
					status="locked"
					summary="Waits for step 2."
				/>
			</Stepper>
		);
		expect( screen.getByText( 'Body two' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Pick how it signs in' ).closest( 'li' )
		).toHaveAttribute( 'aria-current', 'step' );
		await userEvent.click( screen.getByRole( 'button', { name: /Edit/ } ) );
		expect( onEdit ).toHaveBeenCalled();
		expect( await axe( container ) ).toHaveNoViolations();
	} );
} );

describe( 'RadioCard', () => {
	it( 'is a native radio group; locked cards cannot be chosen', async () => {
		const onChange = jest.fn();
		const { container } = render(
			<RadioCardGroup
				legend="Sign-in method"
				name="auth"
				value="oauth"
				onChange={ onChange }
			>
				<RadioCard
					value="oauth"
					title="OAuth"
					description="Sign in through the browser."
				/>
				<RadioCard
					value="app"
					title="Application password"
					description="Paste a password."
				/>
				<RadioCard
					value="bricks"
					title="Bricks"
					disabled
					requirement="Needs Bricks 2.3.13 to 2.4.x"
				/>
			</RadioCardGroup>
		);
		expect( screen.getByRole( 'radio', { name: /OAuth/ } ) ).toBeChecked();
		await userEvent.click(
			screen.getByRole( 'radio', { name: /Application password/ } )
		);
		expect( onChange ).toHaveBeenCalledWith( 'app' );
		expect(
			screen.getByRole( 'radio', { name: /Bricks/ } )
		).toBeDisabled();
		expect(
			screen.getByRole( 'group', { name: 'Sign-in method' } )
		).toBeInTheDocument();
		expect( await axe( container ) ).toHaveNoViolations();
	} );
} );
