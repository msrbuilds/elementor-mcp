import { __, sprintf } from '@wordpress/i18n';
import { Badge, Button, Icon, IconButton } from '@emcp/ui';
import { formatTime, relativeTime } from './lib';

const ICONS = {
	edit: 'pencil',
	delete: 'trash-2',
	add: 'plus',
	settings: 'sliders-horizontal',
	undo: 'rotate-ccw',
};

/**
 * One change (spec 8.19). Rolled-back rows use the muted surface, never
 * opacity (it fails colour contrast).
 *
 * @param {Object}     props
 * @param {Object}     props.row      Row from the payload.
 * @param {boolean}    props.busy     A request for this row is running.
 * @param {() => void} props.onUndo   ( row ).
 * @param {() => void} props.onDiff   ( row ).
 * @param {() => void} props.onDelete ( row ).
 */
export function ChangeRow( { row, busy, onUndo, onDiff, onDelete } ) {
	return (
		<li
			className={ `emcp-history__row${
				row.rolledBack ? ' is-rolled-back' : ''
			}` }
		>
			<Icon
				name={ ICONS[ row.type ] || 'pencil' }
				className="emcp-history__type"
			/>
			<div className="emcp-history__main">
				<p className="emcp-history__title">
					<span>{ row.title }</span>
					{ row.rolledBack && (
						<Badge kind="status" value="neutral">
							{ __( 'Rolled back', 'emcp-tools' ) }
						</Badge>
					) }
				</p>
				<p className="emcp-history__meta">
					{ row.description && <span>{ row.description }</span> }
					<code>{ row.tool }</code>
					<time
						dateTime={ new Date( row.time * 1000 ).toISOString() }
						title={ formatTime( row.time ) }
					>
						{ relativeTime( row.time ) }
					</time>
				</p>
			</div>
			<div className="emcp-history__actions">
				{ row.diffable && (
					<IconButton
						icon="eye"
						label={ sprintf(
							/* translators: %s: change title. */
							__( 'View difference: %s', 'emcp-tools' ),
							row.title
						) }
						onClick={ () => onDiff( row ) }
					/>
				) }
				<Button
					size="sm"
					icon="rotate-ccw"
					disabled={ ! row.reversible || busy }
					loading={ busy }
					title={ row.reversible ? undefined : row.reason }
					aria-label={ sprintf(
						/* translators: %s: change title. */
						__( 'Undo: %s', 'emcp-tools' ),
						row.title
					) }
					onClick={ () => onUndo( row ) }
				>
					{ __( 'Undo', 'emcp-tools' ) }
				</Button>
				<IconButton
					icon="trash-2"
					label={ sprintf(
						/* translators: %s: change title. */
						__( 'Delete from History: %s', 'emcp-tools' ),
						row.title
					) }
					onClick={ () => onDelete( row ) }
				/>
			</div>
		</li>
	);
}
