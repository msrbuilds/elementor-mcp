import { __, sprintf } from '@wordpress/i18n';
import { Badge, Button, IconButton } from '@emcp/ui';

const WEIGHTS = [ 50, 25, 15, 10 ];
const SYSTEM = 'system-ui, -apple-system, "Segoe UI", sans-serif';

/**
 * One brand kit card (spec 8.17).
 *
 * @param {Object}              props           Props.
 * @param {Object}              props.kit       Kit item.
 * @param {boolean}             props.disabled  Applying is unavailable.
 * @param {boolean}             props.busy      This kit is being applied.
 * @param {(k: Object) => void} props.onApply   Apply handler.
 * @param {(k: Object) => void} props.onPreview Preview handler.
 */
export function KitCard( { kit, disabled, busy, onApply, onPreview } ) {
	return (
		<article className="eui-kit">
			<div className="eui-kit__swatches" aria-hidden="true">
				{ kit.swatches.map( ( c, i ) => (
					<span
						key={ i }
						style={ { flexGrow: WEIGHTS[ i ], backgroundColor: c } }
					/>
				) ) }
			</div>
			<div className="eui-kit__specimen">
				{ /* The kit's own font when the browser has it; no web font is fetched in wp-admin (spec 6.2). */ }
				<span
					className="eui-kit__aa"
					style={ {
						color: kit.headingColor,
						fontFamily: `"${ kit.headingFont }", ${ SYSTEM }`,
					} }
				>
					Aa
				</span>
				<span className="eui-kit__fonts">
					<span>{ kit.headingFont + ' · ' + kit.bodyFont }</span>
					<span
						className="eui-kit__accent"
						style={ { color: kit.accentColor } }
					>
						{ __( 'Accent', 'emcp-tools' ) }
					</span>
				</span>
			</div>
			<div className="eui-kit__body">
				<p className="eui-kit__category">{ kit.categoryLabel }</p>
				<h3 className="eui-kit__title">{ kit.title }</h3>
				<p className="eui-kit__desc">{ kit.description }</p>
				<div className="eui-kit__actions">
					{ kit.applied ? (
						<Badge kind="status" value="success" dot>
							{ __( 'Applied', 'emcp-tools' ) }
						</Badge>
					) : (
						<Button
							size="sm"
							onClick={ () => onApply( kit ) }
							loading={ busy }
							disabled={ disabled || busy }
						>
							{ __( 'Apply kit', 'emcp-tools' ) }
						</Button>
					) }
					<IconButton
						icon="eye"
						label={ sprintf(
							/* translators: %s: kit name. */ __(
								'Preview %s',
								'emcp-tools'
							),
							kit.title
						) }
						onClick={ () => onPreview( kit ) }
					/>
				</div>
			</div>
		</article>
	);
}
