/**
 * Browser smoke of the admin frame (spec 14). Needs a running site:
 * EMCP_E2E_URL plus either EMCP_E2E_USER + EMCP_E2E_PASS or EMCP_E2E_COOKIES
 * (a JSON file written by tests-e2e/auth-cookies.php).
 */
const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests-e2e',
	timeout: 120000,
	workers: 1,
	use: {
		baseURL: process.env.EMCP_E2E_URL,
		ignoreHTTPSErrors: true,
		// Screens animate in (cards rise, numbers count). Layout, hit-area and
		// axe checks assert the settled page, so they run with reduced motion;
		// dashboard.spec.js turns motion on to check the animations themselves.
		contextOptions: { reducedMotion: 'reduce' },
	},
	projects: [
		{ name: '1440', use: { viewport: { width: 1440, height: 900 } } },
		{ name: '1280', use: { viewport: { width: 1280, height: 800 } } },
		{ name: '1024', use: { viewport: { width: 1024, height: 768 } } },
	],
} );
