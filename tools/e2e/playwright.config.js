// End-to-end tests of the key store flows in the three browser engines.
// Run from tools/:  npx playwright test -c e2e
const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: '.',
	timeout: 90_000,
	expect: { timeout: 15_000 },
	fullyParallel: false, // One WooCommerce site and stock pool: keep orders sequential.
	workers: 1,
	retries: 0,
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.NAFHA_URL || 'http://localhost:8080',
		locale: 'ar-QA',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{ name: 'webkit-iphone', use: { ...devices[ 'iPhone 14' ] } }, // Safari engine.
		{ name: 'chromium-android', use: { ...devices[ 'Pixel 7' ] } },
		{ name: 'firefox-desktop', use: { ...devices[ 'Desktop Firefox' ] } },
		{ name: 'chromium-desktop', use: { ...devices[ 'Desktop Chrome' ] } },
	],
	outputDir: '../reports/e2e',
} );
