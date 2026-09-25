import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Badge, Toggle, cx } from '@emcp/ui';

const RISK = {
	'read-only': () => __( 'Read-only', 'emcp-tools' ),
	writes: () => __( 'Writes', 'emcp-tools' ),
	destructive: () => __( 'Destructive', 'emcp-tools' ),
};

/**
 * One tool: toggle, name, risk badge, description, slug, operations.
 *
 * @param {Object}                props          Props.
 * @param {Object}                props.tool     Tool.
 * @param {boolean}               props.on       Enabled in the form.
 * @param {(on: boolean) => void} props.onChange Toggle handler.
 */
export function ToolCard( { tool, on, onChange } ) {
	const [ open, setOpen ] = useState( false );
	return (
		<div
			className={ cx(
				'eui-tool',
				! on && 'is-off',
				! tool.available && 'is-unavailable'
			) }
			title={ tool.requirementNote || undefined }
		>
			<Toggle
				checked={ on && tool.available }
				onChange={ onChange }
				label={ tool.name }
				hideLabel
				size="sm"
				disabled={ ! tool.available }
			/>
			<div className="eui-tool__body">
				<div className="eui-tool__head">
					<span className="eui-tool__name">{ tool.name }</span>
					{ tool.available ? (
						<Badge kind="risk" value={ tool.risk }>
							{ RISK[ tool.risk ]() }
						</Badge>
					) : (
						<Badge kind="status" value="neutral">
							{ tool.requirement ||
								__( 'Unavailable', 'emcp-tools' ) }
						</Badge>
					) }
				</div>
				<p className="eui-tool__desc">{ tool.description }</p>
				<code className="eui-tool__slug">{ tool.slug }</code>
				{ tool.operations.length > 0 && (
					<div className="eui-tool__ops">
						<button
							type="button"
							className="eui-tool__ops-toggle"
							aria-expanded={ open }
							onClick={ () => setOpen( ! open ) }
						>
							{ sprintf(
								/* translators: %d: number of operations. */
								__( '%d operations', 'emcp-tools' ),
								tool.operations.length
							) }
						</button>
						{ open && (
							<ul className="eui-tool__ops-list">
								{ tool.operations.map( ( op ) => (
									<li key={ op }>
										<code>{ op }</code>
									</li>
								) ) }
							</ul>
						) }
					</div>
				) }
			</div>
		</div>
	);
}
