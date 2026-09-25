import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'jest-axe';
import {
	Card,
	PageHeader,
	Badge,
	Notice,
	EmptyState,
	Skeleton,
} from './Layout';

describe( 'layout primitives', () => {
	it( 'PageHeader renders an h1 with tier tag, description, actions and back link', async () => {
		const { container } = render(
			<PageHeader
				title="PHP Snippets"
				tier="free"
				description="Run small pieces of PHP."
				actions={ <button>Add snippet</button> }
				back={ { href: '/sb', label: 'Sandbox' } }
			/>
		);
		expect( screen.getByRole( 'heading', { level: 1 } ) ).toHaveTextContent(
			'PHP Snippets'
		);
		expect( screen.getByText( 'Free' ) ).toHaveClass(
			'eui-badge--tier-free'
		);
		expect(
			screen.getByRole( 'link', { name: /Sandbox/ } )
		).toHaveAttribute( 'href', '/sb' );
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	it( 'Badge shows translated text for risk values', () => {
		render( <Badge kind="risk" value="read-only" /> );
		expect( screen.getByText( 'Read-only' ) ).toHaveClass(
			'eui-badge--risk-read-only'
		);
	} );

	it( 'Notice uses alert for danger and can be dismissed', async () => {
		const onDismiss = jest.fn();
		render(
			<Notice
				tone="danger"
				title="This runs real PHP on your site."
				onDismiss={ onDismiss }
			>
				Only activate code you trust.
			</Notice>
		);
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
			'This runs real PHP on your site.'
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Dismiss' } )
		);
		expect( onDismiss ).toHaveBeenCalled();
	} );

	it( 'Card renders a heading and actions', () => {
		render(
			<Card title="Server status" actions={ <span>All good</span> }>
				Body
			</Card>
		);
		expect(
			screen.getByRole( 'heading', { level: 2, name: 'Server status' } )
		).toBeInTheDocument();
	} );

	it( 'EmptyState and Skeleton are accessible', async () => {
		const { container } = render(
			<>
				<EmptyState icon="blocks" title="No blocks yet">
					Ask your AI agent.
				</EmptyState>
				<Skeleton lines={ 2 } label="Loading backups" />
			</>
		);
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading backups'
		);
		expect( await axe( container ) ).toHaveNoViolations();
	} );
} );
