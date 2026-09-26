/**
 * Builds the Pro screens when the Pro overlay has any. A free clone has no
 * pro/ folder, so this is a no-op there.
 */
import { existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

if (
	! existsSync( 'pro/webpack.config.js' ) ||
	! existsSync( 'pro/admin-src/screens' )
) {
	process.exit( 0 );
}
const run = spawnSync(
	'npx',
	[ 'wp-scripts', 'build', '--config', 'pro/webpack.config.js' ],
	{ stdio: 'inherit', shell: true }
);
process.exit( run.status ?? 1 );
