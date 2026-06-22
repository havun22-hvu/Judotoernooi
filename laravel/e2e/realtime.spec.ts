import { test, expect, type Page } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { toernooiUrl, dashboardUrl } from './fixtures';
import { REVERB_KEY, RT_REVERB_PORT } from './realtime.env';

/**
 * Realtime cross-device: a score registered by one client must reach another
 * client live, over Reverb. This is the chain that silently dies when Reverb
 * hiccups — invisible to PHPUnit (broadcasts are no-op there) and to the rest of
 * the e2e suite (BROADCAST_CONNECTION=null). Run via playwright.realtime.config.ts.
 *
 * Context A = the authenticated organisator (posts the result).
 * Context B = an independent context that subscribes to the public tournament
 * channel with its OWN Pusher client on about:blank, pointed explicitly at the
 * Reverb port. (The blade listener derives the ws port from APP_URL's scheme — it
 * only resolves to a real port behind the production nginx proxy, so for a local,
 * proxy-less run we subscribe directly. This still exercises app → Reverb → browser.)
 */

function seededIds(): any {
    return JSON.parse(fs.readFileSync(path.join(process.cwd(), 'database/e2e-ids.json'), 'utf-8'));
}

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

test('a score POST is broadcast over Reverb to a subscribed browser client', async ({ page, browser }) => {
    test.slow();
    const ids = seededIds();
    const reverbPort = Number(RT_REVERB_PORT);

    // --- Context B: independent subscriber on about:blank (no CSP, no auth). ---
    const ctxB = await browser.newContext();
    const pageB = await ctxB.newPage();
    await pageB.goto('about:blank');
    await pageB.addScriptTag({ path: path.join(process.cwd(), 'e2e/vendor/pusher.min.js') });

    // Open a Pusher client, expose connection + first-event as awaitable promises.
    await pageB.evaluate(
        ({ key, port, toernooiId }) => {
            const w = window as any;
            const pusher = new w.Pusher(key, {
                wsHost: '127.0.0.1',
                wsPort: port,
                wssPort: port,
                forceTLS: false,
                enabledTransports: ['ws'],
                disableStats: true,
                cluster: 'mt1',
            });
            w.__connected = new Promise((resolve, reject) => {
                pusher.connection.bind('connected', () => resolve(true));
                pusher.connection.bind('failed', () => reject(new Error('pusher connection failed')));
                pusher.connection.bind('unavailable', () =>
                    reject(new Error('pusher connection unavailable')),
                );
            });
            w.__matUpdate = new Promise((resolve) => {
                const ch = pusher.subscribe(`toernooi.${toernooiId}`);
                ch.bind('mat.update', (data: any) => resolve(data));
            });
        },
        { key: REVERB_KEY, port: reverbPort, toernooiId: ids.toernooiId },
    );

    // Gate on a real connection before triggering — proves Reverb is reachable.
    await pageB.evaluate(() => (window as any).__connected);

    // --- Context A: register a decisive win on the seeded match. ---
    await page.goto(dashboardUrl(), { waitUntil: 'domcontentloaded' });
    const res = await postJson(page, toernooiUrl('/mat/uitslag'), {
        wedstrijd_id: ids.wedstrijdId,
        winnaar_id: ids.judokaWitId,
        score_wit: 10,
        score_blauw: 0,
        uitslag_type: 'beslissing',
    });
    expect(res.status, `uitslag ${res.status}: ${res.text.slice(0, 200)}`).toBeLessThan(400);

    // --- B must receive the broadcast live. ---
    const update = await pageB.evaluate(
        ({ timeoutMs }) =>
            Promise.race([
                (window as any).__matUpdate,
                new Promise((_, reject) =>
                    setTimeout(() => reject(new Error('no mat.update broadcast received in time')), timeoutMs),
                ),
            ]),
        { timeoutMs: 20_000 },
    );

    expect(update, 'B received a broadcast').toBeTruthy();
    expect((update as any).type, JSON.stringify(update).slice(0, 200)).toBe('score');

    await ctxB.close();
});
