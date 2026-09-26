import { createInterpolateElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Badge, Button, Card, Icon, PageHeader } from '@emcp/ui';

const CARDS = () => ( {
	widgets: {
		title: __( 'Widgets', 'emcp-tools' ),
		tier: 'PRO',
		icon: 'layout-grid',
		desc: __(
			'AI-generated Elementor widgets, sandboxed and shown in the panel under "Custom (EMCP)".',
			'emcp-tools'
		),
	},
	snippets: {
		title: __( 'PHP Snippets', 'emcp-tools' ),
		tier: 'FREE',
		icon: 'code',
		desc: __(
			'Small PHP snippets that run as a shortcode or on a hook, inactive until you review them.',
			'emcp-tools'
		),
	},
	blocks: {
		title: __( 'Blocks', 'emcp-tools' ),
		tier: 'PRO',
		icon: 'blocks',
		desc: __(
			'AI-generated Gutenberg blocks, compiled from a spec and reviewed here before they go live.',
			'emcp-tools'
		),
	},
} );

const KIND_LABELS = () => ( {
	widget: __( 'Widget', 'emcp-tools' ),
	block: __( 'Block', 'emcp-tools' ),
	snippet: __( 'PHP snippet', 'emcp-tools' ),
} );

const REVIEW_ICONS = {
	none: 'circle-check',
	notice: 'info',
	warning: 'triangle-alert',
	critical: 'triangle-alert',
	error: 'triangle-alert',
};

/**
 * "2 days ago" from a Unix timestamp.
 *
 * @param {number} ts Seconds.
 * @return {string} Relative time, or '' without a timestamp.
 */
export function relativeTime( ts ) {
	if ( ! ts ) {
		return '';
	}
	const seconds = ts - Math.floor( Date.now() / 1000 );
	const rtf = new Intl.RelativeTimeFormat( undefined, { numeric: 'auto' } );
	const abs = Math.abs( seconds );
	if ( abs < 3600 ) {
		return rtf.format( Math.round( seconds / 60 ), 'minute' );
	}
	if ( abs < 86400 ) {
		return rtf.format( Math.round( seconds / 3600 ), 'hour' );
	}
	if ( abs < 7 * 86400 ) {
		return rtf.format( Math.round( seconds / 86400 ), 'day' );
	}
	if ( abs < 60 * 86400 ) {
		return rtf.format( Math.round( seconds / ( 7 * 86400 ) ), 'week' );
	}
	return new Intl.DateTimeFormat( undefined, {
		month: 'short',
		day: 'numeric',
		year: 'numeric',
	} ).format( new Date( ts * 1000 ) );
}

function OverviewCard( { card } ) {
	const meta = CARDS()[ card.type ];
	return (
		<a className="emcp-sbo-card" href={ card.url }>
			<span className="emcp-sbo-card__top">
				<span className="emcp-sbo-card__ico" aria-hidden="true">
					<Icon name={ meta.icon } size={ 18 } />
				</span>
				<span className="emcp-sbo-card__title">{ meta.title }</span>
				<span
					className={ `emcp-sbo-card__tier is-${ meta.tier.toLowerCase() }` }
				>
					{ meta.tier }
				</span>
			</span>
			<span className="emcp-sbo-card__desc">{ meta.desc }</span>
			<span className="emcp-sbo-card__meta">
				{ card.available ? (
					<>
						<span className="emcp-sbo-card__stat">
							<span className="emcp-sbo-card__num">
								{ card.active }
							</span>
							<span>{ __( 'active', 'emcp-tools' ) }</span>
						</span>
						<span className="emcp-sbo-card__stat">
							<span className="emcp-sbo-card__num">
								{ card.inactive }
							</span>
							<span>
								{ __( 'inactive / drafts', 'emcp-tools' ) }
							</span>
						</span>
					</>
				) : (
					<span className="emcp-sbo-card__locked">
						<Icon name="lock" size={ 14 } />
						{ __( 'Requires Pro', 'emcp-tools' ) }
					</span>
				) }
				<span className="emcp-sbo-card__arrow" aria-hidden="true">
					<Icon name="arrow-right" size={ 16 } />
				</span>
			</span>
		</a>
	);
}

function ReviewRow( { item } ) {
	const when = relativeTime( item.updatedTs );
	const meta = item.author
		? sprintf(
				/* translators: 1: who drafted it, 2: when. */
				__( 'Drafted by %1$s · %2$s', 'emcp-tools' ),
				item.author,
				when
			)
		: when;
	return (
		<li className="emcp-sbo-review__row">
			<Badge kind="status" value="neutral">
				{ KIND_LABELS()[ item.kind ] }
			</Badge>
			<span className="emcp-sbo-review__main">
				<span className="emcp-sbo-review__title">{ item.title }</span>
				{ meta && (
					<span className="emcp-sbo-review__meta">{ meta }</span>
				) }
			</span>
			<span
				className={ `emcp-sbo-review__flag is-${ item.review.level }` }
			>
				<Icon
					name={ REVIEW_ICONS[ item.review.level ] || 'info' }
					size={ 14 }
				/>
				{ item.review.text }
			</span>
			<Button
				size="sm"
				href={ item.reviewUrl }
				aria-label={ sprintf(
					/* translators: %s: item title. */
					__( 'Review code of %s', 'emcp-tools' ),
					item.title
				) }
			>
				{ __( 'Review code', 'emcp-tools' ) }
			</Button>
		</li>
	);
}

/**
 * Sandbox overview (spec 8.11).
 *
 * @param {Object} props      Props.
 * @param {Object} props.data Boot payload (EMCP_Tools_Admin_Sandbox_Data::overview()).
 */
export function SandboxScreen( { data } ) {
	const ex = data.export;
	return (
		<div className="emcp-sbo">
			<PageHeader
				title={ __( 'Sandbox', 'emcp-tools' ) }
				description={ createInterpolateElement(
					__(
						'Code your AI agent generates lives here, isolated under <code>wp-content/emcp-sandbox</code>, never in your theme, core or other plugins.',
						'emcp-tools'
					),
					{ code: <code /> }
				) }
				actions={
					ex.show && (
						<Button
							variant="primary"
							icon={ ex.locked ? 'lock' : 'package' }
							href={ ex.url }
						>
							{ __( 'Export as plugin', 'emcp-tools' ) }
							{ ex.locked && (
								<span className="eui-visually-hidden">
									{ ' ' }
									{ __( '(Pro)', 'emcp-tools' ) }
								</span>
							) }
						</Button>
					)
				}
			/>
			<div className="emcp-sbo-cards">
				{ data.cards.map( ( card ) => (
					<OverviewCard key={ card.type } card={ card } />
				) ) }
			</div>
			<Card
				title={ __( 'Awaiting your review', 'emcp-tools' ) }
				actions={
					<span className="emcp-sbo-review__note">
						{ __(
							'AI can only create inactive drafts. Activation is your approval step.',
							'emcp-tools'
						) }
					</span>
				}
				padded={ false }
			>
				{ data.review.length ? (
					<ul className="emcp-sbo-review">
						{ data.review.map( ( item ) => (
							<ReviewRow
								key={ `${ item.kind }-${ item.id }` }
								item={ item }
							/>
						) ) }
					</ul>
				) : (
					<p className="emcp-sbo-review__empty">
						{ __( 'Nothing is waiting for you.', 'emcp-tools' ) }
					</p>
				) }
			</Card>
		</div>
	);
}
