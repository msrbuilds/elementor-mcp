import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Drawer,
	Field,
	Notice,
	Select,
	Skeleton,
	TextInput,
	Textarea,
	errorMessage,
	request,
} from '@emcp/ui';

const API = '/emcp-tools/v1/admin/sandbox/snippets';

const SEVERITY = () => ( {
	critical: __( 'Must change', 'emcp-tools' ),
	warning: __( 'Worth a read', 'emcp-tools' ),
	notice: __( 'Note', 'emcp-tools' ),
} );

/**
 * A validator report: the verdict, then each finding with its line.
 *
 * @param {Object}      props            Props.
 * @param {Object}      props.validation { findings[{severity, line, message}] }.
 * @param {Object|null} props.summary    { blocked, headline, detail }.
 * @param {string}      [props.only]     Show only this severity.
 */
export function Findings( { validation, summary, only } ) {
	const findings = ( ( validation && validation.findings ) || [] ).filter(
		( f ) => ! only || f.severity === only
	);
	if ( ! summary && ! findings.length ) {
		return null;
	}
	const labels = SEVERITY();
	return (
		<div className="emcp-sn-findings">
			{ summary && (
				<p className="emcp-sn-findings__head">
					<strong
						className={ summary.blocked ? 'is-blocked' : 'is-ok' }
					>
						{ summary.headline }
					</strong>{ ' ' }
					{ summary.detail }
				</p>
			) }
			{ findings.length > 0 && (
				<ul className="emcp-sn-findings__list">
					{ findings.map( ( f, i ) => (
						<li key={ i } className={ `is-${ f.severity }` }>
							<strong>
								{ f.line
									? sprintf(
											/* translators: 1: severity label, 2: line number. */
											__(
												'%1$s · line %2$d:',
												'emcp-tools'
											),
											labels[ f.severity ] ||
												labels.notice,
											f.line
										)
									: `${ labels[ f.severity ] || labels.notice }:` }
							</strong>{ ' ' }
							{ f.message }
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

const EMPTY = {
	title: '',
	code: '',
	context: 'shortcode',
	hook: '',
	priority: '10',
};

/**
 * Add or edit a PHP snippet. Uses WordPress's code editor when it is on.
 *
 * @param {Object}                     props            Props.
 * @param {boolean}                    props.open       Open.
 * @param {Object|null}                props.item       { id } (0 for a new snippet).
 * @param {Object|boolean}             props.codeEditor wp.codeEditor settings, or false.
 * @param {() => void}                 props.onClose    Close.
 * @param {(fields: Object) => Object} props.onSave     Saves; rejects with the REST error.
 */
export function SnippetEditor( { open, item, codeEditor, onClose, onSave } ) {
	const [ fields, setFields ] = useState( EMPTY );
	const [ loading, setLoading ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ failure, setFailure ] = useState( null );
	// Which snippet the fields hold (0 for a new one, null for none yet). The
	// form, and CodeMirror with it, only mounts once it matches, so a new
	// snippet never opens on the code of the one edited before.
	const [ loadedFor, setLoadedFor ] = useState( null );
	const area = useRef();
	const editor = useRef( null );
	const id = item && item.id ? item.id : 0;

	useEffect( () => {
		if ( ! open ) {
			setLoadedFor( null );
			return;
		}
		setFailure( null );
		if ( ! id ) {
			setFields( EMPTY );
			setLoadedFor( 0 );
			return;
		}
		let live = true;
		setLoading( true );
		request( `${ API }/${ id }` )
			.then( ( d ) => {
				if ( live ) {
					setFields( {
						...d.snippet,
						priority: String( d.snippet.priority || 10 ),
					} );
					setLoadedFor( id );
				}
			} )
			.catch(
				( e ) => live && setFailure( { message: errorMessage( e ) } )
			)
			.finally( () => live && setLoading( false ) );
		return () => {
			live = false;
		};
	}, [ open, id ] );

	const ready = open && ! loading && loadedFor === id;

	// CodeMirror takes over the textarea once it holds this snippet's code.
	useEffect( () => {
		const wp = window.wp;
		if (
			! ready ||
			! codeEditor ||
			! area.current ||
			! wp ||
			! wp.codeEditor
		) {
			return;
		}
		editor.current = wp.codeEditor.initialize( area.current, codeEditor );
		return () => {
			if ( editor.current && editor.current.codemirror ) {
				editor.current.codemirror.toTextArea();
			}
			editor.current = null;
		};
	}, [ ready, codeEditor ] );

	const set = ( key ) => ( value ) =>
		setFields( ( f ) => ( { ...f, [ key ]: value } ) );
	const usesHook = 'hook' === fields.context || 'both' === fields.context;

	const save = async () => {
		const code =
			editor.current && editor.current.codemirror
				? editor.current.codemirror.getValue()
				: fields.code;
		setSaving( true );
		setFailure( null );
		try {
			await onSave( {
				title: fields.title,
				code,
				context: fields.context,
				hook: usesHook ? fields.hook : '',
				priority: Number( fields.priority ) || 10,
			} );
		} catch ( e ) {
			setFailure( {
				message: errorMessage( e ),
				validation: e && e.data ? e.data.validation : null,
				summary: e && e.data ? e.data.summary : null,
			} );
		} finally {
			setSaving( false );
		}
	};

	return (
		<Drawer
			open={ open }
			title={
				id
					? __( 'Edit snippet', 'emcp-tools' )
					: __( 'Add snippet', 'emcp-tools' )
			}
			onClose={ onClose }
			width={ 720 }
			footer={
				<>
					<Button onClick={ onClose } disabled={ saving }>
						{ __( 'Cancel', 'emcp-tools' ) }
					</Button>
					<Button
						variant="primary"
						loading={ saving }
						disabled={ ! ready }
						onClick={ save }
					>
						{ id
							? __( 'Save', 'emcp-tools' )
							: __( 'Save draft', 'emcp-tools' ) }
					</Button>
				</>
			}
		>
			{ ! ready && failure && (
				<Notice tone="danger">{ failure.message }</Notice>
			) }
			{ ! ready && ! failure && <Skeleton lines={ 6 } /> }
			{ ready && (
				<div className="emcp-sn-form">
					<Field label={ __( 'Title', 'emcp-tools' ) }>
						{ ( p ) => (
							<TextInput
								{ ...p }
								value={ fields.title }
								placeholder={ __( 'My snippet', 'emcp-tools' ) }
								onChange={ ( e ) =>
									set( 'title' )( e.target.value )
								}
							/>
						) }
					</Field>
					<div className="emcp-sn-form__row">
						<Field label={ __( 'Runs as', 'emcp-tools' ) }>
							{ ( p ) => (
								<Select
									{ ...p }
									value={ fields.context }
									onChange={ set( 'context' ) }
									options={ [
										{
											value: 'shortcode',
											label: __(
												'Shortcode',
												'emcp-tools'
											),
										},
										{
											value: 'hook',
											label: __( 'Hook', 'emcp-tools' ),
										},
										{
											value: 'both',
											label: __( 'Both', 'emcp-tools' ),
										},
									] }
								/>
							) }
						</Field>
						{ usesHook && (
							<>
								<Field label={ __( 'Hook', 'emcp-tools' ) }>
									{ ( p ) => (
										<TextInput
											{ ...p }
											mono
											value={ fields.hook }
											placeholder="wp_footer"
											onChange={ ( e ) =>
												set( 'hook' )( e.target.value )
											}
										/>
									) }
								</Field>
								<Field label={ __( 'Priority', 'emcp-tools' ) }>
									{ ( p ) => (
										<TextInput
											{ ...p }
											type="number"
											value={ fields.priority }
											onChange={ ( e ) =>
												set( 'priority' )(
													e.target.value
												)
											}
										/>
									) }
								</Field>
							</>
						) }
					</div>
					<Field
						label={ __( 'Code', 'emcp-tools' ) }
						help={ __(
							'No <?php tag needed; return or echo for shortcode output.',
							'emcp-tools'
						) }
					>
						{ ( p ) => (
							<Textarea
								{ ...p }
								ref={ area }
								mono
								rows={ 16 }
								spellCheck={ false }
								value={ fields.code }
								onChange={ ( e ) =>
									set( 'code' )( e.target.value )
								}
							/>
						) }
					</Field>
					{ failure && (
						<Notice tone="danger">
							<span>{ failure.message }</span>
							<Findings
								validation={ failure.validation }
								summary={ failure.summary }
							/>
						</Notice>
					) }
				</div>
			) }
		</Drawer>
	);
}
