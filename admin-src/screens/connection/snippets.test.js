import {
	appSteps,
	basic,
	cliSteps,
	deeplink,
	fill,
	oauthSteps,
	serverName,
} from './snippets';

const conn = {
	endpoint: 'https://ex.test/wp-json/mcp/emcp-tools-server',
	siteUrl: 'https://www.ex.test',
	username: 'admin',
	password: 'abcd efgh',
};
const byId = ( id, extra = {} ) => ( {
	id,
	label: id,
	methods: { bundle: false, cli: null, ai_prompt: false, json: [] },
	oauth: null,
	guide: '',
	guideTitle: '',
	...extra,
} );

describe( 'snippets', () => {
	it( 'derives the server name from the site host', () => {
		expect( serverName( 'https://www.My-Site.test/', '' ) ).toBe(
			'emcp-my-site-test'
		);
		expect( serverName( '', '' ) ).toBe( 'emcp-tools' );
	} );

	it( 'builds the basic token without password spaces', () => {
		expect( basic( 'admin', 'abcd efgh' ) ).toBe(
			btoa( 'admin:abcdefgh' )
		);
	} );

	it( 'fills every placeholder', () => {
		expect(
			fill( '%NAME% %ENDPOINT% %B64% %NAME%', {
				name: 'n',
				endpoint: 'e',
				b64: 'b',
			} )
		).toBe( 'n e b n' );
	} );

	it( 'builds the npx and http JSON configs like the legacy screen', () => {
		const steps = appSteps(
			byId( 'claude-desktop', {
				methods: {
					bundle: false,
					cli: null,
					ai_prompt: false,
					json: [ 'npx', 'http' ],
				},
			} ),
			conn
		);
		const npx = JSON.parse(
			steps.find( ( s ) => s.title === 'Manual config: Node proxy (npx)' )
				.text
		);
		expect( npx.mcpServers[ 'emcp-ex-test' ] ).toEqual( {
			type: 'stdio',
			command: 'npx',
			args: [ '-y', '@msrbuilds/emcp-proxy@latest' ],
			env: {
				WP_URL: 'https://www.ex.test',
				WP_USERNAME: 'admin',
				WP_APP_PASSWORD: 'abcd efgh',
				MCP_PROTOCOL_VERSION: '2024-11-05',
			},
		} );
		const http = JSON.parse(
			steps.find( ( s ) => s.title === 'Manual config: direct HTTP' ).text
		);
		expect( http.mcpServers[ 'emcp-ex-test' ] ).toEqual( {
			type: 'http',
			url: conn.endpoint,
			headers: {
				Authorization: 'Basic ' + basic( 'admin', 'abcd efgh' ),
			},
		} );
	} );

	it( 'builds TOML, YAML and OpenClaw variants', () => {
		const steps = appSteps(
			byId( 'x', {
				methods: {
					bundle: false,
					cli: null,
					ai_prompt: false,
					json: [ 'toml', 'hermes-http', 'openclaw-http' ],
				},
			} ),
			conn
		);
		expect( steps[ 0 ].text ).toBe(
			'[mcp_servers.emcp-ex-test]\nurl = "' +
				conn.endpoint +
				'"\nhttp_headers = { "Authorization" = "Basic ' +
				basic( 'admin', 'abcd efgh' ) +
				'" }'
		);
		expect(
			steps[ 1 ].text.startsWith(
				'mcp_servers:\n  emcp-ex-test:\n    url: "'
			)
		).toBe( true );
		expect( steps[ 2 ].text.startsWith( '"mcp": {' ) ).toBe( true );
	} );

	it( 'adds the bundle, terminal command and AI prompt when the client has them', () => {
		const steps = appSteps(
			byId( 'claude-code', {
				methods: {
					bundle: true,
					cli: 'claude mcp add %NAME% "%ENDPOINT%" %B64%',
					ai_prompt: true,
					json: [],
				},
			} ),
			conn
		);
		expect( steps.map( ( s ) => s.kind ) ).toEqual( [
			'bundle',
			'code',
			'code',
		] );
		expect( steps[ 1 ].text ).toBe(
			'claude mcp add emcp-ex-test "' +
				conn.endpoint +
				'" ' +
				basic( 'admin', 'abcd efgh' )
		);
	} );

	it( 'renders every OAuth shape', () => {
		const connector = oauthSteps(
			byId( 'claude-ai', {
				oauth: {
					type: 'connector',
					app: 'claude.ai',
					deeplink: 'claude-ai',
				},
			} ),
			'emcp-x',
			'https://e'
		);
		expect( connector[ 0 ] ).toEqual( {
			kind: 'link',
			title: 'Add the connector to claude.ai',
			href: deeplink( 'claude-ai', 'emcp-x', 'https://e' ),
		} );
		expect(
			connector
				.filter( ( s ) => s.kind === 'copy' )
				.map( ( s ) => s.text )
		).toEqual( [ 'emcp-x', 'https://e' ] );
		const cmd = oauthSteps(
			byId( 'claude-code', {
				oauth: { type: 'cmd', cmd: 'claude mcp add %NAME% %ENDPOINT%' },
			} ),
			'emcp-x',
			'https://e'
		);
		expect( cmd[ 0 ].text ).toBe( 'claude mcp add emcp-x https://e' );
		const config = oauthSteps(
			byId( 'cursor', {
				oauth: {
					type: 'config',
					lang: 'json',
					paths: [ { path: '~/.cursor/mcp.json', label: 'Global' } ],
					template: '{"%NAME%":"%ENDPOINT%"}',
					deeplink: 'cursor',
				},
			} ),
			'emcp-x',
			'https://e'
		);
		expect( config.map( ( s ) => s.kind ) ).toEqual( [
			'link',
			'paths',
			'text',
			'code',
			'text',
		] );
		const steps = oauthSteps(
			byId( 'codex', {
				oauth: {
					type: 'steps',
					steps: [
						{ title: 'Name', copy: '%NAME%' },
						{ title: 'Save', desc: 'At %ENDPOINT%' },
					],
				},
			} ),
			'emcp-x',
			'https://e'
		);
		expect( steps ).toEqual( [
			{ kind: 'copy', title: 'Name', text: 'emcp-x' },
			{ kind: 'text', title: 'Save', text: 'At https://e' },
		] );
		expect( oauthSteps( byId( 'z' ), 'n', 'https://e' )[ 0 ] ).toEqual( {
			kind: 'copy',
			title: 'Add this site as an HTTP MCP server',
			text: 'https://e',
		} );
	} );

	it( 'builds the WP-CLI stdio config with the setup token', () => {
		const cli = {
			command: 'wp',
			path: 'F:/sites/x',
			user: 'admin',
			token: 'tok123',
			name: 'emcp-x',
		};
		const json = JSON.parse(
			cliSteps( byId( 'cursor' ), cli ).find( ( s ) => s.kind === 'code' )
				.text
		);
		expect( json.mcpServers[ 'emcp-x' ] ).toEqual( {
			type: 'stdio',
			command: 'wp',
			args: [
				'mcp-adapter',
				'serve',
				'--server=emcp-tools-server',
				'--user=admin',
				'--path=F:/sites/x',
			],
			env: { EMCP_SETUP: 'tok123' },
		} );
		expect(
			cliSteps( byId( 'claude-code' ), cli ).find(
				( s ) => s.kind === 'code'
			).text
		).toBe(
			'claude mcp add emcp-x --env EMCP_SETUP=tok123 -- wp mcp-adapter serve --server=emcp-tools-server --user=admin --path=F:/sites/x'
		);
	} );

	it( 'splits a multi-word WP-CLI command into command and args', () => {
		const cli = {
			command: 'php /x/wp-cli.phar',
			path: '/w',
			user: 'admin',
			token: 't',
			name: 'emcp-x',
		};
		const json = JSON.parse(
			cliSteps( byId( 'cursor' ), cli ).find( ( s ) => s.kind === 'code' )
				.text
		);
		expect( json.mcpServers[ 'emcp-x' ].command ).toBe( 'php' );
		expect( json.mcpServers[ 'emcp-x' ].args[ 0 ] ).toBe(
			'/x/wp-cli.phar'
		);
	} );

	it( 'quotes paths with spaces in commands and keeps them whole in JSON', () => {
		const cli = {
			command: '"C:/Program Files/php/php.exe" /x/wp-cli.phar',
			path: 'C:/Local Sites/x/app/public',
			user: 'admin',
			token: 't',
			name: 'emcp-x',
		};
		const cmd = cliSteps( byId( 'claude-code' ), cli ).find(
			( s ) => s.kind === 'code'
		).text;
		expect( cmd ).toContain( '"--path=C:/Local Sites/x/app/public"' );
		expect( cmd ).toContain(
			'-- "C:/Program Files/php/php.exe" /x/wp-cli.phar mcp-adapter'
		);
		const json = JSON.parse(
			cliSteps( byId( 'cursor' ), cli ).find( ( s ) => s.kind === 'code' )
				.text
		).mcpServers[ 'emcp-x' ];
		expect( json.command ).toBe( 'C:/Program Files/php/php.exe' );
		expect( json.args ).toContain( '--path=C:/Local Sites/x/app/public' );
	} );

	it( 'tells connector users to leave the OAuth client fields empty', () => {
		const steps = oauthSteps(
			byId( 'claude-desktop', {
				oauth: { type: 'connector', app: 'Claude Desktop' },
			} ),
			'n',
			'https://e'
		);
		expect( JSON.stringify( steps ) ).toMatch(
			/Leave the OAuth Client ID and Secret empty/
		);
	} );

	it( 'never uses an em dash in its copy', () => {
		const all = JSON.stringify( [
			appSteps(
				byId( 'claude-desktop', {
					methods: {
						bundle: true,
						cli: 'c',
						ai_prompt: true,
						json: [
							'npx',
							'http',
							'remote',
							'toml',
							'toml-stdio',
							'openclaw-http',
							'openclaw-npx',
							'hermes-http',
							'hermes-npx',
						],
					},
				} ),
				conn
			),
			oauthSteps(
				byId( 'x', {
					oauth: { type: 'config', paths: [], template: '' },
				} ),
				'n',
				'e'
			),
			cliSteps( byId( 'cursor' ), {
				command: 'wp',
				path: '/',
				user: 'a',
				token: 't',
				name: 'n',
			} ),
		] );
		expect( all ).not.toMatch( /\u2014/ );
	} );
} );
