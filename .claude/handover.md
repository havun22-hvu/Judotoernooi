---
title: JudoToernooi Handover
type: claude
scope: judotoernooi
last_updated: 2026-06-04
---

# JudoToernooi — Handover

> Vul dit aan aan het einde van elke sessie.

## Huidige status

**Status:** Stabiel in productie — multi-tenant SaaS op judotoernament.org
**Branch:** main (schoon) · **`upgrade/laravel-12` gepusht, wacht op deploy**
**AutoFix:** actief op production + staging

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
