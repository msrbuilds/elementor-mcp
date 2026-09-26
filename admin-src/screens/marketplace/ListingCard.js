import { __, sprintf } from '@wordpress/i18n';
import { Badge, Button, Icon } from '@emcp/ui';

/**
 * Install control shared by the grid card and the list row.
 *
 * @param {Object}              props           Props.
 * @param {Object}              props.item      Listing.
 * @param {boolean}             props.busy      This install is running.
 * @param {boolean}             props.disabled  Another install is running.
 * @param {(i: Object) => void} props.onInstall Install.
 */
export function InstallControl( { item, busy, disabled, onInstall } ) {
	if ( item.installed ) {
		return (
			<a className="eui-mk-installed" href={ item.installed.reviewUrl }>
				<Icon name="check" />
				<span aria-hidden="true">
					{ __( 'Installed', 'emcp-tools' ) }
				</span>
				<span className="eui-visually-hidden">
					{ sprintf(
						/* translators: %s: item name. */
						__( 'Installed, review %s', 'emcp-tools' ),
						item.title
					) }
				</span>
			</a>
		);
	}
	return (
		<Button
			size="sm"
			variant="primary"
			icon="download"
			loading={ busy }
			disabled={ disabled }
			aria-label={ sprintf(
				/* translators: %s: item name. */
				__( 'Install %s', 'emcp-tools' ),
				item.title
			) }
			onClick={ () => onInstall( item ) }
		>
			{ __( 'Install', 'emcp-tools' ) }
		</Button>
	);
}

/**
 * One Marketplace card (spec 8.18).
 *
 * @param {Object}              props           Props.
 * @param {Object}              props.item      Listing.
 * @param {boolean}             props.busy      This install is running.
 * @param {boolean}             props.disabled  Another install is running.
 * @param {(i: Object) => void} props.onInstall Install.
 */
export function ListingCard( props ) {
	const { item } = props;
	return (
		<article className="eui-mk-card">
			<div className="eui-mk-card__media">
				{ item.thumbnail ? (
					<img src={ item.thumbnail } alt="" loading="lazy" />
				) : (
					<span
						className="eui-mk-card__placeholder"
						aria-hidden="true"
					/>
				) }
				{ 'pro' === item.access && (
					<span className="eui-mk-card__pro">
						<Badge kind="tier" value="pro" />
					</span>
				) }
			</div>
			<div className="eui-mk-card__body">
				<p className="eui-mk-card__meta">
					{ [ item.kindLabel, item.category ]
						.filter( Boolean )
						.join( ' · ' ) }
				</p>
				<h3 className="eui-mk-card__title">{ item.title }</h3>
				<p className="eui-mk-card__summary">{ item.summary }</p>
				<div className="eui-mk-card__foot">
					{ item.author && (
						<span className="eui-mk-card__author">
							{ item.author.avatar ? (
								<img src={ item.author.avatar } alt="" />
							) : (
								<span
									className="eui-mk-card__av"
									aria-hidden="true"
								>
									{ item.author.name.charAt( 0 ) }
								</span>
							) }
							<span className="eui-mk-card__name">
								{ item.author.name }
							</span>
							{ item.author.verified && (
								<span className="eui-mk-card__verified">
									<Icon name="circle-check" />
									<span className="eui-visually-hidden">
										{ __(
											'Verified author',
											'emcp-tools'
										) }
									</span>
								</span>
							) }
						</span>
					) }
					<InstallControl { ...props } />
				</div>
			</div>
		</article>
	);
}
