import { forwardRef, useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon } from './Icon';
import { cx } from '../utils/cx';
import './Form.css';

export function Toggle( {
	checked,
	onChange,
	label,
	hideLabel = false,
	disabled = false,
	size = 'md',
	id,
} ) {
	const auto = useId();
	const buttonId = id || `eui-toggle-${ auto }`;
	return (
		<span
			className={ cx(
				'eui-toggle',
				`eui-toggle--${ size }`,
				disabled && 'is-disabled'
			) }
		>
			<button
				id={ buttonId }
				type="button"
				role="switch"
				aria-checked={ checked ? 'true' : 'false' }
				disabled={ disabled }
				className="eui-toggle__track"
				onClick={ () => onChange( ! checked ) }
			>
				<span className="eui-toggle__knob" aria-hidden="true" />
			</button>
			<label
				htmlFor={ buttonId }
				className={ cx(
					'eui-toggle__label',
					hideLabel && 'eui-visually-hidden'
				) }
			>
				{ label }
			</label>
		</span>
	);
}

export function Checkbox( { checked, onChange, label, disabled = false, id } ) {
	const auto = useId();
	const inputId = id || `eui-check-${ auto }`;
	return (
		<label
			htmlFor={ inputId }
			className={ cx( 'eui-check', disabled && 'is-disabled' ) }
		>
			<input
				id={ inputId }
				type="checkbox"
				className="eui-check__input"
				checked={ !! checked }
				disabled={ disabled }
				onChange={ ( e ) => onChange( e.target.checked ) }
			/>
			<span className="eui-check__box" aria-hidden="true">
				<Icon name="check" size={ 12 } />
			</span>
			<span className="eui-check__label">{ label }</span>
		</label>
	);
}

export function Field( {
	label,
	help,
	error,
	optional = false,
	id,
	children,
} ) {
	const auto = useId();
	const fieldId = id || `eui-field-${ auto }`;
	const helpId = help ? `${ fieldId }-help` : undefined;
	const errorId = error ? `${ fieldId }-error` : undefined;
	const describedBy =
		[ helpId, errorId ].filter( Boolean ).join( ' ' ) || undefined;
	return (
		<div className={ cx( 'eui-field', error && 'has-error' ) }>
			<label className="eui-field__label" htmlFor={ fieldId }>
				{ label }
				{ optional && (
					<span className="eui-field__optional">
						{ ' ' }
						{ __( 'optional', 'emcp-tools' ) }
					</span>
				) }
			</label>
			{ children( {
				id: fieldId,
				'aria-describedby': describedBy,
				'aria-invalid': error ? 'true' : undefined,
			} ) }
			{ help && (
				<p id={ helpId } className="eui-field__help">
					{ help }
				</p>
			) }
			{ error && (
				<p id={ errorId } className="eui-field__error" role="alert">
					{ error }
				</p>
			) }
		</div>
	);
}

export const TextInput = forwardRef( function TextInputComponent(
	{ mono = false, className, type = 'text', ...rest },
	ref
) {
	return (
		<input
			ref={ ref }
			type={ type }
			className={ cx( 'eui-input', mono && 'eui-mono', className ) }
			{ ...rest }
		/>
	);
} );

export const Textarea = forwardRef( function TextareaComponent(
	{ mono = false, className, rows = 4, ...rest },
	ref
) {
	return (
		<textarea
			ref={ ref }
			rows={ rows }
			className={ cx(
				'eui-input',
				'eui-textarea',
				mono && 'eui-mono',
				className
			) }
			{ ...rest }
		/>
	);
} );

export const Select = forwardRef( function SelectComponent(
	{ options, value, onChange, className, ...rest },
	ref
) {
	return (
		<span className={ cx( 'eui-select', className ) }>
			<select
				ref={ ref }
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
				{ ...rest }
			>
				{ options.map( ( o ) => (
					<option
						key={ o.value }
						value={ o.value }
						disabled={ o.disabled }
					>
						{ o.label }
					</option>
				) ) }
			</select>
			<Icon name="chevron-down" className="eui-select__chevron" />
		</span>
	);
} );
