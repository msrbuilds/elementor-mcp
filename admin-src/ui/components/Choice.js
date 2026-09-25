import { useRef } from '@wordpress/element';
import { rovingKeyDown } from '../utils/roving';
import { cx } from '../utils/cx';
import { Icon } from './Icon';
import './Choice.css';

function useGroup( options, value, onChange ) {
	const refs = useRef( [] );
	const selected = options.findIndex( ( o ) => o.value === value );
	const tabStop = selected >= 0 ? selected : 0;
	const move = ( i ) => {
		onChange( options[ i ].value );
		refs.current[ i ]?.focus();
	};
	return { refs, tabStop, move };
}

export function Segmented( { label, options, value, onChange, className } ) {
	const { refs, tabStop, move } = useGroup( options, value, onChange );
	return (
		<div
			role="radiogroup"
			aria-label={ label }
			className={ cx( 'eui-seg', className ) }
		>
			{ options.map( ( o, i ) => (
				<button
					key={ o.value }
					ref={ ( el ) => ( refs.current[ i ] = el ) }
					type="button"
					role="radio"
					aria-checked={ o.value === value ? 'true' : 'false' }
					tabIndex={ i === tabStop ? 0 : -1 }
					disabled={ o.disabled }
					className={ cx(
						'eui-seg__opt',
						o.value === value && 'is-active'
					) }
					onClick={ () => onChange( o.value ) }
					onKeyDown={ ( e ) =>
						rovingKeyDown( e, {
							index: i,
							count: options.length,
							onMove: move,
							isDisabled: ( j ) => !! options[ j ].disabled,
						} )
					}
				>
					<span>{ o.label }</span>
					{ undefined !== o.count && (
						<span className="eui-seg__count">{ o.count }</span>
					) }
				</button>
			) ) }
		</div>
	);
}

export function Tabs( {
	label,
	options,
	value,
	onChange,
	idPrefix,
	className,
} ) {
	const { refs, tabStop, move } = useGroup( options, value, onChange );
	return (
		<div
			role="tablist"
			aria-label={ label }
			className={ cx( 'eui-tabs', className ) }
		>
			{ options.map( ( o, i ) => (
				<button
					key={ o.value }
					id={ `${ idPrefix }-tab-${ o.value }` }
					ref={ ( el ) => ( refs.current[ i ] = el ) }
					type="button"
					role="tab"
					aria-selected={ o.value === value ? 'true' : 'false' }
					aria-controls={ `${ idPrefix }-panel-${ o.value }` }
					tabIndex={ i === tabStop ? 0 : -1 }
					className={ cx(
						'eui-tabs__tab',
						o.value === value && 'is-active'
					) }
					onClick={ () => onChange( o.value ) }
					onKeyDown={ ( e ) =>
						rovingKeyDown( e, {
							index: i,
							count: options.length,
							onMove: move,
							isDisabled: ( j ) => !! options[ j ].disabled,
						} )
					}
				>
					<span>{ o.label }</span>
					{ undefined !== o.count && (
						<span className="eui-tabs__count eui-mono">
							{ o.count }
						</span>
					) }
				</button>
			) ) }
		</div>
	);
}

export function FilterChip( {
	label,
	active = false,
	count,
	onClick,
	onRemove,
	removeLabel,
} ) {
	return (
		<span className={ cx( 'eui-chip', active && 'is-active' ) }>
			<button
				type="button"
				className="eui-chip__main"
				aria-pressed={ active ? 'true' : 'false' }
				onClick={ onClick }
			>
				<span>{ label }</span>
				{ undefined !== count && (
					<span className="eui-chip__count">{ count }</span>
				) }
			</button>
			{ onRemove && (
				<button
					type="button"
					className="eui-chip__remove"
					aria-label={ removeLabel }
					onClick={ onRemove }
				>
					<Icon name="x" size={ 12 } />
				</button>
			) }
		</span>
	);
}
