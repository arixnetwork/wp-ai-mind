/**
 * Playwright global setup.
 *
 * Ensures WP-CLI is available in the Docker WordPress container and that the
 * nj_agent test user exists. Safe to run repeatedly — idempotent.
 *
 * The standard wordpress:php8.2-apache Docker image does not ship WP-CLI;
 * this setup downloads the phar on first run and caches it in the container.
 */

'use strict';

const { execSync } = require( 'child_process' );

const CONTAINER = 'blognjohanssoneu-wordpress-1';
const WP        = `docker exec ${ CONTAINER }`;

/**
 * Ensure WP-CLI is available in the container, downloading if needed.
 */
function ensureWpCli() {
	try {
		execSync( `${ WP } wp --version --allow-root`, { stdio: 'pipe' } );
	} catch {
		console.log( '[E2E setup] WP-CLI not found — installing into container...' );
		execSync(
			`${ WP } bash -c "curl -sL ` +
			`https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar ` +
			`-o /usr/local/bin/wp && chmod +x /usr/local/bin/wp"`,
			{ stdio: 'inherit' }
		);
		console.log( '[E2E setup] WP-CLI installed.' );
	}
}

/**
 * Ensure the nj_agent test user exists in WordPress.
 */
function ensureTestUser() {
	try {
		execSync(
			`${ WP } wp user get nj_agent --field=login --allow-root`,
			{ stdio: 'pipe' }
		);
		console.log( '[E2E setup] nj_agent user already exists — skipping creation.' );
	} catch {
		console.log( '[E2E setup] Creating nj_agent test user in Docker...' );
		execSync(
			`${ WP } wp user create nj_agent nj_agent@example.com ` +
			'--role=administrator ' +
			'--user_pass=C8IcqAWJu8F3dOw6E4ndWhIe ' +
			'--allow-root',
			{ stdio: 'inherit' }
		);
		console.log( '[E2E setup] nj_agent created.' );
	}
}

async function globalSetup() {
	ensureWpCli();
	ensureTestUser();
}

module.exports = globalSetup;
