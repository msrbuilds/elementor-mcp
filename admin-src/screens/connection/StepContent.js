import { __ } from '@wordpress/i18n';
import { Button, CodeBlock, CopyField, SafeHtml } from '@emcp/ui';

const escapeHtml = ( s ) =>
	String( s ).replace(
		/[&<>"']/g,
		( c ) =>
			( {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#39;',
			} )[ c ]
	);

/**
 * Render snippet steps (Task 4) as lettered instructions.
 *
 * @param {Object}     props          Props.
 * @param {Object[]}   props.steps    Steps from snippets.js.
 * @param {Object}     props.vars     { name, endpoint, b64 } for guide placeholders.
 * @param {() => void} props.onBundle Download the .mcpb bundle.
 */
export function StepContent( { steps, vars = {}, onBundle } ) {
	let letter = 0;
	return (
		<ol className="eui-conn__steps">
			{ steps.map( ( s, i ) => {
				const mark = s.title
					? String.fromCharCode( 97 + letter++ )
					: '';
				return (
					<li key={ i } className="eui-conn__substep">
						{ mark && (
							<span
								className="eui-conn__letter"
								aria-hidden="true"
							>
								{ mark }
							</span>
						) }
						<div className="eui-conn__substep-body">
							{ s.title &&
								'code' !== s.kind &&
								'link' !== s.kind && (
									<p className="eui-conn__substep-title">
										{ s.title }
									</p>
								) }
							{ 'text' === s.kind && s.text && (
								<p className="eui-conn__substep-desc">
									{ s.text }
								</p>
							) }
							{ 'copy' === s.kind && (
								<CopyField value={ s.text } label={ s.title } />
							) }
							{ 'code' === s.kind && (
								<CodeBlock
									value={ s.text }
									label={
										s.title || __( 'Config', 'emcp-tools' )
									}
								/>
							) }
							{ 'link' === s.kind && (
								<a
									className="eui-btn eui-btn--primary"
									href={ s.href }
									target="_blank"
									rel="noopener noreferrer"
								>
									{ s.title }
								</a>
							) }
							{ 'paths' === s.kind && (
								<ul className="eui-conn__paths">
									{ s.paths.map( ( p ) => (
										<li key={ p.path }>
											<code>{ p.path }</code>{ ' ' }
											{ p.label && (
												<span>{ p.label }</span>
											) }
										</li>
									) ) }
								</ul>
							) }
							{ 'guide' === s.kind && (
								<SafeHtml
									html={ String( s.text )
										.replace(
											/%NAME%/g,
											escapeHtml( vars.name || '' )
										)
										.replace(
											/%ENDPOINT%/g,
											escapeHtml( vars.endpoint || '' )
										)
										.replace(
											/%B64%/g,
											escapeHtml( vars.b64 || '' )
										) }
								/>
							) }
							{ 'bundle' === s.kind && (
								<>
									<p className="eui-conn__substep-desc">
										{ __(
											'Download and double-click to install in Claude Desktop, no config files to edit.',
											'emcp-tools'
										) }
									</p>
									<p className="eui-conn__warning">
										{ __(
											'Treat this file as a secret: it contains your application password. Delete it once Claude Desktop has imported it.',
											'emcp-tools'
										) }
									</p>
									<Button onClick={ onBundle }>
										{ __(
											'Download .mcpb bundle',
											'emcp-tools'
										) }
									</Button>
								</>
							) }
						</div>
					</li>
				);
			} ) }
		</ol>
	);
}
