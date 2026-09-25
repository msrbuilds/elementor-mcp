import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Card,
	Icon,
	Notice,
	PageHeader,
	RadioCard,
	RadioCardGroup,
	SaveBar,
	Toggle,
	errorMessage,
	request,
	useQueryState,
	useSettingsForm,
	useToast,
} from '@emcp/ui';

const packKey = ( id ) => 'pack:' + id;

function valuesFrom( data ) {
	const values = { builder: data.selected };
	data.packs.forEach( ( p ) => {
		values[ packKey( p.id ) ] = !! p.enabled;
	} );
	return values;
}

function payloadFrom( diff ) {
	const out = { packs_enable: [], packs_disable: [] };
	Object.entries( diff ).forEach( ( [ k, v ] ) => {
		if ( 'builder' === k ) {
			out.builder = v;
		} else if ( k.startsWith( 'pack:' ) ) {
			( v ? out.packs_enable : out.packs_disable ).push( k.slice( 5 ) );
		}
	} );
	return out;
}

/**
 * Page Builders screen (spec 8.5).
 *
 * @param {Object} props      Props.
 * @param {Object} props.data Boot payload from EMCP_Tools_Admin_Builders_Data.
 */
export function BuildersScreen( { data: initialData } ) {
	const toast = useToast();
	const [ data, setData ] = useState( initialData );
	const [ detectedOnly, setDetectedOnly ] = useQueryState( 'detected', '0' );
	const form = useSettingsForm( valuesFrom( initialData ), async ( diff ) => {
		try {
			const fresh = await request( '/emcp-tools/v1/admin/builders', {
				method: 'POST',
				data: payloadFrom( diff ),
			} );
			setData( fresh );
			toast.success(
				__(
					'Page builder settings saved. Reconnect your client to see the change.',
					'emcp-tools'
				)
			);
			return valuesFrom( fresh );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
			throw e;
		}
	} );
	const { values, setValue } = form;
	const status = ( b ) => {
		if ( ! b.available ) {
			return (
				<Badge kind="status" value="neutral">
					{ __( 'Not detected', 'emcp-tools' ) }
				</Badge>
			);
		}
		return b.id === data.selected ? (
			<Badge kind="status" value="success">
				{ __( 'Active', 'emcp-tools' ) }
			</Badge>
		) : (
			<Badge kind="status" value="success">
				{ __( 'Detected', 'emcp-tools' ) }
			</Badge>
		);
	};
	const saved = data.builders.find( ( b ) => b.id === data.selected );
	const stale = saved && ! saved.available;
	const builders = data.builders.filter(
		( b ) => '1' !== detectedOnly || b.available
	);

	return (
		<div className="eui-builders">
			<PageHeader
				title={ __( 'Page Builders', 'emcp-tools' ) }
				description={ __(
					'Choose which builder your AI edits with. Switching keeps your pages and per-tool settings.',
					'emcp-tools'
				) }
				actions={
					<Toggle
						checked={ '1' === detectedOnly }
						onChange={ ( v ) => setDetectedOnly( v ? '1' : '0' ) }
						label={ __( 'Show detected only', 'emcp-tools' ) }
					/>
				}
			/>
			{ stale && (
				<Notice tone="warning">
					{ sprintf(
						/* translators: %s: page builder name. */
						__(
							'%s is selected but is not active on this site, so only the Gutenberg tools are on. Activate it again or pick another builder.',
							'emcp-tools'
						),
						saved.label
					) }
				</Notice>
			) }
			<Card>
				<div className="eui-builders__banner">
					<Icon name="blocks" />
					<div>
						<p className="eui-builders__banner-title">
							{ __( 'Gutenberg is always on', 'emcp-tools' ) }
						</p>
						<p className="eui-builders__banner-text">
							{ __(
								'Core block tools plus any block plugins you enable below work alongside the standalone builder you pick.',
								'emcp-tools'
							) }
						</p>
					</div>
					<Badge kind="status" value="success">
						{ __( 'Active', 'emcp-tools' ) }
					</Badge>
				</div>
			</Card>

			<h2 className="eui-builders__section-title">
				{ __( '1. Standalone builder', 'emcp-tools' ) }
				<span className="eui-builders__section-hint">
					{ __(
						'Pick one. Its tools tab appears in Tools.',
						'emcp-tools'
					) }
				</span>
			</h2>
			<RadioCardGroup
				legend={ __( '1. Standalone builder', 'emcp-tools' ) }
				name="emcp-builder"
				value={ values.builder }
				onChange={ ( v ) => setValue( 'builder', v ) }
				columns={ 3 }
			>
				<RadioCard
					value=""
					title={ __( 'Gutenberg only', 'emcp-tools' ) }
					description={ __(
						'Use the block editor and the block plugins below. No standalone builder.',
						'emcp-tools'
					) }
					tag={
						'' === data.selected ? (
							<Badge kind="status" value="success">
								{ __( 'Active', 'emcp-tools' ) }
							</Badge>
						) : null
					}
				/>
				{ builders.map( ( b ) => (
					<RadioCard
						key={ b.id }
						value={ b.id }
						title={ b.label }
						description={ b.description }
						tag={ status( b ) }
						disabled={ ! b.available }
						requirement={ b.available ? undefined : b.requirement }
					/>
				) ) }
			</RadioCardGroup>

			<section
				className="eui-builders__packs"
				aria-labelledby="eui-builders-packs"
			>
				<h2
					id="eui-builders-packs"
					className="eui-builders__section-title"
				>
					{ __( '2. Gutenberg block plugins', 'emcp-tools' ) }
					<span className="eui-builders__section-hint">
						{ __(
							'Enable any combination. Each gets its own tools tab.',
							'emcp-tools'
						) }
					</span>
				</h2>
				<Card padded={ false }>
					<ul className="eui-builders__pack-list">
						{ data.packs.map( ( p ) => (
							<li key={ p.id } className="eui-builders__pack">
								<Toggle
									checked={
										!! values[ packKey( p.id ) ] &&
										p.available
									}
									onChange={ ( v ) =>
										setValue( packKey( p.id ), v )
									}
									label={ p.label }
									disabled={ ! p.available }
								/>
								<span className="eui-builders__pack-desc">
									{ __(
										'Native blocks, schemas and page editing alongside Gutenberg.',
										'emcp-tools'
									) }
								</span>
								{ ! p.available && (
									<span className="eui-builders__pack-req">
										<Icon name="lock" /> { p.requirement }
									</span>
								) }
							</li>
						) ) }
					</ul>
				</Card>
			</section>

			<p className="eui-builders__footnote">
				<Icon name="info" />{ ' ' }
				{ __(
					'These switches control EMCP integrations only; your plugins and themes stay active. Reconnect your AI client after saving.',
					'emcp-tools'
				) }
			</p>

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
