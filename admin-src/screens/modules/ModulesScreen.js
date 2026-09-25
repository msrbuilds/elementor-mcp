import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Card,
	Icon,
	PageHeader,
	SaveBar,
	SearchInput,
	Segmented,
	Toggle,
	errorMessage,
	request,
	useQueryState,
	useSettingsForm,
	useToast,
} from '@emcp/ui';
import { SettingsDrawer } from './SettingsDrawer';

const valuesFrom = ( modules ) =>
	Object.fromEntries( modules.map( ( m ) => [ m.id, !! m.active ] ) );

/**
 * Modules screen (spec 8.4).
 *
 * @param {Object}     props        Props.
 * @param {Object}     props.data   Boot payload from EMCP_Tools_Admin_Modules_Data.
 * @param {() => void} props.reload Page reload (injectable for tests).
 */
export function ModulesScreen( {
	data,
	reload = () => window.location.reload(),
} ) {
	const toast = useToast();
	const [ filter, setFilter ] = useQueryState( 'show', 'all' );
	const [ search, setSearch ] = useQueryState( 'q', '', { debounce: 250 } );
	const [ drawer, setDrawer ] = useState( null );
	const [ saved, setSaved ] = useState( false );
	const form = useSettingsForm(
		valuesFrom( data.modules ),
		async ( diff ) => {
			const payload = { activate: [], deactivate: [] };
			Object.entries( diff ).forEach( ( [ id, on ] ) =>
				( on ? payload.activate : payload.deactivate ).push( id )
			);
			try {
				const fresh = await request( '/emcp-tools/v1/admin/modules', {
					method: 'POST',
					data: payload,
				} );
				toast.success( __( 'Modules saved.', 'emcp-tools' ) );
				setSaved( true );
				return valuesFrom( fresh.modules );
			} catch ( e ) {
				toast.error( errorMessage( e ) );
				throw e;
			}
		}
	);
	const { values, setValue, baseline } = form;

	// Modules gate sidebar entries, so reload after a save, but only once the
	// form is clean: reloading while dirty would trip the leave-page guard.
	useEffect( () => {
		if ( saved && ! form.dirty ) {
			reload();
		}
	}, [ saved, form.dirty ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const byId = Object.fromEntries( data.modules.map( ( m ) => [ m.id, m ] ) );
	const changedTitle = byId[ form.changedKeys[ 0 ] ]?.title;
	let hint = __( 'Modules change after saving', 'emcp-tools' );
	if ( 1 === form.count && values[ form.changedKeys[ 0 ] ] ) {
		/* translators: %s: module name. */
		hint = sprintf( __( '%s turned on', 'emcp-tools' ), changedTitle );
	} else if ( 1 === form.count ) {
		/* translators: %s: module name. */
		hint = sprintf( __( '%s turned off', 'emcp-tools' ), changedTitle );
	}

	const onCount = data.modules.filter( ( m ) => values[ m.id ] ).length;
	const q = search.trim().toLowerCase();
	const visible = data.modules.filter( ( m ) => {
		if ( 'enabled' === filter && ! values[ m.id ] ) {
			return false;
		}
		if ( 'disabled' === filter && values[ m.id ] ) {
			return false;
		}
		return (
			! q || `${ m.title } ${ m.description }`.toLowerCase().includes( q )
		);
	} );

	return (
		<div className="eui-modules">
			<PageHeader
				title={ __( 'Modules', 'emcp-tools' ) }
				description={ __(
					'Turn large features on or off. Each module is self-contained; some are free and some are Pro.',
					'emcp-tools'
				) }
				actions={
					<div className="eui-modules__filters">
						<Segmented
							label={ __( 'Show', 'emcp-tools' ) }
							value={ filter }
							onChange={ setFilter }
							options={ [
								{
									value: 'all',
									label: __( 'All', 'emcp-tools' ),
									count: data.modules.length,
								},
								{
									value: 'enabled',
									label: __( 'Enabled', 'emcp-tools' ),
									count: onCount,
								},
								{
									value: 'disabled',
									label: __( 'Disabled', 'emcp-tools' ),
									count: data.modules.length - onCount,
								},
							] }
						/>
						<SearchInput
							label={ __( 'Search modules', 'emcp-tools' ) }
							value={ search }
							onChange={ setSearch }
							placeholder={ __(
								'Search modules…',
								'emcp-tools'
							) }
						/>
					</div>
				}
			/>
			{ data.groups.map( ( g ) => {
				const shown = visible.filter( ( m ) => m.group === g.id );
				if ( ! shown.length ) {
					return null;
				}
				const all = data.modules.filter( ( m ) => m.group === g.id );
				const heading = `eui-modules-${ g.id }`;
				return (
					<section
						key={ g.id }
						className="eui-modules__group"
						aria-labelledby={ heading }
					>
						<h2 id={ heading } className="eui-modules__group-title">
							{ g.label }
							<span className="eui-modules__group-count">
								{ sprintf(
									/* translators: 1: on, 2: total. */ __(
										'%1$d / %2$d on',
										'emcp-tools'
									),
									all.filter( ( m ) => values[ m.id ] )
										.length,
									all.length
								) }
							</span>
						</h2>
						<div className="eui-modules__grid">
							{ shown.map( ( m ) => (
								<Card
									key={ m.id }
									className={
										values[ m.id ]
											? 'eui-module'
											: 'eui-module is-off'
									}
								>
									<div className="eui-module__head">
										<span
											className="eui-module__icon"
											aria-hidden="true"
										>
											<Icon name={ m.icon } />
										</span>
										<span className="eui-module__title">
											{ m.title }
										</span>
										<Badge kind="tier" value={ m.tier } />
										<span title={ m.reason || undefined }>
											<Toggle
												checked={ !! values[ m.id ] }
												onChange={ ( v ) =>
													setValue( m.id, v )
												}
												label={ m.title }
												hideLabel
												disabled={ ! m.available }
											/>
										</span>
									</div>
									<p className="eui-module__desc">
										{ m.description }
									</p>
									{ ! m.available && m.reason && (
										<p className="eui-module__reason">
											<Icon name="lock" /> { m.reason }
										</p>
									) }
									{ baseline[ m.id ] && m.settingsUrl && (
										<a
											className="eui-module__link"
											href={ m.settingsUrl }
											aria-label={ sprintf(
												/* translators: %s: module. */ __(
													'Configure %s',
													'emcp-tools'
												),
												m.title
											) }
										>
											{ __( 'Configure', 'emcp-tools' ) }{ ' ' }
											<Icon name="arrow-right" />
										</a>
									) }
									{ baseline[ m.id ] && m.hasSettings && (
										<button
											type="button"
											className="eui-module__link"
											onClick={ () => setDrawer( m ) }
											aria-label={ sprintf(
												/* translators: %s: module. */ __(
													'Settings for %s',
													'emcp-tools'
												),
												m.title
											) }
										>
											{ __( 'Settings', 'emcp-tools' ) }{ ' ' }
											<Icon name="arrow-right" />
										</button>
									) }
								</Card>
							) ) }
						</div>
					</section>
				);
			} ) }
			{ drawer && (
				<SettingsDrawer
					module={ drawer }
					onClose={ () => setDrawer( null ) }
				/>
			) }
			<SaveBar
				count={ form.count }
				hint={ hint }
				onDiscard={ form.discard }
				onSave={ form.submit }
				saving={ form.saving }
				saveLabel={ __( 'Save modules', 'emcp-tools' ) }
			/>
		</div>
	);
}
