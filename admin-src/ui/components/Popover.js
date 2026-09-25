import { useEffect, useId, useRef } from '@wordpress/element';
import { usePopover } from '../utils/usePopover';
import { rovingKeyDown } from '../utils/roving';
import { cx } from '../utils/cx';
import { Icon } from './Icon';
import { IconButton } from './Button';
import './Popover.css';

export function Menu( { label, items, icon = 'ellipsis', align = 'end' } ) {
	const pop = usePopover();
	const itemRefs = useRef( [] );
	const menuId = useId();

	useEffect( () => {
		if ( pop.open ) {
			itemRefs.current[ 0 ]?.focus();
		}
	}, [ pop.open ] );

	const onKeyDown = ( i ) => ( e ) =>
		rovingKeyDown( e, {
			index: i,
			count: items.length,
			orientation: 'vertical',
			onMove: ( t ) => itemRefs.current[ t ]?.focus(),
		} );

	return (
		<div className="eui-menu">
			<IconButton
				ref={ pop.triggerRef }
				icon={ icon }
				label={ label }
				aria-haspopup="menu"
				aria-expanded={ pop.open ? 'true' : 'false' }
				aria-controls={ pop.open ? menuId : undefined }
				onClick={ pop.toggle }
			/>
			{ pop.open && (
				<ul
					id={ menuId }
					ref={ pop.panelRef }
					role="menu"
					aria-label={ label }
					className={ cx(
						'eui-pop',
						'eui-menu__panel',
						`eui-pop--${ align }`
					) }
				>
					{ items.map( ( item, i ) => (
						<li key={ item.label } role="none">
							{ item.href ? (
								<a
									ref={ ( el ) =>
										( itemRefs.current[ i ] = el )
									}
									role="menuitem"
									tabIndex={ -1 }
									href={ item.href }
									className={ cx(
										'eui-menu__item',
										item.danger && 'is-danger'
									) }
									onKeyDown={ onKeyDown( i ) }
								>
									{ item.icon && <Icon name={ item.icon } /> }
									{ item.label }
								</a>
							) : (
								<button
									ref={ ( el ) =>
										( itemRefs.current[ i ] = el )
									}
									type="button"
									role="menuitem"
									tabIndex={ -1 }
									className={ cx(
										'eui-menu__item',
										item.danger && 'is-danger'
									) }
									onKeyDown={ onKeyDown( i ) }
									onClick={ () => {
										pop.close( true );
										item.onSelect?.();
									} }
								>
									{ item.icon && <Icon name={ item.icon } /> }
									{ item.label }
								</button>
							) }
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

export function Dropdown( {
	label,
	icon,
	badge,
	panelLabel,
	align = 'start',
	children,
} ) {
	const pop = usePopover();
	const panelId = useId();
	return (
		<div className="eui-dropdown">
			<button
				ref={ pop.triggerRef }
				type="button"
				className="eui-btn eui-btn--secondary eui-btn--md eui-dropdown__trigger"
				aria-expanded={ pop.open ? 'true' : 'false' }
				aria-controls={ pop.open ? panelId : undefined }
				onClick={ pop.toggle }
			>
				{ icon && <Icon name={ icon } /> }
				<span>{ label }</span>
				{ badge ? (
					<span className="eui-dropdown__badge">{ badge }</span>
				) : null }
				<Icon name="chevron-down" />
			</button>
			{ pop.open && (
				<div
					id={ panelId }
					ref={ pop.panelRef }
					role="dialog"
					aria-label={ panelLabel || label }
					className={ cx(
						'eui-pop',
						'eui-dropdown__panel',
						`eui-pop--${ align }`
					) }
				>
					{ 'function' === typeof children
						? children( { close: pop.close } )
						: children }
				</div>
			) }
		</div>
	);
}
