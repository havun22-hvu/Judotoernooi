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
npm run e2e            # headless, starts its own server on :8007
npm run e2e:ui         # interactive UI mode (great for debugging)
npm run e2e:report      # open the HTML report of the last run
```

The config (`playwright.config.ts`) boots the app itself via `webServer`:
it runs `npm run build` (production assets) then `php artisan serve` on port
`8007`. An already-running dev server on that port is **reused** locally for
speed; CI always starts a fresh one.

### Run against staging instead of a local server

```bash
E2E_BASE_URL=https://staging.judotournament.org npm run e2e
```

When `E2E_BASE_URL` is set, Playwright does **not** start a local server.

## Layout

| Path                     | Purpose                                              |
| ------------------------ | ---------------------------------------------------- |
| `e2e/*.spec.ts`          | Test specs                                            |
| `e2e/pages/*.ts`         | Page Objects (selectors live here, not in specs)     |
| `playwright.config.ts`   | Config: projects, webServer, reporters               |

Artifacts (`test-results/`, `playwright-report/`, `playwright/.cache/`) are
git-ignored.

## Current coverage

- **Public pages** — home, help, legal pages, sitemap: 200 status, visible
  heading, and **no uncaught JS errors** (the JS-error assertion is the
  regression guard for Alpine CSP violations).

These run without seeded data or auth, so they stay stable across upgrades.

## Next steps (authenticated flows)

Tournament-management flows need an authenticated session and seeded data.
The durable pattern is a dedicated e2e database plus a test-only login seam
(guarded by `app()->environment()`), so flows don't depend on magic-link email.
Add that before writing organisator/coach/mat flow tests.
