---
title: JudoToernooi Handover
type: claude
scope: judotoernooi
last_updated: 2026-06-23
---

# JudoToernooi — Handover

> Vul dit aan aan het einde van elke sessie.

## SESSIE 26-06 — auth-UX: oogje, beeldvullende login, magic-link-login

**Prod + staging op `ba9e76dc`.** Drie auth-UX-dingen, gedeployd + geverifieerd:
- **Wachtwoord-oogje op login gefixt** (`266bb97c`): de knop was dood (geen handler) → CSP-veilige
  `data-action="toggle-pw"` (zelfde patroon als setup-password). e2e-klik-test. Registratie heeft géén
  wachtwoordveld (magic-link); de setup-password-pagina had al werkende oogjes.
- **Login beeldvullend op mobiel** (`47b585c0`): full-screen wit + edge-to-edge velden op `<sm`,
  desktop houdt de kaart. CSS-bundle herbouwd.
- **Magic-link-login** (`ba9e76dc`): knop "Inloggen via e-maillink" op het login-scherm. De infra
  bestond al (alleen voor registratie); nieuw is een `login`-type token + `POST login.magic`
  (enumeration-veilig, throttled) → mail → `GET login.magic-verify` logt in. **Migratie**
  `magic_link_tokens.type` enum→string (additief). 4 tests. GEEN session-regenerate (zie #3-revert).

> ⚠️ **PROD MIGRATIE-INCONSISTENTIE (pre-existing, openstaand):** `2026_04_17_create_jobs_table`
> staat op prod als **Pending** terwijl de `jobs`-tabel **al bestaat** → `php artisan migrate --force`
> faalt erop (1050 Table 'jobs' already exists). Daarom de magic-link-migratie **path-specifiek**
> gedraaid. **Elke toekomstige deploy-met-migratie struikelt hierover tot het is rechtgezet.** Fix
> (met Henk afstemmen): de jobs-migratie als uitgevoerd markeren in `migrations` of idempotent maken
> (`Schema::hasTable`). Raakt queue-infra → niet blind doen. Backup vóór deze deploy:
> `judo_toernooi_voor-magic-login-migratie_2026-06-26_06-26-01.sql.gz`.

## SESSIE 25→26-06 (vervolg) — #3 TERUGGEDRAAID + mobiele UI

**Prod + staging op `47b585c0`.** Na de security-deploy bleek tijdens device-test op Henks P10:

- ⛔ **#3 (session-rotatie) TERUGGEDRAAID** (`3ef4c6f2` revert van `652626bc`). De
  `session()->regenerate()` op de login-paden **brak de biometrie/passkey-login op de mobiele PWA**
  — exact de cookie-flow die de dev-comment (`// causes cookie issues`) al noemde en die ik vooraf
  flagde. PHPUnit ving 't niet (browser-cookie-fenomeen). **NIET terugzetten zonder device-rooktest.**
  De session-fixation-winst was verwaarloosbaar (al gedekt door `__Secure-`/secure/httponly/samesite).
  Eventueel later veilig herintroduceren — enkel op de full-page-navigatie-paden, mét device-test.
- **Mobiele UI** (`47b585c0` + homepage-commit): homepage `overflow-x-hidden`+`viewport-fit=cover`;
  **login beeldvullend op mobiel** (full-screen wit + edge-to-edge velden via `sm:`-varianten,
  desktop houdt de gecentreerde kaart).
- **"Te brede pagina" was GEEN code-bug** — het was **"Desktop-site"-modus** in Henks Chrome.
  Geverifieerd met Playwright-meting: homepage én login zijn `overflow=0` op 320/360/412px.
- **CSS-bundle was STALE** — de gecommitte bundle liep achter op de bron (miste o.a.
  `overflow-x-hidden`). Herbouwd + gecommit. **LES: na élke blade-wijziging die Tailwind-classes
  toevoegt → `npm run build` + de nieuwe bundle (+manifest) meecommitten, anders mist de class op prod.**

**OPENSTAAND:**
- [ ] **Biometrie-bevestiging:** werkt passkey/biometrie-login nu na de #3-revert? (Henk testte op P10.)
- [ ] **Passkey registreren** zit in account/`setup-pin`, niet op het login-scherm — verschijnt op
  login pas als het device een passkey heeft. Mogelijk UX-verbetering (duidelijker maken).
- [ ] **Smartphone-PWA scope** (Henk): de PWA hoeft op mobiel alleen QR-scannen + intro/info te
  doen, niet de volle app. Aparte feature — nog te plannen.
- [ ] **CSP/HSTS-hardening** (uit security-sweep, bewust uitgesteld — browser-verificatie nodig).

## DEPLOY 25-06 — security-sweep + sessie-werk LIVE

**Staging + productie beide op `cc90055d`** (was `e1eab29b`/guzzle-deploy 23-06). Fast-forward
`git pull` + post-merge `optimize:clear` + `queue:restart`. **Geen migrations/composer/assets**
(geverifieerd). Prod-backup vóór deploy: `judo_toernooi_voor-security-sweep_2026-06-25_11-52-23.sql.gz`.
Bevat álle sessie-werk sinds 23-06: write-route-validatie (10 + simulate-guard), publiek mobiel
scorebord, doc-rot, en de 6 security-fixes. Smoke (staging→prod): home/login/toernooi-pagina 200,
geen verse errors. Staging als canary gebruikt (groen) vóór prod.

> **Open rooktest (Henk, P10):** de session-rotatie (#3) raakt álle login-paden. Magic-link/PIN
> zijn PHPUnit+staging-geverifieerd, maar de **passkey/QR-flow** (browser-cookie-flow) verdient
> één handmatige login-test op prod — dat is de enige laag die PHPUnit niet dekt. Bij problemen:
> rollback-commit is `652626bc` (alleen #3) of de DB-backup hierboven.

## SESSIE 25-06 — security-sweep (hele app, 4 parallelle audits)

**6 fixes gebouwd + getest, op main. Volledige suite 3583 groen, 0 failures. NIET gedeployd (deploy = cue).**
Audits: autorisatie/IDOR, rate-limiting, auth-hardening/uploads, headers/CSP/data-exposure (agent-severity's
kritisch heringeschat — 2 alarmistische claims ontkracht: `APP_DEBUG=true` = lokale dev-env, niet prod;
QR-token brute-force onmogelijk = 64-char tokens).

- **`#1` tenant-scoping (IDOR)** `EnsureToernooiScope` middleware op de `/{org}/toernooi/{toernooi}`-groep:
  elk geresolveerd route-model met `toernooi_id` moet matchen met `{toernooi}` → 403. Dichtte broken
  object-level auth: child-bindings (judoka/poule/blok/mat/toegang/coachKaart/betaling) waren op id-only
  geresolved → een org kon via z'n eigen toernooi-URL een child van een ánder toernooi muteren. (De
  autorisatie-agent claimde "elke org kan alles" — overdreven; `CheckToernooiRol`+`hasAccessToToernooi`
  beschermde toernooi-niveau al; dit was het smallere child-gat.) 403 ipv 404 voor consistentie met de
  bestaande checks. `TenantScopeTest` + 7 bestaande 403-tests bleven groen.
- **`#2` coach-kaart PIN** — 4-cijfer activatie-PIN had geen throttle → per-kaart lockout (5/5min) + route-throttle.
- **`#3` session-rotatie (fixation)** — alleen wachtwoord-login regenereerde; PIN/passkey/QR/3×magic-link niet.
  `session()->regenerate()` idiomatisch toegevoegd op alle 6. **Docs-first les:** de `// causes cookie issues`-
  comment kwam uit het unified-login-bouwcommit (`b90fef44`), géén gedocumenteerde bug; alle 6 zijn directe
  login-responses (niet de QR-polling-request) → idiomatische volgorde veilig. Test bewijst rotatie + login werkt.
- **`#4` coach-portal PIN** — 5-cijfer PIN (leesbaar by-design) → per-code lockout.
- **`#5` upload** — coach-foto `image`-rule accepteerde SVG (stored-XSS) → `mimes:jpeg,jpg,png,webp`.
- **audit-log** — sitebeheerder-impersonate logt nu start/stop (admin/klant/IP).

**Bewust NIET gedaan — risico > waarde, verdient browser-sessie (geen open gat):**
- **CSP afbakenen** (`wss://*.pusher.com`-wildcard, externe `img-src`) — raakt de realtime-WebSocket
  (`SecurityHeaders.php:72`); fout = scoreboard/live kapot, browser-fenomeen dat PHPUnit niet vangt.
- **HSTS-staging** (onwenselijk: forceert subdomeinen permanent HTTPS) · **rate-limit-middleware-dubbeling**
  (al via controller 3/10min) · **publieke leeftijd→categorie** (breekt favorieten-UI; PII-winst verwaarloosbaar,
  publiek toernooi toont sowieso namen+categorieën).

## SESSIE 25-06 (nacht, autonoom) — publiek mobiel scorebord + doc-rot

**Feature gebouwd + gepusht (`360ee266`, main). NIET gedeployd (deploy = Henks cue).**
- **Publiek mobiel scorebord** (portrait+landscape) voor ouders/coaches, geen inlog/app:
  publieke route `{org}/{toernooi}/mat/scoreboard-mobiel/{mat}` (`mat.scoreboard-mobile`) +
  `MatController::scoreboardMobile()` (deelt `scoreboardViewData()` met de LCD). Nieuwe view
  `scoreboard-mobile.blade.php`. De live-engine (Pusher+`handleEvent`+timer/osaekomi/sound/disconnect)
  is geëxtraheerd naar `partials/_scoreboard-engine.blade.php` en **gedeeld** met de LCD-view.
- **LCD bleef pixel-identiek** — geverifieerd: enkel `@include` toegevoegd (0 HTML/CSS verwijderd),
  77 scoreboard/mat PHPUnit groen, visual-regression LCD-baseline 4 passed. Optie A (gedeelde
  partial) geslaagd; geen terugval naar duplicatie nodig.
- **Build-asset-ruis teruggedraaid**: de bouw-agent draaide onnodig `npm run build` (de views zijn
  standalone, geen Tailwind/Vite) → CSS-bundle-rebuild **niet** gecommit (geen app-breed CSS-risico).
- **Gemini-blauwdruk was fout** (gokte Echo/privé-channel/`data.redScore`/nieuwe backend). Vervangen
  door een correct plan op de echte code (`.claude/blueprint.md`). Les: Gemini speculeert óók over
  bestaande code — altijd toetsen.
- **NOG TE DOEN (klein, CSP-gevoelig → browser-check nodig):** deel-knop/QR naar de mobiele URL bij
  de device-toegang-links (`device-toegangen.blade.php`, Alpine-CSP). Bewust niet 's nachts gedaan —
  CSP-knoppen moet je visueel verifiëren (deze sessie zat vol CSP-dode-knop-bugs op edit-partials).
  De feature werkt al via de directe URL.

**Doc-rot opgeruimd (`7f7a954e`):** 5 voltooide wegwerp-plannen verwijderd (matten-toegangen,
winnaar-doorschuiven, printbare-brackets, testverbeteringen, testplan-playwright) + Fase 2
(Hantei/GS) in CATEGORIE-doc op GEÏMPLEMENTEERD gezet (stond onterecht TODO).

## SESSIE 24-06 (vervolg) — write-route validatie + HavunCore-overdracht

**Twee dingen afgerond, beide op main (`8b1c9baf`, gepusht).**

- **Write-route audit weerlegt gisteren-aanname.** Niet 2 maar **10** echt-ongevalideerde
  write-routes gevonden (volledige handmatige audit van web.php+api.php; ~150 write-routes,
  ~95 met validatie, ~45 input-loos). De 6 data-integriteit-gaten **gedicht** (`884ba064`,
  milde type/bounds-validatie vóór elke try/catch, breekt bestaand gedrag niet):
  Judoka/StamJudoka `importConfirm` (mapping), Blok `genereerVerdeling`/`genereerVariabeleVerdeling`/
  `kiesVariant`, Publiek `favorieten`. 3 rejection-tests + bestaande suites groen (156+25+3 passed).
- **`betaling/simulate` security-gat GEDICHT.** De fake-payment-route (alleen off-production
  gebruikt, zie `Mollie/StripeService::isSimulationMode`) had geen environment-guard → een client
  op productie kon een betaling als `paid` forceren. `simulate`+`simulateComplete` nu
  `abort_if(config('app.env') === 'production', 404)`. 2 production-block-tests + 3 bestaande
  simulate-tests groen.
- **`qrGenerate` device-strings begrensd** (`2dc966cc`): `browser`/`os` → `nullable|string|max:255`,
  2 tests. Audit nu **7/10 gedicht + simulate-guard**.
- **Laatste 2 ook gevalideerd** (`dbd88f06`) → audit **10/10 + simulate-guard**: `receiveSync`
  dwingt nu `{timestamp, toernooien[]}` af, cap 100 toernooien, cachet alleen gevalideerde keys
  (geen rauwe `$request->all()` meer); `errorReport` dwingt element-types/lengtes af mét behoud van
  de tolerante count/size-truncatie (telemetry mag niet hele batches weigeren). 4 tests.
- **Repo-hygiëne GEDICHT** (`ca25ecdd`): `storage/framework/testing/` gegitignored + 3 getrackte
  coach-fotos jpg's uit de index (regenereren per testrun → was werktree-ruis).
- **HavunCore-overdracht** `8b1c9baf` → `.claude/HAVUNCORE-forms-coverage-plan.md`: plan om
  `QualitySafetyScanner::formsCoverage` route-based te maken (de 53%-vs-60% is een meet-artefact;
  materiële dekking ≈90%). Inclusief SaaS blast-radius + veilige uitrol. **Door te geven aan een
  HavunCore-sessie** (`/arch --project=havuncore`). Niet hier doen → scope-regel.

## OPENSTAANDE ITEMS (na sessie 23-06)

- [ ] **HavunCore: forms-coverage scanner route-based maken** — plan ligt klaar in
  **`.claude/HAVUNCORE-forms-coverage-plan.md`** (gecommit, niet scratchpad → wordt onthouden).
  qv-scanner meet JudoToernooi op **215 routes / 59%** (occurrence-based artefact); materieel ≈90%.
  Dit is een **HavunCore-wijziging**: open een HavunCore-sessie en wijs dat MD aan
  (`/arch --project=havuncore`). HavunCore-sessie heeft het doc al beoordeeld als self-contained.
  *(Reden dat dit hier als open-punt staat: scratchpad/cross-project-bestanden worden niet
  automatisch door `/start` geladen — dit punt + het vaste pad zorgt dat een volgende sessie het ziet.)*
- [ ] **`.env.bak.2026-05-02` residu op prod** (51d, K&V info-item) — klaar om naar
  `/var/backups/havun-env/judotoernooi/`. `.env`-adjacent → niet aangeraakt, wacht op go.
- [ ] **Device-sweep (#4 van de 4 testpunten):** fysieke sweep op Henks P10 met
  `docs/3-DEVELOPMENT/DEVICE-TEST-CHECKLIST.md` — kan Claude niet zelf.

### Afgerond

- [x] **K&V form-validation (24-06): 2 echte gaten gedicht, 60%-drempel is heuristiek-artefact.**
  `ToernooiWachtwoordenRequest` + `ToernooiBloktijdenRequest` toegevoegd (commit `ac31ac31`) —
  de enige écht-ongevalideerde input-endpoints (`updateWachtwoorden`/`updateBloktijden`). 5 tests.
  **60% is niet eerlijk haalbaar**: de qv-heuristiek (`QualitySafetyScanner::formsCoverage`) telt
  `(FormRequest-classes + inline ::validate-occurrences) / write-routes` → een gedeelde FormRequest
  (ToernooiRequest/StamJudokaRequest/ClubRequest op store+update) telt als **1 i.p.v. 2**, passkey-
  request-classes extenden geen FormRequest (niet geteld), en service-laag-validatie telt niet.
  De **echte** route-dekking ligt materieel hoger dan 53%. Converteren inline→FormRequest verzet
  de teller niet (−1/+1). Verder ophogen = óf schijn-validatie (afgewezen) óf de heuristiek
  fijnmaziger maken (= **HavunCore-wijziging, buiten scope** van dit project). Aanbeveling:
  qv-forms-check route-based maken (dekking = #write-routes-mét-validatie / #write-routes) i.p.v.
  occurrence-based, óf de drempel voor judotoernooi accepteren. Blauwdruk-routelijst was grotendeels
  gehallucineerd (zie `.claude/blueprint.md` — negeren).
- [x] **Repo-hygiëne** (24-06, `ca25ecdd`): `storage/framework/testing/` gegitignored + 3 getrackte
  coach-fotos jpg's uit de index gehaald. Werktree-ruis weg.
- [ ] Servers staan op de runtime-relevante staat; de laatste test-only commits (visual-bracket)
  zijn niet gedeployd (geen prod-impact). Synchroniseer servers alleen bij wens om pariteit.

## GUZZLE SECURITY-DEPLOY + STALE-TEST-FIX (23-06-2026)

**Gedeployed naar staging + prod, alle drie op `ff081bca`.**
- `guzzlehttp/guzzle` 7.11.1→7.12.1 + `guzzlehttp/psr7` 2.11.0→2.12.1 (3 medium CVE's:
  request-smuggling, HTTPS-proxy-downgrade, CRLF-injection). Lock-only, minor, non-breaking.
- Backup: `judo_toernooi_voor-guzzle-2026-06-23_..._11-51-14.sql.gz`.
- Deploy mét `composer install --no-dev --optimize-autoloader` (runtime-dep). Staging had nog
  dev-deps (41 removals, incl. infection) → opgeruimd; prod was al --no-dev (0 removals).
  Geverifieerd: guzzle 7.12.1 op beide, home/login 200, audit schoon.
- **Bijvangst:** volledige suite draaien legde 3 stale tests bloot (geen guzzle-issue): de
  sessie-1-refactor verplaatste `syncMatToegangen` naar `ToernooiService` + maakte 'm
  matten-records-driven. 2 ScoreboardPublicTest- + 1 AuthControllersCoverageTest-test
  codeerden nog het oude gedrag. Gefixt (echte Mat-records + service-aanroep). Suite weer
  **3557 groen**. Geen prod-gedragswijziging — puur test-drift.

## PRODUCTIE-DEPLOY VOLTOOID (23-06-2026)

**Alles gedeployed + gesynchroniseerd. Lokaal + staging + productie alle drie op `edb285a6`.**
- Prod was `ef65a127` → nu `edb285a6` (sessie-1-pakket: mat-toegangen-sync, eliminatie→poules,
  tab/CSP-fixes, dashboard csrf-meta + alle sessie-2 test/docs-werk dat runtime niet raakt).
- Backup vóór deploy: `/var/backups/havun/milestones/judo_toernooi_voor-deploy-2026-06-23_..._08-23-40.sql.gz`.
- Stappen: ff `git pull` in repo-pad + `optimize:clear` + post-merge-hook + `queue:restart`.
  Geen migrations/composer (sessie-1-pakket was PHP/Blade; sessie-2 voegde alleen een dev-dep toe → `--no-dev` negeert).
- Geverifieerd: staging + prod home/login 200, geen nieuwe errors (enige log-errors zijn oud, 12-04).
- Branches/PR's: niets op te ruimen — alleen `main` lokaal+origin, geen open PR's.

## Huidige status (22-06-2026, sessie 2 — testverbeteringen)

**4 testverbeteringen gebouwd + gepusht naar main** (`835c9c13`). Plan/uitkomst:
`.claude/blueprint-testverbeteringen.md`. Alle nieuwe e2e-specs lokaal groen.
- **Infection (mutation testing)** `843f442d` — `composer infection` (twee-staps i.v.m.
  Windows-bug). Baseline **MSI 47% bij 91% coverage** op CategorieClassifier/Eliminatie/
  Weging/PouleIndeling → veel loze asserts. Doc: `docs/3-DEVELOPMENT/MUTATION-TESTING.md`.
  Vervolg-optie: overlevende mutanten dichten (richtlijn MSI ≥70%).
- **Visual regression** `78ac91d4` + bracket `17abca5a` — `npm run e2e` draait
  `visual.auth.spec.ts` (scorebord-LCD + spreker + **bracket**). `npm run
  e2e:update-snapshots` om baselines te verversen. Bracket via de lichte print-view
  (`noodplan/bracket/{poule}/live`, `layouts.print`) i.p.v. de zware organisator-
  pagina die onder de PHP-dev-server hangt. Alle 3 schermen groen.
- **Realtime cross-device** `2fcb2381` — `npm run e2e:realtime` (Reverb áán, eigen config).
  Bewijst score-POST → Reverb → browser live. NIET in CI-gate (flaky-by-design).
- **Device-sweep** `2fcb2381` — handmatige checklist `DEVICE-TEST-CHECKLIST.md` +
  inerte BrowserStack-stub (jij draait dit op je P10).

**Niet gedeployed** (test-only, raakt runtime niet). Productie staat nog op `ef65a127`
(het staging-pakket van sessie 1 wacht nog op jouw akkoord — zie hieronder).

## Huidige status (22-06-2026)

### Sessie 21–22 juni — overzicht
Lange sessie. Op **main + staging** (staging = `59ef4256`), **productie nog op `ef65a127`**
(alleen de 3 vroege CSP/judoka-fixes draaien op prod; de rest hieronder wacht op deploy-cue).

**Features (op staging, te beoordelen):**
- **Mat-toegangen ↔ aantal matten** (`b2e16844`/`734acc30`): mat-device-toegangen volgen het
  aantal matten (toevoegen/verwijderen). Verlagen mét poules → waarschuwing → poules matloos
  (mat_id null, niet verwijderd), lijst ververst direct. Spec: INTERFACES.md. Plan:
  `.claude/plan-matten-toegangen.md`. Tests: `MattenToegangenSyncTest`.
- **Eliminatie → poules omzetten** (`59ef4256`): nieuwe poules erven `categorie_key` (blijven
  onder dezelfde categorie, bv. H-15) + de eerste poule houdt het oude nummer.

**Tab- + CSP-fixes op edit.blade.php (op staging):**
- Tab blijft behouden bij open/intern-wissel én bij Opslaan (`active_tab` hidden + redirect).
- Categorie-editor + hele `toernooi/edit.blade.php` CSP-schoon: **0 inline `on*=` en 0 inline
  `style=`** (preset-opslaan werkte niet onder CSP). Zie `.claude/smallwork.md`.

**Werkwijze-les vastgelegd:** AskUserQuestion-keuzetool AFGESCHAFT — alleen platte-tekst-vragen,
1 voor 1; cancel ≠ go-ahead. Zie geheugen `[[feedback_vraag-cancel-niet-doorgaan]]`.

**TE DOEN volgende sessie:** na Henk's akkoord op staging → het hele staging-pakket (mat-toegangen,
eliminatie→poules, tab/CSP-fixes, csrf-meta) naar productie deployen (prod loopt achter op `ef65a127`).

### Staging op `e0015101` (alle sessie-werk) — 21-06
Alles van deze sessie staat op **staging** (incl. dashboard-csrf-meta + 7 e2e-flows-infra).
ff-pull + post-merge cache-clear + queue-restart. Geverifieerd: home/login 200, bundel
`app-S3IsyqRL.js`, geen errors. **Productie nog op `ef65a127`** (de 3 bugfixes; csrf-meta
+ e2e-files nog niet — e2e raakt runtime niet, meta is additief).

### e2e-seed verrijkt + poule-generatie flowtest — KLAAR (commit `2c311215`, main, gepusht)
- E2eTestSeeder: toernooi krijgt `dynamischeKlassen()` (mini's/pupillen) + 5 deterministische
  pupillen (zelfde leeftijd/geslacht, dicht bijeen qua gewicht) → ze categoriseren en vormen
  één geldige poule (was: alles "ongecategoriseerd / max 0"). Geen regressie op display/csp-tests.
- Nieuwe flowtest `flows.auth.spec.ts` → `POST /poule/genereer` + `/poule/verifieer` via
  **in-page fetch** (same-origin sessie-cookie), assert `problemen`-key + poules/wedstrijden.
- **CSRF in Playwright-les:** `page.request` deelt de sessie NIET betrouwbaar (419). In-page
  `fetch` wél (cookie auto). CSRF-token uit de `csrf-token` meta; dashboard kreeg die meta
  (stond er niet, anders dan layouts.app) zodat de test 'm van de snelle dashboard-pagina haalt
  i.p.v. de trage judoka/poule-pagina. **NB:** dashboard-meta-wijziging nog niet gedeployed
  (triviaal/additief, gaat mee met volgende deploy).
- **e2e-machine-les:** background-runs laten `php artisan serve` op :8008 achter → kill vóór run.

### Functionele e2e-flows COMPLEET (laatste: `5079f67d`, main, gepusht)
Alle haalbare flows in `e2e/flows.auth.spec.ts` (authenticated project), allemaal groen:
1. **Judoka-CRUD (UI)** — knop opent modal + form submit (`942cab1e` regressie-guard).
2. **Uitslag → poulestand** — `/mat/uitslag` win → `/spreker/standings` wp=2 (`2a60f755`).
3. **Eliminatie doorschuiven** — 2 halve-finales winnen → finale accepteert doorgeschoven
   winnaar (de finale-POST slaagt alléén als doorschuiven werkte) (`fe823935`).
4. **Weging** — geldige weging slaagt, <15kg geweigerd (`7d006902`).
5. **Poule-generatie** — `/poule/genereer` + `/verifieer`, `problemen`-key (`2c311215`).
6. **Poule verplaatsen — kleurbeurt** — move poule → mat 2, groen blijft op mat 1,
   geel/blauw vervallen. Leest mat-state via `/mat/wedstrijden`. Guardt `c8b8fe13` (`5079f67d`).
7. **Doorsturen naar zaaloverzicht** — `/wedstrijddag/naar-zaaloverzicht` markeert poules
   doorgestuurd. Dé actie achter de knop die dood was onder de kapotte CSP — jouw
   oorspronkelijke melding die deze hele zoektocht startte (`5bccadbd`).

### Resterende laag-B items — NIET e2e-haalbaar (elders gedekt)
Onderzocht en bewust niet als e2e gebouwd (harde grenzen, geen scope-luiheid):
- **Realtime cross-device broadcast** — e2e draait `BROADCAST_CONNECTION=null` (geen Reverb);
  broadcasts zijn no-op. Gedekt door PHPUnit broadcast-event-tests.
- **Facturen/PDF** — `admin/facturen` is sitebeheerder-auth (niet de organisator-sessie van e2e).
  Gedekt door `AdminControllerTest`.
- **Betalingen** — Mollie/Stripe redirecten naar externe providers. Gedekt door
  `PaymentControllersCoverageTest`/`StripeProviderCoverageTest`.
- **DnD-interactie zelf** — getest via de onderliggende HTTP-endpoints (zie flow 6+7); de
  drag-drop-UI is in Playwright te flaky voor waarde.
- **Mat JP/WP-dropdown (UI)** — de scoring-datflow is gedekt (flow 2 uitslag→stand); de
  mat-interface zelf laadt zonder JS-errors (smoke). Directe dropdown-UI-interactie
  overgeslagen: mat-interface is de zwaarste/flakiest pagina in e2e (CDN/Reverb).
  Conclusie: e2e dekt nu de kern-competitieflows; periphere/externe/zware-UI zaken blijven
  op PHPUnit-niveau (juiste laag).

**Patroon/lessen (herbruikbaar):**
- Seeder (`E2eTestSeeder`) bouwt nu een complete round-robin poule + een 4-judoka eliminatie-
  bracket (via echte `EliminatieService::genereerBracket`), schrijft dynamische IDs naar
  `database/e2e-ids.json` (gitignored). Specs lezen die via `seededIds()`.
- HTTP-POSTs via **in-page `fetch`** (`postJson()`) — same-origin cookie + CSRF uit dashboard-meta.
  `page.request` deelde de sessie NIET (419).
- Volgorde in spec: judoka → uitslag → eliminatie → weging → **genereer LAATST** (die wist poules).
- `blockExternalCdn` + `waitForCspReady` + `test.slow()` tegen sandbox-CDN-hang/machine-load.
- Geen regressie op display/csp/pwa; bekende cold-load flakiness (slaagt op retry).

**Nog open (laag B / out-of-scope):** DnD poule-verplaatsing + kleurbeurt, realtime cross-device
broadcast, facturen/PDF, betalingen. Dashboard-`csrf-token`-meta (commit `2c311215`) nog niet
gedeployed (triviaal, gaat mee met volgende deploy).

### uitslag → poulestand flowtest — KLAAR (commit `2a60f755`, main, gepusht)
- Seeder bouwt nu een **complete poule**: 5 pupillen via `poule_judoka`-pivot + volledige
  round-robin (ongespeelde) wedstrijden. Dynamische IDs (poule, 1e wedstrijd, beide judokas)
  → `database/e2e-ids.json` (gitignored), gelezen door de spec.
- Test: `POST /mat/uitslag` (beslissende winst voor wit) → `POST /spreker/standings`, assert
  winnaar bovenaan met `wp=2` + precies één judoka op 2 winstpunten. **Draait vóór de
  genereer-test** (die de poule wist). Volgorde in spec: judoka → uitslag → genereer.
- 4 flows groen + display/csp geen regressie (2 flaky = bekende cold-load).
- **Volgende geplande flow:** eliminatie winnaar-doorschuiven (in uitvoering, autonoom).

### 3 CSP/judoka-fixes GEDEPLOYED naar staging + productie (21-06)
- Staging + prod beide op `ef65a127` (was staging `90c2c30d`, prod `cf34d15b`).
- ff-pull + post-merge hook (`optimize:clear`) + `queue:restart`. PHP/Blade/JS + gecommitte
  assets, **geen migrations/composer**. Prod-backup via `php artisan backup:milestone
  voor-csp-fixes` → `/var/backups/havun/milestones/judo_toernooi_voor-csp-fixes_2026-06-20_22-10-22.sql.gz`.
- Geverifieerd: staging + prod home/login 200, verse bundel `app-S3IsyqRL.js` live, geen
  deploy-errors. Bevat: cspActions-race-fix (`72a5171d`), CSP-parserfout (`2897440e`),
  dode judoka-knop (`942cab1e`).

## Huidige status (20-06-2026)

### cspActions load-order race — OPGELOST (commit `72a5171d`, main, gepusht)
- **Gevonden via Playwright-baseline:** `e2e/csp-authenticated.auth.spec.ts` faalde
  niet-deterministisch met `window.cspActions is not a function` op admin-pagina's
  (judoka/blok/dashboard wisselend). **Echte intermitterende productie-bug:** onder
  `script-src 'strict-dynamic'` injecteert Vite de bundel dynamisch → `csp-actions.js`
  draait niet gegarandeerd vóór de inline DOMContentLoaded-registraties → knoppen dood.
- **Fix (queue-stub):** `partials/csp-actions-stub.blade.php` definieert `window.cspActions`
  vroeg als buffer (vóór `@vite`); `csp-actions.js` flusht de wachtrij + zet `__ready`.
  Toegevoegd aan layouts/app + alle standalone-@vite views die cspActions gebruiken
  (dashboard, setup-pin, coach-kaart/activeer, dojo/scanner, mat/weging/spreker-interface).
  Bundel herbouwd (`app-D3OJMhA8.js`) + meegecommit. Doc: `docs/alpine-csp-migration.md`.
  Via `/arch` blauwdruk → `/mpc`. Zie [[csp-alpine-gotchas]].
- **Guard:** `e2e/csp-race.auth.spec.ts` vertraagt de bundel 800ms → forceert de race.
  Geverifieerd groen: csp-authenticated (5 admin-pagina's) + csp-race (dashboard/judoka/blok).
- **NOG NIET GEDEPLOYED** naar staging/prod. PHP/Blade/JS + assets, geen migrations.

### CSP Parser Error op judoka/index — OPGELOST (commit `2897440e`, main, gepusht)
- `x-init="setTimeout(() => $el.classList.remove('animate-error-blink'), 1500)"` op de
  ongecategoriseerd-alert → `@alpinejs/csp` kan arrow functions in directives niet parsen
  → "CSP Parser Error: Unexpected token )" bij elke judoka/index-load. Verplaatst naar
  `init()` op de `showOpen`-component (plain JS). Geverifieerd: console-error weg.

### Dode "Judoka toevoegen"-knop zonder stambestand — OPGELOST (commit `942cab1e`, main, gepusht)
- **Ontdekt tijdens functionele e2e-bouw.** De cspActions-registratie van de ALTIJD-
  aanwezige knoppen (`open-add-judoka`, `close-add-judoka`, tabel `confirm-delete`) zat
  ingesloten in `@if($organisator->stamJudokas()->actief()->exists())` (regel 769-912),
  samen met de stambestand-modal. Dus voor elke organisator **zonder stambestand** werd
  dat hele `<script>` niet gerenderd → handlers nooit geregistreerd → **"Judoka
  toevoegen"-knop + verwijder-bevestiging dood**. (e2e-seed heeft geen stamJudokas → zo
  ontdekt.) Reëel op prod voor organisatoren zonder stambestand.
- **Fix:** altijd-aan registraties in een eigen genonced `<script>` vóór de `@if`;
  stambestand-specifieke acties blijven erbinnen. **Let op:** een `@if` binnen een JS
  `//`-comment wordt door Blade alsnog als directive geparsed → comments zonder `@`.
- **Functionele e2e toegevoegd:** `e2e/flows.auth.spec.ts` (authenticated) — klikt de knop
  (seed-org zónder stambestand), assert modal opent + form submit landt schoon. **Groen.**
  Herbruikbare helpers: `blockExternalCdn` (CDN-scripts aborten — anders hangen
  pusher/sortable ~60s in de sandbox) + `waitForCspReady` (wacht op `window.cspActions.__ready`).

> **e2e-tip:** background test-runs laten soms een `php artisan serve` op :8008 achter →
> volgende run aborteert ("port already used"). Kill stale servers vóór een run.

### Playwright-baseline (20-06) + bekende flaky
- Volledige suite: ~44 passed. Pre-existing flaky/traag: **PWA "mat" + "jurytafel"**
  falen op deze machine (mat-interface + poule.index laden synchrone CDN-scripts die
  ~60s hangen → DCL-timeout; retry faalt door first-device-wins). **A/B-getest met
  `git stash`: faalt OOK zonder mijn wijzigingen → pre-existing, niet de cspActions-fix.**
  Mogelijke oorzaak: CDN traag/onbereikbaar in deze omgeving. Te onderzoeken: CDN-scripts
  op mat/interface async maken of mocken in e2e.
- **Testplan functionele flows** ligt klaar in `.claude/testplan-playwright.md` (laag A
  API-flows + laag B UI-wiring). Wacht op scope-akkoord Henk + seeder-uitbreiding (eliminatie-poule).

## Huidige status (14-06-2026)

### Kleurbeurt bij poule/groep-verplaatsing — OPGELOST (commit `c8b8fe13`, main)
- **Wens Henk:** verplaats je een poule/groep naar een andere mat, dan blijft de
  **groene** kleurbeurt op de oude mat staan (lopende partij maakt af; scorebord +
  LCD blijven 'm tonen). Alleen geel/blauw vervallen (met doorschuiving). Niet
  gestart? Jury zet groen handmatig uit (knop vraagt al bevestiging + notificeert).
- **Bug:** `Mat::resetWedstrijdSelectieVoorPoule` reset ook **groen** (in DB) zonder
  de schermen te notificeren → DB en LCD liepen uiteen. En het was niet groep-
  bewust: groep B verplaatsen wiste groep A's kleur (zelfde `poule_id`).
- **Fix:** methode laat groen staan, reset alleen geel/blauw, en is nu groep-bewust
  (`resetWedstrijdSelectieVoorPoule($pouleId, $groep)`). `verplaatsPoule` geeft
  `$groep` mee. Uitslag landt sowieso op expliciet `wedstrijd_id` (ScoreboardController),
  dus data klopt ongeacht mat. 5 unit-tests (groen-blijft + groep-A/B), 69 raakvlak-
  tests groen. Docs: `MAT-WEDSTRIJD-SELECTIE.md` §"Poule verplaatst".
- **Onderzocht (belangrijk voor context):** `match.start` wordt alleen gebroadcast,
  niet opgeslagen → systeem weet niet of partij gestart is → daarom mens-in-de-loop
  (waarschuwing) i.p.v. auto-detectie. Verplaatsen broadcast (nog) niet; geel/blauw-
  refresh op andere jury-schermen is een bekend, klein, bestaand gat (niet in scope).
- **GEDEPLOYED (14-06):** staging + productie beide op `e474a2b0` (ff-pull +
  `optimize:clear` + `queue:restart`). PHP-only. Prod-backup
  `judo_toernooi_voor-kleurbeurt-fix_2026-06-14_21-02-28.sql.gz`. Geverifieerd:
  fix-code aanwezig, home/login 200, geen deploy-errors.

### Mat-toewijzing bij poule-conversie — OPGELOST (commit `18dfce34`, main)
- **Bug:** afgeleide poules verloren hun mat. Bij omzetten eliminatie→poules
  (`WedstrijddagController::zetOmNaarPoules`), poule→kruisfinale (`wijzigType`)
  en handmatig poule toevoegen (`PouleController`) erfden de nieuwe poules
  alleen `blok_id`, niet `mat_id`. MatAssigner draait alleen bij "Naar
  Zaaloverzicht" → na-conversie poules kregen `mat_id=NULL` → op geen mat
  zichtbaar. (Gevonden op staging test3: #9/#10 -70kg, #11 -80kg in blok 3.)
- **Fix:** alle afgeleide-poule flows erven nu `mat_id` van de bronpoule. Tevens
  pre-existing `UNIQUE(toernooi_id,nummer)`-crash in de `poules_kruisfinale`-tak
  gefixt (`nummer` => `$maxNummer + $aantalPoules + 1`). Tests in
  `WedstrijddagControllerCoverageTest` asserten de mat-erving. 105 controller-
  tests groen. Doc: `docs/2-FEATURES/BLOKVERDELING.md` (sectie "Afgeleide poules
  erven de mat").
- **Data-reparatie staging:** #9/#10/#11 (test3, blok 83) → mat 90 gezet
  (backup `voor-matfix-poules_2026-06-14_18-40-23`).
- **GEDEPLOYED (14-06):** staging + productie beide op `9100ee0f` (ff-pull +
  `optimize:clear` + `queue:restart`). PHP-only, geen migrations/composer/assets.
  Prod-backup: `judo_toernooi_voor-matfix-deploy_2026-06-14_18-51-30.sql.gz`.
  Geverifieerd: fix-code aanwezig, home/login 200, geen deploy-errors.

### AutoFix stale alerts opgeruimd (14-06)
- Melding "AutoFix failed: _content.blade.php / Unclosed '(' does not match '}'"
  bleek stale (13-06 ochtend, CSP-migratie liep nog; bron inmiddels gezond).
  4 staging-alerts + 2 oude Ignition-alerts (staging+prod) op `resolved` gezet.
  Open wens: AutoFix stale failed-alerts zelf laten auto-resolven (re-check vóór
  high-severity alert vuurt) — nog te bouwen.

## Huidige status (13-06-2026)

**ALLE feature-branches geconsolideerd naar `main` (00cc0c2a) + opgeruimd. Staging draait op main. Productie ongewijzigd.**

| Branch | Inhoud | Status |
|--------|--------|--------|
| `feat/winnaar-auto-doorschuiven` | Eliminatie: winnaar schuift automatisch door | ✅ in main, branch verwijderd |
| `fix/csp-categorie-editor-delegation` | edit.blade.php categorie-validatie CSP-fix | ✅ in main, branch verwijderd |
| `fix/csp-inline-handlers-migratie` | Volledige CSP-migratie 265 inline handlers | ✅ in main, branch verwijderd |
| `fix/eliminatie-bracket-render` | Bracket CSSOM-positionering + badge + noodplan-print | ✅ in main, branch verwijderd |
| `fix/csp-alpine-migration` | Alpine/realtime onder strikte CSP | ✅ in main, branch verwijderd |
| `upgrade/laravel-12` | L12 upgrade | ✅ in main, branch verwijderd |

Lokaal + origin: alleen `main`. Staging hard-gereset naar origin/main (oude lokale main = puur deploy-merge-ruis, geverifieerd geen uniek werk; reflog bewaart). Geraakte-gebieden testsuite **607 groen**.

**PRODUCTIE-DEPLOY VOLTOOID (13-06-2026, 21:20).** Prod draaide nog L11.47 → nu **L12.62** (gelijk aan staging/main `6492c2e6`). Stappen: MySQL-backup (`/root/backups/judo_toernooi_pre-l12_20260613-212038.sql.gz`, 101K, 52 tabellen) → maintenance down → `git pull` → `composer install --no-dev` → `migrate` (Nothing to migrate, géén nieuwe migrations) → `optimize` → `queue:restart` → up. Geverifieerd: homepage 200, login 200, **strikte CSP live** (`script-src 'self' 'nonce' 'strict-dynamic'`, geen unsafe-inline), correcte bundle `app-BrUnN-aO.js`, geen errors. Rollback indien nodig: `git checkout 84a79367 && composer install --no-dev` + restore backup.

### Bracket #Nrs (slotnummers) — OPGELOST (13-06-2026, staging)
- **Bug:** de #Nrs-knop (slotnummers tonen) deed niets. Oorzaak: `@click="debugSlots = !debugSlots; ..."` is een **cross-scope assignment** (debugSlots op hoofd-component, knop in `bracketTabs` child-scope) → werkt niet onder `@alpinejs/csp`. Zie [[csp-alpine-gotchas]].
- **Fix:** `toggleDebugSlots(pouleId, groep)` method op de hoofd-component (`this` = plain JS) + `@click` roept die aan. Herlaad-knop werkte al (pure call).
- **Admin-gate:** #Nrs nu achter `@if($isAdmin)` — `interface-admin` geeft `isAdmin=true`, `interface` (vrijwilliger) `false`. Alleen beheerder ziet de knop.
- **LIVE OP PROD (14-06-2026).** Prod gepulld naar `a6285035` (backup `/root/backups/judo_toernooi_pre-nrsfix_20260614-085757.sql.gz`); `git pull` + cache-optimize (geen composer/migrate). Geverifieerd: homepage/login 200, verse bundle `app-CmNiIFXc.css`, geen errors. Staging + prod + main nu allemaal `a6285035`.

**TE DOEN (open):**
1. Staging `npm ci` ontbreekt `vite-plugin-manifest-sri` — niet blokkerend (staging serveert gecommitte build), maar verse builds op staging falen tot `npm ci`.
2. **Let op bij elke deploy:** build-assets (`laravel/public/build/`) zijn **gecommit** en worden via `git pull` uitgerold (servers bouwen niet). Na blade/JS-wijzigingen die Tailwind-output raken: `npm run build` + de assets meecommitten, anders serveren de servers stale CSS/JS.

### LCD / TV-URLs — OPGELOST (13-06-2026)
- **`havun.nl/tv/{code}` 404 gefixt.** Root-cause: nginx op havun.nl had alleen `location = /tv` (exact-match) → `/tv/JTCI` viel door naar de Node-proxy → 404. Toegevoegd: regex-redirects `^/tv/(.+)$` → `judotournament.org/tv/$1` + `^/tvs/(.+)$` → staging. Getest (301 ✅), nginx herladen. Config: `/etc/nginx/sites-enabled/havun.nl` (backup in `/root/havun.nl.bak*`).
- **LCD bereikbaar via 2 URLs** — device-toegangen toont nu beide: knop **"Kort"** (`havun.nl/tv/{code}`) + **"Volledig"** (`judotournament.org/{org}/{toernooi}/mat/scoreboard-live/{mat}`, werkt altijd). Volledig gedocumenteerd in `docs/2-FEATURES/SCOREBORD-APP.md`.
- **LCD CSP:** `scoreboard-live` werkt onder strikte CSP. 2 inline `style=` op de "GEEN VERBINDING"-overlay → nonced classes (`.msg`/`.sub`). De `?.`/`??` daarin zijn **vanilla JS** (geen Alpine) → CSP-veilig; mijn eerdere "spreker ?./?? fix"-notitie was onjuist en is geschrapt.
- **Omgeving-badge op LCD (dilemma korte token).** Een verkorte token (`/tv/{code}`) is omgeving-loos → bij staging↔prod-wisselen kun je ongemerkt de verkeerde DB raken. Oplossing (Henk + Claude): rode hoek-badge op `scoreboard-live` zodra `!app()->isProduction()` → toont "STAGING"/"LOCAL", niets op productie (`.env-badge`, nonced CSS). Géén `/tv2`-vanity voor staging gemaakt (één tekentje van `/tv` = typefout-val); staging gebruikt gewoon `staging.judotournament.org/tv/{token}` — domein = omgeving-marker. De badge is de sluitende laag, werkt ongeacht welke URL.

### Wat is er 's nachts gebeurd

**1. Auto-doorschuiven winnaar (feat/winnaar-auto-doorschuiven)** — `/arch`+`/mpc` flow. `EliminatieService::verwerkUitslag` schuift de winnaar nu bij ELKE uitslag automatisch door naar de volgende ronde (was alleen bij correctie). Verliezer→B was al automatisch. 6 unit + 1 feature test, volledige suite **3476 groen**. DnD blijft override. Byes ongemoeid (bewuste scope-keuze, Henk akkoord).

**2. VOLLEDIGE CSP-migratie (fix/csp-inline-handlers-migratie)** — de root-cause: commit `e6cb1746` haalde `unsafe-inline` uit `script-src`, maar migreerde alleen de `<script>`-tags, niet de **265 vanilla inline `on*=` handlers** over 49 views → dode knoppen (Henk's "doorsturen", categorie-validatie, weging, etc.).
- **Patroon:** nieuwe helper `resources/js/csp-actions.js` (in Vite-bundle, krijgt nonce). Inline `onclick="fn(x)"` → `data-action="naam"` + `data-*`; document-level event delegation roept bestaande globale functies aan. Per-view `cspActions({...})` in DOMContentLoaded, plus **built-in acties** (print/reload/confirm/confirm-submit/confirm-navigate + migratie-bridge via window-lookup) zodat simpele views geen registratie nodig hebben.
- **Gemigreerd (alle 49 views op 2 na):** wedstrijddag/poules+poule-card, poule/index, judoka/index, blok/index, poule/eliminatie, organisator/dashboard, weging, dojo/scanner, chat-widgets, setup-pin, pwa-mobile, **layouts/app**, mat/_content (bracket-nav), scoreboard-live, en ~30 long-tail views.
- **NIET gemigreerd (bewust):** `toernooi/edit.blade.php` categorie-editor (20 handlers) → gedekt door branch `fix/csp-categorie-editor-delegation`; `noodplan/offline-pakket` → standalone offline-pakket met eigen CDN-Alpine, draait NIET onder app-CSP.
- **Verificatie:** nieuwe `e2e/csp-authenticated.auth.spec.ts` (strikte CSP op e2e-server) — dashboard/wedstrijddag/poule/judoka/blok = **0 CSP-violations, 0 JS-errors**. Publieke csp.spec groen. Ook `auth.setup.ts` selector-flake gefixt (deblokkeert authenticated suite).

### ⚠️ Merge-naar-main aandachtspunt
`fix/csp-categorie-editor-delegation` en `fix/csp-inline-handlers-migratie` raken beide `edit.blade.php` (verschillende secties → cleane merge op staging gelukt). Bij main-merge: merge eerst auto-doorschuiven, dan beide CSP-branches. De categorie-editor-branch en de grote migratie zijn complementair (geen overlap in gedrag).

### Te testen op staging (ochtend)
1. **Eliminatie**: bracket spelen, uitslag via scoreboard/mat → winnaar verschijnt automatisch in volgende potje; verliezer in B.
2. **Wedstrijddag → "doorsturen naar zaaloverzicht"** (jouw oorspronkelijke melding) → moet werken.
3. **Categorie-editor** (toernooi/edit): Δkg=0 + gewichten → waarschuwing verdwijnt, eliminatie selecteerbaar.
4. Steekproef knoppen op weging/dojo/poules/judoka/dashboard (alles via delegation nu).

---

## (Historisch) Status 12-06-2026

**Branch:** main · **L12 = GEDEPLOYED** (staging+main) · CSP-fix gedeployed naar **staging** (main `77071f6e`), **productie nog op `84a79367`** (oude code, Alpine geblokkeerd — bewust niet gedeployed).
**AutoFix:** actief op production + staging

## ⚠️ ACTIEF: CSP/Alpine-fix — staging klaar, productie wacht, mobiele puntjes open

**De grote vondst:** de strikte CSP (`script-src nonce + strict-dynamic`, geen `unsafe-inline`) blokkeerde de **Vite-bundle volledig** omdat `@vite` géén nonce kreeg → **Alpine draaide nergens** op staging/prod (login dood, PWA's vast, layout instabiel). Henk merkt 't omdat hij lokaal ontwikkelt (CSP uit) en pas op de P10 testte.

**Gefixt + geverifieerd (e2e groen, gedeployed staging):**
- `Vite::useCspNonce()` in `SecurityHeaders` → app.js/css laden weer *(de kernfix)*
- `connect-src` → Reverb ws-host via `parse_url(config('app.url'))` (zo bepalen de blades óók de wsHost) — realtime was overal geblokkeerd
- `img-src` JS-icon + `@nonce` op 6 Pusher-tags
- Alpine `@alpinejs/csp`-migratie: login (onclick→addEventListener), weging (`function countdown`→`Alpine.data`), mat (connectionDot + menuWithHelp methods), spreker (`__laden`, `:style`), tv/qr-scan, 5× `:style`-string→object (CSSOM)
- shared lib: `connectionDot`, `menuWithHelp.{openHelp,refreshMat,openSettings}`, `fontSizer.apply()`
- e2e: CSP-violation-detectie toegevoegd (`csp.ts`/`csp.spec`/`login.spec`/`pwa.spec`)
- admin-tabbalk (`pages/toernooi/index`) → `overflow-x-auto` (was breder dan header op mobiel)
- service-worker → **v1.5.0** (forceert clients verse assets)

**KEY-INZICHT (bespaart morgen veel tijd):** de `@alpinejs/csp` build **ondersteunt ternary's + compound `@click` WÉL** (home `/` en mat slaagden met 0 violations). De doc `docs/alpine-csp-migration.md` was overdreven voorzichtig — NIET alle ~25 views met expressies hoeven migratie. Echte breakers waren smal: Vite-nonce (groot), connect-src, Pusher-nonces, paar globale-functie x-data, inline `:style`-strings.

**OPEN voor morgen:**
1. **Biometrie NIET testbaar op staging** — passkey is domein-gebonden; als op prod (`judotournament.org`) ingesteld, heeft de P10 geen passkey voor `staging.…`. Knop-fix is echt; verifieer op **productie** of registreer staging-passkey. (Henk's antwoord op "waar ingesteld?" nog niet gekregen.)
2. **PWA-installknop zit onder "backup actief"-melding** — z-index/positie-overlap; screenshot nodig om te lokaliseren. installBanner = `publiek/index:1083` (`fixed bottom-0 z-50`), backup-toast in `layouts/app.blade.php:458`.
3. **Mobiele responsiveness-sweep** van ingelogde schermen op een ECHT toestel — Playwright Pixel7-emulatie mist real-device-issues (forceert eigen viewport).
4. **Productie-deploy** van de CSP-fix (zelfde commit, mét backup) zodra staging is goedgekeurd.
5. Niet-e2e-geverifieerd (browser-check op staging): `:style`-object op scoreboard/publiek/toernooi-mobiel/weging-admin, tv/qr-scan, `offline/index` (laadt eigen CDN-Alpine — apart geval, ongemoeid).

---

## (Afgerond) Laravel 12 upgrade — GEDEPLOYED

## ⚠️ Laravel 12 upgrade — KLAAR op branch, NOG NIET gedeployed

Branch `upgrade/laravel-12` bevat de volledige upgrade 11.47 → 12.62. Lokaal
volledig groen: **3469 PHPUnit tests + 18 Playwright e2e**. NIET naar main
gemerged om te voorkomen dat een routine `git pull`-deploy hem zonder de
juiste stappen oppakt.

**Deploy-stappen (met BACKUP eerst — Henk's eis):**
1. **MySQL-dump** van production EN staging maken (vóór alles)
2. Staging eerst: `cd repo-staging && git fetch && git merge origin/upgrade/laravel-12`
3. `composer install --no-dev -o` (haalt L12 vendor binnen — anders L11 vendor + L12 code = kapot)
4. `php artisan view:clear && php artisan config:clear && php artisan cache:clear`
5. `php artisan migrate --force` (geen nieuwe migraties verwacht, maar check)
6. Smoke-test staging (publieke pagina's, scorebord, facturen-download)
7. Pas daarna production identiek
8. Na groen: merge branch → main, branch verwijderen

Kernpunten van de upgrade (zie commit-messages voor detail):
- `config/filesystems.php` toegevoegd: `local` disk root gepind op `storage/app`
  (L12 default werd `storage/app/private` → facturen onbereikbaar)
- JSON-LD `@context` → `@@context` in home + publiek/index (L12 Blade-compiler
  zag `@context` als directive → fatale ParseError op publieke pagina's)
- Carbon 2→3 mee-geüpgraded, composer audit clean (lost ook CVE-2026-48019 op)

## Openstaande items

- [x] **phpoffice/phpspreadsheet security update**: opgelost 2026-05-28 → v1.30.4
- [ ] **ShouldQueue voor MatUpdate/ScoreboardEvent**: optioneel — converteren van `ShouldBroadcastNow` naar queued broadcast voor retry bij tijdelijk Reverb-uitval. Lage prioriteit, bewust uitgesteld.
- [ ] **Blauw-positie scorebord op staging bekijken**: verplaatst naar Device Toegangen (Organisatie-tab) — Henk moet nog goedkeuren.
- [ ] **Gear-icoontje header op staging bekijken**: instellingen-icon links naast taalvlag — Henk moet nog goedkeuren.
- [ ] **Hantei (W) en Gelijkspel (G) in mat interface op staging bekijken**: JP-dropdown uitgebreid, Henk moet nog goedkeuren.
- [ ] **Scoreboard testen**: Henk wilde controleren of het scoreboard correct werkt — nog niet gedaan in deze sessie.

## Kritieke context voor volgende sessie

- Artisan altijd met `cd laravel &&` prefix
- Auth guard is `organisator` — NIET `web`
- DB is SQLite lokaal, MySQL production — NOOIT test draaien op staging/production
- Realtime via Reverb/WebSockets — geen polling
- AutoFix kan server-wijzigingen maken vóór sessiestart → altijd `git pull` na `git push` server → lokaal
- Deploy: `git pull` in repo-pad (`/var/www/judotoernooi/repo-prod`), NIET in symlink
- Alpine.js gebruikt `@alpinejs/csp` build — GEEN `Alpine.evaluate(el, string)`, wél `Alpine.$data(el).method()` of `x-on:event.window`
- Alpine CSP verbiedt ook compound `@click` expressies (`x = 1; method()`) → altijd een aparte methode in de component

## Sessie-log

### 2026-06-10/11 — Laravel 12 upgrade + Playwright

**Security:** `composer audit` vond 2 nieuwe CVE's. phpspreadsheet 1.30.4→1.30.5
(CVE-2026-45034, critical patch-bypass) gefixt + getest + naar main gepusht.
De tweede (laravel/framework CVE-2026-48019 CRLF) had geen 11.x-patch → leidde
tot de major upgrade.

**Laravel 11.47 → 12.62 (branch `upgrade/laravel-12`):** zie deploy-blok boven.
Twee echte issues gevonden via de volledige testrun:
1. JSON-LD `@context` Blade-compiler regressie (publieke pagina's 500) → `@@context`
2. Pre-existing testfout (niet L12): `winnaar_id`-validatie verwachtte 'required'
   maar gelijkspel-feature (2 juni) maakte 'm nullable → test bijgewerkt.
Eindresultaat: 3469 PHPUnit groen.

**Playwright e2e geïntroduceerd:** `@playwright/test` in `laravel/`, config met
self-hosting webServer (build + artisan serve), Page Object Model, smoke-specs
voor home + publieke/legal pagina's (200 + zichtbare heading + geen JS-errors)
+ sitemap. `npm run e2e`. 18 specs groen. Vangt Alpine-CSP/render-regressies
die PHPUnit niet ziet. Docs in `laravel/e2e/README.md`.

### 2026-06-04

**Helpfile en docs bijgewerkt:** vrijwilliger PIN-vermeldingen verwijderd uit `pages/help.blade.php`, `GEBRUIKERSHANDLEIDING.md`, `INTERFACES.md`, `URL-STRUCTUUR.md`, `ROLLEN_HIERARCHIE.md`, `NOODPLAN-HANDLEIDING.md`. Vrijwilliger PWA's (mat/weging/spreker/dojo) krijgen toegang via unieke URL + auto device binding bij eerste keer. **PIN blijft wel** voor coach portal (clubs) en LCD scorebord koppeling.

**Openstaand:** `SCOREBORD-APP.md` (regel 137-157) documenteert nog `code + pincode` auth voor Android scorebord app — is dit een code-change nodig in backend API of alleen docs-aanpassing? Wacht op besluit gebruiker.

### 2026-05-27

**Bug gefixt:** Mat interface (WP/JP grid) werd niet bijgewerkt nadat JudoScoreBoard Android app een wedstrijd beëindigde. Oorzaak: `Alpine.evaluate(el, 'laadWedstrijden()')` werkt niet met `@alpinejs/csp` build (geen runtime string evaluatie). Fix: vervangen door idiomatische `x-on:mat-score-update.window="laadWedstrijden()"` directive op het `mat-interface` div.

**Duurzame Reverb-betrouwbaarheid geïmplementeerd (Gemini-blueprint):**
- `_content.blade.php`: `x-on:ws-connected.window="laadWedstrijden()"` — state refresh bij herverbinding
- `interface.blade.php`: groen/rood bolletje in header toont live WebSocket-status
- `scoreboard-live.blade.php`: disconnect-overlay met afteltimer + automatische page reload na 60s verbroken verbinding

**Nieuwe feedback-memory:** `/arch` VERPLICHT gebruiken vóór elke diagnose of implementatie — Gemini leest MD docs wél.

**Sessie afsluiting:**
- Dashboard dropdown menu fix — `modalWithAbout` Alpine component miste `toggle()`/`close()` methods (deployed)
- "Geen wedstrijden" badge op mat interface uitgelegd (geen bug): poule flow = wedstrijddag doorsturen → zaaloverzicht chip klikken → wedstrijden gegenereerd → mat interface. Bij test-toernooi was stap 2 (zaaloverzicht chip) niet uitgevoerd.

### 2026-05-28

**Security updates geïnstalleerd en gedeployd:**
- `phpoffice/phpspreadsheet` 1.30.1 → 1.30.4 (critical SSRF/RCE CVE-2026-34084 + 2x high CPU DoS)
- `symfony/*` 7.4.0 → 7.4.12/7.4.13 (SMTP injection, URL injection, YAML DoS, diverse CVEs)
- 24 packages geüpdated in één `composer update`
- Alle 3467 tests groen na update
- Gedeployd naar production én staging

### 2026-06-02

**UI verbeteringen (op staging, wachten op goedkeuring):**
- **Blauw-positie scorebord verplaatst**: van "Matten & Tijdsblokken" naar "Device Toegangen" sectie (Organisatie-tab). Eigen `PUT /scorebord` route + `updateScoreboardInstelling()` in `ToernooiInstellingenController`. Bewaart setting via `mat_voorkeuren['blauw_rechts']` op toernooi.
- **Gear-icoontje in header**: tandwiel-icon toegevoegd links naast taalvlag, zichtbaar in toernooi-context, linkt naar `toernooi.edit`.

**Hantei & Gelijkspel in mat interface:**
- JP-dropdown uitgebreid met `G` (gelijkspel, beide WP=1, JP=0) en `W` (hantei/winnaar aanwijzen, WP=2, JP=0)
- `updateJP()` in `_content.blade.php` handelt `value='hantei'` af
- `saveScore()` stuurt `uitslag_type='hantei'` of `uitslag_type='gelijkspel'` naar backend
- `ScoreboardController::uitslagTypeToJP('hantei')` stond al op 0
- Bewuste keuze: geen per-categorie regels opslaan — scheids beslist zelf op basis van toernooi-regels

**Architectuurkeuze vastgelegd:** Hantei/GS-regels per categorie zijn NIET geconfigureerd in toernooi-instellingen. De scheids kent de regels. Systeem registreert alleen de uitkomst.

### 2026-06-03

**Sessieterugblik (geen nieuwe code):** Sessie bestond uitsluitend uit terugblik op vorige sessie (vergeten `/end` te doen). Twee commits waren al gepushed na de handover van 2026-06-02:
- `feat(categories)`: `eind_optie` en `golden_score_duur` velden toegevoegd aan categorieën (ondersteuning Hantei/GS per categorie)
- `fix(scoreboard)`: mat interface update werkt nu correct bij resultaat vanuit JudoScoreBoard Android app

### 2026-06-04

**LCD kort URL:** Device Toegangen toont nu `havun.nl/tv/{code}` i.p.v. lange `judotournament.org/tv` URL (makkelijker typen met TV-afstandsbediening). Zowel kopieer-knop als QR als popup-tekst bijgewerkt.

**Home page verbeterd:**
- Lightbox popup navigeerbaar met ‹ › pijltjes + toetsenbord ←→ + teller "1/4"
- Bouncing scroll-link "Bekijk screenshots ↓" toegevoegd in hero-sectie
- JudoScoreBoard callout (al op production aanwezig maar niet in repo) gecommit

**Bug fix:** "MET eigen router (Deco)" niet selecteerbaar in Instellingen → Noodplan. Oorzaak: `@click="heeftEigenRouter = true; saveNetwerkConfig()"` is een compound expressie — verboden door `@alpinejs/csp`. Fix: `setMetRouter()` / `setZonderRouter()` methodes toegevoegd aan `netwerkConfig` component. Test toegevoegd in `ToernooiControllerCoverageTest`.
