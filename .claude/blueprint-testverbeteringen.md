---
title: Blueprint — 4 testverbeteringen
type: claude
scope: judotoernooi
last_updated: 2026-06-22
status: AFGEROND (22-06-2026)
---

# Blueprint — 4 testverbeteringen

## Eindresultaat (22-06-2026) — alle 4 gebouwd + gecommit op main
- **#2 Infection** (`843f442d`): baseline MSI **47%** bij 91% coverage op 4 kern-services.
  Windows initiële-run-bug omzeild via twee-staps `composer infection`
  (coverage → `--skip-initial-tests`). Doc: `docs/3-DEVELOPMENT/MUTATION-TESTING.md`.
- **#3 Visual** (`78ac91d4`): `visual.auth.spec.ts` — scorebord-LCD + spreker groen/stabiel
  (3 passed, 23s). **Bracket bewust uitgesloten**: poule/eliminatie rendert intermitterend
  nooit klaar onder de single-threaded PHP-dev-server + `.bracket-container` alleen in
  gegenereerde-staat (niet betrouwbaar in seed). Bracket-gedrag blijft gedekt door de
  eliminatie-flowtest. Baselines per platform (`*-win32.png`).
- **#1 Realtime** (`2fcb2381`): `realtime.spec.ts` + `playwright.realtime.config.ts` —
  Reverb áán, 2 contexts, score-POST → `mat.update` live ontvangen (2 passed, 42s).
  Vondsten: blade leidt ws-poort uit APP_URL-scheme (prod-nginx-aanname) → spec abonneert
  op expliciete poort; Chrome PNA blokkeert about:blank→127.0.0.1 ws → launch-flag.
- **#4 Device** (`2fcb2381`): `DEVICE-TEST-CHECKLIST.md` + inerte BrowserStack-stub.



> Doel: het vangnet versterken op de plekken waar het echt pijn doet —
> realtime (Reverb laat het wel eens afweten), schijnzekerheid in de PHPUnit-suite,
> visuele breuk op fragiele schermen, en echte-device-gedrag.

Henk gaf akkoord op alle 4 (sessie 22-06). Geen `php artisan test` op server (SQLite lokaal).

## Bestaande infra (hergebruiken, niet opnieuw bouwen)
- `e2e/env.ts` → `E2E_ENV` met `BROADCAST_CONNECTION: 'null'` (regel 41) = broadcasts no-op.
- `e2e/global-setup.ts` → rebuild+migrate+seed `database/e2e.sqlite`.
- `playwright.config.ts` → projects (chromium/mobile-chrome/setup/authenticated/pwa),
  `webServer` = `npm run build && php artisan serve :8008` met `E2E_ENV` geïnjecteerd.
- Helpers in `e2e/flows.auth.spec.ts`: `blockExternalCdn`, `waitForCspReady`, `postJson`,
  `seededIds`, `trackPageErrors`. Fixtures: `ORG_SLUG`, `TOERNOOI_SLUG`, `toernooiUrl()`.
- Seeded IDs (`database/e2e-ids.json`): poule 1 (round-robin), eliminatie poule 2
  (halveFinales 11/12 → finale 13), mat1Id=1, mat2Id=2.
- Broadcast: `MatUpdate` (channel `toernooi.{tId}` + `mat.{tId}.{matId}`, event `mat.update`,
  types score/beurt/poule_klaar/bracket) gedispatcht uit `Api/ScoreboardController`.
  Frontend: `partials/mat-updates-listener.blade.php` (rauwe Pusher-JS via `js.pusher.com`)
  → vuurt DOM-events `mat-update`/`mat-score-update`/… op `window`.

---

## 1. Realtime cross-device e2e (Reverb áán) — het grootste gat

**Wat:** twee browser-contexts. Context A POST een score; context B (luistert op
`toernooi.{tId}`) moet de DOM-event `mat-score-update` ontvangen. Dit bewijst de
hele keten HTTP → `MatUpdate::dispatch` → Reverb → Pusher-client → DOM-event —
precies wat stuk gaat als Reverb hapert.

**Aparte suite, niet in de groene hoofdsuite** (realtime is inherent flakier; mag de
baseline niet destabiliseren). Eigen config + `npm run e2e:realtime`.

**Aanpak:**
- Nieuw `playwright.realtime.config.ts`:
  - `webServer` als **array** (Playwright ondersteunt meerdere): (1) `php artisan reverb:start
    --host=127.0.0.1 --port=8085`, (2) `php artisan serve :8009`. Beide met realtime-env.
  - Realtime-env = `E2E_ENV` maar met `BROADCAST_CONNECTION: 'reverb'` +
    `REVERB_HOST=127.0.0.1`, `REVERB_PORT=8085`, `REVERB_SCHEME=http`, en de
    `REVERB_APP_*` uit `.env` (geërfd via `process.env`). Dedicated port 8085 zodat
    een dev-reverb op 8080 niet botst.
  - Eén project, geen CDN-block. **Pusher-JS lokaal serveren:** `page.route('**/js.pusher.com/**')`
    → `route.fulfill()` met een lokaal gecachte `pusher.min.js` (eenmalig opgehaald naar
    `e2e/vendor/pusher.min.js`). Zo geen sandbox-CDN-hang én geen app-wijziging.
- Nieuw `e2e/realtime.spec.ts`:
  1. Context B (organisator-storageState) → mat-interface (laadt mat-updates-listener),
     installeer `window`-listener voor `mat-score-update` als awaitbare promise.
  2. Wacht tot B's Pusher `reverb-connected` window-event vuurt (verbinding staat).
  3. Context A (organisator-storageState) → dashboard → `postJson('/mat/uitslag', …)`
     op de geseede poule-wedstrijd (zoals A2 in bestaande flow).
  4. Assert: B's `mat-score-update`-promise resolved binnen ~10s met juiste `detail`.
- Risico's: Reverb-startup-timing (poll `/` of korte retry vóór tests), Pusher-connect-
  latency (wacht expliciet op `reverb-connected`), broadcast-async (ruime expect-timeout).
  Markeer suite `test.slow()`. Bij hardnekkige flakiness: documenteer als "lokaal draaien,
  niet in CI-gate" — beter een eerlijk los vangnet dan een rode hoofdsuite.

**Acceptatie:** `npm run e2e:realtime` groen lokaal; bewijst dat een score in A live in B
landt. Faalt als Reverb-keten breekt.

## 2. Mutation testing (Infection) — schijnzekerheid in de suite blootleggen

**Wat:** Infection muteert de broncode (`>`→`>=`, `&&`→`||`, return-waarden) en draait
per mutant alléén de dekkende tests. Overlevende mutanten = je test merkt de wijziging
niet → loze assert. Coverage-% zegt niets; dit wel.

**Aanpak:**
- `composer require --dev infection/infection` (dev-only; Henk akkoord op mutation testing).
  Coverage-driver pcov is aanwezig.
- `infection.json5`: scope op de bug-gevoelige kern (niet de hele app — anders uren):
  `source.directories` = `app/Services/CategorieClassifier.php`, `EliminatieService.php`,
  `WegingService.php`, `PouleIndelingService.php` (+ evt. `BracketLayoutService.php`).
  `mutators` = default set. `testFramework: phpunit`, `--threads=4`.
  `tmpDir` op een lokale map; `logs.text` = `tests/infection.log`.
- MSI-drempel: géén harde `--min-msi` in CI eerst — eerst meten, baseline vastleggen.
  Doel-MSI documenteren (bijv. ≥70% op deze kern) als richtlijn, niet als gate.
- Compose-script `composer infection` toevoegen.

**Acceptatie:** `composer infection` draait, produceert MSI-rapport + lijst overlevende
mutanten op de 4 kern-services. Baseline-MSI in doc vastgelegd. (Mutanten zelf fixen =
losse vervolgactie, niet deze blueprint.)

## 3. Visual regression — fragiele schermen pixel-vastleggen

**Wat:** Playwright `toHaveScreenshot()` op de schermen die herhaaldelijk visueel braken:
eliminatie-bracket (CSSOM-positionering via `BracketLayoutService`), scorebord-live (LCD),
spreker-interface. Alleen **desktop** (Pixel7-emulatie blijft onbetrouwbaar voor mobiel).

**Aanpak:**
- Nieuw `e2e/visual.auth.spec.ts` (authenticated project):
  - Bracket: `toernooiUrl('/poule/2/eliminatie')` → wacht render → `toHaveScreenshot('bracket.png')`.
  - Scorebord LCD (publiek): `/${ORG_SLUG}/${TOERNOOI_SLUG}/mat/scoreboard-live/1` →
    `blockExternalCdn` (geen realtime nodig voor statische render) → maskeer de klok/timer
    (dynamisch) met `mask:` → `toHaveScreenshot('scoreboard.png')`.
  - Spreker: `toernooiUrl('/spreker')` → `waitForCspReady` → screenshot.
- Determinisme: `maxDiffPixelRatio` klein; dynamische elementen (tijd, verbindingsbol)
  maskeren; animaties uit via `reducedMotion`/`animations: 'disabled'` in de screenshot-opties.
- Baselines committen (`e2e/visual.auth.spec.ts-snapshots/`), alleen Desktop-Chrome-project.
- `npm run e2e:update-snapshots` script (`playwright test --update-snapshots`).

**Acceptatie:** suite groen tegen verse baselines; een opzettelijke CSS-breuk maakt 'm rood.

## 4. Echt-device sweep — kan ik niet draaien, wél faciliteren

Een fysiek toestel (Henks P10) kan ik niet aansturen. Leverbaar:
- **`docs/3-DEVELOPMENT/DEVICE-TEST-CHECKLIST.md`**: herhaalbare handmatige checklist —
  per ingelogd/PWA-scherm (mat, weging, spreker, dojo, scorebord-LCD, publiek, organisator-
  dashboard) de te checken punten: overflow/zoom, PWA-installknop-overlap (bekend gat,
  z-index, `publiek/index` vs `layouts/app` toast), passkey/biometrie (domeingebonden!),
  realtime-bol groen, oriëntatie. Met invulkolom pass/fail + toestel/OS/datum.
- **BrowserStack-haakje (optioneel):** `playwright.device.config.ts`-stub die tegen
  `E2E_BASE_URL=staging` + BrowserStack-capabilities kan draaien als Henk ooit een account
  koppelt. Niet geactiveerd (geen credentials); puur als startpunt gedocumenteerd.

**Acceptatie:** checklist-doc compleet en bruikbaar; BrowserStack-stub aanwezig + uitgelegd.

---

## Volgorde & commits (atomic, 1 onderdeel = 1+ commits)
1. **Infection** (#2) — meest losstaand, valideert bestaande suite. `feat(test): infection mutation testing`.
2. **Visual regression** (#3) — `feat(test): visual regression op bracket/scorebord/spreker`.
3. **Realtime** (#1) — zwaarst/flakiest, apart. `feat(test): realtime cross-device e2e met Reverb`.
4. **Device-checklist + stub** (#4) — docs. `docs(test): device-test-checklist + browserstack-stub`.

Per stap: groen lokaal vóór commit. Docs (`e2e/README.md`, `docs/3-DEVELOPMENT/`) bijwerken
als onderdeel van de stap, niet apart.

## Risico's (vooraf benoemd)
- Realtime in sandbox = grootste flakiness-risico (Pusher-connect + Reverb-timing). Mitigatie:
  lokaal Pusher serveren, expliciet op `reverb-connected` wachten, ruime timeouts, eigen suite.
- Infection-runtime kan oplopen → strak scopen op 4 services; uitbreiden ná baseline.
- Visual baselines zijn OS/font-gevoelig → pin op één project (Desktop Chrome), maskeer dynamiek.
- `composer require infection` = nieuwe dev-dependency (normaal overleg-plichtig; hier expliciet akkoord).
