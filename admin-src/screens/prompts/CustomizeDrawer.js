import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Drawer,
	Field,
	Select,
	TextInput,
	Textarea,
	copyText,
} from '@emcp/ui';
import { CUSTOM_BUILDERS, applyCustomization, parsePrompt } from './customize';
import { handoffUrl, newHandoffId, stashHandoff } from './handoff';

// Suggestions for the font fields; any Google font name can be typed.
const FONTS = [
	'Inter',
	'Inter Tight',
	'Roboto',
	'Open Sans',
	'Lato',
	'Montserrat',
	'Poppins',
	'Nunito',
	'Nunito Sans',
	'Source Sans 3',
	'Work Sans',
	'Karla',
	'DM Sans',
	'Manrope',
	'Outfit',
	'Plus Jakarta Sans',
	'Figtree',
	'Space Grotesk',
	'Bricolage Grotesque',
	'Barlow',
	'Barlow Condensed',
	'Oswald',
	'Anton',
	'Bebas Neue',
	'Archivo Black',
	'Playfair Display',
	'DM Serif Display',
	'Fraunces',
	'Cormorant Garamond',
	'EB Garamond',
	'Libre Baskerville',
	'Lora',
	'Merriweather',
	'Bodoni Moda',
	'Abril Fatface',
	'Alfa Slab One',
	'Fredoka',
	'Quicksand',
	'Baloo 2',
	'Shippori Mincho',
];

const SIX = /^#[0-9a-f]{6}$/i;
const THREE = /^#[0-9a-f]{3}$/i;

// <input type="color"> takes only #rrggbb in lower case.
function swatch( hex ) {
	if ( SIX.test( hex ) ) {
		return hex.toLowerCase();
	}
	if ( THREE.test( hex ) ) {
		return (
			'#' + [ ...hex.slice( 1 ) ].map( ( c ) => c + c ).join( '' )
		).toLowerCase();
	}
	return '#000000';
}

function initialValues( parsed ) {
	return {
		builder: parsed.builder,
		name: parsed.name,
		colors: Object.fromEntries(
			parsed.colors.map( ( c ) => [ c.hex.toLowerCase(), c.hex ] )
		),
		fonts: Object.fromEntries(
			parsed.fonts.map( ( f ) => [ f.role, f.font ] )
		),
		facts: Object.fromEntries(
			parsed.facts.map( ( f ) => [ f.label, f.value ] )
		),
	};
}

/**
 * The Customize side panel: every part of a library prompt a user can
 * change (page builder, business name, colours, fonts, content facts), the
 * customized prompt as it will be sent, and Copy / Use in AI Chat.
 *
 * @param {Object}     props
 * @param {Object}     props.prompt    Prompt item.
 * @param {string}     props.aiChatUrl AI Chat page, or ''.
 * @param {() => void} props.onClose   Close the panel.
 */
export function CustomizeDrawer( { prompt, aiChatUrl, onClose } ) {
	const parsed = useMemo(
		() => parsePrompt( prompt.content ),
		[ prompt.content ]
	);
	const [ values, setValues ] = useState( () => initialValues( parsed ) );
	const [ copied, setCopied ] = useState( false );
	const timer = useRef();
	const handoffId = useRef( newHandoffId() );
	useEffect( () => () => clearTimeout( timer.current ), [] );

	const text = useMemo(
		() => applyCustomization( prompt.content, parsed, values ),
		[ prompt.content, parsed, values ]
	);
	const changed = text !== prompt.content;
	const set = ( group, key, value ) =>
		setValues( ( v ) => ( {
			...v,
			[ group ]: { ...v[ group ], [ key ]: value },
		} ) );

	const copy = async () => {
		if ( ! ( await copyText( text ) ) ) {
			return;
		}
		setCopied( true );
		clearTimeout( timer.current );
		timer.current = setTimeout( () => setCopied( false ), 2000 );
	};
	const handOff = () => stashHandoff( handoffId.current, text );

	const builders = CUSTOM_BUILDERS.includes( parsed.builder )
		? CUSTOM_BUILDERS
		: [ parsed.builder, ...CUSTOM_BUILDERS ].filter( Boolean );
	const empty =
		! parsed.builder &&
		! parsed.name &&
		! parsed.colors.length &&
		! parsed.fonts.length &&
		! parsed.facts.length;

	return (
		<Drawer
			open
			title={ sprintf(
				/* translators: %s: prompt title. */
				__( 'Customize %s', 'emcp-tools' ),
				prompt.title
			) }
			onClose={ onClose }
			width={ 600 }
			footer={
				<>
					<Button
						onClick={ () => setValues( initialValues( parsed ) ) }
						disabled={ ! changed }
					>
						{ __( 'Reset', 'emcp-tools' ) }
					</Button>
					<Button icon={ copied ? 'check' : 'copy' } onClick={ copy }>
						{ copied
							? __( 'Copied', 'emcp-tools' )
							: __( 'Copy prompt', 'emcp-tools' ) }
					</Button>
					{ aiChatUrl && (
						<a
							className="eui-btn eui-btn--primary eui-btn--md"
							href={ handoffUrl( aiChatUrl, handoffId.current ) }
							target="_blank"
							rel="noopener noreferrer"
							onClick={ handOff }
							onAuxClick={ handOff }
						>
							<span className="eui-btn__label">
								{ __( 'Use in AI Chat', 'emcp-tools' ) }{ ' ' }
								<span className="eui-visually-hidden">
									{ __(
										'(opens in a new tab)',
										'emcp-tools'
									) }
								</span>
							</span>
						</a>
					) }
				</>
			}
		>
			<div className="eui-customize">
				{ empty && (
					<p className="eui-customize__empty">
						{ __(
							'This prompt has no builder, business, colour, font or content fields to change.',
							'emcp-tools'
						) }
					</p>
				) }
				{ ( parsed.builder || parsed.name ) && (
					<section className="eui-customize__section">
						<h3 className="eui-customize__title">
							{ __( 'Basics', 'emcp-tools' ) }
						</h3>
						{ parsed.builder && (
							<Field label={ __( 'Page builder', 'emcp-tools' ) }>
								{ ( a ) => (
									<Select
										{ ...a }
										value={ values.builder }
										onChange={ ( v ) =>
											setValues( ( s ) => ( {
												...s,
												builder: v,
											} ) )
										}
										options={ builders.map( ( b ) => ( {
											value: b,
											label: b,
										} ) ) }
									/>
								) }
							</Field>
						) }
						{ parsed.name && (
							<Field
								label={ __( 'Business name', 'emcp-tools' ) }
								help={ __(
									'Replaced everywhere the prompt names the business.',
									'emcp-tools'
								) }
							>
								{ ( a ) => (
									<TextInput
										{ ...a }
										value={ values.name }
										onChange={ ( e ) =>
											setValues( ( s ) => ( {
												...s,
												name: e.target.value,
											} ) )
										}
									/>
								) }
							</Field>
						) }
					</section>
				) }
				{ parsed.colors.length > 0 && (
					<section className="eui-customize__section">
						<h3 className="eui-customize__title">
							{ __( 'Colours', 'emcp-tools' ) }
						</h3>
						<ul className="eui-customize__colors">
							{ parsed.colors.map( ( c ) => {
								const key = c.hex.toLowerCase();
								const role = c.roles.join( ' · ' );
								const current = values.colors[ key ];
								return (
									<li
										key={ key }
										className="eui-customize__color"
									>
										<input
											type="color"
											className="eui-customize__swatch"
											aria-label={ sprintf(
												/* translators: %s: colour role. */
												__( '%s colour', 'emcp-tools' ),
												role
											) }
											value={ swatch( current ) }
											onChange={ ( e ) =>
												set(
													'colors',
													key,
													e.target.value.toUpperCase()
												)
											}
										/>
										<span className="eui-customize__role">
											<span>{ role }</span>
											{ c.note && (
												<span className="eui-customize__note">
													{ c.note }
												</span>
											) }
										</span>
										<TextInput
											mono
											className="eui-customize__hex"
											aria-label={ sprintf(
												/* translators: %s: colour role. */
												__( '%s hex', 'emcp-tools' ),
												role
											) }
											value={ current }
											maxLength={ 7 }
											onChange={ ( e ) =>
												set(
													'colors',
													key,
													e.target.value
												)
											}
										/>
									</li>
								);
							} ) }
						</ul>
					</section>
				) }
				{ parsed.fonts.length > 0 && (
					<section className="eui-customize__section">
						<h3 className="eui-customize__title">
							{ __( 'Typography', 'emcp-tools' ) }
						</h3>
						<datalist id="eui-customize-fonts">
							{ FONTS.map( ( f ) => (
								<option key={ f } value={ f } />
							) ) }
						</datalist>
						<div className="eui-customize__pair">
							{ parsed.fonts.map( ( f ) => (
								<Field
									key={ f.role }
									label={ sprintf(
										/* translators: %s: font role, such as Display or Body. */
										__( '%s font', 'emcp-tools' ),
										f.role
									) }
								>
									{ ( a ) => (
										<TextInput
											{ ...a }
											list="eui-customize-fonts"
											value={ values.fonts[ f.role ] }
											onChange={ ( e ) =>
												set(
													'fonts',
													f.role,
													e.target.value
												)
											}
										/>
									) }
								</Field>
							) ) }
						</div>
					</section>
				) }
				{ parsed.facts.length > 0 && (
					<section className="eui-customize__section">
						<h3 className="eui-customize__title">
							{ __( 'Content', 'emcp-tools' ) }
						</h3>
						{ parsed.facts.map( ( f ) => (
							<Field key={ f.label } label={ f.label }>
								{ ( a ) => (
									<Textarea
										{ ...a }
										rows={ Math.min(
											5,
											Math.max(
												2,
												Math.ceil( f.value.length / 70 )
											)
										) }
										value={ values.facts[ f.label ] }
										onChange={ ( e ) =>
											set(
												'facts',
												f.label,
												e.target.value
											)
										}
									/>
								) }
							</Field>
						) ) }
					</section>
				) }
				<details className="eui-customize__preview">
					<summary>
						{ changed
							? __(
									'Preview the customized prompt',
									'emcp-tools'
								)
							: __( 'Preview the prompt', 'emcp-tools' ) }
					</summary>
					<pre
						className="eui-customize__text"
						aria-label={ __( 'Customized prompt', 'emcp-tools' ) }
						tabIndex={ 0 }
					>
						{ text }
					</pre>
				</details>
			</div>
		</Drawer>
	);
}
