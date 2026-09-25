import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Badge, Button, IconButton, copyText, request } from '@emcp/ui';

export const HANDOFF_KEY = 'emcp.aiChat.prompt';

/**
 * One prompt: title, category, 4-line preview, Copy, Use in AI Chat, Preview.
 *
 * @param {Object}              props           Props.
 * @param {Object}              props.prompt    Prompt item.
 * @param {string}              props.aiChatUrl AI Chat page, or ''.
 * @param {(p: Object) => void} props.onPreview Open the preview drawer.
 */
export function PromptCard( { prompt, aiChatUrl, onPreview } ) {
	const [ copied, setCopied ] = useState( false );
	const timer = useRef();
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

	const handOff = () => {
		try {
			window.sessionStorage.setItem(
				HANDOFF_KEY,
				JSON.stringify( { text: prompt.content, at: Date.now() } )
			);
		} catch {}
	};

	return (
		<article className="eui-prompt">
			<div className="eui-prompt__head">
				<h3 className="eui-prompt__title">{ prompt.title }</h3>
				<Badge kind="status" value="info">
					{ prompt.categoryLabel }
				</Badge>
			</div>
			<pre className="eui-prompt__preview" tabIndex={ 0 }>
				{ prompt.content }
			</pre>
			<div className="eui-prompt__actions">
				<Button
					size="sm"
					className={ copied ? 'eui-prompt__copied' : undefined }
					icon={ copied ? 'check' : 'copy' }
					onClick={ copy }
				>
					{ copied
						? __( 'Copied', 'emcp-tools' )
						: __( 'Copy prompt', 'emcp-tools' ) }
				</Button>
				{ aiChatUrl && (
					<a
						className="eui-prompt__chat"
						href={ aiChatUrl }
						onClick={ handOff }
					>
						{ __( 'Use in AI Chat', 'emcp-tools' ) }
					</a>
				) }
				<IconButton
					icon="eye"
					label={ sprintf(
						/* translators: %s: prompt title. */
						__( 'Preview %s', 'emcp-tools' ),
						prompt.title
					) }
					onClick={ () => onPreview( prompt ) }
				/>
			</div>
		</article>
	);
}
