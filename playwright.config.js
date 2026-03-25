// playwright.config.js
// Minimal Playwright config for local E2E smoke tests against localhost:8080.
const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/E2E/playwright',
	timeout: 30_000,
	retries: 0,
	globalSetup: require.resolve( './tests/E2E/playwright/global-setup.js' ),
	use: {
		baseURL: 'http://localhost:8080',
		headless: true,
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
} );
