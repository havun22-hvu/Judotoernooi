import { test, expect } from '@playwright/test';
import { HomePage } from './pages/HomePage';

test.describe('Home / landing page', () => {
    test('renders the landing page with title and hero heading', async ({ page }) => {
        const home = new HomePage(page);
        await home.goto();
        await home.expectLoaded();
    });

    /**
     * The app ships the Alpine.js CSP build, which throws on disallowed inline
     * expressions. A clean `pageerror` log is therefore a strong regression
     * signal for Alpine CSP violations (a recurring class of bug in this app).
     */
    test('loads without uncaught JavaScript errors', async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (err) => pageErrors.push(err.message));

        await page.goto('/');
        await expect(page.locator('h1').first()).toBeVisible();

        expect(pageErrors, `Uncaught JS errors:\n${pageErrors.join('\n')}`).toEqual([]);
    });

    test('serves a valid 200 response', async ({ page }) => {
        const response = await page.goto('/');
        expect(response?.status()).toBe(200);
    });
});
