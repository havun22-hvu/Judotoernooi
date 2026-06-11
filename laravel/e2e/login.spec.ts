import { test, expect } from '@playwright/test';
import { trackCspViolations, expectNoCspViolations } from './csp';

/**
 * Verifies the login page's button handlers work under the strict CSP. The
 * inline onclick attributes were CSP-blocked (dead buttons, no error); they are
 * now wired via addEventListener in a nonce'd script. Clicking must produce the
 * DOM change AND raise no CSP violation.
 */
test.describe('Login page interactions (CSP-safe handlers)', () => {
    test('tab switch and password toggle respond, no CSP violations', async ({ page }) => {
        const violations = trackCspViolations(page);

        await page.goto('/login', { waitUntil: 'domcontentloaded' });

        // Register tab is hidden until its tab button is clicked.
        await expect(page.locator('#register-tab')).toBeHidden();
        await page.locator('#tab-register').click();
        await expect(page.locator('#register-tab')).toBeVisible();

        await page.locator('#tab-login').click();
        await expect(page.locator('#login-tab')).toBeVisible();

        // Password visibility toggle flips the input type.
        const password = page.locator('#password');
        await expect(password).toHaveAttribute('type', 'password');
        await page.locator('#toggle-password').click();
        await expect(password).toHaveAttribute('type', 'text');

        expectNoCspViolations(violations, '/login interactions');
    });
});
