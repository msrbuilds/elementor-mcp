import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Icon, Notice, errorMessage, request } from '@emcp/ui';

const API = '/emcp-tools/v1/admin/connection';

const when = ( ts ) => ( ts ? new Date( ts * 1000 ).toLocaleTimeString() : '' );

/**
 * Step 4: wait for the first successful call from the client being set up
 * (spec 9.5). Polls every 3 seconds for up to 5 minutes.
 *
 * @param {Object}     props             Props.
 * @param {string}     props.setupId     Setup record id.
 * @param {string}     props.clientLabel Client name.
 * @param {string}     props.method      oauth | app | cli.
 * @param {Object}     props.conn        { username, password } for the server test.
 * @param {() => void} props.onRestart   Open a new setup.
 * @param {number}     props.interval    Poll interval in ms.
 * @param {number}     props.maxPolls    Polls before giving up.
 */
export function FirstCallStep( {
	setupId,
	clientLabel,
	method,
	conn,
	onRestart,
	interval = 3000,
	maxPolls = 100,
} ) {
	const [ state, setState ] = useState( { phase: 'waiting', failures: [] } );
	const [ test, setTest ] = useState( null );
	const [ testing, setTesting ] = useState( false );
	const polls = useRef( 0 );

	useEffect( () => {
		let stopped = false;
		let timer;
		polls.current = 0;
		setState( { phase: 'waiting', failures: [] } );
		const poll = async () => {
			polls.current += 1;
			try {
				const res = await request(
					API + '/first-call?setup=' + encodeURIComponent( setupId )
				);
				if ( stopped ) {
					return;
				}
				if ( res.matched ) {
					setState( { phase: 'matched', match: res, failures: [] } );
					return;
				}
				setState( {
					phase: 'waiting',
					failures: res.seen_failures || [],
				} );
			} catch ( e ) {
				if ( stopped ) {
					return;
				}
				if ( 'emcp_setup_gone' === e?.code ) {
					setState( { phase: 'gone', failures: [] } );
					return;
				}
			}
			if ( polls.current >= maxPolls ) {
				setState( ( s ) => ( { ...s, phase: 'timeout' } ) );
				return;
			}
			timer = setTimeout( poll, interval );
		};
		poll();
		return () => {
			stopped = true;
			clearTimeout( timer );
		};
	}, [ setupId, interval, maxPolls ] );

	const runTest = async () => {
		setTesting( true );
		try {
			const res =
				'app' === method
					? await request( API + '/test', {
							method: 'POST',
							data: {
								username: conn.username,
								password: conn.password,
							},
						} )
					: await request( API + '/oauth-discovery', {
							method: 'POST',
						} );
			setTest( res );
		} catch ( e ) {
			setTest( { ok: false, message: errorMessage( e ) } );
		} finally {
			setTesting( false );
		}
	};

	if ( 'matched' === state.phase ) {
		const m = state.match;
		return (
			<Notice
				tone="success"
				title={ sprintf(
					/* translators: %s: client name. */ __(
						'%s is connected',
						'emcp-tools'
					),
					clientLabel
				) }
			>
				{ sprintf(
					/* translators: 1: MCP method or tool name, 2: time. */
					__( 'First call: %1$s at %2$s.', 'emcp-tools' ),
					m.tool || m.method,
					when( m.time )
				) }
			</Notice>
		);
	}
	if ( 'gone' === state.phase ) {
		return (
			<Notice
				tone="warning"
				title={ __( 'This setup expired', 'emcp-tools' ) }
				actions={
					<Button onClick={ onRestart }>
						{ __( 'Start again', 'emcp-tools' ) }
					</Button>
				}
			>
				{ __(
					'Setups last 30 minutes. Start again to get fresh instructions.',
					'emcp-tools'
				) }
			</Notice>
		);
	}
	const last = state.failures[ state.failures.length - 1 ];
	return (
		<div className="eui-conn__wait">
			{ 'waiting' === state.phase && (
				<p className="eui-conn__waiting" role="status">
					<Icon name="loader-circle" className="eui-conn__spin" />
					{ sprintf(
						/* translators: %s: client name. */ __(
							'Waiting for %s to call the server',
							'emcp-tools'
						),
						clientLabel
					) }
				</p>
			) }
			{ last && (
				<Notice tone="error">
					{ sprintf(
						/* translators: 1: client name, 2: error message. */
						__(
							'We saw a call from %1$s, but it failed: %2$s',
							'emcp-tools'
						),
						clientLabel,
						last.failure_reason || last.method
					) }
				</Notice>
			) }
			{ 'timeout' === state.phase && (
				<Notice
					tone="warning"
					title={ __( 'No call yet', 'emcp-tools' ) }
				>
					<ul className="eui-conn__trouble">
						<li>
							{ __(
								'Restart the client after changing its config.',
								'emcp-tools'
							) }
						</li>
						<li>
							{ __(
								'Check that the server URL is reachable from the computer running the client.',
								'emcp-tools'
							) }
						</li>
						<li>
							{ __(
								'Some hosts strip the Authorization header; the server test below shows it.',
								'emcp-tools'
							) }
						</li>
					</ul>
				</Notice>
			) }
			{ ( 'timeout' === state.phase || last ) && 'cli' !== method && (
				<Button
					onClick={ runTest }
					loading={ testing }
					disabled={ testing }
				>
					{ __( 'Run a server test', 'emcp-tools' ) }
				</Button>
			) }
			{ test && (
				<Notice tone={ test.ok ? 'success' : 'error' }>
					{ test.message }
				</Notice>
			) }
		</div>
	);
}
