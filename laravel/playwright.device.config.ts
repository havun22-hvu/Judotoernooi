import { defineConfig } from '@playwright/test';

/**
 * STUB — real-device testing via BrowserStack. NOT activated (no credentials in
 * this repo). It exists as a ready starting point for the manual device sweep in
 * docs/3-DEVELOPMENT/DEVICE-TEST-CHECKLIST.md, so wiring real devices later is a
 * config + secret away, not a from-scratch build.
 *
 * Why a separate config: real devices run against a deployed URL (staging), not a
 * local `php artisan serve`. There is no test-login seam on staging, so only the
 * public, no-auth specs are meaningful here (home, publiek, scoreboard-live).
 *
 * Activate:
 *   1. Sign up for BrowserStack Automate, set env vars:
 *        BROWSERSTACK_USERNAME, BROWSERSTACK_ACCESS_KEY
 *   2. Point at the target:  E2E_BASE_URL=https://staging.judotournament.org
 *   3. Run:  npx playwright test --config=playwright.device.config.ts
 *
 * Playwright connects to BrowserStack's cloud over CDP (`connectOptions.wsEndpoint`)
 * with a `caps` payload describing the real device. See BrowserStack's Playwright
 * docs for the exact capability schema; the devices below are illustrative.
 */

const USER = process.env.BROWSERSTACK_USERNAME;
const KEY = process.env.BROWSERSTACK_ACCESS_KEY;
const BASE_URL = process.env.E2E_BASE_URL ?? 'https://staging.judotournament.org';

if (!USER || !KEY) {
    // Fail loud and early instead of silently running nothing.
    throw new Error(
        'playwright.device.config.ts is a stub: set BROWSERSTACK_USERNAME and ' +
            'BROWSERSTACK_ACCESS_KEY (and optionally E2E_BASE_URL) to run real-device tests. ' +
            'See docs/3-DEVELOPMENT/DEVICE-TEST-CHECKLIST.md.',
    );
}

/** Build a BrowserStack CDP endpoint for a given real-device capability set. */
const wsEndpoint = (caps: Record<string, unknown>) =>
    `wss://cdp.browserstack.com/playwright?caps=${encodeURIComponent(JSON.stringify(caps))}`;

const commonCaps = {
    'browserstack.username': USER,
    'browserstack.accessKey': KEY,
    project: 'JudoToernooi',
    build: 'device-sweep',
};

export default defineConfig({
    testDir: './e2e',
    // Only public, no-auth specs make sense against staging (no login seam).
    testMatch: /(home|public-pages|csp)\.spec\.ts/,
    fullyParallel: false,
    workers: 1,
    retries: 1,
    reporter: [['list'], ['html', { open: 'never' }]],
    timeout: 60_000,
    use: { baseURL: BASE_URL, locale: 'nl-NL' },

    projects: [
        {
            name: 'pixel-8-android',
            use: {
                connectOptions: {
                    wsEndpoint: wsEndpoint({
                        ...commonCaps,
                        osVersion: '14.0',
                        deviceName: 'Google Pixel 8',
                        realMobile: 'true',
                        name: 'Pixel 8 — public smoke',
                    }),
                },
            },
        },
        {
            name: 'iphone-15-ios',
            use: {
                connectOptions: {
                    wsEndpoint: wsEndpoint({
                        ...commonCaps,
                        osVersion: '17',
                        deviceName: 'iPhone 15',
                        realMobile: 'true',
                        name: 'iPhone 15 — public smoke',
                    }),
                },
            },
        },
    ],
});
