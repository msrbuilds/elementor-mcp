/**
 * Regression tests for the Plan 1A final review findings (overlays, focus and
 * keyboard). Each test is named after the finding it pins.
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from '@wordpress/element';
import { Dialog, Drawer } from './Dialog';
import { Dropdown, Menu } from './Popover';
import { Segmented } from './Choice';

describe( 'review finding 1: Escape in a popover inside a dialog', () => {
	it( 'closes only the popover, not the dialog', async () => {
		const onClose = jest.fn();
		render(
			<Dialog open title="Filters" onClose={ onClose }>
				<Dropdown label="Category">
					<button>Apply</button>
				</Dropdown>
			</Dialog>
		);
		const trigger = screen.getByRole( 'button', { name: /Category/ } );
		await userEvent.click( trigger );
		expect(
			screen.getByRole( 'dialog', { name: 'Category' } )
		).toBeInTheDocument();
		await userEvent.keyboard( '{Escape}' );
		expect(
			screen.queryByRole( 'dialog', { name: 'Category' } )
		).not.toBeInTheDocument();
		expect( onClose ).not.toHaveBeenCalled();
		expect( trigger ).toHaveFocus();
	} );
} );

describe( 'review finding 2: focus trap and roving groups', () => {
	it( 'wraps Tab from a trailing segmented control back inside the drawer', async () => {
		function Harness() {
			const [ v, setV ] = useState( 'a' );
			return (
				<>
					<Drawer open title="Settings" onClose={ () => {} }>
						<button>First</button>
						<Segmented
							label="Mode"
							value={ v }
							onChange={ setV }
							options={ [
								{ value: 'a', label: 'A' },
								{ value: 'b', label: 'B' },
								{ value: 'c', label: 'C' },
							] }
						/>
					</Drawer>
					<button>Outside</button>
				</>
			);
		}
		render( <Harness /> );
		screen.getByRole( 'radio', { name: 'A' } ).focus();
		await userEvent.tab();
		expect(
			screen.getByRole( 'button', { name: 'Outside' } )
		).not.toHaveFocus();
		expect(
			screen.getByRole( 'dialog', { name: 'Settings' } )
		).toContainElement( document.activeElement );
	} );
} );

describe( 'review finding 5: popover panels escape scroll containers', () => {
	it( 'positions the panel with fixed coordinates from the trigger', async () => {
		render(
			<div style={ { overflow: 'auto', height: '40px' } }>
				<Menu
					label="More actions"
					items={ [ { label: 'Delete', onSelect: () => {} } ] }
				/>
			</div>
		);
		const trigger = screen.getByRole( 'button', { name: 'More actions' } );
		trigger.getBoundingClientRect = () => ( {
			top: 100,
			bottom: 130,
			left: 400,
			right: 430,
			width: 30,
			height: 30,
		} );
		await userEvent.click( trigger );
		const panel = screen.getByRole( 'menu' );
		expect( panel.style.position ).toBe( 'fixed' );
		expect( panel.style.top ).toBe( '136px' );
	} );
} );

describe( 'review finding 6: disabled options and the keyboard', () => {
	it( 'skips a disabled option when moving with the arrows', async () => {
		const onChange = jest.fn();
		render(
			<Segmented
				label="Access"
				value="all"
				onChange={ onChange }
				options={ [
					{ value: 'all', label: 'All' },
					{ value: 'pro', label: 'Pro', disabled: true },
					{ value: 'free', label: 'Free' },
				] }
			/>
		);
		screen.getByRole( 'radio', { name: 'All' } ).focus();
		await userEvent.keyboard( '{ArrowRight}' );
		expect( onChange ).not.toHaveBeenCalledWith( 'pro' );
		expect( onChange ).toHaveBeenCalledWith( 'free' );
	} );
} );
