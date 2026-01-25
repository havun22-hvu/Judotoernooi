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

## Gerelateerde Bestanden

- `app/Services/EliminatieService.php` - Business logic
- `resources/views/pages/poule/interface.blade.php` - UI rendering
- `database/migrations/*_eliminatie_*.php` - Schema

## Changelog

Zie [../../CHANGELOG.md](../../CHANGELOG.md) voor wijzigingsgeschiedenis.
