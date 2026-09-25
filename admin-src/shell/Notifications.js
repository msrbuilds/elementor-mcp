import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Drawer, EmptyState, request } from '@emcp/ui';

/**
 * Notifications drawer (spec 8.26). Opening it marks unread items read.
 *
 * @param {Object}                   props         Props.
 * @param {boolean}                  props.open    Whether it is open.
 * @param {() => void}               props.onClose Close handler.
 * @param {Array}                    props.items   Notifications.
 * @param {(unread: number) => void} props.onRead  Called with the new unread count.
 */
export function Notifications( { open, onClose, items, onRead } ) {
	useEffect( () => {
		const unread = items.filter( ( n ) => n.unread ).map( ( n ) => n.id );
		if ( ! open || ! unread.length ) {
			return;
		}
		request( '/emcp-tools/v1/admin/notifications/read', {
			method: 'POST',
			data: { ids: unread },
		} )
			.then( ( res ) =>
				onRead( res && 'number' === typeof res.unread ? res.unread : 0 )
			)
			.catch( () => {} );
	}, [ open ] ); // eslint-disable-line react-hooks/exhaustive-deps

	return (
		<Drawer
			open={ open }
			title={ __( 'Notifications', 'emcp-tools' ) }
			onClose={ onClose }
		>
			{ ! items.length && (
				<EmptyState
					icon="bell"
					title={ __( 'No announcements yet', 'emcp-tools' ) }
				/>
			) }
			<ul className="eui-notifs">
				{ items.map( ( n ) => (
					<li key={ n.id } className="eui-notifs__item">
						<strong>{ n.title }</strong>
						{ n.body && <p>{ n.body }</p> }
						{ n.url && (
							<a
								href={ n.url }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ n.cta || __( 'Learn more', 'emcp-tools' ) }
							</a>
						) }
					</li>
				) ) }
			</ul>
		</Drawer>
	);
}
