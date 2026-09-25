import { createContext, useContext, useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from './Button';
import { Icon } from './Icon';
import { cx } from '../utils/cx';
import './Steps.css';

export function Stepper( { label, children } ) {
	return (
		<ol className="eui-stepper" aria-label={ label }>
			{ children }
		</ol>
	);
}

export function Step( {
	number,
	title,
	meta,
	status,
	summary,
	onEdit,
	children,
} ) {
	return (
		<li
			className={ cx( 'eui-step', `is-${ status }` ) }
			aria-current={ 'active' === status ? 'step' : undefined }
		>
			<span className="eui-step__marker" aria-hidden="true">
				{ 'complete' === status ? (
					<Icon name="check" size={ 14 } />
				) : (
					number
				) }
			</span>
			<div className="eui-step__main">
				<div className="eui-step__head">
					<h3 className="eui-step__title">{ title }</h3>
					{ meta && <span className="eui-step__meta">{ meta }</span> }
				</div>
				{ 'complete' === status && (
					<div className="eui-step__summary">
						<span>{ summary }</span>
						{ onEdit && (
							<Button
								variant="ghost"
								size="sm"
								onClick={ onEdit }
							>
								{ __( 'Edit', 'emcp-tools' ) }
							</Button>
						) }
					</div>
				) }
				{ 'active' === status && (
					<div className="eui-step__body">{ children }</div>
				) }
				{ 'locked' === status && summary && (
					<p className="eui-step__summary">{ summary }</p>
				) }
			</div>
		</li>
	);
}

const RadioContext = createContext( null );

export function RadioCardGroup( {
	legend,
	name,
	value,
	onChange,
	columns = 2,
	children,
} ) {
	return (
		<fieldset
			className="eui-radio-cards"
			style={ { '--eui-cols': columns } }
		>
			<legend className="eui-visually-hidden">{ legend }</legend>
			<RadioContext.Provider value={ { name, value, onChange } }>
				{ children }
			</RadioContext.Provider>
		</fieldset>
	);
}

export function RadioCard( {
	value,
	title,
	description,
	icon,
	tag,
	disabled = false,
	requirement,
} ) {
	const group = useContext( RadioContext );
	const id = useId();
	const checked = group.value === value;
	return (
		<label
			htmlFor={ id }
			className={ cx(
				'eui-radio-card',
				checked && 'is-checked',
				disabled && 'is-disabled'
			) }
		>
			<input
				id={ id }
				type="radio"
				className="eui-radio-card__input"
				name={ group.name }
				value={ value }
				checked={ checked }
				disabled={ disabled }
				onChange={ () => group.onChange( value ) }
			/>
			<span className="eui-radio-card__dot" aria-hidden="true" />
			<span className="eui-radio-card__text">
				<span className="eui-radio-card__title">
					{ title }
					{ tag }
				</span>
				{ description && (
					<span className="eui-radio-card__desc">
						{ description }
					</span>
				) }
				{ requirement && (
					<span className="eui-radio-card__req">
						<Icon name="lock" size={ 12 } />
						{ requirement }
					</span>
				) }
			</span>
			{ icon && <Icon name={ icon } className="eui-radio-card__icon" /> }
		</label>
	);
}
