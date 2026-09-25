import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Checkbox,
	Drawer,
	EmptyState,
	FilterChip,
	Notice,
	PageHeader,
	Pagination,
	SearchInput,
	errorMessage,
	request,
	useConfirm,
	useQueryState,
	useToast,
} from '@emcp/ui';
import { KitCard } from './KitCard';

const API = '/emcp-tools/v1/admin/brand-kits';
const SLOTS = () => [
	__( 'Primary', 'emcp-tools' ),
	__( 'Secondary', 'emcp-tools' ),
	__( 'Text', 'emcp-tools' ),
	__( 'Accent', 'emcp-tools' ),
];

/**
 * Brand Kits screen (spec 8.17).
 *
 * @param {Object} props      Props.
 * @param {Object} props.data Boot payload (EMCP_Tools_Admin_Brand_Kits_Data).
 */
export function BrandKitsScreen( { data: initial } ) {
	const toast = useToast();
	const confirm = useConfirm();
	const [ data, setData ] = useState( initial );
	const [ search, setSearch ] = useQueryState( 'q', '', { debounce: 250 } );
	const [ category, setCategory ] = useQueryState( 'category', '' );
	const [ page, setPage ] = useQueryState( 'paged', '1' );
	const [ clobber, setClobber ] = useState( false );
	const [ busy, setBusy ] = useState( '' );
	const [ preview, setPreview ] = useState( null );
	const first = useRef( true );

	useEffect( () => {
		if ( first.current ) {
			first.current = false;
			if ( ! search && ! category && '1' === page ) {
				return;
			}
		}
		request(
			`${ API }?${ new URLSearchParams( { search, category, page } ) }`
		)
			.then( setData )
			.catch( ( e ) => toast.error( errorMessage( e ) ) );
	}, [ search, category, page ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Actions post to the bare path: a `category` in the query would override
	// the route's {category} (WP_REST_Request reads GET before URL params).
	// The list is then re-read with the current filters.
	const refresh = () =>
		request(
			`${ API }?${ new URLSearchParams( { search, category, page } ) }`
		).then( setData );

	const apply = async ( kit ) => {
		setBusy( kit.category + '/' + kit.slug );
		try {
			await request( `${ API }/${ kit.category }/${ kit.slug }/apply`, {
				method: 'POST',
				data: { backup: true },
			} );
			toast.success(
				sprintf(
					/* translators: %s: kit name. */
					__(
						'%s applied. A restore point was saved first.',
						'emcp-tools'
					),
					kit.title
				)
			);
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			// Re-read even after a failure: the site may have changed.
			await refresh().catch( () => {} );
			setBusy( '' );
		}
	};

	const restore = async () => {
		const ok = await confirm( {
			title: __( 'Restore the previous kit?', 'emcp-tools' ),
			message: clobber
				? __(
						'Your site colors, fonts and your custom colors and fonts go back to the last restore point.',
						'emcp-tools'
					)
				: __(
						'Your site colors and fonts go back to the last restore point. Custom colors and fonts are kept.',
						'emcp-tools'
					),
			confirmLabel: __( 'Restore', 'emcp-tools' ),
			tone: 'danger',
		} );
		if ( ! ok ) {
			return;
		}
		setBusy( 'restore' );
		try {
			await request( API + '/restore', {
				method: 'POST',
				data: { full_clobber: clobber },
			} );
			await refresh();
			toast.success( __( 'Previous kit restored.', 'emcp-tools' ) );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			setBusy( '' );
		}
	};

	const sync = async () => {
		setBusy( 'sync' );
		try {
			const res = await request( API + '/sync', { method: 'POST' } );
			setData( res );
			toast.success( res.message );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			setBusy( '' );
		}
	};

	const all = data.categories.reduce( ( n, c ) => n + c.count, 0 );
	const pick = ( slug ) => {
		setCategory( slug );
		setPage( '1' );
	};
	const c = data.current;

	return (
		<div className="eui-kits">
			<PageHeader
				title={ __( 'Brand Kits', 'emcp-tools' ) }
				tier={ 'pro' === data.source ? 'pro' : undefined }
				description={ sprintf(
					/* translators: %d: kit count. */
					__(
						"%d color and typography kits. Applying one replaces your site's global palette and fonts.",
						'emcp-tools'
					),
					all
				) }
				actions={
					data.licensed && (
						<Button
							icon="refresh-cw"
							onClick={ sync }
							loading={ 'sync' === busy }
							disabled={ !! busy }
						>
							{ __( 'Sync library', 'emcp-tools' ) }
						</Button>
					)
				}
			/>
			{ ! data.elementorActive && (
				<Notice
					tone="warning"
					title={ __(
						'Activate Elementor to apply kits',
						'emcp-tools'
					) }
				>
					{ __(
						'Brand kits write to the Elementor site settings. You can browse them now and apply one after Elementor is active.',
						'emcp-tools'
					) }
				</Notice>
			) }
			{ data.error && (
				<Notice
					tone="warning"
					title={ __(
						'The brand kit library could not be synced',
						'emcp-tools'
					) }
				>
					{ sprintf(
						/* translators: %s: error message. */ __(
							'%s Showing the bundled starter kits instead.',
							'emcp-tools'
						),
						data.error
					) }
				</Notice>
			) }
			<div className="eui-kits__current">
				{ c ? (
					<div className="eui-kits__swatches" aria-hidden="true">
						{ c.swatches.map( ( s, i ) => (
							<span key={ i } style={ { backgroundColor: s } } />
						) ) }
					</div>
				) : null }
				<div className="eui-kits__current-text">
					<p className="eui-kits__current-title">
						{ c
							? sprintf(
									/* translators: %s: kit name. */ __(
										'Current kit: %s',
										'emcp-tools'
									),
									c.title
								)
							: __(
									'No kit applied from this library yet',
									'emcp-tools'
								) }
					</p>
					{ c && (
						<p className="eui-kits__current-meta">
							{ sprintf(
								/* translators: %s: date and time. */
								__(
									'Applied %s. A restore point was saved before applying.',
									'emcp-tools'
								),
								new Date( c.appliedAt * 1000 ).toLocaleString()
							) }
						</p>
					) }
				</div>
				{ data.restorable && (
					<div className="eui-kits__restore">
						<Checkbox
							checked={ clobber }
							onChange={ setClobber }
							label={ __(
								'Also restore custom colors and fonts',
								'emcp-tools'
							) }
						/>
						<Button
							icon="rotate-ccw"
							onClick={ restore }
							loading={ 'restore' === busy }
							disabled={ !! busy || ! data.elementorActive }
						>
							{ __( 'Restore previous', 'emcp-tools' ) }
						</Button>
					</div>
				) }
			</div>
			{ 'free' === data.source && ! data.licensed && (
				<Notice
					tone="info"
					title={ __( 'These are the starter kits', 'emcp-tools' ) }
				>
					{ __(
						'EMCP Pro adds the full library, kept in sync.',
						'emcp-tools'
					) }{ ' ' }
					<a
						href={ data.upgradeUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Get the full library', 'emcp-tools' ) }
					</a>
				</Notice>
			) }
			<h2 className="eui-visually-hidden">
				{ __( 'Brand kit library', 'emcp-tools' ) }
			</h2>
			<div className="eui-kits__filters">
				<SearchInput
					label={ __( 'Search kits', 'emcp-tools' ) }
					value={ search }
					onChange={ ( v ) => {
						setSearch( v );
						setPage( '1' );
					} }
					placeholder={ __( 'Search kits…', 'emcp-tools' ) }
				/>
				<div
					className="eui-kits__chips"
					role="group"
					aria-label={ __( 'Categories', 'emcp-tools' ) }
				>
					<FilterChip
						label={ __( 'All', 'emcp-tools' ) }
						count={ all }
						active={ '' === category }
						onClick={ () => pick( '' ) }
					/>
					{ data.categories.map( ( cat ) => (
						<FilterChip
							key={ cat.slug }
							label={ cat.label }
							count={ cat.count }
							active={ category === cat.slug }
							onClick={ () => pick( cat.slug ) }
						/>
					) ) }
				</div>
			</div>
			{ data.items.length ? (
				<div className="eui-kits__grid">
					{ data.items.map( ( k ) => (
						<KitCard
							key={ k.category + '/' + k.slug }
							kit={ k }
							disabled={
								! data.elementorActive ||
								( !! busy &&
									busy !== k.category + '/' + k.slug )
							}
							busy={ busy === k.category + '/' + k.slug }
							onApply={ apply }
							onPreview={ setPreview }
						/>
					) ) }
				</div>
			) : (
				<EmptyState
					icon="search"
					title={ __( 'No kits match', 'emcp-tools' ) }
				>
					{ __( 'Try another search or category.', 'emcp-tools' ) }
				</EmptyState>
			) }
			{ data.pages > 1 && (
				<Pagination
					page={ data.page }
					totalPages={ data.pages }
					onChange={ ( n ) => setPage( String( n ) ) }
					label={ __( 'Kit pages', 'emcp-tools' ) }
				/>
			) }
			{ preview && (
				<Drawer
					open
					title={ preview.title }
					onClose={ () => setPreview( null ) }
				>
					{ preview.thumbnail && (
						<img
							className="eui-kits__thumb"
							src={ preview.thumbnail }
							alt=""
						/>
					) }
					<ul className="eui-kits__palette">
						{ preview.swatches.map( ( s, i ) => (
							<li key={ i }>
								<span
									style={ { backgroundColor: s } }
									aria-hidden="true"
								/>
								{ SLOTS()[ i ] }
								<code>{ s }</code>
							</li>
						) ) }
					</ul>
					<p className="eui-kits__muted">
						{ sprintf(
							/* translators: 1: heading font, 2: body font. */ __(
								'Headings in %1$s, body text in %2$s.',
								'emcp-tools'
							),
							preview.headingFont,
							preview.bodyFont
						) }
					</p>
					<p className="eui-kits__muted">{ preview.description }</p>
				</Drawer>
			) }
		</div>
	);
}
