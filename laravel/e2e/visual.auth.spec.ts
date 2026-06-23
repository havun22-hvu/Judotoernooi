import { test, expect } from '@playwright/test';
import { toernooiUrl, ORG_SLUG, TOERNOOI_SLUG } from './fixtures';
import { blockAllExternal, waitForCspReady } from './helpers';

/**
 * Visual regression for the screens that break visually and that PHPUnit can't
 * see: the scoreboard-live LCD, the spreker interface, and the eliminatie bracket.
 *
 * Desktop-only (runs in the `authenticated` project = Desktop Chrome). Pixel7
 * emulation is unreliable for real mobile layout, so mobile stays out of visual
 * snapshots — see docs/3-DEVELOPMENT/DEVICE-TEST-CHECKLIST.md for the device sweep.
 *
 * Baselines live in visual.auth.spec.ts-snapshots/ and are committed. Regenerate
 * intentionally with: npm run e2e:update-snapshots.
 *
 * Scope = pixels only. "No JS errors" is the job of the functional/smoke specs
 * (authenticated.auth.spec.ts). We block all external requests to freeze the frame
 * (no live timer/realtime); the realtime path is covered separately
 * (realtime.spec.ts). Animations are disabled and dynamic regions (timer,
 * disconnect overlay) are masked for determinism.
 *
 * Bracket note: the organisator poule/eliminatie page is too heavy to snapshot
 * (it intermittently never fires DOMContentLoaded under the single-threaded PHP
 * dev server). Instead we snapshot the bracket via the lighter PRINT view
 * (noodplan/bracket/{poule}/live → layouts.print), which renders the same
 * server-side bracket layout without the heavy organisator chrome.
 */

// LCD scoreboard is a public route (no /toernooi/ segment), shown on the TV.
const scoreboardUrl = (mat = 1) => `/${ORG_SLUG}/${TOERNOOI_SLUG}/mat/scoreboard-live/${mat}`;

const SNAPSHOT_OPTS = {
    animations: 'disabled' as const,
    // Small tolerance for sub-pixel font/AA differences across machines.
    maxDiffPixelRatio: 0.02,
};

test.describe('Visual regression', () => {
    test('eliminatie bracket (print view) renders stably', async ({ page }) => {
        test.slow();
        await blockAllExternal(page);
        await page.setViewportSize({ width: 1280, height: 720 });
        // Seeded eliminatie poule = id 2 (database/e2e-ids.json). The print view
        // (layouts.print) loads only the local Vite bundle, no external CDN, so it
        // does not hang like the organisator eliminatie page.
        await page.goto(toernooiUrl('/noodplan/bracket/2/live'), { waitUntil: 'commit' });

        const bracket = page.locator('.bracket-page').first();
        await expect(bracket).toBeVisible({ timeout: 30_000 });
        await page.waitForTimeout(500);
        await expect(bracket).toHaveScreenshot('bracket.png', SNAPSHOT_OPTS);
    });

    test('scoreboard-live (LCD) renders stably', async ({ page }) => {
        test.slow();
        await blockAllExternal(page);
        await page.setViewportSize({ width: 1280, height: 720 });
        await page.goto(scoreboardUrl(1), { waitUntil: 'commit' });

        // The static, server-rendered timer is present immediately; wait for it.
        await expect(page.locator('#timer-display')).toBeVisible();
        await expect(page).toHaveScreenshot('scoreboard.png', {
            ...SNAPSHOT_OPTS,
            fullPage: true,
            // Timer counts / disconnect overlay are time-dependent → mask them.
            mask: [page.locator('#timer-display'), page.locator('.disconnect-overlay')],
        });
    });

    test('spreker interface renders stably', async ({ page }) => {
        test.slow();
        await blockAllExternal(page);
        await page.setViewportSize({ width: 1280, height: 720 });
        await page.goto(toernooiUrl('/spreker'), { waitUntil: 'commit' });
        await waitForCspReady(page);
        // Let Alpine settle the initial render before snapshotting.
        await page.waitForTimeout(500);

        await expect(page).toHaveScreenshot('spreker.png', { ...SNAPSHOT_OPTS, fullPage: true });
    });
});
