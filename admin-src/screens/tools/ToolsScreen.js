import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	FilterChip,
	Icon,
	Menu,
	Meter,
	Notice,
	PageHeader,
	SaveBar,
	SearchInput,
	Select,
	Tabs,
	Toggle,
	errorMessage,
	request,
	useConfirm,
	useQueryState,
	useSettingsForm,
	useToast,
} from '@emcp/ui';
import { ToolCard } from './ToolCard';
import {
	countTurnedOff,
	filterCategories,
	groupCounts,
	key,
	payloadFromDiff,
	resetToDefaults,
	setMany,
	tabCounts,
	valuesFromPayload,
} from './model';

const COLLAPSE_KEY = 'emcp-tools-collapsed';

function readCollapsed() {
	try {
		return JSON.parse(
			window.localStorage.getItem( COLLAPSE_KEY ) || '[]'
		);
	} catch {
		return [];
	}
}

function writeCollapsed( ids ) {
	try {
		window.localStorage.setItem( COLLAPSE_KEY, JSON.stringify( ids ) );
	} catch {}
}

/**
 * Tools screen (spec 8.3).
 *
 * @param {Object} props      Props.
 * @param {Object} props.data Boot payload from EMCP_Tools_Admin_Tools_Data.
 */
export function ToolsScreen( { data: initialData } ) {
	const toast = useToast();
	const confirm = useConfirm();
	const [ data, setData ] = useState( initialData );
	const firstTab = data.tabs[ 0 ]?.id || 'wordpress';
	const [ tab, setTab ] = useQueryState( 'tab', firstTab );
	const [ search, setSearch ] = useQueryState( 'q', '', { debounce: 250 } );
	const [ risk, setRisk ] = useQueryState( 'risk', 'all' );
	const [ status, setStatus ] = useQueryState( 'status', 'any' );
	const [ collapsed, setCollapsed ] = useState( readCollapsed );

	const form = useSettingsForm(
		valuesFromPayload( initialData ),
		async ( diff ) => {
			try {
				const fresh = await request( '/emcp-tools/v1/admin/tools', {
					method: 'POST',
					data: payloadFromDiff( diff ),
				} );
				setData( fresh );
				toast.success(
					__(
						'Tools saved. Reconnect your client to see the change.',
						'emcp-tools'
					)
				);
				if ( fresh.ignored?.length ) {
					toast.info(
						sprintf(
							/* translators: %d: number of tools. */
							_n(
								'%d tool could not be changed.',
								'%d tools could not be changed.',
								fresh.ignored.length,
								'emcp-tools'
							),
							fresh.ignored.length
						)
					);
				}
				return valuesFromPayload( fresh );
			} catch ( e ) {
				toast.error( errorMessage( e ) );
				throw e;
			}
		}
	);
	const { values, setValue, setValues } = form;

	const activeTab = data.tabs.some( ( t ) => t.id === tab ) ? tab : firstTab;
	const filters = { tab: activeTab, search, risk, status };
	const shown = filterCategories( data.categories, filters, values );
	const counts = tabCounts( data.categories, values );
	const allTools = data.categories.flatMap( ( c ) => c.tools );
	const enabledCount = allTools.filter(
		( t ) => t.available && values[ key( t.slug ) ]
	).length;
	const shownTools = shown.flatMap( ( c ) => c.tools );

	const toggleCollapsed = ( id ) => {
		const next = collapsed.includes( id )
			? collapsed.filter( ( c ) => c !== id )
			: [ ...collapsed, id ];
		setCollapsed( next );
		writeCollapsed( next );
	};

	const onReset = async () => {
		const next = resetToDefaults( data.categories, data.defaults, values );
		const off = countTurnedOff( values, next );
		const ok = await confirm( {
			title: __( 'Reset to defaults?', 'emcp-tools' ),
			message: sprintf(
				/* translators: %d: number of tools. */
				_n(
					'This turns off %d enabled tool and turns the defaults back on. Nothing is saved until you choose Save changes.',
					'This turns off %d enabled tools and turns the defaults back on. Nothing is saved until you choose Save changes.',
					off,
					'emcp-tools'
				),
				off
			),
			confirmLabel: __( 'Reset', 'emcp-tools' ),
		} );
		if ( ok ) {
			setValues( next );
		}
	};

	const riskOptions = [
		[ 'all', __( 'All', 'emcp-tools' ) ],
		[ 'read-only', __( 'Read-only', 'emcp-tools' ) ],
		[ 'writes', __( 'Writes', 'emcp-tools' ) ],
		[ 'destructive', __( 'Destructive', 'emcp-tools' ) ],
	];

	return (
		<div className="eui-tools">
			<PageHeader
				title={ __( 'Tools', 'emcp-tools' ) }
				description={ __(
					'Choose which abilities your AI client is allowed to call.',
					'emcp-tools'
				) }
				actions={
					<div className="eui-tools__header-actions">
						<div className="eui-tools__meter">
							<Meter
								value={ enabledCount }
								max={ allTools.length }
								label={ __( 'Enabled', 'emcp-tools' ) }
								valueText={ `${ enabledCount } / ${ allTools.length }` }
							/>
						</div>
						<Menu
							label={ __( 'Bulk Actions', 'emcp-tools' ) }
							icon="chevron-down"
							showLabel
							items={ [
								{
									label: __(
										'Enable all visible',
										'emcp-tools'
									),
									onSelect: () =>
										setValues(
											setMany( values, shownTools, true )
										),
								},
								{
									label: __(
										'Disable all visible',
										'emcp-tools'
									),
									onSelect: () =>
										setValues(
											setMany( values, shownTools, false )
										),
								},
								{
									label: __(
										'Reset to defaults',
										'emcp-tools'
									),
									onSelect: onReset,
								},
							] }
						/>
					</div>
				}
			/>

			<div className="eui-tools__modes">
				<Card>
					<div className="eui-tools__mode">
						<Toggle
							checked={ values.dispatcher }
							onChange={ ( v ) => setValue( 'dispatcher', v ) }
							label={ __( 'Compact tool mode', 'emcp-tools' ) }
							hideLabel
						/>
						<div>
							<p className="eui-tools__mode-title">
								{ __( 'Compact tool mode', 'emcp-tools' ) }{ ' ' }
								<span className="eui-tools__mode-tag">
									{ __(
										'For clients with tool limits',
										'emcp-tools'
									) }
								</span>
							</p>
							<p className="eui-tools__mode-desc">
								{ __(
									'Exposes 3 dispatcher tools instead of every tool. Per-tool toggles still apply.',
									'emcp-tools'
								) }
							</p>
						</div>
					</div>
				</Card>
				{ null !== values.themerPhp && (
					<Card>
						<div className="eui-tools__mode">
							<Toggle
								checked={ !! values.themerPhp }
								onChange={ ( v ) => setValue( 'themerPhp', v ) }
								label={ __(
									'Themer PHP templates',
									'emcp-tools'
								) }
								hideLabel
							/>
							<div>
								<p className="eui-tools__mode-title">
									{ __(
										'Themer PHP templates',
										'emcp-tools'
									) }{ ' ' }
									<span className="eui-tools__mode-tag">
										{ __( 'Advanced', 'emcp-tools' ) }
									</span>
								</p>
								<p className="eui-tools__mode-desc">
									{ __(
										'Lets agents author PHP region templates into a validated sandbox.',
										'emcp-tools'
									) }
								</p>
							</div>
						</div>
					</Card>
				) }
			</div>

			<Card className="eui-tools__toolbar">
				<Tabs
					label={ __( 'Tool categories', 'emcp-tools' ) }
					idPrefix="eui-tools"
					value={ activeTab }
					onChange={ setTab }
					options={ data.tabs.map( ( t ) => ( {
						value: t.id,
						label: t.label,
						count: `${ counts[ t.id ]?.on ?? 0 }/${ counts[ t.id ]?.total ?? 0 }`,
					} ) ) }
				/>
				<div className="eui-tools__filters">
					<SearchInput
						label={ __( 'Search tools', 'emcp-tools' ) }
						value={ search }
						onChange={ setSearch }
						placeholder={ __( 'Search tools…', 'emcp-tools' ) }
						className="eui-tools__search"
					/>
					<div
						className="eui-tools__risk"
						role="group"
						aria-label={ __( 'Risk', 'emcp-tools' ) }
					>
						{ riskOptions.map( ( [ value, label ] ) => (
							<FilterChip
								key={ value }
								label={ label }
								active={ risk === value }
								onClick={ () => setRisk( value ) }
							/>
						) ) }
					</div>
					<Select
						aria-label={ __( 'Status', 'emcp-tools' ) }
						value={ status }
						onChange={ setStatus }
						options={ [
							{
								value: 'any',
								label: __( 'Status: Any', 'emcp-tools' ),
							},
							{
								value: 'enabled',
								label: __( 'Enabled', 'emcp-tools' ),
							},
							{
								value: 'disabled',
								label: __( 'Disabled', 'emcp-tools' ),
							},
							{
								value: 'unavailable',
								label: __( 'Unavailable', 'emcp-tools' ),
							},
						] }
					/>
				</div>
			</Card>

			{ 'elementor' === activeTab && ! data.elementorActive && (
				<Notice
					tone="warning"
					title={ __( 'Elementor is not active', 'emcp-tools' ) }
				>
					{ __(
						'Activate Elementor to use these tools.',
						'emcp-tools'
					) }
				</Notice>
			) }

			<div
				id={ `eui-tools-panel-${ activeTab }` }
				role="tabpanel"
				aria-labelledby={ `eui-tools-tab-${ activeTab }` }
			>
				{ shown.length === 0 && (
					<p className="eui-tools__empty">
						{ __( 'No tools match these filters.', 'emcp-tools' ) }
					</p>
				) }
				{ shown.map( ( category, index ) => {
					const isCollapsed = collapsed.includes( category.id );
					const { on, total } = groupCounts( category, values );
					const heading = `eui-tools-group-${ category.id }`;
					const showGroupLabel =
						category.groupLabel &&
						category.groupLabel !== shown[ index - 1 ]?.groupLabel;
					return (
						<section
							key={ category.id }
							className="eui-tools__group"
							aria-labelledby={ heading }
						>
							{ showGroupLabel && (
								<p className="eui-tools__group-label">
									{ category.groupLabel }
								</p>
							) }
							<div className="eui-tools__group-head">
								<button
									type="button"
									className="eui-tools__group-toggle"
									aria-expanded={ ! isCollapsed }
									onClick={ () =>
										toggleCollapsed( category.id )
									}
								>
									<Icon
										name={
											isCollapsed
												? 'chevron-right'
												: 'chevron-down'
										}
									/>
									<span
										id={ heading }
										className="eui-tools__group-title"
									>
										{ category.label }
									</span>
									<span className="eui-tools__group-count">{ `${ on } / ${ total }` }</span>
								</button>
								{ category.proLocked ? (
									<a
										className="eui-tools__upgrade"
										href={ data.upgradeUrl }
										target="_blank"
										rel="noopener noreferrer"
									>
										<Icon name="lock" />{ ' ' }
										{ __(
											'Requires EMCP Pro, Upgrade',
											'emcp-tools'
										) }
									</a>
								) : (
									<div className="eui-tools__group-actions">
										<Button
											size="sm"
											onClick={ () =>
												setValues(
													setMany(
														values,
														category.tools,
														true
													)
												)
											}
											aria-label={ sprintf(
												/* translators: %s: category. */ __(
													'Enable all in %s',
													'emcp-tools'
												),
												category.label
											) }
										>
											{ __( 'Enable', 'emcp-tools' ) }
										</Button>
										<Button
											size="sm"
											onClick={ () =>
												setValues(
													setMany(
														values,
														category.tools,
														false
													)
												)
											}
											aria-label={ sprintf(
												/* translators: %s: category. */ __(
													'Disable all in %s',
													'emcp-tools'
												),
												category.label
											) }
										>
											{ __( 'Disable', 'emcp-tools' ) }
										</Button>
									</div>
								) }
							</div>
							{ category.notice && (
								<Notice tone={ category.notice.type || 'info' }>
									{ category.notice.message }
								</Notice>
							) }
							{ category.note && (
								<p className="eui-tools__note">
									{ category.note }
								</p>
							) }
							{ ! isCollapsed && (
								<div className="eui-tools__grid">
									{ category.tools.map( ( tool ) => (
										<ToolCard
											key={ tool.slug }
											tool={ tool }
											on={ !! values[ key( tool.slug ) ] }
											onChange={ ( v ) =>
												setValue( key( tool.slug ), v )
											}
										/>
									) ) }
								</div>
							) }
						</section>
					);
				} ) }
			</div>

			<SaveBar
				count={ form.count }
				hint={ __(
					'Reconnect your client after saving',
					'emcp-tools'
				) }
				onDiscard={ form.discard }
				onSave={ form.submit }
				saving={ form.saving }
				saveLabel={ __( 'Save changes', 'emcp-tools' ) }
			/>
		</div>
	);
}
