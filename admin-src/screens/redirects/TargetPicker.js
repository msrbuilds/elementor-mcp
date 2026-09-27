import { useEffect, useId, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Field, request } from '@emcp/ui';

/**
 * Whether text is a redirect target as typed: a root-relative path or an
 * http(s) URL (the server applies the same rule).
 *
 * @param {string} v Text.
 * @return {boolean} Whether it is a URL.
 */
export const looksLikeUrl = ( v ) => /^(\/(?!\/)|https?:\/\/)/i.test( v );

/**
 * The "To" field: a URL, or a published page found by title (a combobox:
 * focus stays in the input, arrows move the active option, Enter picks it).
 *
 * @param {Object}              props
 * @param {Object}              props.value    { value, postId }.
 * @param {(v: Object) => void} props.onChange Called with { value, postId }.
 * @param {string}              props.error    Field error.
 * @param {Object}              props.inputRef Ref for the input.
 */
export function TargetPicker( { value, onChange, error, inputRef } ) {
	const [ items, setItems ] = useState( [] );
	const [ open, setOpen ] = useState( false );
	const [ active, setActive ] = useState( -1 );
	const listId = useId();
	const timer = useRef();
	const seq = useRef( 0 );

	useEffect( () => () => clearTimeout( timer.current ), [] );

	const close = () => {
		setOpen( false );
		setActive( -1 );
	};

	const choose = ( it ) => {
		onChange( { value: it.title, postId: it.id } );
		close();
	};

	const type = ( text ) => {
		onChange( { value: text, postId: 0 } );
		clearTimeout( timer.current );
		const mine = ++seq.current;
		if ( text.trim().length < 2 || looksLikeUrl( text.trim() ) ) {
			close();
			return;
		}
		timer.current = setTimeout( async () => {
			try {
				const r = await request(
					`/emcp-tools/v1/admin/redirects/targets?search=${ encodeURIComponent(
						text.trim()
					) }`
				);
				if ( mine !== seq.current ) {
					return; // A newer keystroke owns the list.
				}
				setItems( r.items || [] );
				setActive( -1 );
				setOpen( ( r.items || [] ).length > 0 );
			} catch {
				close();
			}
		}, 250 );
	};

	const onKeyDown = ( e ) => {
		if ( ! open ) {
			return;
		}
		if ( 'ArrowDown' === e.key ) {
			e.preventDefault();
			setActive( ( i ) => ( i + 1 ) % items.length );
		} else if ( 'ArrowUp' === e.key ) {
			e.preventDefault();
			setActive( ( i ) => ( i <= 0 ? items.length - 1 : i - 1 ) );
		} else if ( 'Enter' === e.key && active >= 0 ) {
			e.preventDefault();
			choose( items[ active ] );
		} else if ( 'Escape' === e.key ) {
			e.preventDefault();
			close();
		}
	};

	const optionId = ( i ) => `${ listId }-${ i }`;

	return (
		<div className="emcp-redirects__target">
			<Field
				label={ __( 'To', 'emcp-tools' ) }
				error={ error }
				help={ __(
					'A URL such as /new-page, or type a page title to search.',
					'emcp-tools'
				) }
			>
				{ ( p ) => (
					<div className="emcp-redirects__anchor">
						<input
							{ ...p }
							ref={ inputRef }
							type="text"
							className="eui-input"
							role="combobox"
							autoComplete="off"
							aria-expanded={ open ? 'true' : 'false' }
							aria-controls={ listId }
							aria-autocomplete="list"
							aria-activedescendant={
								open && active >= 0
									? optionId( active )
									: undefined
							}
							value={ value.value }
							onChange={ ( e ) => type( e.target.value ) }
							onKeyDown={ onKeyDown }
							onBlur={ close }
						/>
						<ul
							id={ listId }
							role="listbox"
							aria-label={ __( 'Pages', 'emcp-tools' ) }
							className="emcp-redirects__options"
							hidden={ ! open }
						>
							{ open &&
								items.map( ( it, i ) => (
									// Keyboard selection happens in the combobox input (aria-activedescendant).
									// eslint-disable-next-line jsx-a11y/click-events-have-key-events
									<li
										key={ it.id }
										id={ optionId( i ) }
										role="option"
										aria-selected={
											i === active ? 'true' : 'false'
										}
										className={
											i === active
												? 'is-active'
												: undefined
										}
										onMouseDown={ ( e ) =>
											e.preventDefault()
										}
										onClick={ () => choose( it ) }
									>
										<span>{ it.title }</span>
										<span className="emcp-redirects__type">
											{ it.type }
										</span>
									</li>
								) ) }
						</ul>
					</div>
				) }
			</Field>
		</div>
	);
}
