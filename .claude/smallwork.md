# Smallwork Log

> Kleine technische fixes die niet in permanente docs hoeven.
>
> **Wat hoort hier:**
> - Typos in code (variabele namen, comments)
> - Bug fixes (iets werkt niet zoals spec zegt)
> - Performance optimalisaties
> - Technische refactoring
> - Dependency updates
>
> **Wat hoort hier NIET:**
> - Features → SPEC.md of FEATURES.md
> - Styling/design → STYLING.md
> - Prijzen/teksten → relevante doc
> - User flows → relevante doc

---

## Sessie: 17 januari 2026

### Feat: Revalidatie na verplaatsen judoka naar wachtruimte
- **Type:** Enhancement
- **Wat:** Na drag naar wachtruimte wordt poule opnieuw gevalideerd op gewichtsrange
- **Waarom:** Oranje marking bleef staan ook als poule nu OK was
- **Bestanden:**
  - `app/Http/Controllers/WedstrijddagController.php` - `isProblematischNaWeging()` response
  - `resources/views/pages/wedstrijddag/poules.blade.php` - JS update + console.log
- **Naar permanente docs?** ☑ Nee - technische enhancement

### Feat: Vergrootglas zoek-match icoon bij judoka's
- **Type:** Enhancement
- **Wat:** 🔍 knop bij elke judoka om geschikte poules te zoeken
- **Waarom:** Organisator kon niet snel zien waar judoka naartoe kon
- **Bestanden:** `resources/views/pages/wedstrijddag/poules.blade.php`
- **Naar permanente docs?** ☑ Nee - UI enhancement

### Fix: Oranje border bij initiële render voor gewichtsproblemen
- **Type:** Bug fix
- **Wat:** Check `$problematischeGewichtsPoules->has($poule->id)` toegevoegd aan PHP render
- **Waarom:** Oranje border werd alleen via JS gezet, niet bij page load
- **Bestanden:** `resources/views/pages/wedstrijddag/poules.blade.php`
- **Naar permanente docs?** ☑ Nee - technische fix

### FIX: Vals-positieve gewichtsrange markering
- **Type:** Bug fix
- **Wat:** Poules werden onterecht oranje gemarkeerd (gewichtsprobleem) terwijl range OK was
- **Oorzaak:** `isProblematischNaWeging()` gebruikte fallback `?? 3` voor `max_kg_verschil`, waardoor bij config mismatch een verkeerde waarde werd gebruikt
- **Oplossing:** Extra check toegevoegd: als `max_kg_verschil <= 0` na config lookup, behandel als niet-problematisch (consistent met `isDynamisch()` logica)
- **Bestanden:** `app/Models/Poule.php:276-298` - `isProblematischNaWeging()`
- **Naar permanente docs?** ☑ Nee - bug fix

### FIX: Poule header kleur bleef oranje
- **Type:** Bug fix (gerelateerd aan bovenstaande)
- **Wat:** Header was oranje i.p.v. blauw bij poules met >= 3 judoka's
- **Oorzaak:** Zelfde root cause - `$heeftGewichtsprobleem` was true door vals-positieve detectie
- **Oplossing:** Opgelost met bovenstaande fix
- **Naar permanente docs?** ☑ Nee - bug fix

---

## Sessie: 20 januari 2026

### Feat: Wedstrijdsysteem dropdown voor alle poule types
- **Type:** Feature
- **Wat:** Dropdown "Omzetten" toegevoegd aan alle poule types (voorronde, eliminatie, kruisfinale)
- **Waarom:** Organisator wilde ook van poule naar eliminatie kunnen, niet alleen andersom
- **Opties:**
  - Poule → Eliminatie, Poules + kruisfinale
  - Eliminatie → Alleen poules, Poules + kruisfinale
  - Kruisfinale → Alleen poules, Eliminatie
- **Bestanden:**
  - `resources/views/pages/poule/index.blade.php` - dropdown HTML + JS
  - `app/Http/Controllers/WedstrijddagController.php` - `wijzigPouleType()` methode
  - `routes/web.php` - nieuwe route
- **Naar permanente docs?** ☑ Nee - UI feature

### Fix: Lege kruisfinales verwijderbaar
- **Type:** Bug fix
- **Wat:** (-) knop nu ook zichtbaar voor lege kruisfinales
- **Waarom:** Na omzetten naar eliminatie kon kruisfinale niet verwijderd worden
- **Bestanden:** `resources/views/pages/poule/index.blade.php:247`
- **Naar permanente docs?** ☑ Nee - UI fix

### Fix: Gewichtsklasse headers verwijderd
- **Type:** Simplify
- **Wat:** Headers "50-54 kg" boven poule groepen verwijderd
- **Waarom:** Poule titels zijn duidelijk genoeg, headers waren verwarrend
- **Bestanden:** `resources/views/pages/poule/index.blade.php`
- **Naar permanente docs?** ☑ Nee - UI simplification

### Fix: P → # voor poule nummers in blokken
- **Type:** Consistency fix
- **Wat:** "P1" gewijzigd naar "#1" in blokoverzicht
- **Waarom:** Consistentie met poule pagina (overal # prefix)
- **Bestanden:** `resources/views/pages/blok/index.blade.php:427`
- **Naar permanente docs?** ☑ Ja → context.md (UI Conventies tabel)

### Feat: Coachkaarten berekening + genereer knop
- **Type:** Feature
- **Wat:** Club pagina toont nu huidig/benodigd aantal coachkaarten + "Genereer Coachkaarten" knop
- **Berekening:** drukste blok per club ÷ judokas_per_coach (afgerond naar boven)
- **Bestanden:**
  - `app/Http/Controllers/ClubController.php` - benodigdeKaarten berekening
  - `resources/views/pages/club/index.blade.php` - huidig/benodigd display + knop
- **Naar permanente docs?** ☑ Nee - UI feature (logica bestond al in Club model)

---

## Sessie: 21 januari 2026

### Fix: Groen/Geel wedstrijd selectie systeem (Mat Interface)
- **Type:** Bug fix (KRITIEK)
- **Wat:** Groen/geel systeem werkt nu correct:
  - **Groen** = speelt nu (actieve_wedstrijd_id)
  - **Geel** = klaar maken (huidige_wedstrijd_id)
  - Als groene wedstrijd punten krijgt → gele wordt groen
- **Probleem was:** Gele werd niet groen, volgende sequentiële nummer werd groen
- **Oorzaak:** `saveScore()` had complexe logica die verkeerd werkte + API route gaf 404
- **Oplossing:**
  1. `saveScore()` vereenvoudigd - gebruikt nu `getHuidigeEnVolgende()` correct
  2. `setWedstrijdStatus()` helper functie opgeschoond
  3. Debug logging verwijderd
- **Bestanden:**
  - `resources/views/pages/mat/partials/_content.blade.php` (saveScore, setWedstrijdStatus)
- **Naar permanente docs?** ☑ Nee - technische bugfix

### Fix: Telefoon veld toegevoegd aan judoka import + coach portal
- **Type:** Enhancement
- **Wat:** Telefoon veld toegevoegd aan:
  - Import CSV (auto-detectie kolom)
  - Coach portal (toevoegen + bewerken)
  - Club pagina (WhatsApp link)
- **Waarom:** WhatsApp contact met coaches/ouders
- **Bestanden:**
  - `app/Services/ImportService.php` - kolom detectie + parseTelefoon()
  - `resources/views/pages/coach/judokas.blade.php` - form velden
  - `resources/views/pages/club/index.blade.php` - telefoon kolom
- **Naar permanente docs?** ☑ Nee - feature enhancement

---

## Sessie: 21 januari 2026 (vervolg)

### Feat: Per-poule chips in Zaaloverzicht
- **Type:** Feature (BELANGRIJK)
- **Wat:** Zaaloverzicht toont nu elke poule als aparte chip i.p.v. per categorie
- **Waarom:** Bij variabele categorieën zijn er meerdere poules met dezelfde leeftijdsklasse+gewichtsklasse, die moeten apart geactiveerd kunnen worden
- **Chip format:** `{leeftijdsklasse} {gewichtsklasse} #{poule_nummer}` (bijv. "Jeugd -24 #5")
- **Bestanden:**
  - `app/Http/Controllers/BlokController.php` - `getCategoryStatuses()` herschreven voor per-poule status
  - `app/Http/Controllers/BlokController.php` - `activeerPoule()` en `resetPoule()` methods toegevoegd
  - `resources/views/pages/blok/zaaloverzicht.blade.php` - view toont nu per-poule chips
  - `routes/web.php` - routes `blok.activeer-poule` en `blok.reset-poule` toegevoegd
- **Naar permanente docs?** ☑ Ja → GEBRUIKERSHANDLEIDING.md (activatie sectie) en BLOKVERDELING.md

### Docs: MD files consistentie check
- **Type:** Docs verification
- **Wat:** GEBRUIKERSHANDLEIDING.md en BLOKVERDELING.md gecontroleerd op consistentie
- **Resultaat:** ✓ Doorsturen per poule correct gedocumenteerd, button kleuren correct, chip kleuren correct
- **Naar permanente docs?** ☑ Nee - was alleen verificatie

---

## Sessie: 21 januari 2026 (avond)

### Fix: Import warnings zichtbaar per club op admin pagina
- **Type:** Enhancement
- **Wat:** Import warnings worden nu gegroepeerd per club getoond met contactgegevens
- **Waarom:** Organisator moet weten welke judoschool te contacteren voor import problemen
- **Bestanden:**
  - `app/Http/Controllers/JudokaController.php` - `importWarningsPerClub` toegevoegd
  - `resources/views/pages/judoka/index.blade.php` - collapsible sectie met clubs + email/telefoon
- **Naar permanente docs?** ☑ Ja → IMPORT.md

### Fix: Import NOOIT falen op null gewichtsklasse (KRITIEK)
- **Type:** Bug fix (KRITIEK)
- **Wat:** `gewichtsklasse` is NOOIT meer null - altijd 'Onbekend' of 'Variabel'
- **Waarom:** 14 judoka's werden geweigerd omdat U7 categorie geen gewichtsklassen had
- **Oorzaak:** `classificeerJudoka()` returde `null` voor gewichtsklasse bij ontbrekende config
- **Oplossing:**
  - Bij match: `'Variabel'` als default, `'Onbekend'` als bepaling faalt
  - Bij geen match: `'Onbekend'` als fallback
- **Regel:** "een gewichtscategorie is helemaal niet verplicht, wel een opgegeven gewicht"
- **Bestanden:**
  - `app/Services/ImportService.php` - `classificeerJudoka()` aangepast
- **Naar permanente docs?** ☑ Ja → IMPORT.md (regel toegevoegd: import mag NOOIT falen op null gewichtsklasse)

### Feat: import_warnings veld op judokas tabel
- **Type:** Feature
- **Wat:** Nieuw veld `import_warnings` (TEXT) om warnings te persisteren
- **Waarom:** Warnings waren session-based en verdwenen na refresh
- **Bestanden:**
  - `database/migrations/2026_01_21_160000_add_import_warnings_to_judokas_table.php`
  - `app/Models/Judoka.php` - veld toegevoegd aan fillable
- **Naar permanente docs?** ☑ Ja → IMPORT.md

### Feat: import_fouten veld op toernooien tabel
- **Type:** Feature (GEDEELTELIJK)
- **Wat:** Nieuw veld `import_fouten` (JSON) voor persistente opslag van import fouten
- **Waarom:** Fouten verdwenen na page refresh (session-based)
- **Status:** Migration aangemaakt, nog niet gebruikt in controller
- **Bestanden:**
  - `database/migrations/2026_01_21_170000_add_import_fouten_to_toernooien_table.php`
  - `app/Models/Toernooi.php` - veld toegevoegd aan fillable/casts
- **Naar permanente docs?** ☐ Nog niet volledig geïmplementeerd

### Docs: IMPORT.md aangemaakt
- **Type:** Documentation
- **Wat:** Nieuwe documentatie voor import feature
- **Inhoud:** Workflow, foutafhandeling, warnings, belangrijke regels (gewichtsklasse nooit verplicht)
- **Bestanden:** `docs/2-FEATURES/IMPORT.md`
- **Naar permanente docs?** ☑ Al gedaan

---

## Sessie: 23 januari 2026

### Feat: Categorie overlap detectie
- **Type:** Feature
- **Wat:** Waarschuwing wanneer categorieën overlappen (judoka kan in meerdere passen)
- **Wanneer overlap:** Zelfde max_leeftijd + zelfde geslacht + overlappende band_filter
- **Wanneer OK:** Zelfde leeftijd maar verschillend geslacht OF niet-overlappende banden (tm_oranje + vanaf_groen)
- **UI:** Oranje banner in Instellingen pagina, update dynamisch na AJAX save
- **Bestanden:**
  - `app/Services/CategorieClassifier.php` - `detectOverlap()` + helpers
  - `app/Http/Controllers/ToernooiController.php` - check in edit() en update()
  - `resources/views/pages/toernooi/edit.blade.php` - banner + JS update
- **Naar permanente docs?** ☑ Nee - feature enhancement

### Fix: Gewichtsklasse null constraint error
- **Type:** Bug fix (KRITIEK)
- **Wat:** Save faalde bij wijzigen band_filter in categorieën
- **Oorzaak:** `voerValidatieUit()` zette gewichtsklasse op null, database heeft NOT NULL constraint
- **Oplossing:** Skip update als nieuwe gewichtsklasse null zou zijn
- **Bestanden:** `app/Http/Controllers/JudokaController.php:417-421`
- **Naar permanente docs?** ☑ Nee - bug fix

### Fix: Metadata in judokasPerKlasse overzicht
- **Type:** Bug fix
- **Wat:** "preset type 0" en "eigen preset id 0" verscheen als leeftijdsklasse
- **Oorzaak:** Gewichtsklassen config bevat metadata keys (`_preset_type`, `_eigen_preset_id`)
- **Oplossing:** Skip non-array entries en keys die met `_` beginnen
- **Bestanden:** `app/Http/Controllers/JudokaController.php:43-47`
- **Naar permanente docs?** ☑ Nee - bug fix

### Fix: Non-array config entries in overlap check
- **Type:** Bug fix
- **Wat:** Overlap check crashte op non-array config entries (poule_grootte_voorkeur etc)
- **Oplossing:** `is_array()` check toegevoegd
- **Bestanden:** `app/Services/CategorieClassifier.php:281-284`
- **Naar permanente docs?** ☑ Nee - bug fix

### Fix: Band range berekening voor overlap detectie
- **Type:** Bug fix
- **Wat:** tm_oranje + vanaf_groen werd foutief als overlap gemarkeerd
- **Oorzaak:** BandHelper gebruikt omgekeerde volgorde (wit=6, zwart=0)
- **Oplossing:** Range logica omgedraaid: tm_ = hoge nummers, vanaf_ = lage nummers
- **Bestanden:** `app/Services/CategorieClassifier.php:393-411`
- **Naar permanente docs?** ☑ Nee - bug fix

### Feat: Niet-gecategoriseerd waarschuwing op Poules pagina
- **Type:** Enhancement
- **Wat:** Rode waarschuwing banner toegevoegd aan Poules pagina
- **Waarom:** Gebruiker moet gewaarschuwd worden voordat ze poules gaan maken
- **Bestanden:** `resources/views/pages/poule/index.blade.php:71-91`
- **Naar permanente docs?** ☑ Nee - UI enhancement

---

<!--
TEMPLATE voor nieuwe entry:

### Fix: [korte titel]
- **Type:** Bug fix / Performance / Refactor / Typo / Update
- **Wat:** [wat aangepast]
- **Waarom:** [reden]
- **Bestanden:** [welke files gewijzigd]
- **Naar permanente docs?** ☐ Ja → [welke doc] / ☑ Nee

-->
