---
title: Optimale wedstrijdvolgorde per poulegrootte
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Optimale wedstrijdvolgorde per poulegrootte

> Onderdeel van [Wedstrijdschema Systeem](../WEDSTRIJDSCHEMA.md).

## Optimale Wedstrijdvolgorde

Om judoka's voldoende rust te geven, worden wedstrijden in een optimale volgorde gepland. Elke judoka krijgt minimaal één wedstrijd rust tussen zijn/haar wedstrijden.

### 2 Judoka's (Configureerbaar)

| Modus | Wedstrijden | Beschrijving |
|-------|-------------|--------------|
| Standaard | 2 | 1x tegen elkaar (heen + terug) |
| Best of Three | 3 | 3x tegen elkaar |

**Instelling:** Toernooi → Instellingen → "Best of Three bij 2 deelnemers"

```
Standaard:
Wed 1: 1 vs 2
Wed 2: 2 vs 1

Best of Three:
Wed 1: 1 vs 2
Wed 2: 2 vs 1
Wed 3: 1 vs 2
```

### 3 Judoka's (Dubbele Poule)
```
Wed 1: 1 vs 2    Wed 4: 1 vs 2  (herhaling)
Wed 2: 1 vs 3    Wed 5: 1 vs 3
Wed 3: 2 vs 3    Wed 6: 2 vs 3
```

### 4 Judoka's
```
Wed 1: 1 vs 2    (3 en 4 rusten)
Wed 2: 3 vs 4    (1 en 2 rusten)
Wed 3: 2 vs 3    (1 en 4 rusten)
Wed 4: 1 vs 4    (2 en 3 rusten)
Wed 5: 2 vs 4    (1 en 3 rusten)
Wed 6: 1 vs 3    (2 en 4 rusten)
```

### 5 Judoka's
```
Wed 1:  1 vs 2    Wed 6:  1 vs 3
Wed 2:  3 vs 4    Wed 7:  2 vs 4
Wed 3:  1 vs 5    Wed 8:  3 vs 5
Wed 4:  2 vs 3    Wed 9:  1 vs 4
Wed 5:  4 vs 5    Wed 10: 2 vs 5
```

### 6 Judoka's
```
Wed 1:  1 vs 2    Wed 6:  4 vs 6    Wed 11: 4 vs 5
Wed 2:  3 vs 4    Wed 7:  3 vs 5    Wed 12: 3 vs 6
Wed 3:  5 vs 6    Wed 8:  2 vs 4    Wed 13: 1 vs 4
Wed 4:  1 vs 3    Wed 9:  1 vs 6    Wed 14: 2 vs 6
Wed 5:  2 vs 5    Wed 10: 2 vs 3    Wed 15: 1 vs 5
```

### 7+ Judoka's
Voor 7 of meer judoka's wordt het "Circle Method" round-robin algoritme gebruikt. Dit garandeert een eerlijke verdeling waarbij elke judoka tegen iedereen vecht.

