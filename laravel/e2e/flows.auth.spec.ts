import { test, expect, type Page } from '@playwright/test';
import { toernooiUrl, dashboardUrl } from './fixtures';

/**
 * Functional UI flows for the organisator — beyond "the page loads", these drive
 * real interactions (open a modal, fill a form, submit) and assert the outcome.
 * This is the coverage PHPUnit cannot give: it exercises the actual browser
 * wiring (cspActions delegation, Alpine, form submit) that a CSP/Alpine
 * regression would silently break.
 *
 * Runs in the `authenticated` project (storageState from auth.setup.ts).
 */

function trackPageErrors(page: Page): string[] {
    const errors: string[] = [];
    page.on('pageerror', (err) => errors.push(err.message));
    return errors;
}

/**
 * Abort external CDN scripts (pusher, sortable, qrcode, ...). On CI / sandboxed
 * runners these synchronous <script src> tags can hang ~60s when the CDN is
 * unreachable, stalling DOMContentLoaded and the bundle. The realtime/chat code
 * guards `typeof Pusher === 'undefined'`, so failing them fast is harmless for
 * functional flows (which don't need realtime).
 */
async function blockExternalCdn(page: Page): Promise<void> {
    const hosts = ['js.pusher.com', 'cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'unpkg.com'];
    await page.route(
        (url) => hosts.some((h) => url.hostname === h),
        (route) => route.abort(),
    );
}

/**
 * Wait until cspActions has loaded and flushed its registrations. Under strict
 * CSP ('strict-dynamic') the bundle is injected async, so a click fired right
 * after domcontentloaded can be lost (the delegation listener isn't attached
 * yet). Real users are slow enough; the test isn't — so gate interactions on the
 * marker the real csp-actions.js sets after flushing.
 */
async function waitForCspReady(page: Page): Promise<void> {
    await page.waitForFunction(() => window.cspActions && window.cspActions.__ready === true, null, {
        timeout: 30_000,
    });
}

test.describe('Judoka beheer (UI)', () => {
    test('add-judoka button opens the modal and the form submits', async ({ page }) => {
        test.slow();
        const errors = trackPageErrors(page);
        await blockExternalCdn(page);
        await page.goto(toernooiUrl('/judoka'), { waitUntil: 'domcontentloaded' });
        await waitForCspReady(page);

        // Regression guard for the "dead add-judoka button" bug: its cspActions
        // handler used to be registered inside the stambestand @if, so it never
        // ran for organisators without a stambestand. Clicking must open the modal.
        await page.locator('[data-action="open-add-judoka"]').first().click();

        const modal = page.locator('#addJudokaModal');
        await expect(modal).toBeVisible();

        await modal.locator('input[name="naam"]').fill(`E2E Judoka ${Date.now()}`);
        await modal.locator('input[name="geboortejaar"]').fill('2015');
        await modal.locator('select[name="geslacht"]').selectOption('M');
        await modal.locator('input[name="gewicht"]').fill('30');

        // The submit goes through a full POST; we land back on the judoka index.
        // (The created judoka is intentionally not asserted in the list — the list
        // filters out incomplete judokas, and the seed tournament has no age
        // categories, so a minimal judoka is hidden. The modal opening + clean
        // submit is the regression guard here.)
        await modal.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(/\/judoka/);

        expect(errors, `Uncaught JS errors:\n${errors.join('\n')}`).toEqual([]);
    });
});

test.describe('Poule-generatie (HTTP)', () => {
    test('generating poules yields a valid response with a problemen key', async ({ page }) => {
        test.slow();
        // Use the lightweight dashboard for the CSRF token (it carries the
        // csrf-token meta) — the data-heavy judoka/poule pages are slow to load.
        // POST from inside the page via fetch so the same-origin session cookie is
        // sent automatically (page.request did not share the session reliably).
        await blockExternalCdn(page);
        await page.goto(dashboardUrl(), { waitUntil: 'domcontentloaded' });

        const post = (path: string) =>
            page.evaluate(async (url) => {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN':
                            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                        Accept: 'application/json',
                    },
                });
                return { status: res.status, text: await res.text() };
            }, path);

        // Generate poules from the seeded (categorised) pupillen.
        const gen = await post(toernooiUrl('/poule/genereer'));
        expect(gen.status, `genereer ${gen.status}: ${gen.text.slice(0, 200)}`).toBeLessThan(400);

        // Verify returns the poule-rules report. The `problemen` key MUST be on
        // every verify response (poule-rules check) — 13 PHPUnit tests guard it
        // server-side; this guards it through the real HTTP stack.
        const ver = await post(toernooiUrl('/poule/verifieer'));
        expect(ver.status, `verifieer ${ver.status}: ${ver.text.slice(0, 200)}`).toBeLessThan(400);
        const body = JSON.parse(ver.text);

        expect(body).toHaveProperty('problemen');
        expect(Array.isArray(body.problemen)).toBe(true);
        // The five clustered pupillen must produce at least one poule with matches.
        expect(body.totaal_poules, JSON.stringify(body).slice(0, 200)).toBeGreaterThanOrEqual(1);
        expect(body.totaal_wedstrijden).toBeGreaterThan(0);
    });
});
