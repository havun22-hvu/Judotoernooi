# Eliminatie Systeem (Double Elimination)

> **Status**: Actief
> **Laatste update**: 2026-02-08
> **Verantwoordelijke**: EliminatieService.php

## Overzicht

Het double elimination systeem zorgt ervoor dat judoka's pas na **twee nederlagen** uitgeschakeld zijn.

```
A-groep (Hoofdboom)  →  Goud + Zilver
B-groep (Herkansing) →  Brons (1 of 2, instelbaar)

Verlies in A = naar B-groep
Verlies in B = uitgeschakeld
```

## Documentatie Structuur

| Document | Inhoud |
|----------|--------|
| [FORMULES.md](./FORMULES.md) | Wiskundige berekeningen, D, V1, V2 |
| [SLOT-SYSTEEM.md](./SLOT-SYSTEEM.md) | Slot nummering, doorschuifregels |
| [TEST-MATRIX.md](./TEST-MATRIX.md) | Verificatietabellen per N |

## Vereisten

### Vaste Gewichtsklassen Verplicht

Eliminatie en kruisfinale vereisen **vaste gewichtsklassen**. De hele categorie/gewichtsklasse gaat in 1 poule (geen splitsen in groepjes van 5).

| Instelling | Vereiste | Reden |
|------------|----------|-------|
| Δkg | **= 0** | Vaste klassen, geen dynamische groepering |
| Δlft | **= 0** | Hele categorie in 1 poule, niet splitsen op leeftijd |
| Gewichtsklassen | **Ingevuld** | Bijv. `-30, -34, -38, +38` |

**In toernooi-instellingen:**
- Eliminatie/kruisfinale opties zijn disabled als niet aan vereisten voldaan
- Waarschuwingstekst toont welke vereisten ontbreken
- Bij wijziging Δkg/Δlft/gewichten wordt automatisch teruggezet naar "Poules"

### Waarom?
- Eliminatie bracket werkt alleen als ALLE judoka's in de gewichtsklasse tegen elkaar kunnen
- Splitsen in subgroepen is niet mogelijk in een eliminatie bracket
- Kruisfinale combineert poule + bracket en heeft dezelfde vereiste

## Quick Reference

### Minimum Grootte

**Eliminatie poules vereisen minimaal 8 judoka's.**

Bij verificatie ("Verifieer poules" knop) worden eliminatie poules apart behandeld:
- Normaal: 3-6 judoka's
- **Eliminatie: min. 8 judoka's** (geen maximum)
- Kruisfinale: geen grootte validatie

Zie ook: [CLASSIFICATIE.md](../CLASSIFICATIE.md#verificatie-poule-grootte-per-type)

### Kernformules

```
N  = aantal judoka's
D  = 2^floor(log2(N))           # grootste macht van 2 <= N
a1 = verliezers eerste A-ronde  # N-D (of D/2 bij exacte macht van 2)
a2 = verliezers tweede A-ronde  # D/2 (of D/4 bij exacte macht van 2)

SAMEN  als: a1 <= a2  (a1 past in B-start, evt. met byes op WIT)
DUBBEL als: a1 > a2   (a1 te groot, extra (1)-ronde nodig)
```

### Wedstrijden Totaal

| Instelling | A-groep | B-groep | Totaal |
|------------|---------|---------|--------|
| 2 brons (default) | N - 1 | N - 4 | 2N - 5 |
| 1 brons | N - 1 | N - 3 | 2N - 4 |

### B-Structuur Bepalen

| Conditie | B-structuur | Voorbeeld |
|----------|-------------|-----------|
| a1 ≤ a2 | **SAMEN** (geen suffix) | N=5-6, 9-12, 17-24, 33-48 |
| a1 > a2 | **DUBBEL** met (1)/(2) | N=7-8, 13-16, 25-32, 49-64 |

Zie [FORMULES.md](./FORMULES.md) voor de volledige berekening incl. exacte machten van 2.

### B-Start Byes (Dubbele Rondes)

Bij dubbele rondes worden de eerste A-ronde verliezers verspreid over **alle** B-start(1) wedstrijden. Elke B(1) wedstrijd krijgt minimaal 1 judoka. Als er minder verliezers zijn dan 2× B-capaciteit, krijgen sommige B(1) wedstrijden maar 1 judoka (bye).

```
B-capaciteit = berekenMinimaleBWedstrijden(V1)
Volle weds    = V1 - B-capaciteit     (krijgen 2 judoka's, 2:1 mapping)
Bye weds      = 2 × B-cap - V1        (krijgen 1 judoka op WIT, blauw=null)

Voorbeeld N=54: V1=22, B-cap=16, slots=32
→ Volle weds = 22 - 16 = 6   (idx 0-11: 12 verliezers, 2:1)
→ Bye weds   = 32 - 22 = 10  (idx 12-21: 10 verliezers, alleen WIT)
```

**Spreiding in `koppelARondeAanBRonde` type 'eerste':**
1. Eerste `volle × 2` verliezers → 2:1 mapping (normaal, wit+blauw)
2. Resterende verliezers → 1:1 op WIT (bye, blauw blijft null)

Bye wedstrijden worden **handmatig door de hoofdjury** geregistreerd → winnaar schuift door naar B(2) WIT.

## Implementatie

### Service

```php
use App\Services\EliminatieService;

$service = app(EliminatieService::class);

// Genereer bracket
$service->genereerBracket($poule, $judokaIds);

// Verwerk uitslag
$service->verwerkUitslag($wedstrijd, $winnaarId);

// Statistieken
$stats = $service->berekenStatistieken($n);
```

### Database Velden

| Veld | Type | Beschrijving |
|------|------|--------------|
| `groep` | A/B | Hoofdboom of herkansing |
| `ronde` | string | b_achtste_finale, b_achtste_finale_2, etc. |
| `bracket_positie` | int | Wedstrijdnummer binnen ronde (1-based) |
| `volgende_wedstrijd_id` | int | Winnaar gaat naar deze wedstrijd |
| `winnaar_naar_slot` | wit/blauw | Positie in volgende wedstrijd |
| `slot_wit` | int | Slotnummer voor wit (2N-1) |
| `slot_blauw` | int | Slotnummer voor blauw (2N) |

## B-groep op Aparte Mat

Eliminatie poules kunnen de B-groep (herkansing) op een **andere mat** draaien dan de A-groep (hoofdboom). Dit maakt parallel spelen mogelijk.

### Database

| Veld | Tabel | Beschrijving |
|------|-------|--------------|
| `mat_id` | poules | Mat voor A-groep (of beide als geen split) |
| `b_mat_id` | poules | Mat voor B-groep (nullable, FK naar matten) |

### Werking

- **Zaaloverzicht**: Eliminatie poule toont ALTIJD 2 entries: `#N - A` en `#N - B`
- **Standaard**: `b_mat_id = mat_id` (beide groepen op zelfde mat)
- **Split**: Sleep B-chip in zaaloverzicht naar andere mat → `b_mat_id` wordt geüpdatet
- **Mat interface**: Toont alleen relevante groep (`groep_filter` = A, B of null)
- **Aan de beurt**: A+B op zelfde mat → 1 groen/geel/blauw. Gesplitst → apart per mat
- **Afronden**: Pas na ALLE wedstrijden (A+B) klaar, broadcast naar beide mats

### Bestanden

| Bestand | Wijziging |
|---------|-----------|
| `BlokMatVerdelingService::getZaalOverzicht()` | Split A/B entries per mat |
| `BlokMatVerdelingService::verdeelOverMatten()` | Default `b_mat_id = mat_id` |
| `BlokController::verplaatsPoule()` | `groep` parameter (A/B) |
| `WedstrijdSchemaService::getSchemaVoorMat()` | Query op `b_mat_id`, `groep_filter` |
| `MatController::doPouleKlaar()` | Cross-mat check + dual broadcast |
| `zaaloverzicht.blade.php` | A/B chips + drag-drop |
| `_content.blade.php` | Tab filtering op `groep_filter` |

## Poule Weergave per Pagina

Welke poules worden waar getoond, afhankelijk van type en vulling:

| Type poule | Judoka's | Blokverdeling | Zaaloverzicht | Wedstrijddag |
|------------|----------|---------------|---------------|--------------|
| **Normaal** | >0 | Ja | Ja | Ja |
| **Normaal** | 0 | Nee | Nee | Ja* |
| **Eliminatie** | >0 | Ja (geheel) | Ja (A+B split) | Ja |
| **Eliminatie** | 0 | Nee | Nee | Ja* |
| **Kruisfinale** | virtueel | Ja | Ja | Ja |

*\* Lege poules bij vaste gewichtsklassen blijven op wedstrijddag zichtbaar voor overpoulen*

**Eliminatie A/B split** → altijd in zaaloverzicht (2 chips), altijd in wedstrijddag (2 tabs of 1 tab per mat). Niet in blokverdeling (geheel).

### Filter logica

| Locatie | Filter | Bestand |
|---------|--------|---------|
| Service (data) | `judokas > 1 \|\| type === 'kruisfinale'` | `BlokMatVerdelingService:1025` |
| Zaaloverzicht chips | `judokas > 1 \|\| type === 'kruisfinale'` | `zaaloverzicht.blade.php:63` |
| Zaaloverzicht matten | `judokas > 1 \|\| type === 'kruisfinale'` | `zaaloverzicht.blade.php:199` |
| Poule.updateStatistieken | Bewaar virtueel count alleen voor `kruisfinale` | `Poule.php:222` |

## Bracket Rendering (Mat Interface)

> **Laatste update:** 2026-02-10 — herschreven van Alpine x-html naar Blade + DOM

### Architectuur

**Oud (vóór 10 feb 2026):** Alpine `x-html="renderBracket()"` bouwde ~300 regels HTML als JS string en herbouwde de hele DOM na elke drop. Dit gaf merkbare vertraging.

**Nieuw:** Server-rendered Blade partials + SortableJS + pure DOM updates. Zelfde patroon als de 4 andere werkende DnD-pagina's (zaaloverzicht, wedstrijddag poules, wachtruimte, poule index).

### Waarom deze keuze?

1. **Performance**: Blade rendert HTML server-side → geen JS string building → geen complete DOM rebuild
2. **Consistentie**: Alle 5 DnD-pagina's gebruiken nu hetzelfde patroon (SortableJS + DOM updates)
3. **Onderhoud**: Layout wijzigingen in Blade (PHP) ipv JS template strings — Tailwind purge werkt correct
4. **Touch support**: SortableJS voor alle devices (PC + tablet) — geen aparte HTML5 DnD fallback nodig

### Data flow

```
1. Page load → laadWedstrijden() → Alpine poules data
2. x-init → laadBracketHtml(pouleId, groep) → AJAX POST naar /mat/bracket-html
3. Server: MatController::getBracketHtml()
   → Poule + wedstrijden laden
   → BracketLayoutService berekent posities
   → Blade partial renderen → HTML response
4. Client: container.innerHTML = html
5. initBracketSortable() + applyBeurtaanduiding()
```

### Na een drop (judoka verplaatsen)

```
1. SortableJS onEnd → DOM revert ALTIJD (clone verwijderen, item terugzetten)
2. fakeEvent bouwen → window.dropJudoka() aanroepen
3. dropJudoka() doet validatie (slot check, wachtwoord, etc.)
4. API call naar plaatsJudoka → server response met updated_slots[]
5. updateAlleBracketSlots() update individuele DOM elementen
6. Bij fout: location.reload()
```

### Positie-berekening (BracketLayoutService)

| Constante | Waarde | Beschrijving |
|-----------|--------|--------------|
| `SLOT_HEIGHT` | 28px | Hoogte van 1 slot (wit of blauw) |
| `POTJE_HEIGHT` | 56px | 2 × SLOT_HEIGHT (wit + blauw) |
| `POTJE_GAP` | 8px | Verticale ruimte tussen potjes |
| `HORIZON_HEIGHT` | 20px | Ruimte tussen B-bracket bovenste/onderste helft |

**A-bracket**: Recursieve `berekenPotjeTop(niveau, potjeIdx)` — elk potje is gecentreerd tussen de 2 potjes van het vorige niveau.

**B-bracket**: Mirrored layout — bovenste helft + onderste helft met horizon gap. Rondes `_1` en `_2` worden gegroepeerd per niveau.

### Lookup tabellen

Ronde-namen en volgorde staan in `BracketLayoutService.php` als class constants:

| Constant | Doel |
|----------|------|
| `RONDE_VOLGORDE` | Kolom-volgorde (links→rechts) |
| `RONDE_NAMEN` | Leesbare namen (1/32, 1/16, Finale, etc.) |

**Ondersteunde rondes (A-groep):**
`tweeendertigste_finale`, `zestiende_finale`, `achtste_finale`, `kwartfinale`, `halve_finale`, `finale`

**Ondersteunde rondes (B-groep):**
`b_zestiende_finale_1/_2`, `b_achtste_finale_1/_2`, `b_kwartfinale_1/_2`, `b_halve_finale_1/_2`, `b_brons`
Plus varianten zonder suffix (SAMEN modus).

**Bij nieuwe ronde-namen:** altijd `RONDE_VOLGORDE` + `RONDE_NAMEN` in BracketLayoutService bijwerken!

### Beurtaanduiding (double-click kleuren)

Double-click op eliminatie potje → zelfde 3-kleuren systeem als poules:
- **Groen** = speelt nu (`actieve_wedstrijd_id`)
- **Geel** = staat klaar (`volgende_wedstrijd_id`)
- **Blauw** = gereed maken (`gereedmaken_wedstrijd_id`)

**Flow:** `ondblclick` → `window.dblClickBracket()` → `Alpine.$data()` → `toggleVolgendeWedstrijd()` → `setWedstrijdStatus()` → `applyBeurtaanduiding()`

**applyBeurtaanduiding()** zet inline `style=""` op de wit/blauw slot divs (via `document.getElementById('slot-{id}-wit')`). Inline styles wint van Tailwind classes.

### Bestanden

| Bestand | Rol |
|---------|-----|
| `app/Services/BracketLayoutService.php` | Positie-berekening, ronde lookups |
| `views/pages/mat/partials/_bracket.blade.php` | A-bracket container |
| `views/pages/mat/partials/_bracket-b.blade.php` | B-bracket container (mirrored) |
| `views/pages/mat/partials/_bracket-potje.blade.php` | Individueel potje (wit+blauw) |
| `views/pages/mat/partials/_bracket-medailles.blade.php` | Medaille slots (goud/zilver/brons) |
| `views/pages/mat/partials/_content.blade.php` | JS: laadBracketHtml, updateBracketSlot, SortableJS, beurtaanduiding |
| `app/Http/Controllers/MatController.php` | getBracketHtml endpoint + updated_slots in plaatsJudoka |

### Routes

| Route | Middleware | Beschrijving |
|-------|-----------|--------------|
| `POST {org}/toernooi/{toernooi}/mat/bracket-html` | auth:organisator | Admin bracket HTML |
| `POST {org}/{toernooi}/mat/{toegang}/bracket-html` | device.binding | Device-bound bracket HTML |

## Gerelateerde Bestanden

- `app/Services/EliminatieService.php` - Bracket generatie, uitslag verwerking
- `app/Services/BracketLayoutService.php` - Visuele layout berekening
- `resources/views/pages/mat/partials/_content.blade.php` - Mat interface JS
- `resources/views/pages/mat/partials/_bracket*.blade.php` - Blade partials
- `database/migrations/*_eliminatie_*.php` - Schema

## Changelog

Zie [../../CHANGELOG.md](../../CHANGELOG.md) voor wijzigingsgeschiedenis.
