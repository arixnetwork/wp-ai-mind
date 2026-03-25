// tests/E2E/playwright/p1-activation.spec.js
// P1 smoke tests — verify the plugin activates cleanly on localhost:8080.
//
// Credentials: uses the nj_agent E2E test account (seeded by global-setup.js).
// Run: npx playwright test tests/E2E/playwright/p1-activation.spec.js

const { test, expect } = require( '@playwright/test' );

// ---------------------------------------------------------------------------
// Shared helper — log in as the E2E test admin.
// ---------------------------------------------------------------------------
async function loginAsAdmin( page ) {
	await page.goto( 'http://localhost:8080/wp-login.php' );
	await page.fill( '#user_login', 'nj_agent' );
	await page.fill( '#user_pass', 'C8IcqAWJu8F3dOw6E4ndWhIe' );
	await page.click( '#wp-submit' );
	// Wait for the dashboard to load.
	await page.waitForURL( '**/wp-admin/**' );
}

// ---------------------------------------------------------------------------

test.describe( 'P1 — Plugin activation', () => {

	test( 'Admin menu item "AI Mind" appears after activation', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( 'http://localhost:8080/wp-admin/' );
		await expect( page.locator( '#adminmenu' ) ).toContainText( 'AI Mind' );
	} );

	test( 'Chat page renders the React mount point (#wp-ai-mind-chat)', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( 'http://localhost:8080/wp-admin/?page=wp-ai-mind' );
		await expect( page.locator( '#wp-ai-mind-chat' ) ).toBeAttached();
	} );

	test( 'Plugin page loads without PHP fatal errors', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( 'http://localhost:8080/wp-admin/?page=wp-ai-mind' );
		await expect( page ).not.toHaveTitle( /Fatal error/i );
		// Also confirm the page title is not a generic WordPress error.
		await expect( page ).not.toHaveTitle( /Error/i );
	} );

} );
