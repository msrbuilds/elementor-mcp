import { useEffect, useId, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Dialog, cx } from '@emcp/ui';
import { buildIndex, searchPalette } from './palette-search';

const kindLabels = () => ( {
	screen: __( 'Screen', 'emcp-tools' ),
	tool: __( 'Tool', 'emcp-tools' ),
	setting: __( 'Setting', 'emcp-tools' ),
} );

/**
 * Command palette: screens, tools and settings (spec 7).
 *
 * @param {Object}                props          Props.
 * @param {boolean}               props.open     Whether it is open.
 * @param {() => void}            props.onClose  Close handler.
 * @param {Object}                props.data     window.emcpShell.
 * @param {(url: string) => void} props.navigate Navigation.
 */
export function Palette( { open, onClose, data, navigate } ) {
	const index = useMemo( () => buildIndex( data ), [ data ] );
	const [ query, setQuery ] = useState( '' );
	const [ active, setActive ] = useState( 0 );
	const listId = useId();
	const results = useMemo(
		() => searchPalette( index, query ),
		[ index, query ]
	);

	useEffect( () => {
		if ( open ) {
			setQuery( '' );
			setActive( 0 );
		}
	}, [ open ] );
	useEffect( () => setActive( 0 ), [ query ] );

	const go = ( item ) => {
		if ( item ) {
			onClose();
			navigate( item.url );
		}
	};
	const onKeyDown = ( e ) => {
		if ( 'ArrowDown' === e.key ) {
			e.preventDefault();
			setActive( ( a ) => Math.min( a + 1, results.length - 1 ) );
		} else if ( 'ArrowUp' === e.key ) {
			e.preventDefault();
			setActive( ( a ) => Math.max( a - 1, 0 ) );
		} else if ( 'Enter' === e.key ) {
			e.preventDefault();
			go( results[ active ] );
		}
	};
	const kinds = kindLabels();

	return (
		<Dialog
			open={ open }
			title={ __( 'Search EMCP Tools', 'emcp-tools' ) }
			onClose={ onClose }
			size="md"
		>
			<input
				type="text"
				className="eui-input eui-palette__input"
				role="combobox"
				aria-label={ __(
					'Search screens, tools and settings',
					'emcp-tools'
				) }
				aria-expanded="true"
				aria-controls={ listId }
				aria-activedescendant={
					results[ active ] ? `${ listId }-${ active }` : undefined
				}
				value={ query }
				onChange={ ( e ) => setQuery( e.target.value ) }
				onKeyDown={ onKeyDown }
			/>
			<ul
				id={ listId }
				role="listbox"
				className="eui-palette__list"
				aria-label={ __( 'Results', 'emcp-tools' ) }
			>
				{ results.map( ( item, i ) => (
					// Keyboard selection is handled on the combobox (arrows and Enter).
					// eslint-disable-next-line jsx-a11y/click-events-have-key-events
					<li
						key={ `${ item.kind }-${ item.url }` }
						id={ `${ listId }-${ i }` }
						role="option"
						aria-selected={ i === active ? 'true' : 'false' }
						className={ cx(
							'eui-palette__item',
							i === active && 'is-active'
						) }
						onMouseEnter={ () => setActive( i ) }
						onClick={ () => go( item ) }
					>
						<span className="eui-palette__label">
							{ item.label }
						</span>
						<span className="eui-palette__hint">
							{ item.hint || kinds[ item.kind ] }
						</span>
					</li>
				) ) }
				{ ! results.length && (
					<li className="eui-palette__empty" role="presentation">
						{ __( 'No matches.', 'emcp-tools' ) }
					</li>
				) }
			</ul>
		</Dialog>
	);
}
