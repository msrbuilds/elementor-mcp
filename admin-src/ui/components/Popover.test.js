import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'jest-axe';
import { Menu, Dropdown } from './Popover';

describe( 'Menu', () => {
	const items = [
		{ label: 'Export', onSelect: jest.fn() },
		{ label: 'Save to Cloud', onSelect: jest.fn() },
		{ label: 'Delete', onSelect: jest.fn(), danger: true },
	];

	it( 'opens with focus on the first item and arrows move', async () => {
		const { container } = render(
			<Menu label="More actions" items={ items } />
		);
		const trigger = screen.getByRole( 'button', { name: 'More actions' } );
		await userEvent.click( trigger );
		expect( trigger ).toHaveAttribute( 'aria-expanded', 'true' );
		expect(
			screen.getByRole( 'menuitem', { name: 'Export' } )
		).toHaveFocus();
		await userEvent.keyboard( '{ArrowDown}' );
		expect(
			screen.getByRole( 'menuitem', { name: 'Save to Cloud' } )
		).toHaveFocus();
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	it( 'can show its label as a text button with the icon after it', async () => {
		const { container } = render(
			<Menu
				label="Bulk Actions"
				icon="chevron-down"
				showLabel
				items={ items }
			/>
		);
		const trigger = screen.getByRole( 'button', { name: 'Bulk Actions' } );
		expect( trigger ).toHaveClass( 'eui-btn', 'eui-menu__trigger' );
		expect( trigger.textContent ).toBe( 'Bulk Actions' );
		expect(
			trigger.querySelector( '.eui-btn__label + .eui-icon' )
		).not.toBeNull();
		await userEvent.click( trigger );
		expect( trigger ).toHaveAttribute( 'aria-expanded', 'true' );
		expect(
			screen.getByRole( 'menuitem', { name: 'Export' } )
		).toHaveFocus();
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	it( 'closes on Escape and returns focus to the trigger', async () => {
		render( <Menu label="More actions" items={ items } /> );
		const trigger = screen.getByRole( 'button', { name: 'More actions' } );
		await userEvent.click( trigger );
		await userEvent.keyboard( '{Escape}' );
		expect( screen.queryByRole( 'menu' ) ).not.toBeInTheDocument();
		expect( trigger ).toHaveFocus();
	} );

	it( 'runs the chosen item and closes', async () => {
		render( <Menu label="More actions" items={ items } /> );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'More actions' } )
		);
		await userEvent.click(
			screen.getByRole( 'menuitem', { name: 'Delete' } )
		);
		expect( items[ 2 ].onSelect ).toHaveBeenCalled();
		expect( screen.queryByRole( 'menu' ) ).not.toBeInTheDocument();
	} );

	it( 'closes on an outside click without stealing focus', async () => {
		render(
			<>
				<Menu label="More actions" items={ items } />
				<button>Elsewhere</button>
			</>
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'More actions' } )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Elsewhere' } )
		);
		expect( screen.queryByRole( 'menu' ) ).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Elsewhere' } )
		).toHaveFocus();
	} );
} );

describe( 'Dropdown', () => {
	it( 'shows a panel with its badge count and closes from inside', async () => {
		render(
			<Dropdown label="Category" badge={ 1 }>
				{ ( { close } ) => <button onClick={ close }>Apply</button> }
			</Dropdown>
		);
		const trigger = screen.getByRole( 'button', { name: /Category/ } );
		expect( trigger ).toHaveTextContent( '1' );
		await userEvent.click( trigger );
		expect(
			screen.getByRole( 'dialog', { name: 'Category' } )
		).toBeInTheDocument();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Apply' } )
		);
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect( trigger ).toHaveFocus();
	} );
} );
