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

## Sessie: 4 februari 2026

### Refactor: Band enum als enige bron van waarheid
- **Type:** Refactoring + documentatie
- **Wat:**
  - Alle band logica geconsolideerd in `Band.php` enum
  - `BandHelper.php` deprecated (wrapper voor backwards compatibility)
  - Nieuwe methodes: `toKleur()`, `niveau()`, `sortNiveau()`, `getSortNiveau()`, `pastInFilter()`
  - Alle views/controllers gebruiken nu `Band::toKleur()` i.p.v. `stripKyu()`
  - CODE-STANDAARDEN.md sectie 13 volledig gedocumenteerd
- **Volgorde:**
  ```
  wit → geel → oranje → groen → blauw → bruin → zwart
  niveau():      0      1       2        3        4        5       6
  sortNiveau():  1      2       3        4        5        6       7
  value:         6      5       4        3        2        1       0
  ```
- **Regel:** Band = alleen kleur (wit, geel, oranje, etc.) - NOOIT kyu
- **→ Gedocumenteerd in:** `CODE-STANDAARDEN.md §13`

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

### Feat: Mat interface auto-refresh → Reverb push
- **Type:** Feature (vervangen op 10 feb 2026)
- **Wat:** Mat interface refreshte elke 30 sec → nu vervangen door Reverb WebSocket push events
- **Bestanden:** mat/_content.blade.php, interface.blade.php, interface-admin.blade.php, mat-updates-listener.blade.php, MatController.php
- **Logica:** Luistert naar score, beurt, poule_klaar en bracket events via Reverb

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

### UI: Zaaloverzicht navigatie + per-blok reset bij Instellingen
- **Type:** UI improvement
- **Wat:**
  - "Terug naar Blokkenverdeling" link verborgen als voorbereiding klaar (zaaloverzicht)
  - Per-blok "Reset Blok" noodknop toegevoegd bij Instellingen → Organisatie (niet in zaaloverzicht)
- **Bestanden:** zaaloverzicht.blade.php, edit.blade.php, BlokController.php, web.php
- **Reset gedrag:** Verwijdert wedstrijden, reset doorgestuurd_op, behoudt mat toewijzingen

### Fix: Poule titel niet getoond in zaaloverzicht
- **Type:** Bug fix
- **Wat:** Zaaloverzicht toonde "#17 j." i.p.v. "#17 jeugd 27.1-27.9kg"
- **Oorzaak:** View gebruikte `gewichtsklasse` (leeg bij dynamische poules) i.p.v. `titel`
- **Oplossing:** `$poule['titel']` gebruiken in zaaloverzicht.blade.php
- **Bestanden:** zaaloverzicht.blade.php (1 regel fix)

**⚠️ LESSON LEARNED:**
- Probleem was simpel maar kostte 10+ pogingen door zoeken in verkeerde plek
- Eerst: check waar data in VIEW vandaan komt
- Niet meteen backend/services aanpassen

### Fix: Spreker tekst "AL AFGEROEPEN"
- **Type:** UI fix
- **Wat:** Tekst aangepast van "per ongeluk? Klik om terug te zetten" naar "Klik om nog een keer te zien"
- **Bestanden:** spreker/partials/_content.blade.php

### Fix: Live pagina auto-refresh probleem
- **Type:** Bug fix
- **Wat:** Auto-refresh (30s) resettte tab naar 'info', vervangen door handmatige "Ververs" knop
- **Bestanden:** publiek/index.blade.php
- **Oplossing:**
  - setInterval voor live tab verwijderd
  - "Ververs" knop toegevoegd met loading state
  - activeTab opgeslagen in sessionStorage voor tab persistence bij refresh

---

## Sessie: 30 januari 2026

### Feat: Offline backup sync voor noodplan
- **Type:** Feature
- **Wat:** Automatische sync van poule data naar localStorage voor offline printen
- **Bestanden:** NoodplanController.php, app.blade.php, noodplan/index.blade.php
- **Werking:** Fetch polling elke 30 sec, status indicator rechtsonder
- **UI:** "OFFLINE BACKUP" sectie met "Print vanuit backup" knop

### Fix: Free tier toegang testfase
- **Type:** Config change
- **Wat:** Cees Veen en sitebeheerder hebben gratis volledige toegang tijdens testfase
- **Bestanden:** Toernooi.php (isFreeTier methode)
- **Slugs:** 'cees-veen', 'judoschool-cees-veen'

### Fix: Noodknop reset ook zaalindeling
- **Type:** Bug fix
- **Wat:** "Reset Blok naar Eind Voorbereiding" reset nu ook mat_id (zaaloverzicht wordt leeg)
- **Bestanden:** BlokController.php, edit.blade.php
- **Was:** Behield mat toewijzingen, nu volledig reset

### Feat: "Einde weegtijd" knop in hoofdjury weeglijst
- **Type:** Feature
- **Wat:** Knop om weegtijd te sluiten, gekoppeld aan geselecteerd blok in dropdown
- **Bestanden:** interface-admin.blade.php
- **Details:**
  - Knop verschijnt naast blok-filter als een blok geselecteerd is
  - Sluit weegtijd → markeert niet-gewogen judoka's als afwezig
  - Countdown timer en knop altijd zichtbaar (niet alleen wedstrijddag)
  - Toont "Gesloten" als blok al gesloten is
- **Docs:** INTERFACES.md bijgewerkt

---

## Sessie: 31 januari 2026

### Fix: Device-bound PWA routes voor iPad/tablet
- **Type:** Bug fix
- **Wat:** Mat, spreker, dojo interfaces werkten niet op iPad - admin API routes vereisten auth
- **Bestanden:** MatController.php, SprekerController.php, DojoController.php, routes/web.php, views
- **Oplossing:** Device-bound API routes toegevoegd met `device.binding` middleware

### Fix: Best of three instelling niet opgeslagen
- **Type:** Bug fix
- **Wat:** Alpine.js `:value` binding werkte niet correct met form serialization
- **Bestanden:** edit.blade.php
- **Oplossing:** Hidden inputs gebruiken nu `x-ref` + `x-watch` i.p.v. `:value`

### Fix: Toernooi relatie caching in WedstrijdSchemaService
- **Type:** Bug fix
- **Wat:** Service gebruikte gecachte toernooi data, best_of_three werd niet gelezen
- **Bestanden:** WedstrijdSchemaService.php
- **Oplossing:** `$poule->toernooi()->first()` i.p.v. `$poule->toernooi`

### Fix: String keys in wedstrijd_schemas JSON
- **Type:** Bug fix
- **Wat:** JSON decode behoudt string keys ("2"), maar code zocht met int key (2)
- **Bestanden:** WedstrijdSchemaService.php
- **Oplossing:** Check zowel int als string key: `$schemas[$aantal] ?? $schemas[(string) $aantal]`

### Fix: Auto-update schema bij best_of_three toggle
- **Type:** Bug fix
- **Wat:** Custom schema werd niet aangepast wanneer best_of_three werd ingeschakeld
- **Bestanden:** ToernooiController.php
- **Oplossing:** Bij opslaan automatisch `wedstrijd_schemas[2]` updaten naar 3, 2, of 1 wedstrijden

### Fix: Wedstrijddag toont verkeerd aantal wedstrijden
- **Type:** Bug fix
- **Wat:** Poule-card gebruikte formule `n*(n-1)/2` i.p.v. echte count + best_of_three
- **Bestanden:** poule-card.blade.php
- **Oplossing:** Gebruik `$poule->wedstrijden->count()` of schatting met toernooi settings

### Fix: Positie waarde 999 te groot voor tinyint
- **Type:** Bug fix
- **Wat:** `positie => 999` overschreed tinyint max (255) bij judoka toevoegen aan poule
- **Bestanden:** WedstrijddagController.php
- **Oplossing:** Bereken echte volgende positie: `$nieuwePoule->judokas()->count() + 1`

### Feat: Noodplan print schema header rows
- **Type:** UI improvement
- **Wat:** Toernooinaam + datum als header boven elk wedstrijdschema, zelfde breedte als tabel
- **Bestanden:** ingevuld-schema.blade.php, index.blade.php (Live), NoodplanController.php
- **Details:**
  - title-row (donker): toernooinaam + datum
  - info-row (licht): poule nummer, categorie, mat, blok
  - Beide rows als `<tr>` met `colspan` → automatisch zelfde breedte als tabel
  - `toernooi_datum` toegevoegd aan sync-data API response

---

## Sessie: 31 januari 2026 (deel 2)

### Fix: JudokaController route missing organisator
- **Type:** Bug fix
- **Wat:** `route('toernooi.judoka.show', [$toernooi, $judoka])` miste organisator param
- **Bestanden:** JudokaController.php
- **Oplossing:** Gebruik `$toernooi->routeParamsWith(['judoka' => $judoka])`

### Fix: Coach portal band select reset
- **Type:** Bug fix
- **Wat:** Band werd gereset naar "Band" bij openen edit form
- **Bestanden:** coach/judokas.blade.php
- **Oorzaak:** Band was niet doorgegeven aan `judokaEditForm()` Alpine component
- **Oplossing:** Band parameter toegevoegd + `x-model="band"` op select

### Fix: Band kyu cleanup
- **Type:** Data cleanup
- **Wat:** Alle kyu suffixen verwijderd uit band kolom
- **Databases:** Local (SQLite), staging, production (MySQL)
- **SQL:** `UPDATE judokas SET band = LOWER(SUBSTRING_INDEX(band, ' ', 1))`

### Fix: Sync deadline check ontbrak
- **Type:** Bug fix
- **Wat:** `syncJudokasCode()` had geen checks voor portal modus en deadline
- **Bestanden:** CoachPortalController.php
- **Oplossing:** Checks toegevoegd voor `portaalMagWijzigen()` en `isInschrijvingOpen()`

### Fix: Toast overlapt menu
- **Type:** UI fix
- **Wat:** Success toast stond te hoog (`top-4`) en overlapte navigatie
- **Bestanden:** layouts/app.blade.php
- **Oplossing:** `top-4` → `top-20`

### Fix: BlokController route missing organisator
- **Type:** Bug fix
- **Wat:** Route naar `toernooi.blok.index` met `kies` param miste organisator
- **Bestanden:** BlokController.php
- **Oplossing:** `array_merge($toernooi->routeParams(), ['kies' => 1])`

### Fix: CheckToernooiRol middleware routes
- **Type:** Bug fix
- **Wat:** Login redirects in middleware misten organisator param
- **Bestanden:** CheckToernooiRol.php
- **Oplossing:** `$toernooi->routeParams()` gebruiken

### Fix: Noodplan script tag escape
- **Type:** Bug fix
- **Wat:** `<script>` en `</script>` in JS template literal werden door browser geïnterpreteerd
- **Bestanden:** noodplan/index.blade.php
- **Symptoom:** Raw JS code zichtbaar op pagina
- **Oplossing:** `<scr` + `ipt>` en `<\/script>` escape

### Fix: Noodplan live schema dubbele potjes
- **Type:** Bug fix
- **Wat:** Live wedstrijd schema's toonden 3 i.p.v. 6 wedstrijden bij dubbele potjes
- **Bestanden:** noodplan/index.blade.php
- **Oorzaak:** `generateSchema()` maakte standaard round-robin, negeerde instellingen
- **Oplossing:** Gebruik echte wedstrijden uit backup i.p.v. gegenereerd schema

---

## Sessie: 1 februari 2026

### Feat: Startup wizard voor wedstrijddag
- **Type:** Feature
- **Wat:** Stap-voor-stap handleiding voor leken: Primary starten, Standby starten, Deco configureren, Pre-flight check
- **Bestanden:** startup-wizard.blade.php, LocalSyncController.php, routes/web.php, dashboard.blade.php
- **URL:** `/local-server/opstarten`

### Feat: Computernaam prominent tonen
- **Type:** UI improvement
- **Wat:** Hostname automatisch invullen en tonen op alle local server pagina's voor Deco configuratie
- **Bestanden:** setup.blade.php, dashboard.blade.php, emergency-failover.blade.php, health-dashboard.blade.php, preflight.blade.php

### Fix: WP invoer geen auto-fill
- **Type:** Bug fix
- **Wat:** Handmatig WP invoeren vulde automatisch tegenstander in (0→2), nu alleen eigen waarde
- **Bestanden:** mat/_content.blade.php
- **Oplossing:** Auto-fill alleen via JP invoer, niet via WP

### Fix: JP invoer reset en gelijkspel
- **Type:** Bug fix
- **Wat:** JP blanco = reset alles, JP 0 = gelijkspel (beide WP=1)
- **Bestanden:** mat/_content.blade.php
- **Was:** Lege waarde werd als 0 behandeld

### Fix: Groen/geel wedstrijd klik gedrag
- **Type:** UI improvement
- **Wat:** Nieuwe selectie logica voor groen (speelt nu) en geel (volgende)
- **Bestanden:** mat/_content.blade.php
- **Gedrag:**
  - Klik groen → bevestiging, geel wordt groen
  - Klik geel → wordt neutraal
  - Klik grijs (geen groen) → wordt groen
  - Klik grijs (wel groen, geen geel) → wordt geel
  - Klik grijs (wel groen, wel geel) → alert "eerst gele uitzetten"

### Docs: Help pagina bijgewerkt
- **Type:** Documentation
- **Wat:** Wedstrijd selectie tabel en score invoer uitleg toegevoegd
- **Bestanden:** help.blade.php

---

## Sessie: 2 februari 2026

### Feat: Noodplan netwerk status monitoring
- **Type:** Feature
- **Wat:** Live latency meting voor lokaal netwerk en internet op noodplan tab
- **Bestanden:** edit.blade.php, web.php
- **Details:**
  - Lokaal netwerk: ping naar lokale server IP (als ingesteld)
  - Internet: ping naar `/ping` endpoint
  - Automatische check elke 30 sec
  - Status badges met ms weergave

### Feat: Netwerk configuratie UI
- **Type:** Feature
- **Wat:** Keuze MET/ZONDER eigen router, IP-adressen configuratie
- **Bestanden:** edit.blade.php, ToernooiController.php
- **Velden:** heeft_eigen_router, local_server_primary_ip, local_server_standby_ip, hotspot_ip

### Feat: Voorbereiding sectie noodplan
- **Type:** Feature
- **Wat:** Download noodbackup en poule-indeling knoppen
- **Bestanden:** edit.blade.php
- **Details:** Verplaatst van "overstappen" sectie naar aparte voorbereiding box

### Fix: Ping route werkte niet
- **Type:** Bug fix
- **Wat:** API routes niet geladen in Laravel 11, route verplaatst naar web.php
- **Bestanden:** web.php, api.php
- **Oplossing:** `Route::get('/ping', ...)` in web.php

### UI: Lokale server instructies vereenvoudigd
- **Type:** UI improvement
- **Wat:** Technische terminal commando's verwijderd, simpele stappen voor organisatoren
- **Bestanden:** edit.blade.php

### Rename: WiFi → Lokaal netwerk
- **Type:** UI fix
- **Wat:** "WiFi" hernoemd naar "Lokaal netwerk" (ondersteunt WiFi én LAN)
- **Bestanden:** edit.blade.php

---

## Sessie: 3 februari 2026 (deel 2)

### Feat: Zoekfunctie coach portal
- **Type:** UI improvement
- **Wat:** Zoekbalk toegevoegd aan judokas en weegkaarten pagina's in coach portal
- **Bestanden:** coach/judokas.blade.php, coach/weegkaarten.blade.php
- **Werking:** Alpine.js client-side filter op naam

---

## Sessie: 3 februari 2026

### Feat: Email audit log
- **Type:** Feature
- **Wat:** Logging van alle verstuurde emails (uitnodigingen, correctie verzoeken) als bewijs
- **Bestanden:** EmailLog.php (nieuw model), email_logs migration, ClubController.php, JudokaController.php, email-log.blade.php
- **Routes:** `toernooi.email-log` op Clubs pagina

### Fix: Mat import ontbreekt in BlokController
- **Type:** Bug fix
- **Wat:** `Class 'App\Http\Controllers\Mat' not found` bij poule verplaatsen in zaaloverzicht
- **Bestanden:** BlokController.php
- **Oplossing:** `use App\Models\Mat;` import toegevoegd

### Fix: QR code niet zichtbaar op weegkaart
- **Type:** Bug fix
- **Wat:** QRCode library laadde niet vanwege verkeerde CDN URL
- **Bestanden:** weegkaart/show.blade.php, coach-kaart/show.blade.php
- **Oorzaak:** CDN URL `@1.5.3/build/qrcode.min.js` bestond niet
- **Oplossing:** URL gewijzigd naar `/npm/qrcode/build/qrcode.min.js` + retry logic

### Fix: Scroll positie reset na form submit
- **Type:** Bug fix
- **Wat:** Na opslaan van instellingen sprong pagina naar top
- **Bestanden:** edit.blade.php
- **Oplossing:** Global form submit listener voor alle forms, niet alleen main form

### UI: Sticky tabs op instellingen pagina
- **Type:** UI improvement
- **Wat:** Tabs blijven zichtbaar bij scrollen
- **Bestanden:** edit.blade.php
- **CSS:** `sticky top-0 bg-white z-10`

---

---

## Sessie: 5 februari 2026

### Feat: Verdachte gewicht waarschuwingen
- **Type:** Feature
- **Wat:** Waarschuwingen voor verdachte/onrealistische gewichten op meerdere plekken
- **Bestanden:** weging/_content.blade.php, poule-card.blade.php, poules.blade.php
- **Details:**
  - Weegapp: Confirmation dialog bij > 2 kg afwijking van opgegeven gewicht
  - Weegapp: Rode markering (🚨) in history voor verdachte gewichten
  - Wedstrijddag poule cards: Rode border en 🚨 icoon voor verdachte gewichten
  - Criteria: < 15 kg OF > 5 kg afwijking van opgave

### Fix: Undefined variable $isVerkeerdePoule
- **Type:** Bug fix
- **Wat:** Error in wedstrijddag poule-card.blade.php
- **Bestanden:** poule-card.blade.php
- **Oplossing:** Variable verwijderd, styling nu gekoppeld aan $heeftProbleem

### Fix: Gewogen indicator check
- **Type:** Bug fix
- **Wat:** `gewicht_gewogen !== null` → `gewicht_gewogen > 0`
- **Bestanden:** 11+ bestanden (controllers, views, services)
- **Reden:** PHP truthiness: `0 !== null` is TRUE maar `0 > 0` is FALSE

### Feat: Adaptive polling voor live pagina
- **Type:** Feature
- **Wat:** Configureerbare/adaptive refresh voor publiek pagina
- **Bestanden:** publiek/index.blade.php, migration, Toernooi.php, ToernooiRequest.php
- **Details:**
  - Nieuwe kolom: `live_refresh_interval` (5/10/15/30/60 sec)
  - Adaptive: 5 sec tijdens activiteit, 60 sec bij idle
  - Debug panel (dubbel-klik op LIVE/POLL knop)

### Oops: Staging database reset
- **Type:** Oops
- **Wat:** migrate:fresh uitgevoerd op staging zonder seeder
- **Oplossing:** Gebruiker maakt nieuwe testdata aan via app

---

## Sessie: 4 februari 2026

### Feat: LIVE/OFFLINE connectie indicator Publiek PWA
- **Type:** Feature
- **Wat:** Globale connectie status knop in header (groen=LIVE, blauw=OFFLINE)
- **Bestanden:** publiek/index.blade.php, mat-updates-listener.blade.php
- **Details:** Klikbaar om te verversen, animate-pulse bij OFFLINE
- **Docs:** CHAT.md sectie 2 bijgewerkt

### Fix: JP 0 niet getoond bij verliezer
- **Type:** Bug fix
- **Wat:** JavaScript `0 || ''` geeft `''`, nu `!== undefined` check
- **Bestanden:** WedstrijdSchemaService.php, mat/_content.blade.php
- **Backend:** Zet lege score naar '0' als er winnaar is
- **Frontend:** Check met `!== undefined` i.p.v. truthy

### Fix: Afwezige judoka's in uitslagen
- **Type:** Bug fix
- **Wat:** Judoka's zonder gewogen gewicht of status 'afwezig' gefilterd uit standings
- **Bestanden:** RoleToegang.php, BlokController.php, PubliekController.php
- **Filter:** `gewicht_gewogen !== null && aanwezigheid !== 'afwezig'`

### Fix: Gelijkspel punten niet geteld
- **Type:** Bug fix
- **Wat:** Draw (winnaar_id=NULL) gaf 0 WP, moet 1 WP zijn
- **Bestanden:** RoleToegang.php, BlokController.php, PubliekController.php
- **Logica:** Win=2, Draw=1, Loss=0

### Fix: Oproepen per poule met nummer
- **Type:** UI improvement
- **Wat:** Filter afwezige judoka's + toon poelenummer in titel
- **Bestanden:** spreker/_content.blade.php
- **Format:** "Poule 1 - Jeugd 27.1-27.9kg"

### UI: Notities tekstveld groter
- **Type:** UI fix
- **Wat:** Textarea `min-height: 400px`, `rows="15"`, `resize-y`
- **Bestanden:** spreker/_content.blade.php

### Docs: Deploy commands vereenvoudigd
- **Type:** Documentation
- **Wat:** 8 individuele cache commands → 2 (optimize:clear + optimize)
- **Bestanden:** .claude/deploy.md

---

## Sessie: 5 februari 2026 (ochtend)

### Refactor: Live verversing naar organisator niveau
- **Type:** Refactoring
- **Wat:** `live_refresh_interval` verplaatst van toernooi naar organisator
- **Bestanden:** Migration, Organisator.php, web.php, ToernooiController.php, organisator/instellingen.blade.php, app.blade.php, edit.blade.php, publiek/index.blade.php
- **Details:** Nieuwe "Instellingen" pagina in hamburger menu voor organisator-level settings

### Fix: Clubs unique constraint per organisator
- **Type:** Bug fix
- **Wat:** `clubs.naam` was globaal uniek, nu uniek per organisator
- **Bestanden:** Migration 2026_02_05_084602
- **Oorzaak:** Zelfde clubnaam (bv "Judoschool Cees Veen") mag bestaan bij meerdere organisatoren

### Feat: Auto-create organisator's eigen club
- **Type:** Feature
- **Wat:** Organisator's eigen judoschool wordt automatisch aangemaakt bij bezoeken clubs pagina
- **Bestanden:** ClubController.php
- **Reden:** Organisator (judoschool) doet vaak mee aan eigen toernooien

### Fix: Noodplan schema's waarschuwing ongewogen judoka's
- **Type:** UI improvement
- **Wat:** Waarschuwing wanneer weging verplicht is maar judoka's niet gewogen
- **Bestanden:** ingevuld-schema.blade.php, NoodplanController.php
- **Details:** Banner met aantal ongewogen + tips

### Data: Oude club zonder organisator verwijderd
- **Type:** Data cleanup
- **Wat:** Club id=13 "Judoschool Cees Veen" met NULL organisator_id verwijderd
- **Database:** Production

---

## Sessie: 7 februari 2026

### Feat: Geboortejaar parser compleet herschreven
- **Type:** Feature improvement
- **Wat:** `parseGeboortejaar()` (PHP) en `extractJaar()` (JS) ondersteunen nu alle denkbare datumformaten
- **Bestanden:** ImportService.php, import-preview.blade.php
- **Ondersteund:**
  - Gewoon jaar (2015), 2-digit jaar (15), Excel serial (43831, 43831.5, 43831,5)
  - Alle separators: `-` `/` `.` `\` en spaties
  - Spaties rond separators: `24 - 01 - 2015`
  - Compact zonder separators: YYYYMMDD (20150124), DDMMYYYY (24012015), DDMMYY (240115)
  - Haakjes/brackets: (2015), [24-01-2015]
  - Nederlandse maandnamen: januari, februari, mrt, okt, etc.
  - Nederlandse ordinals: 24ste, 1e, 2de
  - Engelse natural language, ISO 8601, datetime strings

### Fix: CSP blokkeert Nominatim API
- **Type:** Bug fix
- **Wat:** `nominatim.openstreetmap.org` toegevoegd aan CSP `connect-src` directive
- **Bestanden:** SecurityHeaders.php
- **Symptoom:** Locatie zoeken bij toernooi aanmaken/bewerken werkte niet op staging/production

### Feat: Automatische milestone backup bij poule→mat verdeling
- **Type:** Feature
- **Wat:** BackupService maakt mysqldump vóór destructieve operaties in zetOpMat()
- **Bestanden:** BackupService.php (nieuw), BlokController.php
- **Details:**
  - Alleen op production/staging (MySQL), skip op local (SQLite)
  - Backup in `/var/backups/havun/milestones/` met label + timestamp
  - Max 20 backups bewaard, auto-cleanup
  - Herbruikbaar voor andere mijlpalen: `$backupService->maakMilestoneBackup('label')`

---

## Sessie: 8 februari 2026

### Fix: Blokverdeling vaste categorieën classificatie
- **Type:** Bug fix
- **Wat:** Alleen `max_kg_verschil` bepaalt nu of categorie vast/variabel is. `max_leeftijd_verschil` is irrelevant voor vaste categorieën met gewichten.
- **Bestanden:** blok/index.blade.php, CategorieHelper.php, VariabeleBlokVerdelingService.php
- **Was:** `max_kg == 0 && max_lft == 0` → vast. Nu: `max_kg == 0` → vast.

### Fix: Blokverdeling sortering op categorie_key
- **Type:** Bug fix
- **Wat:** Sortering gebruikt nu `categorie_key` (config positie) ipv `leeftijdsklasse` label matching voor robuustere volgorde (jong→oud, licht→zwaar).
- **Bestanden:** blok/index.blade.php
- **Details:** `categorie_key` toegevoegd aan chip data arrays

### Feat: Kruisfinale/eliminatie volgen voorronde blok
- **Type:** Feature
- **Wat:** `fixKruisfinaleBlokken()` wist kruisfinale/eliminatie poules toe aan hetzelfde blok als hun voorronde
- **Bestanden:** VariabeleBlokVerdelingService.php

### Fix: Kruisfinale/eliminatie chips niet sleepbaar
- **Type:** UI fix
- **Wat:** Kruisfinale/eliminatie chips `draggable="false"` + `cursor-default` (volgen automatisch voorronde)
- **Bestanden:** _category_chip.blade.php

---

## Sessie: 8 februari 2026 (avond)

### Fix: Eliminatie bracket rendering - ontbrekende rondes
- **Type:** Bug fix
- **Wat:** `tweeendertigste_finale` en `b_zestiende_finale_1/2` misten in lookup tabellen → order 99 → bracket kapot
- **Bestanden:** mat/partials/_content.blade.php
- **Oplossing:** Toegevoegd aan `rondeVolgordeLookup`, `getRondeDisplayNaam`, `rondeNiveauMap`
- **Regel:** Bij nieuwe ronde-namen ALTIJD alle 3 lookups bijwerken!

### Fix: B-bracket slot nummers omgekeerd in onderste helft
- **Type:** Bug fix
- **Wat:** `mirroredIdx = sortedWeds.length - 1 - i` draaide volgorde om
- **Bestanden:** mat/partials/_content.blade.php
- **Oplossing:** Expliciete visuele slot nummers `i * 2 + 1` / `i * 2 + 2` meegeven

### Fix: A-bracket finale slot [1]/[2] positionering
- **Type:** UI fix
- **Wat:** Goud/zilver slots niet goed uitgelijnd achter finale wedstrijd
- **Bestanden:** mat/partials/_content.blade.php
- **Oplossing:** `finaleTop = berekenPotjeTop(...)`, zilver op `finaleTop + h`

### Feat: Wedstrijdentelling in poule titel
- **Type:** UI improvement
- **Wat:** Poule titel toont nu `(54 judoka's, 103w)` i.p.v. alleen judoka's
- **Bestanden:** mat/partials/_content.blade.php

### Fix: B-bracket byes niet verspreid
- **Type:** Bug fix
- **Wat:** B-start(1) wedstrijden werden 2:1 gevuld, lege wedstrijden bleven leeg
- **Bestanden:** EliminatieService.php (`koppelARondeAanBRonde` type 'eerste')
- **Oplossing:** Verliezers verspreid: eerste batch 2:1, rest 1:1 op WIT (bye)
- **Voorbeeld:** N=54: 22 verliezers in 16 B(1) weds → 6 vol + 10 byes
- **Regel:** B-bracket byes worden handmatig door hoofdjury geregistreerd (GEEN auto-doorschuif)

---

---

## Sessie: 14 februari 2026

### Refactor: Page Builder verwijderd → Havunity
- **Type:** Grote opschoning
- **Wat:** Pagina builder uit JudoToernooi gehaald, wordt apart product (Havunity)
- **Verwijderd (28 bestanden, 4077 regels):**
  - `PaginaBuilderController.php`
  - `pagina-builder.blade.php` + `pagina-builder-pro.blade.php`
  - 3 pro-partials (`pro-content`, `pro-section`, `pro-header-footer`)
  - 19 block partials (`blocks/` directory)
  - 4 pagina-builder routes uit `web.php`
- **Publieke info tab:** Vereenvoudigd naar standaard content (icon + judoschool naam + toernooi info)
- **Edit pagina:** Pagina Builder knop → Preview link + URL kopiëren knop
- **Test tab → Admin tab:** Hernoemd in edit.blade.php
- **Havunity:** Nieuw project `D:\GitHub\Havunity\` met PLAN.md, CLAUDE.md, .claude/context.md

---

## Sessie: 16 februari 2026

### Fix: DnD voor judoka's in eliminatie poules
- **Type:** Bug fix
- **Wat:** Eliminatie poule containers misten `sortable-poule` class, `data-poule-id`, en judoka divs misten `judoka-item`, `data-judoka-id`, `draggable="true"` → SortableJS initialiseerde niet
- **Bestanden:** poules.blade.php (variabel + vast toernooi secties)
- **Oplossing:** Juiste classes en data-attributen toegevoegd aan beide secties

### Fix: Eliminatie poule titelbalk counts na DnD
- **Type:** Bug fix
- **Wat:** Aantal judoka's en wedstrijden werden niet bijgewerkt na DnD van/naar eliminatie poules
- **Bestanden:** poules.blade.php
- **Oorzaak:** Wrappers misten `.poule-card`, `data-poule-id`, `.poule-actief`/`.poule-wedstrijden` spans
- **Oplossing:**
  - HTML: `.poule-card`, `data-poule-id`, `data-poule-nummer`, `data-type="eliminatie"` op wrappers
  - HTML: `.poule-actief` + `.poule-wedstrijden` spans in headers
  - JS: `berekenEliminatieWedstrijden()` (2N-5 formule)
  - JS: `updatePouleCountsFromServer()` helper voor exacte waarden uit API response
  - JS: `updatePouleFromDOM()` skip blauw resetten voor eliminatie (behoud oranje)
  - Alle 4 DnD response handlers roepen nu `updatePouleCountsFromServer()` aan

### Fix: #undefined in eliminatie poule titels na DnD
- **Type:** Bug fix
- **Wat:** `updatePouleTitel()` gebruikte `pouleCard.dataset.pouleNummer` maar dat ontbrak op eliminatie wrappers
- **Bestanden:** poules.blade.php
- **Oplossing:** `data-poule-nummer="{{ $elimPoule->nummer }}"` toegevoegd

### UI: select-none op zaaloverzicht en poules pagina
- **Type:** UI fix
- **Wat:** Tekst selectie uitgeschakeld om DnD niet te verstoren
- **Bestanden:** zaaloverzicht.blade.php, poules.blade.php

---

## Sessie: 15 februari 2026

### Feat: Danpunten (JBN) registratie
- **Type:** Feature
- **Wat:** Compleet danpunten systeem voor bruine banden: toernooi toggle, JBN lidnummer per judoka, CSV export voor JBN
- **Bestanden:** Migration, Toernooi.php, Judoka.php, PubliekController.php, ImportService.php, CoachPortalController.php, edit.blade.php, coach/judokas.blade.php, judoka/edit.blade.php, resultaten/organisator.blade.php, web.php
- **Docs:** `docs/2-FEATURES/DANPUNTEN.md` (nieuw), `docs/README.md` (link toegevoegd)
- **→ Gedocumenteerd in:** `DANPUNTEN.md`

### Fix: Str class import in danpunten export
- **Type:** Bug fix
- **Wat:** `Class "App\Http\Controllers\Str" not found` op staging
- **Bestanden:** PubliekController.php
- **Oplossing:** `\Illuminate\Support\Str::slug()` (fully qualified, zelfde patroon als exportUitslagen)

### UI: Instellingen pagina blokken reorganisatie
- **Type:** UI improvement
- **Wat:** Weging, Dojo/Coach en Danpunten als 3 aparte blokken (waren allemaal in Weging)
- **Bestanden:** edit.blade.php
- **Details:**
  - "Dojo / Coach" blok: judoka's per coach kaart + coach in/uitcheck bij dojo
  - "Danpunten (JBN)" blok: danpunten registreren checkbox
  - "Weging" blok: alleen weging-gerelateerde instellingen

---

<!--
TEMPLATE:

### Fix: [korte titel]
- **Type:** Bug fix / Performance / Refactor
- **Wat:** [wat aangepast]
- **Bestanden:** [welke files]
-->
