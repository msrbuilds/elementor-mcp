import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	Field,
	FilterChip,
	Select,
	TextInput,
	Textarea,
} from '@emcp/ui';

const VOICE_MAX = 8;

/**
 * Site profile: business name, industry, purpose and brand voice (spec 8.7).
 *
 * @param {Object}              props            Props.
 * @param {Object}              props.profile    { name, industry, purpose, voice[] }.
 * @param {string[]}            props.industries Industry options.
 * @param {string[]}            props.voices     Preset voices.
 * @param {(p: Object) => void} props.onChange   Receives the next profile.
 */
export function ProfileCard( { profile, industries, voices, onChange } ) {
	const [ custom, setCustom ] = useState( '' );
	const set = ( key, value ) => onChange( { ...profile, [ key ]: value } );
	const voice = profile.voice || [];
	const full = voice.length >= VOICE_MAX;
	const chips = [
		...voices,
		...voice.filter( ( v ) => ! voices.includes( v ) ),
	];
	const toggleVoice = ( v ) => {
		if ( voice.includes( v ) ) {
			set(
				'voice',
				voice.filter( ( x ) => x !== v )
			);
		} else if ( ! full ) {
			set( 'voice', [ ...voice, v ] );
		}
	};
	const addCustom = () => {
		const v = custom.trim();
		if ( ! v || full ) {
			return;
		}
		if ( ! voice.includes( v ) ) {
			set( 'voice', [ ...voice, v ] );
		}
		setCustom( '' );
	};

	return (
		<Card
			title={ __( 'Site profile', 'emcp-tools' ) }
			actions={
				<span className="eui-ctx__muted">
					{ __( 'Written by you', 'emcp-tools' ) }
				</span>
			}
		>
			<div className="eui-ctx__profile-grid">
				<Field label={ __( 'Business name', 'emcp-tools' ) }>
					{ ( a11y ) => (
						<TextInput
							{ ...a11y }
							value={ profile.name }
							maxLength={ 120 }
							onChange={ ( e ) => set( 'name', e.target.value ) }
						/>
					) }
				</Field>
				<Field label={ __( 'Industry', 'emcp-tools' ) }>
					{ ( a11y ) => (
						<Select
							{ ...a11y }
							value={ profile.industry }
							onChange={ ( v ) => set( 'industry', v ) }
							options={ [
								{
									value: '',
									label: __(
										'Choose an industry',
										'emcp-tools'
									),
								},
								...industries.map( ( i ) => ( {
									value: i,
									label: i,
								} ) ),
							] }
						/>
					) }
				</Field>
			</div>
			<Field label={ __( 'What the site is for', 'emcp-tools' ) }>
				{ ( a11y ) => (
					<Textarea
						{ ...a11y }
						rows={ 3 }
						maxLength={ 1000 }
						value={ profile.purpose }
						onChange={ ( e ) => set( 'purpose', e.target.value ) }
					/>
				) }
			</Field>
			<div className="eui-ctx__voice">
				<span className="eui-field__label" id="eui-ctx-voice">
					{ __( 'Brand voice', 'emcp-tools' ) }
				</span>
				<div
					className="eui-ctx__chips"
					role="group"
					aria-labelledby="eui-ctx-voice"
				>
					{ chips.map( ( v ) => (
						<FilterChip
							key={ v }
							label={ v }
							active={ voice.includes( v ) }
							onClick={ () => toggleVoice( v ) }
						/>
					) ) }
				</div>
				<div className="eui-ctx__add-voice">
					<TextInput
						aria-label={ __( 'Custom voice', 'emcp-tools' ) }
						value={ custom }
						maxLength={ 40 }
						disabled={ full }
						placeholder={ __( 'Add your own', 'emcp-tools' ) }
						onChange={ ( e ) => setCustom( e.target.value ) }
						onKeyDown={ ( e ) => {
							if ( 'Enter' === e.key ) {
								e.preventDefault();
								addCustom();
							}
						} }
					/>
					<Button
						size="sm"
						icon="plus"
						disabled={ full || ! custom.trim() }
						onClick={ addCustom }
					>
						{ __( 'Add voice', 'emcp-tools' ) }
					</Button>
				</div>
			</div>
		</Card>
	);
}
