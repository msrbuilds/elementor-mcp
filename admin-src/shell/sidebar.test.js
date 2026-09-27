import { initSidebarToggle } from './sidebar';

function frame( collapsed = false ) {
	document.body.innerHTML = `<div class="eui-frame${
		collapsed ? ' is-collapsed' : ''
	}"><div id="emcp-frame-sidebar"></div><button type="button" data-emcp-sidebar-toggle aria-controls="emcp-frame-sidebar" aria-expanded="${
		collapsed ? 'false' : 'true'
	}" aria-label="${
		collapsed ? 'Expand sidebar' : 'Collapse sidebar'
	}"></button></div>`;
	return document.querySelector( '.eui-frame' );
}

describe( 'initSidebarToggle', () => {
	it( 'collapses and expands the sidebar and saves each change', () => {
		const root = frame();
		const save = jest.fn( () => Promise.resolve() );
		initSidebarToggle( root, { save } );
		const button = root.querySelector( '[data-emcp-sidebar-toggle]' );
		button.click();
		expect( root.classList.contains( 'is-collapsed' ) ).toBe( true );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( button.getAttribute( 'aria-label' ) ).toBe( 'Expand sidebar' );
		expect( save ).toHaveBeenLastCalledWith( true );
		button.click();
		expect( root.classList.contains( 'is-collapsed' ) ).toBe( false );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( button.getAttribute( 'aria-label' ) ).toBe(
			'Collapse sidebar'
		);
		expect( save ).toHaveBeenLastCalledWith( false );
	} );

	it( 'writes a cookie the next page request carries', () => {
		const root = frame();
		initSidebarToggle( root, { save: () => Promise.resolve() } );
		const button = root.querySelector( '[data-emcp-sidebar-toggle]' );
		button.click();
		expect( document.cookie ).toContain( 'emcp_tools_sidebar=1' );
		button.click();
		expect( document.cookie ).toContain( 'emcp_tools_sidebar=0' );
	} );

	it( 'starts from the state the server rendered', () => {
		const root = frame( true );
		const save = jest.fn( () => Promise.resolve() );
		initSidebarToggle( root, { save } );
		root.querySelector( '[data-emcp-sidebar-toggle]' ).click();
		expect( root.classList.contains( 'is-collapsed' ) ).toBe( false );
		expect( save ).toHaveBeenCalledWith( false );
	} );

	it( 'keeps the new state when saving fails', async () => {
		const root = frame();
		initSidebarToggle( root, {
			save: () => Promise.reject( new Error( 'x' ) ),
		} );
		root.querySelector( '[data-emcp-sidebar-toggle]' ).click();
		await Promise.resolve();
		expect( root.classList.contains( 'is-collapsed' ) ).toBe( true );
	} );
} );
