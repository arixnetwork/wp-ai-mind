/**
 * Playwright global setup.
 *
 * Ensures the nj_agent test user exists in the Docker WordPress environment
 * before any E2E tests run. Safe to run repeatedly — exits silently if the
 * user already exists.
 */

'use strict';

const { execSync } = require( 'child_process' );

async function globalSetup() {
	const container = 'blognjohanssoneu-wordpress-1';

	try {
		// Check if the user already exists.
		execSync(
			`docker exec ${ container } wp user get nj_agent --field=login --allow-root`,
			{ stdio: 'pipe' }
		);
		console.log( '[E2E setup] nj_agent user already exists — skipping creation.' );
	} catch {
		// User does not exist — create it.
		console.log( '[E2E setup] Creating nj_agent test user in Docker...' );
		execSync(
			`docker exec ${ container } wp user create nj_agent nj_agent@example.com ` +
			'--role=administrator ' +
			'--user_pass=C8IcqAWJu8F3dOw6E4ndWhIe ' +
			'--allow-root',
			{ stdio: 'inherit' }
		);
		console.log( '[E2E setup] nj_agent created.' );
	}
}

module.exports = globalSetup;
