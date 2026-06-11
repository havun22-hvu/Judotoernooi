import { test, expect, type Page } from '@playwright/test';
import { DashboardPage } from './pages/DashboardPage';
import { ToernooiPage } from './pages/ToernooiPage';
import { PoulePage } from './pages/PoulePage';
import { MatPage } from './pages/MatPage';
import { toernooiUrl, TOERNOOI_SLUG } from './fixtures';

/**
 * Authenticated organisator smoke flows. The session is provided by the
 * `setup` project (auth.setup.ts) via storageState, so these specs start
 * already logged in. Like the public suite, every page is checked for uncaught
 * JS errors — the regression guard for Alpine CSP violations on the
 * data-driven, Alpine-heavy organisator screens.
 */

/** Collect uncaught browser errors for the lifetime of a test. */
function trackPageErrors(page: Page): string[] {
    const errors: string[] = [];
    page.on('pageerror', (err) => errors.push(err.message));
    return errors;
}

test.describe('Authenticated organisator flows', () => {
    test('dashboard shows the seeded tournament without JS errors', async ({ page }) => {
        const errors = trackPageErrors(page);
        const dashboard = new DashboardPage(page);

        await dashboard.goto();
        await dashboard.expectLoaded();

        expect(errors, `Uncaught JS errors on dashboard:\n${errors.join('\n')}`).toEqual([]);
    });

    test('open tournament from the dashboard', async ({ page }) => {
        const dashboard = new DashboardPage(page);
        await dashboard.goto();

        await dashboard.tournamentLink().click();

        await expect(page).toHaveURL(new RegExp(`/toernooi/${TOERNOOI_SLUG}`));
        await expect(page.getByText('E2E Test Toernooi').first()).toBeVisible();
    });

    test('tournament detail page loads without JS errors', async ({ page }) => {
        const errors = trackPageErrors(page);
        const toernooi = new ToernooiPage(page);

        await toernooi.goto();
        await toernooi.expectLoaded();

        expect(errors, `Uncaught JS errors on tournament page:\n${errors.join('\n')}`).toEqual([]);
    });

    test('poule overview loads without JS errors', async ({ page }) => {
        const errors = trackPageErrors(page);
        const poule = new PoulePage(page);

        await poule.goto();
        await poule.expectLoaded();

        expect(errors, `Uncaught JS errors on poule page:\n${errors.join('\n')}`).toEqual([]);
    });

    test('mat overview loads without JS errors', async ({ page }) => {
        const errors = trackPageErrors(page);
        const mat = new MatPage(page);

        await mat.gotoIndex();
        await mat.expectLoaded();

        expect(errors, `Uncaught JS errors on mat overview:\n${errors.join('\n')}`).toEqual([]);
    });

    test('mat interface loads without JS errors', async ({ page }) => {
        const errors = trackPageErrors(page);
        const mat = new MatPage(page);

        await mat.gotoInterface();
        await mat.expectLoaded();

        expect(errors, `Uncaught JS errors on mat interface:\n${errors.join('\n')}`).toEqual([]);
    });
});
