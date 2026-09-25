/**
 * Client config snippets for the Connection screen. A port of the builders in
 * assets/js/admin.js (emcpServerName, emcpJsonConfig, emcpOpenclawConfig,
 * emcpHermesConfig, emcpTomlConfig, emcpTomlStdioConfig, emcpRenderOAuth), so
 * the new screen and the legacy view produce identical configs. Pure: no DOM.
 */
import { __, sprintf } from '@wordpress/i18n';

const PROXY = '@msrbuilds/emcp-proxy@latest';

export function serverName( siteUrl, endpoint ) {
	let host = '';
	try {
		host = new URL( siteUrl || endpoint ).hostname;
	} catch {
		host = '';
	}
	host = ( host || '' )
		.replace( /^www\./, '' )
		.replace( /[^a-zA-Z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' )
		.toLowerCase();
	return host ? 'emcp-' + host : 'emcp-tools';
}

export function basic( username, password ) {
	return btoa( username + ':' + String( password ).replace( /\s+/g, '' ) );
}

export function fill( tpl, { name = '', endpoint = '', b64 = '' } ) {
	return String( tpl )
		.replace( /%NAME%/g, name )
		.replace( /%ENDPOINT%/g, endpoint )
		.replace( /%B64%/g, b64 );
}

export function deeplink( kind, name, endpoint ) {
	if ( 'cursor' === kind ) {
		return (
			'cursor://anysphere.cursor-deeplink/mcp/install?name=' +
			encodeURIComponent( name ) +
			'&config=' +
			btoa( JSON.stringify( { url: endpoint } ) )
		);
	}
	if ( 'claude-ai' === kind ) {
		return (
			'https://claude.ai/customize/connectors?modal=add-custom-connector&connectorName=' +
			encodeURIComponent( name ) +
			'&connectorUrl=' +
			encodeURIComponent( endpoint )
		);
	}
	return '';
}

const signin = () =>
	__(
		'The next time your AI client connects, your browser opens so you can authorize it. Approve to finish connecting.',
		'emcp-tools'
	);

export function oauthSteps( client, name, endpoint ) {
	const o = client.oauth;
	const vars = { name, endpoint };
	if ( ! o || 'object' !== typeof o ) {
		return [
			{
				kind: 'copy',
				title: __(
					'Add this site as an HTTP MCP server',
					'emcp-tools'
				),
				text: endpoint,
			},
			{
				kind: 'text',
				title: __( 'Sign in', 'emcp-tools' ),
				text: signin(),
			},
		];
	}
	if ( 'cmd' === o.type ) {
		return [
			{
				kind: 'code',
				title: __( 'Run this in your terminal', 'emcp-tools' ),
				text: fill( o.cmd, vars ),
			},
			{
				kind: 'text',
				title: __( 'Sign in', 'emcp-tools' ),
				text: signin(),
			},
		];
	}
	if ( 'connector' === o.type ) {
		const app = o.app || __( 'your client', 'emcp-tools' );
		const out = [];
		if ( o.deeplink ) {
			out.push( {
				kind: 'link',
				title: sprintf(
					/* translators: %s: app name. */
					__( 'Add the connector to %s', 'emcp-tools' ),
					app
				),
				href: deeplink( o.deeplink, name, endpoint ),
			} );
		}
		if ( o.note ) {
			out.push( { kind: 'text', title: '', text: o.note } );
		}
		out.push( {
			kind: 'text',
			title: __( 'Open Connectors', 'emcp-tools' ),
			text: sprintf(
				/* translators: %s: app name. */
				__(
					'In %s, open Settings, then Connectors, then Add custom connector.',
					'emcp-tools'
				),
				app
			),
		} );
		out.push( {
			kind: 'copy',
			title: __( 'Name the connector', 'emcp-tools' ),
			text: name,
		} );
		out.push( {
			kind: 'copy',
			title: __( 'Enter the server URL', 'emcp-tools' ),
			text: endpoint,
		} );
		return out;
	}
	if ( 'steps' === o.type ) {
		return ( o.steps || [] ).map( ( s ) =>
			s.copy
				? {
						kind: 'copy',
						title: s.title || '',
						text: fill( s.copy, vars ),
					}
				: {
						kind: 'text',
						title: s.title || '',
						text: s.desc ? fill( s.desc, vars ) : '',
					}
		);
	}
	if ( 'config' === o.type ) {
		const out = [];
		if ( o.deeplink ) {
			out.push( {
				kind: 'link',
				title: __( 'One-click install', 'emcp-tools' ),
				href: deeplink( o.deeplink, name, endpoint ),
			} );
		}
		out.push( {
			kind: 'paths',
			title: __( 'Open your config', 'emcp-tools' ),
			paths: o.paths || [],
		} );
		out.push( {
			kind: 'text',
			title: __( 'Add this server', 'emcp-tools' ),
			text:
				o.merge_msg ||
				__(
					'If your config file already has content, merge this into it instead of replacing it.',
					'emcp-tools'
				),
		} );
		out.push( { kind: 'code', title: '', text: fill( o.template, vars ) } );
		out.push( {
			kind: 'text',
			title: __( 'Restart and sign in', 'emcp-tools' ),
			text: o.note ? fill( o.note, vars ) + ' ' + signin() : signin(),
		} );
		return out;
	}
	return [
		{
			kind: 'copy',
			title: __( 'Connector URL', 'emcp-tools' ),
			text: endpoint,
		},
		{ kind: 'text', title: __( 'Sign in', 'emcp-tools' ), text: signin() },
	];
}

function proxyEnv( c ) {
	return {
		WP_URL: c.siteUrl,
		WP_USERNAME: c.username,
		WP_APP_PASSWORD: c.password,
		MCP_PROTOCOL_VERSION: '2024-11-05',
	};
}

function jsonConfig( variant, c, name, b64 ) {
	const servers = {};
	if ( 'npx' === variant ) {
		servers[ name ] = {
			type: 'stdio',
			command: 'npx',
			args: [ '-y', PROXY ],
			env: proxyEnv( c ),
		};
	} else if ( 'http' === variant ) {
		servers[ name ] = {
			type: 'http',
			url: c.endpoint,
			headers: { Authorization: 'Basic ' + b64 },
		};
	} else {
		servers[ name ] = {
			command: 'npx',
			args: [
				'-y',
				'mcp-remote',
				c.endpoint,
				'--header',
				'Authorization: Basic ' + b64,
			],
		};
	}
	return JSON.stringify( { mcpServers: servers }, null, 4 );
}

function openclaw( variant, c, name, b64 ) {
	const server =
		'npx' === variant
			? { command: 'npx', args: [ '-y', PROXY ], env: proxyEnv( c ) }
			: {
					url: c.endpoint,
					transport: 'streamable-http',
					headers: { Authorization: 'Basic ' + b64 },
				};
	return (
		'"mcp": ' + JSON.stringify( { servers: { [ name ]: server } }, null, 4 )
	);
}

function hermes( variant, c, name, b64 ) {
	if ( 'npx' === variant ) {
		return (
			'mcp_servers:\n  ' +
			name +
			':\n    command: "npx"\n    args: ["-y", "' +
			PROXY +
			'"]\n    env:\n' +
			'      WP_URL: "' +
			c.siteUrl +
			'"\n      WP_USERNAME: "' +
			c.username +
			'"\n      WP_APP_PASSWORD: "' +
			c.password +
			'"\n      MCP_PROTOCOL_VERSION: "2024-11-05"'
		);
	}
	return (
		'mcp_servers:\n  ' +
		name +
		':\n    url: "' +
		c.endpoint +
		'"\n    headers:\n      Authorization: "Basic ' +
		b64 +
		'"'
	);
}

function toml( c, name, b64 ) {
	return (
		'[mcp_servers.' +
		name +
		']\nurl = "' +
		c.endpoint +
		'"\nhttp_headers = { "Authorization" = "Basic ' +
		b64 +
		'" }'
	);
}

function tomlStdio( c, name ) {
	return (
		'[mcp_servers.' +
		name +
		']\ncommand = "npx"\nargs = ["-y", "' +
		PROXY +
		'"]\n\n[mcp_servers.' +
		name +
		'.env]\n' +
		'WP_URL = "' +
		c.siteUrl +
		'"\nWP_USERNAME = "' +
		c.username +
		'"\nWP_APP_PASSWORD = "' +
		c.password +
		'"\nMCP_PROTOCOL_VERSION = "2024-11-05"'
	);
}

const LABELS = () => ( {
	npx: __( 'Manual config: Node proxy (npx)', 'emcp-tools' ),
	http: __( 'Manual config: direct HTTP', 'emcp-tools' ),
	remote: __( 'Manual config: npx mcp-remote', 'emcp-tools' ),
	toml: __( 'Manual config: direct HTTP (config.toml)', 'emcp-tools' ),
	'toml-stdio': __(
		'Manual config: Node proxy, npx (config.toml)',
		'emcp-tools'
	),
	'openclaw-http': __(
		'Manual config: direct HTTP (openclaw.json)',
		'emcp-tools'
	),
	'openclaw-npx': __(
		'Manual config: Node proxy, npx (openclaw.json)',
		'emcp-tools'
	),
	'hermes-http': __(
		'Manual config: direct HTTP (config.yaml)',
		'emcp-tools'
	),
	'hermes-npx': __(
		'Manual config: Node proxy, npx (config.yaml)',
		'emcp-tools'
	),
} );

export function appSteps( client, c ) {
	const name = serverName( c.siteUrl, c.endpoint );
	const b64 = basic( c.username, c.password );
	const m = client.methods || {};
	const labels = LABELS();
	const out = [];
	if ( client.guide ) {
		out.push( {
			kind: 'guide',
			title: client.guideTitle || __( 'Setup guide', 'emcp-tools' ),
			text: client.guide,
		} );
	}
	if ( m.bundle ) {
		out.push( {
			kind: 'bundle',
			title: __( 'One-click bundle (.mcpb)', 'emcp-tools' ),
		} );
	}
	if ( m.cli ) {
		out.push( {
			kind: 'code',
			title: __( 'Terminal command', 'emcp-tools' ),
			text: fill( m.cli, { name, endpoint: c.endpoint, b64 } ),
		} );
	}
	if ( m.ai_prompt ) {
		out.push( {
			kind: 'code',
			title: __(
				'Ask your AI to set it up (paste into chat)',
				'emcp-tools'
			),
			text:
				'Add an MCP server named "' +
				name +
				'" at ' +
				c.endpoint +
				' using the HTTP transport with header  Authorization: Basic ' +
				b64,
		} );
	}
	( m.json || [] ).forEach( ( variant ) => {
		let text;
		if ( 'toml' === variant ) {
			text = toml( c, name, b64 );
		} else if ( 'toml-stdio' === variant ) {
			text = tomlStdio( c, name );
		} else if ( variant.startsWith( 'openclaw-' ) ) {
			text = openclaw( variant.slice( 9 ), c, name, b64 );
		} else if ( variant.startsWith( 'hermes-' ) ) {
			text = hermes( variant.slice( 7 ), c, name, b64 );
		} else {
			text = jsonConfig( variant, c, name, b64 );
		}
		out.push( {
			kind: 'code',
			title: labels[ variant ] || labels.remote,
			text,
		} );
	} );
	return out;
}

export function cliSteps( client, cli ) {
	const args = [
		'mcp-adapter',
		'serve',
		'--server=emcp-tools-server',
		'--user=' + cli.user,
		'--path=' + cli.path,
	];
	const note = {
		kind: 'text',
		title: __( 'About the setup token', 'emcp-tools' ),
		text: __(
			'EMCP_SETUP lets this screen recognise the first call. It stops working once the setup finishes; leaving it in the config is harmless.',
			'emcp-tools'
		),
	};
	if ( 'claude-code' === client.id ) {
		return [
			{
				kind: 'code',
				title: __( 'Run this in your terminal', 'emcp-tools' ),
				text:
					'claude mcp add ' +
					cli.name +
					' --env EMCP_SETUP=' +
					cli.token +
					' -- ' +
					cli.command +
					' ' +
					args.join( ' ' ),
			},
			note,
		];
	}
	const parts = String( cli.command ).trim().split( /\s+/ );
	const config = {
		mcpServers: {
			[ cli.name ]: {
				type: 'stdio',
				command: parts[ 0 ],
				args: [ ...parts.slice( 1 ), ...args ],
				env: { EMCP_SETUP: cli.token },
			},
		},
	};
	return [
		{
			kind: 'text',
			title: __( 'Add this server to your config', 'emcp-tools' ),
			text: __(
				"Merge it into the client's MCP config file, then restart the client.",
				'emcp-tools'
			),
		},
		{
			kind: 'code',
			title: __( 'WP-CLI (stdio)', 'emcp-tools' ),
			text: JSON.stringify( config, null, 4 ),
		},
		note,
	];
}
