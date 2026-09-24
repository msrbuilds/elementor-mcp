import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'jest-axe';
import { Button, IconButton } from './Button';

describe( 'Button', () => {
	it( 'renders a button with variant and size classes', async () => {
		const { container } = render( <Button variant="primary">Save</Button> );
		const btn = screen.getByRole( 'button', { name: 'Save' } );
		expect( btn ).toHaveClass( 'eui-btn', 'eui-btn--primary', 'eui-btn--md' );
		expect( btn ).toHaveAttribute( 'type', 'button' );
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	it( 'is disabled and busy while loading', async () => {
		const onClick = jest.fn();
		render( <Button loading onClick={ onClick }>Save</Button> );
		const btn = screen.getByRole( 'button', { name: 'Save' } );
		expect( btn ).toBeDisabled();
		expect( btn ).toHaveAttribute( 'aria-busy', 'true' );
		await userEvent.click( btn );
		expect( onClick ).not.toHaveBeenCalled();
	} );

	it( 'renders a link when given href', () => {
		render( <Button href="/x">Open</Button> );
		expect( screen.getByRole( 'link', { name: 'Open' } ) ).toHaveAttribute( 'href', '/x' );
	} );
} );

describe( 'IconButton', () => {
	it( 'uses its label as the accessible name and tooltip', async () => {
		const { container } = render( <IconButton icon="x" label="Close" /> );
		const btn = screen.getByRole( 'button', { name: 'Close' } );
		expect( btn ).toHaveAttribute( 'title', 'Close' );
		expect( btn ).toHaveClass( 'eui-iconbtn' );
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	it( 'refuses to render without a label', () => {
		jest.spyOn( console, 'error' ).mockImplementation( () => {} );
		expect( () => render( <IconButton icon="x" /> ) ).toThrow( 'IconButton requires a label' );
		console.error.mockRestore();
	} );
} );
