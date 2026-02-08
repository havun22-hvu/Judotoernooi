# Eliminatie Systeem (Double Elimination)

> **Status**: Actief
> **Laatste update**: 2026-01-01
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
V1 = N - D                       # verliezers eerste A-ronde
V2 = D / 2                       # verliezers tweede A-ronde

Dubbele B-rondes als: V1 > V2
```

### Wedstrijden Totaal

| Instelling | A-groep | B-groep | Totaal |
|------------|---------|---------|--------|
| 2 brons (default) | N - 1 | N - 4 | 2N - 5 |
| 1 brons | N - 1 | N - 3 | 2N - 4 |

### B-Structuur Bepalen

| Conditie | B-structuur | Voorbeeld |
|----------|-------------|-----------|
| V1 <= V2 | SAMEN (geen suffix) | N=12, 24, 48 |
| V1 > V2 | DUBBEL met (1)/(2) | N=16, 32, 64 |

**Let op exacte machten van 2:**
- N=16: V1=0, maar A-1/8 heeft 8 verliezers, A-1/4 heeft 4 → DUBBEL
- N=32: V1=0, maar A-1/16 heeft 16 verliezers, A-1/8 heeft 8 → DUBBEL

Zie [FORMULES.md](./FORMULES.md) voor de correcte berekening.

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

- **Standaard**: `b_mat_id = mat_id` (beide groepen op zelfde mat)
- **Split**: Sleep B-chip in zaaloverzicht naar andere mat → `b_mat_id` wordt geüpdatet
- **Mat interface**: Toont alleen relevante groep (`groep_filter` = A, B of null)
- **Afronden**: Pas na ALLE wedstrijden (A+B) klaar, broadcast naar beide mats
- **Zaaloverzicht**: Eliminatie poule met split toont 2 chips: `#N - A` en `#N - B`

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
| **Eliminatie** | >0 | Ja (geheel) | Ja (geheel) | Ja |
| **Eliminatie** | 0 | Nee | Nee | Ja* |
| **Kruisfinale** | virtueel | Ja | Ja | Ja |

*\* Lege poules bij vaste gewichtsklassen blijven op wedstrijddag zichtbaar voor overpoulen*

**Eliminatie A/B split** (aparte mat) → alleen op wedstrijddag, niet in blokverdeling/zaaloverzicht.

### Filter logica

| Locatie | Filter | Bestand |
|---------|--------|---------|
| Service (data) | `judokas > 1 \|\| type === 'kruisfinale'` | `BlokMatVerdelingService:1025` |
| Zaaloverzicht chips | `judokas > 1 \|\| type === 'kruisfinale'` | `zaaloverzicht.blade.php:63` |
| Zaaloverzicht matten | `judokas > 1 \|\| type === 'kruisfinale'` | `zaaloverzicht.blade.php:199` |
| Poule.updateStatistieken | Bewaar virtueel count alleen voor `kruisfinale` | `Poule.php:222` |

## Gerelateerde Bestanden

- `app/Services/EliminatieService.php` - Business logic
- `resources/views/pages/poule/interface.blade.php` - UI rendering
- `database/migrations/*_eliminatie_*.php` - Schema

## Changelog

Zie [../../CHANGELOG.md](../../CHANGELOG.md) voor wijzigingsgeschiedenis.
