---
title: Overpoulen: afwezigen, lege poules & zoek-match
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Overpoulen: afwezigen, lege poules & zoek-match

> Onderdeel van [Classificatie & Poule Indeling](../CLASSIFICATIE.md).

### Afwezigen (BEIDE categorie types)

- Afwezigen gaan **automatisch** uit de poule
- Zichtbaar bij ℹ️ info tooltip van de poule
- NIET zichtbaar in de poule zelf

### Lege Poules op Wedstrijddag

```
┌──────────────────────────────────────────────────────────────────┐
│ LEGE POULES                                                        │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│ VASTE CATEGORIEËN: Lege poules WEL tonen                         │
│   → Voorbeeld: -36kg poule leeg → judoka uit -32kg kan erheen   │
│                                                                   │
│ DYNAMISCHE CATEGORIEËN: Lege poules NIET tonen                   │
│                                                                   │
│ ⚠️ LEGE POULES NOOIT OP MAT ZETTEN!                              │
│   • Lege poule = geen wedstrijden = niet op mat                  │
│   • Mat interface toont alleen poules met judoka's               │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

### Zoek Match (Wedstrijddag variant)

Hergebruik Zoek Match met blok-beperkingen:

| Blok situatie | Actie |
|---------------|-------|
| **Zelfde blok** | Direct in poule |
| **Ander blok (weging gesloten)** | Direct in poule |
| **Ander blok (weging open)** | Zoek Match toont waarschuwing |

