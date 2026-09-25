import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Button,
	Card,
	CopyField,
	EmptyState,
	Field,
	Icon,
	TextInput,
	Toggle,
	errorMessage,
	request,
	useConfirm,
	useSettingsForm,
	useToast,
} from '@emcp/ui';

const API = '/emcp-tools/v1/admin/connection';

export function ServerRail( { data, apps, setApps, onSaved = () => {} } ) {
	const toast = useToast();
	const confirm = useConfirm();
	const [ status, setStatus ] = useState( data.status );
	const [ oauth, setOauth ] = useState( data.oauth );
	const [ open, setOpen ] = useState( true );
	const form = useSettingsForm( data.advanced, async ( diff ) => {
		try {
			const res = await request( API + '/advanced', {
				method: 'POST',
				data: diff,
			} );
			setStatus( res.status );
			setOauth( res.oauth );
			onSaved( res );
			if ( res.ignored?.length ) {
				toast.error(
					__(
						'OAuth sign-in needs HTTPS on this site.',
						'emcp-tools'
					)
				);
			} else {
				toast.success(
					__(
						'Settings saved. Reconnect your client to see the change.',
						'emcp-tools'
					)
				);
			}
			return res.advanced;
		} catch ( e ) {
			toast.error( errorMessage( e ) );
			throw e;
		}
	} );
	const good =
		status.abilitiesApi &&
		status.serverEnabled &&
		'none' !== status.adapter;
	const rows = [
		[
			__( 'MCP Tools for Elementor', 'emcp-tools' ),
			__( 'Active', 'emcp-tools' ),
			true,
		],
		[
			__( 'MCP Adapter', 'emcp-tools' ),
			{
				bundled: __( 'Bundled', 'emcp-tools' ),
				external: __( 'External', 'emcp-tools' ),
				none: __( 'Missing', 'emcp-tools' ),
			}[ status.adapter ],
			'none' !== status.adapter,
		],
		[
			__( 'MCP Server', 'emcp-tools' ),
			status.serverEnabled
				? __( 'Enabled', 'emcp-tools' )
				: __( 'Disabled', 'emcp-tools' ),
			status.serverEnabled,
		],
		[
			__( 'Tools enabled', 'emcp-tools' ),
			`${ status.toolsEnabled } / ${ status.toolsTotal }`,
			status.toolsEnabled > 0,
		],
	];

	const removeApp = async ( app, revokeOnly ) => {
		const ok = await confirm( {
			title: revokeOnly
				? /* translators: %s: app name. */
					sprintf( __( 'Sign %s out?', 'emcp-tools' ), app.name )
				: /* translators: %s: app name. */
					sprintf( __( 'Remove %s?', 'emcp-tools' ), app.name ),
			message: revokeOnly
				? __(
						'Its tokens stop working; it can sign in again.',
						'emcp-tools'
					)
				: __(
						'The app and its tokens are deleted. It must register again to connect.',
						'emcp-tools'
					),
			confirmLabel: revokeOnly
				? __( 'Sign out', 'emcp-tools' )
				: __( 'Remove', 'emcp-tools' ),
			tone: 'danger',
		} );
		if ( ! ok ) {
			return;
		}
		try {
			const path =
				API +
				'/oauth-clients/' +
				encodeURIComponent( app.id ) +
				( revokeOnly ? '/revoke' : '' );
			const res = await request( path, {
				method: revokeOnly ? 'POST' : 'DELETE',
			} );
			setApps( res.apps );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		}
	};

	return (
		<div className="eui-conn__rail">
			<Card>
				<section aria-labelledby="eui-conn-status">
					<div className="eui-conn__rail-head">
						<h2
							id="eui-conn-status"
							className="eui-conn__rail-title"
						>
							{ __( 'Server status', 'emcp-tools' ) }
						</h2>
						<Badge
							kind="status"
							value={ good ? 'success' : 'warning' }
						>
							{ good
								? __( 'All good', 'emcp-tools' )
								: __( 'Needs attention', 'emcp-tools' ) }
						</Badge>
					</div>
					<ul className="eui-conn__status">
						{ rows.map( ( [ label, value, ok ] ) => (
							<li key={ label }>
								<span
									className={
										ok
											? 'eui-conn__dot is-ok'
											: 'eui-conn__dot'
									}
									aria-hidden="true"
								/>
								{ label }
								<strong>{ value }</strong>
							</li>
						) ) }
					</ul>
					<CopyField
						value={ data.endpoint }
						label={ __( 'MCP endpoint', 'emcp-tools' ) }
					/>
				</section>
			</Card>
			<Card>
				<section aria-labelledby="eui-conn-apps">
					<h2 id="eui-conn-apps" className="eui-conn__rail-title">
						{ __( 'Connected apps', 'emcp-tools' ) }
					</h2>
					{ ! apps.length && (
						<EmptyState
							icon="plug"
							title={ __( 'No apps yet', 'emcp-tools' ) }
						>
							{ __(
								'They show up here as soon as they register.',
								'emcp-tools'
							) }
						</EmptyState>
					) }
					<ul className="eui-conn__apps">
						{ apps.map( ( a ) => (
							<li key={ a.id }>
								<div>
									<strong>{ a.name }</strong>
									<span className="eui-conn__muted">
										{
											{
												connected: __(
													'Connected',
													'emcp-tools'
												),
												signed_out: __(
													'Signed out',
													'emcp-tools'
												),
												registered: __(
													'Never signed in',
													'emcp-tools'
												),
											}[ a.state ]
										}
										{ a.user ? ' · ' + a.user : '' }
									</span>
								</div>
								<div className="eui-conn__app-actions">
									{ 'connected' === a.state && (
										<Button
											size="sm"
											onClick={ () =>
												removeApp( a, true )
											}
											aria-label={ sprintf(
												/* translators: %s: app. */ __(
													'Sign out %s',
													'emcp-tools'
												),
												a.name
											) }
										>
											{ __( 'Sign out', 'emcp-tools' ) }
										</Button>
									) }
									<Button
										size="sm"
										variant="ghost"
										onClick={ () => removeApp( a, false ) }
										aria-label={ sprintf(
											/* translators: %s: app. */ __(
												'Remove %s',
												'emcp-tools'
											),
											a.name
										) }
									>
										<Icon name="trash-2" />
									</Button>
								</div>
							</li>
						) ) }
					</ul>
				</section>
			</Card>
			<Card>
				<section aria-labelledby="eui-conn-adv">
					<button
						type="button"
						className="eui-conn__rail-toggle"
						aria-expanded={ open }
						onClick={ () => setOpen( ! open ) }
					>
						<span
							id="eui-conn-adv"
							className="eui-conn__rail-title"
						>
							{ __( 'Advanced settings', 'emcp-tools' ) }
						</span>
						<Icon
							name={ open ? 'chevron-down' : 'chevron-right' }
						/>
					</button>
					{ open && (
						<div className="eui-conn__adv">
							<Toggle
								checked={ form.values.server_enabled }
								onChange={ ( v ) =>
									form.setValue( 'server_enabled', v )
								}
								label={ __( 'Abilities API', 'emcp-tools' ) }
							/>
							<p className="eui-conn__muted">
								{ __(
									'Expose EMCP tools to AI agents on this site.',
									'emcp-tools'
								) }
							</p>
							<Toggle
								checked={ form.values.oauth_enabled }
								onChange={ ( v ) =>
									form.setValue( 'oauth_enabled', v )
								}
								label={ __( 'OAuth sign-in', 'emcp-tools' ) }
								disabled={ ! oauth.available }
							/>
							<p className="eui-conn__muted">
								{ oauth.available
									? __(
											'Clients sign in instead of pasting a password.',
											'emcp-tools'
										)
									: __(
											'Needs HTTPS on this site.',
											'emcp-tools'
										) }
							</p>
							<Toggle
								checked={ form.values.strict_schemas }
								onChange={ ( v ) =>
									form.setValue( 'strict_schemas', v )
								}
								label={ __(
									'OpenAI-strict schemas',
									'emcp-tools'
								) }
							/>
							<p className="eui-conn__muted">
								{ __(
									'Only for strict function-calling clients (for example CrewAI). Keep off for Claude and Gemini.',
									'emcp-tools'
								) }
							</p>
							<Field
								label={ __(
									'Server URL override',
									'emcp-tools'
								) }
							>
								{ ( a11y ) => (
									<TextInput
										{ ...a11y }
										value={ form.values.public_base_url }
										placeholder={ sprintf(
											/* translators: %s: URL. */ __(
												'Auto-detected: %s',
												'emcp-tools'
											),
											data.detectedBaseUrl
										) }
										onChange={ ( e ) =>
											form.setValue(
												'public_base_url',
												e.target.value
											)
										}
									/>
								) }
							</Field>
							<div className="eui-conn__adv-foot">
								<p className="eui-conn__warn">
									<Icon name="triangle-alert" />{ ' ' }
									{ __(
										'Agents can create, edit and delete content.',
										'emcp-tools'
									) }
								</p>
								<Button
									variant="primary"
									onClick={ form.submit }
									loading={ form.saving }
									disabled={ ! form.dirty || form.saving }
								>
									{ __( 'Save', 'emcp-tools' ) }
								</Button>
							</div>
						</div>
					) }
				</section>
			</Card>
		</div>
	);
}
