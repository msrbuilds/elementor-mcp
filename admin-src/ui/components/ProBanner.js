import { __ } from '@wordpress/i18n';
import { Icon } from './Icon';
import './ProBanner.css';

/**
 * Free builds: a prominent call to action for a Pro library (Prompts, Brand
 * Kits). A region named by its heading, so each banner needs its own `id`.
 *
 * @param {Object}   props
 * @param {string}   props.id     Unique id, used for the heading.
 * @param {string}   props.title  Headline.
 * @param {string}   props.text   Supporting line.
 * @param {string[]} props.points Short highlights, shown with a check.
 * @param {string}   props.url    Upgrade URL, opened in a new tab.
 */
export function ProBanner( { id, title, text, points = [], url } ) {
	const titleId = `eui-pro-banner-${ id }`;
	return (
		<section className="eui-pro-banner" aria-labelledby={ titleId }>
			<div className="eui-pro-banner__body">
				<span className="eui-pro-banner__badge">
					{ __( 'Pro', 'emcp-tools' ) }
				</span>
				<h2 id={ titleId } className="eui-pro-banner__title">
					{ title }
				</h2>
				{ text && <p className="eui-pro-banner__text">{ text }</p> }
				{ points.length > 0 && (
					<ul className="eui-pro-banner__points">
						{ points.map( ( p ) => (
							<li key={ p }>
								<Icon name="check" size={ 16 } />
								{ p }
							</li>
						) ) }
					</ul>
				) }
			</div>
			<a
				className="eui-pro-banner__cta"
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
