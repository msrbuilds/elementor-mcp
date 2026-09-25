import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'jest-axe';
import { SearchInput } from './SearchInput';

describe( 'SearchInput (review finding 7)', () => {
	beforeEach( () => jest.useFakeTimers() );
	afterEach( () => jest.useRealTimers() );

	it( 'is a labelled search box that reports after the debounce', async () => {
		const onChange = jest.fn();
		const user = userEvent.setup( {
			advanceTimers: jest.advanceTimersByTime,
		} );
		const { container } = render(
			<SearchInput
				label="Search tools"
				value=""
				onChange={ onChange }
				debounce={ 250 }
			/>
		);
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search tools' } ),
			'menu'
		);
		expect( onChange ).not.toHaveBeenCalled();
		act( () => jest.advanceTimersByTime( 250 ) );
		expect( onChange ).toHaveBeenLastCalledWith( 'menu' );
		expect( onChange ).toHaveBeenCalledTimes( 1 );
		jest.useRealTimers();
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	it( 'clears immediately with the clear button', async () => {
		const onChange = jest.fn();
		const user = userEvent.setup( {
			advanceTimers: jest.advanceTimersByTime,
		} );
		render(
			<SearchInput
				label="Search tools"
				value="menu"
				onChange={ onChange }
				debounce={ 250 }
			/>
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Clear search' } )
		);
		expect( onChange ).toHaveBeenCalledWith( '' );
		expect( screen.getByRole( 'searchbox' ) ).toHaveValue( '' );
	} );

	it( 'follows a new value from the parent', () => {
		const { rerender } = render(
			<SearchInput label="Search" value="a" onChange={ () => {} } />
		);
		rerender(
			<SearchInput label="Search" value="b" onChange={ () => {} } />
		);
		expect( screen.getByRole( 'searchbox' ) ).toHaveValue( 'b' );
	} );
} );
