import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { ToolsScreen } from './ToolsScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const NAME = { selector: '.eui-tool__name' };

const data = {
	tabs: [
		{ id: 'wordpress', label: 'WordPress' },
		{ id: 'plugins', label: 'Plugins' },
	],
	categories: [
		{
			id: 'content',
			label: 'WordPress Content',
			platform: 'wordpress',
			group: '',
			groupLabel: '',
			note: '',
			notice: null,
			danger: false,
			proLocked: false,
			tools: [
				{
					slug: 'emcp-tools/list-posts',
					name: 'List Posts',
					description: 'List posts.',
					risk: 'read-only',
					available: true,
					requirement: '',
					requirementNote: '',
					operations: [],
				},
				{
					slug: 'emcp-tools/delete-post',
					name: 'Delete Post',
					description: 'Trash a post.',
					risk: 'destructive',
					available: true,
					requirement: '',
					requirementNote: '',
					operations: [],
				},
			],
		},
		{
			id: 'acf',
			label: 'ACF',
			platform: 'plugins',
			group: '',
			groupLabel: '',
			note: '',
			notice: null,
			danger: false,
			proLocked: false,
			tools: [
				{
					slug: 'emcp-tools/acf-read',
					name: 'ACF Read',
					description: 'Read fields.',
					risk: 'read-only',
					available: false,
					requirement: 'Needs ACF',
					requirementNote: 'Install ACF.',
					operations: [ 'get-fields', 'list-field-groups' ],
				},
			],
		},
	],
	enabled: {
		'emcp-tools/list-posts': true,
		'emcp-tools/delete-post': false,
		'emcp-tools/acf-read': false,
	},
	dispatcher: false,
	themerPhp: false,
	defaults: [ 'emcp-tools/delete-post' ],
	elementorActive: true,
	licensed: true,
	upgradeUrl: 'https://emcptools.com/pricing',
};

function mount() {
	window.history.replaceState(
		{},
		'',
		'/wp-admin/admin.php?page=emcp-tools-tools'
	);
	return render(
		<AppProviders>
			<ToolsScreen data={ data } />
		</AppProviders>
	);
}

describe( 'ToolsScreen', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'shows the enabled meter, tabs with counts and the first tab tools', () => {
		mount();
		expect( screen.getByText( '1 / 3' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'tab', { name: /WordPress/ } )
		).toHaveAttribute( 'aria-selected', 'true' );
		expect( screen.getByText( 'List Posts', NAME ) ).toBeInTheDocument();
		expect(
			screen.queryByText( 'ACF Read', NAME )
		).not.toBeInTheDocument();
	} );

	it( 'toggling a tool shows the save bar and saves a diff', async () => {
		apiFetch.mockResolvedValue( {
			...data,
			enabled: { ...data.enabled, 'emcp-tools/delete-post': true },
			ignored: [],
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Delete Post' } )
		);
		expect( screen.getByText( /1 unsaved change/ ) ).toBeInTheDocument();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/tools',
				method: 'POST',
				data: { enable: [ 'emcp-tools/delete-post' ], disable: [] },
			} )
		);
		expect(
			screen.queryByText( /unsaved change/ )
		).not.toBeInTheDocument();
	} );

	it( 'keeps a toggle flipped during an in-flight save dirty', async () => {
		let resolve;
		apiFetch.mockImplementation(
			() =>
				new Promise( ( r ) => {
					resolve = r;
				} )
		);
		mount();
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Delete Post' } )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'List Posts' } )
		);
		await act( async () =>
			resolve( {
				...data,
				enabled: { ...data.enabled, 'emcp-tools/delete-post': true },
				ignored: [],
			} )
		);
		expect(
			screen.getByRole( 'switch', { name: 'List Posts' } )
		).not.toBeChecked();
		expect( screen.getByText( /1 unsaved change/ ) ).toBeInTheDocument();
	} );

	it( 'filters by risk and search', async () => {
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: /Destructive/ } )
		);
		expect(
			screen.queryByText( 'List Posts', NAME )
		).not.toBeInTheDocument();
		expect( screen.getByText( 'Delete Post', NAME ) ).toBeInTheDocument();
	} );

	it( 'unavailable tools show their requirement and a disabled toggle', async () => {
		mount();
		await userEvent.click( screen.getByRole( 'tab', { name: /Plugins/ } ) );
		expect( screen.getByText( 'Needs ACF' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'switch', { name: 'ACF Read' } )
		).toBeDisabled();
		expect( screen.getByText( /2 operations/ ) ).toBeInTheDocument();
	} );

	it( 'reset to defaults asks first, then marks the changes', async () => {
		mount();
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Delete Post' } )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Bulk Actions' } )
		);
		await userEvent.click(
			screen.getByRole( 'menuitem', { name: 'Reset to defaults' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Reset to defaults?',
		} );
		expect( dialog ).toHaveTextContent( 'turns off 1 enabled tool' );
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Reset' } )
		);
		expect(
			screen.getByRole( 'switch', { name: 'Delete Post' } )
		).not.toBeChecked();
		expect(
			screen.queryByText( /unsaved change/ )
		).not.toBeInTheDocument();
	} );

	it( 'group enable acts on its visible tools only', async () => {
		mount();
		const group = screen.getByRole( 'region', {
			name: /WordPress Content/,
		} );
		await userEvent.click(
			within( group ).getByRole( 'button', {
				name: 'Enable all in WordPress Content',
			} )
		);
		expect(
			screen.getByRole( 'switch', { name: 'Delete Post' } )
		).toBeChecked();
	} );
} );
