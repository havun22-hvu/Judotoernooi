import { test, expect, type Page } from '@playwright/test';
import { dashboardUrl, toernooiUrl } from './fixtures';

/**
 * Regression guard for the cspActions load-order race under strict CSP.
 *
 * Root cause: script-src uses 'strict-dynamic', so the Vite bundle (which
 * defines window.cspActions in resources/js/csp-actions.js) is injected
 * dynamically and is NOT guaranteed to execute before the inline view scripts
 * that call window.cspActions({...}) on DOMContentLoaded. When the module lost
 * the race the call threw "window.cspActions is not a function" and every button
 * registered through it on that page stayed dead.
 *
 * The fix is a queue-stub in layouts/app.blade.php <head> that defines
 * window.cspActions early as a buffer; csp-actions.js replaces it and flushes
 * the queue. This test FORCES the race by delaying the bundle ~800ms, so it
 * fails loudly if the stub is ever removed.
 *
 * Runs in the `authenticated` project (storageState from auth.setup.ts).
 */

const BUNDLE_DELAY_MS = 800;

/** Delay the app bundle so it reliably loses the race against DOMContentLoaded. */
async function delayAppBundle(page: Page): Promise<void> {
    await page.route('**/build/assets/app-*.js', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, BUNDLE_DELAY_MS));
        await route.continue();
    });
}

const adminPages: Array<{ name: string; url: string }> = [
    { name: 'dashboard', url: dashboardUrl() },
    { name: 'judoka/index', url: toernooiUrl('/judoka') },
    { name: 'blok/index', url: toernooiUrl('/blok') },
];

for (const { name, url } of adminPages) {
    test(`cspActions survives a slow bundle on ${name}`, async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (err) => pageErrors.push(err.message));

        await delayAppBundle(page);
        // domcontentloaded (not 'load'): these pages open a Reverb WebSocket that
        // keeps the network busy, so 'load' is unreliable. The inline view scripts
        // run their window.cspActions() registrations on DOMContentLoaded — while
        // the delayed bundle is still pending — which is exactly the race we force.
        await page.goto(url, { waitUntil: 'domcontentloaded' });

        // Wait for the delayed bundle to execute and flush the buffered queue.
        // The real csp-actions.js sets this marker after flushing; the stub never
        // does — so this also proves the registrations were actually replayed.
        await page.waitForFunction(() => window.cspActions && window.cspActions.__ready === true, null, {
            timeout: 10_000,
        });

        // No "is not a function" must have surfaced during the DCL registrations.
        const raceError = pageErrors.find((e) => e.includes('cspActions is not a function'));
        expect(raceError, `Race surfaced on ${name}:\n${pageErrors.join('\n')}`).toBeUndefined();
    });
}
