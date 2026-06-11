import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright end-to-end test configuration for JudoToernooi.
 *
 * The `webServer` block boots a real Laravel server (php artisan serve) and
 * builds the Vite assets first, so tests run against the production-style
 * asset pipeline rather than the HMR dev server. Locally an already-running
 * server on the same port is reused for speed; CI always starts a fresh one.
 *
 * Override the target with E2E_BASE_URL to run against staging:
 *   E2E_BASE_URL=https://staging.judotournament.org npx playwright test
 */
const PORT = process.env.E2E_PORT ?? '8007';
const BASE_URL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${PORT}`;

// When an external base URL is supplied we must not spin up a local server.
const usesExternalServer = Boolean(process.env.E2E_BASE_URL);

export default defineConfig({
    testDir: './e2e',
    // The local dev target is `php artisan serve`, which is single-threaded.
    // Running specs in parallel floods it and the heavier pages time out, so
    // we serialise with a single worker. On Linux/CI the webServer below sets
    // PHP_CLI_SERVER_WORKERS to allow real concurrency there.
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 1,
    workers: 1,
    reporter: process.env.CI
        ? [['github'], ['html', { open: 'never' }]]
        : [['list'], ['html', { open: 'never' }]],
    timeout: 30_000,
    expect: { timeout: 5_000 },

    use: {
        baseURL: BASE_URL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        locale: 'nl-NL',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'mobile-chrome',
            use: { ...devices['Pixel 7'] },
        },
    ],

    webServer: usesExternalServer
        ? undefined
        : {
              command: `npm run build && php artisan serve --host=127.0.0.1 --port=${PORT}`,
              url: BASE_URL,
              timeout: 120_000,
              reuseExistingServer: !process.env.CI,
              // Lets PHP's built-in server handle concurrent requests on
              // platforms that support it (Linux/macOS); ignored on Windows.
              env: { PHP_CLI_SERVER_WORKERS: '4' },
              stdout: 'pipe',
              stderr: 'pipe',
          },
});
