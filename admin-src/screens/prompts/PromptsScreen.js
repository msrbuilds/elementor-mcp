import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	CodeBlock,
	Drawer,
	EmptyState,
	FilterChip,
	Icon,
	Notice,
	PageHeader,
	Pagination,
	SearchInput,
	errorMessage,
	request,
	useQueryState,
	useToast,
} from '@emcp/ui';
import { PromptCard } from './PromptCard';
import { ChipCarousel } from './ChipCarousel';
import { CustomizeDrawer } from './CustomizeDrawer';

const API = '/emcp-tools/v1/admin/prompts';

/* Free builds: the upgrade call to action for the full Pro library. */
function ProBanner( { count, url } ) {
	const points = [
		__( '50 landing pages', 'emcp-tools' ),
		__( '50 complete websites', 'emcp-tools' ),
		__( '20 categories, kept in sync', 'emcp-tools' ),
	];
	return (
		<section
			className="eui-prompts-pro"
			aria-labelledby="eui-prompts-pro-title"
		>
			<div className="eui-prompts-pro__body">
				<span className="eui-prompts-pro__badge">
					{ __( 'Pro', 'emcp-tools' ) }
				</span>
				<h2
					id="eui-prompts-pro-title"
					className="eui-prompts-pro__title"
				>
					{ __(
						'Get 100+ premium prompts with EMCP Pro',
						'emcp-tools'
					) }
				</h2>
				<p className="eui-prompts-pro__text">
					{ sprintf(
						/* translators: %d: number of free sample prompts. */
						_n(
							'You are using %d free sample. Pro unlocks the full library, each prompt a complete brief your AI builds from, ready to customize.',
							'You are using %d free samples. Pro unlocks the full library, each prompt a complete brief your AI builds from, ready to customize.',
							count,
							'emcp-tools'
						),
						count
					) }
				</p>
				<ul className="eui-prompts-pro__points">
					{ points.map( ( p ) => (
						<li key={ p }>
							<Icon name="check" size={ 16 } />
							{ p }
						</li>
					) ) }
				</ul>
			</div>
			<a
				className="eui-prompts-pro__cta"
				href={ url }
				target="_blank"
				rel="noopener noreferrer"
			>
				{ __( 'Upgrade to Pro', 'emcp-tools' ) }
				<Icon name="arrow-right" size={ 16 } />
				<span className="eui-visually-hidden">
					{ __( '(opens in a new tab)', 'emcp-tools' ) }
				</span>
			</a>
		</section>
	);
}

function ago( ts ) {
	if ( ! ts ) {
		return '';
	}
	const days = Math.round( ( Date.now() / 1000 - ts ) / 86400 );
	if ( days < 1 ) {
		return __( 'today', 'emcp-tools' );
	}
	return sprintf(
		/* translators: %d: number of days. */
		_n( '%d day ago', '%d days ago', days, 'emcp-tools' ),
		days
	);
}

/**
 * Prompts screen (spec 8.8).
 *
 * @param {Object} props      Props.
 * @param {Object} props.data Boot payload (EMCP_Tools_Admin_Prompts_Data).
 */
export function PromptsScreen( { data: initial } ) {
	const toast = useToast();
	const [ data, setData ] = useState( initial );
	const [ search, setSearch ] = useQueryState( 'q', '', { debounce: 250 } );
	const [ category, setCategory ] = useQueryState( 'category', '' );
	const [ page, setPage ] = useQueryState( 'paged', '1' );
	const [ notice, setNotice ] = useState( ! initial.noticeDismissed );
	const [ syncing, setSyncing ] = useState( false );
	const [ preview, setPreview ] = useState( null );
	const [ customizing, setCustomizing ] = useState( null );
	const first = useRef( true );

	useEffect( () => {
		if ( first.current ) {
			first.current = false;
			if ( ! search && ! category && '1' === page ) {
				return;
			}
		}
		const q = new URLSearchParams( { search, category, page } );
		request( `${ API }?${ q }` )
			.then( setData )
			.catch( ( e ) => toast.error( errorMessage( e ) ) );
	}, [ search, category, page ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const sync = async () => {
		setSyncing( true );
		try {
			const res = await request( API + '/sync', { method: 'POST' } );
			setData( res );
			toast.success( res.message );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			setSyncing( false );
		}
	};

	const dismiss = () => {
		setNotice( false );
		request( API + '/notice/dismiss', { method: 'POST' } ).catch(
			() => {}
		);
	};

	const all = data.categories.reduce( ( n, c ) => n + c.count, 0 );
	const pick = ( slug ) => {
		setCategory( slug );
		setPage( '1' );
	};

	return (
		<div className="eui-prompts">
			<PageHeader
				title={ __( 'Prompts', 'emcp-tools' ) }
				tier={ 'pro' === data.source ? 'pro' : undefined }
				description={
					sprintf(
						/* translators: 1: prompts, 2: categories. */
						__(
							'%1$d prompts across %2$d categories.',
							'emcp-tools'
						),
						all,
						data.categories.length
					) +
					( data.syncedAt
						? ' ' +
							sprintf(
								/* translators: %s: relative time. */ __(
									'Last synced %s.',
									'emcp-tools'
								),
								ago( data.syncedAt )
							)
						: '' )
				}
				actions={
					data.licensed && (
						<Button
							icon="refresh-cw"
							onClick={ sync }
							loading={ syncing }
							disabled={ syncing }
						>
							{ __( 'Sync library', 'emcp-tools' ) }
						</Button>
					)
				}
			/>
			{ notice && (
				<Notice
					tone="info"
					title={ __( 'Prompts have been rewritten', 'emcp-tools' ) }
					onDismiss={ dismiss }
				>
					{ __(
						'Each prompt gives the AI a style guide, design direction, content and build standards for a landing page or complete website. Use Customize to choose your builder, business details, colours and fonts.',
						'emcp-tools'
					) }
					{ data.v1Url && (
						<>
							{ ' ' }
							<a href={ data.v1Url }>
								{ __(
									'Prefer the originals? Download v1 prompts',
									'emcp-tools'
								) }
							</a>
						</>
					) }
				</Notice>
			) }
			{ data.error && (
				<Notice
					tone="warning"
					title={ __(
						'The prompt library could not be synced',
						'emcp-tools'
					) }
				>
					{ sprintf(
						/* translators: %s: error message. */ __(
							'%s Showing the bundled samples instead.',
							'emcp-tools'
						),
						data.error
					) }
				</Notice>
			) }
			{ ! data.licensed && (
				<ProBanner count={ all } url={ data.upgradeUrl } />
			) }
			<h2 className="eui-visually-hidden">
				{ __( 'Prompt library', 'emcp-tools' ) }
			</h2>
			<div className="eui-prompts__filters">
				<SearchInput
					debounce={ 250 }
					label={ __( 'Search prompts', 'emcp-tools' ) }
					value={ search }
					onChange={ ( v ) => {
						setSearch( v );
						setPage( '1' );
					} }
					placeholder={ __( 'Search prompts…', 'emcp-tools' ) }
				/>
				<ChipCarousel label={ __( 'Categories', 'emcp-tools' ) }>
					<FilterChip
						label={ __( 'All', 'emcp-tools' ) }
						count={ all }
						active={ '' === category }
						onClick={ () => pick( '' ) }
					/>
					{ data.categories.map( ( c ) => (
						<FilterChip
							key={ c.slug }
							label={ c.label }
							count={ c.count }
							active={ category === c.slug }
							onClick={ () => pick( c.slug ) }
						/>
					) ) }
				</ChipCarousel>
			</div>
			{ data.items.length ? (
				<div className="eui-prompts__grid">
					{ data.items.map( ( p ) => (
						<PromptCard
							key={ p.category + '/' + p.slug }
							prompt={ p }
							aiChatUrl={ data.aiChatUrl }
							onPreview={ setPreview }
							onCustomize={ setCustomizing }
						/>
					) ) }
				</div>
			) : (
				<EmptyState
					icon="search"
					title={ __( 'No prompts match', 'emcp-tools' ) }
				>
					{ __( 'Try another search or category.', 'emcp-tools' ) }
				</EmptyState>
			) }
			{ data.pages > 1 && (
				<Pagination
					page={ data.page }
					totalPages={ data.pages }
					onChange={ ( n ) => setPage( String( n ) ) }
					label={ __( 'Prompt pages', 'emcp-tools' ) }
				/>
			) }
			{ customizing && (
				<CustomizeDrawer
					prompt={ customizing }
					aiChatUrl={ data.aiChatUrl }
					onClose={ () => setCustomizing( null ) }
				/>
			) }
			{ preview && (
				<Drawer
					open
					title={ preview.title }
					onClose={ () => setPreview( null ) }
					width={ 640 }
				>
					<CodeBlock
						value={ preview.content }
						label={ preview.categoryLabel }
					/>
				</Drawer>
			) }
		</div>
	);
}
