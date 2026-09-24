import { render } from '@testing-library/react';
import names from '../icon-names.json';
import { ICONS } from '../icons';
import { Icon } from './Icon';

describe( 'Icon', () => {
	it( 'has a component for every listed name, and nothing else', () => {
		expect( Object.keys( ICONS ).sort() ).toEqual( [ ...names ].sort() );
		Object.entries( ICONS ).forEach( ( [ name, Cmp ] ) => {
			expect( [ name, typeof Cmp ] ).toEqual( [ name, expect.stringMatching( /function|object/ ) ] );
		} );
	} );

	it( 'renders a decorative svg by default', () => {
		const { container } = render( <Icon name="plug" /> );
		const svg = container.querySelector( 'svg' );
		expect( svg ).toHaveAttribute( 'aria-hidden', 'true' );
		expect( svg ).toHaveClass( 'eui-icon' );
	} );

	it( 'renders a labelled image when given a label', () => {
		const { getByRole } = render( <Icon name="lock" label="Locked" /> );
		expect( getByRole( 'img', { name: 'Locked' } ) ).toBeInTheDocument();
	} );

	it( 'renders nothing for an unknown name', () => {
		const { container } = render( <Icon name="not-an-icon" /> );
		expect( container.firstChild ).toBeNull();
	} );
} );
