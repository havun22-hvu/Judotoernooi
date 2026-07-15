---
title: Plaatsbepaling in poule (standings)
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Plaatsbepaling in poule (standings)

> Onderdeel van [Mat Wedstrijd Selectie](../MAT-WEDSTRIJD-SELECTIE.md).

## Plaatsbepaling in Poule (Standings)

### Punten Systeem

| Uitslag | WP (Wedstrijd Punten) | JP (Judo Punten) |
|---------|----------------------|------------------|
| Winst | 2 | Score van wedstrijd |
| Gelijkspel | 1 | Score van wedstrijd |
| Verlies | 0 | Score van wedstrijd |

### Plaatsbepaling Regels (in volgorde)

1. **Hoogste WP** (wedstrijd punten)
2. **Hoogste JP** (judo punten) - bij gelijke WP
3. **Onderling resultaat** (head-to-head) - bij gelijke WP én JP:
   - 2 judoka's: winnaar van onderlinge wedstrijd staat hoger
   - 3+ judoka's: alleen als één judoka van ALLE anderen in groep heeft gewonnen
4. **Gedeelde positie** (barrage nodig) - bij cirkel-resultaat (A→B, B→C, C→A)

### Afwezige Judoka's

Judoka's met **0 WP én 0 JP** worden **niet** in de uitslag getoond.
Dit zijn afwezige judoka's die geen wedstrijden hebben gespeeld.

### Voorbeeld Cirkel-Resultaat (Barrage)

```
Judoka A wint van B (A→B)
Judoka B wint van C (B→C)
Judoka C wint van A (C→A)

Resultaat: A, B en C krijgen allemaal dezelfde positie
→ Barrage nodig om winnaar te bepalen
```

### Code Locatie

- `getPlaats()` functie in `_content.blade.php` (mat interface)
- `heeftBarrageNodig()` functie voor detectie cirkel-resultaat
