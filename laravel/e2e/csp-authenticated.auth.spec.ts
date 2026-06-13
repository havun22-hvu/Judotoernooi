import { test } from '@playwright/test';
import { trackCspViolations, expectNoCspViolations } from './csp';
import { toernooiUrl } from './fixtures';

/**
 * CSP regression guard for the authenticated admin/wedstrijddag flows.
 *
 * The strict CSP (script-src nonce + 'strict-dynamic', no 'unsafe-inline')
 * blocks every vanilla inline event handler (onclick=, onchange=, ...). These
 * views were migrated to CSP-safe event delegation (data-action + cspActions).
 * A regression — a re-introduced inline handler, or a missing nonce — surfaces
 * as a CSP *console error*; a broken delegation registration surfaces as a
 * pageerror. This guards both on load.
 *
 * Runs in the `authenticated` project (storageState from auth.setup.ts).
 */
const adminPages: Array<{ name: string; path: string }> = [
    { name: 'wedstrijddag/poules', path: '/wedstrijddag/poules' },
];

for (const { name, path } of adminPages) {
    test(`no CSP violations on ${name}`, async ({ page }) => {
        const violations = trackCspViolations(page);
        const pageErrors: string[] = [];
        page.on('pageerror', (err) => pageErrors.push(err.message));

        await page.goto(toernooiUrl(path), { waitUntil: 'load' });

        expectNoCspViolations(violations, name);
        // The data-action registration runs on DOMContentLoaded; a missing
        // cspActions helper or bad callback would throw here.
        if (pageErrors.length) {
            throw new Error(`JS errors on ${name}:\n${pageErrors.join('\n')}`);
        }
    });
}
