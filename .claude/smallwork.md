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

## Sessie: 13 januari 2026 (nacht - vervolg)

### Fix: Preset dropdown change event
- **Type:** Bug fix
- **Wat:** Dropdown was al geselecteerd bij page load, waardoor change event niet triggerde
- **Waarom:** Gebruiker kon preset niet opnieuw laden als die al geselecteerd stond
- **Oplossing:** Dropdown niet automatisch selecteren bij page load
- **Bestanden:** `resources/views/pages/toernooi/edit.blade.php`
- **Naar permanente docs?** ☑ Nee - UI fix

### Fix: Eigen preset radio button change handler
- **Type:** Bug fix
- **Wat:** Radio button "Alles" (eigen preset) had geen @change handler
- **Waarom:** Klikken op de radio laadde de preset configuratie niet
- **Oplossing:** `@change="loadEigenPreset()"` toegevoegd + window.loadEigenPreset functie
- **Bestanden:** `resources/views/pages/toernooi/edit.blade.php`
- **Naar permanente docs?** ☑ Nee - UI fix

### Fix: Poule sortering leeftijdsklasse flexibeler
- **Type:** Bug fix
- **Wat:** Sortering vereiste exacte label match ("U7 Alles" ≠ "U7")
- **Waarom:** U7 poules kwamen onderaan i.p.v. bovenaan
- **Oplossing:** `getLeeftijdsklasseVolgorde()` met prefix matching + numerieke fallback
- **Bestanden:** `app/Http/Controllers/PouleController.php`
- **Naar permanente docs?** ☑ Nee - technische fix

### Fix: Band sortering na groupBy
- **Type:** Bug fix (KRITIEK)
- **Wat:** `groupBy()` bewaart sorteervolgorde niet - banden waren door elkaar
- **Waarom:** Witte en gele banden zaten in dezelfde poules ondanks band prioriteit
- **Oplossing:** Na groupBy de judokas binnen elke groep opnieuw sorteren op sort_band
- **Bestanden:** `app/Services/PouleIndelingService.php:groepeerJudokas()`
- **Naar permanente docs?** ☑ Nee - technische fix

### Fix: getBandNiveau parsing voor "wit (6 kyu)" formaat
- **Type:** Bug fix (KRITIEK)
- **Wat:** Functie verwachtte "wit" maar database had "wit (6 kyu)" → sort_band was altijd 0
- **Waarom:** Band sortering werkte helemaal niet
- **Oplossing:** Extract eerste woord of zoek kleur in string
- **Bestanden:** `app/Services/PouleIndelingService.php:getBandNiveau()`
- **Naar permanente docs?** ☑ Nee - technische fix

---

## Sessie: 14 januari 2026

### Fix: Lege poules naar wedstrijddag
- **Type:** Bug fix
- **Wat:** Filter verwijderd die lege poules uitsloot van wedstrijddag pagina
- **Waarom:** Lege poules zijn nodig voor overpoelen (wachtruimte)
- **Bestanden:** `app/Http/Controllers/WedstrijddagController.php:51-53`
- **Naar permanente docs?** ☑ Ja → GEBRUIKERSHANDLEIDING.md (regel 496)

### Fix: Gewichtscategorie in poule titel
- **Type:** Enhancement
- **Wat:** Gewichtsklasse toegevoegd aan poule titel op voorbereiding pagina
- **Waarom:** Titel toonde alleen "#1 Mini's U7", nu "#1 Mini's U7 -23"
- **Bestanden:** `resources/views/pages/poule/index.blade.php:158`
- **Naar permanente docs?** ☑ Nee - UI enhancement

### Fix: Wachtruimte toont gewogen judoka's (gewogen = aanwezig)
- **Type:** Bug fix (KRITIEK)
- **Wat:** Filter `aanwezigheid = 'aanwezig'` verwijderd uit wachtruimte query
- **Waarom:** Gewogen judoka's zijn per definitie aanwezig (kun niet wegen zonder er te zijn)
- **Bestanden:** `app/Http/Controllers/WedstrijddagController.php:24-30`
- **Naar permanente docs?** ☑ Ja → GEBRUIKERSHANDLEIDING.md (sectie Automatische Aanwezigheidsbepaling)

### Fix: Info popup als tooltip i.p.v. browser alert
- **Type:** Enhancement
- **Wat:** ⓘ icoon toont nu tooltip boven de icon i.p.v. browser alert
- **Waarom:** Alert verscheen helemaal bovenaan scherm, tooltip is gebruiksvriendelijker
- **Oplossing:** Alpine.js x-data met click toggle, removed overflow-hidden van poule-card
- **Bestanden:** `resources/views/pages/wedstrijddag/poules.blade.php:196-200, 302-308, 291, 298`
- **Naar permanente docs?** ☑ Nee - UI enhancement

### Docs: Automatische aanwezigheidsbepaling gedocumenteerd
- **Type:** Docs
- **Wat:** Nieuwe sectie in GEBRUIKERSHANDLEIDING.md met aanwezigheidslogica
- **Regels:** Gewogen = aanwezig, niet gewogen na sluiting weegtijd = afwezig
- **Bestanden:** `docs/2-FEATURES/GEBRUIKERSHANDLEIDING.md:474-487`
- **Naar permanente docs?** ☑ Al gedaan

### Docs: "Doorgestreept" verwijzingen verwijderd
- **Type:** Docs cleanup
- **Wat:** Alle "doorgestreepte judoka's" verwijzingen vervangen
- **Waarom:** UI toont nu ⓘ icoon met popup, geen doorgestreepte tekst meer
- **Bestanden:** `docs/2-FEATURES/GEBRUIKERSHANDLEIDING.md` (5 plekken)
- **Naar permanente docs?** ☑ Al gedaan

---

## Sessie: 15 januari 2026

### Fix: Drag & drop 500 error
- **Type:** Bug fix
- **Wat:** `getPresetConfig()` methode bestond niet meer, vervangen door `getAlleGewichtsklassen()`
- **Waarom:** 500 error bij drag & drop van judoka's tussen poules
- **Bestanden:**
  - `app/Http/Controllers/PouleController.php`
  - `app/Services/VariabeleBlokVerdelingService.php`
- **Naar permanente docs?** ☑ Nee - technische bugfix

### Fix: Poule titel update na drag & drop
- **Type:** Bug fix
- **Wat:** Statistieken (lft/kg range) verschenen ACHTER de titel i.p.v. IN de titel
- **Waarom:** Obsolete `data-poule-ranges` span werd nog gevuld
- **Oplossing:** Span verwijderd, JavaScript `updatePouleStats()` vereenvoudigd
- **Bestanden:** `resources/views/pages/poule/index.blade.php`
- **Naar permanente docs?** ☑ Nee - UI fix

### Fix: Max 5 judoka's per poule bij dynamische indeling
- **Type:** Enhancement
- **Wat:** Check toegevoegd `if (count($huidigePoule) >= 5)` in maakPoules()
- **Waarom:** Poules werden te groot, 5 is ideale grootte
- **Bestanden:** `app/Services/DynamischeIndelingService.php:795-797`
- **Naar permanente docs?** ☑ Ja → PLANNING_DYNAMISCHE_INDELING.md

### Fix: Label-first lookup in maakPouleTitel
- **Type:** Bug fix (OPGELOST)
- **Wat:** Lookup logica aangepast naar label-first (zelfde als PouleController)
- **Waarom:** Leeftijd range verscheen niet in poule titel bij genereren
- **Oplossing:** Blade template gebruikte handmatige titel constructie, nu `$poule->titel` direct
- **Bestanden:** `resources/views/pages/poule/index.blade.php`
- **Naar permanente docs?** ☑ Nee - UI fix

### Fix: Duplicate function name scoreVerdeling
- **Type:** Bug fix (KRITIEK)
- **Wat:** FatalError door twee functies met naam `scoreVerdeling()` met verschillende signatures
- **Waarom:** Nieuwe functie voor poule grootte optimalisatie had zelfde naam als bestaande
- **Oplossing:** Nieuwe functie hernoemd naar `scorePouleGrootteVerdeling()`
- **Bestanden:** `app/Services/DynamischeIndelingService.php:953`
- **Naar permanente docs?** ☑ Nee - technische bugfix

### Fix: Classificatie bug 6-jarigen in "Heren"
- **Type:** Bug fix (KRITIEK)
- **Wat:** Auto-detect geslacht op basis van key suffix (`_d`, `_h`) overschreef explicit `gemengd`
- **Waarom:** 352 judoka's werden verkeerd geclassificeerd (6-jarigen als "Heren")
- **Oplossing:** Check of `geslacht` expliciet 'gemengd' is voordat auto-detect triggert
- **Bestanden:** `app/Services/PouleIndelingService.php:710-723`
- **Naar permanente docs?** ☑ Ja → PLANNING_DYNAMISCHE_INDELING.md (classificatie logica)

### Enhancement: Poule grootte optimalisatie (5 > 4 > 3)
- **Type:** Enhancement
- **Wat:** Nieuw algoritme voor optimale verdeling: prioriteit 5, dan 4, dan 3
- **Waarom:** 8 judoka's → 4+4 (niet 5+3), 11 judoka's → 4+4+3 (niet 5+5+1)
- **Bestanden:** `app/Services/DynamischeIndelingService.php` (nieuwe functies)
- **Naar permanente docs?** ☑ Ja → PLANNING_DYNAMISCHE_INDELING.md (al gedocumenteerd)

---

## Sessie: 16 januari 2026

### Fix: Hardcoded Leeftijdsklasse enum verwijderd
- **Type:** Refactor (KRITIEK)
- **Wat:** Alle `Leeftijdsklasse::` enum calls vervangen door config-based methods
- **Waarom:** Enum negeerde toernooi config, Mini's (max 6j) werden als Jeugd geclassificeerd
- **Oplossing:**
  - `Toernooi::bepaalLeeftijdsklasse()` - classificeert op basis van config
  - `Toernooi::bepaalGewichtsklasse()` - gewichtsklasse op basis van config
  - `Toernooi::getLeeftijdsklasseSortValue()` - sorteerwaarde voor UI
- **Bestanden:**
  - `app/Models/Toernooi.php` - 3 nieuwe methods
  - `app/Http/Controllers/JudokaController.php` - enum import verwijderd
  - `app/Http/Controllers/CoachPortalController.php` - enum import verwijderd
- **Naar permanente docs?** ☑ Ja → CONFIGURATIE.md + PLANNING_DYNAMISCHE_INDELING.md (al gedaan)

### Fix: Valideer hercategoriseert leeftijdsklasse
- **Type:** Enhancement
- **Wat:** `JudokaController::valideer()` hercategoriseert nu ook leeftijdsklasse
- **Waarom:** Na wijzigen categorieën moesten judoka's handmatig bijgewerkt worden
- **Bestanden:** `app/Http/Controllers/JudokaController.php:valideer()`
- **Naar permanente docs?** ☑ Nee - technische enhancement

### Refactor: DynamischeIndelingService herschreven
- **Type:** Refactor (GROOT)
- **Wat:** 1374 → ~300 regels, simpel greedy algoritme
- **Algoritme:**
  1. Greedy: loop door gesorteerde judoka's, maak poules van max 5
  2. Merge buren: kleine poules (< 4) samenvoegen met aangrenzende
  3. Globale merge: kleine poules samenvoegen met ALLE andere kleine (niet alleen buren)
  4. Balanceer: steel van poules 6+ naar kleine (5 niet verkleinen!)
- **Resultaat:** 335 judoka's → 71 poules, slechts 5 kleine orphans
- **Bestanden:** `app/Services/DynamischeIndelingService.php`
- **Naar permanente docs?** ☑ Ja → PLANNING_DYNAMISCHE_INDELING.md (al gedaan)

### Fix: Groepering per leeftijdsklasse bij dynamische indeling
- **Type:** Bug fix
- **Wat:** Bij dynamische indeling (max_kg_verschil > 0) worden judoka's nu per leeftijdsklasse gegroepeerd, niet per gewichtsklasse
- **Waarom:** Judoka's met 25.6kg en 26.7kg werden in aparte groepen gezet en nooit samengevoegd
- **Bestanden:** `app/Services/PouleIndelingService.php:groepeerJudokas()`
- **Naar permanente docs?** ☑ Nee - technische fix

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

### BUG (OPENSTAAND): Vals-positieve gewichtsrange markering
- **Type:** Bug (NIET OPGELOST)
- **Wat:** Poules worden oranje gemarkeerd terwijl gewichtsrange OK is
- **Voorbeelden:**
  - Poule #9 Jeugd 26.5-28.8kg: range 27.3-31.8 = 4.5kg (1 judoka buiten klasse)
  - Poule #5 Jeugd 21-23kg: range 21.5-23.4 = 1.9kg (zou OK moeten zijn!)
- **Mogelijke oorzaken:**
  1. `isDynamisch()` retourneert true voor vaste gewichtsklassen
  2. `max_kg_verschil` staat verkeerd geconfigureerd
  3. `isProblematischNaWeging()` checkt verkeerde data
- **Bestanden:** `app/Models/Poule.php` - `isProblematischNaWeging()`, `isDynamisch()`
- **Naar permanente docs?** ☐ Moet eerst opgelost worden

### BUG (OPENSTAAND): Poule header kleur blijft oranje na fix
- **Type:** Bug (NIET OPGELOST)
- **Wat:** Header is oranje/rood i.p.v. blauw bij poules met >= 3 judoka's
- **Verwacht:** bg-blue-700 (blauw) bij >= 3 actieve judoka's
- **Realiteit:** Headers zijn oranje/bruin kleur
- **Mogelijke oorzaken:**
  1. CSS override ergens
  2. Cached oude versie
  3. Styling die ik niet heb gevonden
- **Bestanden:** `resources/views/pages/wedstrijddag/poules.blade.php`
- **Naar permanente docs?** ☐ Moet eerst opgelost worden

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

<!--
TEMPLATE voor nieuwe entry:

### Fix: [korte titel]
- **Type:** Bug fix / Performance / Refactor / Typo / Update
- **Wat:** [wat aangepast]
- **Waarom:** [reden]
- **Bestanden:** [welke files gewijzigd]
- **Naar permanente docs?** ☐ Ja → [welke doc] / ☑ Nee

-->
