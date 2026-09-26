/** Gzipped size budgets for admin bundles (spec 12). */
const KB = 1024;
const BUDGETS = {
	ui: 60 * KB,
	shell: 25 * KB,
	'screen-ai-chat': 80 * KB,
	'screen-backup': 80 * KB,
};
const SCREEN_DEFAULT = 45 * KB;

function budgetFor( name ) {
	if ( BUDGETS[ name ] ) {
		return BUDGETS[ name ];
	}
	return name.startsWith( 'screen-' ) ? SCREEN_DEFAULT : null;
}

function overBudget( files ) {
	return files
		.filter( ( f ) => {
			const budget = budgetFor( f.name );
			return null !== budget && f.gzip > budget;
		} )
		.map( ( f ) => ( { ...f, budget: budgetFor( f.name ) } ) );
}

module.exports = { budgetFor, overBudget };
