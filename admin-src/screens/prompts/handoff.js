/**
 * "Use in AI Chat" opens AI Chat in a new tab. A new tab does not share
 * sessionStorage, so the prompt goes to localStorage under an id that the
 * link carries as ?handoff=; the AI Chat tab takes that one item and clears
 * it (pro/admin-src/chat/service/handoff.js, takeHandoffById()).
 */
export const HANDOFF_PREFIX = 'emcp.aiChat.handoff.';

/** A fresh id: lowercase letters and digits only. */
export function newHandoffId() {
	return (
		Math.random().toString( 36 ).slice( 2, 10 ) + Date.now().toString( 36 )
	).replace( /[^a-z0-9]/g, '' );
}

/**
 * The AI Chat URL carrying the hand-off id.
 *
 * @param {string} aiChatUrl AI Chat page.
 * @param {string} id        Hand-off id.
 * @return {string} URL.
 */
export function handoffUrl( aiChatUrl, id ) {
	const url = new URL( aiChatUrl, window.location.href );
	url.searchParams.set( 'handoff', id );
	return url.toString();
}

/**
 * Store the prompt for the tab about to open.
 *
 * @param {string} id   Hand-off id.
 * @param {string} text Prompt text.
 */
export function stashHandoff( id, text ) {
	try {
		window.localStorage.setItem(
			HANDOFF_PREFIX + id,
			JSON.stringify( { text, at: Date.now() } )
		);
	} catch {}
}
