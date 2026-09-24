import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon } from './Icon';
import { cx } from '../utils/cx';
import './Code.css';

/**
 * Copy text to the clipboard. The Clipboard API needs a secure context, which
 * wp-admin on plain http (common on local sites) is not, so fall back to a
 * temporary textarea and execCommand. Resolves false when nothing worked.
 *
 * @param {string} text Text to copy.
 * @return {Promise<boolean>} Whether the copy succeeded.
 */
export async function copyText( text ) {
	if ( window.isSecureContext && navigator.clipboard ) {
		try {
			await navigator.clipboard.writeText( text );
			return true;
		} catch ( e ) {
			// Fall through to the textarea path.
		}
	}
	const ta = document.createElement( 'textarea' );
	ta.value = text;
	ta.setAttribute( 'readonly', '' );
	ta.style.position = 'fixed';
	ta.style.opacity = '0';
	document.body.appendChild( ta );
	ta.select();
	let ok = false;
	try {
		ok = !! document.execCommand( 'copy' );
	} catch ( e ) {
		ok = false;
	} finally {
		ta.remove();
	}
	return ok;
}

export function CopyButton( { text, label, className } ) {
	const [ state, setState ] = useState( 'idle' );
	const timer = useRef();
	useEffect( () => () => clearTimeout( timer.current ), [] );
	const onClick = async () => {
		const ok = await copyText( text );
		setState( ok ? 'copied' : 'failed' );
		clearTimeout( timer.current );
		timer.current = setTimeout( () => setState( 'idle' ), 2000 );
	};
	const shown = {
		idle: label || __( 'Copy', 'emcp-tools' ),
		copied: __( 'Copied', 'emcp-tools' ),
		failed: __( 'Copy failed', 'emcp-tools' ),
	}[ state ];
	return (
		<button type="button" className={ cx( 'eui-copy', `is-${ state }`, className ) } onClick={ onClick } aria-live="polite">
			<Icon name={ 'copied' === state ? 'check' : 'copy' } size={ 14 } />
			<span>{ shown }</span>
		</button>
	);
}

export function CopyField( { value, label } ) {
	return (
		<div className="eui-copy-field">
			<code className="eui-copy-field__value" aria-label={ label }>{ value }</code>
			<CopyButton text={ value } />
		</div>
	);
}

export function CodeBlock( { value, label } ) {
	return (
		<div className="eui-code">
			<div className="eui-code__bar">
				<span className="eui-code__label">{ label }</span>
				<CopyButton text={ value } />
			</div>
			<pre className="eui-code__pre" aria-label={ label }>
				<code>{ value }</code>
			</pre>
		</div>
	);
}

/**
 * The only raw-HTML sink in the admin UI. `html` must already be sanitized on
 * the server with wp_kses_post() (spec 8.23 and 11). A test pins this file as
 * the single use of dangerouslySetInnerHTML.
 */
export function SafeHtml( { html, className } ) {
	return <div className={ cx( 'eui-safe-html', className ) } dangerouslySetInnerHTML={ { __html: html } } />;
}
