import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'jest-axe';
import { Table, Pagination, pageList } from './Table';

const columns = [
	{ key: 'name', header: 'Snippet' },
	{ key: 'hook', header: 'Runs on', mono: true },
	{
		key: 'actions',
		header: <span className="eui-visually-hidden">Actions</span>,
		align: 'end',
		render: ( r ) => <button>Edit { r.name }</button>,
	},
];

describe( 'Table', () => {
	it( 'renders headers, cells and custom renderers', async () => {
		const { container } = render(
			<Table
				caption="PHP snippets"
				columns={ columns }
				rows={ [ { id: 1, name: 'Reading time', hook: 'init' } ] }
			/>
		);
		const table = screen.getByRole( 'table', { name: 'PHP snippets' } );
		expect( within( table ).getAllByRole( 'columnheader' ) ).toHaveLength(
			3
		);
		expect( screen.getByRole( 'cell', { name: 'init' } ) ).toHaveClass(
			'is-mono'
		);
		expect(
			screen.getByRole( 'button', { name: 'Edit Reading time' } )
		).toBeInTheDocument();
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	it( 'shows the empty state across all columns', () => {
		render(
			<Table columns={ columns } rows={ [] } empty="No snippets yet" />
		);
		const cell = screen.getByText( 'No snippets yet' ).closest( 'td' );
		expect( cell ).toHaveAttribute( 'colspan', '3' );
	} );

	it( 'shows skeleton rows while loading', () => {
		const { container } = render(
			<Table columns={ columns } rows={ [] } loading />
		);
		expect( container.querySelectorAll( '.eui-skeleton' ).length ).toBe(
			3
		);
	} );
} );

describe( 'Pagination', () => {
	it( 'lists pages with gaps', () => {
		expect( pageList( 1, 3 ) ).toEqual( [ 1, 2, 3 ] );
		expect( pageList( 5, 20 ) ).toEqual( [ 1, 'gap', 4, 5, 6, 'gap', 20 ] );
		expect( pageList( 1, 20 ) ).toEqual( [ 1, 2, 'gap', 20 ] );
	} );

	it( 'marks the current page and navigates', async () => {
		const onChange = jest.fn();
		render(
			<Pagination page={ 1 } totalPages={ 2 } onChange={ onChange } />
		);
		expect( screen.getByRole( 'button', { name: '1' } ) ).toHaveAttribute(
			'aria-current',
			'page'
		);
		expect(
			screen.getByRole( 'button', { name: 'Previous page' } )
		).toBeDisabled();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Next page' } )
		);
		expect( onChange ).toHaveBeenCalledWith( 2 );
	} );

	it( 'renders nothing for a single page', () => {
		const { container } = render(
			<Pagination page={ 1 } totalPages={ 1 } onChange={ () => {} } />
		);
		expect( container.firstChild ).toBeNull();
	} );
} );
