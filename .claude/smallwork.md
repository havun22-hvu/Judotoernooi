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

## Sessie: 25 januari 2026

### Fix: Variabele gewichtscategorieën
- **Type:** Bug fix
- **Wat:** Per-poule `isDynamisch()` check i.p.v. globaal
- **Bestanden:** Wedstrijddag controller + views

### Fix: Poule breedte
- **Type:** UI fix
- **Wat:** Grid layout (grid-cols-3) i.p.v. flex-wrap met min-width
- **Bestanden:** Wedstrijddag poules view

### Fix: VARIABEL vs VAST toernooi layout
- **Type:** Bug fix
- **Wat:** Aparte layouts voor variabel (4 kolommen, geen headers) vs vast (headers + wachtruimte)
- **Bestanden:** poules.blade.php, ToernooiController.php

### Fix: Titel formaat met slashes
- **Type:** UI improvement
- **Wat:** `#1 Jeugd / 5-7j / 16.1-18.3kg` i.p.v. `#1 Jeugd 5-7j 16.1-18.3kg`
- **Bestanden:** poule-card.blade.php, poules.blade.php, poule/index.blade.php

### Fix: Eliminatie poule UX
- **Type:** UI improvement
- **Wat:** Zoekfunctie per judoka, info tooltip, → naar matten knop
- **Bestanden:** poules.blade.php

### Feat: Nieuwe poule knop in blokbalk
- **Type:** Feature
- **Wat:** Groene "+ Poule" knop in wedstrijddag blokbalk
- **Bestanden:** poules.blade.php, WedstrijddagController.php

### Fix: Weegkaart modal skip voor portal
- **Type:** Bug fix
- **Wat:** "Weegkaart opslaan?" modal niet tonen bij portal/organisator toegang, alleen bij QR-scan smartphone
- **Bestanden:** weegkaart/show.blade.php, coach/weegkaarten.blade.php, judoka/show.blade.php
- **Oplossing:** `?from_portal` query parameter

### Fix: Weegkaart band zonder kyu
- **Type:** UI fix
- **Wat:** Band tonen als "Blauw" i.p.v. "Blauw (2e kyu)"
- **Bestanden:** weegkaart/show.blade.php
- **Regel:** `explode(' ', $judoka->band)[0]` - alleen eerste woord

### Fix: Nieuwe lege poules niet zichtbaar
- **Type:** Bug fix
- **Wat:** Handmatig aangemaakte lege poules werden uitgefilterd bij variabel gewicht
- **Bestanden:** WedstrijddagController.php
- **Oplossing:** Poules aangemaakt binnen 24h altijd tonen

### Fix: Eliminatie poule te weinig judoka's
- **Type:** Bug fix
- **Wat:** Eliminatie poules met <8 judoka's nu ook als problematisch (rood) gemarkeerd
- **Bestanden:** poule-card.blade.php
- **Regel:** `$isProblematisch = $aantalActief > 0 && $aantalActief < ($isEliminatie ? 8 : 3)`

### Feat: Barrage systeem voor 3-weg gelijkspel
- **Type:** Feature
- **Wat:** Detecteert 3+ judoka's met gelijke WP+JP en cirkel-verliezen, toont "Barrage" knop
- **Bestanden:** BlokController.php, mat/_content.blade.php, Poule.php
- **Migration:** barrage_van_poule_id in poules tabel
- **Logica:** Barrage poule wordt aangemaakt op zelfde mat, judoka's blijven ook in originele poule

### Fix: Poule verplaatsen vereenvoudigd
- **Type:** Bug fix
- **Wat:** verplaatsPoule update alleen mat_id, geen onnodige resets meer
- **Bestanden:** BlokController.php
- **Behouden:** Alle wedstrijden, scores, voortgang intact bij verplaatsen

### Feat: Mat interface auto-refresh
- **Type:** Feature
- **Wat:** Mat interface refresht elke 30 sec voor verplaatste poules
- **Bestanden:** mat/_content.blade.php
- **Logica:** `setInterval(() => laadWedstrijden(), 30000)`

### Fix: Barrage altijd single round-robin
- **Type:** Bug fix
- **Wat:** Barrage poules negeren dubbel_bij_3_judokas config, altijd 1x tegen elkaar
- **Bestanden:** WedstrijdSchemaService.php
- **Logica:** Check `$poule->type === 'barrage'` → force single round-robin schema

---

## Sessie: 26 januari 2026

### Fix: Spreker oproepen filter
- **Type:** Bug fix
- **Wat:** Alleen doorgestuurde poules tonen in oproepen tab
- **Bestanden:** BlokController.php, RoleToegang.php
- **Filter:** `whereNotNull('doorgestuurd_op')`

### Fix: Spreker auto-refresh vervangen
- **Type:** UI improvement
- **Wat:** Vervelende 10s auto-refresh vervangen door handmatige "Vernieuwen" knop
- **Bestanden:** spreker/_content.blade.php

### Feat: Spreker geschiedenis klikbaar
- **Type:** Feature
- **Wat:** Eerder afgeroepen poules klikbaar → modal met uitslagen
- **Bestanden:** spreker/_content.blade.php, BlokController.php, RoleToegang.php, web.php
- **Route:** `POST /spreker/standings`

### Fix: Organisator kan toernooi niet verwijderen
- **Type:** Bug fix
- **Wat:** Na delete werd geredirect naar /toernooi (sitebeheerder-only), nu naar dashboard
- **Bestanden:** ToernooiController.php
- **Oplossing:** Sitebeheerder → toernooi.index, organisator → organisator.dashboard

### Feat: Standaard categorie bij nieuw toernooi
- **Type:** Feature
- **Wat:** Bij nieuw toernooi (zonder template) wordt standaard categorie aangemaakt
- **Bestanden:** ToernooiService.php
- **Config:** max_lft=99, v.lft=1, v.kg=3, v.band=2, band_streng_beginners=true

### Fix: Preset opslaan werkte niet
- **Type:** Bug fix
- **Wat:** `presetScrollPosition is not defined` error bij opslaan preset
- **Bestanden:** edit.blade.php
- **Oorzaak:** Ongebruikte variabele die niet was gedefinieerd

### Fix: Separate URL/PIN copy buttons
- **Type:** UI fix
- **Wat:** Device toegangen kopieert nu URL en PIN apart i.p.v. samen
- **Bestanden:** device-toegangen.blade.php
- **Oplossing:** Twee aparte knoppen met eigen `copyUrl()` en `copyPin()` functies

### Fix: PIN paste in login
- **Type:** Bug fix
- **Wat:** Plakken van PIN verdeelt nu alle cijfers over de 4 invoervelden
- **Bestanden:** toegang/pin.blade.php
- **Oplossing:** `handlePaste()` functie die geplakte tekst filtert en verdeelt

---

## Sessie: 27 januari 2026

### Refactor: URL Structuur met Organisator Context
- **Type:** Major refactor
- **Wat:** Alle URLs nu met organisator context: `/{organisator}/toernooi/{toernooi}/...`
- **Bestanden:** 15+ controllers, 20+ views, routes/web.php
- **Docs:** `docs/URL-STRUCTUUR.md`, `docs/2-FEATURES/FREEMIUM.md`

### Fix: Route parameters in views
- **Type:** Bug fix
- **Wat:** Alle `route(..., $toernooi)` vervangen door `route(..., $toernooi->routeParams())`
- **Bestanden:** 9 blade files (layouts, publiek, spreker, toernooi, wedstrijddag)
- **Reden:** Nieuwe URL structuur vereist organisator+toernooi in routes

### Fix: Controller Organisator parameter
- **Type:** Bug fix
- **Wat:** Alle controller methods krijgen `Organisator $organisator` als eerste parameter
- **Bestanden:** 15 controllers via batch script
- **Oplossing:** Laravel route model binding verwacht organisator parameter

---

## Sessie: 28 januari 2026

### Fix: Upgrade pagina vereenvoudigd
- **Type:** UI improvement
- **Wat:** Dropdown selector i.p.v. meerdere kaarten, prijs per 50 judokas
- **Bestanden:** upgrade.blade.php

### Fix: Default sortering prioriteit
- **Type:** Config change
- **Wat:** Gewijzigd naar band → gewicht → leeftijd (was leeftijd → gewicht → band)
- **Bestanden:** PouleIndelingService.php, DynamischeIndelingService.php, edit.blade.php

### Fix: Default portal modus
- **Type:** Config change
- **Wat:** Default portal modus nu 'mutaties' (was 'uit')
- **Bestanden:** edit.blade.php

### Fix: Paid tier upgrade velden
- **Type:** Bug fix
- **Wat:** ToernooiBetalingController zette verkeerde velden na upgrade
- **Bestanden:** ToernooiBetalingController.php
- **Oplossing:** `plan_type => 'paid'`, `paid_tier => $tier`, `paid_max_judokas => $max`

### Fix: Kyu notatie verwijderd
- **Type:** UI fix
- **Wat:** Band toont nu alleen kleur (bijv. "Wit" i.p.v. "Wit (6e kyu)")
- **Bestanden:** judoka/index.blade.php, judoka/show.blade.php, coach/weegkaarten.blade.php

### Fix: Club portal pincode
- **Type:** Bug fix
- **Wat:** Gebruikte globale `$club->pincode` i.p.v. toernooi-specifieke `pivot->pincode`
- **Bestanden:** club/index.blade.php, ClubUitnodigingMail.php, CorrectieVerzoekMail.php, ClubController.php
- **Oplossing:** `$club->getPincodeForToernooi($toernooi)` en pivot data laden

### Feat: Bulk club selectie
- **Type:** Feature
- **Wat:** "Alles aan" en "Alles uit" knoppen voor clubs
- **Bestanden:** club/index.blade.php, ClubController.php, routes/web.php
- **Routes:** `club.select-all`, `club.deselect-all`

### Fix: Template save 500 error
- **Type:** Bug fix
- **Wat:** (1) Organisator parameter ontbrak in controller, (2) NULL constraint op portal_modus
- **Bestanden:** ToernooiTemplateController.php, ToernooiTemplate.php
- **Oplossing:** Default values voor portal_modus ('mutaties') en betaling_actief (false)

### Fix: Poule titel gewichtsrange niet bijgewerkt
- **Type:** Bug fix
- **Wat:** Poule titel behield oude gewichtsrange wanneer judoka uit poule werd gehaald
- **Bestanden:** poules.blade.php
- **Oorzaak:** `updatePouleTitel()` deed `return` bij null gewichts_range, waardoor titel niet werd geüpdatet
- **Oplossing:** Functie update nu altijd titel, met kg-range als aanwezig, zonder als poule leeg
- **Note:** Bug zat er al vanaf originele implementatie (bf37db5), was geen edge case voor lege poule

### Fix: Wedstrijdschema generatie filterde judoka's te streng
- **Type:** Bug fix
- **Wat:** Bij dynamische categorieën werden judoka's onterecht gefilterd op gewichtsklasse
- **Bestanden:** WedstrijdSchemaService.php
- **Oorzaak:** `isGewichtBinnenKlasse()` check keek naar judoka's eigen gewichtsklasse, irrelevant bij dynamisch
- **Oplossing:** Skip gewichtsklasse check voor dynamische categorieën (`$poule->isDynamisch()`)

### UI: Zaaloverzicht navigatie en per-blok reset
- **Type:** UI improvement
- **Wat:**
  - "Terug naar Blokkenverdeling" link verborgen als voorbereiding klaar
  - Per-blok "Reset Blok" knop toegevoegd (alleen zichtbaar als er wedstrijden zijn)
- **Bestanden:** zaaloverzicht.blade.php, BlokController.php, web.php
- **Reset gedrag:** Verwijdert wedstrijden, reset doorgestuurd_op, behoudt mat toewijzingen

---

<!--
TEMPLATE:

### Fix: [korte titel]
- **Type:** Bug fix / Performance / Refactor
- **Wat:** [wat aangepast]
- **Bestanden:** [welke files]
-->
