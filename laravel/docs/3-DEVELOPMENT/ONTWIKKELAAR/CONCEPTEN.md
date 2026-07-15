---
title: Belangrijke concepten: judoka code, poules, blokverdeling
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Belangrijke concepten: judoka code, poules, blokverdeling

> Onderdeel van [Ontwikkelaar Gids](../ONTWIKKELAAR.md).

## Belangrijke Concepten

### Judoka Code

Unieke code voor poule-indeling:

```
LLGGBG
LL = Leeftijdscode (08, 10, 12, 15, 18, 21)
GG = Gewichtscode (20, 23, 26, ...)
B  = Bandcode (0-6)
G  = Geslacht (M/V)

Voorbeeld: "123445M" = B-pupillen, -34kg, oranje band, man
```

### Poule Verdeling Algoritme

1. Groepeer judoka's op leeftijd + gewicht + (geslacht bij -15+)
2. Per groep: verdeel in optimale poules (target: 5)
3. Vermijd poules van 1-2 (penalty score)
4. Balanceer grootte over poules

### Wedstrijd Schema

Optimale volgorde om rust te geven:

- **3 judoka's**: Dubbele ronde (6 wedstrijden)
- **4 judoka's**: 1-2, 3-4, 1-3, 2-4, 1-4, 2-3
- **5+ judoka's**: Round-robin

### Blok Verdeling

De blokverdeling heeft twee doelen:
1. **Gelijkmatige verdeling** - evenveel wedstrijden per blok
2. **Aansluiting gewichten** - opeenvolgende gewichtsklassen in zelfde/aansluitende blokken

**HARDE LIMIET: 25% afwijking**
- Het algoritme mag NOOIT een blok meer dan 25% boven het gewenste aantal wedstrijden plaatsen
- Bij overschrijding wordt het blok overgeslagen
- Varianten die de limiet overschrijden worden als ongeldig verworpen
- Hogere afwijking kan alleen handmatig door de organisator (via drag & drop)

**Waarom aansluiting belangrijk is:**
Bij overpoelen gaat een te zware judoka naar een zwaardere gewichtsklasse. Die klasse moet in hetzelfde of volgend blok zitten, anders moet de judoka lang wachten.

**Aansluiting regels:**
| Overgang | Score | Uitleg |
|----------|-------|--------|
| Zelfde blok (0) | Perfect | Ideaal |
| +1 blok | Perfect | Ook ideaal, volgend blok |
| +2 blokken | Acceptabel | Minder goed maar werkbaar |
| -1 blok (terug) | Slecht | Blok is al geweest! |
| +3+ blokken | Slecht | Te lang wachten |

**Service:** `BlokMatVerdelingService`

```php
// Genereer 5 varianten met verschillende verdelingen
$varianten = $service->genereerVarianten($toernooi, $verdelingGewicht, $aansluitingGewicht);

// Pas gekozen variant toe op database
$service->pasVariantToe($toernooi, $variant['toewijzingen']);
```

**Balans slider (0-100):**
- Eén slider die balans bepaalt tussen verdeling en aansluiting
- `balans = 0`: 100% verdeling, 0% aansluiting (perfecte spreiding)
- `balans = 100`: 0% verdeling, 100% aansluiting (gewichten samen)
- Controller berekent: `verdelingGewicht = 100 - balans`, `aansluitingGewicht = balans`

**Scores per variant:**
- `max_afwijking_pct` - grootste percentage afwijking van gewenst (max 25%)
- `breaks` - aantal slechte overgangen (+2, -1, of +3+)
- `is_valid` - false als een blok >25% afwijkt

**JavaScript Interactiviteit (index.blade.php):**

De pagina gebruikt client-side JavaScript voor real-time updates:

```javascript
// Update bij elke wijziging (variant switch, drag & drop):
updateAllStats();

// Deze functie update:
// 1. Blok totalen en afwijking badges
// 2. Sleepvak statistieken
// 3. Overzicht panel bloktoewijzingen (blok-badge elementen)
```

**Overzicht Panel (rechts):**
- Toont per leeftijdsklasse alle gewichtscategorieën
- Bloknummer per categorie voor beoordeling aansluiting
- Update direct bij variant switch of drag & drop via `data-key` matching

