import { defineConfig, devices } from '@playwright/test';
import { E2E_ENV, STORAGE_STATE } from './e2e/env';
import {
    REVERB_APP_ID,
    REVERB_KEY,
    REVERB_SECRET,
    RT_APP_PORT,
    RT_REVERB_PORT,
    RT_BASE_URL,
} from './e2e/realtime.env';

/**
 * Realtime cross-device e2e — runs Reverb FOR REAL (the main suite sets
 * BROADCAST_CONNECTION=null). Proves the chain that actually breaks in production
 * when Reverb hiccups: a score POST → MatUpdate broadcast → Reverb → a subscribed
 * browser websocket client receives it.
 *
 * Separate config (not a project in playwright.config.ts) on purpose: realtime is
 * inherently flakier (ws timing, reverb startup) and must NOT destabilise the green
 * baseline suite. Run it explicitly: `npm run e2e:realtime`.
 *
 * Two web servers: Reverb (ws) on RT_REVERB_PORT and the app on RT_APP_PORT, both
 * with matching test Reverb credentials. allowed_origins '*' so the spec's
 * about:blank Pusher client (Origin: null) may connect.
 */
const RT_ENV: Record<string, string> = {
    ...E2E_ENV,
    BROADCAST_CONNECTION: 'reverb',
    REVERB_APP_ID,
    REVERB_APP_KEY: REVERB_KEY,
    REVERB_APP_SECRET: REVERB_SECRET,
    // The app PUBLISHES to reverb here:
    REVERB_HOST: '127.0.0.1',
    REVERB_PORT: RT_REVERB_PORT,
    REVERB_SCHEME: 'http',
    // The reverb server BINDS here:
    REVERB_SERVER_HOST: '127.0.0.1',
    REVERB_SERVER_PORT: RT_REVERB_PORT,
    REVERB_ALLOWED_ORIGINS: '*',
};

export default defineConfig({
    testDir: './e2e',
    testMatch: /realtime\.spec\.ts/,
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: [['list']],
    timeout: 90_000,
    expect: { timeout: 20_000 },

    globalSetup: './e2e/global-setup.ts',

    use: {
        baseURL: RT_BASE_URL,
        locale: 'nl-NL',
        trace: 'on-first-retry',
        video: 'retain-on-failure',
    },

    projects: [
        { name: 'setup', use: { ...devices['Desktop Chrome'] }, testMatch: /auth\.setup\.ts/ },
        {
            name: 'realtime',
            use: {
                ...devices['Desktop Chrome'],
                storageState: STORAGE_STATE,
                // Context B subscribes from about:blank (an untrusted context) to a
                // local 127.0.0.1 ws — Chrome's Private/Local Network Access check
                // blocks that (net::ERR_BLOCKED_BY_LOCAL_NETWORK_ACCESS_CHECKS).
                // Disable it for this run; in production the page and Reverb share an
                // origin behind nginx, so the check never fires there.
                launchOptions: {
                    args: ['--disable-features=LocalNetworkAccessChecks,PrivateNetworkAccessChecks'],
                },
            },
            testMatch: /realtime\.spec\.ts/,
            dependencies: ['setup'],
        },
    ],

    webServer: [
        {
            command: `php artisan reverb:start --host=127.0.0.1 --port=${RT_REVERB_PORT}`,
            port: Number(RT_REVERB_PORT),
            reuseExistingServer: false,
            env: { ...process.env, ...RT_ENV },
            stdout: 'pipe',
            stderr: 'pipe',
            timeout: 60_000,
        },
        {
            command: `npm run build && php artisan serve --host=127.0.0.1 --port=${RT_APP_PORT}`,
            url: RT_BASE_URL,
            reuseExistingServer: false,
            env: { ...process.env, ...RT_ENV, PHP_CLI_SERVER_WORKERS: '4' },
            stdout: 'pipe',
            stderr: 'pipe',
            timeout: 120_000,
        },
    ],
});
