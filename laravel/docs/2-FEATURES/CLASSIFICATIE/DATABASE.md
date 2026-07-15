---
title: Database Velden
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Database Velden

> Onderdeel van [Classificatie & Poule Indeling](../CLASSIFICATIE.md).

## Database Velden

### judokas tabel

| Veld | Inhoud | Voorbeeld |
|------|--------|-----------|
| `leeftijdsklasse` | Label uit config (weergave) | "Mini's", "U11 Heren" |
| `categorie_key` | Config array key (lookup) | "minis", "u11_h" |
| `sort_categorie` | Volgorde (0, 1, 2...) | 0, 1, 2 |
| `sort_gewicht` | Gewicht in grammen | 30500 (= 30.5kg) |
| `sort_band` | Band niveau (1-7) | 3 (= oranje) |

### poules tabel

| Veld | Inhoud | Voorbeeld |
|------|--------|-----------|
| `leeftijdsklasse` | Label (weergave) | "U7", "U11 Jongens" |
| `gewichtsklasse` | Klasse of range | "-24" of "24-27kg" |
| `categorie_key` | Config array key (lookup) | "u7", "u11_h" |

### categorie_key uitleg

De `categorie_key` is de directe link naar de gewichtsklassen config:

```php
// Config in toernooien.gewichtsklassen
'u7' => ['label' => 'U7', 'max_leeftijd' => 6, ...],
'u11_h' => ['label' => 'U11 Jongens', 'max_leeftijd' => 10, ...],

// Lookup via CategorieClassifier
$config = $classifier->getConfigVoorPoule($poule);
// Gebruikt $poule->categorie_key om juiste config te vinden
```

**Belangrijk:**
- `leeftijdsklasse` = label, alleen voor weergave
- `categorie_key` = array key, voor config lookup
- Nooit zoeken op label! Labels kunnen wijzigen.

### Band Niveaus

**Python solver (0-indexed, voor constraints):**

| Band | Niveau |
|------|--------|
| wit | 0 |
| geel | 1 |
| oranje | 2 |
| groen | 3 |
| blauw | 4 |
| bruin | 5 |
| zwart | 6 |

**PHP BandHelper (1-indexed, voor sortering):**

| Band | Niveau |
|------|--------|
| wit | 1 |
| geel | 2 |
| oranje | 3 |
| groen | 4 |
| blauw | 5 |
| bruin | 6 |
| zwart | 7 |

> **Let op:** Python solver gebruikt 0-indexed (wit=0) voor constraint checking.
> PHP BandHelper gebruikt 1-indexed (wit=1) voor sort_band database veld.

---

