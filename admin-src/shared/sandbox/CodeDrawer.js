import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	CodeBlock,
	Drawer,
	Notice,
	Skeleton,
	Tabs,
	errorMessage,
	request,
} from '@emcp/ui';
import { API } from './api';

/**
 * The code of one Sandbox item, fetched when the Drawer opens.
 *
 * @param {Object}                     props         Props.
 * @param {boolean}                    props.open    Open.
 * @param {string}                     props.type    widgets | blocks | snippets.
 * @param {number}                     props.id      Item id.
 * @param {string}                     props.title   Item title ('' until the detail arrives).
 * @param {() => void}                 props.onClose Close.
 * @param {(detail: Object) => Object} [props.below] ( detail ) => node shown under the code.
 */
export function CodeDrawer( { open, type, id, title, onClose, below } ) {
	const [ state, setState ] = useState( { loading: false } );
	const [ tab, setTab ] = useState( '' );
	const ticket = useRef( 0 );

	useEffect( () => {
		if ( ! open || ! id ) {
			return;
		}
		const mine = ++ticket.current;
		setState( { loading: true } );
		request( `${ API }/${ type }/${ id }` )
			.then( ( detail ) => {
				if ( mine === ticket.current ) {
					setState( { detail } );
					setTab(
						detail.tabs && detail.tabs[ 0 ]
							? detail.tabs[ 0 ].id
							: ''
					);
				}
			} )
			.catch( ( e ) => {
				if ( mine === ticket.current ) {
					setState( { error: errorMessage( e ) } );
				}
			} );
	}, [ open, type, id ] );

	const detail = state.detail;
	const current =
		detail && detail.tabs
			? detail.tabs.find( ( t ) => t.id === tab ) || detail.tabs[ 0 ]
			: null;
	const heading =
		title ||
		( detail && detail.row ? detail.row.title : '' ) ||
		__( 'Code', 'emcp-tools' );

	return (
		<Drawer
			open={ open }
			title={ heading }
			onClose={ onClose }
			width={ 760 }
		>
			{ state.loading && <Skeleton lines={ 6 } /> }
			{ state.error && <Notice tone="danger">{ state.error }</Notice> }
			{ current && (
				<>
					{ detail.tabs.length > 1 && (
						<Tabs
							label={ __( 'Files', 'emcp-tools' ) }
							idPrefix="emcp-sb-code"
							value={ current.id }
							onChange={ setTab }
							options={ detail.tabs.map( ( t ) => ( {
								value: t.id,
								label: t.label,
							} ) ) }
						/>
					) }
					<div
						role={ detail.tabs.length > 1 ? 'tabpanel' : undefined }
						id={ `emcp-sb-code-panel-${ current.id }` }
						aria-labelledby={
							detail.tabs.length > 1
								? `emcp-sb-code-tab-${ current.id }`
								: undefined
						}
						className="emcp-sb-code"
					>
						<CodeBlock
							label={ current.label }
							value={ current.value }
						/>
					</div>
					{ below && below( detail ) }
				</>
			) }
		</Drawer>
	);
}
