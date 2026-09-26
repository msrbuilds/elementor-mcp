import { useId, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Badge, Button, Icon } from '@emcp/ui';
import { ChangeRow } from './ChangeRow';
import { formatTime } from './lib';

/**
 * One session: a collapsible group with Undo whole session (spec 8.19).
 *
 * @param {Object}     props
 * @param {Object}     props.session       Session from the payload.
 * @param {string}     props.busy          Id or key of the running request.
 * @param {() => void} props.onUndoSession ( session ).
 * @param {Object}     props.rowActions    { onUndo, onDiff, onDelete }.
 */
export function SessionGroup( { session, busy, onUndoSession, rowActions } ) {
	const [ open, setOpen ] = useState( true );
	const listId = useId();
	const titleId = useId();
	const hidden = session.count - session.shown;
	return (
		<div
			className="emcp-history__session"
			role="group"
			aria-labelledby={ titleId }
		>
			<div className="emcp-history__session-head">
				<button
					type="button"
					className="emcp-history__toggle"
					aria-expanded={ open ? 'true' : 'false' }
					aria-controls={ listId }
					onClick={ () => setOpen( ! open ) }
				>
					<Icon name={ open ? 'chevron-down' : 'chevron-right' } />
					<span
						id={ titleId }
						className="emcp-history__session-title"
					>
						{ session.title }
					</span>
				</button>
				{ session.client && (
					<Badge kind="status" value="neutral">
						{ session.client }
					</Badge>
				) }
				<span className="emcp-history__session-meta">
					{ sprintf(
						/* translators: 1: number of changes, 2: start time. */
						_n(
							'%1$d change, %2$s',
							'%1$d changes, %2$s',
							session.count,
							'emcp-tools'
						),
						session.count,
						formatTime( session.start )
					) }
				</span>
				<Button
					size="sm"
					icon="rotate-ccw"
					disabled={ ! session.open || busy === session.key }
					loading={ busy === session.key }
					aria-label={ sprintf(
						/* translators: %s: session title. */
						__( 'Undo whole session: %s', 'emcp-tools' ),
						session.title
					) }
					onClick={ () => onUndoSession( session ) }
				>
					{ __( 'Undo whole session', 'emcp-tools' ) }
				</Button>
			</div>
			{ open && (
				<ul id={ listId } className="emcp-history__rows">
					{ session.rows.map( ( row ) => (
						<ChangeRow
							key={ row.id }
							row={ row }
							busy={ busy === row.id }
							{ ...rowActions }
						/>
					) ) }
					{ hidden > 0 && (
						<li className="emcp-history__more">
							{ sprintf(
								/* translators: %d: changes not shown. */
								_n(
									'%d more change is not shown.',
									'%d more changes are not shown.',
									hidden,
									'emcp-tools'
								),
								hidden
							) }
						</li>
					) }
				</ul>
			) }
		</div>
	);
}
