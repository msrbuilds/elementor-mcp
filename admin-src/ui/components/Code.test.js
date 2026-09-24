import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'jest-axe';
import { copyText, CopyButton, CopyField, CodeBlock } from './Code';

function setSecure( secure, clipboard ) {
	Object.defineProperty( window, 'isSecureContext', { value: secure, configurable: true } );
	Object.defineProperty( navigator, 'clipboard', { value: clipboard, configurable: true } );
}

describe( 'copyText', () => {
	it( 'uses the Clipboard API in a secure context', async () => {
		const writeText = jest.fn().mockResolvedValue();
		setSecure( true, { writeText } );
		await expect( copyText( 'abc' ) ).resolves.toBe( true );
		expect( writeText ).toHaveBeenCalledWith( 'abc' );
	} );

	it( 'falls back to execCommand on plain http', async () => {
		setSecure( false, undefined );
		document.execCommand = jest.fn().mockReturnValue( true );
		await expect( copyText( 'abc' ) ).resolves.toBe( true );
		expect( document.execCommand ).toHaveBeenCalledWith( 'copy' );
		expect( document.querySelector( 'textarea' ) ).toBeNull();
	} );

	it( 'reports failure honestly', async () => {
		setSecure( false, undefined );
		document.execCommand = jest.fn().mockReturnValue( false );
		await expect( copyText( 'abc' ) ).resolves.toBe( false );
	} );
} );

describe( 'CopyButton', () => {
	beforeEach( () => jest.useFakeTimers() );
	afterEach( () => jest.useRealTimers() );

	it( 'shows Copied for 2 seconds', async () => {
		setSecure( true, { writeText: jest.fn().mockResolvedValue() } );
		const user = userEvent.setup( { advanceTimers: jest.advanceTimersByTime } );
		render( <CopyButton text="x" label="Copy" /> );
		await user.click( screen.getByRole( 'button', { name: 'Copy' } ) );
		expect( screen.getByRole( 'button' ) ).toHaveTextContent( 'Copied' );
		act( () => jest.advanceTimersByTime( 2000 ) );
		expect( screen.getByRole( 'button' ) ).toHaveTextContent( 'Copy' );
	} );

	it( 'shows Copy failed when nothing could copy', async () => {
		setSecure( false, undefined );
		document.execCommand = jest.fn().mockReturnValue( false );
		const user = userEvent.setup( { advanceTimers: jest.advanceTimersByTime } );
		render( <CopyButton text="x" label="Copy" /> );
		await user.click( screen.getByRole( 'button', { name: 'Copy' } ) );
		expect( screen.getByRole( 'button' ) ).toHaveTextContent( 'Copy failed' );
	} );
} );

describe( 'CopyField and CodeBlock', () => {
	it( 'render values as text, never HTML', async () => {
		const { container } = render(
			<>
				<CopyField label="Server URL" value="https://msrplugins.test/wp-json/mcp/emcp-tools-server" />
				<CodeBlock label="Snippet code" value={ '<?php echo "<b>x</b>";' } />
			</>
		);
		expect( container.querySelector( 'b' ) ).toBeNull();
		expect( screen.getByText( /echo "<b>x<\/b>"/ ) ).toBeInTheDocument();
		expect( await axe( container ) ).toHaveNoViolations();
	} );
} );
