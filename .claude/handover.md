---
title: JudoToernooi Handover
type: claude
scope: judotoernooi
last_updated: 2026-06-14
---

# JudoToernooi — Handover

> Vul dit aan aan het einde van elke sessie.

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
- **TE DOEN:** staat op **main**, nog niet op staging/prod. PHP-only.

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
