/**
 * Shared constants for the realtime (Reverb-on) e2e run. Imported by both
 * playwright.realtime.config.ts (to configure the app + reverb processes) and
 * realtime.spec.ts (so the in-page Pusher client uses the SAME key/port).
 *
 * Test-only Reverb credentials: we override REVERB_APP_* with fixed values so the
 * spec knows the key without reading .env, and so app+reverb agree deterministically.
 */
export const REVERB_APP_ID = '700700';
export const REVERB_KEY = 'e2ereverbkey';
export const REVERB_SECRET = 'e2ereverbsecret';

// Dedicated ports, clear of a dev reverb (8080) and the main e2e server (8008).
export const RT_APP_PORT = process.env.E2E_RT_PORT ?? '8009';
export const RT_REVERB_PORT = process.env.E2E_RT_REVERB_PORT ?? '8085';
export const RT_BASE_URL = `http://127.0.0.1:${RT_APP_PORT}`;
