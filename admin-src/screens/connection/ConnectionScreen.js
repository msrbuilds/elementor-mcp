import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice, PageHeader, Segmented, useQueryState } from '@emcp/ui';
import { McpSetup } from './McpSetup';
import { ServerRail } from './ServerRail';
import { CloudSection } from './CloudSection';
import { ServicesSection } from './ServicesSection';

const CLOUD_FLAGS = [
	'cloud_connected',
	'cloud_error',
	'cloud_disconnected',
	'cloud_gateway',
	'synced',
];

function flagNotice() {
	const q = new URLSearchParams( window.location.search );
	if ( q.get( 'cloud_connected' ) ) {
		return [
			'success',
			__( 'This site is connected to EMCP Cloud.', 'emcp-tools' ),
		];
	}
	if ( q.get( 'cloud_disconnected' ) ) {
		return [
			'info',
			__( 'This site is disconnected from EMCP Cloud.', 'emcp-tools' ),
		];
	}
	if ( q.get( 'cloud_error' ) ) {
		return [
			'error',
			__(
				'Connecting to EMCP Cloud failed. Please try again.',
				'emcp-tools'
			),
		];
	}
	if ( 'disabled' === q.get( 'cloud_gateway' ) ) {
		return [
			'success',
			__( 'Gateway access is off for this site.', 'emcp-tools' ),
		];
	}
	if ( 'reissued' === q.get( 'cloud_gateway' ) ) {
		return [
			'success',
			__( 'Gateway credential re-issued.', 'emcp-tools' ),
		];
	}
	if ( q.get( 'cloud_gateway' ) ) {
		return [
			'error',
			__(
				'The gateway credential could not be issued. Reconnect with gateway access switched on, then try again.',
				'emcp-tools'
			),
		];
	}
	if ( 'push' === q.get( 'synced' ) ) {
		return [
			'success',
			__( 'Settings pushed to the cloud.', 'emcp-tools' ),
		];
	}
	if ( 'pull' === q.get( 'synced' ) ) {
		return [
			'success',
			__( 'Settings pulled from the cloud and applied.', 'emcp-tools' ),
		];
	}
	if ( 'err' === q.get( 'synced' ) ) {
		return [
			'error',
			__(
				'Settings sync failed. Please try again or reconnect this site to EMCP Cloud.',
				'emcp-tools'
			),
		];
	}
	return null;
}

export function ConnectionScreen( { data } ) {
	const fromCloud = CLOUD_FLAGS.some( ( f ) =>
		new URLSearchParams( window.location.search ).has( f )
	);
	const [ section, setSection ] = useQueryState(
		'section',
		fromCloud ? 'cloud' : 'mcp'
	);
	const [ apps, setApps ] = useState( data.apps );
	// Saved in the rail, read by the wizard: OAuth state and the public URLs.
	const [ live, setLive ] = useState( {
		oauth: data.oauth,
		endpoint: data.endpoint,
		siteUrl: data.siteUrl,
	} );
	const [ notice ] = useState( flagNotice );
	const options = [
		{ value: 'mcp', label: __( 'MCP', 'emcp-tools' ) },
		{ value: 'cloud', label: __( 'Cloud', 'emcp-tools' ) },
		{ value: 'services', label: __( '3rd-party services', 'emcp-tools' ) },
	];
	return (
		<div className="eui-conn">
			<PageHeader
				title={ __( 'Connection', 'emcp-tools' ) }
				description={ __(
					'Connect an AI client to this site in four steps.',
					'emcp-tools'
				) }
				actions={
					<Segmented
						label={ __( 'Section', 'emcp-tools' ) }
						value={ section }
						onChange={ setSection }
						options={ options }
					/>
				}
			/>
			{ notice && <Notice tone={ notice[ 0 ] }>{ notice[ 1 ] }</Notice> }
			{ /* Kept mounted while hidden: the open setup and a one-time password survive a section switch. */ }
			<div className="eui-conn__grid" hidden={ 'mcp' !== section }>
				<div className="eui-conn__main">
					<McpSetup data={ { ...data, ...live, apps } } />
				</div>
				<ServerRail
					data={ data }
					apps={ apps }
					setApps={ setApps }
					onSaved={ ( res ) =>
						setLive( {
							oauth: res.oauth,
							endpoint: res.endpoint || live.endpoint,
							siteUrl: res.siteUrl || live.siteUrl,
						} )
					}
				/>
			</div>
			{ 'cloud' === section && <CloudSection data={ data } /> }
			{ 'services' === section && <ServicesSection data={ data } /> }
		</div>
	);
}
