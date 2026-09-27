import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Card,
	EmptyState,
	PageHeader,
	SafeHtml,
	SearchInput,
	Segmented,
	Skeleton,
	errorMessage,
	request,
	useQueryState,
	useToast,
} from '@emcp/ui';

const API = '/emcp-tools/v1/admin/changelog';
const SHOWN = 6;

/**
 * One changelog item: badge, title, text, issue links.
 *
 * @param {Object} props
 * @param {Object} props.item Parsed item (html is kses-filtered server-side).
 */
function Item( { item } ) {
	return (
		<li className="emcp-cl__item">
			<span className={ `emcp-cl__tag is-${ item.type }` }>
				{ item.tag }
			</span>
			<div className="emcp-cl__body">
				{ item.title && (
					<strong className="emcp-cl__title">{ item.title }</strong>
				) }
				<SafeHtml className="emcp-cl__text" html={ item.html } />
			</div>
			{ item.issues.length > 0 && (
				<span className="emcp-cl__issues">
					{ item.issues.map( ( i ) => (
						<a
							key={ i.number }
							href={ i.url }
							target="_blank"
							rel="noopener noreferrer"
						>
							#{ i.number }
						</a>
					) ) }
				</span>
			) }
		</li>
	);
}

/**
 * The LATEST / UNRELEASED pill of the first release.
 *
 * @param {Object} props
 * @param {Object} props.release Index entry or release.
 */
function Pill( { release } ) {
	return (
		<span className="emcp-cl__pill">
			{ release.unreleased
				? __( 'UNRELEASED', 'emcp-tools' )
				: __( 'LATEST', 'emcp-tools' ) }
		</span>
	);
}

/**
 * Changelog (spec 8.23).
 *
 * @param {Object} props
 * @param {Object} props.data Boot payload (EMCP_Tools_Admin_Changelog_Data).
 */
export function ChangelogScreen( { data } ) {
	const first = data.index[ 0 ]?.version || '';
	const [ version, setVersion ] = useQueryState( 'version', first );
	const [ cache, setCache ] = useState(
		data.latest ? { [ data.latest.version ]: data.latest } : {}
	);
	const [ filter, setFilter ] = useState( 'all' );
	const [ expanded, setExpanded ] = useState( false );
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( null );
	const gen = useRef( 0 );
	const toast = useToast();

	const pos = data.index.findIndex( ( r ) => r.version === version );
	const current = cache[ version ];
	const showAll = expanded || pos >= SHOWN;
	const rail = showAll ? data.index : data.index.slice( 0, SHOWN );

	useEffect( () => {
		if ( ! version || cache[ version ] || pos < 0 ) {
			return;
		}
		let live = true;
		request( `${ API }/${ encodeURIComponent( version ) }` )
			.then(
				( r ) =>
					live && setCache( ( c ) => ( { ...c, [ r.version ]: r } ) )
			)
			.catch( ( e ) => live && toast.error( errorMessage( e ) ) );
		return () => {
			live = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ version ] );

	const onSearch = async ( q ) => {
		setSearch( q );
		const mine = ++gen.current;
		if ( q.trim().length < 2 ) {
			setResults( null );
			return;
		}
		try {
			const r = await request(
				`${ API }?search=${ encodeURIComponent( q.trim() ) }`
			);
			if ( mine === gen.current ) {
				setResults( r.results );
			}
		} catch ( e ) {
			if ( mine === gen.current ) {
				toast.error( errorMessage( e ) );
			}
		}
	};

	const open = ( v ) => {
		setVersion( v );
		setFilter( 'all' );
		setSearch( '' );
		setResults( null );
		++gen.current;
	};

	const items = current
		? current.items.filter( ( i ) => 'all' === filter || i.type === filter )
		: [];

	return (
		<div className="emcp-cl">
			<PageHeader
				title={ __( 'Changelog', 'emcp-tools' ) }
				description={ __(
					'What changed in each release of EMCP Tools.',
					'emcp-tools'
				) }
				actions={
					<SearchInput
						label={ __( 'Search changes or #issue', 'emcp-tools' ) }
						placeholder={ __(
							'Search changes or #issue',
							'emcp-tools'
						) }
						value={ search }
						debounce={ 250 }
						onChange={ onSearch }
					/>
				}
			/>
			<div className="emcp-cl__layout">
				<nav
					className="emcp-cl__rail"
					aria-label={ __( 'Releases', 'emcp-tools' ) }
				>
					<h2 className="emcp-cl__rail-title">
						{ __( 'Releases', 'emcp-tools' ) }
					</h2>
					<ul>
						{ rail.map( ( r, i ) => (
							<li key={ r.version }>
								<button
									type="button"
									className="emcp-cl__release"
									aria-current={
										r.version === version && ! results
											? 'true'
											: undefined
									}
									onClick={ () => open( r.version ) }
								>
									<span>v{ r.version }</span>
									{ 0 === i && <Pill release={ r } /> }
								</button>
							</li>
						) ) }
					</ul>
					{ ! showAll && data.index.length > SHOWN && (
						<button
							type="button"
							className="emcp-cl__older"
							onClick={ () => setExpanded( true ) }
						>
							{ __( 'Older releases', 'emcp-tools' ) }
						</button>
					) }
				</nav>
				<div className="emcp-cl__main">
					{ null !== results && (
						<Card
							title={ sprintf(
								/* translators: %s: search query. */
								__( 'Results for “%s”', 'emcp-tools' ),
								search.trim()
							) }
						>
							{ results.length ? (
								results.map( ( g ) => (
									<section
										key={ g.version }
										className="emcp-cl__group"
									>
										<h3>
											<button
												type="button"
												className="emcp-cl__group-link"
												onClick={ () =>
													open( g.version )
												}
											>
												{ sprintf(
													/* translators: %s: version. */
													__(
														'Version %s',
														'emcp-tools'
													),
													g.version
												) }
											</button>
										</h3>
										<ul className="emcp-cl__items">
											{ g.items.map( ( it, n ) => (
												<Item key={ n } item={ it } />
											) ) }
										</ul>
									</section>
								) )
							) : (
								<EmptyState
									icon="search"
									title={ __(
										'No changes match',
										'emcp-tools'
									) }
								/>
							) }
						</Card>
					) }
					{ null === results && ! current && (
						<Card>
							<Skeleton
								lines={ 6 }
								label={ __( 'Loading release', 'emcp-tools' ) }
							/>
						</Card>
					) }
					{ null === results && current && (
						<section className="emcp-cl__release-card">
							<header className="emcp-cl__head">
								<h2>
									{ sprintf(
										/* translators: %s: version. */
										__( 'Version %s', 'emcp-tools' ),
										current.version
									) }
								</h2>
								{ 0 === pos && <Pill release={ current } /> }
							</header>
							{ current.summary && (
								<SafeHtml
									className="emcp-cl__summary"
									html={ current.summary }
								/>
							) }
							<Segmented
								label={ __( 'Filter changes', 'emcp-tools' ) }
								options={ [
									{
										value: 'all',
										label: sprintf(
											/* translators: %d: count. */
											__( 'All %d', 'emcp-tools' ),
											current.counts.all
										),
									},
									{
										value: 'new',
										label: sprintf(
											/* translators: %d: count. */
											__( 'New %d', 'emcp-tools' ),
											current.counts.new
										),
									},
									{
										value: 'fixed',
										label: sprintf(
											/* translators: %d: count. */
											__( 'Fixed %d', 'emcp-tools' ),
											current.counts.fixed
										),
									},
								] }
								value={ filter }
								onChange={ setFilter }
							/>
							{ items.length ? (
								<ul className="emcp-cl__items">
									{ items.map( ( it, n ) => (
										<Item key={ n } item={ it } />
									) ) }
								</ul>
							) : (
								<EmptyState
									icon="info"
									title={ __(
										'No changes of this kind in this release',
										'emcp-tools'
									) }
								/>
							) }
						</section>
					) }
				</div>
			</div>
		</div>
	);
}
