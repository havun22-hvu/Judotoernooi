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

## Sessie: 5 januari 2026

### Fix: Leeftijdsklasse string conversie
- **Type:** Bug fix
- **Wat:** `B-pupillen` werd niet correct omgezet naar `b_pupillen` in KO settings
- **Waarom:** `str_replace(" -", "_")` verving alleen " -" (spatie+hyphen), niet "-" alleen
- **Bestanden:** `resources/views/pages/toernooi/edit.blade.php:316-318`
- **Oplossing:** `preg_replace('/[\s\-]+/', '_', $lkKey)` voor alle spaties en hyphens
- **Naar permanente docs?** ☑ Nee - technische bugfix

### Fix: Gespiegelde slot logica verwijderd uit backend
- **Type:** Refactor
- **Wat:** `$gespiegeld` parameter en onderste helft logica verwijderd
- **Waarom:** Slots moeten ALTIJD van boven naar beneden genummerd zijn, zonder spiegeling
- **Bestanden:** `app/Services/EliminatieService.php`
- **Naar permanente docs?** ☑ Al gedaan → `docs/2-FEATURES/ELIMINATIE/SLOT-SYSTEEM.md`

---

## Sessie: 6 januari 2026

### Fix: Custom labels voor poule titels
- **Type:** Bug fix
- **Wat:** Poule overzicht toonde JBN labels (A-pupillen, B-pupillen) i.p.v. custom labels uit toernooi config
- **Waarom:** `PouleController@index` stuurde geen label mapping naar de view
- **Bestanden:**
  - `app/Http/Controllers/PouleController.php` - labels mapping toegevoegd
  - `resources/views/pages/poule/index.blade.php` - 3 plekken aangepast
  - `app/Http/Controllers/WedstrijddagController.php` - ook aangepast (andere route)
- **Naar permanente docs?** ☑ Nee - technische implementatie

### Fix: Gewichtsklassen checkbox opslag
- **Type:** Bug fix
- **Wat:** "Gewichtsklassen gebruiken" checkbox werd niet correct opgeslagen
- **Waarom:** `x-model` en `checked` attribute conflicteerden in Alpine.js
- **Bestanden:** `resources/views/pages/toernooi/edit.blade.php:638-640`
- **Oplossing:** `checked` attribute verwijderd, alleen `x-model` behouden
- **Naar permanente docs?** ☑ Nee - technische bugfix

### Feature: Leeftijd/gewicht ranges in poule headers
- **Type:** Enhancement
- **Wat:** Poule headers tonen nu werkelijke leeftijd en gewicht ranges (bijv. "8-9j, 25.2-27.1kg")
- **Waarom:** Gebruiker wilde meer context zien bij elke poule
- **Bestanden:** `resources/views/pages/poule/index.blade.php:98-116, 128-131`
- **Naar permanente docs?** ☑ Nee - UI enhancement

---

## Sessie: 7-8 januari 2026

### Fix: Import onvolledige judoka's
- **Type:** Enhancement
- **Wat:** Judoka's zonder geboortejaar worden nu geïmporteerd i.p.v. overgeslagen
- **Waarom:** Gebruiker wilde alle judoka's importeren, ook met onvolledige data
- **Bestanden:**
  - `app/Services/ImportService.php` - import logica aangepast
  - `app/Models/Judoka.php` - cast `is_onvolledig` toegevoegd
  - `database/migrations/..._add_is_onvolledig_to_judokas_table.php` - nieuw veld
  - `resources/views/pages/judoka/index.blade.php` - filter knop
- **Naar permanente docs?** ☑ Ja → GEBRUIKERSHANDLEIDING.md (regel 76-78)

### Fix: Gewicht afleiden van gewichtsklasse
- **Type:** Bug fix
- **Wat:** Als alleen gewichtsklasse is ingevuld ("-34"), wordt gewicht afgeleid (34 kg)
- **Waarom:** Import bestanden bevatten soms alleen gewichtsklasse
- **Bestanden:** `app/Services/ImportService.php:gewichtVanKlasse()`
- **Naar permanente docs?** ☑ Ja → GEBRUIKERSHANDLEIDING.md (regel 75)

### Fix: Clubspreiding respecteert gewicht prioriteit
- **Type:** Bug fix
- **Wat:** Bij swappen voor clubspreiding wordt nu ook gewichtsverschil gecheckt
- **Waarom:** 20kg en 26kg judoka's werden gemixt ondanks gewicht prioriteit 1
- **Bestanden:** `app/Services/PouleIndelingService.php:pasClubspreidingToe()`
- **Naar permanente docs?** ☑ Ja → PLANNING_DYNAMISCHE_INDELING.md (regel 321-330)

### Fix: Auto-herberekening judoka codes bij wijziging prioriteiten
- **Type:** Bug fix
- **Wat:** Bij wijziging drag & drop prioriteiten worden codes automatisch herberekend
- **Waarom:** Oude check keek alleen naar verwijderd veld `judoka_code_volgorde`
- **Bestanden:** `app/Http/Controllers/ToernooiController.php:update()`
- **Naar permanente docs?** ☑ Ja → GEBRUIKERSHANDLEIDING.md (regel 95)

---

## Sessie: 9 januari 2026

### Fix: JBN presets gemengd voor jeugd
- **Type:** Bug fix / Data correctie
- **Wat:** JBN 2025/2026 presets gebruiken nu `geslacht='gemengd'` voor Mini's t/m Pupillen
- **Waarom:** JBN standaard: jongens/meisjes pas gescheiden vanaf -15 jaar
- **Bestanden:** `app/Models/Toernooi.php` (getJbn2025Gewichtsklassen, getJbn2026Gewichtsklassen)
- **Naar permanente docs?** ☑ Ja → JBN-REGLEMENT-2026.md (geslacht regel toegevoegd)

### Fix: Default max_leeftijd_verschil naar 1 jaar
- **Type:** Bug fix
- **Wat:** Nieuwe categorie krijgt nu standaard 1 jaar i.p.v. 2 jaar verschil
- **Waarom:** Consistentie met bestaande categorieën
- **Bestanden:** `resources/views/pages/toernooi/edit.blade.php:1115`
- **Naar permanente docs?** ☑ Nee - UI default

### Fix: Start met lege categorieën
- **Type:** Enhancement
- **Wat:** `getAlleGewichtsklassen()` retourneert nu lege array als geen config
- **Waarom:** Gebruiker wil zelf kiezen: JBN preset of handmatig
- **Bestanden:** `app/Models/Toernooi.php:getAlleGewichtsklassen()`
- **Naar permanente docs?** ☑ Nee - technische default

### Cleanup: Overbodige gemengd methods verwijderd
- **Type:** Refactor
- **Wat:** `getJbn2025GewichtsklassenGemengd()` en `getJbn2026GewichtsklassenGemengd()` verwijderd
- **Waarom:** Standaard presets zijn nu zelf gemengd
- **Bestanden:** `app/Models/Toernooi.php`
- **Naar permanente docs?** ☑ Nee - code cleanup

---

## Sessie: 10 januari 2026

### Fix: Gewicht fallback naar gewichtsklasse in DynamischeIndelingService
- **Type:** Bug fix (KRITIEK)
- **Wat:** Algoritme gebruikte `$judoka->gewicht` direct, maar dit veld is vaak `null`
- **Waarom:** Judoka's hebben alleen `gewichtsklasse` (bijv. "-38"), niet `gewicht` ingevuld
- **Gevolg:** Poules hadden 30kg verschil i.p.v. max 3kg - harde constraint werd genegeerd
- **Oplossing:** `getEffectiefGewicht()` helper toegevoegd met fallback:
  1. `gewicht_gewogen` (na weging - meest nauwkeurig)
  2. `gewicht` (ingeschreven)
  3. `gewichtsklasse` (bijv. "-38" → 38.0)
- **Bestanden:**
  - `app/Services/DynamischeIndelingService.php` - helper + 20+ plekken bijgewerkt
  - `app/Http/Controllers/PouleController.php` - berekenPouleRanges()
  - `resources/views/pages/poule/index.blade.php` - range berekening + gewicht display
- **Naar permanente docs?** ☑ Ja → PLANNING_DYNAMISCHE_INDELING.md (gewicht fallback)

### Fix: Gewicht weergave per judoka in poule overzicht
- **Type:** Enhancement
- **Wat:** Judoka's tonen nu gewicht achter naam, met gewichtsklasse als fallback (≤38kg)
- **Waarom:** Gebruiker wilde gewicht zien per judoka
- **Bestanden:** `resources/views/pages/poule/index.blade.php:173-187, 195, 210`
- **Naar permanente docs?** ☑ Nee - UI enhancement

### Fix: Navigatie - title link naar dashboard
- **Type:** Bug fix
- **Wat:** Klik op toernooi naam (links boven) gaat nu naar dashboard i.p.v. instellingen
- **Waarom:** `toernooi.edit` was instellingen, moet `toernooi.show` zijn (dashboard)
- **Bestanden:** `resources/views/layouts/app.blade.php:35`
- **Naar permanente docs?** ☑ Nee - UI fix

### Enhancement: Organisator dropdown menu
- **Type:** Enhancement
- **Wat:** Organisator naam is nu dropdown met "Toernooien" en "Uitloggen"
- **Waarom:** Overzichtelijker, minder clutter in navbar
- **Bestanden:** `resources/views/layouts/app.blade.php:50-71`
- **Naar permanente docs?** ☑ Nee - UI enhancement

---

## Sessie: 11 januari 2026

### Fix: Placeholder voor max leeftijd input
- **Type:** Bug fix
- **Wat:** Max leeftijd input gebruikte `value="99"` met grijze tekst, waardoor getypte cijfers vermengden
- **Waarom:** Gebruiker kon niet normaal typen in het veld
- **Oplossing:** `placeholder="99"` i.p.v. `value="99"`, getypte tekst nu blauw
- **Bestanden:** `resources/views/pages/toernooi/edit.blade.php` (3 plekken)
- **Naar permanente docs?** ☑ Nee - UI fix

### Fix: Auto-redirect bij session expire
- **Type:** Enhancement
- **Wat:** Globale fetch interceptor die 401/419 responses afvangt en redirect naar login
- **Waarom:** Pagina bleef zichtbaar maar AJAX calls faalden stilletjes
- **Bestanden:** `resources/views/layouts/app.blade.php`
- **Naar permanente docs?** ☑ Nee - technische enhancement

### Fix: Autosave reliability verbeterd
- **Type:** Bug fix (KRITIEK)
- **Wat:** Autosave toonde "Opgeslagen" ook bij gefaalde saves (session expire, validatie errors)
- **Oorzaak:** Check was `response.ok || response.redirected` - redirect naar login werd als success gezien
- **Oplossing:** JSON response parsen en `success: true` checken + event delegation voor dynamische elementen
- **Bestanden:** `resources/views/pages/toernooi/edit.blade.php`
- **Naar permanente docs?** ☑ Nee - technische bugfix

### Fix: Validatie verdeling_prioriteiten
- **Type:** Bug fix
- **Wat:** Validatie accepteerde `groepsgrootte,bandkleur,clubspreiding` maar form stuurde `gewicht,band,groepsgrootte,clubspreiding`
- **Waarom:** Validatie was niet bijgewerkt na toevoegen nieuwe prioriteiten
- **Bestanden:** `app/Http/Requests/ToernooiRequest.php:72`
- **Naar permanente docs?** ☑ Nee - technische bugfix

### Fix: Standaard prioriteit volgorde
- **Type:** Enhancement
- **Wat:** Default volgorde nu: groepsgrootte, gewicht, band, clubspreiding
- **Waarom:** Gebruiker wilde groepsgrootte als eerste prioriteit
- **Bestanden:** `resources/views/pages/toernooi/edit.blade.php:722`
- **Naar permanente docs?** ☑ Nee - UI default

### Feat: Auto-sync blokken en matten
- **Type:** Enhancement
- **Wat:** Blokken en matten worden nu automatisch aangemaakt/verwijderd bij wijzigen settings
- **Waarom:** Blokken werden niet aangemaakt bij bestaande toernooien (alleen bij nieuwe)
- **Bestanden:**
  - `app/Services/ToernooiService.php` - syncBlokken(), syncMatten()
  - `app/Http/Controllers/ToernooiController.php` - aangeroepen in update()
- **Naar permanente docs?** ☑ Nee - technische enhancement

### Refactor: Device toegangen los van vrijwilligers
- **Type:** Refactor
- **Wat:** Device toegangen bevatten geen persoonsgegevens meer (naam/telefoon/email weg)
- **Waarom:** Organisator beheert vrijwilligerslijst zelf via WhatsApp
- **Bestanden:**
  - `resources/views/pages/toernooi/partials/device-toegangen.blade.php`
  - `.claude/context.md` - docs geüpdatet
- **Naar permanente docs?** ☑ Ja → context.md (al gedaan)

---

## Sessie: 12 januari 2026

### Feat: Delete button voor judoka's
- **Type:** Feature
- **Wat:** Delete button (×) toegevoegd aan judoka overzicht tabel
- **Bestanden:**
  - `resources/views/pages/judoka/index.blade.php` - Acties kolom + delete form
  - `docs/2-FEATURES/GEBRUIKERSHANDLEIDING.md` - sectie "Judoka Verwijderen" toegevoegd
- **Naar permanente docs?** ☑ Ja → GEBRUIKERSHANDLEIDING.md (regel 85-95)

### Fix: Poule titels nemen categorie naam over uit instellingen
- **Type:** Bug fix
- **Wat:** Mapping in `leeftijdsklasseToConfigKey()` was incompleet (miste C-pupillen, -21 categorieën)
- **Waarom:** Poule titels toonden JBN standaard namen i.p.v. custom categorie namen
- **Oplossing:** Mapping uitgebreid + fallback via normalisatie voor custom categorieën
- **Bestanden:**
  - `app/Services/PouleIndelingService.php:955-986`
  - `docs/2-FEATURES/GEBRUIKERSHANDLEIDING.md` - sectie "Poule Titels" toegevoegd
  - `docs/4-PLANNING/PLANNING_DYNAMISCHE_INDELING.md` - sectie verduidelijkt
- **Naar permanente docs?** ☑ Ja → beide docs bijgewerkt

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
