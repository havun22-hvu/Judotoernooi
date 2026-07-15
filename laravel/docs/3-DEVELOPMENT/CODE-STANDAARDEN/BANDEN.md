---
title: Band-kleuren (geen kyu!)
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Band-kleuren (geen kyu!)

> Onderdeel van [Code-standaarden](../CODE-STANDAARDEN.md).

## 13. Band Kleuren (GEEN kyu!)

### Volgorde beginner → expert (ENIGE BRON VAN WAARHEID: Band enum)

```
wit → geel → oranje → groen → blauw → bruin → zwart

niveau():      0      1       2        3        4        5       6   (beginner eerst)
sortNiveau():  1      2       3        4        5        6       7   (database sort_band)
enum value:    6      5       4        3        2        1       0   (expert eerst, voor filters)
```

### Opslag

**ALLEEN** lowercase kleur naam in database:
- ✓ `wit`, `geel`, `oranje`, `groen`, `blauw`, `bruin`, `zwart`
- ✗ `Geel (5e kyu)`, `5`, `5e kyu` - **NOOIT**

### Weergave (UI)

**ALLEEN** kleur naam met hoofdletter - **NOOIT kyu nummers**:
- ✓ `Wit`, `Geel`, `Groen`
- ✗ `Geel (5e kyu)`, `5e kyu` - **NOOIT**

### Code Gebruik

```php
// ✓ GOED - centrale methode voor weergave
{{ \App\Enums\Band::toKleur($judoka->band) }}

// ✓ GOED - enum voor logica
$bandEnum = Band::fromString($judoka->band);
$niveau = $bandEnum->niveau();  // 0=wit/beginner, 6=zwart/expert

// ✗ FOUT - direct tonen zonder conversie
{{ $judoka->band }}  // Kan "geel (5e kyu)" zijn als data fout is!

// ✗ FOUT - hardcoded kyu mapping
$kyu = match($band) { 'geel' => 5, 'groen' => 3, ... };
```

### Import

Bij import wordt kyu automatisch omgezet:
- Input: `"Geel (5e kyu)"` of `"5"` of `"geel"`
- Output (opslag): `"geel"`

```php
// ImportService::parseBand() doet dit automatisch
$band = Band::fromString($input);
return $band ? strtolower($band->name) : 'wit';
```

### Beschikbare Methodes (allemaal in Band enum)

| Methode | Doel | Voorbeeld |
|---------|------|-----------|
| `Band::toKleur($band)` | Weergave in UI | `"geel"` → `"Geel"` |
| `Band::fromString($str)` | String naar enum | `"geel (5e kyu)"` → `Band::GEEL` |
| `Band::getSortNiveau($str)` | Sortering (static) | `"geel"` → `2` |
| `Band::pastInFilter($band, $filter)` | Filter check | `"groen"`, `"tm_groen"` → `true` |
| `$band->niveau()` | Beginner→expert | `Band::WIT->niveau()` → `0` |
| `$band->sortNiveau()` | Database sort_band | `Band::WIT->sortNiveau()` → `1` |
| `$band->label()` | Kleur naam | `Band::GEEL->label()` → `"Geel"` |
| `$band->value` | Expert→beginner | `Band::ZWART->value` → `0` |

### Filters

```php
// "tm_groen" = beginners t/m groen (wit, geel, oranje, groen)
Band::pastInFilter('geel', 'tm_groen');   // true
Band::pastInFilter('blauw', 'tm_groen');  // false

// "vanaf_blauw" = gevorderden vanaf blauw (blauw, bruin, zwart)
Band::pastInFilter('bruin', 'vanaf_blauw'); // true
Band::pastInFilter('groen', 'vanaf_blauw'); // false
```

### BandHelper (DEPRECATED)

`BandHelper` is deprecated. Gebruik `Band` enum direct:

```php
// ✗ OUD
BandHelper::getSortNiveau($band);
BandHelper::pastInFilter($band, $filter);

// ✓ NIEUW
Band::getSortNiveau($band);
Band::pastInFilter($band, $filter);
```

---

