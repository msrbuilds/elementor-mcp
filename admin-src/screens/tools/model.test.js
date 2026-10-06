import {
	countTurnedOff,
	filterCategories,
	groupCounts,
	key,
	payloadFromDiff,
	resetToDefaults,
	setMany,
	slotsOf,
	tabCounts,
	valuesFromPayload,
} from './model';

const categories = [
	{
		id: 'content',
		label: 'WordPress Content',
		platform: 'wordpress',
		tools: [
			{
				slug: 'emcp-tools/list-posts',
				name: 'List Posts',
				description: 'List posts',
				risk: 'read-only',
				available: true,
			},
			{
				slug: 'emcp-tools/delete-post',
				name: 'Delete Post',
				description: 'Trash a post',
				risk: 'destructive',
				available: true,
			},
		],
	},
	{
		id: 'acf',
		label: 'ACF',
		platform: 'plugins',
		tools: [
			{
				slug: 'emcp-tools/acf-write',
				name: 'ACF Write',
				description: 'Write fields',
				risk: 'writes',
				available: false,
			},
		],
	},
];
const payload = {
	categories,
	enabled: {
		'emcp-tools/list-posts': true,
		'emcp-tools/delete-post': false,
		'emcp-tools/acf-write': false,
	},
	dispatcher: false,
	themerPhp: null,
};

describe( 'tools model', () => {
	it( 'maps payload to flat form values and back to a diff payload', () => {
		const values = valuesFromPayload( payload );
		expect( values[ key( 'emcp-tools/list-posts' ) ] ).toBe( true );
		expect( values.dispatcher ).toBe( false );
		expect( values.themerPhp ).toBeNull();
		expect(
			payloadFromDiff( {
				[ key( 'emcp-tools/delete-post' ) ]: true,
				[ key( 'emcp-tools/list-posts' ) ]: false,
				dispatcher: true,
			} )
		).toEqual( {
			enable: [ 'emcp-tools/delete-post' ],
			disable: [ 'emcp-tools/list-posts' ],
			dispatcher_mode: true,
		} );
	} );

	it( 'filters by tab, search, risk and status together', () => {
		const values = valuesFromPayload( payload );
		const base = {
			tab: 'wordpress',
			search: '',
			risk: 'all',
			status: 'any',
		};
		expect(
			filterCategories( categories, base, values )[ 0 ].tools
		).toHaveLength( 2 );
		expect(
			filterCategories(
				categories,
				{ ...base, risk: 'destructive' },
				values
			)[ 0 ].tools.map( ( t ) => t.name )
		).toEqual( [ 'Delete Post' ] );
		expect(
			filterCategories(
				categories,
				{ ...base, search: 'list-posts' },
				values
			)[ 0 ].tools
		).toHaveLength( 1 );
		expect(
			filterCategories(
				categories,
				{ ...base, status: 'disabled' },
				values
			)[ 0 ].tools.map( ( t ) => t.name )
		).toEqual( [ 'Delete Post' ] );
		expect(
			filterCategories(
				categories,
				{ ...base, search: 'nothing' },
				values
			)
		).toEqual( [] );
		expect(
			filterCategories(
				categories,
				{ ...base, tab: 'plugins', status: 'unavailable' },
				values
			)[ 0 ].tools
		).toHaveLength( 1 );
	} );

	it( 'counts per tab and per group', () => {
		const values = valuesFromPayload( payload );
		expect( tabCounts( categories, values ) ).toEqual( {
			wordpress: { on: 1, total: 2 },
			plugins: { on: 0, total: 1 },
		} );
		expect( groupCounts( categories[ 0 ], values ) ).toEqual( {
			on: 1,
			total: 2,
		} );
	} );

	it( 'setMany never switches an unavailable tool on', () => {
		const values = valuesFromPayload( payload );
		const next = setMany(
			values,
			[ ...categories[ 0 ].tools, ...categories[ 1 ].tools ],
			true
		);
		expect( next[ key( 'emcp-tools/delete-post' ) ] ).toBe( true );
		expect( next[ key( 'emcp-tools/acf-write' ) ] ).toBe( false );
	} );

	it( 'resetToDefaults only changes visible, available tools', () => {
		const values = {
			...valuesFromPayload( payload ),
			[ key( 'emcp-tools/hidden-tab-tool' ) ]: false,
		};
		const next = resetToDefaults(
			categories,
			[ 'emcp-tools/list-posts' ],
			values
		);
		expect( next[ key( 'emcp-tools/list-posts' ) ] ).toBe( false );
		expect( next[ key( 'emcp-tools/delete-post' ) ] ).toBe( true );
		expect( next[ key( 'emcp-tools/acf-write' ) ] ).toBe( false );
		expect( next[ key( 'emcp-tools/hidden-tab-tool' ) ] ).toBe( false );
		expect( countTurnedOff( values, next ) ).toBe( 1 );
	} );

	it( 'slotsOf pairs one read tool with at most one other, else null', () => {
		const t = ( slug, risk ) => ( { slug, risk } );
		expect(
			slotsOf( {
				tools: [ t( 'w', 'destructive' ), t( 'r', 'read-only' ) ],
			} )
		).toEqual( {
			read: t( 'r', 'read-only' ),
			write: t( 'w', 'destructive' ),
		} );
		expect( slotsOf( { tools: [ t( 'r', 'read-only' ) ] } ) ).toEqual( {
			read: t( 'r', 'read-only' ),
			write: undefined,
		} );
		expect(
			slotsOf( {
				tools: [ t( 'a', 'read-only' ), t( 'b', 'read-only' ) ],
			} )
		).toBeNull();
		expect(
			slotsOf( {
				tools: [
					t( 'r', 'read-only' ),
					t( 'w', 'writes' ),
					t( 'd', 'destructive' ),
				],
			} )
		).toBeNull();
	} );
} );
