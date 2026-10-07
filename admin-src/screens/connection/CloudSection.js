import { __, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Button,
	Card,
	Icon,
	Notice,
	Toggle,
	useConfirm,
} from '@emcp/ui';
import { useState } from '@wordpress/element';

function PostForm( {
	url,
	action,
	nonce,
	nonceName = '_wpnonce',
	children,
	fields = {},
	confirmText = '',
	confirmLabel = __( 'Pull', 'emcp-tools' ),
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
				confirmLabel,
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

const defaultNavigate = ( url ) => window.location.assign( url );

/**
 * A site-local date ('2026-09-20') in the viewer's locale.
 *
 * @param {string} ymd Date from the server.
 * @return {string} Formatted date.
 */
function formatDay( ymd ) {
	return new Date( `${ ymd }T00:00:00Z` ).toLocaleDateString( undefined, {
		dateStyle: 'medium',
		timeZone: 'UTC',
	} );
}

/**
 * Who the site is linked to: the account email (redacted by the server),
 * the Cloud host and when it connected.
 *
 * @param {Object} props
 * @param {Object} props.c            data.cloud.
 * @param {string} props.adminPostUrl admin-post.php.
 */
function Account( { c, adminPostUrl } ) {
	const when = c.connectedAt
		? sprintf(
				/* translators: %s: date. */
				__( 'Connected %s', 'emcp-tools' ),
				formatDay( c.connectedAt )
			)
		: '';
	return (
		<div className="eui-conn__account">
			<span className="eui-conn__account-icon" aria-hidden="true">
				<Icon name="cloud" size={ 18 } />
			</span>
			<span className="eui-conn__account-text">
				<strong className="eui-conn__account-name">
					{ c.account || __( 'EMCP Cloud account', 'emcp-tools' ) }
				</strong>
				<span className="eui-conn__muted">
					{ [ c.host || c.baseUrl, when ]
						.filter( Boolean )
						.join( ' · ' ) }
				</span>
				{ ! c.account && (
					// Connections made before the email scope never got an
					// email; connecting again keeps gateway access as it is.
					<PostForm
						url={ adminPostUrl }
						action={ c.connectAction }
						nonce={ c.connectNonce }
						fields={ c.gateway ? { emcp_gateway_optin: '1' } : {} }
					>
						<button
							type="submit"
							className="eui-conn__setting-link"
						>
							{ __(
								'Reconnect to show the account email',
								'emcp-tools'
							) }
						</button>
					</PostForm>
				) }
			</span>
		</div>
	);
}

export function CloudSection( { data, navigate = defaultNavigate } ) {
	const confirm = useConfirm();
	const [ gateway, setGateway ] = useState( true );
	// Set while a gateway change is on its way to admin-post.
	const [ pending, setPending ] = useState( null );
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
			<Toggle
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
			navigate( c.disconnectUrl );
		}
	};
	const gatewayOn = null === pending ? !! c.gateway : pending;
	const toggleGateway = async ( on ) => {
		if ( ! on ) {
			const ok = await confirm( {
				title: __( 'Turn off gateway access?', 'emcp-tools' ),
				message: __(
					'AI clients connected through the EMCP Cloud gateway lose access to this site until you turn it back on.',
					'emcp-tools'
				),
				confirmLabel: __( 'Turn off', 'emcp-tools' ),
				tone: 'danger',
			} );
			if ( ! ok ) {
				return;
			}
		}
		setPending( on );
		navigate( on ? c.reissueUrl : c.gatewayOffUrl );
	};
	return (
		<div className="eui-conn__cloud">
			{ data.management && (
				<Notice tone="info">
					<p>
						{ sprintf(
							/* translators: 1: WordPress login, 2: user ID. */
							__(
								'Managing this site as %1$s (WordPress user ID %2$d).',
								'emcp-tools'
							),
							data.management.login,
							data.management.id
						) }
					</p>
					<p>
						{ __(
							'Reissuing Gateway access authorizes this WordPress account. Existing MCP credentials retain their original execution identity and permissions.',
							'emcp-tools'
						) }
					</p>
					<a href={ data.management.url }>
						{ __( 'Manage local access', 'emcp-tools' ) }
					</a>
				</Notice>
			) }
			{ c.identityConflict && (
				<Notice tone="warning">
					{ __(
						'This installation has a copied Cloud identity or a changed address. Cloud requests are paused to protect the original connection. Connect it as a separate site below.',
						'emcp-tools'
					) }
				</Notice>
			) }
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
				{ c.connected && (
					<div className="eui-conn__account-row">
						<Account c={ c } adminPostUrl={ data.adminPostUrl } />
						<Button size="sm" onClick={ disconnect }>
							{ __( 'Disconnect', 'emcp-tools' ) }
						</Button>
					</div>
				) }
				{ ! c.identityConflict &&
					( ! c.connected || ! c.healthy ) &&
					connect }
				{ c.connected && c.healthy && (
					<div className="eui-conn__setting">
						<div className="eui-conn__setting-text">
							<span className="eui-conn__setting-title">
								{ __( 'Gateway access', 'emcp-tools' ) }
							</span>
							<span className="eui-conn__muted">
								{ __(
									'Let AI clients reach this site through the EMCP Cloud gateway, with no site password to paste.',
									'emcp-tools'
								) }
							</span>
							{ gatewayOn && null === pending && (
								<a
									className="eui-conn__setting-link"
									href={ c.reissueUrl }
								>
									{ __(
										'Re-issue credential',
										'emcp-tools'
									) }
								</a>
							) }
						</div>
						<Toggle
							checked={ gatewayOn }
							onChange={ toggleGateway }
							disabled={ null !== pending }
							label={ __( 'Gateway access', 'emcp-tools' ) }
							hideLabel
						/>
					</div>
				) }
			</Card>
			{ c.separateAction && (
				<Card title={ __( 'Cloned or restored site?', 'emcp-tools' ) }>
					<p>
						{ __(
							'Use a separate Cloud identity for a copy of another site. This clears only this installation’s saved Cloud connection. The original site and its Cloud data stay connected. You will connect this copy again and it will count toward your site allowance.',
							'emcp-tools'
						) }
					</p>
					<PostForm
						url={ data.adminPostUrl }
						action={ c.separateAction }
						nonce={ c.separateNonce }
						fields={ { confirm_separate: '1' } }
						confirmText={ __(
							'Give this installation a separate Cloud identity?',
							'emcp-tools'
						) }
						confirmLabel={ __( 'Separate site', 'emcp-tools' ) }
					>
						<Button type="submit">
							{ __( 'Connect as a separate site', 'emcp-tools' ) }
						</Button>
					</PostForm>
				</Card>
			) }
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
