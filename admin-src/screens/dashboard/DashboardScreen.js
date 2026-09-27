import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Button,
	PageHeader,
	errorMessage,
	request,
	useConfirm,
	useToast,
} from '@emcp/ui';
import {
	ActivityCard,
	Attention,
	CloudPanel,
	Features,
	HealthStrip,
	Help,
	MostUsed,
	RecentChanges,
	Videos,
} from './parts';

const API = '/emcp-tools/v1/admin/dashboard';

/**
 * Dashboard (spec 8.1).
 *
 * @param {Object} props
 * @param {Object} props.data Boot payload (EMCP_Tools_Admin_Dashboard_Data).
 */
export function DashboardScreen( { data } ) {
	const [ range, setRange ] = useState( data.activity.range );
	const [ activity, setActivity ] = useState( data.activity );
	const [ recent, setRecent ] = useState( data.recent );
	const [ attention, setAttention ] = useState( data.attention );
	const [ busy, setBusy ] = useState( '' );
	const gen = useRef( 0 );
	const toast = useToast();
	const confirm = useConfirm();

	// Only the newest request may write: switching range twice quickly must
	// never show the first answer last.
	const load = async ( days ) => {
		const mine = ++gen.current;
		try {
			const res = await request( `${ API }?range=${ days }` );
			if ( mine !== gen.current ) {
				return;
			}
			setActivity( res.activity );
			setRecent( res.recent );
			setAttention( res.attention );
		} catch ( e ) {
			if ( mine === gen.current ) {
				toast.error( errorMessage( e ) );
			}
		}
	};

	const onRange = ( days ) => {
		setRange( days );
		load( days );
	};

	// Same confirm and conflict handling as History (spec 8.1).
	const undo = async ( row, force = false ) => {
		setBusy( row.id );
		try {
			await request(
				`/emcp-tools/v1/admin/history/${ encodeURIComponent(
					row.id
				) }/undo`,
				{ method: 'POST', data: { force, limit: 1 } }
			);
			toast.success( __( 'Change undone.', 'emcp-tools' ) );
			await load( range );
		} catch ( e ) {
			if ( 'conflict' === e.code && ! force ) {
				const ok = await confirm( {
					title: __( 'This changed since', 'emcp-tools' ),
					message: e.message,
					confirmLabel: __( 'Undo anyway', 'emcp-tools' ),
					tone: 'danger',
				} );
				if ( ok ) {
					await undo( row, true );
				}
				return;
			}
			toast.error( errorMessage( e ) );
		} finally {
			setBusy( '' );
		}
	};

	const dismiss = async ( item ) => {
		try {
			const res = await request(
				`${ API }/attention/${ encodeURIComponent( item.id ) }/dismiss`,
				{ method: 'POST' }
			);
			setAttention( res.attention );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		}
	};

	const { header } = data;

	return (
		<div className="emcp-dash">
			<PageHeader
				title={ __( 'Dashboard', 'emcp-tools' ) }
				description={ sprintf(
					/* translators: 1: site host, 2: number of days. */
					__(
						'What your AI has done on %1$s over the last %2$d days.',
						'emcp-tools'
					),
					header.site,
					range
				) }
				actions={
					<>
						{ header.premium && (
							<Badge kind="status" value="success" dot>
								{ __( 'Pro plan active', 'emcp-tools' ) }
							</Badge>
						) }
						{ header.aiChatUrl && (
							<Button
								href={ header.aiChatUrl }
								icon="message-square"
							>
								{ __( 'Open AI Chat', 'emcp-tools' ) }
							</Button>
						) }
						<Button
							variant="primary"
							href={ header.connectUrl }
							icon="plug"
						>
							{ __( 'Connect a client', 'emcp-tools' ) }
						</Button>
					</>
				}
			/>
			<HealthStrip health={ data.health } />
			<div className="emcp-dash__row">
				<ActivityCard
					activity={ activity }
					range={ range }
					onRange={ onRange }
				/>
				<MostUsed items={ activity.mostUsed } logUrl={ data.logUrl } />
			</div>
			<div className="emcp-dash__row">
				<RecentChanges
					rows={ recent }
					busy={ busy }
					onUndo={ ( r ) => undo( r ) }
					historyUrl={ data.historyUrl }
				/>
				<Attention items={ attention } onDismiss={ dismiss } />
			</div>
			<CloudPanel cloud={ data.cloud } />
			<Features items={ data.features } />
			<div className="emcp-dash__row">
				<Videos items={ data.videos } tutorials={ data.tutorials } />
				<Help items={ data.help } />
			</div>
		</div>
	);
}
