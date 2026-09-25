import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { request, useConfirm } from '@emcp/ui';
import { Palette } from './Palette';
import { Notifications } from './Notifications';

const defaultNavigate = ( url ) => window.location.assign( url );

/**
 * Behaviour on top of the server-rendered frame: palette (Ctrl/Cmd+K and the
 * sidebar search), notifications drawer, unsaved-changes guard on sidebar
 * links, and promo dismissal.
 *
 * @param {Object}                props          Props.
 * @param {Object}                props.data     window.emcpShell.
 * @param {(url: string) => void} props.navigate Navigation (injectable for tests).
 * @param {Document}              props.doc      Document.
 */
export function ShellApp( {
	data,
	navigate = defaultNavigate,
	doc = document,
} ) {
	const confirm = useConfirm();
	const [ paletteOpen, setPaletteOpen ] = useState( false );
	const [ drawerOpen, setDrawerOpen ] = useState( false );
	const [ items, setItems ] = useState( data.notifications || [] );

	useEffect( () => {
		// WordPress core binds Ctrl/Cmd+K to its own command palette. On EMCP
		// screens ours wins: take the key in the window's capture phase and stop
		// it before it reaches core's document listener.
		const view = doc.defaultView || window;
		const onKey = ( e ) => {
			if (
				( e.ctrlKey || e.metaKey ) &&
				! e.altKey &&
				'k' === String( e.key ).toLowerCase()
			) {
				e.preventDefault();
				e.stopPropagation();
				setPaletteOpen( true );
			}
		};
		const onClick = async ( e ) => {
			const target = e.target instanceof window.Element ? e.target : null;
			if ( ! target ) {
				return;
			}
			if ( target.closest( '[data-emcp-palette-open]' ) ) {
				setPaletteOpen( true );
				return;
			}
			if ( target.closest( '[data-emcp-notifications-open]' ) ) {
				setDrawerOpen( true );
				return;
			}
			const dismiss = target.closest( '[data-emcp-promo-dismiss]' );
			if ( dismiss ) {
				doc.querySelector( '[data-emcp-promo]' )?.remove();
				request( '/emcp-tools/v1/admin/promo/dismiss', {
					method: 'POST',
					data: {
						id: dismiss.getAttribute( 'data-emcp-promo-dismiss' ),
					},
				} ).catch( () => {} );
				return;
			}
			const link = target.closest( 'a[data-emcp-nav]' );
			if (
				link &&
				'1' === doc.documentElement.dataset.emcpDirty &&
				! link.target
			) {
				e.preventDefault();
				const leave = await confirm( {
					title: __( 'Leave without saving?', 'emcp-tools' ),
					message: __(
						'Your unsaved changes on this screen will be lost.',
						'emcp-tools'
					),
					confirmLabel: __( 'Leave', 'emcp-tools' ),
					cancelLabel: __( 'Stay', 'emcp-tools' ),
					tone: 'danger',
				} );
				if ( leave ) {
					delete doc.documentElement.dataset.emcpDirty;
					navigate( link.getAttribute( 'href' ) );
				}
			}
		};
		view.addEventListener( 'keydown', onKey, true );
		doc.addEventListener( 'click', onClick );
		return () => {
			view.removeEventListener( 'keydown', onKey, true );
			doc.removeEventListener( 'click', onClick );
		};
	}, [ confirm, doc, navigate ] );

	const onRead = ( unread ) => {
		setItems( ( list ) =>
			list.map( ( n ) => ( { ...n, unread: false } ) )
		);
		const dot = doc.querySelector( '[data-emcp-unread]' );
		if ( dot ) {
			dot.hidden = unread <= 0;
		}
	};

	return (
		<>
			<Palette
				open={ paletteOpen }
				onClose={ () => setPaletteOpen( false ) }
				data={ data }
				navigate={ navigate }
			/>
			<Notifications
				open={ drawerOpen }
				onClose={ () => setDrawerOpen( false ) }
				items={ items }
				onRead={ onRead }
			/>
		</>
	);
}
