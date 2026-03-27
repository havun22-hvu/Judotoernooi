# Smallwork Log

> Kleine technische fixes die niet in permanente docs hoeven.
>
> **Wat hoort hier:**
> - Bug fixes, typos, performance
> - Technische refactoring
>
> **Wat hoort hier NIET:**
> - Features → docs/
> - Styling → STYLING.md
>
> **Archief:** Oude sessies staan in `archive/`

---

## Sessie: 27 maart 2026

### Feat: Toernooi type (intern/open)
- **Type:** Feature
- **Wat:** Nieuw `toernooi_type` veld (intern/open). Bij intern worden eliminatie, danpunten, dojo/coach en coachkaarten secties verborgen in instellingen. Betalingslogica ongewijzigd. Radio buttons met autosave + page reload.
- **Bestanden:** edit.blade.php, Toernooi.php, ToernooiRequest.php, migratie

### Fix: Round-robin tekst versimpeld
- **Type:** UI fix
- **Wat:** "(dubbele round-robin)" → "(dubbel)", "(enkelvoudige round-robin)" → "(enkel)", "Altijd enkel (round-robin)" → "Altijd enkel"
- **Bestanden:** edit.blade.php

### Feat: Homepage redesign
- **Type:** UI redesign
- **Wat:** Professionelere SaaS-look: diepere blue-950 tinten, SVG Heroicons i.p.v. emoji's, strakere typografie (extrabold hero), subtielere shadows, meer whitruimte. Screenshots en stappen als @foreach loops. Feature tags: "Herclassificatie bij afwijkend gewicht" (was "Automatische herclassificatie"), "Live app voor ouders & coaches" toegevoegd. Deployed production.
- **Bestanden:** home.blade.php

### Docs: Grote docs cleanup
- **Type:** Documentatie
- **Wat:** 11 bestanden gearchiveerd (.claude/archive/). smallwork 1139→164 regels. handover opgeschoond. Planning docs gemarkeerd als GEREALISEERD/ON HOLD. README.md bijgewerkt. FREEMIUM.md uitgebreid met toernooi type sectie.
- **Bestanden:** diverse .md bestanden

---

## Sessie: 25 maart 2026

### Fix: Datum+tijd overal i.p.v. diffForHumans
- **Type:** UI fix
- **Wat:** Alle `diffForHumans()` ("1 dag geleden", "Nooit") vervangen door `d-m-Y H:i` formaat. Relatieve tijd als hover tooltip. "Nooit" → "-".
- **Bestanden:** klanten.blade.php, klant-edit.blade.php, toernooi/index.blade.php, organisator/dashboard.blade.php

### Fix: Dubbele "Laatste login" verwijderd
- **Type:** UI fix
- **Wat:** "Laatste login" stond in titelbalk én in "Laatst actief" kolom van toernooien tabel. Titelbalk-versie verwijderd (alleen "Klant sinds" blijft).
- **Bestanden:** toernooi/index.blade.php

### Feat: Upgrade-link bij judoka import limiet
- **Type:** Feature
- **Wat:** Blauwe banner met "Meer deelnemers nodig?" + link naar Instellingen → Organisatie tab wanneer free tier limiet bereikt wordt bij handmatig toevoegen of CSV import.
- **Bestanden:** JudokaController.php, judoka/index.blade.php, judoka/import.blade.php

### Fix: laatste_login bij registratie
- **Type:** Bug fix
- **Wat:** `updateLaatsteLogin()` werd niet aangeroepen bij nieuwe registratie → "Nooit ingelogd" voor nieuwe accounts.
- **Bestanden:** OrganisatorAuthController.php

---

## Sessie: 24 maart 2026

### UI: Clubs Uitnodigen pagina heringedeeld
- **Type:** UI improvement
- **Wat:** Kolommen "Plaats" en "Email" (tekst) verwijderd uit tabel. Kopieer-icoon veranderd van `~` naar clipboard SVG (dubbel blad). WhatsApp verplaatst uit Coach Portal kolom naar eigen kolom. Email ook eigen kolom met envelop-icoon. Iconen gekleurd als club email/@/telefoon heeft, anders grijs. Klik opent mailto: of wa.me met voorgevuld uitnodigingsbericht.
- **Bestanden:** resources/views/pages/club/index.blade.php

### Fix: Activiteiten log details onleesbaar
- **Type:** UI fix
- **Wat:** "Toon" details expandeert nu als aparte rij onder de log entry i.p.v. inline in de cel
- **Bestanden:** activiteiten.blade.php

### Infra: Server herstructurering — symlink deploy
- **Type:** Infrastructuur
- **Wat:** Production + staging omgezet van platte git checkout naar verse git clone + symlink. `git pull` werkt nu correct. Paden: `repo-prod/laravel` en `repo-staging/laravel` gesymlinkt als `/var/www/judotoernooi/laravel` en `staging`.
- **Bestanden:** deploy.md, CLAUDE.md, context.md

### UI: Freemium messaging verzacht
- **Type:** UI improvement
- **Wat:** "Geblokkeerd" (rood) → "Beschikbaar bij betaald pakket" (grijs). "Status" → "Pakket". Negatieve beperkingen lijst verwijderd van noodplan upgrade pagina. Upgrade hint bij max deelnemers veld. Freemium banner toont nu ook voor admin.
- **Bestanden:** upgrade.blade.php, upgrade-required.blade.php, edit.blade.php, freemium-banner.blade.php

### Feat: Danpunten als betaalde feature
- **Type:** Feature restriction
- **Wat:** Danpunten checkbox verborgen voor free tier, vervangen door upgrade-link. Toegevoegd aan FREEMIUM.md, upgrade pagina en freemium banner.
- **Bestanden:** edit.blade.php, upgrade.blade.php, freemium-banner.blade.php, FREEMIUM.md

---

## Sessie: 22 maart 2026

### Fix: Spreker tabs verspringen bij tabwisseling
- **Type:** UI fix
- **Wat:** Root container `max-w-4xl` + `min-height` op content tabs
- **Bestanden:** spreker/partials/_content.blade.php

### Fix: Mat selectie blokkeerde na scorebord uitslag
- **Type:** Bug fix
- **Wat:** `isEchtGespeeld` check blokkeerde hele request als actieve wedstrijd al winnaar had. Skip check voor wedstrijden in huidige selectie.
- **Bestanden:** MatController.php

### Fix: Mat interface race condition bij Reverb beurt update
- **Type:** Bug fix
- **Wat:** matSelectie direct uit Reverb event data bijwerken i.p.v. wachten op API roundtrip
- **Bestanden:** mat/partials/_content.blade.php

### Deploy: Scoreboard backend naar staging
- **Type:** Deploy
- **Wat:** ScoreboardController, events, migratie, web display, download page

---

## Sessie: 9 maart 2026

### Feat: "Uit database" knop op toernooi deelnemersbeheer
- **Type:** Feature
- **Wat:** Organisator kan stam judoka's importeren in een toernooi via paarse "Uit database" knop
- **Bestanden:** JudokaController.php (2 methods), index.blade.php (knop + modal), web.php (2 routes)
- **Backend:** `StambestandService::importNaarToernooi()` met classificatie

### Fix: StambestandService eigenaar() → organisator
- **Type:** Bug fix
- **Wat:** `$toernooi->eigenaar()` bestaat niet, moet `$toernooi->organisator` zijn
- **Bestanden:** StambestandService.php

### Fix: StambestandService classificatie ontbrak
- **Type:** Bug fix
- **Wat:** Bij import uit stambestand werd geen leeftijdsklasse/gewichtsklasse berekend
- **Bestanden:** StambestandService.php

### Docs: JUDOKA-DATABASE.md feature doc
- **Type:** Documentatie
- **Wat:** Feature doc voor judoka database (stambestand) integratie
- **Bestanden:** laravel/docs/2-FEATURES/JUDOKA-DATABASE.md

---

## Sessie: 6 maart 2026

### Fix: Root cause drag bugs wedstrijddag - vaste vs variabele gewichtsklassen
- **Type:** Bug fix (root cause)
- **Wat:** Gewichtsrange verscheen in poule titels en oranje header bij vaste gewichtsklassen na slepen
- **Root cause:** `WedstrijddagController::verplaatsJudoka()` gebruikte globale `$toernooi->max_kg_verschil` i.p.v. per-category `Poule::isDynamisch()`. Stuurde ook raw DB `titel` i.p.v. `getDisplayTitel()`
- **Fix:** `isDynamisch()` + `isProblematischNaWeging()` per poule, `getDisplayTitel()` voor titels
- **Bestanden:** WedstrijddagController.php, poules.blade.php (wedstrijddag JS)

### Fix: Waarschuwingsdriehoek ontbrak bij vaste gewichtsklassen
- **Type:** Bug fix
- **Wat:** `judoka_past_in_poule` was altijd `true` voor vaste gewichtsklassen, te zware judoka kreeg geen waarschuwing
- **Fix:** `isGewichtBinnenKlasse()` check toegevoegd voor vaste klassen
- **Bestanden:** WedstrijddagController.php, PouleController.php

### Fix: Groene stip verscheen na drag
- **Type:** Bug fix
- **Wat:** `updateJudokaStyling()` overschreef originele iconen (gewogen-stip, afwezig-marker) met waarschuwingsicoon
- **Fix:** Alleen eigen iconen toevoegen/verwijderen, originele iconen ongemoeid laten
- **Bestanden:** poules.blade.php (wedstrijddag JS)

### Refactor: PouleController buildPouleResponse helper
- **Type:** Refactoring
- **Wat:** Herhalende poule response code in `verplaatsJudokaApi` vereenvoudigd naar `buildPouleResponse()` helper
- **Bestanden:** PouleController.php

### Fix: Homepage footer grote lege ruimte
- **Type:** UI fix
- **Wat:** `sticky bottom-0 z-20` verwijderd van footer
- **Bestanden:** home.blade.php

### Fix: Verwarrende 'KLAAR' badge in publieke PWA
- **Type:** UI fix
- **Wat:** "NU" en "KLAAR" tekst badges verwijderd van judoka rijen (achtergrondkleuren + banners zijn voldoende)
- **Bestanden:** publiek/index.blade.php

### Feat: Blauwe 'Op dek' status in publieke PWA
- **Type:** Feature
- **Wat:** Blauw gereedmaken status toegevoegd: banner, achtergrondkleur, tab indicator, sortering (groen > geel > blauw > rest)
- **Bestanden:** publiek/index.blade.php, en.json

### Fix: Cross-poule gereedmaken match niet zichtbaar in favorieten
- **Type:** Bug fix
- **Wat:** `gereedmaken_wedstrijd_id` op mat kan wedstrijd van andere poule refereren, code zocht alleen in huidige poule
- **Fix:** DB fallback: `?? Wedstrijd::find(...)` als wedstrijd niet in huidige poule zit
- **Bestanden:** PubliekController.php

