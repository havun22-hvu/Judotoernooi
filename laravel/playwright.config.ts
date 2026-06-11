import { defineConfig, devices } from '@playwright/test';
import { E2E_BASE_URL, E2E_ENV, E2E_PORT, STORAGE_STATE } from './e2e/env';

/**
 * Playwright end-to-end test configuration for JudoToernooi.
 *
 * The `webServer` block boots a real Laravel server (php artisan serve) on a
 * dedicated e2e port, with E2E_ENV injected as process env vars so it talks to
 * the isolated e2e SQLite database and exposes the test-login seam. Assets are
 * built first so tests run against the production-style pipeline, not HMR.
 *
 * Projects:
 *   - setup          → logs in once via the seam, saves the session (auth.setup.ts)
 *   - chromium       → public, no-auth specs (Desktop Chrome)
 *   - mobile-chrome  → public, no-auth specs (Pixel 7)
 *   - authenticated  → organisator flows, reuses the saved session
 *
 * Override the target with E2E_BASE_URL to run against staging (no local server
 * and no auth project then — staging has no seam).
 */
const usesExternalServer = Boolean(process.env.E2E_BASE_URL);

const AUTH_PATTERN = /.*\.auth\.spec\.ts/;
const SETUP_PATTERN = /auth\.setup\.ts/;
// Volunteer-PWA specs bind a device per access code (first-device-wins), so
// they must run in exactly ONE project — otherwise a second project is locked
// out. They run mobile (Pixel 7), matching real volunteer phones.
const PWA_PATTERN = /pwa\.spec\.ts/;

export default defineConfig({
    testDir: './e2e',
    // php artisan serve is single-threaded locally, so serialise to avoid
    // flooding it; CI sets PHP_CLI_SERVER_WORKERS for real concurrency.
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 1,
    workers: 1,
    reporter: process.env.CI
        ? [['github'], ['html', { open: 'never' }]]
        : [['list'], ['html', { open: 'never' }]],
    timeout: 30_000,
    expect: { timeout: 5_000 },

    // DB rebuild + seed (no server needed); runs before the suite.
    globalSetup: usesExternalServer ? undefined : './e2e/global-setup.ts',

    use: {
        baseURL: E2E_BASE_URL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        locale: 'nl-NL',
    },

    projects: [
        // Public, no-auth smoke specs — stable across upgrades, need no data.
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
            testIgnore: [AUTH_PATTERN, SETUP_PATTERN, PWA_PATTERN],
        },
        {
            name: 'mobile-chrome',
            use: { ...devices['Pixel 7'] },
            testIgnore: [AUTH_PATTERN, SETUP_PATTERN, PWA_PATTERN],
        },

        // Data-dependent projects: skipped when targeting an external server
        // (staging has no seeded data and no test-login seam).
        ...(usesExternalServer
            ? []
            : [
                  {
                      name: 'setup',
                      use: { ...devices['Desktop Chrome'] },
                      testMatch: SETUP_PATTERN,
                  },
                  {
                      name: 'authenticated',
                      use: { ...devices['Desktop Chrome'], storageState: STORAGE_STATE },
                      testMatch: AUTH_PATTERN,
                      dependencies: ['setup'],
                  },
                  {
                      name: 'pwa',
                      use: { ...devices['Pixel 7'] },
                      testMatch: PWA_PATTERN,
                  },
              ]),
    ],

    webServer: usesExternalServer
        ? undefined
        : {
              command: `npm run build && php artisan serve --host=127.0.0.1 --port=${E2E_PORT}`,
              url: E2E_BASE_URL,
              timeout: 120_000,
              // Always start our own env-controlled server; never reuse a stray
              // dev server that would run the wrong env / database.
              reuseExistingServer: false,
              env: { ...process.env, ...E2E_ENV, PHP_CLI_SERVER_WORKERS: '4' },
              stdout: 'pipe',
              stderr: 'pipe',
          },
});
