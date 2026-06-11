import { test, expect } from '@playwright/test';

/**
 * Smoke coverage for the public, no-auth pages. These render without seeded
 * tournament data, so they form a stable baseline that catches routing,
 * Blade-render and asset-pipeline regressions across an upgrade (e.g. the
 * Laravel 11 -> 12 bump).
 */
const publicPages: { name: string; path: string }[] = [
    { name: 'help', path: '/help' },
    { name: 'algemene voorwaarden', path: '/algemene-voorwaarden' },
    { name: 'privacyverklaring', path: '/privacyverklaring' },
    { name: 'cookiebeleid', path: '/cookiebeleid' },
    { name: 'disclaimer', path: '/disclaimer' },
];

for (const { name, path } of publicPages) {
    test(`public page "${name}" loads with 200 and no JS errors`, async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (err) => pageErrors.push(err.message));

        const response = await page.goto(path);

        expect(response?.status(), `${path} should return 200`).toBe(200);
        await expect(page.locator('h1, h2').first()).toBeVisible();
        expect(pageErrors, `Uncaught JS errors on ${path}:\n${pageErrors.join('\n')}`).toEqual([]);
    });
}

test('sitemap.xml is served as XML', async ({ request }) => {
    const response = await request.get('/sitemap.xml');
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('xml');
});
