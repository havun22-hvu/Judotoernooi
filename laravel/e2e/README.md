# End-to-end tests (Playwright)

Browser-level smoke and flow tests for JudoToernooi, complementary to the
PHPUnit suite in `tests/`. PHPUnit covers units, services and HTTP responses;
Playwright drives a **real browser** against a **running app**, so it catches
things PHPUnit cannot: Alpine.js (CSP build) runtime errors, asset-pipeline
breakage, and real rendering/navigation.

## Prerequisites (one-time)

```bash
cd laravel
npm install            # installs @playwright/test (devDependency)
npx playwright install chromium
```

## Running

```bash
cd laravel
npm run e2e                  # headless, starts its own server on :8008
npm run e2e:ui               # interactive UI mode (great for debugging)
npm run e2e:report           # open the HTML report of the last run
npm run e2e:update-snapshots # regenerate the visual-regression baselines
npm run e2e:realtime         # realtime cross-device run (Reverb on, separate config)
```

### Visual regression (`visual.auth.spec.ts`)

Pixel snapshots of the screens that break visually and that PHPUnit can't see:
the **scoreboard-live LCD** and the **spreker interface**. Desktop-only (Pixel7
emulation is unreliable for mobile). Baselines live in
`e2e/visual.auth.spec.ts-snapshots/` and are committed **per platform**
(`*-win32.png` here); CI on another OS generates its own. An intentional visual
change means regenerating with `e2e:update-snapshots`. All external requests are
blocked to freeze the frame; dynamic regions (timer, disconnect overlay) are
masked. Scope is pixels only — the "no JS errors" guard lives in the functional
specs.

The **eliminatie-bracket is intentionally not snapshotted**: that page is the
heaviest organisator view and intermittently never finishes rendering under the
single-threaded PHP dev server (pre-existing flakiness), and its bracket only
renders in a generated-bracket state not reliably reproducible in the seeded e2e
DB. Its data/behaviour is covered by the eliminatie flow test instead.

### Realtime cross-device (`realtime.spec.ts` + `playwright.realtime.config.ts`)

The only run that starts **Reverb for real** (the rest set
`BROADCAST_CONNECTION=null`). It opens two contexts: one posts a score, the other
subscribes to the public `toernooi.{id}` channel with its own Pusher client (served
from `e2e/vendor/pusher.min.js`, no CDN) and must receive the `mat.update` live.
Separate config + `npm run e2e:realtime` so its inherent ws/reverb-timing flakiness
never destabilises the green baseline suite. Note: the blade listener derives the
ws port from `APP_URL`'s scheme (works only behind the prod nginx proxy), so the
spec subscribes on an explicit port — it still exercises app → Reverb → browser.

### Real-device sweep

Not automatable here (needs a physical phone). See
`docs/3-DEVELOPMENT/DEVICE-TEST-CHECKLIST.md` for the repeatable manual checklist
and `playwright.device.config.ts` for the (inert) BrowserStack starting point.

The config (`playwright.config.ts`) boots the app itself via `webServer`:
it runs `npm run build` (production assets) then `php artisan serve` on port
`8008` (a dedicated e2e port, clear of a dev server on `:8007`). The server
always starts fresh — it must run with the injected e2e environment, so a stray
dev server is never reused.

### Run against staging instead of a local server

```bash
E2E_BASE_URL=https://staging.judotournament.org npm run e2e
```

When `E2E_BASE_URL` is set, Playwright does **not** start a local server.

## Layout

| Path                          | Purpose                                              |
| ----------------------------- | ---------------------------------------------------- |
| `e2e/*.spec.ts`               | Public + volunteer-PWA specs (no organisator session)|
| `e2e/*.auth.spec.ts`          | Authenticated organisator specs                      |
| `e2e/auth.setup.ts`           | Setup project: logs in once, saves the session       |
| `e2e/global-setup.ts`         | Rebuilds + migrates + seeds the e2e database         |
| `e2e/env.ts`                  | Shared e2e port / database / env-var overrides       |
| `e2e/fixtures.ts`             | Seeded slugs + URL helpers                           |
| `e2e/pages/*.ts`              | Page Objects (selectors live here, not in specs)     |
| `playwright.config.ts`        | Config: projects, webServer, reporters               |

Artifacts (`test-results/`, `playwright-report/`, `playwright/.cache/`,
`e2e/.auth/`, `database/e2e.sqlite`) are git-ignored.

## Current coverage

- **Public pages** — home, help, legal pages, sitemap: 200 status, visible
  heading, and **no uncaught JS errors** (the JS-error assertion is the
  regression guard for Alpine CSP violations). Run on Desktop Chrome + Pixel 7.
- **Authenticated organisator flows** — dashboard, open tournament, tournament
  detail, poule overview, mat overview, mat interface: each loads with a visible
  heading and **no uncaught JS errors** (same CSP guard, on the Alpine-heavy
  organisator screens).
- **Volunteer PWAs** — mat, weging, jurytafel, spreker, dojo: each binds via its
  device-access code and loads its interface without JS errors. These use
  device binding, not the organisator session (see below), so they run in the
  no-auth projects.

## How authenticated flows work

No tester logs in by hand and nothing depends on magic-link email:

1. **Isolated database** — `global-setup.ts` deletes/recreates
   `database/e2e.sqlite`, then migrates and seeds it via `artisan`. The
   developer's dev database is never touched.
2. **Deterministic data** — `database/seeders/E2eTestSeeder.php` creates one
   `is_test` organisator (`e2e@judotoernooi.test`), one match-day tournament
   with a blok, a mat, a poule and judokas. Fixed slugs live in `fixtures.ts`.
3. **Test-login seam** — `GET /e2e/login` authenticates that organisator
   without credentials and redirects to its dashboard. It is **only registered**
   in `local`/`testing` with `E2E_LOGIN=1` (see `bootstrap/app.php`); in every
   other environment the route does not exist (404). The controller re-checks
   the same guard. There is no auth-bypass surface in production.
4. **Session reuse** — the `setup` project (`auth.setup.ts`) hits the seam once
   and saves the session to `e2e/.auth/organisator.json`; the `authenticated`
   project reuses it, so specs start already logged in.

Environment isolation is injected as process env vars (see `env.ts`) — there is
**no `.env.e2e` file** and no secret to manage; everything else (e.g. `APP_KEY`)
is inherited from the dev `.env`.

### Volunteer PWAs (mat / weging / jurytafel / spreker / dojo)

These use a different auth model: **device binding**, not the organisator
session. The seeder creates one `DeviceToegang` row per role with a fixed code;
a spec visits `/{org}/{toernooi}/toegang/{code}`, which auto-binds the device
(first-device-wins, sets a `device_token_{id}` cookie) and redirects to the role
interface. Each test runs in its own context, so it binds cleanly — no shared
storageState. Codes live in `fixtures.ts` (`PWA_ROLES`).

### Adding an authenticated spec

Name it `*.auth.spec.ts`, build/extend a Page Object in `e2e/pages/`, and assert
a visible heading plus an empty `pageerror` log. Use the `toernooiUrl()` /
`dashboardUrl()` helpers from `fixtures.ts` for slug-scoped URLs.
