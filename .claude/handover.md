---
title: JudoToernooi Handover
type: claude
scope: judotoernooi
last_updated: 2026-06-02
---

# JudoToernooi — Handover

> Vul dit aan aan het einde van elke sessie.

## Huidige status

**Status:** Stabiel in productie — multi-tenant SaaS op judotoernament.org
**Branch:** main (schoon, alles gepushed)
**AutoFix:** actief op production + staging

## Openstaande items

- [x] **phpoffice/phpspreadsheet security update**: opgelost 2026-05-28 → v1.30.4
- [ ] **ShouldQueue voor MatUpdate/ScoreboardEvent**: optioneel — converteren van `ShouldBroadcastNow` naar queued broadcast voor retry bij tijdelijk Reverb-uitval. Lage prioriteit, bewust uitgesteld.
- [ ] **Blauw-positie scorebord op staging bekijken**: verplaatst naar Device Toegangen (Organisatie-tab) — Henk moet nog goedkeuren.
- [ ] **Gear-icoontje header op staging bekijken**: instellingen-icon links naast taalvlag — Henk moet nog goedkeuren.
- [ ] **Hantei (W) en Gelijkspel (G) in mat interface op staging bekijken**: JP-dropdown uitgebreid, Henk moet nog goedkeuren.

## Kritieke context voor volgende sessie

- Artisan altijd met `cd laravel &&` prefix
- Auth guard is `organisator` — NIET `web`
- DB is SQLite lokaal, MySQL production — NOOIT test draaien op staging/production
- Realtime via Reverb/WebSockets — geen polling
- AutoFix kan server-wijzigingen maken vóór sessiestart → altijd `git pull` na `git push` server → lokaal
- Deploy: `git pull` in repo-pad (`/var/www/judotoernooi/repo-prod`), NIET in symlink
- Alpine.js gebruikt `@alpinejs/csp` build — GEEN `Alpine.evaluate(el, string)`, wél `Alpine.$data(el).method()` of `x-on:event.window`

## Sessie-log

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
