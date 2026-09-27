import { __, sprintf } from '@wordpress/i18n';
import {
	BarChart,
	Button,
	Card,
	EmptyState,
	HBarList,
	Icon,
	IconButton,
	Segmented,
} from '@emcp/ui';
import { relativeTime } from '../history/lib';

const TYPE_ICONS = {
	edit: 'pencil',
	delete: 'trash-2',
	add: 'plus',
	settings: 'sliders-horizontal',
	undo: 'rotate-ccw',
};

/**
 * A short day label ("Sep 11") for a Y-m-d date, never shifted by the
 * browser's zone.
 *
 * @param {string} date Y-m-d.
 */
const dayLabel = ( date ) => {
	try {
		return new Intl.DateTimeFormat( undefined, {
			month: 'short',
			day: 'numeric',
			timeZone: 'UTC',
		} ).format( new Date( `${ date }T12:00:00Z` ) );
	} catch {
		return date;
	}
};

/**
 * The four-cell health strip.
 *
 * @param {Object} props
 * @param {Object} props.health health() payload.
 */
export function HealthStrip( { health } ) {
	const cells = [
		{ key: 'server', icon: 'server', ...health.server },
		{ key: 'clients', icon: 'plug', ...health.clients },
		{ key: 'tools', icon: 'wrench', ...health.tools },
		{
			key: 'version',
			icon: health.version.update ? 'refresh-cw' : 'circle-check',
			...health.version,
		},
	];
	return (
		<div className="emcp-dash__health">
			{ cells.map( ( c ) => {
				const body = (
					<>
						<span
							className={ `emcp-dash__health-icon is-${ c.key }${
								'server' === c.key && ! c.online
									? ' is-off'
									: ''
							}` }
							aria-hidden="true"
						>
							<Icon name={ c.icon } />
						</span>
						<span className="emcp-dash__health-text">
							<strong>{ c.label }</strong>
							<span>{ c.sub }</span>
						</span>
					</>
				);
				return 'version' === c.key && c.update ? (
					<a key={ c.key } className="emcp-dash__cell" href={ c.url }>
						{ body }
					</a>
				) : (
					<div key={ c.key } className="emcp-dash__cell">
						{ body }
					</div>
				);
			} ) }
		</div>
	);
}

/**
 * AI activity: range switch, KPIs, the stacked bar chart.
 *
 * @param {Object}   props
 * @param {Object}   props.activity activity() payload.
 * @param {number}   props.range    Selected range.
 * @param {Function} props.onRange  ( days ).
 */
export function ActivityCard( { activity, range, onRange } ) {
	const last = activity.days.length - 1;
	const chart = activity.days.map( ( d, i ) => ( {
		label: i === last ? __( 'Today', 'emcp-tools' ) : dayLabel( d.date ),
		values: { kept: d.kept, rolled: d.rolled },
	} ) );
	return (
		<Card
			title={ __( 'AI activity', 'emcp-tools' ) }
			actions={
				<Segmented
					label={ __( 'Range', 'emcp-tools' ) }
					options={ [
						{ value: '7', label: __( '7d', 'emcp-tools' ) },
						{ value: '14', label: __( '14d', 'emcp-tools' ) },
						{ value: '30', label: __( '30d', 'emcp-tools' ) },
					] }
					value={ String( range ) }
					onChange={ ( v ) => onRange( Number( v ) ) }
				/>
			}
		>
			<dl className="emcp-dash__kpis">
				{ activity.kpis.map( ( k ) => (
					<div key={ k.key } className="emcp-dash__kpi">
						<dt>{ k.label }</dt>
						<dd>
							<span className="emcp-dash__kpi-value">
								{ k.value }
							</span>
							<span className="emcp-dash__kpi-sub">
								{ k.sub }
							</span>
						</dd>
					</div>
				) ) }
			</dl>
			<BarChart
				label={ __( 'Changes per day', 'emcp-tools' ) }
				data={ chart }
				series={ [
					{
						key: 'kept',
						label: __( 'Changes kept', 'emcp-tools' ),
						colorVar: '--emcp-primary',
					},
					{
						key: 'rolled',
						label: __( 'Rolled back', 'emcp-tools' ),
						colorVar: '--emcp-primary-soft',
					},
				] }
			/>
		</Card>
	);
}

/**
 * Most used tools as horizontal bars.
 *
 * @param {Object} props
 * @param {Array}  props.items  mostUsed.
 * @param {string} props.logUrl MCP Log screen.
 */
export function MostUsed( { items, logUrl } ) {
	return (
		<Card title={ __( 'Most used tools', 'emcp-tools' ) }>
			{ items.length ? (
				<HBarList
					label={ __( 'Most used tools', 'emcp-tools' ) }
					items={ items.map( ( m ) => ( {
						label: m.tool.replace( /^emcp-tools[/-]/, '' ),
						value: m.count,
					} ) ) }
				/>
			) : (
				<EmptyState
					icon="wrench"
					title={ __( 'No tool calls yet', 'emcp-tools' ) }
				/>
			) }
			<a className="emcp-dash__more" href={ logUrl }>
				{ __( 'Open MCP Log', 'emcp-tools' ) }
				<Icon name="arrow-right" />
			</a>
		</Card>
	);
}

/**
 * The five newest changes, with Undo.
 *
 * @param {Object}   props
 * @param {Array}    props.rows       recent.
 * @param {string}   props.busy       Row id being undone.
 * @param {Function} props.onUndo     ( row ).
 * @param {string}   props.historyUrl History screen.
 */
export function RecentChanges( { rows, busy, onUndo, historyUrl } ) {
	return (
		<Card
			title={ __( 'Recent changes', 'emcp-tools' ) }
			actions={
				<a className="emcp-dash__link" href={ historyUrl }>
					{ __( 'View history', 'emcp-tools' ) }
				</a>
			}
		>
			{ rows.length ? (
				<ul
					className="emcp-dash__recent"
					aria-label={ __( 'Recent changes', 'emcp-tools' ) }
				>
					{ rows.map( ( r ) => (
						<li
							key={ r.id }
							className={ `emcp-dash__change${
								r.rolledBack ? ' is-rolled-back' : ''
							}` }
						>
							<span
								className={ `emcp-dash__change-icon is-${ r.type }` }
								aria-hidden="true"
							>
								<Icon
									name={ TYPE_ICONS[ r.type ] || 'pencil' }
								/>
							</span>
							<span className="emcp-dash__change-main">
								<strong>{ r.title }</strong>
								<code className="emcp-dash__mono">
									{ r.tool }
								</code>
							</span>
							<span className="emcp-dash__change-client">
								{ r.client }
							</span>
							<span className="emcp-dash__change-time">
								{ relativeTime( r.time ) }
							</span>
							<span className="emcp-dash__change-action">
								{ r.rolledBack && (
									<span className="emcp-dash__rolled">
										{ __( 'Rolled back', 'emcp-tools' ) }
									</span>
								) }
								{ ! r.rolledBack && r.reversible && (
									<Button
										size="sm"
										variant="ghost"
										icon="rotate-ccw"
										loading={ busy === r.id }
										aria-label={ sprintf(
											/* translators: %s: change title. */
											__( 'Undo: %s', 'emcp-tools' ),
											r.title
										) }
										onClick={ () => onUndo( r ) }
									>
										{ __( 'Undo', 'emcp-tools' ) }
									</Button>
								) }
								{ ! r.rolledBack && ! r.reversible && (
									<span
										className="emcp-dash__muted"
										title={ r.reason }
									>
										{ __( 'Can’t undo', 'emcp-tools' ) }
									</span>
								) }
							</span>
						</li>
					) ) }
				</ul>
			) : (
				<EmptyState
					icon="history"
					title={ __( 'No changes recorded yet', 'emcp-tools' ) }
				>
					{ __(
						'Changes your AI makes appear here, each with Undo.',
						'emcp-tools'
					) }
				</EmptyState>
			) }
		</Card>
	);
}

/**
 * Needs your attention (spec 9.7).
 *
 * @param {Object}   props
 * @param {Array}    props.items     attention.
 * @param {Function} props.onDismiss ( item ).
 */
export function Attention( { items, onDismiss } ) {
	return (
		<Card title={ __( 'Needs your attention', 'emcp-tools' ) }>
			{ items.length ? (
				<ul className="emcp-dash__attention">
					{ items.map( ( it ) => (
						<li
							key={ it.id }
							className={ `emcp-dash__alert is-${ it.severity }` }
						>
							<span
								className="emcp-dash__alert-icon"
								aria-hidden="true"
							>
								<Icon name={ it.icon } />
							</span>
							<span className="emcp-dash__alert-main">
								<strong>{ it.title }</strong>
								<span>{ it.body }</span>
								<a href={ it.action_url }>
									{ it.action_label }
								</a>
							</span>
							<IconButton
								icon="x"
								size="sm"
								label={ sprintf(
									/* translators: %s: item title. */
									__( 'Dismiss: %s', 'emcp-tools' ),
									it.title
								) }
								onClick={ () => onDismiss( it ) }
							/>
						</li>
					) ) }
				</ul>
			) : (
				<EmptyState
					icon="circle-check"
					title={ __( 'Nothing needs your attention', 'emcp-tools' ) }
				/>
			) }
		</Card>
	);
}

/**
 * The dark EMCP Cloud panel (not dismissible).
 *
 * @param {Object} props
 * @param {Object} props.cloud { connected, url }.
 */
export function CloudPanel( { cloud } ) {
	const feats = [
		[
			'database-backup',
			__( 'Back up & restore anywhere', 'emcp-tools' ),
			__( 'Secure, reliable backups you can count on.', 'emcp-tools' ),
		],
		[
			'refresh-cw',
			__( 'Sync across all your sites', 'emcp-tools' ),
			__( 'Keep every site in perfect sync.', 'emcp-tools' ),
		],
		[
			'store',
			__( 'Publish & sell', 'emcp-tools' ),
			__(
				'Share your creations on the marketplace and grow.',
				'emcp-tools'
			),
		],
	];
	return (
		<section
			className="emcp-dash__cloud"
			aria-labelledby="emcp-dash-cloud-h"
		>
			<div className="emcp-dash__cloud-intro">
				<span className="emcp-dash__cloud-badge">
					<Icon name="cloud" />
					{ __( 'EMCP Cloud', 'emcp-tools' ) }
				</span>
				<h2 id="emcp-dash-cloud-h">
					{ __( 'Your artifacts,', 'emcp-tools' ) }{ ' ' }
					<span>{ __( 'everywhere', 'emcp-tools' ) }</span>
				</h2>
				<p>
					{ __(
						'Back up your blocks, widgets and snippets, sync them across sites, and publish to the marketplace.',
						'emcp-tools'
					) }
				</p>
				<Button variant="primary" href={ cloud.url } icon="arrow-right">
					{ cloud.connected
						? __( 'Open EMCP Cloud', 'emcp-tools' )
						: __( 'Explore EMCP Cloud', 'emcp-tools' ) }
				</Button>
			</div>
			<ul className="emcp-dash__cloud-feats">
				{ feats.map( ( [ icon, title, body ] ) => (
					<li key={ title }>
						<span aria-hidden="true">
							<Icon name={ icon } />
						</span>
						<strong>{ title }</strong>
						<span>{ body }</span>
					</li>
				) ) }
			</ul>
		</section>
	);
}

/**
 * Jump to a feature.
 *
 * @param {Object} props
 * @param {Array}  props.items features.
 */
export function Features( { items } ) {
	return (
		<section
			className="emcp-dash__features"
			aria-labelledby="emcp-dash-features-h"
		>
			<div className="emcp-dash__section-head">
				<h2 id="emcp-dash-features-h">
					{ __( 'Jump to a feature', 'emcp-tools' ) }
				</h2>
				<p>{ __( 'Everything this plugin can do', 'emcp-tools' ) }</p>
			</div>
			<ul className="emcp-dash__feature-grid">
				{ items.map( ( f ) => (
					<li key={ f.url }>
						<a className="emcp-dash__feature" href={ f.url }>
							<span aria-hidden="true">
								<Icon name={ f.icon } />
							</span>
							<span className="emcp-dash__feature-text">
								<strong>
									{ f.title }
									{ f.pro && (
										<span className="emcp-dash__pro">
											{ __( 'Pro', 'emcp-tools' ) }
										</span>
									) }
								</strong>
								<span>{ f.desc }</span>
							</span>
						</a>
					</li>
				) ) }
			</ul>
		</section>
	);
}

/**
 * Video guides.
 *
 * @param {Object} props
 * @param {Array}  props.items     videos.
 * @param {string} props.tutorials All tutorials URL.
 */
export function Videos( { items, tutorials } ) {
	return (
		<Card
			title={ __( 'Video guides', 'emcp-tools' ) }
			actions={
				<a
					className="emcp-dash__link"
					href={ tutorials }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'All tutorials', 'emcp-tools' ) }
					<Icon name="external-link" />
				</a>
			}
		>
			<ul className="emcp-dash__videos">
				{ items.map( ( v ) => (
					<li key={ v.url }>
						<a
							className="emcp-dash__video"
							href={ v.url }
							target="_blank"
							rel="noopener noreferrer"
						>
							<span className="emcp-dash__thumb">
								<img src={ v.thumb } alt="" loading="lazy" />
								<span
									className="emcp-dash__play"
									aria-hidden="true"
								/>
							</span>
							<strong>{ v.title }</strong>
							<span>{ v.channel }</span>
						</a>
					</li>
				) ) }
			</ul>
		</Card>
	);
}

/**
 * Help & resources.
 *
 * @param {Object} props
 * @param {Array}  props.items help.
 */
export function Help( { items } ) {
	return (
		<Card title={ __( 'Help & resources', 'emcp-tools' ) }>
			<ul className="emcp-dash__help">
				{ items.map( ( h ) => {
					const external = /^https?:/.test( h.url );
					return (
						<li key={ h.url }>
							<a
								href={ h.url }
								target={ external ? '_blank' : undefined }
								rel={
									external ? 'noopener noreferrer' : undefined
								}
							>
								<span aria-hidden="true">
									<Icon name={ h.icon } />
								</span>
								<span className="emcp-dash__help-text">
									<strong>{ h.title }</strong>
									<span>{ h.desc }</span>
								</span>
								<Icon name="chevron-right" />
							</a>
						</li>
					);
				} ) }
			</ul>
		</Card>
	);
}
