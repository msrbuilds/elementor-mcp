import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Drawer,
	EmptyState,
	Field,
	FilterChip,
	Notice,
	PageHeader,
	Pagination,
	SearchInput,
	Segmented,
	Select,
	Skeleton,
	Table,
	Toggle,
	errorMessage,
	request,
	useQueryState,
	useToast,
} from '@emcp/ui';
import { InstallControl, ListingCard } from './ListingCard';

const API = '/emcp-tools/v1/admin/marketplace';
const VIEW_KEY = 'emcp.marketplace.view';
const TYPES = () => [
	{ value: '', key: 'all', label: __( 'All', 'emcp-tools' ) },
	{ value: 'widget', key: 'widget', label: __( 'Widgets', 'emcp-tools' ) },
	{ value: 'block', key: 'block', label: __( 'Blocks', 'emcp-tools' ) },
	{ value: 'snippet', key: 'snippet', label: __( 'Snippets', 'emcp-tools' ) },
	{
		value: 'template',
		key: 'template',
		label: __( 'Templates', 'emcp-tools' ),
	},
];

function readView() {
	try {
		return 'list' === window.localStorage.getItem( VIEW_KEY )
			? 'list'
			: 'grid';
	} catch {
		return 'grid';
	}
}

/**
 * Marketplace screen (spec 8.18).
 *
 * @param {Object} props      Props.
 * @param {Object} props.data Boot payload (EMCP_Tools_Admin_Marketplace_Data::boot()).
 */
export function MarketplaceScreen( { data: boot } ) {
	const toast = useToast();
	const [ search, setSearch ] = useQueryState( 'q', '', { debounce: 250 } );
	const [ type, setType ] = useQueryState( 'type', '' );
	const [ category, setCategory ] = useQueryState( 'category', '' );
	const [ access, setAccess ] = useQueryState( 'access', '' );
	const [ sort, setSort ] = useQueryState( 'sort', 'newest' );
	const [ verified, setVerified ] = useQueryState( 'verified', '' );
	const [ page, setPage ] = useQueryState( 'paged', '1' );
	const [ list, setList ] = useState( null );
	const [ view, setView ] = useState( readView );
	const [ busy, setBusy ] = useState( '' );
	const [ installs, setInstalls ] = useState( null );
	// Only the latest request's answer is shown: an older, slower one is dropped.
	const latest = useRef( 0 );

	const load = () => {
		const query = new URLSearchParams( {
			search,
			type,
			category,
			access,
			sort,
			page,
		} );
		// The REST boolean refuses an empty string, so the flag is sent only when on.
		if ( verified ) {
			query.set( 'verified', '1' );
		}
		const ticket = ++latest.current;
		return request( `${ API }?${ query }` ).then( ( res ) => {
			if ( ticket === latest.current ) {
				setList( res );
			}
		} );
	};

	useEffect( () => {
		if ( boot.connected ) {
			load().catch( ( e ) => toast.error( errorMessage( e ) ) );
		}
	}, [ search, type, category, access, sort, verified, page ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const reset = ( setter ) => ( v ) => {
		setter( v );
		setPage( '1' );
	};
	const clearAll = () => {
		setSearch( '' );
		setType( '' );
		setCategory( '' );
		setAccess( '' );
		setVerified( '' );
		setPage( '1' );
	};
	const chooseView = ( v ) => {
		setView( v );
		try {
			window.localStorage.setItem( VIEW_KEY, v );
		} catch {}
	};

	const install = async ( item ) => {
		setBusy( item.slug );
		try {
			const res = await request( `${ API }/${ item.slug }/install`, {
				method: 'POST',
			} );
			toast.success( res.message );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			await load().catch( () => {} );
			setBusy( '' );
		}
	};

	const openInstalls = async () => {
		setInstalls( [] );
		try {
			setInstalls( ( await request( API + '/installs' ) ).installs );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
			setInstalls( null );
		}
	};

	const header = (
		<PageHeader
			title={ __( 'Marketplace', 'emcp-tools' ) }
			description={ __(
				'Community and Pro blocks, widgets, snippets and templates from EMCP Cloud. Installs land as drafts for you to review.',
				'emcp-tools'
			) }
			actions={
				boot.connected && (
					<>
						<Button icon="download" onClick={ openInstalls }>
							{ __( 'My installs', 'emcp-tools' ) }
						</Button>
						<Button
							variant="primary"
							icon="upload"
							href={ boot.sandboxUrl }
						>
							{ __( 'Publish to Cloud', 'emcp-tools' ) }
						</Button>
					</>
				)
			}
		/>
	);

	if ( ! boot.connected ) {
		return (
			<div className="eui-mk">
				{ header }
				<EmptyState
					icon="cloud"
					title={ __(
						'Connect this site to EMCP Cloud',
						'emcp-tools'
					) }
					actions={
						<Button variant="primary" href={ boot.connectUrl }>
							{ __( 'Connect to EMCP Cloud', 'emcp-tools' ) }
						</Button>
					}
				>
					{ __(
						'Browse and install marketplace items once this site is connected.',
						'emcp-tools'
					) }
				</EmptyState>
			</div>
		);
	}

	const types = TYPES();
	const counts = list?.counts || {};
	const typeLabel = types.find( ( t ) => t.value === type )?.label;
	const chips = [
		type && {
			key: 'type',
			label: typeLabel || type,
			clear: () => reset( setType )( '' ),
		},
		category && {
			key: 'category',
			label: category,
			clear: () => reset( setCategory )( '' ),
		},
		access && {
			key: 'access',
			label:
				'pro' === access
					? __( 'Pro', 'emcp-tools' )
					: __( 'Free', 'emcp-tools' ),
			clear: () => reset( setAccess )( '' ),
		},
	].filter( Boolean );

	return (
		<div className="eui-mk">
			{ header }
			<div className="eui-mk__toolbar">
				<SearchInput
					debounce={ 250 }
					label={ __( 'Search the marketplace', 'emcp-tools' ) }
					value={ search }
					onChange={ reset( setSearch ) }
					placeholder={ __( 'Search items…', 'emcp-tools' ) }
				/>
				<Segmented
					label={ __( 'Type', 'emcp-tools' ) }
					value={ type }
					onChange={ reset( setType ) }
					options={ types.map( ( t ) => ( {
						value: t.value,
						label: t.label,
						count: counts[ t.key ],
					} ) ) }
				/>
				<Field label={ __( 'Category', 'emcp-tools' ) }>
					{ ( a11y ) => (
						<Select
							{ ...a11y }
							value={ category }
							onChange={ reset( setCategory ) }
							options={ [
								{
									value: '',
									label: __( 'All categories', 'emcp-tools' ),
								},
								...( list?.categories || [] ).map( ( c ) => ( {
									value: c,
									label: c,
								} ) ),
							] }
						/>
					) }
				</Field>
				<Segmented
					label={ __( 'Access', 'emcp-tools' ) }
					value={ access }
					onChange={ reset( setAccess ) }
					options={ [
						{ value: '', label: __( 'All', 'emcp-tools' ) },
						{
							value: 'community',
							label: __( 'Free', 'emcp-tools' ),
						},
						{ value: 'pro', label: __( 'Pro', 'emcp-tools' ) },
					] }
				/>
				<Field label={ __( 'Sort', 'emcp-tools' ) }>
					{ ( a11y ) => (
						<Select
							{ ...a11y }
							value={ sort }
							onChange={ reset( setSort ) }
							options={ [
								{
									value: 'newest',
									label: __( 'Newest', 'emcp-tools' ),
								},
								{
									value: 'popular',
									label: __( 'Most installed', 'emcp-tools' ),
								},
							] }
						/>
					) }
				</Field>
			</div>
			<h2 className="eui-visually-hidden">
				{ __( 'Marketplace items', 'emcp-tools' ) }
			</h2>
			<div className="eui-mk__results">
				<span className="eui-mk__count">
					{ list
						? sprintf(
								/* translators: %d: number of results. */
								_n(
									'%d result',
									'%d results',
									list.total,
									'emcp-tools'
								),
								list.total
							)
						: '' }
				</span>
				{ chips.map( ( c ) => (
					<FilterChip
						key={ c.key }
						label={ c.label }
						active
						onClick={ c.clear }
						onRemove={ c.clear }
						removeLabel={ sprintf(
							/* translators: %s: filter name. */
							__( 'Remove filter %s', 'emcp-tools' ),
							c.label
						) }
					/>
				) ) }
				{ ( chips.length > 0 || search || verified ) && (
					<Button variant="ghost" size="sm" onClick={ clearAll }>
						{ __( 'Clear all', 'emcp-tools' ) }
					</Button>
				) }
				<span className="eui-mk__spacer" />
				<Toggle
					label={ __( 'Verified authors only', 'emcp-tools' ) }
					checked={ !! verified }
					onChange={ ( on ) => reset( setVerified )( on ? '1' : '' ) }
				/>
				<Segmented
					label={ __( 'View', 'emcp-tools' ) }
					value={ view }
					onChange={ chooseView }
					options={ [
						{
							value: 'grid',
							label: __( 'Grid view', 'emcp-tools' ),
						},
						{
							value: 'list',
							label: __( 'List view', 'emcp-tools' ),
						},
					] }
				/>
			</div>
			{ list?.error && (
				<Notice
					tone="warning"
					title={ __(
						'EMCP Cloud could not be reached',
						'emcp-tools'
					) }
				>
					{ list.error }
				</Notice>
			) }
			{ ! list && (
				<Skeleton
					lines={ 6 }
					label={ __( 'Loading items', 'emcp-tools' ) }
				/>
			) }
			{ list && ! list.items.length && ! list.error && (
				<EmptyState
					icon="search"
					title={ __(
						'Nothing matches those filters',
						'emcp-tools'
					) }
					actions={
						<Button onClick={ clearAll }>
							{ __( 'Clear all', 'emcp-tools' ) }
						</Button>
					}
				/>
			) }
			{ list && list.items.length > 0 && 'grid' === view && (
				<div className="eui-mk__grid">
					{ list.items.map( ( item ) => (
						<ListingCard
							key={ item.slug }
							item={ item }
							busy={ busy === item.slug }
							disabled={ '' !== busy }
							onInstall={ install }
						/>
					) ) }
				</div>
			) }
			{ list && list.items.length > 0 && 'list' === view && (
				<Table
					caption={ __( 'Marketplace items', 'emcp-tools' ) }
					rowKey="slug"
					rows={ list.items }
					columns={ [
						{
							key: 'title',
							label: __( 'Name', 'emcp-tools' ),
							render: ( i ) => <strong>{ i.title }</strong>,
						},
						{
							key: 'kindLabel',
							label: __( 'Type', 'emcp-tools' ),
						},
						{
							key: 'category',
							label: __( 'Category', 'emcp-tools' ),
						},
						{
							key: 'author',
							label: __( 'Author', 'emcp-tools' ),
							render: ( i ) => i.author?.name || '',
						},
						{
							key: 'install',
							label: __( 'Install', 'emcp-tools' ),
							align: 'end',
							render: ( i ) => (
								<InstallControl
									item={ i }
									busy={ busy === i.slug }
									disabled={ '' !== busy }
									onInstall={ install }
								/>
							),
						},
					] }
				/>
			) }
			{ list && list.pages > 1 && (
				<Pagination
					page={ list.page }
					totalPages={ list.pages }
					onChange={ ( p ) => setPage( String( p ) ) }
					label={ __( 'Marketplace pages', 'emcp-tools' ) }
				/>
			) }
			{ null !== installs && (
				<Drawer
					open
					title={ __( 'My installs', 'emcp-tools' ) }
					onClose={ () => setInstalls( null ) }
				>
					{ installs.length ? (
						<ul className="eui-mk__installs">
							{ installs.map( ( i ) => (
								<li key={ i.slug }>
									<a href={ i.reviewUrl }>
										{ sprintf(
											/* translators: %s: item name. */
											__( 'Review %s', 'emcp-tools' ),
											i.title
										) }
									</a>
									<span className="eui-mk__install-meta">
										{ i.status }
									</span>
								</li>
							) ) }
						</ul>
					) : (
						<p>
							{ __(
								'Nothing installed from the marketplace yet.',
								'emcp-tools'
							) }
						</p>
					) }
				</Drawer>
			) }
		</div>
	);
}
