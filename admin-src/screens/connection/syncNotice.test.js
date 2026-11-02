import { syncErrorMessage } from './ConnectionScreen';

describe( 'settings sync notices', () => {
	it( 'tells the user to push first when the Cloud holds no settings', () => {
		expect( syncErrorMessage( 'nothing_pushed' ) ).toMatch(
			/Push settings to cloud/
		);
	} );

	it( 'does not tell the user to reconnect for an empty or unusable blob', () => {
		[ 'nothing_pushed', 'nothing_applied', 'invalid_blob' ].forEach(
			( code ) => {
				expect( syncErrorMessage( code ) ).not.toMatch( /Reconnect/ );
			}
		);
	} );

	it( 'names the plan and connection problems', () => {
		expect( syncErrorMessage( 'cloud_http_402' ) ).toMatch( /paid/ );
		expect( syncErrorMessage( 'not_connected' ) ).toMatch( /Reconnect/ );
	} );

	it( 'shows an unknown code', () => {
		expect( syncErrorMessage( 'cloud_http_500' ) ).toMatch(
			/cloud_http_500/
		);
	} );
} );
