import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Checkbox,
	EmptyState,
	Field,
	Notice,
	Skeleton,
	Table,
	TextInput,
	errorMessage,
	request,
	useConfirm,
	useToast,
} from '@emcp/ui';

const API = '/emcp-tools/v1/admin/history/fs-backups';

/**
 * Bytes as KB, MB or GB.
 *
 * @param {number} n Bytes.
 * @return {string} Size.
 */
export function formatBytes( n ) {
	if ( n < 1024 ) {
		return sprintf(
			/* translators: %d: bytes. */
			__( '%d B', 'emcp-tools' ),
			n
		);
	}
	const units = [ 'KB', 'MB', 'GB' ];
	let v = n / 1024;
	let i = 0;
	while ( v >= 1024 && i < units.length - 1 ) {
		v /= 1024;
		i++;
	}
	return `${ v.toFixed( v < 10 ? 1 : 0 ) } ${ units[ i ] }`;
}

/**
 * Filesystem tool backups (#196): the copies write-file, edit-file and
 * delete-file keep before each change, with select-and-delete and retention.
 */
export function FsBackups() {
	const [ data, setData ] = useState( null );
	const [ selected, setSelected ] = useState( [] );
	const [ days, setDays ] = useState( '' );
	const [ maxMb, setMaxMb ] = useState( '' );
	const [ busy, setBusy ] = useState( '' );
	const toast = useToast();
	const confirm = useConfirm();

	const load = ( d ) => {
		setData( d );
		setDays( String( d.retention.days ) );
		setMaxMb( String( d.retention.max_mb ) );
		setSelected( ( cur ) =>
			cur.filter( ( n ) => d.backups.some( ( b ) => b.name === n ) )
		);
	};

	useEffect( () => {
		request( API )
			.then( load )
			.catch( ( e ) => toast.error( errorMessage( e ) ) );
		// eslint-disable-next-line react-hooks/exhaustive-deps -- load once.
	}, [] );

	if ( ! data ) {
		return (
			<Skeleton
				lines={ 6 }
				label={ __( 'Loading backups', 'emcp-tools' ) }
			/>
		);
	}

	const toggle = ( name, on ) =>
		setSelected( ( cur ) =>
			on ? [ ...cur, name ] : cur.filter( ( n ) => n !== name )
		);

	const remove = async () => {
		setBusy( 'delete' );
		try {
			const check = await request( `${ API }/delete`, {
				method: 'POST',
				data: { names: selected, dry_run: true },
			} );
			const lost = check.historyLosingUndo || [];
			const ok = await confirm( {
				title: sprintf(
					/* translators: %d: number of backups. */
					_n(
						'Delete %d backup?',
						'Delete %d backups?',
						selected.length,
						'emcp-tools'
					),
					selected.length
				),
				message: lost.length
					? sprintf(
							/* translators: %d: number of History entries. */
							_n(
								'%d History entry loses its undo, because it needs one of these copies. This cannot be undone.',
								'%d History entries lose their undo, because they need these copies. This cannot be undone.',
								lost.length,
								'emcp-tools'
							),
							lost.length
						)
					: __(
							'No History entry needs these copies. This cannot be undone.',
							'emcp-tools'
						),
				confirmLabel: __( 'Delete', 'emcp-tools' ),
				tone: 'danger',
			} );
			if ( ! ok ) {
				return;
			}
			const r = await request( `${ API }/delete`, {
				method: 'POST',
				data: { names: selected, confirm: true },
			} );
			load( r.fsBackups );
			setSelected( [] );
			toast.success(
				sprintf(
					/* translators: %s: size freed. */
					__( 'Deleted. %s freed.', 'emcp-tools' ),
					formatBytes( r.bytesFreed )
				)
			);
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			setBusy( '' );
		}
	};

	const saveRetention = async () => {
		setBusy( 'retention' );
		try {
			load(
				await request( `${ API }/retention`, {
					method: 'POST',
					data: {
						days: Number( days ) || 0,
						max_mb: Number( maxMb ) || 0,
					},
				} )
			);
			toast.success( __( 'Retention saved.', 'emcp-tools' ) );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			setBusy( '' );
		}
	};

	const all =
		data.backups.length > 0 && selected.length === data.backups.length;

	return (
		<div className="emcp-fs-backups">
			<p>
				{ __(
					'Write File, Edit File and Delete File keep a copy of each file before they change it, so the change can be undone from History. The copies are stored in a protected folder and are removed after the retention period.',
					'emcp-tools'
				) }
			</p>
			<p className="emcp-fs-backups__total">
				{ sprintf(
					/* translators: 1: number of backups, 2: total size. */
					_n(
						'%1$s backup, %2$s',
						'%1$s backups, %2$s',
						data.count,
						'emcp-tools'
					),
					data.count.toLocaleString(),
					formatBytes( data.totalBytes )
				) }
			</p>
			<div className="emcp-fs-backups__retention">
				<Field
					label={ __( 'Keep for (days)', 'emcp-tools' ) }
					help={ __(
						'0 keeps them regardless of age.',
						'emcp-tools'
					) }
				>
					{ ( p ) => (
						<TextInput
							{ ...p }
							type="number"
							min="0"
							value={ days }
							onChange={ ( e ) => setDays( e.target.value ) }
						/>
					) }
				</Field>
				<Field
					label={ __( 'Size limit (MB)', 'emcp-tools' ) }
					help={ __(
						'0 for no limit. The oldest go first.',
						'emcp-tools'
					) }
				>
					{ ( p ) => (
						<TextInput
							{ ...p }
							type="number"
							min="0"
							value={ maxMb }
							onChange={ ( e ) => setMaxMb( e.target.value ) }
						/>
					) }
				</Field>
				<Button
					onClick={ saveRetention }
					loading={ 'retention' === busy }
				>
					{ __( 'Save retention', 'emcp-tools' ) }
				</Button>
			</div>
			{ data.count > data.backups.length && (
				<Notice tone="info">
					{ sprintf(
						/* translators: %d: number shown. */
						__( 'Showing the newest %d.', 'emcp-tools' ),
						data.backups.length
					) }
				</Notice>
			) }
			{ ! data.backups.length ? (
				<EmptyState
					icon="history"
					title={ __( 'No filesystem backups', 'emcp-tools' ) }
				/>
			) : (
				<>
					<div className="emcp-fs-backups__actions">
						<Checkbox
							checked={ all }
							label={ __( 'Select all', 'emcp-tools' ) }
							onChange={ ( on ) =>
								setSelected(
									on
										? data.backups.map( ( b ) => b.name )
										: []
								)
							}
						/>
						<Button
							variant="danger"
							disabled={ ! selected.length }
							loading={ 'delete' === busy }
							onClick={ remove }
						>
							{ __( 'Delete selected', 'emcp-tools' ) }
						</Button>
					</div>
					<Table
						caption={ __( 'Filesystem backups', 'emcp-tools' ) }
						rowKey="name"
						rows={ data.backups }
						columns={ [
							{
								key: 'pick',
								header: __( 'Select', 'emcp-tools' ),
								width: '3rem',
								render: ( b ) => (
									<Checkbox
										checked={ selected.includes( b.name ) }
										label={
											<span className="screen-reader-text">
												{ sprintf(
													/* translators: %s: backup file name. */
													__(
														'Select %s',
														'emcp-tools'
													),
													b.name
												) }
											</span>
										}
										onChange={ ( on ) =>
											toggle( b.name, on )
										}
									/>
								),
							},
							{
								key: 'name',
								header: __( 'Copy of', 'emcp-tools' ),
								mono: true,
								render: ( b ) => b.name,
							},
							{
								key: 'size',
								header: __( 'Size', 'emcp-tools' ),
								align: 'end',
								render: ( b ) => formatBytes( b.size ),
							},
							{
								key: 'time',
								header: __( 'Taken', 'emcp-tools' ),
								render: ( b ) =>
									new Date( b.time ).toLocaleString(),
							},
						] }
					/>
				</>
			) }
		</div>
	);
}
