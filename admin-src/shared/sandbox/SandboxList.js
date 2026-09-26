import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Button,
	Card,
	CopyField,
	EmptyState,
	IconButton,
	Menu,
	Notice,
	PageHeader,
	Pagination,
	SearchInput,
	Segmented,
	Table,
	Toggle,
	errorMessage,
	request,
	useConfirm,
	useQueryState,
	useToast,
} from '@emcp/ui';
import { API, downloadBundle, listPath } from './api';
import { CodeDrawer } from './CodeDrawer';
import { CloudLibraryDrawer } from './CloudLibraryDrawer';
import { ImportButton } from './ImportButton';
import { PushUpdateDialog } from './PushUpdateDialog';

/**
 * The Sandbox list layout shared by Widgets, Blocks and PHP Snippets
 * (spec 8.11 list pattern; screenshots 14, 22, 23).
 *
 * @param {Object} props        Props.
 * @param {Object} props.data   Boot payload (EMCP_Tools_Admin_Sandbox_Data::list()).
 * @param {Object} props.config Per-type columns, copy and actions.
 */
export function SandboxList( { data: initial, config } ) {
	const toast = useToast();
	const confirm = useConfirm();
	const [ data, setData ] = useState( initial );
	const [ status, setStatus ] = useQueryState( 'status', 'all' );
	const [ search, setSearch ] = useQueryState( 'search', '' );
	const [ page, setPage ] = useQueryState( 'paged', '1' );
	const [ busy, setBusy ] = useState( '' );
	const [ code, setCode ] = useState( null );
	const [ library, setLibrary ] = useState( false );
	const [ libCount, setLibCount ] = useState( null );
	const [ pushing, setPushing ] = useState( null );
	const latest = useRef( 0 );
	const first = useRef( true );
	const { type, kind } = initial;
	const query = () => ( { status, search, page: Number( page ) || 1 } );

	const load = () => {
		const ticket = ++latest.current;
		return request( listPath( type, query() ) ).then( ( res ) => {
			if ( ticket === latest.current ) {
				setData( res );
			}
		} );
	};

	useEffect( () => {
		if ( first.current ) {
			first.current = false;
			const q = initial.query || {};
			if (
				q.status === status &&
				q.search === search &&
				String( q.page ) === String( Number( page ) || 1 )
			) {
				return;
			}
		}
		load().catch( ( e ) => toast.error( errorMessage( e ) ) );
	}, [ status, search, page ] ); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect( () => {
		const review = Number(
			new URLSearchParams( window.location.search ).get( 'review' )
		);
		if ( review ) {
			const found = ( initial.items || [] ).find(
				( r ) => r.id === review
			);
			setCode( found || { id: review, title: '' } );
		}
		if ( initial.cloud && initial.cloud.connected ) {
			request( `${ API }/cloud/library?kind=${ kind }` )
				.then( ( res ) => setLibCount( res.count ) )
				.catch( () => {} );
		}
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Every write returns the list for the query it carried; a failure re-reads it.
	// rethrow: the caller shows the error itself (the snippet editor keeps its
	// Drawer open and lists the validator's findings).
	const write = async ( key, path, { rethrow = false, ...options } = {} ) => {
		setBusy( key );
		try {
			const res = await request( path, {
				method: 'POST',
				...options,
				data: { ...( options.data || {} ), ...query() },
			} );
			latest.current++;
			if ( res && res.items ) {
				setData( res );
			}
			if ( res && res.message ) {
				toast.success( res.message );
			}
			return res;
		} catch ( e ) {
			if ( rethrow ) {
				throw e;
			}
			toast.error( errorMessage( e ) );
			await load().catch( () => {} );
			return null;
		} finally {
			setBusy( '' );
		}
	};

	const helpers = {
		data,
		setData,
		reload: load,
		write,
		busy,
		openCode: ( row ) => setCode( row ),
	};

	const toggle = async ( row ) => {
		const next = ! row.active;
		if (
			next &&
			config.confirmActivate &&
			! ( await config.confirmActivate( row, helpers ) )
		) {
			return;
		}
		await write(
			`status-${ row.id }`,
			`${ API }/${ type }/${ row.id }/status`,
			{ data: { active: next } }
		);
	};

	const remove = async ( row ) => {
		const ok = await confirm( {
			/* translators: %s: item title. */
			title: sprintf( __( 'Delete %s?', 'emcp-tools' ), row.title ),
			message: config.deleteMessage,
			confirmLabel: __( 'Delete', 'emcp-tools' ),
			tone: 'danger',
		} );
		if ( ok ) {
			await write(
				`delete-${ row.id }`,
				`${ API }/${ type }/${ row.id }`,
				{
					method: 'DELETE',
					data: { confirm: true },
				}
			);
		}
	};

	const exportRow = ( row ) =>
		request( `${ API }/${ type }/${ row.id }/export` )
			.then( downloadBundle )
			.catch( ( e ) => toast.error( errorMessage( e ) ) );

	const cloudItems = ( row ) => {
		if ( ! data.cloud.connected ) {
			return [];
		}
		const items = [];
		const save = () =>
			write(
				`cloud-${ row.id }`,
				`${ API }/${ type }/${ row.id }/cloud`
			);
		if ( 'none' === row.cloud ) {
			items.push( {
				label: __( 'Save to Cloud', 'emcp-tools' ),
				icon: 'cloud',
				onSelect: save,
			} );
		}
		if ( 'changed' === row.cloud ) {
			items.push( {
				label: __( 'Update in Cloud', 'emcp-tools' ),
				icon: 'cloud',
				onSelect: save,
			} );
		}
		const mk = row.marketplace || {};
		if (
			mk.slug &&
			mk.published &&
			'changed' === row.cloud &&
			! mk.pending
		) {
			items.push( {
				label: __( 'Push update to Marketplace', 'emcp-tools' ),
				icon: 'upload',
				onSelect: () => setPushing( row ),
			} );
		}
		if ( mk.viewUrl ) {
			items.push( {
				label: __( 'View on Marketplace', 'emcp-tools' ),
				icon: 'external-link',
				href: mk.viewUrl,
			} );
		} else if ( 'none' !== row.cloud && mk.publishUrl ) {
			items.push( {
				label: __( 'Publish to Marketplace', 'emcp-tools' ),
				icon: 'external-link',
				href: mk.publishUrl,
			} );
		}
		return items;
	};

	const cloudCell = ( row ) => {
		if ( 'synced' === row.cloud ) {
			return (
				<span className="emcp-sb-cloud is-synced">
					{ __( 'Synced', 'emcp-tools' ) }
				</span>
			);
		}
		if ( 'changed' === row.cloud ) {
			return (
				<span className="emcp-sb-cloud is-changed">
					{ __( 'Changed', 'emcp-tools' ) }
				</span>
			);
		}
		return (
			<span
				className="emcp-sb-cloud"
				title={ __( 'Not in Cloud', 'emcp-tools' ) }
			>
				<span aria-hidden="true">&ndash;</span>
				<span className="eui-visually-hidden">
					{ __( 'Not in Cloud', 'emcp-tools' ) }
				</span>
			</span>
		);
	};

	const columns = [
		{
			key: 'toggle',
			header: (
				<span className="eui-visually-hidden">
					{ __( 'On or off', 'emcp-tools' ) }
				</span>
			),
			width: 56,
			render: ( row ) => (
				<Toggle
					checked={ row.active }
					hideLabel
					label={ sprintf(
						/* translators: %s: item title. */
						__( 'Activate %s', 'emcp-tools' ),
						row.title
					) }
					disabled={ !! busy || ! data.canEdit }
					onChange={ () => toggle( row ) }
				/>
			),
		},
		{
			key: 'title',
			header: config.nameHeader,
			render: ( row ) => (
				<div className="emcp-sb-name">
					<span className="emcp-sb-name__title">{ row.title }</span>
					{ config.nameExtra && config.nameExtra( row ) }
					{ row.lastError && (
						<span className="emcp-sb-name__error">
							{ sprintf(
								/* translators: %s: error message. */
								__(
									'Switched off after an error: %s',
									'emcp-tools'
								),
								row.lastError
							) }
						</span>
					) }
				</div>
			),
		},
		...( config.identHeader
			? [
					{
						key: 'ident',
						header: config.identHeader,
						render: ( row ) => (
							<code className="emcp-sb-chip">{ row.ident }</code>
						),
					},
				]
			: [] ),
		{
			key: 'status',
			header: __( 'Status', 'emcp-tools' ),
			render: ( row ) => (
				<Badge
					kind="status"
					value={ row.active ? 'success' : 'neutral' }
				>
					{ row.active
						? __( 'Active', 'emcp-tools' )
						: __( 'Inactive', 'emcp-tools' ) }
				</Badge>
			),
		},
		...( config.extraColumns || [] ),
		{
			key: 'cloud',
			header: __( 'Cloud', 'emcp-tools' ),
			render: cloudCell,
		},
		{
			key: 'actions',
			header: (
				<span className="eui-visually-hidden">
					{ __( 'Actions', 'emcp-tools' ) }
				</span>
			),
			align: 'end',
			render: ( row ) => (
				<div className="emcp-sb-actions">
					<IconButton
						icon="code"
						label={ sprintf(
							/* translators: %s: item title. */
							__( 'View code of %s', 'emcp-tools' ),
							row.title
						) }
						onClick={ () => setCode( row ) }
					/>
					{ config.rowActions && config.rowActions( row, helpers ) }
					<IconButton
						icon="download"
						label={ sprintf(
							/* translators: %s: item title. */
							__( 'Export %s', 'emcp-tools' ),
							row.title
						) }
						onClick={ () => exportRow( row ) }
					/>
					<Menu
						label={ sprintf(
							/* translators: %s: item title. */
							__( 'More actions for %s', 'emcp-tools' ),
							row.title
						) }
						items={ [
							...cloudItems( row ),
							...( config.menuItems
								? config.menuItems( row, helpers )
								: [] ),
							{
								label: __( 'Delete', 'emcp-tools' ),
								danger: true,
								onSelect: () => remove( row ),
							},
						] }
					/>
				</div>
			),
		},
	];

	const firstRun = 0 === data.counts.all && ! data.query.search;
	const filters = [
		{
			value: 'all',
			/* translators: %d: number of items. */
			label: sprintf( __( 'All %d', 'emcp-tools' ), data.counts.all ),
		},
		{
			value: 'active',
			label: sprintf(
				/* translators: %d: number of active items. */
				__( 'Active %d', 'emcp-tools' ),
				data.counts.active
			),
		},
		{
			value: 'inactive',
			label: sprintf(
				/* translators: %d: number of inactive items. */
				__( 'Inactive %d', 'emcp-tools' ),
				data.counts.inactive
			),
		},
		...( config.extraFilters || [] ).map( ( f ) => ( {
			value: f.value,
			label: `${ f.label } ${ data.counts[ f.value ] || 0 }`,
		} ) ),
	];

	const onImported = ( res ) => {
		toast.success( res.message );
		if ( res.type === type ) {
			load().catch( () => {} );
		} else if ( res.reviewUrl ) {
			window.location.assign( res.reviewUrl );
		}
	};

	let body;
	const blocked = config.blocked ? config.blocked( data ) : null;
	if ( blocked ) {
		body = <div className="emcp-sb-blocked">{ blocked }</div>;
	} else if ( firstRun ) {
		body = (
			<EmptyState
				icon="code"
				title={ config.emptyTitle }
				actions={
					<>
						{ data.aiChatUrl && (
							<Button
								variant="primary"
								icon="message-square"
								href={ data.aiChatUrl }
							>
								{ __( 'Open AI Chat', 'emcp-tools' ) }
							</Button>
						) }
						<Button
							icon="cloud"
							onClick={ () => setLibrary( true ) }
						>
							{ __( 'Browse Cloud library', 'emcp-tools' ) }
						</Button>
					</>
				}
			>
				{ config.emptyText }
			</EmptyState>
		);
	} else {
		body = (
			<Table
				caption={ config.title }
				columns={ columns }
				rows={ data.items }
				empty={ config.noMatch }
			/>
		);
	}

	return (
		<div className="emcp-sb">
			<PageHeader
				back={ {
					href: data.backUrl,
					label: __( 'Sandbox', 'emcp-tools' ),
				} }
				title={ config.title }
				tier={ config.tier }
				description={ config.description }
				actions={
					<>
						<ImportButton onImported={ onImported } />
						<Button
							icon="cloud"
							onClick={ () => setLibrary( true ) }
						>
							{ __( 'Cloud library', 'emcp-tools' ) }
							{ null !== libCount && undefined !== libCount && (
								<span className="emcp-sb-count">
									{ libCount }
								</span>
							) }
							<span className="emcp-sb-tag">
								{ __( 'CROSS-SITE', 'emcp-tools' ) }
							</span>
						</Button>
						{ config.headerActions &&
							config.headerActions( helpers ) }
					</>
				}
			/>
			<Notice tone={ config.notice.tone }>{ config.notice.text }</Notice>
			{ config.extra && config.extra( helpers ) }
			<Card padded={ false } className="emcp-sb-card">
				{ ! firstRun && ! blocked && (
					<div className="emcp-sb-toolbar">
						<Segmented
							label={ __( 'Filter', 'emcp-tools' ) }
							options={ filters }
							value={ data.query.status }
							onChange={ ( v ) => {
								setPage( '1' );
								setStatus( v );
							} }
						/>
						<div className="emcp-sb-toolbar__end">
							<SearchInput
								label={ config.searchLabel }
								placeholder={ config.searchLabel }
								value={ search }
								debounce={ 250 }
								onChange={ ( v ) => {
									setPage( '1' );
									setSearch( v );
								} }
							/>
							{ data.cloud.connected ? (
								<>
									<Button
										icon="refresh-cw"
										loading={ 'refresh' === busy }
										onClick={ () =>
											write(
												'refresh',
												`${ API }/${ type }/cloud/refresh`
											)
										}
									>
										{ __(
											'Refresh cloud status',
											'emcp-tools'
										) }
									</Button>
									<Button
										variant="primary"
										icon="cloud"
										loading={ 'bulk' === busy }
										onClick={ () =>
											write(
												'bulk',
												`${ API }/${ type }/cloud/bulk`
											)
										}
									>
										{ __(
											'Save all to Cloud',
											'emcp-tools'
										) }
									</Button>
								</>
							) : (
								<a
									className="emcp-sb-connect"
									href={ data.cloud.connectUrl }
								>
									{ __( 'Connect Cloud', 'emcp-tools' ) }
								</a>
							) }
						</div>
					</div>
				) }
				{ body }
				{ ! firstRun && ! blocked && (
					<div className="emcp-sb-foot">
						<span>
							{ sprintf(
								/* translators: 1: item count with its noun, 2: active count. */
								__( '%1$s · %2$d active', 'emcp-tools' ),
								`${ data.counts.all } ${
									1 === data.counts.all
										? config.noun.one
										: config.noun.many
								}`,
								data.counts.active
							) }
						</span>
						{ data.pages > 1 && (
							<Pagination
								page={ data.page }
								totalPages={ data.pages }
								onChange={ ( p ) => setPage( String( p ) ) }
							/>
						) }
					</div>
				) }
				{ firstRun && ! blocked && config.emptyPrompt && (
					<div className="emcp-sb-prompt">
						<CopyField
							label={ __( 'Example prompt', 'emcp-tools' ) }
							value={ config.emptyPrompt }
						/>
					</div>
				) }
			</Card>
			<CodeDrawer
				open={ !! code }
				type={ type }
				id={ code ? code.id : 0 }
				title={ code ? code.title : '' }
				onClose={ () => setCode( null ) }
				below={ config.codeBelow }
			/>
			<CloudLibraryDrawer
				open={ library }
				kind={ kind }
				cloud={ data.cloud }
				onClose={ () => setLibrary( false ) }
				onImported={ onImported }
			/>
			<PushUpdateDialog
				row={ pushing }
				onClose={ () => setPushing( null ) }
				onDone={ ( res ) => {
					setPushing( null );
					toast.success( res.message );
					if ( res.row ) {
						setData( ( d ) => ( {
							...d,
							items: d.items.map( ( r ) =>
								r.id === res.row.id ? res.row : r
							),
						} ) );
					}
				} }
			/>
		</div>
	);
}
