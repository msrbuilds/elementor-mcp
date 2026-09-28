import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Badge, Button, IconButton, copyText, request } from '@emcp/ui';
import { handoffUrl, newHandoffId, stashHandoff } from './handoff';

/**
 * One prompt: title, category, 4-line preview, Copy, Customize, Use in AI
 * Chat, Preview.
 *
 * @param {Object}              props             Props.
 * @param {Object}              props.prompt      Prompt item.
 * @param {string}              props.aiChatUrl   AI Chat page, or ''.
 * @param {(p: Object) => void} props.onPreview   Open the preview drawer.
 * @param {(p: Object) => void} props.onCustomize Open the customizer.
 */
export function PromptCard( { prompt, aiChatUrl, onPreview, onCustomize } ) {
	const [ copied, setCopied ] = useState( false );
	const timer = useRef();
	const handoffId = useRef( newHandoffId() );
	useEffect( () => () => clearTimeout( timer.current ), [] );

	const copy = async () => {
		const ok = await copyText( prompt.content );
		if ( ! ok ) {
			return;
		}
		setCopied( true );
		clearTimeout( timer.current );
		timer.current = setTimeout( () => setCopied( false ), 2000 );
		request(
			`/emcp-tools/v1/admin/prompts/${ prompt.category }/${ prompt.slug }/copied`,
			{ method: 'POST' }
		).catch( () => {} );
	};

	// Click and middle click both open the tab, so both stash the prompt.
	const handOff = () => stashHandoff( handoffId.current, prompt.content );

	return (
		<article className="eui-prompt">
			<div className="eui-prompt__head">
				<div className="eui-prompt__heading">
					<Badge kind="status" value="info">
						{ prompt.categoryLabel }
					</Badge>
					<h3 className="eui-prompt__title">{ prompt.title }</h3>
				</div>
				<span className="eui-prompt__icons">
					<IconButton
						icon={ copied ? 'check' : 'copy' }
						className={ copied ? 'eui-prompt__copied' : undefined }
						label={
							copied
								? __( 'Copied', 'emcp-tools' )
								: __( 'Copy prompt', 'emcp-tools' )
						}
						onClick={ copy }
					/>
					<IconButton
						icon="eye"
						label={ sprintf(
							/* translators: %s: prompt title. */
							__( 'Preview %s', 'emcp-tools' ),
							prompt.title
						) }
						onClick={ () => onPreview( prompt ) }
					/>
				</span>
			</div>
			<pre className="eui-prompt__preview" tabIndex={ 0 }>
				{ prompt.content }
			</pre>
			<div className="eui-prompt__actions">
				{ onCustomize && (
					<Button
						size="sm"
						icon="sliders-horizontal"
						onClick={ () => onCustomize( prompt ) }
					>
						{ __( 'Customize', 'emcp-tools' ) }
					</Button>
				) }
				{ aiChatUrl && (
					<a
						className="eui-prompt__chat"
						href={ handoffUrl( aiChatUrl, handoffId.current ) }
						target="_blank"
						rel="noopener noreferrer"
						onClick={ handOff }
						onAuxClick={ handOff }
					>
						{ __( 'Use in AI Chat', 'emcp-tools' ) }{ ' ' }
						<span className="eui-visually-hidden">
							{ __( '(opens in a new tab)', 'emcp-tools' ) }
						</span>
					</a>
				) }
			</div>
		</article>
	);
}
