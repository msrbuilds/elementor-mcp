import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Checkbox,
	Drawer,
	Field,
	Skeleton,
	TextInput,
	Toggle,
	errorMessage,
	isEqual,
	request,
	useToast,
} from '@emcp/ui';

/**
 * Settings drawer for a module with settings_schema() (spec 8.4).
 *
 * @param {Object}     props         Props.
 * @param {Object}     props.module  Module.
 * @param {() => void} props.onClose Close handler.
 */
export function SettingsDrawer( { module, onClose } ) {
	const toast = useToast();
	const base = `/emcp-tools/v1/admin/modules/${ module.id }/settings`;
	const [ form, setForm ] = useState( null );
	const [ values, setValues ] = useState( {} );
	const [ saving, setSaving ] = useState( false );
	const [ progress, setProgress ] = useState( null );

	useEffect( () => {
		request( base )
			.then( ( res ) => {
				setForm( res );
				setValues( res.values );
			} )
			.catch( ( e ) => toast.error( errorMessage( e ) ) );
	}, [ base ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const changed = form
		? Object.fromEntries(
				Object.entries( values ).filter(
					( [ k, v ] ) => ! isEqual( v, form.values[ k ] )
				)
			)
		: {};

	const save = async () => {
		setSaving( true );
		try {
			const res = await request( base, {
				method: 'POST',
				data: { values: changed },
			} );
			setForm( ( f ) => ( { ...f, values: res.values } ) );
			setValues( res.values );
			toast.success( __( 'Settings saved.', 'emcp-tools' ) );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			setSaving( false );
		}
	};

	const runBulk = async () => {
		setProgress( { percent: 0, done: false } );
		try {
			let step;
			do {
				step = await request(
					'/emcp-tools/v1/admin/modules/image-optimization/bulk',
					{ method: 'POST', data: { batch: 10 } }
				);
				setProgress( step );
			} while ( ! step.done );
			toast.success( __( 'Library optimized.', 'emcp-tools' ) );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
			setProgress( null );
		}
	};

	const restore = async () => {
		try {
			const res = await request(
				'/emcp-tools/v1/admin/modules/image-optimization/restore',
				{ method: 'POST' }
			);
			toast.success(
				sprintf(
					/* translators: %d: number of files. */
					__( 'Restored %d files.', 'emcp-tools' ),
					res.restored
				)
			);
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		}
	};

	const field = ( f ) => {
		if ( f.parent && ! values[ f.parent ] ) {
			return null;
		}
		const set = ( v ) =>
			setValues( ( cur ) => ( { ...cur, [ f.key ]: v } ) );
		if ( 'toggle' === f.type ) {
			return (
				<Toggle
					key={ f.key }
					checked={ !! values[ f.key ] }
					onChange={ set }
					label={ f.label }
				/>
			);
		}
		if ( 'checkboxes' === f.type ) {
			const list = values[ f.key ] || [];
			return (
				<fieldset key={ f.key } className="eui-mod-drawer__choices">
					<legend>{ f.label }</legend>
					{ f.choices.map( ( c ) => (
						<Checkbox
							key={ c.value }
							label={ c.label }
							checked={ list.includes( c.value ) }
							onChange={ ( on ) =>
								set(
									on
										? [ ...list, c.value ]
										: list.filter( ( v ) => v !== c.value )
								)
							}
						/>
					) ) }
				</fieldset>
			);
		}
		return (
			<Field key={ f.key } label={ f.label } help={ f.help }>
				{ ( a11y ) => (
					<TextInput
						{ ...a11y }
						type={ 'range' === f.type ? 'range' : 'number' }
						min={ f.min }
						max={ f.max }
						value={ values[ f.key ] ?? 0 }
						onChange={ ( e ) =>
							set( parseInt( e.target.value, 10 ) || 0 )
						}
					/>
				) }
			</Field>
		);
	};

	return (
		<Drawer
			open
			title={ module.title }
			onClose={ onClose }
			footer={
				<Button
					variant="primary"
					onClick={ save }
					loading={ saving }
					disabled={ ! Object.keys( changed ).length }
				>
					{ __( 'Save settings', 'emcp-tools' ) }
				</Button>
			}
		>
			{ ! form && (
				<Skeleton
					lines={ 4 }
					label={ __( 'Loading settings', 'emcp-tools' ) }
				/>
			) }
			{ form && (
				<div className="eui-mod-drawer">
					{ form.fields.map( field ) }
				</div>
			) }
			{ form && 'image-optimization' === module.id && (
				<div className="eui-mod-drawer__bulk">
					<p>
						{ __(
							'Optimize images already in the library, or put the originals back.',
							'emcp-tools'
						) }
					</p>
					<div className="eui-mod-drawer__bulk-actions">
						<Button
							onClick={ runBulk }
							loading={ !! progress && ! progress.done }
						>
							{ __( 'Optimize existing library', 'emcp-tools' ) }
						</Button>
						<Button variant="ghost" onClick={ restore }>
							{ __( 'Restore originals', 'emcp-tools' ) }
						</Button>
					</div>
					{ progress && (
						<p
							className="eui-mod-drawer__progress"
							role="status"
						>{ `${ progress.percent }%` }</p>
					) }
				</div>
			) }
		</Drawer>
	);
}
