import { test } from '@playwright/test';
import { trackCspViolations, expectNoCspViolations } from './csp';
import { toernooiUrl, dashboardUrl } from './fixtures';

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
const adminPages: Array<{ name: string; url: string }> = [
    { name: 'dashboard', url: dashboardUrl() },
    { name: 'wedstrijddag/poules', url: toernooiUrl('/wedstrijddag/poules') },
    { name: 'poule/index', url: toernooiUrl('/poule') },
    { name: 'judoka/index', url: toernooiUrl('/judoka') },
    { name: 'blok/index', url: toernooiUrl('/blok') },
];

for (const { name, url } of adminPages) {
    test(`no CSP violations on ${name}`, async ({ page }) => {
        const violations = trackCspViolations(page);
        const pageErrors: string[] = [];
        page.on('pageerror', (err) => pageErrors.push(err.message));

        await page.goto(url, { waitUntil: 'load' });

        expectNoCspViolations(violations, name);
        // The data-action registration runs on DOMContentLoaded; a missing
        // cspActions helper or bad callback would throw here.
        if (pageErrors.length) {
            throw new Error(`JS errors on ${name}:\n${pageErrors.join('\n')}`);
        }
    });
}
