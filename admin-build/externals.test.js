const { requestToExternal, requestToHandle } = require( './externals' );

describe( 'externals', () => {
	it( 'maps @emcp/ui to the emcpUI global and the emcp-admin-ui handle', () => {
		expect( requestToExternal( '@emcp/ui' ) ).toBe( 'emcpUI' );
		expect( requestToHandle( '@emcp/ui' ) ).toBe( 'emcp-admin-ui' );
	} );

	it( 'leaves every other request to the default WordPress mapping', () => {
		expect( requestToExternal( '@wordpress/element' ) ).toBeUndefined();
		expect( requestToHandle( 'lucide-react' ) ).toBeUndefined();
	} );
} );
