import { type Page } from '@playwright/test';

/**
 * Shared e2e helpers. Extracted so the visual- and realtime-specs reuse the same
 * battle-tested wiring as flows.auth.spec.ts (which keeps its own local copies for
 * historical reasons — not refactored to avoid touching a green spec).
 */

/** Collect uncaught page errors; assert it stays empty as a CSP/Alpine guard. */
export function trackPageErrors(page: Page): string[] {
    const errors: string[] = [];
    page.on('pageerror', (err) => errors.push(err.message));
    return errors;
}

/**
 * Abort external CDN scripts (pusher, sortable, qrcode, ...). On CI / sandboxed
 * runners these synchronous <script src> tags can hang ~60s when the CDN is
 * unreachable, stalling DOMContentLoaded. Realtime code guards
 * `typeof Pusher === 'undefined'`, so failing them fast is harmless for static
 * rendering (visual snapshots don't need realtime — the realtime spec serves
 * Pusher locally instead, see realtime.spec.ts).
 */
export async function blockExternalCdn(page: Page): Promise<void> {
    const hosts = ['js.pusher.com', 'cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'unpkg.com'];
    await page.route(
        (url) => hosts.some((h) => url.hostname === h),
        (route) => route.abort(),
    );
}

/**
 * Abort EVERY non-local request (anything not on 127.0.0.1/localhost). Stronger
 * than blockExternalCdn's host allowlist: the Alpine-heavy organisator pages
 * (poule/eliminatie, spreker) load synchronous external scripts that intermittently
 * hang ~60s in the sandbox and stall DOMContentLoaded → page.goto timeout. For
 * deterministic visual snapshots we want zero external dependencies anyway, so kill
 * them all. A failed/aborted <script src> still lets DOMContentLoaded fire.
 */
export async function blockAllExternal(page: Page): Promise<void> {
    const local = new Set(['127.0.0.1', 'localhost']);
    await page.route(
        (url) => !local.has(url.hostname),
        (route) => route.abort(),
    );
}

/**
 * Wait until cspActions has loaded and flushed its registrations. Under strict
 * CSP ('strict-dynamic') the bundle is injected async, so interactions fired
 * right after domcontentloaded can be lost. Gate on the marker csp-actions.js
 * sets after flushing.
 */
export async function waitForCspReady(page: Page): Promise<void> {
    await page.waitForFunction(() => window.cspActions && window.cspActions.__ready === true, null, {
        timeout: 30_000,
    });
}
