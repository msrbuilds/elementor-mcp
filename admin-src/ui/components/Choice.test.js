import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'jest-axe';
import { useState } from '@wordpress/element';
import { Segmented, Tabs, FilterChip } from './Choice';

const opts = [
	{ value: 'all', label: 'All', count: 14 },
	{ value: 'on', label: 'Enabled', count: 10 },
	{ value: 'off', label: 'Disabled', count: 4 },
];

function Harness( { Cmp } ) {
	const [ v, setV ] = useState( 'all' );
	return (
		<Cmp
			label="Filter"
			options={ opts }
			value={ v }
			onChange={ setV }
			idPrefix="t"
		/>
	);
}

describe( 'Segmented', () => {
	it( 'is a radiogroup with one tab stop that arrows select', async () => {
		const { container } = render( <Harness Cmp={ Segmented } /> );
		const radios = screen.getAllByRole( 'radio' );
		expect( radios.map( ( r ) => r.tabIndex ) ).toEqual( [ 0, -1, -1 ] );
		radios[ 0 ].focus();
		await userEvent.keyboard( '{ArrowRight}' );
		expect(
			screen.getByRole( 'radio', { name: /Enabled/ } )
		).toHaveAttribute( 'aria-checked', 'true' );
		expect(
			screen.getByRole( 'radio', { name: /Enabled/ } )
		).toHaveFocus();
		expect( await axe( container ) ).toHaveNoViolations();
	} );
} );

describe( 'Tabs', () => {
	it( 'is a tablist with selected state and panel ids', async () => {
		render( <Harness Cmp={ Tabs } /> );
		const tab = screen.getByRole( 'tab', { name: /Disabled/ } );
		await userEvent.click( tab );
		expect( tab ).toHaveAttribute( 'aria-selected', 'true' );
		expect( tab ).toHaveAttribute( 'aria-controls', 't-panel-off' );
		expect( tab.id ).toBe( 't-tab-off' );
	} );
} );

describe( 'FilterChip', () => {
	it( 'is a toggle button with an optional remove button', async () => {
		const onClick = jest.fn();
		const onRemove = jest.fn();
		const { container } = render(
			<FilterChip
				label="Widgets"
				active
				count={ 48 }
				onClick={ onClick }
				onRemove={ onRemove }
				removeLabel="Remove Widgets filter"
			/>
		);
		expect(
			screen.getByRole( 'button', { name: /Widgets/, pressed: true } )
		).toBeInTheDocument();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Remove Widgets filter' } )
		);
		expect( onRemove ).toHaveBeenCalled();
		expect( onClick ).not.toHaveBeenCalled();
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	it( 'shows a logo image or an icon before the label, both decorative', () => {
		const { container } = render(
			<>
				<FilterChip label="Cursor" image="/img/cursor.png" />
				<FilterChip label="Hermes" icon="plug" />
			</>
		);
		const [ withImage, withIcon ] =
			container.querySelectorAll( '.eui-chip__main' );
		const img = withImage.querySelector( 'img.eui-chip__media' );
		expect( img ).toHaveAttribute( 'src', '/img/cursor.png' );
		expect( img ).toHaveAttribute( 'alt', '' );
		expect( withImage.firstElementChild ).toBe( img );
		expect( withIcon.firstElementChild ).toHaveClass( 'eui-chip__media' );
		expect(
			screen.getByRole( 'button', { name: 'Cursor' } )
		).toBeInTheDocument();
	} );
} );
