import { execSync } from 'child_process';
import * as fs from 'fs';
import * as path from 'path';
import { fileURLToPath } from 'url';
import { E2E_DB, E2E_ENV } from './env';

// ESM (package.json "type": "module") has no __dirname.
const here = path.dirname(fileURLToPath(import.meta.url));
const laravelRoot = path.resolve(here, '..');

/**
 * Builds a clean e2e database before the suite runs: drops the old SQLite file,
 * recreates it empty, migrates and seeds it. Pure CLI work — it needs no running
 * server, so it can safely overlap with webServer startup. The login step that
 * does need the server lives in auth.setup.ts (a setup project) instead.
 */
async function globalSetup(): Promise<void> {
    // eslint-disable-next-line no-console
    console.log(`[e2e] Rebuilding database at ${E2E_DB}`);

    if (fs.existsSync(E2E_DB)) {
        fs.unlinkSync(E2E_DB);
    }
    fs.writeFileSync(E2E_DB, '');

    const opts = {
        cwd: laravelRoot,
        stdio: 'inherit' as const,
        env: { ...process.env, ...E2E_ENV },
    };

    execSync('php artisan migrate --force', opts);
    execSync('php artisan db:seed --class=E2eTestSeeder --force', opts);
}

export default globalSetup;
