import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon } from './Icon';
import { IconButton } from './Button';
import { cx } from '../utils/cx';
import './SearchInput.css';

/**
 * Search box with a leading icon, a clear button and a debounced onChange
 * (spec 6.3; spec 7 debounces search by 250 ms). Clearing reports at once.
 *
 * @param {Object}   props
 * @param {string}   props.label         Accessible name.
 * @param {string}   props.value         Current value from the parent.
 * @param {Function} props.onChange      ( value ) => void.
 * @param {number}   [props.debounce]    Milliseconds to wait before reporting.
 * @param {string}   [props.placeholder] Placeholder text.
 * @param {string}   [props.className]   Extra classes.
 */
export function SearchInput( {
	label,
	value,
	onChange,
	debounce = 0,
	placeholder,
	className,
} ) {
	const [ text, setText ] = useState( value ?? '' );
	const timer = useRef();

	useEffect( () => {
		setText( value ?? '' );
	}, [ value ] );
	useEffect( () => () => clearTimeout( timer.current ), [] );

	const report = ( next ) => {
		clearTimeout( timer.current );
		if ( debounce > 0 ) {
			timer.current = setTimeout( () => onChange( next ), debounce );
		} else {
			onChange( next );
		}
	};

	const clear = () => {
		clearTimeout( timer.current );
		setText( '' );
		onChange( '' );
	};

	return (
		<span className={ cx( 'eui-search', className ) }>
			<Icon name="search" className="eui-search__icon" />
			<input
				type="search"
				className="eui-input eui-search__input"
				aria-label={ label }
				placeholder={ placeholder }
				value={ text }
				onChange={ ( e ) => {
					setText( e.target.value );
					report( e.target.value );
				} }
			/>
			{ '' !== text && (
				<IconButton
					icon="x"
					size="sm"
					label={ __( 'Clear search', 'emcp-tools' ) }
					className="eui-search__clear"
					onClick={ clear }
				/>
			) }
		</span>
	);
}
