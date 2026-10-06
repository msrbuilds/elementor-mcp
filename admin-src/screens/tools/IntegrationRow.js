import { __, _n, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Badge, Icon, Notice, Toggle, cx } from '@emcp/ui';
import { key, slotsOf } from './model';

const RISK = {
	'read-only': () => __( 'Read-only', 'emcp-tools' ),
	writes: () => __( 'Writes', 'emcp-tools' ),
	destructive: () => __( 'Destructive', 'emcp-tools' ),
};

/**
 * One plugin or theme integration as a single row: its Read and Write switches
 * side by side, each with its operations count; expanding shows each tool's
 * description, slug and operations, and the integration's note.
 *
 * @param {Object}                              props            Props.
 * @param {Object}                              props.category   Category from the payload.
 * @param {Object}                              props.values     Form values.
 * @param {(slug: string, on: boolean) => void} props.onToggle   Switch handler.
 * @param {boolean}                             props.open       Expanded.
 * @param {(open: boolean) => void}             props.onOpen     Expand handler.
 * @param {string}                              props.upgradeUrl Upgrade link for a Pro-locked row.
 */
export function IntegrationRow( {
	category,
	values,
	onToggle,
	open,
	onOpen,
	upgradeUrl,
} ) {
	const [ opsOpen, setOpsOpen ] = useState( [] );
	const slots = slotsOf( category );
	const requirements = [
		...new Set(
			category.tools
				.filter( ( t ) => ! t.available && t.requirement )
				.map( ( t ) => t.requirement )
		),
	];
	const detailsId = `eui-tools-int-${ category.id }`;

	const showOps = ( slug ) => {
		onOpen( true );
		setOpsOpen( ( s ) =>
			s.includes( slug )
				? s.filter( ( x ) => x !== slug )
				: [ ...s, slug ]
		);
	};

	const slot = ( tool, word ) => {
		if ( ! tool ) {
			return <div className="eui-int__slot" />;
		}
		const on = !! values[ key( tool.slug ) ];
		return (
			<div
				className={ cx(
					'eui-int__slot',
					! tool.available && 'is-unavailable'
				) }
			>
				<Toggle
					checked={ on && tool.available }
					onChange={ ( v ) => onToggle( tool.slug, v ) }
					label={
						<>
							<span className="eui-visually-hidden">
								{ category.label }{ ' ' }
							</span>
							{ word }
						</>
					}
					disabled={ ! tool.available }
				/>
				{ tool.operations.length > 0 && (
					<button
						type="button"
						className="eui-int__ops"
						aria-expanded={ open && opsOpen.includes( tool.slug ) }
						aria-controls={ detailsId }
						aria-label={ sprintf(
							/* translators: 1: integration, 2: Read or Write, 3: number of operations. */
							_n(
								'%1$s %2$s: %3$d operation',
								'%1$s %2$s: %3$d operations',
								tool.operations.length,
								'emcp-tools'
							),
							category.label,
							word,
							tool.operations.length
						) }
						onClick={ () => showOps( tool.slug ) }
					>
						{ sprintf(
							/* translators: %d: number of operations. */
							_n(
								'%d operation',
								'%d operations',
								tool.operations.length,
								'emcp-tools'
							),
							tool.operations.length
						) }
					</button>
				) }
			</div>
		);
	};

	return (
		<div className={ cx( 'eui-int', open && 'is-open' ) }>
			<div className="eui-int__row">
				<div className="eui-int__lead">
					<button
						type="button"
						className="eui-int__toggle"
						aria-expanded={ open }
						aria-controls={ detailsId }
						onClick={ () => onOpen( ! open ) }
					>
						<Icon
							name={ open ? 'chevron-down' : 'chevron-right' }
						/>
						<span className="eui-int__name">
							{ category.label }
						</span>
					</button>
					{ requirements.map( ( r ) => (
						<Badge key={ r } kind="status" value="neutral">
							{ r }
						</Badge>
					) ) }
				</div>
				{ category.proLocked ? (
					<a
						className="eui-tools__upgrade eui-int__upgrade"
						href={ upgradeUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						<Icon name="lock" />{ ' ' }
						{ __( 'Requires EMCP Pro, Upgrade', 'emcp-tools' ) }
					</a>
				) : (
					<div className="eui-int__slots">
						{ slot( slots.read, __( 'Read', 'emcp-tools' ) ) }
						{ slot( slots.write, __( 'Write', 'emcp-tools' ) ) }
					</div>
				) }
			</div>
			{ open && (
				<div id={ detailsId } className="eui-int__details">
					{ category.notice && (
						<Notice tone={ category.notice.type || 'info' }>
							{ category.notice.message }
						</Notice>
					) }
					<div className="eui-int__cards">
						{ category.tools.map( ( tool ) => (
							<div
								key={ tool.slug }
								className="eui-int__card"
								title={ tool.requirementNote || undefined }
							>
								<div className="eui-tool__head">
									<span className="eui-tool__name">
										{ tool.name }
									</span>
									{ tool.available ? (
										<Badge kind="risk" value={ tool.risk }>
											{ RISK[ tool.risk ]() }
										</Badge>
									) : (
										<Badge kind="status" value="neutral">
											{ tool.requirement ||
												__(
													'Unavailable',
													'emcp-tools'
												) }
										</Badge>
									) }
								</div>
								<p className="eui-tool__desc">
									{ tool.description }
								</p>
								<code className="eui-tool__slug">
									{ tool.slug }
								</code>
								{ opsOpen.includes( tool.slug ) &&
									tool.operations.length > 0 && (
										<ul className="eui-tool__ops-list">
											{ tool.operations.map( ( op ) => (
												<li key={ op }>
													<code>{ op }</code>
												</li>
											) ) }
										</ul>
									) }
							</div>
						) ) }
					</div>
					{ category.note && (
						<p className="eui-int__note">{ category.note }</p>
					) }
				</div>
			) }
		</div>
	);
}
