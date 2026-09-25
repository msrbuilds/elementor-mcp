import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { ModulesScreen } from './ModulesScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const data = {
	groups: [
		{ id: 'content', label: 'Content & design' },
		{ id: 'ai', label: 'AI & agents' },
		{ id: 'site', label: 'Site & safety' },
	],
	licensed: false,
	modules: [
		{
			id: 'prompts',
			title: 'Prompts',
			description: 'Prompt blueprints.',
			tier: 'free',
			group: 'content',
			icon: 'lightbulb',
			active: true,
			available: true,
			reason: '',
			settingsUrl: '/wp-admin/admin.php?page=emcp-tools-prompts',
			hasSettings: false,
		},
		{
			id: 'image-optimization',
			title: 'Image Optimization',
			description: 'Compress uploads.',
			tier: 'free',
			group: 'content',
			icon: 'image',
			active: true,
			available: true,
			reason: '',
			settingsUrl: '',
			hasSettings: true,
		},
		{
			id: 'redirects',
			title: 'Redirect Manager',
			description: '301 redirects.',
			tier: 'free',
			group: 'site',
			icon: 'shuffle',
			active: true,
			available: true,
			reason: '',
			settingsUrl: '/wp-admin/admin.php?page=emcp-tools-redirects',
			hasSettings: false,
		},
		{
			id: 'memory',
			title: 'Project Memory',
			description: 'Remember your site.',
			tier: 'pro',
			group: 'ai',
			icon: 'brain',
			active: false,
			available: false,
			reason: 'Requires an active EMCP Pro licence.',
			settingsUrl: '',
			hasSettings: false,
		},
	],
};

function mount() {
	window.history.replaceState(
		{},
		'',
		'/wp-admin/admin.php?page=emcp-tools-modules'
	);
	return render(
		<AppProviders>
			<ModulesScreen data={ data } reload={ jest.fn() } />
		</AppProviders>
	);
}

describe( 'ModulesScreen', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'groups modules with on counts and filter counts', () => {
		mount();
		expect(
			screen.getByRole( 'radio', { name: /All\s*4/ } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'radio', { name: /Enabled\s*3/ } )
		).toBeInTheDocument();
		const content = screen.getByRole( 'region', {
			name: /Content & design/,
		} );
		expect( within( content ).getByText( '2 / 2 on' ) ).toBeInTheDocument();
	} );

	it( 'turning a module off names the change in the save bar and saves a diff, then reloads', async () => {
		apiFetch.mockResolvedValue( { ...data, ignored: [] } );
		const reload = jest.fn();
		window.history.replaceState(
			{},
			'',
			'/wp-admin/admin.php?page=emcp-tools-modules'
		);
		render(
			<AppProviders>
				<ModulesScreen data={ data } reload={ reload } />
			</AppProviders>
		);
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Redirect Manager' } )
		);
		expect(
			screen.getByText( 'Redirect Manager turned off' )
		).toBeInTheDocument();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Save modules' } )
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/modules',
				method: 'POST',
				data: { activate: [], deactivate: [ 'redirects' ] },
			} )
		);
		expect( reload ).toHaveBeenCalled();
	} );

	it( 'configure and settings links show for saved-on modules', () => {
		mount();
		expect(
			screen.getByRole( 'link', { name: /Configure Prompts/ } )
		).toHaveAttribute(
			'href',
			'/wp-admin/admin.php?page=emcp-tools-prompts'
		);
		expect(
			screen.getByRole( 'button', {
				name: /Settings for Image Optimization/,
			} )
		).toBeInTheDocument();
	} );

	it( 'a Pro module on a free build is locked', () => {
		mount();
		expect(
			screen.getByRole( 'switch', { name: 'Project Memory' } )
		).toBeDisabled();
	} );

	it( 'the settings drawer loads fields and saves typed values', async () => {
		apiFetch
			.mockResolvedValueOnce( {
				fields: [
					{
						key: 'q',
						type: 'range',
						label: 'Quality',
						min: 1,
						max: 100,
					},
					{ key: 'c', type: 'toggle', label: 'Compress uploads' },
				],
				values: { q: 60, c: true },
			} )
			.mockResolvedValueOnce( {
				fields: [],
				values: { q: 70, c: false },
				ignored: [],
			} );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', {
				name: /Settings for Image Optimization/,
			} )
		);
		const drawer = await screen.findByRole( 'dialog', {
			name: 'Image Optimization',
		} );
		await userEvent.click(
			within( drawer ).getByRole( 'switch', { name: 'Compress uploads' } )
		);
		await userEvent.click(
			within( drawer ).getByRole( 'button', { name: 'Save settings' } )
		);
		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/modules/image-optimization/settings',
				method: 'POST',
				data: { values: { c: false } },
			} )
		);
	} );
} );
