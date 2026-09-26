import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CodeBlock,
	Drawer,
	Field,
	Icon,
	Meter,
	PageHeader,
	SaveBar,
	Textarea,
	Toggle,
	errorMessage,
	request,
	useSettingsForm,
	useToast,
} from '@emcp/ui';
import { ProfileCard } from './ProfileCard';

const API = '/emcp-tools/v1/admin/context';
const PREVIEW_LINES = 12;

const toValues = ( d ) => ( {
	profile: d.profile,
	sections: Object.fromEntries(
		d.sections.map( ( s ) => [ s.id, s.enabled ] )
	),
	instructions: d.instructions,
	enabled: d.enabled,
} );

const kilo = ( n ) => {
	const k = ( n / 1000 ).toFixed( 1 );
	return `${ k.endsWith( '.0' ) ? k.slice( 0, -2 ) : k }k`;
};

function relative( ts ) {
	if ( ! ts ) {
		return '';
	}
	const diff = Math.round( ts - Date.now() / 1000 );
	const rtf = new Intl.RelativeTimeFormat( undefined, { numeric: 'auto' } );
	const abs = Math.abs( diff );
	if ( abs < 3600 ) {
		return rtf.format( Math.round( diff / 60 ), 'minute' );
	}
	if ( abs < 86400 ) {
		return rtf.format( Math.round( diff / 3600 ), 'hour' );
	}
	return rtf.format( Math.round( diff / 86400 ), 'day' );
}

/**
 * Context screen (spec 8.7).
 *
 * @param {Object} props      Props.
 * @param {Object} props.data Boot payload (EMCP_Tools_Admin_Context_Data).
 */
export function ContextScreen( { data: initial } ) {
	const toast = useToast();
	const [ data, setData ] = useState( initial );
	const [ preview, setPreview ] = useState( {
		text: initial.preview,
		tokens: initial.tokens,
	} );
	const [ refreshing, setRefreshing ] = useState( false );
	const [ drawer, setDrawer ] = useState( false );
	const latest = useRef( 0 );
	const first = useRef( true );

	const form = useSettingsForm( toValues( initial ), async ( diff ) => {
		const body = { ...diff };
		// Only the sections that changed, so a stale screen cannot flip others.
		if ( body.sections ) {
			body.sections = Object.fromEntries(
				Object.entries( body.sections ).filter(
					( [ id, on ] ) => form.baseline.sections[ id ] !== on
				)
			);
		}
		try {
			const res = await request( API, { method: 'POST', data: body } );
			setData( res );
			setPreview( { text: res.preview, tokens: res.tokens } );
			toast.success(
				__(
					'Context saved. Agents see it the next time they connect.',
					'emcp-tools'
				)
			);
			return toValues( res );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
			throw e;
		}
	} );

	// Live preview of the working copy (debounced; latest answer wins).
	useEffect( () => {
		if ( first.current ) {
			first.current = false;
			return undefined;
		}
		const timer = setTimeout( () => {
			const ticket = ++latest.current;
			request( API + '/preview', { method: 'POST', data: form.values } )
				.then( ( res ) => {
					if ( ticket === latest.current ) {
						setPreview( { text: res.text, tokens: res.tokens } );
					}
				} )
				.catch( () => {} );
		}, 400 );
		return () => clearTimeout( timer );
	}, [ form.values ] );

	const refresh = async () => {
		setRefreshing( true );
		try {
			const res = await request( API + '/refresh', { method: 'POST' } );
			setData( ( d ) => ( {
				...d,
				sections: d.sections.map(
					( s ) => res.sections.find( ( n ) => n.id === s.id ) || s
				),
				refreshedAt: res.refreshedAt,
			} ) );
			toast.success( __( 'Detected items refreshed.', 'emcp-tools' ) );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			setRefreshing( false );
		}
	};

	const { values } = form;
	const over = preview.tokens > data.budget;
	const lines = ( preview.text || '' ).split( '\n' );

	return (
		<div className="eui-ctx">
			<PageHeader
				title={ __( 'Context', 'emcp-tools' ) }
				description={ __(
					'What your AI knows about this site before it starts. Connected agents and AI Chat read this at the start of every session.',
					'emcp-tools'
				) }
				actions={
					<Button icon="eye" onClick={ () => setDrawer( true ) }>
						{ __( 'Preview what the AI sees', 'emcp-tools' ) }
					</Button>
				}
			/>
			<div className="eui-ctx__layout">
				<div className="eui-ctx__main">
					<ProfileCard
						profile={ values.profile }
						industries={ data.industries }
						voices={ data.voices }
						onChange={ ( p ) => form.setValue( 'profile', p ) }
					/>
					<Card
						title={ __( 'Detected automatically', 'emcp-tools' ) }
						actions={
							<div className="eui-ctx__refresh">
								{ data.refreshedAt > 0 && (
									<span className="eui-ctx__muted">
										{ sprintf(
											/* translators: %s: relative time, e.g. "2 hours ago". */
											__( 'Refreshed %s', 'emcp-tools' ),
											relative( data.refreshedAt )
										) }
									</span>
								) }
								<Button
									size="sm"
									icon="refresh-cw"
									loading={ refreshing }
									onClick={ refresh }
								>
									{ __( 'Refresh', 'emcp-tools' ) }
								</Button>
							</div>
						}
					>
						<ul className="eui-ctx__sections">
							{ data.sections.map( ( s ) => (
								<li key={ s.id } className="eui-ctx__section">
									<span
										className="eui-ctx__icon"
										aria-hidden="true"
									>
										<Icon name={ s.icon } />
									</span>
									<Toggle
										label={ s.label }
										checked={ !! values.sections[ s.id ] }
										onChange={ ( on ) =>
											form.setValue(
												'sections',
												( m ) => ( {
													...m,
													[ s.id ]: on,
												} )
											)
										}
									/>
									<span className="eui-ctx__summary">
										{ s.summary }
									</span>
								</li>
							) ) }
						</ul>
					</Card>
					<Card title={ __( 'Extra instructions', 'emcp-tools' ) }>
						<p className="eui-ctx__muted">
							{ __(
								'Plain-language rules added to every session. For rules agents learn over time, use',
								'emcp-tools'
							) }{ ' ' }
							<a href={ data.memoryUrl }>
								{ __( 'Project Memory', 'emcp-tools' ) }
							</a>
							.
						</p>
						<Field
							label={ __( 'Extra instructions', 'emcp-tools' ) }
						>
							{ ( a11y ) => (
								<Textarea
									{ ...a11y }
									mono
									rows={ 6 }
									maxLength={ data.maxChars }
									value={ values.instructions }
									onChange={ ( e ) =>
										form.setValue(
											'instructions',
											e.target.value
										)
									}
								/>
							) }
						</Field>
						<Toggle
							label={ __(
								'Send context to connected agents',
								'emcp-tools'
							) }
							checked={ !! values.enabled }
							onChange={ ( on ) =>
								form.setValue( 'enabled', on )
							}
						/>
					</Card>
				</div>
				<div className="eui-ctx__rail">
					<Card title={ __( 'Context size', 'emcp-tools' ) }>
						<div
							className={ `eui-ctx__meter${ over ? ' is-over' : '' }` }
						>
							<Meter
								value={ preview.tokens }
								max={ data.budget }
								warnAt={ data.budget }
								label={ __( 'Estimated tokens', 'emcp-tools' ) }
								valueText={ sprintf(
									/* translators: 1: estimated tokens, e.g. "3.2k", 2: guide, e.g. "8k". */
									__( '%1$s of %2$s tokens', 'emcp-tools' ),
									kilo( preview.tokens ),
									kilo( data.budget )
								) }
							/>
						</div>
						<p
							className={ `eui-ctx__hint${ over ? ' is-over' : '' }` }
						>
							{ __(
								'Smaller context leaves more room for the actual task. Turn off detected items the AI does not need.',
								'emcp-tools'
							) }
						</p>
					</Card>
					<div className="eui-ctx__preview">
						<p className="eui-ctx__preview-label">
							{ __( 'Preview', 'emcp-tools' ) }
						</p>
						<pre className="eui-ctx__preview-text" tabIndex={ 0 }>
							{ lines.slice( 0, PREVIEW_LINES ).join( '\n' ) }
							{ lines.length > PREVIEW_LINES ? '\n…' : '' }
						</pre>
					</div>
					<Button
						variant="primary"
						disabled={ ! form.dirty }
						loading={ form.saving }
						onClick={ form.submit }
					>
						{ __( 'Save context', 'emcp-tools' ) }
					</Button>
				</div>
			</div>
			<SaveBar
				count={ form.count }
				hint={ __(
					'Agents see changes the next time they connect',
					'emcp-tools'
				) }
				onDiscard={ form.discard }
				onSave={ form.submit }
				saving={ form.saving }
				saveLabel={ __( 'Save changes', 'emcp-tools' ) }
			/>
			{ drawer && (
				<Drawer
					open
					title={ __( 'What the AI sees', 'emcp-tools' ) }
					onClose={ () => setDrawer( false ) }
					width={ 720 }
				>
					<CodeBlock
						value={ preview.text }
						label={ __( 'Server instructions', 'emcp-tools' ) }
					/>
				</Drawer>
			) }
		</div>
	);
}
