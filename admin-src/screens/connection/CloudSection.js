import { __ } from '@wordpress/i18n';
import { Badge, Button, Card, Checkbox, Notice, useConfirm } from '@emcp/ui';
import { useState } from '@wordpress/element';

function PostForm( {
	url,
	action,
	nonce,
	nonceName = '_wpnonce',
	children,
	fields = {},
	confirmText = '',
} ) {
	const confirm = useConfirm();
	const onSubmit = async ( e ) => {
		if ( ! confirmText ) {
			return;
		}
		e.preventDefault();
		const form = e.currentTarget;
		if (
			await confirm( {
				title: confirmText,
				confirmLabel: __( 'Pull', 'emcp-tools' ),
				tone: 'danger',
			} )
		) {
			form.submit();
		}
	};
	return (
		<form
			method="post"
			action={ url }
			className="eui-conn__inline-form"
			onSubmit={ onSubmit }
		>
			<input type="hidden" name="action" value={ action } />
			<input type="hidden" name={ nonceName } value={ nonce } />
			{ Object.entries( fields ).map( ( [ k, v ] ) => (
				<input key={ k } type="hidden" name={ k } value={ v } />
			) ) }
			{ children }
		</form>
	);
}

export function CloudSection( { data } ) {
	const confirm = useConfirm();
	const [ gateway, setGateway ] = useState( true );
	const c = data.cloud;
	if ( ! c ) {
		return (
			<Notice tone="info">
				{ __(
					'Turn on the EMCP Cloud module to connect this site.',
					'emcp-tools'
				) }
			</Notice>
		);
	}
	const connect = (
		<PostForm
			url={ data.adminPostUrl }
			action={ c.connectAction }
			nonce={ c.connectNonce }
			fields={ gateway ? { emcp_gateway_optin: '1' } : {} }
		>
			<Checkbox
				checked={ gateway }
				onChange={ setGateway }
				label={ __(
					'Let EMCP Cloud manage this site through the gateway',
					'emcp-tools'
				) }
			/>
			<Button type="submit" variant="primary">
				{ c.connected
					? __( 'Reconnect', 'emcp-tools' )
					: __( 'Connect to EMCP Cloud', 'emcp-tools' ) }
			</Button>
		</PostForm>
	);
	const disconnect = async () => {
		if (
			await confirm( {
				title: __( 'Disconnect EMCP Cloud?', 'emcp-tools' ),
				message: __(
					'Backups and sync stop until you connect again.',
					'emcp-tools'
				),
				confirmLabel: __( 'Disconnect', 'emcp-tools' ),
				tone: 'danger',
			} )
		) {
			window.location.assign( c.disconnectUrl );
		}
	};
	return (
		<div className="eui-conn__cloud">
			<Card
				title={ __( 'Cloud account', 'emcp-tools' ) }
				actions={
					c.connected ? (
						<Badge
							kind="status"
							value={ c.healthy ? 'success' : 'warning' }
						>
							{ c.healthy
								? __( 'Connected', 'emcp-tools' )
								: __( 'Reconnect needed', 'emcp-tools' ) }
						</Badge>
					) : null
				}
			>
				{ ! c.connected && (
					<p className="eui-conn__muted">
						{ __(
							'Connect this site to your EMCP Cloud account to back up and sync your work.',
							'emcp-tools'
						) }
					</p>
				) }
				{ ( ! c.connected || ! c.healthy ) && connect }
				{ c.connected && (
					<div className="eui-conn__actions">
						<Button onClick={ disconnect }>
							{ __( 'Disconnect', 'emcp-tools' ) }
						</Button>
						{ c.healthy && (
							<a className="eui-btn" href={ c.reissueUrl }>
								{ c.gateway
									? __(
											'Re-issue gateway credential',
											'emcp-tools'
										)
									: __(
											'Enable gateway access',
											'emcp-tools'
										) }
							</a>
						) }
					</div>
				) }
			</Card>
			{ c.connected && c.sync && (
				<Card title={ __( 'Settings sync', 'emcp-tools' ) }>
					{ c.sync.entitled ? (
						<>
							<p className="eui-conn__muted">
								{ __(
									'Copy your EMCP settings between connected sites: tool toggles, active modules, compact tool mode and behaviour preferences. Secrets and API keys are never synced.',
									'emcp-tools'
								) }
							</p>
							<div className="eui-conn__actions">
								<PostForm
									url={ data.adminPostUrl }
									action="emcp_tools_settings_push"
									nonce={ c.sync.nonce }
								>
									<Button type="submit" variant="primary">
										{ __(
											'Push settings to cloud',
											'emcp-tools'
										) }
									</Button>
								</PostForm>
								<PostForm
									url={ data.adminPostUrl }
									action="emcp_tools_settings_pull"
									nonce={ c.sync.nonce }
									confirmText={ __(
										"Pull settings and overwrite this site's settings?",
										'emcp-tools'
									) }
								>
									<Button type="submit">
										{ __(
											'Pull settings from cloud',
											'emcp-tools'
										) }
									</Button>
								</PostForm>
							</div>
						</>
					) : (
						<>
							<p className="eui-conn__muted">
								{ __(
									'Sync your EMCP settings across all your sites. This is a paid EMCP Cloud feature.',
									'emcp-tools'
								) }
							</p>
							<a
								className="eui-btn"
								href={ c.sync.billingUrl }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ __(
									'Upgrade your Cloud plan',
									'emcp-tools'
								) }
							</a>
						</>
					) }
				</Card>
			) }
		</div>
	);
}
