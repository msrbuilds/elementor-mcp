import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Drawer,
	EmptyState,
	Notice,
	PageHeader,
	Select,
	Skeleton,
	errorMessage,
	request,
	useConfirm,
	useQueryState,
	useToast,
} from '@emcp/ui';
import { DiffView } from './DiffView';
import { SessionGroup } from './SessionGroup';
import { Toolbar } from './Toolbar';
import { sessionResultMessage, toQuery, writeBody } from './lib';

const API = '/emcp-tools/v1/admin/history';

/**
 * History screen (spec 8.19).
 *
 * @param {Object} props      Props.
 * @param {Object} props.data Boot payload (EMCP_Tools_Admin_History_Data).
 */
export function HistoryScreen( { data } ) {
	const [ search, setSearch ] = useQueryState( 'search', '' );
	const [ kind, setKind ] = useQueryState( 'kind', 'all' );
	const [ client, setClient ] = useQueryState( 'client', '' );
	const [ range, setRange ] = useQueryState( 'range', 'all' );
	const filters = { search, kind, client, range };
	const [ state, setState ] = useState( data );
	const [ loading, setLoading ] = useState( false );
	const [ busy, setBusy ] = useState( '' );
	const [ diff, setDiff ] = useState( null );
	const toast = useToast();
	const confirm = useConfirm();
	const first = useRef( true );

	useEffect( () => {
		if ( first.current ) {
			first.current = false;
			return;
		}
		let stale = false;
		setLoading( true );
		const qs = toQuery( { search, kind, client, range } );
		request( qs ? `${ API }?${ qs }` : API )
			.then( ( d ) => ! stale && setState( d ) )
			.catch( ( e ) => ! stale && toast.error( errorMessage( e ) ) )
			.finally( () => ! stale && setLoading( false ) );
		return () => {
			stale = true;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps -- refetch only when a filter changes.
	}, [ search, kind, client, range ] );

	const setters = {
		search: setSearch,
		kind: setKind,
		client: setClient,
		range: setRange,
	};

	const write = async ( id, path, method, extra, success ) => {
		setBusy( id );
		try {
			const r = await request( path, {
				method,
				data: {
					...writeBody( filters, state.sessions.length ),
					...extra,
				},
			} );
			setState( r.history );
			if ( success ) {
				success( r.result );
			}
		} finally {
			setBusy( '' );
		}
	};

	const undo = async ( row, force = false ) => {
		try {
			await write(
				row.id,
				`${ API }/${ row.id }/undo`,
				'POST',
				{ force },
				() => toast.success( __( 'Change undone.', 'emcp-tools' ) )
			);
		} catch ( e ) {
			if ( 'conflict' === e.code && ! force ) {
				const ok = await confirm( {
					title: __( 'This changed since', 'emcp-tools' ),
					message: e.message,
					confirmLabel: __( 'Undo anyway', 'emcp-tools' ),
					tone: 'danger',
				} );
				if ( ok ) {
					await undo( row, true );
				}
				return;
			}
			toast.error( errorMessage( e ) );
		}
	};

	const undoSession = async ( session ) => {
		const ok = await confirm( {
			title: __( 'Undo this whole session?', 'emcp-tools' ),
			message: sprintf(
				/* translators: %s: session title. */
				__(
					'Every change in "%s" is undone, newest first. It stops at the first change that cannot be undone.',
					'emcp-tools'
				),
				session.title
			),
			confirmLabel: __( 'Undo session', 'emcp-tools' ),
			tone: 'danger',
		} );
		if ( ! ok ) {
			return;
		}
		try {
			await write(
				session.key,
				`${ API }/sessions/undo`,
				'POST',
				{ session: session.key, confirm: true },
				( result ) => {
					const msg = sessionResultMessage( result );
					toast[ msg.tone ]( msg.text );
				}
			);
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		}
	};

	const remove = async ( row ) => {
		const ok = await confirm( {
			title: __( 'Delete this change from History?', 'emcp-tools' ),
			message: __(
				'Its undo record is deleted too, so it can no longer be undone.',
				'emcp-tools'
			),
			confirmLabel: __( 'Delete', 'emcp-tools' ),
			tone: 'danger',
		} );
		if ( ! ok ) {
			return;
		}
		try {
			await write(
				row.id,
				`${ API }/${ row.id }`,
				'DELETE',
				{ confirm: true },
				() =>
					toast.success( __( 'Removed from History.', 'emcp-tools' ) )
			);
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		}
	};

	const clearAll = async () => {
		const ok = await confirm( {
			title: __( 'Clear history', 'emcp-tools' ),
			message: __(
				'Every change and its undo record is deleted. This cannot be undone.',
				'emcp-tools'
			),
			confirmLabel: __( 'Clear history', 'emcp-tools' ),
			tone: 'danger',
			requireText: 'CLEAR',
		} );
		if ( ! ok ) {
			return;
		}
		try {
			await write( 'clear', API, 'DELETE', { confirm: true }, () =>
				toast.success( __( 'History cleared.', 'emcp-tools' ) )
			);
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		}
	};

	const saveRetention = async ( days ) => {
		try {
			await write(
				'retention',
				`${ API }/retention`,
				'POST',
				{ days: Number( days ) },
				() => toast.success( __( 'Retention saved.', 'emcp-tools' ) )
			);
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		}
	};

	const dismiss = async ( banner ) => {
		try {
			await write(
				banner.id,
				`${ API }/banners/${ banner.id }/dismiss`,
				'POST',
				{}
			);
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		}
	};

	const openDiff = async ( row ) => {
		setDiff( { row, data: null } );
		try {
			const d = await request( `${ API }/${ row.id }/diff` );
			setDiff( ( cur ) =>
				cur && cur.row.id === row.id ? { row, data: d } : cur
			);
		} catch ( e ) {
			setDiff( null );
			toast.error( errorMessage( e ) );
		}
	};

	const loadOlder = async () => {
		setBusy( 'older' );
		try {
			const d = await request(
				`${ API }?${ toQuery( filters, { before: state.nextCursor } ) }`
			);
			setState( ( cur ) => ( {
				...d,
				sessions: [ ...cur.sessions, ...d.sessions ],
			} ) );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			setBusy( '' );
		}
	};

	const filtered = !! (
		search ||
		client ||
		'all' !== kind ||
		'all' !== range
	);

	return (
		<div className="emcp-history">
			<PageHeader
				title={ __( 'History', 'emcp-tools' ) }
				description={ sprintf(
					/* translators: 1: number of changes, 2: number rolled back. */
					__( '%1$s changes, %2$s rolled back', 'emcp-tools' ),
					state.totals.changes.toLocaleString(),
					state.totals.rolledBack.toLocaleString()
				) }
				actions={
					<>
						<Select
							aria-label={ __( 'Retention', 'emcp-tools' ) }
							options={ state.retentionOptions.map( ( d ) => ( {
								value: String( d ),
								label: sprintf(
									/* translators: %d: number of days. */
									__( 'Keep %d days', 'emcp-tools' ),
									d
								),
							} ) ) }
							value={ String( state.retention ) }
							onChange={ saveRetention }
						/>
						<Button
							variant="danger"
							onClick={ clearAll }
							disabled={ ! state.totals.changes }
						>
							{ __( 'Clear history', 'emcp-tools' ) }
						</Button>
					</>
				}
			/>
			{ state.banners.map( ( b ) => (
				<Notice
					key={ b.id }
					tone={ b.tone }
					title={ b.title }
					onDismiss={ b.dismissible ? () => dismiss( b ) : undefined }
					actions={
						b.action ? (
							<Button href={ b.action.url }>
								{ b.action.label }
							</Button>
						) : undefined
					}
				>
					{ b.body }
				</Notice>
			) ) }
			<Toolbar
				filters={ filters }
				clients={ state.clients }
				onChange={ ( k, v ) => setters[ k ]( v ) }
			/>
			{ loading && (
				<Skeleton
					lines={ 6 }
					label={ __( 'Loading changes', 'emcp-tools' ) }
				/>
			) }
			{ ! loading && ! state.sessions.length && (
				<EmptyState
					icon="history"
					title={
						filtered
							? __(
									'No changes match these filters.',
									'emcp-tools'
								)
							: __( 'No changes yet', 'emcp-tools' )
					}
				>
					{ filtered
						? null
						: __(
								'Changes made by AI connections and in this admin appear here, grouped by session.',
								'emcp-tools'
							) }
				</EmptyState>
			) }
			{ ! loading &&
				state.sessions.map( ( s ) => (
					<SessionGroup
						key={ s.key }
						session={ s }
						busy={ busy }
						onUndoSession={ undoSession }
						rowActions={ {
							onUndo: ( r ) => undo( r ),
							onDiff: openDiff,
							onDelete: remove,
						} }
					/>
				) ) }
			{ ! loading && state.nextCursor && (
				<div className="emcp-history__older">
					<Button onClick={ loadOlder } loading={ 'older' === busy }>
						{ __( 'Load older sessions', 'emcp-tools' ) }
					</Button>
				</div>
			) }
			<Drawer
				open={ !! diff }
				title={ diff ? diff.row.title : '' }
				onClose={ () => setDiff( null ) }
			>
				{ diff && ! diff.data && (
					<Skeleton
						lines={ 8 }
						label={ __( 'Loading difference', 'emcp-tools' ) }
					/>
				) }
				{ diff && diff.data && <DiffView diff={ diff.data } /> }
			</Drawer>
		</div>
	);
}
