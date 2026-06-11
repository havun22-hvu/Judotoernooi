import { test, expect, type Page } from '@playwright/test';
import { PWA_ROLES, pwaEntryUrl } from './fixtures';

/**
 * Volunteer PWA smoke flows (mat, weging, jurytafel, spreker, dojo).
 *
 * These do NOT use the organisator session — they authenticate via device
 * binding instead: visiting /{org}/{toernooi}/toegang/{code} auto-binds the
 * current device (first-device-wins) and redirects to the role interface, with
 * the device-token cookie set on the redirect. Each test runs in its own fresh
 * context, so it binds cleanly. Like the rest of the suite, the regression
 * guard is "loads with a heading and no uncaught JS errors" — these Alpine-heavy
 * interfaces are exactly where a CSP violation would surface.
 */

function trackPageErrors(page: Page): string[] {
    const errors: string[] = [];
    page.on('pageerror', (err) => errors.push(err.message));
    return errors;
}

for (const { role, code, interface: iface } of PWA_ROLES) {
    test(`PWA "${role}" binds via toegang code and loads without JS errors`, async ({ page }) => {
        const errors = trackPageErrors(page);

        await page.goto(pwaEntryUrl(code));

        // The seam redirects to the role interface under the same toernooi.
        await expect(page).toHaveURL(new RegExp(`/${iface}/`));
        await expect(page.locator('h1, h2').first()).toBeVisible();

        expect(errors, `Uncaught JS errors on ${role} PWA:\n${errors.join('\n')}`).toEqual([]);
    });
}
