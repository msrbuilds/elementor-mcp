import { __, sprintf } from '@wordpress/i18n';
import { Badge, CopyButton } from '@emcp/ui';
import { formatWhen } from './time';

const SLOW_MS = 200;

/**
 * One request row plus, when expanded, its details row.
 *
 * @param {Object}     props
 * @param {Object}     props.row      Row from EMCP_Tools_Admin_Log_Data::row().
 * @param {number}     props.max      Slowest duration on the page (bar scale).
 * @param {string}     props.tz       site | utc | browser.
 * @param {string}     props.siteZone Site time zone.
 * @param {boolean}    props.open     Details shown.
 * @param {() => void} props.onToggle Toggle the details.
 */
export function LogRow( { row, max, tz, siteZone, open, onToggle } ) {
	const pct = max ? Math.max( 2, Math.round( ( row.ms / max ) * 100 ) ) : 0;
	const detailsId = `emcp-log-details-${ row.key }`;
	const name = row.reqId || row.tool || row.method;
	const details = [
		[ __( 'Client', 'emcp-tools' ), row.client ],
		[ __( 'Session', 'emcp-tools' ), row.session ],
		[ __( 'Credential', 'emcp-tools' ), row.credential ],
		[ __( 'Stage', 'emcp-tools' ), row.stage ],
		[ __( 'Failure reason', 'emcp-tools' ), row.reason ],
		[ __( 'Error', 'emcp-tools' ), row.error ],
		[ __( 'History entry', 'emcp-tools' ), row.ledger ],
	].filter( ( d ) => '' !== d[ 1 ] );
	return (
		<>
			<tr className={ open ? 'is-open' : undefined }>
				<td className="emcp-log__when">
					{ formatWhen( row.ts, tz, siteZone ) }
				</td>
				<td>
					<code className="emcp-log__mono">{ row.method }</code>
				</td>
				<td className="emcp-log__tool">{ row.tool }</td>
				<td>
					<Badge
						kind="status"
						value={ 'error' === row.status ? 'danger' : 'success' }
					>
						{ 'error' === row.status
							? __( 'Error', 'emcp-tools' )
							: __( 'Success', 'emcp-tools' ) }
					</Badge>
				</td>
				<td>
					<span className="emcp-log__duration">
						<span className="emcp-log__track" aria-hidden="true">
							<span
								className={
									row.ms > SLOW_MS
										? 'emcp-log__bar is-slow'
										: 'emcp-log__bar'
								}
								style={ { inlineSize: `${ pct }%` } }
							/>
						</span>
						<span>
							{ sprintf(
								/* translators: %d: duration in milliseconds. */
								__( '%d ms', 'emcp-tools' ),
								row.ms
							) }
						</span>
					</span>
				</td>
				<td>
					{ row.reqId && (
						<span className="emcp-log__req">
							<code className="emcp-log__mono">
								{ row.reqId }
							</code>
							<CopyButton
								text={ row.reqId }
								label={ __( 'Copy request ID', 'emcp-tools' ) }
							/>
						</span>
					) }
				</td>
				<td className="emcp-log__more">
					<button
						type="button"
						className="emcp-log__toggle"
						aria-expanded={ open ? 'true' : 'false' }
						aria-controls={ detailsId }
						aria-label={
							open
								? sprintf(
										/* translators: %s: request id or tool. */
										__( 'Show less: %s', 'emcp-tools' ),
										name
									)
								: sprintf(
										/* translators: %s: request id or tool. */
										__( 'Show more: %s', 'emcp-tools' ),
										name
									)
						}
						onClick={ onToggle }
					>
						{ open
							? __( 'Show less', 'emcp-tools' )
							: __( 'Show more', 'emcp-tools' ) }
					</button>
				</td>
			</tr>
			<tr
				id={ detailsId }
				className="emcp-log__details"
				hidden={ ! open }
			>
				<td colSpan={ 7 }>
					{ open &&
						( details.length ? (
							<dl>
								{ details.map( ( [ label, value ] ) => (
									<div key={ label }>
										<dt>{ label }</dt>
										<dd>{ value }</dd>
									</div>
								) ) }
							</dl>
						) : (
							<p>{ __( 'No further details.', 'emcp-tools' ) }</p>
						) ) }
				</td>
			</tr>
		</>
	);
}
