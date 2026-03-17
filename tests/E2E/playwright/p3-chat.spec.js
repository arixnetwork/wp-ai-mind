// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('P3 — Chat admin page', () => {
    test.beforeEach(async ({ page }) => {
        // Log in as admin (WP default: admin / password)
        await page.goto('/wp-login.php');
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        await page.waitForURL('**/wp-admin/**');
    });

    test('Chat page loads with React mount point', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=wp-ai-mind');
        await expect(page.locator('#wp-ai-mind-chat')).toBeVisible();
    });

    test('Chat shell renders with sidebar and composer', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=wp-ai-mind');
        // Wait for React to hydrate
        await page.waitForSelector('.wpaim-shell', { timeout: 10000 });
        await expect(page.locator('.wpaim-sidebar')).toBeVisible();
        await expect(page.locator('.wpaim-composer')).toBeVisible();
    });

    test('Settings page loads with React mount point', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=wp-ai-mind-settings');
        await expect(page.locator('#wp-ai-mind-settings')).toBeVisible();
    });

    test('Settings tabs render after hydration', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=wp-ai-mind-settings');
        await page.waitForSelector('.wpaim-settings-shell', { timeout: 10000 });
        await expect(page.locator('.wpaim-settings-tabs')).toBeVisible();
    });

    test('REST endpoint /wp-ai-mind/v1/providers responds', async ({ page }) => {
        // Hit the REST API directly via the browser (nonce not required for this check)
        const response = await page.request.get(
            'http://localhost:8080/wp-json/wp-ai-mind/v1/providers'
        );
        // 200 or 401 — either means the route is registered
        expect([200, 401]).toContain(response.status());
    });
});
