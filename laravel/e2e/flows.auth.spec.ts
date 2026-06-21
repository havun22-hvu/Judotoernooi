import { test, expect, type Page } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
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

/**
 * POST JSON from inside the page via fetch, so the same-origin session cookie is
 * sent automatically (page.request did not share the session reliably). The CSRF
 * token is read from the page's csrf-token meta. Call after navigating to a page
 * that carries that meta (e.g. the dashboard). Omit `body` for a bodyless POST.
 */
function postJson(page: Page, url: string, body?: unknown): Promise<{ status: number; text: string }> {
    return page.evaluate(
        async ({ url, body }) => {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: body === undefined ? undefined : JSON.stringify(body),
            });
            return { status: res.status, text: await res.text() };
        },
        { url, body },
    );
}

/** Read the dynamic ids E2eTestSeeder wrote (poule/match/judoka ids). */
function seededIds(): any {
    return JSON.parse(fs.readFileSync(path.join(process.cwd(), 'database/e2e-ids.json'), 'utf-8'));
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

test.describe('Uitslag → poulestand (HTTP)', () => {
    test('registering a win updates the standings (wp=2)', async ({ page }) => {
        test.slow();
        // IDs of the seeded poule + first match, written by E2eTestSeeder (the
        // auto-increment ids aren't known up front). This runs before the
        // poule-generation test, which deletes the seeded poule.
        const ids = seededIds();

        await blockExternalCdn(page);
        await page.goto(dashboardUrl(), { waitUntil: 'domcontentloaded' });

        // Register a decisive win for the white judoka of the first match.
        const uitslag = await postJson(page, toernooiUrl('/mat/uitslag'), {
            wedstrijd_id: ids.wedstrijdId,
            winnaar_id: ids.judokaWitId,
            score_wit: 10,
            score_blauw: 0,
            uitslag_type: 'beslissing',
        });
        expect(uitslag.status, `uitslag ${uitslag.status}: ${uitslag.text.slice(0, 200)}`).toBeLessThan(400);

        // The standings must now show exactly one judoka on 2 win-points, on top.
        const stand = await postJson(page, toernooiUrl('/spreker/standings'), { poule_id: ids.pouleId });
        expect(stand.status, `standings ${stand.status}: ${stand.text.slice(0, 200)}`).toBeLessThan(400);
        const body = JSON.parse(stand.text);

        expect(Array.isArray(body.standings)).toBe(true);
        expect(body.standings[0]?.wp, JSON.stringify(body.standings).slice(0, 200)).toBe(2);
        expect(body.standings.filter((s: { wp: number }) => s.wp === 2)).toHaveLength(1);
    });
});

test.describe('Eliminatie winnaar-doorschuiven (HTTP)', () => {
    test('winners of the half-finals advance into the final', async ({ page }) => {
        test.slow();
        const { eliminatie } = seededIds();

        await blockExternalCdn(page);
        await page.goto(dashboardUrl(), { waitUntil: 'domcontentloaded' });

        // Win both A-group half-finals (winner = the white judoka of each). The
        // mat/uitslag endpoint runs EliminatieService::verwerkUitslag, which must
        // push each winner into the final's slot.
        for (const hf of eliminatie.halveFinales) {
            const res = await postJson(page, toernooiUrl('/mat/uitslag'), {
                wedstrijd_id: hf.id,
                winnaar_id: hf.witId,
                uitslag_type: 'ippon',
            });
            expect(res.status, `halve-finale ${res.status}: ${res.text.slice(0, 200)}`).toBeLessThan(400);
        }

        // Score the final with the first half-final's winner. The endpoint rejects
        // a winner who isn't one of the match's two judokas — so this only
        // succeeds if that judoka was actually advanced into the final. That is
        // the advancement assertion.
        const finale = await postJson(page, toernooiUrl('/mat/uitslag'), {
            wedstrijd_id: eliminatie.finaleId,
            winnaar_id: eliminatie.halveFinales[0].witId,
            uitslag_type: 'ippon',
        });
        expect(
            finale.status,
            `final winner not advanced (status ${finale.status}): ${finale.text.slice(0, 200)}`,
        ).toBeLessThan(400);
    });
});

test.describe('Weging (HTTP)', () => {
    test('a valid weigh-in succeeds; under 15kg is refused', async ({ page }) => {
        test.slow();
        const ids = seededIds();
        await blockExternalCdn(page);
        await page.goto(dashboardUrl(), { waitUntil: 'domcontentloaded' });

        // A valid weigh-in is accepted. (Re-weighing a seeded judoka with a normal
        // weight is harmless — it stays present and in its poule.)
        const ok = await postJson(page, toernooiUrl(`/weging/${ids.judokaBlauwId}/registreer`), { gewicht: 28 });
        expect(ok.status, `weging ${ok.status}: ${ok.text.slice(0, 200)}`).toBeLessThan(400);
        expect(JSON.parse(ok.text).success).toBe(true);

        // Under 15kg (but not 0, which means "absent") is refused without saving.
        const tooLight = await postJson(page, toernooiUrl(`/weging/${ids.judokaBlauwId}/registreer`), { gewicht: 10 });
        expect(JSON.parse(tooLight.text).success).toBe(false);
    });
});

test.describe('Poule verplaatsen — kleurbeurt (HTTP)', () => {
    test('moving a poule keeps green on the old mat and clears yellow/blue', async ({ page }) => {
        test.slow();
        const ids = seededIds();
        await blockExternalCdn(page);
        await page.goto(dashboardUrl(), { waitUntil: 'domcontentloaded' });

        const matState = async () => {
            const res = await postJson(page, toernooiUrl('/mat/wedstrijden'), {
                blok_id: ids.blokId,
                mat_id: ids.mat1Id,
            });
            expect(res.status, `mat-state ${res.status}: ${res.text.slice(0, 200)}`).toBeLessThan(400);
            return JSON.parse(res.text).mat;
        };

        // Precondition: green/yellow/blue are all set on mat 1.
        const before = await matState();
        expect(before.actieve_wedstrijd_id).toBe(ids.groenWedstrijdId);
        expect(before.volgende_wedstrijd_id).not.toBeNull();
        expect(before.gereedmaken_wedstrijd_id).not.toBeNull();

        // Move the poule to mat 2.
        const move = await postJson(page, toernooiUrl('/blok/verplaats-poule'), {
            poule_id: ids.pouleId,
            mat_id: ids.mat2Id,
        });
        expect(move.status, `verplaats ${move.status}: ${move.text.slice(0, 200)}`).toBeLessThan(400);

        // The running match (green) stays on the old mat so it can finish there;
        // only yellow/blue of the departed poule clear. This is the kleurbeurt fix.
        const after = await matState();
        expect(after.actieve_wedstrijd_id, 'green must stay on the old mat').toBe(ids.groenWedstrijdId);
        expect(after.volgende_wedstrijd_id, 'yellow must clear').toBeNull();
        expect(after.gereedmaken_wedstrijd_id, 'blue must clear').toBeNull();
    });
});

test.describe('Poule-generatie (HTTP)', () => {
    test('generating poules yields a valid response with a problemen key', async ({ page }) => {
        test.slow();
        // Use the lightweight dashboard for the CSRF token (it carries the
        // csrf-token meta) — the data-heavy judoka/poule pages are slow to load.
        await blockExternalCdn(page);
        await page.goto(dashboardUrl(), { waitUntil: 'domcontentloaded' });

        // Generate poules from the seeded (categorised) pupillen.
        const gen = await postJson(page, toernooiUrl('/poule/genereer'));
        expect(gen.status, `genereer ${gen.status}: ${gen.text.slice(0, 200)}`).toBeLessThan(400);

        // Verify returns the poule-rules report. The `problemen` key MUST be on
        // every verify response (poule-rules check) — 13 PHPUnit tests guard it
        // server-side; this guards it through the real HTTP stack.
        const ver = await postJson(page, toernooiUrl('/poule/verifieer'));
        expect(ver.status, `verifieer ${ver.status}: ${ver.text.slice(0, 200)}`).toBeLessThan(400);
        const body = JSON.parse(ver.text);

        expect(body).toHaveProperty('problemen');
        expect(Array.isArray(body.problemen)).toBe(true);
        // The five clustered pupillen must produce at least one poule with matches.
        expect(body.totaal_poules, JSON.stringify(body).slice(0, 200)).toBeGreaterThanOrEqual(1);
        expect(body.totaal_wedstrijden).toBeGreaterThan(0);
    });
});
