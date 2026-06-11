import { test as setup, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { ORG_SLUG } from './fixtures';
import { STORAGE_STATE } from './env';

/**
 * Runs once as a Playwright "setup project" before the authenticated specs.
 * The webServer is guaranteed up here, so we hit the test-login seam, follow
 * the redirect to the slug-scoped dashboard, and persist the session cookie to
 * STORAGE_STATE. The authenticated project reuses it, so no spec logs in itself.
 */
setup('authenticate test organisator via e2e seam', async ({ page }) => {
    await page.goto('/e2e/login');

    // Seam: /e2e/login -> /dashboard -> /{org}/dashboard.
    await page.waitForURL(new RegExp(`/${ORG_SLUG}/dashboard`));
    await expect(page.locator('h1, h2').first()).toBeVisible();

    fs.mkdirSync(path.dirname(STORAGE_STATE), { recursive: true });
    await page.context().storageState({ path: STORAGE_STATE });
});
