const { budgetFor, overBudget } = require( './budgets' );

describe( 'bundle budgets', () => {
	it( 'uses the spec 12 limits', () => {
		expect( budgetFor( 'ui' ) ).toBe( 60 * 1024 );
		expect( budgetFor( 'shell' ) ).toBe( 25 * 1024 );
		expect( budgetFor( 'screen-tools' ) ).toBe( 45 * 1024 );
		expect( budgetFor( 'screen-chat' ) ).toBe( 80 * 1024 );
		expect( budgetFor( 'screen-backup' ) ).toBe( 80 * 1024 );
		expect( budgetFor( 'vendor-thing' ) ).toBeNull();
	} );

	it( 'reports only files over budget', () => {
		expect(
			overBudget( [
				{ name: 'ui', gzip: 10 },
				{ name: 'screen-tools', gzip: 50 * 1024 },
			] )
		).toEqual( [
			{ name: 'screen-tools', gzip: 50 * 1024, budget: 45 * 1024 },
		] );
	} );
} );
