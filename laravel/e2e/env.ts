import * as path from 'path';
import { fileURLToPath } from 'url';

// ESM (package.json "type": "module") has no __dirname.
const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Shared configuration for the e2e environment, imported by both
 * playwright.config.ts and global-setup.ts so the webServer and the CLI
 * migrate/seed steps agree on exactly one database and one set of flags.
 *
 * Isolation strategy: we do NOT write a .env.e2e file. Instead these values
 * are injected as real process environment variables, which Laravel's env()
 * reads with precedence over the .env file. The developer's dev database and
 * .env stay untouched; everything inherited from .env (e.g. APP_KEY) is reused.
 */

// Absolute path so it resolves identically from any working directory.
export const E2E_DB = path.resolve(__dirname, '../database/e2e.sqlite');

// Saved organisator session, written by the setup project, reused by the
// authenticated project. Kept here (not in auth.setup.ts) so playwright.config
// can import it without pulling in the setup test registration.
export const STORAGE_STATE = path.resolve(__dirname, '.auth/organisator.json');

// A dedicated port keeps the e2e server clear of a dev `artisan serve :8007`.
export const E2E_PORT = process.env.E2E_PORT ?? '8008';
export const E2E_BASE_URL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${E2E_PORT}`;

/** Environment overrides handed to both `artisan` (seed) and `artisan serve`. */
export const E2E_ENV: Record<string, string> = {
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: E2E_DB,
    E2E_LOGIN: '1',
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    BROADCAST_CONNECTION: 'null',
    LOG_CHANNEL: 'stderr',
    TELESCOPE_ENABLED: 'false',
};
