import { test } from '@playwright/test';
import { trackCspViolations, expectNoCspViolations } from './csp';

/**
 * CSP regression guard for no-auth pages. The strict CSP (script-src with a
 * nonce + 'strict-dynamic', no 'unsafe-inline') runs in every non-local
 * environment, including the e2e `testing` server. A missing nonce on a
 * <script>/<link> (the Vite bundle, an external CDN, an inline handler) is a
 * CSP *console error*, not a `pageerror`, so this listens to the console.
 */
const publicPages = ['/', '/help', '/algemene-voorwaarden', '/login'];

for (const path of publicPages) {
    test(`no CSP violations on ${path}`, async ({ page }) => {
        const violations = trackCspViolations(page);

        // 'load' (not networkidle): home/public pages open a Reverb WebSocket
        // that keeps the network busy; 'load' still fires after scripts attempt
        // to load, which is when CSP violations surface.
        await page.goto(path, { waitUntil: 'load' });

        expectNoCspViolations(violations, path);
    });
}
