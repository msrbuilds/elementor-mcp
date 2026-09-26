import { render, screen } from '@testing-library/react';
import { DiffView } from './DiffView';

describe( 'DiffView', () => {
	it( 'renders added and removed lines in a focusable block', () => {
		render(
			<DiffView
				diff={ { kind: 'text', before: 'a\nb', after: 'a\nc' } }
			/>
		);
		const block = screen.getByRole( 'region', { name: 'Difference' } );
		expect( block ).toHaveAttribute( 'tabindex', '0' );
		expect( screen.getByText( 'b' ).closest( '.is-del' ) ).not.toBeNull();
		expect( screen.getByText( 'c' ).closest( '.is-add' ) ).not.toBeNull();
		expect( screen.getByText( 'Removed:' ) ).toHaveClass(
			'screen-reader-text'
		);
	} );

	it( 'says when there is nothing to preview', () => {
		render(
			<DiffView
				diff={ {
					kind: 'none',
					before: '',
					after: '',
					reason: 'binary',
				} }
			/>
		);
		expect( screen.getByText( 'No preview' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'This file is binary.' )
		).toBeInTheDocument();
	} );

	it( 'says when nothing changed', () => {
		render(
			<DiffView diff={ { kind: 'json', before: '{}', after: '{}' } } />
		);
		expect(
			screen.getByText( 'The current value matches the saved one.' )
		).toBeInTheDocument();
	} );
} );
