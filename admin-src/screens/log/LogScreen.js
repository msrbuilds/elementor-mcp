import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	EmptyState,
	PageHeader,
	Pagination,
	SearchInput,
	Segmented,
	Select,
	errorMessage,
	request,
	useConfirm,
	useQueryState,
	useToast,
} from '@emcp/ui';
import { LogRow } from './LogRow';

const API = '/emcp-tools/v1/admin/log';
const TZ_KEY = 'emcp.log.tz';

const readTz = () => {
	try {
		const v = window.localStorage.getItem( TZ_KEY );
		return [ 'site', 'utc', 'browser' ].includes( v ) ? v : 'site';
	} catch {
		return 'site';
	}
};

const ms = ( n ) =>
	sprintf(
		/* translators: %d: duration in milliseconds. */
		__( '%d ms', 'emcp-tools' ),
		n
	);

/**
 * MCP Log screen (spec 8.22).
 *
 * @param {Object} props      Props.
 * @param {Object} props.data Boot payload (EMCP_Tools_Admin_Log_Data).
 */
export function LogScreen( { data } ) {
	const [ state, setState ] = useState( data );
	const [ status, setStatus ] = useQueryState( 'status', 'all' );
	const [ search, setSearch ] = useQueryState( 'search', '' );
	const [ , setPage ] = useQueryState( 'paged', '1' );
	const [ tz, setTz ] = useState( readTz );
	const [ open, setOpen ] = useState( {} );
	const [ loading, setLoading ] = useState( false );
	const toast = useToast();
	const confirm = useConfirm();
	// Bumped on every load: an older response is dropped.
	const generation = useRef( 0 );
	// The latest filters, read by callbacks that fire later (the debounced
	// search) so they never load with a status the user already changed.
	const latest = useRef( { status, search } );
	latest.current = { status, search };
	const [ exporting, setExporting ] = useState( false );

	const load = async ( next ) => {
		const mine = ++generation.current;
		const params = new URLSearchParams();
		if ( 'all' !== next.status ) {
			params.set( 'status', next.status );
		}
		if ( next.search ) {
			params.set( 'search', next.search );
		}
		if ( next.page > 1 ) {
			params.set( 'page', String( next.page ) );
		}
		const qs = params.toString();
		setLoading( true );
		try {
			const r = await request( qs ? `${ API }?${ qs }` : API );
			if ( mine === generation.current ) {
				setState( r );
				setOpen( {} );
			}
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			if ( mine === generation.current ) {
				setLoading( false );
			}
		}
	};

	const clear = async () => {
		const ok = await confirm( {
			title: __( 'Clear the MCP log?', 'emcp-tools' ),
			message: __(
				'Every stored request is deleted. This cannot be undone.',
				'emcp-tools'
			),
			confirmLabel: __( 'Clear log', 'emcp-tools' ),
			tone: 'danger',
		} );
		if ( ! ok ) {
			return;
		}
		const mine = ++generation.current;
		try {
			const r = await request( API, {
				method: 'DELETE',
				data: { confirm: true },
			} );
			if ( mine === generation.current ) {
				setState( r );
				setOpen( {} );
			}
			toast.success( __( 'Log cleared.', 'emcp-tools' ) );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		}
	};

	const chooseTz = ( v ) => {
		setTz( v );
		try {
			window.localStorage.setItem( TZ_KEY, v );
		} catch {}
	};

	const filterQuery = ( f ) => {
		const params = new URLSearchParams();
		if ( 'all' !== f.status ) {
			params.set( 'status', f.status );
		}
		if ( f.search ) {
			params.set( 'search', f.search );
		}
		return params.toString();
	};

	// A header-authenticated fetch saved as a file: a link would have to carry
	// the wp_rest nonce in its URL, where access logs keep it.
	const exportCsv = async () => {
		setExporting( true );
		try {
			const qs = filterQuery( latest.current );
			const res = await request(
				qs ? `${ state.exportPath }?${ qs }` : state.exportPath,
				{ parse: false }
			);
			const blob = await res.blob();
			const disposition =
				( res.headers && res.headers.get( 'Content-Disposition' ) ) ||
				'';
			const named = /filename="([^"]+)"/.exec( disposition );
			const url = window.URL.createObjectURL( blob );
			const a = document.createElement( 'a' );
			a.href = url;
			a.download = named ? named[ 1 ] : 'emcp-mcp-log.csv';
			document.body.appendChild( a );
			a.click();
			a.remove();
			window.setTimeout( () => window.URL.revokeObjectURL( url ), 1000 );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			setExporting( false );
		}
	};

	const max = state.rows.reduce( ( m, r ) => Math.max( m, r.ms ), 0 );
	const filtered = 'all' !== status || !! search;
	const s = state.stats;

	return (
		<div className="emcp-log">
			<PageHeader
				title={ __( 'MCP Log', 'emcp-tools' ) }
				description={ __(
					'The most recent requests from connected AI clients, on every transport.',
					'emcp-tools'
				) }
				actions={
					<>
						<Button onClick={ exportCsv } loading={ exporting }>
							{ __( 'Export CSV', 'emcp-tools' ) }
						</Button>
						<Button
							variant="danger"
							onClick={ clear }
							disabled={ ! s.requests }
						>
							{ __( 'Clear log', 'emcp-tools' ) }
						</Button>
					</>
				}
			/>
			<dl className="emcp-log__stats">
				<div>
					<dt>{ __( 'Requests', 'emcp-tools' ) }</dt>
					<dd>{ s.requests.toLocaleString() }</dd>
				</div>
				<div>
					<dt>{ __( 'Errors', 'emcp-tools' ) }</dt>
					<dd>{ s.errors.toLocaleString() }</dd>
				</div>
				<div>
					<dt>{ __( 'Median', 'emcp-tools' ) }</dt>
					<dd>{ ms( s.medianMs ) }</dd>
				</div>
				<div>
					<dt>{ __( 'Slowest', 'emcp-tools' ) }</dt>
					<dd>
						{ s.slowest ? ms( s.slowest.ms ) : '0 ms' }
						{ s.slowest && s.slowest.tool && (
							<span className="emcp-log__slow-tool">
								{ s.slowest.tool }
							</span>
						) }
					</dd>
				</div>
			</dl>
			<div className="emcp-log__toolbar">
				<SearchInput
					label={ __( 'Search requests', 'emcp-tools' ) }
					placeholder={ __( 'Search requests', 'emcp-tools' ) }
					value={ search }
					debounce={ 250 }
					onChange={ ( v ) => {
						setSearch( v );
						setPage( '1' );
						load( {
							status: latest.current.status,
							search: v,
							page: 1,
						} );
					} }
				/>
				<Segmented
					label={ __( 'Status', 'emcp-tools' ) }
					options={ [
						{ value: 'all', label: __( 'All', 'emcp-tools' ) },
						{
							value: 'success',
							label: __( 'Success', 'emcp-tools' ),
						},
						{ value: 'error', label: __( 'Errors', 'emcp-tools' ) },
					] }
					value={ status }
					onChange={ ( v ) => {
						setStatus( v );
						setPage( '1' );
						load( {
							status: v,
							search: latest.current.search,
							page: 1,
						} );
					} }
				/>
				<Select
					aria-label={ __( 'Time zone', 'emcp-tools' ) }
					options={ [
						{
							value: 'site',
							label: sprintf(
								/* translators: %s: site time zone. */
								__( 'Site time (%s)', 'emcp-tools' ),
								state.timezone
							),
						},
						{ value: 'utc', label: __( 'UTC', 'emcp-tools' ) },
						{
							value: 'browser',
							label: __( 'Browser time', 'emcp-tools' ),
						},
					] }
					value={ tz }
					onChange={ chooseTz }
				/>
			</div>
			{ ! state.debug && (
				<p className="emcp-log__hint">
					{ __(
						'Turn on WP_DEBUG to keep full error messages.',
						'emcp-tools'
					) }
				</p>
			) }
			<div
				className="eui-table-wrap"
				aria-busy={ loading ? 'true' : undefined }
			>
				<table className="eui-table emcp-log__table">
					<caption className="eui-visually-hidden">
						{ __( 'MCP requests', 'emcp-tools' ) }
					</caption>
					<thead>
						<tr>
							<th scope="col">{ __( 'Time', 'emcp-tools' ) }</th>
							<th scope="col">
								{ __( 'Request', 'emcp-tools' ) }
							</th>
							<th scope="col">
								{ __( 'Status', 'emcp-tools' ) }
							</th>
							<th scope="col">
								{ __( 'Duration', 'emcp-tools' ) }
							</th>
							<th scope="col">
								{ __( 'Request ID', 'emcp-tools' ) }
							</th>
							<th scope="col">
								<span className="eui-visually-hidden">
									{ __( 'Details', 'emcp-tools' ) }
								</span>
							</th>
						</tr>
					</thead>
					<tbody>
						{ state.rows.length ? (
							state.rows.map( ( r ) => (
								<LogRow
									key={ r.key }
									row={ r }
									max={ max }
									tz={ tz }
									siteZone={ state.timezone }
									open={ !! open[ r.key ] }
									onToggle={ () =>
										setOpen( ( o ) => ( {
											...o,
											[ r.key ]: ! o[ r.key ],
										} ) )
									}
								/>
							) )
						) : (
							<tr>
								<td colSpan={ 6 } className="eui-table__empty">
									<EmptyState
										icon="scroll-text"
										title={
											filtered
												? __(
														'No requests match these filters.',
														'emcp-tools'
													)
												: __(
														'No requests yet',
														'emcp-tools'
													)
										}
									>
										{ filtered
											? null
											: __(
													'Requests from connected AI clients appear here.',
													'emcp-tools'
												) }
									</EmptyState>
								</td>
							</tr>
						) }
					</tbody>
				</table>
			</div>
			<Pagination
				page={ state.page }
				totalPages={ state.pages }
				label={ __( 'Log pages', 'emcp-tools' ) }
				onChange={ ( p ) => {
					setPage( String( p ) );
					load( { status, search, page: p } );
				} }
			/>
		</div>
	);
}
