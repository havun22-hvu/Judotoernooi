/**
 * Fixed identifiers seeded by database/seeders/E2eTestSeeder.php. The seam, the
 * setup project and the authenticated specs all assert on these — keep them in
 * sync with the seeder constants.
 */
export const ORG_EMAIL = 'e2e@judotoernooi.test';
export const ORG_SLUG = 'e2e-test-organisator';
export const TOERNOOI_SLUG = 'e2e-test-toernooi';

/** Slug-scoped URL helpers (the app nests everything under the org slug). */
export const dashboardUrl = () => `/${ORG_SLUG}/dashboard`;
export const toernooiUrl = (suffix = '') => `/${ORG_SLUG}/toernooi/${TOERNOOI_SLUG}${suffix}`;

/**
 * Volunteer PWA device-access codes (one per role), seeded by E2eTestSeeder.
 * `role` is the redirect target; `interface` is the URL fragment the device
 * lands on after auto-binding. Note: the toegang entry is NOT under /toernooi/.
 */
export const PWA_ROLES = [
    { role: 'weging', code: 'WEGE00000001', interface: 'weging' },
    { role: 'mat', code: 'MATT00000001', interface: 'mat' },
    { role: 'jurytafel', code: 'JURY00000001', interface: 'jury' },
    { role: 'spreker', code: 'SPRK00000001', interface: 'spreker' },
    { role: 'dojo', code: 'DOJO00000001', interface: 'dojo' },
] as const;

/** Device-binding entry URL: visiting it auto-binds and redirects to the role. */
export const pwaEntryUrl = (code: string) =>
    `/${ORG_SLUG}/${TOERNOOI_SLUG}/toegang/${code}`;
