import { render, screen, within } from '@testing-library/react';
import { axe } from 'jest-axe';
import { Meter, BarChart, HBarList } from './Charts';

const series = [ { key: 'kept', label: 'Changes kept', colorVar: '--emcp-primary' }, { key: 'rolledBack', label: 'Rolled back', colorVar: '--emcp-primary-soft' } ];
const data = [ { label: 'Sep 11', values: { kept: 4, rolledBack: 1 } }, { label: 'Sep 12', values: { kept: 8, rolledBack: 0 } }, { label: 'Today', values: { kept: 0, rolledBack: 0 } } ];

describe( 'charts', () => {
	it( 'Meter exposes value and warns past the threshold', async () => {
		const { container } = render( <Meter label="Context size" value={ 9000 } max={ 8000 } valueText="9k of 8k tokens" warnAt={ 8000 } /> );
		const meter = screen.getByRole( 'meter', { name: 'Context size' } );
		expect( meter ).toHaveAttribute( 'aria-valuetext', '9k of 8k tokens' );
		expect( container.firstChild ).toHaveClass( 'is-warning' );
		expect( container.querySelector( '.eui-meter__fill' ).style.width ).toBe( '100%' );
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	it( 'BarChart stacks series and exposes the numbers as a table', async () => {
		const { container } = render( <BarChart label="AI activity, last 3 days" data={ data } series={ series } /> );
		expect( container.querySelector( 'svg' ) ).toHaveAttribute( 'aria-hidden', 'true' );
		expect( container.querySelectorAll( 'rect' ).length ).toBe( 6 );
		const table = screen.getByRole( 'table', { name: 'AI activity, last 3 days' } );
		expect( within( table ).getByRole( 'row', { name: /Sep 11 4 1/ } ) ).toBeInTheDocument();
		expect( screen.getByText( 'Changes kept', { selector: '.eui-chart__key' } ) ).toBeInTheDocument();
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	it( 'BarChart handles all-zero data without dividing by zero', () => {
		const { container } = render( <BarChart label="Empty" data={ [ { label: 'Today', values: { kept: 0 } } ] } series={ [ series[ 0 ] ] } /> );
		expect( container.querySelector( 'rect' ).getAttribute( 'height' ) ).toBe( '0' );
	} );

	it( 'HBarList scales bars to the largest value', () => {
		const { container } = render( <HBarList label="Most used tools" items={ [ { label: 'content delete-post', value: 32 }, { label: 'elementor get-page', value: 16 } ] } /> );
		const fills = container.querySelectorAll( '.eui-hbars__fill' );
		expect( fills[ 0 ].style.width ).toBe( '100%' );
		expect( fills[ 1 ].style.width ).toBe( '50%' );
		expect( screen.getByRole( 'list', { name: 'Most used tools' } ) ).toBeInTheDocument();
	} );
} );
