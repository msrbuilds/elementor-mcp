import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Button,
	CopyField,
	Field,
	FilterChip,
	Notice,
	RadioCard,
	RadioCardGroup,
	Select,
	Step,
	Stepper,
	TextInput,
	errorMessage,
	request,
	useQueryState,
	useToast,
} from '@emcp/ui';
import { appSteps, basic, cliSteps, oauthSteps, serverName } from './snippets';
import { StepContent } from './StepContent';

// WordPress generates application passwords as 24 letters and digits
// (wp_generate_password( 24, false )), shown in groups of four.
const APP_PASSWORD = /^[A-Za-z0-9]{24}$/;
import { FirstCallStep } from './FirstCallStep';

const API = '/emcp-tools/v1/admin/connection';

/**
 * Connection > MCP: the 4-step setup (spec 8.2, 9.5).
 *
 * @param {Object} props      Props.
 * @param {Object} props.data Boot payload.
 */
export function McpSetup( { data } ) {
	const toast = useToast();
	const [ clientId, setClientId ] = useQueryState( 'client', '' );
	const [ method, setMethod ] = useQueryState( 'method', '' );
	const [ editing, setEditing ] = useState( 0 );
	const [ confirmed, setConfirmed ] = useState( false );
	const [ setup, setSetup ] = useState( null );
	const [ creds, setCreds ] = useState( {
		userId: data.users[ 0 ]?.id || data.currentUserId,
		username: data.users[ 0 ]?.login || '',
		password: '',
		created: false,
	} );
	const [ existing, setExisting ] = useState( [] );
	// An existing password chosen in step 3: WordPress keeps only its hash, so
	// the user pastes its text and the configs fill in from that.
	const [ chosen, setChosen ] = useState( '' );
	const pwRef = useRef( null );
	const [ busy, setBusy ] = useState( false );
	const expectRef = useRef( '' );

	const client = data.clients.find( ( c ) => c.id === clientId ) || null;
	const cliOk = data.localCli.available && client?.cli;
	const methodOk =
		method &&
		( 'cli' !== method || cliOk ) &&
		( 'oauth' !== method || data.oauth.enabled );
	let step = 3;
	if ( ! client || 1 === editing ) {
		step = 1;
	} else if ( ! methodOk || 2 === editing ) {
		step = 2;
	} else if ( confirmed ) {
		step = 4;
	}

	// What the reopened setup should keep waiting for: the password created
	// or chosen here, or the app picked for a reconnect (spec 9.5).
	const carriedExpect = () => {
		if ( 'app' === method && creds.uuid ) {
			return 'app:' + creds.uuid;
		}
		const prefix = 'app' === method ? 'app:' : 'oauth:';
		return expectRef.current.startsWith( prefix ) ? expectRef.current : '';
	};

	const openSetup = async ( expect = '' ) => {
		const body = { client: clientId, method };
		if ( expect ) {
			body.expect = expect;
			if ( 'app' === method ) {
				body.user_id = creds.userId;
			}
		}
		try {
			const res = await request( API + '/setup', {
				method: 'POST',
				data: body,
			} );
			expectRef.current = expect;
			setSetup( res );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		}
	};

	useEffect( () => {
		if ( client && methodOk && 0 === editing ) {
			setSetup( null );
			setConfirmed( false );
			openSetup( carriedExpect() );
		}
	}, [ clientId, method, editing ] ); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect( () => {
		if ( 'app' === method && creds.userId ) {
			request( API + '/app-passwords?user_id=' + creds.userId )
				.then( ( r ) => setExisting( r.passwords || [] ) )
				.catch( () => setExisting( [] ) );
		}
	}, [ method, creds.userId ] );

	useEffect( () => {
		if ( chosen ) {
			pwRef.current?.querySelector( 'input' )?.focus();
		}
	}, [ chosen ] );

	const createPassword = async () => {
		setBusy( true );
		try {
			const res = await request( API + '/app-password', {
				method: 'POST',
				data: { user_id: creds.userId, setup: setup?.setup_id },
			} );
			setCreds( ( c ) => ( {
				...c,
				username: res.username,
				password: res.password,
				created: true,
				uuid: res.uuid,
			} ) );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			setBusy( false );
		}
	};

	const downloadBundle = () => {
		const form = document.createElement( 'form' );
		form.method = 'post';
		form.action = data.mcpb.url;
		Object.entries( {
			action: data.mcpb.action,
			_emcp_nonce: data.mcpb.nonce,
			user_id: creds.userId,
			app_password: creds.password,
		} ).forEach( ( [ k, v ] ) => {
			const input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = k;
			input.value = String( v );
			form.appendChild( input );
		} );
		document.body.appendChild( form );
		form.submit();
		form.remove();
	};

	const name = serverName( data.siteUrl, data.endpoint );
	const conn = {
		endpoint: data.endpoint,
		siteUrl: data.siteUrl,
		username: creds.username,
		password: creds.password,
	};
	const statusOf = ( n ) => {
		if ( n < step ) {
			return 'complete';
		}
		return n === step ? 'active' : 'locked';
	};
	const methodLabel = {
		oauth: __( 'OAuth', 'emcp-tools' ),
		app: __( 'Application password', 'emcp-tools' ),
		cli: __( 'WP-CLI on this computer', 'emcp-tools' ),
	};

	let content = null;
	if ( 3 === step && client ) {
		if ( ! setup ) {
			content = (
				<p className="eui-conn__muted">
					{ __( 'Preparing your setup…', 'emcp-tools' ) }
				</p>
			);
		} else if ( 'oauth' === method ) {
			content = (
				<>
					<StepContent
						steps={ oauthSteps( client, name, data.endpoint ) }
						vars={ { name, endpoint: data.endpoint } }
					/>
					{ data.apps.length > 0 && (
						<Field
							label={ __(
								'Reconnect an app that is already connected',
								'emcp-tools'
							) }
							help={ __(
								'Choose it if the client already signed in once and will not show the consent screen again.',
								'emcp-tools'
							) }
						>
							{ ( a11y ) => (
								<Select
									{ ...a11y }
									value={ expectRef.current.replace(
										/^oauth:/,
										''
									) }
									onChange={ ( v ) =>
										openSetup( v ? 'oauth:' + v : '' )
									}
									options={ [
										{
											value: '',
											label: __(
												'A new app (default)',
												'emcp-tools'
											),
										},
										...data.apps.map( ( a ) => ( {
											value: a.id,
											label: a.name,
										} ) ),
									] }
								/>
							) }
						</Field>
					) }
				</>
			);
		} else if ( 'cli' === method ) {
			content = (
				<StepContent
					steps={ cliSteps( client, {
						command: data.localCli.command,
						path: data.localCli.path,
						user: creds.username || data.users[ 0 ]?.login || '',
						token: setup.token,
						name,
					} ) }
				/>
			);
		} else {
			// A pasted password counts only in WordPress's application password
			// format, so a browser-filled login password never reaches a config.
			const appLike = APP_PASSWORD.test(
				String( creds.password ).replace( /\s+/g, '' )
			);
			const hasCreds =
				creds.username &&
				creds.password &&
				( creds.created || appLike );
			content = (
				<>
					<div className="eui-conn__creds">
						<Field label={ __( 'Administrator', 'emcp-tools' ) }>
							{ ( a11y ) => (
								<Select
									{ ...a11y }
									value={ String( creds.userId ) }
									onChange={ ( v ) => {
										const u = data.users.find(
											( x ) => String( x.id ) === v
										);
										setChosen( '' );
										setCreds( {
											userId: u.id,
											username: u.login,
											password: '',
											created: false,
										} );
									} }
									options={ data.users.map( ( u ) => ( {
										value: String( u.id ),
										label: u.name + ' (' + u.login + ')',
									} ) ) }
								/>
							) }
						</Field>
						<Button
							variant="primary"
							onClick={ createPassword }
							loading={ busy }
							disabled={ busy }
						>
							{ __( 'Create password', 'emcp-tools' ) }
						</Button>
					</div>
					{ creds.created && (
						<Notice
							tone="warning"
							title={ __( 'Copy it now', 'emcp-tools' ) }
						>
							{ __(
								'This password is shown once. The configs below already contain it.',
								'emcp-tools'
							) }
							<CopyField
								value={ creds.password }
								label={ __(
									'Application password',
									'emcp-tools'
								) }
							/>
						</Notice>
					) }
					{ ! creds.created && existing.length > 0 && (
						<details className="eui-conn__existing">
							<summary>
								{ __(
									'Use a password I already have',
									'emcp-tools'
								) }
							</summary>
							<Field
								label={ __( 'Which password', 'emcp-tools' ) }
							>
								{ ( a11y ) => (
									<Select
										{ ...a11y }
										value={ chosen }
										onChange={ ( v ) => {
											setChosen( v );
											setCreds( ( c ) => ( {
												...c,
												password: '',
											} ) );
											openSetup( v ? 'app:' + v : '' );
										} }
										options={ [
											{
												value: '',
												label: __(
													'Choose…',
													'emcp-tools'
												),
											},
											...existing.map( ( p ) => ( {
												value: p.uuid,
												label: p.name,
											} ) ),
										] }
									/>
								) }
							</Field>
							{ chosen && (
								<div ref={ pwRef }>
									<Field
										label={ __(
											'Paste its password',
											'emcp-tools'
										) }
										help={ __(
											'Only used to fill the configs below; it is not sent to the server.',
											'emcp-tools'
										) }
										error={
											creds.password && ! appLike
												? __(
														'That is not an application password. WordPress shows them once, as 24 letters and digits in groups of four.',
														'emcp-tools'
													)
												: undefined
										}
									>
										{ ( a11y ) => (
											<TextInput
												{ ...a11y }
												type="text"
												autoComplete="off"
												spellCheck={ false }
												data-1p-ignore="true"
												data-lpignore="true"
												value={ creds.password }
												onChange={ ( e ) =>
													setCreds( ( c ) => ( {
														...c,
														password:
															e.target.value,
													} ) )
												}
											/>
										) }
									</Field>
								</div>
							) }
						</details>
					) }
					{ hasCreds ? (
						<StepContent
							steps={ appSteps( client, conn ) }
							vars={ {
								name,
								endpoint: data.endpoint,
								b64: basic( creds.username, creds.password ),
							} }
							onBundle={ downloadBundle }
						/>
					) : (
						<p className="eui-conn__muted">
							{ chosen && ! creds.created
								? __(
										'WordPress keeps only a hash of an application password, so paste the password you saved when you created it; the configs then fill in.',
										'emcp-tools'
									)
								: __(
										'Create a password to see the config for this client.',
										'emcp-tools'
									) }
						</p>
					) }
				</>
			);
		}
	}

	return (
		<>
			<h2 className="eui-visually-hidden">
				{ __( 'Connect an AI client', 'emcp-tools' ) }
			</h2>
			<Stepper label={ __( 'Connect an AI client', 'emcp-tools' ) }>
				<Step
					number={ 1 }
					title={ __( 'Choose your AI client', 'emcp-tools' ) }
					meta={ __( 'Step 1 of 4', 'emcp-tools' ) }
					status={ statusOf( 1 ) }
					summary={ client?.label }
					onEdit={ () => setEditing( 1 ) }
				>
					<div
						className="eui-conn__chips"
						role="group"
						aria-label={ __( 'AI clients', 'emcp-tools' ) }
					>
						{ data.clients.map( ( c ) => (
							<FilterChip
								key={ c.id }
								label={ c.label }
								image={ c.image || undefined }
								icon={ 'mcp-remote' === c.id ? 'code' : 'plug' }
								active={ c.id === clientId }
								onClick={ () => {
									setClientId( c.id );
									setEditing( 0 );
								} }
							/>
						) ) }
					</div>
				</Step>
				<Step
					number={ 2 }
					title={ __( 'Pick how it signs in', 'emcp-tools' ) }
					meta={ __( 'Step 2 of 4', 'emcp-tools' ) }
					status={ statusOf( 2 ) }
					summary={ methodLabel[ method ] }
					onEdit={ () => setEditing( 2 ) }
				>
					<RadioCardGroup
						legend={ __( 'Sign-in method', 'emcp-tools' ) }
						name="emcp-conn-method"
						value={ method }
						onChange={ ( v ) => {
							setMethod( v );
							setEditing( 0 );
						} }
						columns={ cliOk ? 3 : 2 }
					>
						<RadioCard
							value="oauth"
							title={ __( 'OAuth', 'emcp-tools' ) }
							tag={
								<Badge kind="status" value="success">
									{ __( 'Recommended', 'emcp-tools' ) }
								</Badge>
							}
							description={ __(
								'Sign in through the browser, no password to copy.',
								'emcp-tools'
							) }
							disabled={ ! data.oauth.enabled }
							requirement={
								data.oauth.enabled
									? undefined
									: __(
											'Turn on OAuth sign-in in Advanced settings.',
											'emcp-tools'
										)
							}
						/>
						<RadioCard
							value="app"
							title={ __( 'Application password', 'emcp-tools' ) }
							description={ __(
								'Generate a password and paste it into the client config.',
								'emcp-tools'
							) }
						/>
						{ cliOk && (
							<RadioCard
								value="cli"
								title={ __(
									'WP-CLI on this computer',
									'emcp-tools'
								) }
								description={ __(
									'The client starts WP-CLI directly. Local sites only.',
									'emcp-tools'
								) }
							/>
						) }
					</RadioCardGroup>
				</Step>
				<Step
					number={ 3 }
					title={
						client
							? sprintf(
									/* translators: %s: client name. */ __(
										'Add EMCP to %s',
										'emcp-tools'
									),
									client.label
								)
							: __( 'Add EMCP to your client', 'emcp-tools' )
					}
					meta={ __( 'Step 3 of 4', 'emcp-tools' ) }
					status={ statusOf( 3 ) }
					summary={ step > 3 ? __( 'Added', 'emcp-tools' ) : '' }
					onEdit={ () => setConfirmed( false ) }
				>
					{ content }
					<div className="eui-conn__actions">
						<Button
							variant="primary"
							onClick={ () => setConfirmed( true ) }
							disabled={
								! setup ||
								( 'app' === method &&
									! ( creds.username && creds.password ) )
							}
						>
							{ __( "I've added it, continue", 'emcp-tools' ) }
						</Button>
						<Button onClick={ () => setEditing( 2 ) }>
							{ __( 'Back', 'emcp-tools' ) }
						</Button>
					</div>
				</Step>
				<Step
					number={ 4 }
					title={ __( 'Test the connection', 'emcp-tools' ) }
					meta={ __( 'Step 4 of 4', 'emcp-tools' ) }
					status={ 4 === step ? 'active' : 'locked' }
					summary={
						client
							? sprintf(
									/* translators: %s: client name. */ __(
										"We'll wait for %s to call the server and confirm it here.",
										'emcp-tools'
									),
									client.label
								)
							: ''
					}
				>
					{ 4 === step && setup && (
						<FirstCallStep
							setupId={ setup.setup_id }
							clientLabel={ client.label }
							method={ method }
							conn={ conn }
							onRestart={ () => {
								setConfirmed( false );
								openSetup( carriedExpect() );
							} }
						/>
					) }
				</Step>
			</Stepper>
		</>
	);
}
