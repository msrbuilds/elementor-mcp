import { __ } from '@wordpress/i18n';
import { request } from '@emcp/ui';

const defaultSave = ( collapsed ) =>
	request( '/emcp-tools/v1/admin/frame/sidebar', {
		method: 'POST',
		data: { collapsed },
	} );

/**
 * The sidebar toggle in the top bar: collapses the sidebar to its icon rail
 * and back. The server renders the saved state (no flash on load); each
 * change is saved per user, and a failed save keeps the change for this page.
 *
 * @param {Element}                                  frame        .eui-frame.
 * @param {Object}                                   options
 * @param {(collapsed: boolean) => Promise<unknown>} options.save Persist the state.
 */
export function initSidebarToggle( frame, { save = defaultSave } = {} ) {
	const button = frame.querySelector( '[data-emcp-sidebar-toggle]' );
	if ( ! button ) {
		return;
	}
	button.addEventListener( 'click', () => {
		const collapsed = ! frame.classList.contains( 'is-collapsed' );
		frame.classList.toggle( 'is-collapsed', collapsed );
		const label = collapsed
			? __( 'Expand sidebar', 'emcp-tools' )
			: __( 'Collapse sidebar', 'emcp-tools' );
		button.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
		button.setAttribute( 'aria-label', label );
		button.setAttribute( 'title', label );
		// The next page request carries this at once; the REST save below can
		// land after that page is already rendering.
		document.cookie = `emcp_tools_sidebar=${
			collapsed ? '1' : '0'
		}; path=/; max-age=31536000; SameSite=Lax`;
		Promise.resolve( save( collapsed ) ).catch( () => {} );
	} );
}
